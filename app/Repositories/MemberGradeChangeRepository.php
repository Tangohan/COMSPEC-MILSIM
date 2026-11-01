<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Historique des changements de grade (table member_grade_changes).
 *
 * Voie officielle : étape member_grade_changes de la file active, lancée par run-migrations.php
 * (docs/technical/migrations-active-queue.md). ensureTable() reste un filet de sécurité idempotent.
 */
class MemberGradeChangeRepository
{
    public const MIGRATION_FILE = 'migrations/2026_11_01_002_member_grade_changes.sql';

    public const SOURCE_MANUAL = 'manual';
    public const SOURCE_ELEVATION = 'elevation';

    /** Origine appliquée aux changements enregistrés pendant withSource(). */
    private static ?string $currentSource = null;

    private ?bool $tableReady = null;

    public function __construct(private ?PDO $pdo = null)
    {
    }

    private function pdo(): PDO
    {
        return $this->pdo ??= Database::getPdo();
    }

    /**
     * Exécute $fn en marquant les changements de grade enregistrés comme venant de $source
     * (ex. élévation validée). En dehors, l'origine par défaut est « modification manuelle ».
     *
     * @template T
     * @param callable(): T $fn
     * @return T
     */
    public static function withSource(string $source, callable $fn): mixed
    {
        $previous = self::$currentSource;
        self::$currentSource = $source;
        try {
            return $fn();
        } finally {
            self::$currentSource = $previous;
        }
    }

    public static function currentSource(): string
    {
        return self::$currentSource ?? self::SOURCE_MANUAL;
    }

    public function tableExists(): bool
    {
        if ($this->tableReady !== null) {
            return $this->tableReady;
        }
        try {
            if ($this->pdo()->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
                $st = $this->pdo()->query("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'member_grade_changes'");
            } else {
                $st = $this->pdo()->query(
                    "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'member_grade_changes' LIMIT 1"
                );
            }
            $this->tableReady = (bool) ($st && $st->fetchColumn());
        } catch (\Throwable) {
            $this->tableReady = false;
        }

        return $this->tableReady;
    }

    /**
     * Crée la table au premier changement de grade si la mise à jour du schéma n’a pas encore eu lieu.
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
            error_log('[member_grade_changes] création de la table impossible : ' . $e->getMessage());

            return false;
        }
        $this->tableReady = null;

        return $this->tableExists();
    }

    /**
     * Enregistre un changement de grade réel (rien si le grade est identique).
     */
    public function record(
        int $tenantId,
        int $userId,
        ?int $fromGradeId,
        ?int $toGradeId,
        ?string $fromLabel,
        ?string $toLabel,
        ?int $actorUserId,
        ?string $source = null,
        ?string $changedAt = null
    ): bool {
        $fromGradeId = $fromGradeId !== null && $fromGradeId > 0 ? $fromGradeId : null;
        $toGradeId = $toGradeId !== null && $toGradeId > 0 ? $toGradeId : null;
        if ($tenantId < 1 || $userId < 1 || $fromGradeId === $toGradeId || !$this->ensureTable()) {
            return false;
        }
        $source = in_array($source ?? self::currentSource(), [self::SOURCE_MANUAL, self::SOURCE_ELEVATION], true)
            ? ($source ?? self::currentSource())
            : self::SOURCE_MANUAL;
        $clip = static fn (?string $v): ?string => ($v = trim((string) $v)) === '' ? null : mb_substr($v, 0, 150);
        $st = $this->pdo()->prepare(
            'INSERT INTO member_grade_changes
                (tenant_id, user_id, from_grade_id, to_grade_id, from_label, to_label, source, actor_user_id, changed_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        return $st->execute([
            $tenantId,
            $userId,
            $fromGradeId,
            $toGradeId,
            $clip($fromLabel),
            $clip($toLabel),
            $source,
            $actorUserId !== null && $actorUserId > 0 ? $actorUserId : null,
            $changedAt ?? date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Changements de grade d’un membre dans une communauté, du plus ancien au plus récent.
     *
     * @return list<array<string, mixed>>
     */
    public function listForUser(int $tenantId, int $userId): array
    {
        if ($tenantId < 1 || $userId < 1 || !$this->tableExists()) {
            return [];
        }
        $st = $this->pdo()->prepare(
            'SELECT c.*, a.display_name AS actor_display_name
             FROM member_grade_changes c
             LEFT JOIN users a ON a.id = c.actor_user_id
             WHERE c.tenant_id = ? AND c.user_id = ?
             ORDER BY c.changed_at ASC, c.id ASC'
        );
        $st->execute([$tenantId, $userId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
