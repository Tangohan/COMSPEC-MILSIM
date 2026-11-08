<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Configuration du matricule d’organisation — toujours scopée par tenant_id.
 */
final class TenantMemberNumberConfigRepository
{
    private PDO $pdo;

    private static ?bool $schemaReady = null;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function schemaReady(): bool
    {
        if (self::$schemaReady !== null) {
            return self::$schemaReady;
        }
        $st = $this->pdo->query(
            "SELECT 1 FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tenant_member_number_config' LIMIT 1"
        );
        self::$schemaReady = $st && (bool) $st->fetchColumn();

        return self::$schemaReady;
    }

    public function auditSchemaReady(): bool
    {
        $st = $this->pdo->query(
            "SELECT 1 FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tenant_member_number_audit' LIMIT 1"
        );

        return $st && (bool) $st->fetchColumn();
    }

    /** @return array<string, mixed>|null */
    public function get(int $tenantId): ?array
    {
        if (!$this->schemaReady() || $tenantId < 1) {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT * FROM tenant_member_number_config WHERE tenant_id = ? LIMIT 1');
        $stmt->execute([$tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @return array<string, mixed>
     */
    public function getOrCreate(int $tenantId): array
    {
        $existing = $this->get($tenantId);
        if ($existing !== null) {
            return $existing;
        }
        if (!$this->schemaReady() || $tenantId < 1) {
            return $this->defaults($tenantId);
        }
        $stmt = $this->pdo->prepare(
            'INSERT INTO tenant_member_number_config
                (tenant_id, enabled, label, mode, pattern, prefix, next_sequence, unique_required, required, created_at, updated_at)
             VALUES (?, 0, ?, \'free\', \'{PREFIX}-{NUMBER:4}\', \'\', 1, 1, 0, NOW(), NOW())'
        );
        $stmt->execute([$tenantId, "Matricule d'organisation"]);

        return $this->get($tenantId) ?? $this->defaults($tenantId);
    }

    /**
     * @param array{
     *   enabled?: bool|int,
     *   label?: string,
     *   mode?: string,
     *   pattern?: string,
     *   prefix?: string,
     *   next_sequence?: int,
     *   unique_required?: bool|int,
     *   required?: bool|int
     * } $data
     */
    public function updateConfig(int $tenantId, array $data): bool
    {
        if (!$this->schemaReady() || $tenantId < 1) {
            return false;
        }
        $this->getOrCreate($tenantId);
        $stmt = $this->pdo->prepare(
            'UPDATE tenant_member_number_config SET
                enabled = ?,
                label = ?,
                mode = ?,
                pattern = ?,
                prefix = ?,
                next_sequence = ?,
                unique_required = ?,
                required = ?,
                updated_at = NOW()
             WHERE tenant_id = ?'
        );

        return $stmt->execute([
            !empty($data['enabled']) ? 1 : 0,
            (string) ($data['label'] ?? "Matricule d'organisation"),
            (string) ($data['mode'] ?? 'free'),
            (string) ($data['pattern'] ?? '{PREFIX}-{NUMBER:4}'),
            (string) ($data['prefix'] ?? ''),
            max(1, (int) ($data['next_sequence'] ?? 1)),
            !empty($data['unique_required']) ? 1 : 0,
            !empty($data['required']) ? 1 : 0,
            $tenantId,
        ]);
    }

    /** Consomme et retourne le prochain numéro séquentiel (atomique, scopé tenant). */
    public function consumeNextSequence(int $tenantId): ?int
    {
        if (!$this->schemaReady() || $tenantId < 1) {
            return null;
        }
        $this->getOrCreate($tenantId);
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'SELECT next_sequence FROM tenant_member_number_config WHERE tenant_id = ? FOR UPDATE'
            );
            $stmt->execute([$tenantId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                $this->pdo->rollBack();

                return null;
            }
            $consumed = (int) $row['next_sequence'];
            $upd = $this->pdo->prepare(
                'UPDATE tenant_member_number_config SET next_sequence = next_sequence + 1, updated_at = NOW() WHERE tenant_id = ?'
            );
            $upd->execute([$tenantId]);
            $this->pdo->commit();

            return $consumed;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function appendAudit(
        int $tenantId,
        int $userId,
        ?string $oldValue,
        ?string $newValue,
        ?int $actorUserId,
        ?string $reason = null,
        string $source = 'manual'
    ): void {
        if (!$this->auditSchemaReady() || $tenantId < 1 || $userId < 1) {
            return;
        }
        $stmt = $this->pdo->prepare(
            'INSERT INTO tenant_member_number_audit
                (tenant_id, user_id, old_value, new_value, reason, actor_user_id, source, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $tenantId,
            $userId,
            $oldValue !== null && $oldValue !== '' ? $oldValue : null,
            $newValue !== null && $newValue !== '' ? $newValue : null,
            $reason !== null && trim($reason) !== '' ? mb_substr(trim($reason), 0, 255) : null,
            $actorUserId !== null && $actorUserId > 0 ? $actorUserId : null,
            mb_substr($source, 0, 40),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listAuditForUser(int $tenantId, int $userId, int $limit = 20): array
    {
        if (!$this->auditSchemaReady() || $tenantId < 1 || $userId < 1) {
            return [];
        }
        $limit = max(1, min(100, $limit));
        $stmt = $this->pdo->prepare(
            'SELECT * FROM tenant_member_number_audit
             WHERE tenant_id = ? AND user_id = ?
             ORDER BY created_at DESC, id DESC
             LIMIT ' . $limit
        );
        $stmt->execute([$tenantId, $userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Derniers changements de matricule dans la communauté, avec le nom du membre et de l’auteur.
     *
     * @return list<array<string, mixed>>
     */
    public function listRecentAuditForTenant(int $tenantId, int $limit = 15): array
    {
        if (!$this->auditSchemaReady() || $tenantId < 1) {
            return [];
        }
        $limit = max(1, min(100, $limit));
        $stmt = $this->pdo->prepare(
            'SELECT a.id, a.user_id, a.old_value, a.new_value, a.reason, a.source, a.created_at, a.actor_user_id,
                    u.display_name AS member_name, u.callsign AS member_callsign, act.display_name AS actor_name
             FROM tenant_member_number_audit a
             LEFT JOIN users u ON u.id = a.user_id
             LEFT JOIN users act ON act.id = a.actor_user_id
             WHERE a.tenant_id = ?
             ORDER BY a.created_at DESC, a.id DESC
             LIMIT ' . $limit
        );
        $stmt->execute([$tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function unitTemplatesReady(): bool
    {
        $st = $this->pdo->query(
            "SELECT 1 FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tenant_member_number_unit_templates' LIMIT 1"
        );

        return $st && (bool) $st->fetchColumn();
    }

    /**
     * Modèle de matricule choisi pour chaque unité de la communauté (les autres unités suivent le modèle général).
     *
     * @return array<int, string> identifiant d’unité => modèle
     */
    public function listUnitTemplates(int $tenantId): array
    {
        if ($tenantId < 1 || !$this->unitTemplatesReady()) {
            return [];
        }
        $stmt = $this->pdo->prepare('SELECT unit_id, pattern FROM tenant_member_number_unit_templates WHERE tenant_id = ? ORDER BY unit_id');
        $stmt->execute([$tenantId]);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $pattern = trim((string) ($row['pattern'] ?? ''));
            if ($pattern !== '') {
                $out[(int) $row['unit_id']] = $pattern;
            }
        }

        return $out;
    }

    /** Enregistre le modèle d’une unité ; un modèle vide la remet sur le modèle général. */
    public function saveUnitTemplate(int $tenantId, int $unitId, ?string $pattern): bool
    {
        if ($tenantId < 1 || $unitId < 1 || !$this->unitTemplatesReady()) {
            return false;
        }
        $pattern = $pattern !== null ? trim($pattern) : '';
        if ($pattern === '') {
            $stmt = $this->pdo->prepare('DELETE FROM tenant_member_number_unit_templates WHERE tenant_id = ? AND unit_id = ?');

            return $stmt->execute([$tenantId, $unitId]);
        }
        $stmt = $this->pdo->prepare('UPDATE tenant_member_number_unit_templates SET pattern = ?, updated_at = NOW() WHERE tenant_id = ? AND unit_id = ?');
        $stmt->execute([$pattern, $tenantId, $unitId]);
        if ($stmt->rowCount() > 0) {
            return true;
        }
        $exists = $this->pdo->prepare('SELECT 1 FROM tenant_member_number_unit_templates WHERE tenant_id = ? AND unit_id = ? LIMIT 1');
        $exists->execute([$tenantId, $unitId]);
        if ($exists->fetchColumn()) {
            return true;
        }
        $ins = $this->pdo->prepare(
            'INSERT INTO tenant_member_number_unit_templates (tenant_id, unit_id, pattern, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())'
        );

        return $ins->execute([$tenantId, $unitId, $pattern]);
    }

    /** @return array<string, mixed>|null Ligne d’historique de CETTE communauté. */
    public function findAudit(int $tenantId, int $auditId): ?array
    {
        if (!$this->auditSchemaReady() || $tenantId < 1 || $auditId < 1) {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT * FROM tenant_member_number_audit WHERE id = ? AND tenant_id = ? LIMIT 1');
        $stmt->execute([$auditId, $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** @return array<string, mixed> */
    private function defaults(int $tenantId): array
    {
        return [
            'tenant_id' => $tenantId,
            'enabled' => 0,
            'label' => "Matricule d'organisation",
            'mode' => 'free',
            'pattern' => '{PREFIX}-{NUMBER:4}',
            'prefix' => '',
            'next_sequence' => 1,
            'unique_required' => 1,
            'required' => 0,
        ];
    }
}
