<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/**
 * Histoire / notes du personnage qu'un membre écrit pour une de ses identités (sse_identity_stories), date du dernier
 * envoi de l'identité au téléphone du jeu, et trace de la fusion des anciennes couvertures (sse_cover_export_merges).
 *
 * Ces tables sont créées par l'étape database/steps/4450. Tant qu'elle n'est pas passée, tout se lit comme « absent »
 * (aucune histoire, fusion non faite) et rien ne s'écrit : jamais une erreur.
 */
class SseIdentityStoryRepository
{
    public const MAX_LENGTH = 6000;

    private Database $db;
    /** @var array<string, bool> présence des tables, lue une fois par requête */
    private array $present = [];

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /** Le champ « Histoire » existe-t-il (étape 4450 passée) ? */
    public function available(): bool
    {
        return $this->has('sse_identity_stories');
    }

    /** Les couvertures exportées ont-elles été fondues dans les identités (étape 4450 passée) ? */
    public function merged(): bool
    {
        return $this->has('sse_cover_export_merges');
    }

    /**
     * Couvertures exportées du membre déjà fondues dans ses identités (identifiants de sse_cover_exports). Table absente : aucune.
     *
     * @return array<int, true>
     */
    public function mergedExportIds(int $tenantId, int $userId): array
    {
        if (!$this->merged()) {
            return [];
        }
        $out = [];
        foreach ($this->db->fetchAll('SELECT export_id FROM sse_cover_export_merges WHERE tenant_id = :t AND user_id = :u', ['t' => $tenantId, 'u' => $userId]) as $r) {
            $out[(int) $r['export_id']] = true;
        }

        return $out;
    }

    private function has(string $table): bool
    {
        if (!isset($this->present[$table])) {
            try {
                $this->db->fetchOne('SELECT 1 AS ok FROM ' . $table . ' LIMIT 1');
                $this->present[$table] = true;
            } catch (\Throwable) {
                $this->present[$table] = false;
            }
        }

        return $this->present[$table];
    }

    /**
     * @param list<int> $identityIds
     * @return array<int, array{story: string, game_synced_at: ?string, updated_at: string}>
     */
    public function forIdentities(int $tenantId, array $identityIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $identityIds), static fn (int $i): bool => $i > 0)));
        if ($ids === [] || !$this->available()) {
            return [];
        }
        $out = [];
        foreach ($this->db->fetchAll(
            'SELECT identity_id, story, game_synced_at, updated_at FROM sse_identity_stories WHERE tenant_id = :t AND identity_id IN (' . implode(',', $ids) . ')',
            ['t' => $tenantId]
        ) as $r) {
            $out[(int) $r['identity_id']] = [
                'story' => (string) $r['story'],
                'game_synced_at' => $r['game_synced_at'] !== null ? (string) $r['game_synced_at'] : null,
                'updated_at' => (string) $r['updated_at'],
            ];
        }

        return $out;
    }

    /** Enregistre l'histoire d'une identité (l'appelant a vérifié qu'elle est au membre). Faux : table absente. */
    public function save(int $tenantId, int $identityId, string $story, ?int $actorId): bool
    {
        return $this->upsert($tenantId, $identityId, ['story' => mb_substr($story, 0, self::MAX_LENGTH), 'updated_by_user_id' => $actorId, 'updated_at' => gmdate('Y-m-d H:i:s')]);
    }

    /** Note que l'identité vient d'être envoyée au téléphone du jeu. */
    public function markSynced(int $tenantId, int $identityId, string $at): bool
    {
        return $this->upsert($tenantId, $identityId, ['game_synced_at' => $at]);
    }

    /** @param array<string, mixed> $values */
    private function upsert(int $tenantId, int $identityId, array $values): bool
    {
        if ($identityId < 1 || !$this->available()) {
            return false;
        }
        $key = ['t' => $tenantId, 'i' => $identityId];
        if ($this->db->fetchOne('SELECT id FROM sse_identity_stories WHERE tenant_id = :t AND identity_id = :i', $key) === null) {
            $row = $values + ['story' => '', 'game_synced_at' => null, 'updated_by_user_id' => null, 'updated_at' => gmdate('Y-m-d H:i:s')];
            $this->db->insert(
                'INSERT INTO sse_identity_stories (tenant_id, identity_id, story, game_synced_at, updated_by_user_id, updated_at)
                 VALUES (:t, :i, :story, :game_synced_at, :updated_by_user_id, :updated_at)',
                $key + $row
            );

            return true;
        }
        $set = [];
        foreach (array_keys($values) as $col) {
            $set[] = $col . ' = :' . $col;
        }
        $this->db->execute('UPDATE sse_identity_stories SET ' . implode(', ', $set) . ' WHERE tenant_id = :t AND identity_id = :i', $key + $values);

        return true;
    }
}
