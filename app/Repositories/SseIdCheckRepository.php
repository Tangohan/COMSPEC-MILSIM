<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\SseIdentityCatalog;

/**
 * COMSPEC IDENTITY — contrôles d'identité remontés du terrain (N0 visuel, N1 MRZ, N2 puce, N3 fichiers).
 *
 * Idempotent : une même clé (idempotency_key) renvoie le contrôle déjà enregistré, jamais un doublon.
 */
final class SseIdCheckRepository
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
        SseIdentityRepository::ensureSchema();
    }

    /** @return array<string, mixed>|null */
    public function findByKey(int $tenantId, string $key): ?array
    {
        $row = $this->db->fetchOne(
            'SELECT * FROM sse_id_checks WHERE tenant_id = :t AND idempotency_key = :k',
            ['t' => $tenantId, 'k' => $key]
        );

        return $row === null ? null : self::hydrate($row);
    }

    /**
     * @param array<string, mixed> $row colonnes de sse_id_checks (sans id ni tenant_id)
     * @return array{id: int, ref: string, duplicate: bool}
     */
    public function store(int $tenantId, array $row): array
    {
        $key = (string) $row['idempotency_key'];
        $existing = $this->findByKey($tenantId, $key);
        if ($existing !== null) {
            return ['id' => $existing['id'], 'ref' => $existing['ref'], 'duplicate' => true];
        }
        $cols = [
            'context_id', 'op_key', 'idempotency_key', 'document_id', 'identity_id', 'person_id', 'doc_type', 'country_code',
            'doc_number', 'presented_last', 'presented_first', 'presented_dob', 'subject_netid', 'subject_bio_key',
            'controller_callsign', 'controller_steam_id', 'levels', 'results_json', 'verdict', 'grid', 'pos_x', 'pos_y', 'pos_z',
            'game_time', 'case_ref',
        ];
        $params = ['tenant_id' => $tenantId, 'created_at' => gmdate('Y-m-d H:i:s')];
        foreach ($cols as $c) {
            $params[$c] = $row[$c] ?? null;
        }
        try {
            $id = $this->db->insert(
                'INSERT INTO sse_id_checks (tenant_id, created_at, ' . implode(', ', $cols) . ')
                 VALUES (:tenant_id, :created_at, :' . implode(', :', $cols) . ')',
                $params
            );
        } catch (\PDOException $e) {
            // Course entre deux envois de la même clé : la contrainte unique a refusé le second.
            $existing = $this->findByKey($tenantId, $key);
            if ($existing === null) {
                throw $e;
            }

            return ['id' => $existing['id'], 'ref' => $existing['ref'], 'duplicate' => true];
        }

        return ['id' => $id, 'ref' => SseIdentityCatalog::checkRef($id), 'duplicate' => false];
    }

    /** @return array<string, mixed>|null */
    public function find(int $id, int $tenantId): ?array
    {
        $row = $this->db->fetchOne('SELECT * FROM sse_id_checks WHERE id = :id AND tenant_id = :t', ['id' => $id, 't' => $tenantId]);

        return $row === null ? null : self::hydrate($row);
    }

    /**
     * @param array{verdict?: string, q?: string, person_id?: int, document_id?: int, limit?: int} $filters
     * @return list<array<string, mixed>>
     */
    public function list(int $tenantId, array $filters = []): array
    {
        $where = ['tenant_id = :t'];
        $params = ['t' => $tenantId];
        $verdict = (string) ($filters['verdict'] ?? '');
        if ($verdict !== '' && isset(SseIdentityCatalog::VERDICTS[$verdict])) {
            $where[] = 'verdict = :verdict';
            $params['verdict'] = $verdict;
        }
        foreach (['person_id', 'document_id'] as $k) {
            if ((int) ($filters[$k] ?? 0) > 0) {
                $where[] = $k . ' = :' . $k;
                $params[$k] = (int) $filters[$k];
            }
        }
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(COALESCE(doc_number, \'\') LIKE :q1 OR COALESCE(presented_last, \'\') LIKE :q2 OR COALESCE(controller_callsign, \'\') LIKE :q3 OR COALESCE(grid, \'\') LIKE :q4)';
            $params['q1'] = $params['q2'] = $params['q3'] = $params['q4'] = '%' . $q . '%';
        }
        $limit = max(1, min(500, (int) ($filters['limit'] ?? 200)));
        $rows = $this->db->fetchAll('SELECT * FROM sse_id_checks WHERE ' . implode(' AND ', $where) . ' ORDER BY id DESC LIMIT ' . $limit, $params);

        return array_map([self::class, 'hydrate'], $rows);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function hydrate(array $row): array
    {
        $id = (int) ($row['id'] ?? 0);
        $results = json_decode((string) ($row['results_json'] ?? ''), true);
        $verdict = (string) ($row['verdict'] ?? '');
        $int = static fn (mixed $v): ?int => $v !== null && (int) $v > 0 ? (int) $v : null;
        $levels = array_values(array_filter(explode(',', (string) ($row['levels'] ?? '')), static fn (string $l): bool => isset(SseIdentityCatalog::LEVELS[$l])));

        return [
            'id' => $id,
            'ref' => SseIdentityCatalog::checkRef($id),
            'context_id' => (int) ($row['context_id'] ?? 1),
            'op_key' => $row['op_key'] ?? null,
            'idempotency_key' => (string) ($row['idempotency_key'] ?? ''),
            'document_id' => $int($row['document_id'] ?? null),
            'identity_id' => $int($row['identity_id'] ?? null),
            'person_id' => $int($row['person_id'] ?? null),
            'doc_type' => $row['doc_type'] ?? null,
            'country_code' => $row['country_code'] ?? null,
            'doc_number' => $row['doc_number'] ?? null,
            'presented_last' => $row['presented_last'] ?? null,
            'presented_first' => $row['presented_first'] ?? null,
            'presented_dob' => $row['presented_dob'] ?? null,
            'subject_netid' => $row['subject_netid'] ?? null,
            'subject_bio_key' => $row['subject_bio_key'] ?? null,
            'controller_callsign' => $row['controller_callsign'] ?? null,
            'controller_steam_id' => $row['controller_steam_id'] ?? null,
            'levels' => $levels,
            'results' => is_array($results) ? $results : [],
            'verdict' => $verdict,
            'verdict_label' => SseIdentityCatalog::label(SseIdentityCatalog::VERDICTS, $verdict, 'Sans verdict'),
            'grid' => $row['grid'] ?? null,
            'pos' => ($row['pos_x'] ?? null) !== null ? [(float) $row['pos_x'], (float) ($row['pos_y'] ?? 0), (float) ($row['pos_z'] ?? 0)] : null,
            'game_time' => $row['game_time'] ?? null,
            'case_ref' => $row['case_ref'] ?? null,
            'created_at' => (string) ($row['created_at'] ?? ''),
        ];
    }
}
