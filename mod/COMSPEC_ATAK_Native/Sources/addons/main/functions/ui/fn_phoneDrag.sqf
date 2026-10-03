/*
    Déplacer le téléphone en main : clic gauche maintenu sur la coque (hors écran), puis relâcher.
    Le décalage est mémorisé par mode et orientation (profil), et repris par le téléphone porté.
    Params : ["DOWN" | "MOVE" | "UP", bouton]
*/
params ["_event", ["_button", 0]];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _mouse = getMousePosition;
switch (_event) do {
    case "DOWN": {
        if (_button isNotEqualTo 0) exitWith { false };
        private _l = [] call comspec_atak_native_fnc_layoutGet;
        private _in = {
            params ["_p", "_r"];
            _r params ["_x", "_y", "_w", "_h"];
            (_p select 0) >= _x && {(_p select 0) <= _x + _w} && {(_p select 1) >= _y} && {(_p select 1) <= _y + _h}
        };
        if !([_mouse, _l get "visible"] call _in) exitWith { false };
        if ([_mouse, _l get "device"] call _in) exitWith { false };
        _s set ["drag", [_mouse, profileNamespace getVariable [_l get "offsetKey", [0, 0]], _l get "offsetKey"]];
        true
    };
    case "MOVE": {
        private _drag = _s getOrDefault ["drag", []];
        if ((count _drag) isEqualTo 0) exitWith { false };
        _drag params ["_start", "_off", "_key"];
        profileNamespace setVariable [_key, [(_off select 0) + (_mouse select 0) - (_start select 0), (_off select 1) + (_mouse select 1) - (_start select 1)]];
        [] call comspec_atak_native_fnc_layoutApply;
        true
    };
    case "UP": {
        if ((count (_s getOrDefault ["drag", []])) isEqualTo 0) exitWith { false };
        _s set ["drag", []];
        saveProfileNamespace;
        [_s getOrDefault ["activePage", "MAP"]] call comspec_atak_native_fnc_pageRender;
        true
    };
    default { false };
}
