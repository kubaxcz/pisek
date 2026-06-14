<?php

declare(strict_types=1);

namespace Piskari\Http;

use Piskari\Config\Config;

final class Response
{
    private function __construct(
        private readonly int $statusCode,
        private readonly ?array $data
    ) {
    }

    public static function sendDefaultHeaders(): void
    {
        if (PHP_SAPI === 'cli') {
            return;
        }

        $origin = Config::getString('CORS_ALLOW_ORIGIN', '*') ?? '*';
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Headers: Content-Type, X-Admin-Password');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Content-Type: application/json; charset=utf-8');
    }

    /**
     * @param array<int|string, mixed> $data
     */
    public static function json(array $data, int $statusCode = 200): self
    {
        return new self($statusCode, $data);
    }

    public static function empty(int $statusCode = 204): self
    {
        return new self($statusCode, null);
    }

    public function send(): void
    {
        http_response_code($this->statusCode);
        if ($this->data === null) {
            return;
        }

        echo json_encode($this->data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
