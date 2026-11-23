params ["_map","_button","_mx","_my"];
[] call comspec_atak_native_fnc_mapUnfocus;
// Point [x, y] d'écran sur le menu OUTILS ou la barre d'outils posés sur la carte : c'est leur bouton qui agit, pas la carte.
private _onUi = {
    params ["_px", "_py"];
    private _st = uiNamespace getVariable ["COMSPEC_ATAK_State",createHashMap];
    ((["mapToolsRect", "mapToolbarRect"] apply { _st getOrDefault [_x, []] }) findIf {
        (count _x) isEqualTo 4 && {_px >= (_x select 0)} && {_px <= ((_x select 0) + (_x select 2))} && {_py >= (_x select 1)} && {_py <= ((_x select 1) + (_x select 3))}
    }) >= 0
};
// Clic droit sans glisser (le glisser droit déplace la carte et garde l'outil) : quitte l'outil actif ; en mode
// MARQUEUR, point de pose de la palette (VALIDER), en mode POINTS DE PASSAGE celui de la fiche d'étape ; en sélection,
// menu contextuel PING / MARQUEUR / SUPPRIMER.
if (_button isEqualTo 1) exitWith {
    private _s = uiNamespace getVariable ["COMSPEC_ATAK_State",createHashMap];
    private _rc = _s getOrDefault ["mapRc", []];
    _s set ["mapRc", []];
    if ((count _rc) isEqualTo 3 && {(abs (_mx - (_rc select 0))) < pixelW * 6} && {(abs (_my - (_rc select 1))) < pixelH * 6} && {(diag_tickTime - (_rc select 2)) < 0.8}
        && {!([_mx, _my] call _onUi)}) then {
        private _mode = _s getOrDefault ["mapMode", "SELECT"];
        if (!(_mode in ["SELECT", "MARKER", "WP"]) || {(count (_s getOrDefault ["drawStroke", []])) > 0}) exitWith {
            _s set ["drawStroke", []];
            ["SELECT"] call comspec_atak_native_fnc_mapToolSet;
            [{ ["MAP"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame;
        };
        if (_mode isEqualTo "MARKER") exitWith {
            ["anchor", _map ctrlMapScreenToWorld [_mx, _my]] call comspec_atak_native_fnc_markerPlace;
        };
        if (_mode isEqualTo "WP") exitWith {
            ["anchor", _map ctrlMapScreenToWorld [_mx, _my]] call comspec_atak_native_fnc_wpAction;
        };
        ["open", [_map, [_mx, _my]]] call comspec_atak_native_fnc_mapCtxMenu;
    };
    false
};
if (_button isNotEqualTo 0) exitWith { false };
private _w = _map ctrlMapScreenToWorld [_mx,_my];
// Clic gauche armé à l'appui (fn_mapMouseButtonDown) : il n'agit que si ce n'était pas un glisser (fn_mapMouseMoving :
// 6 px, ou 150 ms de maintien et 3 px). Un glisser a déplacé la carte : ni point posé, ni outil fermé, ni menu fermé.
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State",createHashMap];
// Pointeur en cours (appui maintenu, fn_mapOnDraw) : le relâchement l'arrête, sans autre action de clic.
if (_s getOrDefault ["mapHold", false]) exitWith { ["pointStop"] call comspec_atak_native_fnc_mapPing; false };
private _lc = _s getOrDefault ["mapLc", []];
private _drag = _s getOrDefault ["mapDrag", false];
_s set ["mapLc", []];
_s set ["mapDrag", false];
if ((count _lc) isEqualTo 5) exitWith {
    _lc params ["_x0", "_y0", "_shift", "_ctrl", "_alt"];
    // Relâché loin de l'appui sans MouseMoving (curseur passé sur un panneau) : c'est aussi un glisser.
    if (!_drag && {((((_mx - _x0) / pixelW) ^ 2) + (((_my - _y0) / pixelH) ^ 2)) <= 36} && {!([_x0, _y0] call _onUi)}) then {
        // Menu OUTILS ouvert : un vrai clic sur la carte hors du panneau le ferme, sans autre action.
        if !(["close"] call comspec_atak_native_fnc_mapToolMenu) then {
            [_map, 0, _x0, _y0, _shift, _ctrl, _alt, true] call comspec_atak_native_fnc_mapMouseButtonDown;
        };
    };
    false
};
["UP", [_w select 0, _w select 1, 0]] call comspec_atak_native_fnc_markerStroke
