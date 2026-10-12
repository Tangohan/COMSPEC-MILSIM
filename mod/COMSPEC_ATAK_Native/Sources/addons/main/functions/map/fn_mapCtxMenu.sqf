/*
    Menu contextuel de la carte (clic droit sans glisser) : PING, METTRE UN MARQUEUR, SUPPRIMER (marqueur joueur pointé).
    ["open", [contrôle carte, position écran]]  ouvre le menu au curseur (en-tête : coordonnées du point)
    ["close"]                                    ferme (clic ailleurs, Échap, choix d'une ligne)
    ["act", clé]                                 exécute une ligne (PING, MARKER, DELETE)
    ["render"]                                   dessine le menu (appelé par fn_pageMap, par-dessus la carte)
    État : COMSPEC_ATAK_State mapCtx = HashMap [pos (monde), marker (nom ou ""), at (heure)].
*/
params [["_mode", ""], ["_arg", []]];
disableSerialization;
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _rerender = { [{ ["MAP"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame; };
private _unbindEsc = {
    private _d = [] call comspec_atak_native_fnc_display;
    private _h = uiNamespace getVariable ["COMSPEC_ATAK_MapCtxKey", -1];
    if (_h >= 0 && {!isNull _d}) then { _d displayRemoveEventHandler ["KeyDown", _h]; };
    uiNamespace setVariable ["COMSPEC_ATAK_MapCtxKey", -1];
};
switch (_mode) do {
    case "open": {
        _arg params ["_map", "_screen"];
        if (isNull _map) exitWith {};
        private _w = _map ctrlMapScreenToWorld _screen;
        private _m = [_map, _screen] call comspec_atak_native_fnc_markerAt;
        // Mêmes règles que la suppression (fn_markerDelete) : seuls les marqueurs posés par les joueurs.
        if ((_m find "_USER_DEFINED") isNotEqualTo 0) then { _m = ""; };
        _s set ["mapCtx", createHashMapFromArray [["pos", [_w select 0, _w select 1, 0]], ["marker", _m], ["at", diag_tickTime]]];
        [] call _unbindEsc;
        private _d = [] call comspec_atak_native_fnc_display;
        if (!isNull _d) then {
            uiNamespace setVariable ["COMSPEC_ATAK_MapCtxKey", _d displayAddEventHandler ["KeyDown", {
                params ["", "_key"];
                if (_key isNotEqualTo 0x01) exitWith { false };
                ["close"] call comspec_atak_native_fnc_mapCtxMenu;
                true
            }]];
        };
        playSound "ClickSoft";
        call _rerender;
    };
    case "close": {
        [] call _unbindEsc;
        if ((count (_s getOrDefault ["mapCtx", createHashMap])) isEqualTo 0) exitWith {};
        _s set ["mapCtx", createHashMap];
        call _rerender;
    };
    case "act": {
        private _c = _s getOrDefault ["mapCtx", createHashMap];
        if ((count _c) isEqualTo 0) exitWith {};
        private _pos = _c get "pos";
        private _m = _c getOrDefault ["marker", ""];
        ["close"] call comspec_atak_native_fnc_mapCtxMenu;
        playSound "ClickSoft";
        switch (_arg) do {
            case "PING": { ["send", _pos] call comspec_atak_native_fnc_mapPing; };
            case "MARKER": { ["", _pos] call comspec_atak_native_fnc_markerEditOpen; };
            case "DELETE": { if (_m isNotEqualTo "") then { [_m] call comspec_atak_native_fnc_markerDelete; }; };
        };
    };
    case "render": {
        private _c = _s getOrDefault ["mapCtx", createHashMap];
        if ((count _c) isEqualTo 0) exitWith {};
        // Menu oublié (page quittée puis rouverte) : on ne le ressort pas.
        if ((diag_tickTime - (_c getOrDefault ["at", 0])) > 60) exitWith { _s set ["mapCtx", createHashMap]; [] call _unbindEsc; };
        private _d = [] call comspec_atak_native_fnc_display;
        if (isNull _d) exitWith {};
        private _map = _d displayCtrl 88530;
        if (isNull _map) exitWith {};
        private _l = [] call comspec_atak_native_fnc_layoutGet;
        (_l get "body") params ["_bx", "_by", "_bw", "_bh"];
        private _font = _l get "font";
        private _fs = _l get "fontSmall";
        private _ratio = pixelH / pixelW;
        private _pos = _c get "pos";
        private _m = _c getOrDefault ["marker", ""];
        if (_m isNotEqualTo "" && {(markerShape _m) isEqualTo ""}) then { _m = ""; _c set ["marker", ""]; };
        private _rows = [
            ["PING", "PING", "\A3\ui_f\data\map\markers\military\circle_CA.paa", [1, 0.85, 0.15, 1]],
            ["MARKER", "METTRE UN MARQUEUR", "\z\comspec_atak_native\addons\main\data\map_marker.paa", [0.36, 0.78, 0.42, 1]]
        ];
        if (_m isNotEqualTo "") then { _rows pushBack ["DELETE", "SUPPRIMER", "\z\comspec_atak_native\addons\main\data\ui_close.paa", [0.95, 0.30, 0.25, 1]]; };
        private _rowH = _font * 1.7;
        private _headH = _font * 1.35;
        private _w = ((_font * 11) / _ratio) min (_bw * 0.9);
        private _h = _headH + _rowH * (count _rows) + pixelH * 4;
        private _sp = _map ctrlMapWorldToScreen _pos;
        // Ouvert à droite et sous le curseur, recalé dans la carte s'il déborde.
        private _x0 = ((_sp select 0) + pixelW * 4) min (_bx + _bw - _w - pixelW * 4);
        private _y0 = ((_sp select 1) + pixelH * 4) min (_by + _bh - _h - pixelH * 4);
        _x0 = _x0 max (_bx + pixelW * 4);
        _y0 = _y0 max (_by + pixelH * 4);
        private _mk = { params ["_class", "_p", ["_text", ""]]; [_class, _p, _text, false] call comspec_atak_native_fnc_pageCtrl };
        // Ombre, carte sombre, liseré vert en haut (comme les panneaux de la carte).
        private _sh = ["COMSPEC_RscText", [_x0 + pixelW * 3, _y0 + pixelH * 3, _w, _h]] call _mk;
        _sh ctrlSetBackgroundColor [0, 0, 0, 0.45];
        private _bg = ["COMSPEC_RscText", [_x0, _y0, _w, _h]] call _mk;
        _bg ctrlSetBackgroundColor [0.02, 0.025, 0.022, 0.95];
        private _edge = ["COMSPEC_RscText", [_x0, _y0, _w, pixelH * 2]] call _mk;
        _edge ctrlSetBackgroundColor [0.36, 0.78, 0.42, 0.75];
        // En-tête : coordonnées du point cliqué.
        private _hd = ["COMSPEC_RscText", [_x0 + pixelW * 8, _y0 + pixelH * 2, _w - pixelW * 16, _headH], format ["%1", [_pos] call comspec_atak_native_fnc_gridRef]] call _mk;
        _hd ctrlSetFont "EtelkaMonospaceProBold";
        _hd ctrlSetFontHeight (_fs * 0.95);
        _hd ctrlSetTextColor [0.62, 0.70, 0.66, 1];
        if (_m isNotEqualTo "") then { _hd ctrlSetTooltip format ["Marqueur pointé : %1", [markerText _m, _m] select ((markerText _m) isEqualTo "")]; };
        private _ry = _y0 + pixelH * 2 + _headH;
        private _ih = _rowH * 0.62;
        private _iw = _ih / _ratio;
        {
            _x params ["_key", "_label", "_icon", "_col"];
            private _div = ["COMSPEC_RscText", [_x0 + pixelW * 6, _ry, _w - pixelW * 12, pixelH]] call _mk;
            _div ctrlSetBackgroundColor [1, 1, 1, 0.08];
            private _ic = ["COMSPEC_RscIcon", [_x0 + pixelW * 10, _ry + (_rowH - _ih) / 2, _iw, _ih], _icon] call _mk;
            _ic ctrlSetTextColor _col;
            private _t = ["COMSPEC_RscText", [_x0 + pixelW * 16 + _iw, _ry, _w - _iw - pixelW * 22, _rowH], _label] call _mk;
            _t ctrlSetFont "RobotoCondensedBold";
            _t ctrlSetFontHeight (_font * 0.92);
            if (_key isEqualTo "DELETE") then { _t ctrlSetTextColor _col; };
            private _b = ["COMSPEC_RscButtonOverlay", [_x0, _ry, _w, _rowH]] call _mk;
            _b ctrlAddEventHandler ["ButtonClick", compile format ["['act', '%1'] call comspec_atak_native_fnc_mapCtxMenu;", _key]];
            _ry = _ry + _rowH;
        } forEach _rows;
    };
};
true
