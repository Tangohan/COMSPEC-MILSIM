<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;
use PDOException;

class UserUiTourRepository
{
    public const KEY_DASHBOARD = 'dashboard.v1';

    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function schemaReady(): bool
    {
        try {
            $st = $this->pdo->query(
                "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_ui_tours' LIMIT 1"
            );

            return (bool) $st?->fetchColumn();
        } catch (PDOException) {
            return false;
        }
    }

    public function isDismissed(int $userId, string $tourKey): bool
    {
        if ($userId < 1 || !$this->schemaReady()) {
            return false;
        }
        try {
            $st = $this->pdo->prepare(
                'SELECT 1 FROM user_ui_tours WHERE user_id = ? AND tour_key = ? AND dismissed_at IS NOT NULL LIMIT 1'
            );
            $st->execute([$userId, $tourKey]);

            return (bool) $st->fetchColumn();
        } catch (PDOException) {
            return false;
        }
    }

    public function dismiss(int $userId, string $tourKey, bool $completed = false): void
    {
        if ($userId < 1 || $tourKey === '' || !$this->schemaReady()) {
            return;
        }
        try {
            $st = $this->pdo->prepare(
                'INSERT INTO user_ui_tours (user_id, tour_key, dismissed_at, completed_at, created_at)
                 VALUES (?, ?, NOW(), IF(?, NOW(), NULL), NOW())
                 ON DUPLICATE KEY UPDATE
                    dismissed_at = NOW(),
                    completed_at = IF(?, NOW(), completed_at),
                    updated_at = NOW()'
            );
            $done = $completed ? 1 : 0;
            $st->execute([$userId, $tourKey, $done, $done]);
        } catch (PDOException) {
            // Table absente ou indisponible : le guide reste masquable côté écran.
        }
    }

    /**
     * Lignes dont la clé commence par $prefix (parcours enregistrés en plusieurs lignes, ex. accueil du profil).
     *
     * @return array<string, array{created_at: ?string, dismissed_at: ?string, completed_at: ?string}>|null null si la table manque
     */
    public function listByPrefix(int $userId, string $prefix): ?array
    {
        if ($userId < 1 || $prefix === '' || !$this->schemaReady()) {
            return null;
        }
        try {
            $st = $this->pdo->prepare(
                'SELECT tour_key, created_at, dismissed_at, completed_at FROM user_ui_tours WHERE user_id = ? AND tour_key LIKE ?'
            );
            $st->execute([$userId, addcslashes($prefix, '\%_') . '%']);
            $out = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $out[(string) $row['tour_key']] = [
                    'created_at' => $row['created_at'] !== null ? (string) $row['created_at'] : null,
                    'dismissed_at' => $row['dismissed_at'] !== null ? (string) $row['dismissed_at'] : null,
                    'completed_at' => $row['completed_at'] !== null ? (string) $row['completed_at'] : null,
                ];
            }

            return $out;
        } catch (PDOException) {
            return null;
        }
    }

    /**
     * Remplace toutes les lignes de $prefix par $rows (clé => dates), en une transaction.
     *
     * @param array<string, array{created_at?: ?string, completed_at?: ?string}> $rows
     * @return bool false si la table manque ou si l'écriture échoue
     */
    public function replacePrefix(int $userId, string $prefix, array $rows): bool
    {
        if ($userId < 1 || $prefix === '' || !$this->schemaReady()) {
            return false;
        }
        $started = false;
        try {
            if (!$this->pdo->inTransaction()) {
                $this->pdo->beginTransaction();
                $started = true;
            }
            $del = $this->pdo->prepare('DELETE FROM user_ui_tours WHERE user_id = ? AND tour_key LIKE ?');
            $del->execute([$userId, addcslashes($prefix, '\%_') . '%']);
            $ins = $this->pdo->prepare(
                'INSERT INTO user_ui_tours (user_id, tour_key, dismissed_at, completed_at, created_at)
                 VALUES (?, ?, NOW(), ?, COALESCE(?, NOW()))'
            );
            foreach ($rows as $key => $dates) {
                if (!str_starts_with($key, $prefix) || strlen($key) > 64) {
                    continue;
                }
                $ins->execute([$userId, $key, $dates['completed_at'] ?? null, $dates['created_at'] ?? null]);
            }
            if ($started) {
                $this->pdo->commit();
            }

            return true;
        } catch (PDOException) {
            if ($started && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            return false;
        }
    }
}
