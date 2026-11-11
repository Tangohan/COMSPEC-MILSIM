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
    ];

    /** Posés par-dessus d'autres à dessein (fonds, invites sous un champ, icônes d'en-tête, repère d'onglet, groupes). */
    private const UNDER = [95000, 95007, 95008, 95013, 95015, 95018, 95019, 95020, 95021, 95022, 95036, 95037, 95113, 95114, 95209, 95502, 95503, 95504, 95507, 95600, 95700, 95701, 95800, 95801];

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
                $interactive = (bool) preg_match('/\b(action|onLBSelChanged|onKeyUp) =/', $c['body']);
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
        preg_match_all('/"(\w+)" call _L/', $sqf, $ml);
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
            [95601, 'NoZeusSel', ''], [95601, 'Refused', ''], [95601, 'PickDoc', ''],
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
        $tabs = [
            'id' => [95100, 95199], 'docs' => [95200, 95299], 'beh' => [95300, 95399], 'side' => [95400, 95499],
        ];
        $base = array_values(array_filter($all, static fn (array $c): bool => $c['pos'] !== null && $c['idc'] >= 95000 && ($c['idc'] < 95100 || ($c['idc'] >= 95500 && $c['idc'] < 95700))));
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
        $sets = [];
        foreach ($tabs as $tab => [$lo, $hi]) {
            $sets['page + onglet ' . $tab] = array_merge($base, array_values(array_filter($all, static fn (array $c): bool => $c['pos'] !== null && $c['idc'] >= $lo && $c['idc'] <= $hi)));
        }
        $sets['volet Filtres'] = array_values(array_filter($all, static fn (array $c): bool => $c['idc'] >= 95700 && $c['idc'] < 95800));
        $sets['volet Mémoire'] = array_values(array_filter($all, static fn (array $c): bool => $c['idc'] >= 95800 && $c['idc'] < 95900));
        $sets['état vide'] = array_values(array_filter($all, static fn (array $c): bool => in_array($c['idc'], [95019, 95020, 95021, 95022], true)));
        foreach ($sets as $label => $set) {
            $set = array_values(array_filter($set, static fn (array $c): bool => !in_array($c['idc'], self::UNDER, true) || str_starts_with($label, 'état')));
            $n = count($set);
            for ($i = 0; $i < $n; $i++) {
                for ($j = $i + 1; $j < $n; $j++) {
                    [$x1, $y1, $w1, $h1] = $set[$i]['pos'];
                    [$x2, $y2, $w2, $h2] = $set[$j]['pos'];
                    $e = 0.02;
                    if ($x1 + $w1 - $e > $x2 && $x2 + $w2 - $e > $x1 && $y1 + $h1 - $e > $y2 && $y2 + $h2 - $e > $y1) {
                        $bad[] = sprintf('%s : %s chevauche %s', $label, $set[$i]['name'], $set[$j]['name']);
                    }
                }
            }
        }
        self::assertSame([], $bad, implode("\n", $bad));
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
