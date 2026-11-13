<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\LazyDatabaseConnection;
use PDO;

/**
 * Kits proposés par rôle (fonction / emploi de la communauté) ou par unité : les kits de départ. Chaque kit est
 * publié comme tenue d’arsenal (sauf ceux réservés à une unité, que les membres copient dans leurs tenues).
 */
final class GameRoleKitRepository
{
    use LazyDatabaseConnection;

    /** Origines d'un kit : de zéro, tenue remontée du jeu, assistant de création, configurateur d'équipement. */
    public const SOURCES = ['scratch', 'game_loadout', 'assistant', 'configurator'];

    private static ?bool $ready = null;

    private static ?bool $audienceReady = null;

    private static ?bool $targetsReady = null;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    public function tableReady(): bool
    {
        if (self::$ready === true) {
            return true;
        }
        try {
            $st = $this->pdo()->query(
                "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'game_role_kits' LIMIT 1"
            );
            $ok = (bool) ($st && $st->fetchColumn());
        } catch (\Throwable) {
            $ok = false;
        }
        if ($ok) {
            self::$ready = true;
        }

        return $ok;
    }

    /**
     * Colonnes unit_id et source présentes (étape equipment_starter_kits de run-migrations) : sans elles les kits
     * restent proposés par rôle ou à tous, comme avant.
     */
    public function audienceReady(): bool
    {
        if (self::$audienceReady === true) {
            return true;
        }
        try {
            $st = $this->pdo()->query(
                "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'game_role_kits' AND COLUMN_NAME IN ('unit_id', 'source')"
            );
            $ok = $st && (int) $st->fetchColumn() === 2;
        } catch (\Throwable) {
            $ok = false;
        }
        if ($ok) {
            self::$audienceReady = true;
        }

        return $ok;
    }

    /**
     * Colonnes member_user_id et qualification_id présentes (étape equipment_configurator) : un kit peut alors être
     * attribué à un membre précis ou aux titulaires d'une qualification.
     */
    public function targetsReady(): bool
    {
        if (self::$targetsReady === true) {
            return true;
        }
        if (!$this->audienceReady()) {
            return false;
        }
        try {
            $st = $this->pdo()->query(
                "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'game_role_kits' AND COLUMN_NAME IN ('member_user_id', 'qualification_id')"
            );
            $ok = $st && (int) $st->fetchColumn() === 2;
        } catch (\Throwable) {
            $ok = false;
        }
        if ($ok) {
            self::$targetsReady = true;
        }

        return $ok;
    }

    /**
     * Qualifications valides (ou bientôt expirées) d'un membre : définitions de personnel_qualifications.
     *
     * @return list<int>
     */
    public function qualificationIdsForUser(int $tenantId, int $userId): array
    {
        if ($userId < 1) {
            return [];
        }
        try {
            $st = $this->pdo()->prepare(
                "SELECT DISTINCT definition_id FROM personnel_qualifications
                 WHERE user_id = ? AND (tenant_id = ? OR tenant_id IS NULL) AND definition_id IS NOT NULL
                   AND status IN ('valid', 'expiring')"
            );
            $st->execute([$userId, $tenantId]);

            return array_values(array_filter(array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []), static fn (int $id): bool => $id > 0));
        } catch (\Throwable) {
            return [];
        }
    }

    /** Condition SQL « kit proposé à tous » selon les colonnes présentes. */
    private function everyoneSql(): string
    {
        if ($this->targetsReady()) {
            return '(job_role_id IS NULL AND unit_id IS NULL AND member_user_id IS NULL AND qualification_id IS NULL)';
        }

        return $this->audienceReady() ? '(job_role_id IS NULL AND unit_id IS NULL)' : 'job_role_id IS NULL';
    }

    /** @return list<array<string, mixed>> */
    public function listForTenant(int $tenantId): array
    {
        if (!$this->tableReady()) {
            return [];
        }
        $st = $this->pdo()->prepare(
            'SELECT * FROM game_role_kits WHERE tenant_id = ? ORDER BY job_role_id IS NOT NULL, name ASC, id ASC'
        );
        $st->execute([$tenantId]);

        return array_map([$this, 'map'], $st->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    /**
     * Kits proposés à un membre : ceux de ses rôles, ceux de ses unités (et des unités qui les contiennent), plus les
     * kits « tous les rôles » sans unité.
     *
     * @param list<int> $roleIds
     * @param list<int> $unitIds unités du membre et de leurs parents
     * @param int $memberUserId membre (kits qui lui sont attribués personnellement), 0 = aucun
     * @param list<int> $qualificationIds qualifications valides du membre
     * @return list<array<string, mixed>>
     */
    public function listForRoles(int $tenantId, array $roleIds, array $unitIds = [], int $memberUserId = 0, array $qualificationIds = []): array
    {
        if (!$this->tableReady()) {
            return [];
        }
        $roleIds = array_values(array_unique(array_filter(array_map('intval', $roleIds), static fn (int $id): bool => $id > 0)));
        $unitIds = array_values(array_unique(array_filter(array_map('intval', $unitIds), static fn (int $id): bool => $id > 0)));
        $qualificationIds = array_values(array_unique(array_filter(array_map('intval', $qualificationIds), static fn (int $id): bool => $id > 0)));
        $audience = $this->audienceReady();
        $everyone = $this->everyoneSql();
        $sql = 'SELECT * FROM game_role_kits WHERE tenant_id = ? AND (' . $everyone;
        $params = [$tenantId];
        if ($roleIds !== []) {
            $sql .= ' OR job_role_id IN (' . implode(', ', array_fill(0, count($roleIds), '?')) . ')';
            $params = array_merge($params, $roleIds);
        }
        if ($audience && $unitIds !== []) {
            $sql .= ' OR unit_id IN (' . implode(', ', array_fill(0, count($unitIds), '?')) . ')';
            $params = array_merge($params, $unitIds);
        }
        $targets = $this->targetsReady();
        if ($targets && $memberUserId > 0) {
            $sql .= ' OR member_user_id = ?';
            $params[] = $memberUserId;
        }
        if ($targets && $qualificationIds !== []) {
            $sql .= ' OR qualification_id IN (' . implode(', ', array_fill(0, count($qualificationIds), '?')) . ')';
            $params = array_merge($params, $qualificationIds);
        }
        // Kit attribué personnellement d'abord, puis les plus ciblés, enfin ceux proposés à tous.
        $sql .= ') ORDER BY ' . ($targets ? 'member_user_id IS NULL, ' : '') . $everyone . ', name ASC, id ASC';
        $st = $this->pdo()->prepare($sql);
        $st->execute($params);

        return array_map([$this, 'map'], $st->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    /** @return array<string, mixed>|null */
    public function find(int $tenantId, int $id): ?array
    {
        if ($id < 1 || !$this->tableReady()) {
            return null;
        }
        $st = $this->pdo()->prepare('SELECT * FROM game_role_kits WHERE tenant_id = ? AND id = ? LIMIT 1');
        $st->execute([$tenantId, $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->map($row) : null;
    }

    /**
     * @param array{job_role_id: int|null, name: string, description: string|null, slots: array<string, mixed>, payload_text: string, required_mods: list<string>, unit_id?: int|null, source?: string} $data
     */
    public function create(int $tenantId, int $userId, array $data): int
    {
        $audience = $this->audienceReady();
        $targets = $this->targetsReady();
        $st = $this->pdo()->prepare(
            'INSERT INTO game_role_kits
                (tenant_id, job_role_id, ' . ($audience ? 'unit_id, source, ' : '') . ($targets ? 'member_user_id, qualification_id, ' : '') . 'name, description, slots_json, payload_text, required_mods_json, created_by, updated_by, created_at, updated_at)
             VALUES (?, ?, ' . ($audience ? '?, ?, ' : '') . ($targets ? '?, ?, ' : '') . '?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $values = [$tenantId, $data['job_role_id']];
        if ($audience) {
            $values[] = $data['unit_id'] ?? null;
            $values[] = self::source($data['source'] ?? 'scratch');
        }
        if ($targets) {
            $values[] = $data['member_user_id'] ?? null;
            $values[] = $data['qualification_id'] ?? null;
        }
        array_push(
            $values,
            $data['name'],
            $data['description'],
            self::json($data['slots']),
            $data['payload_text'],
            self::json($data['required_mods']),
            $userId > 0 ? $userId : null,
            $userId > 0 ? $userId : null,
        );
        $st->execute($values);

        return (int) $this->pdo()->lastInsertId();
    }

    /**
     * @param array{job_role_id: int|null, name: string, description: string|null, slots: array<string, mixed>, payload_text: string, required_mods: list<string>, unit_id?: int|null} $data
     */
    public function update(int $tenantId, int $id, int $userId, array $data): bool
    {
        $unit = $this->audienceReady() && array_key_exists('unit_id', $data);
        $targets = $unit && $this->targetsReady() && array_key_exists('member_user_id', $data);
        $st = $this->pdo()->prepare(
            'UPDATE game_role_kits SET job_role_id = ?, ' . ($unit ? 'unit_id = ?, ' : '') . ($targets ? 'member_user_id = ?, qualification_id = ?, ' : '') . 'name = ?, description = ?, slots_json = ?, payload_text = ?,
                    required_mods_json = ?, updated_by = ?, updated_at = NOW()
             WHERE tenant_id = ? AND id = ?'
        );
        $values = [$data['job_role_id']];
        if ($unit) {
            $values[] = $data['unit_id'];
        }
        if ($targets) {
            $values[] = $data['member_user_id'];
            $values[] = $data['qualification_id'] ?? null;
        }
        array_push(
            $values,
            $data['name'],
            $data['description'],
            self::json($data['slots']),
            $data['payload_text'],
            self::json($data['required_mods']),
            $userId > 0 ? $userId : null,
            $tenantId,
            $id,
        );
        $st->execute($values);

        return $st->rowCount() > 0;
    }

    /** Change à qui le kit est proposé : une unité, un rôle, un membre, une qualification, ou tous (tout à null). */
    public function setAudience(int $tenantId, int $id, ?int $unitId, ?int $jobRoleId, int $userId, ?int $memberUserId = null, ?int $qualificationId = null): bool
    {
        if (!$this->audienceReady()) {
            return false;
        }
        if ($this->targetsReady()) {
            $st = $this->pdo()->prepare(
                'UPDATE game_role_kits SET unit_id = ?, job_role_id = ?, member_user_id = ?, qualification_id = ?, updated_by = ?, updated_at = NOW() WHERE tenant_id = ? AND id = ?'
            );
            $st->execute([$unitId, $jobRoleId, $memberUserId, $qualificationId, $userId > 0 ? $userId : null, $tenantId, $id]);

            return $st->rowCount() > 0;
        }
        $st = $this->pdo()->prepare(
            'UPDATE game_role_kits SET unit_id = ?, job_role_id = ?, updated_by = ?, updated_at = NOW() WHERE tenant_id = ? AND id = ?'
        );
        $st->execute([$unitId, $jobRoleId, $userId > 0 ? $userId : null, $tenantId, $id]);

        return $st->rowCount() > 0;
    }

    public function setWardrobe(int $tenantId, int $id, ?int $wardrobeId): void
    {
        $this->pdo()->prepare('UPDATE game_role_kits SET wardrobe_id = ? WHERE tenant_id = ? AND id = ?')
            ->execute([$wardrobeId !== null && $wardrobeId > 0 ? $wardrobeId : null, $tenantId, $id]);
    }

    public function delete(int $tenantId, int $id): bool
    {
        $st = $this->pdo()->prepare('DELETE FROM game_role_kits WHERE tenant_id = ? AND id = ?');
        $st->execute([$tenantId, $id]);

        return $st->rowCount() > 0;
    }

    private static function json(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
    }

    private static function source(mixed $source): string
    {
        $source = is_string($source) ? $source : '';

        return in_array($source, self::SOURCES, true) ? $source : 'scratch';
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function map(array $row): array
    {
        $slots = json_decode((string) ($row['slots_json'] ?? ''), true);
        $mods = json_decode((string) ($row['required_mods_json'] ?? ''), true);

        return [
            'id' => (int) ($row['id'] ?? 0),
            'tenant_id' => (int) ($row['tenant_id'] ?? 0),
            'job_role_id' => isset($row['job_role_id']) && (int) $row['job_role_id'] > 0 ? (int) $row['job_role_id'] : null,
            'unit_id' => isset($row['unit_id']) && (int) $row['unit_id'] > 0 ? (int) $row['unit_id'] : null,
            'member_user_id' => isset($row['member_user_id']) && (int) $row['member_user_id'] > 0 ? (int) $row['member_user_id'] : null,
            'qualification_id' => isset($row['qualification_id']) && (int) $row['qualification_id'] > 0 ? (int) $row['qualification_id'] : null,
            'source' => self::source($row['source'] ?? 'scratch'),
            'name' => (string) ($row['name'] ?? ''),
            'description' => (string) ($row['description'] ?? ''),
            'slots' => is_array($slots) ? $slots : [],
            'payload_text' => (string) ($row['payload_text'] ?? ''),
            'required_mods' => is_array($mods) ? array_values(array_map('strval', $mods)) : [],
            'wardrobe_id' => isset($row['wardrobe_id']) && (int) $row['wardrobe_id'] > 0 ? (int) $row['wardrobe_id'] : null,
            'created_by' => isset($row['created_by']) ? (int) $row['created_by'] : null,
            'updated_by' => isset($row['updated_by']) ? (int) $row['updated_by'] : null,
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }
}
