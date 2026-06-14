<?php

declare(strict_types=1);

namespace Piskari\Scrape;

use RuntimeException;

/**
 * Orchestrates a scrape: plan first (persist the per-sector work-list), then
 * process one sector per call. The plan lives in the DB so an interrupted run
 * can always be resumed by continuing to call step().
 */
final class ScrapeService
{
    /**
     * Area entry points. Add more here to extend coverage.
     *
     * @var list<array{name:string, url:string}>
     */
    public const AREAS = [
        ['name' => 'Křížový vrch', 'url' => 'https://www.piskari.cz/cs/krizovy-vrch/'],
        ['name' => 'Adršpach', 'url' => 'https://www.piskari.cz/cs/adrspach/'],
    ];

    public function __construct(
        private readonly HttpClient $http = new HttpClient(),
        private readonly Parser $parser = new Parser(),
        private readonly ScrapeRepository $repo = new ScrapeRepository(),
        private readonly int $requestDelayMs = 400
    ) {
    }

    public function activeRunId(): ?int
    {
        return $this->repo->activeRunId();
    }

    /**
     * Discover all areas and their sectors and persist the work-list.
     * Returns the new run id. Throws if a run is already active.
     */
    public function planRun(): int
    {
        if ($this->repo->activeRunId() !== null) {
            throw new RuntimeException('A scrape run is already active.');
        }

        $runId = $this->repo->createRun();
        $total = 0;

        try {
            foreach (self::AREAS as $area) {
                $html = $this->http->get($area['url']);
                $areaId = $this->repo->upsertArea($area['name'], $area['url']);

                foreach ($this->parser->parseSectors($html, $area['url']) as $sector) {
                    $this->repo->upsertSector(
                        $areaId,
                        $sector['name'],
                        $sector['url'],
                        $sector['climbing_season'],
                        $sector['climbing_restriction']
                    );
                    $this->repo->addJob($runId, $area['name'], $sector['name'], $sector['url']);
                    $total++;
                }

                $this->throttle();
            }

            $this->repo->setRunSectorsTotal($runId, $total);
            $this->repo->setRunStatus($runId, 'running');
            $this->repo->refreshRunCounts($runId);
        } catch (\Throwable $e) {
            $this->repo->setRunStatus($runId, 'failed', $e->getMessage());
            throw $e;
        }

        return $runId;
    }

    /**
     * Process the next pending sector job of a run, synchronously.
     *
     * @return array{done:bool, job:?array<string,mixed>, run:array<string,mixed>}
     */
    public function step(int $runId): array
    {
        $run = $this->repo->getRun($runId);
        if ($run === null) {
            throw new RuntimeException('Unknown scrape run: ' . $runId);
        }

        $this->repo->reclaimStaleJobs($runId);
        $job = $this->repo->claimNextJob($runId);

        if ($job === null) {
            $this->repo->setRunStatus($runId, 'done');
            $this->repo->refreshRunCounts($runId);
            return ['done' => true, 'job' => null, 'run' => $this->repo->getRun($runId) ?? []];
        }

        try {
            [$rocks, $routes] = $this->scrapeSector($job);
            $this->repo->completeJob($job['id'], $rocks, $routes);
        } catch (\Throwable $e) {
            $this->repo->failJob($job['id'], $e->getMessage());
        }

        $this->repo->refreshRunCounts($runId);

        $remaining = $this->repo->pendingJobCount($runId);
        if ($remaining === 0) {
            $this->repo->setRunStatus($runId, 'done');
        }

        return [
            'done' => $remaining === 0,
            'job' => $this->currentJobView($runId, $job['id']),
            'run' => $this->repo->getRun($runId) ?? [],
        ];
    }

    /**
     * Scrape one sector: its rocks and their routes. Returns [rocks, routes].
     *
     * @param array{id:int, area_name:string, sector_name:string, sector_url:string} $job
     * @return array{0:int, 1:int}
     */
    private function scrapeSector(array $job): array
    {
        // Sector listings paginate at 30 rocks/page; follow "next" until the
        // last page, collecting distinct rocks (keyed by URL).
        $rocks = [];
        $seen = [];
        $pageUrl = $job['sector_url'];
        $pages = 0;
        while ($pageUrl !== null && !isset($seen[$pageUrl]) && $pages < 200) {
            $seen[$pageUrl] = true;
            $pages++;
            $html = $this->http->get($pageUrl);
            foreach ($this->parser->parseRocks($html, $pageUrl) as $rock) {
                if (!isset($seen['rock:' . $rock['url']])) {
                    $seen['rock:' . $rock['url']] = true;
                    $rocks[] = $rock;
                }
            }
            $next = $this->parser->parseNextPageUrl($html, $pageUrl);
            $pageUrl = $next;
            if ($next !== null) {
                $this->throttle();
            }
        }

        // The sector was upserted (with its season/restriction) during planning;
        // resolve its id without clobbering that data. Fall back to an upsert if
        // the row is somehow missing (e.g. a manually-triggered step).
        $sectorId = $this->repo->sectorIdByUrl($job['sector_url']);
        if ($sectorId === null) {
            $areaId = $this->repo->upsertArea($job['area_name'], $this->areaUrlFor($job['area_name']));
            $sectorId = $this->repo->upsertSector($areaId, $job['sector_name'], $job['sector_url'], null, null);
        }

        $routeCount = 0;
        foreach ($rocks as $rock) {
            $this->throttle();
            $rockHtml = $this->http->get($rock['url']);
            $parsed = $this->parser->parseRock($rockHtml, $rock['url']);

            $rockId = $this->repo->upsertRock(
                $sectorId,
                $rock['name'],
                $rock['url'],
                $job['area_name'],
                $job['sector_name'],
                [
                    'gps_raw' => $parsed['gps_raw'],
                    'gps_lat' => $parsed['gps_lat'],
                    'gps_lon' => $parsed['gps_lon'],
                ]
            );

            foreach ($parsed['routes'] as $route) {
                $this->repo->upsertRoute($rockId, $route);
                $routeCount++;
            }
        }

        return [count($rocks), $routeCount];
    }

    private function areaUrlFor(string $areaName): string
    {
        foreach (self::AREAS as $area) {
            if ($area['name'] === $areaName) {
                return $area['url'];
            }
        }
        return 'https://www.piskari.cz/cs/';
    }

    /**
     * @return array<string, mixed>|null
     */
    private function currentJobView(int $runId, int $jobId): ?array
    {
        foreach ($this->repo->listJobs($runId) as $job) {
            if ((int) $job['id'] === $jobId) {
                return $job;
            }
        }
        return null;
    }

    private function throttle(): void
    {
        if ($this->requestDelayMs > 0) {
            usleep($this->requestDelayMs * 1000);
        }
    }
}
