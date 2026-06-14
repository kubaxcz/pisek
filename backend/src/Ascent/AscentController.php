<?php

declare(strict_types=1);

namespace Piskari\Ascent;

use Piskari\Auth\AuthService;
use Piskari\Http\Request;
use Piskari\Http\Response;

final class AscentController
{
    public function __construct(
        private readonly AscentRepository $repo = new AscentRepository(),
        private readonly AuthService $auth = new AuthService()
    ) {
    }

    /**
     * Route detail: all user entries plus the caller's own entry (if any).
     * Public — anyone can read; only the own-entry is tied to the session.
     */
    public function detail(Request $request, int $routeId): Response
    {
        $entries = $this->repo->listForRoute($routeId);

        $user = $this->auth->currentUser($request);
        $own = $user !== null ? $this->repo->own($user->id, $routeId) : null;

        return Response::json([
            'route_id' => $routeId,
            'entries' => $entries,
            'own' => $own,
        ]);
    }

    public function save(Request $request, int $routeId): Response
    {
        $user = $this->auth->currentUser($request);
        if ($user === null) {
            return Response::json(['error' => 'Přihlaste se prosím.'], 401);
        }

        $body = $request->getJsonBody();
        $routeStars = $this->parseStars($body['route_stars'] ?? null);
        $belayStars = $this->parseStars($body['belay_stars'] ?? null);
        $protection = Protection::normalizeSequence($body['protection'] ?? []);
        $note = $this->parseNote($body['note'] ?? null);

        if ($routeStars === null && $belayStars === null && $protection === [] && $note === null) {
            return Response::json(['error' => 'Vyplňte alespoň jednu hodnotu.'], 422);
        }

        $this->repo->upsert($user->id, $routeId, $routeStars, $belayStars, $protection, $note);

        return Response::json(['own' => $this->repo->own($user->id, $routeId)]);
    }

    public function delete(Request $request, int $routeId): Response
    {
        $user = $this->auth->currentUser($request);
        if ($user === null) {
            return Response::json(['error' => 'Přihlaste se prosím.'], 401);
        }

        $this->repo->delete($user->id, $routeId);
        return Response::json(['ok' => true]);
    }

    private function parseStars(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $n = (int) $value;
        return ($n >= 1 && $n <= 5) ? $n : null;
    }

    private function parseNote(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $note = trim($value);
        if ($note === '') {
            return null;
        }
        return mb_substr($note, 0, 2000);
    }
}
