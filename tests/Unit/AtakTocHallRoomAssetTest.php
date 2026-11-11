<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Module « ATAK · Salle TOC complète » (retour d'un testeur sur la 4.6.0 : « pas de salle, dans Eden comme dans Zeus,
 * seulement un grand écran qui flotte en l'air »). Lecture des tables de fn_tocHallBuild et du SQF : classes
 * présentes dans la config, aucun chevauchement, un seul constructeur pour Eden et Zeus, pose au sol trouvé, un pas
 * par objet. Le rendu en jeu se vérifie avec ["selfTest"] call comspec_atak_native_fnc_tocHallBuild.
 */
final class AtakTocHallRoomAssetTest extends TestCase
{
    private const MAIN = 'mod/COMSPEC_ATAK_Native/Sources/addons/main/';
    private const BUILD = self::MAIN . 'functions/toc/fn_tocHallBuild.sqf';
    private const SCREEN_ROLES = ['wall', 'side', 'stand', 'clock', 'led', 'plate', 'ceiling'];

    private static function read(string $path): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(dirname(__DIR__, 2) . '/' . $path));
    }

    /** Table « private _nom = [ … ]; » suivie de « // ---- FIN » : du JSON strict. */
    private static function table(string $name): array
    {
        $src = self::read(self::BUILD);
        self::assertSame(1, preg_match('/private ' . preg_quote($name, '/') . ' = (\[.*?\]);\n\/\/ ---- FIN/s', $src, $m), 'table ' . $name . ' absente');
        $data = json_decode($m[1], true);
        self::assertIsArray($data, 'table ' . $name . ' : JSON invalide (' . json_last_error_msg() . ')');

        return $data;
    }

    /** Corps d'un « case "nom": { … } » (accolades équilibrées). */
    private static function sqfCase(string $src, string $name): string
    {
        $start = strpos($src, 'case "' . $name . '": {');
        self::assertNotFalse($start, 'mode « ' . $name . ' » absent');
        $i = (int) strpos($src, '{', (int) $start);
        $depth = 0;
        for ($j = $i, $len = strlen($src); $j < $len; $j++) {
            if ($src[$j] === '{') {
                $depth++;
            } elseif ($src[$j] === '}' && --$depth === 0) {
                return substr($src, $i, $j - $i + 1);
            }
        }

        return '';
    }

    private static function config(): string
    {
        return self::read(self::MAIN . 'config.cpp') . "\n" . self::read(self::MAIN . 'toc_screens3d.hpp') . "\n"
            . self::read(self::MAIN . 'toc_screens_legacy.hpp') . "\n" . self::read(self::MAIN . 'toc_pc.hpp') . "\n"
            . self::read(self::MAIN . 'toc_furniture.hpp');
    }

    /**
     * Même règle que fn_tocHallBuild « _resolve » : objets de la salle dans son repère.
     *
     * @return array{0: list<array{0: string, 1: string, 2: float, 3: float, 4: float, 5: string}>, 1: list<string>}
     */
    private static function resolve(array $room, bool $wall, float $ceil, float $step, string $arc = ''): array
    {
        $k = array_column(self::table('_k'), 1, 0);
        [, , $seats, $entries] = $room;
        $rows = [];
        $skipped = [];
        foreach ($entries as [$role, $cls, $x, $y, $mount, , , , $alt, $ax, $ay]) {
            $ok = match ($mount) {
                'wall' => $wall,
                'ceiling' => $ceil >= $k['ceilMin'] && $ceil <= $k['ceilMax'],
                default => true,
            };
            if ($ok) {
                $rows[] = [$role, $cls, (float) $x, (float) $y, $mount === 'ceiling' ? $ceil : 0.0, $mount];
            } elseif ($alt === '') {
                $skipped[] = $cls;
            } else {
                $rows[] = [$role, $alt, (float) $ax, (float) $ay, 0.0, 'floor'];
            }
        }
        // Mobilier du mod : consoles droites (une pièce pour deux ou trois postes, table _deskPlan) ou une console en
        // arc par poste ; $step vaut alors « seat » ou le pas des consoles en arc.
        $desk = array_column(self::table('_furniture'), 1, 0)['desk'][0];
        if ($arc === '') {
            foreach (array_column(self::table('_deskPlan'), 1, 0)[$seats] as [$cls, $xu]) {
                $rows[] = ['desk', $cls, $xu * $step, (float) $k['deskY'], 0.0, 'floor'];
            }
        }
        for ($i = 0; $i < $seats; $i++) {
            $sx = -(($seats - 1) * $step) / 2 + $i * $step;
            if ($arc !== '') {
                $rows[] = ['desk', $arc, $sx, (float) $k['deskY'], 0.0, 'floor'];
            }
            $rows[] = ['chair', 'chair', $sx, (float) $k['chairY'], 0.0, 'floor'];
            $rows[] = ['pc', 'pc', $sx, (float) $k['pcY'], (float) $desk[1], 'desk'];
        }

        return [$rows, $skipped];
    }

    /** Boîte dans le repère de la salle : [x mini, x maxi, y mini, y maxi, z mini, z maxi]. */
    private static function box(array $row, array $dims): array
    {
        [, $cls, $x, $y, $z] = $row;
        self::assertArrayHasKey($cls, $dims, 'taille inconnue : ' . $cls);
        [$w, $d0, $d1, $z0, $z1] = $dims[$cls];

        return [$x - $w / 2, $x + $w / 2, $y - $d1, $y - $d0, $z + $z0, $z + $z1];
    }

    private static function dims(): array
    {
        $out = [];
        foreach (self::table('_dims') as $d) {
            $out[$d[0]] = array_slice($d, 1);
        }

        return $out;
    }

    public function testEveryClassOfTheCompositionExistsInConfig(): void
    {
        $cfg = self::config();
        $dims = self::dims();
        $rooms = self::table('_rooms');
        self::assertSame(['S', 'M', 'L'], array_column($rooms, 0));
        foreach ($rooms as [$size, $wallY, $seats, $entries]) {
            self::assertGreaterThanOrEqual(2, $seats);
            self::assertGreaterThanOrEqual(6, $wallY);
            foreach ($entries as $e) {
                self::assertCount(12, $e, "salle $size : objet incomplet");
                foreach ([$e[1], $e[8]] as $cls) {
                    if ($cls === '') {
                        continue;
                    }
                    self::assertMatchesRegularExpression('/class\s+' . preg_quote($cls, '/') . '\s*:/', $cfg, "salle $size : classe $cls absente de la config");
                    self::assertArrayHasKey($cls, $dims, "salle $size : taille de $cls absente de _dims");
                }
                self::assertContains($e[0], self::SCREEN_ROLES);
                self::assertContains($e[4], ['wall', 'ceiling', 'floor']);
            }
        }
        // PC portable de TOC (poste par défaut) et mobilier du jeu de base, premier présent retenu.
        self::assertMatchesRegularExpression('/class\s+COMSPEC_ATAK_TocLaptop\s*:/', $cfg);
        $src = self::read(self::BUILD);
        self::assertStringContainsString('["COMSPEC_ATAK_TocLaptop", _post] select _fixed', $src);
        $furn = array_column(self::table('_furniture'), 1, 0);
        // Mobilier du mod d'abord (tests/Unit/AtakTocDeskAssetTest.php), celui du jeu de base en repli.
        self::assertSame('COMSPEC_ATAK_TocDesk_Single', $furn['desk'][0][0]);
        self::assertSame('COMSPEC_ATAK_TocChair', $furn['chair'][0][0]);
        self::assertContains('Land_TableDesk_F', array_column($furn['desk'], 0));
        self::assertContains('Land_OfficeChair_01_F', array_column($furn['chair'], 0));
        foreach ([$furn['desk'][0][0], $furn['chair'][0][0]] as $own) {
            self::assertMatchesRegularExpression('/class\s+' . $own . '\s*:/', $cfg);
        }
        self::assertStringContainsString('_cands findIf { isClass (configFile >> "CfgVehicles" >> (_x select 0)) }', $src);
        // Classe absente du jeu : l'objet est sauté, la salle continue.
        self::assertStringContainsString('if !(isClass (configFile >> "CfgVehicles" >> _cls)) exitWith { [objNull, "classe absente du jeu"] };', $src);
    }

    public function testNoTwoObjectsOverlapWhateverTheSupport(): void
    {
        $dims = self::dims();
        $sizes = ['S' => [8.0, 7.0], 'M' => [11.0, 8.5], 'L' => [16.0, 9.5]];
        foreach (self::table('_rooms') as $room) {
            $size = $room[0];
            foreach ([[true, 3.4], [true, -1.0], [false, 3.4], [false, -1.0]] as [$wall, $ceil]) {
                // Consoles droites (pas d'un poste) et consoles en arc (poste à trois écrans, écran incurvé).
                $kk = array_column(self::table('_k'), 1, 0);
                foreach ([[(float) $kk['seat'], ''], [max((float) $kk['arcStep'], 2.1), 'COMSPEC_ATAK_TocDesk_Curved']] as [$step, $arc]) {
                    [$rows, $skipped] = self::resolve($room, $wall, $ceil, $step, $arc);
                    $desks = count(array_filter($rows, static fn (array $r): bool => $r[0] === 'desk'));
                    self::assertGreaterThanOrEqual(1, $desks);
                    $tag = sprintf('salle %s, mur %s, plafond %s, pas %s', $size, $wall ? 'oui' : 'non', $ceil, $step);
                    self::assertCount(count($room[3]) + $desks + 2 * $room[2], array_merge($rows, $skipped), $tag);
                    $boxes = array_map(static fn (array $r): array => self::box($r, $dims), $rows);
                    $xMin = 0.0;
                    $xMax = 0.0;
                    $yMax = 0.0;
                    foreach ($boxes as $i => $a) {
                        $xMin = min($xMin, $a[0]);
                        $xMax = max($xMax, $a[1]);
                        $yMax = max($yMax, $a[3]);
                        self::assertGreaterThanOrEqual(0, $a[2], $tag . ' : ' . $rows[$i][1] . ' derrière le module');
                        if ($rows[$i][5] !== 'ceiling') {
                            self::assertGreaterThanOrEqual(0, $a[4], $tag . ' : ' . $rows[$i][1] . ' sous le sol');
                        }
                        for ($j = $i + 1, $n = count($boxes); $j < $n; $j++) {
                            $b = $boxes[$j];
                            $hit = $a[0] < $b[1] - 0.001 && $b[0] < $a[1] - 0.001 && $a[2] < $b[3] - 0.001 && $b[2] < $a[3] - 0.001
                                && $a[4] < $b[5] - 0.001 && $b[4] < $a[5] - 0.001;
                            self::assertFalse($hit, sprintf('%s : %s (x %.2f, y %.2f) chevauche %s (x %.2f, y %.2f)', $tag, $rows[$i][1], $rows[$i][2], $rows[$i][3], $rows[$j][1], $rows[$j][2], $rows[$j][3]));
                        }
                    }
                    // Encombrement annoncé dans la description du module et le formulaire Zeus.
                    self::assertLessThanOrEqual($sizes[$size][0], $xMax - $xMin, $tag . ' : largeur annoncée dépassée');
                    self::assertLessThanOrEqual($sizes[$size][1], $yMax, $tag . ' : profondeur annoncée dépassée');
                    // Sans mur ni plafond : toujours un mur d'images de remplacement, rien de mural, rien de suspendu.
                    self::assertContains('wall', array_column($rows, 0), $tag . ' : aucun écran principal');
                    foreach ($rows as $r) {
                        self::assertFalse($r[5] === 'wall' && !$wall, $tag . ' : écran mural sans mur');
                        self::assertFalse($r[5] === 'ceiling' && $ceil < 3, $tag . ' : écran suspendu sans plafond');
                    }
                }
            }
        }
    }

    public function testWallAndCeilingScreensHaveFreeStandingReplacements(): void
    {
        foreach (self::table('_rooms') as [$size, , , $entries]) {
            foreach ($entries as [$role, $cls, , , $mount, $view, , , $alt, , , $altView]) {
                if ($mount === 'floor') {
                    self::assertMatchesRegularExpression('/_(Stand|Truss|Tripod)$/', $cls, "salle $size : $cls posé au sol sans pied");
                    continue;
                }
                if (in_array($role, ['wall', 'side', 'ceiling', 'clock'], true)) {
                    self::assertMatchesRegularExpression('/_(Stand|Truss|Tripod)$/', $alt, "salle $size : $cls sans remplaçant sur pied");
                    self::assertNotSame('', $altView);
                    // Un écran 16:9 sur pied ne prend ni le bandeau ni la plaque.
                    self::assertNotContains($altView, ['STRIP', 'PLATE'], "salle $size : $alt en $altView");
                } else {
                    self::assertSame('', $alt, "salle $size : $cls (bandeau ou plaque) n'a pas d'équivalent sur pied, il est sauté");
                }
                self::assertNotSame('', $view);
            }
        }
        $src = self::read(self::BUILD);
        $build = self::sqfCase($src, 'build');
        // Extérieur, mur absent, objet manquant, repli : dits dans le résumé rendu au Zeus et dans le journal.
        foreach (['"OUT"', '"NOWALL"', '"FAIL"', '"LOCAL"'] as $flag) {
            self::assertMatchesRegularExpression('/_flags pushBack(Unique)? ' . $flag . '/', $src);
            self::assertStringContainsString('[' . $flag . ', "', self::sqfCase($src, 'done'));
        }
        self::assertStringContainsString('non posé classe=%1 : pas de %2 à cet endroit', self::sqfCase($src, 'finish'));
    }

    public function testOneGuardedStepPerObjectAndNoEarlyExitInTheLoop(): void
    {
        $src = self::read(self::BUILD);
        $build = self::sqfCase($src, 'build');
        $start = strpos($build, 'private _n = count _plan;');
        $end = strpos($build, '} forEach _plan;');
        self::assertNotFalse($start);
        self::assertNotFalse($end);
        $loop = substr($build, (int) $start, (int) $end - (int) $start);
        // La boucle ne pose rien elle-même : un rappel par objet (4.7.0 : une erreur dans la boucle arrêtait toute la pose).
        self::assertStringNotContainsString('exitWith', $loop);
        self::assertStringNotContainsString('createVehicle', $loop);
        self::assertStringContainsString('[{ ["step", _this] call comspec_atak_native_fnc_tocHallBuild; }, [_ctx, _x, _forEachIndex],', $loop);
        $step = self::sqfCase($src, 'step');
        self::assertStringContainsString('if (isNil "_res" || {!(_res isEqualType [])}) then { _res = [objNull, "erreur de script', $step);
        self::assertStringContainsString('salle | objet %1/%2 classe=%3 ok', $step);
        self::assertStringContainsString('salle | objet %1/%2 classe=%3 échec %4', $step);
        self::assertStringContainsString('diag_log text ("[COMSPEC TOC] " + _txt);', $src);
        // Salle inscrite avant la pose (les écrans s'y lient), mobilier inscrit au bilan ; résumé final.
        self::assertLessThan($start, (int) strpos($build, '[_id, _nm, _sd, [], "LOCAL" in _flags] call _register;'));
        $finish = self::sqfCase($src, 'finish');
        self::assertStringContainsString('[_id, _nm, _sd, _props, "LOCAL" in _flags] call _register;', $finish);
        self::assertStringContainsString('posée : %3 objet(s) sur %4', $finish);
        self::assertStringContainsString('_o setVariable ["COMSPEC_ATAK_TocHall", _id, true];', $step);
        self::assertStringContainsString('COMSPEC_ATAK_TocHallMaster', $finish);
    }

    public function testPositionsUseTheFloorFoundUnderTheModule(): void
    {
        $src = self::read(self::BUILD);
        $build = self::sqfCase($src, 'build');
        self::assertStringContainsString('lineIntersectsSurfaces [_p0, _p1, _ign, objNull, true, 4, "GEOM", "FIRE"]', $src);
        // Sol sous le module, plafond au-dessus, mur devant, puis sol sous chaque objet.
        self::assertStringContainsString('private _p = ATLToASL [_pos select 0, _pos select 1, (_pos param [2, 0]) max 0];', $build);
        self::assertStringContainsString('[_p vectorAdd [0, 0, 1.2], _p vectorAdd [0, 0, -40]] call _hit', $build);
        self::assertStringContainsString('[_org vectorAdd [0, 0, 0.5], _org vectorAdd [0, 0, 12]] call _hit', $build);
        self::assertStringContainsString('getTerrainHeightASL', $build);
        $step = self::sqfCase($src, 'step');
        self::assertStringContainsString('_o setPosASL _at;', $step);
        self::assertStringContainsString('_o setVectorDirAndUp [[sin _d, cos _d, 0], [0, 0, 1]];', $step);
        self::assertLessThan(strpos($step, '_o setVectorDirAndUp'), strpos($step, '_o setPosASL _at;'));
        self::assertStringNotContainsString('getPos [', $step);
        self::assertStringNotContainsString('setPosATL', $step);
        // Plus de position prise telle quelle à la hauteur du module (ancienne pose : _pos getPos […], setPosATL).
        self::assertStringNotContainsString('getPos [', $build);
        self::assertStringNotContainsString('setPosATL', $build);
        // Tout le plan est calculé avant la première pose : un objet déjà posé ne fausse aucune mesure.
        self::assertLessThan(strpos($build, 'private _n = count _plan;'), strpos($build, 'private _plan = _rows apply {'));
        // Bureau figé une fois posé ; écrans, PC et chaise gardent leur simulation (menu ACE).
        self::assertStringContainsString('if (_role in ["desk", "prop"]) then { if (isServer) then { _o enableSimulationGlobal false; } else { _o enableSimulation false; }; };', $step);
    }

    public function testEdenAndZeusCallTheSameBuilder(): void
    {
        $src = self::read(self::BUILD);
        $mod = self::read(self::MAIN . 'functions/toc/fn_moduleTocHall.sqf');
        $hall = self::read(self::MAIN . 'functions/toc/fn_tocHall.sqf');
        self::assertStringContainsString('class tocHallBuild {};', self::read(self::MAIN . 'config.cpp'));
        // Module : Eden et Zeus passent par fn_tocHallBuild, rien n'est posé dans le module lui-même.
        self::assertStringContainsString('["eden", _this] call comspec_atak_native_fnc_tocHallBuild;', $mod);
        self::assertStringContainsString('["zeus", [_pos, _dir, _size,', $mod);
        self::assertStringNotContainsString('createVehicle', $mod);
        self::assertStringContainsString('if (is3DEN) exitWith { true };', $mod);
        // Module lâché au Zeus sans attributs Eden : jamais pris pour un module de la mission.
        self::assertStringContainsString('isNil { _logic getVariable "Size" }', $mod);
        // Les trois chemins (Eden, Zeus sur ce poste ou en repli, Zeus par le serveur) : « build ».
        self::assertStringContainsString('["build", [_pos, _dir, _size, _nm, _sd, _id, _post, false, false]] call _fn', self::sqfCase($src, 'eden'));
        self::assertSame(2, substr_count(self::sqfCase($src, 'zeus'), '["build", [_pos, _dir, _size, _nm, _sd, _id, _post, true, '));
        self::assertStringContainsString('["build", [_pos, _dir, _size, _nm, _sd, _id, _post, _snap, false, _by]] call comspec_atak_native_fnc_tocHallBuild', $hall);
        self::assertStringContainsString('["build", [_pos, _dir, _size, _nm, _sd, _id, _post, false, false]] call comspec_atak_native_fnc_tocHallBuild', self::sqfCase($hall, 'spawnRoom'));
        // Un seul endroit crée les objets : plus de pose dans fn_tocHall.
        self::assertStringNotContainsString('createVehicle', self::sqfCase($hall, 'spawnRoom'));
        self::assertSame(1, substr_count($src, 'createVehicle ['));
        self::assertSame(1, substr_count($src, '_o = ["spawn", [ASLToATL _at, _d, _cls,'));
        // Serveur plus ancien : le poste du Zeus pose la salle, marquée non gardée ; serveur muet 8 s : même repli.
        $zeus = self::sqfCase($src, 'zeus');
        self::assertStringContainsString('if (isServer || {["local", objNull] call comspec_atak_native_fnc_tocConfig}) exitWith {', $zeus);
        self::assertStringContainsString('["do", ["spawn", [_pos, _dir, _size, _nm, _sd, _id, _post, player, true]]] call comspec_atak_native_fnc_tocHall;', $zeus);
        self::assertStringContainsString('if (!isServer && {!(["local", objNull] call comspec_atak_native_fnc_tocConfig)}) exitWith {', self::sqfCase($src, 'build'));
        // Résultat rendu au Zeus par sa ligne de retour, jamais une alerte du jeu.
        self::assertStringContainsString('["comspec_atak_native_tocHallDone", [_res], _by] call CBA_fnc_targetEvent;', self::sqfCase($src, 'finish'));
        self::assertStringContainsString('["comspec_atak_native_tocHallDone", { ["done", _this] call comspec_atak_native_fnc_tocHallBuild; }] call CBA_fnc_addEventHandler;', $hall);
        self::assertStringContainsString('["feedback", [_t]] call comspec_atak_native_fnc_zeusMenu;', self::sqfCase($src, 'done'));
        foreach ([$src, $mod] as $code) {
            self::assertDoesNotMatchRegularExpression('/\b(hint|hintSilent|hintC|systemChat|BIS_fnc_showNotification|BIS_fnc_guiMessage)\b/', $code);
        }
    }

    public function testKeptRoomIsRecognisedByItsScreensNotByItsName(): void
    {
        $src = self::read(self::BUILD);
        $eden = self::sqfCase($src, 'eden');
        $mod = self::read(self::MAIN . 'functions/toc/fn_moduleTocHall.sqf');
        // Avant : fn_tocHall « saved » (un nom dans la sauvegarde de la carte) faisait sauter toute la pose.
        self::assertStringNotContainsString('["saved"', $mod);
        self::assertStringNotContainsString('["saved"', $eden);
        self::assertStringContainsString('{ missionNamespace getVariable ["COMSPEC_ATAK_TocRestored", false] }', $mod);
        self::assertStringContainsString('(_x distance2D _pos) < (_kv get "keepRadius")', $eden);
        self::assertStringContainsString('if (_known && {_have >= ((ceil (_want / 2)) max 2)}) then {', $eden);
        // Reprise incomplète : retirée puis reposée en entier, jamais « un objet puis plus rien ».
        self::assertStringContainsString('["remove", _id] call _fn;', $eden);
        // Deux modules du même nom à deux endroits : deux salles.
        self::assertStringContainsString('format ["E-%1-%2-%3", _nm,', $eden);
        // Réglage « Supprimer la salle avec le module ».
        self::assertStringContainsString('_logic addEventHandler ["Deleted", {', $eden);
        $hpp = self::read(self::MAIN . 'modules.hpp');
        self::assertStringContainsString('property = "COMSPEC_ATAK_Module_TocHall_Bind";', $hpp);
        self::assertStringContainsString('_logic getVariable ["Bind", true]', $mod);
        self::assertStringContainsString('case "roomRemove": { ["remove", _args] call comspec_atak_native_fnc_tocHallBuild; };', self::read(self::MAIN . 'functions/toc/fn_tocHall.sqf'));
    }

    public function testSelfTestAndTextsFrenchAndEnglish(): void
    {
        $src = self::read(self::BUILD);
        $self = self::sqfCase($src, 'selfTest');
        self::assertStringContainsString('forEach [[true, 3.4], [true, -1], [false, 3.4], [false, -1]];', $self);
        self::assertStringContainsString('isClass (configFile >> "CfgVehicles" >> (_x select 1))', $self);
        self::assertStringContainsString('chevauchements', $self);
        self::assertStringNotContainsString('createVehicle', $self);
        $en = self::read(self::MAIN . 'i18n/en.txt');
        $xx = self::read('mod/COMSPEC_ATAK_Native/localisation/template/xx.txt');
        $mod = self::read(self::MAIN . 'functions/toc/fn_moduleTocHall.sqf');
        preg_match_all('/\["(?:OUT|NOWALL|FAIL|LOCAL)", "([^"]+)"\]/', $src, $notes);
        preg_match_all('/\["((?:Petite|Moyenne|Grande), [^"]+)", "[SML]"\]/', $mod, $labels);
        preg_match_all('/\("(ATAK : salle TOC [^"]+)"\) call comspec_atak_native_fnc_tr/', $src, $msgs);
        $texts = array_merge($notes[1], $labels[1], $msgs[1]);
        self::assertCount(9, $texts);
        foreach ($texts as $fr) {
            self::assertStringContainsString("\n[\"" . $fr . '", "', $en, "« $fr » sans anglais");
            self::assertStringContainsString("\n[\"" . $fr . '", "", "', $xx, "« $fr » absent du gabarit de traduction");
        }
        // Stringtable : encombrement de chaque taille dans la description et les tailles, nouveau réglage.
        $st = self::read(self::MAIN . 'stringtable.xml');
        foreach (['TOCHALL_DESC' => ['8 x 7 m', '11 x 8.5 m', '16 x 9.5 m'], 'TOCHALL_Z_S' => ['8 x 7 m'], 'TOCHALL_Z_M' => ['11 x 8.5 m'], 'TOCHALL_Z_L' => ['16 x 9.5 m'],
            'TOCHALL_B_NAME' => [], 'TOCHALL_B_TIP' => ['Example']] as $key => $needles) {
            self::assertSame(1, preg_match('/<Key ID="STR_COMSPEC_ATAK_CFG_' . $key . '">\s*<Original>[^<]+<\/Original>\s*<English>([^<]+)<\/English>\s*<French>([^<]+)<\/French>/', $st, $t), $key);
            foreach ($needles as $needle) {
                self::assertStringContainsString($needle, $t[1], $key);
            }
            self::assertNotSame($t[1], $t[2], $key . ' : français identique à l\'anglais');
        }
    }
}
