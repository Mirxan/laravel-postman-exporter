<?php

namespace Mirxan\PostmanExporter\Route;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Route;
use ReflectionMethod;
use ReflectionNamedType;
use Illuminate\Http\Request;
use Mirxan\PostmanExporter\Helpers\ResponseExtractor;

class PostmanRouteParser
{
    public function getRoutes(): array
    {
        $filtered = [];
        $includedMiddlewares = config('postman-exporter.included_middlewares', []);

        foreach (Route::getRoutes() as $route) {
            if (!in_array('GET', $route->methods()) && !in_array('POST', $route->methods())) {
                continue;
            }

            if (!empty($includedMiddlewares)) {
                $routeMiddlewares = method_exists($route, 'gatherMiddleware')
                    ? $route->gatherMiddleware()
                    : $route->middleware();

                if (empty(array_intersect($includedMiddlewares, $routeMiddlewares))) {
                    continue;
                }
            }

            $action = $route->getActionName();
            if (!Str::contains($action, '@')) continue;

            [$controller, $method] = explode('@', $action);

            try {
                $reflection = new ReflectionMethod($controller, $method);
                $doc = $reflection->getDocComment() ?: '';

                $skip = false;
                foreach ($reflection->getParameters() as $param) {
                    $type = $param->getType();
                    if (
                        !$type ||
                        ($type instanceof ReflectionNamedType && $type->getName() === Request::class)
                    ) {
                        $skip = true;
                        break;
                    }
                }

                if ($skip) {
                    continue;
                }

                $filtered[] = [
                    'uri' => $route->uri(),
                    'method' => $route->methods()[0],
                    'controller' => $controller,
                    'method_name' => $method,
                    'doc' => $doc,
                    'response' => ResponseExtractor::guess($controller, $method),
                ];
            } catch (\Throwable $e) {
                continue;
            }
        }

        return $filtered;
    }

}
