<?php

namespace Mirxan\PostmanExporter\Helpers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use ReflectionMethod;
use ReflectionNamedType;

class ResponseExtractor
{
    public static function guess($controller, $method)
    {
        try {
            $instance = app()->make($controller);
            $reflection = new ReflectionMethod($controller, $method);

            $parameters = [];
            foreach ($reflection->getParameters() as $param) {
                $type = $param->getType();

                if ($type instanceof ReflectionNamedType) {
                    $typeName = $type->getName();

                    if ($typeName === Request::class) {
                        $parameters[] = app(Request::class);
                    } else {
                        return null;
                    }
                } else {
                    $parameters[] = null;
                }
            }

            $response = $reflection->invokeArgs($instance, $parameters);

            if ($response instanceof JsonResource) {
                return json_encode($response->toArray(app(Request::class)), JSON_PRETTY_PRINT);
            }

            return null;

        } catch (\Throwable $e) {
            return null;
        }
    }
}
