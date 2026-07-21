<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/core/Router.php';

/* IIS rewrite supplies path; query fallback enables direct PHP testing. */
$path = (string) ($_GET['path'] ?? '');
if ($path === '') {
    $requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '';
    $apiPosition = strpos($requestPath, '/api/');
    $path = $apiPosition === false ? $requestPath : substr($requestPath, $apiPosition + 4);
}

$routes = require __DIR__ . '/routes.php';
(new Router())->register($routes)->dispatch($path);
