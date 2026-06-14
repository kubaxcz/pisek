<?php

declare(strict_types=1);

namespace Piskari\Auth;

use PDO;
use Piskari\Db\Db;

final class AuthRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Db::pdo();
    }

    /**
     * @param array{sub:string, email:string, name:?string, picture:?string} $profile
     */
    public function upsertUser(array $profile): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO app_user (google_sub, email, name, picture, last_login_at)
             VALUES (:sub, :email, :name, :picture, CURRENT_TIMESTAMP)
             ON DUPLICATE KEY UPDATE email = VALUES(email), name = VALUES(name),
               picture = VALUES(picture), last_login_at = CURRENT_TIMESTAMP,
               id = LAST_INSERT_ID(id)'
        );
        $stmt->execute([
            ':sub' => $profile['sub'],
            ':email' => $profile['email'],
            ':name' => $profile['name'],
            ':picture' => $profile['picture'],
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function createSession(int $userId, string $token, int $ttlDays): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO user_session (user_id, token, expires_at)
             VALUES (:user_id, :token, DATE_ADD(CURRENT_TIMESTAMP, INTERVAL :ttl DAY))'
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':token', $token);
        $stmt->bindValue(':ttl', $ttlDays, PDO::PARAM_INT);
        $stmt->execute();
    }

    /**
     * @return array<string, mixed>|null  the joined user row for a live session
     */
    public function userBySession(string $token): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT u.id, u.email, u.name, u.picture
             FROM user_session s JOIN app_user u ON u.id = s.user_id
             WHERE s.token = :token AND s.expires_at > CURRENT_TIMESTAMP'
        );
        $stmt->execute([':token' => $token]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function deleteSession(string $token): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM user_session WHERE token = :token');
        $stmt->execute([':token' => $token]);
    }
}
