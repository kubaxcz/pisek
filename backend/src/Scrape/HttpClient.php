<?php

declare(strict_types=1);

namespace Piskari\Scrape;

use RuntimeException;

/**
 * Minimal polite HTTP client for fetching piskari.cz pages.
 */
final class HttpClient
{
    private const USER_AGENT = 'piskari-catalog-bot/1.0 (+https://www.piskari.cz crawler for personal catalogue)';

    public function __construct(
        private readonly int $timeoutSeconds = 20,
        private readonly int $maxRetries = 3
    ) {
    }

    /**
     * Fetch a URL and return the response body as a UTF-8 string.
     *
     * @throws RuntimeException on a permanent failure after retries.
     */
    public function get(string $url): string
    {
        $lastError = '';

        for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
            $ch = curl_init($url);
            if ($ch === false) {
                throw new RuntimeException('Failed to initialise cURL for ' . $url);
            }

            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 5,
                CURLOPT_CONNECTTIMEOUT => $this->timeoutSeconds,
                CURLOPT_TIMEOUT => $this->timeoutSeconds,
                CURLOPT_USERAGENT => self::USER_AGENT,
                CURLOPT_ENCODING => '',
            ]);

            $body = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $error = curl_error($ch);
            unset($ch);

            if (is_string($body) && $status >= 200 && $status < 300) {
                return $body;
            }

            $lastError = $error !== '' ? $error : ('HTTP ' . $status);

            if ($attempt < $this->maxRetries) {
                // Linear back-off; keeps the crawl polite.
                sleep($attempt);
            }
        }

        throw new RuntimeException(sprintf('Failed to fetch %s: %s', $url, $lastError));
    }
}
