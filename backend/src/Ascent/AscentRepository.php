<?php

declare(strict_types=1);

namespace Piskari\Ascent;

use PDO;
use Piskari\Db\Db;

final class AscentRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Db::pdo();
    }

    /**
     * @param list<string> $protection
     */
    public function upsert(int $userId, int $routeId, ?int $routeStars, ?int $belayStars, array $protection, ?string $note): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO route_ascent (user_id, route_id, route_stars, belay_stars, protection_json, note)
             VALUES (:user_id, :route_id, :route_stars, :belay_stars, :protection, :note)
             ON DUPLICATE KEY UPDATE route_stars = VALUES(route_stars), belay_stars = VALUES(belay_stars),
               protection_json = VALUES(protection_json), note = VALUES(note)'
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':route_id' => $routeId,
            ':route_stars' => $routeStars,
            ':belay_stars' => $belayStars,
            ':protection' => json_encode($protection, JSON_UNESCAPED_UNICODE),
            ':note' => $note,
        ]);
    }

    public function delete(int $userId, int $routeId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM route_ascent WHERE user_id = :user_id AND route_id = :route_id');
        $stmt->execute([':user_id' => $userId, ':route_id' => $routeId]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function own(int $userId, int $routeId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT route_stars, belay_stars, protection_json, note
             FROM route_ascent WHERE user_id = :user_id AND route_id = :route_id'
        );
        $stmt->execute([':user_id' => $userId, ':route_id' => $routeId]);
        $row = $stmt->fetch();
        return $row === false ? null : $this->hydrate($row);
    }

    /**
     * All entries for a route, with the contributor's display name.
     *
     * @return list<array<string, mixed>>
     */
    public function listForRoute(int $routeId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT a.route_stars, a.belay_stars, a.protection_json, a.note, a.updated_at,
                    u.id AS user_id, u.name AS user_name
             FROM route_ascent a JOIN app_user u ON u.id = a.user_id
             WHERE a.route_id = :route_id
             ORDER BY a.updated_at DESC'
        );
        $stmt->execute([':route_id' => $routeId]);
        return array_map(fn (array $r): array => $this->hydrate($r), $stmt->fetchAll());
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function hydrate(array $row): array
    {
        $row['route_stars'] = $row['route_stars'] !== null ? (int) $row['route_stars'] : null;
        $row['belay_stars'] = $row['belay_stars'] !== null ? (int) $row['belay_stars'] : null;
        $decoded = $row['protection_json'] !== null ? json_decode((string) $row['protection_json'], true) : [];
        $row['protection'] = is_array($decoded) ? array_values($decoded) : [];
        unset($row['protection_json']);
        return $row;
    }
}
