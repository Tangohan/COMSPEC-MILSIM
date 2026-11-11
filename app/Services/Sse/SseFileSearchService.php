<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Core\Database;
use App\Repositories\SseFileRecordRepository;
use App\Repositories\SseIdentityRepository;
use App\Repositories\SseLoreRepository;
use App\Support\SseFileSubjectCatalog as S;
use App\Support\SseFileText;
use App\Support\SseFileVisibility as V;
use App\Support\SseMeasure;

/**
 * Fichiers SSE — recherche des sujets : une case unique (nom, alias, surnom, numéro de papier, plaque, téléphone, date
 * de naissance, référence) et des filtres de signalement (sexe, âge, taille, poids, corpulence, cheveux, yeux, signes,
 * photo). Les correspondances sont classées avec un score et un niveau de confiance (exact, probable, possible).
 *
 * Recherche tolérante : formes normalisées sans accents, clés phonétiques (Hadad trouve HADDAD), distance d'édition.
 * Index : sse_file_aliases (norm, phonetic), sse_file_links (value_norm), sse_file_profiles (height_cm). Seul ce que les
 * fichiers contiennent et qu'un opérateur peut voir (connu, révélé) sert à trouver et s'affiche ; jamais une identité
 * falsifiée (un faux n'existe pas dans les fichiers), une fiche fusionnée ou une personne « hors fichiers ».
 */
final class SseFileSearchService
{
    public const PER_PAGE = 20;

    /** Filtres reconnus (paramètres GET de l'écran). */
    public const PARAMS = ['q', 'last', 'first', 'dob', 'dob_tol', 'aliases', 'fuzzy', 'sex', 'h_min', 'h_max', 'w_min', 'w_max',
        'age_min', 'age_max', 'build', 'hair', 'eyes', 'marks', 'photo', 'flag', 'country'];

    private Database $db;

    public function __construct(
        private ?SseFileRecordRepository $records = null,
        private ?SseIdentityRepository $identities = null,
        private ?SseLoreRepository $lore = null,
        private ?string $today = null,
    ) {
        $this->records ??= new SseFileRecordRepository();
        $this->identities ??= new SseIdentityRepository();
        $this->lore ??= new SseLoreRepository();
        $this->db = $this->records->db();
        $this->today ??= gmdate('Y-m-d');
    }

    /**
     * Filtres lus d'une requête (valeurs bornées ; tailles et poids acceptés en cm/kg ou en pieds-pouces/livres).
     *
     * @param array<string, mixed> $in
     * @return array<string, string>
     */
    public static function cleanParams(array $in): array
    {
        $out = [];
        foreach (self::PARAMS as $k) {
            $v = $in[$k] ?? '';
            $out[$k] = mb_substr(trim(is_scalar($v) ? (string) $v : ''), 0, 120);
        }
        foreach (['h_min', 'h_max'] as $k) {
            $out[$k] = $out[$k] !== '' ? (string) (SseMeasure::parseHeight($out[$k]) ?? '') : '';
        }
        foreach (['w_min', 'w_max'] as $k) {
            $out[$k] = $out[$k] !== '' ? (string) (SseMeasure::parseWeight($out[$k]) ?? '') : '';
        }
        foreach (['age_min', 'age_max'] as $k) {
            $out[$k] = is_numeric($out[$k]) && (int) $out[$k] >= 0 && (int) $out[$k] <= 120 ? (string) (int) $out[$k] : '';
        }
        $out['dob_tol'] = in_array($out['dob_tol'], ['0', '1', '5'], true) ? $out['dob_tol'] : '';
        $out['sex'] = in_array($out['sex'], ['M', 'F'], true) ? $out['sex'] : '';
        $out['build'] = (string) S::pick(S::BUILDS, $out['build']);
        $out['hair'] = (string) S::pick(S::HAIR, $out['hair']);
        $out['eyes'] = (string) S::pick(S::EYES, $out['eyes']);
        $out['flag'] = in_array($out['flag'], ['wanted', 'caution', 'none'], true) ? $out['flag'] : '';
        $out['photo'] = $out['photo'] === '1' ? '1' : '';
        $out['aliases'] = $out['aliases'] === '0' ? '0' : '';
        $out['fuzzy'] = $out['fuzzy'] === '0' ? '0' : '';
        $out['country'] = preg_match('/^[A-Z]{3}$/', strtoupper($out['country'])) === 1 ? strtoupper($out['country']) : '';

        return $out;
    }

    /** @param array<string, string> $p */
    public static function hasCriteria(array $p): bool
    {
        foreach ($p as $k => $v) {
            if ($v !== '' && !in_array($k, ['aliases', 'fuzzy', 'dob_tol'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ce que la case unique contient : référence, date, téléphone, numéro (papier, plaque) ou nom.
     *
     * @return array{type: string, value: string}
     */
    public static function detect(string $q): array
    {
        $q = trim($q);
        if ($q === '') {
            return ['type' => 'none', 'value' => ''];
        }
        if (preg_match('/^(IDT|DOC|REC|EXT)-?0*(\d{1,10})$/i', $q, $m) === 1) {
            return ['type' => 'ref', 'value' => strtoupper($m[1]) . '-' . (int) $m[2]];
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $q) === 1 || preg_match('#^\d{1,2}[./]\d{1,2}[./]\d{4}$#', $q) === 1) {
            return ['type' => 'dob', 'value' => $q];
        }
        $digits = (string) preg_replace('/\D/', '', $q);
        if (preg_match('/^\+?[\d\s().\-\/]+$/', $q) === 1 && strlen($digits) >= 7) {
            return ['type' => 'phone', 'value' => SseFileText::phoneKey($q)];
        }
        $compact = SseFileText::compact($q);
        if ($compact !== '' && preg_match('/\d/', $compact) === 1 && preg_match('/^[\p{L}\d\s\-]+$/u', $q) === 1 && strlen($compact) >= 3) {
            return ['type' => 'number', 'value' => $compact];
        }

        return ['type' => 'name', 'value' => SseFileText::norm($q)];
    }

    /**
     * Recherche paginée. Chaque résultat : identité (vue opérateur), profil, alias visibles, photo, score, confiance,
     * raisons (« Alias « Kader » », « Taille 182 cm »…).
     *
     * @param array<string, string> $p filtres nettoyés (cleanParams)
     * @return array{hits: list<array<string, mixed>>, total: int, page: int, pages: int, detected: array{type: string, value: string}}
     */
    public function search(int $tenantId, array $p, int $page = 1, int $perPage = self::PER_PAGE): array
    {
        $this->records->syncRegistryNames($tenantId);
        $phases = $this->lore->phases($tenantId);
        $detected = self::detect($p['q'] ?? '');
        $useAliases = ($p['aliases'] ?? '') !== '0';
        $fuzzy = ($p['fuzzy'] ?? '') !== '0';

        /** @var array<int, array{score: int, why: list<array{0: string, 1: bool}>, alias: ?string}> $cand */
        $cand = [];
        $nameQuery = $detected['type'] === 'name' ? $detected['value'] : '';
        $last = SseFileText::norm($p['last'] ?? '');
        $first = SseFileText::norm($p['first'] ?? '');
        $hasName = $nameQuery !== '' || $last !== '' || $first !== '';

        if ($hasName) {
            $cand = $this->nameCandidates($tenantId, $nameQuery, $last, $first, $useAliases, $fuzzy, $phases);
        } elseif (in_array($detected['type'], ['phone', 'number', 'ref', 'dob'], true)) {
            $cand = $this->numberCandidates($tenantId, $detected, $phases);
        }
        $nameDriven = $hasName || in_array($detected['type'], ['phone', 'number', 'ref'], true);
        $dobFilter = $detected['type'] === 'dob' ? self::normDate($detected['value']) : self::normDate($p['dob'] ?? '');

        if (!$nameDriven) {
            // Signalement seul (ou date de naissance seule) : toutes les fiches de la communauté, filtrées ensuite.
            foreach ($this->db->fetchAll(
                "SELECT i.id FROM sse_identities i WHERE i.tenant_id = :t AND i.kind <> 'forged' ORDER BY i.id DESC LIMIT 3000",
                ['t' => $tenantId]
            ) as $r) {
                $cand[(int) $r['id']] = ['score' => 30, 'why' => [], 'alias' => null];
            }
        }
        if ($cand === []) {
            return ['hits' => [], 'total' => 0, 'page' => 1, 'pages' => 0, 'detected' => $detected];
        }

        $ids = array_keys($cand);
        $identities = [];
        foreach (array_chunk($ids, 400) as $chunk) {
            $identities += $this->identities->findMany($tenantId, $chunk);
        }
        $profiles = $this->records->profiles($tenantId, array_slice($ids, 0, 3000));
        $photoIds = $this->photoIdentityIds($tenantId);

        $hits = [];
        foreach ($cand as $iid => $c) {
            $identity = $identities[$iid] ?? null;
            if ($identity === null || $identity['kind'] === 'forged') {
                continue;
            }
            $rawProfile = $profiles[$iid] ?? null;
            if (!V::inFiles($rawProfile)) {
                continue;
            }
            $phase = V::phaseOf($rawProfile, $phases);
            $profile = V::operatorProfile($rawProfile, $phase);
            $ident = V::operatorIdentity($identity, $rawProfile, $phase);
            $score = $c['score'];
            $why = $c['why'];
            $ok = $this->applyFilters($p, $ident, $profile, $dobFilter, isset($photoIds[$iid]), $score, $why);
            if (!$ok) {
                continue;
            }
            $hits[] = [
                'identity' => $ident,
                'profile' => $profile,
                'score' => min(99, $score),
                'confidence' => S::confidence(min(99, $score)),
                'why' => $why,
                'matched_alias' => $c['alias'],
                'has_photo' => isset($photoIds[$iid]),
            ];
        }
        usort($hits, static fn (array $a, array $b): int => [$b['score'], (int) $b['identity']['id']] <=> [$a['score'], (int) $a['identity']['id']]);
        $total = count($hits);
        $pages = (int) ceil($total / max(1, $perPage));
        $page = max(1, min(max(1, $pages), $page));
        $hits = array_slice($hits, ($page - 1) * $perPage, $perPage);
        $this->decorate($tenantId, $hits, $phases);

        return ['hits' => $hits, 'total' => $total, 'page' => $page, 'pages' => $pages, 'detected' => $detected];
    }

    /**
     * @param array<int, int> $phases
     * @return array<int, array{score: int, why: list<array{0: string, 1: bool}>, alias: ?string}>
     */
    private function nameCandidates(int $tenantId, string $q, string $last, string $first, bool $useAliases, bool $fuzzy, array $phases): array
    {
        $words = array_values(array_filter(explode(' ', trim($q . ' ' . $last . ' ' . $first)), static fn (string $w): bool => strlen($w) >= 2));
        if ($words === []) {
            return [];
        }
        $norms = array_merge([trim($q !== '' ? $q : $last . ' ' . $first)], $words);
        $phon = $fuzzy ? array_values(array_filter(array_map([SseFileText::class, 'phonetic'], $words), static fn (string $k): bool => strlen($k) >= 2)) : [];
        $rows = $this->records->aliasCandidates($tenantId, $norms, $phon, 1500);
        $profiles = $this->records->profiles($tenantId, array_map(static fn (array $r): int => (int) $r['identity_id'], $rows));
        $out = [];
        foreach ($rows as $a) {
            if (!$useAliases && $a['kind'] !== 'legal') {
                continue;
            }
            $iid = (int) $a['identity_id'];
            $phase = V::phaseOf($profiles[$iid] ?? null, $phases);
            if (!V::row($a, $phase) || ($a['kind'] === 'legal' && !V::field($profiles[$iid] ?? null, 'identity', $phase))) {
                continue;
            }
            [$score, $why] = self::scoreName($a, $q, $last, $first, $fuzzy);
            if ($score <= 0) {
                continue;
            }
            if (!isset($out[$iid]) || $score > $out[$iid]['score']) {
                $out[$iid] = ['score' => $score, 'why' => [$why], 'alias' => $a['kind'] === 'legal' ? null : $a['alias']];
            }
        }

        return $out;
    }

    /**
     * Score d'un nom ou alias pour la requête.
     *
     * @param array<string, mixed> $a
     * @return array{0: int, 1: array{0: string, 1: bool}}
     */
    public static function scoreName(array $a, string $q, string $last, string $first, bool $fuzzy): array
    {
        $alias = (string) $a['norm'];
        $isLegal = $a['kind'] === 'legal';
        $aliasWords = explode(' ', $alias);
        $wordScore = static function (string $w) use ($aliasWords, $fuzzy): int {
            $best = 0;
            foreach ($aliasWords as $aw) {
                $s = SseFileText::similarity($w, $aw);
                if (!$fuzzy && $s < 88) {
                    $s = 0;
                }
                $best = max($best, $s);
            }

            return $best;
        };
        if ($last !== '' || $first !== '') {
            // Champs séparés : le nom porte le premier mot du nom légal (« HADDAD Karim »).
            $scores = [];
            foreach (array_filter([$last, $first]) as $part) {
                $partScore = 0;
                foreach (explode(' ', $part) as $w) {
                    $partScore = max($partScore, $wordScore($w));
                }
                $scores[] = $partScore;
            }
            $score = min($scores);
        } else {
            $qWords = array_values(array_filter(explode(' ', $q)));
            $full = SseFileText::similarity($q, $alias);
            $sum = 0;
            foreach ($qWords as $w) {
                $sum += $wordScore($w);
            }
            $avg = $qWords !== [] ? (int) round($sum / count($qWords)) : 0;
            $score = max($full, min($avg, 95));
            if (!$fuzzy && $full < 88 && $avg < 88) {
                $score = 0;
            }
        }
        if ($score < 55) {
            return [0, ['', false]];
        }
        $exact = $score >= 95;
        $label = match (true) {
            !$isLegal => 'Alias « ' . $a['alias'] . ' »',
            $exact => 'Nom',
            $score >= 85 => 'Début du nom',
            default => 'Orthographe approchante',
        };
        if (!$isLegal) {
            $score -= 4;
        }

        return [$score, [$label, true]];
    }

    /**
     * @param array{type: string, value: string} $d
     * @param array<int, int> $phases
     * @return array<int, array{score: int, why: list<array{0: string, 1: bool}>, alias: ?string}>
     */
    private function numberCandidates(int $tenantId, array $d, array $phases): array
    {
        $out = [];
        $add = static function (int $iid, int $score, string $why) use (&$out): void {
            if ($iid > 0 && (!isset($out[$iid]) || $out[$iid]['score'] < $score)) {
                $out[$iid] = ['score' => $score, 'why' => [[$why, true]], 'alias' => null];
            }
        };
        if ($d['type'] === 'ref') {
            [$kind, $num] = explode('-', $d['value']) + ['', '0'];
            if ($kind === 'IDT') {
                $add((int) $num, 99, 'Référence de la fiche');
            } elseif ($kind === 'DOC') {
                $r = $this->db->fetchOne('SELECT identity_id FROM sse_id_documents WHERE id = :id AND tenant_id = :t', ['id' => (int) $num, 't' => $tenantId]);
                $add((int) ($r['identity_id'] ?? 0), 97, 'Référence du papier');
            } elseif ($kind === 'EXT') {
                $r = $this->db->fetchOne('SELECT identity_id FROM sse_phone_extractions WHERE id = :id AND tenant_id = :t', ['id' => (int) $num, 't' => $tenantId]);
                $add((int) ($r['identity_id'] ?? 0), 95, 'Extraction de téléphone rattachée');
            } elseif ($kind === 'REC') {
                $r = $this->db->fetchOne('SELECT identity_id FROM sse_seek_records WHERE tenant_id = :t AND rec LIKE :r', ['t' => $tenantId, 'r' => 'REC-%' . $num]);
                $add((int) ($r['identity_id'] ?? 0), 95, 'Fiche SEEK II');
            }

            return $out;
        }
        if ($d['type'] === 'dob') {
            $dob = self::normDate($d['value']);
            if ($dob !== null) {
                foreach ($this->db->fetchAll("SELECT id FROM sse_identities WHERE tenant_id = :t AND birth_date = :d AND kind <> 'forged' LIMIT 200", ['t' => $tenantId, 'd' => $dob]) as $r) {
                    $add((int) $r['id'], 80, 'Date de naissance');
                }
            }

            return $out;
        }
        $profiles = [];
        if ($d['type'] === 'phone') {
            foreach ($this->records->linksByValue($tenantId, 'phone', $d['value']) as $l) {
                $profiles[$l['identity_id']] ??= $this->records->profile($tenantId, $l['identity_id']);
                if (V::row($l, V::phaseOf($profiles[$l['identity_id']], $phases))) {
                    $add($l['identity_id'], 96, 'Téléphone ' . $l['value']);
                }
            }

            return $out;
        }
        // Numéro : papier délivré (registre), plaque (liens véhicules), clé biométrique.
        foreach ($this->db->fetchAll(
            "SELECT d.identity_id, d.doc_number, d.doc_type FROM sse_id_documents d WHERE d.tenant_id = :t AND d.doc_number LIKE :n
             AND d.workflow IN ('issued', 'revoked') AND d.st_origin <> 'forged' LIMIT 50",
            ['t' => $tenantId, 'n' => '%' . SseFileRecordRepository::likeEscape($d['value']) . '%']
        ) as $r) {
            $add((int) $r['identity_id'], (string) $r['doc_number'] === $d['value'] ? 97 : 72, 'Papier ' . $r['doc_number']);
        }
        $plates = array_merge($this->records->linksByValue($tenantId, 'vehicle', $d['value']), $this->records->linksLike($tenantId, 'vehicle', $d['value']));
        foreach ($plates as $l) {
            $profiles[$l['identity_id']] ??= $this->records->profile($tenantId, $l['identity_id']);
            if (V::row($l, V::phaseOf($profiles[$l['identity_id']], $phases))) {
                $add($l['identity_id'], $l['value_norm'] === $d['value'] ? 96 : 70, 'Véhicule ' . $l['value']);
            }
        }
        foreach ($this->db->fetchAll("SELECT id FROM sse_identities WHERE tenant_id = :t AND bio_key = :k AND kind <> 'forged' LIMIT 20", ['t' => $tenantId, 'k' => $d['value']]) as $r) {
            $add((int) $r['id'], 94, 'Clé biométrique');
        }

        return $out;
    }

    /**
     * Filtres de signalement (vue opérateur). Une donnée inconnue ne passe pas un filtre qui la demande.
     *
     * @param array<string, string> $p
     * @param array<string, mixed> $ident
     * @param array<string, mixed>|null $profile
     * @param list<array{0: string, 1: bool}> $why
     */
    private function applyFilters(array $p, array $ident, ?array $profile, ?string $dob, bool $hasPhoto, int &$score, array &$why): bool
    {
        if ($dob !== null) {
            $birth = $ident['birth_date'] ?? null;
            if ($birth === null) {
                return false;
            }
            $tol = (int) ($p['dob_tol'] !== '' ? $p['dob_tol'] : '0');
            $years = abs((strtotime($birth) - strtotime($dob)) / 31557600);
            if ($tol === 0 ? $birth !== $dob : $years > $tol) {
                return false;
            }
            $score += $tol === 0 ? 18 : 8;
            $why[] = [$tol === 0 ? 'Date de naissance' : 'Âge compatible', true];
        }
        if ($p['sex'] !== '') {
            if (($ident['sex'] ?? 'X') !== $p['sex']) {
                return false;
            }
            $score += 2;
        }
        $age = self::age($ident['birth_date'] ?? null, (string) $this->today);
        if ($p['age_min'] !== '' || $p['age_max'] !== '') {
            if ($age === null || ($p['age_min'] !== '' && $age < (int) $p['age_min']) || ($p['age_max'] !== '' && $age > (int) $p['age_max'])) {
                return false;
            }
            $score += 6;
            $why[] = [$age . ' ans', true];
        }
        $h = $profile['height_cm'] ?? null;
        if ($p['h_min'] !== '' || $p['h_max'] !== '') {
            if ($h === null || ($p['h_min'] !== '' && $h < (int) $p['h_min']) || ($p['h_max'] !== '' && $h > (int) $p['h_max'])) {
                return false;
            }
            $score += 10;
            $why[] = ['Taille ' . $h . ' cm', true];
        }
        $w = $profile['weight_kg'] ?? null;
        if ($p['w_min'] !== '' || $p['w_max'] !== '') {
            if ($w === null || ($p['w_min'] !== '' && $w < (int) $p['w_min']) || ($p['w_max'] !== '' && $w > (int) $p['w_max'])) {
                return false;
            }
            $score += 6;
            $why[] = ['Poids ' . $w . ' kg', true];
        }
        foreach (['build' => S::BUILDS, 'hair' => S::HAIR, 'eyes' => S::EYES] as $k => $map) {
            if ($p[$k] !== '') {
                if (($profile[$k] ?? null) !== $p[$k]) {
                    return false;
                }
                $score += 5;
                $why[] = [match ($k) { 'build' => 'Corpulence', 'hair' => 'Cheveux', default => 'Yeux' } . ' ' . mb_strtolower($map[$p[$k]]), false];
            }
        }
        if ($p['marks'] !== '') {
            $hay = SseFileText::norm((string) ($profile['marks'] ?? ''));
            $found = [];
            foreach (explode(' ', SseFileText::norm($p['marks'])) as $mw) {
                if (strlen($mw) >= 3 && !SseFileText::isStopWord($mw) && $hay !== '' && str_contains($hay, $mw)) {
                    $found[] = $mw;
                }
            }
            if ($found === []) {
                return false;
            }
            $score += 8 * count($found);
            $why[] = ['Signe : ' . implode(', ', $found), true];
        }
        if ($p['photo'] === '1' && !$hasPhoto && empty($ident['photo_path'])) {
            return false;
        }
        if ($p['country'] !== '' && ($profile['country'] ?? $ident['nationality'] ?? null) !== $p['country']) {
            return false;
        }

        return true;
    }

    /**
     * Ajoute à chaque résultat : alias visibles, photo principale, mentions actives et dernier contrôle.
     *
     * @param list<array<string, mixed>> $hits
     * @param array<int, int> $phases
     */
    private function decorate(int $tenantId, array &$hits, array $phases): void
    {
        $ids = array_map(static fn (array $h): int => (int) $h['identity']['id'], $hits);
        if ($ids === []) {
            return;
        }
        $aliases = $this->records->aliasesFor($tenantId, $ids);
        $photos = $this->records->photosFor($tenantId, $ids);
        $profiles = $this->records->profiles($tenantId, $ids);
        [$in, $params] = SseFileRecordRepository::in('i', $ids);
        $params['t'] = $tenantId;
        $mentions = [];
        try {
            foreach ($this->db->fetchAll("SELECT identity_id, mention FROM sse_file_mentions WHERE tenant_id = :t AND lifted_at IS NULL AND identity_id IN ({$in})", $params) as $m) {
                $mentions[(int) $m['identity_id']][] = (string) $m['mention'];
            }
        } catch (\PDOException) {
            // Table des mentions absente (étape 4330) : résultats sans mention.
        }
        $lastCheck = [];
        try {
            foreach ($this->db->fetchAll("SELECT identity_id, MAX(created_at) AS at, COUNT(*) AS n FROM sse_id_checks WHERE tenant_id = :t AND identity_id IN ({$in}) GROUP BY identity_id", $params) as $c) {
                $lastCheck[(int) $c['identity_id']] = ['at' => (string) $c['at'], 'count' => (int) $c['n']];
            }
        } catch (\PDOException) {
            // Pas de journal des contrôles : rien à signaler.
        }
        foreach ($hits as $k => $h) {
            $iid = (int) $h['identity']['id'];
            $phase = V::phaseOf($profiles[$iid] ?? null, $phases);
            $hits[$k]['aliases'] = array_values(array_map(
                static fn (array $a): string => (string) $a['alias'],
                array_filter($aliases[$iid] ?? [], static fn (array $a): bool => $a['kind'] !== 'legal' && V::row($a, $phase))
            ));
            $visiblePhotos = V::field($profiles[$iid] ?? null, 'photo', $phase)
                ? array_values(array_filter($photos[$iid] ?? [], static fn (array $ph): bool => V::row($ph, $phase)))
                : [];
            $hits[$k]['photo'] = $visiblePhotos[0] ?? null;
            $hits[$k]['mentions'] = array_values(array_unique($mentions[$iid] ?? []));
            $hits[$k]['checks'] = $lastCheck[$iid] ?? null;
        }
    }

    /**
     * Requête véhicule (QV) : plaque complète ou partielle, ou description (marque, modèle, couleur), dans les véhicules
     * des fiches (liens « vehicle » connus et révélés).
     *
     * @return list<array{link: array<string, mixed>, identity: ?array<string, mixed>, exact: bool}>
     */
    public function vehicles(int $tenantId, string $plate, string $text): array
    {
        $plateN = SseFileText::compact($plate);
        $words = array_values(array_filter(explode(' ', SseFileText::norm($text)), static fn (string $w): bool => strlen($w) >= 2));
        if ($plateN === '' && $words === []) {
            return [];
        }
        $rows = $plateN !== ''
            ? $this->records->linksLike($tenantId, 'vehicle', $plateN, 100)
            : array_map([SseFileRecordRepository::class, 'hydrateLink'], $this->db->fetchAll(
                "SELECT * FROM sse_file_links WHERE tenant_id = :t AND kind = 'vehicle' ORDER BY id DESC LIMIT 1000",
                ['t' => $tenantId]
            ));
        $phases = $this->lore->phases($tenantId);
        $ids = array_values(array_unique(array_map(static fn (array $l): int => (int) $l['identity_id'], $rows)));
        $profiles = $this->records->profiles($tenantId, $ids);
        $identities = $this->identities->findMany($tenantId, $ids);
        $out = [];
        foreach ($rows as $l) {
            $iid = (int) $l['identity_id'];
            $prof = $profiles[$iid] ?? null;
            $phase = V::phaseOf($prof, $phases);
            if (!V::row($l, $phase) || !V::inFiles($prof)) {
                continue;
            }
            $hay = SseFileText::norm(implode(' ', array_map('strval', $l['detail'])) . ' ' . $l['value']);
            foreach ($words as $w) {
                if (!str_contains($hay, $w)) {
                    continue 2;
                }
            }
            $i = $identities[$iid] ?? null;
            $out[] = [
                'link' => $l,
                'identity' => $i !== null && $i['kind'] !== 'forged' ? V::operatorIdentity($i, $prof, $phase) : null,
                'exact' => $plateN !== '' && $l['value_norm'] === $plateN,
            ];
            if (count($out) >= 50) {
                break;
            }
        }
        usort($out, static fn (array $a, array $b): int => (int) $b['exact'] <=> (int) $a['exact']);

        return $out;
    }

    /** @return array<int, true> identités qui ont au moins une photo */
    private function photoIdentityIds(int $tenantId): array
    {
        $out = [];
        foreach ($this->db->fetchAll('SELECT DISTINCT identity_id FROM sse_file_photos WHERE tenant_id = :t AND identity_id IS NOT NULL', ['t' => $tenantId]) as $r) {
            $out[(int) $r['identity_id']] = true;
        }

        return $out;
    }

    public static function age(?string $birth, string $today): ?int
    {
        if ($birth === null || preg_match('/^\d{4}-\d{2}-\d{2}$/', $birth) !== 1) {
            return null;
        }
        [$y, $m, $d] = array_map('intval', explode('-', $birth));
        [$ty, $tm, $td] = array_map('intval', explode('-', substr($today, 0, 10)));
        $age = $ty - $y;
        if ($tm < $m || ($tm === $m && $td < $d)) {
            $age--;
        }

        return $age;
    }

    /**
     * AAAA-MM-JJ, JJ/MM/AAAA, JJ.MM.AAAA, ou MM/JJ/AAAA (format américain) quand le jour est en second et dépasse 12 ;
     * $monthFirst force le format américain (terminal réglé sur les États-Unis). null sinon.
     */
    public static function normDate(string $s, bool $monthFirst = false): ?string
    {
        $s = trim($s);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s) === 1) {
            return checkdate((int) substr($s, 5, 2), (int) substr($s, 8, 2), (int) substr($s, 0, 4)) ? $s : null;
        }
        if (preg_match('#^(\d{1,2})[./\-](\d{1,2})[./\-](\d{4})$#', $s, $m) === 1) {
            [$a, $b, $y] = [(int) $m[1], (int) $m[2], (int) $m[3]];
            [$day, $month] = ($b > 12 || ($monthFirst && $a <= 12)) ? [$b, $a] : [$a, $b];

            return checkdate($month, $day, $y) ? sprintf('%04d-%02d-%02d', $y, $month, $day) : null;
        }

        return null;
    }
}
