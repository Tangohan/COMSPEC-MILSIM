<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Affichage TEXTE des écrans TOC (demande de TTRD du 11/11 : « j'adore les écrans LCD horloge, faut la même chose pour
 * écrire ce qu'on veut en texte et mode défilant quand c'est trop long ») et formats d'image hors 16:9 (bandeaux LED
 * 8:1 et 16:1, colonnes 8:9 d'un écran incurvé). Lecture du code SQF : tables JSON du code rejouées ici (défilement,
 * formats, paramètres) ; le rendu lui-même se vérifie en jeu (fn_tocText « selfCheck », fn_tocScreen « audit »).
 */
final class AtakTocTextTest extends TestCase
{
    private const MAIN = 'mod/COMSPEC_ATAK_Native/Sources/addons/main/';
    private const TOC = self::MAIN . 'functions/toc/';

    private static function read(string $path): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(dirname(__DIR__, 2) . '/' . $path));
    }

    /** Table JSON du code SQF : « // ---- TABLE _nom » … « private _nom = [ … ]; » … « // ---- FIN ». */
    private static function table(string $src, string $name): array
    {
        self::assertSame(1, preg_match('/\/\/ ---- TABLE ' . preg_quote($name, '/') . ' .*?private ' . preg_quote($name, '/') . ' = (\[.*?\]);\n\s*\/\/ ---- FIN/s', $src, $m), $name);
        $data = json_decode($m[1], true);
        self::assertIsArray($data, $name . ' : JSON strict attendu (' . json_last_error_msg() . ')');

        return $data;
    }

    /** Même calcul que _marquee (fn_tocText.sqf). */
    private static function marquee(float $tw, float $pw, float $spd, float $gap, float $t, bool $always, bool $pass): array
    {
        if ($tw <= $pw && !$always) {
            return [false, ($pw - $tw) / 2, 0.0];
        }
        if ($pass) {
            $len = $pw + $tw;

            return [true, $pw - min($t * $spd, $len), $len];
        }
        $loop = max($tw + $gap, $pw);

        return [true, -fmod($t * $spd, $loop), $loop];
    }

    public function testTextDisplayIsRegisteredWithItsParameterSchema(): void
    {
        $scr = self::read(self::TOC . 'fn_tocScreen.sqf');
        $txt = self::read(self::TOC . 'fn_tocText.sqf');
        self::assertStringContainsString('class tocText {};', self::read(self::MAIN . 'config.cpp'));
        self::assertMatchesRegularExpression('/private _views = \[[^\]]*"TEXT",[^\]]*"COLA", "COLB"[^\]]*\];/', $scr);
        self::assertStringContainsString('["TEXT", ["TEXTE", "texte à LED"]]', $scr);
        // Liste centrale des affichages : « params » donne les paramètres de l'affichage (fenêtres de réglage génériques).
        self::assertStringContainsString('[[], ["schema"] call comspec_atak_native_fnc_tocText] select (_a isEqualTo "TEXT")', $scr);
        $schema = self::table($txt, '_schema');
        self::assertSame(['text', 'color', 'secs', 'blink', 'speed', 'scroll', 'align', 'border'], array_column($schema, 0));
        foreach ($schema as [$key, $label, $type, $default, $choices]) {
            self::assertNotSame('', $label, $key);
            self::assertContains($type, ['TEXT', 'ENUM'], $key);
            if ($type === 'ENUM') {
                self::assertContains($default, array_column($choices, 1), $key . ' : défaut parmi les choix');
            }
        }
        $by = array_column($schema, 4, 0);
        self::assertSame(['G', 'A', 'R', 'W'], array_column($by['color'], 1), 'vert, ambre, rouge, blanc');
        self::assertSame([0, 1, 2], array_column($by['speed'], 1), 'lent, normal, rapide');
        self::assertSame([0, 1], array_column($by['scroll'], 1), 'si le texte dépasse, toujours défiler');
        // Dessin : avant les tableaux, carte et tableau masqués ; FLASH du poste à la place des messages.
        self::assertStringContainsString('["draw", [_o, _d, [_o] call _fmtOf]] call comspec_atak_native_fnc_tocText', $scr);
        self::assertStringContainsString('private _fl = ["flash", _side] call comspec_atak_native_fnc_tocBoard;', $txt);
        self::assertStringContainsString('_msgs = [[format ["FLASH · %1 · %2 · %3Z"', $txt);
    }

    public function testMarqueeMathMatchesTheVectorsOfTheCode(): void
    {
        $txt = self::read(self::TOC . 'fn_tocText.sqf');
        // La fonction pure du code : fixe et centré quand le texte tient, boucle (texte + blanc, jamais moins que le
        // panneau) déduite du temps, passage unique pour le tour de rôle.
        self::assertStringContainsString('if (_tw <= _pw && {!_always}) exitWith { [false, (_pw - _tw) / 2, 0] };', $txt);
        self::assertStringContainsString('private _loop = (_tw + _gap) max _pw;', $txt);
        self::assertStringContainsString('[true, -((_t * _spd) mod _loop), _loop]', $txt);
        self::assertStringContainsString('[true, _pw - ((_t * _spd) min _len), _len]', $txt);
        self::assertStringContainsString('case "selfCheck": {', $txt);
        $vectors = self::table($txt, '_vectors');
        self::assertGreaterThanOrEqual(10, count($vectors));
        $seen = ['static' => 0, 'loop' => 0, 'pass' => 0, 'always' => 0];
        foreach ($vectors as $i => [$tw, $pw, $spd, $gap, $t, $always, $pass, $eScroll, $eX, $eLoop]) {
            [$scroll, $x, $loop] = self::marquee((float) $tw, (float) $pw, (float) $spd, (float) $gap, (float) $t, $always, $pass);
            self::assertSame($eScroll, $scroll, "cas $i : défile");
            self::assertEqualsWithDelta($eX, $x, 0.001, "cas $i : position");
            self::assertEqualsWithDelta($eLoop, $loop, 0.001, "cas $i : boucle");
            $seen[!$scroll ? 'static' : ($pass ? 'pass' : ($always ? 'always' : 'loop'))]++;
        }
        foreach ($seen as $kind => $n) {
            self::assertGreaterThan(0, $n, "aucun cas « $kind » dans la table");
        }
        // Propriétés : la boucle revient au départ après boucle / vitesse ; le second texte suit sans trou plus grand que le blanc.
        [, $x0, $loop] = self::marquee(2.3, 1.0, 0.2, 0.1, 0.0, false, false);
        [, $x1] = self::marquee(2.3, 1.0, 0.2, 0.1, $loop / 0.2, false, false);
        self::assertEqualsWithDelta($x0, $x1, 0.0001);
        self::assertEqualsWithDelta(2.4, $loop, 0.0001);
        for ($t = 0.0; $t < 20; $t += 0.37) {
            [, $x] = self::marquee(2.3, 1.0, 0.2, 0.1, $t, false, false);
            self::assertLessThanOrEqual(0.0, $x);
            self::assertGreaterThan(-$loop, $x);
            // Le panneau [0, 1] est toujours couvert par le premier texte, le blanc ou le second.
            self::assertGreaterThanOrEqual(1.0, $x + $loop + 2.3);
        }
        // Position tirée de l'heure du serveur (même endroit du texte pour tous), animée entre deux images par l'horloge locale.
        self::assertStringContainsString('serverTime mod (_loop / _spd)', $txt);
        self::assertStringContainsString('private _ph = serverTime mod (_cycle max 1);', $txt);
    }

    public function testScrollingOnlyCostsInFrontOfALitScreen(): void
    {
        $txt = self::read(self::TOC . 'fn_tocText.sqf');
        $k = array_column(self::table($txt, '_k'), 1, 0);
        self::assertSame(30, $k['hz']);
        self::assertSame(300, $k['maxLen']);
        self::assertSame(8, $k['maxMsgs']);
        self::assertSame([4, 7, 11], $k['cps']);
        self::assertStringContainsString('}, 1 / 30] call CBA_fnc_addPerFrameHandler];', $txt);
        // Une seule boucle ; un écran figé, en veille ou passé à un autre affichage en sort ; elle s'arrête d'elle-même.
        self::assertStringContainsString('(_o getVariable ["COMSPEC_ATAK_TocOn", false]) && {(_o getVariable ["COMSPEC_ATAK_TocTex", ""]) isNotEqualTo ""}', $txt);
        self::assertStringContainsString('param [3, ""]) isEqualTo "TEXT"', $txt);
        self::assertStringContainsString('[missionNamespace getVariable "COMSPEC_ATAK_TxtPfh"] call CBA_fnc_removePerFrameHandler;', $txt);
        // Largeur : mesure du jeu si elle est plausible, sinon estimation par caractère.
        self::assertStringContainsString('private _m = ctrlTextWidth _c;', $txt);
        self::assertStringContainsString('[_est, _m] select (_m > 0.5 * _est && {_m < 2 * _est})', $txt);
        // Redessin chaque seconde (variables d'heure, tour de rôle, FLASH), budget de huit images par tour.
        $scr = self::read(self::TOC . 'fn_tocScreen.sqf');
        self::assertMatchesRegularExpression('/in \["CLOCK", "SAT", "STRIP", "MOSAIC", "TEXT",[^\]]*"COLA"[^\]]*\]/', $scr);
        self::assertStringContainsString('if (_due && {_drawn < 8 || {_last < 0}}) then {', $scr);
    }

    public function testTextIsSanitisedByTheServerAndSavedWithTheScreen(): void
    {
        $txt = self::read(self::TOC . 'fn_tocText.sqf');
        $scr = self::read(self::TOC . 'fn_tocScreen.sqf');
        $per = self::read(self::TOC . 'fn_tocPersist.sqf');
        // Aucune balise de texte structuré : « < » et « > » remplacés, une seule ligne, longueur bornée.
        self::assertStringContainsString('[["[\r\n\t]+", " "], ["<", "‹"], [">", "›"]]', $txt);
        self::assertStringContainsString('_s select [0, _max]', $txt);
        self::assertStringContainsString('if !(_col in ["G", "A", "R", "W"]) then { _col = "G"; };', $txt);
        self::assertStringContainsString('(count _out) < (_kv get "maxMsgs")', $txt);
        self::assertStringContainsString('[_m param [2, 10], 10, 3, 600] call _num', $txt);
        // Le texte passe par ctrlSetText (jamais parseText) sur l'écran.
        self::assertStringNotContainsString('parseText _txt', $txt);
        self::assertStringContainsString('_c ctrlSetText _s; // i18n:brut', $txt);
        // Publié par le serveur seulement, nettoyé ; relu nettoyé depuis la sauvegarde ; dalles d'un mur comprises.
        self::assertStringContainsString('case "text": { _o setVariable ["COMSPEC_ATAK_TocText", ["sanitize", _v] call comspec_atak_native_fnc_tocText, true]; };', $scr);
        self::assertSame(2, substr_count($per, '_x getVariable ["COMSPEC_ATAK_TocText", []]'));
        self::assertStringContainsString('_o setVariable ["COMSPEC_ATAK_TocText", ["sanitize", _txt] call comspec_atak_native_fnc_tocText, true];', $per);
        self::assertStringContainsString('_rv setVariable ["COMSPEC_ATAK_TocText", ["sanitize", _txt] call comspec_atak_native_fnc_tocText, true];', $scr);
    }

    public function testImageFormatsFollowTheRatioOfThePanel(): void
    {
        $scr = self::read(self::TOC . 'fn_tocScreen.sqf');
        $formats = self::table($scr, '_formats');
        $pick = static function (float $ratio) use ($formats): array {
            foreach ($formats as $f) {
                if ($ratio < $f[0]) {
                    return $f;
                }
            }

            return $formats[0];
        };
        $expect = [
            '0.889' => ['COL', 1, 'COLA'], '1.778' => ['STD', 2, 'SPLIT'], '4' => ['STRIP', 4, 'STRIP'],
            '8' => ['LED', 8, 'TEXT'], '12' => ['LED', 16, 'TEXT'], '16' => ['LED', 16, 'TEXT'],
        ];
        foreach ($expect as $ratio => [$kind, $pxAspect, $default]) {
            $f = $pick((float) $ratio);
            self::assertSame($kind, $f[1], "format du rapport $ratio");
            self::assertSame($pxAspect, $f[3], "texture du rapport $ratio");
            self::assertSame($default, $f[5], "affichage par défaut du rapport $ratio");
            // Texture en puissances de 2, au rapport annoncé (Arma refuse les autres tailles).
            self::assertSame(1, preg_match('/^#\(rgb,(\d+),(\d+),1\)$/', $f[2], $m), $f[2]);
            foreach ([(int) $m[1], (int) $m[2]] as $px) {
                self::assertSame(0, $px & ($px - 1), $f[2]);
            }
            self::assertSame($pxAspect, intdiv((int) $m[1], (int) $m[2]));
            if ($kind !== 'STD') {
                self::assertContains('TEXT', $f[4], "le texte à LED va sur le format $kind");
                self::assertContains($default, $f[4]);
            }
        }
        // Bandeaux LED : texte seulement ; une carte 16:9 n'y va jamais.
        self::assertSame(['TEXT'], $pick(8.0)[4]);
        self::assertStringContainsString('_view = [_o, _view] call _fitView;', $scr);
        self::assertStringContainsString('case "mode": { if (_v in _views && {_v in (([_o] call _fmtOf) select 4)}) then { _c set [3, _v]; }; };', $scr);
        // Format par dalle (écran incurvé : zone 16:9 et colonnes 8:9) et texture demandée selon le format.
        self::assertStringContainsString('getArray (configOf _h >> "comspec_screenPanelRatio")', $scr);
        self::assertStringContainsString('([_o] call _fmtOf) select 1, _tex]]] call _fn;', $scr);
        // Grands murs : dalles 55" en 1024 x 512 à partir de 7 zones.
        self::assertStringContainsString('if (_np >= 7 && {_o isNotEqualTo _h || {_np >= 12}}) then { _spec = "#(rgb,1024,512,1)"; };', $scr);
    }

    public function testColumnDisplaysFitTheirEightByNineCanvas(): void
    {
        $thm = self::read(self::TOC . 'fn_tocTheme.sqf');
        $brd = self::read(self::TOC . 'fn_tocBoard.sqf');
        $walls = array_column(self::table($thm, '_walls'), 1, 0);
        foreach (['COLA', 'COLB', 'COLC', 'COLD', 'COLE', 'COLF'] as $view) {
            self::assertArrayHasKey($view, $walls);
            self::assertMatchesRegularExpression('/case "' . $view . '": \{/', $brd);
            foreach ($walls[$view] as [$name, $x, $y, $w, $h]) {
                self::assertGreaterThanOrEqual(0, $x, "$view.$name");
                self::assertGreaterThanOrEqual(0, $y, "$view.$name");
                self::assertLessThanOrEqual(800, $x + $w, "$view.$name déborde de la colonne (800 px)");
                self::assertLessThanOrEqual(900, $y + $h, "$view.$name déborde en bas");
            }
        }
        self::assertStringContainsString('if (_col) then { _texW = 800; };', $brd);
    }

    public function testTextCanBeSetFromEveryPlaceAndIsTranslated(): void
    {
        $scr = self::read(self::TOC . 'fn_tocScreen.sqf');
        $txt = self::read(self::TOC . 'fn_tocText.sqf');
        // ACE : « Texte à LED… » ouvre la fenêtre de saisie (écran et chaque dalle d'un mur) ; arbre dans fn_tocMenu (4.8.0).
        $menu = self::read(self::TOC . 'fn_tocMenu.sqf');
        self::assertStringContainsString('["TEXT", ["text", "Texte à LED…", ', $menu);
        self::assertStringContainsString('case "text": { [{ ["edit", _this] call comspec_atak_native_fnc_tocText; }, _o] call comspec_atak_native_fnc_nextFrame; };', $scr);
        self::assertStringContainsString('format ["pntxt|%1", _i]] call _leaf', $menu);
        self::assertStringContainsString('case "pntxt": {', $menu);
        // PC du poste (onglet SALLE), app Poste TOC, module Eden / Zeus.
        $hall = self::read(self::TOC . 'fn_tocHallUi.sqf');
        self::assertStringContainsString('["Texte (panneau à LED)…", "TEXT"]', $hall);
        self::assertStringContainsString('isEqualTo "TEXT") then { ["edit", _o] call comspec_atak_native_fnc_tocText; };', $hall);
        self::assertStringContainsString("['edit', objectFromNetId '%1'] call comspec_atak_native_fnc_tocText;", self::read(self::TOC . 'fn_pageToc.sqf'));
        $mod = self::read(self::TOC . 'fn_moduleTocScreen.sqf');
        self::assertStringContainsString('] + (["zeusFields"] call comspec_atak_native_fnc_tocText), {', $mod);
        self::assertStringContainsString('["fromFields", [_logic getVariable ["Text", ""], _logic getVariable ["TextColor", "G"], _logic getVariable ["TextSpeed", 1], _logic getVariable ["TextScroll", 0]]]', $mod);
        $hpp = self::read(self::MAIN . 'modules.hpp');
        $st = self::read(self::MAIN . 'stringtable.xml');
        foreach (['Text', 'TextColor', 'TextSpeed', 'TextScroll'] as $attr) {
            self::assertStringContainsString('property = "COMSPEC_ATAK_Module_TocScreen_' . $attr . '";', $hpp);
        }
        self::assertStringContainsString('value = "TEXT"', $hpp);
        foreach (['V_TEXT', 'TX_NAME', 'TX_TIP', 'TXC_NAME', 'TXS_NAME', 'TXM_NAME', 'TXM_1'] as $key) {
            self::assertMatchesRegularExpression('/<Key ID="STR_COMSPEC_ATAK_CFG_TOCSCR_' . $key . '">\s*<Original>[^<]+<\/Original>\s*<English>[^<]+<\/English>\s*<French>[^<]+<\/French>/', $st, $key);
        }
        // Éditeur : construit depuis la table des paramètres, aperçu en direct, envoi par le serveur.
        self::assertStringContainsString('} forEach (_schema select [1]);', $txt);
        self::assertStringContainsString('"COMSPEC_ATAK_TxtPrev", objNull] call _render;', $txt);
        self::assertStringContainsString('["view", [_o, "text", _send]] call comspec_atak_native_fnc_tocScreen;', $txt);
        // Aucune fenêtre d'alerte du jeu.
        foreach (['hint ', 'systemChat', 'BIS_fnc_showNotification', 'cutText'] as $popup) {
            self::assertStringNotContainsString($popup, $txt);
        }
        // Français et anglais : libellés des paramètres et de l'éditeur.
        $en = self::read(self::MAIN . 'i18n/en.txt');
        $xx = self::read('mod/COMSPEC_ATAK_Native/localisation/template/xx.txt');
        $labels = ['Afficher un texte…', 'Texte à LED…', 'Modifier le texte LED…','Texte de l\'écran', 'AFFICHER SUR L\'ÉCRAN', 'AJOUTER UN MESSAGE', 'Toujours défiler', 'Si le texte dépasse', 'Clignotant (alerte)',
            'Texte (panneau à LED)…', 'Colonne horloge et chronos', 'Colonne réseau et flux', 'texte à LED', 'Texte envoyé à l\'écran'];
        foreach (self::table($txt, '_schema') as [, $label, , , $choices]) {
            $labels[] = $label;
            foreach ($choices as [$choice]) {
                $labels[] = $choice;
            }
        }
        foreach (array_unique($labels) as $fr) {
            self::assertStringContainsString('["' . $fr . '", "', $en, "« $fr » sans anglais");
            self::assertStringContainsString('["' . $fr . '", "", "', $xx, "« $fr » absent du modèle de traduction");
        }
    }
}
