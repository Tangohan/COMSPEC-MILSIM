<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Optimisation des gels, lot 2 (carte du téléphone et données de carte) : fige les garde-fous branchés dans le SQF.
 *  - texture d'aéronef qui existe dans le jeu (« iconAir_ca.paa » n'existe pas : échec de chargement à chaque image) ;
 *  - liste de dessin des unités rejouée tant que rien ne change, invalidée par chaque source de changement, les unités
 *    qui bougent à chaque image restant recalculées à leur rang ;
 *  - numéros de grille et carte de la montre : commandes gardées, refaites au changement ;
 *  - marqueurs de la mission : présélection par zone (comparée ici au parcours complet sur tirages), relecture de
 *    sécurité par tranches ;
 *  - sondage des unités d'Athena et magasin : plus de conversion de la table en texte ;
 *  - mesures au RPT par partie, rien à l'écran.
 * Lecture du SQF seulement (rien n'est mesurable sans le jeu) : les durées se lisent dans le RPT, lignes [PERF].
 */
final class AtakPerfLot2MapAssetTest extends TestCase
{
    private const MAIN = 'mod/COMSPEC_ATAK_Native/Sources/addons/main/';
    private const TOUCHED = [
        'functions/map/fn_mapOnDraw.sqf', 'functions/map/fn_mapGameMarkers.sqf', 'functions/map/fn_localDataRefresh.sqf',
        'functions/map/fn_mapPlaceText.sqf', 'functions/map/fn_mapTextW.sqf', 'functions/map/fn_strokeDraw.sqf',
        'functions/map/fn_symbology.sqf', 'functions/garmin/fn_watchMapDraw.sqf', 'functions/network/fn_pollUnits.sqf',
        'functions/core/fn_storeSet.sqf', 'functions/pages/fn_bftFilter.sqf', 'functions/teams/fn_ftCatalog.sqf',
    ];
    /** Icônes de véhicule livrées par le jeu (a3\ui_f\data\map\vehicleicons, relevé dans ui_f_data.pbo), en minuscules. */
    private const GAME_VEHICLE_ICONS = [
        'iconanimal', 'iconapc', 'iconbackpack', 'iconcar', 'iconcrate', 'iconcrateammo', 'iconcrategrenades', 'iconcratelarge',
        'iconcratelong', 'iconcrateord', 'iconcratesupp', 'iconcrateveh', 'iconcratewpns', 'iconexplosiveap',
        'iconexplosiveapdirectional', 'iconexplosiveat', 'iconexplosivegp', 'iconexplosivegpdirectional', 'iconexplosiveuw',
        'iconhelicopter', 'iconhexalien', 'iconlogic', 'iconman', 'iconmanat', 'iconmancommander', 'iconmanengineer',
        'iconmanexplosive', 'iconmanleader', 'iconmanmedic', 'iconmanmg', 'iconmanofficer', 'iconmanrecon', 'iconmanvirtual',
        'iconmotorcycle', 'iconobject', 'iconparachute', 'iconplane', 'iconship', 'iconsound', 'iconstaticaa',
        'iconstaticcannon', 'iconstaticmg', 'iconstaticmgnest', 'iconstaticmortar', 'iconstaticsearchlight', 'icontank',
        'icontruck', 'iconvehicle', 'iconvirtual',
    ];

    private static function root(): string
    {
        return dirname(__DIR__, 2) . '/';
    }

    private static function read(string $file): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(self::root() . self::MAIN . $file));
    }

    /** Code sans commentaires ni contenu des chaînes. */
    private static function code(string $src): string
    {
        $src = (string) preg_replace('~/\*.*?\*/~s', '', $src);
        $src = (string) preg_replace('~"(?:[^"\n]|"")*"~', '""', $src);
        $src = (string) preg_replace("~'(?:[^'\n]|'')*'~", "''", $src);

        return (string) preg_replace('~//[^\n]*~', '', $src);
    }

    /** Code sans commentaires, chaînes gardées. */
    private static function bare(string $src): string
    {
        $src = (string) preg_replace('~/\*.*?\*/~s', '', $src);

        return (string) preg_replace('~^\s*//[^\n]*$~m', '', $src);
    }

    private static function between(string $src, string $from, string $to): string
    {
        $a = strpos($src, $from);
        self::assertNotFalse($a, $from . ' absent');
        $b = strpos($src, $to, $a + strlen($from));
        self::assertNotFalse($b, $to . ' absent');

        return substr($src, $a, $b - $a);
    }

    public function testTouchedFilesKeepBalancedDelimiters(): void
    {
        foreach (self::TOUCHED as $file) {
            $code = self::code(self::read($file));
            foreach ([['{', '}'], ['[', ']'], ['(', ')']] as [$open, $close]) {
                self::assertSame(substr_count($code, $open), substr_count($code, $close), "{$file} : « {$open} » et « {$close} » ne s'équilibrent pas");
            }
        }
    }

    public function testNothingIsShownOnScreen(): void
    {
        foreach (self::TOUCHED as $file) {
            self::assertDoesNotMatchRegularExpression(
                '/\b(hint|hintSilent|hintC|systemChat|titleText|cutText|BIS_fnc_showNotification|comspec_atak_native_fnc_toast)\b/',
                self::code(self::read($file)),
                "{$file} : message à l'écran"
            );
        }
    }

    public function testEveryVehicleIconOfTheAddonExistsInTheGame(): void
    {
        $dir = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::root() . self::MAIN, \FilesystemIterator::SKIP_DOTS));
        $seen = 0;
        foreach ($dir as $path) {
            if (!in_array(strtolower($path->getExtension()), ['sqf', 'hpp', 'cpp'], true)) {
                continue;
            }
            preg_match_all('~ui_f\\\\data\\\\map\\\\vehicleicons\\\\(\w+?)_ca\.paa~i', (string) file_get_contents($path->getPathname()), $m);
            foreach ($m[1] as $name) {
                $seen++;
                self::assertContains(strtolower($name), self::GAME_VEHICLE_ICONS, $path->getFilename() . " : texture absente du jeu « {$name}_ca.paa »");
            }
        }
        self::assertGreaterThan(20, $seen);
        // Aéronef d'une piste : hélicoptère ou avion, d'après l'engin.
        $sym = self::read('functions/map/fn_symbology.sqf');
        self::assertStringNotContainsString('iconAir_ca', self::bare($sym));
        self::assertStringContainsString('iconPlane_ca.paa", "\A3\ui_f\data\map\vehicleicons\iconHelicopter_ca.paa"] select (!isNull _av && {_av isKindOf "Helicopter"})', $sym);
    }

    public function testUnitDrawListIsInvalidatedByEverySourceOfChange(): void
    {
        $draw = self::read('functions/map/fn_mapOnDraw.sqf');
        $sig = self::between($draw, 'private _uSig = [', '];');
        foreach ([
            '(_data getOrDefault ["revisions", createHashMap]) getOrDefault ["units", -1]', // données
            '_selected',                                                                   // sélection
            '_scale',                                                                      // zoom
            '_k', '_is', '_allyK', '_contactK', '_ctK',                                    // tailles (écran, réglages, palier)
            '_symMode', '_tac', '_selfCfg',                                                // style, formes, mon point
            '_bgStyle', '_allyRgb', '_pal get "hostile"', '_pal get "neutral"', '_pal get "unknown"', '_aStale', '_aLost', // thème
            '_ewBft', '_compSkip',                                                         // guerre électronique, terminal compromis
            '_lod', '_lfs get "u2"', '_ltk',                                               // niveau de détail, textes
            'profileNamespace getVariable ["COMSPEC_ATAK_AccRings", false]',               // calque des cercles de précision
            'pixelW', 'pixelH', '_ux1 - _ux0', '_uy1 - _uy0',                              // résolution, taille de la vue
        ] as $source) {
            self::assertMatchesRegularExpression('~(^|[\s,\[])' . preg_quote($source, '~') . '\s*(,|$)~', $sig, "signature sans « {$source} »");
        }
        // Cadrage : glissement de la vue borné par la marge prise autour d'elle ; jamais pendant une image de calcul des étiquettes.
        $replay = self::between($draw, 'private _uReplay = ', ';');
        self::assertStringContainsString('!_lay', $replay);
        self::assertStringContainsString('(_uDl select 0) isEqualTo _uSig', $replay);
        self::assertStringContainsString('<= _uTolX', $replay);
        self::assertStringContainsString('<= _uTolY', $replay);
        self::assertStringContainsString('private _uTolX = 0.04 * (_ux1 - _ux0) / ((_ucw + 0.1) max 0.001);', $draw);
        self::assertStringContainsString('private _uA = _map ctrlMapScreenToWorld [_ucx - 0.05, _ucy - 0.05];', $draw);
        // Gardée par contrôle de carte (miniature, en main, classique : chacune la sienne).
        self::assertStringContainsString('_map setVariable ["comspec_uDl", [_uSig, _uVc, _uCmds, _uPos]];', $draw);
    }

    public function testUnitsThatChangeEveryFrameAreNeverReplayed(): void
    {
        $draw = self::read('functions/map/fn_mapOnDraw.sqf');
        $body = self::between($draw, 'private _uBody = {', "\nif (_uReplay) then {");
        // Moi et la sélection, engin (cap lu à l'image), MIL-STD (vitesse), contact périmé (âge).
        self::assertStringContainsString('private _uDyn = _self || {_uid isEqualTo _selected};', $body);
        self::assertSame(3, substr_count($body, '_uDyn = true;'));
        self::assertLessThan(strpos($body, 'private _vd = getDirVisual _veh;'), strpos($body, "_uDyn = true;\n        private _vs = _size * 1.55;"));
        self::assertLessThan(strpos($body, 'speed (vehicle _mo)'), strpos($body, "_uDyn = true;\n        (_dc select 9) params"));
        self::assertStringContainsString("_ctAge = diag_tickTime - (_entity getOrDefault [\"updated\", diag_tickTime]);", $body);
        // Recalculée à son rang (ordre d'empilement gardé), sinon ses commandes telles quelles et son ancre d'étiquette.
        self::assertStringContainsString('if (_uDyn) then { _uCmds pushBack [4, [_uid, _ctN0]]; } else { _uCmds append _uRec; _uPos pushBack [_uid, _pos]; };', $body);
        // Le corps est appelé (plus de boucle autour) : ni « continue », ni variable de boucle hors des crochets de sélection.
        $code = self::code($body);
        self::assertStringNotContainsString('continue', $code);
        $noCorner = str_replace(self::between($body, '_x params ["_sx", "_sy"];', '} forEach [[1, 1], [-1, 1], [1, -1], [-1, -1]];'), '', $body);
        self::assertDoesNotMatchRegularExpression('~\b_x\b|\b_y\b~', self::code($noCorner));
        // Chaque dessin d'une unité rejouable est enregistré : aucun drawIcon / drawEllipse direct hors des branches recalculées.
        $static = $noCorner;
        foreach ([['if (_vehIc isNotEqualTo "") then {', '} else {'], ['if (_sCone) then {', "\n    };"], ['if (_uid isEqualTo _selected) then {', "\n    };"]] as [$from, $to]) {
            $static = str_replace(self::between($static, $from, $to), '', $static);
        }
        $draws = preg_match_all('~_map draw(Icon|Ellipse) (\[|_da)~', $static);
        self::assertSame($draws, substr_count($static, '_uRec pushBack [0, ') + substr_count($static, '_uRec pushBack [1, '), 'un dessin d\'unité n\'est pas enregistré');
        self::assertSame(1, substr_count($static, '_uRec pushBack [2, _da];'));
        self::assertSame(1, substr_count($static, '_uRec pushBack [3, _da];'));
        // Rejeu : cinq opérations dans l'ordre des codes, ancres des étiquettes et survol refaits à chaque image.
        $play = self::between($draw, "\nif (_uReplay) then {", '["carte unités (image)", _perfU]');
        self::assertSame(5, preg_match_all('~^        \{ ~m', self::between($play, 'private _uOps = [', '];')));
        self::assertStringContainsString('{ _uposH set _x; } forEach (_uDl select 3);', $play);
        self::assertStringContainsString('_hovUnit = ((_uDl select 3) findIf', $play);
        self::assertStringContainsString('{ [_x, _y] call _uBody; } forEach (_data getOrDefault ["units",createHashMap]);', $play);
    }

    public function testGridNumbersAndWatchMapReplayKeptCommands(): void
    {
        $draw = self::read('functions/map/fn_mapOnDraw.sqf');
        self::assertStringContainsString('private _gSig = [_gTl, _gBr, _mx, _my, _maxN, _gs, _gsD, _gc, _gsh, _gFont, worldName];', $draw);
        self::assertStringContainsString('if ((_gDl param [0, []]) isNotEqualTo _gSig) then {', $draw);
        self::assertStringContainsString('{ _map drawIcon _x; } forEach (_gDl select 1);', $draw);
        self::assertSame(1, substr_count(self::bare($draw), 'configFile >> "CfgWorlds"'));
        self::assertLessThan(strpos($draw, 'configFile >> "CfgWorlds"'), strpos($draw, 'if ((_gCfg param [0, ""]) isNotEqualTo worldName) then {'));

        $watch = self::read('functions/garmin/fn_watchMapDraw.sqf');
        self::assertStringContainsString('private _sig = [(_data getOrDefault ["revisions", createHashMap]) getOrDefault ["units", -1], _ally, _nato, _lbl, _font, _rings];', $watch);
        $build = self::between($watch, 'if ((_dl param [0, []]) isNotEqualTo _sig) then {', '_mc setVariable ["comspec_watchDl", _dl];');
        self::assertStringNotContainsString('_mc draw', $build);
        self::assertSame(4, substr_count($build, '_cmds pushBack ['));
        // Symboles calculés dans la reconstruction seulement ; mon point reste dessiné en direct, avant les alliés.
        $live = str_replace($build, '', $watch);
        self::assertStringNotContainsString('fnc_symbology', self::bare($live));
        self::assertStringNotContainsString('fnc_natoSymbol', self::bare($live));
        self::assertLessThan(strpos($watch, 'private _sig = ['), strpos($watch, '_mc drawIcon [_tri, [1, 1, 1, 1], _me, 14, 14, getDir (vehicle player), "", 0];'));
        self::assertStringContainsString('{ if ((_x select 0) isEqualTo 0) then { _mc drawIcon (_x select 1); } else { _mc drawEllipse (_x select 1); }; } forEach (_dl select 1);', $watch);
    }

    /** Présélection par zone des marqueurs : même liste dessinée, dans le même ordre, que le parcours complet. */
    public function testMarkerZonePreselectionMatchesTheFullScanOnRandomDraws(): void
    {
        $gm = self::read('functions/map/fn_mapGameMarkers.sqf');
        self::assertSame(1, preg_match('~private _zw = \(\(_view select 2\) - \(_view select 0\)\) \* ([0-9.]+);~', $gm, $m));
        $margin = (float) $m[1];
        self::assertGreaterThan(0.0, $margin);
        self::assertStringContainsString('private _zh = ((_view select 3) - (_view select 1)) * ' . $m[1] . ';', $gm);
        // Conditions de reconstruction : version de la liste, échéance, vue sortie de la zone (quatre côtés).
        $guard = self::between($gm, 'private _cand = _map getVariable ["comspec_gmCand", []];', 'private _tC = diag_tickTime;');
        foreach ([
            '(_cand select 0) isNotEqualTo (_cache param [6, 0])', '_now > (_cand select 1)',
            '(_view select 0) < ((_cand select 2) select 0)', '(_view select 1) < ((_cand select 2) select 1)',
            '(_view select 2) > ((_cand select 2) select 2)', '(_view select 3) > ((_cand select 2) select 3)',
        ] as $cond) {
            self::assertStringContainsString($cond, $guard);
        }
        // Tests portés tels quels : symbole dans la zone, boîte qui touche la zone, entrée sans boîte gardée.
        self::assertStringContainsString('if ((_p select 0) >= (_zone select 0) && {(_p select 0) <= (_zone select 2)} && {(_p select 1) >= (_zone select 1)} && {(_p select 1) <= (_zone select 3)}) then { _keep pushBack _x; };', $gm);
        self::assertStringContainsString('if ((count _bb) < 4 || {!((_bb select 2) < (_zone select 0) || {(_bb select 0) > (_zone select 2)} || {(_bb select 3) < (_zone select 1)} || {(_bb select 1) > (_zone select 3)})}) then { _keep pushBack _x; };', $gm);
        // La boucle de dessin (test exact de la vue compris) est celle d'avant, sur la présélection ; version montée à chaque changement de la liste.
        self::assertStringContainsString('} forEach (_cand select 3);', $gm);
        self::assertStringContainsString('if ((_pos select 0) < (_view select 0) || {(_pos select 0) > (_view select 2)} || {(_pos select 1) < (_view select 1)} || {(_pos select 1) > (_view select 3)}) then { continue };', $gm);
        self::assertStringContainsString('_cache set [6, (_cache param [6, 0]) + 1];', $gm);

        $inside = static fn (array $p, array $r): bool => $p[0] >= $r[0] && $p[0] <= $r[2] && $p[1] >= $r[1] && $p[1] <= $r[3];
        $apart = static fn (array $bb, array $r): bool => $bb[2] < $r[0] || $bb[0] > $r[2] || $bb[3] < $r[1] || $bb[1] > $r[3];
        // Dessin exact d'une image : symbole dans la vue, trait ou zone dont la boîte touche la vue.
        $drawn = static function (array $entries, array $view) use ($inside, $apart): array {
            $out = [];
            foreach ($entries as $e) {
                if ($e['kind'] === 0 ? $inside($e['pos'], $view) : ($e['bb'] === [] || !$apart($e['bb'], $view))) {
                    $out[] = $e['id'];
                }
            }

            return $out;
        };
        mt_srand(20261115);
        $rnd = static fn (float $a, float $b): float => $a + ($b - $a) * mt_rand() / mt_getrandmax();
        $frames = 0;
        for ($draw = 0; $draw < 200; $draw++) {
            $entries = [];
            $n = mt_rand(0, 180);
            for ($i = 0; $i < $n; $i++) {
                $x = $rnd(0, 12000);
                $y = $rnd(0, 12000);
                $kind = mt_rand(0, 3);
                $r = $rnd(1, 900);
                $entries[] = ['id' => $i, 'kind' => $kind, 'pos' => [$x, $y], 'bb' => ($kind === 0 || mt_rand(0, 19) === 0) ? [] : [$x - $r, $y - $r, $x + $r, $y + $r]];
            }
            $w = $rnd(80, 9000);
            $h = $w * $rnd(0.4, 1.6);
            $x0 = $rnd(-500, 12000);
            $y0 = $rnd(-500, 12000);
            $view = [$x0, $y0, $x0 + $w, $y0 + $h];
            $zone = [$view[0] - $w * $margin, $view[1] - $h * $margin, $view[2] + $w * $margin, $view[3] + $h * $margin];
            $cand = [];
            foreach ($entries as $e) {
                if ($e['kind'] === 0 ? $inside($e['pos'], $zone) : ($e['bb'] === [] || !$apart($e['bb'], $zone))) {
                    $cand[] = $e;
                }
            }
            // La vue glisse (suivi du joueur, glissement à la main) tant qu'elle reste dans la zone : même dessin.
            for ($step = 0; $step < 12; $step++) {
                $dx = $rnd(-0.3, 0.3) * $w;
                $dy = $rnd(-0.3, 0.3) * $h;
                $v = [$view[0] + $dx, $view[1] + $dy, $view[2] + $dx, $view[3] + $dy];
                if ($v[0] < $zone[0] || $v[1] < $zone[1] || $v[2] > $zone[2] || $v[3] > $zone[3]) {
                    continue; // le SQF refait la présélection : cas couvert par les conditions ci-dessus
                }
                $frames++;
                self::assertSame($drawn($entries, $v), $drawn($cand, $v), "tirage {$draw}, pas {$step}");
            }
        }
        self::assertGreaterThan(1000, $frames);
    }

    public function testSafetyRereadOfMarkersIsSlicedAndKeepsTheirOrder(): void
    {
        $gm = self::read('functions/map/fn_mapGameMarkers.sqf');
        // Relecture complète dans l'image : premier dessin ou changement de style seulement.
        self::assertStringContainsString('private _full = _styleChg || {_due && {(_cache select 0) < 0}};', $gm);
        self::assertStringContainsString('private _due = _now > (_cache select 0) || {"" in _dirty};', $gm);
        self::assertStringContainsString('_queue = +allMapMarkers;', $gm);
        self::assertStringContainsString('_queue insert [-1, keys (_cache select 2), true];', $gm);
        self::assertSame(1, preg_match('~_slice = _queue select \[0, (\d+)\];\s+_queue deleteRange \[0, \(count _queue\) min (\d+)\];~', $gm, $m));
        self::assertSame($m[1], $m[2]);
        $per = (int) $m[1];
        self::assertGreaterThan(0, $per);
        self::assertLessThanOrEqual(40, $per);
        self::assertStringContainsString('} forEach ((keys _dirty) + _slice);', $gm);
        // Simulation de la file : tous les marqueurs relus une fois, dans l'ordre du jeu, jamais plus d'une tranche par image.
        foreach ([0, 1, $per - 1, $per, $per + 1, 63, 600, 1337] as $count) {
            $queue = range(1, $count);
            if ($count === 0) {
                $queue = [];
            }
            $done = [];
            $images = 0;
            while ($queue !== []) {
                $slice = array_slice($queue, 0, $per);
                $queue = array_slice($queue, min(count($queue), $per));
                self::assertLessThanOrEqual($per, count($slice));
                array_push($done, ...$slice);
                $images++;
            }
            self::assertSame($count === 0 ? [] : range(1, $count), $done);
            self::assertSame((int) ceil($count / $per), $images);
        }
        // Les trois relectures de 5 min (marqueurs, traits, table de sélection) ne retombent plus dans la même image.
        self::assertStringContainsString('_cache set [0, _now + 300];', $gm);
        self::assertStringContainsString('missionNamespace setVariable ["COMSPEC_ATAK_SkFull", _now + 350];', self::read('functions/map/fn_strokeDraw.sqf'));
        self::assertStringContainsString('_mk = [diag_tickTime + 400, _local, _remRev,', self::read('functions/map/fn_localDataRefresh.sqf'));
    }

    public function testUnitPollAndStoreNoLongerStringifyTheTables(): void
    {
        $poll = self::read('functions/network/fn_pollUnits.sqf');
        // Empreinte des lignes sans leur âge ; lignes identiques : seule l'heure des pistes avance, rien n'est refait.
        self::assertStringContainsString('_rows pushBack [_cs, _wx, _wy, _role, _age, _status]; _print pushBack [_cs, _wx, _wy, _role, _status];', $poll);
        self::assertStringContainsString('_sig = "L|" + (hashValue _print);', $poll);
        self::assertStringContainsString('if (!isNil "_u") then { _u set ["updated", _now - (_x select 4)]; };', $poll);
        self::assertStringContainsString('if ((_sig select [0, 2]) isNotEqualTo "L|") then {', $poll);
        self::assertStringContainsString('["remoteUnits", _units, -1, _sig] call comspec_atak_native_fnc_storeSet', $poll);
        self::assertStringNotContainsString('str ', self::code($poll));
        // Les grosses tables arrivent toutes au magasin avec une signature (quatrième paramètre).
        foreach ([
            'functions/map/fn_localDataRefresh.sqf' => ['["units",_units,-1,"n" + str _unitsSeq]', '["markers",_markers,-1,str (_mk select 3)]'],
            'functions/network/fn_pollMarkers.sqf' => ['["remoteMarkers",_markers,-1,_raw]'],
            'functions/network/fn_pollOrders.sqf' => ['["tasks",_tasks,-1,_raw]'],
        ] as $file => $calls) {
            foreach ($calls as $call) {
                self::assertStringContainsString($call . ' call comspec_atak_native_fnc_storeSet', self::read($file), $file);
            }
        }
        self::assertStringNotContainsString('str diag_tickTime', self::bare(self::read('functions/map/fn_localDataRefresh.sqf')));
        // Repli sans signature : mesuré, avec le nom du domaine dans la ligne « Lent ».
        self::assertStringContainsString('["magasin : empreinte par texte", _tS, 4, -1, "magasin : empreinte par texte (" + _domain + ")"] call comspec_atak_native_fnc_perfAcc;', self::read('functions/core/fn_storeSet.sqf'));
    }

    public function testBftFilterSettingsAreReadOncePerPass(): void
    {
        $refresh = self::read('functions/map/fn_localDataRefresh.sqf');
        self::assertSame(1, substr_count($refresh, '["cfg"] call comspec_atak_native_fnc_bftFilter'));
        self::assertStringContainsString('{!_bftOff && {!([_e, _bftCfg] call comspec_atak_native_fnc_bftFilter)}}', $refresh);
        $filter = self::read('functions/pages/fn_bftFilter.sqf');
        // Les six réglages ne sont lus que par « cfg » ; un appel sans second paramètre les relit (montre, BFT : inchangés).
        $cfg = self::between($filter, 'if (_u isEqualTo "cfg") exitWith {', "\n};");
        self::assertSame(6, substr_count($cfg, 'profileNamespace getVariable ['));
        self::assertSame(6, substr_count(self::bare($filter), 'profileNamespace getVariable ['));
        self::assertStringContainsString('if ((count _cfg) < 7) then { _cfg = ["cfg"] call comspec_atak_native_fnc_bftFilter; };', $filter);
        self::assertStringContainsString('_cfg params ["_hAi", "_hAir", "_hVeh", "_hLost", "_hOff", "_max"];', $filter);
    }

    public function testEveryPartOfTheMapIsMeasuredInTheRpt(): void
    {
        $keys = [
            'functions/map/fn_mapOnDraw.sqf' => [
                'carte préparation (image)', 'carte relief (image)', 'carte unités (image)', 'carte unités (rejouées)', 'carte unités (recalculées)',
                'carte listes des repères', 'carte points de passage (image)', 'carte tracés (image)', 'carte cartouches (image)',
                'carte grille (image)', 'carte marqueurs mission (image)', 'carte étiquettes (image)',
            ],
            'functions/map/fn_mapGameMarkers.sqf' => ['marqueurs : reconstruction complète', 'carte marqueurs mission (présélection)'],
            'functions/map/fn_mapPlaceText.sqf' => ['lieux de la carte (construction)'],
            'functions/map/fn_mapTextW.sqf' => ['largeurs de texte (mesures)'],
            'functions/map/fn_strokeDraw.sqf' => ['traits : relevé complet'],
            'functions/map/fn_localDataRefresh.sqf' => ['marqueurs : table complète (sélection)'],
            'functions/network/fn_pollUnits.sqf' => ['Athena : unités (liste Link)'],
        ];
        foreach ($keys as $file => $list) {
            $src = self::read($file);
            foreach ($list as $key) {
                self::assertMatchesRegularExpression('~\["' . preg_quote($key, '~') . '", _\w+[\],]~u', $src, "{$file} : clé « {$key} » absente");
            }
            self::assertGreaterThanOrEqual(count($list), substr_count($src, 'call comspec_atak_native_fnc_perfAcc'));
        }
        // Couleur des lieux lue une fois par type, largeur mesurée seulement hors cache.
        $places = self::read('functions/map/fn_mapPlaceText.sqf');
        self::assertStringContainsString('private _col = _colOf getOrDefault [_ty, []];', $places);
        self::assertSame(1, substr_count(self::bare($places), 'call compile'));
        $width = self::read('functions/map/fn_mapTextW.sqf');
        self::assertLessThan(strpos($width, 'private _tW = diag_tickTime;'), strpos($width, 'if (_w >= 0) exitWith { _w };'));
    }
}
