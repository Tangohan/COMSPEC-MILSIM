<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\SilentSchemaMigration;

final class SseWatchlistRepository
{
    /** Niveaux d'un avis (« hvt » : cible prioritaire désignée en jeu, COMSPEC IDENTITY lot 5). */
    public const LEVELS = ['surveillance', 'prioritaire', 'hvt'];

    /** Étapes d'un avis né en jeu (BOLO, HVT) ; « removed » le désactive. */
    public const GAME_STATUSES = ['active', 'find', 'fix', 'captured', 'ekia', 'removed'];

    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
        static $done = false;
        if (!$done) {
            SilentSchemaMigration::run(base_path('bootstrap/atak_sse_persons_migration.php'));
            SilentSchemaMigration::run(base_path('bootstrap/atak_sse_identity_lot45_migration.php'));
            $done = true;
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): int
    {
        return (int) $this->db->insert(
            'INSERT INTO sse_watchlist_entries (
                tenant_id, last_name, first_name, alias, threat_level, notes, active
            ) VALUES (
                :tenant_id, :last_name, :first_name, :alias, :threat_level, :notes, 1
            )',
            [
                'tenant_id' => (int) $data['tenant_id'],
                'last_name' => trim((string) ($data['last_name'] ?? '')),
                'first_name' => trim((string) ($data['first_name'] ?? '')),
                'alias' => ($a = trim((string) ($data['alias'] ?? ''))) !== '' ? $a : null,
                'threat_level' => in_array(($data['threat_level'] ?? ''), self::LEVELS, true)
                    ? $data['threat_level'] : 'surveillance',
                'notes' => ($n = trim((string) ($data['notes'] ?? ''))) !== '' ? $n : null,
            ]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listActive(int $tenantId, string $q = ''): array
    {
        $where = ['tenant_id = :t', 'active = 1'];
        $params = ['t' => $tenantId];
        $q = trim($q);
        if ($q !== '') {
            $where[] = '(last_name LIKE :q1 OR first_name LIKE :q2 OR COALESCE(alias, \'\') LIKE :q3 OR COALESCE(notes, \'\') LIKE :q4)';
            $like = '%' . $q . '%';
            $params['q1'] = $like;
            $params['q2'] = $like;
            $params['q3'] = $like;
            $params['q4'] = $like;
        }
        $rows = $this->db->fetchAll(
            'SELECT * FROM sse_watchlist_entries WHERE ' . implode(' AND ', $where)
            . ' ORDER BY id DESC LIMIT 500',
            $params
        );
        $out = [];
        foreach ($rows as $row) {
            $out[] = $this->hydrate($row);
        }

        return $out;
    }

    /**
     * Avis né en jeu (BOLO, HVT désignée par le Zeus) : créé à la première remontée, mis à jour ensuite (même référence
     * du jeu : PID, capture, levée). « removed » désactive l'avis.
     *
     * @param array{game_ref: string, context_id?: int|null, last_name?: string, first_name?: string, alias?: string|null,
     *     threat_level?: string, notes?: string|null, bio_key?: string|null, known_docs?: list<string>, game_status?: string} $data
     * @return array{id: int, created: bool}
     */
    public function upsertFromGame(int $tenantId, array $data): array
    {
        $ref = (string) $data['game_ref'];
        $level = in_array(($data['threat_level'] ?? ''), self::LEVELS, true) ? (string) $data['threat_level'] : 'surveillance';
        $status = in_array(($data['game_status'] ?? ''), self::GAME_STATUSES, true) ? (string) $data['game_status'] : 'active';
        $docs = implode(',', array_slice($data['known_docs'] ?? [], 0, 20));
        $row = [
            'last_name' => mb_substr(trim((string) ($data['last_name'] ?? '')), 0, 120),
            'first_name' => mb_substr(trim((string) ($data['first_name'] ?? '')), 0, 120),
            'alias' => ($a = trim((string) ($data['alias'] ?? ''))) !== '' ? mb_substr($a, 0, 120) : null,
            'threat_level' => $level,
            'notes' => ($n = trim((string) ($data['notes'] ?? ''))) !== '' ? mb_substr($n, 0, 2000) : null,
            'bio_key' => ($k = trim((string) ($data['bio_key'] ?? ''))) !== '' ? mb_substr($k, 0, 64) : null,
            'known_docs' => $docs !== '' ? mb_substr($docs, 0, 500) : null,
            'game_status' => $status,
            'active' => $status === 'removed' ? 0 : 1,
            'context_id' => isset($data['context_id']) && (int) $data['context_id'] > 0 ? (int) $data['context_id'] : null,
        ];
        $existing = $this->db->fetchOne(
            'SELECT id FROM sse_watchlist_entries WHERE tenant_id = :t AND game_ref = :r ORDER BY id LIMIT 1',
            ['t' => $tenantId, 'r' => $ref]
        );
        if (is_array($existing) && (int) ($existing['id'] ?? 0) > 0) {
            $id = (int) $existing['id'];
            // Mise à jour : un nom ou un alias vide ne remplace pas ce qui est connu.
            $set = ['threat_level = :threat_level', 'game_status = :game_status', 'active = :active'];
            $params = ['id' => $id, 't' => $tenantId, 'threat_level' => $level, 'game_status' => $status, 'active' => $row['active']];
            foreach (['last_name', 'first_name', 'alias', 'notes', 'bio_key', 'known_docs'] as $c) {
                if ($row[$c] !== null && $row[$c] !== '') {
                    $set[] = $c . ' = :' . $c;
                    $params[$c] = $row[$c];
                }
            }
            $this->db->execute('UPDATE sse_watchlist_entries SET ' . implode(', ', $set) . ' WHERE id = :id AND tenant_id = :t', $params);

            return ['id' => $id, 'created' => false];
        }
        $id = (int) $this->db->insert(
            'INSERT INTO sse_watchlist_entries (
                tenant_id, last_name, first_name, alias, threat_level, notes, active, source, game_ref, context_id, bio_key, known_docs, game_status
            ) VALUES (
                :tenant_id, :last_name, :first_name, :alias, :threat_level, :notes, :active, :source, :game_ref, :context_id, :bio_key, :known_docs, :game_status
            )',
            ['tenant_id' => $tenantId, 'source' => 'game', 'game_ref' => $ref] + $row
        );

        return ['id' => $id, 'created' => true];
    }

    public function hasGameRef(int $tenantId, string $gameRef): bool
    {
        return $this->db->fetchOne(
            'SELECT id FROM sse_watchlist_entries WHERE tenant_id = :t AND game_ref = :r LIMIT 1',
            ['t' => $tenantId, 'r' => $gameRef]
        ) !== null;
    }

    public function deactivate(int $id, int $tenantId): bool
    {
        return $this->db->execute(
            'UPDATE sse_watchlist_entries SET active = 0 WHERE id = :id AND tenant_id = :t',
            ['id' => $id, 't' => $tenantId]
        ) > 0;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function hydrate(array $row): array
    {
        $level = (string) ($row['threat_level'] ?? 'surveillance');

        return [
            'id' => (int) ($row['id'] ?? 0),
            'last_name' => (string) ($row['last_name'] ?? ''),
            'first_name' => (string) ($row['first_name'] ?? ''),
            'alias' => $row['alias'] ?? null,
            'display_name' => trim(($row['last_name'] ?? '') . ' ' . ($row['first_name'] ?? ''))
                ?: (string) ($row['alias'] ?? 'Entrée sans nom'),
            'threat_level' => $level,
            'threat_level_label' => match ($level) {
                'prioritaire' => 'Personne prioritaire',
                'hvt' => 'Cible prioritaire (HVT)',
                default => 'Surveillance',
            },
            'notes' => $row['notes'] ?? null,
            'source' => $row['source'] ?? null,
            'game_ref' => $row['game_ref'] ?? null,
            'bio_key' => $row['bio_key'] ?? null,
            'known_docs' => array_values(array_filter(explode(',', (string) ($row['known_docs'] ?? '')), static fn (string $d): bool => $d !== '')),
            'game_status' => $row['game_status'] ?? null,
            'created_at' => $row['created_at'] ?? null,
        ];
    }
}
