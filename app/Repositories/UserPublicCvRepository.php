<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\SqlText;
use PDO;

/** Réglages de la page publique du joueur (table user_public_cv). */
class UserPublicCvRepository
{
    public const MIGRATION_FILE = 'migrations/2026_11_01_001_user_public_cv.sql';

    private ?bool $tableReady = null;

    public function __construct(private ?PDO $pdo = null)
    {
    }

    private function pdo(): PDO
    {
        return $this->pdo ??= Database::getPdo();
    }

    public function tableExists(): bool
    {
        if ($this->tableReady !== null) {
            return $this->tableReady;
        }
        try {
            $st = $this->pdo()->query(
                "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_public_cv' LIMIT 1"
            );
            $this->tableReady = (bool) ($st && $st->fetchColumn());
        } catch (\Throwable) {
            $this->tableReady = false;
        }

        return $this->tableReady;
    }

    /**
     * Crée la table au premier enregistrement d’un joueur si la mise à jour du schéma n’a pas encore eu lieu.
     * Exécute uniquement le fichier SQL de cette fonction (CREATE TABLE IF NOT EXISTS, idempotent).
     */
    public function ensureTable(): bool
    {
        if ($this->tableExists()) {
            return true;
        }
        $sql = @file_get_contents(dirname(__DIR__, 2) . '/' . self::MIGRATION_FILE);
        if (!is_string($sql) || trim($sql) === '') {
            return false;
        }
        try {
            $this->pdo()->exec($sql);
        } catch (\Throwable $e) {
            error_log('[user_public_cv] création de la table impossible : ' . $e->getMessage());

            return false;
        }
        $this->tableReady = null;

        return $this->tableExists();
    }

    /** @return array<string, mixed>|null */
    public function findByUserId(int $userId): ?array
    {
        if ($userId < 1 || !$this->tableExists()) {
            return null;
        }
        $st = $this->pdo()->prepare('SELECT * FROM user_public_cv WHERE user_id = ? LIMIT 1');
        $st->execute([$userId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed>|null */
    public function findBySlug(string $slug): ?array
    {
        if ($slug === '' || !$this->tableExists()) {
            return null;
        }
        $st = $this->pdo()->prepare('SELECT * FROM user_public_cv WHERE ' . SqlText::equals($this->pdo(), 'slug') . ' LIMIT 1');
        $st->execute([$slug]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function slugTakenByOther(string $slug, int $userId): bool
    {
        if ($slug === '' || !$this->tableExists()) {
            return false;
        }
        $st = $this->pdo()->prepare('SELECT 1 FROM user_public_cv WHERE ' . SqlText::equals($this->pdo(), 'slug') . ' AND user_id <> ? LIMIT 1');
        $st->execute([$slug, $userId]);

        return (bool) $st->fetchColumn();
    }

    public function save(int $userId, int $tenantId, bool $enabled, ?string $slug, string $bio, string $sectionsJson): void
    {
        if ($userId < 1 || $tenantId < 1 || !$this->tableExists()) {
            return;
        }
        $this->pdo()->prepare(
            'INSERT INTO user_public_cv (user_id, tenant_id, enabled, slug, bio, sections_json, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE tenant_id = VALUES(tenant_id), enabled = VALUES(enabled), slug = VALUES(slug),
                 bio = VALUES(bio), sections_json = VALUES(sections_json), updated_at = NOW()'
        )->execute([
            $userId,
            $tenantId,
            $enabled ? 1 : 0,
            $slug !== null && $slug !== '' ? $slug : null,
            $bio !== '' ? $bio : null,
            $sectionsJson,
        ]);
    }
}
