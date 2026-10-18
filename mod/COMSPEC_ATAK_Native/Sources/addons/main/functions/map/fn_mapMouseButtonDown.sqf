/* Clic gauche : action de l'outil actif (en sélection, sur un relais du calque Relais : sa fiche). Clic droit : quitte l'outil
   (retour à la sélection), sinon menu contextuel (au relâchement ; sur un relais, la fiche du relais en tête) ; en mode
   MARQUEUR, choisit le point de pose de la palette. */
params ["_map","_button","_mx","_my",["_shift",false],["_ctrl",false],["_alt",false]];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State",createHashMap];
private _world = _map ctrlMapScreenToWorld [_mx,_my];
// Menu contextuel ouvert : un clic gauche ailleurs le ferme seulement ; un clic droit le ferme et peut en rouvrir un.
private _ctxOpen = (count (_s getOrDefault ["mapCtx", createHashMap])) > 0;
if (_ctxOpen) then { ["close"] call comspec_atak_native_fnc_mapCtxMenu; };
if (_ctxOpen && {_button isEqualTo 0}) exitWith { true };
if (_button isEqualTo 0) exitWith {
    // App Feux : la cible est pointée sur la carte.
    private _mp = _s getOrDefault ["medevacPick", ""];
    if (_mp isNotEqualTo "") exitWith { _s set ["medevacPick", ""]; ["picked", [_world select 0, _world select 1, 0]] call comspec_atak_native_fnc_medicalAction; true };
    // App Reco : POINTÉ CARTE, le point touché devient le carroyage de la note.
    if (_s getOrDefault ["recoPick", false]) exitWith {
        _s set ["recoPick", false];
        (uiNamespace getVariable ["COMSPEC_ATAK_RecoDraft", createHashMap]) set ["grid", [[_world select 0, _world select 1, 0], 8] call comspec_atak_native_fnc_gridRef];
        ["RECO"] call comspec_atak_native_fnc_navigate;
        true
    };
    if ((_s getOrDefault ["reportPick", ""]) isNotEqualTo "") exitWith { _s set ["reportPick", ""]; ["picked", [_world select 0, _world select 1, 0]] call comspec_atak_native_fnc_reportAction; true };
    if ((_s getOrDefault ["dronePick", ""]) isNotEqualTo "") exitWith { _s set ["dronePick", ""]; ["picked", [_world select 0, _world select 1, 0]] call comspec_atak_native_fnc_droneAction; true };
    private _lp = _s getOrDefault ["logiPick", ""];
    if (_lp isNotEqualTo "") exitWith { _s set ["logiPick", ""]; ["picked", [_world select 0, _world select 1, 0]] call comspec_atak_native_fnc_logisticsAction; true };
    // App JTAC : cible ou IP pointés sur la carte.
    private _jp = _s getOrDefault ["jtacPick", ""];
    if (_jp isNotEqualTo "") exitWith { _s set ["jtacPick", ""]; ["picked", [_jp, [_world select 0, _world select 1, 0]]] call comspec_atak_native_fnc_jtacAction; true };
    private _pick = _s getOrDefault ["firePick", ""];
    if (_pick isNotEqualTo "") exitWith {
        _s set ["firePick", ""];
        ["picked", [_world select 0, _world select 1, 0]] call comspec_atak_native_fnc_firesAction;
        true
    };
    // Outil DRONE : le clic devient un ordre au drone appairé (aller ici, point de route, zone).
    if ((_s getOrDefault ["mapMode","SELECT"]) isEqualTo "DRONE") exitWith { ["mapClick", [_world select 0, _world select 1, 0]] call comspec_atak_native_fnc_droneAction; true };
    if (["DOWN", [_world select 0, _world select 1, 0]] call comspec_atak_native_fnc_markerStroke) exitWith { true };
    // Étape de l'itinéraire sous le curseur (sélection ou mode étapes) : sa fiche s'ouvre (renommer, type, déplacer).
    private _wi = if ((_s getOrDefault ["mapMode","SELECT"]) in ["SELECT","WP"] && {!_shift && !_ctrl && !_alt}) then { [_map, [_mx, _my]] call comspec_atak_native_fnc_wpAt } else { -1 };
    if (_wi >= 0) exitWith { ["edit", _wi] call comspec_atak_native_fnc_wpAction; true };
    // Sélection : un marqueur sous le curseur passe avant les unités.
    // Relais du calque Relais sous le curseur : sa fiche (état, fréquence, perte, santé) et ses actions.
    if ((_s getOrDefault ["mapMode","SELECT"]) isEqualTo "SELECT" && {!_shift && !_ctrl && !_alt}
        && {!isNull (["relayAt", [_map, [_mx, _my]]] call comspec_atak_native_fnc_mapCtxMenu)}) exitWith {
        ["open", [_map, [_mx, _my], true]] call comspec_atak_native_fnc_mapCtxMenu;
        true
    };
    if ((_s getOrDefault ["mapMode","SELECT"]) isEqualTo "SELECT" && {!_shift && !_ctrl && !_alt}) then {
        private _m = [_map,[_mx,_my]] call comspec_atak_native_fnc_markerAt;
        if (_m isNotEqualTo "") exitWith {
            _s set ["selectedMarker",_m];
            [{ ["MAP"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
        };
        if ((_s getOrDefault ["selectedMarker",""]) isNotEqualTo "") then {
            _s set ["selectedMarker",""];
            [{ ["MAP"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
        };
        [_world,_shift,_ctrl,_alt] call comspec_atak_native_fnc_mapSelect;
    } else {
        [_world,_shift,_ctrl,_alt] call comspec_atak_native_fnc_mapSelect;
    };
    true
};
if (_button isEqualTo 1) exitWith {
    // Modes MARQUEUR et POINTS DE PASSAGE : le clic droit choisit le point de pose (au relâchement, fn_markerPlace /
    // fn_wpAction "anchor"), il ne quitte plus.
    if (!((_s getOrDefault ["mapMode","SELECT"]) in ["SELECT","MARKER","WP"]) || {(count (_s getOrDefault ["drawStroke",[]])) > 0}) then {
        _s set ["drawStroke",[]];
        ["SELECT"] call comspec_atak_native_fnc_mapToolSet;
        [{ ["MAP"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
        _s set ["mapRc", []];
    } else {
        // Sélection : clic droit armé, le menu contextuel s'ouvre au relâchement s'il n'y a pas eu de glisser (fn_mapMouseButtonUp).
        _s set ["mapRc", [_mx, _my, diag_tickTime]];
    };
    true
};
false
