<?php

declare(strict_types=1);

$config = require dirname(__DIR__) . '/config/app.php';
date_default_timezone_set($config['timezone']);

$sessionPath = $config['session_path'] ?? dirname(__DIR__) . '/storage/sessions';
if (!is_dir($sessionPath)) {
    mkdir($sessionPath, 0775, true);
}
ini_set('session.save_path', $sessionPath);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once dirname(__DIR__) . '/app/controllers/RespuestaController.php';

$routes = require dirname(__DIR__) . '/routes/web.php';

$requestPath = trim((string) ($_GET['route'] ?? ''));

if ($requestPath === '') {
    $requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
}

$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$baseDir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');

if (
    $requestPath !== ''
    && $baseDir !== ''
    && $baseDir !== '/'
    && str_starts_with($requestPath, $baseDir)
) {
    $requestPath = substr($requestPath, strlen($baseDir)) ?: '/';
}

$requestPath = '/' . trim($requestPath, '/');
if ($requestPath === '//') {
    $requestPath = '/';
}

$requestPath = $requestPath === '/index.php' ? '/' : $requestPath;

$route = $routes[$requestPath] ?? null;

if ($route === null) {
    http_response_code(404);
    echo 'Ruta no encontrada.';
    exit;
}

[$controllerName, $method] = $route;

$controller = new $controllerName();
$controller->$method();
