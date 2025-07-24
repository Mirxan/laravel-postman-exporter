<?php

namespace Mirxan\PostmanExporter\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Mirxan\PostmanExporter\Route\PostmanRouteParser;
use Mirxan\PostmanExporter\Builder\PostmanJsonBuilder;

class ExportPostmanCommand extends Command
{
    protected $signature = 'export:postman';
    protected $description = 'Export Laravel API routes to Postman collection';

    public function handle()
    {
        $routes = (new PostmanRouteParser())->getRoutes();
        $json = (new PostmanJsonBuilder())->build($routes);

        $path = config('postman-exporter.path');
        $path = str_replace(['{timestamp}', '{app}'], [now()->timestamp, config('app.name')], $path);
        Storage::disk(config('postman-exporter.disk'))->put($path, $json);

        $envConfig = config('postman-exporter.environment');
        $environment = [
            'id' => 'env-' . now()->timestamp,
            'name' => $envConfig['name'],
            'values' => $envConfig['variables'],
            'timestamp' => now()->timestamp,
            '_postman_variable_scope' => 'environment',
            '_postman_exported_at' => now()->toIso8601String(),
            '_postman_exported_using' => 'Postman Laravel Exporter v1.0',
        ];

        Storage::disk(config('postman-exporter.disk'))->put(
            'postman/' . $envConfig['file_name'],
            json_encode($environment, JSON_PRETTY_PRINT)
        );

        $this->info("✅ Postman collection exported to: storage/app/{$path}");
    }
}
