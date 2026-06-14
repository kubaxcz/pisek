<?php

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

date_default_timezone_set('Europe/Prague');

spl_autoload_register(static function (string $className): void {
    foreach (['Piskari\\Tests\\' => __DIR__, 'Piskari\\' => __DIR__ . '/../src'] as $prefix => $baseDir) {
        if (strncmp($prefix, $className, strlen($prefix)) !== 0) {
            continue;
        }
        $relative = substr($className, strlen($prefix));
        $filePath = $baseDir . '/' . str_replace('\\', '/', $relative) . '.php';
        if (is_file($filePath)) {
            require_once $filePath;
            return;
        }
    }
});
