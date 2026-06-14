<?php

declare(strict_types=1);

namespace Piskari\Catalog;

use PDO;
use Piskari\Db\Db;
use Piskari\Support\Text;

final class CatalogRepository
{
    /** Per-route user-rating aggregate columns (expects alias rt for route). */
    private const AGG_COLUMNS =
        'COALESCE(agg.user_rating_count, 0) AS user_rating_count,
         agg.user_stars_avg AS user_stars_avg,
         agg.belay_stars_avg AS belay_stars_avg,
         COALESCE(agg.belay_count, 0) AS belay_count,
         COALESCE(agg.ascent_count, 0) AS ascent_count';

    private const AGG_JOIN =
        'LEFT JOIN (
            SELECT route_id,
                   COUNT(route_stars) AS user_rating_count,
                   AVG(route_stars) AS user_stars_avg,
                   AVG(belay_stars) AS belay_stars_avg,
                   SUM(CASE WHEN protection_json IS NOT NULL AND protection_json NOT IN (\'[]\', \'\') THEN 1 ELSE 0 END) AS belay_count,
                   COUNT(*) AS ascent_count
            FROM route_ascent GROUP BY route_id
         ) agg ON agg.route_id = rt.id';

    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Db::pdo();
    }

    /**
     * Areas with their sectors nested (for the public landing overview).
     *
     * @return list<array<string, mixed>>
     */
    public function areasWithSectors(): array
    {
        $areas = $this->pdo->query('SELECT id, name, url FROM area ORDER BY name ASC')->fetchAll();

        $sectorStmt = $this->pdo->query(
            'SELECT s.id, s.area_id, s.name, s.url, s.climbing_season, s.climbing_restriction,
                    (SELECT COUNT(*) FROM rock r WHERE r.sector_id = s.id) AS rock_count
             FROM sector s ORDER BY s.name ASC'
        );
        $sectorsByArea = [];
        foreach ($sectorStmt->fetchAll() as $sector) {
            $sector['rock_count'] = (int) $sector['rock_count'];
            $sectorsByArea[(int) $sector['area_id']][] = $sector;
        }

        foreach ($areas as &$area) {
            $area['sectors'] = $sectorsByArea[(int) $area['id']] ?? [];
        }
        unset($area);

        return $areas;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function sector(int $sectorId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT s.id, s.name, s.url, s.climbing_season, s.climbing_restriction,
                    a.id AS area_id, a.name AS area_name
             FROM sector s JOIN area a ON a.id = s.area_id WHERE s.id = :id'
        );
        $stmt->execute([':id' => $sectorId]);
        $sector = $stmt->fetch();
        return $sector === false ? null : $sector;
    }

    /**
     * Flat list of all routes in a sector (joined across rocks).
     *
     * @return list<array<string, mixed>>
     */
    public function routesInSector(int $sectorId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT rt.id, rt.name, rt.url, rt.difficulty, rt.first_ascent_date, rt.first_ascent_raw,
                    rt.stars, rt.comments_count, rt.has_photos,
                    rk.id AS rock_id, rk.name AS rock_name,
                    ' . self::AGG_COLUMNS . '
             FROM route rt
             JOIN rock rk ON rk.id = rt.rock_id
             ' . self::AGG_JOIN . '
             WHERE rk.sector_id = :id
             ORDER BY rk.name ASC, rt.sort_order ASC, rt.name ASC'
        );
        $stmt->execute([':id' => $sectorId]);
        return $this->castRoutes($stmt->fetchAll());
    }

    /**
     * @return array<string, mixed>|null
     */
    public function rock(int $rockId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT rk.id, rk.name, rk.url, rk.area_name, rk.sub_area_name,
                    rk.gps_raw, rk.gps_lat, rk.gps_lon,
                    rk.sector_id, s.name AS sector_name
             FROM rock rk JOIN sector s ON s.id = rk.sector_id WHERE rk.id = :id'
        );
        $stmt->execute([':id' => $rockId]);
        $rock = $stmt->fetch();
        if ($rock === false) {
            return null;
        }

        $rock['gps_lat'] = $rock['gps_lat'] !== null ? (float) $rock['gps_lat'] : null;
        $rock['gps_lon'] = $rock['gps_lon'] !== null ? (float) $rock['gps_lon'] : null;

        $routeStmt = $this->pdo->prepare(
            'SELECT rt.id, rt.name, rt.url, rt.difficulty, rt.first_ascent_date, rt.first_ascent_raw,
                    rt.stars, rt.comments_count, rt.has_photos,
                    ' . self::AGG_COLUMNS . '
             FROM route rt ' . self::AGG_JOIN . '
             WHERE rt.rock_id = :id ORDER BY rt.sort_order ASC, rt.name ASC'
        );
        $routeStmt->execute([':id' => $rockId]);
        $rock['routes'] = $this->castRoutes($routeStmt->fetchAll());

        return $rock;
    }

    /**
     * Diacritics-insensitive search of rocks and routes by name.
     *
     * @return array{rocks: list<array<string,mixed>>, routes: list<array<string,mixed>>}
     */
    public function search(string $query, int $limit = 30): array
    {
        $needle = Text::fold($query);
        if ($needle === '') {
            return ['rocks' => [], 'routes' => []];
        }
        $like = '%' . $needle . '%';

        $rockStmt = $this->pdo->prepare(
            'SELECT rk.id, rk.name, rk.url, rk.area_name, rk.sub_area_name, rk.sector_id
             FROM rock rk WHERE rk.name_fold LIKE :like ORDER BY rk.name ASC LIMIT :limit'
        );
        $rockStmt->bindValue(':like', $like);
        $rockStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $rockStmt->execute();

        $routeStmt = $this->pdo->prepare(
            'SELECT rt.id, rt.name, rt.url, rt.difficulty, rt.stars, rt.comments_count, rt.has_photos,
                    rk.id AS rock_id, rk.name AS rock_name, rk.area_name, rk.sub_area_name,
                    ' . self::AGG_COLUMNS . '
             FROM route rt JOIN rock rk ON rk.id = rt.rock_id
             ' . self::AGG_JOIN . '
             WHERE rt.name_fold LIKE :like ORDER BY rt.name ASC LIMIT :limit'
        );
        $routeStmt->bindValue(':like', $like);
        $routeStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $routeStmt->execute();

        return [
            'rocks' => $rockStmt->fetchAll(),
            'routes' => $this->castRoutes($routeStmt->fetchAll()),
        ];
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function castRoutes(array $rows): array
    {
        foreach ($rows as &$row) {
            $row['stars'] = (int) $row['stars'];
            $row['comments_count'] = (int) $row['comments_count'];
            $row['has_photos'] = (bool) $row['has_photos'];
            if (array_key_exists('user_rating_count', $row)) {
                $row['user_rating_count'] = (int) $row['user_rating_count'];
                $row['belay_count'] = (int) $row['belay_count'];
                $row['ascent_count'] = (int) $row['ascent_count'];
                $row['user_stars_avg'] = $row['user_stars_avg'] !== null ? round((float) $row['user_stars_avg'], 1) : null;
                $row['belay_stars_avg'] = $row['belay_stars_avg'] !== null ? round((float) $row['belay_stars_avg'], 1) : null;
            }
        }
        unset($row);
        return $rows;
    }
}
