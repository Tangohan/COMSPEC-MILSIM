<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\SilentSchemaMigration;
use App\Support\SseFileSubjectCatalog as S;
use App\Support\SseFileText;

/**
 * Fichiers SSE — données de la fiche sujet autour d'une identité du registre (sse_identities) : signalement, alias,
 * liens (adresses, véhicules, téléphones, courriels, personnes liées), photos, annotations, recherches des membres et
 * réglages de la communauté. Toutes les requêtes portent la communauté (tenant_id) : aucun accès d'une communauté à
 * une autre.
 */
final class SseFileRecordRepository
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
        self::ensureSchema();
    }

    /** Tables créées au premier usage (mêmes migrations que database/steps/4330, 4340 et 4350). */
    public static function ensureSchema(): void
    {
        static $done = false;
        if (!$done) {
            $done = true;
            SseFileRepository::ensureSchema();
            SilentSchemaMigration::run(base_path('bootstrap/sse_files_records_migration.php'));
            SilentSchemaMigration::run(base_path('bootstrap/sse_phone_lore_migration.php'));
        }
    }

    public function db(): Database
    {
        return $this->db;
    }

    // ------------------------------------------------------------- Profils

    /** @return array<string, mixed>|null */
    public function profile(int $tenantId, int $identityId): ?array
    {
        $row = $this->db->fetchOne('SELECT * FROM sse_file_profiles WHERE tenant_id = :t AND identity_id = :i', ['t' => $tenantId, 'i' => $identityId]);

        return $row === null ? null : self::hydrateProfile($row);
    }

    /**
     * @param list<int> $identityIds
     * @return array<int, array<string, mixed>>
     */
    public function profiles(int $tenantId, array $identityIds): array
    {
        $ids = self::ids($identityIds);
        if ($ids === []) {
            return [];
        }
        [$in, $params] = self::in('i', $ids);
        $params['t'] = $tenantId;
        $out = [];
        foreach ($this->db->fetchAll('SELECT * FROM sse_file_profiles WHERE tenant_id = :t AND identity_id IN (' . $in . ')', $params) as $row) {
            $p = self::hydrateProfile($row);
            $out[(int) $p['identity_id']] = $p;
        }

        return $out;
    }

    /**
     * Profil créé ou mis à jour (seules les clés fournies changent).
     *
     * @param array<string, mixed> $data
     */
    public function saveProfile(int $tenantId, int $identityId, array $data, ?int $userId): void
    {
        $clean = self::normalizeProfile($data);
        $now = gmdate('Y-m-d H:i:s');
        $existing = $this->db->fetchOne('SELECT id FROM sse_file_profiles WHERE tenant_id = :t AND identity_id = :i', ['t' => $tenantId, 'i' => $identityId]);
        if ($existing === null) {
            $cols = array_keys($clean);
            $params = $clean + ['t' => $tenantId, 'i' => $identityId, 'u' => $userId, 'now' => $now];
            $this->db->insert(
                'INSERT INTO sse_file_profiles (tenant_id, identity_id' . ($cols !== [] ? ', ' . implode(', ', $cols) : '') . ', updated_by_user_id, created_at, updated_at)
                 VALUES (:t, :i' . ($cols !== [] ? ', :' . implode(', :', $cols) : '') . ', :u, :now, :now)',
                $params
            );

            return;
        }
        if ($clean === []) {
            return;
        }
        $set = [];
        foreach (array_keys($clean) as $c) {
            $set[] = $c . ' = :' . $c;
        }
        $this->db->execute(
            'UPDATE sse_file_profiles SET ' . implode(', ', $set) . ', updated_by_user_id = :u, updated_at = :now WHERE tenant_id = :t AND identity_id = :i',
            $clean + ['t' => $tenantId, 'i' => $identityId, 'u' => $userId, 'now' => $now]
        );
    }

    /** Identité portant cet identifiant stable d'IA dans la communauté (dernière en date). */
    public function identityIdByAiUid(int $tenantId, string $aiUid): ?int
    {
        $row = $this->db->fetchOne(
            'SELECT identity_id FROM sse_file_profiles WHERE tenant_id = :t AND ai_uid = :u AND merged_into_id IS NULL ORDER BY id DESC LIMIT 1',
            ['t' => $tenantId, 'u' => $aiUid]
        );

        return $row !== null ? (int) $row['identity_id'] : null;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function normalizeProfile(array $data): array
    {
        $out = [];
        $str = static fn (mixed $v, int $max): ?string => ($s = trim(is_scalar($v) ? (string) $v : '')) !== '' ? mb_substr($s, 0, $max) : null;
        $int = static fn (mixed $v, int $min, int $max): ?int => is_numeric($v) && (int) $v >= $min && (int) $v <= $max ? (int) $v : null;
        if (array_key_exists('country', $data)) {
            $c = strtoupper((string) $str($data['country'], 3));
            $out['country'] = preg_match('/^[A-Z]{3}$/', $c) === 1 ? $c : null;
        }
        if (array_key_exists('height_cm', $data)) {
            $out['height_cm'] = $int($data['height_cm'], 100, 250);
        }
        if (array_key_exists('weight_kg', $data)) {
            $out['weight_kg'] = $int($data['weight_kg'], 30, 250);
        }
        foreach (['build' => S::BUILDS, 'hair' => S::HAIR, 'eyes' => S::EYES] as $k => $map) {
            if (array_key_exists($k, $data)) {
                $out[$k] = S::pick($map, $data[$k]);
            }
        }
        if (array_key_exists('complexion', $data)) {
            $out['complexion'] = $str($data['complexion'], 24);
        }
        if (array_key_exists('marks', $data)) {
            $out['marks'] = $str($data['marks'], 1000);
            $out['marks_norm'] = $out['marks'] !== null ? mb_substr(SseFileText::norm($out['marks']), 0, 1000) : null;
        }
        if (array_key_exists('caution_codes', $data)) {
            $codes = S::pickMany(S::CAUTIONS, $data['caution_codes']);
            $out['caution_codes'] = $codes !== [] ? implode(',', $codes) : null;
        }
        if (array_key_exists('in_files', $data)) {
            $out['in_files'] = !empty($data['in_files']) && $data['in_files'] !== '0' ? 1 : 0;
        }
        foreach (['affiliation' => 160, 'cover_note' => 1000] as $k => $max) {
            if (array_key_exists($k, $data)) {
                $out[$k] = $str($data[$k], $max);
            }
        }
        if (array_key_exists('knowledge', $data)) {
            $out['knowledge'] = $str($data['knowledge'], 8000);
        }
        if (array_key_exists('ai_answers', $data)) {
            $a = self::normalizeAnswers($data['ai_answers']);
            $out['ai_answers_json'] = $a !== [] ? (string) json_encode($a, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
        }
        if (array_key_exists('visibility', $data)) {
            $v = self::normalizeVisibility($data['visibility']);
            $out['visibility_json'] = $v !== [] ? (string) json_encode($v, JSON_UNESCAPED_UNICODE) : null;
        }
        if (array_key_exists('ai_uid', $data)) {
            $u = (string) $str($data['ai_uid'], 64);
            $out['ai_uid'] = preg_match('/^[A-Za-z0-9_.:\-]{3,64}$/', $u) === 1 ? $u : null;
        }
        if (array_key_exists('lore_session_id', $data)) {
            $out['lore_session_id'] = $int($data['lore_session_id'], 1, PHP_INT_MAX);
        }
        if (array_key_exists('merged_into_id', $data)) {
            $out['merged_into_id'] = $int($data['merged_into_id'], 1, PHP_INT_MAX);
        }

        return $out;
    }

    /**
     * Réponses de l'IA (même découpage que le mod : réponses, sujets évoqués, sujets interdits).
     *
     * @return array{answers?: list<string>, evoke?: list<string>, banned?: list<string>}
     */
    public static function normalizeAnswers(mixed $raw): array
    {
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }
        $out = [];
        foreach (['answers', 'evoke', 'banned'] as $k) {
            $list = is_array($raw) && is_array($raw[$k] ?? null) ? $raw[$k] : (is_array($raw) && is_string($raw[$k] ?? null) ? preg_split('/\R/u', (string) $raw[$k]) : []);
            $clean = [];
            foreach ($list ?: [] as $line) {
                $line = trim(is_scalar($line) ? (string) $line : '');
                if ($line !== '' && count($clean) < 40) {
                    $clean[] = mb_substr($line, 0, 300);
                }
            }
            if ($clean !== []) {
                $out[$k] = $clean;
            }
        }

        return $out;
    }

    /**
     * @return array<string, array{v: string, phase: int}>
     */
    public static function normalizeVisibility(mixed $raw): array
    {
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }
        $out = [];
        foreach (is_array($raw) ? $raw : [] as $field => $cfg) {
            if (!is_string($field) || !isset(S::VIS_FIELDS[$field])) {
                continue;
            }
            $v = is_array($cfg) ? ($cfg['v'] ?? 'known') : $cfg;
            $phase = is_array($cfg) && is_numeric($cfg['phase'] ?? null) ? max(0, min(20, (int) $cfg['phase'])) : 0;
            $out[$field] = ['v' => $v === 'hidden' ? 'hidden' : 'known', 'phase' => $phase];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function hydrateProfile(array $row): array
    {
        $int = static fn (mixed $v): ?int => $v !== null && $v !== '' ? (int) $v : null;
        $answers = self::normalizeAnswers((string) ($row['ai_answers_json'] ?? ''));

        return [
            'id' => (int) ($row['id'] ?? 0),
            'identity_id' => (int) ($row['identity_id'] ?? 0),
            'country' => ($row['country'] ?? null) !== null && $row['country'] !== '' ? (string) $row['country'] : null,
            'height_cm' => $int($row['height_cm'] ?? null),
            'weight_kg' => $int($row['weight_kg'] ?? null),
            'build' => ($row['build'] ?? null) ?: null,
            'hair' => ($row['hair'] ?? null) ?: null,
            'eyes' => ($row['eyes'] ?? null) ?: null,
            'complexion' => ($row['complexion'] ?? null) ?: null,
            'marks' => ($row['marks'] ?? null) ?: null,
            'caution_codes' => array_values(array_filter(explode(',', (string) ($row['caution_codes'] ?? '')))),
            'in_files' => (int) ($row['in_files'] ?? 1) === 1,
            'affiliation' => ($row['affiliation'] ?? null) ?: null,
            'cover_note' => ($row['cover_note'] ?? null) ?: null,
            'knowledge' => ($row['knowledge'] ?? null) ?: null,
            'ai_answers' => $answers,
            'visibility' => self::normalizeVisibility((string) ($row['visibility_json'] ?? '')),
            'ai_uid' => ($row['ai_uid'] ?? null) ?: null,
            'lore_session_id' => $int($row['lore_session_id'] ?? null),
            'merged_into_id' => $int($row['merged_into_id'] ?? null),
            'updated_by_user_id' => $int($row['updated_by_user_id'] ?? null),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }

    // -------------------------------------------------------------- Alias

    /**
     * Noms à l'état civil et alias du registre recopiés dans l'index des noms (sans doublon). Bornée à $limit identités
     * par appel : une communauté de plusieurs milliers d'identités est rattrapée en quelques consultations.
     */
    public function syncRegistryNames(int $tenantId, int $limit = 400): int
    {
        $rows = $this->db->fetchAll(
            "SELECT i.id, i.last_name, i.first_name, i.alias, i.created_at, i.updated_at FROM sse_identities i
             LEFT JOIN sse_file_aliases a ON a.tenant_id = i.tenant_id AND a.identity_id = i.id AND a.kind = 'legal'
             WHERE i.tenant_id = :t AND i.kind <> 'forged' AND (a.id IS NULL OR a.created_at < COALESCE(i.updated_at, i.created_at))
             ORDER BY i.id ASC LIMIT " . max(1, min(2000, $limit)),
            ['t' => $tenantId]
        );
        foreach ($rows as $r) {
            $iid = (int) $r['id'];
            $this->db->execute("DELETE FROM sse_file_aliases WHERE tenant_id = :t AND identity_id = :i AND kind IN ('legal', 'registry')", ['t' => $tenantId, 'i' => $iid]);
            $legal = trim((string) $r['last_name'] . ' ' . (string) $r['first_name']);
            $this->insertAlias($tenantId, $iid, $legal !== '' ? $legal : '—', 'legal', 'known', 0, null);
            if (trim((string) ($r['alias'] ?? '')) !== '') {
                $this->insertAlias($tenantId, $iid, (string) $r['alias'], 'registry', 'known', 0, null);
            }
        }

        return count($rows);
    }

    public function addAlias(int $tenantId, int $identityId, string $alias, string $kind = 'aka', string $visibility = 'known', int $phase = 0, ?int $userId = null): ?int
    {
        $alias = trim($alias);
        if ($alias === '' || SseFileText::norm($alias) === '') {
            return null;
        }
        $kind = in_array($kind, ['aka', 'nickname', 'spelling'], true) ? $kind : 'aka';
        $exists = $this->db->fetchOne(
            'SELECT id FROM sse_file_aliases WHERE tenant_id = :t AND identity_id = :i AND norm = :n AND kind <> :legal',
            ['t' => $tenantId, 'i' => $identityId, 'n' => SseFileText::norm($alias), 'legal' => 'legal']
        );
        if ($exists !== null) {
            return (int) $exists['id'];
        }

        return $this->insertAlias($tenantId, $identityId, $alias, $kind, $visibility, $phase, $userId);
    }

    private function insertAlias(int $tenantId, int $identityId, string $alias, string $kind, string $visibility, int $phase, ?int $userId): int
    {
        $alias = mb_substr(trim($alias), 0, 160);

        return $this->db->insert(
            'INSERT INTO sse_file_aliases (tenant_id, identity_id, alias, kind, norm, phonetic, visibility, reveal_phase, created_by_user_id, created_at)
             VALUES (:t, :i, :a, :k, :n, :p, :v, :ph, :u, :now)',
            [
                't' => $tenantId, 'i' => $identityId, 'a' => $alias, 'k' => $kind,
                'n' => mb_substr(SseFileText::norm($alias), 0, 160), 'p' => mb_substr(SseFileText::phonetic($alias), 0, 160),
                'v' => $visibility === 'hidden' ? 'hidden' : 'known', 'ph' => max(0, min(20, $phase)),
                'u' => $userId, 'now' => gmdate('Y-m-d H:i:s'),
            ]
        );
    }

    public function deleteAlias(int $tenantId, int $aliasId, ?int $identityId = null): bool
    {
        return $this->db->execute(
            "DELETE FROM sse_file_aliases WHERE id = :id AND tenant_id = :t AND kind NOT IN ('legal', 'registry')" . ($identityId !== null ? ' AND identity_id = :i' : ''),
            ['id' => $aliasId, 't' => $tenantId] + ($identityId !== null ? ['i' => $identityId] : [])
        ) > 0;
    }

    public function setAliasVisibility(int $tenantId, int $aliasId, string $visibility, int $phase, ?int $identityId = null): bool
    {
        return $this->db->execute(
            'UPDATE sse_file_aliases SET visibility = :v, reveal_phase = :p WHERE id = :id AND tenant_id = :t' . ($identityId !== null ? ' AND identity_id = :i' : ''),
            ['v' => $visibility === 'hidden' ? 'hidden' : 'known', 'p' => max(0, min(20, $phase)), 'id' => $aliasId, 't' => $tenantId] + ($identityId !== null ? ['i' => $identityId] : [])
        ) > 0;
    }

    /**
     * @param list<int> $identityIds
     * @return array<int, list<array<string, mixed>>>
     */
    public function aliasesFor(int $tenantId, array $identityIds): array
    {
        $ids = self::ids($identityIds);
        if ($ids === []) {
            return [];
        }
        [$in, $params] = self::in('i', $ids);
        $params['t'] = $tenantId;
        $out = [];
        foreach ($this->db->fetchAll('SELECT * FROM sse_file_aliases WHERE tenant_id = :t AND identity_id IN (' . $in . ') ORDER BY id ASC', $params) as $r) {
            $out[(int) $r['identity_id']][] = self::hydrateAlias($r);
        }

        return $out;
    }

    /**
     * Candidats par nom ou alias : forme normalisée (égale, début, contient) ou clé phonétique (égale ou contient).
     *
     * @param list<string> $norms formes normalisées cherchées
     * @param list<string> $phonetics clés phonétiques cherchées (un mot chacune)
     * @return list<array<string, mixed>>
     */
    public function aliasCandidates(int $tenantId, array $norms, array $phonetics, int $limit = 400): array
    {
        $or = [];
        $params = ['t' => $tenantId];
        $i = 0;
        foreach (array_slice(array_values(array_unique(array_filter($norms))), 0, 6) as $n) {
            $or[] = 'norm LIKE :n' . $i;
            $params['n' . $i] = '%' . self::likeEscape($n) . '%';
            $i++;
        }
        foreach (array_slice(array_values(array_unique(array_filter($phonetics))), 0, 6) as $p) {
            $or[] = '(phonetic = :pe' . $i . ' OR phonetic LIKE :ps' . $i . ' OR phonetic LIKE :pm' . $i . ')';
            $params['pe' . $i] = $p;
            $params['ps' . $i] = self::likeEscape($p) . ' %';
            $params['pm' . $i] = '% ' . self::likeEscape($p) . '%';
            $i++;
        }
        if ($or === []) {
            return [];
        }

        return array_map(
            [self::class, 'hydrateAlias'],
            $this->db->fetchAll('SELECT * FROM sse_file_aliases WHERE tenant_id = :t AND (' . implode(' OR ', $or) . ') LIMIT ' . max(1, min(2000, $limit)), $params)
        );
    }

    /** @param array<string, mixed> $r @return array<string, mixed> */
    public static function hydrateAlias(array $r): array
    {
        return [
            'id' => (int) $r['id'],
            'identity_id' => (int) $r['identity_id'],
            'alias' => (string) $r['alias'],
            'kind' => (string) $r['kind'],
            'kind_label' => S::label(S::ALIAS_KINDS, (string) $r['kind']),
            'norm' => (string) $r['norm'],
            'phonetic' => (string) $r['phonetic'],
            'visibility' => (string) ($r['visibility'] ?? 'known'),
            'reveal_phase' => (int) ($r['reveal_phase'] ?? 0),
            'created_at' => (string) ($r['created_at'] ?? ''),
        ];
    }

    // -------------------------------------------------------------- Liens

    /**
     * @param array{kind: string, value: string, detail?: array<string, mixed>, linked_identity_id?: ?int, relation?: ?string,
     *     flag?: ?string, visibility?: string, reveal_phase?: int} $data
     */
    public function addLink(int $tenantId, int $identityId, array $data, ?int $userId = null): ?int
    {
        $kind = isset(S::LINK_KINDS[$data['kind']]) ? $data['kind'] : null;
        $value = mb_substr(trim((string) $data['value']), 0, 255);
        if ($kind === null || ($value === '' && empty($data['linked_identity_id']))) {
            return null;
        }
        $detail = [];
        foreach (is_array($data['detail'] ?? null) ? $data['detail'] : [] as $k => $v) {
            if (is_string($k) && is_scalar($v) && trim((string) $v) !== '' && count($detail) < 12) {
                $detail[mb_substr($k, 0, 24)] = mb_substr(trim((string) $v), 0, 160);
            }
        }
        $linked = (int) ($data['linked_identity_id'] ?? 0);

        return $this->db->insert(
            'INSERT INTO sse_file_links (tenant_id, identity_id, kind, value, value_norm, detail_json, linked_identity_id, relation, flag, visibility, reveal_phase, created_by_user_id, created_at)
             VALUES (:t, :i, :k, :v, :vn, :d, :l, :r, :f, :vis, :ph, :u, :now)',
            [
                't' => $tenantId, 'i' => $identityId, 'k' => $kind, 'v' => $value !== '' ? $value : '—',
                'vn' => self::linkNorm($kind, $value),
                'd' => $detail !== [] ? (string) json_encode($detail, JSON_UNESCAPED_UNICODE) : null,
                'l' => $linked > 0 && $linked !== $identityId ? $linked : null,
                'r' => S::pick(S::RELATIONS, $data['relation'] ?? null),
                'f' => $kind === 'vehicle' ? S::pick(S::VEHICLE_FLAGS, $data['flag'] ?? null) : null,
                'vis' => ($data['visibility'] ?? 'known') === 'hidden' ? 'hidden' : 'known',
                'ph' => max(0, min(20, (int) ($data['reveal_phase'] ?? 0))),
                'u' => $userId, 'now' => gmdate('Y-m-d H:i:s'),
            ]
        );
    }

    /** Forme de recherche d'un lien : téléphone (9 derniers chiffres), plaque ou courriel compactés, adresse normalisée. */
    public static function linkNorm(string $kind, string $value): string
    {
        return mb_substr(match ($kind) {
            'phone' => SseFileText::phoneKey($value),
            'vehicle' => SseFileText::compact($value),
            'email' => strtolower(trim($value)),
            default => SseFileText::norm($value),
        }, 0, 255);
    }

    public function deleteLink(int $tenantId, int $linkId, ?int $identityId = null): bool
    {
        return $this->db->execute(
            'DELETE FROM sse_file_links WHERE id = :id AND tenant_id = :t' . ($identityId !== null ? ' AND identity_id = :i' : ''),
            ['id' => $linkId, 't' => $tenantId] + ($identityId !== null ? ['i' => $identityId] : [])
        ) > 0;
    }

    public function setLinkVisibility(int $tenantId, int $linkId, string $visibility, int $phase, ?int $identityId = null): bool
    {
        return $this->db->execute(
            'UPDATE sse_file_links SET visibility = :v, reveal_phase = :p WHERE id = :id AND tenant_id = :t' . ($identityId !== null ? ' AND identity_id = :i' : ''),
            ['v' => $visibility === 'hidden' ? 'hidden' : 'known', 'p' => max(0, min(20, $phase)), 'id' => $linkId, 't' => $tenantId] + ($identityId !== null ? ['i' => $identityId] : [])
        ) > 0;
    }

    /**
     * @param list<int> $identityIds
     * @return array<int, list<array<string, mixed>>>
     */
    public function linksFor(int $tenantId, array $identityIds): array
    {
        $ids = self::ids($identityIds);
        if ($ids === []) {
            return [];
        }
        [$in, $params] = self::in('i', $ids);
        $params['t'] = $tenantId;
        $out = [];
        foreach ($this->db->fetchAll('SELECT * FROM sse_file_links WHERE tenant_id = :t AND identity_id IN (' . $in . ') ORDER BY kind, id', $params) as $r) {
            $out[(int) $r['identity_id']][] = self::hydrateLink($r);
        }

        return $out;
    }

    /**
     * Liens portant cette valeur (même téléphone, même plaque, même adresse…), toutes fiches de la communauté.
     *
     * @return list<array<string, mixed>>
     */
    public function linksByValue(int $tenantId, string $kind, string $valueNorm, int $limit = 50): array
    {
        if ($valueNorm === '') {
            return [];
        }
        $rows = $this->db->fetchAll(
            'SELECT * FROM sse_file_links WHERE tenant_id = :t AND kind = :k AND value_norm = :v ORDER BY id DESC LIMIT ' . max(1, min(200, $limit)),
            ['t' => $tenantId, 'k' => $kind, 'v' => $valueNorm]
        );

        return array_map([self::class, 'hydrateLink'], $rows);
    }

    /**
     * Liens d'un type dont la valeur contient ce texte (plaque partielle « 4471 », rue…).
     *
     * @return list<array<string, mixed>>
     */
    public function linksLike(int $tenantId, string $kind, string $fragmentNorm, int $limit = 50): array
    {
        if (strlen($fragmentNorm) < 3) {
            return [];
        }
        $rows = $this->db->fetchAll(
            'SELECT * FROM sse_file_links WHERE tenant_id = :t AND kind = :k AND value_norm LIKE :v ORDER BY id DESC LIMIT ' . max(1, min(200, $limit)),
            ['t' => $tenantId, 'k' => $kind, 'v' => '%' . self::likeEscape($fragmentNorm) . '%']
        );

        return array_map([self::class, 'hydrateLink'], $rows);
    }

    /** @return list<array<string, mixed>> personnes qui ont désigné cette identité comme personne liée */
    public function backLinks(int $tenantId, int $identityId): array
    {
        return array_map([self::class, 'hydrateLink'], $this->db->fetchAll(
            "SELECT * FROM sse_file_links WHERE tenant_id = :t AND kind = 'associate' AND linked_identity_id = :i ORDER BY id DESC LIMIT 100",
            ['t' => $tenantId, 'i' => $identityId]
        ));
    }

    /** @param array<string, mixed> $r @return array<string, mixed> */
    public static function hydrateLink(array $r): array
    {
        $detail = json_decode((string) ($r['detail_json'] ?? ''), true);

        return [
            'id' => (int) $r['id'],
            'identity_id' => (int) $r['identity_id'],
            'kind' => (string) $r['kind'],
            'kind_label' => S::label(S::LINK_KINDS, (string) $r['kind']),
            'value' => (string) $r['value'],
            'value_norm' => (string) $r['value_norm'],
            'detail' => is_array($detail) ? $detail : [],
            'linked_identity_id' => ($r['linked_identity_id'] ?? null) !== null ? (int) $r['linked_identity_id'] : null,
            'relation' => ($r['relation'] ?? null) ?: null,
            'relation_label' => S::label(S::RELATIONS, ($r['relation'] ?? null) ?: null, ''),
            'flag' => ($r['flag'] ?? null) ?: null,
            'flag_label' => S::label(S::VEHICLE_FLAGS, ($r['flag'] ?? null) ?: null, ''),
            'visibility' => (string) ($r['visibility'] ?? 'known'),
            'reveal_phase' => (int) ($r['reveal_phase'] ?? 0),
            'created_at' => (string) ($r['created_at'] ?? ''),
        ];
    }

    // ------------------------------------------------------------- Photos

    /**
     * @param array{identity_id?: ?int, path: string, mime: string, width?: ?int, height?: ?int, bytes?: ?int, sha1?: ?string,
     *     kind?: string, source?: string, source_ref?: ?string, caption?: ?string, visibility?: string} $data
     */
    public function addPhoto(int $tenantId, array $data, ?int $userId): int
    {
        $iid = (int) ($data['identity_id'] ?? 0);

        return $this->db->insert(
            'INSERT INTO sse_file_photos (tenant_id, identity_id, path, mime, width, height, bytes, sha1, kind, source, source_ref, caption, visibility, uploaded_by_user_id, created_at)
             VALUES (:t, :i, :p, :m, :w, :h, :b, :s, :k, :src, :ref, :c, :v, :u, :now)',
            [
                't' => $tenantId, 'i' => $iid > 0 ? $iid : null, 'p' => mb_substr($data['path'], 0, 255), 'm' => mb_substr($data['mime'], 0, 32),
                'w' => $data['width'] ?? null, 'h' => $data['height'] ?? null, 'b' => $data['bytes'] ?? null, 's' => $data['sha1'] ?? null,
                'k' => in_array($data['kind'] ?? '', ['front', 'profile', 'field', 'document'], true) ? $data['kind'] : 'field',
                'src' => in_array($data['source'] ?? '', ['upload', 'atak', 'seek', 'person', 'extraction'], true) ? $data['source'] : 'upload',
                'ref' => isset($data['source_ref']) ? mb_substr((string) $data['source_ref'], 0, 80) : null,
                'c' => isset($data['caption']) && trim((string) $data['caption']) !== '' ? mb_substr(trim((string) $data['caption']), 0, 255) : null,
                'v' => ($data['visibility'] ?? 'known') === 'hidden' ? 'hidden' : 'known',
                'u' => $userId, 'now' => gmdate('Y-m-d H:i:s'),
            ]
        );
    }

    /** @return array<string, mixed>|null */
    public function photo(int $tenantId, int $photoId): ?array
    {
        $r = $this->db->fetchOne('SELECT * FROM sse_file_photos WHERE id = :id AND tenant_id = :t', ['id' => $photoId, 't' => $tenantId]);

        return $r === null ? null : self::hydratePhoto($r);
    }

    public function attachPhoto(int $tenantId, int $photoId, ?int $identityId): bool
    {
        return $this->db->execute(
            'UPDATE sse_file_photos SET identity_id = :i WHERE id = :id AND tenant_id = :t',
            ['i' => $identityId !== null && $identityId > 0 ? $identityId : null, 'id' => $photoId, 't' => $tenantId]
        ) > 0;
    }

    public function deletePhoto(int $tenantId, int $photoId): bool
    {
        return $this->db->execute('DELETE FROM sse_file_photos WHERE id = :id AND tenant_id = :t', ['id' => $photoId, 't' => $tenantId]) > 0;
    }

    /**
     * @param list<int> $identityIds
     * @return array<int, list<array<string, mixed>>>
     */
    public function photosFor(int $tenantId, array $identityIds): array
    {
        $ids = self::ids($identityIds);
        if ($ids === []) {
            return [];
        }
        [$in, $params] = self::in('i', $ids);
        $params['t'] = $tenantId;
        $out = [];
        foreach ($this->db->fetchAll("SELECT * FROM sse_file_photos WHERE tenant_id = :t AND identity_id IN ({$in}) ORDER BY CASE kind WHEN 'front' THEN 0 WHEN 'profile' THEN 1 ELSE 2 END, id DESC", $params) as $r) {
            $out[(int) $r['identity_id']][] = self::hydratePhoto($r);
        }

        return $out;
    }

    /** @return list<array<string, mixed>> photos sans fiche (à rattacher) */
    public function unattachedPhotos(int $tenantId, int $limit = 60): array
    {
        return array_map([self::class, 'hydratePhoto'], $this->db->fetchAll(
            'SELECT * FROM sse_file_photos WHERE tenant_id = :t AND identity_id IS NULL ORDER BY id DESC LIMIT ' . max(1, min(200, $limit)),
            ['t' => $tenantId]
        ));
    }

    public function photoBySource(int $tenantId, string $source, string $ref): ?array
    {
        $r = $this->db->fetchOne('SELECT * FROM sse_file_photos WHERE tenant_id = :t AND source = :s AND source_ref = :r', ['t' => $tenantId, 's' => $source, 'r' => $ref]);

        return $r === null ? null : self::hydratePhoto($r);
    }

    /** @param array<string, mixed> $r @return array<string, mixed> */
    public static function hydratePhoto(array $r): array
    {
        return [
            'id' => (int) $r['id'],
            'identity_id' => ($r['identity_id'] ?? null) !== null ? (int) $r['identity_id'] : null,
            'path' => (string) $r['path'],
            'mime' => (string) $r['mime'],
            'width' => ($r['width'] ?? null) !== null ? (int) $r['width'] : null,
            'height' => ($r['height'] ?? null) !== null ? (int) $r['height'] : null,
            'kind' => (string) $r['kind'],
            'source' => (string) $r['source'],
            'source_ref' => $r['source_ref'] ?? null,
            'caption' => $r['caption'] ?? null,
            'visibility' => (string) ($r['visibility'] ?? 'known'),
            'uploaded_by_user_id' => ($r['uploaded_by_user_id'] ?? null) !== null ? (int) $r['uploaded_by_user_id'] : null,
            'created_at' => (string) ($r['created_at'] ?? ''),
        ];
    }

    // -------------------------------------------------------------- Notes

    public function addNote(int $tenantId, int $identityId, string $body, ?int $userId, ?string $authorLabel): ?int
    {
        $body = trim($body);
        if ($body === '') {
            return null;
        }

        return $this->db->insert(
            'INSERT INTO sse_file_notes (tenant_id, identity_id, user_id, author_label, body, created_at) VALUES (:t, :i, :u, :a, :b, :now)',
            ['t' => $tenantId, 'i' => $identityId, 'u' => $userId, 'a' => $authorLabel !== null ? mb_substr($authorLabel, 0, 80) : null, 'b' => mb_substr($body, 0, 2000), 'now' => gmdate('Y-m-d H:i:s')]
        );
    }

    /** Suppression douce (annulable) : seulement l'auteur, ou un gestionnaire si $any. */
    public function deleteNote(int $tenantId, int $noteId, ?int $userId, bool $any): bool
    {
        $sql = 'UPDATE sse_file_notes SET deleted_at = :now WHERE id = :id AND tenant_id = :t AND deleted_at IS NULL';
        $params = ['now' => gmdate('Y-m-d H:i:s'), 'id' => $noteId, 't' => $tenantId];
        if (!$any) {
            $sql .= ' AND user_id = :u';
            $params['u'] = (int) $userId;
        }

        return $this->db->execute($sql, $params) > 0;
    }

    public function restoreNote(int $tenantId, int $noteId, ?int $userId, bool $any): bool
    {
        $sql = 'UPDATE sse_file_notes SET deleted_at = NULL WHERE id = :id AND tenant_id = :t';
        $params = ['id' => $noteId, 't' => $tenantId];
        if (!$any) {
            $sql .= ' AND user_id = :u';
            $params['u'] = (int) $userId;
        }

        return $this->db->execute($sql, $params) > 0;
    }

    /** @return list<array<string, mixed>> */
    public function notesFor(int $tenantId, int $identityId): array
    {
        return $this->db->fetchAll(
            'SELECT id, user_id, author_label, body, created_at FROM sse_file_notes WHERE tenant_id = :t AND identity_id = :i AND deleted_at IS NULL ORDER BY id DESC LIMIT 100',
            ['t' => $tenantId, 'i' => $identityId]
        );
    }

    // ---------------------------------------------------------- Recherches

    /** @param array<string, string> $params */
    public function rememberSearch(int $tenantId, int $userId, array $params, bool $saved = false, ?string $label = null): void
    {
        ksort($params);
        $params = array_filter($params, static fn (string $v): bool => $v !== '');
        if ($params === [] || $userId < 1) {
            return;
        }
        $json = (string) json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $hash = sha1($json);
        $now = gmdate('Y-m-d H:i:s');
        $row = $this->db->fetchOne('SELECT id FROM sse_file_searches WHERE tenant_id = :t AND user_id = :u AND params_hash = :h', ['t' => $tenantId, 'u' => $userId, 'h' => $hash]);
        if ($row !== null) {
            $this->db->execute(
                'UPDATE sse_file_searches SET uses = uses + 1, last_used_at = :now' . ($saved ? ', saved = 1, label = :l' : '') . ' WHERE id = :id AND tenant_id = :t',
                ['now' => $now, 'id' => (int) $row['id'], 't' => $tenantId] + ($saved ? ['l' => $label !== null ? mb_substr($label, 0, 80) : null] : [])
            );

            return;
        }
        $this->db->insert(
            'INSERT INTO sse_file_searches (tenant_id, user_id, label, params_json, params_hash, saved, uses, last_used_at) VALUES (:t, :u, :l, :p, :h, :s, 1, :now)',
            ['t' => $tenantId, 'u' => $userId, 'l' => $label !== null ? mb_substr($label, 0, 80) : null, 'p' => mb_substr($json, 0, 2000), 'h' => $hash, 's' => $saved ? 1 : 0, 'now' => $now]
        );
        // Recherches récentes : les 30 dernières non enregistrées seulement.
        $old = $this->db->fetchAll(
            'SELECT id FROM sse_file_searches WHERE tenant_id = :t AND user_id = :u AND saved = 0 ORDER BY last_used_at DESC LIMIT 200 OFFSET 30',
            ['t' => $tenantId, 'u' => $userId]
        );
        foreach ($old as $o) {
            $this->db->execute('DELETE FROM sse_file_searches WHERE id = :id AND tenant_id = :t', ['id' => (int) $o['id'], 't' => $tenantId]);
        }
    }

    /** @return list<array{id: int, label: ?string, params: array<string, string>, saved: bool, last_used_at: string}> */
    public function searches(int $tenantId, int $userId, bool $saved, int $limit = 10): array
    {
        $rows = $this->db->fetchAll(
            'SELECT * FROM sse_file_searches WHERE tenant_id = :t AND user_id = :u AND saved = :s ORDER BY last_used_at DESC LIMIT ' . max(1, min(50, $limit)),
            ['t' => $tenantId, 'u' => $userId, 's' => $saved ? 1 : 0]
        );
        $out = [];
        foreach ($rows as $r) {
            $p = json_decode((string) $r['params_json'], true);
            $out[] = ['id' => (int) $r['id'], 'label' => $r['label'] ?? null, 'params' => is_array($p) ? array_map('strval', $p) : [], 'saved' => (int) $r['saved'] === 1, 'last_used_at' => (string) $r['last_used_at']];
        }

        return $out;
    }

    public function deleteSearch(int $tenantId, int $userId, int $id): bool
    {
        return $this->db->execute('DELETE FROM sse_file_searches WHERE id = :id AND tenant_id = :t AND user_id = :u', ['id' => $id, 't' => $tenantId, 'u' => $userId]) > 0;
    }

    // ------------------------------------------------------------ Réglages

    /** Réglages par défaut d'une communauté. */
    public const SETTINGS_DEFAULTS = [
        'default_country' => 'USA',
        'query_retention_days' => 0,
        'extraction_retention_days' => 0,
        'extractions_enabled' => true,
        'extractions_visibility' => 'portal',
        'member_photo_upload' => true,
        'xref_enabled' => true,
        'purpose_required' => true,
    ];

    /** @return array<string, mixed> */
    public function settings(int $tenantId): array
    {
        $row = $this->db->fetchOne('SELECT settings_json FROM sse_file_settings WHERE tenant_id = :t', ['t' => $tenantId]);
        $data = $row !== null ? json_decode((string) $row['settings_json'], true) : [];

        return self::normalizeSettings(is_array($data) ? $data : []);
    }

    /** @param array<string, mixed> $data */
    public function saveSettings(int $tenantId, array $data, ?int $userId): void
    {
        $json = (string) json_encode(self::normalizeSettings($data + $this->settings($tenantId)), JSON_UNESCAPED_UNICODE);
        $now = gmdate('Y-m-d H:i:s');
        if ($this->db->fetchOne('SELECT tenant_id FROM sse_file_settings WHERE tenant_id = :t', ['t' => $tenantId]) === null) {
            $this->db->execute('INSERT INTO sse_file_settings (tenant_id, settings_json, updated_by_user_id, updated_at) VALUES (:t, :j, :u, :now)', ['t' => $tenantId, 'j' => $json, 'u' => $userId, 'now' => $now]);

            return;
        }
        $this->db->execute('UPDATE sse_file_settings SET settings_json = :j, updated_by_user_id = :u, updated_at = :now WHERE tenant_id = :t', ['t' => $tenantId, 'j' => $json, 'u' => $userId, 'now' => $now]);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function normalizeSettings(array $data): array
    {
        $d = self::SETTINGS_DEFAULTS;
        $bool = static fn (mixed $v, bool $def): bool => $v === null ? $def : ($v === true || $v === 1 || $v === '1' || $v === 'on' || $v === 'true');
        $country = strtoupper(trim((string) ($data['default_country'] ?? $d['default_country'])));

        return [
            'default_country' => preg_match('/^[A-Z]{3}$/', $country) === 1 ? $country : $d['default_country'],
            'query_retention_days' => max(0, min(3650, (int) ($data['query_retention_days'] ?? 0))),
            'extraction_retention_days' => max(0, min(3650, (int) ($data['extraction_retention_days'] ?? 0))),
            'extractions_enabled' => $bool($data['extractions_enabled'] ?? null, true),
            'extractions_visibility' => ($data['extractions_visibility'] ?? 'portal') === 'managers' ? 'managers' : 'portal',
            'member_photo_upload' => $bool($data['member_photo_upload'] ?? null, true),
            'xref_enabled' => $bool($data['xref_enabled'] ?? null, true),
            'purpose_required' => $bool($data['purpose_required'] ?? null, true),
        ];
    }

    // -------------------------------------------------------------- Outils

    /**
     * @param list<int> $ids
     * @return list<int>
     */
    public static function ids(array $ids): array
    {
        return array_slice(array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $i): bool => $i > 0))), 0, 500);
    }

    /**
     * @param list<int|string> $values
     * @return array{0: string, 1: array<string, int|string>}
     */
    public static function in(string $prefix, array $values): array
    {
        $marks = [];
        $params = [];
        foreach (array_values($values) as $i => $v) {
            $marks[] = ':' . $prefix . $i;
            $params[$prefix . $i] = $v;
        }

        return [implode(', ', $marks), $params];
    }

    public static function likeEscape(string $s): string
    {
        return str_replace(['%', '_'], ['', ''], $s);
    }
}
