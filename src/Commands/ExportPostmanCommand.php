<?php

namespace Mirxan\PostmanExporter\Commands;

use File;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Mirxan\PostmanExporter\Route\PostmanRouteParser;
use Mirxan\PostmanExporter\Builder\PostmanJsonBuilder;

class ExportPostmanCommand extends Command
{
    protected $signature = 'export:postman {--sync}';
    protected $description = 'Export Laravel API routes to Postman and optionally sync with Postman API';
    private $postmanUrl = 'https://api.getpostman.com';

    public function handle()
    {
        $sync = $this->option('sync');
        $apiKey = config('postman-exporter.postman_api_key');

        if ($sync && empty($apiKey)) {
            return $this->error('❌ Postman API key is missing in config.');
        }

        $routes = (new PostmanRouteParser())->getRoutes();
        $collectionJson = (new PostmanJsonBuilder())->build($routes);

        $collectionPath = $this->storeCollection($collectionJson);
        $environmentData = $this->buildEnvironment();

        $this->storeEnvironmentFile($environmentData);

        if ($sync) {
            $this->syncToPostman($collectionJson, $environmentData, $apiKey);
        }

        $this->info("✅ Postman collection exported to: storage/app/{$collectionPath}");
    }

    private function storeCollection(string $json): string
    {
        $path = config('postman-exporter.path');
        $path = str_replace(['{timestamp}', '{app}'], [now()->timestamp, config('app.name')], $path);
        Storage::disk(config('postman-exporter.disk'))->put($path, $json);
        return $path;
    }

    private function buildEnvironment(): array
    {
        $envConfig = config('postman-exporter.environment');
        return [
            'environment' => [
                'name' => $envConfig['name'],
                'values' => $envConfig['variables'],
            ]
        ];
    }

    private function storeEnvironmentFile(array $envData): void
    {
        $fileName = config('postman-exporter.environment.file_name');
        Storage::disk(config('postman-exporter.disk'))->put(
            'postman/' . $fileName,
            json_encode($envData, JSON_PRETTY_PRINT)
        );
    }

    private function syncToPostman(string $collectionJson, array $environmentData, string $apiKey): void
    {
        $collectionId = config('postman-exporter.postman_collection_id');
        $environmentId = config('postman-exporter.postman_envirenment_id');
        $headers = [
            'X-Api-Key' => $apiKey,
            'Content-Type' => 'application/json',
        ];

        $collection = [
            'collection' => json_decode($collectionJson, true)
        ];
        $collection['collection']['info']['name'] = config('postman-exporter.collection_name');
        $collection['collection']['info']['schema'] = config('postman-exporter.collection_schema');

        if ($collectionId && $environmentId) {
            $collectionResp = Http::withHeaders($headers)->put("{$this->postmanUrl}/collections/{$collectionId}", $collection);
            $envResp = Http::withHeaders($headers)->put("{$this->postmanUrl}/environments/{$environmentId}", $environmentData);
        } else {
            $collectionResp = Http::withHeaders($headers)->post("{$this->postmanUrl}/collections", $collection);
            $envResp = Http::withHeaders($headers)->post("{$this->postmanUrl}/environments", $environmentData);

            if ($envResp->successful()) {
                $envId = $envResp->json('environment.uid');
                $this->updateConfig('postman_envirenment_id', $envId);
                $this->info("🆕 Environment created. ID: {$envId}");
            } else {
                $this->error("❌ Failed to create environment: " . $envResp->body());
                return;
            }

            if ($collectionResp->successful()) {
                $colId = $collectionResp->json('collection.uid');
                $this->updateConfig('postman_collection_id', $colId);
                $this->info("🆕 Collection created. ID: {$colId}");
            } else {
                $this->error("❌ Failed to create collection: " . $collectionResp->body());
                return;
            }
        }

        $collectionResp->successful()
            ? $this->info("✅ Collection synced successfully.")
            : $this->error("❌ Failed to sync collection: " . $collectionResp->body());

        $envResp->successful()
            ? $this->info("✅ Environment synced successfully.")
            : $this->error("❌ Failed to sync environment: " . $envResp->body());
    }

    private function updateConfig(string $key, string $newValue): void
    {
        $configPath = config_path('postman-exporter.php');
        $configContent = File::get($configPath);
        $pattern = "/('{$key}'\s*=>\s*)'(.*?)'/";
        $replacement = "$1'{$newValue}'";
        $updatedContent = preg_replace($pattern, $replacement, $configContent);
        File::put($configPath, $updatedContent);
    }

    private function removePostmanStorage(): void
    {
        Storage::disk(config('postman-exporter.disk'))->deleteDirectory('postman');
    }
}
