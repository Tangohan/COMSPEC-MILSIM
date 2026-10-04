/*
    Mode drone : ordre exécuté là où le drone est local (événement CBA comspec_atak_native_droneCmd,
    envoyé par fn_droneAction avec CBA_fnc_targetEvent sur le drone).
    Params : [drone, ordre, arguments]
      "takeoff" []                          démarre les moteurs et décolle, puis stationnaire à l'altitude choisie
                                            (tout ordre de vol donné au sol passe d'abord par cette séquence) ;
      "hover"   []                          stationnaire à l'altitude choisie ;
      "follow"  [cible, altitude, km/h]     suit une unité (le pilote ou un autre joueur), 15 m derrière ;
      "home"    [pilote, altitude, km/h]    revient au pilote puis se pose à côté ;
      "land"    []                          se pose sur place ;
      "manual"  []                          rend la main au terminal UAV du pilote : le drone reste en stationnaire ;
      "rth"     [position ASL]              liaison perdue : retour au point de décollage et atterrissage ;
      "alt"     [altitude] / "speed" [km/h] réglages en vol ;
      "cqb"     [actif, altitude, km/h]     profil CQB (2 à 8 m, 5 à 15 km/h) : vol bas piloté par une boucle à nous
                                            (vitesse imposée, hauteur tenue au-dessus du sol ou du plancher, arrêt devant
                                            un obstacle à moins de 2 m) ; stationnaire, suivi (5 m derrière, hauteur d'homme),
                                            escorte, aller et retour. Orbite, observation, recherche et frappe en sortent ;
                                            liaison perdue en CQB : le drone se pose sur place (pas de remontée sous un toit).
      "strike"  [cible (position ASL ou objet), pilote]   tir et oublie : approche puis piqué final ;
      "hunt"    [rayon, pilote]             recherche : orbite et premier ennemi vu, frappé si armé, signalé sinon ;
      "goto"    [position ASL]              tâche ALLER : rejoint le point puis passe en stationnaire ;
      "loiter"  [position ASL, rayon]       tâche ORBITE : tourne autour du point au rayon donné ;
      "observe" [position ASL, rayon]       tâche OBSERVER : même orbite, la caméra du pilote reste pointée sur le point ;
      "escort"  [unité]                     tâche ESCORTE : suit l'unité (ou son véhicule) 30 m en arrière ;
      "route"   [[positions ASL...], "ONCE" | "LOOP" | "PINGPONG"]   route tracée sur la carte : une fois puis
                                            stationnaire, en boucle, ou en aller-retour (CQB respecté).
    "hunt" accepte aussi [rayon, pilote, centre ASL, zone inArea] : recherche sur une zone tracée sur la carte.
      "standby" ["LAND" | "HOVER"]          veille : se pose (moteurs coupés une fois au sol) ou tient le stationnaire ;
                                            consommation réduite (2 %/h posé, 10 %/h en stationnaire), pas de capteur ;
                                            n'importe quel ordre de vol l'en sort (le téléphone renvoie l'ordre d'avant) ;
      "force"   [ordre, arguments]          FORCER L'EXÉCUTION : équipage IA recréé s'il manque, points de passage du groupe
                                            effacés, déplacement IA réactivé, altitude et vitesse réappliquées, puis l'ordre
                                            est rejoué normalement (il refait son doMove).
    Ordres traités ailleurs que là où le drone est local (même événement, avant le contrôle de localité) :
      "relocal" [ordre, arguments]          sur le serveur seulement : si l'équipage IA n'est pas local à la même machine que
                                            le drone, le groupe y est déplacé (setGroupOwner), puis "force" y est renvoyé ;
      "transferIn" [nom, uid cible, point de décollage ASL, "HELLO" | "DONE"]   sur le client du joueur qui reçoit le drone
                                            (événement visé sur son unité) : "HELLO" annonce la liaison, "DONE" appaire le drone
                                            chez lui (fn_droneAction "transferIn").
    Variables publiques du drone : COMSPEC_DroneMode, COMSPEC_DroneAlt, COMSPEC_DroneSpd, COMSPEC_DroneArmed (munition),
    COMSPEC_DroneSide (camp du pilote), COMSPEC_DroneCqb (profil CQB), COMSPEC_DroneTgt (position ASL visée, pour la carte),
    COMSPEC_DroneTask ([tâche, position ASL, rayon] de la tâche en cours, [] sinon),
    COMSPEC_DroneRoute ([positions, mode, étape en cours] de la route, pour la carte),
    COMSPEC_DroneEvt ([n°, type, position ASL, quoi] : dernier événement de la recherche, lu par le journal du pilote),
    COMSPEC_DroneAckN (compteur d'ordres traités), COMSPEC_DroneStandby ("LAND" | "HOVER" en veille), COMSPEC_DroneForced (time du
    dernier FORCER L'EXÉCUTION). Posées par le téléphone : COMSPEC_DroneOwner, COMSPEC_DroneLock, COMSPEC_DroneName, COMSPEC_DroneIcon.
*/
params [["_d", objNull], ["_cmd", "hover"], ["_args", []]];
// Transfert d'appairage : reçu sur le client du nouveau pilote (l'événement vise son unité, pas le drone).
if (_cmd isEqualTo "transferIn") exitWith {
    _args params [["_from", ""], ["_uid", ""], ["_home", []], ["_stage", "DONE"]];
    if (!hasInterface || {isNull _d} || {!alive _d} || {getPlayerUID player isNotEqualTo _uid}) exitWith {};
    ["transferIn", [_d, _from, _home, _stage]] call comspec_atak_native_fnc_droneAction;
};
// Localité : drone et équipage IA sur deux machines différentes (terminal UAV connecté puis lâché, joueur parti...).
if (_cmd isEqualTo "relocal") exitWith {
    if (!isServer || {isNull _d} || {!alive _d}) exitWith {};
    private _drv = driver _d;
    if (isNull _drv || {(groupOwner group _drv) isEqualTo (owner _d)}) exitWith {};
    (group _drv) setGroupOwner (owner _d);
    [{ ["comspec_atak_native_droneCmd", [_this select 0, "force", _this select 1], _this select 0] call CBA_fnc_targetEvent; }, [_d, _args], 1.5] call CBA_fnc_waitAndExecute;
};
if (isNull _d || {!alive _d} || {!local _d}) exitWith {};
// Accusé de réception pour la surveillance du téléphone (ordre resté sans effet → reprise).
_d setVariable ["COMSPEC_DroneAckN", (_d getVariable ["COMSPEC_DroneAckN", 0]) + 1, true];
private _alt = _d getVariable ["COMSPEC_DroneAlt", 40];
private _spd = _d getVariable ["COMSPEC_DroneSpd", 40];
private _setMode = { params ["_m"]; _d setVariable ["COMSPEC_DroneMode", _m, true]; _d setVariable ["COMSPEC_DroneTask", [], true]; _d setVariable ["COMSPEC_DroneRoute", [], true]; _d setVariable ["COMSPEC_DroneLoop", (_d getVariable ["COMSPEC_DroneLoop", 0]) + 1]; };
private _fly = {
    params ["_pos"];
    _d flyInHeight [_d getVariable ["COMSPEC_DroneAlt", 40], true];
    _d limitSpeed (_d getVariable ["COMSPEC_DroneSpd", 40]);
    _d doMove (ASLToAGL _pos);
};
// Route : étape suivante selon le mode (une fois, boucle, aller-retour) ; -1 quand la route est finie.
private _routeNext = {
    params ["_i", "_n", "_mode", "_dir"];
    switch (_mode) do {
        case "LOOP": { [(_i + 1) mod _n, 1] };
        case "PINGPONG": {
            if (_n < 2) exitWith { [0, 1] };
            if ((_i + _dir) >= _n || {(_i + _dir) < 0}) then { _dir = -_dir; };
            [_i + _dir, _dir]
        };
        default { [[_i + 1, -1] select ((_i + 1) >= _n), 1] };
    };
};
// Équipage IA (comme un terminal qui prend le drone), dans le camp du pilote.
private _crew = {
    if (!isNull driver _d && {alive driver _d}) exitWith {};
    { if (!alive _x) then { _d deleteVehicleCrew _x; }; } forEach (crew _d);
    createVehicleCrew _d;
    private _side = _d getVariable ["COMSPEC_DroneSide", sideUnknown];
    if (_side in [west, east, independent] && {(side group driver _d) isNotEqualTo _side}) then { (crew _d) joinSilent (createGroup [_side, true]); };
};
// Démarrage : IA de vol, moteurs, montée franche, puis l'ordre suit son cours.
private _start = {
    if (((getPosATL _d) select 2) > 1 && {isEngineOn _d}) exitWith { false };
    call _crew;
    _d land "NONE";
    _d engineOn true;
    _d flyInHeight [_d getVariable ["COMSPEC_DroneAlt", 40], true];
    _d spawn {
        for "_i" from 1 to 60 do {
            if (!alive _this || {((getPosATL _this) select 2) > 3}) exitWith {};
            _this setVelocityModelSpace [0, 0, 3];
            sleep 0.05;
        };
    };
    true
};
// Charge militaire : munition créée à l'impact et déclenchée aussitôt.
private _detonate = {
    private _ammo = _d getVariable ["COMSPEC_DroneArmed", ""];
    if (_ammo isNotEqualTo "") then {
        private _a = createVehicle [_ammo, ASLToAGL (getPosASL _d), [], 0, "CAN_COLLIDE"];
        triggerAmmo _a;
    };
    _d setVariable ["COMSPEC_DroneArmed", "", true];
    _d setDamage 1;
};
// FORCER L'EXÉCUTION : on remet l'IA de vol d'aplomb, puis l'ordre est rejoué comme s'il arrivait.
if (_cmd isEqualTo "force") then {
    _args params [["_fc", "hover"], ["_fa", []]];
    call _crew;
    private _g = group driver _d;
    if (!isNull _g) then {
        for "_i" from ((count waypoints _g) - 1) to 0 step -1 do { deleteWaypoint [_g, _i]; };
        { _x enableAI "MOVE"; _x enableAI "PATH"; } forEach (crew _d);
        // Une IA en alerte se met à esquiver au lieu d'obéir : comportement « sans souci », comme un terminal UAV.
        _g setBehaviour "CARELESS";
    };
    _d land "NONE";
    if (((getPosATL _d) select 2) > 1) then { _d engineOn true; };
    _d flyInHeight [_alt, true];
    _d limitSpeed _spd;
    _d doMove (getPosATL _d);
    _d setVariable ["COMSPEC_DroneForced", time, true];
    _cmd = _fc;
    _args = _fa;
};
// Veille quittée par un ordre de vol : consommation normale.
if (_cmd in ["takeoff", "hover", "follow", "home", "land", "hunt", "goto", "loiter", "observe", "escort", "route", "rth", "strike", "manual"]) then { _d setVariable ["COMSPEC_DroneStandby", nil, true]; };
// Ordre de vol donné au sol : décollage d'abord (le retour au pilote d'un drone déjà posé ne le fait pas redécoller).
if (_cmd in ["takeoff", "hover", "follow", "escort", "goto", "loiter", "observe", "strike", "hunt", "route"]) then { call _start; };
// Profil CQB : l'IA de vol ne sait pas passer une porte, notre boucle prend la main ; les ordres « hauts » en sortent.
private _cqb = _d getVariable ["COMSPEC_DroneCqb", false];
if (_cqb && {_cmd in ["loiter", "observe", "hunt", "strike"]}) then {
    _cqb = false;
    _d setVariable ["COMSPEC_DroneCqb", false, true];
    _d setVariable ["COMSPEC_DroneAlt", 30, true];
    _d setVariable ["COMSPEC_DroneSpd", 40, true];
};
if (_cqb && {_cmd isEqualTo "rth"}) then { _cmd = "land"; };
if (!isNull driver _d) then { if (_cqb && {_cmd in ["takeoff", "hover", "follow", "escort", "goto", "home", "route"]}) then { (driver _d) disableAI "MOVE"; } else { (driver _d) enableAI "MOVE"; }; };
if (_cqb && {_cmd in ["takeoff", "hover", "follow", "escort", "goto", "home", "route"]}) exitWith {
    private _t = _args param [0, objNull];
    private _rmode = _args param [1, "ONCE"];
    if (_cmd isEqualTo "route" && {!(_t isEqualType []) || {(count _t) isEqualTo 0}}) exitWith {};
    if (_cmd in ["follow", "escort", "home"] && {!(_t isEqualType objNull) || {isNull _t}}) exitWith {};
    if (_cmd isEqualTo "home" && {((getPosATL _d) select 2) < 1}) exitWith {};
    private _kind = switch (_cmd) do { case "goto": { "GOTO" }; case "home": { "HOME" }; case "route": { "ROUTE" }; case "follow"; case "escort": { "FOLLOW" }; default { "HOLD" }; };
    [["HOVER", "FOLLOW", "HOME", "ESCORT", "GOTO", "ROUTE"] select ((["hover", "follow", "home", "escort", "goto", "route"] find _cmd) max 0)] call _setMode;
    if (_cmd isEqualTo "route") then { _d setVariable ["COMSPEC_DroneRoute", [_t, _rmode, 0], true]; };
    if (_kind isEqualTo "FOLLOW") then { _d setVariable ["COMSPEC_DroneFollow", _t, true]; };
    if (_cmd isEqualTo "escort") then { _d setVariable ["COMSPEC_DroneTask", ["ESCORT", getPosASL _t, 5], true]; };
    if (_cmd isEqualTo "goto") then { _d setVariable ["COMSPEC_DroneTask", ["GOTO", _t, 0], true]; };
    private _goal = switch (_kind) do { case "HOLD": { getPosASL _d }; case "ROUTE": { _t select 0 }; default { _t }; };
    private _loop = _d getVariable ["COMSPEC_DroneLoop", 0];
    doStop _d;
    [_d, _goal, _kind, _loop, [_t, []] select (_kind isNotEqualTo "ROUTE"), _rmode, _routeNext] spawn {
        params ["_d", "_goal", "_kind", "_loop", "_route", "_rmode", "_routeNext"];
        private _blocked = 0;
        private _ri = 0;
        private _rdir = 1;
        private _ign = if (_goal isEqualType objNull) then { vehicle _goal } else { objNull };
        while { alive _d && {(_d getVariable ["COMSPEC_DroneLoop", 0]) isEqualTo _loop} } do {
            private _pos = getPosASL _d;
            // Point visé : la cible suivie (5 m derrière elle, à hauteur d'homme) ou le point fixe.
            private _g = if (_goal isEqualType objNull) then {
                if (!alive _goal) then { _pos } else {
                    private _dir = getDir (vehicle _goal);
                    private _off = [5, 0] select (_kind isEqualTo "HOME");
                    (getPosASL _goal) vectorAdd [-_off * sin _dir, -_off * cos _dir, 0]
                }
            } else { _goal };
            private _h = [(_g select 0) - (_pos select 0), (_g select 1) - (_pos select 1), 0];
            private _dist = vectorMagnitude _h;
            if (_kind isEqualTo "HOME" && {_dist < 4}) exitWith { _d setVariable ["COMSPEC_DroneMode", "LAND", true]; (driver _d) enableAI "MOVE"; _d land "LAND"; };
            if (_kind isEqualTo "GOTO" && {_dist < 1}) exitWith { [_d, "hover", []] call comspec_atak_native_fnc_droneCmd; };
            if (_kind isEqualTo "ROUTE" && {_dist < 1}) then {
                ([_ri, count _route, _rmode, _rdir] call _routeNext) params ["_ni", "_nd"];
                _ri = _ni; _rdir = _nd;
                if (_ri >= 0) then { _goal = _route select _ri; _d setVariable ["COMSPEC_DroneRoute", [_route, _rmode, _ri], true]; };
            };
            if (_ri < 0) exitWith { [_d, "hover", []] call comspec_atak_native_fnc_droneCmd; };
            // Hauteur tenue au-dessus de ce qu'il y a dessous (sol, plancher, toit), jamais au-delà d'un plafond.
            private _down = lineIntersectsSurfaces [_pos, _pos vectorAdd [0, 0, -30], _d, _ign, true, 1];
            private _floor = if ((count _down) > 0) then { ((_down select 0) select 0) select 2 } else { getTerrainHeightASL _pos };
            private _vz = ((_floor + (_d getVariable ["COMSPEC_DroneAlt", 3]) - (_pos select 2)) * 1.5) max -1.5 min 1.5;
            if ((count (lineIntersectsSurfaces [_pos, _pos vectorAdd [0, 0, 0.8], _d, _ign, true, 1])) > 0) then { _vz = _vz min -0.3; };
            private _v = [0, 0, 0];
            if (_dist > 0.4) then {
                private _n = vectorNormalized _h;
                // Approche lente et précise : la vitesse décroît dans les derniers mètres.
                _v = _n vectorMultiply (((_d getVariable ["COMSPEC_DroneSpd", 10]) / 3.6) min (_dist * 0.6));
                // Obstacle à moins de 2 m devant (plus loin quand on va vite) : on s'arrête, jamais à travers un mur.
                if ((count (lineIntersectsSurfaces [_pos, _pos vectorAdd (_n vectorMultiply (2 + 0.5 * vectorMagnitude _v)), _d, _ign, true, 1])) > 0) then {
                    _v = [0, 0, 0];
                    _blocked = _blocked + 1;
                } else { _blocked = 0; };
            } else { _blocked = 0; };
            // Bloqué plus de 3 s : stationnaire et alerte au pilote.
            if (_blocked > 60) exitWith {
                _d setVariable ["COMSPEC_DroneEvt", [((_d getVariable ["COMSPEC_DroneEvt", [0]]) select 0) + 1, "OBSTACLE", getPosASL _d, "obstacle devant"], true];
                [_d, "hover", []] call comspec_atak_native_fnc_droneCmd;
            };
            // Nez vers la cible suivie (caméra sur elle), sinon dans le sens de la marche.
            private _look = if (_goal isEqualType objNull && {alive _goal}) then { _pos getDir _goal } else { [getDir _d, (_h select 0) atan2 (_h select 1)] select (_dist > 1) };
            _d setVectorDirAndUp [[sin _look, cos _look, 0], [0, 0, 1]];
            _d setVelocity [_v select 0, _v select 1, _vz];
            sleep 0.05;
        };
    };
};
switch (_cmd) do {
    case "cqb": {
        _args params [["_on", false], ["_a", 3], ["_v", 10]];
        _d setVariable ["COMSPEC_DroneCqb", _on, true];
        _d setVariable ["COMSPEC_DroneAlt", _a, true];
        _d setVariable ["COMSPEC_DroneSpd", _v, true];
        // En vol : on repart en stationnaire dans le nouveau profil (remontée prudente en quittant le CQB).
        if (((getPosATL _d) select 2) > 1) then { [_d, "hover", []] call comspec_atak_native_fnc_droneCmd; };
    };
    case "alt": { _d setVariable ["COMSPEC_DroneAlt", _args param [0, 40], true]; _d flyInHeight [_args param [0, 40], true]; };
    case "speed": { _d setVariable ["COMSPEC_DroneSpd", _args param [0, 40], true]; _d limitSpeed (_args param [0, 40]); };
    case "takeoff";
    case "hover": {
        ["HOVER"] call _setMode;
        doStop _d;
        _d flyInHeight [_alt, true];
        _d doMove (getPosATL _d);
    };
    case "land": { ["LAND"] call _setMode; _d land "LAND"; };
    case "standby": {
        _args params [["_kind", "LAND"]];
        if !(_kind in ["LAND", "HOVER"]) then { _kind = "LAND"; };
        ["STANDBY"] call _setMode;
        _d setVariable ["COMSPEC_DroneStandby", _kind, true];
        if (!isNull driver _d) then { (driver _d) enableAI "MOVE"; };
        if (_kind isEqualTo "LAND") then { _d land "LAND"; } else { doStop _d; _d flyInHeight [_alt, true]; _d doMove (getPosATL _d); };
        private _loop = _d getVariable ["COMSPEC_DroneLoop", 0];
        [_d, _kind, _loop] spawn {
            params ["_d", "_kind", "_loop"];
            // Consommation de veille imposée : la batterie ne descend qu'au rythme de la veille, pas à celui du vol.
            private _f0 = fuel _d;
            private _t0 = time;
            private _rate = [0.10, 0.02] select (_kind isEqualTo "LAND");
            while { alive _d && {(_d getVariable ["COMSPEC_DroneLoop", 0]) isEqualTo _loop} } do {
                // Posé : moteurs coupés une fois au sol.
                if (_kind isEqualTo "LAND" && {isEngineOn _d} && {((getPosATL _d) select 2) < 0.6} && {(speed _d) < 2}) then { _d engineOn false; };
                _d setFuel ((_f0 - _rate * (time - _t0) / 3600) max 0);
                sleep 5;
            };
        };
    };
    case "manual": { ["MANUAL"] call _setMode; doStop _d; _d flyInHeight [_alt, true]; };
    case "rth": {
        _args params [["_home", getPosASL _d]];
        ["RTH"] call _setMode;
        private _loop = _d getVariable ["COMSPEC_DroneLoop", 0];
        [_d, _home, _loop] spawn {
            params ["_d", "_home", "_loop"];
            while { alive _d && {(_d getVariable ["COMSPEC_DroneLoop", 0]) isEqualTo _loop} } do {
                if ((_d distance2D _home) < 15) exitWith { _d land "LAND"; };
                _d flyInHeight [(_d getVariable ["COMSPEC_DroneAlt", 40]) max 40, true];
                _d doMove (ASLToAGL _home);
                sleep 2;
            };
        };
    };
    case "follow";
    case "escort";
    case "home": {
        _args params [["_t", objNull]];
        if (isNull _t) exitWith {};
        if (_cmd isEqualTo "home" && {((getPosATL _d) select 2) < 1}) exitWith {};
        [["FOLLOW", "HOME", "ESCORT"] select (["follow", "home", "escort"] find _cmd)] call _setMode;
        _d setVariable ["COMSPEC_DroneFollow", _t, true];
        if (_cmd isEqualTo "escort") then { _d setVariable ["COMSPEC_DroneTask", ["ESCORT", getPosASL _t, 30], true]; };
        private _loop = _d getVariable ["COMSPEC_DroneLoop", 0];
        [_d, _t, _loop, _cmd] spawn {
            params ["_d", "_t", "_loop", "_cmd"];
            // Escorte : plus loin en arrière, pour voir le convoi et ce qui l'attend devant.
            private _off = [15, 30] select (_cmd isEqualTo "escort");
            while { alive _d && {alive _t} && {(_d getVariable ["COMSPEC_DroneLoop", 0]) isEqualTo _loop} } do {
                private _dir = getDir (vehicle _t);
                private _p = (getPosASL _t) vectorAdd [-_off * sin _dir, -_off * cos _dir, 0];
                if (_cmd isEqualTo "home" && {(_d distance2D _t) < 25}) exitWith { _d setVariable ["COMSPEC_DroneMode", "LAND", true]; _d land "LAND"; };
                _d flyInHeight [_d getVariable ["COMSPEC_DroneAlt", 40], true];
                _d limitSpeed (_d getVariable ["COMSPEC_DroneSpd", 40]);
                _d doMove (ASLToAGL _p);
                sleep 1;
            };
        };
    };
    case "strike": {
        _args params [["_tgt", []], ["_pilot", objNull]];
        if ((_d getVariable ["COMSPEC_DroneArmed", ""]) isEqualTo "") exitWith {};
        ["STRIKE"] call _setMode;
        private _loop = _d getVariable ["COMSPEC_DroneLoop", 0];
        [_d, _tgt, _loop, _detonate, _pilot] spawn {
            params ["_d", "_tgt", "_loop", "_detonate", "_pilot"];
            // Tir et oublie : un point laser éteint en route laisse la dernière position connue ; une cible détruite arrête la frappe.
            private _last = if (_tgt isEqualType objNull) then { getPosASL _tgt } else { _tgt };
            private _isUnit = _tgt isEqualType objNull && {_tgt isKindOf "AllVehicles"};
            while { alive _d && {(_d getVariable ["COMSPEC_DroneLoop", 0]) isEqualTo _loop} } do {
                if (_isUnit && {!alive _tgt}) exitWith { [_d, "hover", []] call comspec_atak_native_fnc_droneCmd; };
                if (_tgt isEqualType objNull && {!isNull _tgt}) then { _last = [getPosASL _tgt, aimPos _tgt] select _isUnit; };
                private _p = _last;
                _d setVariable ["COMSPEC_DroneTgt", _p, true];
                if ((_d distance2D _p) > 120) then {
                    _d flyInHeight [(_d getVariable ["COMSPEC_DroneAlt", 40]) max 50, true];
                    _d limitSpeed 150;
                    _d doMove (ASLToAGL _p);
                    sleep 1;
                } else {
                    // Piqué final piloté : vitesse dirigée vers la cible, impact ou passage à moins de 3 m.
                    private _v = vectorNormalized (_p vectorDiff (getPosASL _d));
                    _d setVectorDir _v;
                    _d setVelocity (_v vectorMultiply 28);
                    if ((_d distance _p) < 3 || {((getPosATL _d) select 2) < 1.2}) exitWith { call _detonate; };
                    sleep 0.05;
                };
            };
        };
    };
    case "route": {
        _args params [["_pts", []], ["_rmode", "ONCE"]];
        if ((count _pts) isEqualTo 0) exitWith {};
        ["ROUTE"] call _setMode;
        _d setVariable ["COMSPEC_DroneRoute", [_pts, _rmode, 0], true];
        private _loop = _d getVariable ["COMSPEC_DroneLoop", 0];
        [_d, _pts, _rmode, _loop, _routeNext] spawn {
            params ["_d", "_pts", "_rmode", "_loop", "_routeNext"];
            private _i = 0;
            private _dir = 1;
            while { alive _d && {(_d getVariable ["COMSPEC_DroneLoop", 0]) isEqualTo _loop} } do {
                private _p = _pts select _i;
                if ((_d distance2D _p) < 25) then {
                    ([_i, count _pts, _rmode, _dir] call _routeNext) params ["_ni", "_nd"];
                    _i = _ni; _dir = _nd;
                    if (_i >= 0) then { _p = _pts select _i; _d setVariable ["COMSPEC_DroneRoute", [_pts, _rmode, _i], true]; };
                };
                if (_i < 0) exitWith { [_d, "hover", []] call comspec_atak_native_fnc_droneCmd; };
                _d flyInHeight [_d getVariable ["COMSPEC_DroneAlt", 40], true];
                _d limitSpeed (_d getVariable ["COMSPEC_DroneSpd", 40]);
                _d doMove (ASLToAGL _p);
                sleep 1;
            };
        };
    };
    case "hunt": {
        _args params [["_rad", 250], ["_pilot", objNull], ["_center", []], ["_area", []]];
        ["HUNT"] call _setMode;
        private _loop = _d getVariable ["COMSPEC_DroneLoop", 0];
        if ((count _center) < 3) then { _center = getPosASL _d; };
        if ((count _area) > 0) then { _d setVariable ["COMSPEC_DroneTask", ["HUNT", _center, _rad, _area], true]; };
        [_d, _center, _rad, _loop, _pilot, _area] spawn {
            params ["_d", "_center", "_rad", "_loop", "_pilot", "_area"];
            private _side = _d getVariable ["COMSPEC_DroneSide", sideUnknown];
            private _a = 0;
            private _n = 0;
            while { alive _d && {(_d getVariable ["COMSPEC_DroneLoop", 0]) isEqualTo _loop} } do {
                // Orbite de recherche autour du point de départ (nouveau point toutes les 4 s, balayage chaque seconde).
                if ((_n mod 4) isEqualTo 0) then {
                    _a = (_a + 30) mod 360;
                    _d flyInHeight [_d getVariable ["COMSPEC_DroneAlt", 40], true];
                    _d limitSpeed (_d getVariable ["COMSPEC_DroneSpd", 40]);
                    _d doMove (ASLToAGL (_center vectorAdd [_rad * sin _a, _rad * cos _a, 0]));
                };
                _n = _n + 1;
                // Ennemi vu par la caméra : à moins de 400 m et en vue directe.
                private _eye = getPosASL _d;
                private _seen = (_d nearEntities [["CAManBase", "LandVehicle"], 400]) select {
                    alive _x && {[_side, side group _x] call BIS_fnc_sideIsEnemy} && {!captive _x} && {(count _area) isEqualTo 0 || {_x inArea _area}}
                    && {([_d, "VIEW", vehicle _x] checkVisibility [_eye, aimPos _x]) > 0.35}
                };
                if ((count _seen) > 0) exitWith {
                    _seen = _seen apply { [_x distance _d, _x] };
                    _seen sort true;
                    private _t = (_seen select 0) select 1;
                    private _armed = (_d getVariable ["COMSPEC_DroneArmed", ""]) isNotEqualTo "";
                    _d setVariable ["COMSPEC_DroneEvt", [((_d getVariable ["COMSPEC_DroneEvt", [0]]) select 0) + 1, ["SPOT", "ENGAGE"] select _armed, getPosASL _t, getText (configOf _t >> "displayName")], true];
                    if (!isNull _pilot) then { ["comspec_atak_native_droneEvent", [_d, ["SPOT", "ENGAGE"] select _armed, getPosASL _t, getText (configOf _t >> "displayName")], _pilot] call CBA_fnc_targetEvent; };
                    if (_armed) then { [_d, "strike", [_t, _pilot]] call comspec_atak_native_fnc_droneCmd; } else { [_d, "hover", []] call comspec_atak_native_fnc_droneCmd; };
                };
                sleep 1;
            };
        };
    };
    case "goto": {
        _args params [["_p", []]];
        if ((count _p) < 3) exitWith {};
        ["GOTO"] call _setMode;
        _d setVariable ["COMSPEC_DroneTask", ["GOTO", _p, 0], true];
        private _loop = _d getVariable ["COMSPEC_DroneLoop", 0];
        [_d, _p, _loop] spawn {
            params ["_d", "_p", "_loop"];
            while { alive _d && {(_d getVariable ["COMSPEC_DroneLoop", 0]) isEqualTo _loop} } do {
                // Arrivé : stationnaire au-dessus du point (le journal du pilote note le passage GOTO → HOVER).
                if ((_d distance2D _p) < 20) exitWith { [_d, "hover", []] call comspec_atak_native_fnc_droneCmd; };
                _d flyInHeight [_d getVariable ["COMSPEC_DroneAlt", 40], true];
                _d limitSpeed (_d getVariable ["COMSPEC_DroneSpd", 40]);
                _d doMove (ASLToAGL _p);
                sleep 2;
            };
        };
    };
    case "loiter";
    case "observe": {
        _args params [["_c", []], ["_rad", 150]];
        if ((count _c) < 3) exitWith {};
        private _m = ["LOITER", "OBSERVE"] select (_cmd isEqualTo "observe");
        [_m] call _setMode;
        _d setVariable ["COMSPEC_DroneTask", [_m, _c, _rad], true];
        private _loop = _d getVariable ["COMSPEC_DroneLoop", 0];
        [_d, _c, _rad, _loop] spawn {
            params ["_d", "_c", "_rad", "_loop"];
            // Orbite : on entre sur le cercle du côté où se trouve le drone, puis un nouveau point toutes les 3 s.
            private _a = _c getDir _d;
            while { alive _d && {(_d getVariable ["COMSPEC_DroneLoop", 0]) isEqualTo _loop} } do {
                private _ms = (_d getVariable ["COMSPEC_DroneSpd", 40]) / 3.6;
                // Pas angulaire : l'arc parcouru en 3 s (borné pour garder un cercle propre sur les petits rayons).
                if ((_d distance2D _c) < (_rad + 60)) then { _a = (_a + ((deg (_ms * 3 / (_rad max 30))) max 8 min 45)) mod 360; };
                _d flyInHeight [_d getVariable ["COMSPEC_DroneAlt", 40], true];
                _d limitSpeed (_d getVariable ["COMSPEC_DroneSpd", 40]);
                _d doMove (ASLToAGL (_c vectorAdd [_rad * sin _a, _rad * cos _a, 0]));
                sleep 3;
            };
        };
    };
};
