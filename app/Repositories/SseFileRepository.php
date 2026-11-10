<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\SilentSchemaMigration;
use App\Support\SseFileCatalog;

/**
 * « Passage aux fichiers » : journal des consultations, mentions aux fichiers et fiches SEEK II (par communauté).
 *
 * Le journal ne s'efface pas ; une mention se lève (date, auteur) sans disparaître ; une fiche SEEK II est créée à la
 * première remontée puis mise à jour (même REC ou même clé d'envoi).
 */
final class SseFileRepository
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
        self::ensureSchema();
    }

    /** Tables créées au premier usage (même migration que database/steps/4330). */
    public static function ensureSchema(): void
    {
        static $done = false;
        if (!$done) {
            $done = true;
            SseIdentityRepository::ensureSchema();
            SilentSchemaMigration::run(base_path('bootstrap/sse_file_queries_migration.php'));
        }
    }

    // ------------------------------------------------------------ Journal

    /**
     * Ligne de journal. Avec une clé d'envoi déjà vue : la ligne existante (duplicate).
     *
     * @param array{query_type: string, criteria: array<string, mixed>, source: string, operator_user_id?: ?int,
     *     operator_label?: ?string, operator_steam_id?: ?string, hit: bool, verdict?: ?string, codes?: list<string>,
     *     deferred?: bool, idempotency_key?: ?string, qrn?: ?string, game_time?: ?string, grid?: ?string} $row
     * @return array{id: int, qrn: string, duplicate: bool}
     */
    public function logQuery(int $tenantId, array $row): array
    {
        $key = isset($row['idempotency_key']) && $row['idempotency_key'] !== '' ? (string) $row['idempotency_key'] : null;
        if ($key !== null && ($existing = $this->findQueryByKey($tenantId, $key)) !== null) {
            return ['id' => $existing['id'], 'qrn' => $existing['qrn'], 'duplicate' => true];
        }
        $now = gmdate('Y-m-d H:i:s');
        $criteria = json_encode($row['criteria'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
        $qrn = isset($row['qrn']) && $row['qrn'] !== '' ? mb_substr((string) $row['qrn'], 0, 32) : null;
        $params = [
            't' => $tenantId,
            'qrn' => $qrn,
            'type' => (string) $row['query_type'],
            'crit' => is_string($criteria) ? mb_substr($criteria, 0, 4000) : '{}',
            'src' => (string) $row['source'],
            'uid' => ($row['operator_user_id'] ?? 0) > 0 ? (int) $row['operator_user_id'] : null,
            'label' => isset($row['operator_label']) && $row['operator_label'] !== '' ? mb_substr((string) $row['operator_label'], 0, 80) : null,
            'steam' => isset($row['operator_steam_id']) && $row['operator_steam_id'] !== '' ? substr((string) $row['operator_steam_id'], 0, 32) : null,
            'hit' => !empty($row['hit']) ? 1 : 0,
            'verdict' => isset($row['verdict']) && $row['verdict'] !== '' ? mb_substr((string) $row['verdict'], 0, 16) : null,
            'codes' => ($row['codes'] ?? []) !== [] ? mb_substr(implode(',', $row['codes'] ?? []), 0, 1000) : null,
            'deferred' => !empty($row['deferred']) ? 1 : 0,
            'k' => $key,
            'gt' => isset($row['game_time']) && $row['game_time'] !== '' ? mb_substr((string) $row['game_time'], 0, 32) : null,
            'grid' => isset($row['grid']) && $row['grid'] !== '' ? mb_substr((string) $row['grid'], 0, 32) : null,
            'now' => $now,
        ];
        try {
            $id = $this->db->insert(
                'INSERT INTO sse_file_queries (tenant_id, qrn, query_type, criteria_json, source, operator_user_id, operator_label,
                    operator_steam_id, hit, verdict, codes, deferred, idempotency_key, game_time, grid, created_at)
                 VALUES (:t, :qrn, :type, :crit, :src, :uid, :label, :steam, :hit, :verdict, :codes, :deferred, :k, :gt, :grid, :now)',
                $params
            );
        } catch (\PDOException $e) {
            // Course entre deux envois de la même clé : la contrainte unique a refusé le second.
            $existing = $key !== null ? $this->findQueryByKey($tenantId, $key) : null;
            if ($existing === null) {
                throw $e;
            }

            return ['id' => $existing['id'], 'qrn' => $existing['qrn'], 'duplicate' => true];
        }
        if ($qrn === null) {
            $qrn = SseFileCatalog::qrn($id, $now);
            $this->db->execute('UPDATE sse_file_queries SET qrn = :q WHERE id = :id AND tenant_id = :t', ['q' => $qrn, 'id' => $id, 't' => $tenantId]);
        }

        return ['id' => $id, 'qrn' => $qrn, 'duplicate' => false];
    }

    /** @return array<string, mixed>|null */
    public function findQueryByKey(int $tenantId, string $key): ?array
    {
        $row = $this->db->fetchOne('SELECT * FROM sse_file_queries WHERE tenant_id = :t AND idempotency_key = :k', ['t' => $tenantId, 'k' => $key]);

        return $row === null ? null : self::hydrateQuery($row);
    }

    /** @return array<string, mixed>|null */
    public function findQuery(int $id, int $tenantId): ?array
    {
        $row = $this->db->fetchOne('SELECT * FROM sse_file_queries WHERE id = :id AND tenant_id = :t', ['id' => $id, 't' => $tenantId]);

        return $row === null ? null : self::hydrateQuery($row);
    }

    /**
     * @param array{source?: string, type?: string, hit?: string, q?: string, limit?: int} $filters hit = « 1 » ou « 0 »
     * @return list<array<string, mixed>>
     */
    public function listQueries(int $tenantId, array $filters = []): array
    {
        $where = ['tenant_id = :t'];
        $params = ['t' => $tenantId];
        $source = (string) ($filters['source'] ?? '');
        if (isset(SseFileCatalog::SOURCES[$source])) {
            $where[] = 'source = :src';
            $params['src'] = $source;
        }
        $type = (string) ($filters['type'] ?? '');
        if (isset(SseFileCatalog::QUERY_TYPES[$type])) {
            $where[] = 'query_type = :type';
            $params['type'] = $type;
        }
        $hit = (string) ($filters['hit'] ?? '');
        if ($hit === '1' || $hit === '0') {
            $where[] = 'hit = :hit';
            $params['hit'] = (int) $hit;
        }
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = "(COALESCE(qrn, '') LIKE :q1 OR COALESCE(criteria_json, '') LIKE :q2 OR COALESCE(operator_label, '') LIKE :q3)";
            $params['q1'] = $params['q2'] = $params['q3'] = '%' . $q . '%';
        }
        $limit = max(1, min(500, (int) ($filters['limit'] ?? 200)));
        $rows = $this->db->fetchAll('SELECT * FROM sse_file_queries WHERE ' . implode(' AND ', $where) . ' ORDER BY id DESC LIMIT ' . $limit, $params);

        return array_map([self::class, 'hydrateQuery'], $rows);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function hydrateQuery(array $row): array
    {
        $id = (int) ($row['id'] ?? 0);
        $criteria = json_decode((string) ($row['criteria_json'] ?? ''), true);
        $type = (string) ($row['query_type'] ?? 'QA');
        $source = (string) ($row['source'] ?? 'web');

        return [
            'id' => $id,
            'qrn' => ($row['qrn'] ?? null) !== null && $row['qrn'] !== '' ? (string) $row['qrn'] : SseFileCatalog::qrn($id, (string) ($row['created_at'] ?? '')),
            'query_type' => $type,
            'query_type_label' => SseFileCatalog::label(SseFileCatalog::QUERY_TYPES, $type),
            'criteria' => is_array($criteria) ? $criteria : [],
            'source' => $source,
            'source_label' => SseFileCatalog::label(SseFileCatalog::SOURCES, $source),
            'operator_user_id' => ($row['operator_user_id'] ?? null) !== null && (int) $row['operator_user_id'] > 0 ? (int) $row['operator_user_id'] : null,
            'operator_label' => ($row['operator_label'] ?? null) !== null ? (string) $row['operator_label'] : null,
            'operator_steam_id' => ($row['operator_steam_id'] ?? null) !== null ? (string) $row['operator_steam_id'] : null,
            'hit' => (int) ($row['hit'] ?? 0) === 1,
            'verdict' => (string) ($row['verdict'] ?? ''),
            'codes' => array_values(array_filter(explode(',', (string) ($row['codes'] ?? '')), static fn (string $c): bool => $c !== '')),
            'deferred' => (int) ($row['deferred'] ?? 0) === 1,
            'game_time' => $row['game_time'] ?? null,
            'grid' => $row['grid'] ?? null,
            'created_at' => (string) ($row['created_at'] ?? ''),
        ];
    }

    // ----------------------------------------------------------- Mentions

    /**
     * @param array{mention: string, identity_id?: ?int, document_id?: ?int, seek_record_id?: ?int, note?: ?string, reference?: ?string} $data
     */
    public function createMention(int $tenantId, array $data, ?int $userId): int
    {
        $int = static fn (mixed $v): ?int => is_numeric($v) && (int) $v > 0 ? (int) $v : null;
        $str = static fn (mixed $v, int $max): ?string => ($s = trim(is_scalar($v) ? (string) $v : '')) !== '' ? mb_substr($s, 0, $max) : null;

        return $this->db->insert(
            'INSERT INTO sse_file_mentions (tenant_id, mention, identity_id, document_id, seek_record_id, note, reference, created_by_user_id, created_at)
             VALUES (:t, :m, :i, :d, :s, :note, :ref, :uid, :now)',
            [
                't' => $tenantId,
                'm' => (string) $data['mention'],
                'i' => $int($data['identity_id'] ?? null),
                'd' => $int($data['document_id'] ?? null),
                's' => $int($data['seek_record_id'] ?? null),
                'note' => $str($data['note'] ?? null, 1000),
                'ref' => $str($data['reference'] ?? null, 64),
                'uid' => $userId !== null && $userId > 0 ? $userId : null,
                'now' => gmdate('Y-m-d H:i:s'),
            ]
        );
    }

    public function liftMention(int $id, int $tenantId, ?int $userId, string $note = ''): bool
    {
        return $this->db->execute(
            'UPDATE sse_file_mentions SET lifted_at = :now, lifted_by_user_id = :uid, lift_note = :note
             WHERE id = :id AND tenant_id = :t AND lifted_at IS NULL',
            [
                'now' => gmdate('Y-m-d H:i:s'),
                'uid' => $userId !== null && $userId > 0 ? $userId : null,
                'note' => ($n = trim($note)) !== '' ? mb_substr($n, 0, 500) : null,
                'id' => $id,
                't' => $tenantId,
            ]
        ) > 0;
    }

    /** @return array<string, mixed>|null */
    public function findMention(int $id, int $tenantId): ?array
    {
        $row = $this->db->fetchOne('SELECT * FROM sse_file_mentions WHERE id = :id AND tenant_id = :t', ['id' => $id, 't' => $tenantId]);

        return $row === null ? null : self::hydrateMention($row);
    }

    /**
     * Mentions actives (non levées) portées par ces identités, papiers ou fiches SEEK II.
     *
     * @param list<int> $identityIds
     * @param list<int> $documentIds
     * @param list<int> $seekIds
     * @return list<array<string, mixed>>
     */
    public function activeMentionsFor(int $tenantId, array $identityIds, array $documentIds, array $seekIds = []): array
    {
        $or = [];
        $params = ['t' => $tenantId];
        foreach (['identity_id' => $identityIds, 'document_id' => $documentIds, 'seek_record_id' => $seekIds] as $col => $ids) {
            $ids = array_slice(array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $i): bool => $i > 0))), 0, 200);
            if ($ids === []) {
                continue;
            }
            $marks = [];
            foreach ($ids as $i => $id) {
                $marks[] = ':' . $col . $i;
                $params[$col . $i] = $id;
            }
            $or[] = $col . ' IN (' . implode(', ', $marks) . ')';
        }
        if ($or === []) {
            return [];
        }
        $rows = $this->db->fetchAll(
            'SELECT * FROM sse_file_mentions WHERE tenant_id = :t AND lifted_at IS NULL AND (' . implode(' OR ', $or) . ') ORDER BY id DESC LIMIT 100',
            $params
        );

        return array_map([self::class, 'hydrateMention'], $rows);
    }

    /**
     * @param array{active?: bool, mention?: string, limit?: int} $filters
     * @return list<array<string, mixed>>
     */
    public function listMentions(int $tenantId, array $filters = []): array
    {
        $where = ['tenant_id = :t'];
        $params = ['t' => $tenantId];
        if (!empty($filters['active'])) {
            $where[] = 'lifted_at IS NULL';
        }
        $mention = (string) ($filters['mention'] ?? '');
        if (isset(SseFileCatalog::MENTIONS[$mention])) {
            $where[] = 'mention = :m';
            $params['m'] = $mention;
        }
        $limit = max(1, min(500, (int) ($filters['limit'] ?? 200)));
        $rows = $this->db->fetchAll('SELECT * FROM sse_file_mentions WHERE ' . implode(' AND ', $where) . ' ORDER BY id DESC LIMIT ' . $limit, $params);

        return array_map([self::class, 'hydrateMention'], $rows);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function hydrateMention(array $row): array
    {
        $int = static fn (mixed $v): ?int => $v !== null && (int) $v > 0 ? (int) $v : null;
        $mention = (string) ($row['mention'] ?? 'flagged');

        return [
            'id' => (int) ($row['id'] ?? 0),
            'mention' => $mention,
            'mention_label' => SseFileCatalog::label(SseFileCatalog::MENTIONS, $mention),
            'identity_id' => $int($row['identity_id'] ?? null),
            'document_id' => $int($row['document_id'] ?? null),
            'seek_record_id' => $int($row['seek_record_id'] ?? null),
            'note' => $row['note'] ?? null,
            'reference' => $row['reference'] ?? null,
            'created_by_user_id' => $int($row['created_by_user_id'] ?? null),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'lifted_at' => $row['lifted_at'] ?? null,
            'lifted_by_user_id' => $int($row['lifted_by_user_id'] ?? null),
            'lift_note' => $row['lift_note'] ?? null,
            'active' => ($row['lifted_at'] ?? null) === null,
        ];
    }

    // ---------------------------------------------------------- SEEK II

    /** @return array<string, mixed>|null */
    public function findSeekByKey(int $tenantId, string $key): ?array
    {
        $row = $this->db->fetchOne('SELECT * FROM sse_seek_records WHERE tenant_id = :t AND idempotency_key = :k', ['t' => $tenantId, 'k' => $key]);

        return $row === null ? null : self::hydrateSeek($row);
    }

    /** @return array<string, mixed>|null */
    public function findSeekByRec(int $tenantId, string $rec): ?array
    {
        $row = $this->db->fetchOne('SELECT * FROM sse_seek_records WHERE tenant_id = :t AND rec = :r', ['t' => $tenantId, 'r' => $rec]);

        return $row === null ? null : self::hydrateSeek($row);
    }

    /** @return array<string, mixed>|null */
    public function findSeek(int $id, int $tenantId): ?array
    {
        $row = $this->db->fetchOne('SELECT * FROM sse_seek_records WHERE id = :id AND tenant_id = :t', ['id' => $id, 't' => $tenantId]);

        return $row === null ? null : self::hydrateSeek($row);
    }

    /**
     * Fiche SEEK II créée, ou mise à jour si elle existe (même REC).
     *
     * @param array<string, mixed> $row colonnes de sse_seek_records (sans id, tenant_id, dates)
     * @return array{id: int, rec: string, created: bool}
     */
    public function upsertSeek(int $tenantId, array $row): array
    {
        $cols = [
            'idempotency_key', 'bio_key', 'last_name', 'first_name', 'birth_date', 'subject_unknown', 'subject_steam_id', 'subject_netid',
            'doc_type', 'doc_country', 'doc_number', 'verdict', 'enrolled', 'mod_fingers', 'mod_iris', 'mod_face', 'quality_json', 'grid',
            'game_time', 'controller_callsign', 'controller_steam_id', 'reason', 'unit', 'context_id', 'op_key',
        ];
        $now = gmdate('Y-m-d H:i:s');
        $rec = trim((string) ($row['rec'] ?? ''));
        $existing = $rec !== '' ? $this->findSeekByRec($tenantId, $rec) : null;
        $params = ['t' => $tenantId, 'now' => $now];
        foreach ($cols as $c) {
            $params[$c] = $row[$c] ?? null;
        }
        if ($existing !== null) {
            unset($params['idempotency_key']);
            $set = [];
            foreach ($cols as $c) {
                if ($c !== 'idempotency_key') {
                    $set[] = $c . ' = :' . $c;
                }
            }
            $params['id'] = $existing['id'];
            $this->db->execute('UPDATE sse_seek_records SET ' . implode(', ', $set) . ', updated_at = :now WHERE id = :id AND tenant_id = :t', $params);

            return ['id' => (int) $existing['id'], 'rec' => (string) $existing['rec'], 'created' => false];
        }
        $params['rec'] = $rec !== '' ? mb_substr($rec, 0, 40) : 'REC-PENDING-' . bin2hex(random_bytes(6));
        $id = $this->db->insert(
            'INSERT INTO sse_seek_records (tenant_id, rec, ' . implode(', ', $cols) . ', created_at)
             VALUES (:t, :rec, :' . implode(', :', $cols) . ', :now)',
            $params
        );
        if ($rec === '') {
            $rec = SseFileCatalog::seekRef($id);
            $this->db->execute('UPDATE sse_seek_records SET rec = :r WHERE id = :id AND tenant_id = :t', ['r' => $rec, 'id' => $id, 't' => $tenantId]);
        }

        return ['id' => $id, 'rec' => $rec, 'created' => true];
    }

    /** Rattachement au registre et contrôle N4 né de la fiche. */
    public function linkSeek(int $id, int $tenantId, ?int $identityId, ?int $personId, ?int $documentId, ?int $checkId): void
    {
        $this->db->execute(
            'UPDATE sse_seek_records SET identity_id = COALESCE(:i, identity_id), person_id = COALESCE(:p, person_id),
                document_id = COALESCE(:d, document_id), check_id = COALESCE(:c, check_id) WHERE id = :id AND tenant_id = :t',
            ['i' => $identityId, 'p' => $personId, 'd' => $documentId, 'c' => $checkId, 'id' => $id, 't' => $tenantId]
        );
    }

    /**
     * Fiches SEEK II d'une clé biométrique, d'un REC, d'identités ou d'un numéro de papier.
     *
     * @param array{bio_key?: string, rec?: string, identity_ids?: list<int>, doc_number?: string, limit?: int} $by
     * @return list<array<string, mixed>>
     */
    public function seekFor(int $tenantId, array $by): array
    {
        $or = [];
        $params = ['t' => $tenantId];
        if (($by['bio_key'] ?? '') !== '') {
            $or[] = 'bio_key = :bio';
            $params['bio'] = (string) $by['bio_key'];
        }
        if (($by['rec'] ?? '') !== '') {
            $or[] = 'rec = :rec';
            $params['rec'] = (string) $by['rec'];
        }
        if (($by['doc_number'] ?? '') !== '') {
            $or[] = 'doc_number = :num';
            $params['num'] = (string) $by['doc_number'];
        }
        $ids = array_slice(array_values(array_unique(array_filter(array_map('intval', $by['identity_ids'] ?? []), static fn (int $i): bool => $i > 0))), 0, 100);
        if ($ids !== []) {
            $marks = [];
            foreach ($ids as $i => $id) {
                $marks[] = ':i' . $i;
                $params['i' . $i] = $id;
            }
            $or[] = 'identity_id IN (' . implode(', ', $marks) . ')';
        }
        if ($or === []) {
            return [];
        }
        $limit = max(1, min(100, (int) ($by['limit'] ?? 20)));
        $rows = $this->db->fetchAll('SELECT * FROM sse_seek_records WHERE tenant_id = :t AND (' . implode(' OR ', $or) . ') ORDER BY id DESC LIMIT ' . $limit, $params);

        return array_map([self::class, 'hydrateSeek'], $rows);
    }

    /**
     * Identités du registre qui portent cette clé biométrique (rattachement appris par SseIdSubjectMatchService).
     *
     * @return list<array<string, mixed>>
     */
    public function identitiesByBioKey(int $tenantId, string $bioKey): array
    {
        if (trim($bioKey) === '') {
            return [];
        }
        $rows = $this->db->fetchAll(
            'SELECT * FROM sse_identities WHERE tenant_id = :t AND bio_key = :k ORDER BY id ASC LIMIT 20',
            ['t' => $tenantId, 'k' => $bioKey]
        );

        return array_map([SseIdentityRepository::class, 'hydrate'], $rows);
    }

    /**
     * @param array{q?: string, verdict?: string, limit?: int} $filters
     * @return list<array<string, mixed>>
     */
    public function listSeek(int $tenantId, array $filters = []): array
    {
        $where = ['tenant_id = :t'];
        $params = ['t' => $tenantId];
        $verdict = (string) ($filters['verdict'] ?? '');
        if (isset(SseFileCatalog::SEEK_VERDICTS[$verdict])) {
            $where[] = 'verdict = :v';
            $params['v'] = $verdict;
        }
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = "(rec LIKE :q1 OR COALESCE(bio_key, '') LIKE :q2 OR COALESCE(last_name, '') LIKE :q3 OR COALESCE(doc_number, '') LIKE :q4 OR COALESCE(controller_callsign, '') LIKE :q5)";
            $params['q1'] = $params['q2'] = $params['q3'] = $params['q4'] = $params['q5'] = '%' . $q . '%';
        }
        $limit = max(1, min(500, (int) ($filters['limit'] ?? 200)));
        $rows = $this->db->fetchAll('SELECT * FROM sse_seek_records WHERE ' . implode(' AND ', $where) . ' ORDER BY id DESC LIMIT ' . $limit, $params);

        return array_map([self::class, 'hydrateSeek'], $rows);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function hydrateSeek(array $row): array
    {
        $int = static fn (mixed $v): ?int => $v !== null && (int) $v > 0 ? (int) $v : null;
        $verdict = (string) ($row['verdict'] ?? 'inconnu');
        $quality = json_decode((string) ($row['quality_json'] ?? ''), true);
        $unknown = (int) ($row['subject_unknown'] ?? 0) === 1;

        return [
            'id' => (int) ($row['id'] ?? 0),
            'rec' => (string) ($row['rec'] ?? ''),
            'bio_key' => ($row['bio_key'] ?? null) !== null && $row['bio_key'] !== '' ? (string) $row['bio_key'] : null,
            'last_name' => ($row['last_name'] ?? null) !== null ? (string) $row['last_name'] : null,
            'first_name' => ($row['first_name'] ?? null) !== null ? (string) $row['first_name'] : null,
            'display_name' => $unknown ? '' : trim(mb_strtoupper((string) ($row['last_name'] ?? '')) . ' ' . (string) ($row['first_name'] ?? '')),
            'birth_date' => ($row['birth_date'] ?? null) !== null ? (string) $row['birth_date'] : null,
            'subject_unknown' => $unknown,
            'subject_steam_id' => $row['subject_steam_id'] ?? null,
            'doc_type' => $row['doc_type'] ?? null,
            'doc_country' => $row['doc_country'] ?? null,
            'doc_number' => $row['doc_number'] ?? null,
            'verdict' => $verdict,
            'verdict_label' => SseFileCatalog::label(SseFileCatalog::SEEK_VERDICTS, $verdict),
            'enrolled' => (int) ($row['enrolled'] ?? 0) === 1,
            'modalities' => [
                'fingers' => (int) ($row['mod_fingers'] ?? 0) === 1,
                'iris' => (int) ($row['mod_iris'] ?? 0) === 1,
                'face' => (int) ($row['mod_face'] ?? 0) === 1,
            ],
            'quality' => is_array($quality) ? $quality : [],
            'grid' => $row['grid'] ?? null,
            'game_time' => $row['game_time'] ?? null,
            'controller_callsign' => $row['controller_callsign'] ?? null,
            'controller_steam_id' => $row['controller_steam_id'] ?? null,
            'reason' => $row['reason'] ?? null,
            'unit' => $row['unit'] ?? null,
            'context_id' => $int($row['context_id'] ?? null),
            'op_key' => $row['op_key'] ?? null,
            'identity_id' => $int($row['identity_id'] ?? null),
            'person_id' => $int($row['person_id'] ?? null),
            'document_id' => $int($row['document_id'] ?? null),
            'check_id' => $int($row['check_id'] ?? null),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }
}
