/*
    Passerelle de véhicule : un véhicule (terrestre, bateau, hélicoptère) devient relais du réseau ATAK de son camp.
      relay : passerelle activée, même en roulant. Portée 4 km, 6 Mbit/s (liaison satellite du véhicule).
      cp    : poste de commandement mobile déployé, véhicule à l'arrêt : mât télescopique dressé sur le toit,
              portée 10 km, 20 Mbit/s, terminal du PC pour tous à bord et à moins de 8 m (fn_vehicleTerminal).
              Le véhicule qui repart replie le mât (retour en relay).
    Les téléphones à portée y prennent leur débit (fn_linkQuality, même sans relais Overwatch ni Athena) et le
    maillage Wave Relay s'y raccroche (fn_waveRelayAction, calque carte).
    État public du véhicule : COMSPEC_ATAK_Gateway = [mode, camp (str side), nom]. Liste publique tenue par le
    serveur : COMSPEC_ATAK_Gateways. Portées réglables par mission : COMSPEC_ATAK_GwRange = [relay, cp] en mètres.
    Params : [action, véhicule]
      "can" v        : le joueur peut-il commander la passerelle (équipage à bord, ou à moins de 6 m dehors) ?
      "state" v      : "" | "relay" | "cp"
      "relay" v / "cp" v / "off" v : changer l'état (envoyé au serveur)
      "list"         : passerelles de mon camp [[véhicule, mode, portée m, Mbit/s, nom]...]
      "nearest"      : la meilleure à portée du joueur [véhicule, distance, portée, Mbit/s, mode, nom] ou []
      "srv" [act, v, camp] (serveur)     "sweep" (serveur, toutes les 5 s)
*/
params [["_act", "list"], ["_v", objNull]];
private _ranges = missionNamespace getVariable ["COMSPEC_ATAK_GwRange", [4000, 10000]];
private _spec = { params ["_mode"]; [[_ranges select 0, 6], [_ranges select 1, 20]] select (_mode isEqualTo "cp") };
private _ok = { params ["_o"]; !isNull _o && {alive _o} && {!(_o isKindOf "StaticWeapon")} && {_o isKindOf "LandVehicle" || {_o isKindOf "Ship"} || {_o isKindOf "Helicopter"}} };
switch (_act) do {
    case "state": { (_v getVariable ["COMSPEC_ATAK_Gateway", []]) param [0, ""] };
    case "can": {
        if !([_v] call _ok) exitWith { false };
        private _in = objectParent player isEqualTo _v;
        (_in && {driver _v isEqualTo player || {commander _v isEqualTo player} || {gunner _v isEqualTo player} || {effectiveCommander _v isEqualTo player}})
            || {!_in && {isNull objectParent player} && {(player distance _v) < 6}}
    };
    case "relay";
    case "cp";
    case "off": {
        if !([_v] call _ok) exitWith { false };
        if (_act isEqualTo "cp" && {(speed _v) > 1 || {!isTouchingGround _v && {!(_v isKindOf "Ship")}}}) exitWith {
            ["WARNING", "Arrêtez le véhicule pour déployer le poste de commandement", 4, 30] call comspec_atak_native_fnc_notify; false
        };
        ["comspec_atak_native_gwSrv", ["srv", [_act, _v, str side group player]]] call CBA_fnc_serverEvent;
        playSound "ClickSoft";
        ["INFO", switch (_act) do {
            case "relay": { "Passerelle du véhicule activée : relais du réseau ATAK" };
            case "cp": { "Poste de commandement en cours de déploiement (mât)" };
            default { "Passerelle du véhicule coupée" };
        }, 4, 30] call comspec_atak_native_fnc_notify;
        true
    };
    case "list": {
        private _side = str side group player;
        ((missionNamespace getVariable ["COMSPEC_ATAK_Gateways", []]) select { [_x] call _ok && {((_x getVariable ["COMSPEC_ATAK_Gateway", []]) param [1, ""]) isEqualTo _side} }) apply {
            (_x getVariable ["COMSPEC_ATAK_Gateway", []]) params [["_m", "relay"], "", ["_n", ""]];
            ([_m] call _spec) params ["_r", "_mb"];
            [_x, _m, _r, _mb, _n]
        }
    };
    case "nearest": {
        private _best = [];
        private _score = -1;
        {
            _x params ["_o", "_m", "_r", "_mb", "_n"];
            private _d = player distance _o;
            private _eff = _r;
            // Relief entre l'antenne et le téléphone : portée réduite comme pour le maillage.
            if (terrainIntersectASL [eyePos player, (getPosASL _o) vectorAdd [0, 0, [3, 9] select (_m isEqualTo "cp")]]) then { _eff = _r * 0.35; };
            if (_d < _eff) then {
                private _s = _mb * (1 - (_d / _eff) ^ 2);
                if (_s > _score) then { _score = _s; _best = [_o, _d, _eff, _mb, _m, _n]; };
            };
        } forEach (["list"] call comspec_atak_native_fnc_vehicleGateway);
        _best
    };
    case "srv": {
        if (!isServer) exitWith {};
        _v params [["_mode", "off"], ["_veh", objNull], ["_side", ""]];
        if (isNull _veh) exitWith {};
        private _mast = _veh getVariable ["COMSPEC_ATAK_GwMast", objNull];
        if (!isNull _mast && {_mode isNotEqualTo "cp"}) then { deleteVehicle _mast; _veh setVariable ["COMSPEC_ATAK_GwMast", objNull]; };
        private _list = (missionNamespace getVariable ["COMSPEC_ATAK_Gateways", []]) select { !isNull _x && {_x isNotEqualTo _veh} };
        if (_mode isEqualTo "off") then {
            _veh setVariable ["COMSPEC_ATAK_Gateway", nil, true];
        } else {
            private _cmd = effectiveCommander _veh;
            private _name = format ["%1%2", getText (configOf _veh >> "displayName"), ["", format [" (%1)", groupId group _cmd]] select (!isNull _cmd)];
            _veh setVariable ["COMSPEC_ATAK_Gateway", [_mode, _side, _name], true];
            _list pushBack _veh;
            if (_mode isEqualTo "cp" && {isNull _mast}) then {
                // Mât télescopique sur le toit (petite antenne du jeu), retiré au repli.
                private _bb = boundingBoxReal _veh;
                _mast = createVehicle ["Land_TTowerSmall_1_F", getPosATL _veh, [], 0, "CAN_COLLIDE"];
                _mast attachTo [_veh, [0, ((_bb select 0) select 1) * 0.4, (_bb select 1) select 2]];
                _mast allowDamage false;
                _veh setVariable ["COMSPEC_ATAK_GwMast", _mast];
            };
        };
        missionNamespace setVariable ["COMSPEC_ATAK_Gateways", _list, true];
    };
    case "sweep": {
        if (!isServer) exitWith {};
        private _list = missionNamespace getVariable ["COMSPEC_ATAK_Gateways", []];
        private _keep = [];
        {
            if (isNull _x) then { continue };
            private _mast = _x getVariable ["COMSPEC_ATAK_GwMast", objNull];
            if (!alive _x) then { if (!isNull _mast) then { deleteVehicle _mast; }; _x setVariable ["COMSPEC_ATAK_Gateway", nil, true]; continue };
            private _st = _x getVariable ["COMSPEC_ATAK_Gateway", []];
            if (_st isEqualTo []) then { continue };
            // Poste de commandement qui repart : mât replié, passerelle en relais.
            if ((_st select 0) isEqualTo "cp" && {(speed _x) > 3}) then {
                if (!isNull _mast) then { deleteVehicle _mast; _x setVariable ["COMSPEC_ATAK_GwMast", objNull]; };
                _x setVariable ["COMSPEC_ATAK_Gateway", ["relay", _st select 1, _st select 2], true];
                ["comspec_atak_native_note", ["Véhicule en mouvement : mât replié, poste de commandement redevenu relais"], crew _x] call CBA_fnc_targetEvent;
            };
            _keep pushBack _x;
        } forEach _list;
        if (_keep isNotEqualTo _list) then { missionNamespace setVariable ["COMSPEC_ATAK_Gateways", _keep, true]; };
    };
    default { false };
};
