<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Règles des parties détectées en jeu (clé « nom@carte#AAAAMMJJHHMM » de l'ATAK natif) : motifs sur la mission ou la
 * carte, type d'opération détecté par règle, exclusion « ignorer à l'avenir », proposition de type. Calcul pur.
 *
 * Un motif est comparé sans tenir compte de la casse ; « * » vaut n'importe quelle suite, « ? » un caractère ; sans
 * joker, il faut la valeur exacte. La même règle est traduite en LIKE (toLike) pour retirer les parties ignorées des
 * heures en SQL (AtakPlayedOpExclusion) : les deux lectures doivent rester d'accord.
 */
final class AtakPlayedOpRules
{
    /** Champs d'une règle. « name » (nom affiché) ne sert qu'aux types : il n'existe pas en SQL. */
    public const FIELDS = [
        'mission' => 'Nom de la mission',
        'map' => 'Carte',
        'name' => 'Nom affiché',
    ];

    /** Champs d'une règle d'exclusion (relus en SQL). */
    public const IGNORE_FIELDS = ['mission', 'map'];

    /** Catégories proposées tant que la communauté n'a pas fait sa liste. */
    public const DEFAULT_CATEGORIES = ['Entraînement', 'Opération', 'Campagne', 'Test', 'Événement'];

    public const ICONS = ['flag', 'target', 'school', 'science', 'event', 'campaign', 'shield', 'flight'];

    /** Partie de la clé : « mission » (avant @), « map » (entre @ et #), « name » = libellé donné. */
    public static function fieldValue(string $missionKey, string $field, string $label = ''): string
    {
        $base = explode('#', $missionKey, 2)[0];
        $parts = explode('@', $base, 2);

        return match ($field) {
            'mission' => $parts[0],
            'map' => $parts[1] ?? '',
            'name' => $label,
            default => '',
        };
    }

    public static function normalizeField(string $field, bool $forIgnore = false): string
    {
        $allowed = $forIgnore ? self::IGNORE_FIELDS : array_keys(self::FIELDS);

        return in_array($field, $allowed, true) ? $field : 'mission';
    }

    public static function normalizePattern(string $pattern): string
    {
        return mb_substr(trim((string) preg_replace('/[\x00-\x1F\x7F]/u', '', $pattern)), 0, 190);
    }

    public static function matches(string $pattern, string $value): bool
    {
        $pattern = self::normalizePattern($pattern);
        if ($pattern === '' || $value === '') {
            return false;
        }
        $re = '';
        foreach (preg_split('//u', $pattern, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $ch) {
            $re .= match ($ch) {
                '*' => '.*',
                '?' => '.',
                default => preg_quote($ch, '/'),
            };
        }

        return preg_match('/^' . $re . '$/iu', $value) === 1;
    }

    /** Même motif pour SQL LIKE (antislash = échappement par défaut de MySQL). */
    public static function toLike(string $pattern): string
    {
        $out = '';
        foreach (preg_split('//u', self::normalizePattern($pattern), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $ch) {
            $out .= match ($ch) {
                '\\' => '\\\\',
                '%' => '\\%',
                '_' => '\\_',
                '*' => '%',
                '?' => '_',
                default => $ch,
            };
        }

        return $out;
    }

    /**
     * Type d'une partie : celui choisi à la main, sinon le premier type (le plus ancien) dont la règle correspond.
     *
     * @param list<array<string, mixed>> $types types actifs (id, name, match_field, pattern…)
     * @return array{type: array<string, mixed>, source: string}|null source = manual | rule
     */
    public static function resolveType(string $missionKey, string $label, array $types, int $manualTypeId = 0): ?array
    {
        if ($manualTypeId > 0) {
            foreach ($types as $t) {
                if ((int) ($t['id'] ?? 0) === $manualTypeId) {
                    return ['type' => $t, 'source' => 'manual'];
                }
            }
        }
        usort($types, static fn (array $a, array $b): int => (int) ($a['id'] ?? 0) <=> (int) ($b['id'] ?? 0));
        foreach ($types as $t) {
            if (!empty($t['archived_at'])) {
                continue;
            }
            $field = self::normalizeField((string) ($t['match_field'] ?? 'mission'));
            if (self::matches((string) ($t['pattern'] ?? ''), self::fieldValue($missionKey, $field, $label))) {
                return ['type' => $t, 'source' => 'rule'];
            }
        }

        return null;
    }

    /**
     * Partie ignorée ? À la main (ignored_at), sinon par la première règle active qui correspond, sauf « compter quand
     * même ». Une partie supprimée ne compte pas non plus (voir AtakPlayedOpExclusion).
     *
     * @param array<string, mixed> $meta ligne atak_debrief_ops (ignored_at, count_anyway…)
     * @param list<array<string, mixed>> $rules
     * @return array{ignored: bool, by: string, rule: array<string, mixed>|null} by = manual | rule | ''
     */
    public static function resolveIgnore(string $missionKey, array $meta, array $rules): array
    {
        if (!empty($meta['ignored_at'])) {
            return ['ignored' => true, 'by' => 'manual', 'rule' => null];
        }
        $rule = self::matchingRule($missionKey, $rules);
        if ($rule !== null && empty($meta['count_anyway'])) {
            return ['ignored' => true, 'by' => 'rule', 'rule' => $rule];
        }

        return ['ignored' => false, 'by' => '', 'rule' => $rule];
    }

    /**
     * @param list<array<string, mixed>> $rules
     * @return array<string, mixed>|null
     */
    public static function matchingRule(string $missionKey, array $rules): ?array
    {
        foreach ($rules as $r) {
            if (!empty($r['disabled_at'])) {
                continue;
            }
            $field = self::normalizeField((string) ($r['match_field'] ?? 'mission'), true);
            if (self::matches((string) ($r['pattern'] ?? ''), self::fieldValue($missionKey, $field))) {
                return $r;
            }
        }

        return null;
    }

    /**
     * Type proposé d'après ce qu'on sait de la partie : mots du nom de mission, nombre de joueurs, durée. Rien n'est
     * enregistré : le gestionnaire relit et enregistre le type (avec sa règle) s'il lui convient.
     *
     * @return array{name: string, category: string, field: string, pattern: string, reasons: list<string>}|null reasons : keyword (mot du nom), short (courte à 1-2 joueurs), crowd (8 joueurs ou plus)
     */
    public static function suggest(string $missionKey, int $players, ?int $durationSeconds): ?array
    {
        $mission = self::fieldValue($missionKey, 'mission');
        if ($mission === '') {
            return null;
        }
        $lower = mb_strtolower($mission);
        $keywords = [
            'Test' => '/(test|debug|essai|tempmission|sandbox|^tmp)/u',
            'Entraînement' => '/(train|entra[iî]n|instruc|formation|tuto|cours|qualif|drill|exercice|exercise)/u',
            'Campagne' => '/(campagne|campaign|camp_|chapitre|episode|épisode)/u',
            'Événement' => '/(event|[ée]v[ée]nement|gala|c[ée]r[ée]monie|parade)/u',
            'Opération' => '/(^op[\s_.\-]|operation|opération|^co\d|mission)/u',
        ];
        $reasons = [];
        $category = '';
        foreach ($keywords as $cat => $re) {
            if (preg_match($re, $lower) === 1) {
                $category = $cat;
                $reasons[] = 'keyword';
                break;
            }
        }
        if ($category === '' && $players > 0 && $players <= 2 && $durationSeconds !== null && $durationSeconds < 1800) {
            $category = 'Test';
            $reasons[] = 'short';
        }
        if ($category === '' && $players >= 8) {
            $category = 'Opération';
            $reasons[] = 'crowd';
        }
        if ($category === '') {
            return null;
        }

        return [
            'name' => $category === 'Test' ? 'Test' : $category . ' · ' . $mission,
            'category' => $category,
            'field' => 'mission',
            'pattern' => $mission,
            'reasons' => $reasons,
        ];
    }

    /**
     * Liste de catégories saisie (une par ligne ou séparées par des virgules), sans doublon, 30 au plus.
     *
     * @return list<string>
     */
    public static function parseCategories(string $raw): array
    {
        $out = [];
        foreach (preg_split('/[\r\n,;]+/u', $raw) ?: [] as $c) {
            $c = mb_substr(trim($c), 0, 80);
            if ($c === '') {
                continue;
            }
            foreach ($out as $o) {
                if (mb_strtolower($o) === mb_strtolower($c)) {
                    continue 2;
                }
            }
            $out[] = $c;
        }

        return array_slice($out, 0, 30);
    }

    public static function normalizeColor(string $color): string
    {
        return preg_match('/^#[0-9a-f]{6}$/i', trim($color)) === 1 ? strtolower(trim($color)) : '#3b6ea5';
    }

    public static function normalizeIcon(string $icon): string
    {
        return in_array($icon, self::ICONS, true) ? $icon : 'flag';
    }
}
