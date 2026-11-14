<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** ATAK 4.17.0 (vague d'optimisation et de débogage) : fige les correctifs et les optimisations branchés dans le mod. */
final class Atak4170WaveAssetTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../';
    private const MAIN = self::ROOT . 'mod/COMSPEC_ATAK_Native/Sources/addons/main/';

    private function read(string $path): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(self::MAIN . $path));
    }

    /** Équilibre des accolades, crochets et parenthèses hors chaînes et commentaires. */
    private function assertBalanced(string $path): void
    {
        $code = preg_replace('~/\*.*?\*/~s', '', $this->read($path)) ?? '';
        $code = preg_replace('~//[^\r\n]*~', '', $code) ?? '';
        $code = preg_replace('~"(?:[^"]|"")*"~s', '""', $code) ?? '';
        $code = preg_replace("~'(?:[^']|'')*'~s", "''", $code) ?? '';
        foreach (['{' => '}', '[' => ']', '(' => ')'] as $open => $close) {
            self::assertSame(substr_count($code, $open), substr_count($code, $close), "équilibre $open$close dans $path");
        }
    }

    // ── Lot 1 : bogues ──────────────────────────────────────────────────────────────────────────────────────────

    public function testPhoneCbaChoicesAreStoredInTheProfile(): void
    {
        // 5e paramètre de CBA_settings_fnc_set (_store) : sans lui, le choix ne vaut que pour la partie en cours.
        $pin = $this->read('functions/map/fn_mapPinnedApp.sqf');
        self::assertStringContainsString('[_var, _v, false, "client", true] call CBA_settings_fnc_set;', $pin);

        $settings = $this->read('functions/pages/fn_pageSettings.sqf');
        self::assertStringContainsString('[_k, _new, false, "client", true] call CBA_settings_fnc_set;', $settings);
        self::assertStringContainsString('[_k, _v, false, "client", true] call CBA_settings_fnc_set;', $settings);
        self::assertStringContainsString('["comspec_atak_native_full_scale", _v, false, "client", true] call CBA_settings_fnc_set;', $settings);
        self::assertStringContainsString('["comspec_atak_native_language", "fr", 0, "client", true] call CBA_settings_fnc_set;', $settings);

        $map = $this->read('functions/pages/fn_pageMap.sqf');
        self::assertStringContainsString('[_var, !(missionNamespace getVariable [_var, _def]), false, "client", true] call CBA_settings_fnc_set;', $map);

        // Plus aucun appel du téléphone sans enregistrement.
        foreach ([$pin, $settings, $map] as $src) {
            self::assertSame(0, preg_match('~["\']client["\']\] call CBA_settings_fnc_set~', $src), 'appel CBA sans enregistrement');
        }
    }

    public function testPinnedBannerFollowsAppPolicyAndOrderList(): void
    {
        $pin = $this->read('functions/map/fn_mapPinnedApp.sqf');
        // App interdite, retirée ou désinstallée : pas de bandeau.
        self::assertStringContainsString('(count _app) > 0 && {["installed", _app get "id"] call comspec_atak_native_fnc_appInstall}', $pin);
        self::assertStringNotContainsString('case "app": { _page };', $pin);
        // Ordre montré : même liste que le nombre de fn_appBadge.
        self::assertStringContainsString('private _closed = ["ACK", "EXEC", "FAILED", "DONE", "COMPLETED", "SUCCEEDED", "CANCELLED", "CANCELED"];', $pin);
        self::assertStringContainsString('private _done = ["ACK", "EXEC", "FAILED", "DONE", "COMPLETED", "CANCELLED", "CANCELED"];', $this->read('functions/ui/fn_appBadge.sqf'));
        // Coupe en caractères, pas en octets.
        self::assertStringContainsString('forceUnicode 1;' . "\n" . '    private _len = count _t;', $pin);
        self::assertStringContainsString('if (_len > _n) then { forceUnicode 1; _t = (_t select [0, (_n - 1) max 1]) + "…"; };', $pin);
        $this->assertBalanced('functions/map/fn_mapPinnedApp.sqf');
    }

    public function testOperatorPrecedenceAndForEachIndexFixes(): void
    {
        $sheet = $this->read('functions/pages/fn_waveRelaySheet.sqf');
        self::assertStringContainsString('_rows append ([' . "\n", $sheet);
        self::assertStringContainsString('] apply { [_x select 0, (_x select 1) call _lvl, _x select 2, _x select 3] });', $sheet);

        // _forEachIndex n'existe que dans forEach.
        foreach (['functions/pages/fn_firesAction.sqf', 'functions/reports/fn_reportAuto.sqf'] as $path) {
            self::assertSame(0, preg_match('~apply \{[^{}\n]*_forEachIndex~', $this->read($path)), "_forEachIndex dans apply ($path)");
            $this->assertBalanced($path);
        }
        $fires = $this->read('functions/pages/fn_firesAction.sqf');
        self::assertSame(2, substr_count($fires, '{ _kv pushBack format [\'"line%1":"%2"\', _forEachIndex + 1, [_x] call _esc]; } forEach _lines;'));
        $auto = $this->read('functions/reports/fn_reportAuto.sqf');
        self::assertStringContainsString('{ _s pushBack [player distance2D _x, _forEachIndex]; } forEach _all;', $auto);
        self::assertStringContainsString('{ _s pushBack [player distance _x, _forEachIndex]; } forEach _cands;', $auto);

        // App Store : toLower sur le texte joint, pas sur le tableau.
        self::assertStringContainsString('(toLower (([_x get "name", _x get "section", _x get "desc"] call comspec_atak_native_fnc_tr) joinString " ")) find _q', $this->read('functions/pages/fn_pageStore.sqf'));

        // Bilan de performance : count sur les valeurs de la table.
        self::assertStringContainsString('{ ((_x select 1) select [0, 4]) isEqualTo "PFH " } count (values _orig);', $this->read('functions/core/fn_perfLog.sqf'));

        // Capture de la carte : la fonction est relancée en spawn (un « spawn _body » perdait les variables du fichier).
        $cap = $this->read('functions/camera/fn_mapCapture.sqf');
        self::assertStringContainsString('["sync"] spawn comspec_atak_native_fnc_mapCapture;', $cap);
        self::assertStringNotContainsString('[] spawn _body;', $cap);
    }

    public function testAccentedPrefixesAreComparedWithFind(): void
    {
        $debug = $this->read('functions/pages/fn_pageDebug.sqf');
        self::assertStringContainsString('((_x select 2) find "ATAK·") isEqualTo 0', $debug);
        self::assertStringContainsString('((_x select 2) find "OW·") isEqualTo 0', $debug);
        self::assertStringContainsString('(_lbl find "VIDÉO DU") isEqualTo 0', $this->read('functions/pages/fn_pageMap.sqf'));
    }

    public function testPlayerHandlersWaitForThePlayer(): void
    {
        $post = $this->read('XEH_postInitClient.sqf');
        // Plus aucun gestionnaire posé sur « player » au premier niveau du postInit (joueur nul en arrivée tardive).
        self::assertSame(0, preg_match('~^(?:    )?player addEventHandler~m', preg_replace('~\[\{ !isNull player \}, \{\n.*?\n(?:    )?\}\] call CBA_fnc_waitUntilAndExecute;~s', '', $post) ?? ''));
        self::assertStringNotContainsString('[player] call _add;', $post);
        self::assertStringContainsString('[player] call (missionNamespace getVariable ["COMSPEC_ATAK_BatterySwapAction", {}]);', $post);
        self::assertGreaterThanOrEqual(5, substr_count($post, "[{ !isNull player }, {\n"));
    }

    // ── Lot 1 : performance ─────────────────────────────────────────────────────────────────────────────────────

    public function testRelayLabelsAreSkippedOutOfView(): void
    {
        $mesh = $this->read('functions/map/fn_meshDraw.sqf');
        self::assertStringContainsString('private _va = _map ctrlMapScreenToWorld [_vx - 1, _vy - 0.08];', $mesh);
        self::assertStringContainsString('if (!_txt || {(_p select 0) < _vx0} || {(_p select 0) > _vx1} || {(_p select 1) < _vy0} || {(_p select 1) > _vy1}) then { continue };', $mesh);
        $this->assertBalanced('functions/map/fn_meshDraw.sqf');
    }

    public function testPlanGeometryIsKeptBetweenFrames(): void
    {
        $plan = $this->read('functions/plan/fn_planDraw.sqf');
        self::assertStringContainsString('private _geo = uiNamespace getVariable ["COMSPEC_ATAK_PlanGeo", createHashMap];', $plan);
        self::assertStringContainsString('private _keep = !(_id in ["", "_draft"]);', $plan);
        self::assertStringContainsString('private _gk = if (_keep) then { hashValue _pts } else { "" };', $plan);
        self::assertStringContainsString('_g params ["_bx0", "_bx1", "_by0", "_by1", "_p3"];', $plan);
        // Table bornée, et plus de copie des points à chaque image.
        self::assertStringContainsString('if ((count _geo) > 600) then { _geo = createHashMap; };', $plan);
        self::assertStringNotContainsString('private _p3 = _pts apply', $plan);
        $this->assertBalanced('functions/plan/fn_planDraw.sqf');
    }

    public function testMapDrawIsMeasuredAndCompassExitsEarly(): void
    {
        $draw = $this->read('functions/map/fn_mapOnDraw.sqf');
        self::assertStringContainsString('["carte maillage et relais (image)", _perfT] call comspec_atak_native_fnc_perfAcc;', $draw);
        self::assertStringContainsString('["carte plan de briefing (image)", _perfPl] call comspec_atak_native_fnc_perfAcc;', $draw);
        $en = $this->read('i18n/en.txt');
        self::assertStringContainsString('["carte maillage et relais (image)", "mesh and relays map (frame)"],', $en);
        self::assertStringContainsString('["carte plan de briefing (image)", "briefing plan on the map (frame)"],', $en);

        // Boussole : sortie avant le préambule quand le cap n'a pas bougé.
        $ov = $this->read('functions/map/fn_mapOverlayUpdate.sqf');
        $exit = strpos($ov, 'if (_compassOnly && {(abs ((getDirVisual (vehicle player)) - (_ov getOrDefault ["compassH", -1]))) < 0.2}) exitWith { false };');
        self::assertNotFalse($exit);
        self::assertLessThan(strpos($ov, 'private _bc = ["band"] call comspec_atak_native_fnc_mapPrefs;'), $exit);
        $this->assertBalanced('functions/map/fn_mapOverlayUpdate.sqf');
    }

    // ── Lot 2 : mini-apps de la carte ───────────────────────────────────────────────────────────────────────────

    /** Clés de la table des apps épinglables de fn_mapPinnedApp, dans l'ordre. */
    private function pinnedKeys(): array
    {
        self::assertSame(1, preg_match('~private _apps = (\[\[.*?\]\]);~', $this->read('functions/map/fn_mapPinnedApp.sqf'), $m));
        preg_match_all('~\["([A-Z]+)", "[^"]+"\]~u', $m[1], $keys);

        return $keys[1];
    }

    public function testMiniAppsReadDataThatExists(): void
    {
        $pin = $this->read('functions/map/fn_mapPinnedApp.sqf');
        self::assertSame(['CHAT', 'SMS', 'TASK', 'WAYPOINTS', 'FIRES', 'MEDICAL', 'ALERTS', 'GARMIN'], $this->pinnedKeys());

        // Chaque mini-app : son cas, la donnée lue, et le fichier qui l'écrit.
        foreach ([
            'WAYPOINTS' => ['COMSPEC_ATAK_Waypoints', 'functions/nav/fn_wpAction.sqf'],
            'FIRES' => ['COMSPEC_ATAK_FsMsns', 'functions/pages/fn_fireSupport.sqf'],
            'MEDICAL' => ['COMSPEC_ATAK_MedevacReqs', 'functions/pages/fn_medicalAction.sqf'],
            'ALERTS' => ['COMSPEC_ATAK_AlertLog', 'functions/pages/fn_alertsLog.sqf'],
            'GARMIN' => ['COMSPEC_ATAK_WatchM', 'functions/garmin/fn_watch.sqf'],
        ] as $page => [$var, $source]) {
            self::assertStringContainsString('case "' . $page . '": {', $pin);
            self::assertStringContainsString('["' . $var . '", ', $pin, $page);
            self::assertStringContainsString($var, $this->read($source), $source);
        }
        // Le toucher ouvre la page de la clé : chaque clé est une app du téléphone.
        $cfg = $this->read('config.cpp');
        foreach ($this->pinnedKeys() as $page) {
            self::assertStringContainsString('page="' . $page . '";', $cfg, $page);
        }
        // Distance et cap au même format que le reste de la carte, sans fonction nouvelle.
        self::assertStringContainsString('["dist", _d, _az] call comspec_atak_native_fnc_mapFmt, [_az, 8] call comspec_atak_native_fnc_cardinal', $pin);
        // Heure H : même base de temps que la montre.
        self::assertStringContainsString('[time, serverTime] select isMultiplayer', $pin);
        self::assertStringContainsString('private _now = [time, serverTime] select isMultiplayer;', $this->read('functions/garmin/fn_watch.sqf'));
        // Mission de tir et demande MEDEVAC terminées : plus montrées.
        self::assertStringContainsString('in ["EOM", "FAIL"]', $pin);
        self::assertStringContainsString('(_x getOrDefault ["status", 4]) < 4', $pin);
        // Règle : aucune notification, aucun toast, aucun message d'Arma.
        foreach (['fnc_notify', 'fnc_toast', 'systemChat', 'hint ', 'hintSilent', 'titleText', 'cutText'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $pin, $forbidden);
        }
        $this->assertBalanced('functions/map/fn_mapPinnedApp.sqf');
    }

    public function testUpToThreeAppsArePinnedWithTheSameSettings(): void
    {
        $pin = $this->read('functions/map/fn_mapPinnedApp.sqf');
        // Le réglage de la 4.16.0 reste le premier emplacement : le choix déjà enregistré chez les joueurs est gardé.
        self::assertStringContainsString('private _vars = ["comspec_atak_native_map_pinned_app", "comspec_atak_native_map_pinned_app_2", "comspec_atak_native_map_pinned_app_3"];', $pin);
        self::assertStringContainsString('if (_v in _keys) then { _sel pushBackUnique _v; };', $pin);

        // Les trois réglages CBA ont la même liste que la table, « Aucune » par défaut, et sont par joueur.
        $pre = $this->read('XEH_preInit.sqf');
        self::assertStringContainsString('[["none", "' . implode('", "', $this->pinnedKeys()) . '"], [', $pre);
        self::assertStringContainsString('"Alertes", "Heure H"], 0], 0,', $pre);
        foreach (['', '_2', '_3'] as $slot) {
            self::assertSame(1, substr_count($pre, '["comspec_atak_native_map_pinned_app' . $slot . '", "App épinglée sur la carte'), $slot);
        }

        // Bascule : retirée si épinglée, sinon ajoutée ; la quatrième remplace la troisième ; enregistrée dans le profil.
        self::assertStringContainsString('private _new = if (_page in _sel) then { _sel - [_page] } else { [[], (_sel select [0, (count _vars) - 1]) + [_page]] select (_page in _keys) };', $pin);
        self::assertStringContainsString('if ((missionNamespace getVariable [_var, "none"]) isNotEqualTo _v) then { [_var, _v, false, "client", true] call CBA_settings_fnc_set; };', $pin);
        self::assertStringContainsString("['toggle', [%1]] call comspec_atak_native_fnc_mapPinnedApp;", $pin);
        // Même ligne dans le panneau SITUATION et dans Réglages > Carte ; la page ouverte est redessinée.
        self::assertStringContainsString('(["rows"] call comspec_atak_native_fnc_mapPinnedApp)', $this->read('functions/pages/fn_pageMap.sqf'));
        self::assertStringContainsString('(["rows"] call comspec_atak_native_fnc_mapPinnedApp) select 0,', $this->read('functions/pages/fn_pageSettings.sqf'));
        self::assertStringContainsString('if !(_open in ["MAP", "SETTINGS"]) exitWith { false };', $pin);

        // Une fois par seconde pour tous les bandeaux, texte réécrit seulement s'il change, coût mesuré.
        self::assertStringContainsString('{ [_x] call _fill; } forEach _pins;', $pin);
        self::assertStringContainsString('(_c getVariable ["txt", ""]) isEqualTo _txt', $pin);
        self::assertStringContainsString('["carte : app épinglée", _t0] call comspec_atak_native_fnc_perfAcc;', $pin);
        self::assertSame(1, substr_count($this->read('functions/map/fn_mapOverlayUpdate.sqf'), '["tick"] call comspec_atak_native_fnc_mapPinnedApp;'));
    }

    public function testPinnedBannersShareTheBandRowThenStack(): void
    {
        $map = $this->read('functions/pages/fn_pageMap.sqf');
        // Rien en petit format ; 22 caractères au moins par bandeau ; la place de la rangée est partagée.
        self::assertStringContainsString('if ((count _pinApps) > 0 && {!_mini}) then {', $map);
        self::assertStringContainsString('private _n = (floor (_room / (_pinMin + _pad))) min (count _pinApps);', $map);
        self::assertStringContainsString('private _pinW = _pinMax min (_room / _n - _pad);', $map);
        // Rangées empilées : la carte garde la moitié de sa hauteur, le reste n'est pas dessiné.
        self::assertStringContainsString('private _fit = _n > 0 && {_pinRow isEqualTo 0 || {(_pinOff + _pinH) < (_mh * 0.5)}};', $map);
        self::assertStringContainsString('if (!_fit && {_pinRow > 0 || {_bandH <= 0}}) then { break };', $map);
        // Chaque bandeau réserve sa place : étiquettes de la carte, puis fiches, RECHERCHER et menu OUTILS par _panH.
        self::assertStringContainsString('_hud pushBack [_pinX - _bx, _pinY - _by, _pinX + _pinW - _bx, _pinY + _pinH - _by];', $map);
        self::assertStringContainsString('if (!_pinTop) then { _panH = _panH max (_pinOff + _pinH); };', $map);
        $this->assertBalanced('functions/pages/fn_pageMap.sqf');
    }

    public function testMiniAppTextsAreTranslated(): void
    {
        $en = $this->read('i18n/en.txt');
        foreach ([
            '["NAV", "NAV"]', '["FEUX", "FIRES"]', '["MEDEVAC", "MEDEVAC"]', '["ALERTES", "ALERTS"]', '["HEURE H", "H-HOUR"]',
            '["Aucun point de passage", "No waypoint"]', '["Aucune mission de tir", "No fire mission"]', '["Aucune demande en cours", ',
            '["Aucune alerte", "No alert"]', '["Aucune heure H", "No H-hour"]',
            '["Apps épinglées sur la carte", "Apps pinned on the map"]',
            '["App épinglée sur la carte (2)", "App pinned on the map (2)"]', '["App épinglée sur la carte (3)", "App pinned on the map (3)"]',
            '["Itinéraire (NAV)", "Route (NAV)"]', '["Trois au plus : un bandeau par app, ', '["Deuxième bandeau, mêmes choix que le premier.', '["Troisième bandeau, mêmes choix que le premier.',
            // Libellés repris tels quels des apps complètes.
            '["coups partis", "shot"]', '["tir fini", "rounds complete"]', '["SUR ZONE", "ON STATION"]', '["FIN DE CONTACT", "END OF CONTACT"]', '["Envoyée par %1", "Sent by %1"]',
        ] as $pair) {
            self::assertStringContainsString("\n" . $pair, $en, $pair);
        }
        // Aucun des textes ajoutés n'est en double dans le dictionnaire.
        foreach (['NAV', 'MEDEVAC', 'HEURE H', 'Aucune alerte', 'Apps épinglées sur la carte', 'Itinéraire (NAV)'] as $fr) {
            self::assertSame(1, substr_count($en, "\n[\"" . $fr . '", '), $fr);
        }
    }
}
