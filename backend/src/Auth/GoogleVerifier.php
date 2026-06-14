<?php

declare(strict_types=1);

namespace Piskari\Auth;

use Piskari\Config\Config;

/**
 * Verifies a Google Identity Services ID token (JWT) via Google's tokeninfo
 * endpoint. The endpoint validates the signature and expiry server-side; we
 * additionally check the audience matches our configured client id and that
 * the email is verified.
 */
final class GoogleVerifier
{
    private const TOKENINFO_URL = 'https://oauth2.googleapis.com/tokeninfo?id_token=';

    /**
     * @return array{sub:string, email:string, name:?string, picture:?string}|null
     */
    public function verify(string $idToken): ?array
    {
        $clientId = Config::getString('GOOGLE_CLIENT_ID');
        if ($clientId === null || $clientId === '' || trim($idToken) === '') {
            return null;
        }

        $payload = $this->fetchTokenInfo($idToken);
        if ($payload === null) {
            return null;
        }

        $aud = (string) ($payload['aud'] ?? '');
        $sub = (string) ($payload['sub'] ?? '');
        $email = (string) ($payload['email'] ?? '');
        $emailVerified = (string) ($payload['email_verified'] ?? 'false');

        if ($aud !== $clientId || $sub === '' || $email === '') {
            return null;
        }
        if ($emailVerified !== 'true' && $emailVerified !== '1') {
            return null;
        }

        return [
            'sub' => $sub,
            'email' => strtolower($email),
            'name' => isset($payload['name']) ? (string) $payload['name'] : null,
            'picture' => isset($payload['picture']) ? (string) $payload['picture'] : null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchTokenInfo(string $idToken): ?array
    {
        $ch = curl_init(self::TOKENINFO_URL . urlencode($idToken));
        if ($ch === false) {
            return null;
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 8,
        ]);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        unset($ch);

        if (!is_string($body) || $status !== 200) {
            return null;
        }

        $payload = json_decode($body, true);
        return is_array($payload) ? $payload : null;
    }
}
