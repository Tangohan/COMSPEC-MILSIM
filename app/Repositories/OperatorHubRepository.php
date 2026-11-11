<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\SilentSchemaMigration;

/**
 * Outils opérateur : outils épinglés par membre (operator_tool_favorites) et état posé par le membre sur ses identités
 * d'emprunt et ses couvertures (operator_identity_states). Toute requête est bornée à la communauté et au membre.
 */
class OperatorHubRepository
{
    public const KIND_IDENTITY = 'identity';
    public const KIND_COVER = 'cover';
    public const MAX_FAVORITES = 12;

    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
        self::ensureSchema();
    }

    /** Tables créées au premier usage (même migration que database/steps/4390). */
    public static function ensureSchema(): void
    {
        static $done = false;
        if (!$done) {
            $done = true;
            SilentSchemaMigration::run(base_path('bootstrap/operator_hub_migration.php'));
        }
    }

    // ------------------------------------------------------------------ Outils épinglés

    /** @return list<string> */
    public function favorites(int $tenantId, int $userId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT tool_key FROM operator_tool_favorites WHERE tenant_id = :t AND user_id = :u ORDER BY id ASC LIMIT ' . self::MAX_FAVORITES,
            ['t' => $tenantId, 'u' => $userId]
        );

        return array_values(array_map(static fn (array $r): string => (string) $r['tool_key'], $rows));
    }

    /** Épingle ou retire l'outil. Rend l'état final (vrai = épinglé), ou null si la limite est atteinte. */
    public function toggleFavorite(int $tenantId, int $userId, string $toolKey): ?bool
    {
        $params = ['t' => $tenantId, 'u' => $userId, 'k' => $toolKey];
        if ($this->db->execute('DELETE FROM operator_tool_favorites WHERE tenant_id = :t AND user_id = :u AND tool_key = :k', $params) > 0) {
            return false;
        }
        if (count($this->favorites($tenantId, $userId)) >= self::MAX_FAVORITES) {
            return null;
        }
        $this->db->insert(
            'INSERT INTO operator_tool_favorites (tenant_id, user_id, tool_key, created_at) VALUES (:t, :u, :k, :now)',
            $params + ['now' => gmdate('Y-m-d H:i:s')]
        );

        return true;
    }

    // ------------------------------------------------------------------ États (identités, couvertures)

    /**
     * États d'un membre pour une nature d'objet, par identifiant d'objet.
     *
     * @return array<int, array{is_active: bool, archived_at: ?string, revoked_at: ?string, edited_at: ?string}>
     */
    public function states(int $tenantId, int $userId, string $kind): array
    {
        $out = [];
        foreach ($this->db->fetchAll(
            'SELECT item_id, is_active, archived_at, revoked_at, edited_at FROM operator_identity_states WHERE tenant_id = :t AND user_id = :u AND item_kind = :k',
            ['t' => $tenantId, 'u' => $userId, 'k' => $kind]
        ) as $r) {
            $out[(int) $r['item_id']] = [
                'is_active' => (int) $r['is_active'] === 1,
                'archived_at' => $r['archived_at'] !== null ? (string) $r['archived_at'] : null,
                'revoked_at' => $r['revoked_at'] !== null ? (string) $r['revoked_at'] : null,
                'edited_at' => $r['edited_at'] !== null ? (string) $r['edited_at'] : null,
            ];
        }

        return $out;
    }

    /**
     * Pose des valeurs sur l'état d'un objet (créé au besoin). Clés admises : is_active, archived_at, revoked_at, edited_at.
     *
     * @param array<string, mixed> $values
     */
    public function setState(int $tenantId, int $userId, string $kind, int $itemId, array $values, ?int $actorId): void
    {
        $values = array_intersect_key($values, array_flip(['is_active', 'archived_at', 'revoked_at', 'edited_at']));
        if ($values === []) {
            return;
        }
        if (array_key_exists('is_active', $values)) {
            $values['is_active'] = !empty($values['is_active']) ? 1 : 0;
        }
        $now = gmdate('Y-m-d H:i:s');
        $key = ['t' => $tenantId, 'k' => $kind, 'i' => $itemId];
        if ($this->db->fetchOne('SELECT id FROM operator_identity_states WHERE tenant_id = :t AND item_kind = :k AND item_id = :i', $key) === null) {
            $row = $values + ['is_active' => 0, 'archived_at' => null, 'revoked_at' => null, 'edited_at' => null];
            $this->db->insert(
                'INSERT INTO operator_identity_states (tenant_id, user_id, item_kind, item_id, is_active, archived_at, revoked_at, edited_at, actor_user_id, updated_at)
                 VALUES (:t, :u, :k, :i, :is_active, :archived_at, :revoked_at, :edited_at, :actor, :now)',
                $key + $row + ['u' => $userId, 'actor' => $actorId, 'now' => $now]
            );

            return;
        }
        $set = [];
        foreach (array_keys($values) as $col) {
            $set[] = $col . ' = :' . $col;
        }
        $this->db->execute(
            'UPDATE operator_identity_states SET ' . implode(', ', $set) . ', actor_user_id = :actor, updated_at = :now
              WHERE tenant_id = :t AND item_kind = :k AND item_id = :i AND user_id = :u',
            $key + $values + ['u' => $userId, 'actor' => $actorId, 'now' => $now]
        );
    }

    /** Un seul objet actif par membre et par nature : les autres sont désactivés. */
    public function setOnlyActive(int $tenantId, int $userId, string $kind, int $itemId, ?int $actorId): void
    {
        $this->db->execute(
            'UPDATE operator_identity_states SET is_active = 0, updated_at = :now WHERE tenant_id = :t AND user_id = :u AND item_kind = :k AND item_id <> :i',
            ['now' => gmdate('Y-m-d H:i:s'), 't' => $tenantId, 'u' => $userId, 'k' => $kind, 'i' => $itemId]
        );
        $this->setState($tenantId, $userId, $kind, $itemId, ['is_active' => 1], $actorId);
    }

    /**
     * Couvertures révoquées d'un membre (identifiants de sse_cover_exports). Table absente : aucune.
     *
     * @return array<int, true>
     */
    public static function revokedCoverIds(int $tenantId, int $userId): array
    {
        try {
            $out = [];
            foreach (Database::getInstance()->fetchAll(
                "SELECT item_id FROM operator_identity_states WHERE tenant_id = :t AND user_id = :u AND item_kind = 'cover' AND revoked_at IS NOT NULL",
                ['t' => $tenantId, 'u' => $userId]
            ) as $r) {
                $out[(int) $r['item_id']] = true;
            }

            return $out;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Couverture que le membre a marquée active (identifiant de sse_cover_exports) ; 0 : aucune. Une couverture révoquée
     * n'est jamais rendue. Table absente : 0.
     */
    public static function activeCoverId(int $tenantId, int $userId): int
    {
        try {
            $row = Database::getInstance()->fetchOne(
                "SELECT item_id FROM operator_identity_states WHERE tenant_id = :t AND user_id = :u AND item_kind = 'cover' AND is_active = 1 AND revoked_at IS NULL ORDER BY updated_at DESC, id DESC LIMIT 1",
                ['t' => $tenantId, 'u' => $userId]
            );

            return $row !== null ? (int) $row['item_id'] : 0;
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Journal des changements faits par le membre depuis ses outils (journal d'audit SSE), le plus récent d'abord.
     *
     * @return list<array{action: string, object_type: string, object_id: ?int, created_at: string, after: array<string, mixed>}>
     */
    public function auditTrail(int $tenantId, int $userId, int $limit = 30): array
    {
        try {
            $rows = $this->db->fetchAll(
                "SELECT action, object_type, object_id, created_at, after_json FROM sse_audit_log
                  WHERE tenant_id = :t AND actor_user_id = :u AND (action LIKE 'op_identity.%' OR action LIKE 'id_cover.%' OR action LIKE 'id_member.%')
                  ORDER BY id DESC LIMIT " . max(1, min(100, $limit)),
                ['t' => $tenantId, 'u' => $userId]
            );
        } catch (\Throwable) {
            return [];
        }
        $out = [];
        foreach ($rows as $r) {
            $after = json_decode((string) ($r['after_json'] ?? ''), true);
            // Seulement ce qui touche ses propres papiers (un responsable agit aussi sur ceux des autres).
            if (is_array($after) && isset($after['member_user_id']) && (int) $after['member_user_id'] !== $userId) {
                continue;
            }
            $out[] = [
                'action' => (string) $r['action'],
                'object_type' => (string) $r['object_type'],
                'object_id' => $r['object_id'] !== null ? (int) $r['object_id'] : null,
                'created_at' => (string) ($r['created_at'] ?? ''),
                'after' => is_array($after) ? $after : [],
            ];
        }

        return $out;
    }
}
