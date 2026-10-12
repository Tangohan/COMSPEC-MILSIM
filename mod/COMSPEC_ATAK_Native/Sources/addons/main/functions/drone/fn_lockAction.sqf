/*
    Marquage de cibles au viseur : caméra du drone appairé ou appareil photo du téléphone.
    Le réticule doit être sur la cible (tolérance qui se resserre avec le zoom) et la cible assez visible
    (pas noyée dans la végétation ou derrière un mur) : un appui la marque, comme un « lock » de nacelle.
    Le marqueur posé sur la carte suit la cible tant que la source la voit ; sortie du champ, masquée ou
    hors portée, la cible est « perdue » après le délai réglé, puis traitée selon le réglage serveur
    comspec_atak_native_lock_lost (calque retiré, marqueur gardé, effacé après délai, ou tout gardé).
      ["mark", "DRONE" | "PHONE"(, objet(, [œil ASL, direction]))]   marque la cible sous le réticule ; renvoie la clé ou "" ;
                                    objet donné : marque cet objet (détection du drone accrochée depuis l'onglet CIBLES) ;
                                    rayon donné : celui d'un clic sur l'image de la vue drone, à la place du réticule ;
      ["aimed", "DRONE" | "PHONE"]  cible sous le réticule (objet ou objNull), sans marquer ;
      ["tick"]                      suivi des cibles marquées (toutes les 0,5 s, XEH_postInitClient) ;
      ["release", clé | ""]         libère une cible (ou toutes) et retire son marqueur ;
      ["list"]                      cibles marquées, les plus récentes d'abord : [[clé, entrée]...] ;
      ["toMarker", clé]             marqueur permanent à sa position (éditeur de marqueur ouvert, texte prérempli) ;
      ["toSse", clé]                fiche SSE d'observation (FRO) préremplie avec la cible, ouverte dans l'app FRS ;
      ["droneCam", clé]             caméra du drone appairé pointée et gardée sur la cible, vue drone incrustée sur la carte.
    Entrée (hashmap) : obj, num, src, marker, seen (time), pos (ASL), state ("TRACK" | "LOST" | "DOWN"),
    lostAt, clock, name, tag, ghost (cadre gardé à la dernière position).
    État : missionNamespace COMSPEC_ATAK_Locks (clé → entrée), COMSPEC_ATAK_LockN (dernier numéro).
*/
params [["_act", "tick"], ["_arg", ""], ["_obj", objNull], ["_ray", []]];
if (!hasInterface) exitWith { [] };
private _locks = missionNamespace getVariable ["COMSPEC_ATAK_Locks", createHashMap];
missionNamespace setVariable ["COMSPEC_ATAK_Locks", _locks];
private _say = { params ["_lvl", "_txt"]; [_lvl, _txt, 4, 40] call comspec_atak_native_fnc_notify; };

// Œil, direction du réticule, champ (tangente du demi-angle vertical) et portée de la source.
private _source = {
    params ["_src"];
    if (_src isEqualTo "DRONE") exitWith {
        private _d = missionNamespace getVariable ["COMSPEC_ATAK_Drone", objNull];
        if (isNull _d || {!alive _d}) exitWith { [] };
        private _cam = missionNamespace getVariable ["COMSPEC_ATAK_DroneCam", objNull];
        private _eye = if (isNull _cam) then { _d modelToWorldVisualWorld [0, 0.3, -0.4] } else { _cam modelToWorldVisualWorld [0, 0, 0] };
        private _dir = missionNamespace getVariable ["COMSPEC_ATAK_DroneCamDir", []];
        if (_dir isEqualTo []) then { _dir = if (isNull _cam) then { _d vectorModelToWorldVisual [0, 0.8, -0.6] } else { vectorDirVisual _cam }; };
        private _fov = 0.7 / (missionNamespace getVariable ["COMSPEC_ATAK_DroneZoom", 1]);
        private _k = 1 - 0.7 * fog;
        [_eye, vectorNormalized _dir, _fov, 700 * _k * ((missionNamespace getVariable ["COMSPEC_ATAK_DroneZoom", 1]) max 1) ^ 0.5, _d, !isNull _cam]
    };
    // Téléphone : objectif du mode photo s'il est ouvert, sinon le regard du joueur.
    private _st = uiNamespace getVariable ["COMSPEC_ATAK_PhotoSt", createHashMap];
    private _photo = uiNamespace getVariable ["COMSPEC_ATAK_PhotoMode", false];
    private _cam = _st getOrDefault ["cam", objNull];
    private _eye = eyePos player;
    private _dir = getCameraViewDirection player;
    if (_photo && {!isNull _cam}) then { _eye = getPosASL _cam; _dir = vectorDir _cam; };
    private _fov = [0.75, _st getOrDefault ["fov", 0.75]] select _photo;
    [_eye, vectorNormalized _dir, _fov, missionNamespace getVariable ["comspec_atak_native_lock_range", 800], player, _photo]
};

// Cible sous le réticule : ce que touche le rayon (homme, véhicule), sinon la plus proche du centre dans
// une tolérance de quelques pour cent du champ, en vue directe et assez visible.
private _aimed = {
    params ["_src", ["_ray", []]];
    private _sv = [_src] call _source;
    if (_sv isEqualTo []) exitWith { objNull };
    _sv params ["_eye", "_dir", "_fov", "_range", "_self"];
    // Rayon d'un clic sur l'image (vue drone) : il remplace le réticule.
    if (_ray isEqualType [] && {(count _ray) isEqualTo 2}) then { _eye = _ray select 0; _dir = vectorNormalized (_ray select 1); };
    private _ok = { params ["_o"]; !isNull _o && {alive _o} && {_o isNotEqualTo player} && {_o isNotEqualTo _self} && {_o isNotEqualTo (vehicle player)}
        && {(_o isKindOf "CAManBase") || {_o isKindOf "LandVehicle"} || {_o isKindOf "Air"} || {_o isKindOf "Ship"} || {_o isKindOf "StaticWeapon"}} };
    // Pièce du drone lui-même (objets attachés au modèle, ex. coque d'un Mavic) ou de la caméra : ignorée.
    private _mine = { params ["_o"]; !isNull _o && {_o isEqualTo _self || {(attachedTo _o) isEqualTo _self} || {_o in (attachedObjects _self)}} };
    private _hit = objNull;
    {
        _x params ["", "", "_o", "_p"];
        private _c = [_p, _o] select (isNull _p);
        if ([_c] call _mine) then { continue };
        // Équipage touché à travers la vitre ou la tourelle : on marque le véhicule.
        if (!isNull _c && {_c isKindOf "CAManBase"} && {(vehicle _c) isNotEqualTo _c}) then { _c = vehicle _c; };
        if ([_c] call _ok) then { _hit = _c; };
        // Premier obstacle touché (cible, mur, végétation, relief) : rien derrière ne compte.
        break;
    } forEach (lineIntersectsSurfaces [_eye, _eye vectorAdd (_dir vectorMultiply _range), _self, player, true, 6, "VIEW", "FIRE"]);
    if (!isNull _hit) exitWith { _hit };
    // Rayon passé à côté (bord d'un véhicule, rotor, homme à contre-jour) : la cible dont le volume frôle le rayon,
    // dans une tolérance de quelques pour cent du champ, en vue directe et assez visible (deux points essayés).
    private _tan = (_fov * 0.07) max 0.012;
    private _mid = _eye vectorAdd (_dir vectorMultiply (_range / 2));
    private _best = objNull;
    private _bestScore = 1;
    {
        private _o = _x;
        private _cs = [aimPos _o, _o modelToWorldVisualWorld [0, 0, 0]];
        private _size = [((boundingBoxReal _o) select 2) * 0.45 max 1, 0.6] select (_o isKindOf "CAManBase");
        {
            private _v = _x vectorDiff _eye;
            private _along = _v vectorDotProduct _dir;
            if (_along > 1 && {_along < _range}) then {
                private _perp = vectorMagnitude (_v vectorDiff (_dir vectorMultiply _along));
                private _score = _perp / (_size + _along * _tan);
                if (_score < _bestScore && {([_self, "VIEW", _o] checkVisibility [_eye, _x]) > 0.2}) then { _best = _o; _bestScore = _score; };
            };
        } forEach _cs;
    } forEach ((_mid nearEntities [["CAManBase", "LandVehicle", "Air", "Ship", "StaticWeapon"], _range / 2 + 5]) select { [_x] call _ok });
    _best
};

// La source voit-elle encore la cible ? Portée, vue directe et, caméra ouverte, dans son cadre.
private _seesIt = {
    params ["_e"];
    private _o = _e get "obj";
    if (isNull _o || {!alive _o}) exitWith { false };
    private _sv = [_e get "src"] call _source;
    if (_sv isEqualTo []) exitWith { false };
    _sv params ["_eye", "_dir", "_fov", "_range", "_self", "_framed"];
    if ((_e get "src") isEqualTo "DRONE" && {!((["link"] call comspec_atak_native_fnc_droneAction) select 2)}) exitWith { false };
    private _v = (aimPos _o) vectorDiff _eye;
    if ((vectorMagnitude _v) > _range * 1.15) exitWith { false };
    // Dans le cadre : caméra ouverte (16/9) ; sinon le regard (≈ 70°) ou la nacelle libre du drone.
    private _in = if (_framed) then {
        private _z = _v vectorDotProduct _dir;
        if (_z <= 0) exitWith { false };
        private _right = vectorNormalized (_dir vectorCrossProduct [0, 0, 1]);
        if ((vectorMagnitude _right) < 0.01) then { _right = [1, 0, 0]; };
        private _up = _right vectorCrossProduct _dir;
        private _aspect = [1.7778, getResolution select 4] select ((_e get "src") isEqualTo "PHONE");
        (abs ((_v vectorDotProduct _right) / _z)) < (_fov * _aspect * 1.02) && {(abs ((_v vectorDotProduct _up) / _z)) < (_fov * 1.02)}
    } else { ((_e get "src") isEqualTo "DRONE") || {(_dir vectorCos _v) > 0.8} };
    _in && {([_self, "VIEW", _o] checkVisibility [_eye, aimPos _o]) > 0.2}
};

private _shortName = {
    params ["_o"];
    private _n = getText (configOf _o >> "displayName");
    if (_n isEqualTo "") then { _n = typeOf _o; };
    _n
};

private _tagOf = {
    params ["_o"];
    private _sx = side group _o;
    private _me = side group player;
    switch (true) do {
        case ([_me, _sx] call BIS_fnc_sideIsEnemy): { "ENNEMI" };
        case (_sx isNotEqualTo civilian && {[_me, _sx] call BIS_fnc_sideIsFriendly}): { "AMI" };
        default { "INCONNU" };
    }
};

private _dropMarker = {
    params ["_e"];
    private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
    private _keys = ["markerAff", "markerType", "markerColor", "markerEditAfter"];
    private _old = _keys apply { _s getOrDefault [_x, "§"] };
    private _aff = ["u", "o", "b"] select (["INCONNU", "ENNEMI", "AMI"] find (_e get "tag"));
    _s set ["markerAff", _aff];
    _s set ["markerType", format ["%1_%2", _aff, ["motor_inf", "inf"] select ((_e get "obj") isKindOf "CAManBase")]];
    _s set ["markerColor", "AUTO"];
    _s set ["markerEditAfter", false];
    private _m = [_e get "pos"] call comspec_atak_native_fnc_markerDrop;
    { if ((_old select _forEachIndex) isEqualTo "§") then { _s deleteAt _x; } else { _s set [_x, _old select _forEachIndex]; }; } forEach _keys;
    if (_m isNotEqualTo "") then {
        _m setMarkerText format ["LOCK %1 · %2 (%3)", [_e get "num", 3] call CBA_fnc_formatNumber, _e get "name", ["téléphone", "drone"] select ((_e get "src") isEqualTo "DRONE")];
    };
    _m
};

private _release = {
    params ["_key", ["_why", ""]];
    private _e = _locks getOrDefault [_key, createHashMap];
    if ((count _e) isEqualTo 0) exitWith {};
    private _m = _e getOrDefault ["marker", ""];
    if (_m isNotEqualTo "" && {(markerShape _m) isNotEqualTo ""}) then { deleteMarker _m; };
    _locks deleteAt _key;
    if ((missionNamespace getVariable ["COMSPEC_ATAK_DroneCamTgt", ""]) isEqualTo format ["LOCK:%1", _key]) then {
        missionNamespace setVariable ["COMSPEC_ATAK_DroneCamTgt", ""];
    };
    if (_why isNotEqualTo "") then {
        ["log", format ["LOCK %1 : %2", [_e get "num", 3] call CBA_fnc_formatNumber, _why]] call comspec_atak_native_fnc_droneAction;
    };
};

switch (_act) do {
    case "aimed": { [_arg] call _aimed };
    case "list": {
        private _l = (keys _locks) apply { [-((_locks get _x) get "num"), _x] };
        _l sort true;
        _l apply { [_x select 1, _locks get (_x select 1)] }
    };
    case "mark": {
        if !(missionNamespace getVariable ["comspec_atak_native_lock_enabled", true]) exitWith {
            ["WARNING", "Marquage au viseur désactivé sur ce serveur"] call _say; ""
        };
        private _src = [_arg, "DRONE"] select (_arg isEqualTo "");
        if (_src isEqualTo "DRONE" && {!((["link"] call comspec_atak_native_fnc_droneAction) select 2)}) exitWith {
            ["WARNING", "Drone hors liaison : marquage impossible"] call _say; ""
        };
        private _o = if (!isNull _obj && {alive _obj}) then { _obj } else { [_src, _ray] call _aimed };
        if (isNull _o) exitWith {
            ["WARNING", ["Rien sous le réticule : placez le viseur sur la cible (assez visible) et recommencez", "Rien sous le clic : cliquez sur la cible (personne ou véhicule assez visible)"] select ((count _ray) isEqualTo 2)] call _say;
            playSound "addItemFailed";
            ""
        };
        private _key = hashValue _o;
        private _e = _locks getOrDefault [_key, createHashMap];
        if ((count _e) > 0 && {(_e get "state") isEqualTo "TRACK"}) exitWith {
            ["INFO", format ["LOCK %1 déjà accroché", [_e get "num", 3] call CBA_fnc_formatNumber]] call _say; _key
        };
        if ((count _e) > 0) then { [_key] call _release; };
        private _n = (missionNamespace getVariable ["COMSPEC_ATAK_LockN", 90 + floor random 9]) + 1;
        missionNamespace setVariable ["COMSPEC_ATAK_LockN", _n];
        _e = createHashMapFromArray [
            ["obj", _o], ["num", _n], ["src", _src], ["marker", ""], ["seen", time], ["pos", getPosASL _o],
            ["state", "TRACK"], ["lostAt", -1], ["clock", [dayTime, "HH:MM:SS"] call BIS_fnc_timeToString],
            ["name", [_o] call _shortName], ["tag", [_o] call _tagOf], ["ghost", false], ["movedAt", time]
        ];
        _e set ["marker", [_e] call _dropMarker];
        _locks set [_key, _e];
        playSound "ClickSoft";
        ["SUCCESS", format ["LOCK %1 : %2 marqué en %3", [_n, 3] call CBA_fnc_formatNumber, _e get "name", [getPosASL _o, 8] call comspec_atak_native_fnc_gridRef]] call _say;
        if (_src isEqualTo "DRONE") then {
            ["log", format ["LOCK %1 : %2 (%3) marqué au viseur en %4", [_n, 3] call CBA_fnc_formatNumber, _e get "name", toLower (_e get "tag"), [getPosASL _o, 8] call comspec_atak_native_fnc_gridRef], "#e5483a"] call comspec_atak_native_fnc_droneAction;
        };
        _key
    };
    // Appareil photo du téléphone ouvert : cadres LOCK dessinés dans la scène (Draw3D), cadre jaune sur la cible visée.
    case "draw3d": {
        private _st = uiNamespace getVariable ["COMSPEC_ATAK_PhotoSt", createHashMap];
        private _cam = _st getOrDefault ["cam", objNull];
        private _eye = [eyePos player, getPosASLVisual _cam] select !isNull _cam;
        private _dir = [getCameraViewDirection player, vectorDirVisual _cam] select !isNull _cam;
        private _right = vectorNormalized (_dir vectorCrossProduct [0, 0, 1]);
        if ((vectorMagnitude _right) < 0.01) then { _right = [1, 0, 0]; };
        private _up = _right vectorCrossProduct _dir;
        private _box = {
            params ["_o", "_p", "_c", "_txt"];
            private _sz = if (_o isKindOf "CAManBase" || {isNull _o}) then { [0.45, 1.9] } else {
                private _bb = boundingBoxReal _o;
                [(((_bb select 1) select 0) - ((_bb select 0) select 0)) / 2, ((_bb select 1) select 2) - ((_bb select 0) select 2)]
            };
            private _ctr = _p vectorAdd [0, 0, (_sz select 1) / 2];
            if (((_ctr vectorDiff _eye) vectorDotProduct _dir) < 1) exitWith {};
            private _hw = _right vectorMultiply (_sz select 0);
            private _hh = _up vectorMultiply ((_sz select 1) / 2);
            private _a = ASLToAGL ((_ctr vectorDiff _hw) vectorAdd _hh);
            private _b = ASLToAGL ((_ctr vectorAdd _hw) vectorAdd _hh);
            private _c2 = ASLToAGL ((_ctr vectorAdd _hw) vectorDiff _hh);
            private _d2 = ASLToAGL ((_ctr vectorDiff _hw) vectorDiff _hh);
            drawLine3D [_a, _b, _c];
            drawLine3D [_b, _c2, _c];
            drawLine3D [_c2, _d2, _c];
            drawLine3D [_d2, _a, _c];
            drawIcon3D ["#(argb,8,8,3)color(0,0,0,0)", _c, _a, 1, 1, 0, _txt, 2, 0.03, "EtelkaMonospacePro", "left"];
        };
        {
            private _o = _y get "obj";
            private _state = _y get "state";
            if (_state isEqualTo "LOST" && {!(_y get "ghost")}) then { continue };
            private _live = _state isEqualTo "TRACK" && {!isNull _o};
            private _p = [_y get "pos", getPosASLVisual _o] select _live;
            [[objNull, _o] select _live, _p, switch (_state) do { case "TRACK": { [0.95, 0.25, 0.2, 1] }; case "DOWN": { [0.36, 0.78, 0.42, 1] }; default { [0.7, 0.72, 0.7, 0.7] }; },
                format ["LOCK_%1 %2m%3", [_y get "num", 3] call CBA_fnc_formatNumber, round (_eye distance _p), ["", " PERDU", " NEUTR."] select ((["TRACK", "LOST", "DOWN"] find _state) max 0)]] call _box;
        } forEach _locks;
        if (diag_tickTime >= (_st getOrDefault ["lockNext", 0])) then {
            _st set ["lockNext", diag_tickTime + 0.25];
            _st set ["lockAimed", ["PHONE"] call _aimed];
        };
        private _a = _st getOrDefault ["lockAimed", objNull];
        if (!isNull _a && {alive _a} && {!((hashValue _a) in _locks)}) then {
            [_a, getPosASLVisual _a, [0.98, 0.82, 0.2, 1], format ["VISÉ %1m · T : marquer", round (_eye distance _a)]] call _box;
        };
    };
    // Depuis une cible marquée : marqueur permanent (éditeur de marqueur ouvert, texte prérempli), fiche SSE
    // d'observation (FRO) préremplie, ou caméra du drone accrochée dessus (vue drone incrustée sur la carte).
    case "toMarker": {
        private _e = _locks getOrDefault [_arg, createHashMap];
        if ((count _e) isEqualTo 0) exitWith { [] };
        private _p = _e get "pos";
        ["MAP"] call comspec_atak_native_fnc_navigate;
        [[_p select 0, _p select 1, 0]] call comspec_atak_native_fnc_mapCenter;
        ["", [_p select 0, _p select 1, 0]] call comspec_atak_native_fnc_markerEditOpen;
        private _ed = (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["markerEdit", createHashMap];
        _ed set ["text", format ["%1 LOCK %2", ["ENI", "AMI"] select ((_e get "tag") isEqualTo "AMI"), [_e get "num", 3] call CBA_fnc_formatNumber] select [0, 40]];
        _ed set ["desc", format ["%1, marqué à %2 en %3", _e get "name", _e get "clock", [_p, 8] call comspec_atak_native_fnc_gridRef]];
        if ((_e get "tag") isNotEqualTo "AMI") then { _ed set ["color", "ColorRed"]; };
        []
    };
    case "toSse": {
        private _e = _locks getOrDefault [_arg, createHashMap];
        if ((count _e) isEqualTo 0) exitWith { [] };
        ["frs", "FRO"] call comspec_atak_native_fnc_sseAction;
        private _draft = uiNamespace getVariable ["COMSPEC_ATAK_FrsDraft", createHashMap];
        if ((count _draft) isEqualTo 0) exitWith { [] };
        private _nl = toString [10];
        private _line = format ["Cible LOCK %1 : %2 (%3), marquée à %4 en %5, %6 (dernière vue il y a %7 s).",
            [_e get "num", 3] call CBA_fnc_formatNumber, _e get "name", toLower (_e get "tag"), _e get "clock",
            [_e get "pos", 8] call comspec_atak_native_fnc_gridRef,
            (createHashMapFromArray [["TRACK", "toujours en vue"], ["LOST", "perdue de vue"], ["DOWN", "neutralisée"]]) getOrDefault [_e get "state", ""],
            round (time - (_e get "seen"))];
        private _body = _draft getOrDefault ["body", ""];
        _draft set ["body", [_body + _nl + _line, _line + _nl] select ((trim _body) isEqualTo "")];
        []
    };
    case "droneCam": {
        private _e = _locks getOrDefault [_arg, createHashMap];
        if ((count _e) isEqualTo 0) exitWith { [] };
        if (isNull (missionNamespace getVariable ["COMSPEC_ATAK_Drone", objNull])) exitWith { ["WARNING", "Aucun drone appairé"] call _say; [] };
        ["camAim", format ["LOCK:%1", _arg]] call comspec_atak_native_fnc_droneAction;
        private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
        if ((_s getOrDefault ["droneView", "OFF"]) isEqualTo "OFF") then { ["view", "INSET"] call comspec_atak_native_fnc_droneAction; };
        ["MAP"] call comspec_atak_native_fnc_navigate;
        [[(_e get "pos") select 0, (_e get "pos") select 1, 0]] call comspec_atak_native_fnc_mapCenter;
        ["INFO", format ["Caméra du drone sur LOCK %1", [_e get "num", 3] call CBA_fnc_formatNumber]] call _say;
        []
    };
    case "release": {
        if (_arg isEqualTo "") then {
            { [_x] call _release; } forEach (keys _locks);
            ["INFO", "Cibles marquées libérées"] call _say;
        } else { [_arg, "libérée"] call _release; };
        []
    };
    case "tick": {
        if ((count _locks) isEqualTo 0) exitWith {};
        private _grace = missionNamespace getVariable ["comspec_atak_native_lock_grace", 5];
        private _mode = missionNamespace getVariable ["comspec_atak_native_lock_lost", 0];
        private _keep = missionNamespace getVariable ["comspec_atak_native_lock_keep", 300];
        private _live = missionNamespace getVariable ["comspec_atak_native_lock_live", true];
        private _reacq = missionNamespace getVariable ["comspec_atak_native_lock_reacquire", true];
        // Libérations faites après le parcours (on ne retire pas d'entrée d'une table en la parcourant).
        private _drop = [];
        {
            private _key = _x;
            private _e = _y;
            private _o = _e get "obj";
            private _num = [_e get "num", 3] call CBA_fnc_formatNumber;
            private _m = _e get "marker";
            // Cible tuée ou détruite : le marqueur reste à sa dernière position, barré « neutralisé ».
            if ((_e get "state") isNotEqualTo "DOWN" && {isNull _o || {!alive _o}}) then {
                _e set ["state", "DOWN"];
                _e set ["lostAt", time];
                if (_m isNotEqualTo "" && {(markerShape _m) isNotEqualTo ""}) then { _m setMarkerTextLocal format ["LOCK %1 · %2 neutralisé", _num, _e get "name"]; _m setMarkerAlpha 0.6; };
                ["log", format ["LOCK %1 : %2 neutralisé", _num, _e get "name"], "#5cc76b"] call comspec_atak_native_fnc_droneAction;
                continue;
            };
            if ((_e get "state") isEqualTo "DOWN") then {
                if ((time - (_e get "lostAt")) > (_keep max 60)) then { _drop pushBack [_key, ""]; };
                continue;
            };
            // Cible perdue : retentée toutes les 2 s seulement (le test de vue coûte un calcul de liaison et de visibilité),
            // jamais si la reprise est désactivée. Sinon les cibles perdues gardées (modes 0 et 3) alourdissaient
            // le suivi un peu plus à chaque marquage, toute la partie.
            if ((_e get "state") isEqualTo "LOST") then {
                if (_mode isEqualTo 1 && {(time - (_e get "lostAt")) > _keep}) then { _drop pushBack [_key, "marqueur effacé (perdue depuis trop longtemps)"]; continue; };
                if (!_reacq || {diag_tickTime < (_e getOrDefault ["nextTry", 0])}) then { continue; };
                _e set ["nextTry", diag_tickTime + 2];
            };
            private _sees = [_e] call _seesIt;
            if (_sees) then {
                private _p = getPosASL _o;
                _e set ["seen", time];
                if ((_e get "state") isEqualTo "LOST") then {
                    if (!_reacq) then { continue };
                    _e set ["state", "TRACK"];
                    _e set ["ghost", false];
                    if (_m isEqualTo "" || {(markerShape _m) isEqualTo ""}) then { _e set ["pos", _p]; _m = [_e] call _dropMarker; _e set ["marker", _m]; };
                    if (_m isNotEqualTo "") then { _m setMarkerAlphaLocal 1; _m setMarkerText format ["LOCK %1 · %2 (%3)", _num, _e get "name", ["téléphone", "drone"] select ((_e get "src") isEqualTo "DRONE")]; };
                    ["INFO", format ["LOCK %1 raccroché", _num]] call _say;
                    ["log", format ["LOCK %1 : cible revue, suivi repris", _num], "#f2ab33"] call comspec_atak_native_fnc_droneAction;
                };
                // Suivi en direct : le marqueur suit la cible (au plus une fois par seconde, au-delà de 5 m).
                if (_live && {(_p distance2D (_e get "pos")) > 5} && {(time - (_e get "movedAt")) >= 1}) then {
                    _e set ["pos", _p];
                    _e set ["movedAt", time];
                    if (_m isNotEqualTo "" && {(markerShape _m) isNotEqualTo ""}) then { _m setMarkerPos _p; };
                };
                continue;
            };
            if ((_e get "state") isEqualTo "TRACK" && {(time - (_e get "seen")) > _grace}) then {
                _e set ["state", "LOST"];
                _e set ["lostAt", time];
                ["WARNING", format ["LOCK %1 perdu : %2 hors du champ", _num, _e get "name"]] call _say;
                ["log", format ["LOCK %1 : cible perdue (%2)", _num, ["hors du champ de la caméra", "hors de vue"] select ((_e get "src") isEqualTo "PHONE")], "#f2ab33"] call comspec_atak_native_fnc_droneAction;
                // 0 calque retiré, marqueur gardé · 1 calque retiré, marqueur effacé après délai · 2 tout effacé · 3 tout gardé (cadre fantôme).
                if (_mode isEqualTo 2) then { _drop pushBack [_key, ""]; continue; };
                if (_mode isEqualTo 3) then { _e set ["ghost", true]; };
                if (_m isNotEqualTo "" && {(markerShape _m) isNotEqualTo ""}) then {
                    _m setMarkerAlphaLocal 0.55;
                    _m setMarkerText format ["LOCK %1 · %2 perdu %3", _num, _e get "name", [dayTime, "HH:MM"] call BIS_fnc_timeToString];
                };
            };
            if ((_e get "state") isEqualTo "LOST" && {_mode isEqualTo 1} && {(time - (_e get "lostAt")) > _keep}) then { _drop pushBack [_key, "marqueur effacé (perdue depuis trop longtemps)"]; };
        } forEach _locks;
        { _x call _release; } forEach _drop;
        // Au plus 24 cibles suivies : au-delà, les plus anciennes perdues ou neutralisées sont libérées.
        if ((count _locks) > 24) then {
            private _old = ((keys _locks) apply { [_x, _locks get _x] }) select { ((_x select 1) get "state") isNotEqualTo "TRACK" };
            _old = [_old, [], { (_x select 1) get "num" }, "ASCEND"] call BIS_fnc_sortBy;
            { if ((count _locks) <= 24) exitWith {}; [_x select 0, "libérée (trop de cibles suivies)"] call _release; } forEach _old;
        };
    };
    default { [] };
};
