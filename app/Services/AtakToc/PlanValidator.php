<?php

declare(strict_types=1);

namespace App\Services\AtakToc;

/**
 * Contrôle et nettoyage de tout ce qui entre dans un plan (éditeur, import JSON, import depuis le jeu) : un élément
 * invalide est refusé avec une raison, jamais enregistré à moitié. Coordonnées bornées au terrain (mètres Arma).
 */
final class PlanValidator
{
    public const MAX_ELEMENTS = 1500;
    public const MAX_POINTS = 600;
    public const MAX_PHASES = 24;
    public const MAX_LAYERS = 16;
    public const MAX_IMPORT_BYTES = 2_000_000;
    public const COORD_MIN = -2000.0;
    public const COORD_MAX = 60000.0;

    public const DASHES = ['solid', 'dash', 'dot'];
    public const ARROWS = ['none', 'end', 'both'];
    public const SIDES = ['WEST', 'EAST', 'GUER', 'CIV'];
    public const SHARING = ['private', 'unit', 'community'];

    public static function uid(mixed $v): ?string
    {
        $s = is_scalar($v) ? (string) $v : '';

        return preg_match('/^[A-Za-z0-9_-]{2,32}$/', $s) === 1 ? $s : null;
    }

    public static function text(mixed $v, int $max): string
    {
        $s = is_scalar($v) ? (string) $v : '';
        $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s) ?? '';

        return mb_substr(trim($s), 0, $max);
    }

    public static function color(mixed $v, string $default): string
    {
        $s = is_string($v) ? strtolower(trim($v)) : '';

        return preg_match('/^#[0-9a-f]{6}$/', $s) === 1 ? $s : $default;
    }

    /** @return list<array{0: float, 1: float}>|null */
    public static function points(mixed $pts): ?array
    {
        if (!is_array($pts) || $pts === [] || count($pts) > self::MAX_POINTS) {
            return null;
        }
        $out = [];
        foreach ($pts as $p) {
            if (!is_array($p) || !isset($p[0], $p[1]) || !is_numeric($p[0]) || !is_numeric($p[1])) {
                return null;
            }
            $x = (float) $p[0];
            $y = (float) $p[1];
            if (!is_finite($x) || !is_finite($y) || $x < self::COORD_MIN || $x > self::COORD_MAX || $y < self::COORD_MIN || $y > self::COORD_MAX) {
                return null;
            }
            $out[] = [round($x, 1), round($y, 1)];
        }

        return $out;
    }

    /**
     * Élément du plan, nettoyé. Retour : [élément|null, raison du refus].
     *
     * @param list<string> $phaseUids phases connues du plan (les autres sont retirées de l'élément)
     * @return array{0: array<string, mixed>|null, 1: string}
     */
    public static function element(mixed $raw, array $phaseUids = []): array
    {
        if (!is_array($raw)) {
            return [null, 'element_invalid'];
        }
        $uid = self::uid($raw['uid'] ?? null);
        if ($uid === null) {
            return [null, 'uid_invalid'];
        }
        $kinds = PlanCatalog::kinds();
        $kind = is_string($raw['kind'] ?? null) ? $raw['kind'] : '';
        if (!isset($kinds[$kind])) {
            return [null, 'kind_invalid'];
        }
        $def = $kinds[$kind];
        $geom = is_string($raw['geom'] ?? null) ? $raw['geom'] : $def['geoms'][0];
        if (!in_array($geom, $def['geoms'], true)) {
            return [null, 'geom_invalid'];
        }
        $pts = self::points($raw['pts'] ?? null);
        if ($pts === null) {
            return [null, 'points_invalid'];
        }
        $n = count($pts);
        $need = match ($geom) {
            'point', 'circle', 'ellipse' => $n === 1,
            'rect' => $n === 2,
            'line' => $n >= 2,
            'area' => $n >= 3,
            default => false,
        };
        if (!$need) {
            return [null, 'points_count'];
        }
        $p = is_array($raw['props'] ?? null) ? $raw['props'] : [];
        $props = [
            'color' => self::color($p['color'] ?? null, $def['color']),
            'width' => max(1, min(8, (int) ($p['width'] ?? 2))),
            'dash' => in_array($p['dash'] ?? null, self::DASHES, true) ? $p['dash'] : ($def['dash'] ?? 'solid'),
            'arrow' => in_array($p['arrow'] ?? null, self::ARROWS, true) ? $p['arrow'] : ($def['arrow'] ?? 'none'),
            'fill' => round(max(0.0, min(0.6, (float) ($p['fill'] ?? ($def['fill'] ?? 0.0)))), 2),
            'label' => self::text($p['label'] ?? ($def['text'] ?? ''), 60),
            'note' => self::text($p['note'] ?? '', 400),
            'size' => max(10, min(64, (int) ($p['size'] ?? 24))),
            'rotation' => fmod(max(-720.0, min(720.0, (float) ($p['rotation'] ?? 0))), 360.0),
        ];
        if (in_array($geom, ['circle', 'ellipse'], true)) {
            $props['radius'] = round(max(5.0, min(20000.0, (float) ($p['radius'] ?? 100))), 1);
            $props['rx'] = round(max(5.0, min(20000.0, (float) ($p['rx'] ?? $props['radius']))), 1);
            $props['ry'] = round(max(5.0, min(20000.0, (float) ($p['ry'] ?? $props['radius']))), 1);
        }
        if ($kind === 'unit') {
            $aff = strtoupper(is_string($p['affil'] ?? null) ? $p['affil'] : 'F');
            $props['affil'] = in_array($aff, PlanCatalog::AFFILIATIONS, true) ? $aff : 'F';
            $ech = $p['echelon'] ?? '';
            $props['echelon'] = is_string($ech) && in_array($ech, PlanCatalog::ECHELONS, true) ? $ech : '';
            $ut = $p['unitType'] ?? '';
            $props['unitType'] = is_string($ut) && isset(PlanCatalog::UNIT_TYPES[$ut]) ? $ut : 'infantry';
            $props['callsign'] = self::text($p['callsign'] ?? '', 40);
            $sidc = is_string($p['sidc'] ?? null) ? strtoupper($p['sidc']) : '';
            $props['sidc'] = preg_match('/^[A-Z0-9*-]{10,30}$/', $sidc) === 1 ? $sidc : '';
        }
        if ($kind === 'icon') {
            $props['icon'] = in_array($p['icon'] ?? '', PlanCatalog::ICONS, true) ? (string) $p['icon'] : 'flag';
        }
        if ($kind === 'text') {
            $props['label'] = self::text($p['label'] ?? '', 120);
            if ($props['label'] === '') {
                return [null, 'text_empty'];
            }
        }
        $phases = [];
        foreach (is_array($raw['phases'] ?? null) ? $raw['phases'] : [] as $ph) {
            $phu = self::uid($ph);
            if ($phu !== null && in_array($phu, $phaseUids, true) && !in_array($phu, $phases, true)) {
                $phases[] = $phu;
            }
        }

        return [[
            'uid' => $uid,
            'kind' => $kind,
            'geom' => $geom,
            'pts' => $pts,
            'props' => $props,
            'phases' => $phases,
            'layer' => self::uid($raw['layer'] ?? null) ?? '',
            'group' => self::uid($raw['group'] ?? null) ?? '',
            'z' => max(-100000, min(100000, (int) ($raw['z'] ?? 0))),
            'locked' => !empty($raw['locked']),
        ], ''];
    }

    /**
     * @return array{0: array<string, mixed>|null, 1: string}
     */
    public static function phase(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [null, 'phase_invalid'];
        }
        $uid = self::uid($raw['uid'] ?? null);
        $name = self::text($raw['name'] ?? '', 80);
        if ($uid === null || $name === '') {
            return [null, 'phase_invalid'];
        }

        return [[
            'uid' => $uid,
            'ord' => max(0, min(999, (int) ($raw['ord'] ?? 0))),
            'name' => $name,
            'note' => self::text($raw['note'] ?? '', 2000),
            'time_label' => self::text($raw['time_label'] ?? ($raw['time'] ?? ''), 40),
        ], ''];
    }

    /**
     * Réglages du plan gardés dans meta_json : calques, légende, ordre d'opération (SMEAC), vue.
     *
     * @return array<string, mixed>
     */
    public static function meta(mixed $raw): array
    {
        $raw = is_array($raw) ? $raw : [];
        $layers = [];
        foreach (array_slice(is_array($raw['layers'] ?? null) ? $raw['layers'] : [], 0, self::MAX_LAYERS) as $l) {
            if (!is_array($l)) {
                continue;
            }
            $uid = self::uid($l['uid'] ?? null);
            $name = self::text($l['name'] ?? '', 40);
            if ($uid !== null && $name !== '') {
                $layers[] = ['uid' => $uid, 'name' => $name, 'visible' => !array_key_exists('visible', $l) || !empty($l['visible']), 'locked' => !empty($l['locked'])];
            }
        }
        $notes = [];
        foreach (['situation', 'mission', 'execution', 'support', 'command', 'free'] as $k) {
            $notes[$k] = self::text(is_array($raw['notes'] ?? null) ? ($raw['notes'][$k] ?? '') : '', 6000);
        }
        $legend = [];
        foreach (array_slice(is_array($raw['legend'] ?? null) ? $raw['legend'] : [], 0, 30) as $row) {
            if (is_array($row)) {
                $label = self::text($row['label'] ?? '', 60);
                if ($label !== '') {
                    $legend[] = ['color' => self::color($row['color'] ?? null, '#f2f2f2'), 'label' => $label];
                }
            }
        }
        $view = is_array($raw['view'] ?? null) ? $raw['view'] : [];

        return [
            'layers' => $layers,
            'notes' => $notes,
            'legend' => $legend,
            'side' => in_array($raw['side'] ?? null, self::SIDES, true) ? $raw['side'] : 'WEST',
            'dtg' => self::text($raw['dtg'] ?? '', 40),
            'view' => [
                'x' => is_numeric($view['x'] ?? null) ? round(max(self::COORD_MIN, min(self::COORD_MAX, (float) $view['x'])), 1) : null,
                'y' => is_numeric($view['y'] ?? null) ? round(max(self::COORD_MIN, min(self::COORD_MAX, (float) $view['y'])), 1) : null,
                'z' => is_numeric($view['z'] ?? null) ? max(-4, min(12, (int) $view['z'])) : null,
            ],
        ];
    }

    /**
     * Fichier JSON importé (export du site, version 1) : plan complet nettoyé, ou raison du refus.
     *
     * @return array{0: array{title:string, world:string, meta:array<string,mixed>, phases:list<array<string,mixed>>, elements:list<array<string,mixed>>, rejected:int}|null, 1: string}
     */
    public static function importDocument(string $json): array
    {
        if (strlen($json) > self::MAX_IMPORT_BYTES) {
            return [null, 'import_too_large'];
        }
        $doc = json_decode($json, true, 32);
        if (!is_array($doc) || ($doc['format'] ?? '') !== 'athena-plan' || (int) ($doc['version'] ?? 0) !== 1) {
            return [null, 'import_format'];
        }
        $phases = [];
        foreach (array_slice(is_array($doc['phases'] ?? null) ? $doc['phases'] : [], 0, self::MAX_PHASES) as $raw) {
            [$ph] = self::phase($raw);
            if ($ph !== null) {
                $phases[$ph['uid']] = $ph;
            }
        }
        $phaseUids = array_keys($phases);
        $elements = [];
        $rejected = 0;
        $rawElements = is_array($doc['elements'] ?? null) ? $doc['elements'] : [];
        if (count($rawElements) > self::MAX_ELEMENTS) {
            return [null, 'import_too_many'];
        }
        foreach ($rawElements as $raw) {
            [$el] = self::element($raw, $phaseUids);
            if ($el === null) {
                $rejected++;
                continue;
            }
            $elements[$el['uid']] = $el;
        }

        return [[
            'title' => self::text($doc['title'] ?? 'Plan importé', 120) ?: 'Plan importé',
            'world' => self::world($doc['world'] ?? ''),
            'meta' => self::meta($doc['meta'] ?? []),
            'phases' => array_values($phases),
            'elements' => array_values($elements),
            'rejected' => $rejected,
        ], ''];
    }

    public static function world(mixed $v): string
    {
        $s = strtolower(is_string($v) ? trim($v) : '');

        return preg_match('/^[a-z0-9_-]{1,64}$/', $s) === 1 ? $s : '';
    }
}
