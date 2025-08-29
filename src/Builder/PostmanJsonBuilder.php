<?php

namespace Mirxan\PostmanExporter\Builder;

use Illuminate\Routing\Route;
use Illuminate\Foundation\Http\FormRequest;
use Mirxan\PostmanExporter\Helpers\ResponseExtractor;
use ReflectionMethod;
use ReflectionClass;
use Throwable;

class PostmanJsonBuilder
{
    public function build(array $routes): string
    {
        $folders = [];
        $config = $this->loadConfiguration();

        foreach ($routes as $route) {
            $routeItem = $this->buildRouteItem($route, $config);
            $this->addRouteToFolders($folders, $route, $routeItem, $config);
        }

        return $this->generatePostmanCollection($folders);
    }

    protected function loadConfiguration(): array
    {
        return [
            'folders' => config('postman-exporter.folders', []),
            'defaultParams' => config('postman-exporter.params_value', []),
            'contentType' => config('postman-exporter.content_type', 'form-data'),
            'baseUrlKey' => config('postman-exporter.base_url_key'),
            'collectionName' => config('postman-exporter.collection_name'),
            'collectionSchema' => config('postman-exporter.collection_schema'),
        ];
    }

    protected function buildRouteItem(array $route, array $config): array
    {
        $folderConfig = $this->getFolderConfig($route, $config['folders']);
        $headers = $this->buildHeaders($route, $folderConfig);
        $auth = $this->buildAuth($route, $folderConfig);
        
        $isNoAuth = $this->extractNoAuth($route['doc']);

        if ($isNoAuth) {
            $auth = null;
            $headers = array_filter($headers, function ($header) {
                return !in_array($header['key'], ['Authorization', 'X-Auth-Token']);        
            });
        }

        $queryParams = $this->buildQueryParams($route);
        
        $request = $this->buildRequest($route, $headers, $queryParams, $config['baseUrlKey']);
        
        if ($this->shouldHaveBody($route['method'])) {
            $requestBody = $this->buildRequestBody($route, $config);
            if (!empty($requestBody)) {
                $request['body'] = $this->formatBody($requestBody, $config['contentType']);
            }
        }
        
        $response = $this->buildResponse($route);
        
        $item = [
            'name' => $this->getRouteName($route),
            'request' => $request,
            'response' => $response,
        ];
        
        $item['request']['auth'] = $auth ? $this->formatAuth($auth) : ['type' => 'noauth'];
        
        $events = $this->buildEvents($folderConfig);
        if (!empty($events)) {
            $item['event'] = $events;
        }
        
        return $item;
    }

    protected function getFolderConfig(array $route, array $folderConfigs): ?array
    {
        $folderKey = $this->detectFolderKey($route);

        if ($folderKey && isset($folderConfigs[$folderKey])) {
            return $folderConfigs[$folderKey];
        }

        return $folderConfigs['default'] ?? null;
    }

    protected function buildHeaders(array $route, ?array $folderConfig): array
    {
        $routeHeaders = $this->extractHeaders($route['doc']);

        $globalHeaders = ($folderConfig['isGlobal'] ?? false) ? ($folderConfig['headers'] ?? []) : [];
        
        return array_merge($routeHeaders, $this->formatHeaders($globalHeaders));
    }

    protected function buildAuth(array $route, ?array $folderConfig): ?array
    {
        $routeAuth = $this->extractAuth($route['doc']);

        $folderAuth = ($folderConfig['isGlobal'] ?? false) ? ($folderConfig['auth'] ?? null) : null;

        return $routeAuth ?? $folderAuth;
    }

    protected function buildQueryParams(array $route): array
    {
        $queryParams = $this->extractQuery($route['doc']);
        
        if (!empty($queryParams)) {
            foreach ($queryParams as &$param) {
                if (str_starts_with($param['value'], ':')) {
                    $param['value'] = '{' . ltrim($param['value'], ':') . '}';
                }
            }
        }
        
        return $queryParams;
    }

    protected function buildRequest(array $route, array $headers, array $queryParams, string $baseUrlKey): array
    {
        return [
            'method' => $route['method'],
            'header' => $headers,
            'url' => [
                'raw' => '{{' . $baseUrlKey . '}}/' . $this->convertBracesToColons($route['uri']),
                'host' => ['{{' . $baseUrlKey . '}}'],
                'path' => $this->convertPathSegments(explode('/', $route['uri'])),
                'query' => $queryParams,
            ],
            'description' => $this->extractTag($route['doc'], 'description'),
        ];
    }

    protected function buildRequestBody(array $route, array $config): array
    {
        $requestClass = $this->extractRequestClass($route);
        
        if ($requestClass && class_exists($requestClass)) {
            return $this->getParamsFromRequestClass($requestClass, $route, $config['defaultParams']);
        }
        
        return $this->getParamsFromDocblock($route, $config['defaultParams']);
    }

    protected function getParamsFromRequestClass(string $requestClass, array $route, array $defaultParams): array
    {
        try {
            $rules = $this->extractRulesFromRequestClass($requestClass, $route);
            
            if (!empty($rules)) {
                $params = [];
                foreach ($rules as $key => $rule) {
                    $params[$key] = $this->inferTypeNameFromRules($rule);
                }
                return $params;
            }
        } catch (Throwable $e) {
            return [
                'error' => 'Could not resolve request rules: ' . $e->getMessage(),
                'request_class' => $requestClass
            ];
        }
        
        return $defaultParams;
    }


    protected function getParamsFromDocblock(array $route, array $defaultParams): array
    {
        $parsedBody = json_decode($this->extractTag($route['doc'], 'body'), true);
        return $parsedBody ?? $defaultParams;
    }

    protected function buildResponse(array $route): array
    {
        $response = $this->extractResponse($route['doc']);

        if (!$response) {
            try {
                $controller = $route['controller'];
                $method = $route['method_name'];

                $response = ResponseExtractor::guess($controller, $method);
            } catch (\Throwable $e) {
                $response = null;
            }
        }

        if (!$response) {
            return [];
        }

        return [[
            'name' => 'Example',
            'status' => 'OK',
            'code' => 200,
            'body' => $response,
        ]];
    }

    protected function buildEvents(?array $folderConfig): array
    {
        if (!($folderConfig['isGlobal'] ?? false)) {
            return [];
        }
        
        $events = [];
        
        if (!empty($folderConfig['prerequest'])) {
            $events[] = [
                'listen' => 'prerequest',
                'script' => [
                    'type' => 'text/javascript',
                    'exec' => explode("\n", $folderConfig['prerequest']),
                ],
            ];
        }
        
        if (!empty($folderConfig['test'])) {
            $events[] = [
                'listen' => 'test',
                'script' => [
                    'type' => 'text/javascript',
                    'exec' => explode("\n", $folderConfig['test']),
                ],
            ];
        }
        
        return $events;
    }

    protected function shouldHaveBody(string $method): bool
    {
        return in_array($method, ['POST', 'PUT', 'PATCH', 'GET']);
    }

    protected function addRouteToFolders(array &$folders, array $route, array $item, array $config): void
    {
        $folderKey = $this->detectFolderKey($route);
        $folderConfig = $this->getFolderConfig($route, $config['folders']);

        if ($folderConfig) {
            $keyToUse = $folderKey ?? 'default';
            $level = $folderConfig['level'] ?? 1;

            $this->assignToNestedFolder($folders, $route, $item, $keyToUse, $level, $folderConfig);
        } else {
            $folders[] = $item;
        }
    }

    protected function generatePostmanCollection(array $folders): string
    {
        $config = $this->loadConfiguration();
        
        return json_encode([
            'info' => [
                'name' => $config['collectionName'],
                'schema' => $config['collectionSchema'],
            ],
            'item' => array_values($folders),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    protected function extractRulesFromRequestClass(string $requestClass, array $route): array
    {
        $rules = $this->extractRulesUsingReflection($requestClass);
        
        if (!empty($rules)) {
            return $rules;
        }

        try {
            $requestInstance = $this->createRequestInstance($requestClass, $route);
            
            if ($requestInstance instanceof FormRequest && method_exists($requestInstance, 'rules')) {
                $rules = $requestInstance->rules();
                
                if (!empty($rules)) {
                    return $rules;
                }
            }
        } catch (Throwable $e) {}

        return $this->parseRulesFromSource($requestClass);
    }

    protected function extractRulesUsingReflection(string $requestClass): array
    {
        $reflection = new ReflectionClass($requestClass);
        
        if (!$reflection->hasMethod('rules')) {
            return [];
        }
        
        $reflection->getMethod('rules');
        
        $filename = $reflection->getFileName();
        if ($filename && file_exists($filename)) {
            $source = file_get_contents($filename);
            $methodSource = $this->extractMethodSource($source, 'rules');
            
            if ($methodSource) {
                return $this->parseRulesFromMethodSource($methodSource);
            }
        }
        
        return [];
    }

    protected function parseRulesFromSource(string $requestClass): array
    {
        $reflection = new ReflectionClass($requestClass);
        $filename = $reflection->getFileName();
        
        if (!$filename || !file_exists($filename)) {
            return [];
        }
        
        $source = file_get_contents($filename);
        $methodSource = $this->extractMethodSource($source, 'rules');
        
        if ($methodSource) {
            return $this->parseRulesFromMethodSource($methodSource);
        }
        
        return [];
    }

    protected function extractMethodSource(string $source, string $methodName): ?string
    {
        $pattern = '/function\s+' . $methodName . '\s*\([^)]*\)\s*\{(.*?)\}/s';
        
        if (preg_match($pattern, $source, $matches)) {
            return $matches[1];
        }
        
        return null;
    }

    protected function parseRulesFromMethodSource(string $methodSource): array
    {
        $rules = [];
        
        if (preg_match('/return\s*\[(.*?)\];/s', $methodSource, $matches)) {
            $arrayContent = $matches[1];
            
            if (preg_match_all('/[\'"]([^\'"]+)[\'"]\s*=>\s*[\'"]([^\'"]+)[\'"]/', $arrayContent, $ruleMatches, PREG_SET_ORDER)) {
                foreach ($ruleMatches as $match) {
                    $rules[$match[1]] = $match[2];
                }
            }
        }
        
        return $rules;
    }

    protected function createRequestInstance(string $requestClass, array $route): FormRequest
    {
        try {
            $request = \Illuminate\Http\Request::create($route['uri'], $route['method']);
            
            $routeInstance = new Route(
                [$route['method']], 
                $route['uri'], 
                [
                    'uses' => $route['controller'] . '@' . $route['method_name'],
                    'controller' => $route['controller'] . '@' . $route['method_name']
                ]
            );
            
            $request->setRouteResolver(fn () => $routeInstance);
            
            /** @var FormRequest $requestInstance */
            $requestInstance = new $requestClass();
            
            $requestInstance->setContainer(app())
                            ->setRedirector(app('redirect'))
                            ->setJson($request->json())
                            ->setUserResolver(fn () => null)
                            ->setRouteResolver(fn () => $routeInstance);
            
            $requestInstance->initialize(
                $request->query->all(),
                $request->request->all(),
                $request->attributes->all(),
                $request->cookies->all(),
                $request->files->all(),
                $request->server->all()
            );

            $requestInstance->setMethod($route['method']);
            
            return $requestInstance;
        } catch (Throwable $e) {
            throw new \Exception('Could not create FormRequest instance: ' . $requestClass . '. Error: ' . $e->getMessage());
        }
    }

    protected function extractRequestClass(array $route): ?string
    {
        $controller = $route['controller'];
        $methodName = $route['method_name'];

        if (!class_exists($controller)) {
            return null;
        }

        $reflection = new ReflectionMethod($controller, $methodName);
        foreach ($reflection->getParameters() as $param) {
            $type = $param->getType();

            if ($type && class_exists($type->getName())) {
                $paramClass = $type->getName();

                if (is_subclass_of($paramClass, FormRequest::class)) {
                    return $paramClass;
                }
            }
        }

        $doc = $route['doc'];

        if (preg_match('/@request\s+([\w\\\\]+)/', $doc, $matches)) {
            return $matches[1];
        }

        if (preg_match('/@param\s+([\w\\\\]+Request)\s+/', $doc, $matches)) {
            return $matches[1];
        }

        return null;
    }

    protected function formatBody(array $params, string $contentType): array
    {
        switch ($contentType) {
            case 'json':
                return [
                    'mode' => 'raw',
                    'raw' => json_encode($params, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                    'options' => ['raw' => ['language' => 'json']],
                ];
            case 'form-data':
                return [
                    'mode' => 'formdata',
                    'formdata' => $this->formatFormData($params),
                ];
            case 'urlencoded':
                return [
                    'mode' => 'urlencoded',
                    'urlencoded' => $this->formatFormData($params),
                ];
            case 'multipart':
                return [
                    'mode' => 'formdata',
                    'formdata' => $this->formatMultipart($params),
                ];
            default:
                return [
                    'mode' => 'raw',
                    'raw' => json_encode($params, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                ];
        }
    }

    protected function getRouteName(array $route): string
    {
        if (isset($route['action']['as'])) {
            return $route['action']['as'];
        }
        
        $description = $this->extractTag($route['doc'], 'description');
        if ($description) {
            return $description;
        }
        
        return $route['uri'];
    }

    protected function convertBracesToColons(string $uri): string
    {
        return preg_replace('/\{(\w+)\}/', ':$1', $uri);
    }

    protected function convertPathSegments(array $segments): array
    {
        return array_map(
            fn($segment) => preg_replace('/\{(\w+)\}/', ':$1', $segment),
            $segments
        );
    }

    protected function assignToNestedFolder(
        array &$folders,
        array $route,
        array $item,
        string $folderKey,
        int $level,
        array $folderConfig
    ) {
        $current = &$folders;
        $uriSegments = explode('/', $route['uri']);
        $depth = min($level, count($uriSegments));

        for ($i = 0; $i < $depth; $i++) {
            $segment = $uriSegments[$i] ?? $folderKey;
            $segment = ucfirst($segment);
            $found = false;

            foreach ($current as &$existing) {
                if (isset($existing['name']) && $existing['name'] === $segment) {
                    $current = &$existing['item'];
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                $newFolder = [
                    'name' => $segment,
                    'item' => [],
                ];

                if ($i === 0 && isset($folderConfig['auth'])) {
                    $newFolder['auth'] = $this->formatAuth($folderConfig['auth']);
                }

                $current[] = $newFolder;
                $current = &$current[array_key_last($current)]['item'];
            }
        }

        $current[] = $item;
    }

    protected function detectFolderKey(array $route): ?string
    {
        $uri = $route['uri'] ?? '';

        foreach (array_keys(config('postman-exporter.folders')) as $folderKey) {
            if ($folderKey !== 'default' && str_starts_with($uri, $folderKey)) {
                return $folderKey;
            }
        }

        return null;
    }

    protected function extractNoAuth(string $doc): bool
    {
        return preg_match('/@no-auth/', $doc) === 1;
    }

    protected function extractAuth(string $doc): ?array
    {
        preg_match('/@auth\s+(.+)/', $doc, $matches);
        if (!empty($matches[1])) {
            $auth = json_decode($matches[1], true);
            return is_array($auth) ? $auth : null;
        }
        return null;
    }

    protected function formatAuth(array $authConfig): array
    {
        $formatted = ['type' => $authConfig['type']];
        $formatted[$authConfig['type']] = $authConfig[$authConfig['type']] ?? [];
        return $formatted;
    }

    protected function formatHeaders(array $headers): array
    {
        return array_map(function ($value, $key) {
            return ['key' => trim($key, ''), 'value' => trim($value, '')];
        }, $headers, array_keys($headers));
    }

    protected function extractTag(string $doc, string $tag): ?string
    {
        preg_match('/@' . $tag . '\s+(.*)/', $doc, $matches);
        return $matches[1] ?? null;
    }

    protected function extractHeaders(string $doc): array
    {
        preg_match_all('/@header\s+(.+?):\s*(.+)/', $doc, $matches, PREG_SET_ORDER);
        return array_map(fn($match) => ['key' => $match[1], 'value' => $match[2]], $matches);
    }

    protected function extractQuery(string $doc): array
    {
        preg_match_all('/@query\s+(.+?)=(.+)/', $doc, $matches, PREG_SET_ORDER);
        return array_map(fn($m) => ['key' => $m[1], 'value' => $m[2]], $matches);
    }

    protected function extractFormParams(string $doc): array
    {
        preg_match_all('/@form\s+(.+?)=(.+)/', $doc, $matches, PREG_SET_ORDER);
        return array_reduce($matches, function ($carry, $match) {
            $carry[$match[1]] = $match[2];
            return $carry;
        }, []);
    }

    protected function extractMultipartParams(string $doc): array
    {
        preg_match_all('/@multipart\s+(.+?)=(.+)/', $doc, $matches, PREG_SET_ORDER);
        return array_reduce($matches, function ($carry, $match) {
            $carry[$match[1]] = $match[2];
            return $carry;
        }, []);
    }

    protected function extractResponse(string $doc): ?string
    {
        preg_match('/@response\s+(.*)/', $doc, $matches);
        return $matches[1] ?? null;
    }

    protected function formatFormData(array $params): array
    {
        return array_map(fn($key, $value) => [
            'key' => $key,
            'value' => $value,
            'type' => 'text',
        ], array_keys($params), $params);
    }

    protected function formatMultipart(array $params): array
    {
        return array_map(function ($key, $value) {
            if (file_exists($value)) {
                return [
                    'key' => $key,
                    'src' => [$value],
                    'type' => 'file',
                ];
            }
            return [
                'key' => $key,
                'value' => $value,
                'type' => 'text',
            ];
        }, array_keys($params), $params);
    }

    protected function inferTypeNameFromRules($rules): string
    {
        if (is_string($rules)) {
            $rules = explode('|', $rules);
        }

        foreach ($rules as $rule) {
            $rule = strtolower(trim($rule));

            if (in_array($rule, ['string', 'email'])) {
                return '<string>';
            }
            if (in_array($rule, ['integer', 'int', 'numeric'])) {
                return '<integer>';
            }
            if ($rule === 'boolean') {
                return '<boolean>';
            }
            if ($rule === 'array') {
                return '<array>';
            }
            if ($rule === 'file') {
                return '<file>';
            }
            if ($rule === 'date') {
                return '<date>';
            }
            if ($rule === 'uuid') {
                return '<uuid>';
            }
        }

        return '<string>';
    }
}