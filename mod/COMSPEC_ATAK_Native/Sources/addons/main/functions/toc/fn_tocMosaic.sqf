/*
    Mosaïque de caméras ATAK sur un écran TOC (demande de TTRD, 11/11) : affichage MOSAIC, grille 2x2 ou 3x3 de
    caméras du camp (mêmes sources que fn_tocCam « sources » : caméras poitrine des porteurs d'ATAK, drones, caméras
    fixes), chaque vignette avec l'indicatif, l'état de la liaison et l'heure ZULU, « PAS DE SIGNAL » si la source est
    coupée. Sert aussi la caméra poitrine du chef sur l'écran d'escouade (fn_tocScreen, SQUAD).
    Réglage de l'écran : variable publique COMSPEC_ATAK_TocMosaic = [grille 2 | 3, choix "AUTO" (tout le camp) |
    "GROUP" (un groupe) | "MANUAL" (case par case), groupe, [netId par case], rotation en s (0 : aucune)].
    Plus de sources que de cases : pages qui tournent toutes les « rotation » secondes (10 s si non réglé).
    Coût : une caméra et une cible de rendu 256 px (moins si la liaison montante est faible) par source vue, créées par
    chaque joueur seulement pour les écrans qu'il regarde de près ; plafond par joueur = 4 vignettes par caméra
    d'écran restant libre sous comspec_atak_native_toc_cam_max (caméras d'écran et satellites déjà comptés). Au-delà,
    les vignettes en trop passent à tour de rôle (2 s chacune) au lieu d'être rendues ensemble.
    Modes (client) :
      ["cfg", écran]             réglage (valeurs par défaut comprises)
      ["tiles", écran]           cases de la page en cours : [[netId ou "", libellé de la source]...] (taille grille²)
      ["tex", netId]             texture r2t de la source (caméra créée si le plafond le permet), "" sinon
      ["info", netId]            [indicatif, état de la liaison, côté de l'image en px (0 : coupée)]
      ["budget"]                 vignettes rendues possibles en même temps sur ce poste
      ["tick"]                   chaque seconde (fn_tocScreen) : caméras non demandées depuis 3 s libérées
      ["key", clé]               réglage depuis une clé de contenu « MOS|2|AUTO », « MOS|3|G|groupe », « MOS|2|M|id,id »
*/
params [["_mode", "tiles"], ["_a", objNull]];
private _fn = comspec_atak_native_fnc_tocMosaic;
private _cams = uiNamespace getVariable ["COMSPEC_ATAK_TocMosCams", createHashMap];
uiNamespace setVariable ["COMSPEC_ATAK_TocMosCams", _cams];
// Côté de l'image selon la liaison montante de la source (le poste est filaire), comme fn_tocCam, plafonné à 256.
private _resOf = {
    params ["_src"];
    if (_src isKindOf "Man" && {_src isEqualTo player}) exitWith { 256 };
    ((["eval", _src] call comspec_atak_native_fnc_feedLink) getOrDefault ["up", [0, 100]]) params [["_k", 0], ["_l", 100]];
    switch (true) do {
        case (_k <= 0): { 0 };
        case (_k >= 700): { 256 };
        case (_k >= 250): { 128 };
        default { 64 };
    }
};
switch (_mode) do {
    case "cfg": {
        private _c = _a getVariable ["COMSPEC_ATAK_TocMosaic", []];
        if !(_c isEqualType []) then { _c = []; };
        _c params [["_g", 2], ["_m", "AUTO"], ["_grp", ""], ["_man", []], ["_rot", 10]];
        [[2, 3] select (_g isEqualTo 3), _m, _grp, _man, _rot]
    };
    case "key": {
        // « MOS|grille|AUTO », « MOS|grille|G|groupe », « MOS|grille|M|id,id,… », « MOS|grille|R|secondes » (rotation seule).
        private _p = _a splitString "|";
        private _g = [2, 3] select ((_p param [1, "2"]) isEqualTo "3");
        switch (_p param [2, "AUTO"]) do {
            case "G": { [_g, "GROUP", _p param [3, ""], [], 10] };
            case "M": { [_g, "MANUAL", "", (_p param [3, ""]) splitString ",", 0] };
            default { [_g, "AUTO", "", [], 10] };
        }
    };
    case "budget": {
        private _max = missionNamespace getVariable ["comspec_atak_native_toc_cam_max", 2];
        if !(_max isEqualType 0) then { _max = 2; };
        private _busy = (count (missionNamespace getVariable ["COMSPEC_ATAK_TocCams", []]))
            + ({ !isNull (_x select 0) } count (values (missionNamespace getVariable ["COMSPEC_ATAK_TocSatCams", createHashMap])));
        (((round _max) - _busy) max 0) * 4
    };
    case "tiles": {
        (["cfg", _a] call _fn) params ["_g", "_m", "_grp", "_man", "_rot"];
        private _n = _g * _g;
        private _src = ["sources"] call comspec_atak_native_fnc_tocCam;
        if (_m isEqualTo "MANUAL") exitWith {
            private _out = [];
            for "_i" from 0 to (_n - 1) do {
                private _id = _man param [_i, ""];
                private _s = _src select { (_x select 1) isEqualTo _id };
                _out pushBack [[_id, ""] select (_id isEqualTo ""), [(_s select 0) param [0, ""], ""] select ((count _s) isEqualTo 0)];
            };
            _out
        };
        if (_m isEqualTo "GROUP") then {
            _src = _src select { private _o = objectFromNetId (_x select 1); !isNull _o && {(groupId group _o) isEqualTo _grp || {(groupId group (effectiveCommander _o)) isEqualTo _grp}} };
        };
        // Caméras poitrine d'abord, puis drones et caméras fixes ; plus de sources que de cases : pages qui tournent.
        _src = [_src, [], { ["MAN", "UAV", "CAM"] find (_x select 2) }, "ASCEND"] call BIS_fnc_sortBy;
        private _pages = (ceil ((count _src) / _n)) max 1;
        private _pg = [0, (floor (time / ((_rot max 5)))) mod _pages] select (_pages > 1);
        private _page = _src select [_pg * _n, _n];
        private _out = _page apply { [_x select 1, _x select 0] };
        while { (count _out) < _n } do { _out pushBack ["", ""]; };
        _out
    };
    case "info": {
        private _o = objectFromNetId _a;
        if (isNull _o || {!alive _o}) exitWith { ["", "coupée", 0] };
        private _cs = switch (true) do {
            case (unitIsUAV _o): { getText (configOf _o >> "displayName") };
            case (_o isKindOf "Man"): { [_o, true] call comspec_atak_native_fnc_unitCallsign };
            default { _o getVariable ["COMSPEC_ATAK_CamName", "caméra fixe"] };
        };
        private _r = [_o] call _resOf;
        [_cs, (createHashMapFromArray [[0, "coupée"], [64, "faible"], [128, "moyenne"], [256, "bonne"]]) getOrDefault [_r, "bonne"], _r]
    };
    case "tex": {
        private _id = _a;
        if (_id isEqualTo "") exitWith { "" };
        private _src = objectFromNetId _id;
        if (isNull _src || {!alive _src}) exitWith { "" };
        private _e = _cams getOrDefault [_id, []];
        if ((count _e) >= 3 && {!isNull (_e select 0)}) exitWith {
            _e set [2, diag_tickTime + 8];
            if ((_e select 5) <= 0) exitWith { "" };
            format ["#(argb,%1,%1,1)r2t(%2,1.7778)", _e select 5, _e select 1]
        };
        // Plafond du poste atteint : la vignette attend son tour (rotation dans fn_tocBoard).
        if (({ !isNull (_x select 0) } count (values _cams)) >= (["budget"] call _fn)) exitWith { "" };
        private _res = [_src] call _resOf;
        if (_res <= 0) exitWith { "" };
        private _n = (uiNamespace getVariable ["COMSPEC_ATAK_TocMosSeq", 0]) + 1;
        uiNamespace setVariable ["COMSPEC_ATAK_TocMosSeq", _n];
        private _rt = format ["comspec_tocmos_%1", _n];
        private _kind = switch (true) do { case (unitIsUAV _src): { "UAV" }; case (_src isKindOf "Man"): { "MAN" }; default { "CAM" }; };
        private _cam = "camera" camCreate (ASLToAGL getPosASL _src);
        _cam camSetFov 0.75;
        _cam camCommit 0;
        // Même montage que fn_tocCam : poitrine, nacelle du drone (orientée chaque image), caméra fixe.
        switch (_kind) do {
            case "MAN": { _cam attachTo [_src, [0, 0.22, 0.05], "spine3", true]; _cam setVectorDirAndUp [[0, 1, 0], [0, 0, 1]]; };
            case "UAV": {
                private _p0 = _src selectionPosition ["uavCameraGunnerPos", "Memory"];
                if (_p0 isEqualTo [0, 0, 0]) then { _p0 = _src selectionPosition ["uavCameraDriverPos", "Memory"]; };
                _cam attachTo [_src, [[0, 0.3, -0.3], _p0] select (_p0 isNotEqualTo [0, 0, 0])];
            };
            default { _cam attachTo [_src, [0, 0.12, 0.03]]; _cam setVectorDirAndUp [[0, 1, 0], [0, 0, 1]]; };
        };
        ["open", [_cam, _rt]] call comspec_atak_native_fnc_tocRtt;
        _rt setPiPEffect [parseNumber (sunOrMoon < 0.3)];
        _cams set [_id, [_cam, _rt, diag_tickTime + 8, _kind, _src, _res]];
        if (isNil { uiNamespace getVariable "COMSPEC_ATAK_TocMosPfh" }) then {
            uiNamespace setVariable ["COMSPEC_ATAK_TocMosPfh", [{
                private _cs = uiNamespace getVariable ["COMSPEC_ATAK_TocMosCams", createHashMap];
                if ((count _cs) isEqualTo 0) exitWith {
                    [uiNamespace getVariable "COMSPEC_ATAK_TocMosPfh"] call CBA_fnc_removePerFrameHandler;
                    uiNamespace setVariable ["COMSPEC_ATAK_TocMosPfh", nil];
                };
                {
                    _y params ["_cam", "", "", "_kind", "_u"];
                    if (_kind isEqualTo "UAV" && {!isNull _cam} && {alive _u}) then {
                        private _dir = [];
                        private _w = (_u weaponsTurret [0]) param [0, ""];
                        if (!isNull gunner _u && {_w isNotEqualTo ""}) then { _dir = _u vectorWorldToModelVisual (_u weaponDirection _w); } else {
                            if (isClass (configOf _u >> "PilotCamera")) then { _dir = getPilotCameraDirection _u; };
                        };
                        if ((count _dir) < 3 || {(vectorMagnitude _dir) < 0.5}) then { _dir = [0, 0.94, -0.34]; };
                        _dir = vectorNormalized _dir;
                        private _s = _dir vectorCrossProduct [0, 0, 1];
                        if ((vectorMagnitude _s) < 0.01) then { _s = [1, 0, 0]; };
                        _cam setVectorDirAndUp [_dir, vectorNormalized (_s vectorCrossProduct _dir)];
                    };
                } forEach _cs;
            }, 0] call CBA_fnc_addPerFrameHandler];
        };
        ["INFO", "TOC", format ["Mosaïque : caméra %1 (%2 px) rendue sur ce poste", _id, _res]] call comspec_atak_native_fnc_log;
        format ["#(argb,%1,%1,1)r2t(%2,1.7778)", _res, _rt]
    };
    case "tick": {
        // Caméras plus demandées (écran quitté, page tournée, source coupée) : libérées.
        {
            _y params ["_cam", "_rt", "_until", "", "_src"];
            if (diag_tickTime > _until || {isNull _src} || {!alive _src}) then {
                if (!isNull _cam) then { ["close", [_cam, _rt]] call comspec_atak_native_fnc_tocRtt; };
                _cams deleteAt _x;
            };
        } forEach +_cams;
    };
    default { false };
};
