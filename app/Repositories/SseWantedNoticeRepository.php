<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * SSE identités : avis de recherche (fait de jeu de rôle). Toute requête est bornée à la communauté. Un seul avis actif par
 * identité : la clé unique (tenant, identité, active_key) refuse un second avis actif, active_key repassant à NULL à la
 * fermeture. Les écrans appellent {@see self::ready()} d'abord : sans l'étape de base de données 4600, ils s'affichent sans
 * avis plutôt qu'en erreur.
 */
final class SseWantedNoticeRepository
{
    private Database $db;

    /** @var \WeakMap<PDO, bool>|null */
    private static ?\WeakMap $readyByConnection = null;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /** Les tables de l'étape sse_wanted_notices existent-elles ? Résultat gardé par connexion quand il est positif. */
    public static function ready(): bool
    {
        try {
            $pdo = Database::getPdo();
            self::$readyByConnection ??= new \WeakMap();
            $known = self::$readyByConnection[$pdo] ?? null;
            if (is_array($known) && ($known['ok'] || time() - $known['at'] < 20)) {
                return $known['ok'];
            }
            try {
                $pdo->query('SELECT 1 FROM sse_wanted_notices WHERE 1 = 0');
                self::$readyByConnection[$pdo] = ['ok' => true, 'at' => time()];

                return true;
            } catch (\Throwable) {
                // Table absente (étape 4600 pas encore passée) : on ne retente pas à chaque appel.
                self::$readyByConnection[$pdo] = ['ok' => false, 'at' => time()];

                return false;
            }
        } catch (\Throwable) {
            return false;
        }
    }

    /** Oublie le résultat gardé (tests qui créent les tables après coup). */
    public static function forgetReady(): void
    {
        self::$readyByConnection = null;
    }

    /**
     * Avis actif et encore valable de l'identité (un avis dont la date d'expiration est passée n'est plus actif).
     *
     * @return array<string, mixed>|null
     */
    public function activeForIdentity(int $tenantId, int $identityId): ?array
    {
        if ($tenantId < 1 || $identityId < 1) {
            return null;
        }
        $row = $this->db->fetchOne(
            "SELECT n.*, i.last_name AS identity_last_name, i.first_name AS identity_first_name FROM sse_wanted_notices n
             LEFT JOIN sse_identities i ON i.id = n.identity_id AND i.tenant_id = n.tenant_id
             WHERE n.tenant_id = :t AND n.identity_id = :i AND n.status = 'active'
                AND (n.expires_at IS NULL OR n.expires_at > :now) ORDER BY n.id DESC LIMIT 1",
            ['t' => $tenantId, 'i' => $identityId, 'now' => gmdate('Y-m-d H:i:s')]
        );

        return $row === null ? null : self::hydrate($row);
    }

    /**
     * Avis actifs d'une liste d'identités (une seule requête), indexés par identité.
     *
     * @param list<int> $identityIds
     * @return array<int, array<string, mixed>>
     */
    public function activeForIdentities(int $tenantId, array $identityIds): array
    {
        $ids = array_slice(array_values(array_unique(array_filter(array_map('intval', $identityIds), static fn (int $i): bool => $i > 0))), 0, 500);
        if ($tenantId < 1 || $ids === []) {
            return [];
        }
        $params = ['t' => $tenantId, 'now' => gmdate('Y-m-d H:i:s')];
        $marks = [];
        foreach ($ids as $k => $id) {
            $marks[] = ':i' . $k;
            $params['i' . $k] = $id;
        }
        $out = [];
        foreach ($this->db->fetchAll(
            "SELECT n.*, i.last_name AS identity_last_name, i.first_name AS identity_first_name FROM sse_wanted_notices n
             LEFT JOIN sse_identities i ON i.id = n.identity_id AND i.tenant_id = n.tenant_id
             WHERE n.tenant_id = :t AND n.status = 'active' AND (n.expires_at IS NULL OR n.expires_at > :now)
                AND n.identity_id IN (" . implode(', ', $marks) . ')',
            $params
        ) as $row) {
            $n = self::hydrate($row);
            $out[$n['identity_id']] = $n;
        }

        return $out;
    }

    /** @return array<string, mixed>|null */
    public function find(int $tenantId, int $id): ?array
    {
        $row = $this->db->fetchOne(
            'SELECT n.*, i.last_name AS identity_last_name, i.first_name AS identity_first_name FROM sse_wanted_notices n
             LEFT JOIN sse_identities i ON i.id = n.identity_id AND i.tenant_id = n.tenant_id
             WHERE n.id = :id AND n.tenant_id = :t',
            ['id' => $id, 't' => $tenantId]
        );

        return $row === null ? null : self::hydrate($row);
    }

    /** @return list<array<string, mixed>> les plus récents d'abord */
    public function listForIdentity(int $tenantId, int $identityId, int $limit = 50): array
    {
        return array_map([self::class, 'hydrate'], $this->db->fetchAll(
            'SELECT * FROM sse_wanted_notices WHERE tenant_id = :t AND identity_id = :i ORDER BY id DESC LIMIT ' . max(1, min(200, $limit)),
            ['t' => $tenantId, 'i' => $identityId]
        ));
    }

    /**
     * Liste des avis de la communauté.
     *
     * @param array{status?: string, ids?: list<int>, type?: string, threat?: string, q?: string, codes?: list<string>, q_codes?: list<string>, limit?: int, offset?: int} $filters
     * @return list<array<string, mixed>>
     */
    public function list(int $tenantId, array $filters = []): array
    {
        $where = ['n.tenant_id = :t'];
        $params = ['t' => $tenantId];
        $status = (string) ($filters['status'] ?? '');
        if ($status === 'active') {
            $where[] = "n.status = 'active' AND (n.expires_at IS NULL OR n.expires_at > :now)";
            $params['now'] = gmdate('Y-m-d H:i:s');
        } elseif ($status === 'expired') {
            $where[] = "(n.status = 'expired' OR (n.status = 'active' AND n.expires_at IS NOT NULL AND n.expires_at <= :now))";
            $params['now'] = gmdate('Y-m-d H:i:s');
        } elseif (in_array($status, ['lifted', 'captured'], true)) {
            $where[] = 'n.status = :status';
            $params['status'] = $status;
        }
        $type = trim((string) ($filters['type'] ?? ''));
        if ($type !== '') {
            $where[] = 'n.notice_type = :type';
            $params['type'] = $type;
        }
        $threat = trim((string) ($filters['threat'] ?? ''));
        if ($threat !== '') {
            $where[] = 'n.threat_level = :threat';
            $params['threat'] = $threat;
        }
        $onlyIds = array_slice(array_values(array_filter(array_map('intval', (array) ($filters['ids'] ?? [])), static fn (int $i): bool => $i >= 0)), 0, 300);
        if ($onlyIds !== []) {
            $marks = [];
            foreach ($onlyIds as $k => $oid) {
                $marks[] = ':oid' . $k;
                $params['oid' . $k] = $oid;
            }
            $where[] = 'n.id IN (' . implode(', ', $marks) . ')';
        }
        $q = trim((string) ($filters['q'] ?? ''));
        $codeFilter = array_values(array_filter((array) ($filters['codes'] ?? []), 'is_string'));
        $extrasReady = ($codeFilter !== [] || !empty($filters['q_codes'])) && SseWantedExtrasRepository::ready();
        if ($codeFilter !== [] && $extrasReady) {
            $where[] = SseWantedExtrasRepository::codeClause($codeFilter, $tenantId, $params, 'n', 'cf');
        }
        if ($q !== '') {
            $match = '(n.ref LIKE :q1 OR i.last_name LIKE :q2 OR i.first_name LIKE :q3';
            $qCodes = array_values(array_filter((array) ($filters['q_codes'] ?? []), 'is_string'));
            if ($qCodes !== [] && $extrasReady) {
                $match .= ' OR ' . SseWantedExtrasRepository::codeClause($qCodes, $tenantId, $params, 'n', 'cq');
            }
            $where[] = $match . ')';
            $like = '%' . str_replace(['%', '_'], '', $q) . '%';
            $params['q1'] = $params['q2'] = $params['q3'] = $like;
        }
        $limit = max(1, min(300, (int) ($filters['limit'] ?? 100)));
        $offset = max(0, (int) ($filters['offset'] ?? 0));

        return array_map([self::class, 'hydrate'], $this->db->fetchAll(
            'SELECT n.*, i.last_name AS identity_last_name, i.first_name AS identity_first_name, NULL AS identity_photo,
                    i.nationality AS identity_nationality
             FROM sse_wanted_notices n
             LEFT JOIN sse_identities i ON i.id = n.identity_id AND i.tenant_id = n.tenant_id
             WHERE ' . implode(' AND ', $where) . ' ORDER BY n.id DESC LIMIT ' . $limit . ' OFFSET ' . $offset,
            $params
        ));
    }

    /** @return array{active: int, lifted: int, expired: int, captured: int} */
    public function counts(int $tenantId): array
    {
        $out = ['active' => 0, 'lifted' => 0, 'expired' => 0, 'captured' => 0];
        $now = gmdate('Y-m-d H:i:s');
        foreach ($this->db->fetchAll(
            'SELECT status, (expires_at IS NOT NULL AND expires_at <= :now) AS past, COUNT(*) AS n FROM sse_wanted_notices
             WHERE tenant_id = :t GROUP BY status, past',
            ['t' => $tenantId, 'now' => $now]
        ) as $r) {
            $status = (string) $r['status'];
            if ($status === 'active' && (int) $r['past'] === 1) {
                $status = 'expired';
            }
            if (isset($out[$status])) {
                $out[$status] += (int) $r['n'];
            }
        }

        return $out;
    }

    /**
     * Pose un avis. Renvoie l'identifiant, ou null si un avis actif existe déjà pour l'identité.
     *
     * @param array<string, mixed> $row champs déjà validés (voir SseWantedService)
     */
    public function create(int $tenantId, int $identityId, array $row, string $refFormat, ?int $userId): ?int
    {
        // Un avis arrivé à échéance libère la place (statut « expiré », jamais supprimé).
        $this->expireDue($tenantId, $identityId);
        $now = gmdate('Y-m-d H:i:s');
        $useYear = self::formatUsesYear($refFormat);
        $year = (int) gmdate('Y');
        $scopeYear = $useYear ? $year : 0;
        for ($attempt = 0; $attempt < 6; $attempt++) {
            $seq = $this->nextSeq($tenantId, $scopeYear) + $attempt;
            $ref = self::buildRef($refFormat, $year, $seq);
            try {
                return $this->db->insert(
                    'INSERT INTO sse_wanted_notices (tenant_id, identity_id, ref, ref_year, ref_seq, notice_type, threat_level, armed, reason,
                        instructions, zone, last_seen_place, last_seen_at, contact_label, contact_detail, authority, status, active_key,
                        issued_at, expires_at, created_by_user_id, created_at)
                     VALUES (:t, :i, :ref, :ry, :rs, :type, :threat, :armed, :reason, :instr, :zone, :place, :seen, :cl, :cd, :auth,
                        \'active\', 1, :issued, :exp, :uid, :now)',
                    [
                        't' => $tenantId, 'i' => $identityId, 'ref' => $ref, 'ry' => $scopeYear, 'rs' => $seq,
                        'type' => $row['notice_type'], 'threat' => $row['threat_level'], 'armed' => $row['armed'],
                        'reason' => $row['reason'], 'instr' => $row['instructions'], 'zone' => $row['zone'],
                        'place' => $row['last_seen_place'], 'seen' => $row['last_seen_at'],
                        'cl' => $row['contact_label'], 'cd' => $row['contact_detail'], 'auth' => $row['authority'],
                        'issued' => $row['issued_at'] ?? $now, 'exp' => $row['expires_at'], 'uid' => $userId, 'now' => $now,
                    ]
                );
            } catch (\PDOException $e) {
                // Clé unique : référence déjà prise (concurrence) ou avis actif déjà présent.
                if ($this->activeRow($tenantId, $identityId) !== null) {
                    return null;
                }
                if ($attempt === 5) {
                    throw $e;
                }
            }
        }

        return null;
    }

    /**
     * Ferme l'avis actif (levée ou capture) avec son motif. Faux si l'avis n'est plus actif.
     */
    public function close(int $tenantId, int $id, string $status, string $reason, ?int $userId): bool
    {
        if (!in_array($status, ['lifted', 'captured', 'expired'], true)) {
            return false;
        }
        $now = gmdate('Y-m-d H:i:s');

        return $this->db->execute(
            "UPDATE sse_wanted_notices SET status = :s, active_key = NULL, closed_at = :now, closed_by_user_id = :u, closed_reason = :r, updated_at = :now2
             WHERE id = :id AND tenant_id = :t AND status = 'active'",
            ['s' => $status, 'now' => $now, 'u' => $userId, 'r' => mb_substr($reason, 0, 500), 'now2' => $now, 'id' => $id, 't' => $tenantId]
        ) > 0;
    }

    /** Rouvre un avis clos par erreur de renouvellement (jamais pour une levée ou une capture). */
    public function reopen(int $tenantId, int $id): bool
    {
        return $this->db->execute(
            "UPDATE sse_wanted_notices SET status = 'active', active_key = 1, closed_at = NULL, closed_by_user_id = NULL, closed_reason = NULL, updated_at = :now
             WHERE id = :id AND tenant_id = :t AND status = 'expired'",
            ['now' => gmdate('Y-m-d H:i:s'), 'id' => $id, 't' => $tenantId]
        ) > 0;
    }

    /** Repousse la date d'expiration d'un avis actif. */
    /** Champs validés d'un avis actif ; la référence et la date d'émission restent celles de l'avis. */
    public function amend(int $tenantId, int $id, array $data): bool
    {
        $params = ['t' => $tenantId, 'id' => $id, 'now' => gmdate('Y-m-d H:i:s')];
        $set = [];
        foreach (['notice_type', 'threat_level', 'armed', 'reason', 'instructions', 'zone', 'last_seen_place', 'last_seen_at', 'contact_label', 'contact_detail', 'expires_at'] as $field) {
            $set[] = $field . ' = :' . $field;
            $params[$field] = $data[$field] ?? null;
        }

        return $this->db->execute("UPDATE sse_wanted_notices SET " . implode(', ', $set) . ", updated_at = :now WHERE tenant_id = :t AND id = :id AND status = 'active'", $params) > 0;
    }

    public function extend(int $tenantId, int $id, string $expiresAt): bool
    {
        return $this->db->execute(
            "UPDATE sse_wanted_notices SET expires_at = :e, updated_at = :now WHERE id = :id AND tenant_id = :t AND status = 'active'",
            ['e' => $expiresAt, 'now' => gmdate('Y-m-d H:i:s'), 'id' => $id, 't' => $tenantId]
        ) > 0;
    }

    /**
     * Passe en « expiré » les avis actifs dont la date d'expiration est passée.
     *
     * @return list<array<string, mixed>> avis qui viennent d'expirer
     */
    public function expireDue(int $tenantId, ?int $identityId = null): array
    {
        $now = gmdate('Y-m-d H:i:s');
        $params = ['t' => $tenantId, 'now' => $now];
        $sql = "SELECT * FROM sse_wanted_notices WHERE tenant_id = :t AND status = 'active' AND expires_at IS NOT NULL AND expires_at <= :now";
        if ($identityId !== null) {
            $sql .= ' AND identity_id = :i';
            $params['i'] = $identityId;
        }
        $done = [];
        foreach ($this->db->fetchAll($sql . ' LIMIT 100', $params) as $row) {
            if ($this->close($tenantId, (int) $row['id'], 'expired', 'Date d’expiration atteinte.', null)) {
                $done[] = self::hydrate($row);
            }
        }

        return $done;
    }

    /** Dernier numéro d'ordre utilisé pour l'année (0 : compteur sans année). */
    public function nextSeq(int $tenantId, int $scopeYear): int
    {
        $row = $this->db->fetchOne(
            'SELECT MAX(ref_seq) AS m FROM sse_wanted_notices WHERE tenant_id = :t AND ref_year = :y',
            ['t' => $tenantId, 'y' => $scopeYear]
        );

        return (int) ($row['m'] ?? 0) + 1;
    }

    /** @return array<string, mixed>|null */
    private function activeRow(int $tenantId, int $identityId): ?array
    {
        return $this->db->fetchOne(
            "SELECT id FROM sse_wanted_notices WHERE tenant_id = :t AND identity_id = :i AND status = 'active'",
            ['t' => $tenantId, 'i' => $identityId]
        );
    }

    /** Le format de référence porte-t-il l'année ({Y} ou {y}) ? */
    public static function formatUsesYear(string $format): bool
    {
        return str_contains($format, '{Y}') || str_contains($format, '{y}');
    }

    /**
     * Référence d'un avis à partir du format de la communauté : {N} numéro sur 4 chiffres, {N:5} sur 5, {Y} année sur 4
     * chiffres, {y} sur 2. Exemple : « A-{N}/{Y} » donne « A-0123/2026 ».
     */
    public static function buildRef(string $format, int $year, int $seq): string
    {
        $ref = (string) preg_replace_callback(
            '/\{N(?::([1-8]))?\}/',
            static fn (array $m): string => str_pad((string) $seq, (int) ($m[1] ?? 4), '0', STR_PAD_LEFT),
            $format
        );

        return str_replace(['{Y}', '{y}'], [sprintf('%04d', $year), sprintf('%02d', $year % 100)], $ref);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function hydrate(array $row): array
    {
        $str = static fn (mixed $v): ?string => $v === null || $v === '' ? null : (string) $v;
        $status = (string) ($row['status'] ?? 'active');
        $expires = $str($row['expires_at'] ?? null);
        $expired = $status === 'active' && $expires !== null && strtotime($expires . ' UTC') !== false && strtotime($expires . ' UTC') <= time();
        $last = trim((string) ($row['identity_last_name'] ?? ''));
        $first = trim((string) ($row['identity_first_name'] ?? ''));

        return [
            'id' => (int) ($row['id'] ?? 0),
            'tenant_id' => (int) ($row['tenant_id'] ?? 0),
            'identity_id' => (int) ($row['identity_id'] ?? 0),
            'ref' => (string) ($row['ref'] ?? ''),
            'notice_type' => (string) ($row['notice_type'] ?? 'poi'),
            'threat_level' => (string) ($row['threat_level'] ?? 'medium'),
            'armed' => (string) ($row['armed'] ?? 'unknown'),
            'reason' => $str($row['reason'] ?? null),
            'instructions' => $str($row['instructions'] ?? null),
            'zone' => $str($row['zone'] ?? null),
            'last_seen_place' => $str($row['last_seen_place'] ?? null),
            'last_seen_at' => $str($row['last_seen_at'] ?? null),
            // Noms livrés enregistrés avant le retrait des mentions de jeu : sans leur ancien « (fictif) » / « — RP ».
            'contact_label' => \App\Support\SseFictionMentions::cleanNullable($str($row['contact_label'] ?? null)),
            'contact_detail' => $str($row['contact_detail'] ?? null),
            'authority' => \App\Support\SseFictionMentions::cleanNullable($str($row['authority'] ?? null)),
            'status' => $expired ? 'expired' : $status,
            'issued_at' => (string) ($row['issued_at'] ?? ''),
            'expires_at' => $expires,
            'closed_at' => $str($row['closed_at'] ?? null),
            'closed_by_user_id' => ($row['closed_by_user_id'] ?? null) !== null ? (int) $row['closed_by_user_id'] : null,
            'closed_reason' => $str($row['closed_reason'] ?? null),
            'created_by_user_id' => ($row['created_by_user_id'] ?? null) !== null ? (int) $row['created_by_user_id'] : null,
            'created_at' => (string) ($row['created_at'] ?? ''),
            'identity_name' => $last !== '' || $first !== '' ? trim(mb_strtoupper($last) . ' ' . $first) : '',
            'identity_photo' => $str($row['identity_photo'] ?? null),
            'identity_nationality' => $str($row['identity_nationality'] ?? null),
        ];
    }
}
