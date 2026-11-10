<?php

declare(strict_types=1);

namespace App\Services\GameEquipment\Configurator;

/**
 * Contrôle d'une tenue normalisée (LoadoutDocument) contre le catalogue de la communauté (ConfiguratorCatalog::merge) :
 * objets connus, bon emplacement, accessoires et chargeurs compatibles avec l'arme, objets acceptés par chaque
 * conteneur (allowedSlots d'Arma), charge de l'uniforme, du gilet et du sac (maximumLoad), masse totale.
 *
 * Règle : rien n'est deviné. Une compatibilité ou une masse que le jeu n'a pas fournie (objet seulement vu en jeu,
 * catalogue de configuration pas encore importé) donne un avertissement, jamais une erreur ni une valeur inventée.
 * Pur : testable seul, mêmes règles que le configurateur côté navigateur (public/assets/js/equipment-configurator.js).
 */
final class LoadoutValidator
{
    /** Accessoires d'une arme : index dans le tableau getUnitLoadout => famille attendue. */
    public const ATTACHMENTS = [1 => 'muzzle', 2 => 'pointer', 3 => 'optic', 6 => 'bipod'];

    private const SLOT_WORDS = [
        'muzzle' => 'bouche',
        'pointer' => 'rail latéral',
        'optic' => 'optique',
        'bipod' => 'bipied',
    ];

    private const CONTAINER_WORDS = ['uniform' => 'l’uniforme', 'vest' => 'le gilet', 'backpack' => 'le sac'];

    /**
     * @param list<mixed> $lo tenue normalisée
     * @param array<string, array<string, mixed>> $catalog clé = classe en minuscules
     * @param bool $strictUnknown true : un objet absent du catalogue est une erreur (enregistrement) ;
     *                            false : un avertissement (analyse d'une tenue importée)
     * @return array{ok: bool, errors: list<string>, warnings: list<string>, unknown: list<string>, loads: array<string, array{load: float|null, max: int|null}>, mass: float|null}
     */
    public static function validate(array $lo, array $catalog, bool $strictUnknown = true): array
    {
        $errors = [];
        $warnings = [];
        $unknown = [];
        foreach (LoadoutDocument::classes($lo) as $class) {
            if (!isset($catalog[strtolower($class)])) {
                $unknown[] = $class;
            }
        }
        if ($unknown !== []) {
            $msg = 'Objet' . (count($unknown) > 1 ? 's' : '') . ' absent' . (count($unknown) > 1 ? 's' : '') . ' du catalogue : '
                . implode(', ', array_slice($unknown, 0, 8)) . (count($unknown) > 8 ? '…' : '') . '.';
            if ($strictUnknown) {
                $errors[] = $msg;
            } else {
                $warnings[] = $msg;
            }
        }
        $spec = static fn (string $c): ?array => $c === '' ? null : ($catalog[strtolower($c)] ?? null);
        $name = static fn (string $c): string => ($s = $spec($c)) !== null && (string) ($s['name'] ?? '') !== '' ? (string) $s['name'] : $c;

        // Emplacements de la tenue.
        $slotChecks = [0 => 'primary', 1 => 'secondary', 2 => 'handgun', 8 => 'binocular'];
        foreach ($slotChecks as $i => $kind) {
            $w = is_array($lo[$i] ?? null) ? $lo[$i] : [];
            if ($w !== []) {
                self::kindCheck((string) $w[0], $kind, $spec, $name, $errors);
                self::weaponCheck($w, $spec, $name, $errors, $warnings);
            }
        }
        foreach (LoadoutDocument::CONTAINER_SLOTS as $kind => $i) {
            $c = is_array($lo[$i] ?? null) ? $lo[$i] : [];
            if ($c !== []) {
                self::kindCheck((string) $c[0], $kind, $spec, $name, $errors);
            }
        }
        self::kindCheck((string) ($lo[6] ?? ''), 'headgear', $spec, $name, $errors);
        self::kindCheck((string) ($lo[7] ?? ''), 'goggles', $spec, $name, $errors);
        foreach (LoadoutDocument::ASSIGNED_SLOTS as $k => $slot) {
            self::kindCheck((string) (($lo[9] ?? [])[$k] ?? ''), $slot, $spec, $name, $errors);
        }

        // Contenu des conteneurs, charge et masse.
        $loads = [];
        $total = 0.0;
        $totalKnown = true;
        $addMass = static function (?float $m) use (&$total, &$totalKnown): void {
            if ($m === null) {
                $totalKnown = false;
            } else {
                $total += $m;
            }
        };
        $massOf = static function (string $c) use ($spec): ?float {
            $m = $spec($c)['mass'] ?? null;

            return $m === null ? null : (float) $m;
        };
        foreach ([0, 1, 2, 8] as $i) {
            $addMass(self::weaponMass(is_array($lo[$i] ?? null) ? $lo[$i] : [], $spec));
        }
        foreach ([6, 7] as $i) {
            if ((string) ($lo[$i] ?? '') !== '') {
                $addMass($massOf((string) $lo[$i]));
            }
        }
        foreach (is_array($lo[9] ?? null) ? $lo[9] : [] as $c) {
            if ((string) $c !== '') {
                $addMass($massOf((string) $c));
            }
        }
        foreach (LoadoutDocument::CONTAINER_SLOTS as $kind => $i) {
            $c = is_array($lo[$i] ?? null) ? $lo[$i] : [];
            if ($c === []) {
                continue;
            }
            $cSpec = $spec((string) $c[0]);
            $addMass($massOf((string) $c[0]));
            $load = 0.0;
            $known = true;
            foreach ($c[1] as $e) {
                if (is_array($e[0])) {
                    $wClass = (string) $e[0][0];
                    self::kindCheck($wClass, ['primary', 'secondary', 'handgun', 'binocular'], $spec, $name, $errors);
                    self::weaponCheck($e[0], $spec, $name, $errors, $warnings);
                    self::slotCheck($wClass, $kind, $spec, $name, $errors);
                    $m = self::weaponMass($e[0], $spec);
                    $count = (int) $e[1];
                } else {
                    $class = (string) $e[0];
                    self::slotCheck($class, $kind, $spec, $name, $errors);
                    if ($e[1] === true) {
                        self::kindCheck($class, 'backpack', $spec, $name, $errors);
                        $count = 1;
                    } else {
                        $count = (int) $e[1];
                        if (count($e) === 3) {
                            self::kindCheck($class, 'magazine', $spec, $name, $errors);
                            $cap = $spec($class)['ammo'] ?? null;
                            if ($cap !== null && (int) $e[2] > (int) $cap) {
                                $errors[] = '« ' . $name($class) . ' » : ' . (int) $e[2] . ' munitions pour un chargeur de ' . (int) $cap . '.';
                            }
                        }
                    }
                    $m = $spec($class)['mass'] ?? null;
                }
                if ($m === null) {
                    $known = false;
                } else {
                    $load += (float) $m * $count;
                }
            }
            $max = isset($cSpec['load']) ? (int) $cSpec['load'] : null;
            $loads[$kind] = ['load' => $known ? round($load, 3) : null, 'max' => $max];
            $addMass($known ? $load : null);
            if ($known && $max !== null && $load > $max + 0.0001) {
                $errors[] = ucfirst(self::CONTAINER_WORDS[$kind]) . ' déborde : charge ' . self::fmt($load) . ' pour ' . $max . ' au plus (« ' . $name((string) $c[0]) . ' »).';
            } elseif ($c[1] !== [] && ($max === null || !$known)) {
                $warnings[] = 'Charge de ' . self::CONTAINER_WORDS[$kind] . ' non vérifiée : masse ou capacité inconnue (importez le catalogue de configuration).';
            }
        }

        foreach (self::orphanMagazines($lo, $catalog) as $class) {
            $warnings[] = '« ' . $name($class) . ' » ne va dans aucune arme de la tenue (munitions pour un autre joueur ?).';
        }

        return [
            'ok' => $errors === [],
            'errors' => array_values(array_unique($errors)),
            'warnings' => array_values(array_unique($warnings)),
            'unknown' => $unknown,
            'loads' => $loads,
            'mass' => $totalKnown ? round($total, 3) : null,
        ];
    }

    /**
     * Chargeurs (balles, roquettes) rangés dans la tenue qui ne vont dans aucune de ses armes. Rien n'est signalé si
     * une arme de la tenue n'a pas de liste de chargeurs connue (on ne peut pas savoir) ni pour les grenades à main,
     * fumigènes et explosifs (lancés ou posés sans arme).
     *
     * @param list<mixed> $lo
     * @param array<string, array<string, mixed>> $catalog
     * @return list<string> classes
     */
    public static function orphanMagazines(array $lo, array $catalog): array
    {
        $weapons = [];
        foreach ([0, 1, 2] as $i) {
            if (is_array($lo[$i] ?? null) && $lo[$i] !== []) {
                $weapons[] = (string) $lo[$i][0];
            }
        }
        $stored = [];
        foreach (LoadoutDocument::CONTAINER_SLOTS as $i) {
            $c = is_array($lo[$i] ?? null) ? $lo[$i] : [];
            foreach ($c[1] ?? [] as $e) {
                if (is_array($e[0])) {
                    $weapons[] = (string) $e[0][0];
                } elseif (count($e) === 3) {
                    $stored[strtolower((string) $e[0])] = (string) $e[0];
                }
            }
        }
        if ($weapons === [] || $stored === []) {
            return [];
        }
        $accepted = [];
        foreach ($weapons as $w) {
            $mags = $catalog[strtolower($w)]['mags'] ?? null;
            if (!is_array($mags)) {
                return [];
            }
            foreach ($mags as $m) {
                $accepted[strtolower((string) $m)] = true;
            }
        }
        $out = [];
        foreach ($stored as $key => $class) {
            $cat = (string) ($catalog[$key]['cat'] ?? '');
            if (!isset($accepted[$key]) && in_array($cat, ['balles', 'roquettes'], true)) {
                $out[] = $class;
            }
        }

        return $out;
    }

    /**
     * @param string|list<string> $expected
     * @param callable(string): ?array $spec
     * @param callable(string): string $name
     * @param list<string> $errors
     */
    private static function kindCheck(string $class, string|array $expected, callable $spec, callable $name, array &$errors): void
    {
        if ($class === '') {
            return;
        }
        $s = $spec($class);
        if ($s === null) {
            return;
        }
        $kind = (string) ($s['kind'] ?? 'item');
        $accepted = [];
        foreach ((array) $expected as $e) {
            $accepted = array_merge($accepted, ConfiguratorKinds::acceptedKinds($e));
        }
        // Objet seulement vu en jeu sous une famille vague : accepté (le jeu ne dit rien de plus).
        if (in_array($kind, $accepted, true) || ($kind === 'item' && empty($s['spec']))) {
            return;
        }
        $want = ConfiguratorKinds::label(is_array($expected) ? $expected[0] : $expected);
        $errors[] = '« ' . $name($class) . ' » (' . ConfiguratorKinds::label($kind) . ') ne va pas à l’emplacement ' . $want . '.';
    }

    /**
     * @param list<mixed> $w
     * @param callable(string): ?array $spec
     * @param callable(string): string $name
     * @param list<string> $errors
     * @param list<string> $warnings
     */
    private static function weaponCheck(array $w, callable $spec, callable $name, array &$errors, array &$warnings): void
    {
        $ws = $spec((string) $w[0]);
        $compat = is_array($ws['compat'] ?? null) ? $ws['compat'] : null;
        $mags = is_array($ws['mags'] ?? null) ? $ws['mags'] : null;
        $unchecked = false;
        foreach (self::ATTACHMENTS as $i => $kind) {
            $a = (string) ($w[$i] ?? '');
            if ($a === '') {
                continue;
            }
            self::kindCheck($a, $kind, $spec, $name, $errors);
            if ($compat === null || !isset($compat[$kind])) {
                $unchecked = true;
                continue;
            }
            if (!in_array(strtolower($a), $compat[$kind], true)) {
                $errors[] = '« ' . $name($a) . ' » ne se monte pas sur « ' . $name((string) $w[0]) . ' » (emplacement ' . self::SLOT_WORDS[$kind] . ').';
            }
        }
        foreach ([4, 5] as $i) {
            $m = is_array($w[$i] ?? null) ? $w[$i] : [];
            if ($m === []) {
                continue;
            }
            $mc = (string) $m[0];
            self::kindCheck($mc, 'magazine', $spec, $name, $errors);
            $cap = $spec($mc)['ammo'] ?? null;
            if ($cap !== null && (int) $m[1] > (int) $cap) {
                $errors[] = '« ' . $name($mc) . ' » : ' . (int) $m[1] . ' munitions pour un chargeur de ' . (int) $cap . '.';
            }
            if ($mags === null) {
                $unchecked = true;
                continue;
            }
            if (!in_array(strtolower($mc), $mags, true)) {
                $errors[] = '« ' . $name($mc) . ' » ne va pas dans « ' . $name((string) $w[0]) . ' » (chargeur incompatible).';
            }
        }
        if ($unchecked && $ws !== null) {
            $warnings[] = 'Compatibilités de « ' . $name((string) $w[0]) . ' » non vérifiées : importez le catalogue de configuration.';
        }
    }

    /**
     * @param callable(string): ?array $spec
     * @param callable(string): string $name
     * @param list<string> $errors
     */
    private static function slotCheck(string $class, string $container, callable $spec, callable $name, array &$errors): void
    {
        $slots = $spec($class)['slots'] ?? [];
        if (!is_array($slots) || $slots === []) {
            return;
        }
        $code = ConfiguratorKinds::SLOT_CODES[$container];
        if (!in_array($code, array_map('intval', $slots), true)) {
            $errors[] = '« ' . $name($class) . ' » ne se range pas dans ' . self::CONTAINER_WORDS[$container] . ' (le jeu le refuse).';
        }
    }

    /**
     * Masse d'une arme montée : arme, accessoires et chargeurs ; null si une masse manque.
     *
     * @param list<mixed> $w
     * @param callable(string): ?array $spec
     */
    public static function weaponMass(array $w, callable $spec): ?float
    {
        if ($w === []) {
            return 0.0;
        }
        $total = 0.0;
        $parts = [(string) $w[0]];
        foreach ([1, 2, 3, 6] as $i) {
            if ((string) ($w[$i] ?? '') !== '') {
                $parts[] = (string) $w[$i];
            }
        }
        foreach ([4, 5] as $i) {
            if (is_array($w[$i] ?? null) && $w[$i] !== []) {
                $parts[] = (string) $w[$i][0];
            }
        }
        foreach ($parts as $p) {
            $m = $spec($p)['mass'] ?? null;
            if ($m === null) {
                return null;
            }
            $total += (float) $m;
        }

        return $total;
    }

    private static function fmt(float $v): string
    {
        return rtrim(rtrim(number_format($v, 1, ',', ''), '0'), ',');
    }
}
