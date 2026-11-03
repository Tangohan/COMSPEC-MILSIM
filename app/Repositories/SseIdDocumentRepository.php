<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\SseIdentityCatalog;

/**
 * COMSPEC IDENTITY — papiers d'identité (carte, passeport, permis) et leurs exemplaires en jeu.
 *
 * Numéro unique par communauté, pays et type. Statuts indépendants : origine, intégrité, validité, signalement,
 * attribution, puce. Cycle : brouillon → validé → émis → révoqué ; seuls les papiers émis partent dans le pack.
 */
final class SseIdDocumentRepository
{
    /** Plafond du pack par carte (contrat Lot 1). */
    public const PACK_MAX_PER_MAP = 500;

    private const SELECT = 'SELECT d.*, i.last_name AS identity_last_name, i.first_name AS identity_first_name, i.kind AS identity_kind,
            i.person_id AS identity_person_id, i.user_id AS identity_user_id, i.sex AS identity_sex, i.birth_date AS identity_birth_date, i.nationality AS identity_nationality
        FROM sse_id_documents d JOIN sse_identities i ON i.id = d.identity_id AND i.tenant_id = d.tenant_id';

    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
        SseIdentityRepository::ensureSchema();
    }

    /**
     * @param array<string, mixed> $data identity_id, doc_type, country_code, doc_number, issued_on, expires_on, authority, statuts…
     */
    public function create(int $tenantId, array $data, ?int $userId = null): int
    {
        $s = self::normalizeStatuses($data);
        $holder = (int) ($data['holder_person_id'] ?? 0);
        $ctx = (int) ($data['context_id'] ?? 0);
        $op = trim((string) ($data['op_key'] ?? ''));

        return $this->db->insert(
            'INSERT INTO sse_id_documents (tenant_id, context_id, op_key, identity_id, holder_person_id, doc_type, country_code, doc_number,
                issued_on, expires_on, authority, chip_status, st_origin, st_integrity, st_validity, st_report, st_attribution,
                forgery_kind, forgery_quality, workflow, notes, layout, print_json, duplicated_from_id, batch_id, created_by_user_id, created_at)
             VALUES (:t, :ctx, :op, :identity, :holder, :type, :country, :number, :issued, :expires, :authority, :chip, :origin,
                :integrity, :validity, :report, :attribution, :fkind, :fquality, :wf, :notes, :layout, :print, :dup, :batch, :uid, :now)',
            [
                't' => $tenantId,
                'ctx' => $ctx > 0 ? $ctx : null,
                'op' => $op !== '' ? mb_substr($op, 0, 64) : null,
                'identity' => (int) $data['identity_id'],
                'holder' => $holder > 0 ? $holder : null,
                'type' => SseIdentityCatalog::pick(SseIdentityCatalog::DOC_TYPES, $data['doc_type'] ?? null, 'id_card'),
                'country' => strtoupper(substr(trim((string) ($data['country_code'] ?? '')), 0, 3)),
                'number' => self::normalizeNumber((string) ($data['doc_number'] ?? '')),
                'issued' => self::date($data['issued_on'] ?? null),
                'expires' => self::date($data['expires_on'] ?? null),
                'authority' => ($a = trim((string) ($data['authority'] ?? ''))) !== '' ? mb_substr($a, 0, 160) : null,
                'chip' => $s['chip_status'],
                'origin' => $s['st_origin'],
                'integrity' => $s['st_integrity'],
                'validity' => $s['st_validity'],
                'report' => $s['st_report'],
                'attribution' => $s['st_attribution'],
                'fkind' => $s['forgery_kind'],
                'fquality' => $s['forgery_quality'],
                'wf' => 'draft',
                'notes' => ($n = trim((string) ($data['notes'] ?? ''))) !== '' ? mb_substr($n, 0, 4000) : null,
                'layout' => \App\Support\SseIdWorkshopCatalog::layout($data['layout'] ?? null),
                'print' => \App\Support\SseIdWorkshopCatalog::printJson($data['print'] ?? null),
                'dup' => (int) ($data['duplicated_from_id'] ?? 0) > 0 ? (int) $data['duplicated_from_id'] : null,
                'batch' => (int) ($data['batch_id'] ?? 0) > 0 ? (int) $data['batch_id'] : null,
                'uid' => $userId,
                'now' => gmdate('Y-m-d H:i:s'),
            ]
        );
    }

    public function numberExists(int $tenantId, string $country, string $type, string $number, int $exceptId = 0): bool
    {
        $row = $this->db->fetchOne(
            'SELECT id FROM sse_id_documents WHERE tenant_id = :t AND country_code = :c AND doc_type = :type AND doc_number = :n',
            ['t' => $tenantId, 'c' => strtoupper($country), 'type' => $type, 'n' => self::normalizeNumber($number)]
        );

        return $row !== null && (int) $row['id'] !== $exceptId;
    }

    /**
     * Modifie le numéro, les dates, l'autorité, la carte, l'opération et les notes (papier non révoqué).
     *
     * @param array<string, mixed> $data
     */
    public function updateDetails(int $id, int $tenantId, array $data): bool
    {
        $ctx = (int) ($data['context_id'] ?? 0);
        $op = trim((string) ($data['op_key'] ?? ''));

        return $this->db->execute(
            "UPDATE sse_id_documents SET doc_number = :number, issued_on = :issued, expires_on = :expires, authority = :authority,
                context_id = :ctx, op_key = :op, notes = :notes, updated_at = :now
             WHERE id = :id AND tenant_id = :t AND workflow <> 'revoked'",
            [
                'number' => self::normalizeNumber((string) ($data['doc_number'] ?? '')),
                'issued' => self::date($data['issued_on'] ?? null),
                'expires' => self::date($data['expires_on'] ?? null),
                'authority' => ($a = trim((string) ($data['authority'] ?? ''))) !== '' ? mb_substr($a, 0, 160) : null,
                'ctx' => $ctx > 0 ? $ctx : null,
                'op' => $op !== '' ? mb_substr($op, 0, 64) : null,
                'notes' => ($n = trim((string) ($data['notes'] ?? ''))) !== '' ? mb_substr($n, 0, 4000) : null,
                'now' => gmdate('Y-m-d H:i:s'),
                'id' => $id,
                't' => $tenantId,
            ]
        ) > 0;
    }

    /**
     * Atelier (lot 6) : modèle d'impression et champs imprimés en plus (taille, yeux, adresse, signature, tampon…).
     *
     * @param array<string, mixed>|null $print
     */
    public function updatePrint(int $id, int $tenantId, ?string $layout, ?array $print): bool
    {
        return $this->db->execute(
            'UPDATE sse_id_documents SET layout = :layout, print_json = :print, updated_at = :now WHERE id = :id AND tenant_id = :t',
            [
                'layout' => \App\Support\SseIdWorkshopCatalog::layout($layout),
                'print' => \App\Support\SseIdWorkshopCatalog::printJson($print),
                'now' => gmdate('Y-m-d H:i:s'),
                'id' => $id,
                't' => $tenantId,
            ]
        ) > 0;
    }

    /** Copies d'un papier (papier dupliqué : son remplaçant). @return list<int> */
    public function duplicatesOf(int $id, int $tenantId): array
    {
        return array_map(static fn (array $r): int => (int) $r['id'], $this->db->fetchAll(
            'SELECT id FROM sse_id_documents WHERE tenant_id = :t AND duplicated_from_id = :d ORDER BY id',
            ['t' => $tenantId, 'd' => $id]
        ));
    }

    /** Signalement porté sur le papier (perdu, volé…), sans toucher aux autres statuts. */
    public function setReport(int $id, int $tenantId, string $report): bool
    {
        if (!isset(SseIdentityCatalog::REPORTS[$report])) {
            return false;
        }

        return $this->db->execute(
            'UPDATE sse_id_documents SET st_report = :r, updated_at = :now WHERE id = :id AND tenant_id = :t',
            ['r' => $report, 'now' => gmdate('Y-m-d H:i:s'), 'id' => $id, 't' => $tenantId]
        ) > 0;
    }

    /** Supprime un brouillon (jamais un papier validé, émis ou révoqué) et le retire des kits. */
    public function deleteDraft(int $id, int $tenantId): bool
    {
        $done = $this->db->execute(
            "DELETE FROM sse_id_documents WHERE id = :id AND tenant_id = :t AND workflow = 'draft'",
            ['id' => $id, 't' => $tenantId]
        ) > 0;
        if ($done) {
            $this->db->execute('DELETE FROM sse_id_kits WHERE document_id = :id AND tenant_id = :t', ['id' => $id, 't' => $tenantId]);
        }

        return $done;
    }

    /** Nombre de papiers d'un pays (un pays utilisé ne se supprime pas : il s'archive). */
    public function countForCountry(int $tenantId, string $country): int
    {
        return (int) ($this->db->fetchOne(
            'SELECT COUNT(*) AS n FROM sse_id_documents WHERE tenant_id = :t AND country_code = :c',
            ['t' => $tenantId, 'c' => strtoupper(trim($country))]
        )['n'] ?? 0);
    }

    /** @return array<string, mixed>|null */
    public function find(int $id, int $tenantId): ?array
    {
        $row = $this->db->fetchOne(self::SELECT . ' WHERE d.id = :id AND d.tenant_id = :t', ['id' => $id, 't' => $tenantId]);

        return $row === null ? null : self::hydrate($row);
    }

    /**
     * Papier « des fichiers » pour une vérification : émis ou révoqué, jamais un faux (un faux n'existe pas
     * dans les fichiers de l'État émetteur).
     *
     * @return array<string, mixed>|null
     */
    public function findOfficialByNumber(int $tenantId, string $country, string $type, string $number): ?array
    {
        $params = [
            't' => $tenantId,
            'c' => strtoupper(trim($country)),
            'n' => self::normalizeNumber($number),
        ];
        $typeSql = '';
        if ($type !== '' && isset(SseIdentityCatalog::DOC_TYPES[$type])) {
            $typeSql = ' AND d.doc_type = :type';
            $params['type'] = $type;
        }
        $row = $this->db->fetchOne(
            self::SELECT . " WHERE d.tenant_id = :t AND d.country_code = :c AND d.doc_number = :n" . $typeSql
            . " AND d.workflow IN ('issued', 'revoked') AND d.st_origin <> 'forged' ORDER BY d.id DESC LIMIT 1",
            $params
        );

        return $row === null ? null : self::hydrate($row);
    }

    /**
     * @param array{workflow?: string, doc_type?: string, country_code?: string, origin?: string, q?: string, identity_id?: int, user_id?: int, holder_person_id?: int, limit?: int} $filters
     * @return list<array<string, mixed>>
     */
    public function list(int $tenantId, array $filters = []): array
    {
        $where = ['d.tenant_id = :t'];
        $params = ['t' => $tenantId];
        foreach ([
            'workflow' => [SseIdentityCatalog::WORKFLOWS, 'd.workflow'],
            'doc_type' => [SseIdentityCatalog::DOC_TYPES, 'd.doc_type'],
            'origin' => [SseIdentityCatalog::ORIGINS, 'd.st_origin'],
        ] as $key => [$map, $col]) {
            $v = (string) ($filters[$key] ?? '');
            if ($v !== '' && isset($map[$v])) {
                $where[] = $col . ' = :' . $key;
                $params[$key] = $v;
            }
        }
        $country = strtoupper(trim((string) ($filters['country_code'] ?? '')));
        if ($country !== '') {
            $where[] = 'd.country_code = :country';
            $params['country'] = $country;
        }
        if ((int) ($filters['identity_id'] ?? 0) > 0) {
            $where[] = 'd.identity_id = :identity';
            $params['identity'] = (int) $filters['identity_id'];
        }
        if ((int) ($filters['user_id'] ?? 0) > 0) {
            $where[] = 'i.user_id = :user';
            $params['user'] = (int) $filters['user_id'];
        }
        if ((int) ($filters['holder_person_id'] ?? 0) > 0) {
            $where[] = '(d.holder_person_id = :p1 OR i.person_id = :p2)';
            $params['p1'] = $params['p2'] = (int) $filters['holder_person_id'];
        }
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(d.doc_number LIKE :q1 OR i.last_name LIKE :q2 OR i.first_name LIKE :q3)';
            $params['q1'] = $params['q2'] = $params['q3'] = '%' . $q . '%';
        }
        $limit = max(1, min(500, (int) ($filters['limit'] ?? 200)));
        $rows = $this->db->fetchAll(self::SELECT . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY d.id DESC LIMIT ' . $limit, $params);

        return array_map([self::class, 'hydrate'], $rows);
    }

    /**
     * Papiers émis pour une carte (context_id NULL = toutes les cartes) et une opération (op_key NULL = toutes).
     *
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    public function listForPack(int $tenantId, int $mapId, ?string $opKey, ?string $since, int $offset, int $limit): array
    {
        $where = ["d.tenant_id = :t", "d.workflow = 'issued'", '(d.context_id IS NULL OR d.context_id = :map)'];
        $params = ['t' => $tenantId, 'map' => $mapId];
        if ($opKey !== null && $opKey !== '') {
            $where[] = '(d.op_key IS NULL OR d.op_key = :op)';
            $params['op'] = $opKey;
        }
        if ($since !== null && $since !== '') {
            $where[] = '(COALESCE(d.updated_at, d.created_at) >= :since1 OR d.issued_at >= :since2)';
            $params['since1'] = $params['since2'] = $since;
        }
        $sqlWhere = implode(' AND ', $where);
        $total = (int) ($this->db->fetchOne('SELECT COUNT(*) AS n FROM sse_id_documents d WHERE ' . $sqlWhere, $params)['n'] ?? 0);
        $total = min($total, self::PACK_MAX_PER_MAP);
        $offset = max(0, $offset);
        $limit = max(0, min($limit, self::PACK_MAX_PER_MAP - $offset));
        if ($limit === 0) {
            return ['rows' => [], 'total' => $total];
        }
        $rows = $this->db->fetchAll(
            self::SELECT . ' WHERE ' . $sqlWhere . ' ORDER BY d.id ASC LIMIT ' . $limit . ' OFFSET ' . $offset,
            $params
        );

        return ['rows' => array_map([self::class, 'hydrate'], $rows), 'total' => $total];
    }

    /**
     * Papiers révoqués depuis $since (pack incrémental : le mod les retire de sa base locale).
     *
     * @return list<int>
     */
    public function revokedSince(int $tenantId, int $mapId, string $since): array
    {
        $rows = $this->db->fetchAll(
            "SELECT id FROM sse_id_documents WHERE tenant_id = :t AND workflow = 'revoked' AND (context_id IS NULL OR context_id = :map)
             AND revoked_at >= :since ORDER BY id LIMIT 500",
            ['t' => $tenantId, 'map' => $mapId, 'since' => $since]
        );

        return array_map(static fn (array $r): int => (int) $r['id'], $rows);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateStatuses(int $id, int $tenantId, array $data): bool
    {
        $s = self::normalizeStatuses($data);
        $holder = (int) ($data['holder_person_id'] ?? 0);

        return $this->db->execute(
            'UPDATE sse_id_documents SET chip_status = :chip, st_origin = :origin, st_integrity = :integrity, st_validity = :validity,
                st_report = :report, st_attribution = :attribution, forgery_kind = :fkind, forgery_quality = :fquality,
                holder_person_id = :holder, updated_at = :now
             WHERE id = :id AND tenant_id = :t',
            [
                'chip' => $s['chip_status'],
                'origin' => $s['st_origin'],
                'integrity' => $s['st_integrity'],
                'validity' => $s['st_validity'],
                'report' => $s['st_report'],
                'attribution' => $s['st_attribution'],
                'fkind' => $s['forgery_kind'],
                'fquality' => $s['forgery_quality'],
                'holder' => $holder > 0 ? $holder : null,
                'now' => gmdate('Y-m-d H:i:s'),
                'id' => $id,
                't' => $tenantId,
            ]
        ) > 0;
    }

    /**
     * Passe le papier à l'étape suivante du cycle si la transition est permise.
     */
    public function transition(int $id, int $tenantId, string $to, ?int $userId = null, string $reason = ''): bool
    {
        $doc = $this->find($id, $tenantId);
        if ($doc === null || !in_array($to, SseIdentityCatalog::TRANSITIONS[$doc['workflow']] ?? [], true)) {
            return false;
        }
        $now = gmdate('Y-m-d H:i:s');
        $set = ['workflow = :wf', 'updated_at = :now'];
        $params = ['wf' => $to, 'now' => $now, 'id' => $id, 't' => $tenantId, 'from' => $doc['workflow']];
        if ($to === 'validated') {
            $set[] = 'validated_at = :at';
            $set[] = 'validated_by_user_id = :uid';
            $params['at'] = $now;
            $params['uid'] = $userId;
        } elseif ($to === 'issued') {
            $set[] = 'issued_at = :at';
            $params['at'] = $now;
        } elseif ($to === 'revoked') {
            $set[] = 'revoked_at = :at';
            $set[] = 'revoked_reason = :reason';
            $set[] = "st_validity = 'revoked'";
            $params['at'] = $now;
            $params['reason'] = ($r = trim($reason)) !== '' ? mb_substr($r, 0, 255) : null;
        } elseif ($to === 'draft') {
            $set[] = 'validated_at = NULL';
            $set[] = 'validated_by_user_id = NULL';
        }

        return $this->db->execute(
            'UPDATE sse_id_documents SET ' . implode(', ', $set) . ' WHERE id = :id AND tenant_id = :t AND workflow = :from',
            $params
        ) > 0;
    }

    /**
     * Exemplaires physiques connus en jeu.
     *
     * @return list<array<string, mixed>>
     */
    public function instances(int $documentId, int $tenantId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM sse_id_document_instances WHERE document_id = :d AND tenant_id = :t ORDER BY id DESC LIMIT 50',
            ['d' => $documentId, 't' => $tenantId]
        );
    }

    /** @return array<string, int> */
    public function counts(int $tenantId): array
    {
        $out = array_fill_keys(array_keys(SseIdentityCatalog::WORKFLOWS), 0);
        foreach ($this->db->fetchAll('SELECT workflow, COUNT(*) AS n FROM sse_id_documents WHERE tenant_id = :t GROUP BY workflow', ['t' => $tenantId]) as $r) {
            $out[(string) $r['workflow']] = (int) $r['n'];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $data
     * @return array{chip_status: string, st_origin: string, st_integrity: string, st_validity: string, st_report: string, st_attribution: string, forgery_kind: ?string, forgery_quality: ?int}
     */
    public static function normalizeStatuses(array $data): array
    {
        $origin = SseIdentityCatalog::pick(SseIdentityCatalog::ORIGINS, $data['st_origin'] ?? null, 'official');
        $fkind = SseIdentityCatalog::pick(SseIdentityCatalog::FORGERY_KINDS, $data['forgery_kind'] ?? null, '');
        $fq = $data['forgery_quality'] ?? null;
        $isForged = $origin === 'forged' || $fkind !== '';

        return [
            'chip_status' => SseIdentityCatalog::pick(SseIdentityCatalog::CHIPS, $data['chip_status'] ?? null, 'ok'),
            'st_origin' => $origin,
            'st_integrity' => SseIdentityCatalog::pick(SseIdentityCatalog::INTEGRITIES, $data['st_integrity'] ?? null, 'intact'),
            'st_validity' => SseIdentityCatalog::pick(SseIdentityCatalog::VALIDITIES, $data['st_validity'] ?? null, 'valid'),
            'st_report' => SseIdentityCatalog::pick(SseIdentityCatalog::REPORTS, $data['st_report'] ?? null, 'none'),
            'st_attribution' => SseIdentityCatalog::pick(SseIdentityCatalog::ATTRIBUTIONS, $data['st_attribution'] ?? null, 'holder'),
            'forgery_kind' => $isForged ? ($fkind !== '' ? $fkind : 'counterfeit') : null,
            'forgery_quality' => $isForged ? max(0, min(100, is_numeric($fq) ? (int) $fq : 50)) : null,
        ];
    }

    /** Numéro tel qu'imprimé : majuscules, lettres et chiffres seulement. */
    public static function normalizeNumber(string $number): string
    {
        return substr((string) preg_replace('/[^A-Z0-9]/', '', strtoupper($number)), 0, 20);
    }

    private static function date(mixed $v): ?string
    {
        $s = trim(is_scalar($v) ? (string) $v : '');

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $s) === 1 ? $s : null;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function hydrate(array $row): array
    {
        $id = (int) ($row['id'] ?? 0);
        $wf = (string) ($row['workflow'] ?? 'draft');
        $expires = ($row['expires_on'] ?? null) !== null ? substr((string) $row['expires_on'], 0, 10) : null;

        return [
            'id' => $id,
            'ref' => SseIdentityCatalog::documentRef($id),
            'context_id' => ($row['context_id'] ?? null) !== null ? (int) $row['context_id'] : null,
            'op_key' => ($row['op_key'] ?? null) !== null ? (string) $row['op_key'] : null,
            'identity_id' => (int) ($row['identity_id'] ?? 0),
            'holder_person_id' => ($row['holder_person_id'] ?? null) !== null ? (int) $row['holder_person_id'] : null,
            'doc_type' => (string) ($row['doc_type'] ?? 'id_card'),
            'doc_type_label' => SseIdentityCatalog::label(SseIdentityCatalog::DOC_TYPES, (string) ($row['doc_type'] ?? '')),
            'country_code' => (string) ($row['country_code'] ?? ''),
            'doc_number' => (string) ($row['doc_number'] ?? ''),
            'issued_on' => ($row['issued_on'] ?? null) !== null ? substr((string) $row['issued_on'], 0, 10) : null,
            'expires_on' => $expires,
            'expired' => $expires !== null && $expires < gmdate('Y-m-d'),
            'authority' => ($row['authority'] ?? null) !== null ? (string) $row['authority'] : null,
            'chip_status' => (string) ($row['chip_status'] ?? 'ok'),
            'st_origin' => (string) ($row['st_origin'] ?? 'official'),
            'st_integrity' => (string) ($row['st_integrity'] ?? 'intact'),
            'st_validity' => (string) ($row['st_validity'] ?? 'valid'),
            'st_report' => (string) ($row['st_report'] ?? 'none'),
            'st_attribution' => (string) ($row['st_attribution'] ?? 'holder'),
            'forgery_kind' => ($row['forgery_kind'] ?? null) !== null ? (string) $row['forgery_kind'] : null,
            'forgery_quality' => ($row['forgery_quality'] ?? null) !== null ? (int) $row['forgery_quality'] : null,
            'workflow' => $wf,
            'workflow_label' => SseIdentityCatalog::label(SseIdentityCatalog::WORKFLOWS, $wf),
            'validated_at' => $row['validated_at'] ?? null,
            'issued_at' => $row['issued_at'] ?? null,
            'revoked_at' => $row['revoked_at'] ?? null,
            'revoked_reason' => $row['revoked_reason'] ?? null,
            'notes' => $row['notes'] ?? null,
            'layout' => \App\Support\SseIdWorkshopCatalog::layout($row['layout'] ?? null),
            'print' => \App\Support\SseIdWorkshopCatalog::printFields($row['print_json'] ?? null),
            'duplicated_from_id' => ($row['duplicated_from_id'] ?? null) !== null && (int) $row['duplicated_from_id'] > 0 ? (int) $row['duplicated_from_id'] : null,
            'batch_id' => ($row['batch_id'] ?? null) !== null && (int) $row['batch_id'] > 0 ? (int) $row['batch_id'] : null,
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => $row['updated_at'] ?? null,
            'identity' => [
                'last_name' => (string) ($row['identity_last_name'] ?? ''),
                'first_name' => (string) ($row['identity_first_name'] ?? ''),
                'kind' => (string) ($row['identity_kind'] ?? ''),
                'person_id' => ($row['identity_person_id'] ?? null) !== null ? (int) $row['identity_person_id'] : null,
                'user_id' => ($row['identity_user_id'] ?? null) !== null ? (int) $row['identity_user_id'] : null,
                'sex' => (string) ($row['identity_sex'] ?? 'X'),
                'birth_date' => ($row['identity_birth_date'] ?? null) !== null ? substr((string) $row['identity_birth_date'], 0, 10) : null,
                'nationality' => ($row['identity_nationality'] ?? null) !== null ? (string) $row['identity_nationality'] : null,
            ],
        ];
    }
}
