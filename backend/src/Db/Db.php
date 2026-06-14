<?php

declare(strict_types=1);

namespace Piskari\Db;

use Piskari\Config\Config;
use PDO;
use PDOException;

final class Db
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        $host = Config::getString('DB_HOST', '127.0.0.1');
        $port = Config::getString('DB_PORT', '3306');
        $dbName = Config::getString('DB_NAME', 'piskari');
        $user = Config::getString('DB_USER', 'root');
        $pass = Config::getString('DB_PASSWORD');
        if ($pass === null) {
            $pass = Config::getString('DB_PASS', '');
        }

        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $dbName);

        try {
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                // Fail fast instead of hanging when the DB host is unreachable.
                PDO::ATTR_TIMEOUT => 5,
            ]);
        } catch (PDOException $e) {
            throw new PDOException(
                sprintf('Database connection failed (host=%s port=%s db=%s user=%s)', $host, $port, $dbName, $user),
                (int) $e->getCode(),
                $e
            );
        }

        self::$pdo = $pdo;
        return $pdo;
    }
}
