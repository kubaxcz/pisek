<?php

declare(strict_types=1);

use Piskari\Config\Config;
use Piskari\Http\Response;

error_reporting(E_ALL);
ini_set('display_errors', '1');

date_default_timezone_set('Europe/Prague');

spl_autoload_register(static function (string $className): void {
    $prefix = 'Piskari\\';
    if (strncmp($prefix, $className, strlen($prefix)) !== 0) {
        return;
    }

    $relative = substr($className, strlen($prefix));
    $relativePath = str_replace('\\', '/', $relative);
    $filePath = __DIR__ . '/' . $relativePath . '.php';
    if (is_file($filePath)) {
        require_once $filePath;
    }
});

Config::initFromEnvFile(__DIR__ . '/../.env');

Response::sendDefaultHeaders();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    Response::empty(204)->send();
    exit;
}
