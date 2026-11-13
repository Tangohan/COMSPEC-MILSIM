<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Fenêtre « IA de la zone » de l'éditeur SSE (addons/editor/dialogs/aizone.hpp, coach.hpp, fn_editorAi.sqf), refaite
 * après « Incompréhensible » (capture du 11 novembre : modale opaque au milieu, textes minuscules, listes coupées, case
 * beige vide). Vérifié sans le jeu :
 *  - chaque contrôle a son texte et son info-bulle en français et en anglais (stringtable de l'addon) ;
 *  - aucune taille de texte sous AZ_TXT_MIN (0.78 case, SSE_TXT_S de la charte) ;
 *  - chaque libellé tient dans sa case, en français et en anglais (largeur estimée caractère par caractère, police
 *    RobotoCondensed, 6 % de marge ; les aides sur plusieurs lignes tiennent en hauteur) ;
 *  - aucun rectangle visible n'en chevauche un autre (page + un onglet, volets à part), tout dans la grille 40 x 25 ;
 *  - la visite guidée est ancrée (pas de voile), notée une fois par profil, rouverte par « ? » ;
 *  - aucune alerte Arma (toast, hint, systemChat…) dans la fenêtre.
 * En jeu : étapes sse_ia_zone et sse_ia_zone_etats de la mission d'autotest (mêmes règles, mesurées par le moteur).
 *
 * « Bug » (SSE 1.9.0, capture du 11 novembre, 13 h 44) : tous les volets ouverts en même temps, par-dessus la liste et les
 * onglets, textes des quatre onglets empilés. RPT : « "Filters" call _L » sur un contrôle (fn_editorAi, _render : un
 * « private _l » local écrasait l'aide de traduction _L, la casse ne compte pas en SQF) et « forEach _idcs » sur un booléen
 * (_layout : « private _empty » écrasait la liste _EMPTY). La construction s'arrêtait avant de cacher quoi que ce soit.
 * Depuis : registre des contrôles (_OWN, un propriétaire par contrôle, _showOnly), volets fermés au départ et un seul à la
 * fois, et vérification statique : aucun nom local ne reprend (casse ignorée) un nom du premier niveau du fichier.
 */
final class SseAiZoneWindowAssetTest extends TestCase
{
    private const DIR = '/mod/@COMSPEC_SSE/addons/editor';

    /** Classes de base de la page et leur taille de texte (cases), police grasse ou non. */
    private const BASE = [
        'COMSPEC_SSE_EdText' => [0.85, false], 'COMSPEC_SSE_EdFill' => [0.85, false], 'COMSPEC_SSE_EdRule' => [0.85, false],
        'COMSPEC_SSE_AzText' => [0.85, false], 'COMSPEC_SSE_AzMuted' => [0.85, false], 'COMSPEC_SSE_AzHeading' => [1.0, true],
        'COMSPEC_SSE_AzHelp' => [0.78, false], 'COMSPEC_SSE_AzButton' => [0.85, false], 'COMSPEC_SSE_AzPrimary' => [0.85, true],
        'COMSPEC_SSE_AzTab' => [0.8, false], 'COMSPEC_SSE_AzColHead' => [0.78, false], 'COMSPEC_SSE_AzColText' => [0.78, false],
        'COMSPEC_SSE_AzCombo' => [0.85, false], 'COMSPEC_SSE_AzEdit' => [0.85, false], 'COMSPEC_SSE_AzEditClear' => [0.85, false],
        'COMSPEC_SSE_AzList' => [0.8, false], 'COMSPEC_SSE_AzPopup' => [0.85, false], 'COMSPEC_SSE_AzAccent' => [0.85, false],
        'COMSPEC_SSE_EdPicture' => [0.85, false], 'COMSPEC_SSE_RscControlsGroup' => [0.85, false], 'RscStructuredText' => [0.85, false],
        'COMSPEC_SSE_AzScrim' => [0.85, false],
    ];

    /** Posés par-dessus d'autres à dessein (fonds, invites sous un champ, icônes d'en-tête, repère d'onglet, groupes, voile). */
    private const UNDER = [95000, 95007, 95008, 95013, 95015, 95018, 95036, 95037, 95113, 95114, 95115, 95116, 95209, 95502, 95503, 95504, 95507, 95600, 95700, 95701, 95800, 95801, 95850];

    /** Fonctions de la fenêtre vérifiées pour les noms de variables (casse ignorée en SQF). */
    private const SQF = ['/functions/fn_editorAi.sqf', '/functions/fn_editor.sqf', '/functions/fn_editorAiCard.sqf', '/functions/fn_editorCoach.sqf'];

    private static function root(): string
    {
        return dirname(__DIR__, 2);
    }

    private static function read(string $rel): string
    {
        $s = file_get_contents(self::root() . self::DIR . $rel);
        self::assertIsString($s, $rel);
        return str_replace("\r\n", "\n", $s);
    }

    /** @return array<string, array{en: string, fr: string}> */
    private static function strings(): array
    {
        $xml = simplexml_load_string(self::read('/stringtable.xml'));
        self::assertNotFalse($xml, 'stringtable.xml invalide');
        $out = [];
        foreach ($xml->xpath('//Key') ?: [] as $k) {
            $out[(string) $k['ID']] = ['en' => trim((string) $k->English), 'fr' => trim((string) $k->French)];
        }
        return $out;
    }

    /**
     * Contrôles d'un fichier de dialogue : classe, base, idc, rectangle (cases), propriétés.
     * @return list<array{name: string, base: string, idc: int, pos: array{float, float, float, float}|null, body: string}>
     */
    private static function controls(string $rel): array
    {
        $src = self::read($rel);
        $out = [];
        if (!preg_match_all('/^\s*class (\w+): (\w+) \{/m', $src, $m, PREG_OFFSET_CAPTURE)) {
            return $out;
        }
        foreach ($m[0] as $i => $hit) {
            $start = $hit[1] + strlen($hit[0]);
            $depth = 1;
            $j = $start;
            while ($depth > 0 && $j < strlen($src)) {
                $c = $src[$j];
                if ($c === '{') {
                    $depth++;
                } elseif ($c === '}') {
                    $depth--;
                }
                $j++;
            }
            $body = substr($src, $start, $j - $start - 1);
            $idc = preg_match('/\bidc = (\d+);/', $body, $mi) ? (int) $mi[1] : -1;
            $pos = preg_match('/ED_POS\(([-\d.]+),([-\d.]+),([-\d.]+),([-\d.]+)\)/', $body, $mp) ? [(float) $mp[1], (float) $mp[2], (float) $mp[3], (float) $mp[4]] : null;
            $out[] = ['name' => $m[1][$i][0], 'base' => $m[2][$i][0], 'idc' => $idc, 'pos' => $pos, 'body' => $body];
        }
        return $out;
    }

    /** Largeur d'un texte en em (RobotoCondensed), caractère par caractère. */
    private static function em(string $s, bool $bold = false): float
    {
        $w = 0.0;
        foreach (preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $ch) {
            $w += match (true) {
                $ch === ' ' => 0.24,
                str_contains("ijl'.,:;!|·", $ch) => 0.23,
                str_contains('ftrI()[]/-', $ch) => 0.31,
                str_contains('mw', $ch) => 0.79,
                str_contains('MW', $ch) => 0.82,
                str_contains('sczxykvJ', $ch) => 0.46,
                (bool) preg_match('/\p{Lu}/u', $ch) => 0.6,
                str_contains('«»…', $ch) => 0.5,
                $ch === '●' => 0.62,
                default => 0.5,
            };
        }
        return $w * ($bold ? 1.06 : 1.0);
    }

    private static function sizeOf(array $ctl): array
    {
        [$size, $bold] = self::BASE[$ctl['base']] ?? [0.85, false];
        if (preg_match('/ED_TXT\(([\w.]+)\)/', $ctl['body'], $m)) {
            $size = self::num($m[1]);
        }
        if (preg_match('/size = SSE_Q\(SSE_GH \* ([\w.]+)\)/', $ctl['body'], $m)) {
            $size = self::num($m[1]);
        }
        if (str_contains($ctl['body'], 'RobotoCondensedBold')) {
            $bold = true;
        }
        return [$size, $bold];
    }

    private static function num(string $v): float
    {
        return match ($v) {
            'AZ_TXT_MIN' => 0.78, 'AZ_TXT_BODY' => 0.85, 'AZ_TXT_HEAD' => 1.0,
            default => (float) $v,
        };
    }

    /** Une ligne : le texte tient dans la largeur (6 % de marge). */
    private static function fitsLine(string $t, float $w, float $size, bool $bold): bool
    {
        return self::em($t, $bold) * $size <= $w * 0.94;
    }

    /** Plusieurs lignes (texte structuré) : retour à la ligne par mots, la hauteur tient. */
    private static function fitsBlock(string $t, float $w, float $h, float $size): bool
    {
        $lines = 1;
        $cur = 0.0;
        foreach (preg_split('/\s+/u', trim($t)) ?: [] as $word) {
            $ww = self::em($word) * $size;
            $sp = self::em(' ') * $size;
            if ($cur > 0 && $cur + $sp + $ww > $w * 0.94) {
                $lines++;
                $cur = $ww;
            } else {
                $cur += ($cur > 0 ? $sp : 0) + $ww;
            }
        }
        return $lines * $size * 1.05 <= $h;
    }

    public function testEveryControlHasACaptionOrTooltipInFrenchAndEnglish(): void
    {
        $str = self::strings();
        $missing = [];
        foreach (['/dialogs/aizone.hpp', '/dialogs/coach.hpp'] as $f) {
            foreach (self::controls($f) as $c) {
                // Le voile sous un volet n'a ni texte ni info-bulle (elle s'afficherait partout autour du volet).
                $interactive = (bool) preg_match('/\b(action|onLBSelChanged|onKeyUp) =/', $c['body']) && $c['base'] !== 'COMSPEC_SSE_AzScrim';
                $keys = [];
                preg_match_all('/"\$(STR_COMSPEC_SSE_\w+)"/', $c['body'], $mk);
                $keys = $mk[1];
                if ($interactive && $keys === []) {
                    $missing[] = $c['name'] . ' : ni texte ni info-bulle';
                }
                foreach ($keys as $k) {
                    if (($str[$k]['fr'] ?? '') === '' || ($str[$k]['en'] ?? '') === '') {
                        $missing[] = $c['name'] . ' : ' . $k . ' sans français ou anglais';
                    }
                }
            }
        }
        // Textes posés par le contrôleur : clé AZ, sinon l'ancienne clé ED (gardée pour les autres appelants).
        $sqf = self::read('/functions/fn_editorAi.sqf');
        preg_match_all('/"(\w+)" call _loc\b/', $sqf, $ml);
        self::assertGreaterThan(50, count(array_unique($ml[1])), 'aide de traduction _loc introuvable');
        foreach (array_unique($ml[1]) as $k) {
            $az = $str['STR_COMSPEC_SSE_AZ_' . $k] ?? null;
            $ed = $str['STR_COMSPEC_SSE_ED_' . $k] ?? null;
            $e = $az ?? $ed;
            if ($e === null || $e['fr'] === '' || $e['en'] === '') {
                $missing[] = 'fn_editorAi : clé ' . $k . ' absente ou incomplète';
            }
        }
        // Familles de valeurs (préfixe + code) affichées dans les listes et les aides.
        foreach ([['KindM_', ['civil', 'cover', 'forged']], ['BehM_', ['auto', 'coopere', 'nerveux', 'ment', 'refuse', 'fuit', 'agressif', 'rendu']],
            ['SideM_', ['auto', 'civ', 'west', 'east', 'guer']], ['Badge_', ['civil', 'generated', 'cover', 'forged', 'alias']],
            ['State_', ['athena', 'generated', 'pending', 'kept']], ['Face_', ['recto', 'verso']]] as [$pre, $codes]) {
            foreach ($codes as $code) {
                $e = $str['STR_COMSPEC_SSE_AZ_' . $pre . $code] ?? null;
                if ($e === null || $e['fr'] === '' || $e['en'] === '') {
                    $missing[] = 'clé AZ_' . $pre . $code . ' absente ou incomplète';
                }
            }
        }
        self::assertSame([], $missing, implode("\n", $missing));
    }

    public function testNoTextSizeBelowTheMinimum(): void
    {
        $styles = self::read('/dialogs/aizone_styles.hpp');
        self::assertMatchesRegularExpression('/#define AZ_TXT_MIN\s+0\.78\b/', $styles, 'taille minimale : SSE_TXT_S de la charte (0.78 case)');
        $low = [];
        foreach (['/dialogs/aizone_styles.hpp', '/dialogs/aizone.hpp', '/dialogs/coach.hpp'] as $f) {
            $src = self::read($f);
            preg_match_all('/(?:ED_TXT\(|SSE_GH \* )([\w.]+)\)/', $src, $m);
            foreach ($m[1] as $v) {
                if (self::num($v) < 0.78) {
                    $low[] = $f . ' : ' . $v;
                }
            }
        }
        // Contrôleur : réduction d'un libellé jamais sous 0.78 case.
        self::assertStringContainsString('private _minH = _gh * 0.78;', self::read('/functions/fn_editorAi.sqf'));
        self::assertStringContainsString('(_gh * 0.78)', self::read('/functions/fn_editorCoach.sqf'));
        self::assertSame([], $low, "Texte sous 0.78 case :\n" . implode("\n", $low));
    }

    public function testEveryLabelFitsItsBoxInFrenchAndEnglish(): void
    {
        $str = self::strings();
        $bad = [];
        $byIdc = [];
        foreach (['/dialogs/aizone.hpp', '/dialogs/coach.hpp'] as $f) {
            foreach (self::controls($f) as $c) {
                $byIdc[$c['idc']] = $c;
                if ($c['pos'] === null || !preg_match('/\btext = "\$(STR_COMSPEC_SSE_\w+)"/', $c['body'], $mk)) {
                    continue;
                }
                [$size, $bold] = self::sizeOf($c);
                [, , $w, $h] = $c['pos'];
                $block = $c['base'] === 'COMSPEC_SSE_AzHelp' || $c['base'] === 'RscStructuredText';
                foreach (['fr', 'en'] as $lang) {
                    $t = $str[$mk[1]][$lang] ?? '';
                    $ok = $block ? self::fitsBlock($t, $w, $h, $size) : self::fitsLine($t, $w, $size, $bold);
                    if (!$ok) {
                        $bad[] = sprintf('%s (%s) : « %s » ne tient pas dans %.2f x %.2f cases', $c['name'], $lang, $t, $w, $h);
                    }
                }
            }
        }
        // Textes posés par le contrôleur dans une case donnée ; %1 remplacé par un cas long (60 IA, PRF-0012).
        $dyn = [
            [95025, 'Checked', '60'], [95002, 'Count', '60'], [95010, 'FiltersN', '3'], [95211, 'MakeTrue', ''], [95211, 'MakeFalse', ''],
            [95030, 'SheetNone', ''], [95031, 'SheetNoneSub', ''], [95030, 'SheetMulti', '60'], [95031, 'SheetMultiSub', '60'], [95109, 'Fold', '60', '▸ '],
            [95019, 'Waiting', ''], [95019, 'EmptyTitle', ''], [95019, 'EmptyFilterTitle', ''], [95020, 'EmptyText', ''], [95020, 'EmptyFilterText', ''],
            [95604, 'Apply', '', '● ', ' (60)'], [95501, 'PvCaptionNone', ''], [95504, 'PvNoAi', ''], [95504, 'PvNoDoc', ''], [95504, 'PvLoading', ''],
            [95209, 'DocsNone', ''], [95102, 'SrcM_random', ''], [95102, 'SrcM_profile', 'PRF-0012'], [95110, 'ProfNone', ''], [95110, 'ProfStatus', '60', '', '', '10/11 20:30'],
            [95908, 'CoNext', ''], [95908, 'CoDone', ''], [95601, 'Dirty', ''], [95601, 'Applied', '60'], [95601, 'KeptAthenaKo', ''], [95601, 'NeedCheck', ''],
            [95601, 'NoZeusSel', ''], [95601, 'Refused', ''], [95601, 'PickDoc', ''], [95601, 'Refreshing', ''], [95601, 'RefreshDone', '60'],
            [95601, 'RefreshLate', ''],
        ];
        foreach (['KindM_civil', 'KindM_cover', 'KindM_forged'] as $k) {
            $dyn[] = [95105, $k, ''];
        }
        foreach (['auto', 'coopere', 'nerveux', 'ment', 'refuse', 'fuit', 'agressif', 'rendu'] as $k) {
            $dyn[] = [95302, 'BehM_' . $k, ''];
        }
        foreach (['auto', 'civ', 'west', 'east', 'guer'] as $k) {
            $dyn[] = [95402, 'SideM_' . $k, 'Indépendants'];
        }
        foreach ($dyn as $row) {
            [$idc, $key, $arg] = $row;
            $pre = $row[3] ?? '';
            $post = $row[4] ?? '';
            $arg2 = $row[5] ?? '';
            $c = $byIdc[$idc] ?? null;
            self::assertNotNull($c, 'contrôle ' . $idc);
            [$size, $bold] = self::sizeOf($c);
            [, , $w, $h] = $c['pos'];
            $e = $str['STR_COMSPEC_SSE_AZ_' . $key] ?? $str['STR_COMSPEC_SSE_ED_' . $key] ?? null;
            self::assertNotNull($e, $key);
            $block = in_array($c['base'], ['COMSPEC_SSE_AzHelp', 'RscStructuredText'], true);
            foreach (['fr', 'en'] as $lang) {
                $t = $pre . str_replace(['%1', '%2'], [$arg, $arg2], $e[$lang]) . $post;
                $ok = $block ? self::fitsBlock($t, $w, $h, $size) : self::fitsLine($t, $w, $size, $bold);
                if (!$ok) {
                    $bad[] = sprintf('%d %s (%s) : « %s » ne tient pas dans %.2f x %.2f cases', $idc, $key, $lang, $t, $w, $h);
                }
            }
        }
        self::assertSame([], $bad, implode("\n", $bad));
    }

    public function testDropDownsAreWideEnoughForTheirLabels(): void
    {
        $str = self::strings();
        $byIdc = [];
        foreach (self::controls('/dialogs/aizone.hpp') as $c) {
            $byIdc[$c['idc']] = $c;
        }
        // Valeurs fixes de chaque liste (les profils Athena, pays et types d'extension sont raccourcis en jeu : info-bulle).
        $lists = [
            95005 => ['25 m', '500 m'],
            95101 => ['@AZ_SrcRandom'],
            95104 => ['@ED_Kind_civil', '@ED_Kind_cover', '@ED_Kind_forged'],
            95107 => ['@AZ_CtryAuto', 'ALT · République d\'Altis'],
            95201 => ['@ED_Papers_auto', '@ED_Papers_none', '@ED_Papers_card', '@ED_Papers_passport', '@ED_Papers_license', '@ED_Papers_card_license', '@ED_Papers_card_passport', '@ED_Papers_military'],
            95212 => ['@ED_Doc_id_card', '@ED_Doc_passport', '@ED_Doc_driving_license', '@ED_Doc_military_id'],
            95301 => ['@ED_Beh_auto', '@ED_Beh_coopere', '@ED_Beh_nerveux', '@ED_Beh_ment', '@ED_Beh_refuse', '@ED_Beh_fuit', '@ED_Beh_agressif', '@ED_Beh_rendu'],
            95401 => ['@ED_Side_auto', '@ED_Side_civ', '@ED_Side_west', '@ED_Side_east', '@ED_Side_guer'],
            95703 => ['@ED_FSide_All', '@ED_Side_civ', '@ED_Side_guer'],
            95705 => ['@ED_FRole_All', '@ED_Role_civilian', '@ED_Role_military', '@ED_Role_suspect'],
            95707 => ['@ED_FState_All', '@AZ_State_athena', '@AZ_State_generated', '@AZ_State_pending', '@AZ_State_kept'],
        ];
        $bad = [];
        foreach ($lists as $idc => $labels) {
            $c = $byIdc[$idc];
            [$size] = self::sizeOf($c);
            [, , $w, $h] = $c['pos'];
            // Flèche carrée : sa largeur est la hauteur de la case.
            $room = $w - $h;
            foreach ($labels as $l) {
                foreach (['fr', 'en'] as $lang) {
                    $t = str_starts_with($l, '@') ? ($str['STR_COMSPEC_SSE_' . substr($l, 1)][$lang] ?? '?') : $l;
                    if (!self::fitsLine($t, $room, $size, false)) {
                        $bad[] = sprintf('%d (%s) : « %s » coupé (%.2f cases utiles)', $idc, $lang, $t, $room);
                    }
                }
            }
        }
        self::assertSame([], $bad, implode("\n", $bad));
    }

    public function testNoVisibleRectanglesOverlapAndAllStayInTheGrid(): void
    {
        $all = self::controls('/dialogs/aizone.hpp');
        $bad = [];
        foreach ($all as $c) {
            if ($c['pos'] === null) {
                continue;
            }
            [$x, $y, $w, $h] = $c['pos'];
            if ($x < 0 || $y < 0 || $x + $w > 40.001 || $y + $h > 25.001) {
                $bad[] = $c['name'] . ' hors de la grille 40 x 25';
            }
        }
        // Chaque état affichable (registre de fn_editorAi) : la page (un onglet, liste remplie ou vide), puis chaque volet à
        // part (couche posée au-dessus, sur son voile).
        $byIdc = [];
        foreach ($all as $c) {
            $byIdc[$c['idc']] = $c;
        }
        $own = self::owners();
        foreach (self::states() as $label => $layers) {
            foreach ($layers as $layer => $owners) {
                $set = [];
                foreach ($owners as $o) {
                    foreach ($own[$o] ?? [] as $idc) {
                        if (isset($byIdc[$idc]) && $byIdc[$idc]['pos'] !== null && !in_array($idc, self::UNDER, true)) {
                            $set[] = $byIdc[$idc];
                        }
                    }
                }
                foreach (self::overlaps($set) as [$a, $b]) {
                    $bad[] = sprintf('%s (%s) : %s chevauche %s', $label, $layer, $a, $b);
                }
            }
        }
        self::assertSame([], $bad, implode("\n", $bad));
    }

    /** @return array<string, list<int>> registre _OWN de fn_editorAi (propriétaire => idc). */
    private static function owners(): array
    {
        $sqf = self::read('/functions/fn_editorAi.sqf');
        self::assertSame(1, preg_match('/^private _OWN = createHashMapFromArray \[\n(.*?)\n\];/ms', $sqf, $m), 'registre _OWN introuvable');
        preg_match_all('/\["(\w+)", \[([\d, ]+)\]\]/', $m[1], $mm, PREG_SET_ORDER);
        $out = [];
        foreach ($mm as $row) {
            self::assertArrayNotHasKey($row[1], $out, 'propriétaire en double : ' . $row[1]);
            $out[$row[1]] = array_map('intval', array_map('trim', explode(',', $row[2])));
        }
        return $out;
    }

    /** @return array<string, array<string, list<string>>> états affichables : couche page et couche volet. */
    private static function states(): array
    {
        $out = [];
        foreach (['id', 'docs', 'beh', 'side'] as $tab) {
            foreach (['listRows' => 'liste remplie', 'listEmpty' => 'liste vide'] as $list => $what) {
                $page = ['frame', 'list', $list, 'mid', 'tab_' . $tab, 'pv', 'foot'];
                foreach (['' => 'aucun volet', 'ath' => 'volet Athena', 'filt' => 'volet Filtres', 'mem' => 'volet Mémoire'] as $v => $vl) {
                    $layers = ['page' => $page];
                    if ($v !== '') {
                        $layers['volet'] = ['scrim', 'volet_' . $v];
                    }
                    $out[sprintf('onglet %s, %s, %s', $tab, $what, $vl)] = $layers;
                }
            }
        }
        return $out;
    }

    /**
     * @param list<array{name: string, pos: array{float, float, float, float}|null}> $set
     * @return list<array{string, string}>
     */
    private static function overlaps(array $set): array
    {
        $out = [];
        $n = count($set);
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                [$x1, $y1, $w1, $h1] = $set[$i]['pos'];
                [$x2, $y2, $w2, $h2] = $set[$j]['pos'];
                $e = 0.02;
                if ($x1 + $w1 - $e > $x2 && $x2 + $w2 - $e > $x1 && $y1 + $h1 - $e > $y2 && $y2 + $h2 - $e > $y1) {
                    $out[] = [$set[$i]['name'], $set[$j]['name']];
                }
            }
        }
        return $out;
    }

    /**
     * Noms de variables d'un fichier SQF : en SQF la casse ne compte pas (_l et _L, _empty et _EMPTY sont la même variable).
     * Un nom déclaré au premier niveau du fichier (aide, constante, registre) ne doit être ni redéclaré à ce niveau, ni repris
     * par un « private », un « params [...] » ou un « for "_i" » plus bas (dans une aide, une boucle, un cas du switch) :
     * sinon l'aide est écrasée pour tout ce qui est appelé depuis là (« Bug » de la 1.9.0).
     * @return list<string>
     */
    private static function shadowedNames(string $src): array
    {
        $decl = [];
        $inComment = false;
        foreach (explode("\n", $src) as $n => $line) {
            $code = $line;
            if ($inComment) {
                $end = strpos($code, '*/');
                if ($end === false) {
                    continue;
                }
                $code = substr($code, $end + 2);
                $inComment = false;
            }
            if (($p = strpos($code, '/*')) !== false && strpos($code, '*/', $p) === false) {
                $inComment = true;
                $code = substr($code, 0, $p);
            }
            $code = (string) preg_replace('~//.*$~', '', $code);
            $names = [];
            preg_match_all('/\bprivate\s+(_\w+)/', $code, $m);
            $names = array_merge($names, $m[1]);
            preg_match_all('/\bprivate\s*\[([^\]]*)\]/', $code, $m);
            foreach ($m[1] as $list) {
                preg_match_all('/"(_\w+)"/', $list, $mm);
                $names = array_merge($names, $mm[1]);
            }
            preg_match_all('/\bfor\s+"(_\w+)"/', $code, $m);
            $names = array_merge($names, $m[1]);
            // params [...] : éléments directs ou premier élément d'un sous-tableau (crochets équilibrés).
            $off = 0;
            while (preg_match('/\bparams\s*\[/', $code, $mp, PREG_OFFSET_CAPTURE, $off)) {
                $b = $mp[0][1] + strlen($mp[0][0]) - 1;
                $depth = 0;
                $len = strlen($code);
                for ($j = $b; $j < $len; $j++) {
                    if ($code[$j] === '[') {
                        $depth++;
                    } elseif ($code[$j] === ']' && --$depth === 0) {
                        break;
                    }
                }
                preg_match_all('/(?:^|,|\[)\s*"(_\w+)"/', substr($code, $b + 1, $j - $b - 1), $mm);
                $names = array_merge($names, $mm[1]);
                $off = $j;
            }
            foreach ($names as $name) {
                $top = (bool) preg_match('/^(private\s+' . preg_quote($name, '/') . '\b|params\b)/', $code);
                $decl[] = [$n + 1, $name, $top];
            }
        }
        $top = [];
        $bad = [];
        foreach ($decl as [$ln, $name, $isTop]) {
            $k = strtolower($name);
            if ($isTop && isset($top[$k])) {
                $bad[] = sprintf('ligne %d : %s déjà déclaré au premier niveau (ligne %d, %s)', $ln, $name, $top[$k][0], $top[$k][1]);
            } elseif ($isTop) {
                $top[$k] = [$ln, $name];
            }
        }
        foreach ($decl as [$ln, $name, $isTop]) {
            $k = strtolower($name);
            if (!$isTop && isset($top[$k])) {
                $bad[] = sprintf('ligne %d : %s reprend %s (ligne %d, casse ignorée en SQF)', $ln, $name, $top[$k][1], $top[$k][0]);
            }
        }
        return $bad;
    }

    public function testNoHelperNameIsRedeclaredOrShadowedCaseInsensitively(): void
    {
        $bad = [];
        foreach (self::SQF as $f) {
            foreach (self::shadowedNames(self::read($f)) as $b) {
                $bad[] = $f . ' ' . $b;
            }
        }
        self::assertSame([], $bad, implode("\n", $bad));
        // La vérification attrape bien les deux causes du « Bug » (cas d'école).
        $old = "private _EMPTY = [1];\nprivate _L = { localize _this };\nprivate _render = {\n    private _l = 1 call _ctrl;\n    private _empty = true;\n};\n";
        self::assertCount(2, self::shadowedNames($old));
        self::assertCount(1, self::shadowedNames("params [[\"_t\", \"\"]];\nprivate _f = { { _x params [\"_T\"]; } forEach [] };\n"));
    }

    public function testEveryControlHasExactlyOneOwnerAndVoletsStartClosed(): void
    {
        $own = self::owners();
        $hpp = self::controls('/dialogs/aizone.hpp');
        $seen = [];
        $bad = [];
        foreach ($own as $o => $idcs) {
            foreach ($idcs as $idc) {
                if (isset($seen[$idc])) {
                    $bad[] = sprintf('%d appartient à %s et à %s', $idc, $seen[$idc], $o);
                }
                $seen[$idc] = $o;
            }
        }
        $order = [];
        foreach ($hpp as $i => $c) {
            $order[$c['idc']] = $i;
            // 95508 est dans le groupe 95507 : il suit son groupe.
            if ($c['idc'] !== 95508 && !isset($seen[$c['idc']])) {
                $bad[] = sprintf('%s (%d) sans propriétaire dans _OWN', $c['name'], $c['idc']);
            }
        }
        foreach (array_keys($seen) as $idc) {
            if (!isset($order[$idc])) {
                $bad[] = sprintf('%d dans _OWN mais absent de aizone.hpp', $idc);
            }
        }
        // Un contrôle n'apparaît qu'une fois (plus de seconde copie de « Détecter autour » / « Sélection Zeus »).
        $actions = [];
        foreach ($hpp as $c) {
            if (preg_match('/action = "(.*?)";/', $c['body'], $ma)) {
                $actions[$ma[1]][] = $c['name'];
            }
        }
        foreach ($actions as $a => $names) {
            if (count($names) > 1 && !str_contains($a, "'filters'") && !str_contains($a, "'memory'")) {
                $bad[] = 'même action pour ' . implode(', ', $names) . ' : ' . $a;
            }
        }
        // Couches : le voile au-dessus de toute la page, chaque volet au-dessus du voile.
        $page = array_merge(...array_values(array_intersect_key($own, array_flip(['frame', 'list', 'listRows', 'listEmpty', 'mid', 'tab_id', 'tab_docs', 'tab_beh', 'tab_side', 'pv', 'foot']))));
        foreach ($page as $idc) {
            if (($order[$idc] ?? PHP_INT_MAX) > $order[95850]) {
                $bad[] = $idc . ' déclaré après le voile (dessiné par-dessus)';
            }
        }
        foreach (['volet_ath', 'volet_filt', 'volet_mem'] as $v) {
            foreach ($own[$v] as $idc) {
                if ($order[$idc] < $order[95850]) {
                    $bad[] = $idc . ' (' . $v . ') déclaré avant le voile (caché dessous)';
                }
            }
        }
        // Les contrôles posés dessous à dessein sont les mêmes dans l'audit en jeu et ici.
        $sqf = self::read('/functions/fn_editorAi.sqf');
        self::assertSame(1, preg_match('/^private _UNDER = \[([\d, ]+)\];/m', $sqf, $mu));
        $under = array_map('intval', array_map('trim', explode(',', $mu[1])));
        self::assertEqualsCanonicalizing(self::UNDER, $under, '_UNDER de fn_editorAi et UNDER du test');
        self::assertSame([], $bad, implode("\n", $bad));

        // État initial : aucun volet ouvert, le registre appliqué tout de suite puis en fin de construction.
        self::assertSame(1, preg_match('/case "load": \{(.*?)\n    \};/s', $sqf, $ml));
        $load = $ml[1];
        $closed = strpos($load, '_st set ["volet", ""];');
        $first = strpos($load, 'call _layout;');
        self::assertNotFalse($closed, 'load : volet fermé au départ');
        self::assertNotFalse($first);
        self::assertLessThan($first, $closed, 'load : volet fermé avant le premier affichage');
        self::assertGreaterThan(1, substr_count($load, 'call _layout;'), 'load : registre repassé en fin de construction');
        // Un seul volet : une seule clé d'état, plus les anciens drapeaux qui pouvaient être vrais ensemble.
        foreach (['filtersOpen', 'memOpen', 'foldOpen'] as $old) {
            self::assertStringNotContainsString($old, $sqf);
        }
        // _layout passe d'abord par _showOnly, qui cache tout propriétaire non visible.
        self::assertSame(1, preg_match('/^private _layout = \{\n(.*?)\n\};/ms', $sqf, $mlay));
        self::assertStringStartsWith("    if (isNull _d) exitWith {};\n    [call _visibleOwners] call _showOnly;", $mlay[1]);
        self::assertStringContainsString('{ if !(_x in _owners) then { [_OWN get _x, false] call _vis; }; } forEach (keys _OWN);', $sqf);
        // Fermeture : Échap, clic sur le voile, autre volet ; la page quittée ferme le volet.
        self::assertStringContainsString('displayAddEventHandler ["KeyDown"', $sqf);
        self::assertStringContainsString('(_key isEqualTo 1) && {["escape"] call comspec_sse_fnc_editorAi}', $sqf);
        self::assertStringContainsString("action = \"['voletClose'] call comspec_sse_fnc_editorAi\"", self::read('/dialogs/aizone.hpp'));
        // Contrôles sous condition : jamais hors de leur propriétaire.
        self::assertDoesNotMatchRegularExpression('/\(950(08|09)\d? call _ctrl\) ctrlShow|\((95114|95209|95504) call _ctrl\) ctrlShow/', $sqf);
        // Audit en jeu, et la visite guidée ne s'ouvre pas sur une fenêtre cassée.
        self::assertStringContainsString('if (_op isEqualTo "audit") exitWith', $sqf);
        foreach (['"orphans"', '"overlaps"', '"clipped"', '"volets"'] as $k) {
            self::assertStringContainsString($k, $sqf);
        }
        self::assertSame(1, preg_match('/case "coach": \{(.*?)\n    \};/s', $sqf, $mc));
        self::assertLessThan(strpos($mc[1], '["start"'), strpos($mc[1], 'call _audit'));
        // Ligne d'état : « Actualisation demandée… » se termine toujours (liste relue, ou Athena muette au bout de 25 s).
        self::assertStringContainsString('"RefreshDone" call _loc', $sqf);
        self::assertStringContainsString('"RefreshLate" call _loc', $sqf);
        self::assertStringContainsString('["refreshLate"] call comspec_sse_fnc_editorAi', $sqf);
    }

    public function testOnboardingIsAnchoredAndStoredOncePerProfile(): void
    {
        $coach = self::read('/dialogs/coach.hpp');
        $fn = self::read('/functions/fn_editorCoach.sqf');
        $ai = self::read('/functions/fn_editorAi.sqf');
        $ed = self::read('/functions/fn_editor.sqf');
        $hpp = self::read('/dialogs/editor.hpp');
        // Plus de modale opaque au milieu (ancien GuideDim / GuideBox).
        self::assertStringNotContainsString('GuideDim', $hpp);
        self::assertStringNotContainsString('GuideBox', $hpp);
        self::assertStringNotContainsString('colorBackground[] = {0, 0, 0, 0.72}', $hpp . $coach);
        // Bulle, pointe, cadre autour de la cible ; Suivant / Passer / Ne plus afficher ; 3 étapes au plus.
        foreach (['CoArrow', 'CoOutT', 'CoNext', 'CoSkip', 'CoNever'] as $c) {
            self::assertStringContainsString('class ' . $c, $coach);
        }
        self::assertStringContainsString('_steps select [0, 3]', $fn);
        self::assertStringContainsString('profileNamespace setVariable', $fn);
        self::assertStringContainsString('saveProfileNamespace', $fn);
        // Visite de la page : drapeau du profil, rouverte par « ? » (forcée), et celle de l'éditeur reste la sienne.
        self::assertStringContainsString('"comspec_sse_aiZoneCoach"', $ai);
        self::assertStringContainsString("action = \"['coach'] call comspec_sse_fnc_editorAi\"", self::read('/dialogs/aizone.hpp'));
        self::assertStringContainsString('"comspec_sse_editorGuided"', $ed);
        self::assertStringContainsString('class editorCoach {};', self::read('/config.cpp'));
    }

    public function testNoArmaAlertPopupsAndEntryPointsStayStable(): void
    {
        foreach (['/functions/fn_editorAi.sqf', '/functions/fn_editorCoach.sqf', '/functions/fn_editorAiCard.sqf'] as $f) {
            $s = self::read($f);
            foreach (['fnc_toast', 'fnc_notify', 'systemChat', 'hint ', 'hintSilent', 'titleText', 'cutText', 'BIS_fnc_showNotification', 'BIS_fnc_guiMessage'] as $bad) {
                self::assertStringNotContainsString($bad, $s, $f . ' : ' . $bad);
            }
        }
        // Entrées stables : fn_aiConfigOpen ([unité ou liste, position]) et la réponse du serveur « aiReply ».
        self::assertStringContainsString('params [["_what", objNull, [objNull, []]], ["_pos", [], [[]]]];', self::read('/functions/fn_aiConfigOpen.sqf'));
        $srv = self::read('/functions/fn_editorAiServer.sqf');
        self::assertStringContainsString('remoteExecCall ["comspec_sse_fnc_editorAi", _owner]', $srv);
        self::assertStringContainsString('case "docView":', $srv);
        // Données gardées inchangées : mêmes clés de profil du serveur.
        self::assertStringContainsString('"comspec_sse_editorAiKept"', $srv);
        self::assertStringContainsString('"comspec_sse_editorAiKeptSeq"', $srv);
    }
}
