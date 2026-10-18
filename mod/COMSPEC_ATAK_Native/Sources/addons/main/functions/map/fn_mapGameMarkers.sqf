/*
    Marqueurs de la mission (allMapMarkers) sur la carte du téléphone, dessinés par l'ATAK : la carte Arma ne les dessine
    plus (showMarkers = 0 dans COMSPEC_RscMapAtak). Arma les dessinait à taille fixe en pixels (symboles OTAN de 32 px),
    énormes sur le petit écran du téléphone porté à côté de textes à l'échelle de l'écran. Ici :
      ICON       symbole du marqueur à l'échelle de l'écran de l'ATAK (comme les unités), réglage « taille des symboles » ;
      RECTANGLE  zone à la brosse du marqueur (Solid, Border, hachures...) ;
      ELLIPSE    idem ;
      POLYLINE   trait d'un pixel (les traits épais ou fléchés sont repassés par fn_strokeDraw).
    Propriétés relues une fois par seconde (ou dès qu'un marqueur apparaît ou disparaît) ; position des symboles lue
    à chaque image (une cible suivie bouge). Les noms sont écrits par fn_mapMarkerText.
    Params : [carte, facteur des symboles (écran x réglage)]
*/
params ["_map", "_mk"];
private _now = diag_tickTime;
private _cache = uiNamespace getVariable ["COMSPEC_ATAK_GmList", [-1, -1, []]];
if (_now > (_cache select 0) || {(count allMapMarkers) isNotEqualTo (_cache select 1)}) then {
    private _cfg = uiNamespace getVariable "COMSPEC_ATAK_GmCfg";
    if (isNil "_cfg") then { _cfg = createHashMap; uiNamespace setVariable ["COMSPEC_ATAK_GmCfg", _cfg]; };
    private _rgbOf = {
        params ["_cls", "_type"];
        private _key = "c:" + _cls + "|" + _type;
        private _c = _cfg getOrDefault [_key, []];
        if (_c isEqualTo []) then {
            private _num = { _this apply { if (_x isEqualType "") then { call compile _x } else { _x } } };
            _c = (getArray (configFile >> "CfgMarkerColors" >> _cls >> "color")) call _num;
            // « Default » : couleur propre au type de marqueur (CfgMarkers).
            if ((count _c) < 4) then { _c = (getArray (configFile >> "CfgMarkers" >> _type >> "color")) call _num; };
            if ((count _c) < 4) then { _c = [0, 0, 0, 1]; };
            _cfg set [_key, _c];
        };
        +_c
    };
    private _list = [];
    {
        private _a = markerAlpha _x;
        if (_a <= 0.02) then { continue };
        private _shape = markerShape _x;
        switch (_shape) do {
            case "ICON": {
                private _type = markerType _x;
                private _ic = _cfg getOrDefault ["i:" + _type, []];
                if (_ic isEqualTo []) then {
                    _ic = [getText (configFile >> "CfgMarkers" >> _type >> "icon"), getNumber (configFile >> "CfgMarkers" >> _type >> "size"), getNumber (configFile >> "CfgMarkers" >> _type >> "shadow")];
                    if ((_ic select 1) <= 0) then { _ic set [1, 32]; };
                    _cfg set ["i:" + _type, _ic];
                };
                if ((_ic select 0) isEqualTo "" || {(toLower _type) isEqualTo "empty"}) then { continue };
                private _c = [markerColor _x, _type] call _rgbOf;
                _c set [3, (_c select 3) * _a];
                (markerSize _x) params [["_sw", 1], ["_sh", 1]];
                _list pushBack [0, _x, _ic select 0, _c, (_ic select 1) * (abs _sw), (_ic select 1) * (abs _sh), markerDir _x, [0, 1] select ((_ic select 2) > 0)];
            };
            case "POLYLINE": {
                private _flat = markerPolyline _x;
                if ((count _flat) < 4) then { continue };
                private _c = [markerColor _x, ""] call _rgbOf;
                _c set [3, (_c select 3) * _a];
                private _pts = [];
                for "_i" from 0 to ((count _flat) - 2) step 2 do { _pts pushBack [_flat select _i, _flat select (_i + 1), 0]; };
                _list pushBack [1, _x, _pts, _c];
            };
            case "RECTANGLE";
            case "ELLIPSE": {
                private _brush = markerBrush _x;
                private _br = _cfg getOrDefault ["b:" + _brush, []];
                if (_br isEqualTo []) then {
                    _br = [getText (configFile >> "CfgMarkerBrushes" >> _brush >> "texture"), (getNumber (configFile >> "CfgMarkerBrushes" >> _brush >> "drawBorder")) > 0];
                    _cfg set ["b:" + _brush, _br];
                };
                private _c = [markerColor _x, ""] call _rgbOf;
                _c set [3, (_c select 3) * _a];
                (markerSize _x) params [["_sw", 1], ["_sh", 1]];
                _list pushBack [[2, 3] select (_shape isEqualTo "ELLIPSE"), _x, getMarkerPos _x, _c, _sw, _sh, markerDir _x, _br select 0, _br select 1];
            };
        };
    } forEach allMapMarkers;
    _cache = [_now + 1, count allMapMarkers, _list];
    uiNamespace setVariable ["COMSPEC_ATAK_GmList", _cache];
};

(ctrlPosition _map) params ["_mx", "_my", "_mw", "_mh"];
private _mg = 0.04;
{
    switch (_x select 0) do {
        case 0: {
            _x params ["", "_m", "_icon", "_c", "_w", "_h", "_dir", "_shadow"];
            private _pos = getMarkerPos _m;
            (_map ctrlMapWorldToScreen _pos) params ["_sx", "_sy"];
            if (_sx < (_mx - _mg) || {_sx > (_mx + _mw + _mg)} || {_sy < (_my - _mg)} || {_sy > (_my + _mh + _mg)}) then { continue };
            _map drawIcon [_icon, _c, _pos, (_w * _mk) max 7, (_h * _mk) max 7, _dir, "", _shadow];
        };
        case 1: {
            _x params ["", "", "_pts", "_c"];
            for "_i" from 0 to ((count _pts) - 2) do { _map drawLine [_pts select _i, _pts select (_i + 1), _c]; };
        };
        default {
            _x params ["_kind", "", "_pos", "_c", "_w", "_h", "_dir", "_tex", "_border"];
            if (_kind isEqualTo 2) then {
                _map drawRectangle [_pos, _w, _h, _dir, _c, _tex];
                if (_border && {_tex isNotEqualTo ""}) then { _map drawRectangle [_pos, _w, _h, _dir, _c, ""]; };
            } else {
                _map drawEllipse [_pos, _w, _h, _dir, _c, _tex];
                if (_border && {_tex isNotEqualTo ""}) then { _map drawEllipse [_pos, _w, _h, _dir, _c, ""]; };
            };
        };
    };
} forEach (_cache select 2);
