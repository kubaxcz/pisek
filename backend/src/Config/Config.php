<?php

declare(strict_types=1);

namespace Piskari\Config;

final class Config
{
    /** @var array<string, string> */
    private static array $values = [];

    private static function normalizeEnvValue(string $value): string
    {
        $v = trim($value);
        if (strlen($v) >= 2) {
            $first = $v[0];
            $last = $v[strlen($v) - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $v = substr($v, 1, -1);
            }
        }
        return trim($v);
    }

    public static function initFromEnvFile(string $envPath): void
    {
        if (!is_file($envPath)) {
            return;
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            $parts = explode('=', $trimmed, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $key = trim($parts[0]);
            $value = self::normalizeEnvValue($parts[1]);

            if ($key === '') {
                continue;
            }

            if (getenv($key) !== false) {
                continue;
            }

            self::$values[$key] = $value;
        }
    }

    public static function getString(string $key, ?string $default = null): ?string
    {
        if (array_key_exists($key, self::$values)) {
            return self::normalizeEnvValue(self::$values[$key]);
        }

        $env = getenv($key);
        if ($env !== false) {
            return self::normalizeEnvValue((string) $env);
        }

        return $default;
    }
}
