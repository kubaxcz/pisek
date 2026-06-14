<?php

declare(strict_types=1);

namespace Piskari\Auth;

use Piskari\Ascent\Protection;
use Piskari\Config\Config;
use Piskari\Http\Request;
use Piskari\Http\Response;

final class AuthController
{
    public function __construct(
        private readonly AuthService $auth = new AuthService()
    ) {
    }

    public function googleLogin(Request $request): Response
    {
        $body = $request->getJsonBody();
        $idToken = isset($body['id_token']) && is_string($body['id_token']) ? $body['id_token'] : '';

        $result = $this->auth->loginWithGoogle($idToken);
        if ($result === null) {
            return Response::json(['error' => 'Přihlášení selhalo.'], 401);
        }

        return Response::json([
            'token' => $result['token'],
            'user' => $result['user']->toArray(),
        ]);
    }

    public function me(Request $request): Response
    {
        $user = $this->auth->currentUser($request);
        return Response::json(['user' => $user?->toArray()]);
    }

    public function logout(Request $request): Response
    {
        $this->auth->logout($request);
        return Response::json(['ok' => true]);
    }

    /**
     * Public runtime config for the frontend (Google client id, protection
     * type options). Avoids baking the client id into the build.
     */
    public function config(Request $request): Response
    {
        return Response::json([
            'googleClientId' => Config::getString('GOOGLE_CLIENT_ID', '') ?? '',
            'protectionTypes' => Protection::TYPES,
        ]);
    }
}
