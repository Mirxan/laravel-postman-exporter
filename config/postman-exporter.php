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
    'path' => 'postman/{app}-collection.json',

    /*
    |--------------------------------------------------------------------------
    | Postman Collection Name
    |--------------------------------------------------------------------------
    */
    'collection_name' => env('APP_NAME', 'Laravel') . now()->toString() . ' API',

    /*
    |--------------------------------------------------------------------------
    | Postman Collection Schema
    |--------------------------------------------------------------------------
    */
    'collection_schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',

    /*
    |--------------------------------------------------------------------------
    | Postman Collection ID
    | This value is set automatically.
    |--------------------------------------------------------------------------
    */
    'postman_collection_id' => '',

    /*
    |--------------------------------------------------------------------------
    | Postman Environment ID
    | This value is set automatically.
    |--------------------------------------------------------------------------
    */
    'postman_envirenment_id' => '',

    /*
    |--------------------------------------------------------------------------
    | Postman Workspace ID
    |--------------------------------------------------------------------------
    */
    'postman_workspace_id' => '',

    /*
    |--------------------------------------------------------------------------
    | Postman API Key
    |--------------------------------------------------------------------------
    */
    'postman_api_key' => '',

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
    | Middleware Filtering
    |--------------------------------------------------------------------------
    | Only include routes that have at least one of the following middleware.
    | Leave this empty to include all routes without filtering.
    */
    'included_middlewares' => [
        'api', // Example: 'auth', 'verified', 'throttle:api'
    ],

    /*
    |--------------------------------------------------------------------------
    | Environment File Configuration
    |--------------------------------------------------------------------------
    | This section defines how the Postman environment file will be structured.
    */
    'environment' => [
        'name' => env('APP_ENV', 'local') . 'env',
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

        // Folder keys should correspond to the starting segment of your route prefixes

        // Example for 'api' prefix: folder with global headers and test scripts
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
            'prerequest' => "console.log('Sending request to API endpoint...');",
            'test' => "pm.test('Status is 200', function () {\n    pm.response.to.have.status(200);\n});",
        ],

        // For all prefixes not present in folders, use these settings
        'default' => [
            'level' => 2,
            'isGlobal' => true,
            'headers' => [
                'Accept' => 'application/json',
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
            'prerequest' => "console.log('Default folder prerequest script');",
            'test' => "pm.test('Default status is 200', function () {\n    pm.response.to.have.status(200);\n});",
        ],
    ],
];
