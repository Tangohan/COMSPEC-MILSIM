<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/**
 * Détections système : une identité sous avis de recherche actif est réapparue (contrôle, scan, consultation, photo,
 * recoupement). Aucune écriture sur l'identité elle-même. Toute requête est bornée à la communauté.
 */
final class SseWantedDetectionRepository
{
    public const STATUSES = ['new', 'acknowledged', 'dismissed'];

    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /**
     * Détection récente du même avis et du même type (et du même canal ou de la même origine) : sert à dédupliquer.
     *
     * @return array<string, mixed>|null
     */
    public function findRecent(int $tenantId, int $noticeId, string $type, string $sinceUtc, ?string $source = null, ?string $sourceRef = null): ?array
    {
        $sql = 'SELECT * FROM sse_detections WHERE tenant_id = :t AND notice_id = :n AND detection_type = :ty AND created_at >= :since';
        $params = ['t' => $tenantId, 'n' => $noticeId, 'ty' => $type, 'since' => $sinceUtc];
        if ($source !== null && $source !== '') {
            $sql .= ' AND source = :src';
            $params['src'] = $source;
        }
        if ($sourceRef !== null && $sourceRef !== '') {
            $sql .= ' AND source_ref = :ref';
            $params['ref'] = $sourceRef;
        }
        $row = $this->db->fetchOne($sql . ' ORDER BY id DESC LIMIT 1', $params);

        return $row === null ? null : self::hydrate($row);
    }

    /** Nombre de détections créées pour l'avis depuis la date donnée (plafond). */
    public function countSince(int $tenantId, int $noticeId, string $sinceUtc): int
    {
        $row = $this->db->fetchOne(
            'SELECT COUNT(*) AS n FROM sse_detections WHERE tenant_id = :t AND notice_id = :n AND created_at >= :since',
            ['t' => $tenantId, 'n' => $noticeId, 'since' => $sinceUtc]
        );

        return (int) ($row['n'] ?? 0);
    }

    /** @param array<string, mixed> $row */
    public function create(int $tenantId, array $row): int
    {
        return $this->db->insert(
            'INSERT INTO sse_detections (tenant_id, identity_id, notice_id, detection_type, source, source_ref, place, occurred_at,
                operator_label, operator_user_id, status, repeat_count, created_at)
             VALUES (:t, :i, :n, :type, :src, :ref, :place, :occ, :op, :uid, \'new\', 1, :now)',
            [
                't' => $tenantId, 'i' => (int) $row['identity_id'], 'n' => (int) $row['notice_id'],
                'type' => (string) $row['detection_type'], 'src' => (string) $row['source'],
                'ref' => $row['source_ref'] ?? null, 'place' => $row['place'] ?? null, 'occ' => $row['occurred_at'] ?? null,
                'op' => $row['operator_label'] ?? null, 'uid' => $row['operator_user_id'] ?? null, 'now' => gmdate('Y-m-d H:i:s'),
            ]
        );
    }

    /** Une répétition de plus regroupée dans une détection existante. */
    public function bump(int $tenantId, int $id): void
    {
        $this->db->execute(
            'UPDATE sse_detections SET repeat_count = repeat_count + 1, last_repeat_at = :now WHERE id = :id AND tenant_id = :t',
            ['now' => gmdate('Y-m-d H:i:s'), 'id' => $id, 't' => $tenantId]
        );
    }

    /** @return array<string, mixed>|null */
    public function find(int $tenantId, int $id): ?array
    {
        $row = $this->db->fetchOne(self::SELECT . ' WHERE d.id = :id AND d.tenant_id = :t', ['id' => $id, 't' => $tenantId]);

        return $row === null ? null : self::hydrate($row);
    }

    /**
     * @param array{status?: string, identity_id?: int, notice_id?: int, limit?: int, offset?: int} $filters
     * @return list<array<string, mixed>>
     */
    public function list(int $tenantId, array $filters = []): array
    {
        $where = ['d.tenant_id = :t'];
        $params = ['t' => $tenantId];
        $status = (string) ($filters['status'] ?? '');
        if (in_array($status, self::STATUSES, true)) {
            $where[] = 'd.status = :status';
            $params['status'] = $status;
        }
        if ((int) ($filters['identity_id'] ?? 0) > 0) {
            $where[] = 'd.identity_id = :i';
            $params['i'] = (int) $filters['identity_id'];
        }
        if ((int) ($filters['notice_id'] ?? 0) > 0) {
            $where[] = 'd.notice_id = :n';
            $params['n'] = (int) $filters['notice_id'];
        }
        $limit = max(1, min(300, (int) ($filters['limit'] ?? 100)));
        $offset = max(0, (int) ($filters['offset'] ?? 0));

        return array_map([self::class, 'hydrate'], $this->db->fetchAll(
            self::SELECT . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY d.id DESC LIMIT ' . $limit . ' OFFSET ' . $offset,
            $params
        ));
    }

    /** @return array{new: int, acknowledged: int, dismissed: int} */
    public function counts(int $tenantId): array
    {
        $out = ['new' => 0, 'acknowledged' => 0, 'dismissed' => 0];
        foreach ($this->db->fetchAll('SELECT status, COUNT(*) AS n FROM sse_detections WHERE tenant_id = :t GROUP BY status', ['t' => $tenantId]) as $r) {
            if (isset($out[(string) $r['status']])) {
                $out[(string) $r['status']] = (int) $r['n'];
            }
        }

        return $out;
    }

    /** Prise en compte ou rejet, avec commentaire. Faux si la détection n'existe pas dans la communauté. */
    public function setStatus(int $tenantId, int $id, string $status, string $comment, ?int $userId): bool
    {
        if (!in_array($status, self::STATUSES, true)) {
            return false;
        }

        return $this->db->execute(
            'UPDATE sse_detections SET status = :s, comment = :c, handled_by_user_id = :u, handled_at = :now WHERE id = :id AND tenant_id = :t',
            ['s' => $status, 'c' => $comment !== '' ? mb_substr($comment, 0, 500) : null, 'u' => $userId, 'now' => gmdate('Y-m-d H:i:s'), 'id' => $id, 't' => $tenantId]
        ) > 0;
    }

    private const SELECT = 'SELECT d.*, n.ref AS notice_ref, n.threat_level AS notice_threat, n.status AS notice_status,
            i.last_name AS identity_last_name, i.first_name AS identity_first_name
        FROM sse_detections d
        LEFT JOIN sse_wanted_notices n ON n.id = d.notice_id AND n.tenant_id = d.tenant_id
        LEFT JOIN sse_identities i ON i.id = d.identity_id AND i.tenant_id = d.tenant_id';

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function hydrate(array $row): array
    {
        $str = static fn (mixed $v): ?string => $v === null || $v === '' ? null : (string) $v;
        $last = trim((string) ($row['identity_last_name'] ?? ''));
        $first = trim((string) ($row['identity_first_name'] ?? ''));

        return [
            'id' => (int) ($row['id'] ?? 0),
            'identity_id' => (int) ($row['identity_id'] ?? 0),
            'notice_id' => (int) ($row['notice_id'] ?? 0),
            'notice_ref' => (string) ($row['notice_ref'] ?? ''),
            'notice_threat' => (string) ($row['notice_threat'] ?? ''),
            'identity_name' => trim(mb_strtoupper($last) . ' ' . $first),
            'type' => (string) ($row['detection_type'] ?? ''),
            'source' => (string) ($row['source'] ?? 'system'),
            'source_ref' => $str($row['source_ref'] ?? null),
            'place' => $str($row['place'] ?? null),
            'occurred_at' => $str($row['occurred_at'] ?? null),
            'operator_label' => $str($row['operator_label'] ?? null),
            'status' => (string) ($row['status'] ?? 'new'),
            'comment' => $str($row['comment'] ?? null),
            'handled_at' => $str($row['handled_at'] ?? null),
            'repeat_count' => max(1, (int) ($row['repeat_count'] ?? 1)),
            'created_at' => (string) ($row['created_at'] ?? ''),
        ];
    }
}
