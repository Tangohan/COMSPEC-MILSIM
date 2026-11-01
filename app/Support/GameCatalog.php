<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Jeux déclarés par les communautés (champ libre « game_label ») : nom propre et regroupement.
 *
 * « Arma3 », « Arma 3 », « ARMA 3 », « ArmA III » ou « A3 » désignent le même jeu : ils partagent
 * une clé (minuscules, sans accents, espaces ni ponctuation) et les alias connus pointent vers le
 * nom d'affichage du catalogue. Un jeu inconnu garde le texte saisi ; ses variantes proches
 * (faute de frappe, à une ou deux lettres près) sont rapprochées dans l'annuaire.
 * Un libellé peut citer plusieurs jeux (« Arma 3 / Reforger ») : séparateurs , ; / | + &.
 */
final class GameCatalog
{
    /** Nom d'affichage => alias (le nom lui-même est toujours reconnu). */
    private const GAMES = [
        'Arma 3' => ['arma3', 'arma iii', 'a3', 'arma 3 milsim', 'arma3 milsim'],
        'Arma Reforger' => ['reforger', 'arma reforger', 'armareforger', 'a reforger'],
        'Arma 4' => ['arma iv', 'arma4', 'a4'],
        'Arma 2' => ['arma ii', 'arma2', 'a2', 'arma 2 operation arrowhead', 'arma 2 oa'],
        'DayZ' => ['day z', 'dayz standalone'],
        'Squad' => [],
        'Squad 44' => ['squad44', 'post scriptum'],
        'Hell Let Loose' => ['hll'],
        'Ready or Not' => ['ron', 'readyornot'],
        'Ground Branch' => ['groundbranch'],
        'Escape from Tarkov' => ['tarkov', 'eft', 'escape from tarkov'],
        'Insurgency: Sandstorm' => ['insurgency sandstorm', 'sandstorm', 'ins sandstorm'],
        'Operation Harsh Doorstop' => ['ohd', 'harsh doorstop'],
        'Gray Zone Warfare' => ['gzw', 'grey zone warfare', 'greyzone warfare', 'grayzone warfare'],
        'Project Reality' => ['pr bf2', 'project reality bf2'],
        'Beyond the Wire' => ['btw'],
        'Rainbow Six Siege' => ['r6', 'r6s', 'r6 siege', 'rainbow 6 siege', 'rainbow six'],
        'DCS World' => ['dcs'],
        'Foxhole' => [],
        'Enlisted' => [],
        'Star Citizen' => [],
        'Arma Cold War Assault' => ['operation flashpoint', 'ofp', 'cold war assault'],
    ];

    /** @var array<string, string>|null clé => nom d'affichage */
    private static ?array $aliasIndex = null;

    /** Clé de comparaison : minuscules, sans accents, sans espaces ni ponctuation. */
    public static function key(string $label): string
    {
        $s = trim($label);
        if ($s === '') {
            return '';
        }
        if (class_exists(\Normalizer::class)) {
            $n = \Normalizer::normalize($s, \Normalizer::FORM_D);
            if (is_string($n)) {
                $s = (string) preg_replace('/\p{Mn}+/u', '', $n);
            }
        }
        $s = mb_strtolower($s, 'UTF-8');
        $s = strtr($s, ['œ' => 'oe', 'æ' => 'ae', 'ß' => 'ss', 'à' => 'a', 'â' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ö' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c']);

        return (string) preg_replace('/[^a-z0-9]+/', '', $s);
    }

    /** @return array<string, string> clé => nom d'affichage, pour tous les noms et alias connus */
    public static function aliasIndex(): array
    {
        if (self::$aliasIndex === null) {
            $index = [];
            foreach (self::GAMES as $label => $aliases) {
                foreach (array_merge([$label], $aliases) as $alias) {
                    $index[self::key($alias)] = $label;
                }
            }
            self::$aliasIndex = $index;
        }

        return self::$aliasIndex;
    }

    /** @return list<string> noms d'affichage du catalogue (suggestions à la saisie) */
    public static function labels(): array
    {
        return array_keys(self::GAMES);
    }

    /** Nom du catalogue pour un seul jeu, ou null s'il n'est pas reconnu. */
    public static function canonical(string $label): ?string
    {
        $key = self::key($label);
        if ($key === '') {
            return null;
        }
        $index = self::aliasIndex();
        if (isset($index[$key])) {
            return $index[$key];
        }
        // Faute de frappe sur un nom connu (« Arma Reforgr »).
        foreach ($index as $aliasKey => $name) {
            if (self::close($key, (string) $aliasKey)) {
                return $name;
            }
        }

        return null;
    }

    /** @return list<string> jeux cités dans un libellé, nettoyés (espaces) mais non traduits */
    public static function split(string $label): array
    {
        $out = [];
        foreach (preg_split('/\s*[,;\/|+&]\s*/u', trim($label)) ?: [] as $part) {
            $part = trim((string) preg_replace('/\s+/u', ' ', (string) $part));
            if ($part !== '' && self::key($part) !== '') {
                $out[] = $part;
            }
        }

        return $out;
    }

    /**
     * Libellé harmonisé à enregistrer : chaque jeu reconnu prend son nom du catalogue, un jeu inconnu
     * garde le texte saisi (espaces normalisés). Idempotent : normalize(normalize(x)) === normalize(x).
     */
    public static function normalize(string $label): string
    {
        $names = [];
        foreach (self::split($label) as $part) {
            $name = self::canonical($part) ?? $part;
            $names[self::key($name)] ??= $name;
        }

        return implode(' / ', array_values($names));
    }

    /**
     * Regroupe les libellés de plusieurs communautés pour le filtre de l'annuaire.
     *
     * @param list<string> $labels un libellé par communauté (vide ignoré)
     * @return array{groups: array<string, array{label: string, count: int}>, keys: list<list<string>>}
     *         groups : clé de groupe => nom affiché et nombre de communautés (trié par nom) ;
     *         keys : pour chaque libellé (même ordre), les clés de groupe de ses jeux.
     */
    public static function group(array $labels): array
    {
        $groups = [];
        $variants = [];
        $keys = [];
        foreach ($labels as $i => $label) {
            $rowKeys = [];
            foreach (self::split((string) $label) as $part) {
                $known = self::canonical($part);
                $key = self::key($known ?? $part);
                if ($known === null) {
                    // Variante proche d'un jeu inconnu déjà vu : même groupe.
                    foreach (array_keys($groups) as $existing) {
                        if (!isset(self::aliasIndex()[$existing]) && self::close($key, (string) $existing)) {
                            $key = (string) $existing;
                            break;
                        }
                    }
                }
                if (in_array($key, $rowKeys, true)) {
                    continue;
                }
                $rowKeys[] = $key;
                $groups[$key] ??= ['label' => $known ?? $part, 'count' => 0];
                ++$groups[$key]['count'];
                if ($known === null) {
                    $variants[$key][$part] = ($variants[$key][$part] ?? 0) + 1;
                }
            }
            $keys[$i] = $rowKeys;
        }
        // Jeu inconnu : la graphie la plus fréquente, à égalité celle en casse mixte avec des espaces.
        foreach ($variants as $key => $spellings) {
            uksort($spellings, static function ($a, $b) use ($spellings): int {
                return [$spellings[$b], self::tidiness((string) $b)] <=> [$spellings[$a], self::tidiness((string) $a)];
            });
            $groups[$key]['label'] = (string) array_key_first($spellings);
        }
        uasort($groups, static fn (array $a, array $b): int => strnatcasecmp($a['label'], $b['label']));

        return ['groups' => $groups, 'keys' => array_values($keys)];
    }

    /** Deux clés proches : faute de frappe, sans confondre deux numéros (Arma 2 / Arma 3). */
    private static function close(string $a, string $b): bool
    {
        if ($a === $b) {
            return true;
        }
        $len = min(strlen($a), strlen($b));
        if ($len < 5 || preg_replace('/\D+/', '', $a) !== preg_replace('/\D+/', '', $b)) {
            return false;
        }
        $max = $len >= 10 ? 2 : 1;

        return abs(strlen($a) - strlen($b)) <= $max && levenshtein($a, $b) <= $max;
    }

    private static function tidiness(string $s): int
    {
        $mixedCase = preg_match('/\p{Lu}/u', $s) === 1 && preg_match('/\p{Ll}/u', $s) === 1;

        return ($mixedCase ? 2 : 0) + substr_count($s, ' ');
    }
}
