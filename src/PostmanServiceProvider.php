<?php

namespace Mirxan\PostmanExporter;

use Illuminate\Support\ServiceProvider;
use Mirxan\PostmanExporter\Commands\ExportPostmanCommand;

class PostmanServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/postman-exporter.php', 'postman-exporter');
    }

    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config/postman-exporter.php' => config_path('postman-exporter.php'),
        ], 'postman-exporter-config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                ExportPostmanCommand::class
            ]);
        }
    }
}
