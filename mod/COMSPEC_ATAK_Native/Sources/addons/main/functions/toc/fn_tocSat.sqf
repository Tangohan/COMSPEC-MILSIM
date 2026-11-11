/*
    Vue satellite du TOC (demande de TTRD, 11/11) : deux satellites par camp (SAT 1, SAT 2), vue 3D en direct prise de
    très haut, nord en haut, avec le suivi des alliés par-dessus (symbole, cadre autour de l'unité, indicatif), comme un
    flux ISR.
    Console ISR (retour de TTRD, « faut vraiment ce rendu TOC SAT ») : capteurs comme une vraie boule optronique, HUD du
    capteur incrusté dans l'image (coins, réticule, échelle, lignes de données), symboles amis, marqueurs, quadrillage et
    contours (fusion) dessinés par-dessus, en contrôles de la même texture (jamais drawIcon3D).
    Capteurs (setPiPEffect, wiki BI : 0 normal, 1 vision nocturne, 2 thermique blanc chaud, 3 correction de couleur,
    7 thermique de remplacement 1 « inversé » = noir chaud) :
      DAY jour [0] (la nuit : DAYN, jour éclairci par correction de couleur) · NV vision nocturne [1]
      WHOT thermique blanc chaud [2] · BHOT thermique noir chaud [7]
      FUSW / FUSB fusion : même thermique [2] / [7] + contours des bâtiments en surimpression (un rendu d'image dans l'image
      n'accepte qu'un effet à la fois : le contraste des bords est ajouté en contrôles, pas en post-traitement)
      AUTO : jour le jour, WHOT la nuit (plus jamais d'image noire).
    Zoom : 1× = 500 m de large au sol, de 0,25× (2 km) à 8× (62,5 m). Hauteur et fov tirés de la demi-largeur (« altFov ») ;
    largeur au sol = 2 · hauteur · tan(fov / 2) (« scale »), barre d'échelle (« bar ») ; vérifié par « selfTest ».
    Seul l'état circule (fn_tocRoom, satellites : centre, demi-largeur au sol, capteur, suivi, opérateur, options).
    Chaque joueur crée lui-même la caméra et la cible de rendu d'un satellite, une par satellite, partagée par tous ses
    écrans, seulement s'il en a besoin : PC du poste ouvert sur l'onglet SAT, ou écran en affichage SAT qu'il regarde à
    moins de comspec_atak_native_toc_cam_range mètres (même limite que les caméras d'écran, comspec_atak_native_toc_cam_max).
    Résolution de la cible : H 2048 x 1024 (PC du poste), M 1024 x 512 (écran à 8 m au plus), L 512 x 256 (plus loin).
    Suivi réaliste : seuls les nœuds du réseau ATAK du camp (fn_tocFeed) ; en direct à leur position, ancien / perdu / hors
    ligne à leur dernier point PLI, en gris. Jamais d'unité sans ATAK, jamais d'ennemi : l'image thermique ne montre que
    ce que le rendu thermique du jeu montre.
    Modes (client) :
      ["need", [camp, n°, s, PC]]          cette vue doit être rendue pendant s secondes (3 par défaut ; 30 quand l'opérateur
                                           quitte l'onglet SAT) ; PC : console du poste (résolution H)
      ["tick", écrans]                     chaque seconde (fn_tocScreen) : caméras créées, libérées, résolution ; clés rendues
      ["rt", [camp, n°]] / ["tex", [camp, n°]]   cible de rendu / texture de l'image ("" : pas rendue sur ce poste)
      ["pose", [camp, n°]]                 [position ASL de la caméra, fov] ou []
      ["project", [camp, n°, posASL, rapport]]   [u, v, mètres par part de largeur] (0-1 dans l'image) ou []
      ["unproject", [camp, n°, u, v, rapport]]   [x, y] au sol
      ["targets", camp]                    nœuds suivis : [[clé, indicatif, fraîcheur, genre, position ASL, taille m, groupe]...]
      ["aim", [camp, n°]]                  point visé [x, y] (suivi compris) et demi-largeur, ou []
      ["label", [camp, n°]]                ligne d'état « SAT 2 · thermique blanc chaud · contrôlé par … »
      ["effect", capteur]                  capteur réellement rendu : "DAY" | "DAYN" | "NV" | "WHOT" | "BHOT" | "FUSW" | "FUSB"
      ["pip", capteur] / ["sensors"] / ["modes"]   effet de l'image, table des capteurs, capteurs au choix
      ["zoomOf", demi-largeur] / ["halfOf", zoom] / ["altFov", demi-largeur] / ["fovDeg", fov] / ["scale", [hauteur, fov°]]
      ["bar", largeur m] -> [longueur m, part de la largeur]       ["selfTest"] -> défauts (vide : tout va bien)
      ["marks", [camp, n°]] / ["edges", [camp, n°]]   marqueurs et tâches connus dans la vue / contours des bâtiments
      ["hudMake", [display, groupe, "PC" | "WALL"]] -> HUD    ["hudPaint", [HUD, camp, n°, image, zone visible, calques]]
      ["hudHide", HUD]
*/
params [["_mode", "tick"], ["_a", []]];
private _fn = comspec_atak_native_fnc_tocSat;
private _aspect = 16 / 9;
private _keyOf = { params ["_s", "_n"]; format ["%1|%2", _s, _n] };
private _cams = missionNamespace getVariable ["COMSPEC_ATAK_TocSatCams", createHashMap];
missionNamespace setVariable ["COMSPEC_ATAK_TocSatCams", _cams];
// Zoom affiché <-> demi-largeur au sol : 1× = 500 m de large.
private _zRef = 250;
private _zMin = 0.25;
private _zMax = 8;
// Capteurs : [code, nom, effet de l'image].
private _sensors = [
    ["DAY", "jour", [0]],
    ["DAYN", "jour éclairci (nuit)", [3, 1, 3.5, 1, 0.04, [0, 0, 0, 0], [1, 1, 1, 1], [0.299, 0.587, 0.114, 0]]],
    ["NV", "vision nocturne", [1]],
    ["WHOT", "thermique blanc chaud", [2]],
    ["BHOT", "thermique noir chaud", [7]],
    ["FUSW", "fusion blanc chaud", [2]],
    ["FUSB", "fusion noir chaud", [7]]
];
// Résolution de la cible de rendu (puissances de 2, image 16:9 par le rapport du r2t).
private _res = createHashMapFromArray [["H", [2048, 1024]], ["M", [1024, 512]], ["L", [512, 256]]];
private _rtOf = { params ["_s", "_n", "_q"]; format ["comspec_tocsat_%1_%2_%3", toLower _s, _n, toLower _q] };
switch (_mode) do {
    case "sensors": { _sensors };
    case "modes": { ["AUTO", "DAY", "NV", "WHOT", "BHOT", "FUSW", "FUSB"] };
    case "pip": { ((_sensors select { (_x select 0) isEqualTo _a }) param [0, ["", "", [0]]]) select 2 };
    case "name": { ((_sensors select { (_x select 0) isEqualTo _a }) param [0, ["", "jour"]]) select 1 };
    case "zoomOf": { ((_zRef / (_a max 1)) max _zMin) min _zMax };
    case "halfOf": { _zRef / ((_a max _zMin) min _zMax) };
    case "altFov": {
        // Assez haut pour que la vue paraisse prise d'orbite, assez bas pour que les objets restent dessinés.
        private _alt = ((_a / 0.75) max 180) min 1400;
        [_alt, (_a / _alt) max 0.05]
    };
    // fov d'Arma (tangente de la demi-ouverture) -> ouverture en degrés ; largeur au sol pour une ouverture en degrés.
    case "fovDeg": { 2 * (atan _a) };
    case "scale": { _a params ["_alt", "_deg"]; 2 * _alt * (tan (_deg / 2)) };
    case "bar": {
        // Barre d'échelle : longueur ronde la plus grande sous 22 % de la largeur de l'image.
        private _wd = _a max 1;
        private _l = 5;
        { if (_x <= _wd * 0.22) then { _l = _x; }; } forEach [5, 10, 20, 25, 50, 100, 200, 250, 500, 1000, 2000];
        [_l, _l / _wd]
    };
    case "selfTest": {
        private _bad = [];
        private _chk = { params ["_lbl", "_got", "_want"]; if (abs (_got - _want) > 0.01) then { _bad pushBack format ["%1 : %2 au lieu de %3", _lbl, _got, _want]; }; };
        ["zoom 1x", ["zoomOf", 250] call _fn, 1] call _chk;
        ["zoom plancher", ["zoomOf", 5000] call _fn, 0.25] call _chk;
        ["zoom plafond", ["zoomOf", 1] call _fn, 8] call _chk;
        ["demi-largeur 0,5x", ["halfOf", 0.5] call _fn, 500] call _chk;
        ["demi-largeur 20x", ["halfOf", 20] call _fn, 31.25] call _chk;
        ["largeur 400 m fov 90", ["scale", [400, 90]] call _fn, 800] call _chk;
        ["largeur 500 m fov 0,75", ["scale", [500, ["fovDeg", 0.75] call _fn]] call _fn, 750] call _chk;
        ["barre 1000 m", (["bar", 1000] call _fn) select 0, 200] call _chk;
        ["barre 300 m", (["bar", 300] call _fn) select 0, 50] call _chk;
        ["hauteur 0,5x", (["altFov", 500] call _fn) select 0, 666.667] call _chk;
        ["largeur au sol 0,5x", 2 * ((["altFov", 500] call _fn) select 0) * ((["altFov", 500] call _fn) select 1), 1000] call _chk;
        { if ((["pip", _x select 0] call _fn) isNotEqualTo (_x select 1)) then { _bad pushBack format ["capteur %1", _x select 0]; }; }
            forEach [["DAY", [0]], ["NV", [1]], ["WHOT", [2]], ["BHOT", [7]], ["FUSW", [2]], ["FUSB", [7]]];
        _bad
    };
    case "targets": {
        private _feed = missionNamespace getVariable [format ["COMSPEC_ATAK_TocFeed_%1", _a], []];
        (_feed param [1, []]) apply {
            _x params ["_k", "_cs", "_p", "", "_fr", "_kind", "", ["_g", ""], ["_n", 1]];
            private _o = objectFromNetId _k;
            private _v = vehicle _o;
            private _pos = [];
            // En direct : position vue (déplacement fluide) ; sinon le dernier point PLI connu du réseau, au sol.
            if (_fr isEqualTo 0 && {!isNull _o} && {alive _o}) then { _pos = getPosASLVisual _v; } else {
                _pos = [_p select 0, _p select 1, getTerrainHeightASL [_p select 0, _p select 1]];
            };
            private _size = if (_kind isEqualTo "inf" || {isNull _o}) then { [2.5, 8] select (_kind isNotEqualTo "inf") } else { ((sizeOf typeOf _v) * 0.7) max 4 };
            [_k, _cs + (["", format [" +%1", _n - 1]] select (_n > 1)), _fr, _kind, _pos, _size, _g]
        }
    };
    case "aim": {
        _a params ["_side", "_n"];
        private _s = ((["state", _side] call comspec_atak_native_fnc_tocRoom) select 2) param [_n, []];
        if ((count _s) < 8) exitWith { [] };
        _s params ["_x0", "_y0", "_h", "", "_fk", "_fid"];
        private _p = [_x0, _y0];
        if (_fk isNotEqualTo "") then {
            private _t = (["targets", _side] call _fn) select { [(_x select 0) isEqualTo _fid, (_x select 6) isEqualTo _fid] select (_fk isEqualTo "G") };
            if ((count _t) > 0) then {
                private _sx = 0; private _sy = 0;
                { _sx = _sx + ((_x select 4) select 0); _sy = _sy + ((_x select 4) select 1); } forEach _t;
                _p = [_sx / (count _t), _sy / (count _t)];
            };
        };
        if ((_p select 0) < 0) then {
            // Jamais pris en main : au-dessus du poste.
            private _o = (missionNamespace getVariable ["COMSPEC_ATAK_TocScreens", []]) param [0, objNull];
            private _q = getPosASL ([_o, player] select (isNull _o));
            _p = [_q select 0, _q select 1];
        };
        [_p, _h]
    };
    case "effect": {
        // Capteur choisi -> image rendue. La nuit, une caméra d'image dans l'image en rendu « jour » est presque noire
        // (retour de TTRD, 11/11, mission à 01:26) : AUTO passe en thermique blanc chaud, JOUR est éclairci.
        // « TI » (4.4.x, téléphone) : thermique blanc chaud.
        private _night = sunOrMoon < 0.5;
        switch ([_a, "WHOT"] select (_a isEqualTo "TI")) do {
            case "NV": { "NV" };
            case "WHOT": { "WHOT" };
            case "BHOT": { "BHOT" };
            case "FUSW": { "FUSW" };
            case "FUSB": { "FUSB" };
            case "DAY": { ["DAY", "DAYN"] select _night };
            default { ["DAY", "WHOT"] select _night };
        }
    };
    case "label": {
        _a params ["_side", "_n"];
        private _s = ((["state", _side] call comspec_atak_native_fnc_tocRoom) select 2) param [_n, []];
        private _m = _s param [3, "AUTO"];
        private _img = format ["%1%2", ["", "auto · "] select (_m isEqualTo "AUTO"), ["name", ["effect", _m] call _fn] call _fn];
        private _fol = switch (_s param [4, ""]) do {
            case "G": { format [" · suit le groupe %1", _s param [5, ""]] };
            case "U": {
                private _t = (["targets", _side] call _fn) select { (_x select 0) isEqualTo (_s param [5, ""]) };
                format [" · suit %1", [(_t select 0) param [1, "?"], "une unité hors réseau"] select ((count _t) isEqualTo 0)]
            };
            default { "" };
        };
        private _who = _s param [6, ""];
        [format ["SAT %1 · %2%3 · libre", _n + 1, _img, _fol], format ["SAT %1 · %2%3 · contrôlé par %4", _n + 1, _img, _fol, _who]] select (_who isNotEqualTo "")
    };
    case "need": {
        _a params ["_side", "_n", ["_secs", 3], ["_pc", false]];
        private _k = [_side, _n] call _keyOf;
        private _e = _cams getOrDefault [_k, []];
        if ((count _e) isEqualTo 0) then {
            _e = [objNull, "", 0, "", "", 0, 0, 0];
            _cams set [_k, _e];
        };
        // Bail du rendu : jamais raccourci (l'onglet SAT quitté garde 30 s, le rafraîchissement suivant n'en remet pas 3).
        _e set [2, (_e select 2) max (diag_tickTime + _secs)];
        if (_pc) then { _e set [7, (_e select 7) max (diag_tickTime + _secs)]; };
        if (isNull (_e select 0)) then { ["make", [_side, _n, ["M", "H"] select _pc]] call _fn; } else {
            if (_pc && {(_e select 4) isNotEqualTo "H"}) then { ["retarget", [_k, "H"]] call _fn; };
        };
        true
    };
    case "make": {
        _a params ["_side", "_n", ["_q", "M"]];
        private _k = [_side, _n] call _keyOf;
        private _e = _cams getOrDefault [_k, [objNull, "", 0, "", "", 0, 0, 0]];
        if (!isNull (_e select 0)) exitWith { true };
        ["check", "SATELLITE"] call comspec_atak_native_fnc_pipCheck;
        private _rt = [_side, _n, _q] call _rtOf;
        private _aim = ["aim", [_side, _n]] call _fn;
        if ((count _aim) < 2) exitWith { false };
        (_aim select 0) params ["_x0", "_y0"];
        (["altFov", _aim select 1] call _fn) params ["_alt", "_fov"];
        private _gz = getTerrainHeightASL [_x0, _y0];
        private _cam = "camera" camCreate [_x0, _y0, _alt];
        _cam setPosASL [_x0, _y0, _gz + _alt];
        _cam setVectorDirAndUp [[0, 0, -1], [0, 1, 0]];
        _cam camSetFov _fov;
        _cam camCommit 0;
        _cam setVariable ["COMSPEC_ATAK_Fov", _fov];
        _cam cameraEffect ["Internal", "Back", _rt];
        // [caméra, cible, bail, capteur rendu, résolution, sol de référence (relief non suivi), fov courant, bail du PC]
        _e = [_cam, _rt, _e select 2, "", _q, _gz, _fov, _e param [7, 0]];
        _cams set [_k, _e];
        ["INFO", "TOC", format ["Satellite %1 : rendu sur ce poste (%2)", _k, _rt]] call comspec_atak_native_fnc_log;
        if (isNil { missionNamespace getVariable "COMSPEC_ATAK_TocSatPfh" }) then {
            missionNamespace setVariable ["COMSPEC_ATAK_TocSatPfh", [{
                private _cs = missionNamespace getVariable ["COMSPEC_ATAK_TocSatCams", createHashMap];
                if (({ !isNull (_x select 0) } count (values _cs)) isEqualTo 0) exitWith {
                    [missionNamespace getVariable "COMSPEC_ATAK_TocSatPfh"] call CBA_fnc_removePerFrameHandler;
                    missionNamespace setVariable ["COMSPEC_ATAK_TocSatPfh", nil];
                };
                {
                    _y params ["_cam", "_rt", "", "_img", "", "_base", "_fc"];
                    if (isNull _cam) then { continue };
                    private _p = _x splitString "|";
                    private _side = _p select 0;
                    private _n = parseNumber (_p select 1);
                    private _st = ((["state", _side] call comspec_atak_native_fnc_tocRoom) select 2) param [_n, []];
                    // Capteur rendu (AUTO et JOUR suivent la nuit) ; effet changé seulement quand il change.
                    private _m = ["effect", _st param [3, "AUTO"]] call comspec_atak_native_fnc_tocSat;
                    if (_m isNotEqualTo _img) then {
                        _y set [3, _m];
                        _rt setPiPEffect (["pip", _m] call comspec_atak_native_fnc_tocSat);
                    };
                    private _aim = ["aim", [_side, _n]] call comspec_atak_native_fnc_tocSat;
                    if ((count _aim) < 2) then { continue };
                    (_aim select 0) params ["_tx", "_ty"];
                    (["altFov", _aim select 1] call comspec_atak_native_fnc_tocSat) params ["_alt", "_fov"];
                    (_st param [8, [false, true]]) params [["_jit", false], ["_terr", true]];
                    private _gz = getTerrainHeightASL [_tx, _ty];
                    // Relief non suivi : altitude constante au-dessus du sol de référence, jamais sous le relief.
                    if (!_terr) then { _gz = _base max (_gz - _alt + 60); };
                    // Dérive lente de la plateforme (option, éteinte par défaut) : quelques mètres, période de 10 s environ.
                    if (_jit) then {
                        private _t = diag_tickTime;
                        _tx = _tx + (_aim select 1) * 0.006 * ((sin (_t * 37)) + 0.4 * (sin (_t * 211)));
                        _ty = _ty + (_aim select 1) * 0.006 * ((cos (_t * 29)) + 0.4 * (sin (_t * 173)));
                    };
                    private _cur = getPosASL _cam;
                    // Glissement doux vers le point visé et le zoom demandé (suivi au rythme du PLI, panoramique, molette).
                    private _k = (diag_deltaTime * 2.5) min 1;
                    private _nf = _fc + (_fov - _fc) * _k;
                    _y set [6, _nf];
                    _cam setPosASL [(_cur select 0) + (_tx - (_cur select 0)) * _k, (_cur select 1) + (_ty - (_cur select 1)) * _k, (_cur select 2) + ((_gz + _alt) - (_cur select 2)) * _k];
                    _cam setVectorDirAndUp [[0, 0, -1], [0, 1, 0]];
                    _cam camSetFov _nf;
                    _cam camCommit 0;
                    _cam setVariable ["COMSPEC_ATAK_Fov", _nf];
                } forEach _cs;
            }, 0] call CBA_fnc_addPerFrameHandler];
        };
        true
    };
    case "retarget": {
        // Résolution changée (PC ouvert, écran plus près ou plus loin) : même caméra, nouvelle cible de rendu.
        _a params ["_k", "_q"];
        private _e = _cams getOrDefault [_k, []];
        if ((count _e) < 8 || {isNull (_e select 0)} || {(_e select 4) isEqualTo _q}) exitWith { false };
        private _p = _k splitString "|";
        private _rt = [_p select 0, parseNumber (_p select 1), _q] call _rtOf;
        (_e select 0) cameraEffect ["Terminate", "Back", _e select 1];
        (_e select 0) cameraEffect ["Internal", "Back", _rt];
        _e set [1, _rt];
        _e set [3, ""];
        _e set [4, _q];
        true
    };
    case "free": {
        private _k = _a call _keyOf;
        private _e = _cams getOrDefault [_k, []];
        if ((count _e) < 2 || {isNull (_e select 0)}) exitWith { _cams deleteAt _k; false };
        (_e select 0) cameraEffect ["Terminate", "Back", _e select 1];
        camDestroy (_e select 0);
        _cams deleteAt _k;
        ["INFO", "TOC", format ["Satellite %1 : rendu arrêté sur ce poste", _k]] call comspec_atak_native_fnc_log;
        true
    };
    case "rt": {
        private _e = _cams getOrDefault [_a call _keyOf, []];
        if ((count _e) < 2 || {isNull (_e select 0)}) exitWith { "" };
        _e select 1
    };
    case "tex": {
        private _e = _cams getOrDefault [_a call _keyOf, []];
        if ((count _e) < 5 || {isNull (_e select 0)}) exitWith { "" };
        (_res getOrDefault [_e select 4, [1024, 512]]) params ["_tw", "_th"];
        format ["#(argb,%1,%2,1)r2t(%3,%4)", _tw, _th, _e select 1, _aspect toFixed 4]
    };
    case "pose": {
        private _e = _cams getOrDefault [_a call _keyOf, []];
        if ((count _e) < 2 || {isNull (_e select 0)}) exitWith { [] };
        [getPosASLVisual (_e select 0), (_e select 0) getVariable ["COMSPEC_ATAK_Fov", 0.75]]
    };
    case "project": {
        _a params ["_side", "_n", "_p", ["_r", _aspect]];
        private _pose = ["pose", [_side, _n]] call _fn;
        if ((count _pose) < 2) exitWith { [] };
        _pose params ["_c", "_fov"];
        private _dz = ((_c select 2) - (_p select 2)) max 1;
        private _hw = _dz * _fov;               // demi-largeur au sol, à la hauteur du point
        private _hh = _hw / _r;
        [0.5 + ((_p select 0) - (_c select 0)) / (2 * _hw), 0.5 - ((_p select 1) - (_c select 1)) / (2 * _hh), 2 * _hw]
    };
    case "unproject": {
        _a params ["_side", "_n", "_u", "_v", ["_r", _aspect]];
        private _pose = ["pose", [_side, _n]] call _fn;
        if ((count _pose) < 2) exitWith { [] };
        _pose params ["_c", "_fov"];
        private _hw = ((_c select 2) - (getTerrainHeightASL [_c select 0, _c select 1])) * _fov;
        [(_c select 0) + (_u - 0.5) * 2 * _hw, (_c select 1) - (_v - 0.5) * 2 * _hw / _r]
    };
    case "marks": {
        // Marqueurs de la carte que ce joueur voit (canal compris) et tâches de sa mission, dans la vue : [[pos ASL, icône,
        // couleur, texte]...]. Gardés 1 s par satellite.
        _a params ["_side", "_n"];
        private _cache = uiNamespace getVariable ["COMSPEC_ATAK_TocSatMk", createHashMap];
        uiNamespace setVariable ["COMSPEC_ATAK_TocSatMk", _cache];
        private _k = [_side, _n] call _keyOf;
        private _hit = _cache getOrDefault [_k, [-1e9, []]];
        if (diag_tickTime - (_hit select 0) < 1) exitWith { _hit select 1 };
        private _pose = ["pose", [_side, _n]] call _fn;
        if ((count _pose) < 2) exitWith { [] };
        _pose params ["_c", "_fov"];
        private _hw = (((_c select 2) - (getTerrainHeightASL [_c select 0, _c select 1])) * _fov) * 1.1;
        private _hh = _hw / _aspect;
        private _rgbOf = {
            params ["_arr"];
            private _v = _arr apply { if (_x isEqualType "") then { call compile _x } else { _x } };
            [[1, 1, 1, 1], _v] select ((count _v) >= 4)
        };
        private _out = [];
        {
            if ((count _out) >= 30) exitWith {};
            private _mp = getMarkerPos _x;
            if ((markerShape _x) isNotEqualTo "ICON" || {(markerAlpha _x) <= 0} || {(markerType _x) in ["", "Empty"]}
                || {abs ((_mp select 0) - (_c select 0)) > _hw} || {abs ((_mp select 1) - (_c select 1)) > _hh}) then { continue };
            private _ty = configFile >> "CfgMarkers" >> markerType _x;
            private _col = markerColor _x;
            private _rgba = [[getArray (configFile >> "CfgMarkerColors" >> _col >> "color")] call _rgbOf, [getArray (_ty >> "color")] call _rgbOf] select (_col in ["", "Default"]);
            _out pushBack [[_mp select 0, _mp select 1, getTerrainHeightASL _mp], getText (_ty >> "icon"), _rgba, markerText _x];
        } forEach allMapMarkers;
        {
            private _tp = taskDestination _x;
            if ((count _out) >= 36 || {(taskState _x) in ["Succeeded", "Failed", "Canceled"]} || {(_tp select 0) isEqualTo 0 && {(_tp select 1) isEqualTo 0}}
                || {abs ((_tp select 0) - (_c select 0)) > _hw} || {abs ((_tp select 1) - (_c select 1)) > _hh}) then { continue };
            _out pushBack [[_tp select 0, _tp select 1, getTerrainHeightASL _tp], getText (configFile >> "CfgMarkers" >> "mil_objective" >> "icon"), [0.95, 0.3, 0.22, 1], (taskDescription _x) param [1, ""]];
        } forEach (simpleTasks player);
        _cache set [_k, [diag_tickTime, _out]];
        _out
    };
    case "edges": {
        // Fusion : contours des bâtiments vus (toits), 40 au plus, recalculés toutes les 1,5 s ou quand la vue bouge.
        // [[coin 1, coin 2, coin 3, coin 4] (ASL)...] ; rien au-delà de 760 m de large (traits illisibles).
        _a params ["_side", "_n"];
        private _pose = ["pose", [_side, _n]] call _fn;
        if ((count _pose) < 2) exitWith { [] };
        _pose params ["_c", "_fov"];
        private _hw = ((_c select 2) - (getTerrainHeightASL [_c select 0, _c select 1])) * _fov;
        if (_hw > 380) exitWith { [] };
        private _cache = uiNamespace getVariable ["COMSPEC_ATAK_TocSatEdge", createHashMap];
        uiNamespace setVariable ["COMSPEC_ATAK_TocSatEdge", _cache];
        private _k = [_side, _n] call _keyOf;
        _cache getOrDefault [_k, [-1e9, [0, 0], 0, []]] params ["_t0", "_c0", "_h0", "_l0"];
        if (diag_tickTime - _t0 < 1.5 && {(_c distance2D _c0) < _hw * 0.2} && {abs (_hw - _h0) < _hw * 0.15}) exitWith { _l0 };
        private _out = [];
        {
            if ((count _out) >= 40) exitWith {};
            (boundingBoxReal _x) params ["_p1", "_p2"];
            if (abs ((_p2 select 0) - (_p1 select 0)) < 3 || {abs ((_p2 select 1) - (_p1 select 1)) < 3}) then { continue };
            private _b = _x;
            private _z = (_p2 select 2) * 0.8;
            _out pushBack ([[_p1 select 0, _p1 select 1], [_p2 select 0, _p1 select 1], [_p2 select 0, _p2 select 1], [_p1 select 0, _p2 select 1]] apply { _b modelToWorldWorld [_x select 0, _x select 1, _z] });
        } forEach (nearestObjects [[_c select 0, _c select 1], ["House"], _hw * 1.15, true]);
        _cache set [_k, [diag_tickTime, [_c select 0, _c select 1], _hw, _out]];
        _out
    };
    case "hudMake": {
        // HUD du capteur : contrôles créés une fois, posés à chaque passage (« hudPaint »). PC : coordonnées d'écran ;
        // WALL : texture 1600 x 900 de l'écran (texte lisible à 3-6 m).
        _a params ["_d", ["_grp", controlNull], ["_prof", "PC"]];
        disableSerialization;
        private _wall = _prof isEqualTo "WALL";
        private _pf = if (_wall) then {
            createHashMapFromArray [["ux", 1 / 1600], ["uy", 1 / 900], ["fH", 34 / 900], ["fL", 28 / 900], ["sym", 40], ["th", 3], ["m", 18], ["nSym", 24], ["nMk", 16], ["nEdge", 24]]
        } else {
            createHashMapFromArray [["ux", pixelW], ["uy", pixelH], ["fH", 0.0175 * safeZoneH], ["fL", 0.0155 * safeZoneH], ["sym", 20], ["th", 2], ["m", 12], ["nSym", 40], ["nMk", 30], ["nEdge", 40]]
        };
        private _mkc = {
            params ["_cls"];
            private _c = if (isNull _grp) then { _d ctrlCreate [_cls, -1] } else { _d ctrlCreate [_cls, -1, _grp] };
            _c ctrlShow false;
            _c
        };
        private _ln = { private _c = ["RscText"] call _mkc; _c ctrlSetText ""; _c };
        private _tx = {
            params [["_f", _pf get "fH"]];
            private _c = ["RscText"] call _mkc;
            _c ctrlSetFont "EtelkaMonospacePro";
            _c ctrlSetFontHeight _f;
            _c setVariable ["COMSPEC_ATAK_F", _f];
            _c ctrlSetBackgroundColor [0, 0, 0, 0.5];
            _c ctrlSetTextColor [0.93, 0.95, 0.93, 1];
            _c
        };
        private _seg = { private _c = ["RscPicture"] call _mkc; _c ctrlSetText "#(argb,8,8,3)color(1,1,1,1)"; _c };
        private _hud = createHashMapFromArray [["prof", _pf], ["wall", _wall], ["all", []]];
        private _all = _hud get "all";
        private _put = { params ["_k", "_v"]; _hud set [_k, _v]; if (_v isEqualType controlNull) then { _all pushBack _v; } else { { if (_x isEqualType []) then { _all append _x; } else { _all pushBack _x; }; } forEach _v; }; };
        ["brk", [1, 2, 3, 4, 5, 6, 7, 8] apply { call _ln }] call _put;
        ["cross", [1, 2, 3, 4] apply { call _ln }] call _put;
        ["bar", [1, 2, 3] apply { call _ln }] call _put;
        ["barT", [_pf get "fL"] call _tx] call _put;
        { [_x, [] call _tx] call _put; } forEach ["tL1", "tL2", "tR1", "tR2", "bL", "bR"];
        // Quadrillage (traits verticaux puis horizontaux), contours de la fusion (4 traits par bâtiment).
        private _gl = []; for "_i" from 1 to 36 do { _gl pushBack (call _ln); };
        _hud set ["grid", _gl]; _all append _gl;
        private _el = []; for "_i" from 1 to ((_pf get "nEdge") * 4) do { _el pushBack (call _seg); };
        _hud set ["edge", _el]; _all append _el;
        // Marqueurs et tâches : icône, nom.
        private _ml = [];
        for "_i" from 1 to (_pf get "nMk") do { _ml pushBack [["RscPicture"] call _mkc, [_pf get "fL"] call _tx]; };
        _hud set ["mk", _ml]; { _all append _x; } forEach _ml;
        // Pistes amies : cadre (4 traits), symbole, indicatif, ligne d'information.
        private _sl = [];
        for "_i" from 1 to (_pf get "nSym") do {
            _sl pushBack [call _ln, call _ln, call _ln, call _ln, ["RscPicture"] call _mkc, [_pf get "fL"] call _tx, [(_pf get "fL") * 0.85] call _tx];
        };
        _hud set ["sym", _sl]; { _all append _x; } forEach _sl;
        _hud
    };
    case "hudHide": {
        if !(_a isEqualType createHashMap) exitWith {};
        { _x ctrlShow false; } forEach (_a getOrDefault ["all", []]);
    };
    case "hudPaint": {
        // HUD posé sur l'image : image entière (16:9, peut déborder de la zone visible) et zone visible (rognée).
        // Calques : MRKR marqueurs et tâches, BFT pistes amies, GRID quadrillage, DATA lignes de données, BOX cadres.
        _a params ["_hud", "_side", "_n", "_ir", "_vr", ["_ov", createHashMap]];
        disableSerialization;
        if !(_hud isEqualType createHashMap) exitWith { false };
        private _pf = _hud get "prof";
        private _ux = _pf get "ux"; private _uy = _pf get "uy";
        private _th = _pf get "th"; private _mg = _pf get "m";
        _ir params ["_ix", "_iy", "_iw", "_ih"];
        _vr params ["_vx", "_vy", "_vw", "_vh"];
        private _pose = ["pose", [_side, _n]] call _fn;
        if ((count _pose) < 2) exitWith { ["hudHide", _hud] call _fn; false };
        _pose params ["_cc", "_fov"];
        private _st = ((["state", _side] call comspec_atak_native_fnc_tocRoom) select 2) param [_n, []];
        private _mode0 = _st param [3, "AUTO"];
        private _sens = ["effect", _mode0] call _fn;
        // Encre des traits : sombre sur image claire (blanc chaud), claire sur image sombre.
        private _ink = [[0.95, 0.96, 0.94, 0.9], [0.05, 0.06, 0.06, 0.88]] select (_sens in ["WHOT", "FUSW"]);
        private _sideRgb = (createHashMapFromArray [["WEST", [0.2, 0.55, 1]], ["EAST", [1, 0.32, 0.26]], ["GUER", [0.3, 0.88, 0.4]], ["CIV", [0.78, 0.5, 1]]]) getOrDefault [_side, [0.2, 0.55, 1]];
        private _gz0 = getTerrainHeightASL [_cc select 0, _cc select 1];
        private _alt = (_cc select 2) - _gz0;
        private _hwG = (_alt max 1) * _fov;
        private _wG = ["scale", [_alt, ["fovDeg", _fov] call _fn]] call _fn;
        private _prj = {
            params ["_p"];
            private _hwp = (((_cc select 2) - (_p select 2)) max 1) * _fov;
            [_ix + _iw * (0.5 + ((_p select 0) - (_cc select 0)) / (2 * _hwp)), _iy + _ih * (0.5 - ((_p select 1) - (_cc select 1)) * _aspect / (2 * _hwp))]
        };
        private _inV = { params ["_px", "_py"]; _px >= _vx && {_px <= _vx + _vw} && {_py >= _vy} && {_py <= _vy + _vh} };
        private _box = { params ["_c", "_r", ["_col", []]]; _c ctrlSetPosition _r; _c ctrlCommit 0; if ((count _col) > 0) then { _c ctrlSetBackgroundColor _col; }; _c ctrlShow true; };
        // Texte d'une ligne : traduit et mesuré seulement quand il change (police à chasse fixe, 0,62 em par signe).
        private _txt = {
            params ["_c", "_s", "_px", "_py", ["_right", false]];
            if ((_c getVariable ["COMSPEC_ATAK_T", "-"]) isNotEqualTo _s) then {
                _c setVariable ["COMSPEC_ATAK_T", _s];
                private _t = _s call comspec_atak_native_fnc_tr;
                _c ctrlSetText _t; // i18n:brut (déjà traduit)
                forceUnicode 0;
                _c setVariable ["COMSPEC_ATAK_W", (count _t) * 0.62 * (_c getVariable ["COMSPEC_ATAK_F", 0.02]) * _ux / _uy + 12 * _ux];
            };
            private _w = _c getVariable ["COMSPEC_ATAK_W", 0.1];
            private _hgt = (_c getVariable ["COMSPEC_ATAK_F", 0.02]) * 1.3;
            _c ctrlSetPosition [[_px, _px - _w] select _right, _py, _w, _hgt];
            _c ctrlCommit 0;
            _c ctrlShow (_s isNotEqualTo "");
            _hgt
        };
        private _seg = {
            params ["_c", "_x1", "_y1", "_x2", "_y2", "_col"];
            private _dxp = (_x2 - _x1) / _ux; private _dyp = (_y2 - _y1) / _uy;
            private _len = sqrt (_dxp * _dxp + _dyp * _dyp);
            if (_len < 2) exitWith { _c ctrlShow false; };
            _c ctrlSetPosition [(_x1 + _x2) / 2 - _len * _ux / 2, (_y1 + _y2) / 2 - _uy, _len * _ux, 2 * _uy];
            _c ctrlCommit 0;
            _c ctrlSetAngle [_dyp atan2 _dxp, 0.5, 0.5];
            _c ctrlSetTextColor _col;
            _c ctrlShow true;
        };
        // Coins du cadre et réticule.
        private _bl = 46;
        (_hud get "brk") params ["_b1", "_b2", "_b3", "_b4", "_b5", "_b6", "_b7", "_b8"];
        private _x0 = _vx + _mg * _ux; private _y0 = _vy + _mg * _uy; private _x1 = _vx + _vw - _mg * _ux; private _y1 = _vy + _vh - _mg * _uy;
        [_b1, [_x0, _y0, _bl * _ux, _th * _uy], _ink] call _box; [_b2, [_x0, _y0, _th * _ux, _bl * _uy], _ink] call _box;
        [_b3, [_x1 - _bl * _ux, _y0, _bl * _ux, _th * _uy], _ink] call _box; [_b4, [_x1 - _th * _ux, _y0, _th * _ux, _bl * _uy], _ink] call _box;
        [_b5, [_x0, _y1 - _th * _uy, _bl * _ux, _th * _uy], _ink] call _box; [_b6, [_x0, _y1 - _bl * _uy, _th * _ux, _bl * _uy], _ink] call _box;
        [_b7, [_x1 - _bl * _ux, _y1 - _th * _uy, _bl * _ux, _th * _uy], _ink] call _box; [_b8, [_x1 - _th * _ux, _y1 - _bl * _uy, _th * _ux, _bl * _uy], _ink] call _box;
        private _mx = _ix + _iw / 2; private _my = _iy + _ih / 2;
        (_hud get "cross") params ["_c1", "_c2", "_c3", "_c4"];
        private _arm = 22; private _gap = 7; private _t1 = 1 max (_th - 1);
        [_c1, [_mx - (_gap + _arm) * _ux, _my - _t1 * _uy / 2, _arm * _ux, _t1 * _uy], _ink] call _box;
        [_c2, [_mx + _gap * _ux, _my - _t1 * _uy / 2, _arm * _ux, _t1 * _uy], _ink] call _box;
        [_c3, [_mx - _t1 * _ux / 2, _my - (_gap + _arm) * _uy, _t1 * _ux, _arm * _uy], _ink] call _box;
        [_c4, [_mx - _t1 * _ux / 2, _my + _gap * _uy, _t1 * _ux, _arm * _uy], _ink] call _box;
        // Barre d'échelle, en bas au centre de la zone visible : longueur ronde, mesurée sur la largeur au sol de l'image.
        (["bar", _wG] call _fn) params ["_bm", "_bf"];
        private _bw = _bf * _iw;
        private _byb = _y1 - 30 * _uy;
        (_hud get "bar") params ["_s1", "_s2", "_s3"];
        [_s1, [_vx + _vw / 2 - _bw / 2, _byb, _bw, _th * _uy], _ink] call _box;
        [_s2, [_vx + _vw / 2 - _bw / 2, _byb - 8 * _uy, _th * _ux, 8 * _uy + _th * _uy], _ink] call _box;
        [_s3, [_vx + _vw / 2 + _bw / 2 - _th * _ux, _byb - 8 * _uy, _th * _ux, 8 * _uy + _th * _uy], _ink] call _box;
        private _bt = _hud get "barT";
        [_bt, format ["%1 M", _bm], _vx + _vw / 2 - ((_bt getVariable ["COMSPEC_ATAK_W", 0]) / 2), _byb - 8 * _uy - (_bt getVariable ["COMSPEC_ATAK_F", 0.02]) * 1.35] call _txt;
        // Lignes de données (DATA) : capteur et zoom, météo ; hauteur et source ; grille et altitude du centre ; heure.
        private _data = _ov getOrDefault ["DATA", true];
        private _sCode = (createHashMapFromArray [["DAY", "DAY"], ["DAYN", "DAY+"], ["NV", "NV"], ["WHOT", "WHOT"], ["BHOT", "BHOT"], ["FUSW", "FUS W"], ["FUSB", "FUS B"]]) getOrDefault [_sens, _sens];
        private _z = ["zoomOf", _st param [2, 500]] call _fn;
        private _who = _st param [6, ""];
        forceUnicode 0;
        if ((count _who) > 14) then { forceUnicode 0; _who = (_who select [0, 13]) + "…"; };
        private _el = round _gz0;
        private _elS = format ["%1%2", ["+", "-"] select (_el < 0), ((str (abs _el)) call { private _s = _this; while { (count _s) < 5 } do { _s = "0" + _s; }; _s })];
        private _tx0 = _x0 + 10 * _ux; private _ty0 = _y0 + 8 * _uy;
        if (_data) then {
            private _h1 = [_hud get "tL1", format ["IMAGE SAT · %1%2 · %3X", ["", "AUTO "] select (_mode0 isEqualTo "AUTO"), _sCode, _z toFixed 2], _tx0, _ty0] call _txt;
            [_hud get "tL2", format ["SAT %1 · NÉB. %2 · BRUME %3", _n + 1, format ["%1 %2", round (overcast * 100), "%"], format ["%1 %2", round (fog * 100), "%"]], _tx0, _ty0 + _h1 + 2 * _uy] call _txt;
            private _h2 = [_hud get "tR1", format ["N · ALT %1 M · FOV %2", round _alt, round (["fovDeg", _fov] call _fn)], _x1 - 10 * _ux, _ty0, true] call _txt;
            [_hud get "tR2", format ["SOURCE SAT %1 · %2", _n + 1, [_who, "LIBRE"] select (_who isEqualTo "")], _x1 - 10 * _ux, _ty0 + _h2 + 2 * _uy, true] call _txt;
            private _hb = ((_hud get "bL") getVariable ["COMSPEC_ATAK_F", 0.02]) * 1.3;
            [_hud get "bL", format ["GR %1 · ÉLÉV %2 M", mapGridPosition [_cc select 0, _cc select 1], _elS], _tx0, _y1 - 8 * _uy - _hb] call _txt;
            [_hud get "bR", format ["DIRECT %1Z", ["zulu"] call comspec_atak_native_fnc_tocRoom], _x1 - 10 * _ux, _y1 - 8 * _uy - _hb, true] call _txt;
        } else {
            { (_hud get _x) ctrlShow false; } forEach ["tL1", "tL2", "tR1", "tR2", "bL", "bR"];
        };
        // Quadrillage (GRID) : cases de 100 m (1 km au-delà de 1,5 km de large), alignées sur la grille de la carte.
        private _gl = _hud get "grid";
        private _gu = 0;
        if (_ov getOrDefault ["GRID", false]) then {
            private _sp = [100, 1000] select (_wG > 1500);
            private _gcol = +_ink; _gcol set [3, 0.32];
            private _hhG = _hwG / _aspect;
            for "_gx" from ((ceil (((_cc select 0) - _hwG) / _sp)) * _sp) to ((_cc select 0) + _hwG) step _sp do {
                if (_gu >= (count _gl)) exitWith {};
                private _px = _ix + _iw * (0.5 + (_gx - (_cc select 0)) / (2 * _hwG));
                if (_px > _vx && {_px < _vx + _vw}) then { [_gl select _gu, [_px, _vy, _ux, _vh], _gcol] call _box; _gu = _gu + 1; };
            };
            for "_gy" from ((ceil (((_cc select 1) - _hhG) / _sp)) * _sp) to ((_cc select 1) + _hhG) step _sp do {
                if (_gu >= (count _gl)) exitWith {};
                private _py = _iy + _ih * (0.5 - (_gy - (_cc select 1)) / (2 * _hhG));
                if (_py > _vy && {_py < _vy + _vh}) then { [_gl select _gu, [_vx, _py, _vw, _uy], _gcol] call _box; _gu = _gu + 1; };
            };
        };
        for "_i" from _gu to ((count _gl) - 1) do { (_gl select _i) ctrlShow false; };
        // Fusion : contours des bâtiments (contraste des bords) par-dessus l'image thermique.
        private _ed = _hud get "edge";
        private _eu = 0;
        if (_sens in ["FUSW", "FUSB"]) then {
            private _ecol = +_ink; _ecol set [3, 0.7];
            {
                private _pts = _x apply { [_x] call _prj };
                if ((_pts findIf { !(_x call _inV) }) >= 0) then { continue };
                if (_eu + 4 > (count _ed)) exitWith {};
                for "_j" from 0 to 3 do {
                    private _p1 = _pts select _j; private _p2 = _pts select ((_j + 1) mod 4);
                    [_ed select _eu, _p1 select 0, _p1 select 1, _p2 select 0, _p2 select 1, _ecol] call _seg;
                    _eu = _eu + 1;
                };
            } forEach (["edges", [_side, _n]] call _fn);
        };
        for "_i" from _eu to ((count _ed) - 1) do { (_ed select _i) ctrlShow false; };
        // Marqueurs et tâches (MRKR).
        private _ml = _hud get "mk";
        private _mu = 0;
        private _icoPx = _pf get "sym";
        if (_ov getOrDefault ["MRKR", true]) then {
            {
                _x params ["_p", "_ico", "_rgba", "_t"];
                if (_mu >= (count _ml)) exitWith {};
                private _sp = [_p] call _prj;
                if !(_sp call _inV) then { continue };
                (_ml select _mu) params ["_mi", "_mt"];
                _mi ctrlSetText _ico; // i18n:brut (image)
                _mi ctrlSetTextColor _rgba;
                [_mi, [(_sp select 0) - _icoPx * _ux / 2, (_sp select 1) - _icoPx * _uy / 2, _icoPx * _ux, _icoPx * _uy]] call _box;
                forceUnicode 0;
                [_mt, [_t, (_t select [0, 21]) + "…"] select ((count _t) > 22), (_sp select 0) + (_icoPx / 2 + 4) * _ux, (_sp select 1) - (_mt getVariable ["COMSPEC_ATAK_F", 0.02]) * 0.65] call _txt;
                if (_t isEqualTo "") then { _mt ctrlShow false; };
                _mu = _mu + 1;
            } forEach (["marks", [_side, _n]] call _fn);
        };
        for "_i" from _mu to ((count _ml) - 1) do { { _x ctrlShow false; } forEach (_ml select _i); };
        // Pistes amies du réseau ATAK (BFT) et cadres (BOX) : en couleur en direct, en gris si le point est ancien.
        private _sl = _hud get "sym";
        private _su = 0;
        private _bft = _ov getOrDefault ["BFT", true];
        private _boxOn = _ov getOrDefault ["BOX", true];
        if (_bft || {_boxOn}) then {
            {
                _x params ["", "_cs", "_fr", "_kind", "_pos", "_size", ["_g", ""]];
                if (_su >= (count _sl)) exitWith {};
                private _sp = [_pos] call _prj;
                if !(_sp call _inV) then { continue };
                _sp params ["_gx", "_gy"];
                (_sl select _su) params ["_l1", "_l2", "_l3", "_l4", "_ic", "_lb", "_sub"];
                private _col = switch (_fr) do { case 0: { _sideRgb + [1] }; case 1: { (_sideRgb apply { _x * 0.75 }) + [0.9] }; default { [0.62, 0.64, 0.63, 0.85] }; };
                private _bpx = (((_size / ((2 * _hwG) max 1)) * _iw) / _ux) max 14;
                private _bw = _bpx * _ux; private _bh = _bpx * _uy;
                if (_boxOn) then {
                    [_l1, [_gx - _bw / 2, _gy - _bh / 2, _bw, _th * _uy], _col] call _box;
                    [_l2, [_gx - _bw / 2, _gy + _bh / 2 - _th * _uy, _bw, _th * _uy], _col] call _box;
                    [_l3, [_gx - _bw / 2, _gy - _bh / 2, _th * _ux, _bh], _col] call _box;
                    [_l4, [_gx + _bw / 2 - _th * _ux, _gy - _bh / 2, _th * _ux, _bh], _col] call _box;
                } else { { _x ctrlShow false; } forEach [_l1, _l2, _l3, _l4]; };
                if (_bft) then {
                    _ic ctrlSetText (format ["\A3\ui_f\data\map\markers\nato\b_%1.paa", (createHashMapFromArray [["veh", "motor_inf"], ["air", "air"], ["boat", "naval"], ["armor", "armor"]]) getOrDefault [_kind, "inf"]]); // i18n:brut (image)
                    _ic ctrlSetTextColor _col;
                    private _ix0 = _gx + _bw / 2 + 3 * _ux;
                    [_ic, [_ix0, _gy - _bh / 2 - _icoPx * _uy, _icoPx * _ux, _icoPx * _uy]] call _box;
                    forceUnicode 0;
                    private _cs2 = [_cs, (_cs select [0, 17]) + "…"] select ((count _cs) > 18);
                    _lb ctrlSetTextColor ([[1, 1, 1, 1], [0.8, 0.8, 0.8, 1]] select (_fr >= 2));
                    private _hl = [_lb, _cs2, _ix0 + (_icoPx + 3) * _ux, _gy - _bh / 2 - _icoPx * _uy] call _txt;
                    // Ligne d'information : groupe, et l'état du point s'il n'est pas en direct.
                    private _info = [[_g, ""] select (_g isEqualTo "" || {_g isEqualTo _cs}), ["", "point ancien", "point perdu", "hors ligne"] select ((_fr min 3) max 0)] select { _x isNotEqualTo "" };
                    _sub ctrlSetTextColor [0.82, 0.85, 0.83, 1];
                    [_sub, _info joinString " · ", _ix0 + (_icoPx + 3) * _ux, _gy - _bh / 2 - _icoPx * _uy + _hl] call _txt;
                } else { { _x ctrlShow false; } forEach [_ic, _lb, _sub]; };
                _su = _su + 1;
            } forEach (["targets", _side] call _fn);
        };
        for "_i" from _su to ((count _sl) - 1) do { { _x ctrlShow false; } forEach (_sl select _i); };
        true
    };
    case "tick": {
        // Écrans SAT regardés de près : satellites à rendre, les plus proches d'abord, dans la limite du joueur.
        private _reg = _a;
        private _max = missionNamespace getVariable ["comspec_atak_native_toc_cam_max", 2];
        private _range = missionNamespace getVariable ["comspec_atak_native_toc_cam_range", 40];
        if !(_max isEqualType 0) then { _max = 2; };
        if !(_range isEqualType 0) then { _range = 40; };
        _max = ((round _max) - count (missionNamespace getVariable ["COMSPEC_ATAK_TocCams", []])) max 0;
        private _eye = positionCameraToWorld [0, 0, 0];
        private _me = str side group player;
        private _want = [];
        {
            private _o = _x;
            private _c = _o getVariable ["COMSPEC_ATAK_TocScr", []];
            if ((count _c) >= 8 && {(_c select 3) isEqualTo "SAT"} && {alive _o} && {!(_o getVariable ["COMSPEC_ATAK_TocOff", false])} && {(_eye distance _o) < _range}
                && {(_c select 1) in ["", _me]}
                && {(getNumber (configOf _o >> "comspec_projBoard")) < 1 || {_o getVariable ["COMSPEC_ATAK_ProjLit", false]}}
                && {call {
                    if ((worldToScreen (ASLToAGL getPosASL _o)) isNotEqualTo []) then { _o setVariable ["COMSPEC_ATAK_TocSatSeen", diag_tickTime]; };
                    diag_tickTime - (_o getVariable ["COMSPEC_ATAK_TocSatSeen", -1e9]) < 3
                }}) then {
                private _k = [[_c select 1, _me] select ((_c select 1) isEqualTo ""), (_c param [8, 0]) min 1 max 0] call _keyOf;
                private _d = _eye distance _o;
                private _i = _want findIf { (_x select 0) isEqualTo _k };
                if (_i < 0) then { _want pushBack [_k, _d]; } else { (_want select _i) set [1, (((_want select _i) select 1) min _d)]; };
            };
        } forEach _reg;
        _want = ([_want, [], { _x select 1 }, "ASCEND"] call BIS_fnc_sortBy) select [0, _max];
        private _dist = createHashMapFromArray _want;
        private _keep = _want apply { _x select 0 };
        // Demandé par le PC du poste (onglet SAT) : toujours rendu, c'est la console de l'opérateur.
        { if ((_y select 2) > diag_tickTime) then { _keep pushBackUnique _x; }; } forEach _cams;
        {
            if !(_x in _keep) then { private _p = _x splitString "|"; ["free", [_p select 0, parseNumber (_p select 1)]] call _fn; };
        } forEach (keys _cams);
        {
            private _p = _x splitString "|";
            private _e = _cams getOrDefault [_x, []];
            // Résolution : PC du poste H ; écran à 8 m au plus M, au-delà de 10 m L (entre les deux : inchangée).
            private _d = _dist getOrDefault [_x, 1e9];
            private _cur = _e param [4, ""];
            private _q = switch (true) do {
                case ((_e param [7, 0]) > diag_tickTime): { "H" };
                case (_d <= 8): { "M" };
                case (_d > 10): { "L" };
                default { [_cur, "M"] select (_cur in ["", "H"]) };
            };
            if ((count _e) < 8 || {isNull (_e select 0)}) then { ["make", [_p select 0, parseNumber (_p select 1), _q]] call _fn; } else { ["retarget", [_x, _q]] call _fn; };
        } forEach _keep;
        _keep
    };
    default { false };
};
