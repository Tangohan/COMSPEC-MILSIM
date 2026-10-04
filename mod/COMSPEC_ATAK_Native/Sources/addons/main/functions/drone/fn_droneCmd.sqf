/*
    Mode drone : ordre exécuté là où le drone est local (événement CBA comspec_atak_native_droneCmd,
    envoyé par fn_droneAction avec CBA_fnc_targetEvent sur le drone).
    Params : [drone, ordre, arguments]
      "hover"   []                          stationnaire à l'altitude choisie ;
      "follow"  [cible, altitude, km/h]     suit une unité (le pilote ou un autre joueur), 15 m derrière ;
      "home"    [pilote, altitude, km/h]    revient au pilote puis se pose à côté ;
      "land"    []                          se pose sur place ;
      "rth"     [position ASL]              liaison perdue : retour au point de décollage et atterrissage ;
      "alt"     [altitude] / "speed" [km/h] réglages en vol ;
      "strike"  [cible (position ASL ou objet), pilote]   tir et oublie : approche puis piqué final ;
      "hunt"    [rayon, pilote]             recherche : orbite et premier ennemi vu, frappé si armé, signalé sinon.
    Variables publiques du drone : COMSPEC_DroneMode, COMSPEC_DroneAlt, COMSPEC_DroneSpd, COMSPEC_DroneArmed (munition),
    COMSPEC_DroneSide (camp du pilote), COMSPEC_DroneTgt (position ASL visée, pour la carte).
*/
params [["_d", objNull], ["_cmd", "hover"], ["_args", []]];
if (isNull _d || {!alive _d} || {!local _d}) exitWith {};
private _alt = _d getVariable ["COMSPEC_DroneAlt", 40];
private _spd = _d getVariable ["COMSPEC_DroneSpd", 40];
private _setMode = { params ["_m"]; _d setVariable ["COMSPEC_DroneMode", _m, true]; _d setVariable ["COMSPEC_DroneLoop", (_d getVariable ["COMSPEC_DroneLoop", 0]) + 1]; };
private _fly = {
    params ["_pos"];
    _d flyInHeight [_d getVariable ["COMSPEC_DroneAlt", 40], true];
    _d limitSpeed (_d getVariable ["COMSPEC_DroneSpd", 40]);
    _d doMove (ASLToAGL _pos);
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
switch (_cmd) do {
    case "alt": { _d setVariable ["COMSPEC_DroneAlt", _args param [0, 40], true]; _d flyInHeight [_args param [0, 40], true]; };
    case "speed": { _d setVariable ["COMSPEC_DroneSpd", _args param [0, 40], true]; _d limitSpeed (_args param [0, 40]); };
    case "hover": {
        ["HOVER"] call _setMode;
        doStop _d;
        _d flyInHeight [_alt, true];
        _d doMove (getPosATL _d);
    };
    case "land": { ["LAND"] call _setMode; _d land "LAND"; };
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
    case "home": {
        _args params [["_t", objNull]];
        if (isNull _t) exitWith {};
        [["FOLLOW", "HOME"] select (_cmd isEqualTo "home")] call _setMode;
        _d setVariable ["COMSPEC_DroneFollow", _t, true];
        private _loop = _d getVariable ["COMSPEC_DroneLoop", 0];
        [_d, _t, _loop, _cmd] spawn {
            params ["_d", "_t", "_loop", "_cmd"];
            while { alive _d && {alive _t} && {(_d getVariable ["COMSPEC_DroneLoop", 0]) isEqualTo _loop} } do {
                private _dir = getDir (vehicle _t);
                private _p = (getPosASL _t) vectorAdd [-15 * sin _dir, -15 * cos _dir, 0];
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
    case "hunt": {
        _args params [["_rad", 250], ["_pilot", objNull]];
        ["HUNT"] call _setMode;
        private _loop = _d getVariable ["COMSPEC_DroneLoop", 0];
        private _center = getPosASL _d;
        [_d, _center, _rad, _loop, _pilot] spawn {
            params ["_d", "_center", "_rad", "_loop", "_pilot"];
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
                    alive _x && {[_side, side group _x] call BIS_fnc_sideIsEnemy} && {!captive _x}
                    && {([_d, "VIEW", vehicle _x] checkVisibility [_eye, aimPos _x]) > 0.35}
                };
                if ((count _seen) > 0) exitWith {
                    _seen = _seen apply { [_x distance _d, _x] };
                    _seen sort true;
                    private _t = (_seen select 0) select 1;
                    private _armed = (_d getVariable ["COMSPEC_DroneArmed", ""]) isNotEqualTo "";
                    if (!isNull _pilot) then { ["comspec_atak_native_droneEvent", [_d, ["SPOT", "ENGAGE"] select _armed, getPosASL _t, getText (configOf _t >> "displayName")], _pilot] call CBA_fnc_targetEvent; };
                    if (_armed) then { [_d, "strike", [_t, _pilot]] call comspec_atak_native_fnc_droneCmd; } else { [_d, "hover", []] call comspec_atak_native_fnc_droneCmd; };
                };
                sleep 1;
            };
        };
    };
};
