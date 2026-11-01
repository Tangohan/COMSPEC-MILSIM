<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Codes unité lisibles, calculés depuis l'arbre de l'ORBAT (aucun accès base).
 *
 * - Code de communauté : 2 à 10 lettres ou chiffres, unique sur la plateforme (ex. SOAR).
 * - Code d'unité : code du parent + « - » + segment de l'unité (ex. SOAR-B-A-1).
 *   Le segment vient du « sigle ou code court » saisi sur l'unité (units.code), sinon il est
 *   déduit du nom : numéro (« Groupe 1 » → 1, « 2nd Platoon » → 2), lettre (« Troop A » → A),
 *   alphabet radio (« Bravo » → B), sigle (« Special Tactics » → ST) ou 3 premières lettres.
 * - Deux unités sœurs ne peuvent pas avoir le même code : la seconde reçoit un suffixe (A, A2…).
 *   Un segment saisi à la main passe avant un segment déduit.
 * - Une unité racine unique qui porte le nom de la communauté ne rajoute pas de segment :
 *   elle prend le code de la communauté et ses unités filles commencent directement par lui.
 *
 * Identifiants administratifs : numéro de communauté sur 4 chiffres (0042) et numéro d'unité
 * dans la communauté sur 4 chiffres, affichés ensemble « 0042-0137 ». Ils ne dépendent ni du
 * nom ni du parent, donc ne changent jamais (voir UnitCodeService).
 */
final class UnitCodeBuilder
{
    public const SEGMENT_MAX = 8;

    public const COMMUNITY_MIN = 2;

    public const COMMUNITY_MAX = 10;

    public const PATH_MAX = 180;

    private const STOP_WORDS = [
        'of', 'the', 'and', 'for', 'a', 'an', 'at', 'in', 'on', 'to',
        'de', 'du', 'des', 'la', 'le', 'les', 'et', 'd', 'l', 'en', 'au', 'aux', 'pour', 'sur',
    ];

    /** Mots qui désignent le type d'échelon : ils ne servent pas de segment s'il reste autre chose. */
    private const ECHELON_WORDS = [
        'escadron', 'escadrille', 'squadron', 'sqn', 'troop', 'troupe', 'groupe', 'group', 'groupement',
        'section', 'peloton', 'platoon', 'plt', 'compagnie', 'company', 'coy', 'team', 'equipe', 'bataillon',
        'battalion', 'bn', 'regiment', 'unit', 'unite', 'detachement', 'detachment', 'det', 'cellule', 'cell',
        'flight', 'wing', 'division', 'brigade', 'squad', 'fireteam', 'element', 'escouade', 'branche',
        'branch', 'pole', 'flottille', 'flotille', 'departement', 'department', 'bureau', 'binome',
    ];

    private const NATO = [
        'alpha', 'alfa', 'bravo', 'charlie', 'delta', 'echo', 'foxtrot', 'golf', 'hotel', 'india', 'juliet',
        'juliett', 'kilo', 'lima', 'mike', 'november', 'oscar', 'papa', 'quebec', 'romeo', 'sierra', 'tango',
        'uniform', 'victor', 'whiskey', 'whisky', 'xray', 'yankee', 'zulu',
    ];

    /** Appellations courantes qui ont un sigle d'usage. */
    private const KNOWN = [
        'etat major' => 'EM',
        'headquarters' => 'HQ',
        'quartier general' => 'QG',
        'commandement' => 'CMD',
        'command' => 'CMD',
    ];

    /** Majuscules ASCII, chiffres seulement. */
    public static function sanitize(string $raw, int $max = self::SEGMENT_MAX): string
    {
        $s = strtoupper(self::fold($raw));
        $s = (string) preg_replace('/[^A-Z0-9]+/', '', $s);

        return substr($s, 0, max(1, $max));
    }

    /**
     * Valide un code saisi à la main (communauté ou segment) : null si invalide.
     */
    public static function normalizeInput(string $raw, int $min, int $max): ?string
    {
        $raw = strtoupper(trim($raw));
        if ($raw === '') {
            return '';
        }
        if (!preg_match('/^[A-Z0-9]+$/', $raw)) {
            return null;
        }
        $len = strlen($raw);

        return $len >= $min && $len <= $max ? $raw : null;
    }

    /** Code de communauté proposé par défaut, depuis le nom (sinon l'adresse courte). */
    public static function communityCodeFromName(string $name, string $slug = ''): string
    {
        $folded = self::fold($name);
        $tokens = self::tokens($folded);
        $words = array_values(array_filter($tokens, static fn (string $t): bool => !self::isStopWord($t)));
        $candidate = '';
        // Un sigle déjà présent dans le nom (« 160th SOAR », « Task Force JTF2 ») gagne.
        foreach ($words as $w) {
            if (preg_match('/^[A-Z][A-Z0-9]{1,9}$/', $w) && !preg_match('/^\d+$/', $w)) {
                $candidate = $w;
                break;
            }
        }
        if ($candidate === '' && count($words) >= 2) {
            foreach ($words as $w) {
                $candidate .= preg_match('/^\d+/', $w, $m) ? $m[0] : substr($w, 0, 1);
            }
        }
        if ($candidate === '' && $words !== []) {
            $candidate = substr($words[0], 0, 6);
        }
        $candidate = self::sanitize($candidate, self::COMMUNITY_MAX);
        if (strlen($candidate) < self::COMMUNITY_MIN) {
            $fromSlug = self::sanitize(str_replace('-', '', $slug), 6);
            $candidate = strlen($fromSlug) >= self::COMMUNITY_MIN ? $fromSlug : ($candidate . 'CM');
        }

        return substr($candidate, 0, self::COMMUNITY_MAX);
    }

    /** Variante unique d'un code de communauté (SOAR, SOAR2, SOAR3…). */
    public static function uniqueVariant(string $base, callable $isTaken, int $max = self::COMMUNITY_MAX): string
    {
        $base = $base !== '' ? substr($base, 0, $max) : 'CM';
        if (!$isTaken($base)) {
            return $base;
        }
        for ($i = 2; $i < 10000; $i++) {
            $suffix = (string) $i;
            $candidate = substr($base, 0, max(1, $max - strlen($suffix))) . $suffix;
            if (!$isTaken($candidate)) {
                return $candidate;
            }
        }

        return substr($base, 0, 4) . strtoupper(bin2hex(random_bytes(3)));
    }

    /** Segment déduit du nom d'une unité. */
    public static function autoSegment(string $name, string $parentName = ''): string
    {
        $folded = trim(self::fold($name));
        $parent = trim(self::fold($parentName));
        if ($parent !== '' && stripos($folded, $parent) === 0 && strlen($folded) > strlen($parent)) {
            $folded = trim(substr($folded, strlen($parent)), " \t-–—:|/,.");
        }
        $tokens = array_values(array_filter(
            self::tokens($folded),
            static fn (string $t): bool => !self::isStopWord($t)
        ));
        if ($tokens === []) {
            return 'U';
        }
        $lowerJoined = strtolower(implode(' ', $tokens));
        foreach (self::KNOWN as $phrase => $code) {
            if ($lowerJoined === $phrase || str_starts_with($lowerJoined, $phrase . ' ')) {
                return $code;
            }
        }
        $kept = array_values(array_filter($tokens, static fn (string $t): bool => !in_array(strtolower($t), self::ECHELON_WORDS, true)));
        if ($kept === []) {
            return self::sanitize(substr($tokens[0], 0, 3), 3);
        }
        foreach ($kept as $t) {
            if (preg_match('/^(\d{1,4})(st|nd|rd|th|e|er|ere|eme|re|o|ieme)?$/i', $t, $m)) {
                return $m[1];
            }
        }
        foreach ($kept as $t) {
            if (strlen($t) === 1 && ctype_alpha($t)) {
                return strtoupper($t);
            }
        }
        foreach ($kept as $t) {
            if (in_array(strtolower($t), self::NATO, true)) {
                return strtoupper($t[0]);
            }
        }
        foreach ($kept as $t) {
            if (preg_match('/^[A-Z][A-Z0-9]{1,5}$/', $t)) {
                return $t;
            }
        }
        if (count($kept) >= 2) {
            $initials = '';
            foreach (array_slice($kept, 0, 4) as $t) {
                $initials .= preg_match('/^\d+/', $t, $m) ? $m[0] : substr($t, 0, 1);
            }

            return self::sanitize($initials);
        }

        return self::sanitize(substr($kept[0], 0, 3), 3);
    }

    /**
     * Codes de toutes les unités d'une communauté.
     *
     * @param list<array<string, mixed>> $units lignes avec id, parent_id, name, code, display_order
     * @return array<int, array{segment: string, path: string, depth: int}>
     */
    public static function build(array $units, string $communityCode, string $communityName = ''): array
    {
        $communityCode = self::sanitize($communityCode, self::COMMUNITY_MAX);
        if ($communityCode === '') {
            $communityCode = 'CM';
        }
        $byId = [];
        foreach ($units as $u) {
            $id = (int) ($u['id'] ?? 0);
            if ($id > 0) {
                $byId[$id] = $u;
            }
        }
        $children = [];
        foreach ($byId as $id => $u) {
            $pid = (int) ($u['parent_id'] ?? 0);
            $children[($pid > 0 && $pid !== $id && isset($byId[$pid])) ? $pid : 0][] = $id;
        }
        $sortIds = static function (array $ids) use ($byId): array {
            usort($ids, static function (int $a, int $b) use ($byId): int {
                $oa = (int) ($byId[$a]['display_order'] ?? 0);
                $ob = (int) ($byId[$b]['display_order'] ?? 0);
                if ($oa !== $ob) {
                    return $oa <=> $ob;
                }
                $na = strcasecmp((string) ($byId[$a]['name'] ?? ''), (string) ($byId[$b]['name'] ?? ''));

                return $na !== 0 ? $na : $a <=> $b;
            });

            return $ids;
        };

        $out = [];
        $used = [$communityCode => true];
        // Racine unique au nom de la communauté : elle porte le code de la communauté.
        $roots = $sortIds($children[0] ?? []);
        $headId = 0;
        if (count($roots) === 1) {
            $root = $byId[$roots[0]];
            $explicit = self::sanitize((string) ($root['code'] ?? ''));
            $sameName = $communityName !== '' && self::sameName((string) ($root['name'] ?? ''), $communityName);
            $auto = self::autoSegment((string) ($root['name'] ?? ''));
            if ($explicit !== '' ? $explicit === $communityCode : ($sameName || $auto === $communityCode)) {
                $headId = $roots[0];
                $out[$headId] = ['segment' => '', 'path' => $communityCode, 'depth' => 0];
            }
        }

        $queue = [];
        if ($headId > 0) {
            $queue[] = [$headId, $communityCode, 0];
        } else {
            $queue[] = [0, $communityCode, -1];
        }
        $visited = $headId > 0 ? [$headId => true] : [];
        while ($queue !== []) {
            [$parentId, $parentPath, $parentDepth] = array_shift($queue);
            $kids = array_values(array_filter($sortIds($children[$parentId] ?? []), static fn (int $k): bool => !isset($visited[$k])));
            if ($kids === []) {
                continue;
            }
            $parentName = $parentId > 0 ? (string) ($byId[$parentId]['name'] ?? '') : '';
            // Les segments saisis d'abord, pour qu'un segment déduit ne leur prenne pas la place.
            $explicit = [];
            $auto = [];
            foreach ($kids as $k) {
                $seg = self::sanitize((string) ($byId[$k]['code'] ?? ''));
                if ($seg !== '') {
                    $explicit[$k] = $seg;
                } else {
                    $auto[$k] = self::autoSegment((string) ($byId[$k]['name'] ?? ''), $parentName);
                }
            }
            foreach ($explicit + $auto as $k => $seg) {
                $visited[$k] = true;
                [$seg, $path] = self::claim($parentPath, $seg, $used, $k);
                $out[$k] = ['segment' => $seg, 'path' => $path, 'depth' => $parentDepth + 1];
                $queue[] = [$k, $path, $parentDepth + 1];
            }
        }
        // Unités prises dans une boucle de parents : rattachées à la communauté.
        foreach ($sortIds(array_keys($byId)) as $id) {
            if (isset($out[$id])) {
                continue;
            }
            [$seg, $path] = self::claim($communityCode, self::autoSegment((string) ($byId[$id]['name'] ?? '')), $used, $id);
            $out[$id] = ['segment' => $seg, 'path' => $path, 'depth' => 0];
        }

        return $out;
    }

    /**
     * @param array<string, bool> $used
     * @return array{0: string, 1: string}
     */
    private static function claim(string $parentPath, string $segment, array &$used, int $unitId): array
    {
        $segment = $segment !== '' ? $segment : 'U';
        $path = $parentPath . '-' . $segment;
        $n = 2;
        while (isset($used[$path])) {
            $suffix = (string) $n++;
            $candidate = substr($segment, 0, max(1, self::SEGMENT_MAX - strlen($suffix))) . $suffix;
            $path = $parentPath . '-' . $candidate;
            if (!isset($used[$path])) {
                $segment = $candidate;
            }
        }
        if (strlen($path) > self::PATH_MAX) {
            $segment = 'U' . $unitId;
            $path = substr($parentPath, 0, self::PATH_MAX - strlen($segment) - 1) . '-' . $segment;
        }
        $used[$path] = true;

        return [$segment, $path];
    }

    /** « 0042 » : numéro de communauté sur 4 chiffres (plus si la plateforme dépasse 9999). */
    public static function formatTenantNumber(int $number): string
    {
        return str_pad((string) max(0, $number), 4, '0', STR_PAD_LEFT);
    }

    /** « 0042-0137 » : communauté puis unité, deux groupes de 4 chiffres faciles à dicter. */
    public static function formatUnitAdminId(int $tenantNumber, int $unitNumber): string
    {
        return self::formatTenantNumber($tenantNumber) . '-' . str_pad((string) max(0, $unitNumber), 4, '0', STR_PAD_LEFT);
    }

    /**
     * Lit un identifiant administratif tapé dans la recherche.
     * « 0042-0137 », « 0042 0137 », « 00420137 » → [42, 137] ; « 137 » → [null, 137].
     *
     * @return array{0: int|null, 1: int}|null
     */
    public static function parseAdminQuery(string $q): ?array
    {
        $q = trim($q);
        if (preg_match('/^(\d{1,6})\s*[-\s.\/]\s*(\d{1,6})$/', $q, $m)) {
            return [(int) $m[1], (int) $m[2]];
        }
        if (preg_match('/^\d{8}$/', $q)) {
            return [(int) substr($q, 0, 4), (int) substr($q, 4)];
        }
        if (preg_match('/^\d{1,6}$/', $q)) {
            return [null, (int) $q];
        }

        return null;
    }

    /** Mot de liaison ignoré ; une lettre majuscule seule (« Troop A ») reste un repère. */
    private static function isStopWord(string $t): bool
    {
        if (strlen($t) === 1 && ctype_upper($t)) {
            return false;
        }

        return in_array(strtolower($t), self::STOP_WORDS, true);
    }

    private static function sameName(string $a, string $b): bool
    {
        $na = strtolower((string) preg_replace('/[^a-z0-9]+/i', '', self::fold($a)));
        $nb = strtolower((string) preg_replace('/[^a-z0-9]+/i', '', self::fold($b)));

        return $na !== '' && $na === $nb;
    }

    /** @return list<string> */
    private static function tokens(string $folded): array
    {
        return array_values(array_filter(
            preg_split('/[^A-Za-z0-9]+/', $folded) ?: [],
            static fn (string $t): bool => $t !== ''
        ));
    }

    /** Retire les accents (é → e, ç → c…) en gardant la casse. */
    public static function fold(string $s): string
    {
        if (class_exists(\Normalizer::class)) {
            $n = \Normalizer::normalize($s, \Normalizer::FORM_D);
            if (is_string($n)) {
                $s = (string) preg_replace('/\p{Mn}+/u', '', $n);
            }
        }
        $s = strtr($s, [
            'Æ' => 'AE', 'æ' => 'ae', 'Œ' => 'OE', 'œ' => 'oe', 'ß' => 'ss', 'Ø' => 'O', 'ø' => 'o',
            'à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'î' => 'i', 'ï' => 'i', 'í' => 'i', 'ô' => 'o', 'ö' => 'o', 'ó' => 'o', 'ù' => 'u', 'û' => 'u',
            'ü' => 'u', 'ú' => 'u', 'ç' => 'c', 'ñ' => 'n', 'ÿ' => 'y',
            'À' => 'A', 'Â' => 'A', 'Ä' => 'A', 'Á' => 'A', 'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'Î' => 'I', 'Ï' => 'I', 'Í' => 'I', 'Ô' => 'O', 'Ö' => 'O', 'Ó' => 'O', 'Ù' => 'U', 'Û' => 'U',
            'Ü' => 'U', 'Ú' => 'U', 'Ç' => 'C', 'Ñ' => 'N',
        ]);

        return (string) preg_replace('/[^\x20-\x7E]/', ' ', $s);
    }
}
