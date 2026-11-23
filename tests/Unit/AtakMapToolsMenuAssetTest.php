<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Menu OUTILS de la carte du téléphone : une table unique (fn_mapToolMenu « table ») rangée par intention, des
 * bascules distinctes des outils, « Tout effacer » en deux appuis, et un glisser de la carte qui ne pose rien et ne
 * ferme ni l'outil ni le menu (fn_mapMouseButtonDown / Moving / Up).
 */
final class AtakMapToolsMenuAssetTest extends TestCase
{
    private const MAIN = 'mod/COMSPEC_ATAK_Native/Sources/addons/main/';

    private static function read(string $rel): string
    {
        $raw = (string) file_get_contents(dirname(__DIR__, 2) . '/' . self::MAIN . $rel);

        return str_replace("\r\n", "\n", $raw);
    }

    /** @return list<array{id: string, group: string, label: string, tip: string, icon: string, type: string, key: string}> */
    private static function table(): array
    {
        $menu = self::read('functions/map/fn_mapToolMenu.sqf');
        preg_match_all('/^\s*\["([A-Z]+)", "([A-Z]+)", "([^"]+)", "([^"]+)", "(\w+)", "(\w+)", "([^"]*)", /mu', $menu, $m, PREG_SET_ORDER);
        $rows = [];
        foreach ($m as $r) {
            $rows[] = ['id' => $r[1], 'group' => $r[2], 'label' => $r[3], 'tip' => $r[4], 'icon' => $r[5], 'type' => $r[6], 'key' => $r[7]];
        }

        return $rows;
    }

    public function testTableIsUniqueAndGroupedByIntent(): void
    {
        $rows = self::table();
        self::assertGreaterThanOrEqual(20, \count($rows));
        $ids = array_column($rows, 'id');
        self::assertSame($ids, array_values(array_unique($ids)), 'ids uniques');

        $menu = self::read('functions/map/fn_mapToolMenu.sqf');
        self::assertStringContainsString('private _groups = [["ALLER", "ALLER"], ["MESURER", "MESURER"], ["PLANIFIER", "PLANIFIER"], ["DESSINER", "DESSINER"], ["AFFICHER", "AFFICHER"], ["PARTAGER", "PARTAGER / DONNÉES"]];', $menu);
        $expected = [
            'ALLER' => ['ROUTE', 'WP', 'SEARCH'],
            'MESURER' => ['MEASURE', 'HEIGHT', 'LOS', 'DISTANCE'],
            'PLANIFIER' => ['FLAT', 'IPTGT', 'DRONE'],
            'DESSINER' => ['LINE', 'DRAW', 'HOUSES'],
            'AFFICHER' => ['COMPASS', 'GRID', 'ZONES', 'SIGINT', 'LABELS'],
            'PARTAGER' => ['PHONE', 'CLEAR'],
        ];
        $byGroup = [];
        foreach ($rows as $r) {
            $byGroup[$r['group']][] = $r['id'];
        }
        self::assertSame($expected, $byGroup);
        // Entrées d'une rubrique contiguës (pas de rubrique éparpillée) et dans l'ordre des rubriques.
        self::assertSame(array_merge(...array_values($expected)), $ids);
    }

    public function testTypesTogglesAndShortcuts(): void
    {
        $rows = self::table();
        $allowedTools = self::read('functions/map/fn_mapToolSet.sqf');
        $keys = [];
        foreach ($rows as $r) {
            self::assertContains($r['type'], ['outil', 'bascule', 'action'], $r['id']);
            // Un outil est un mode de la carte reconnu par fn_mapToolSet (identifiants conservés).
            if ($r['type'] === 'outil') {
                self::assertStringContainsString('"' . $r['id'] . '"', $allowedTools, $r['id']);
            }
            // Libellé court (le détail est dans l'info-bulle), sans « … » de troncature.
            self::assertLessThanOrEqual(24, mb_strlen($r['label']), $r['label']);
            self::assertStringNotContainsString('…', $r['label']);
            self::assertNotSame($r['label'], $r['tip'], $r['id']);
            if ($r['key'] !== '') {
                self::assertMatchesRegularExpression('/^(⇧)?[0-9]$/u', $r['key'], $r['id']);
                $keys[] = $r['key'];
            }
        }
        self::assertSame($keys, array_values(array_unique($keys)), 'raccourcis sans collision');
        // Bascules d'affichage : jamais des outils, et l'inverse.
        $toggles = array_column(array_filter($rows, static fn (array $r): bool => $r['type'] === 'bascule'), 'id');
        sort($toggles);
        self::assertSame(['COMPASS', 'DISTANCE', 'GRID', 'LABELS', 'SIGINT', 'ZONES'], $toggles);
        // « Tout effacer » : action, sans raccourci, en dernier.
        $last = $rows[\count($rows) - 1];
        self::assertSame(['CLEAR', 'action', ''], [$last['id'], $last['type'], $last['key']]);
    }

    public function testClearAllNeedsTwoPresses(): void
    {
        $menu = self::read('functions/map/fn_mapToolMenu.sqf');
        $start = strpos($menu, 'case "CLEAR": {');
        self::assertNotFalse($start);
        $block = substr($menu, (int) $start, (int) strpos($menu, 'default {', (int) $start) - (int) $start);
        self::assertStringContainsString('if ((diag_tickTime - (_s getOrDefault ["mapClearArm", -10])) >= 3) then {', $block);
        self::assertStringContainsString('_s set ["mapClearArm", diag_tickTime];', $block);
        // Le premier appui n'efface rien : l'effacement est dans la branche du second appui seulement.
        $first = substr($block, 0, (int) strpos($block, '} else {'));
        self::assertStringNotContainsString('forEach ["mapMeasure"', $first);
        self::assertStringContainsString('Confirmer ? (3 s)', $menu);
    }

    public function testDragNeverPlacesNorClosesTheTool(): void
    {
        $moving = self::read('functions/map/fn_mapMouseMoving.sqf');
        self::assertStringContainsString('if (_d2 > 36 || {_d2 > 9 && {!(_s getOrDefault ["mapLcOk", false])} && {(diag_tickTime - (_s getOrDefault ["mapLcAt", diag_tickTime])) >= 0.15}}) then {', $moving);
        self::assertStringContainsString('_s set ["mapDrag", true];', $moving);

        $up = self::read('functions/map/fn_mapMouseButtonUp.sqf');
        self::assertStringContainsString('if (!_drag && {((((_mx - _x0) / pixelW) ^ 2) + (((_my - _y0) / pixelH) ^ 2)) <= 36} && {!([_x0, _y0] call _onUi)}) then {', $up);
        // Un vrai clic avec le menu ouvert le ferme seulement ; sinon l'outil agit.
        self::assertStringContainsString('if !(["close"] call comspec_atak_native_fnc_mapToolMenu) then {', $up);

        $down = self::read('functions/map/fn_mapMouseButtonDown.sqf');
        // À l'appui gauche, seul DESSIN démarre (un trait ne pose plus son point A à l'appui).
        self::assertStringContainsString('{(_s getOrDefault ["mapMode", "SELECT"]) isEqualTo "DRAW"}', $down);
        self::assertStringContainsString('isEqualTo "LINE") exitWith { ["DOWN", [_world select 0, _world select 1, 0], _ctrl] call comspec_atak_native_fnc_markerStroke; true };', $down);
        // L'appui droit ne quitte plus l'outil : seulement le relâchement sans glisser.
        $right = substr($down, (int) strpos($down, 'if (_button isEqualTo 1) exitWith {'));
        self::assertStringNotContainsString('mapToolSet', $right);
        self::assertStringContainsString('["SELECT"] call comspec_atak_native_fnc_mapToolSet;', $up);
    }

    public function testMenuIsBuiltOnOpenNotPerFrameAndClosesProperly(): void
    {
        self::assertStringNotContainsString('fnc_mapToolMenu', self::read('functions/map/fn_mapOnDraw.sqf'));
        $page = self::read('functions/pages/fn_pageMap.sqf');
        self::assertStringContainsString('call comspec_atak_native_fnc_mapToolMenu;', $page);
        self::assertStringContainsString('_s set ["mapToolbarRect",', $page);
        $load = self::read('functions/ui/fn_displayLoad.sqf');
        self::assertStringContainsString('["esc"] call comspec_atak_native_fnc_mapToolMenu', $load);
        $menu = self::read('functions/map/fn_mapToolMenu.sqf');
        self::assertStringContainsString('if ((count _vis) > 12) then {', $menu);
        self::assertStringContainsString('COMSPEC_ATAK_MapToolRecent', $menu);
    }

    public function testNoOnScreenMessagesOutsideThePhone(): void
    {
        foreach (['functions/map/fn_mapToolMenu.sqf', 'functions/map/fn_mapMouseButtonDown.sqf', 'functions/map/fn_mapMouseButtonUp.sqf', 'functions/map/fn_mapMouseMoving.sqf'] as $f) {
            $code = self::read($f);
            self::assertDoesNotMatchRegularExpression('/\b(hint|hintSilent|hintC|systemChat|cutText|titleText|cutRsc|titleRsc|sideChat|globalChat)\b/', $code, $f);
        }
    }

    public function testEnglishTranslationsExist(): void
    {
        $en = self::read('i18n/en.txt');
        preg_match_all('/^\["(.*?)", "/mu', $en, $m);
        $keys = array_flip($m[1]);
        $texts = ['OUTILS', 'RÉCENTS', 'ALLER', 'MESURER', 'PLANIFIER', 'DESSINER', 'AFFICHER', 'PARTAGER / DONNÉES', 'Confirmer ? (3 s)', 'Chercher un outil', 'Aucun outil trouvé', 'Fermer le menu (Échap)', 'Mesures et relevés effacés'];
        foreach (self::table() as $r) {
            $texts[] = $r['label'];
            $texts[] = $r['tip'];
        }
        foreach ($texts as $fr) {
            self::assertArrayHasKey($fr, $keys, 'en.txt : ' . $fr);
        }
        // Les clés chinoises existantes restent (fichier non touché par le menu).
        self::assertStringContainsString('["Tout effacer", ', self::read('i18n/zh_cn.txt'));
    }
}
