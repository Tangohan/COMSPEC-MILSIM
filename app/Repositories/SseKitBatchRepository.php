<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\Migrations\SchemaInspector;

/**
 * Lots du générateur de contenu SSE par kits : ce sont des lots de population (sse_id_batches) dont les paramètres
 * portent « generator = kits ». Liste, valeurs déjà prises dans la communauté (pas de doublon), et suppression en bloc
 * d'un lot : ses identités, leurs papiers, fiches, alias, liens, récits et recherchés, sans toucher au reste.
 *
 * Toutes les requêtes sont bornées à la communauté (tenant_id).
 */
final class SseKitBatchRepository
{
    public const GENERATOR = 'kits';

    /** Tables rattachées à une identité, vidées avec le lot (colonnes tenant_id et identity_id). */
    private const OWNED_TABLES = [
        'sse_file_profiles', 'sse_file_aliases', 'sse_file_links', 'sse_file_notes', 'sse_file_xref_decisions', 'sse_lore_members',
        'sse_identity_stories', 'sse_identity_history', 'sse_id_op_wanted', 'sse_detections', 'sse_identity_events', 'sse_wanted_notices',
    ];

    /** Tables d'historique qui gardent leur ligne : seule la référence à l'identité supprimée est retirée. */
    private const DETACHED_TABLES = [
        'sse_file_photos', 'sse_file_queries', 'sse_file_mentions', 'sse_seek_records', 'sse_phone_extractions', 'sse_id_checks', 'sse_id_briefs',
        'sse_ai_profiles',
    ];

    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
        SseIdentityRepository::ensureSchema();
    }

    /**
     * Lots de contenu généré de la communauté, du plus récent au plus ancien.
     *
     * @return list<array<string, mixed>>
     */
    public function list(int $tenantId, int $limit = 100): array
    {
        $rows = $this->db->fetchAll(
            'SELECT * FROM sse_id_batches WHERE tenant_id = :t AND archived_at IS NULL AND params_json LIKE :g ORDER BY id DESC LIMIT ' . max(1, min(200, $limit)),
            ['t' => $tenantId, 'g' => '%"generator":"' . self::GENERATOR . '"%']
        );

        return array_map([SseIdOperationRepository::class, 'hydrateBatch'], $rows);
    }

    /** Lot de contenu généré de la communauté, ou null (lot d'une autre communauté, lot de préparation de mission…). @return array<string, mixed>|null */
    public function find(int $tenantId, int $batchId): ?array
    {
        $row = $this->db->fetchOne('SELECT * FROM sse_id_batches WHERE id = :id AND tenant_id = :t', ['id' => $batchId, 't' => $tenantId]);
        if ($row === null) {
            return null;
        }
        $batch = SseIdOperationRepository::hydrateBatch($row);

        return ($batch['params']['generator'] ?? null) === self::GENERATOR ? $batch : null;
    }

    /** Identités encore présentes des lots de contenu généré (plafond par communauté). */
    public function identitiesCount(int $tenantId): int
    {
        $row = $this->db->fetchOne(
            'SELECT COUNT(*) AS c FROM sse_identities i JOIN sse_id_batches b ON b.id = i.batch_id AND b.tenant_id = i.tenant_id
             WHERE i.tenant_id = :t AND b.archived_at IS NULL AND b.params_json LIKE :g',
            ['t' => $tenantId, 'g' => '%"generator":"' . self::GENERATOR . '"%']
        );

        return (int) ($row['c'] ?? 0);
    }

    /**
     * Valeurs déjà au registre de la communauté : le générateur ne les retire jamais (pas de doublon avec l'existant).
     *
     * @return array{names: list<string>, phones: list<string>, plates: list<string>}
     */
    public function taken(int $tenantId): array
    {
        $names = [];
        foreach ($this->db->fetchAll('SELECT first_name, last_name FROM sse_identities WHERE tenant_id = :t ORDER BY id DESC LIMIT 20000', ['t' => $tenantId]) as $r) {
            $names[] = mb_strtolower(trim((string) $r['first_name'] . ' ' . (string) $r['last_name']));
        }
        $phones = [];
        $plates = [];
        if ($this->has('sse_file_links')) {
            foreach ($this->db->fetchAll(
                "SELECT kind, value FROM sse_file_links WHERE tenant_id = :t AND kind IN ('phone', 'vehicle') ORDER BY id DESC LIMIT 20000",
                ['t' => $tenantId]
            ) as $r) {
                if ($r['kind'] === 'phone') {
                    $phones[] = (string) $r['value'];
                } else {
                    $plates[] = (string) $r['value'];
                }
            }
        }

        return ['names' => $names, 'phones' => $phones, 'plates' => $plates];
    }

    /**
     * Supprime en bloc un lot de contenu généré : papiers, fiches, alias, liens, récits, recherchés puis identités, et
     * enfin le lot. Rien d'autre n'est touché : les liens posés par d'autres fiches vers une identité du lot perdent
     * seulement leur renvoi.
     *
     * @return array{identities: int, documents: int, links: int}|null null : lot introuvable dans la communauté
     */
    public function purge(int $tenantId, int $batchId): ?array
    {
        if ($this->find($tenantId, $batchId) === null) {
            return null;
        }
        $ids = array_map(static fn (array $r): int => (int) $r['id'], $this->db->fetchAll(
            'SELECT id FROM sse_identities WHERE tenant_id = :t AND batch_id = :b',
            ['t' => $tenantId, 'b' => $batchId]
        ));
        $out = ['identities' => 0, 'documents' => 0, 'links' => 0];
        $pdo = Database::getPdo();
        $own = !$pdo->inTransaction();
        if ($own) {
            $pdo->beginTransaction();
        }
        try {
            foreach (array_chunk($ids, 400) as $chunk) {
                $in = implode(',', $chunk);
                $params = ['t' => $tenantId];
                $docIds = array_map(static fn (array $r): int => (int) $r['id'], $this->db->fetchAll(
                    "SELECT id FROM sse_id_documents WHERE tenant_id = :t AND identity_id IN ({$in})",
                    $params
                ));
                if ($docIds !== []) {
                    $din = implode(',', $docIds);
                    if ($this->has('sse_id_document_instances')) {
                        $this->db->execute("DELETE FROM sse_id_document_instances WHERE tenant_id = :t AND document_id IN ({$din})", $params);
                    }
                    foreach (['sse_id_checks', 'sse_file_mentions', 'sse_seek_records'] as $table) {
                        if ($this->has($table)) {
                            $this->db->execute("UPDATE {$table} SET document_id = NULL WHERE tenant_id = :t AND document_id IN ({$din})", $params);
                        }
                    }
                    $out['documents'] += $this->db->execute("DELETE FROM sse_id_documents WHERE tenant_id = :t AND id IN ({$din})", $params);
                }
                foreach (self::OWNED_TABLES as $table) {
                    if (!$this->has($table)) {
                        continue;
                    }
                    $n = $this->db->execute("DELETE FROM {$table} WHERE tenant_id = :t AND identity_id IN ({$in})", $params);
                    if ($table === 'sse_file_links') {
                        $out['links'] += $n;
                    }
                }
                if ($this->has('sse_file_links')) {
                    $this->db->execute("UPDATE sse_file_links SET linked_identity_id = NULL WHERE tenant_id = :t AND linked_identity_id IN ({$in})", $params);
                }
                foreach (self::DETACHED_TABLES as $table) {
                    if ($this->has($table)) {
                        $this->db->execute("UPDATE {$table} SET identity_id = NULL WHERE tenant_id = :t AND identity_id IN ({$in})", $params);
                    }
                }
                $out['identities'] += $this->db->execute("DELETE FROM sse_identities WHERE tenant_id = :t AND batch_id = :b AND id IN ({$in})", $params + ['b' => $batchId]);
            }
            $this->db->execute('DELETE FROM sse_id_op_wanted WHERE tenant_id = :t AND batch_id = :b', ['t' => $tenantId, 'b' => $batchId]);
            $this->db->execute('DELETE FROM sse_id_batches WHERE tenant_id = :t AND id = :b', ['t' => $tenantId, 'b' => $batchId]);
            if ($own) {
                $pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($own && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        return $out;
    }

    /** @var array<string, bool> */
    private array $tables = [];

    private function has(string $table): bool
    {
        if (!isset($this->tables[$table])) {
            try {
                $this->tables[$table] = (new SchemaInspector(Database::getPdo()))->tableExists($table);
            } catch (\Throwable) {
                $this->tables[$table] = false;
            }
        }

        return $this->tables[$table];
    }
}
