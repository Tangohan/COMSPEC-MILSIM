<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Liste lisible de l’équipement d’une tenue envoyée depuis l’arsenal.
 */
final class ArsenalLoadoutItems
{
    /**
     * @return list<array{title: string, items: list<array{name: string, qty: int}}}>
     */
    public static function grouped(string $payload): array
    {
        $loadout = self::parse($payload);
        if ($loadout === []) {
            return [];
        }

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
     * @return list<mixed>
     */
    public static function parse(string $payload): array
    {
        $payload = trim($payload);
        if ($payload === '' || $payload[0] !== '[') {
            return [];
        }
        $decoded = json_decode($payload, true);
        if (!is_array($decoded)) {
            return [];
        }
        if (
            count($decoded) === 2
            && isset($decoded[0])
            && is_array($decoded[0])
            && count($decoded[0]) >= 9
            && is_numeric($decoded[1] ?? null)
        ) {
            $decoded = $decoded[0];
        }
        if (count($decoded) < 9) {
            return [];
        }

        return $decoded;
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
            $items[] = ['name' => self::displayName($container), 'qty' => 1];
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
            'items' => [['name' => self::displayName($value), 'qty' => 1]],
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
            $name = self::displayName($cls);
            if ($name === '' || strcasecmp($name, 'Item') === 0) {
                $name = $fallback;
            }
            $items[] = ['name' => $name, 'qty' => 1];
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
        $weaponName = self::displayName($weapon);
        $items = [['name' => $weaponName, 'qty' => 1]];
        foreach ([1, 2, 3, 6] as $i) {
            $acc = $slot[$i] ?? '';
            if (is_string($acc) && $acc !== '') {
                $items[] = ['name' => self::displayName($acc), 'qty' => 1];
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
            $magName = self::displayName($cls);
            if ($magName === '' || strcasecmp($magName, $weaponName) === 0) {
                continue;
            }
            $items[] = ['name' => $magName, 'qty' => 1];
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
            $items[] = ['name' => self::displayName($first), 'qty' => $qty];
        }

        return $items;
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
                $acc[$name] = 0;
                $order[] = $name;
            }
            $acc[$name] += $qty;
        }
        $out = [];
        foreach ($order as $name) {
            $out[] = ['name' => $name, 'qty' => $acc[$name]];
        }

        return $out;
    }
}
