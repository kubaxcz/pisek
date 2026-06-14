<?php

declare(strict_types=1);

namespace Piskari\Scrape;

use Piskari\Auth\AuthService;
use Piskari\Http\Request;
use Piskari\Http\Response;
use Throwable;

final class ScrapeController
{
    public function __construct(
        private readonly ScrapeService $service = new ScrapeService(),
        private readonly ScrapeRepository $repo = new ScrapeRepository(),
        private readonly AuthService $auth = new AuthService()
    ) {
    }

    public function listRuns(Request $request): Response
    {
        if (!$this->isAdmin($request)) {
            return self::unauthorized();
        }

        return Response::json(['runs' => $this->repo->listRuns()]);
    }

    public function getRun(Request $request, int $runId): Response
    {
        if (!$this->isAdmin($request)) {
            return self::unauthorized();
        }

        $run = $this->repo->getRun($runId);
        if ($run === null) {
            return Response::json(['error' => 'Run not found'], 404);
        }

        return Response::json([
            'run' => $run,
            'jobs' => $this->repo->listJobs($runId),
        ]);
    }

    public function current(Request $request): Response
    {
        if (!$this->isAdmin($request)) {
            return self::unauthorized();
        }

        $runId = $this->service->activeRunId();
        if ($runId === null) {
            return Response::json(['run' => null, 'jobs' => []]);
        }

        return Response::json([
            'run' => $this->repo->getRun($runId),
            'jobs' => $this->repo->listJobs($runId),
        ]);
    }

    public function start(Request $request): Response
    {
        if (!$this->isAdmin($request)) {
            return self::unauthorized();
        }

        try {
            $runId = $this->service->planRun();
        } catch (Throwable $e) {
            return Response::json(['error' => $e->getMessage()], 409);
        }

        return Response::json([
            'run' => $this->repo->getRun($runId),
            'jobs' => $this->repo->listJobs($runId),
        ], 201);
    }

    public function step(Request $request, int $runId): Response
    {
        if (!$this->isAdmin($request)) {
            return self::unauthorized();
        }

        try {
            $result = $this->service->step($runId);
        } catch (Throwable $e) {
            return Response::json(['error' => $e->getMessage()], 400);
        }

        return Response::json([
            'done' => $result['done'],
            'job' => $result['job'],
            'run' => $result['run'],
            'jobs' => $this->repo->listJobs($runId),
        ]);
    }

    private function isAdmin(Request $request): bool
    {
        $user = $this->auth->currentUser($request);
        return $user !== null && $user->isAdmin;
    }

    private static function unauthorized(): Response
    {
        return Response::json(['error' => 'Unauthorized'], 401);
    }
}
