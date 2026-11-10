/*
    Noms des repères sur la carte du téléphone, dessinés par l'ATAK (la carte Arma n'écrit plus le texte des
    marqueurs : sizeExLabel minuscule dans COMSPEC_RscMapAtak). Style au choix (réglage CBA
    comspec_atak_native_map_marker_style, aussi dans Réglages > Carte) :
      PLATE    plaque sombre, liseré à la couleur du repère, capitales grasses (par défaut) ;
      PILL     pilule sombre arrondie, point de couleur, casse d'origine ;
      CARD     cartouche décalé relié au symbole par un trait, ligne 2 distance · azimut depuis moi ;
      OUTLINE  texte blanc détouré de noir, sans fond ;
      CLASSIC  texte Arma d'avant : couleur du repère, sans fond ;
      TAC      petite plaque sombre en monospace sous le symbole : prend la place du style choisi en style tactique (fn_mapColors « tac »).
    Repère de cible accrochée (« LOCK 098 · MH-6M Little Bird (drone) ») : LOCK en rouge, nom de la cible à côté.
    Taille, place et niveau de détail : fn_mapLbl (étiquettes demandées pendant l'image de calcul, rien aux autres images).
    Vue lointaine : seulement les LOCK et le repère sélectionné ; vue moyenne : noms abrégés proprement (16 caractères,
    mots entiers) ; vue rapprochée : noms complets (24) et seconde ligne. Place : à droite du symbole, sinon à gauche,
    dessous, dessus (TAC : dessous d'abord ; CARD : coins), jamais sur une autre étiquette, un symbole ni le bandeau,
    sinon pas écrite (LOCK et sélection toujours écrits) ; 40 au plus.
    Fond des plaques à l'opacité choisie (volet CONFIGURATION), liseré à la couleur du repère, éclairci s'il est noir.
    Params : [carte, taille des textes, facteur écran, échelle de la carte, repères déjà titrés à ignorer,
              cartouches ENI actifs (fn_mapLabels)]
*/
params ["_map", "_ts", "_kt", "_scale", ["_skip", []], ["_boxOn", false]];
private _LF = uiNamespace getVariable ["COMSPEC_ATAK_LblF", createHashMap];
if !(_LF getOrDefault ["lay", false]) exitWith {};
if !(missionNamespace getVariable ["comspec_atak_native_map_show_marker_text", true]) exitWith {};
private _style = toUpper (missionNamespace getVariable ["comspec_atak_native_map_marker_style", "plate"]);
private _now = diag_tickTime;
private _pal = [] call comspec_atak_native_fnc_mapColors;
if (_pal get "tac") then { _style = "TAC"; };
private _eniOn = profileNamespace getVariable ["COMSPEC_ATAK_LblEni", true];

// Candidats (repères ponctuels titrés et visibles), refaits depuis le magasin des marqueurs quand il change (au plus
// une fois par seconde ; toutes les 15 s par sécurité). Avant : refaits chaque seconde, marqueurs inchangés compris
// (nom automatique et couleur relus pour chaque marqueur : à-coup régulier dans une mission à centaines de marqueurs).
private _cand = missionNamespace getVariable ["COMSPEC_ATAK_MkTextCand", [-1, [], -1, 0, ""]];
private _cRev = ((uiNamespace getVariable ["COMSPEC_ATAK_Data", createHashMap]) getOrDefault ["revisions", createHashMap]) getOrDefault ["markers", -1];
private _cLang = missionNamespace getVariable ["COMSPEC_ATAK_Lang", "en"];
if (_now > (_cand select 0) && {(_cand param [2, -1]) isNotEqualTo _cRev || {_now > (_cand param [3, 0])} || {(_cand param [4, ""]) isNotEqualTo _cLang}}) then {
    private _sizeCache = uiNamespace getVariable "COMSPEC_ATAK_MkSizeCache";
    if (isNil "_sizeCache") then { _sizeCache = createHashMap; uiNamespace setVariable ["COMSPEC_ATAK_MkSizeCache", _sizeCache]; };
    private _list = [];
    {
        private _m = _y;
        private _txt = _m getOrDefault ["text", ""];
        if (_txt isEqualTo "") then { continue };
        // Marqueurs natifs des routes et plans (fn_markerOwned) : leur nom est déjà écrit par fn_routeDraw / fn_zoneDraw.
        private _mid0 = _m getOrDefault ["id", _x];
        if ((_mid0 find "/77000") > 0 || {(_mid0 find "comspec_shape_prv_") isEqualTo 0}) then { continue };
        // Nom posé automatiquement (palette, LOCK…) : relu dans la langue du mod, quelle que soit celle de son auteur.
        _txt = [_txt] call comspec_atak_native_fnc_markerAutoName;
        if ((toUpper (_m getOrDefault ["shape", "ICON"])) isNotEqualTo "ICON") then { continue };
        if ((_m getOrDefault ["alpha", 1]) <= 0.05) then { continue };
        private _type = _m getOrDefault ["type", "mil_dot"];
        if ((toLower _type) isEqualTo "empty") then { continue };
        // Couleur du repère (CfgMarkerColors) ; « Default » prend la couleur de la classe du marqueur.
        private _cls = _m getOrDefault ["color", "ColorGreen"];
        private _c = [_cls, _type, [0.36, 0.78, 0.42, 1]] call comspec_atak_native_fnc_markerColor;
        _c set [3, 1];
        // Liseré de la plaque (fond sombre) : éclairci s'il est trop sombre ; texte CLASSIQUE : seulement sur carte sombre.
        private _cL = [+_c] call (_pal get "liftLbl");
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
            _lock = [_txt select [0, _i], ["cut", _rest, 28] call comspec_atak_native_fnc_mapFmt, ["cut", if (_j > 0) then { _rest select [0, _j] } else { _rest }, 22] call comspec_atak_native_fnc_mapFmt];
        };
        if ((count _lock) isEqualTo 0) then { _txt = ["cut", _txt, 24] call comspec_atak_native_fnc_mapFmt; };
        _list pushBack [_id, _m getOrDefault ["local", false], _m getOrDefault ["position", [0, 0, 0]], _txt, _c, _sz, _lock, _eni, _cL];
    } forEach ((uiNamespace getVariable ["COMSPEC_ATAK_Data", createHashMap]) getOrDefault ["markers", createHashMap]);
    // LOCK d'abord (jamais masqués par un autre nom), puis l'ordre des marqueurs.
    _list = (_list select { (count (_x select 6)) > 0 }) + (_list select { (count (_x select 6)) isEqualTo 0 });
    _cand = [_now + 1, _list, _cRev, _now + 15, _cLang];
    missionNamespace setVariable ["COMSPEC_ATAK_MkTextCand", _cand];
};
if ((count (_cand select 1)) isEqualTo 0) exitWith {};

(ctrlPosition _map) params ["_mx", "_my", "_mw", "_mh"];
// Vue en coordonnées du monde (+ marge : une cible suivie bouge entre deux relectures du magasin) : les repères hors vue
// sont écartés sur leur position du magasin, avant les lectures en direct (getMarkerPos, markerAlpha) et la projection.
private _o0 = _map ctrlMapScreenToWorld [_mx, _my];
private _o1 = _map ctrlMapScreenToWorld [_mx + _mw, _my + _mh];
private _mg = ((abs ((_o1 select 0) - (_o0 select 0))) * 0.25) max 50;
private _vx0 = ((_o0 select 0) min (_o1 select 0)) - _mg; private _vx1 = ((_o0 select 0) max (_o1 select 0)) + _mg;
private _vy0 = ((_o0 select 1) min (_o1 select 1)) - _mg; private _vy1 = ((_o0 select 1) max (_o1 select 1)) + _mg;
private _lod = _LF get "lod";
private _far = _lod isEqualTo 0;
private _close = _lod isEqualTo 2;
private _sel = (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["selectedMarker", ""];
private _fill = "#(rgb,8,8,3)color(1,1,1,1)";
private _fB = COMSPEC_ATAK_FontSet select 1;
private _fR = COMSPEC_ATAK_FontSet select 0;
// Tailles en pixels réels (fn_mapLbl) : nom du repère, seconde ligne.
private _fs = (_LF get "fs") get "m";
private _fs2 = (_LF get "fs") get "m2";
private _h = _fs * 1.12;
private _px = pixelW;
// Symboles des marqueurs à l'échelle de l'écran (fn_mapGameMarkers) : même facteur pour décoller le nom.
private _mkS = uiNamespace getVariable ["COMSPEC_ATAK_MkScale", 1];
private _bar = 3 * _px;
private _pad = 4 * _px;
private _padC = 6 * _px;
private _rad = _h / 2;
private _radX = _rad * _px / pixelH;
private _pillBg = [0.085, 0.10, 0.115, 1];
private _red = [0.91, 0.28, 0.23, 1];
private _redTxt = [1, 0.50, 0.44, 1];
private _tw = { params ["_t", "_f", "_s"]; ["w", [_t, _f, _s]] call comspec_atak_native_fnc_mapLbl };
private _plateBg = _pal get "lblBg";
// Fond des noms (Réglages > Carte, fn_mapPrefs « nameBg ») sous les styles sans plaque (CONTOUR, CLASSIQUE) ; les plaques
// gardent leur fond (opacité du volet CONFIGURATION).
private _nbg = _LF get "bg";
private _nbgLight = _LF get "light";
private _liftMap = _pal get "dark";
private _distCache = uiNamespace getVariable "COMSPEC_ATAK_LblCache";
if (isNil "_distCache" || {(count _distCache) > 2000}) then { _distCache = createHashMap; uiNamespace setVariable ["COMSPEC_ATAK_LblCache", _distCache]; };
private _slotsStd = [0, 1, 2, 3, 4, 5, 6, 7];
private _slots = switch (_style) do { case "TAC": { [2, 0, 1, 3, 5, 7, 4, 6] }; case "CARD": { [4, 6, 5, 7] }; default { _slotsStd } };

private _n = 0;
{
    if (_n >= 40) then { break };
    _x params ["_id", "_local", "_pos", "_txt", "_c", "_sz", "_lock", "_eni", ["_cL", []]];
    if (_id in _skip) then { continue };
    // Contact ENI : déjà dans son cartouche rouge (fn_mapLabels), ou masqué (volet CONFIGURATION).
    if (_eni && {_boxOn || {!_eniOn}}) then { continue };
    if (_cL isEqualTo []) then { _cL = _c; };
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
    // Demi-taille du symbole en pixels : l'étiquette s'en décolle.
    private _halfPx = ((_sz * _mkS * 0.5) max 4) min 40;
    private _force = _isLock || {_id isEqualTo _sel};
    // Vue moyenne : nom abrégé proprement (mots entiers) ; vue rapprochée : nom complet.
    if (!_close && {!_isLock}) then { _txt = ["cut", [_txt, 16]] call comspec_atak_native_fnc_mapLbl; };

    // 1. Textes, taille et primitives de l'étiquette (coordonnées depuis son coin haut gauche).
    private _t1 = ""; private _t2 = ""; private _f1 = _fB; private _w1 = 0; private _w2 = 0; private _bw = 0; private _bh = _h;
    private _tc = [[1, 1, 1, 1], _redTxt] select _isLock;
    private _pr = [];
    switch (_style) do {
        case "CLASSIC": {
            _f1 = _fR;
            _t1 = _txt;
            _w1 = [_t1, _f1, _fs] call _tw;
            private _pd = [0, _pad] select (_nbg isNotEqualTo []);
            _bw = _w1 + _pd * 2; _bh = _fs * 1.05;
            if (_nbg isNotEqualTo []) then { _pr pushBack ["R", 0, 0, _bw, _bh, _nbg, _fill]; };
            _pr pushBack ["T", _pd, _bh / 2, _t1, [_c, _cL] select _liftMap, _fs, _f1, 1, "right"];
        };
        case "TAC": {
            _f1 = "EtelkaMonospaceProBold";
            _t1 = toUpper ([_txt, _lock param [0, ""]] select _isLock);
            if (_isLock && {!_far}) then { _t2 = toUpper (_lock select 2); };
            _w1 = [_t1, _f1, _fs] call _tw;
            if (_t2 isNotEqualTo "") then { _w2 = [_t2, "EtelkaMonospacePro", _fs2] call _tw; };
            _bw = _pad + _w1 + _pad + ([0, _w2 + _pad] select (_t2 isNotEqualTo ""));
            _pr pushBack ["R", 0, 0, _bw, _bh, _pal get "tacPlate", _fill];
            if (_isLock) then { _pr pushBack ["R", 0, 0, _bw, _bh, [0.91, 0.28, 0.23, 0.75], ""]; };
            if (_id isEqualTo _sel) then { _pr pushBack ["R", 0, 0, _bw, _bh, _pal get "sel", ""]; };
            _pr pushBack ["T", _pad, _bh / 2, _t1, _tc, _fs, _f1, 0, "right"];
            if (_t2 isNotEqualTo "") then { _pr pushBack ["T", _pad * 2 + _w1, _bh / 2, _t2, _pal get "lblDim", _fs2, "EtelkaMonospacePro", 0, "right"]; };
        };
        case "OUTLINE": {
            _t1 = toUpper ([_txt, format ["%1 · %2", _lock param [0, ""], _lock param [1, ""]]] select _isLock);
            if (_isLock && {_far}) then { _t1 = toUpper (_lock select 0); };
            _w1 = [_t1, _f1, _fs] call _tw;
            private _pd = [0, _pad] select (_nbg isNotEqualTo []);
            _bw = _w1 + _pd * 2; _bh = _fs * 1.05;
            if (_nbg isNotEqualTo []) then { _pr pushBack ["R", 0, 0, _bw, _bh, _nbg, _fill]; };
            _pr pushBack ["T", _pd, _bh / 2, _t1, [_tc, [0.04, 0.05, 0.05, 1]] select (_nbgLight && {!_isLock}), _fs, _f1, [2, 0] select _nbgLight, "right"];
        };
        case "PILL": {
            _f1 = _fR;
            _t1 = [_txt, format ["%1 · %2", _lock param [0, ""], _lock param [2, ""]]] select _isLock;
            if (_isLock && {_far}) then { _t1 = _lock select 0; };
            _w1 = [_t1, _f1, _fs] call _tw;
            _bw = _radX * 3 + _w1;
            _pr pushBack ["R", _radX, 0, _bw - _radX, _bh, _pillBg, _fill];
            _pr pushBack ["E", _radX, _bh / 2, _rad, _pillBg];
            _pr pushBack ["E", _bw - _radX, _bh / 2, _rad, _pillBg];
            _pr pushBack ["E", _radX * 1.1, _bh / 2, _rad * 0.36, [_cL, _red] select _isLock];
            _pr pushBack ["T", _radX * 2.1, _bh / 2, _t1, _tc, _fs, _f1, 0, "right"];
        };
        case "CARD": {
            _t1 = toUpper ([_txt, _lock param [0, ""]] select _isLock);
            // Ligne distance · cap : mesurée chiffres remplacés par 0 (chasse fixe), une mesure par longueur.
            private _t2w = "";
            if (_isLock) then { _t2 = _lock select 1; _t2w = _t2; } else {
                if (_close) then {
                    private _dc = _distCache getOrDefault ["mk:" + _id, []];
                    if ((count _dc) < 2 || {_now > (_dc select 0)}) then {
                        _dc = [_now + 0.5, ["dist", player distance2D _pos, player getDir _pos] call comspec_atak_native_fnc_mapFmt];
                        _distCache set ["mk:" + _id, _dc];
                    };
                    _t2 = _dc select 1;
                    _t2w = _t2 regexReplace ["[0-9]", "0"];
                };
            };
            if (_far) then { _t2 = ""; };
            _w1 = [_t1, _f1, _fs] call _tw;
            if (_t2 isNotEqualTo "") then { _w2 = [_t2w, _fR, _fs2] call _tw; };
            _bw = (_w1 max _w2) + _padC * 2;
            _bh = _fs * 1.1 + ([0, _fs2 * 1.05] select (_t2 isNotEqualTo "")) + 5 * pixelH;
            _pr pushBack ["L", [[0.86, 0.89, 0.92, 0.7], _red] select _isLock];
            _pr pushBack ["R", 0, 0, _bw, _bh, _plateBg, _fill];
            _pr pushBack ["R", 0, 0, _bw, 2 * pixelH, [_cL, _red] select _isLock, _fill];
            if (_id isEqualTo _sel) then { _pr pushBack ["R", 0, 0, _bw, _bh, _pal get "sel", ""]; };
            private _ty = 3 * pixelH + _fs * 0.55;
            _pr pushBack ["T", _padC, _ty, _t1, [[1, 1, 1, 1], _redTxt] select _isLock, _fs, _f1, 0, "right"];
            if (_t2 isNotEqualTo "") then { _pr pushBack ["T", _padC, _ty + _fs * 0.55 + _fs2 * 0.55, _t2, _pal get "lblDim", _fs2, _fR, 0, "right"]; };
        };
        default {
            // PLATE : plaque sombre, liseré de couleur à gauche, capitales grasses ; LOCK bordé de rouge.
            _t1 = toUpper ([_txt, _lock param [0, ""]] select _isLock);
            if (_isLock && {!_far}) then { _t2 = toUpper (_lock select 1); };
            _w1 = [_t1, _f1, _fs] call _tw;
            if (_t2 isNotEqualTo "") then { _w2 = [_t2, _fR, _fs2] call _tw; };
            _bw = _bar + _pad + _w1 + _pad + ([0, _w2 + _pad * 1.6] select (_t2 isNotEqualTo ""));
            _pr pushBack ["R", 0, 0, _bw, _bh, _plateBg, _fill];
            _pr pushBack ["R", 0, 0, _bar, _bh, [_cL, _red] select _isLock, _fill];
            if (_isLock) then { _pr pushBack ["R", 0, 0, _bw, _bh, [0.91, 0.28, 0.23, 0.75], ""]; };
            if (_id isEqualTo _sel) then { _pr pushBack ["R", 0, 0, _bw, _bh, _pal get "sel", ""]; };
            _pr pushBack ["T", _bar + _pad, _bh / 2, _t1, _tc, _fs, _f1, 0, "right"];
            if (_t2 isNotEqualTo "") then {
                private _sx2 = _bar + _pad + _w1 + _pad * 0.8;
                _pr pushBack ["R", _sx2, _bh * 0.22, _sx2 + pixelW, _bh * 0.78, [1, 1, 1, 0.3], _fill];
                _pr pushBack ["T", _sx2 + _pad * 0.8, _bh / 2, _t2, _pal get "lblDim", _fs2, _fR, 0, "right"];
            };
        };
    };
    // 2. Demande : sélection 1, LOCK 2, autres repères 5 (après les unités et les contacts, avant les lieux).
    ["add", [[[5, 2] select _isLock, 1] select (_id isEqualTo _sel), [_pos select 0, _pos select 1], "", _halfPx, _halfPx, [_bw, _bh, _pr], _force, _slots]] call comspec_atak_native_fnc_mapLbl;
    _n = _n + 1;
} forEach (_cand select 1);
