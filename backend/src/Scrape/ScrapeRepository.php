<?php

declare(strict_types=1);

namespace Piskari\Scrape;

use PDO;
use Piskari\Db\Db;
use Piskari\Support\Text;

/**
 * Persistence for scrape runs/jobs and idempotent upserts of catalogue rows.
 * Every catalogue entity is keyed by its piskari.cz URL.
 */
final class ScrapeRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Db::pdo();
    }

    // ----- runs & jobs -------------------------------------------------------

    public function activeRunId(): ?int
    {
        $stmt = $this->pdo->query("SELECT id FROM scrape_run WHERE status IN ('planned','running') ORDER BY id DESC LIMIT 1");
        $id = $stmt->fetchColumn();
        return $id === false ? null : (int) $id;
    }

    public function createRun(): int
    {
        $this->pdo->prepare("INSERT INTO scrape_run (status) VALUES ('planned')")->execute();
        return (int) $this->pdo->lastInsertId();
    }

    public function addJob(int $runId, string $areaName, string $sectorName, string $sectorUrl): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO scrape_job (run_id, area_name, sector_name, sector_url, status)
             VALUES (:run_id, :area_name, :sector_name, :sector_url, \'pending\')
             ON DUPLICATE KEY UPDATE sector_name = VALUES(sector_name), area_name = VALUES(area_name)'
        );
        $stmt->execute([
            ':run_id' => $runId,
            ':area_name' => $areaName,
            ':sector_name' => $sectorName,
            ':sector_url' => $sectorUrl,
        ]);
    }

    public function setRunSectorsTotal(int $runId, int $total): void
    {
        $stmt = $this->pdo->prepare('UPDATE scrape_run SET sectors_total = :total WHERE id = :id');
        $stmt->execute([':total' => $total, ':id' => $runId]);
    }

    public function setRunStatus(int $runId, string $status, ?string $error = null): void
    {
        $finished = in_array($status, ['done', 'failed'], true);
        $stmt = $this->pdo->prepare(
            'UPDATE scrape_run SET status = :status, error_message = :error,
             finished_at = ' . ($finished ? 'CURRENT_TIMESTAMP' : 'finished_at') . '
             WHERE id = :id'
        );
        $stmt->execute([':status' => $status, ':error' => $error, ':id' => $runId]);
    }

    /**
     * Re-queue jobs left in 'running' (e.g. by a crash) so they can be retried.
     */
    public function reclaimStaleJobs(int $runId): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE scrape_job SET status = 'pending'
             WHERE run_id = :id AND status = 'running'"
        );
        $stmt->execute([':id' => $runId]);
    }

    /**
     * @return array{id:int, area_name:string, sector_name:string, sector_url:string}|null
     */
    public function claimNextJob(int $runId): ?array
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                "SELECT id, area_name, sector_name, sector_url FROM scrape_job
                 WHERE run_id = :id AND status = 'pending'
                 ORDER BY id ASC LIMIT 1 FOR UPDATE"
            );
            $stmt->execute([':id' => $runId]);
            $job = $stmt->fetch();

            if ($job === false) {
                $this->pdo->commit();
                return null;
            }

            $update = $this->pdo->prepare(
                "UPDATE scrape_job SET status = 'running', started_at = CURRENT_TIMESTAMP WHERE id = :id"
            );
            $update->execute([':id' => (int) $job['id']]);
            $this->pdo->commit();

            return [
                'id' => (int) $job['id'],
                'area_name' => (string) $job['area_name'],
                'sector_name' => (string) $job['sector_name'],
                'sector_url' => (string) $job['sector_url'],
            ];
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function completeJob(int $jobId, int $rocks, int $routes): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE scrape_job SET status = 'done', rocks_count = :rocks, routes_count = :routes,
             error_message = NULL, finished_at = CURRENT_TIMESTAMP WHERE id = :id"
        );
        $stmt->execute([':rocks' => $rocks, ':routes' => $routes, ':id' => $jobId]);
    }

    public function failJob(int $jobId, string $error): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE scrape_job SET status = 'failed', error_message = :error,
             finished_at = CURRENT_TIMESTAMP WHERE id = :id"
        );
        $stmt->execute([':error' => $error, ':id' => $jobId]);
    }

    /**
     * Recompute run rollups from its jobs.
     */
    public function refreshRunCounts(int $runId): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE scrape_run r SET
               sectors_done = (SELECT COUNT(*) FROM scrape_job j WHERE j.run_id = r.id AND j.status IN ('done','failed')),
               rocks_count = (SELECT COALESCE(SUM(rocks_count),0) FROM scrape_job j WHERE j.run_id = r.id),
               routes_count = (SELECT COALESCE(SUM(routes_count),0) FROM scrape_job j WHERE j.run_id = r.id)
             WHERE r.id = :id"
        );
        $stmt->execute([':id' => $runId]);
    }

    public function pendingJobCount(int $runId): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM scrape_job WHERE run_id = :id AND status = 'pending'");
        $stmt->execute([':id' => $runId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getRun(int $runId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM scrape_run WHERE id = :id');
        $stmt->execute([':id' => $runId]);
        $run = $stmt->fetch();
        return $run === false ? null : $run;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listRuns(int $limit = 50): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM scrape_run ORDER BY id DESC LIMIT :limit');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listJobs(int $runId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM scrape_job WHERE run_id = :id ORDER BY id ASC');
        $stmt->execute([':id' => $runId]);
        return $stmt->fetchAll();
    }

    // ----- catalogue upserts -------------------------------------------------

    public function upsertArea(string $name, string $url): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO area (name, url) VALUES (:name, :url)
             ON DUPLICATE KEY UPDATE name = VALUES(name), id = LAST_INSERT_ID(id)'
        );
        $stmt->execute([':name' => $name, ':url' => $url]);
        return (int) $this->pdo->lastInsertId();
    }

    public function sectorIdByUrl(string $url): ?int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM sector WHERE url = :url');
        $stmt->execute([':url' => $url]);
        $id = $stmt->fetchColumn();
        return $id === false ? null : (int) $id;
    }

    public function upsertSector(int $areaId, string $name, string $url, ?string $season, ?string $restriction): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO sector (area_id, name, url, climbing_season, climbing_restriction)
             VALUES (:area_id, :name, :url, :season, :restriction)
             ON DUPLICATE KEY UPDATE area_id = VALUES(area_id), name = VALUES(name),
               climbing_season = VALUES(climbing_season), climbing_restriction = VALUES(climbing_restriction),
               id = LAST_INSERT_ID(id)'
        );
        $stmt->execute([
            ':area_id' => $areaId,
            ':name' => $name,
            ':url' => $url,
            ':season' => $season,
            ':restriction' => $restriction,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param array{gps_raw:?string, gps_lat:?float, gps_lon:?float} $gps
     */
    public function upsertRock(int $sectorId, string $name, string $url, string $areaName, string $subAreaName, array $gps): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO rock (sector_id, name, name_fold, url, area_name, sub_area_name, gps_raw, gps_lat, gps_lon)
             VALUES (:sector_id, :name, :name_fold, :url, :area_name, :sub_area_name, :gps_raw, :gps_lat, :gps_lon)
             ON DUPLICATE KEY UPDATE sector_id = VALUES(sector_id), name = VALUES(name), name_fold = VALUES(name_fold),
               area_name = VALUES(area_name), sub_area_name = VALUES(sub_area_name),
               gps_raw = VALUES(gps_raw), gps_lat = VALUES(gps_lat), gps_lon = VALUES(gps_lon),
               id = LAST_INSERT_ID(id)'
        );
        $stmt->execute([
            ':sector_id' => $sectorId,
            ':name' => $name,
            ':name_fold' => Text::fold($name),
            ':url' => $url,
            ':area_name' => $areaName,
            ':sub_area_name' => $subAreaName,
            ':gps_raw' => $gps['gps_raw'],
            ':gps_lat' => $gps['gps_lat'],
            ':gps_lon' => $gps['gps_lon'],
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param array{name:string, url:string, difficulty:?string, first_ascent_date:?string, first_ascent_raw:?string, stars:int, comments_count:int, has_photos:bool, sort_order:int} $route
     */
    public function upsertRoute(int $rockId, array $route): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO route (rock_id, name, name_fold, url, difficulty, first_ascent_date, first_ascent_raw,
               stars, comments_count, has_photos, sort_order)
             VALUES (:rock_id, :name, :name_fold, :url, :difficulty, :fa_date, :fa_raw,
               :stars, :comments, :photos, :sort_order)
             ON DUPLICATE KEY UPDATE rock_id = VALUES(rock_id), name = VALUES(name), name_fold = VALUES(name_fold),
               difficulty = VALUES(difficulty), first_ascent_date = VALUES(first_ascent_date),
               first_ascent_raw = VALUES(first_ascent_raw), stars = VALUES(stars),
               comments_count = VALUES(comments_count), has_photos = VALUES(has_photos),
               sort_order = VALUES(sort_order)'
        );
        $stmt->execute([
            ':rock_id' => $rockId,
            ':name' => $route['name'],
            ':name_fold' => Text::fold($route['name']),
            ':url' => $route['url'],
            ':difficulty' => $route['difficulty'],
            ':fa_date' => $route['first_ascent_date'],
            ':fa_raw' => $route['first_ascent_raw'],
            ':stars' => $route['stars'],
            ':comments' => $route['comments_count'],
            ':photos' => $route['has_photos'] ? 1 : 0,
            ':sort_order' => $route['sort_order'],
        ]);
    }
}
