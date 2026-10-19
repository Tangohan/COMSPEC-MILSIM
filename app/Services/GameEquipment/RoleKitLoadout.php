<?php

declare(strict_types=1);

namespace App\Services\GameEquipment;

/**
 * Kit par rôle : emplacements choisis dans le catalogue observé → tableau getUnitLoadout d’Arma 3
 * (chaîne, format `arma_loadout_str` des tenues d’arsenal), mods requis.
 *
 * Format produit (10 éléments, emplacements vides en [] ou "") :
 *   [[primaire, bouche, pointeur, optique, [chargeur, munitions], [], bipied],
 *    [lanceur…], [poing…],
 *    [tenue, [[objet, n], [chargeur, n, munitions]…]], [gilet, […]], [sac, […]],
 *    casque, lunettes, [jumelles, "", "", "", [], [], ""],
 *    [carte, gps, radio, boussole, montre, vision nocturne]]
 *
 * Le mod applique la tenue avec `setUnitLoadout [_loadout, true]` : les chargeurs sont remplis au maximum,
 * le nombre de munitions écrit ici n’est qu’indicatif.
 */
final class RoleKitLoadout
{
    public const WEAPONS = ['primary', 'secondary', 'handgun'];

    public const ATTACHMENTS = ['optic', 'muzzle', 'pointer', 'bipod', 'magazine'];

    public const CONTAINERS = ['uniform', 'vest', 'backpack'];

    /** Emplacements simples (une classe) et la famille du catalogue proposée dans le sélecteur. */
    public const SIMPLE = [
        'uniform' => 'uniform',
        'vest' => 'vest',
        'backpack' => 'backpack',
        'headgear' => 'headgear',
        'goggles' => 'goggles',
        'nvg' => 'nvg',
        'binocular' => 'binocular',
        'radio' => 'radio',
        'map' => 'item',
        'gps' => 'item',
        'compass' => 'item',
        'watch' => 'item',
    ];

    public const MAX_CARGO_LINES = 40;

    /**
     * Valide et nettoie ce qu’envoie le formulaire (ou ce qui est relu en base).
     *
     * @param array<string, mixed> $slots  ['primary' => ['class'=>…, 'optic'=>…], 'uniform' => 'U_…', …]
     * @param array<string, mixed> $cargo  ['uniform' => [['class'=>…, 'count'=>n]…], …]
     * @return array{weapons: array<string, array<string, string>>, simple: array<string, string>, cargo: array<string, list<array{class: string, count: int}>>}
     */
    public static function normalize(array $slots, array $cargo = []): array
    {
        if ($cargo === [] && is_array($slots['cargo'] ?? null)) {
            $cargo = $slots['cargo'];
        }
        $weaponsIn = is_array($slots['weapons'] ?? null) ? $slots['weapons'] : $slots;
        $simpleIn = is_array($slots['simple'] ?? null) ? $slots['simple'] : $slots;

        $weapons = [];
        foreach (self::WEAPONS as $w) {
            $row = is_array($weaponsIn[$w] ?? null) ? $weaponsIn[$w] : ['class' => $weaponsIn[$w] ?? ''];
            $out = ['class' => GameItemUsageExtractor::cleanClass($row['class'] ?? '')];
            foreach (self::ATTACHMENTS as $a) {
                $out[$a] = $out['class'] !== '' ? GameItemUsageExtractor::cleanClass($row[$a] ?? '') : '';
            }
            $weapons[$w] = $out;
        }
        $simple = [];
        foreach (array_keys(self::SIMPLE) as $s) {
            $v = $simpleIn[$s] ?? '';
            if (is_array($v)) {
                $v = $v['class'] ?? '';
            }
            $simple[$s] = GameItemUsageExtractor::cleanClass($v);
        }
        $cargoOut = [];
        foreach (self::CONTAINERS as $c) {
            $lines = [];
            $list = is_array($cargo[$c] ?? null) ? $cargo[$c] : [];
            foreach ($list as $line) {
                if (!is_array($line)) {
                    continue;
                }
                $class = GameItemUsageExtractor::cleanClass($line['class'] ?? '');
                if ($class === '') {
                    continue;
                }
                $count = (int) ($line['count'] ?? 1);
                $count = max(1, min(99, $count));
                $key = strtolower($class);
                if (isset($lines[$key])) {
                    $lines[$key]['count'] = min(99, $lines[$key]['count'] + $count);
                    continue;
                }
                if (count($lines) >= self::MAX_CARGO_LINES) {
                    break;
                }
                $lines[$key] = ['class' => $class, 'count' => $count];
            }
            // Pas de contenu sans conteneur.
            $cargoOut[$c] = $simple[$c] !== '' ? array_values($lines) : [];
        }

        return ['weapons' => $weapons, 'simple' => $simple, 'cargo' => $cargoOut];
    }

    /**
     * @param array{weapons: array<string, array<string, string>>, simple: array<string, string>, cargo: array<string, list<array{class: string, count: int}>>} $kit
     */
    public static function isEmpty(array $kit): bool
    {
        return self::classes($kit) === [];
    }

    /**
     * @param array{weapons: array<string, array<string, string>>, simple: array<string, string>, cargo: array<string, list<array{class: string, count: int}>>} $kit
     * @return list<string>
     */
    public static function classes(array $kit): array
    {
        $out = [];
        foreach ($kit['weapons'] as $w) {
            foreach ($w as $class) {
                if ($class !== '') {
                    $out[strtolower($class)] = $class;
                }
            }
        }
        foreach ($kit['simple'] as $class) {
            if ($class !== '') {
                $out[strtolower($class)] = $class;
            }
        }
        foreach ($kit['cargo'] as $lines) {
            foreach ($lines as $line) {
                $out[strtolower($line['class'])] = $line['class'];
            }
        }

        return array_values($out);
    }

    /**
     * @param array{weapons: array<string, array<string, string>>, simple: array<string, string>, cargo: array<string, list<array{class: string, count: int}>>} $kit
     * @param array<string, array{kind?: string, max_ammo?: int|null}> $catalog clé = classe en minuscules
     */
    public static function build(array $kit, array $catalog = []): string
    {
        $weapon = function (array $w, int $fallbackAmmo) use ($catalog): array {
            if (($w['class'] ?? '') === '') {
                return [];
            }
            $mag = ($w['magazine'] ?? '') !== ''
                ? [$w['magazine'], self::ammoFor($w['magazine'], $catalog) ?? $fallbackAmmo]
                : [];

            return [$w['class'], $w['muzzle'] ?? '', $w['pointer'] ?? '', $w['optic'] ?? '', $mag, [], $w['bipod'] ?? ''];
        };
        $container = static function (string $class, array $lines) use ($catalog): array {
            if ($class === '') {
                return [];
            }
            $items = [];
            foreach ($lines as $line) {
                $key = strtolower($line['class']);
                $kind = (string) ($catalog[$key]['kind'] ?? '');
                $ammo = self::ammoFor($line['class'], $catalog);
                if ($kind === 'magazine' || ($kind === '' && $ammo !== null)) {
                    $items[] = [$line['class'], $line['count'], $ammo ?? 1];
                } else {
                    $items[] = [$line['class'], $line['count']];
                }
            }

            return [$class, $items];
        };
        $s = $kit['simple'];
        $loadout = [
            $weapon($kit['weapons']['primary'], 30),
            $weapon($kit['weapons']['secondary'], 1),
            $weapon($kit['weapons']['handgun'], 15),
            $container($s['uniform'], $kit['cargo']['uniform'] ?? []),
            $container($s['vest'], $kit['cargo']['vest'] ?? []),
            $container($s['backpack'], $kit['cargo']['backpack'] ?? []),
            $s['headgear'],
            $s['goggles'],
            $s['binocular'] !== '' ? [$s['binocular'], '', '', '', [], [], ''] : [],
            [$s['map'], $s['gps'], $s['radio'], $s['compass'], $s['watch'], $s['nvg']],
        ];

        return (string) json_encode($loadout, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Mods à charger pour porter le kit (hors Arma 3 de base).
     *
     * @param list<string> $classes
     * @param array<string, array{mod?: string}> $catalog clé = classe en minuscules
     * @return list<string>
     */
    public static function requiredMods(array $classes, array $catalog): array
    {
        $mods = [];
        foreach ($classes as $class) {
            $mod = trim((string) ($catalog[strtolower($class)]['mod'] ?? ''));
            if ($mod === '' || strcasecmp($mod, 'Arma 3') === 0) {
                continue;
            }
            $mods[strtolower($mod)] = $mod;
        }
        natcasesort($mods);

        return array_values($mods);
    }

    /**
     * Classes absentes du catalogue observé (saisies à la main ou retirées) : signalées, jamais bloquantes.
     *
     * @param list<string> $classes
     * @param array<string, mixed> $catalog
     * @return list<string>
     */
    public static function unknownClasses(array $classes, array $catalog): array
    {
        return array_values(array_filter($classes, static fn (string $c): bool => !isset($catalog[strtolower($c)])));
    }

    /**
     * @param array<string, array{max_ammo?: int|null}> $catalog
     */
    public static function ammoFor(string $class, array $catalog): ?int
    {
        $known = $catalog[strtolower($class)]['max_ammo'] ?? null;
        if (is_int($known) && $known > 0) {
            return $known;
        }
        if (preg_match('/(\d{1,4})\s*rnd/i', $class, $m) === 1 && (int) $m[1] > 0) {
            return (int) $m[1];
        }

        return null;
    }

    /** Nom de la tenue publiée : « [Médecin] Kit standard ». */
    public static function wardrobeName(string $kitName, ?string $roleName): string
    {
        $role = trim((string) $roleName);
        $prefix = '[' . ($role !== '' ? $role : 'Tous les rôles') . '] ';
        $name = $prefix . trim($kitName);

        return mb_substr($name, 0, 120);
    }
}
