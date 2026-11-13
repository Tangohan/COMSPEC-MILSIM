<?php

declare(strict_types=1);

namespace App\Services\GameEquipment\Configurator;

use App\Support\ArsenalLoadoutItems;

/**
 * Tenue complète au format getUnitLoadout d'Arma 3, lue strictement (charge utile envoyée par le configurateur,
 * collée par un membre ou relue en base) :
 *
 *   [primaire, lanceur, poing, uniforme, gilet, sac, casque, lunettes, jumelles, [carte, gps, radio, boussole, montre, vision nocturne]]
 *   arme       = [classe, bouche, rail latéral, optique, [chargeur, munitions] | [], [chargeur 2, munitions] | [], bipied] | []
 *   conteneur  = [classe, [contenu…]] | []
 *   contenu    = [objet, nombre] | [chargeur, nombre, munitions] | [[arme…], nombre] | [sac, true]
 *
 * Les emballages connus (export ACE [nom, tenue], [tenue, infos étendues], texte SQF) sont défaits par
 * ArsenalLoadoutItems::parse. Tout est borné : taille du texte, nombre de lignes par conteneur et au total, quantités ;
 * la profondeur l'est par le format (une arme rangée ne contient pas de conteneur, un sac rangé est vide).
 */
final class LoadoutDocument
{
    public const MAX_BYTES = 262144;

    public const MAX_LINES_PER_CONTAINER = 150;

    public const MAX_LINES_TOTAL = 400;

    public const MAX_COUNT = 999;

    public const MAX_WEAPON_COUNT = 20;

    public const MAX_AMMO = 100000;

    public const WEAPON_SLOTS = ['primary' => 0, 'secondary' => 1, 'handgun' => 2];

    public const CONTAINER_SLOTS = ['uniform' => 3, 'vest' => 4, 'backpack' => 5];

    public const ASSIGNED_SLOTS = ['map', 'gps', 'radio', 'compass', 'watch', 'nvg'];

    /**
     * @return array{ok: bool, loadout?: list<mixed>, error?: string}
     */
    public static function fromText(string $text): array
    {
        $text = trim($text);
        if ($text === '') {
            return ['ok' => false, 'error' => 'Collez le texte d’une tenue (JSON ou export ACE).'];
        }
        if (strlen($text) > self::MAX_BYTES) {
            return ['ok' => false, 'error' => 'Texte trop long pour une tenue (256 Ko au plus).'];
        }
        // Enveloppe du configurateur {"format":"comspec-loadout","loadout":[…]} acceptée en plus des formats du jeu.
        $obj = json_decode($text, true, 32);
        if (is_array($obj) && !array_is_list($obj) && is_array($obj['loadout'] ?? null)) {
            return self::normalize($obj['loadout']);
        }
        $parsed = ArsenalLoadoutItems::parse($text);
        if ($parsed === []) {
            return ['ok' => false, 'error' => 'Ce texte n’est pas une tenue lisible (getUnitLoadout, export ACE ou tenue copiée du configurateur).'];
        }

        return self::normalize($parsed);
    }

    /**
     * @return array{ok: bool, loadout?: list<mixed>, error?: string}
     */
    public static function normalize(mixed $raw): array
    {
        if (!is_array($raw) || !array_is_list($raw) || count($raw) < 9 || count($raw) > 10) {
            return ['ok' => false, 'error' => 'Une tenue a 10 emplacements (format getUnitLoadout).'];
        }
        $lines = 0;
        try {
            $out = [];
            foreach (self::WEAPON_SLOTS as $label => $i) {
                $out[$i] = self::weapon($raw[$i] ?? [], $label);
            }
            foreach (self::CONTAINER_SLOTS as $label => $i) {
                $out[$i] = self::container($raw[$i] ?? [], $label, $lines);
            }
            $out[6] = self::cls($raw[6] ?? '', 'casque');
            $out[7] = self::cls($raw[7] ?? '', 'lunettes');
            $out[8] = self::weapon($raw[8] ?? [], 'jumelles');
            $assigned = $raw[9] ?? [];
            if (!is_array($assigned) || count($assigned) > 6) {
                throw new \InvalidArgumentException('Objets assignés illisibles (carte, GPS, radio, boussole, montre, vision nocturne).');
            }
            $a = [];
            for ($k = 0; $k < 6; $k++) {
                $a[] = self::cls($assigned[$k] ?? '', 'objet assigné');
            }
            $out[9] = $a;
        } catch (\InvalidArgumentException $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
        ksort($out);

        return ['ok' => true, 'loadout' => array_values($out)];
    }

    /**
     * @return list<mixed>
     */
    private static function weapon(mixed $w, string $label): array
    {
        if ($w === [] || $w === '' || $w === null) {
            return [];
        }
        if (!is_array($w) || !array_is_list($w) || count($w) < 1 || count($w) > 7) {
            throw new \InvalidArgumentException('Arme illisible (' . $label . ').');
        }
        $class = self::cls($w[0] ?? '', $label);
        if ($class === '') {
            return [];
        }

        return [
            $class,
            self::cls($w[1] ?? '', 'bouche'),
            self::cls($w[2] ?? '', 'rail latéral'),
            self::cls($w[3] ?? '', 'optique'),
            self::mag($w[4] ?? []),
            self::mag($w[5] ?? []),
            self::cls($w[6] ?? '', 'bipied'),
        ];
    }

    /**
     * @return list<mixed>
     */
    private static function mag(mixed $m): array
    {
        if ($m === [] || $m === null || $m === '') {
            return [];
        }
        if (!is_array($m) || !array_is_list($m) || count($m) < 1 || count($m) > 2) {
            throw new \InvalidArgumentException('Chargeur d’arme illisible.');
        }
        $class = self::cls($m[0] ?? '', 'chargeur');
        if ($class === '') {
            return [];
        }

        return [$class, self::count($m[1] ?? 1, self::MAX_AMMO, 0)];
    }

    /**
     * @return list<mixed>
     */
    private static function container(mixed $c, string $label, int &$lines): array
    {
        if ($c === [] || $c === null || $c === '') {
            return [];
        }
        if (!is_array($c) || !array_is_list($c) || count($c) < 1 || count($c) > 2) {
            throw new \InvalidArgumentException('Conteneur illisible (' . $label . ').');
        }
        $class = self::cls($c[0] ?? '', $label);
        if ($class === '') {
            return [];
        }
        $content = $c[1] ?? [];
        if (!is_array($content) || !array_is_list($content)) {
            throw new \InvalidArgumentException('Contenu illisible (' . $label . ').');
        }
        if (count($content) > self::MAX_LINES_PER_CONTAINER) {
            throw new \InvalidArgumentException('Trop de lignes dans ' . $label . ' (' . self::MAX_LINES_PER_CONTAINER . ' au plus).');
        }
        $out = [];
        foreach ($content as $entry) {
            if (++$lines > self::MAX_LINES_TOTAL) {
                throw new \InvalidArgumentException('Tenue trop chargée (' . self::MAX_LINES_TOTAL . ' lignes au plus).');
            }
            $out[] = self::entry($entry, $label);
        }

        return [$class, $out];
    }

    /**
     * @return list<mixed>
     */
    private static function entry(mixed $e, string $label): array
    {
        if (!is_array($e) || !array_is_list($e) || count($e) < 2 || count($e) > 3) {
            throw new \InvalidArgumentException('Ligne illisible dans ' . $label . '.');
        }
        if (is_array($e[0])) {
            $weapon = self::weapon($e[0], 'arme rangée');
            if ($weapon === [] || count($e) !== 2) {
                throw new \InvalidArgumentException('Arme rangée illisible dans ' . $label . '.');
            }

            return [$weapon, self::count($e[1], self::MAX_WEAPON_COUNT, 1)];
        }
        $class = self::cls($e[0], $label);
        if ($class === '') {
            throw new \InvalidArgumentException('Ligne sans objet dans ' . $label . '.');
        }
        if (is_bool($e[1])) {
            // Sac rangé : [classe, true] (vide, comme getUnitLoadout le rend).
            return [$class, true];
        }
        if (count($e) === 3) {
            return [$class, self::count($e[1], self::MAX_COUNT, 1), self::count($e[2], self::MAX_AMMO, 0)];
        }

        return [$class, self::count($e[1], self::MAX_COUNT, 1)];
    }

    private static function cls(mixed $v, string $label): string
    {
        if ($v === null || $v === '') {
            return '';
        }
        if (!is_string($v) || preg_match(ItemCatalogImport::CLASS_RE, $v) !== 1) {
            throw new \InvalidArgumentException('Nom de classe invalide (' . $label . ').');
        }

        return $v;
    }

    private static function count(mixed $v, int $max, int $min): int
    {
        if (!is_numeric($v)) {
            throw new \InvalidArgumentException('Quantité illisible.');
        }
        $n = (int) $v;
        if ($n < $min || $n > $max) {
            throw new \InvalidArgumentException('Quantité hors limites (' . $min . ' à ' . $max . ').');
        }

        return $n;
    }

    /**
     * Toutes les classes de la tenue (armes, accessoires, chargeurs, conteneurs et contenu, objets assignés).
     *
     * @param list<mixed> $lo tenue normalisée
     * @return list<string>
     */
    public static function classes(array $lo): array
    {
        $out = [];
        $add = static function (mixed $c) use (&$out): void {
            if (is_string($c) && $c !== '') {
                $out[strtolower($c)] = $c;
            }
        };
        $weapon = static function (array $w) use ($add): void {
            if ($w === []) {
                return;
            }
            foreach ([0, 1, 2, 3, 6] as $i) {
                $add($w[$i] ?? '');
            }
            foreach ([4, 5] as $i) {
                if (is_array($w[$i] ?? null) && $w[$i] !== []) {
                    $add($w[$i][0]);
                }
            }
        };
        foreach ([0, 1, 2, 8] as $i) {
            $weapon(is_array($lo[$i] ?? null) ? $lo[$i] : []);
        }
        foreach ([3, 4, 5] as $i) {
            $c = is_array($lo[$i] ?? null) ? $lo[$i] : [];
            if ($c === []) {
                continue;
            }
            $add($c[0]);
            foreach ($c[1] as $e) {
                is_array($e[0]) ? $weapon($e[0]) : $add($e[0]);
            }
        }
        $add($lo[6] ?? '');
        $add($lo[7] ?? '');
        foreach (is_array($lo[9] ?? null) ? $lo[9] : [] as $c) {
            $add($c);
        }

        return array_values($out);
    }

    /** Texte JSON compact de la tenue (format arma_loadout_str des tenues d'arsenal, lisible par parseSimpleArray). */
    public static function toJson(array $lo): string
    {
        return (string) json_encode($lo, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /** Tenue vide (10 emplacements). */
    public static function blank(): array
    {
        return [[], [], [], [], [], [], '', '', [], ['', '', '', '', '', '']];
    }
}
