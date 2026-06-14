<?php

declare(strict_types=1);

namespace Piskari\Auth;

use Piskari\Config\Config;
use Piskari\Http\Request;

final class AuthService
{
    private const SESSION_TTL_DAYS = 30;

    public function __construct(
        private readonly GoogleVerifier $verifier = new GoogleVerifier(),
        private readonly AuthRepository $repo = new AuthRepository()
    ) {
    }

    /**
     * Verify a Google ID token, upsert the user, and open a session.
     *
     * @return array{token:string, user:CurrentUser}|null
     */
    public function loginWithGoogle(string $idToken): ?array
    {
        $profile = $this->verifier->verify($idToken);
        if ($profile === null) {
            return null;
        }

        $userId = $this->repo->upsertUser($profile);
        $token = bin2hex(random_bytes(32));
        $this->repo->createSession($userId, $token, self::SESSION_TTL_DAYS);

        return [
            'token' => $token,
            'user' => new CurrentUser(
                $userId,
                $profile['email'],
                $profile['name'],
                $profile['picture'],
                $this->isAdminEmail($profile['email'])
            ),
        ];
    }

    /**
     * Resolve the current user from the request's bearer/session token.
     */
    public function currentUser(Request $request): ?CurrentUser
    {
        $token = $this->extractToken($request);
        if ($token === null) {
            return null;
        }

        $row = $this->repo->userBySession($token);
        if ($row === null) {
            return null;
        }

        $email = (string) $row['email'];
        return new CurrentUser(
            (int) $row['id'],
            $email,
            $row['name'] !== null ? (string) $row['name'] : null,
            $row['picture'] !== null ? (string) $row['picture'] : null,
            $this->isAdminEmail($email)
        );
    }

    public function logout(Request $request): void
    {
        $token = $this->extractToken($request);
        if ($token !== null) {
            $this->repo->deleteSession($token);
        }
    }

    private function extractToken(Request $request): ?string
    {
        $auth = $request->getHeader('authorization');
        if ($auth !== null && stripos($auth, 'bearer ') === 0) {
            $token = trim(substr($auth, 7));
            return $token !== '' ? $token : null;
        }
        $header = $request->getHeader('x-session-token');
        return $header !== null && $header !== '' ? $header : null;
    }

    private function isAdminEmail(string $email): bool
    {
        $whitelist = Config::getString('ADMIN_EMAILS', '') ?? '';
        $email = strtolower(trim($email));
        foreach (explode(',', $whitelist) as $allowed) {
            $allowed = strtolower(trim($allowed));
            if ($allowed !== '' && $allowed === $email) {
                return true;
            }
        }
        return false;
    }
}
