<?php

declare(strict_types=1);

use App\Support\Autoloader;
use App\Support\Config;
use App\Support\Database;
use App\Support\Env;
use App\Support\ErrorHandler;
use App\Support\Session;

define('APP_BASE_PATH', dirname(__DIR__));

if (PHP_VERSION_ID < 80300) {
    $message = 'Industria Feris CRM requiere PHP 8.3 o superior. Version actual: ' . PHP_VERSION . '.';

    if (PHP_SAPI !== 'cli') {
        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');
    }

    echo $message;
    exit(1);
}

require_once APP_BASE_PATH . '/app/Support/Autoloader.php';

Autoloader::register([
    'App\\' => APP_BASE_PATH . '/app',
    'Integrations\\' => APP_BASE_PATH . '/integrations',
]);

require_once APP_BASE_PATH . '/app/Support/helpers.php';

Env::load(APP_BASE_PATH . '/.env');
Env::load(APP_BASE_PATH . '/.env.local');

Config::load([
    'app' => require APP_BASE_PATH . '/config/app.php',
    'database' => require APP_BASE_PATH . '/config/database.php',
]);

date_default_timezone_set((string) config('app.timezone', 'UTC'));
Session::start();
ErrorHandler::register((bool) config('app.debug', false));
Database::boot();
