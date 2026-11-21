<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

// Jika dijalankan di XAMPP lokal (localhost / 127.0.0.1) dan file .env.lokal.xampp tersedia,
// gunakan konfigurasi .env.lokal.xampp agar file .env utama tetap berisi kredensial hosting.
$host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
if ((str_starts_with($host, 'localhost') || str_starts_with($host, '127.0.0.1'))
    && file_exists(__DIR__.'/../.env.lokal.xampp')
) {
    $app->loadEnvironmentFrom('.env.lokal.xampp');
}

// Normalisasi SCRIPT_NAME & PHP_SELF saat dibuka di subfolder XAMPP (cth: http://localhost/Laundry/)
// yang diteruskan oleh .htaccess root ke public/index.php, agar baseUrl & pathInfo terbaca "/" (bukan "/Laundry" -> 404).
if (isset($_SERVER['SCRIPT_NAME'], $_SERVER['REQUEST_URI'])
    && str_ends_with($_SERVER['SCRIPT_NAME'], '/public/index.php')
    && ! str_contains($_SERVER['REQUEST_URI'], '/public/')
) {
    $_SERVER['SCRIPT_NAME'] = substr($_SERVER['SCRIPT_NAME'], 0, -strlen('/public/index.php')) . '/index.php';
    if (isset($_SERVER['PHP_SELF']) && str_contains($_SERVER['PHP_SELF'], '/public/index.php')) {
        $_SERVER['PHP_SELF'] = str_replace('/public/index.php', '/index.php', $_SERVER['PHP_SELF']);
    }
}

$app->handleRequest(Request::capture());
