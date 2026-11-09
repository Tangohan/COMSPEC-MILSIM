<?php

declare(strict_types=1);

namespace App\Services\GameEquipment\Configurator;

/**
 * Lecture de l'export de configuration fait en jeu (comspec_sse_fnc_loadoutCatalog ou
 * tools/arma/export_catalogue_equipement.sqf, hors ligne) :
 *
 *   ["comspec-equipment-catalog", 1, version du jeu, [[dossier, nom du mod]…], [objet…]]
 *   objet = [classe, racine (w|m|v|g), type principal, sous-type, nom, masse, charge max, type d'emplacement,
 *            munitions, emplacements autorisés[], compat [[bouche…],[optiques…],[rail…],[bipied…]], chargeurs[], mod]
 *
 * Pur : ne touche pas la base. Tout est borné (taille du texte, nombre d'objets, longueur des listes) et chaque
 * valeur est contrôlée ; une ligne illisible est comptée comme écartée, jamais devinée.
 */
final class ItemCatalogImport
{
    public const FORMAT = 'comspec-equipment-catalog';

    public const MAX_BYTES = 48 * 1024 * 1024;

    public const MAX_ITEMS = 30000;

    public const MAX_LIST = 3000;

    public const CLASS_RE = '/^[A-Za-z0-9_][A-Za-z0-9_\-.]{0,127}$/';

    private const ROOTS = ['w', 'm', 'v', 'g'];

    /** Ordre des listes de compatibilité de l'export. */
    public const COMPAT_SLOTS = ['muzzle', 'optic', 'pointer', 'bipod'];

    /**
     * @return array{ok: bool, error?: string, rows: list<array<string, mixed>>, skipped: int, version: string, mods: int}
     */
    public static function parse(string $text): array
    {
        $empty = ['rows' => [], 'skipped' => 0, 'version' => '', 'mods' => 0];
        if (strlen($text) > self::MAX_BYTES) {
            return ['ok' => false, 'error' => 'Export trop gros (48 Mo au plus).'] + $empty;
        }
        $root = SqfArrayText::decode($text, self::MAX_BYTES);
        if ($root === null || !array_is_list($root) || ($root[0] ?? null) !== self::FORMAT) {
            return ['ok' => false, 'error' => 'Ce texte n’est pas un export du catalogue : lancez le script fourni en jeu, puis collez tout le texte copié.'] + $empty;
        }
        if ((int) ($root[1] ?? 0) !== 1) {
            return ['ok' => false, 'error' => 'Version d’export inconnue : mettez à jour le script d’export.'] + $empty;
        }
        $items = is_array($root[4] ?? null) ? $root[4] : [];
        if (count($items) > self::MAX_ITEMS) {
            return ['ok' => false, 'error' => 'Trop d’objets dans l’export (30 000 au plus).'] + $empty;
        }
        $rows = [];
        $skipped = 0;
        foreach ($items as $item) {
            $row = is_array($item) ? self::row($item) : null;
            if ($row === null) {
                $skipped++;
                continue;
            }
            $rows[strtolower($row['class_name'])] = $row;
        }

        return [
            'ok' => true,
            'rows' => array_values($rows),
            'skipped' => $skipped,
            'version' => mb_substr(is_scalar($root[2] ?? null) ? (string) $root[2] : '', 0, 60),
            'mods' => is_array($root[3] ?? null) ? count($root[3]) : 0,
        ];
    }

    /**
     * @param array<int|string, mixed> $item
     * @return array<string, mixed>|null
     */
    public static function row(array $item): ?array
    {
        $class = is_string($item[0] ?? null) ? trim($item[0]) : '';
        $root = is_string($item[1] ?? null) ? strtolower($item[1]) : '';
        if (preg_match(self::CLASS_RE, $class) !== 1 || !in_array($root, self::ROOTS, true)) {
            return null;
        }
        $word = static fn (mixed $v): string => is_string($v) && preg_match('/^[A-Za-z]{0,32}$/', $v) === 1 ? $v : '';
        $typeMain = $word($item[2] ?? '');
        $typeSub = $word($item[3] ?? '');
        $slotType = self::int($item[7] ?? null);
        $compat = null;
        if (is_array($item[10] ?? null) && $item[10] !== []) {
            $compat = [];
            foreach (self::COMPAT_SLOTS as $i => $slot) {
                $compat[$slot] = self::classList($item[10][$i] ?? []);
            }
        }
        $mags = is_array($item[11] ?? null) && strtolower($typeMain) === 'weapon' ? self::classList($item[11]) : null;
        $slots = [];
        foreach (is_array($item[9] ?? null) ? $item[9] : [] as $s) {
            if (is_numeric($s) && (int) $s > 0 && (int) $s < 100000 && count($slots) < 8) {
                $slots[(int) $s] = (int) $s;
            }
        }
        $mass = is_numeric($item[5] ?? null) && (float) $item[5] >= 0 && (float) $item[5] < 1000000 ? round((float) $item[5], 3) : null;
        $maxLoad = self::int($item[6] ?? null);

        return [
            'class_name' => $class,
            'root' => $root,
            'type_main' => $typeMain,
            'type_sub' => $typeSub,
            'name' => self::text($item[4] ?? '', 160),
            'mass' => $mass,
            'max_load' => $maxLoad !== null && $maxLoad > 0 ? $maxLoad : null,
            'slot_type' => $slotType,
            'ammo_count' => ($a = self::int($item[8] ?? null)) !== null && $a > 0 ? $a : null,
            'allowed_slots' => array_values($slots),
            'compat' => $compat,
            'mags' => $mags,
            'mod_name' => self::text($item[12] ?? '', 160),
        ];
    }

    /**
     * @return list<string>
     */
    private static function classList(mixed $list): array
    {
        if (!is_array($list)) {
            return [];
        }
        $out = [];
        foreach ($list as $c) {
            if (is_string($c) && preg_match(self::CLASS_RE, $c) === 1) {
                $out[strtolower($c)] = $c;
                if (count($out) >= self::MAX_LIST) {
                    break;
                }
            }
        }

        return array_values($out);
    }

    private static function int(mixed $v): ?int
    {
        if (!is_numeric($v)) {
            return null;
        }
        $n = (int) round((float) $v);

        return $n >= 0 && $n < 2000000000 ? $n : null;
    }

    private static function text(mixed $v, int $max): string
    {
        if (!is_string($v)) {
            return '';
        }
        // Caractères de contrôle retirés ; le texte reste brut (échappé à l'affichage, jamais interprété).
        $v = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $v) ?? '';

        return mb_substr(trim($v), 0, $max);
    }
}
