/*
    Mode drone, côté téléphone du pilote. Le drone doit être posé à ses pieds pour s'appairer (rien à distance) ;
    ensuite la liaison radio porte comspec_atak_native_drone_range mètres, réduite par le relief.
    Liaison perdue : le drone rentre seul à son point de décollage, comme un DJI.
    Drones du mod Mavic (Mavic_drone_base_F) : appairage identique, signal repris de Mavic_fnc_getSignal.
      ["pair", drone] / ["unpair"]
      ["mode", "hover" | "me" | "player" | "home" | "land" | "hunt"]
      ["player", uid]             joueur à suivre ;
      ["alt", m] / ["speed", km/h]
      ["arm"]                     fixe une charge prise dans l'inventaire (drone à moins de 5 m) ;
      ["strike", "LASER" | "GRID"]  tir et oublie, à confirmer d'un second appui ;
      ["cam"]                     caméra du drone dans l'app ;
      ["takeoff"]                 démarre et décolle (tout ordre de vol donné au sol le fait aussi) ;
      ["cqb"]                     bascule le profil CQB (vol bas 2 à 8 m, 5 à 15 km/h, boucle de vol à nous) ;
      ["manual"]                  pilotage manuel au terminal UAV ; tout autre ordre du téléphone reprend la main.
    Verrou (variable publique COMSPEC_DroneLock du drone, appliqué par XEH_postInitClient sur chaque client) :
      "" = le téléphone pilote, personne ne peut se connecter au drone avec un terminal UAV ;
      uid = seul ce joueur peut s'y connecter (pilotage manuel) ; absent = drone libre.
      ["tick"]                    surveillance (toutes les secondes tant qu'un drone est appairé) ;
      ["link"]                    renvoie [barres 0-4, distance, en liaison].
    Tâches sur point (onglet TÂCHES) :
      ["tab", "PILOT" | "TASK" | "TRACKS" | "LOG"]   onglet de la page ;
      ["task", "GOTO" | "LOITER" | "OBSERVE" | "ESCORT"] / ["taskRad", m]   tâche et rayon choisis ;
      ["pickMap"] / ["picked", position]   point pointé sur la carte du téléphone (clé dronePick, fn_mapMouseButtonDown) ;
      ["taskSend"]                envoie la tâche sur la grille saisie ; ["escort", uid] escorte un joueur allié ;
      ["taskClear"]               efface le point, et remet le drone en stationnaire s'il exécutait une tâche.
    Pistes capteur (onglet PISTES) : ce que la caméra du drone voit, en vue directe et à portée (relevé à chaque tick) :
      ["camAim", clé | ""]        pointe la caméra sur une piste (ou la remet devant) ;
      ["trackMark", clé]          pose un marqueur sur la dernière position vue ; ["tracksClear"] vide la liste ;
      ["strike", "TRK:clé"]       frappe la piste (cible suivie si vue il y a moins de 10 s, sinon dernière position).
    Ordres depuis la carte (outil DRONE de la carte du téléphone, mapMode "DRONE") :
      ["mapSub", "GOTO" | "ROUTE" | "ZONE"]   sous-outil ; ["routeMode", "ONCE" | "LOOP" | "PINGPONG"] ; ["zoneKind", "LOITER" | "OBSERVE" | "HUNT"] ;
      ["mapClick", position]      clic sur la carte (aller ici, point de route, centre puis bord / coins de la zone) ;
      ["routeSend"] / ["mapClear"]   envoie la route tracée / efface le tracé en cours ;
      ["mapBar", [x, y, largeur]] dessine la barre drone de la carte (appelé par fn_pageMap).
    Journal : ["log", texte, couleur] ; tout événement y est daté HH:MM:SS (100 lignes au plus).
    État : missionNamespace COMSPEC_ATAK_Drone (drone appairé), COMSPEC_ATAK_DroneHome, COMSPEC_ATAK_DroneCam,
    COMSPEC_ATAK_DroneCamTgt (clé de piste visée par la caméra), COMSPEC_ATAK_DroneTracks (clé → [objet, type, position ASL,
    vu à (time), vu à (HH:MM:SS), camp vu, distance, n°, état]), COMSPEC_ATAK_DroneLog ([[HH:MM:SS, texte, couleur]...]).
*/
params [["_act", "tick"], ["_arg", ""]];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _d = missionNamespace getVariable ["COMSPEC_ATAK_Drone", objNull];
private _render = { [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "DRONE") then { ["DRONE"] call comspec_atak_native_fnc_pageRender; }; }] call CBA_fnc_execNextFrame; };
private _send = {
    params ["_cmd", ["_args", []]];
    // Tout ordre du téléphone (sauf le passage en manuel) verrouille le terminal UAV : une seule main sur le drone.
    if (_cmd isNotEqualTo "manual" && {(_d getVariable ["COMSPEC_DroneLock", ""]) isNotEqualTo ""}) then { _d setVariable ["COMSPEC_DroneLock", "", true]; };
    ["comspec_atak_native_droneCmd", [_d, _cmd, _args], _d] call CBA_fnc_targetEvent;
};
private _mapRender = { [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "MAP") then { ["MAP"] call comspec_atak_native_fnc_pageRender; }; }] call CBA_fnc_execNextFrame; };
private _say = { params ["_lvl", "_txt"]; [_lvl, _txt, 4, 40] call comspec_atak_native_fnc_notify; };
// Charges acceptées : chargeur de l'inventaire → munition déclenchée à l'impact.
private _payloads = [
    ["RPG7_F", "R_PG7_F", "roquette RPG-7"], ["RPG32_F", "R_PG32V_F", "roquette RPG-42"],
    ["DemoCharge_Remote_Mag", "DemoCharge_Remote_Ammo_Scripted", "charge de démolition"],
    ["HandGrenade", "GrenadeHand", "grenade RGO"], ["MiniGrenade", "mini_Grenade", "grenade RGN"]
];
private _link = {
    if (isNull _d || {!alive _d}) exitWith { [0, 0, false] };
    private _range = missionNamespace getVariable ["comspec_atak_native_drone_range", 2500];
    if (terrainIntersectASL [eyePos player, getPosASL _d]) then { _range = _range * 0.3; };
    if !([player] call comspec_atak_native_fnc_hasDevice) then { _range = 0; };
    if ((missionNamespace getVariable ["COMSPEC_ATAK_Battery", 100]) <= 0) then { _range = 0; };
    private _dist = player distance _d;
    // Drone du mod Mavic (DJI) : on reprend son propre calcul de signal (0-1, perdu sous 0,05).
    if (_range > 0 && {_d isKindOf "Mavic_drone_base_F"} && {!isNil "Mavic_fnc_getSignal"}) exitWith {
        private _sig = [player, _d] call Mavic_fnc_getSignal;
        if !(_sig isEqualType 0) then { _sig = 1; };
        private _okM = _sig >= 0.05;
        [[0, (ceil (_sig * 4)) max 1 min 4] select _okM, round _dist, _okM]
    };
    private _ok = _dist < _range;
    [[0, ceil (4 * (1 - _dist / (_range max 1))) max 1] select _ok, round _dist, _ok]
};
private _needLink = {
    if ((call _link) select 2) exitWith { true };
    ["WARNING", "Drone hors liaison : ordre non transmis"] call _say;
    false
};
// Journal du drone : chaque événement daté, 100 lignes au plus.
private _log = {
    params ["_t", ["_c", ""]];
    private _lg = missionNamespace getVariable ["COMSPEC_ATAK_DroneLog", []];
    _lg pushBack [[dayTime, "HH:MM:SS"] call BIS_fnc_timeToString, _t, _c];
    while { (count _lg) > 100 } do { _lg deleteAt 0; };
    missionNamespace setVariable ["COMSPEC_ATAK_DroneLog", _lg];
};
private _modeName = { params ["_m"]; (createHashMapFromArray [["HOVER", "stationnaire"], ["FOLLOW", "suivi"], ["HOME", "retour au pilote"], ["LAND", "atterrissage"], ["RTH", "retour automatique au point de décollage"], ["STRIKE", "frappe"], ["HUNT", "recherche"], ["MANUAL", "pilotage manuel"], ["GOTO", "vers le point"], ["LOITER", "orbite"], ["OBSERVE", "observation"], ["ESCORT", "escorte"]]) getOrDefault [_m, toLower _m] };
// Grille saisie (6, 8 ou 10 chiffres) → centre de la case, position ASL ; [] si invalide.
private _gridPos = {
    params ["_txt"];
    private _g = _txt regexReplace ["[^0-9]", ""];
    if ((count _g) < 6 || {((count _g) mod 2) isEqualTo 1}) exitWith { [] };
    (([_g] call BIS_fnc_gridToPos)) params [["_p", []], ["_sz", [0, 0]]];
    if ((count _p) < 2) exitWith { [] };
    private _c = [(_p select 0) + (_sz select 0) / 2, (_p select 1) + (_sz select 1) / 2];
    [_c select 0, _c select 1, (getTerrainHeightASL _c) + 1]
};
// Le champ de grille de l'onglet TÂCHES est gardé avant chaque nouveau rendu (absent de l'écran : on garde la valeur connue).
private _saveGrid = { private _v = ["droneTaskGrid", "§"] call comspec_atak_native_fnc_formValue; if (_v isNotEqualTo "§") then { _s set ["droneTaskGridVal", _v]; }; };
private _camOpen = {
    if (!isNull (missionNamespace getVariable ["COMSPEC_ATAK_DroneCam", objNull]) || {isNull _d}) exitWith {};
    private _cam = "camera" camCreate (getPosATL _d);
    _cam cameraEffect ["Internal", "Back", "comspec_dronecam"];
    _cam camSetFov (0.7 / (missionNamespace getVariable ["COMSPEC_ATAK_DroneZoom", 1]));
    _cam camCommit 0;
    _cam attachTo [_d, [0, 0.3, -0.3]];
    _cam setVectorDirAndUp [[0, 0.8, -0.6], [0, 0.6, 0.8]];
    missionNamespace setVariable ["COMSPEC_ATAK_DroneVision", [0, 1] select (sunOrMoon < 0.3)];
    "comspec_dronecam" setPiPEffect [missionNamespace getVariable ["COMSPEC_ATAK_DroneVision", 0]];
    missionNamespace setVariable ["COMSPEC_ATAK_DroneCamT0", time];
    missionNamespace setVariable ["COMSPEC_ATAK_DroneCam", _cam];
    // Nacelle : chaque image, la caméra vise la piste choisie ou le point observé, sinon regarde devant et en bas.
    missionNamespace setVariable ["COMSPEC_ATAK_DroneCamPfh", [{
        private _cam = missionNamespace getVariable ["COMSPEC_ATAK_DroneCam", objNull];
        private _d = missionNamespace getVariable ["COMSPEC_ATAK_Drone", objNull];
        if (isNull _cam || {isNull _d}) exitWith {};
        private _k = missionNamespace getVariable ["COMSPEC_ATAK_DroneCamTgt", ""];
        private _p = [];
        // Nadir : nacelle à la verticale, sous le drone.
        if (_k isEqualTo "NADIR") then { _p = (getPosASLVisual _d) vectorAdd [0.01, 0.01, -50]; };
        if (_k isNotEqualTo "" && {_k isNotEqualTo "NADIR"}) then {
            private _tr = (missionNamespace getVariable ["COMSPEC_ATAK_DroneTracks", createHashMap]) getOrDefault [_k, []];
            if ((count _tr) > 0) then {
                _tr params ["_o", "", "_pos", "_seen"];
                // Piste vue à l'instant : la nacelle la suit ; sinon elle reste sur sa dernière position vue.
                _p = [_pos, aimPos _o] select (alive _o && {time - _seen < 5});
            };
        };
        if (_p isEqualTo []) then {
            private _t = _d getVariable ["COMSPEC_DroneTask", []];
            if ((_t param [0, ""]) isEqualTo "OBSERVE") then { _p = _t select 1; };
        };
        if (_p isEqualTo []) exitWith {
            if (_cam getVariable ["aimed", false]) then {
                _cam setVariable ["aimed", false];
                _cam setVectorDirAndUp [[0, 0.8, -0.6], [0, 0.6, 0.8]];
                missionNamespace setVariable ["COMSPEC_ATAK_DroneCamDir", []];
            };
        };
        private _dir = vectorNormalized (_p vectorDiff (_d modelToWorldVisualWorld [0, 0.3, -0.3]));
        private _side = _dir vectorCrossProduct [0, 0, 1];
        if ((vectorMagnitude _side) < 0.01) then { _side = [1, 0, 0]; };
        private _up = _side vectorCrossProduct _dir;
        // Caméra attachée : orientation exprimée dans le repère du drone.
        _cam setVectorDirAndUp [_d vectorWorldToModelVisual _dir, _d vectorWorldToModelVisual _up];
        _cam setVariable ["aimed", true];
        missionNamespace setVariable ["COMSPEC_ATAK_DroneCamDir", _dir];
    }, 0] call CBA_fnc_addPerFrameHandler];
};
private _camClose = {
    private _cam = missionNamespace getVariable ["COMSPEC_ATAK_DroneCam", objNull];
    if (!isNull _cam) then { _cam cameraEffect ["Terminate", "Back", "comspec_dronecam"]; camDestroy _cam; };
    missionNamespace setVariable ["COMSPEC_ATAK_DroneCam", objNull];
    [missionNamespace getVariable ["COMSPEC_ATAK_DroneCamPfh", -1]] call CBA_fnc_removePerFrameHandler;
    missionNamespace setVariable ["COMSPEC_ATAK_DroneCamPfh", -1];
    missionNamespace setVariable ["COMSPEC_ATAK_DroneCamDir", []];
};
// Capteur : ce que la caméra voit (vue directe, portée réduite par le brouillard), en 360° ou dans le champ de la nacelle pointée.
private _scan = {
    private _eye = _d modelToWorldVisualWorld [0, 0.3, -0.4];
    private _k = 1 - 0.7 * fog;
    private _rMan = 450 * _k;
    private _rVeh = 700 * _k;
    private _cone = missionNamespace getVariable ["COMSPEC_ATAK_DroneCamDir", []];
    private _tracks = missionNamespace getVariable ["COMSPEC_ATAK_DroneTracks", createHashMap];
    private _side = side group player;
    private _grp = units group player;
    private _seen = (_d nearEntities [["CAManBase", "LandVehicle", "Ship", "Air"], _rVeh]) select {
        _x isNotEqualTo _d && {alive _x} && {!(_x in _grp)} && {_x isNotEqualTo (vehicle player)}
        && {[({alive _x} count (crew _x)) > 0, (_x distance _d) < _rMan] select (_x isKindOf "CAManBase")}
        && {(_cone isEqualTo []) || {(_cone vectorCos ((aimPos _x) vectorDiff _eye)) > 0.77}}
        && {([_d, "VIEW", _x] checkVisibility [_eye, aimPos _x]) > 0.3}
    };
    {
        private _key = hashValue _x;
        private _tr = _tracks getOrDefault [_key, []];
        private _sx = side _x;
        private _tag = switch (true) do {
            case ([_side, _sx] call BIS_fnc_sideIsEnemy): { "ENNEMI" };
            case (_sx isNotEqualTo civilian && {[_side, _sx] call BIS_fnc_sideIsFriendly}): { "AMI" };
            default { "INCONNU" };
        };
        private _num = _tr param [7, -1];
        if (_num < 0) then { _num = (missionNamespace getVariable ["COMSPEC_ATAK_DroneTrackN", 0]) + 1; missionNamespace setVariable ["COMSPEC_ATAK_DroneTrackN", _num]; };
        private _name = getText (configOf _x >> "displayName");
        _tracks set [_key, [_x, _name, getPosASL _x, time, [dayTime, "HH:MM:SS"] call BIS_fnc_timeToString, _tag, round (_x distance _d), _num, ""]];
        if ((count _tr) isEqualTo 0 && {_tag isNotEqualTo "AMI"}) then {
            [format ["Nouvelle piste %1 : %2 (%3) en %4, à %5 m du drone", [_num, 2] call CBA_fnc_formatNumber, _name, toLower _tag, [getPosASL _x, 8] call comspec_atak_native_fnc_gridRef, round (_x distance _d)], ["#f2ab33", "#e5483a"] select (_tag isEqualTo "ENNEMI")] call _log;
            if (_tag isEqualTo "ENNEMI") then { ["WARNING", format ["Drone : piste %1, %2 ennemi", [_num, 2] call CBA_fnc_formatNumber, _name], 4, 40] call comspec_atak_native_fnc_notify; };
        };
    } forEach _seen;
    // Évaluation des dégâts : une piste détruite n'est connue que si la caméra revoit sa position.
    {
        _y params ["_o", "_name", "_pos", "", "", "", "", "_num", "_state"];
        if (_state isEqualTo "" && {!alive _o} && {(_d distance _pos) < _rVeh} && {([_d, "VIEW", _o] checkVisibility [_eye, _pos vectorAdd [0, 0, 1]]) > 0.3}) then {
            _y set [8, "DÉTRUIT"];
            [format ["Piste %1 (%2) : détruite, constaté par la caméra", [_num, 2] call CBA_fnc_formatNumber, _name], "#5cc76b"] call _log;
        };
    } forEach _tracks;
    // 30 pistes au plus : les plus anciennes sortent.
    while { (count _tracks) > 30 } do {
        private _old = (keys _tracks) apply { [(_tracks get _x) select 3, _x] };
        _old sort true;
        _tracks deleteAt ((_old select 0) select 1);
    };
    missionNamespace setVariable ["COMSPEC_ATAK_DroneTracks", _tracks];
};
switch (_act) do {
    case "link": { call _link };
    case "log": { [_arg, _this param [2, ""]] call _log; };
    case "pair": {
        private _new = _arg;
        if (isNull _new || {!alive _new} || {(player distance _new) > 15}) exitWith { ["WARNING", "Posez le drone à vos pieds pour l'appairer"] call _say; };
        private _owner = _new getVariable ["COMSPEC_DroneOwner", ""];
        if (_owner isNotEqualTo "" && {_owner isNotEqualTo getPlayerUID player}) exitWith { ["WARNING", "Ce drone est déjà appairé à un autre téléphone"] call _say; };
        if (!isNull _d && {_d isNotEqualTo _new}) then { ["unpair"] call comspec_atak_native_fnc_droneAction; };
        _new setVariable ["COMSPEC_DroneOwner", getPlayerUID player, true];
        _new setVariable ["COMSPEC_DroneLock", "", true];
        _new setVariable ["COMSPEC_DroneSide", side group player, true];
        _new setVariable ["COMSPEC_DroneAlt", _s getOrDefault ["droneAlt", 40], true];
        _new setVariable ["COMSPEC_DroneSpd", _s getOrDefault ["droneSpd", 40], true];
        missionNamespace setVariable ["COMSPEC_ATAK_Drone", _new];
        missionNamespace setVariable ["COMSPEC_ATAK_DroneHome", getPosASL _new];
        missionNamespace setVariable ["COMSPEC_ATAK_DroneLastMode", ""];
        missionNamespace setVariable ["COMSPEC_ATAK_DroneFuelWarn", 0];
        missionNamespace setVariable ["COMSPEC_ATAK_DroneEvtN", (_new getVariable ["COMSPEC_DroneEvt", [0]]) select 0];
        missionNamespace setVariable ["COMSPEC_ATAK_DroneLinked", true];
        ["SUCCESS", format ["Drone appairé : %1", getText (configOf _new >> "displayName")]] call _say;
        [format ["Appairage : %1, point de décollage en %2", getText (configOf _new >> "displayName"), [getPosASL _new, 8] call comspec_atak_native_fnc_gridRef], "#5cc76b"] call _log;
        if ((missionNamespace getVariable ["COMSPEC_ATAK_DronePfh", -1]) < 0) then {
            missionNamespace setVariable ["COMSPEC_ATAK_DronePfh", [{ ["tick"] call comspec_atak_native_fnc_droneAction; }, 1] call CBA_fnc_addPerFrameHandler];
        };
        call _render;
    };
    case "unpair": {
        if (!isNull _d) then {
            if (alive _d) then { ["Désappairage du drone"] call _log; };
            _d setVariable ["COMSPEC_DroneOwner", "", true]; _d setVariable ["COMSPEC_DroneLock", nil, true];
        };
        call _camClose;
        missionNamespace setVariable ["COMSPEC_ATAK_DroneCamTgt", ""];
        missionNamespace setVariable ["COMSPEC_ATAK_Drone", objNull];
        [missionNamespace getVariable ["COMSPEC_ATAK_DronePfh", -1]] call CBA_fnc_removePerFrameHandler;
        missionNamespace setVariable ["COMSPEC_ATAK_DronePfh", -1];
        call _render;
    };
    case "mode": {
        if (isNull _d || {!(call _needLink)}) exitWith {};
        switch (_arg) do {
            case "hover": { ["hover"] call _send; };
            case "me": { ["follow", [player]] call _send; };
            case "player": {
                private _uid = _s getOrDefault ["droneFollowUid", ""];
                private _t = allPlayers param [(allPlayers findIf { getPlayerUID _x isEqualTo _uid }), objNull];
                if (isNull _t || {!alive _t}) exitWith { ["WARNING", "Choisissez d'abord le joueur à suivre"] call _say; };
                ["follow", [_t]] call _send;
            };
            case "home": { ["home", [player]] call _send; };
            case "land": { ["land"] call _send; };
            case "hunt": { ["hunt", [_s getOrDefault ["droneHuntRad", 250], player]] call _send; };
        };
        if (_arg isEqualTo "hunt") then { [format ["Ordre : recherche, rayon %1 m", _s getOrDefault ["droneHuntRad", 250]]] call _log; };
        call _render;
    };
    case "takeoff": {
        if (isNull _d || {!(call _needLink)}) exitWith {};
        ["takeoff"] call _send;
        [format ["Ordre : démarrage et décollage, %1 m", _s getOrDefault ["droneAlt", 40]]] call _log;
        call _render;
    };
    case "cqb": {
        if (isNull _d || {!(call _needLink)}) exitWith {};
        private _on = !(_d getVariable ["COMSPEC_DroneCqb", false]);
        _s set ["droneAlt", [40, 3] select _on];
        _s set ["droneSpd", [40, 10] select _on];
        ["cqb", [_on, _s get "droneAlt", _s get "droneSpd"]] call _send;
        [["Profil CQB quitté : 40 m, 40 km/h", "Profil CQB : vol bas à 3 m, 10 km/h, arrêt devant obstacle"] select _on, "#f2ab33"] call _log;
        call _render;
    };
    case "manual": {
        if (isNull _d || {!(call _needLink)}) exitWith {};
        _d setVariable ["COMSPEC_DroneLock", getPlayerUID player, true];
        ["manual"] call _send;
        ["INFO", "Pilotage manuel : connectez votre terminal UAV. Un ordre du téléphone reprend la main."] call _say;
        call _render;
    };
    case "player": { _s set ["droneFollowUid", _arg]; call _render; };
    case "alt": { _s set ["droneAlt", _arg]; if (!isNull _d && {call _needLink}) then { ["alt", [_arg]] call _send; }; call _render; };
    case "speed": { _s set ["droneSpd", _arg]; if (!isNull _d && {call _needLink}) then { ["speed", [_arg]] call _send; }; call _render; };
    case "radius": { _s set ["droneHuntRad", _arg]; call _render; };
    case "arm": {
        if (isNull _d) exitWith {};
        if ((player distance _d) > 5) exitWith { ["WARNING", "Le drone doit être à moins de 5 m pour fixer une charge"] call _say; };
        if !(missionNamespace getVariable ["comspec_atak_native_drone_strike", true]) exitWith { ["WARNING", "Drones armés interdits sur ce serveur"] call _say; };
        private _mags = magazines player;
        private _i = _payloads findIf { ((_x select 0) in _mags) && {isClass (configFile >> "CfgAmmo" >> (_x select 1))} };
        if (_i < 0) exitWith { ["WARNING", "Aucune charge dans l'inventaire (roquette RPG, charge de démolition ou grenade)"] call _say; };
        (_payloads select _i) params ["_mag", "_ammo", "_label"];
        player removeMagazine _mag;
        _d setVariable ["COMSPEC_DroneArmed", _ammo, true];
        _d setVariable ["COMSPEC_DroneArmedLabel", _label, true];
        ["SUCCESS", format ["Drone armé : %1", _label]] call _say;
        [format ["Armement : %1 fixée", _label], "#f2ab33"] call _log;
        call _render;
    };
    case "strike": {
        if (isNull _d || {!(call _needLink)}) exitWith {};
        if ((_d getVariable ["COMSPEC_DroneArmed", ""]) isEqualTo "") exitWith { ["WARNING", "Drone non armé"] call _say; };
        private _trk = [];
        private _tgt = switch (true) do {
            case (_arg isEqualTo "LASER"): {
                private _l = ((units group player) apply { laserTarget _x }) select { !isNull _x };
                if ((count _l) > 0) then { _l select 0 } else { objNull }
            };
            case ((_arg find "TRK:") isEqualTo 0): {
                // Piste capteur : verrouillée si la caméra la voit encore (moins de 10 s), sinon sa dernière position vue.
                _trk = (missionNamespace getVariable ["COMSPEC_ATAK_DroneTracks", createHashMap]) getOrDefault [_arg select [4], []];
                if ((count _trk) isEqualTo 0) then { [] } else {
                    _trk params ["_o", "", "_pos", "_seen"];
                    [_pos, _o] select (alive _o && {time - _seen < 10})
                }
            };
            default { [["droneGrid", ""] call comspec_atak_native_fnc_formValue] call _gridPos };
        };
        if ((_tgt isEqualType objNull && {isNull _tgt}) || {_tgt isEqualTo []}) exitWith {
            ["WARNING", switch (true) do { case (_arg isEqualTo "LASER"): { "Aucun laser allumé dans votre groupe" }; case ((_arg find "TRK:") isEqualTo 0): { "Piste inconnue" }; default { "Grille invalide (6, 8 ou 10 chiffres)" }; }] call _say;
        };
        // Second appui dans les 5 s pour confirmer : pas de frappe partie par erreur.
        private _c = _s getOrDefault ["droneConfirm", ["", 0]];
        if ((_c select 0) isNotEqualTo _arg || {diag_tickTime - (_c select 1) > 5}) exitWith {
            _s set ["droneConfirm", [_arg, diag_tickTime]];
            ["WARNING", "Appuyez encore pour confirmer la frappe"] call _say;
            call _render;
        };
        _s set ["droneConfirm", ["", 0]];
        ["strike", [_tgt, player]] call _send;
        private _what = switch (true) do {
            case ((count _trk) > 0): { format ["la piste %1 (%2) en %3", [_trk select 7, 2] call CBA_fnc_formatNumber, _trk select 1, [_trk select 2, 8] call comspec_atak_native_fnc_gridRef] };
            case (_tgt isEqualType objNull): { format ["le point laser (%1)", [_tgt, 8] call comspec_atak_native_fnc_gridRef] };
            default { format ["la grille %1", [_tgt, 8] call comspec_atak_native_fnc_gridRef] };
        };
        ["WARNING", format ["Frappe lancée sur %1", _what]] call _say;
        [format ["Frappe lancée sur %1 (%2)", _what, _d getVariable ["COMSPEC_DroneArmedLabel", "charge"]], "#e5483a"] call _log;
        call _render;
    };
    case "cam": {
        if (!isNull (missionNamespace getVariable ["COMSPEC_ATAK_DroneCam", objNull])) then { call _camClose; } else { call _camOpen; };
        call _render;
    };
    case "zoom": {
        missionNamespace setVariable ["COMSPEC_ATAK_DroneZoom", _arg];
        private _cam = missionNamespace getVariable ["COMSPEC_ATAK_DroneCam", objNull];
        if (!isNull _cam) then { _cam camSetFov (0.7 / _arg); _cam camCommit 0; };
        call _render;
    };
    case "vision": {
        // Jour → nuit (intensification) → thermique → jour.
        private _v = ((missionNamespace getVariable ["COMSPEC_ATAK_DroneVision", 0]) + 1) mod 3;
        missionNamespace setVariable ["COMSPEC_ATAK_DroneVision", _v];
        "comspec_dronecam" setPiPEffect [_v];
        call _render;
    };
    case "mapSub": { _s set ["droneMapSub", _arg]; _s set ["droneMapPts", []]; call _mapRender; };
    case "routeMode": { _s set ["droneRouteMode", _arg]; call _mapRender; };
    case "zoneKind": { _s set ["droneZoneKind", _arg]; _s set ["droneMapPts", []]; call _mapRender; };
    case "mapClear": { _s set ["droneMapPts", []]; call _mapRender; };
    case "mapClick": {
        private _p = [_arg select 0, _arg select 1, getTerrainHeightASL [_arg select 0, _arg select 1]];
        private _pts = _s getOrDefault ["droneMapPts", []];
        switch (_s getOrDefault ["droneMapSub", "GOTO"]) do {
            case "ROUTE": {
                if ((count _pts) >= 12) exitWith { ["WARNING", "Route : 12 points au plus"] call _say; };
                _pts pushBack _p;
                _s set ["droneMapPts", _pts];
            };
            case "ZONE": {
                private _kind = _s getOrDefault ["droneZoneKind", "LOITER"];
                if ((count _pts) isEqualTo 0) exitWith { _s set ["droneMapPts", [_p]]; };
                private _a = _pts select 0;
                _s set ["droneMapPts", []];
                if (isNull _d || {!(call _needLink)}) exitWith {};
                if (_kind isEqualTo "HUNT") then {
                    // Zone de recherche rectangulaire : deux coins opposés.
                    private _c = [((_a select 0) + (_p select 0)) / 2, ((_a select 1) + (_p select 1)) / 2];
                    _c pushBack (getTerrainHeightASL _c);
                    private _hx = (abs ((_p select 0) - (_a select 0)) / 2) max 25;
                    private _hy = (abs ((_p select 1) - (_a select 1)) / 2) max 25;
                    ["hunt", [((_hx min _hy) * 0.7) max 50, player, _c, [_c, _hx, _hy, 0, true]]] call _send;
                    [format ["Ordre : recherche sur zone %1 × %2 m en %3", round (_hx * 2), round (_hy * 2), [_c, 8] call comspec_atak_native_fnc_gridRef]] call _log;
                } else {
                    private _r = ((_a distance2D _p) max 30) min 800;
                    [["loiter", "observe"] select (_kind isEqualTo "OBSERVE"), [_a, round _r]] call _send;
                    if (_kind isEqualTo "OBSERVE") then { missionNamespace setVariable ["COMSPEC_ATAK_DroneCamTgt", ""]; };
                    [format ["Ordre : %1 en %2, rayon %3 m (carte)", ["orbite", "observer"] select (_kind isEqualTo "OBSERVE"), [_a, 8] call comspec_atak_native_fnc_gridRef, round _r]] call _log;
                };
            };
            default {
                if (isNull _d || {!(call _needLink)}) exitWith {};
                ["goto", [_p]] call _send;
                [format ["Ordre : aller en %1 (carte)", [_p, 8] call comspec_atak_native_fnc_gridRef]] call _log;
            };
        };
        call _mapRender;
    };
    case "routeSend": {
        private _pts = _s getOrDefault ["droneMapPts", []];
        if ((count _pts) isEqualTo 0) exitWith { ["WARNING", "Route vide : touchez la carte pour poser des points"] call _say; };
        if (isNull _d || {!(call _needLink)}) exitWith {};
        private _rm = _s getOrDefault ["droneRouteMode", "ONCE"];
        ["route", [+_pts, _rm]] call _send;
        [format ["Ordre : route de %1 points, %2", count _pts, (createHashMapFromArray [["ONCE", "une fois"], ["LOOP", "en boucle"], ["PINGPONG", "en aller-retour"]]) get _rm]] call _log;
        _s set ["droneMapPts", []];
        call _mapRender;
    };
    case "mapBar": {
        // Barre DRONE posée sur la carte : état, sous-outils, mode de route, validation.
        disableSerialization;
        _arg params ["_x0", "_y0", "_w"];
        if (isNull _d) exitWith {};
        private _fs = ([] call comspec_atak_native_fnc_layoutGet) get "fontSmall";
        private _pad = (([] call comspec_atak_native_fnc_layoutGet) get "pad") * 2;
        private _mk = { params ["_class", "_pos", ["_text", ""]]; [_class, _pos, _text, false] call comspec_atak_native_fnc_pageCtrl };
        private _ln = call _link;
        private _sub = _s getOrDefault ["droneMapSub", "GOTO"];
        private _pts = _s getOrDefault ["droneMapPts", []];
        private _rh = _fs * 1.5;
        private _rows = [];
        _rows pushBack ([["ALLER ICI", "GOTO"], ["ROUTE", "ROUTE"], ["ZONE", "ZONE"]] apply { [_x select 0, ["mapSub", _x select 1], _sub isEqualTo (_x select 1)] });
        switch (_sub) do {
            case "ROUTE": {
                private _rm = _s getOrDefault ["droneRouteMode", "ONCE"];
                _rows pushBack ([["UNE FOIS", "ONCE"], ["BOUCLE", "LOOP"], ["ALLER-RETOUR", "PINGPONG"]] apply { [_x select 0, ["routeMode", _x select 1], _rm isEqualTo (_x select 1)] });
                _rows pushBack [[format ["VALIDER (%1)", count _pts], ["routeSend"], true], ["EFFACER", ["mapClear"], false]];
            };
            case "ZONE": {
                private _zk = _s getOrDefault ["droneZoneKind", "LOITER"];
                _rows pushBack ([["ORBITE", "LOITER"], ["OBSERVER", "OBSERVE"], ["RECHERCHE", "HUNT"]] apply { [_x select 0, ["zoneKind", _x select 1], _zk isEqualTo (_x select 1)] });
            };
        };
        _rows pushBack [["STATIONNAIRE", ["mode", "hover"], false], ["RETOUR", ["mode", "home"], false]];
        private _h = _rh * 1.1 + (count _rows) * (_rh + _pad / 3) + _pad / 2;
        private _bg = ["COMSPEC_RscMapPanel", [_x0, _y0, _w, _h]] call _mk;
        _bg ctrlSetBackgroundColor [0.025, 0.030, 0.027, 0.92];
        private _st = ["COMSPEC_RscStructuredText", [_x0 + _pad / 2, _y0 + _pad / 4, _w - _pad, _rh * 1.1]] call _mk;
        _st ctrlSetStructuredText parseText format ["<t size='0.85'><t font='RobotoCondensedBold' color='#5cc76b'>DRONE</t>  %1 · %2 · H %3 m%4</t>",
            [_d getVariable ["COMSPEC_DroneMode", "HOVER"]] call _modeName,
            ["<t color='#e5483a'>hors liaison</t>", format ["liaison %1/4", _ln select 0]] select (_ln select 2),
            round ((getPosATL _d) select 2), ["", " · CQB"] select (_d getVariable ["COMSPEC_DroneCqb", false])];
        private _y = _y0 + _rh * 1.1 + _pad / 4;
        {
            private _row = _x;
            private _bw = (_w - _pad) / (count _row);
            {
                _x params ["_lbl", "_a", "_on"];
                private _b = ["COMSPEC_RscButton", [_x0 + _pad / 2 + _forEachIndex * _bw, _y, _bw - _pad / 4, _rh], _lbl] call _mk;
                _b ctrlSetFontHeight (_fs * 0.82);
                if (_on) then { _b ctrlSetBackgroundColor [0.36, 0.78, 0.42, 0.95]; _b ctrlSetTextColor [0.03, 0.05, 0.04, 1]; };
                _b ctrlAddEventHandler ["ButtonClick", compile format ["%1 call comspec_atak_native_fnc_droneAction;", str _a]];
            } forEach _row;
            _y = _y + _rh + _pad / 3;
        } forEach _rows;
    };
    case "camAim": {
        if (isNull _d) exitWith {};
        missionNamespace setVariable ["COMSPEC_ATAK_DroneCamTgt", _arg];
        if (_arg isNotEqualTo "") then { call _camOpen; };
        call _render;
    };
    case "tab": { call _saveGrid; _s set ["droneTab", _arg]; call _render; };
    case "task": { call _saveGrid; _s set ["droneTask", _arg]; call _render; };
    case "taskRad": { call _saveGrid; _s set ["droneTaskRad", _arg]; call _render; };
    case "pickMap": {
        call _saveGrid;
        _s set ["dronePick", "1"];
        [{ ["MAP"] call comspec_atak_native_fnc_navigate; }] call CBA_fnc_execNextFrame;
        ["INFO", "Touchez le point de la tâche sur la carte", 4, 40] call comspec_atak_native_fnc_notify;
    };
    case "picked": {
        // Appelé par la carte (fn_mapMouseButtonDown) avec la position touchée : la grille à 10 chiffres remplit le champ.
        _s set ["droneTaskGridVal", [_arg, 10] call comspec_atak_native_fnc_gridRef];
        _s set ["droneTab", "TASK"];
        ["INFO", format ["Point de la tâche : %1", [_arg, 8] call comspec_atak_native_fnc_gridRef], 3, 30] call comspec_atak_native_fnc_notify;
        [{ ["DRONE"] call comspec_atak_native_fnc_navigate; }] call CBA_fnc_execNextFrame;
    };
    case "taskSend": {
        call _saveGrid;
        if (isNull _d || {!(call _needLink)}) exitWith {};
        private _p = [_s getOrDefault ["droneTaskGridVal", ""]] call _gridPos;
        if (_p isEqualTo []) exitWith { ["WARNING", "Point invalide : saisissez une grille (8 ou 10 chiffres) ou choisissez-le sur la carte"] call _say; };
        private _task = _s getOrDefault ["droneTask", "GOTO"];
        private _rad = _s getOrDefault ["droneTaskRad", 150];
        switch (_task) do {
            case "LOITER": { ["loiter", [_p, _rad]] call _send; };
            case "OBSERVE": { ["observe", [_p, _rad]] call _send; call _camOpen; missionNamespace setVariable ["COMSPEC_ATAK_DroneCamTgt", ""]; };
            default { ["goto", [_p]] call _send; };
        };
        private _g = [_p, 8] call comspec_atak_native_fnc_gridRef;
        [format ["Ordre : %1 en %2%3", (createHashMapFromArray [["GOTO", "aller"], ["LOITER", "orbite"], ["OBSERVE", "observer"]]) getOrDefault [_task, "aller"], _g,
            ["", format [", rayon %1 m", _rad]] select (_task in ["LOITER", "OBSERVE"])]] call _log;
        ["SUCCESS", format ["Tâche envoyée : %1", _g]] call _say;
        call _render;
    };
    case "escort": {
        if (isNull _d || {!(call _needLink)}) exitWith {};
        private _t = allPlayers param [(allPlayers findIf { getPlayerUID _x isEqualTo _arg }), objNull];
        if (isNull _t || {!alive _t}) exitWith { ["WARNING", "Joueur introuvable"] call _say; };
        ["escort", [_t]] call _send;
        [format ["Ordre : escorte de %1%2", name _t, ["", format [" (%1)", getText (configOf vehicle _t >> "displayName")]] select ((vehicle _t) isNotEqualTo _t)]] call _log;
        call _render;
    };
    case "taskClear": {
        _s set ["droneTaskGridVal", ""];
        if (!isNull _d && {(_d getVariable ["COMSPEC_DroneMode", ""]) in ["GOTO", "LOITER", "OBSERVE", "ESCORT"]} && {call _needLink}) then {
            ["hover"] call _send;
            ["Ordre : tâche annulée, stationnaire"] call _log;
        };
        call _render;
    };
    case "trackMark": {
        private _tr = (missionNamespace getVariable ["COMSPEC_ATAK_DroneTracks", createHashMap]) getOrDefault [_arg, []];
        if ((count _tr) isEqualTo 0) exitWith {};
        _tr params ["_o", "_name", "_pos", "", "_clock", "_tag", "", "_num"];
        // Marqueur ordinaire du téléphone (palette réglée le temps de la pose) : camp vu, infanterie ou véhicule.
        private _keys = ["markerAff", "markerType", "markerColor", "markerEditAfter"];
        private _old = _keys apply { _s getOrDefault [_x, "§"] };
        private _aff = ["u", "o", "b"] select (["INCONNU", "ENNEMI", "AMI"] find _tag);
        _s set ["markerAff", _aff];
        _s set ["markerType", format ["%1_%2", _aff, ["motor_inf", "inf"] select (_o isKindOf "CAManBase")]];
        _s set ["markerColor", "AUTO"];
        _s set ["markerEditAfter", false];
        private _m = [_pos] call comspec_atak_native_fnc_markerDrop;
        { if ((_old select _forEachIndex) isEqualTo "§") then { _s deleteAt _x; } else { _s set [_x, _old select _forEachIndex]; }; } forEach _keys;
        if (_m isNotEqualTo "") then {
            _m setMarkerText format ["%1 %2 (drone %3)", ["INC", "ENI", "AMI"] select (["INCONNU", "ENNEMI", "AMI"] find _tag), _name, _clock];
            [format ["Piste %1 marquée sur la carte (%2)", [_num, 2] call CBA_fnc_formatNumber, [_pos, 8] call comspec_atak_native_fnc_gridRef]] call _log;
        };
    };
    case "tracksClear": {
        missionNamespace setVariable ["COMSPEC_ATAK_DroneTracks", createHashMap];
        missionNamespace setVariable ["COMSPEC_ATAK_DroneCamTgt", ""];
        ["Pistes effacées"] call _log;
        call _render;
    };
    case "tick": {
        if (isNull _d) exitWith { ["unpair"] call comspec_atak_native_fnc_droneAction; };
        if (!alive _d) exitWith {
            private _strike = (_d getVariable ["COMSPEC_DroneMode", ""]) isEqualTo "STRIKE";
            ["WARNING", ["Drone perdu", "Drone : impact"] select _strike] call _say;
            if (_strike) then {
                [format ["Impact en %1", [_d getVariable ["COMSPEC_DroneTgt", getPosASL _d], 8] call comspec_atak_native_fnc_gridRef], "#e5483a"] call _log;
            } else {
                [format ["Drone perdu en %1", [missionNamespace getVariable ["COMSPEC_ATAK_DroneLastPos", getPosASL _d], 8] call comspec_atak_native_fnc_gridRef], "#e5483a"] call _log;
            };
            ["unpair"] call comspec_atak_native_fnc_droneAction;
        };
        private _l = call _link;
        private _was = missionNamespace getVariable ["COMSPEC_ATAK_DroneLinked", true];
        missionNamespace setVariable ["COMSPEC_ATAK_DroneLinked", _l select 2];
        private _m = _d getVariable ["COMSPEC_DroneMode", "HOVER"];
        // Liaison perdue : retour automatique au point de décollage (sauf frappe déjà lancée).
        if (_was && {!(_l select 2)}) then {
            ["Liaison perdue", "#e5483a"] call _log;
            if !(_m in ["STRIKE", "RTH", "LAND"]) then {
                ["WARNING", "Drone : liaison perdue, retour au point de décollage"] call _say;
                [] call comspec_atak_native_fnc_vibrate;
                ["rth", [missionNamespace getVariable ["COMSPEC_ATAK_DroneHome", getPosASL player]]] call _send;
            };
        };
        if (!_was && {_l select 2}) then { ["SUCCESS", "Drone : liaison rétablie"] call _say; ["Liaison rétablie", "#5cc76b"] call _log; };
        // Le mode affiché est celui que le drone renvoie : le journal note chaque changement (ordre exécuté, arrivée, repli...).
        private _lm = missionNamespace getVariable ["COMSPEC_ATAK_DroneLastMode", ""];
        if (_m isNotEqualTo _lm) then {
            missionNamespace setVariable ["COMSPEC_ATAK_DroneLastMode", _m];
            if (_lm isNotEqualTo "" && {_l select 2}) then { [format ["Mode : %1", [_m] call _modeName], ["", "#f2ab33"] select (_m in ["RTH", "STRIKE"])] call _log; };
        };
        // Recherche : ennemi repéré ou engagé, renvoyé par le drone.
        private _ev = _d getVariable ["COMSPEC_DroneEvt", [0]];
        if ((_ev select 0) > (missionNamespace getVariable ["COMSPEC_ATAK_DroneEvtN", 0])) then {
            missionNamespace setVariable ["COMSPEC_ATAK_DroneEvtN", _ev select 0];
            _ev params ["", "_kind", "_pos", "_what"];
            if (_kind isEqualTo "OBSTACLE") exitWith {
                ["WARNING", "Drone : obstacle devant, arrêt en stationnaire"] call _say;
                [format ["CQB : obstacle devant en %1, arrêt en stationnaire", [_pos, 8] call comspec_atak_native_fnc_gridRef], "#f2ab33"] call _log;
            };
            [format ["Recherche : %1 %2 en %3", ["ennemi repéré,", "engagement de"] select (_kind isEqualTo "ENGAGE"), _what, [_pos, 8] call comspec_atak_native_fnc_gridRef], "#e5483a"] call _log;
        };
        // Batterie : alerte à 20 %, retour automatique à 10 % (comme un DJI), une seule fois par seuil.
        private _fu = fuel _d;
        private _warn = missionNamespace getVariable ["COMSPEC_ATAK_DroneFuelWarn", 0];
        if (_fu > 0.25 && {_warn > 0}) then { missionNamespace setVariable ["COMSPEC_ATAK_DroneFuelWarn", 0]; _warn = 0; };
        if (_fu <= 0.2 && {_warn < 1}) then {
            missionNamespace setVariable ["COMSPEC_ATAK_DroneFuelWarn", 1];
            ["WARNING", format ["Drone : batterie faible (%1 %%)", round (100 * _fu)]] call _say;
            [] call comspec_atak_native_fnc_vibrate;
            [format ["Batterie faible : %1 %%", round (100 * _fu)], "#f2ab33"] call _log;
        };
        if (_fu <= 0.1 && {_warn < 2} && {((getPosATL _d) select 2) > 2}) then {
            missionNamespace setVariable ["COMSPEC_ATAK_DroneFuelWarn", 2];
            if !(_m in ["STRIKE", "RTH", "LAND"]) then {
                ["WARNING", "Drone : batterie critique, retour au point de décollage"] call _say;
                [] call comspec_atak_native_fnc_vibrate;
                ["rth", [missionNamespace getVariable ["COMSPEC_ATAK_DroneHome", getPosASL player]]] call _send;
                [format ["Batterie critique (%1 %%) : retour automatique", round (100 * _fu)], "#e5483a"] call _log;
            };
        };
        // Capteur : seulement en vol et avec le retour vidéo.
        if ((_l select 2) && {((getPosATL _d) select 2) > 3}) then { call _scan; };
        private _osd = uiNamespace getVariable ["COMSPEC_ATAK_DroneOsd", controlNull];
        if (!isNull _osd) then { _osd ctrlSetStructuredText parseText ([_d, _l] call comspec_atak_native_fnc_droneOsd); };
        private _feed = uiNamespace getVariable ["COMSPEC_ATAK_DroneFeedTxt", []];
        if ((count _feed) isEqualTo 5 && {!isNull (_feed select 0)}) then {
            private _t = [_d, _l, "FEED"] call comspec_atak_native_fnc_droneOsd;
            { _x ctrlSetStructuredText parseText (_t select _forEachIndex); } forEach _feed;
        };
        // Onglets PISTES et JOURNAL : redessinés quand une ligne arrive.
        private _sig = [count (missionNamespace getVariable ["COMSPEC_ATAK_DroneLog", []]), count (missionNamespace getVariable ["COMSPEC_ATAK_DroneTracks", createHashMap])];
        if (_sig isNotEqualTo (missionNamespace getVariable ["COMSPEC_ATAK_DroneSig", []])) then {
            missionNamespace setVariable ["COMSPEC_ATAK_DroneSig", _sig];
            if ((_s getOrDefault ["droneTab", "PILOT"]) in ["TRACKS", "LOG"]) then { call _render; };
        };
    };
};
