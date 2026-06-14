<?php

declare(strict_types=1);

namespace Piskari\Http;

final class Request
{
    /**
     * @param array<string, mixed>  $queryParams
     * @param array<string, string> $headers
     */
    private function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly array $queryParams,
        private readonly array $headers,
        private readonly string $rawBody
    ) {
    }

    /**
     * @param array<string, mixed>  $queryParams
     * @param array<string, string> $headers
     */
    public static function forTest(
        string $method,
        string $path,
        array $queryParams = [],
        array $headers = [],
        string $rawBody = ''
    ): self {
        $normalizedHeaders = [];
        foreach ($headers as $k => $v) {
            if (!is_string($k)) {
                continue;
            }
            $normalizedHeaders[strtolower($k)] = (string) $v;
        }

        return new self(strtoupper($method), $path, $queryParams, $normalizedHeaders, $rawBody);
    }

    public static function fromGlobals(): self
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);
        if (!is_string($path)) {
            $path = '/';
        }

        $queryString = parse_url($uri, PHP_URL_QUERY);
        $queryParams = [];
        if (is_string($queryString) && $queryString !== '') {
            parse_str($queryString, $queryParams);
        }

        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with((string) $key, 'HTTP_')) {
                $name = str_replace('_', '-', strtolower(substr((string) $key, 5)));
                $headers[$name] = (string) $value;
            }
        }

        $rawBody = file_get_contents('php://input');
        if ($rawBody === false) {
            $rawBody = '';
        }

        return new self($method, $path, $queryParams, $headers, $rawBody);
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getQueryParam(string $name): ?string
    {
        if (!array_key_exists($name, $this->queryParams)) {
            return null;
        }

        $value = $this->queryParams[$name];
        if (is_array($value)) {
            return null;
        }

        return (string) $value;
    }

    public function getHeader(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function getJsonBody(): array
    {
        $trimmed = trim($this->rawBody);
        if ($trimmed === '') {
            return [];
        }

        $data = json_decode($trimmed, true);
        if (!is_array($data)) {
            return [];
        }

        return $data;
    }
}
