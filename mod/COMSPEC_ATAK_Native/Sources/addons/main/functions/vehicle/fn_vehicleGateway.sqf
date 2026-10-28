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
    Réglage serveur comspec_atak_native_vehicle_gateways (désactivé par défaut) : coupé, aucune passerelle ni action ACE,
    celles déjà ouvertes sont refermées au balayage suivant. Un véhicule relais voulu par la mission se pose avec le
    module « ATAK · Relais » synchronisé au véhicule (fn_moduleRelay).
    Params : [action, véhicule]
      "can" v        : le joueur peut-il commander la passerelle (équipage à bord, ou à moins de 6 m dehors) ?
      "state" v      : "" | "relay" | "cp"
      "relay" v / "cp" v / "off" v : changer l'état (envoyé au serveur)
      "list"         : passerelles de mon camp [[véhicule, mode, portée m, Mbit/s, nom]...]
      "nearest"      : la meilleure à portée du joueur [véhicule, distance, portée, Mbit/s, mode, nom] ou []
      "srv" [act, v] (serveur, remoteExecCall : camp pris de l'émetteur)     "sweep" (serveur, toutes les 5 s)
*/
params [["_act", "list"], ["_v", objNull]];
private _ranges = missionNamespace getVariable ["COMSPEC_ATAK_GwRange", [4000, 10000]];
private _spec = { params ["_mode"]; [[_ranges select 0, 6], [_ranges select 1, 20]] select (_mode isEqualTo "cp") };
private _on = (["gateways"] call comspec_atak_native_fnc_netCond);
private _ok = { params ["_o"]; !isNull _o && {alive _o} && {!(_o isKindOf "StaticWeapon")} && {_o isKindOf "LandVehicle" || {_o isKindOf "Ship"} || {_o isKindOf "Helicopter"}} };
switch (_act) do {
    case "state": { (_v getVariable ["COMSPEC_ATAK_Gateway", []]) param [0, ""] };
    case "can": {
        if (!_on || {!([_v] call _ok)}) exitWith { false };
        private _in = objectParent player isEqualTo _v;
        (_in && {driver _v isEqualTo player || {commander _v isEqualTo player} || {gunner _v isEqualTo player} || {effectiveCommander _v isEqualTo player}})
            || {!_in && {isNull objectParent player} && {(player distance _v) < 6}}
    };
    case "relay";
    case "cp";
    case "off": {
        if !([_v] call _ok) exitWith { false };
        if (!_on && {_act isNotEqualTo "off"}) exitWith {
            ["WARNING", "Passerelles de véhicule désactivées sur ce serveur", 4, 30] call comspec_atak_native_fnc_notify; false
        };
        if (_act isEqualTo "cp" && {(speed _v) > 1 || {!isTouchingGround _v && {!(_v isKindOf "Ship")}}}) exitWith {
            ["WARNING", "Arrêtez le véhicule pour déployer le poste de commandement", 4, 30] call comspec_atak_native_fnc_notify; false
        };
        // remoteExecCall (et non l'événement CBA) : le serveur sait qui envoie et en tire le camp et la distance.
        ["srv", [_act, _v]] remoteExecCall ["comspec_atak_native_fnc_vehicleGateway", 2];
        playSound "ClickSoft";
        ["INFO", switch (_act) do {
            case "relay": { "Passerelle du véhicule activée : relais du réseau ATAK" };
            case "cp": { "Poste de commandement en cours de déploiement (mât)" };
            default { "Passerelle du véhicule coupée" };
        }, 4, 30] call comspec_atak_native_fnc_notify;
        true
    };
    case "list": {
        if (!_on) exitWith { [] };
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
        // Hystérésis : la passerelle déjà accrochée est gardée jusqu'à 20 % au-delà de sa portée (le masque du
        // relief, recalculé depuis la tête du joueur, basculait à chaque pas au bord de la zone : liaison qui clignote).
        private _last = uiNamespace getVariable ["COMSPEC_ATAK_GwLast", objNull];
        {
            _x params ["_o", "_m", "_r", "_mb", "_n"];
            private _d = player distance _o;
            private _eff = _r;
            // Relief entre l'antenne et le téléphone : portée réduite comme pour le maillage.
            if (terrainIntersectASL [eyePos player, (getPosASL _o) vectorAdd [0, 0, [3, 9] select (_m isEqualTo "cp")]]) then { _eff = _r * 0.35; };
            if (_o isEqualTo _last) then { _eff = _eff * 1.2; };
            if (_d < _eff) then {
                private _s = _mb * (1 - (_d / _eff) ^ 2);
                if (_s > _score) then { _score = _s; _best = [_o, _d, _eff, _mb, _m, _n]; };
            };
        } forEach (["list"] call comspec_atak_native_fnc_vehicleGateway);
        uiNamespace setVariable ["COMSPEC_ATAK_GwLast", _best param [0, objNull]];
        _best
    };
    case "srv": {
        if (!isServer) exitWith {};
        _v params [["_mode", "off", [""]], ["_veh", objNull, [objNull]]];
        if (isNull _veh || {!(_mode in ["relay", "cp", "off"])} || {!([_veh] call _ok)}) exitWith {};
        // Émetteur : celui du remoteExec, jamais un argument (camp, distance et équipage vérifiés ici, comme "can").
        private _u = objNull;
        if (!isMultiplayer) then { _u = player; } else {
            if (isRemoteExecuted) then {
                private _owner = remoteExecutedOwner;
                if (hasInterface && {_owner isEqualTo clientOwner}) then { _u = player; } else {
                    private _i = allPlayers findIf { (owner _x) isEqualTo _owner };
                    if (_i >= 0) then { _u = allPlayers select _i; };
                };
            };
        };
        if (isNull _u || {!alive _u}) exitWith {};
        if ((objectParent _u) isNotEqualTo _veh && {(_u distance _veh) > 10}) exitWith {};
        if (((crew _veh) findIf { alive _x && {[side group _x, side group _u] call BIS_fnc_sideIsEnemy} }) >= 0) exitWith {};
        private _side = str side group _u;
        if (!_on) then { _mode = "off"; };
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
                // Liste des mâts (serveur) : un véhicule supprimé laissait le sien planté là.
                private _masts = missionNamespace getVariable ["COMSPEC_ATAK_GwMasts", []];
                _masts pushBack _mast;
                missionNamespace setVariable ["COMSPEC_ATAK_GwMasts", _masts];
            };
        };
        missionNamespace setVariable ["COMSPEC_ATAK_Gateways", _list, true];
    };
    case "sweep": {
        if (!isServer) exitWith {};
        private _list = missionNamespace getVariable ["COMSPEC_ATAK_Gateways", []];
        // Mâts dont le véhicule a disparu (supprimé, nettoyé) : retirés.
        missionNamespace setVariable ["COMSPEC_ATAK_GwMasts", (missionNamespace getVariable ["COMSPEC_ATAK_GwMasts", []]) select {
            if (isNull _x) then { false } else { if (isNull attachedTo _x) then { deleteVehicle _x; false } else { true } }
        }];
        private _keep = [];
        {
            if (isNull _x) then { continue };
            private _mast = _x getVariable ["COMSPEC_ATAK_GwMast", objNull];
            if (!alive _x) then { if (!isNull _mast) then { deleteVehicle _mast; }; _x setVariable ["COMSPEC_ATAK_Gateway", nil, true]; continue };
            private _st = _x getVariable ["COMSPEC_ATAK_Gateway", []];
            if (_st isEqualTo []) then { continue };
            // Réglage coupé en cours de partie : passerelle refermée, mât retiré.
            if (!_on) then { if (!isNull _mast) then { deleteVehicle _mast; _x setVariable ["COMSPEC_ATAK_GwMast", objNull]; }; _x setVariable ["COMSPEC_ATAK_Gateway", nil, true]; continue };
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
