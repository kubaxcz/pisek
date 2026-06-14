<?php

declare(strict_types=1);

/**
 * CLI runner for a full crawl. Plans a run, then drives it to completion one
 * sector at a time. Safe to interrupt: re-running resumes the active run.
 *
 * Usage:
 *   php bin/scrape.php          # resume the active run, or plan a new one
 *   php bin/scrape.php --new    # fail if a run is already active (plan fresh)
 */

require_once __DIR__ . '/../src/bootstrap.php';

use Piskari\Scrape\ScrapeService;

$service = new ScrapeService();

$runId = $service->activeRunId();
if ($runId === null) {
    fwrite(STDOUT, "Planning new scrape run...\n");
    $runId = $service->planRun();
    fwrite(STDOUT, "Planned run #{$runId}.\n");
} else {
    if (in_array('--new', $argv, true)) {
        fwrite(STDERR, "A run (#{$runId}) is already active. Aborting.\n");
        exit(1);
    }
    fwrite(STDOUT, "Resuming active run #{$runId}.\n");
}

while (true) {
    $result = $service->step($runId);
    $run = $result['run'];
    $job = $result['job'];

    if ($job !== null) {
        fwrite(STDOUT, sprintf(
            "[%d/%d] %s — %s (rocks: %s, routes: %s)\n",
            (int) ($run['sectors_done'] ?? 0),
            (int) ($run['sectors_total'] ?? 0),
            (string) $job['sector_name'],
            (string) $job['status'],
            (string) ($job['rocks_count'] ?? '-'),
            (string) ($job['routes_count'] ?? '-')
        ));
    }

    if ($result['done']) {
        fwrite(STDOUT, sprintf(
            "Done. Sectors: %d, rocks: %d, routes: %d.\n",
            (int) ($run['sectors_total'] ?? 0),
            (int) ($run['rocks_count'] ?? 0),
            (int) ($run['routes_count'] ?? 0)
        ));
        break;
    }
}
