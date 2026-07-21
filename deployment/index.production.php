<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Replace this placeholder with the absolute private-core path on production
// before switching the domain to this front controller.
$privateCorePath = '__PRIVATE_CORE_PATH__';

if (str_contains($privateCorePath, '__PRIVATE_CORE_PATH__')) {
    http_response_code(503);
    exit('Production private-core path is not configured.');
}

if (file_exists($maintenance = $privateCorePath.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $privateCorePath.'/vendor/autoload.php';

/** @var Application $app */
$app = require_once $privateCorePath.'/bootstrap/app.php';

$app->handleRequest(Request::capture());
