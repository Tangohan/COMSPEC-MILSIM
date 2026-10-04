<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Core\Database;

/**
 * Avis de recherche pour le téléphone en jeu : personnes prioritaires du registre SSE (avec photo de face),
 * entrées actives de la liste de surveillance et dossiers d'intérêt prioritaires ou critiques encore ouverts.
 * Lecture seule ; chaque source manquante (table pas encore migrée) est simplement ignorée.
 */
final class SseWantedNoticeService
{
    private const CLOSED_CASES = ['identite_infirme', 'sans_suite', 'archive', 'brouillon'];

    public function __construct(private ?Database $db = null)
    {
        $this->db ??= Database::getInstance();
    }

    /** @return list<array{kind: string, id: int, ref: string, name: string, alias: string, level: string, details: string, photo: string, updated_at: string}> */
    public function forTenant(int $tenantId, int $limit = 40): array
    {
        $limit = max(1, min(80, $limit));
        $out = [];

        foreach ($this->safeFetch(
            "SELECT p.id, p.last_name, p.first_name, p.alias, p.affiliation, p.distinguishing_marks, p.nationality, p.updated_at,
                    (SELECT ph.image_path FROM sse_person_photos ph WHERE ph.person_id = p.id AND ph.tenant_id = p.tenant_id
                     ORDER BY (ph.angle = 'face') DESC, ph.id DESC LIMIT 1) AS photo
             FROM sse_persons p
             WHERE p.tenant_id = :t AND p.status = 'prioritaire'
             ORDER BY p.updated_at DESC LIMIT " . $limit,
            ['t' => $tenantId]
        ) as $r) {
            $out[] = [
                'kind' => 'person',
                'id' => (int) $r['id'],
                'ref' => 'P-' . (int) $r['id'],
                'name' => self::name($r),
                'alias' => (string) ($r['alias'] ?? ''),
                'level' => 'Personne prioritaire',
                'details' => self::join([
                    (string) ($r['affiliation'] ?? ''),
                    (string) ($r['nationality'] ?? ''),
                    (string) ($r['distinguishing_marks'] ?? ''),
                ]),
                'photo' => ($r['photo'] ?? '') !== '' ? (string) user_media_public_url((string) $r['photo']) : '',
                'updated_at' => (string) ($r['updated_at'] ?? ''),
            ];
        }

        foreach ($this->safeFetch(
            'SELECT id, last_name, first_name, alias, threat_level, notes, updated_at
             FROM sse_watchlist_entries WHERE tenant_id = :t AND active = 1
             ORDER BY updated_at DESC LIMIT ' . $limit,
            ['t' => $tenantId]
        ) as $r) {
            $out[] = [
                'kind' => 'watchlist',
                'id' => (int) $r['id'],
                'ref' => 'LS-' . (int) $r['id'],
                'name' => self::name($r),
                'alias' => (string) ($r['alias'] ?? ''),
                'level' => 'Liste de surveillance · ' . str_replace('_', ' ', (string) ($r['threat_level'] ?? '')),
                'details' => (string) ($r['notes'] ?? ''),
                'photo' => '',
                'updated_at' => (string) ($r['updated_at'] ?? ''),
            ];
        }

        $closed = implode(',', array_map(static fn (string $s): string => "'" . $s . "'", self::CLOSED_CASES));
        foreach ($this->safeFetch(
            "SELECT id, reference_code, temporary_designation, suspected_alias, interest_level, suspected_affiliation,
                    apparent_sex, estimated_age_range, collection_needs, updated_at
             FROM sse_interest_cases
             WHERE tenant_id = :t AND interest_level IN ('prioritaire', 'critique') AND status NOT IN (" . $closed . ")
             ORDER BY (interest_level = 'critique') DESC, updated_at DESC LIMIT " . $limit,
            ['t' => $tenantId]
        ) as $r) {
            $out[] = [
                'kind' => 'interest',
                'id' => (int) $r['id'],
                'ref' => (string) ($r['reference_code'] ?? ''),
                'name' => (string) ($r['temporary_designation'] ?? ''),
                'alias' => (string) ($r['suspected_alias'] ?? ''),
                'level' => 'Dossier d\'intérêt · ' . ((string) ($r['interest_level'] ?? '') === 'critique' ? 'critique' : 'prioritaire'),
                'details' => self::join([
                    (string) ($r['suspected_affiliation'] ?? ''),
                    (string) ($r['apparent_sex'] ?? ''),
                    (string) ($r['estimated_age_range'] ?? ''),
                    ($r['collection_needs'] ?? '') !== '' ? 'À recueillir : ' . (string) $r['collection_needs'] : '',
                ]),
                'photo' => '',
                'updated_at' => (string) ($r['updated_at'] ?? ''),
            ];
        }

        return array_slice($out, 0, $limit);
    }

    /** @return list<array<string, mixed>> */
    private function safeFetch(string $sql, array $params): array
    {
        try {
            return $this->db->fetchAll($sql, $params);
        } catch (\Throwable) {
            return [];
        }
    }

    /** @param array<string, mixed> $r */
    private static function name(array $r): string
    {
        $n = trim(mb_strtoupper((string) ($r['last_name'] ?? '')) . ' ' . (string) ($r['first_name'] ?? ''));

        return $n !== '' ? $n : 'Identité inconnue';
    }

    /** @param list<string> $parts */
    private static function join(array $parts): string
    {
        return implode(' · ', array_values(array_filter(array_map('trim', $parts), static fn (string $s): bool => $s !== '')));
    }
}
