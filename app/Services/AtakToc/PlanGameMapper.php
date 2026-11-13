<?php

declare(strict_types=1);

namespace App\Services\AtakToc;

use App\Repositories\AtakWebCommandRepository;

/**
 * Passage d'un plan web vers l'app Plan du jeu (plan partagé du camp, COMSPEC_ATAK_Plan_<camp>) et retour.
 *
 * Aller (« Envoyer en jeu ») : chaque élément devient un élément de l'app Plan [id, type, points, texte, couleur,
 * pointillé, flèche, épaisseur, numéro], porté par une commande web « toc / plnadd » (fil WC1, 480 caractères au plus,
 * texte ASCII). Les formes sans équivalent sont converties : cercle, ellipse et rectangle en polygone, unité neutre ou
 * inconnue en note, FFA/RFA/champ de mines en zone titrée. Couleur : la plus proche des 9 couleurs du jeu.
 * Éditeur v2 : éventail de portée en zone (secteur en polygone), cercle de portée en zone (cercle extérieur), texte
 * avec renvoi en note au point d'ancrage ; un élément masqué ou une unité pas encore posée ne part jamais.
 * Retour (« Importer depuis le jeu ») : plan du camp remonté par les téléphones (atak_briefing_plans).
 * Réalisme : seul le plan dessiné part en jeu, jamais une position d'unité.
 */
final class PlanGameMapper
{
    /** Sommets au plus par élément envoyé (le fil WC1 tient en 480 caractères). */
    public const MAX_WIRE_POINTS = 28;

    /** Préfixe des identifiants d'éléments posés en jeu par un plan web : W<plan en base 36>_<n>. */
    public static function itemPrefix(int $planId): string
    {
        return 'W' . base_convert((string) max(1, $planId), 10, 36) . '_';
    }

    public static function nearestGameColor(string $hex): string
    {
        $hex = strtolower($hex);
        if (preg_match('/^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/', $hex, $m) !== 1) {
            return 'WHITE';
        }
        [$r, $g, $b] = [hexdec($m[1]), hexdec($m[2]), hexdec($m[3])];
        $best = 'WHITE';
        $bestD = PHP_INT_MAX;
        foreach (PlanCatalog::GAME_COLORS as $key => $h) {
            $d = (hexdec(substr($h, 1, 2)) - $r) ** 2 + (hexdec(substr($h, 3, 2)) - $g) ** 2 + (hexdec(substr($h, 5, 2)) - $b) ** 2;
            if ($d < $bestD) {
                $bestD = $d;
                $best = $key;
            }
        }

        return $best;
    }

    /** Texte du jeu : ASCII sûr (accents retirés), comme le fil WC1 l'exige. */
    public static function ascii(string $s, int $max): string
    {
        $map = [
            'à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a', 'ã' => 'a', 'ç' => 'c', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'î' => 'i', 'ï' => 'i', 'í' => 'i', 'ô' => 'o', 'ö' => 'o', 'ó' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ú' => 'u',
            'ÿ' => 'y', 'ñ' => 'n', 'œ' => 'oe', 'æ' => 'ae', 'À' => 'A', 'Â' => 'A', 'Ä' => 'A', 'Ç' => 'C', 'É' => 'E', 'È' => 'E',
            'Ê' => 'E', 'Ë' => 'E', 'Î' => 'I', 'Ï' => 'I', 'Ô' => 'O', 'Ö' => 'O', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U', 'Œ' => 'OE',
            'Æ' => 'AE', '’' => ' ', '\'' => ' ', '«' => '', '»' => '', '–' => '-', '—' => '-', '·' => '-', '/' => '-', '+' => ' ',
            '(' => '', ')' => '', '"' => '', ';' => ',', '!' => '', '?' => '', '#' => 'n', '&' => 'et', '%' => 'pct', '°' => '',
        ];
        $s = strtr($s, $map);
        $s = preg_replace('/[^A-Za-z0-9 .:,_@\-]/', '', $s) ?? '';
        $s = preg_replace('/\s+/', ' ', $s) ?? '';

        return substr(trim($s), 0, $max);
    }

    /**
     * Douglas-Peucker jusqu'à tenir en $max sommets (premier et dernier gardés).
     *
     * @param list<array{0: float, 1: float}> $pts
     * @return list<array{0: float, 1: float}>
     */
    public static function simplify(array $pts, int $max): array
    {
        if (count($pts) <= $max) {
            return $pts;
        }
        $tol = 1.0;
        $out = $pts;
        for ($i = 0; $i < 40 && count($out) > $max; $i++) {
            $out = self::dp($pts, $tol);
            $tol *= 1.6;
        }
        if (count($out) > $max) {
            $step = (int) ceil(count($out) / $max);
            $kept = [];
            foreach ($out as $k => $p) {
                if ($k % $step === 0) {
                    $kept[] = $p;
                }
            }
            if (end($kept) !== end($out)) {
                $kept[count($kept) - 1] = end($out);
            }
            $out = $kept;
        }

        return array_values($out);
    }

    /**
     * @param list<array{0: float, 1: float}> $pts
     * @return list<array{0: float, 1: float}>
     */
    private static function dp(array $pts, float $tol): array
    {
        $n = count($pts);
        if ($n < 3) {
            return $pts;
        }
        [$ax, $ay] = $pts[0];
        [$bx, $by] = $pts[$n - 1];
        $dx = $bx - $ax;
        $dy = $by - $ay;
        $len = sqrt($dx * $dx + $dy * $dy);
        $maxD = -1.0;
        $idx = 0;
        for ($i = 1; $i < $n - 1; $i++) {
            [$px, $py] = $pts[$i];
            $d = $len < 1e-9 ? sqrt(($px - $ax) ** 2 + ($py - $ay) ** 2) : abs($dy * $px - $dx * $py + $bx * $ay - $by * $ax) / $len;
            if ($d > $maxD) {
                $maxD = $d;
                $idx = $i;
            }
        }
        if ($maxD <= $tol) {
            return [$pts[0], $pts[$n - 1]];
        }
        $left = self::dp(array_slice($pts, 0, $idx + 1), $tol);
        $right = self::dp(array_slice($pts, $idx), $tol);

        return array_merge(array_slice($left, 0, -1), $right);
    }

    /**
     * Sommets d'une forme ronde ou rectangulaire, en polygone (le jeu n'a que des polygones).
     *
     * @param array<string, mixed> $el
     * @return list<array{0: float, 1: float}>
     */
    public static function outline(array $el, int $segments = 24): array
    {
        $pts = $el['pts'];
        $p = $el['props'];
        if (($el['kind'] ?? '') === 'fan') {
            return self::fanOutline($el, $segments);
        }
        switch ($el['geom']) {
            case 'circle':
            case 'ellipse':
                [$cx, $cy] = $pts[0];
                $rx = (float) ($el['geom'] === 'circle' ? $p['radius'] : $p['rx']);
                $ry = (float) ($el['geom'] === 'circle' ? $p['radius'] : $p['ry']);
                $rot = deg2rad((float) ($p['rotation'] ?? 0));
                $out = [];
                for ($i = 0; $i < $segments; $i++) {
                    $a = 2 * M_PI * $i / $segments;
                    $x = $rx * cos($a);
                    $y = $ry * sin($a);
                    $out[] = [round($cx + $x * cos($rot) - $y * sin($rot), 1), round($cy + $x * sin($rot) + $y * cos($rot), 1)];
                }

                return $out;
            case 'rect':
                [[$x1, $y1], [$x2, $y2]] = $pts;

                return [[$x1, $y1], [$x2, $y1], [$x2, $y2], [$x1, $y2]];
            default:
                return $pts;
        }
    }

    /**
     * Éventail de portée en polygone : centre puis arc (azimut 0 = nord, sens horaire) ; avec une portée minimale,
     * couronne de secteur (arc extérieur puis arc intérieur) ; sur 360°, le cercle extérieur.
     *
     * @param array<string, mixed> $el
     * @return list<array{0: float, 1: float}>
     */
    public static function fanOutline(array $el, int $segments = 24): array
    {
        [$cx, $cy] = $el['pts'][0];
        $p = $el['props'];
        $radius = max(1.0, (float) ($p['radius'] ?? 1000));
        $min = max(0.0, min($radius, (float) ($p['minRange'] ?? 0)));
        $arc = max(5.0, min(360.0, (float) ($p['arc'] ?? 60)));
        $bearing = (float) ($p['bearing'] ?? 0);
        $at = static fn (float $deg, float $r): array => [round($cx + $r * sin(deg2rad($deg)), 1), round($cy + $r * cos(deg2rad($deg)), 1)];
        $out = [];
        if ($arc >= 360.0) {
            for ($i = 0; $i < $segments; $i++) {
                $out[] = $at(360.0 * $i / $segments, $radius);
            }

            return $out;
        }
        $ring = $min > 0.0;
        $steps = max(2, min($ring ? intdiv($segments, 2) : $segments, (int) ceil($arc / 15)));
        $from = $bearing - $arc / 2;
        if (!$ring) {
            $out[] = [round((float) $cx, 1), round((float) $cy, 1)];
        }
        for ($i = 0; $i <= $steps; $i++) {
            $out[] = $at($from + $arc * $i / $steps, $radius);
        }
        if ($ring) {
            for ($i = $steps; $i >= 0; $i--) {
                $out[] = $at($from + $arc * $i / $steps, $min);
            }
        }

        return $out;
    }

    /**
     * Éléments du plan web => éléments de l'app Plan du jeu. Seuls les éléments de la phase choisie (ou tous).
     *
     * @param list<array<string, mixed>> $elements éléments validés (PlanValidator::element)
     * @return list<array{id:string, kind:string, pts:list<array{0:float,1:float}>, text:string, color:string, dashed:bool, arrow:bool, width:int, num:int, degraded:string}>
     */
    public static function toGameItems(int $planId, array $elements, ?string $phaseUid = null): array
    {
        $kinds = PlanCatalog::kinds();
        $prefix = self::itemPrefix($planId);
        $usort = $elements;
        usort($usort, static fn (array $a, array $b): int => ($a['z'] <=> $b['z']) ?: strcmp((string) $a['uid'], (string) $b['uid']));
        $out = [];
        $n = 0;
        foreach ($usort as $el) {
            if ($phaseUid !== null && $el['phases'] !== [] && !in_array($phaseUid, $el['phases'], true)) {
                continue;
            }
            $def = $kinds[$el['kind']] ?? null;
            if ($def === null || !PlanTimeline::placed($el)) {
                continue;
            }
            $p = $el['props'];
            $game = $def['game'];
            $degraded = '';
            $text = (string) ($p['label'] ?? '');
            if ($el['kind'] === 'unit') {
                $aff = (string) ($p['affil'] ?? 'F');
                $game = $aff === 'F' ? 'UNITF' : ($aff === 'H' ? 'UNITH' : 'NOTE');
                $text = trim((string) ($p['callsign'] ?? '') !== '' ? (string) $p['callsign'] : $text);
                if ($game === 'NOTE') {
                    $text = ($aff === 'N' ? 'NEUTRE ' : 'INCONNU ') . $text;
                    $degraded = 'unit_affil';
                }
            }
            if ($el['kind'] === 'fan') {
                $game = 'ZONE';
                $degraded = 'shape_polygon';
            }
            if ($game === 'ZONE' && in_array($el['kind'], ['ffa', 'rfa', 'minefield', 'bp', 'dz', 'cbrn', 'obstacle_belt'], true)) {
                $degraded = 'zone_title';
                if ($text === '') {
                    $text = (string) ($def['text'] ?? '');
                }
            }
            if ($el['kind'] === 'icon') {
                $degraded = 'icon_note';
            }
            $pts = self::outline($el);
            if ($el['kind'] === 'textbox') {
                // Texte avec renvoi : une note au point d'ancrage.
                $pts = [$pts[0]];
            }
            if (in_array($el['geom'], ['circle', 'ellipse', 'rect'], true)) {
                $degraded = $degraded !== '' ? $degraded : 'shape_polygon';
            }
            if (in_array($game, ['SBF', 'ABF'], true)) {
                $pts = [$pts[0], $pts[count($pts) - 1]];
            }
            $pts = self::simplify($pts, self::MAX_WIRE_POINTS);
            $pts = array_map(static fn (array $q): array => [round($q[0]), round($q[1])], $pts);
            $n++;
            $out[] = [
                'id' => $prefix . $n,
                'kind' => $game,
                'pts' => $pts,
                'text' => self::ascii($text, 40),
                'color' => self::nearestGameColor((string) $p['color']),
                'dashed' => ($p['dash'] ?? 'solid') !== 'solid',
                'arrow' => ($p['arrow'] ?? 'none') !== 'none',
                'width' => max(1, min(3, (int) round(((int) $p['width']) / 2))),
                'num' => 0,
                'degraded' => $degraded,
            ];
        }

        return $out;
    }

    /**
     * Arguments du fil WC1 d'un élément (commande « plnadd »), ou null si l'élément ne tient pas dans le fil.
     *
     * @param array{id:string, kind:string, pts:list<array{0:float,1:float}>, text:string, color:string, dashed:bool, arrow:bool, width:int, num:int} $item
     * @return array<string, scalar>|null
     */
    public static function wireArgs(array $item, string $side, int $planId): ?array
    {
        $args = [
            's' => $side,
            'pl' => base_convert((string) $planId, 10, 36),
            'k' => $item['kind'],
            'c' => $item['color'],
            'f' => ($item['dashed'] ? 'd' : '') . ($item['arrow'] ? 'a' : '') . 'w' . $item['width'],
        ];
        if ($item['text'] !== '') {
            $args['t'] = $item['text'];
        }
        $pts = $item['pts'];
        for ($attempt = 0; $attempt < 6; $attempt++) {
            $chunks = [];
            $cur = '';
            foreach ($pts as $q) {
                $tok = ((int) $q[0]) . ':' . ((int) $q[1]);
                if ($cur !== '' && strlen($cur) + 1 + strlen($tok) > 118) {
                    $chunks[] = $cur;
                    $cur = '';
                }
                $cur .= ($cur === '' ? '' : ',') . $tok;
            }
            if ($cur !== '') {
                $chunks[] = $cur;
            }
            $try = $args;
            foreach ($chunks as $i => $c) {
                $try['p' . $i] = $c;
            }
            if (strlen(AtakWebCommandRepository::buildWire('toc', '*', 'plnadd', $item['id'], $try)) <= 480) {
                return $try;
            }
            $pts = self::simplify($pts, max(2, (int) floor(count($pts) * 0.7)));
        }

        return null;
    }

    /**
     * Plan du camp remonté par le jeu (atak_briefing_plans.items) => éléments web.
     *
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    public static function fromGameItems(array $items, string $uidPrefix = 'g'): array
    {
        $out = [];
        $seen = [];
        foreach ($items as $i => $it) {
            if (!is_array($it)) {
                continue;
            }
            $gk = strtoupper((string) ($it['kind'] ?? ''));
            if (!isset(PlanCatalog::FROM_GAME[$gk])) {
                continue;
            }
            [$kind, $geom] = PlanCatalog::FROM_GAME[$gk];
            $pts = PlanValidator::points($it['pts'] ?? null);
            if ($pts === null) {
                continue;
            }
            if ($geom === 'point') {
                $pts = [$pts[0]];
            } elseif ($geom === 'line' && count($pts) < 2) {
                continue;
            } elseif ($geom === 'area' && count($pts) < 3) {
                if (count($pts) < 2) {
                    continue;
                }
                $geom = 'line';
                $kind = 'line';
            }
            $base = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($it['id'] ?? '')) ?: (string) $i;
            $uid = substr($uidPrefix . '_' . $base, 0, 32);
            while (isset($seen[$uid])) {
                $uid = substr($uidPrefix . '_' . $base, 0, 26) . '_' . $i . count($seen);
            }
            $seen[$uid] = true;
            $color = PlanCatalog::GAME_COLORS[strtoupper((string) ($it['color'] ?? 'WHITE'))] ?? '#f2f2f2';
            $raw = [
                'uid' => $uid,
                'kind' => $kind === 'line' && !empty($it['arrow']) ? 'arrow' : $kind,
                'geom' => $geom,
                'pts' => $pts,
                'props' => [
                    'color' => $color,
                    'dash' => !empty($it['dashed']) ? 'dash' : 'solid',
                    'arrow' => !empty($it['arrow']) ? 'end' : 'none',
                    'width' => max(1, min(3, (int) ($it['width'] ?? 2))) * 2,
                    'label' => (string) ($it['text'] ?? ''),
                    'note' => (string) ($it['desc'] ?? ''),
                    'affil' => $gk === 'UNITH' ? 'H' : 'F',
                    'callsign' => in_array($gk, ['UNITF', 'UNITH'], true) ? (string) ($it['text'] ?? '') : '',
                ],
            ];
            if ($raw['kind'] === 'text' && trim((string) $raw['props']['label']) === '') {
                $raw['props']['label'] = 'NOTE';
            }
            [$el] = PlanValidator::element($raw);
            if ($el !== null) {
                $out[] = $el;
            }
        }

        return $out;
    }
}
