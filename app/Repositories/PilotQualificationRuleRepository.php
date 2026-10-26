<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;
use Throwable;

/**
 * Règles du cursus pilote (pilot_qualification_rules) et données qu'elles lisent : heures de vol du mod
 * (atak_pilot_time) et attributions des qualifications concernées (personnel_qualifications).
 */
final class PilotQualificationRuleRepository
{
    private ?PDO $pdo;
    private static ?bool $ready = null;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    private function pdo(): PDO
    {
        return $this->pdo ??= Database::getPdo();
    }

    public function schemaReady(): bool
    {
        if (self::$ready === null) {
            try {
                $st = $this->pdo()->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pilot_qualification_rules' LIMIT 1");
                self::$ready = $st !== false && (bool) $st->fetchColumn();
            } catch (Throwable) {
                self::$ready = false;
            }
        }

        return self::$ready;
    }

    /**
     * Règles de la communauté avec le nom de la qualification (les qualifications archivées sont écartées).
     *
     * @return list<array<string, mixed>>
     */
    public function listForTenant(int $tenantId, bool $enabledOnly = false): array
    {
        if ($tenantId < 1 || !$this->schemaReady()) {
            return [];
        }
        $st = $this->pdo()->prepare(
            'SELECT r.*, d.name AS definition_name, d.code AS definition_code, d.short_name AS definition_short_name,
                    p.name AS prereq_name
             FROM pilot_qualification_rules r
             JOIN personnel_qualification_definitions d ON d.id = r.definition_id AND d.tenant_id = r.tenant_id
             LEFT JOIN personnel_qualification_definitions p ON p.id = r.prereq_definition_id
             WHERE r.tenant_id = ? AND d.archived_at IS NULL' . ($enabledOnly ? ' AND r.is_enabled = 1' : '') . '
             ORDER BY r.track ASC, r.sort_order ASC, r.id ASC'
        );
        $st->execute([$tenantId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<string, mixed>|null */
    public function find(int $tenantId, int $id): ?array
    {
        if (!$this->schemaReady()) {
            return null;
        }
        $st = $this->pdo()->prepare('SELECT * FROM pilot_qualification_rules WHERE tenant_id = ? AND id = ? LIMIT 1');
        $st->execute([$tenantId, $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** @return array<string, mixed>|null */
    public function findByDefinition(int $tenantId, int $definitionId): ?array
    {
        if (!$this->schemaReady()) {
            return null;
        }
        $st = $this->pdo()->prepare('SELECT * FROM pilot_qualification_rules WHERE tenant_id = ? AND definition_id = ? LIMIT 1');
        $st->execute([$tenantId, $definitionId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(int $tenantId, array $data): int
    {
        $st = $this->pdo()->prepare(
            'INSERT INTO pilot_qualification_rules
                (tenant_id, definition_id, track, tier, scope, scope_label, min_scope_hours, min_track_hours,
                 prereq_definition_id, award_mode, validator_tier, is_enabled, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)'
        );
        $st->execute([
            $tenantId,
            (int) $data['definition_id'],
            (string) $data['track'],
            (string) $data['tier'],
            (string) $data['scope'],
            ($data['scope_label'] ?? '') !== '' ? mb_substr((string) $data['scope_label'], 0, 120) : null,
            (float) ($data['min_scope_hours'] ?? 0),
            (float) ($data['min_track_hours'] ?? 0),
            ($data['prereq_definition_id'] ?? null) ? (int) $data['prereq_definition_id'] : null,
            (string) ($data['award_mode'] ?? 'propose'),
            ($data['validator_tier'] ?? '') !== '' ? (string) $data['validator_tier'] : null,
            (int) ($data['sort_order'] ?? 0),
        ]);

        return (int) $this->pdo()->lastInsertId();
    }

    public function updateSettings(int $tenantId, int $id, float $minScope, float $minTrack, string $mode, bool $enabled): bool
    {
        $st = $this->pdo()->prepare(
            'UPDATE pilot_qualification_rules
             SET min_scope_hours = ?, min_track_hours = ?, award_mode = ?, is_enabled = ?, updated_at = NOW()
             WHERE tenant_id = ? AND id = ?'
        );

        return $st->execute([$minScope, $minTrack, $mode, $enabled ? 1 : 0, $tenantId, $id]);
    }

    /**
     * Heures de vol de toute la communauté, groupées par membre.
     *
     * @return array<int, list<array<string, mixed>>>
     */
    public function pilotRowsByUser(int $tenantId): array
    {
        if ($tenantId < 1) {
            return [];
        }
        try {
            $st = $this->pdo()->prepare(
                'SELECT user_id, vehicle_class, vehicle_label, vehicle_kind, seconds, last_flown_at FROM atak_pilot_time WHERE tenant_id = ?'
            );
            $st->execute([$tenantId]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row['user_id']][] = $row;
        }

        return $out;
    }

    /**
     * Attributions des qualifications données, groupées par membre.
     *
     * @param list<int> $definitionIds
     * @return array<int, list<array<string, mixed>>>
     */
    public function awardsByUser(int $tenantId, array $definitionIds): array
    {
        $definitionIds = array_values(array_unique(array_filter(array_map('intval', $definitionIds), static fn (int $id): bool => $id > 0)));
        if ($tenantId < 1 || $definitionIds === []) {
            return [];
        }
        $in = implode(',', array_fill(0, count($definitionIds), '?'));
        $st = $this->pdo()->prepare(
            "SELECT * FROM personnel_qualifications WHERE (tenant_id = ? OR tenant_id IS NULL) AND definition_id IN ({$in})"
        );
        $st->execute(array_merge([$tenantId], $definitionIds));
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $out[(int) $row['user_id']][] = $row;
        }

        return $out;
    }

    /**
     * Membres de la communauté parmi ces comptes (un ancien membre garde ses heures mais n'est plus évalué) :
     * id => nom et indicatif.
     *
     * @param list<int> $userIds
     * @return array<int, array{display_name: string, callsign: string}>
     */
    public function memberNames(int $tenantId, array $userIds): array
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn (int $id): bool => $id > 0)));
        if ($userIds === []) {
            return [];
        }
        $in = implode(',', array_fill(0, count($userIds), '?'));
        $st = $this->pdo()->prepare("SELECT id, display_name, callsign FROM users WHERE tenant_id = ? AND id IN ({$in})");
        $st->execute(array_merge([$tenantId], $userIds));
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $out[(int) $row['id']] = [
                'display_name' => (string) ($row['display_name'] ?? ''),
                'callsign' => (string) ($row['callsign'] ?? ''),
            ];
        }

        return $out;
    }

    /** Communautés ayant au moins une règle active (tâche automatique). @return list<int> */
    public function tenantsWithRules(): array
    {
        if (!$this->schemaReady()) {
            return [];
        }
        $st = $this->pdo()->query('SELECT DISTINCT tenant_id FROM pilot_qualification_rules WHERE is_enabled = 1');

        return $st ? array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []) : [];
    }
}
