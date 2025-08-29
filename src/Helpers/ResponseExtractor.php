<?php

namespace Mirxan\PostmanExporter\Helpers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use ReflectionMethod;

class ResponseExtractor
{
    public static function guess($controller, $method)
    {
        try {
            if (!class_exists($controller)) {
                return null;
            }

            $instance   = app()->make($controller);
            $reflection = new ReflectionMethod($controller, $method);

            $parameters = [];
            foreach ($reflection->getParameters() as $param) {
                $type = $param->getType()?->getName();

                if ($type === Request::class || (is_string($type) && is_subclass_of($type, Request::class))) {
                    $parameters[] = self::fakeRequest();
                    continue;
                }

                if ($type && class_exists($type) && is_subclass_of($type, Model::class)) {
                    $parameters[] = new $type;
                    continue;
                }

                if ($param->isDefaultValueAvailable()) {
                    $parameters[] = $param->getDefaultValue();
                    continue;
                }

                $parameters[] = null;
            }

            $response = $reflection->invokeArgs($instance, $parameters);

            if ($response instanceof JsonResource && !($response instanceof ResourceCollection) && !($response instanceof AnonymousResourceCollection)) {
                $data = $response->toArray(self::fakeRequest());
                $keys = self::extractAllKeys($data);
                return self::pretty(array_combine($keys, $keys));
            }

            if ($response instanceof ResourceCollection || $response instanceof AnonymousResourceCollection) {
                $resourceClass = $response->collects ?? null;

                if (!$resourceClass && ($response->collection ?? collect())->isNotEmpty()) {
                    $first = $response->collection->first();

                    if ($first instanceof JsonResource) {
                        $data = $first->toArray(self::fakeRequest());
                        $keys = self::extractAllKeys($data);
                        return self::pretty(['data' => [array_combine($keys, $keys)]]);
                    }

                    if ($first instanceof Model) {
                        $keys = $first->getFillable();
                        if (empty($keys)) {
                            $keys = array_keys($first->getAttributes());
                        }
                        return self::pretty(['data' => [array_combine($keys, $keys)]]);
                    }
                }

                if ($resourceClass && is_subclass_of($resourceClass, JsonResource::class)) {
                    $fakeModel = new class {
                        public function __get($name) { return $name; }
                        public function __isset($name) { return true; }
                        public function relationLoaded($name) { return false; }
                        public function toArray() { return []; }
                    };

                    $resource = new $resourceClass($fakeModel);
                    $data = $resource->toArray(self::fakeRequest());
                    $keys = self::extractAllKeys($data);
                    $item = array_combine($keys, $keys);

                    $inner = $response->resource ?? null;
                    $pagination = [];
                    if ($inner instanceof LengthAwarePaginator || $inner instanceof Paginator) {
                        $pagination = self::fakePagination($inner);
                    }

                    $result = ['data' => [$item]];
                    if (!empty($pagination)) {
                        $result = array_merge($result, $pagination);
                    }

                    return self::pretty($result);
                }

                $data = $response->toArray(self::fakeRequest());
                $keys = self::extractAllKeys($data);
                if (isset($data['data']) && is_array($data['data']) && isset($data['data'][0])) {
                    $firstSample = $data['data'][0];
                    $k = self::extractAllKeys($firstSample);
                    return self::pretty(['data' => [array_combine($k, $k)]]);
                }
                return self::pretty(array_combine($keys, $keys));
            }

            if ($response instanceof LengthAwarePaginator) {
                $items = $response->items();
                $sample = $items[0] ?? null;
                $sampleData = self::getDataSample($sample);
                return self::pretty([
                    'data' => [$sampleData],
                    ...self::fakePagination($response),
                ]);
            }

            if ($response instanceof Paginator) {
                $items = $response->items();
                $sample = $items[0] ?? null;
                $sampleData = self::getDataSample($sample);
                return self::pretty([
                    'data' => [$sampleData],
                    ...self::fakePagination($response),
                ]);
            }

            if ($response instanceof Model) {
                $keys = $response->getFillable();
                if (empty($keys)) { $keys = array_keys($response->getAttributes()); }
                return self::pretty(array_combine($keys, $keys));
            }

            if (is_array($response)) {
                $keys = self::extractAllKeys($response);
                return self::pretty(array_combine($keys, $keys));
            }

            if (method_exists($response, 'toArray')) {
                $data = $response->toArray();
                if (is_array($data)) {
                    $keys = self::extractAllKeys($data);
                    return self::pretty(array_combine($keys, $keys));
                }
            }

            return null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private static function fakeRequest(): Request
    {
        return Request::create('/', 'GET');
    }

    private static function extractAllKeys($data): array
    {
        $keys = [];

        if ($data instanceof \Illuminate\Support\Collection) {
            $data = $data->toArray();
        }

        if (is_array($data)) {
            foreach ($data as $key => $value) {
                if (!is_string($key) || $key === '') continue;
                $keys[] = $key;
                $nested = self::extractAllKeys($value);
                foreach ($nested as $n) {
                    $keys[] = $key . '.' . $n;
                }
            }
        } elseif (is_object($data)) {
            $array = (array) $data;
            foreach ($array as $key => $value) {
                $cleanKey = preg_replace('/^.*\0.*\0/', '', $key);
                if ($cleanKey === '') continue;
                $keys[] = $cleanKey;
                $nested = self::extractAllKeys($value);
                foreach ($nested as $n) {
                    $keys[] = $cleanKey . '.' . $n;
                }
            }
        }

        return array_values(array_unique(array_filter($keys)));
    }

    private static function getDataSample($item): array
    {
        if ($item instanceof Model) {
            $keys = $item->getFillable();
            if (empty($keys)) { $keys = array_keys($item->getAttributes()); }
            return array_combine($keys ?: ['id'], $keys ?: ['id']);
        }

        if (is_array($item)) {
            $keys = self::extractAllKeys($item);
            return array_combine($keys ?: ['example'], $keys ?: ['example']);
        }

        return ['example' => 'example'];
    }

    private static function fakePagination($resource): array
    {
        if ($resource instanceof LengthAwarePaginator) {
            return [
                'meta' => [
                    'current_page' => 1,
                    'last_page'    => 10,
                    'per_page'     => $resource->perPage(),
                    'total'        => 150,
                ],
                'links' => [
                    'first' => '{baseUrl}/?page=1',
                    'last'  => '{baseUrl}/?page=10',
                    'prev'  => null,
                    'next'  => '{baseUrl}/?page=2',
                ],
            ];
        }

        if ($resource instanceof Paginator) {
            return [
                'meta' => [
                    'current_page' => 1,
                    'per_page'     => $resource->perPage(),
                ],
                'links' => [
                    'prev_page_url' => null,
                    'next_page_url' => '{baseUrl}/?page=2',
                ],
            ];
        }

        return [];
    }

    private static function pretty(array $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}
