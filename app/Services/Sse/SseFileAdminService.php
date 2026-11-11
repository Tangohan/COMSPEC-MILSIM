<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Core\Database;
use App\Repositories\SseFileRecordRepository;
use App\Repositories\SseIdentityRepository;
use App\Support\SseFileText;

/**
 * Fichiers SSE (back-office) : liste des fiches, doublons probables, fusion de deux fiches, purge selon la durée de
 * conservation. Toujours dans la communauté demandée.
 */
final class SseFileAdminService
{
    private Database $db;

    public function __construct(private ?SseFileRecordRepository $records = null, private ?SseIdentityRepository $identities = null)
    {
        $this->records ??= new SseFileRecordRepository();
        $this->identities ??= new SseIdentityRepository();
        $this->db = $this->records->db();
    }

    /**
     * Fiches de la communauté (hors faux), avec le nombre d'alias, de liens, de photos et de consultations.
     *
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    public function subjects(int $tenantId, string $q, int $page, int $perPage = 40): array
    {
        $where = "i.tenant_id = :t AND i.kind <> 'forged'";
        $params = ['t' => $tenantId];
        $nq = trim($q);
        if ($nq !== '') {
            $where .= ' AND (i.last_name LIKE :q1 OR i.first_name LIKE :q2 OR COALESCE(i.alias, \'\') LIKE :q3 OR COALESCE(i.arma_key, \'\') LIKE :q4)';
            $params['q1'] = $params['q2'] = $params['q3'] = $params['q4'] = '%' . SseFileRecordRepository::likeEscape($nq) . '%';
        }
        $total = (int) ($this->db->fetchOne('SELECT COUNT(*) AS n FROM sse_identities i WHERE ' . $where, $params)['n'] ?? 0);
        $rows = $this->db->fetchAll(
            'SELECT i.*,
                (SELECT COUNT(*) FROM sse_file_aliases a WHERE a.tenant_id = i.tenant_id AND a.identity_id = i.id AND a.kind NOT IN (\'legal\', \'registry\')) AS aliases_count,
                (SELECT COUNT(*) FROM sse_file_links l WHERE l.tenant_id = i.tenant_id AND l.identity_id = i.id) AS links_count,
                (SELECT COUNT(*) FROM sse_file_photos ph WHERE ph.tenant_id = i.tenant_id AND ph.identity_id = i.id) AS photos_count,
                (SELECT COUNT(*) FROM sse_file_queries fq WHERE fq.tenant_id = i.tenant_id AND fq.identity_id = i.id) AS queries_count,
                (SELECT merged_into_id FROM sse_file_profiles p WHERE p.tenant_id = i.tenant_id AND p.identity_id = i.id) AS merged_into_id
             FROM sse_identities i WHERE ' . $where . ' ORDER BY i.id DESC LIMIT ' . max(1, min(200, $perPage)) . ' OFFSET ' . (max(1, $page) - 1) * $perPage,
            $params
        );
        $out = [];
        foreach ($rows as $r) {
            $h = SseIdentityRepository::hydrate($r);
            foreach (['aliases_count', 'links_count', 'photos_count', 'queries_count', 'merged_into_id'] as $k) {
                $h[$k] = (int) ($r[$k] ?? 0);
            }
            $out[] = $h;
        }

        return ['rows' => $out, 'total' => $total];
    }

    /**
     * Doublons probables : même nom à la prononciation et même date de naissance (au plus $limit paires).
     *
     * @return list<array{a: array<string, mixed>, b: array<string, mixed>, reason: string}>
     */
    public function duplicates(int $tenantId, int $limit = 30): array
    {
        $this->records->syncRegistryNames($tenantId);
        $rows = $this->db->fetchAll(
            "SELECT a.identity_id, a.phonetic, i.birth_date FROM sse_file_aliases a INNER JOIN sse_identities i ON i.id = a.identity_id AND i.tenant_id = a.tenant_id
             LEFT JOIN sse_file_profiles p ON p.tenant_id = a.tenant_id AND p.identity_id = a.identity_id
             WHERE a.tenant_id = :t AND a.kind = 'legal' AND i.kind <> 'forged' AND p.merged_into_id IS NULL AND i.birth_date IS NOT NULL
             ORDER BY a.phonetic, i.birth_date LIMIT 5000",
            ['t' => $tenantId]
        );
        $groups = [];
        foreach ($rows as $r) {
            $groups[$r['phonetic'] . '|' . $r['birth_date']][] = (int) $r['identity_id'];
        }
        $pairs = [];
        foreach ($groups as $ids) {
            $ids = array_values(array_unique($ids));
            if (count($ids) < 2) {
                continue;
            }
            $found = $this->identities->findMany($tenantId, $ids);
            for ($n = 1; $n < count($ids) && count($pairs) < $limit; $n++) {
                if (isset($found[$ids[0]], $found[$ids[$n]])) {
                    $pairs[] = ['a' => $found[$ids[0]], 'b' => $found[$ids[$n]], 'reason' => 'Même nom à la prononciation et même date de naissance'];
                }
            }
        }

        return $pairs;
    }

    /**
     * Fusion : la fiche $fromId rejoint $intoId (alias, liens, photos, annotations, mentions, contacts, consultations,
     * extractions, décisions de recoupement). Son nom devient un alias de la fiche gardée ; elle sort des fichiers
     * (merged_into_id) sans être supprimée : ses papiers restent consultables.
     */
    public function merge(int $tenantId, int $fromId, int $intoId, ?int $userId): bool
    {
        if ($fromId === $intoId || $fromId < 1 || $intoId < 1) {
            return false;
        }
        $from = $this->identities->find($fromId, $tenantId);
        $into = $this->identities->find($intoId, $tenantId);
        if ($from === null || $into === null || $from['kind'] === 'forged' || $into['kind'] === 'forged') {
            return false;
        }
        $params = ['t' => $tenantId, 'f' => $fromId, 'i' => $intoId];
        $this->db->execute("UPDATE sse_file_aliases SET identity_id = :i WHERE tenant_id = :t AND identity_id = :f AND kind NOT IN ('legal', 'registry')", $params);
        foreach (['sse_file_links', 'sse_file_photos', 'sse_file_notes', 'sse_file_xref_decisions'] as $table) {
            $this->db->execute("UPDATE {$table} SET identity_id = :i WHERE tenant_id = :t AND identity_id = :f", $params);
        }
        $this->db->execute('UPDATE sse_file_links SET linked_identity_id = :i WHERE tenant_id = :t AND linked_identity_id = :f', $params);
        foreach (['sse_file_mentions', 'sse_file_queries', 'sse_phone_extractions', 'sse_id_checks', 'sse_seek_records'] as $table) {
            try {
                $this->db->execute("UPDATE {$table} SET identity_id = :i WHERE tenant_id = :t AND identity_id = :f", $params);
            } catch (\PDOException) {
                // Table absente sur cette installation : rien à déplacer.
            }
        }
        $legal = trim($from['last_name'] . ' ' . $from['first_name']);
        if ($legal !== '' && SseFileText::norm($legal) !== SseFileText::norm($into['last_name'] . ' ' . $into['first_name'])) {
            $this->records->addAlias($tenantId, $intoId, $legal, 'aka', 'known', 0, $userId);
        }
        $this->records->saveProfile($tenantId, $fromId, ['merged_into_id' => $intoId, 'in_files' => 0], $userId);

        return true;
    }

    /** Purge selon la durée de conservation : consultations et extractions plus anciennes que N jours (0 : jamais). */
    public function purge(int $tenantId, int $queryDays, int $extractionDays): array
    {
        $done = ['queries' => 0, 'extractions' => 0];
        if ($queryDays > 0) {
            $done['queries'] = $this->db->execute('DELETE FROM sse_file_queries WHERE tenant_id = :t AND created_at < :d', ['t' => $tenantId, 'd' => gmdate('Y-m-d H:i:s', time() - $queryDays * 86400)]);
        }
        if ($extractionDays > 0) {
            $phones = new SsePhoneExtractionService($this->records);
            foreach ($this->db->fetchAll('SELECT id FROM sse_phone_extractions WHERE tenant_id = :t AND created_at < :d LIMIT 500', ['t' => $tenantId, 'd' => gmdate('Y-m-d H:i:s', time() - $extractionDays * 86400)]) as $r) {
                $done['extractions'] += $phones->purge($tenantId, (int) $r['id']) ? 1 : 0;
            }
        }

        return $done;
    }
}
