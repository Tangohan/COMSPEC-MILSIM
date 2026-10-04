/* Clic gauche : action de l'outil actif. Clic droit : quitte l'outil (retour à la sélection). */
params ["_map","_button","_mx","_my",["_shift",false],["_ctrl",false],["_alt",false]];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State",createHashMap];
private _world = _map ctrlMapScreenToWorld [_mx,_my];
if (_button isEqualTo 0) exitWith {
    // App Feux : la cible est pointée sur la carte.
    private _mp = _s getOrDefault ["medevacPick", ""];
    if (_mp isNotEqualTo "") exitWith { _s set ["medevacPick", ""]; ["picked", [_world select 0, _world select 1, 0]] call comspec_atak_native_fnc_medicalAction; true };
    if ((_s getOrDefault ["dronePick", ""]) isNotEqualTo "") exitWith { _s set ["dronePick", ""]; ["picked", [_world select 0, _world select 1, 0]] call comspec_atak_native_fnc_droneAction; true };
    private _lp = _s getOrDefault ["logiPick", ""];
    if (_lp isNotEqualTo "") exitWith { _s set ["logiPick", ""]; ["picked", [_world select 0, _world select 1, 0]] call comspec_atak_native_fnc_logisticsAction; true };
    private _pick = _s getOrDefault ["firePick", ""];
    if (_pick isNotEqualTo "") exitWith {
        _s set ["firePick", ""];
        ["picked", [_world select 0, _world select 1, 0]] call comspec_atak_native_fnc_firesAction;
        true
    };
    // Outil DRONE : le clic devient un ordre au drone appairé (aller ici, point de route, zone).
    if ((_s getOrDefault ["mapMode","SELECT"]) isEqualTo "DRONE") exitWith { ["mapClick", [_world select 0, _world select 1, 0]] call comspec_atak_native_fnc_droneAction; true };
    if (["DOWN", [_world select 0, _world select 1, 0]] call comspec_atak_native_fnc_markerStroke) exitWith { true };
    // Sélection : un marqueur sous le curseur passe avant les unités.
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
    if ((_s getOrDefault ["mapMode","SELECT"]) isNotEqualTo "SELECT" || {(count (_s getOrDefault ["drawStroke",[]])) > 0}) then {
        _s set ["drawStroke",[]];
        ["SELECT"] call comspec_atak_native_fnc_mapToolSet;
        [{ ["MAP"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
    };
    true
};
false
