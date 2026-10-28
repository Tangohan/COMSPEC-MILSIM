/*
    Noms des repères sur la carte du téléphone, dessinés par l'ATAK (la carte Arma n'écrit plus le texte des
    marqueurs : sizeExLabel minuscule dans COMSPEC_RscMapAtak). Style au choix (réglage CBA
    comspec_atak_native_map_marker_style, aussi dans Réglages > Carte) :
      PLATE    plaque sombre, liseré à la couleur du repère, capitales grasses (par défaut) ;
      PILL     pilule sombre arrondie, point de couleur, casse d'origine ;
      CARD     cartouche décalé relié au symbole par un trait, ligne 2 distance · azimut depuis moi ;
      OUTLINE  texte blanc détouré de noir, sans fond ;
      CLASSIC  texte Arma d'avant : couleur du repère, sans fond.
    Repère de cible accrochée (« LOCK 098 · MH-6M Little Bird (drone) ») : LOCK en rouge, nom de la cible à côté.
    Désencombrement : rien en zoom très éloigné sauf les LOCK et le repère sélectionné ; une étiquette qui chevauche
    une autre passe à gauche du symbole, sinon n'est pas écrite ; 40 au plus.
    Les rectangles posés sont gardés (COMSPEC_ATAK_LblRects) pour que les noms de lieux (fn_mapPlaceText) les évitent.
    Params : [carte, taille des textes, facteur écran, échelle de la carte, repères déjà titrés à ignorer,
              cartouches ENI actifs (fn_mapLabels)]
*/
params ["_map", "_ts", "_kt", "_scale", ["_skip", []], ["_boxOn", false]];
// Bandeau d'information de la carte compté comme déjà occupé : aucun nom ne passe dessous.
private _rects = [_map] call comspec_atak_native_fnc_mapHudRects;
uiNamespace setVariable ["COMSPEC_ATAK_LblRects", _rects];
if !(missionNamespace getVariable ["comspec_atak_native_map_show_marker_text", true]) exitWith {};
private _style = toUpper (missionNamespace getVariable ["comspec_atak_native_map_marker_style", "plate"]);
private _now = diag_tickTime;

// Candidats (repères ponctuels titrés et visibles), refaits une fois par seconde depuis le magasin des marqueurs.
private _cand = missionNamespace getVariable ["COMSPEC_ATAK_MkTextCand", [-1, []]];
if (_now > (_cand select 0)) then {
    private _colCache = uiNamespace getVariable "COMSPEC_ATAK_MkColorCache";
    if (isNil "_colCache") then { _colCache = createHashMap; uiNamespace setVariable ["COMSPEC_ATAK_MkColorCache", _colCache]; };
    private _sizeCache = uiNamespace getVariable "COMSPEC_ATAK_MkSizeCache";
    if (isNil "_sizeCache") then { _sizeCache = createHashMap; uiNamespace setVariable ["COMSPEC_ATAK_MkSizeCache", _sizeCache]; };
    private _list = [];
    {
        private _m = _y;
        private _txt = _m getOrDefault ["text", ""];
        if (_txt isEqualTo "") then { continue };
        // Nom posé automatiquement (palette, LOCK…) : relu dans la langue du mod, quelle que soit celle de son auteur.
        _txt = [_txt] call comspec_atak_native_fnc_markerAutoName;
        if ((toUpper (_m getOrDefault ["shape", "ICON"])) isNotEqualTo "ICON") then { continue };
        if ((_m getOrDefault ["alpha", 1]) <= 0.05) then { continue };
        private _type = _m getOrDefault ["type", "mil_dot"];
        if ((toLower _type) isEqualTo "empty") then { continue };
        // Couleur du repère (CfgMarkerColors) ; « Default » prend la couleur de la classe du marqueur.
        private _cls = _m getOrDefault ["color", "ColorGreen"];
        private _c = _colCache getOrDefault [_cls, []];
        if (_c isEqualTo []) then {
            private _a = (getArray (configFile >> "CfgMarkerColors" >> _cls >> "color")) apply { if (_x isEqualType "") then { call compile _x } else { _x } };
            _c = [_a];
            _colCache set [_cls, _c];
        };
        _c = +(_c select 0);
        if ((count _c) < 4) then {
            _c = (getArray (configFile >> "CfgMarkers" >> _type >> "color")) apply { if (_x isEqualType "") then { call compile _x } else { _x } };
            if ((count _c) < 4) then { _c = [0.36, 0.78, 0.42, 1]; };
        };
        _c set [3, 1];
        // Taille du symbole dessiné par la carte (CfgMarkers size x taille du marqueur), pour décoller l'étiquette.
        private _sz = _sizeCache getOrDefault [_type, -1];
        if (_sz < 0) then { _sz = getNumber (configFile >> "CfgMarkers" >> _type >> "size"); if (_sz <= 0) then { _sz = 32; }; _sizeCache set [_type, _sz]; };
        _sz = _sz * (((_m getOrDefault ["size", [1, 1]]) param [0, 1]) max 0.3);
        private _id = _m getOrDefault ["id", _x];
        private _eni = ((toLower _type) select [0, 2]) isEqualTo "o_" || {(toUpper (_txt select [0, 4])) in ["ENI ", "ENY "]};
        // LOCK 098 · MH-6M Little Bird (drone) -> ["LOCK 098", "MH-6M Little Bird · drone"]
        private _lock = [];
        if ((_txt select [0, 5]) isEqualTo "LOCK " && {(_txt find " · ") > 0}) then {
            private _i = _txt find " · ";
            private _rest = _txt select [_i + (count " · ")];
            private _p = _rest find " (";
            if (_p > 0 && {(_rest select [(count _rest) - 1]) isEqualTo ")"}) then { _rest = format ["%1 · %2", _rest select [0, _p], _rest select [_p + 2, (count _rest) - _p - 3]]; };
            private _j = _rest find " · ";
            _lock = [_txt select [0, _i], _rest, if (_j > 0) then { _rest select [0, _j] } else { _rest }];
        };
        _list pushBack [_id, _m getOrDefault ["local", false], _m getOrDefault ["position", [0, 0, 0]], _txt, _c, _sz, _lock, _eni];
    } forEach ((uiNamespace getVariable ["COMSPEC_ATAK_Data", createHashMap]) getOrDefault ["markers", createHashMap]);
    // LOCK d'abord (jamais masqués par un autre nom), puis l'ordre des marqueurs.
    _list = (_list select { (count (_x select 6)) > 0 }) + (_list select { (count (_x select 6)) isEqualTo 0 });
    _cand = [_now + 1, _list];
    missionNamespace setVariable ["COMSPEC_ATAK_MkTextCand", _cand];
};
if ((count (_cand select 1)) isEqualTo 0) exitWith {};

(ctrlPosition _map) params ["_mx", "_my", "_mw", "_mh"];
private _o0 = _map ctrlMapScreenToWorld [_mx, _my];
private _mpx = (((_map ctrlMapScreenToWorld [_mx + 0.1, _my]) select 0) - (_o0 select 0)) / 0.1;
private _mpy = ((_o0 select 1) - ((_map ctrlMapScreenToWorld [_mx, _my + 0.1]) select 1)) / 0.1;
if (_mpx <= 0 || {_mpy <= 0}) exitWith {};
// Vue en coordonnées du monde (+ marge : une cible suivie bouge entre deux relectures du magasin) : les repères hors vue
// sont écartés sur leur position du magasin, avant les lectures en direct (getMarkerPos, markerAlpha) et la projection.
private _o1 = _map ctrlMapScreenToWorld [_mx + _mw, _my + _mh];
private _mg = ((abs ((_o1 select 0) - (_o0 select 0))) * 0.25) max 50;
private _vx0 = ((_o0 select 0) min (_o1 select 0)) - _mg; private _vx1 = ((_o0 select 0) max (_o1 select 0)) + _mg;
private _vy0 = ((_o0 select 1) min (_o1 select 1)) - _mg; private _vy1 = ((_o0 select 1) max (_o1 select 1)) + _mg;
private _far = _scale >= 0.3;
private _close = _scale < 0.08;
private _sel = (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["selectedMarker", ""];
private _fill = "#(rgb,8,8,3)color(1,1,1,1)";
private _none = "#(argb,8,8,3)color(0,0,0,0)";
private _fB = COMSPEC_ATAK_FontSet select 1;
private _fR = COMSPEC_ATAK_FontSet select 0;
// Taille relevée (0,021 -> 0,024) pour la petite carte du téléphone porté, puis ramenée à 0,020 en main : les plaques
// (« Z ENT MÉDICALE ») écrasaient le bandeau d'information et les symboles. Le téléphone porté garde 0,024.
private _worn = !((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["interactive", false]);
private _fs = ([0.020, 0.024] select _worn) * _ts * _kt;
private _fs2 = _fs * 0.88;
private _h = _fs * 1.22;
private _px = pixelW;
// Symboles des marqueurs à l'échelle de l'écran (fn_mapGameMarkers) : même facteur pour décoller le nom.
private _mkS = uiNamespace getVariable ["COMSPEC_ATAK_MkScale", 1];
private _gap = 4 * _px;
private _bar = 3 * _px;
private _pad = 4 * _px;
private _padC = 6 * _px;
private _fsC = 0.022 * safeZoneH * (_ts / 1.25) * _kt;
private _rad = _h / 2;
private _radX = _rad * _px / pixelH;
private _pillBg = [0.085, 0.10, 0.115, 1];
private _red = [0.91, 0.28, 0.23, 1];
private _redTxt = [1, 0.50, 0.44, 1];
private _toW = { params ["_sx", "_sy"]; private _w = _map ctrlMapScreenToWorld [_sx, _sy]; [_w select 0, _w select 1, 0] };
private _rect = { params ["_x0", "_y0", "_x1", "_y1", "_c", ["_t", _fill]]; _map drawRectangle [[(_x0 + _x1) / 2, (_y0 + _y1) / 2] call _toW, ((_x1 - _x0) / 2) * _mpx, ((_y1 - _y0) / 2) * _mpy, 0, _c, _t]; };
private _hit = { params ["_r"]; (_rects findIf { (_r select 0) < (_x select 2) && {(_r select 2) > (_x select 0)} && {(_r select 1) < (_x select 3)} && {(_r select 3) > (_x select 1)} }) >= 0 };
private _distCache = uiNamespace getVariable "COMSPEC_ATAK_LblCache";
if (isNil "_distCache" || {(count _distCache) > 400}) then { _distCache = createHashMap; uiNamespace setVariable ["COMSPEC_ATAK_LblCache", _distCache]; };

private _n = 0;
{
    if (_n >= 40) then { break };
    _x params ["_id", "_local", "_pos", "_txt", "_c", "_sz", "_lock", "_eni"];
    if (_id in _skip) then { continue };
    // Contact ENI : déjà dans son cartouche rouge (fn_mapLabels).
    if (_eni && {_boxOn}) then { continue };
    private _isLock = (count _lock) > 0;
    if (_far && {!_isLock} && {_id isNotEqualTo _sel}) then { continue };
    if (!_isLock && {_id isNotEqualTo _sel} && {
        private _wx = _pos param [0, 0]; private _wy = _pos param [1, 0];
        _wx < _vx0 || {_wx > _vx1} || {_wy < _vy0} || {_wy > _vy1}
    }) then { continue };
    // Repère du jeu : position et titre lus en direct (une cible suivie bouge entre deux relectures du magasin).
    if (_local) then {
        _pos = getMarkerPos _id;
        if ((markerAlpha _id) <= 0.05) then { continue };
    };
    private _sp = _map ctrlMapWorldToScreen _pos;
    _sp params ["_sx", "_sy"];
    if (_sx < _mx || {_sx > (_mx + _mw)} || {_sy < _my} || {_sy > (_my + _mh)}) then { continue };
    private _half = ((_sz * _mkS * 0.5 * _px) max (4 * _px)) min (40 * _px);
    private _force = _isLock || {_id isEqualTo _sel};

    // 1. Textes et taille de l'étiquette selon le style.
    private _t1 = ""; private _t2 = ""; private _f1 = _fB; private _w1 = 0; private _w2 = 0; private _bw = 0; private _bh = _h;
    private _card = false;
    switch (_style) do {
        case "CLASSIC": {
            _f1 = _fR;
            _t1 = _txt;
            _w1 = [_t1, _f1, _fsC] call comspec_atak_native_fnc_mapTextW;
            _bw = _w1; _bh = _fsC;
        };
        case "OUTLINE": {
            _t1 = toUpper ([_txt, format ["%1 · %2", _lock param [0, ""], _lock param [1, ""]]] select _isLock);
            if (_isLock && {_far}) then { _t1 = toUpper (_lock select 0); };
            _w1 = [_t1, _f1, _fs] call comspec_atak_native_fnc_mapTextW;
            _bw = _w1;
        };
        case "PILL": {
            _f1 = _fR;
            _t1 = [_txt, format ["%1 · %2", _lock param [0, ""], _lock param [2, ""]]] select _isLock;
            if (_isLock && {_far}) then { _t1 = _lock select 0; };
            _w1 = [_t1, _f1, _fs] call comspec_atak_native_fnc_mapTextW;
            _bw = _radX * 3 + _w1;
        };
        case "CARD": {
            _t1 = toUpper ([_txt, _lock param [0, ""]] select _isLock);
            // Ligne distance · cap : mesurée chiffres remplacés par 0 (chasse fixe), sinon chaque mètre parcouru ajoutait
            // une entrée au cache partagé des largeurs (vidé à 800), et tous les libellés étaient remesurés.
            private _t2w = "";
            if (_isLock) then { _t2 = _lock select 1; _t2w = _t2; } else {
                if (_close) then {
                    private _dc = _distCache getOrDefault ["mk:" + _id, []];
                    if ((count _dc) < 2 || {_now > (_dc select 0)}) then {
                        private _d = player distance2D _pos;
                        _dc = [_now + 0.5, format ["%1 · %2°", [format ["%1 m", round _d], format ["%1 km", [(_d / 1000) toFixed 1, ((_d / 1000) toFixed 1) splitString "." joinString ","] select ((missionNamespace getVariable ["COMSPEC_ATAK_Lang", "en"]) isEqualTo "fr")]] select (_d >= 1000), (round (player getDir _pos)) mod 360]];
                        _distCache set ["mk:" + _id, _dc];
                    };
                    _t2 = _dc select 1;
                    _t2w = _t2 regexReplace ["[0-9]", "0"];
                };
            };
            if (_far) then { _t2 = ""; };
            _w1 = [_t1, _f1, _fs] call comspec_atak_native_fnc_mapTextW;
            if (_t2 isNotEqualTo "") then { _w2 = [_t2w, _fR, _fs2] call comspec_atak_native_fnc_mapTextW; };
            _bw = (_w1 max _w2) + _padC * 2;
            _bh = _fs * 1.15 + ([0, _fs2 * 1.1] select (_t2 isNotEqualTo "")) + 5 * pixelH;
            _card = true;
        };
        default {
            _t1 = toUpper ([_txt, _lock param [0, ""]] select _isLock);
            if (_isLock && {!_far}) then { _t2 = toUpper (_lock select 1); };
            _w1 = [_t1, _f1, _fs] call comspec_atak_native_fnc_mapTextW;
            if (_t2 isNotEqualTo "") then { _w2 = [_t2, _fR, _fs2] call comspec_atak_native_fnc_mapTextW; };
            _bw = _bar + _pad + _w1 + _pad + ([0, _w2 + _pad * 1.6] select (_t2 isNotEqualTo ""));
        };
    };

    // 2. Place : à droite du symbole, sinon à gauche, sinon rien (LOCK et repère sélectionné toujours écrits).
    private _off = [_half + _gap, _half + 12 * _px] select _card;
    private _y0 = [_sy - _bh / 2, _sy - _bh - 6 * pixelH] select _card;
    private _x0 = _sx + _off;
    private _r = [_x0, _y0, _x0 + _bw, _y0 + _bh];
    if (!_force && {[_r] call _hit}) then {
        _x0 = _sx - _off - _bw;
        _r = [_x0, _y0, _x0 + _bw, _y0 + _bh];
    };
    if (!_force && {[_r] call _hit}) then { continue };
    _rects pushBack _r;
    private _x1 = _x0 + _bw;
    private _y1 = _y0 + _bh;
    private _tc = [[1, 1, 1, 1], _redTxt] select _isLock;

    // 3. Dessin.
    switch (_style) do {
        case "CLASSIC": {
            _map drawIcon [_none, _c, [_x0, _sy] call _toW, 0, 0, 0, _t1, 1, _fsC, _f1, "right"];
        };
        case "OUTLINE": {
            _map drawIcon [_none, _tc, [_x0, _sy] call _toW, 0, 0, 0, _t1, 2, _fs, _f1, "right"];
        };
        case "PILL": {
            [_x0 + _radX, _y0, _x1 - _radX, _y1, _pillBg] call _rect;
            { _map drawEllipse [[_x, _sy] call _toW, _rad * _mpy, _rad * _mpy, 0, _pillBg, _fill]; } forEach [_x0 + _radX, _x1 - _radX];
            _map drawEllipse [[_x0 + _radX * 1.1, _sy] call _toW, _rad * 0.36 * _mpy, _rad * 0.36 * _mpy, 0, [_c, _red] select _isLock, _fill];
            _map drawIcon [_none, _tc, [_x0 + _radX * 2.1, _sy] call _toW, 0, 0, 0, _t1, 0, _fs, _f1, "right"];
        };
        case "CARD": {
            private _ax = [_x1, _x0] select (_x0 > _sx);
            _map drawLine [[_pos select 0, _pos select 1, 0], [_ax, _y1] call _toW, [[0.86, 0.89, 0.92, 0.7], _red] select _isLock];
            [_x0, _y0, _x1, _y1, [0.04, 0.05, 0.065, 0.86]] call _rect;
            [_x0, _y0, _x1, _y0 + 2 * pixelH, [_c, _red] select _isLock] call _rect;
            if (_id isEqualTo _sel) then { [_x0, _y0, _x1, _y1, [0.36, 0.78, 0.42, 1], ""] call _rect; };
            private _ty = _y0 + 3 * pixelH + _fs * 0.58;
            _map drawIcon [_none, [[1, 1, 1, 1], _redTxt] select _isLock, [_x0 + _padC, _ty] call _toW, 0, 0, 0, _t1, 0, _fs, _f1, "right"];
            if (_t2 isNotEqualTo "") then { _map drawIcon [_none, [0.70, 0.75, 0.73, 1], [_x0 + _padC, _ty + _fs * 0.55 + _fs2 * 0.6] call _toW, 0, 0, 0, _t2, 0, _fs2, _fR, "right"]; };
        };
        default {
            // PLATE : plaque sombre, liseré de couleur à gauche, capitales grasses ; LOCK bordé de rouge.
            [_x0, _y0, _x1, _y1, [0.035, 0.042, 0.038, 0.82]] call _rect;
            [_x0, _y0, _x0 + _bar, _y1, [_c, _red] select _isLock] call _rect;
            if (_isLock) then { [_x0, _y0, _x1, _y1, [0.91, 0.28, 0.23, 0.75], ""] call _rect; };
            if (_id isEqualTo _sel) then { [_x0, _y0, _x1, _y1, [0.36, 0.78, 0.42, 1], ""] call _rect; };
            private _tx = _x0 + _bar + _pad;
            _map drawIcon [_none, _tc, [_tx, _sy] call _toW, 0, 0, 0, _t1, 0, _fs, _f1, "right"];
            if (_t2 isNotEqualTo "") then {
                private _sx2 = _tx + _w1 + _pad * 0.8;
                _map drawLine [[_sx2, _sy - _h * 0.28] call _toW, [_sx2, _sy + _h * 0.28] call _toW, [1, 1, 1, 0.3]];
                _map drawIcon [_none, [0.76, 0.79, 0.78, 1], [_sx2 + _pad * 0.8, _sy] call _toW, 0, 0, 0, _t2, 0, _fs2, _fR, "right"];
            };
        };
    };
    _n = _n + 1;
} forEach (_cand select 1);
