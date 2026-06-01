<?php

declare(strict_types=1);

use App\Support\Request;
use App\Support\Router;

require_once dirname(__DIR__) . '/bootstrap/app.php';

$router = new Router();
$routes = require APP_BASE_PATH . '/config/routes.php';
$routes($router);

$response = $router->dispatch(Request::capture());
$response->send();
