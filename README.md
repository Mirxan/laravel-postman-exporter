# Laravel Postman Exporter 📨

A developer-friendly Laravel package that **automatically generates a complete Postman collection** from your API routes using PHPDoc-style comments.  
It supports response/parameter detection, headers, bearer authentication, resource structure detection, folder-based grouping — and even Postman environment export!

---

## 🚀 Installation

### Via Composer:

```bash
composer require mirxan/postman-exporter
```
If you're installing from a GitHub repo:

// composer.json
```bash
"repositories": [
  {
    "type": "vcs",
    "url": "https://github.com/mirxan/laravel-postman-exporter"
  }
],
"require": {
  "mirxan/postman-exporter": "^1.0"
}
```
## 🔧 Publish Configuration
Run the following command to publish the configuration file:

```bash
php artisan vendor:publish --tag=postman-exporter-config
```
This will create the config file at:

`config/postman-exporter.php`
## ⚙️ Configuration Example
```bash
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
```
## 📤 Exporting Postman Collection
Run the export command:
```bash
php artisan export:postman
```
#### It will generate the collection file in:

## storage/app/postman/
You can import the `.json` file into Postman to test your Laravel API endpoints.

## 🔄 Optional: Sync with Postman API
If you want to automatically sync your collection and environment with Postman (via their API), run the command with the --sync option:

```bash
php artisan export:postman --sync
```
This will:

✅ Upload or update your collection on your Postman account
✅ Upload or update your environment file

🌍 Exporting Postman Environment
This package also exports a ready-to-use Postman environment file:

```bash
storage/app/postman/laravel_environment.json
```
This allows you to easily manage API variables like `APP_URL`, `TOKEN`, etc., within Postman.

## ✏️ DocBlock Comments for Customization
####  Add PHPDoc-style annotations to your controller methods:

```bash
/**
 * @description Login endpoint for users
 * @header Authorization: Bearer {{TOKEN}}
 * @param email: string - User's email address
 * @param password: string - User's password
 * @response 200 - Successful login
 * @response 422 - Validation error
 */
 ```

`These annotations will be parsed and added to your Postman request definition.`

## 📦 Response Auto-Detection

## Automatically detects JsonResource and ResourceCollection responses:

return new UserResource($user);                // → type: object

return UserResource::collection($users);       // → type: array[object]

This removes the need for manually defining response schemas.

## 📁 Output Example
`storage/app/postman/1721753093_myapp_collection.json`

`storage/app/postman/laravel_environment.json`

You can import both the collection and environment JSON files into Postman.

✅ Features
✅ Laravel 9.x to 12.x support

✅ PHP 8.0 to 8.4 compatibility

✅ Automatically generates Postman collections

✅ Header and parameter detection from PHPDoc

✅ JsonResource and Collection detection

✅ Environment file generation with custom variables

✅ Folder-based grouping and authentication

✅ Supports content types: form-data, json, x-www-form-urlencoded

## 🛠 Contributing
Pull requests are welcome!
Please feel free to open issues or suggest features.

## 📄 License
MIT License © 2025 Muhammadamin Nematov (mirxan085@gmail.com)