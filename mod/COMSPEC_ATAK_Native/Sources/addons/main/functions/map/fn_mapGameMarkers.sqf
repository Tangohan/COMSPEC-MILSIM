/*
    Marqueurs de la mission (allMapMarkers) sur la carte du téléphone, dessinés par l'ATAK : la carte Arma ne les dessine
    plus (showMarkers = 0 dans COMSPEC_RscMapAtak). Arma les dessinait à taille fixe en pixels (symboles OTAN de 32 px),
    énormes sur le petit écran du téléphone porté à côté de textes à l'échelle de l'écran. Ici :
      ICON       symbole du marqueur à l'échelle de l'écran de l'ATAK (comme les unités), réglage « taille des symboles » ;
               point simple (…_dot) doublé et éclairci s'il est noir, sinon invisible ;
      RECTANGLE  zone à la brosse du marqueur (Solid, Border, hachures...) ;
      ELLIPSE    idem ;
      POLYLINE   trait d'un pixel (les traits épais ou fléchés sont repassés par fn_strokeDraw).
    Propriétés relues pour le seul marqueur créé, modifié ou supprimé (relecture complète toutes les 30 s) ; position des symboles lue
    à chaque image (une cible suivie bouge). Les noms sont écrits par fn_mapMarkerText.
    Params : [carte, facteur des symboles (écran x réglage)]
*/
params ["_map", "_mk"];
private _now = diag_tickTime;
// Cache : [prochaine relecture complète, entrées dans l'ordre des marqueurs, index nom -> rang].
// Caches des marqueurs dans missionNamespace (uiNamespace survit à la mission : marqueurs de la partie précédente
// dessinés jusqu'à 30 s après un redémarrage, les icônes à l'origine de la carte).
private _cache = missionNamespace getVariable ["COMSPEC_ATAK_GmList", [-1, [], createHashMap]];
if ((count _cache) < 3 || {!((_cache select 2) isEqualType createHashMap)}) then { _cache = [-1, [], createHashMap]; };
// Marqueurs changés depuis la dernière image (EH MarkerCreated / Deleted / Updated, XEH_postInitClient) : seuls eux sont
// relus. Avant, un seul marqueur déplacé (piste Athena, marqueur suivi d'une mission) faisait relire toutes les propriétés
// de tous les marqueurs, plusieurs fois par seconde : saccade régulière carte ouverte, surtout téléphone porté.
private _dirty = missionNamespace getVariable ["COMSPEC_ATAK_MkDirtyGm", createHashMap];
private _full = _now > (_cache select 0) || {"" in _dirty};
if (_full || {(count _dirty) > 0}) then {
    missionNamespace setVariable ["COMSPEC_ATAK_MkDirtyGm", createHashMap];
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
    // Entrée de dessin d'un marqueur ; [] : rien à dessiner (supprimé, transparent, sans image).
    private _entry = {
        private _a = markerAlpha _x;
        if (_a <= 0.02) exitWith { [] };
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
                if ((_ic select 0) isEqualTo "" || {(toLower _type) isEqualTo "empty"}) exitWith { [] };
                private _c = [markerColor _x, _type] call _rgbOf;
                _c set [3, (_c select 3) * _a];
                (markerSize _x) params [["_sw", 1], ["_sh", 1]];
                // Point simple (mil_dot, hd_dot) : le point ne remplit qu'un quart de son image, noir par défaut ; dessiné
                // ainsi il faisait 7 px sombres sur la carte, invisible. Agrandi, et éclairci s'il est trop sombre.
                if ((count _type) > 3 && {((toLower _type) select [(count _type) - 3]) isEqualTo "dot"}) then {
                    _sw = _sw * 2; _sh = _sh * 2;
                    if (((_c select 0) + (_c select 1) + (_c select 2)) < 0.45) then { _c = [0.96, 0.84, 0.25, _c select 3]; };
                };
                [0, _x, _ic select 0, _c, (_ic select 1) * (abs _sw), (_ic select 1) * (abs _sh), markerDir _x, [0, 1] select ((_ic select 2) > 0)]
            };
            case "POLYLINE": {
                private _flat = markerPolyline _x;
                if ((count _flat) < 4) exitWith { [] };
                private _c = [markerColor _x, ""] call _rgbOf;
                _c set [3, (_c select 3) * _a];
                private _pts = [];
                for "_i" from 0 to ((count _flat) - 2) step 2 do { _pts pushBack [_flat select _i, _flat select (_i + 1), 0]; };
                // Boîte du trait [xmin, ymin, xmax, ymax] : trait hors de la vue non dessiné.
                private _xs = _pts apply { _x select 0 };
                private _ys = _pts apply { _x select 1 };
                [1, _x, _pts, _c, [selectMin _xs, selectMin _ys, selectMax _xs, selectMax _ys]]
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
                // Boîte de la zone (rayon de la diagonale : couvre toute rotation) : zone hors de la vue non dessinée.
                private _mp = getMarkerPos _x;
                private _rr = sqrt (_sw * _sw + _sh * _sh);
                [[2, 3] select (_shape isEqualTo "ELLIPSE"), _x, _mp, _c, _sw, _sh, markerDir _x, _br select 0, _br select 1,
                    [(_mp select 0) - _rr, (_mp select 1) - _rr, (_mp select 0) + _rr, (_mp select 1) + _rr]]
            };
            default { [] };
        }
    };
    if (_full) then {
        // Relecture complète : au premier dessin, si trop de marqueurs ont changé d'un coup, et toutes les 30 s par sécurité.
        private _list = [];
        private _index = createHashMap;
        {
            private _e = call _entry;
            if ((count _e) > 0) then { _index set [_x, count _list]; _list pushBack _e; };
        } forEach allMapMarkers;
        _cache = [_now + 30, _list, _index];
    } else {
        // Marqueur changé : son entrée est remplacée à sa place (ordre de dessin gardé), ajoutée s'il est nouveau,
        // vidée s'il a disparu ; la liste est recompactée à la prochaine relecture complète.
        _cache params ["", "_list", "_index"];
        {
            private _e = call _entry;
            private _i = _index getOrDefault [_x, -1];
            if (_i >= 0) then { _list set [_i, _e]; } else {
                if ((count _e) > 0) then { _index set [_x, count _list]; _list pushBack _e; };
            };
        } forEach (keys _dirty);
    };
    missionNamespace setVariable ["COMSPEC_ATAK_GmList", _cache];
};

(ctrlPosition _map) params ["_mx", "_my", "_mw", "_mh"];
private _mg = 0.04;
// Vue en coordonnées du monde [xmin, ymin, xmax, ymax] (marge comprise) : traits et zones qui n'y touchent pas sautés.
private _wa = _map ctrlMapScreenToWorld [_mx - _mg, _my - _mg];
private _wb = _map ctrlMapScreenToWorld [_mx + _mw + _mg, _my + _mh + _mg];
private _view = [(_wa select 0) min (_wb select 0), (_wa select 1) min (_wb select 1), (_wa select 0) max (_wb select 0), (_wa select 1) max (_wb select 1)];
private _off = {
    params ["_bb"];
    (count _bb) > 3 && {(_bb select 2) < (_view select 0) || {(_bb select 0) > (_view select 2)} || {(_bb select 3) < (_view select 1)} || {(_bb select 1) > (_view select 3)}}
};
{
    if ((count _x) isEqualTo 0) then { continue };
    switch (_x select 0) do {
        case 0: {
            _x params ["", "_m", "_icon", "_c", "_w", "_h", "_dir", "_shadow"];
            private _pos = getMarkerPos _m;
            (_map ctrlMapWorldToScreen _pos) params ["_sx", "_sy"];
            if (_sx < (_mx - _mg) || {_sx > (_mx + _mw + _mg)} || {_sy < (_my - _mg)} || {_sy > (_my + _mh + _mg)}) then { continue };
            _map drawIcon [_icon, _c, _pos, (_w * _mk) max 7, (_h * _mk) max 7, _dir, "", _shadow];
        };
        case 1: {
            _x params ["", "", "_pts", "_c", ["_bb", []]];
            if ([_bb] call _off) then { continue };
            for "_i" from 0 to ((count _pts) - 2) do { _map drawLine [_pts select _i, _pts select (_i + 1), _c]; };
        };
        default {
            _x params ["_kind", "", "_pos", "_c", "_w", "_h", "_dir", "_tex", "_border", ["_bb", []]];
            if ([_bb] call _off) then { continue };
            if (_kind isEqualTo 2) then {
                _map drawRectangle [_pos, _w, _h, _dir, _c, _tex];
                if (_border && {_tex isNotEqualTo ""}) then { _map drawRectangle [_pos, _w, _h, _dir, _c, ""]; };
            } else {
                _map drawEllipse [_pos, _w, _h, _dir, _c, _tex];
                if (_border && {_tex isNotEqualTo ""}) then { _map drawEllipse [_pos, _w, _h, _dir, _c, ""]; };
            };
        };
    };
} forEach (_cache select 1);
