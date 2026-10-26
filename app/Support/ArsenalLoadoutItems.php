<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Liste lisible de l’équipement d’une tenue envoyée depuis l’arsenal.
 */
final class ArsenalLoadoutItems
{
    /**
     * Catalogue des objets vus en jeu (clé = classe en minuscules), actif le temps d’un appel à grouped().
     *
     * @var array<string, array{name?: string, players?: int}>
     */
    private static array $catalog = [];

    /**
     * @param array<string, array{name?: string, players?: int}> $catalog noms affichés en jeu et nombre de joueurs
     *        qui portent l’objet (GameEquipmentRepository::catalogueFor), clé = classe en minuscules
     * @return list<array{title: string, items: list<array{name: string, qty: int, class: string, players?: int}>}>
     */
    public static function grouped(string $payload, array $catalog = []): array
    {
        $loadout = self::parse($payload);
        self::$catalog = $catalog;
        try {
            if ($loadout === []) {
                // Forme inconnue mais lisible : on liste au moins les objets plutôt qu’une fiche vide.
                $items = self::mergeQty(array_map(static fn (string $c): array => self::item($c), self::classes($payload)));

                return $items === [] ? [] : [['title' => 'Contenu', 'items' => $items]];
            }

            return self::sections($loadout);
        } finally {
            self::$catalog = [];
        }
    }

    /**
     * Toutes les classes d’objets d’une tenue (armes, accessoires, chargeurs, contenus, objets assignés).
     *
     * @return list<string>
     */
    public static function classes(string $payload): array
    {
        $out = [];
        $walk = static function (mixed $node) use (&$walk, &$out): void {
            if (is_string($node)) {
                $c = trim($node);
                if ($c !== '' && strlen($c) <= 128 && preg_match('/^[A-Za-z0-9_.\-]+$/', $c) === 1) {
                    $out[strtolower($c)] = $c;
                }

                return;
            }
            if (is_array($node)) {
                foreach ($node as $child) {
                    $walk($child);
                }
            }
        };
        $walk(self::parse($payload) ?: (self::decode($payload) ?? []));

        return array_values($out);
    }

    /**
     * @param list<mixed> $loadout
     * @return list<array{title: string, items: list<array{name: string, qty: int, class: string, players?: int}>}>
     */
    private static function sections(array $loadout): array
    {
        $sections = [];
        self::pushWeapon($sections, 'Arme', $loadout[0] ?? null);
        self::pushWeapon($sections, 'Lanceur', $loadout[1] ?? null);
        self::pushWeapon($sections, 'Pistolet', $loadout[2] ?? null);
        self::pushContainer($sections, 'Tenue', $loadout[3] ?? null);
        self::pushContainer($sections, 'Gilet', $loadout[4] ?? null);
        self::pushContainer($sections, 'Sac', $loadout[5] ?? null);
        self::pushSimple($sections, 'Casque', $loadout[6] ?? null);
        self::pushSimple($sections, 'Lunettes', $loadout[7] ?? null);
        self::pushWeapon($sections, 'Jumelles', $loadout[8] ?? null);
        self::pushAssigned($sections, $loadout[9] ?? null);

        return $sections;
    }

    /**
     * Tableau getUnitLoadout d’une tenue, quel que soit l’emballage envoyé : JSON ou texte SQF (`str`, guillemets
     * doublés, true/false), [loadout, version] ou [loadout, infos étendues ACE], [nom, loadout] (export ACE).
     *
     * @return list<mixed>
     */
    public static function parse(string $payload): array
    {
        $decoded = self::decode($payload);
        if ($decoded === null) {
            return [];
        }
        for ($depth = 0; $depth < 3; $depth++) {
            if (count($decoded) !== 2 || !is_array($decoded[1] ?? null) && !is_numeric($decoded[1] ?? null)) {
                break;
            }
            if (is_array($decoded[0] ?? null) && count($decoded[0]) >= 9) {
                $decoded = $decoded[0];
                break;
            }
            if (is_string($decoded[0] ?? null) && is_array($decoded[1])) {
                $decoded = $decoded[1];
                continue;
            }
            break;
        }
        if (count($decoded) < 9 || array_is_list($decoded) === false) {
            return [];
        }

        return $decoded;
    }

    /**
     * Texte de tenue → tableau PHP (JSON d’abord, sinon lecture du texte SQF). null si illisible.
     *
     * @return array<int|string, mixed>|null
     */
    public static function decode(string $payload): ?array
    {
        $payload = trim($payload);
        if ($payload === '' || $payload[0] !== '[') {
            return null;
        }
        $decoded = json_decode($payload, true);
        if (is_array($decoded)) {
            return $decoded;
        }
        if (strlen($payload) > 600000) {
            return null;
        }
        $pos = 0;
        try {
            $value = self::sqfValue($payload, $pos, 0);
        } catch (\UnexpectedValueException) {
            return null;
        }
        if (!is_array($value)) {
            return null;
        }

        return $value;
    }

    /**
     * Lecture d’une valeur SQF (`str` d’un tableau) : tableaux, chaînes "…" ou '…' (guillemet doublé = guillemet),
     * nombres, true/false ; tout autre mot (nil, any, objNull…) vaut null.
     */
    private static function sqfValue(string $s, int &$pos, int $depth): mixed
    {
        $len = strlen($s);
        while ($pos < $len && ctype_space($s[$pos])) {
            $pos++;
        }
        if ($pos >= $len || $depth > 32) {
            throw new \UnexpectedValueException('sqf_eof');
        }
        $c = $s[$pos];
        if ($c === '[') {
            $pos++;
            $out = [];
            while (true) {
                while ($pos < $len && ctype_space($s[$pos])) {
                    $pos++;
                }
                if ($pos < $len && $s[$pos] === ']') {
                    $pos++;

                    return $out;
                }
                $out[] = self::sqfValue($s, $pos, $depth + 1);
                while ($pos < $len && ctype_space($s[$pos])) {
                    $pos++;
                }
                if ($pos < $len && $s[$pos] === ',') {
                    $pos++;
                    continue;
                }
                if ($pos < $len && $s[$pos] === ']') {
                    $pos++;

                    return $out;
                }
                throw new \UnexpectedValueException('sqf_array');
            }
        }
        if ($c === '"' || $c === "'") {
            $pos++;
            $buf = '';
            while ($pos < $len) {
                $ch = $s[$pos];
                if ($ch === $c) {
                    if ($pos + 1 < $len && $s[$pos + 1] === $c) {
                        $buf .= $c;
                        $pos += 2;
                        continue;
                    }
                    $pos++;

                    return $buf;
                }
                $buf .= $ch;
                $pos++;
            }
            throw new \UnexpectedValueException('sqf_string');
        }
        if (preg_match('/\G-?(?:\d+\.?\d*|\.\d+)(?:[eE][+-]?\d+)?/', $s, $m, 0, $pos) === 1) {
            $pos += strlen($m[0]);

            return $m[0] + 0;
        }
        if (preg_match('/\G[A-Za-z_][A-Za-z0-9_<>]*/', $s, $m, 0, $pos) === 1) {
            $pos += strlen($m[0]);
            $w = strtolower($m[0]);

            return $w === 'true' ? true : ($w === 'false' ? false : null);
        }
        if ($c === '<') {
            // <null>, <NULL-object> : valeurs vides affichées par str.
            $end = strpos($s, '>', $pos);
            if ($end !== false) {
                $pos = $end + 1;

                return null;
            }
        }
        throw new \UnexpectedValueException('sqf_token');
    }

    /**
     * Pièces principales d’une tenue pour sa carte du catalogue (arme, tenue, gilet, casque, sac, lanceur), 5 au plus.
     *
     * @return list<string>
     */
    public static function previewClasses(string $payload): array
    {
        $lo = self::parse($payload);
        if ($lo === []) {
            return array_slice(self::classes($payload), 0, 5);
        }
        $pick = [
            is_array($lo[0] ?? null) ? ($lo[0][0] ?? '') : '',
            is_array($lo[3] ?? null) ? ($lo[3][0] ?? '') : '',
            is_array($lo[4] ?? null) ? ($lo[4][0] ?? '') : '',
            $lo[6] ?? '',
            is_array($lo[5] ?? null) ? ($lo[5][0] ?? '') : '',
            is_array($lo[1] ?? null) ? ($lo[1][0] ?? '') : '',
            is_array($lo[2] ?? null) ? ($lo[2][0] ?? '') : '',
        ];
        $out = [];
        foreach ($pick as $c) {
            if (is_string($c) && trim($c) !== '' && !in_array(trim($c), $out, true)) {
                $out[] = trim($c);
            }
        }

        return array_slice($out, 0, 5);
    }

    /**
     * Lignes d’aperçu (nom, classe, icône) pour une liste de classes, avec les noms vus en jeu si connus.
     *
     * @param list<string> $classes
     * @param array<string, array{name?: string, players?: int}> $catalog
     * @return list<array{name: string, qty: int, class: string, players?: int, icon?: string}>
     */
    public static function previewItems(array $classes, array $catalog = []): array
    {
        self::$catalog = $catalog;
        try {
            return array_map(static fn (string $c): array => self::item($c), $classes);
        } finally {
            self::$catalog = [];
        }
    }

    /**
     * Texte à coller dans l’arsenal ACE (bouton Importer, mannequin) : le getUnitLoadout de la tenue en SQF.
     * null si la tenue est illisible.
     */
    public static function aceExport(string $payload): ?string
    {
        $loadout = self::parse($payload);

        return $loadout === [] ? null : self::sqf($loadout);
    }

    /**
     * Liste à coller dans « Mes équipements » d’ACE (onglet Mes équipements, Importer) : [[nom, loadout], …].
     * Les tenues illisibles sont laissées de côté.
     *
     * @param list<array{name: string, payload: string}> $wardrobes
     */
    public static function aceExportList(array $wardrobes): string
    {
        $out = [];
        $seen = [];
        foreach ($wardrobes as $w) {
            $loadout = self::parse((string) ($w['payload'] ?? ''));
            $name = trim(preg_replace('/\s+/u', ' ', (string) ($w['name'] ?? '')) ?? '');
            if ($loadout === [] || $name === '') {
                continue;
            }
            // ACE range ses tenues par nom : un doublon écraserait la première.
            $base = $name;
            for ($n = 2; isset($seen[mb_strtolower($name)]); $n++) {
                $name = $base . ' (' . $n . ')';
            }
            $seen[mb_strtolower($name)] = true;
            $out[] = [$name, $loadout];
        }

        return self::sqf($out);
    }

    /**
     * Valeur PHP → texte SQF lisible par parseSimpleArray (guillemets doublés dans les chaînes).
     */
    public static function sqf(mixed $v): string
    {
        if (is_array($v)) {
            return '[' . implode(',', array_map([self::class, 'sqf'], array_values($v))) . ']';
        }
        if (is_bool($v)) {
            return $v ? 'true' : 'false';
        }
        if (is_int($v)) {
            return (string) $v;
        }
        if (is_float($v)) {
            return is_finite($v) ? rtrim(rtrim(sprintf('%.6F', $v), '0'), '.') : '0';
        }
        if ($v === null) {
            return '""';
        }

        return '"' . str_replace('"', '""', (string) $v) . '"';
    }

    /**
     * Titre d’affichage d’une tenue (nom technique nettoyé pour la grille catalogue).
     */
    public static function formatWardrobeTitle(string $name): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
        if ($name === '') {
            return 'Tenue';
        }
        $name = preg_replace('/\s*[-–—]\s*/u', ' · ', $name) ?? $name;
        $name = preg_replace('/\s*·\s*/u', ' · ', $name) ?? $name;

        return $name;
    }

    /**
     * Catégories présentes dans le loadout (filtres catalogue).
     *
     * @return list<string> clothing|vest|helmet|backpack|accessory
     */
    public static function presentKinds(string $payload): array
    {
        $loadout = self::parse($payload);
        if ($loadout === []) {
            return [];
        }
        $kinds = [];
        if (self::slotHasGear($loadout[3] ?? null, true)) {
            $kinds[] = 'clothing';
        }
        if (self::slotHasGear($loadout[4] ?? null, true)) {
            $kinds[] = 'vest';
        }
        if (self::slotHasGear($loadout[5] ?? null, true)) {
            $kinds[] = 'backpack';
        }
        if (is_string($loadout[6] ?? null) && trim((string) $loadout[6]) !== '') {
            $kinds[] = 'helmet';
        }
        $hasAccessory = (is_string($loadout[7] ?? null) && trim((string) $loadout[7]) !== '')
            || self::assignedHasGear($loadout[9] ?? null);
        if ($hasAccessory) {
            $kinds[] = 'accessory';
        }

        return $kinds;
    }

    /**
     * Libellés du référentiel de types (filtre catalogue).
     *
     * @return array<string, string>
     */
    public static function kindLabels(): array
    {
        return [
            'clothing' => 'Vêtement',
            'vest' => 'Gilet / Porte-plaques',
            'helmet' => 'Casque',
            'backpack' => 'Sac',
            'accessory' => 'Accessoire',
        ];
    }

    /**
     * Ligne d’objet : nom affiché en jeu s’il est connu, sinon nom déduit de la classe.
     *
     * @return array{name: string, qty: int, class: string, players?: int, icon?: string}
     */
    private static function item(string $class, int $qty = 1, string $fallback = ''): array
    {
        $known = self::$catalog[strtolower(trim($class))] ?? null;
        $name = trim((string) ($known['name'] ?? ''));
        if ($name === '') {
            $name = self::displayName($class);
            if ($fallback !== '' && ($name === '' || strcasecmp($name, 'Item') === 0)) {
                $name = $fallback;
            }
        }
        $row = ['name' => $name, 'qty' => max(1, $qty), 'class' => trim($class)];
        if ($known !== null && isset($known['players'])) {
            $row['players'] = (int) $known['players'];
        }
        // Icône de l’objet (texture du jeu convertie, envoyée par COMSPEC Link 2.0.88), absente tant qu’elle n’est pas arrivée.
        $icon = \App\Repositories\GameItemIconRepository::urlFor($row['class']);
        if ($icon !== null) {
            $row['icon'] = $icon;
        }

        return $row;
    }

    public static function displayName(string $class): string
    {
        $class = trim($class);
        if ($class === '') {
            return '';
        }
        $s = $class;
        foreach ([
            'rhsusf_acc_', 'rhsusf_weap_', 'rhsusf_', 'rhsgref_', 'rhs_weap_', 'rhs_acc_', 'rhs_mag_', 'rhs_',
            'CUP_arifle_', 'CUP_launch_', 'CUP_hgun_', 'CUP_lmg_', 'CUP_srifle_', 'CUP_optic_', 'CUP_muzzle_',
            'CUP_acc_', 'CUP_bipod_', 'CUP_H_', 'CUP_U_', 'CUP_V_', 'CUP_B_', 'CUP_G_', 'CUP_',
            'ace_acc_', 'ace_', 'ACE_acc_', 'ACE_',
            'SMA_BARREL_', 'SMA_',
            'arifle_', 'srifle_', 'LMG_', 'MMG_', 'SMG_', 'sgun_', 'hgun_', 'launch_',
            'optic_', 'acc_', 'muzzle_', 'bipod_',
            'U_B_', 'U_O_', 'U_I_', 'U_C_', 'V_', 'H_', 'G_', 'B_',
            'Item',
        ] as $prefix) {
            if (str_starts_with($s, $prefix)) {
                $s = substr($s, strlen($prefix));
                break;
            }
        }
        if (preg_match('/^(U|V|H|G|B)_(.+)$/', $s, $m) === 1) {
            $s = $m[2];
        }
        if (preg_match('/^(U|V|H|G|B)_(.+)$/', $s, $m) === 1) {
            $s = $m[2];
        }
        $s = preg_replace('/_F$/', '', $s) ?? $s;
        $s = str_replace('_', ' ', $s);
        $s = preg_replace('/\s+/', ' ', $s) ?? $s;
        $s = trim($s);

        return $s !== '' ? $s : $class;
    }

    /**
     * @param list<array{title: string, items: list<array{name: string, qty: int}}}> $sections
     */
    private static function pushWeapon(array &$sections, string $title, mixed $slot): void
    {
        $items = self::weaponItems($slot);
        if ($items !== []) {
            $sections[] = ['title' => $title, 'items' => $items];
        }
    }

    /**
     * @param list<array{title: string, items: list<array{name: string, qty: int}}}> $sections
     */
    private static function pushContainer(array &$sections, string $title, mixed $slot): void
    {
        if (!is_array($slot) || $slot === []) {
            return;
        }
        $items = [];
        $container = $slot[0] ?? '';
        if (is_string($container) && $container !== '') {
            $items[] = self::item($container);
        }
        $cargo = $slot[1] ?? [];
        foreach (self::cargoItems($cargo) as $row) {
            $items[] = $row;
        }
        $items = self::mergeQty($items);
        if ($items !== []) {
            $sections[] = ['title' => $title, 'items' => $items];
        }
    }

    /**
     * @param list<array{title: string, items: list<array{name: string, qty: int}}}> $sections
     */
    private static function pushSimple(array &$sections, string $title, mixed $value): void
    {
        if (!is_string($value) || trim($value) === '') {
            return;
        }
        $sections[] = [
            'title' => $title,
            'items' => [self::item($value)],
        ];
    }

    /**
     * @param list<array{title: string, items: list<array{name: string, qty: int}}}> $sections
     */
    private static function pushAssigned(array &$sections, mixed $slot): void
    {
        if (!is_array($slot)) {
            return;
        }
        $labels = ['Carte', 'GPS', 'Radio', 'Boussole', 'Montre', 'Vision nocturne'];
        $items = [];
        foreach ($labels as $i => $fallback) {
            $cls = $slot[$i] ?? '';
            if (!is_string($cls) || trim($cls) === '') {
                continue;
            }
            $items[] = self::item($cls, 1, $fallback);
        }
        $items = self::mergeQty($items);
        if ($items !== []) {
            $sections[] = ['title' => 'Équipement porté', 'items' => $items];
        }
    }

    /**
     * @return list<array{name: string, qty: int}>
     */
    private static function weaponItems(mixed $slot): array
    {
        if (!is_array($slot) || $slot === []) {
            return [];
        }
        $weapon = $slot[0] ?? '';
        if (!is_string($weapon) || $weapon === '') {
            return [];
        }
        $first = self::item($weapon);
        $weaponName = $first['name'];
        $items = [$first];
        foreach ([1, 2, 3, 6] as $i) {
            $acc = $slot[$i] ?? '';
            if (is_string($acc) && $acc !== '') {
                $items[] = self::item($acc);
            }
        }
        foreach ([4, 5] as $i) {
            $mag = $slot[$i] ?? null;
            if (!is_array($mag) || $mag === []) {
                continue;
            }
            $cls = $mag[0] ?? '';
            if (!is_string($cls) || $cls === '') {
                continue;
            }
            $mag = self::item($cls);
            if ($mag['name'] === '' || strcasecmp($mag['name'], $weaponName) === 0) {
                continue;
            }
            $items[] = $mag;
        }

        return self::mergeQty($items);
    }

    /**
     * @return list<array{name: string, qty: int}>
     */
    private static function cargoItems(mixed $cargo): array
    {
        if (!is_array($cargo)) {
            return [];
        }
        $items = [];
        foreach ($cargo as $entry) {
            if (!is_array($entry) || $entry === []) {
                continue;
            }
            $first = $entry[0] ?? null;
            if (is_array($first)) {
                $nested = self::weaponItems($entry);
                foreach ($nested as $row) {
                    $items[] = $row;
                }
                continue;
            }
            if (!is_string($first) || $first === '') {
                continue;
            }
            $qty = (int) ($entry[1] ?? 1);
            if ($qty < 1) {
                $qty = 1;
            }
            $items[] = self::item($first, $qty);
        }

        return $items;
    }

    private static function slotHasGear(mixed $slot, bool $container): bool
    {
        if ($container) {
            if (!is_array($slot) || $slot === []) {
                return false;
            }
            $containerClass = $slot[0] ?? '';

            return is_string($containerClass) && trim($containerClass) !== '';
        }

        return is_string($slot) && trim($slot) !== '';
    }

    private static function assignedHasGear(mixed $slot): bool
    {
        if (!is_array($slot)) {
            return false;
        }
        foreach ($slot as $cls) {
            if (is_string($cls) && trim($cls) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array{name: string, qty: int}> $items
     * @return list<array{name: string, qty: int}>
     */
    private static function mergeQty(array $items): array
    {
        $acc = [];
        $order = [];
        foreach ($items as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $qty = (int) ($row['qty'] ?? 1);
            if ($qty < 1) {
                $qty = 1;
            }
            if (!isset($acc[$name])) {
                $acc[$name] = $row;
                $acc[$name]['name'] = $name;
                $acc[$name]['qty'] = 0;
                $order[] = $name;
            }
            $acc[$name]['qty'] += $qty;
        }
        $out = [];
        foreach ($order as $name) {
            $out[] = $acc[$name];
        }

        return $out;
    }
}
