<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\SilentSchemaMigration;
use App\Support\SseIdentityCatalog;

/**
 * COMSPEC IDENTITY — identités (civile, alias, couverture, falsifiée, générée), rattachables à une fiche sse_persons.
 *
 * La nature d'une identité est une vérité de jeu : elle part dans le pack du serveur de jeu, jamais dans une
 * réponse de vérification.
 */
final class SseIdentityRepository
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
        self::ensureSchema();
    }

    /** Tables du registre créées au premier usage (même migration que database/steps/3730). */
    public static function ensureSchema(): void
    {
        static $done = false;
        if (!$done) {
            $done = true;
            SilentSchemaMigration::run(base_path('bootstrap/atak_sse_identity_migration.php'));
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(int $tenantId, array $data, ?int $userId = null): int
    {
        $row = self::normalize($data);

        return $this->db->insert(
            'INSERT INTO sse_identities (tenant_id, context_id, op_key, person_id, kind, last_name, first_name, sex, birth_date,
                birth_place, nationality, arma_key, profession, seed, notes, created_by_user_id, created_at)
             VALUES (:t, :ctx, :op, :person, :kind, :last, :first, :sex, :dob, :pob, :nat, :arma, :prof, :seed, :notes, :uid, :now)',
            [
                't' => $tenantId,
                'ctx' => $row['context_id'],
                'op' => $row['op_key'],
                'person' => $row['person_id'],
                'kind' => $row['kind'],
                'last' => $row['last_name'],
                'first' => $row['first_name'],
                'sex' => $row['sex'],
                'dob' => $row['birth_date'],
                'pob' => $row['birth_place'],
                'nat' => $row['nationality'],
                'arma' => $row['arma_key'],
                'prof' => $row['profession'],
                'seed' => $row['seed'],
                'notes' => $row['notes'],
                'uid' => $userId,
                'now' => gmdate('Y-m-d H:i:s'),
            ]
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, int $tenantId, array $data): bool
    {
        $row = self::normalize($data);

        return $this->db->execute(
            'UPDATE sse_identities SET context_id = :ctx, op_key = :op, person_id = :person, kind = :kind, last_name = :last,
                first_name = :first, sex = :sex, birth_date = :dob, birth_place = :pob, nationality = :nat, arma_key = :arma,
                profession = :prof, notes = :notes, updated_at = :now
             WHERE id = :id AND tenant_id = :t',
            [
                'ctx' => $row['context_id'],
                'op' => $row['op_key'],
                'person' => $row['person_id'],
                'kind' => $row['kind'],
                'last' => $row['last_name'],
                'first' => $row['first_name'],
                'sex' => $row['sex'],
                'dob' => $row['birth_date'],
                'pob' => $row['birth_place'],
                'nat' => $row['nationality'],
                'arma' => $row['arma_key'],
                'prof' => $row['profession'],
                'notes' => $row['notes'],
                'now' => gmdate('Y-m-d H:i:s'),
                'id' => $id,
                't' => $tenantId,
            ]
        ) > 0;
    }

    /** @return array<string, mixed>|null */
    public function find(int $id, int $tenantId): ?array
    {
        $row = $this->db->fetchOne('SELECT * FROM sse_identities WHERE id = :id AND tenant_id = :t', ['id' => $id, 't' => $tenantId]);

        return $row === null ? null : self::hydrate($row);
    }

    /**
     * @param list<int> $ids
     * @return array<int, array<string, mixed>>
     */
    public function findMany(int $tenantId, array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $i): bool => $i > 0)));
        if ($ids === []) {
            return [];
        }
        $out = [];
        foreach (array_chunk($ids, 200) as $chunk) {
            $params = ['t' => $tenantId];
            $marks = [];
            foreach ($chunk as $i => $id) {
                $marks[] = ':i' . $i;
                $params['i' . $i] = $id;
            }
            foreach ($this->db->fetchAll('SELECT * FROM sse_identities WHERE tenant_id = :t AND id IN (' . implode(', ', $marks) . ')', $params) as $row) {
                $h = self::hydrate($row);
                $out[$h['id']] = $h;
            }
        }

        return $out;
    }

    /**
     * @param array{kind?: string, q?: string, nationality?: string, person_id?: int, limit?: int, offset?: int} $filters
     * @return list<array<string, mixed>>
     */
    public function list(int $tenantId, array $filters = []): array
    {
        $where = ['i.tenant_id = :t'];
        $params = ['t' => $tenantId];
        $kind = (string) ($filters['kind'] ?? '');
        if ($kind !== '' && isset(SseIdentityCatalog::KINDS[$kind])) {
            $where[] = 'i.kind = :kind';
            $params['kind'] = $kind;
        }
        $nat = strtoupper(trim((string) ($filters['nationality'] ?? '')));
        if ($nat !== '') {
            $where[] = 'i.nationality = :nat';
            $params['nat'] = $nat;
        }
        if ((int) ($filters['person_id'] ?? 0) > 0) {
            $where[] = 'i.person_id = :person';
            $params['person'] = (int) $filters['person_id'];
        }
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(i.last_name LIKE :q1 OR i.first_name LIKE :q2 OR COALESCE(i.arma_key, \'\') LIKE :q3)';
            $params['q1'] = $params['q2'] = $params['q3'] = '%' . $q . '%';
        }
        $limit = max(1, min(500, (int) ($filters['limit'] ?? 200)));
        $offset = max(0, (int) ($filters['offset'] ?? 0));
        $rows = $this->db->fetchAll(
            'SELECT i.*, (SELECT COUNT(*) FROM sse_id_documents d WHERE d.identity_id = i.id AND d.tenant_id = i.tenant_id) AS documents_count
             FROM sse_identities i WHERE ' . implode(' AND ', $where) . ' ORDER BY i.id DESC LIMIT ' . $limit . ' OFFSET ' . $offset,
            $params
        );

        return array_map([self::class, 'hydrate'], $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForPerson(int $tenantId, int $personId): array
    {
        if ($personId < 1) {
            return [];
        }

        return $this->list($tenantId, ['person_id' => $personId, 'limit' => 50]);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function normalize(array $data): array
    {
        $str = static function (mixed $v, int $max): ?string {
            $s = trim(is_scalar($v) ? (string) $v : '');

            return $s === '' ? null : mb_substr($s, 0, $max);
        };
        $dob = trim((string) ($data['birth_date'] ?? ''));
        $ctx = (int) ($data['context_id'] ?? 0);
        $person = (int) ($data['person_id'] ?? 0);
        $seed = $data['seed'] ?? null;

        return [
            'context_id' => $ctx > 0 ? $ctx : null,
            'op_key' => $str($data['op_key'] ?? null, 64),
            'person_id' => $person > 0 ? $person : null,
            'kind' => SseIdentityCatalog::pick(SseIdentityCatalog::KINDS, $data['kind'] ?? null, 'civil'),
            'last_name' => (string) ($str($data['last_name'] ?? null, 120) ?? ''),
            'first_name' => (string) ($str($data['first_name'] ?? null, 120) ?? ''),
            'sex' => SseIdentityCatalog::pick(SseIdentityCatalog::SEXES, strtoupper((string) ($data['sex'] ?? '')), 'X'),
            'birth_date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob) === 1 ? $dob : null,
            'birth_place' => $str($data['birth_place'] ?? null, 120),
            'nationality' => ($n = strtoupper((string) $str($data['nationality'] ?? null, 3))) !== '' ? $n : null,
            'arma_key' => $str($data['arma_key'] ?? null, 64),
            'profession' => $str($data['profession'] ?? null, 120),
            'seed' => is_numeric($seed) && (int) $seed >= 0 ? (int) $seed : null,
            'notes' => $str($data['notes'] ?? null, 4000),
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function hydrate(array $row): array
    {
        $id = (int) ($row['id'] ?? 0);
        $kind = (string) ($row['kind'] ?? 'civil');

        return [
            'id' => $id,
            'ref' => SseIdentityCatalog::identityRef($id),
            'context_id' => ($row['context_id'] ?? null) !== null ? (int) $row['context_id'] : null,
            'op_key' => ($row['op_key'] ?? null) !== null ? (string) $row['op_key'] : null,
            'person_id' => ($row['person_id'] ?? null) !== null ? (int) $row['person_id'] : null,
            'kind' => $kind,
            'kind_label' => SseIdentityCatalog::label(SseIdentityCatalog::KINDS, $kind),
            'last_name' => (string) ($row['last_name'] ?? ''),
            'first_name' => (string) ($row['first_name'] ?? ''),
            'display_name' => trim(mb_strtoupper((string) ($row['last_name'] ?? '')) . ' ' . (string) ($row['first_name'] ?? '')),
            'sex' => (string) ($row['sex'] ?? 'X'),
            'birth_date' => ($row['birth_date'] ?? null) !== null ? substr((string) $row['birth_date'], 0, 10) : null,
            'birth_place' => ($row['birth_place'] ?? null) !== null ? (string) $row['birth_place'] : null,
            'nationality' => ($row['nationality'] ?? null) !== null ? (string) $row['nationality'] : null,
            'arma_key' => ($row['arma_key'] ?? null) !== null ? (string) $row['arma_key'] : null,
            'profession' => ($row['profession'] ?? null) !== null ? (string) $row['profession'] : null,
            'seed' => ($row['seed'] ?? null) !== null ? (int) $row['seed'] : null,
            'notes' => ($row['notes'] ?? null) !== null ? (string) $row['notes'] : null,
            'documents_count' => (int) ($row['documents_count'] ?? 0),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => ($row['updated_at'] ?? null) !== null ? (string) $row['updated_at'] : null,
        ];
    }
}
