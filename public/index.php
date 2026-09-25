<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Op Antagonist (productie): Laravel staat in /domains/databasehub.sorai.nl/laravel_app/
// De webroot /domains/databasehub.sorai.nl/public_html/ moet daar bootstrappen.
// Lokaal: Laravel staat één map hoger (../) — beide paden worden geprobeerd.
$candidates = [
    __DIR__.'/../laravel_app',  // Antagonist productie
    __DIR__.'/..',               // Lokaal development
];

$laravelRoot = null;
foreach ($candidates as $path) {
    if (file_exists($path.'/vendor/autoload.php')) {
        $laravelRoot = $path;
        break;
    }
}

if ($laravelRoot === null) {
    http_response_code(500);
    exit('Laravel installation not found. Run composer install or check FTP deploy paths.');
}

// Maintenance mode
if (file_exists($maintenance = $laravelRoot.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $laravelRoot.'/vendor/autoload.php';

/** @var Application $app */
$app = require_once $laravelRoot.'/bootstrap/app.php';

$app->handleRequest(Request::capture());
