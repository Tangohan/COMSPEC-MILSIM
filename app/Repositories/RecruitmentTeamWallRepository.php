<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Messages internes partagés entre recruteurs d’une même communauté (hors dossiers individuels).
 */
final class RecruitmentTeamWallRepository
{
    private PDO $pdo;

    private static ?bool $tableReady = null;

    private static ?bool $extendedSchemaReady = null;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function tableExists(): bool
    {
        if (self::$tableReady === null) {
            $stmt = $this->pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'recruitment_team_wall_entries' LIMIT 1");
            self::$tableReady = (bool) $stmt?->fetchColumn();
        }

        return self::$tableReady;
    }

    /**
     * Colonnes post_kind + subject (migration bootstrap/recruitment_team_wall_kind_subject_migration.php).
     */
    public function extendedSchemaExists(): bool
    {
        if (self::$extendedSchemaReady !== null) {
            return self::$extendedSchemaReady;
        }
        if (!$this->tableExists()) {
            self::$extendedSchemaReady = false;

            return false;
        }
        $stmt = $this->pdo->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'recruitment_team_wall_entries' AND COLUMN_NAME = 'post_kind' LIMIT 1");
        self::$extendedSchemaReady = (bool) $stmt?->fetchColumn();

        return self::$extendedSchemaReady;
    }

    /**
     * @return array<string, string> code => libellé court
     */
    public static function postKindLabels(): array
    {
        return [
            'general' => 'Général',
            'consigne' => 'Consigne & procédure',
            'planning' => 'Planning & vacations',
            'veille' => 'Veille & outils',
            'idee' => 'Idée & amélioration',
            'annonce' => 'Annonce importante',
        ];
    }

    public static function defaultPostKind(): string
    {
        return 'general';
    }

    public static function isValidPostKind(string $kind): bool
    {
        return array_key_exists($kind, self::postKindLabels());
    }

    /**
     * @return array<string, int>
     */
    public function countForTenant(int $tenantId): int
    {
        if (!$this->tableExists() || $tenantId < 1) {
            return 0;
        }
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM recruitment_team_wall_entries WHERE tenant_id = ?');
        $stmt->execute([$tenantId]);

        return (int) $stmt->fetchColumn();
    }

    public function countsByKindForTenant(int $tenantId): array
    {
        if (!$this->extendedSchemaExists() || $tenantId < 1) {
            return [];
        }
        $stmt = $this->pdo->prepare(
            'SELECT post_kind, COUNT(*) AS c FROM recruitment_team_wall_entries WHERE tenant_id = ? GROUP BY post_kind'
        );
        $stmt->execute([$tenantId]);
        $out = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $k = (string) ($row['post_kind'] ?? '');
            if ($k !== '') {
                $out[$k] = (int) ($row['c'] ?? 0);
            }
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listRecent(int $tenantId, int $limit = 100, ?string $postKindFilter = null, string $order = 'desc'): array
    {
        if (!$this->tableExists() || $tenantId < 1) {
            return [];
        }
        $limit = max(1, min(200, $limit));
        $order = strtolower($order) === 'asc' ? 'ASC' : 'DESC';
        $idOrder = $order === 'ASC' ? 'ASC' : 'DESC';

        if ($this->extendedSchemaExists()) {
            $filter = $postKindFilter !== null && $postKindFilter !== '' && self::isValidPostKind($postKindFilter);
            $sql = 'SELECT id, tenant_id, actor_user_id, post_kind, subject, body, created_at
             FROM recruitment_team_wall_entries
             WHERE tenant_id = ?';
            $params = [$tenantId];
            if ($filter) {
                $sql .= ' AND post_kind = ?';
                $params[] = $postKindFilter;
            }
            $sql .= ' ORDER BY created_at ' . $order . ', id ' . $idOrder . ' LIMIT ' . (int) $limit;
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        $stmt = $this->pdo->prepare(
            'SELECT id, tenant_id, actor_user_id, body, created_at
             FROM recruitment_team_wall_entries
             WHERE tenant_id = ?
             ORDER BY created_at ' . $order . ', id ' . $idOrder . ' LIMIT ' . (int) $limit
        );
        $stmt->execute([$tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function create(int $tenantId, int $actorUserId, string $body, string $postKind = 'general', ?string $subject = null): bool
    {
        if (!$this->tableExists() || $tenantId < 1 || $actorUserId < 1) {
            return false;
        }
        $body = trim($body);
        if ($body === '') {
            return false;
        }
        if (mb_strlen($body) > 4000) {
            $body = mb_substr($body, 0, 4000);
        }
        if (!self::isValidPostKind($postKind)) {
            $postKind = self::defaultPostKind();
        }
        $subject = $subject !== null ? trim($subject) : '';
        if ($subject !== '' && mb_strlen($subject) > 200) {
            $subject = mb_substr($subject, 0, 200);
        }
        $subjectDb = $subject === '' ? null : $subject;

        if ($this->extendedSchemaExists()) {
            $stmt = $this->pdo->prepare(
                'INSERT INTO recruitment_team_wall_entries (tenant_id, actor_user_id, post_kind, subject, body) VALUES (?, ?, ?, ?, ?)'
            );

            return $stmt->execute([$tenantId, $actorUserId, $postKind, $subjectDb, $body]);
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO recruitment_team_wall_entries (tenant_id, actor_user_id, body) VALUES (?, ?, ?)'
        );

        return $stmt->execute([$tenantId, $actorUserId, $body]);
    }
}
