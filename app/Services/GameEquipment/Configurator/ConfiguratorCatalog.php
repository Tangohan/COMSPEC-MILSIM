<?php

declare(strict_types=1);

namespace App\Services\GameEquipment\Configurator;

use App\Repositories\GameEquipmentRepository;
use App\Repositories\GameItemIconRepository;
use App\Repositories\GameItemSpecRepository;

/**
 * Catalogue du configurateur : objets vus en jeu (game_item_catalog, noms affichés et familles remontés par le mod)
 * complétés par la configuration importée du jeu (game_item_specs : masse, charge, compatibilités). Aucune donnée
 * n'est inventée : un champ absent reste null et le configurateur le dit.
 *
 * Deux formes :
 * - lookup : [classe en minuscules => fiche] pour LoadoutValidator (côté serveur) ;
 * - payload compact pour le navigateur (plusieurs milliers d'objets) : listes de compatibilité dédoublonnées
 *   (« groupes » partagés entre armes d'une même famille de rails), clés courtes.
 */
final class ConfiguratorCatalog
{
    public function __construct(
        private ?GameItemSpecRepository $specs = null,
        private ?GameEquipmentRepository $observed = null,
        private ?GameItemIconRepository $icons = null,
    ) {
        $this->specs ??= new GameItemSpecRepository();
        $this->observed ??= new GameEquipmentRepository();
        $this->icons ??= new GameItemIconRepository();
    }

    /**
     * Fiches des classes demandées (validation d'une tenue).
     *
     * @param list<string> $classes
     * @return array<string, array<string, mixed>>
     */
    public function lookup(int $tenantId, array $classes): array
    {
        if ($classes === []) {
            return [];
        }
        try {
            $specRows = $this->specs->rows($tenantId, $classes);
        } catch (\Throwable) {
            $specRows = [];
        }
        try {
            $observed = array_values($this->observed->catalogueFor($tenantId, $classes));
        } catch (\Throwable) {
            $observed = [];
        }

        return self::merge($specRows, $observed, self::locale());
    }

    /**
     * Catalogue complet de la communauté pour le navigateur.
     *
     * @return array{items: list<array<string, mixed>>, groups: list<list<string>>, stats: array<string, int>}
     */
    public function browserPayload(int $tenantId): array
    {
        try {
            $specRows = $this->specs->rows($tenantId);
        } catch (\Throwable) {
            $specRows = [];
        }
        try {
            $observed = $this->observed->catalogue($tenantId, 20000);
        } catch (\Throwable) {
            $observed = [];
        }
        try {
            $hashes = $this->icons->hashesForTenant($tenantId);
        } catch (\Throwable) {
            $hashes = [];
        }

        return self::compact(self::merge($specRows, $observed, self::locale()), $hashes);
    }

    private static function locale(): string
    {
        return function_exists('locale') && locale() === 'en' ? 'en' : 'fr';
    }

    /**
     * @param list<array<string, mixed>> $specRows lignes de game_item_specs
     * @param list<array<string, mixed>> $observedRows lignes de GameEquipmentRepository::catalogue / catalogueFor
     * @param string $locale langue du site (fr|en) : nom des fiches du catalogue de base
     * @return array<string, array<string, mixed>>
     */
    public static function merge(array $specRows, array $observedRows, string $locale = 'fr'): array
    {
        $out = [];
        foreach ($observedRows as $o) {
            $class = (string) ($o['class'] ?? '');
            if ($class === '') {
                continue;
            }
            $k = ConfiguratorKinds::fromObserved($o['kind'] ?? 'item');
            $ammo = isset($o['max_ammo']) && (int) $o['max_ammo'] > 0 ? (int) $o['max_ammo'] : null;
            $out[strtolower($class)] = [
                'class' => $class,
                'name' => (string) ($o['name'] ?? ''),
                'kind' => $k['kind'],
                'cat' => $k['cat'],
                'mass' => null,
                'load' => null,
                'slots' => [],
                'ammo' => $ammo,
                'slot_type' => null,
                'compat' => null,
                'mags' => null,
                'mod' => (string) ($o['mod'] ?? ''),
                'players' => (int) ($o['players'] ?? 0),
                'spec' => false,
            ];
        }
        foreach ($specRows as $r) {
            $class = (string) ($r['class_name'] ?? '');
            if ($class === '') {
                continue;
            }
            $key = strtolower($class);
            $slotType = isset($r['slot_type']) && $r['slot_type'] !== null ? (int) $r['slot_type'] : null;
            $k = ConfiguratorKinds::fromSpec((string) ($r['type_main'] ?? ''), (string) ($r['type_sub'] ?? ''), $slotType);
            $compat = null;
            $decoded = isset($r['compat_json']) && is_string($r['compat_json']) ? json_decode($r['compat_json'], true) : null;
            if (is_array($decoded)) {
                $compat = [];
                foreach (ItemCatalogImport::COMPAT_SLOTS as $slot) {
                    $compat[$slot] = array_values(array_map('strtolower', array_filter(is_array($decoded[$slot] ?? null) ? $decoded[$slot] : [], 'is_string')));
                }
            }
            $mags = null;
            $decodedMags = isset($r['mags_json']) && is_string($r['mags_json']) ? json_decode($r['mags_json'], true) : null;
            if (is_array($decodedMags)) {
                $mags = array_values(array_map('strtolower', array_filter($decodedMags, 'is_string')));
            }
            $slots = [];
            foreach (explode(',', (string) ($r['allowed_slots'] ?? '')) as $s) {
                if ((int) $s > 0) {
                    $slots[] = (int) $s;
                }
            }
            $prev = $out[$key] ?? [];
            $base = ($r['source'] ?? '') === 'vanilla';
            $name = trim((string) ($r['name'] ?? ''));
            if ($base) {
                // Catalogue de base : le nom vu en jeu passe d'abord, puis le nom de la langue du site.
                $localized = $locale === 'en' && trim((string) ($r['name_en'] ?? '')) !== '' ? trim((string) $r['name_en']) : $name;
                $name = trim((string) ($prev['name'] ?? '')) !== '' ? (string) $prev['name'] : $localized;
            }
            $out[$key] = [
                'class' => $class,
                'name' => $name !== '' ? $name : (string) ($prev['name'] ?? ''),
                'kind' => $k['kind'],
                'cat' => $k['cat'],
                'mass' => isset($r['mass']) && $r['mass'] !== null ? (float) $r['mass'] : null,
                'load' => isset($r['max_load']) && $r['max_load'] !== null ? (int) $r['max_load'] : null,
                'slots' => $slots,
                'ammo' => isset($r['ammo_count']) && $r['ammo_count'] !== null ? (int) $r['ammo_count'] : ($prev['ammo'] ?? null),
                'slot_type' => $slotType,
                'compat' => $compat,
                'mags' => $mags,
                'mod' => trim((string) ($r['mod_name'] ?? '')) !== '' ? (string) $r['mod_name'] : (string) ($prev['mod'] ?? ''),
                'players' => (int) ($prev['players'] ?? 0),
                'spec' => true,
                'base' => $base,
            ];
        }

        return $out;
    }

    /**
     * Forme compacte pour le navigateur. Clés : c classe, n nom, k famille, g catégorie, m masse, l charge max,
     * s emplacements autorisés, a munitions, p joueurs qui l'ont porté, o mod, i empreinte d'icône,
     * x compatibilités {mu, op, po, bi, mg} = index dans groups (absent = inconnu), v 1 si configuration importée.
     *
     * @param array<string, array<string, mixed>> $lookup
     * @param array<string, string> $iconHashes
     * @return array{items: list<array<string, mixed>>, groups: list<list<string>>, stats: array<string, int>}
     */
    public static function compact(array $lookup, array $iconHashes = []): array
    {
        $groups = [];
        $index = [];
        $group = static function (array $list) use (&$groups, &$index): int {
            sort($list);
            $key = implode('|', $list);
            if (!isset($index[$key])) {
                $index[$key] = count($groups);
                $groups[] = $list;
            }

            return $index[$key];
        };
        $items = [];
        $withSpecs = 0;
        $base = 0;
        foreach ($lookup as $key => $it) {
            $row = ['c' => $it['class'], 'n' => $it['name'], 'k' => $it['kind']];
            if ($it['cat'] !== '') {
                $row['g'] = $it['cat'];
            }
            if ($it['mass'] !== null) {
                $row['m'] = $it['mass'];
            }
            if ($it['load'] !== null) {
                $row['l'] = $it['load'];
            }
            if ($it['slots'] !== []) {
                $row['s'] = $it['slots'];
            }
            if ($it['ammo'] !== null) {
                $row['a'] = $it['ammo'];
            }
            if (($it['players'] ?? 0) > 0) {
                $row['p'] = $it['players'];
            }
            if ($it['mod'] !== '') {
                $row['o'] = $it['mod'];
            }
            if (isset($iconHashes[$key])) {
                $row['i'] = $iconHashes[$key];
            }
            $x = [];
            if (is_array($it['compat'])) {
                foreach (['muzzle' => 'mu', 'optic' => 'op', 'pointer' => 'po', 'bipod' => 'bi'] as $slot => $short) {
                    $x[$short] = $group($it['compat'][$slot] ?? []);
                }
            }
            if (is_array($it['mags'])) {
                $x['mg'] = $group($it['mags']);
            }
            if ($x !== []) {
                $row['x'] = $x;
            }
            if (!empty($it['spec'])) {
                $row['v'] = 1;
                $withSpecs++;
            }
            if (!empty($it['base'])) {
                $row['b'] = 1;
                $base++;
            }
            $items[] = $row;
        }

        return ['items' => $items, 'groups' => $groups, 'stats' => ['items' => count($items), 'with_specs' => $withSpecs, 'base' => $base, 'groups' => count($groups)]];
    }
}
