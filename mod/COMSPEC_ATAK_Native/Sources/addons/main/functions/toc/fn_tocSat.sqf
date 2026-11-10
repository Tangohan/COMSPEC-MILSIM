/*
    Vue satellite du TOC (demande de TTRD, 11/11) : deux satellites par camp (SAT 1, SAT 2), vue 3D en direct prise de
    très haut, nord en haut, en jour, vision nocturne verte ou thermique, avec le suivi des alliés par-dessus (symbole,
    cadre autour de l'unité, indicatif), comme un flux ISR.
    Seul l'état circule (fn_tocRoom, satellites : centre, demi-largeur au sol, image, suivi, opérateur qui a la main).
    Chaque joueur crée lui-même la caméra et la cible de rendu d'un satellite (comspec_tocsat_<camp>_<n>, une par
    satellite, partagée par tous ses écrans), seulement s'il en a besoin : PC du poste ouvert sur l'onglet SAT, ou écran
    en affichage SAT qu'il regarde à moins de comspec_atak_native_toc_cam_range mètres. Les écrans comptent dans la même
    limite que les caméras d'écran (comspec_atak_native_toc_cam_max, caméras de fn_tocCam comprises).
    L'image va dans une texture UI (fn_tocBoard) : image r2t et cadres de suivi sont des contrôles de la même texture,
    placés par projection de la position monde vers l'image (caméra verticale : demi-largeur au sol = hauteur × fov,
    même règle que la vue aérienne du JPC, fn_jpcMap).
    Suivi réaliste : seuls les nœuds du réseau ATAK du camp (fn_tocFeed, même source que la carte du TOC) ; en direct à leur
    position, ancien / perdu / hors ligne à leur dernier point PLI, en gris. Jamais d'unité sans ATAK, jamais d'ennemi.
    Modes (client) :
      ["need", [camp, n°]]                 cette vue doit être rendue (PC du poste ouvert sur l'onglet SAT) pendant 3 s
      ["tick", écrans]                     chaque seconde (fn_tocScreen) : caméras créées ou libérées ; renvoie les clés rendues
      ["rt", [camp, n°]]                   nom de la cible de rendu ("" : pas rendue sur ce poste)
      ["pose", [camp, n°]]                 [position ASL de la caméra, fov] ou []
      ["project", [camp, n°, posASL, rapport]]   [u, v, mètres par part de largeur] (0-1 dans l'image) ou []
      ["unproject", [camp, n°, u, v, rapport]]   [x, y] au sol
      ["targets", camp]                    nœuds suivis : [[clé, indicatif, fraîcheur, genre, position ASL, taille m, groupe]...]
      ["aim", [camp, n°]]                  point visé [x, y] (suivi compris) et demi-largeur, ou []
      ["label", [camp, n°]]                ligne d'état « SAT 2 · vision nocturne · contrôlé par … »
      ["tex", [camp, n°]]                  texture de l'image (r2t), "" si pas rendue
*/
params [["_mode", "tick"], ["_a", []]];
private _fn = comspec_atak_native_fnc_tocSat;
private _aspect = 16 / 9;
private _keyOf = { params ["_s", "_n"]; format ["%1|%2", _s, _n] };
private _cams = missionNamespace getVariable ["COMSPEC_ATAK_TocSatCams", createHashMap];
missionNamespace setVariable ["COMSPEC_ATAK_TocSatCams", _cams];
// Hauteur et fov de la caméra pour une demi-largeur au sol : assez haut pour que la vue paraisse prise d'orbite,
// assez bas pour que les objets restent dessinés (distance de vue de l'image dans l'image).
private _altFov = {
    params ["_h"];
    private _alt = ((_h / 0.75) max 180) min 1400;
    [_alt, (_h / _alt) max 0.05]
};
switch (_mode) do {
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
    case "label": {
        _a params ["_side", "_n"];
        private _s = ((["state", _side] call comspec_atak_native_fnc_tocRoom) select 2) param [_n, []];
        private _img = (createHashMapFromArray [["DAY", "jour"], ["NV", "vision nocturne"], ["TI", "thermique"]]) getOrDefault [_s param [3, "DAY"], "jour"];
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
        private _k = _a call _keyOf;
        private _e = _cams getOrDefault [_k, []];
        if ((count _e) isEqualTo 0) then {
            _e = [objNull, "", 0, ""];
            _cams set [_k, _e];
        };
        _e set [2, diag_tickTime + 3];
        if (isNull (_e select 0)) then { ["make", _a] call _fn; };
        true
    };
    case "make": {
        _a params ["_side", "_n"];
        private _k = [_side, _n] call _keyOf;
        private _e = _cams getOrDefault [_k, [objNull, "", 0, ""]];
        if (!isNull (_e select 0)) exitWith { true };
        ["check", "SATELLITE"] call comspec_atak_native_fnc_pipCheck;
        private _rt = format ["comspec_tocsat_%1_%2", toLower _side, _n];
        private _aim = ["aim", [_side, _n]] call _fn;
        if ((count _aim) < 2) exitWith { false };
        (_aim select 0) params ["_x0", "_y0"];
        ([_aim select 1] call _altFov) params ["_alt", "_fov"];
        private _cam = "camera" camCreate [_x0, _y0, _alt];
        _cam setPosASL [_x0, _y0, (getTerrainHeightASL [_x0, _y0]) + _alt];
        _cam setVectorDirAndUp [[0, 0, -1], [0, 1, 0]];
        _cam camSetFov _fov;
        _cam camCommit 0;
        _cam cameraEffect ["Internal", "Back", _rt];
        _e = [_cam, _rt, _e select 2, ""];
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
                    _y params ["_cam", "_rt", "", "_img"];
                    if (isNull _cam) then { continue };
                    private _p = _x splitString "|";
                    private _side = _p select 0;
                    private _n = parseNumber (_p select 1);
                    private _st = ((["state", _side] call comspec_atak_native_fnc_tocRoom) select 2) param [_n, []];
                    private _m = _st param [3, "DAY"];
                    if (_m isNotEqualTo _img) then {
                        _y set [3, _m];
                        _rt setPiPEffect [(createHashMapFromArray [["DAY", 0], ["NV", 1], ["TI", 2]]) getOrDefault [_m, 0]];
                    };
                    private _aim = ["aim", [_side, _n]] call comspec_atak_native_fnc_tocSat;
                    if ((count _aim) < 2) then { continue };
                    (_aim select 0) params ["_tx", "_ty"];
                    private _h = _aim select 1;
                    private _alt = ((_h / 0.75) max 180) min 1400;
                    private _fov = (_h / _alt) max 0.05;
                    private _cur = getPosASL _cam;
                    private _gz = getTerrainHeightASL [_tx, _ty];
                    // Glissement doux vers le point visé (suivi au rythme du PLI, panoramique de l'opérateur).
                    private _k = (diag_deltaTime * 2.5) min 1;
                    private _nx = (_cur select 0) + (_tx - (_cur select 0)) * _k;
                    private _ny = (_cur select 1) + (_ty - (_cur select 1)) * _k;
                    private _nz = (_cur select 2) + ((_gz + _alt) - (_cur select 2)) * _k;
                    _cam setPosASL [_nx, _ny, _nz];
                    _cam setVectorDirAndUp [[0, 0, -1], [0, 1, 0]];
                    _cam camSetFov _fov;
                    _cam camCommit 0;
                    _cam setVariable ["COMSPEC_ATAK_Fov", _fov];
                } forEach _cs;
            }, 0] call CBA_fnc_addPerFrameHandler];
        };
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
        private _rt = ["rt", _a] call _fn;
        if (_rt isEqualTo "") exitWith { "" };
        format ["#(argb,1024,1024,1)r2t(%1,%2)", _rt, _aspect toFixed 4]
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
            if ((count _c) >= 8 && {(_c select 3) isEqualTo "SAT"} && {alive _o} && {(_eye distance _o) < _range}
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
        private _keep = _want apply { _x select 0 };
        // Demandé par le PC du poste (onglet SAT) : toujours rendu, c'est la console de l'opérateur.
        { if ((_y select 2) > diag_tickTime) then { _keep pushBackUnique _x; }; } forEach _cams;
        {
            if !(_x in _keep) then { private _p = _x splitString "|"; ["free", [_p select 0, parseNumber (_p select 1)]] call _fn; };
        } forEach (keys _cams);
        {
            private _p = _x splitString "|";
            ["make", [_p select 0, parseNumber (_p select 1)]] call _fn;
        } forEach _keep;
        _keep
    };
    default { false };
};
