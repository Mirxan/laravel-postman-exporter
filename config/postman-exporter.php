<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Storage Disk
    |--------------------------------------------------------------------------
    | The Laravel filesystem disk where the exported collection and environment
    | files will be stored.
    */
    'disk' => 'local',

    /*
    |--------------------------------------------------------------------------
    | Export File Path
    |--------------------------------------------------------------------------
    | The path format where the Postman collection will be saved.
    | You can use {timestamp} and {app} placeholders.
    */
    'path' => 'postman/{app}-collection-{timestamp}.json',

    /*
    |--------------------------------------------------------------------------
    | Postman Collection Name
    |--------------------------------------------------------------------------
    */
    'collection_name' => env('APP_NAME', 'Laravel') . ' API',

    /*
    |--------------------------------------------------------------------------
    | Postman Collection Schema
    |--------------------------------------------------------------------------
    */
    'collection_schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',

    /*
    |--------------------------------------------------------------------------
    | Base URL Key
    |--------------------------------------------------------------------------
    | This key will be used in Postman as an environment variable.
    | It will be used like: {{base_url}}/api/...
    */
    'base_url_key' => 'base_url',

    /*
    |--------------------------------------------------------------------------
    | Default Request Parameters
    |--------------------------------------------------------------------------
    | These values will be used for generating form-data parameters
    | in POST, PUT, or PATCH requests.
    */
    'params_value' => [],

    /*
    |--------------------------------------------------------------------------
    | Default Content Type
    |--------------------------------------------------------------------------
    | Options: form-data, urlencoded, multipart, json, raw = json
    */
    'content_type' => 'json',

    /*
    |--------------------------------------------------------------------------
    | Environment File Configuration
    |--------------------------------------------------------------------------
    | This section defines how the Postman environment file will be structured.
    */
    'environment' => [
        'name' => env('APP_NAME', 'Laravel') . ' Environment',
        'file_name' => 'environment.json',
        'variables' => [
            [
                'key' => 'base_url',
                'value' => env('APP_URL', 'http://localhost'),
                'enabled' => true,
            ],
            [
                'key' => 'token',
                'value' => '',
                'enabled' => true,
            ],
            [
                'key' => 'api_key',
                'value' => 'something',
                'enabled' => true,
            ]
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Folder Structure Configuration
    |--------------------------------------------------------------------------
    | Define folders in your Postman collection and apply global settings.
    | `isGlobal` means the settings apply to every request in this folder.
    */
    'folders' => [

        // Auth folder with global headers and test scripts
        'api' => [
            'level' => 2,
            'isGlobal' => true,
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer {{token}}',
            ],
            'auth' => [
                'type' => 'bearer',
                'bearer' => [
                    [
                        'key' => 'token',
                        'value' => '{{token}}',
                    ],
                ],
            ],
            'prerequest' => "console.log('Sending request to auth endpoint...');",
            'test' => "pm.test('Status is 200', function () {\n    pm.response.to.have.status(200);\n});",
        ],
    ],
];
