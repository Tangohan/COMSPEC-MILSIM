params ["_map","_button","_mx","_my"];
[] call comspec_atak_native_fnc_mapUnfocus;
// Clic droit sans glisser (le glisser droit déplace la carte) : menu contextuel PING / MARQUEUR / SUPPRIMER, ou en mode
// MARQUEUR le point de pose de la palette (VALIDER), en mode POINTS DE PASSAGE celui de la fiche d'étape.
if (_button isEqualTo 1) exitWith {
    private _s = uiNamespace getVariable ["COMSPEC_ATAK_State",createHashMap];
    private _rc = _s getOrDefault ["mapRc", []];
    _s set ["mapRc", []];
    if ((count _rc) isEqualTo 3 && {(abs (_mx - (_rc select 0))) < pixelW * 6} && {(abs (_my - (_rc select 1))) < pixelH * 6} && {(diag_tickTime - (_rc select 2)) < 0.8}) then {
        if ((_s getOrDefault ["mapMode", "SELECT"]) isEqualTo "MARKER") exitWith {
            ["anchor", _map ctrlMapScreenToWorld [_mx, _my]] call comspec_atak_native_fnc_markerPlace;
        };
        if ((_s getOrDefault ["mapMode", "SELECT"]) isEqualTo "WP") exitWith {
            ["anchor", _map ctrlMapScreenToWorld [_mx, _my]] call comspec_atak_native_fnc_wpAction;
        };
        ["open", [_map, [_mx, _my]]] call comspec_atak_native_fnc_mapCtxMenu;
    };
    false
};
if (_button isNotEqualTo 0) exitWith { false };
private _w = _map ctrlMapScreenToWorld [_mx,_my];
// Clic gauche armé à l'appui (fn_mapMouseButtonDown) : il n'agit que si le curseur n'a presque pas bougé, sinon c'était
// un glisser pour déplacer la carte et l'outil reste comme il était.
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State",createHashMap];
private _lc = _s getOrDefault ["mapLc", []];
_s set ["mapLc", []];
if ((count _lc) isEqualTo 5) exitWith {
    _lc params ["_x0", "_y0", "_shift", "_ctrl", "_alt"];
    if ((abs (_mx - _x0)) < pixelW * 8 && {(abs (_my - _y0)) < pixelH * 8}) then {
        [_map, 0, _x0, _y0, _shift, _ctrl, _alt, true] call comspec_atak_native_fnc_mapMouseButtonDown;
    };
    false
};
["UP", [_w select 0, _w select 1, 0]] call comspec_atak_native_fnc_markerStroke
