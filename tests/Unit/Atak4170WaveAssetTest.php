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
        // Sans enregistrement dans le profil CBA, le choix ne vaut que pour la partie en cours : fn_setSetting le garde.
        $pin = $this->read('functions/map/fn_mapPinnedApp.sqf');
        self::assertStringContainsString('[_var, _v] call comspec_atak_native_fnc_setSetting;', $pin);

        $settings = $this->read('functions/pages/fn_pageSettings.sqf');
        self::assertStringContainsString('[_k, _new] call comspec_atak_native_fnc_setSetting;', $settings);
        self::assertStringContainsString('[_k, _v] call comspec_atak_native_fnc_setSetting;', $settings);
        self::assertStringContainsString('["comspec_atak_native_phone_model", _val] call comspec_atak_native_fnc_setSetting;', $settings);
        self::assertStringContainsString("['comspec_atak_native_font', '%1'] call comspec_atak_native_fnc_setSetting;", $settings);
        self::assertStringContainsString('["comspec_atak_native_full_scale", _v] call comspec_atak_native_fnc_setSetting;', $settings);
        self::assertStringContainsString('["comspec_atak_native_language", "fr"] call comspec_atak_native_fnc_setSetting;', $settings);
        self::assertStringContainsString("['comspec_atak_native_language', '%1'] call comspec_atak_native_fnc_setSetting;", $settings);

        $map = $this->read('functions/pages/fn_pageMap.sqf');
        self::assertStringContainsString('[_var, !(missionNamespace getVariable [_var, _def])] call comspec_atak_native_fnc_setSetting;', $map);

        // Plus aucun appel direct du téléphone : ni sans enregistrement, ni avec l'écriture immédiate du profil.
        foreach ([$pin, $settings, $map] as $src) {
            self::assertStringNotContainsString('call CBA_settings_fnc_set', $src);
        }
    }

    public function testPhoneSettingsAreStoredWithoutAnImmediateProfileWrite(): void
    {
        // Le 5e paramètre de CBA_settings_fnc_set lance saveProfileNamespace à chaque appel (image figée sur certains PC) :
        // fn_setSetting range le réglage dans la table CBA du profil et laisse fn_saveProfile regrouper l'écriture.
        $set = $this->read('functions/ui/fn_setSetting.sqf');
        self::assertStringContainsString('if !([_k, _v, 0, "client"] call CBA_settings_fnc_set) exitWith { false };', $set);
        self::assertStringContainsString('private _hash = profileNamespace getVariable ["cba_settings_hash", [] call CBA_fnc_hashCreate];', $set);
        // Comme CBA : clé en minuscules, [valeur, priorité] ; retiré quand le réglage revient à sa valeur par défaut.
        self::assertStringContainsString('if (_v isEqualTo ([_k, "default"] call CBA_settings_fnc_get)) then {', $set);
        self::assertStringContainsString('[_hash, toLower _k] call CBA_fnc_hashRem;', $set);
        self::assertStringContainsString('[_hash, toLower _k, [_v, 0]] call CBA_fnc_hashSet;', $set);
        self::assertStringContainsString('profileNamespace setVariable ["cba_settings_hash", _hash];', $set);
        // Réglage imposé par le fichier userconfig de CBA : jamais rangé dans le profil.
        self::assertStringContainsString('getVariable ["cba_settings_userconfig", locationNull]) getVariable _k }) exitWith { true };', $set);
        // Écriture regroupée (60 s, forcée en fin de mission), jamais immédiate.
        self::assertStringContainsString('[] call comspec_atak_native_fnc_saveProfile;', $set);
        $code = preg_replace('~/\*.*?\*/~s', '', $set) ?? '';
        self::assertStringNotContainsString('saveProfileNamespace', $code);
        self::assertStringContainsString('class addSetting {}; class setSetting {};', $this->read('config.cpp'));
        $this->assertBalanced('functions/ui/fn_setSetting.sqf');
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
        self::assertStringContainsString('if ((missionNamespace getVariable [_var, "none"]) isNotEqualTo _v) then { [_var, _v] call comspec_atak_native_fnc_setSetting; };', $pin);
        self::assertStringContainsString("['toggle', [%1]] call comspec_atak_native_fnc_mapPinnedApp;", $pin);
        // Même ligne dans le panneau SITUATION et dans Réglages > Carte ; la page ouverte est redessinée.
        self::assertStringContainsString('(["rows"] call comspec_atak_native_fnc_mapPinnedApp)', $this->read('functions/pages/fn_pageMap.sqf'));
        self::assertStringContainsString('(["rows"] call comspec_atak_native_fnc_mapPinnedApp) select 0,', $this->read('functions/pages/fn_pageSettings.sqf'));
        self::assertStringContainsString('if !(_open in ["MAP", "SETTINGS"]) exitWith { false };', $pin);
        // Un redessin par image et non par tranche de 0,3 s : deux touchers rapprochés redessinent tous les deux la page.
        self::assertStringContainsString('if ((uiNamespace getVariable ["COMSPEC_ATAK_PinRefreshAt", -1]) isEqualTo diag_frameNo) exitWith { false };', $pin);
        self::assertStringContainsString('uiNamespace setVariable ["COMSPEC_ATAK_PinRefreshAt", diag_frameNo];', $pin);
        self::assertStringNotContainsString('< 0.3', $pin);

        // Une fois par seconde pour tous les bandeaux, texte réécrit seulement s'il change, coût mesuré.
        self::assertStringContainsString('{ [_x] call _fill; } forEach _pins;', $pin);
        self::assertStringContainsString('(_c getVariable ["txt", ""]) isEqualTo _txt', $pin);
        self::assertStringContainsString('["carte : app épinglée", _t0] call comspec_atak_native_fnc_perfAcc;', $pin);
        self::assertSame(1, substr_count($this->read('functions/map/fn_mapOverlayUpdate.sqf'), '["tick"] call comspec_atak_native_fnc_mapPinnedApp;'));
    }

    public function testChoiceRowOnlyOffersAppsThePlayerCanOpen(): void
    {
        $pin = $this->read('functions/map/fn_mapPinnedApp.sqf');
        // Un seul test (interdite, retirée, désinstallée) pour les bandeaux et pour la ligne de choix.
        self::assertSame(1, substr_count($pin, '["installed", _app get "id"] call comspec_atak_native_fnc_appInstall'));
        self::assertStringContainsString('_sel select { [_x, _list] call _usable }', $pin);
        self::assertStringContainsString('private _ok = _keys select { [_x, _list] call _usable };', $pin);
        // Proposées : AUCUNE, les apps affichables, et celles déjà épinglées (pour pouvoir les retirer), avec l'explication.
        self::assertStringContainsString('select { (_x select 0) isEqualTo "none" || {(_x select 0) in _ok} || {(_x select 0) in _sel} }', $pin);
        // 4.18.0 : la même info-bulle, avec une phrase propre quand c'est le mode Essentiel qui masque l'app.
        self::assertStringContainsString('["", ["App interdite ou désinstallée : pas de bandeau", "Masquée par le mode Essentiel : pas de bandeau"] select (["on"] call comspec_atak_native_fnc_essentialMode)] select (_v in _sel && {!(_v in _ok)})]', $pin);
        // 4e élément d'une option de « segment » : info-bulle du bouton, traduite.
        self::assertStringContainsString('_x params ["_text", "_code", ["_active", false], ["_tip", ""], ["_segV", ""]];', $this->read('functions/ui/fn_formRender.sqf'));
        self::assertStringContainsString('["App interdite ou désinstallée : pas de bandeau", "App forbidden or uninstalled: no banner"]', $this->read('i18n/en.txt'));
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

    // ── Lot 3 : interface renforcée ─────────────────────────────────────────────────────────────────────────────

    public function testMapToolbarStaysInsideTheMapAndReservesItsWidth(): void
    {
        $map = $this->read('functions/pages/fn_pageMap.sqf');
        // La hauteur d'un bouton se calcule une fois la barre connue (6 à 9 boutons, un demi-bouton de marge en haut).
        self::assertStringContainsString('private _ihMax = (_mh - 2 * _pad) / (((count _tools) + 0.5) max 8);', $map);
        self::assertStringNotContainsString('private _ihMax = (_mh - 2 * _pad) / 8;', $map);
        self::assertGreaterThan(strpos($map, '_tools pushBack ["droneview"'), strpos($map, 'private _ihMax = '), 'taille calculée après le dernier bouton');
        // La taille choisie (petite, normale, grande) est lue une fois, avant la réserve des bandeaux et des fiches.
        self::assertSame(1, substr_count($map, 'profileNamespace getVariable ["COMSPEC_ATAK_MapToolbarScale", 1]'));
        self::assertStringContainsString('private _toolCol = [0, ((((_mh - 2 * _pad) / 7) min (_font * 2.1 * (_tbScale max 1))) / _ratio) + _pad] select _interactive;', $map);
        self::assertLessThan(strpos($map, 'private _toolCol = '), strpos($map, 'private _tbScale = '));
        // La vue drone incrustée reprend la même réserve au lieu de la recalculer.
        self::assertStringContainsString('private _ix = _bx + _mw - _iw2 - _pad - _toolCol;', $map);
        $this->assertBalanced('functions/pages/fn_pageMap.sqf');
    }

    public function testMapTextsKeepTheFontFloor(): void
    {
        $map = $this->read('functions/pages/fn_pageMap.sqf');
        foreach (['_hd ctrlSetFontHeight ((_fs * 0.9) max COMSPEC_ATAK_FontMin);', '_h ctrlSetFontHeight ((_fs * 0.78) max COMSPEC_ATAK_FontMin);', '_txt ctrlSetFontHeight ((_fs * 0.92) max COMSPEC_ATAK_FontMin);'] as $line) {
            self::assertStringContainsString($line, $map);
        }
    }

    public function testTextsAreCutByTheSharedUnicodeHelper(): void
    {
        // fn_mapFmt "cut" compte en caractères (toArray) et pose « … » : plus de coupe en octets au milieu d'un accent.
        self::assertStringContainsString('private _a = toArray _v;', $this->read('functions/map/fn_mapFmt.sqf'));
        foreach ([
            'functions/nav/fn_routeBanner.sqf' => ['_label = ["cut", _label, 16] call comspec_atak_native_fnc_mapFmt;', '_label select [0, 15]'],
            'functions/map/fn_contactCard.sqf' => ['["cut", _note call _esc, 90] call comspec_atak_native_fnc_mapFmt', 'select [0, 90]'],
            'functions/pages/fn_pageChat.sqf' => ['["cut", _prevTxt, 90] call comspec_atak_native_fnc_mapFmt', '_prevTxt select [0, 90]'],
            'functions/pages/fn_chatListRow.sqf' => ['["cut", _last, 90] call comspec_atak_native_fnc_mapFmt', '_last select [0, 90]'],
            'functions/map/fn_mapOverlayUpdate.sqf' => ['_cs = ["cut", _cs, 12] call comspec_atak_native_fnc_mapFmt;', '_cs select [0, 11]'],
        ] as $path => [$new, $old]) {
            $src = $this->read($path);
            self::assertStringContainsString($new, $src, $path);
            self::assertStringNotContainsString($old, $src, $path);
            $this->assertBalanced($path);
        }
        $ov = $this->read('functions/map/fn_mapOverlayUpdate.sqf');
        self::assertStringContainsString('_fn = ["cut", _fn, 8] call comspec_atak_native_fnc_mapFmt;', $ov);
        self::assertStringNotContainsString('_fn select [0, 8]', $ov);
    }

    public function testStateColoursComeFromTheTheme(): void
    {
        // Ordres et missions de tir : pastille et texte par jetons ; annulé en encre de légende (« off » ne se lit pas en texte).
        $tasks = $this->read('functions/pages/fn_pageTasks.sqf');
        $fires = $this->read('functions/pages/fn_fireSupport.sqf');
        foreach ([$tasks, $fires] as $src) {
            foreach (['[_th get "ok", _th get "hexOk"]', '[_th get "off", _th get "hexTextMuted"]', '[_th get "warn", _th get "hexWarn"]', '[_th get "alert", _th get "hexAlert"]'] as $pair) {
                self::assertStringContainsString($pair, $src, $pair);
            }
        }
        self::assertStringContainsString('default { [_th get "info", _th get "hexInfo"] };', $fires);
        self::assertSame(0, preg_match('~#[0-9a-fA-F]{6}~', $tasks), 'aucune couleur en dur dans Ordres');
        // Lignes de l'app Ordres seulement : le journal JTAC et le tableau des missions gardent leurs couleurs.
        foreach (['"#e04038"', '"#f2ab33"', '"#5cc76b"', '"#94a399"', '"#80bfff"', "'#e04038'", "'#d8f29e'", "color='#8a9a93'>%5"] as $hex) {
            self::assertStringNotContainsString($hex, $fires, $hex);
        }
        // Messages : l'astuce de saisie suit le thème.
        $chat = $this->read('functions/pages/fn_pageChat.sqf');
        self::assertStringContainsString('color=\'" + (COMSPEC_ATAK_Th get "hexTextMuted") + "\'>Astuce : /urgent,', $chat);
        self::assertStringNotContainsString('#5b6b63', $chat);
        foreach (['functions/pages/fn_pageTasks.sqf', 'functions/pages/fn_fireSupport.sqf', 'functions/pages/fn_pageChat.sqf'] as $path) {
            $this->assertBalanced($path);
        }
    }

    public function testStatusBarIconsUseTheChromeInks(): void
    {
        $status = $this->read('functions/ui/fn_statusUpdate.sqf');
        foreach ([
            'case (_bars >= 3): { _th get "mapOk" }; case (_bars >= 1): { _th get "mapWarn" }; default { _th get "mapAlert" };',
            '[_batCtrl, "cs_c", [_th get "onChrome", _th get "mapAlert"] select (_bat <= 12)] call _put;',
            'default { _th get "onChromeDim" };',
            '[_bell, "cs_c", [_th get "onChrome", _th get "mapWarn"] select (_unread > 0)] call _put;',
            '[_logo, "cs_c", _th get "mapOk"] call _put;',
        ] as $line) {
            self::assertStringContainsString($line, $status, $line);
        }
        // Restent en dur, sans jeton : le bleu SAT et le rouge clignotant du terminal compromis.
        foreach (['[0.90, 0.94, 0.91, 1]', '[0.95, 0.67, 0.20, 1]', '[0.88, 0.25, 0.22, 1]', '[0.36, 0.78, 0.42, 1]', '[0.62, 0.70, 0.66, 1]'] as $rgba) {
            self::assertStringNotContainsString($rgba, $status, $rgba);
        }
        // Les jetons existent dans les trois thèmes (fn_theme).
        $theme = $this->read('functions/ui/fn_theme.sqf');
        foreach (['_th set ["onChrome", ', '_th set ["onChromeDim", ', '_th set ["mapOk", ', '_th set ["mapWarn", ', '_th set ["mapAlert", '] as $token) {
            self::assertStringContainsString($token, $theme, $token);
        }
        $this->assertBalanced('functions/ui/fn_statusUpdate.sqf');
    }

    public function testMapPanelsUseThePaletteOfTheirBackground(): void
    {
        // Calques sombres posés sur la carte (consigne d'outil, guidage GPS, fiche de recherche) : encres « map… ».
        $map = $this->read('functions/pages/fn_pageMap.sqf');
        self::assertStringContainsString('<t color=\'" + (COMSPEC_ATAK_Th get "hexMapText") + "\'>%2</t>', $map);
        $banner = $this->read('functions/nav/fn_routeBanner.sqf');
        self::assertStringContainsString('_label, COMSPEC_ATAK_Th get "hexMapOk", COMSPEC_ATAK_Th get "hexMapDim"]', $banner);
        $search = $this->read('functions/map/fn_mapSearch.sqf');
        self::assertStringContainsString('private _col = [COMSPEC_ATAK_Th get "hexMapDim", [(COMSPEC_ATAK_Th get "hexMapOk"), (COMSPEC_ATAK_Th get "hexMapWarn")] select', $search);
        self::assertStringContainsString('COMSPEC_ATAK_Th get "hexMapWarn", COMSPEC_ATAK_Th get "hexMapText"];', $search);
        // Surfaces du thème (volet SITUATION, panneau RECHERCHER) : encres de page, lisibles en thème jour.
        self::assertStringContainsString('[(COMSPEC_ATAK_Th get "hexTextMuted"), (COMSPEC_ATAK_Th get "hexAlert")] select (_ho > 0), COMSPEC_ATAK_Th get "hexFriend"]', $map);
        self::assertStringContainsString('private _hDim = COMSPEC_ATAK_Th get "hexTextMuted";', $map);
        self::assertStringContainsString('[(COMSPEC_ATAK_Th get "hexWarn"), (COMSPEC_ATAK_Th get "hexOk")] select (_msg param [1, false])', $search);
        // Texte du volet SITUATION : posé sur un fond sombre fixe quel que soit le thème, donc alliés à l'encre de la carte
        // (3,5:1 en thème jour avec l'encre de page) ; la fiche de l'app Groupe, sur une surface du thème, garde l'encre de page.
        self::assertStringContainsString('_rows pushBack ["text", [true] call comspec_atak_native_fnc_inspectorUpdate, [0.06, 0.075, 0.068, 0.96], "COMSPEC_ATAK_InspTextCtrl"];', $map);
        $insp = $this->read('functions/ui/fn_inspectorUpdate.sqf');
        self::assertStringContainsString('[_fr, "alliés", COMSPEC_ATAK_Th get "hexMapFriend"] call _tile', $insp);
        self::assertStringContainsString('(COMSPEC_ATAK_Th get (["hexMapFriend", "hexFriend"] select ((count _ent) > 0)))', $insp);
        foreach ([[$map, ['#47b3ff', '#e6efe9']], [$banner, ['#5cc76b', '#8a9a93']], [$search, ['#9aa39e', '#e9a53a', '#cfd8d3']], [$insp, ['#47b3ff']]] as [$src, $hexes]) {
            foreach ($hexes as $hex) {
                self::assertStringNotContainsString($hex, $src, $hex);
            }
        }
        foreach (['functions/nav/fn_routeBanner.sqf', 'functions/map/fn_mapSearch.sqf', 'functions/ui/fn_inspectorUpdate.sqf'] as $path) {
            $this->assertBalanced($path);
        }
    }

    public function testWindUsesTheSharedCardinalTable(): void
    {
        $weather = $this->read('functions/core/fn_weather.sqf');
        self::assertStringContainsString('private _card = [_from, 8] call comspec_atak_native_fnc_cardinal;', $weather);
        self::assertStringNotContainsString('"SO", "O", "NO"', $weather);
        // fn_cardinal : O en français, W sinon, en 8 points.
        $cardinal = $this->read('functions/map/fn_cardinal.sqf');
        self::assertStringContainsString('["N", "NE", "E", "SE", "S", "SW", "W", "NW"], ["N", "NE", "E", "SE", "S", "SO", "O", "NO"]', $cardinal);
    }

    public function testPinnedAppsHelpSaysWhenTheyAreHidden(): void
    {
        $fr = ' ; touchez-le pour ouvrir l\'app ; masquées en petit format ou si la carte est trop petite"';
        self::assertStringContainsString($fr, $this->read('functions/map/fn_mapPinnedApp.sqf'));
        $cba = 'Masqué en petit format ou si la carte est trop petite. Aussi réglable dans Réglages > Carte."';
        self::assertStringContainsString($cba, $this->read('XEH_preInit.sqf'));
        $en = $this->read('i18n/en.txt');
        self::assertStringContainsString($fr . ', "Three at most: ', $en);
        self::assertStringContainsString('touch it to open the app; hidden in small format or when the map is too small"]', $en);
        self::assertStringContainsString($cba . ', "None by default. ', $en);
        self::assertStringContainsString('Hidden in small format or when the map is too small. Also available in Settings > Map."]', $en);
    }

    // ── Version et notes ────────────────────────────────────────────────────────────────────────────────────────

    public function testVersionAndChangelogs(): void
    {
        $hpp = $this->read('script_component.hpp');
        // Version courante : figée par le test de la dernière version (AtakEssentialModeAssetTest) ; ici, 4.17.0 au moins.
        self::assertMatchesRegularExpression('/#define VERSION_STR "4\.(1[7-9]|[2-9]\d)\.\d+"/', $hpp);
        // La DLL COMSPEC Link ne change pas dans cette version.
        self::assertStringContainsString('#define LINK_VERSION_STR "2.0.127"', $hpp);

        $cl = str_replace("\r\n", "\n", (string) file_get_contents(self::ROOT . 'CHANGELOG-ATAK.md'));
        $title = '(ATAK natif 4.17.0.AG - Washington)';
        self::assertSame(1, substr_count($cl, $title));
        // Entrée en tête du journal, au-dessus de la 4.16.0.
        self::assertLessThan(strpos($cl, '(ATAK natif 4.16.0.AG - Washington)'), strpos($cl, $title));
        $entry = substr($cl, (int) strpos($cl, $title));
        $entry = substr($entry, 0, (int) strpos($entry, "\n## ", 10));
        self::assertStringContainsString("### Résumé joueur\nType : nouveauté\nFR : Mini-apps sur la carte et interface renforcée\n- ", $entry);
        self::assertStringContainsString("\nEN : Mini apps on the map and a stronger interface\n- ", $entry);
        // Résumé joueur : mêmes puces dans les deux langues, aucun nom interne.
        $summary = substr($entry, 0, (int) strpos($entry, '### Détail technique'));
        $fr = substr($summary, 0, (int) strpos($summary, "\nEN : "));
        $en = substr($summary, (int) strpos($summary, "\nEN : "));
        self::assertSame(substr_count($fr, "\n- "), substr_count($en, "\n- "));
        self::assertGreaterThanOrEqual(4, substr_count($fr, "\n- "));
        foreach (['fn_', 'comspec_atak_native', 'COMSPEC_ATAK_', 'CBA', '.sqf', 'AssetTest', '`'] as $internal) {
            self::assertStringNotContainsString($internal, $summary, $internal);
        }
        foreach (['NAV', 'FEUX', 'MEDEVAC', 'ALERTES', 'HEURE H', 'SITUATION', 'Réglages > Carte'] as $word) {
            self::assertStringContainsString($word, $fr, $word);
        }
        self::assertStringContainsString('### Détail technique', $entry);
        self::assertStringContainsString('`fn_setSetting`', $entry);

        $steam = str_replace("\r\n", "\n", (string) file_get_contents(self::ROOT . 'mod/COMSPEC_ATAK_Native/publisher/STEAM_CHANGELOG.txt'));
        self::assertStringContainsString('[h1]COMSPEC ATAK 4.17.0 Washington', $steam);
        $from = (int) strpos($steam, '[h1]COMSPEC ATAK 4.17.0 Washington');
        $block = substr($steam, $from, (int) strpos($steam, '[h1]COMSPEC ATAK 4.16.0 Washington') - $from);
        self::assertStringContainsString('[h2]Français[/h2]', $block);
        self::assertStringContainsString('[h2]English[/h2]', $block);
        self::assertSame(2, substr_count($block, '[list]'));
        self::assertSame(2, substr_count($block, '[/list]'));
    }
}
