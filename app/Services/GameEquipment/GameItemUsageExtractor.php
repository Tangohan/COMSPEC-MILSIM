<?php

declare(strict_types=1);

namespace App\Services\GameEquipment;

/**
 * Extrait d’un profil opérateur (register/sync) la liste des objets portés et des mods chargés.
 *
 * - `item_catalog` (mods récents) : [{class, name, kind, mod}] — source prioritaire (nom affiché, famille, mod).
 * - Sinon (anciens mods) : déduit de `equipment` (classes, noms affichés, famille selon l’emplacement) et du
 *   loadout brut (getUnitLoadout), sans mod connu.
 * - `mods` : [{name, dir, workshop, dlc}] — absent sur la plupart des sync, ce n’est pas une erreur.
 *
 * Pur : aucune base, testable seul.
 */
final class GameItemUsageExtractor
{
    public const MAX_ITEMS = 400;

    public const MAX_MODS = 400;

    /**
     * @param array<string, mixed> $payload profil normalisé (OperatorGameObservationNormalizer)
     * @return array{
     *   items: list<array{class: string, name: string, kind: string, mod: string, max_ammo: int|null}>,
     *   mods: list<array{dir: string, name: string, workshop_id: string, is_dlc: bool}>|null
     * }
     */
    public function extract(array $payload): array
    {
        /** @var array<string, array{class: string, name: string, kind: string, mod: string, max_ammo: int|null}> $items */
        $items = [];
        $equipment = is_array($payload['equipment'] ?? null) ? $payload['equipment'] : [];

        $catalog = $payload['item_catalog'] ?? $equipment['item_catalog'] ?? null;
        if (is_array($catalog)) {
            foreach ($catalog as $entry) {
                if (is_string($entry)) {
                    $this->add($items, $entry, '', 'item', '');
                    continue;
                }
                if (!is_array($entry)) {
                    continue;
                }
                $this->add(
                    $items,
                    $entry['class'] ?? $entry['class_name'] ?? $entry[0] ?? null,
                    $entry['name'] ?? $entry['display'] ?? $entry['display_name'] ?? '',
                    GameItemKinds::normalize($entry['kind'] ?? 'item'),
                    $entry['mod'] ?? $entry['mod_name'] ?? '',
                );
            }
        }

        $this->fromEquipment($items, $equipment);
        $loadout = $equipment['loadout'] ?? $payload['loadout'] ?? null;
        if (is_array($loadout)) {
            $this->fromLoadout($items, $loadout);
        }

        return [
            'items' => array_slice(array_values($items), 0, self::MAX_ITEMS),
            'mods' => $this->mods($payload['mods'] ?? null),
        ];
    }

    public static function cleanClass(mixed $value): string
    {
        if (!is_scalar($value)) {
            return '';
        }
        $class = trim((string) $value);
        if ($class === '' || strlen($class) > 128) {
            return '';
        }
        // Noms de classe Arma : lettres, chiffres, _ - . (rien d’autre dans un config.cpp).
        if (preg_match('/^[A-Za-z0-9_.\-]+$/', $class) !== 1) {
            return '';
        }

        return $class;
    }

    private static function cleanText(mixed $value, int $max): string
    {
        if (!is_scalar($value)) {
            return '';
        }
        $text = trim(preg_replace('/\s+/u', ' ', (string) $value) ?? '');
        if ($text === '') {
            return '';
        }

        return mb_substr($text, 0, $max);
    }

    /**
     * @param array<string, array{class: string, name: string, kind: string, mod: string, max_ammo: int|null}> $items
     */
    private function add(array &$items, mixed $class, mixed $name, string $kind, mixed $mod, ?int $ammo = null): void
    {
        $class = self::cleanClass($class);
        if ($class === '') {
            return;
        }
        $key = strtolower($class);
        $name = self::cleanText($name, 160);
        if ($name === $class) {
            $name = '';
        }
        $mod = self::cleanText($mod, 160);
        if (strcasecmp($mod, 'Arma 3') === 0 || strcasecmp($mod, 'A3') === 0) {
            $mod = 'Arma 3';
        }
        $ammo = $ammo !== null && $ammo > 0 ? min($ammo, 65000) : null;
        if (!isset($items[$key])) {
            if (count($items) >= self::MAX_ITEMS) {
                return;
            }
            $items[$key] = ['class' => $class, 'name' => $name, 'kind' => $kind, 'mod' => $mod, 'max_ammo' => $ammo];

            return;
        }
        $cur = &$items[$key];
        if ($cur['name'] === '' && $name !== '') {
            $cur['name'] = $name;
        }
        if ($cur['mod'] === '' && $mod !== '') {
            $cur['mod'] = $mod;
        }
        if (GameItemKinds::specificity($kind) > GameItemKinds::specificity($cur['kind'])) {
            $cur['kind'] = $kind;
        }
        if ($ammo !== null && ($cur['max_ammo'] === null || $ammo > $cur['max_ammo'])) {
            $cur['max_ammo'] = $ammo;
        }
    }

    /**
     * @param array<string, array{class: string, name: string, kind: string, mod: string, max_ammo: int|null}> $items
     * @param array<string, mixed> $eq
     */
    private function fromEquipment(array &$items, array $eq): void
    {
        $simple = [
            'uniform' => [['uniform_class', 'uniform'], ['uniform_display', 'uniform_name']],
            'vest' => [['vest_class', 'vest'], ['vest_display', 'vest_name']],
            'backpack' => [['backpack_class', 'backpack'], ['backpack_display', 'backpack_name']],
            'headgear' => [['helmet_class', 'headgear', 'headgear_class'], ['helmet_display', 'headgear_display']],
            'goggles' => [['goggles_class', 'goggles'], ['goggles_display']],
            'nvg' => [['nvgs_class', 'nvgs', 'nvg_class'], ['nvgs_display', 'nvg_display']],
        ];
        foreach ($simple as $kind => [$classKeys, $nameKeys]) {
            $class = null;
            foreach ($classKeys as $k) {
                if (is_scalar($eq[$k] ?? null) && trim((string) $eq[$k]) !== '') {
                    $class = $eq[$k];
                    break;
                }
            }
            $name = '';
            foreach ($nameKeys as $k) {
                if (is_scalar($eq[$k] ?? null) && trim((string) $eq[$k]) !== '') {
                    $name = $eq[$k];
                    break;
                }
            }
            $this->add($items, $class, $name, $kind, '');
        }

        foreach (['primary' => 'primary', 'secondary' => 'secondary', 'handgun' => 'handgun', 'binocular' => 'binocular'] as $slot => $kind) {
            $w = $eq[$slot] ?? null;
            if (is_string($w)) {
                $this->add($items, $w, '', $kind, '');
                continue;
            }
            if (!is_array($w)) {
                continue;
            }
            $this->add($items, $w['class'] ?? null, $w['display'] ?? $w['name'] ?? '', $kind, '');
            foreach (['muzzle' => 'muzzle', 'pointer' => 'pointer', 'optic' => 'optic', 'bipod' => 'bipod', 'magazine' => 'magazine'] as $attKey => $attKind) {
                $att = $w[$attKey] ?? null;
                if (is_array($att)) {
                    $this->add($items, $att['class'] ?? $att[0] ?? null, $att['display'] ?? $att['name'] ?? '', $attKind, '');
                } else {
                    $this->add($items, $att, '', $attKind, '');
                }
            }
        }

        foreach (['assigned_items' => 'item', 'radios' => 'radio', 'magazines' => 'magazine', 'medical_items' => 'medical'] as $listKey => $kind) {
            $list = $eq[$listKey] ?? null;
            if (!is_array($list)) {
                continue;
            }
            foreach ($list as $entry) {
                if (is_array($entry)) {
                    $this->add($items, $entry['class'] ?? $entry[0] ?? null, $entry['display'] ?? $entry['name'] ?? '', $kind, '');
                } else {
                    $this->add($items, $entry, '', $kind, '');
                }
            }
        }
    }

    /**
     * Loadout brut getUnitLoadout : [primaire, lanceur, poing, tenue, gilet, sac, casque, lunettes, jumelles, assignés].
     *
     * @param array<string, array{class: string, name: string, kind: string, mod: string, max_ammo: int|null}> $items
     * @param array<int|string, mixed> $loadout
     */
    private function fromLoadout(array &$items, array $loadout): void
    {
        // Forme ACE étendue : [[loadout], {extended}]
        if (count($loadout) === 2 && is_array($loadout[0] ?? null) && count($loadout[0]) >= 9) {
            $loadout = $loadout[0];
        }
        if (count($loadout) < 9) {
            return;
        }
        $this->loadoutWeapon($items, $loadout[0] ?? null, 'primary');
        $this->loadoutWeapon($items, $loadout[1] ?? null, 'secondary');
        $this->loadoutWeapon($items, $loadout[2] ?? null, 'handgun');
        foreach ([3 => 'uniform', 4 => 'vest', 5 => 'backpack'] as $idx => $kind) {
            $c = $loadout[$idx] ?? null;
            if (!is_array($c) || $c === []) {
                continue;
            }
            $this->add($items, $c[0] ?? null, '', $kind, '');
            $cargo = $c[1] ?? null;
            if (!is_array($cargo)) {
                continue;
            }
            foreach ($cargo as $entry) {
                if (!is_array($entry) || $entry === []) {
                    continue;
                }
                if (is_array($entry[0] ?? null)) {
                    // Arme rangée dans le conteneur : [[classe, ...], nombre]
                    $this->loadoutWeapon($items, $entry[0], 'item');
                    continue;
                }
                if (count($entry) >= 3 && is_numeric($entry[2])) {
                    $this->add($items, $entry[0] ?? null, '', 'magazine', '', (int) $entry[2]);
                } else {
                    $this->add($items, $entry[0] ?? null, '', 'item', '');
                }
            }
        }
        $this->add($items, $loadout[6] ?? null, '', 'headgear', '');
        $this->add($items, $loadout[7] ?? null, '', 'goggles', '');
        $this->loadoutWeapon($items, $loadout[8] ?? null, 'binocular');
        $assigned = $loadout[9] ?? null;
        if (is_array($assigned)) {
            $kinds = [0 => 'item', 1 => 'item', 2 => 'radio', 3 => 'item', 4 => 'item', 5 => 'nvg'];
            foreach ($kinds as $i => $kind) {
                $this->add($items, $assigned[$i] ?? null, '', $kind, '');
            }
        }
    }

    /**
     * @param array<string, array{class: string, name: string, kind: string, mod: string, max_ammo: int|null}> $items
     */
    private function loadoutWeapon(array &$items, mixed $w, string $kind): void
    {
        if (!is_array($w) || $w === []) {
            return;
        }
        $this->add($items, $w[0] ?? null, '', $kind, '');
        $this->add($items, $w[1] ?? null, '', 'muzzle', '');
        $this->add($items, $w[2] ?? null, '', 'pointer', '');
        $this->add($items, $w[3] ?? null, '', 'optic', '');
        foreach ([4, 5] as $i) {
            $mag = $w[$i] ?? null;
            if (is_array($mag) && $mag !== []) {
                $this->add($items, $mag[0] ?? null, '', 'magazine', '', isset($mag[1]) && is_numeric($mag[1]) ? (int) $mag[1] : null);
            }
        }
        $this->add($items, $w[6] ?? null, '', 'bipod', '');
    }

    /**
     * @return list<array{dir: string, name: string, workshop_id: string, is_dlc: bool}>|null null = liste non envoyée
     */
    private function mods(mixed $mods): ?array
    {
        if (!is_array($mods)) {
            return null;
        }
        $out = [];
        foreach ($mods as $m) {
            if (is_string($m)) {
                $m = ['name' => $m, 'dir' => $m];
            }
            if (!is_array($m)) {
                continue;
            }
            $name = self::cleanText($m['name'] ?? '', 160);
            $dir = self::cleanText($m['dir'] ?? $m['directory'] ?? '', 160);
            if ($dir === '') {
                $dir = $name;
            }
            if ($dir === '') {
                continue;
            }
            $key = strtolower($dir);
            if (isset($out[$key])) {
                continue;
            }
            $workshop = is_scalar($m['workshop'] ?? $m['workshop_id'] ?? null) ? (string) ($m['workshop'] ?? $m['workshop_id']) : '';
            $workshop = preg_replace('/\D+/', '', $workshop) ?? '';
            $workshop = $workshop !== '0' ? substr($workshop, 0, 24) : '';
            $dlc = $m['dlc'] ?? $m['is_dlc'] ?? false;
            $out[$key] = [
                'dir' => $dir,
                'name' => $name !== '' ? $name : $dir,
                'workshop_id' => $workshop,
                'is_dlc' => $dlc === true || $dlc === 1 || $dlc === '1' || (is_string($dlc) && strtolower($dlc) === 'true'),
            ];
            if (count($out) >= self::MAX_MODS) {
                break;
            }
        }

        return array_values($out);
    }
}
