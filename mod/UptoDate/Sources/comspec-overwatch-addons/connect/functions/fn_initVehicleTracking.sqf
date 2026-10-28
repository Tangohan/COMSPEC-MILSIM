/*
 * Tracking véhicules : handlers liés à l’unité joueur courante.
 * Rebind si l’objet player change (REAPP / MRH) — jamais d’empilement sur la même unité.
 */
if (!hasInterface) exitWith { false };
if (isNull player) exitWith { false };

private _bound = missionNamespace getVariable ["COMSPEC_VehTrackPlayer", objNull];
if (!isNull _bound && {_bound isEqualTo player}) exitWith { true };
missionNamespace setVariable ["COMSPEC_VehTrackPlayer", player, false];
missionNamespace setVariable ["COMSPEC_VehTrackLastAt", -1e9, false];

// Les EH du joueur sont repris sur la nouvelle unité au respawn, puis ce script les rajoute (rebind) :
// ils s'empilaient à chaque REAPP. Seul le dernier EH enregistré (id mémorisé) agit ; un doublon repris
// se retire lui-même au premier déclenchement.
private _evh = player addEventHandler ["GetInMan", {
    params ["_unit", "_role", "_vehicle", "_turret"];
    if (_thisEventHandler != (missionNamespace getVariable ["COMSPEC_VehTrackEh_GetInMan", -1])) exitWith {
        _unit removeEventHandler ["GetInMan", _thisEventHandler];
    };
    if (_vehicle isEqualTo _unit) exitWith {};

    if (!isNil "comspec_overwatch_connect_fnc_applyCtabBftCallsign") then {
        [] call comspec_overwatch_connect_fnc_applyCtabBftCallsign;
    };

    if (!isNil "comspec_overwatch_connect_fnc_hideAceMenu") then {
        [] call comspec_overwatch_connect_fnc_hideAceMenu;
    };

    if (!isNil "comspec_overwatch_connect_fnc_emitTelemetryEvent") then {
        private _cs = [] call comspec_overwatch_connect_fnc_getCallsign;
        if (_cs isEqualTo "") then { _cs = name _unit; };
        private _pos = getPosASL _unit;
        ["unit", createHashMapFromArray [
            ["action", "enter"],
            ["call_sign", _cs],
            ["role", _role],
            ["vehicle", getText (configOf _vehicle >> "displayName")],
            ["vehicle_class", typeOf _vehicle],
            ["x", _pos select 0],
            ["y", _pos select 1]
        ], 1] call comspec_overwatch_connect_fnc_emitTelemetryEvent;
        // LOGSTAT immédiat à l’embarquement
        missionNamespace setVariable ["COMSPEC_LogisticsLastSig", "", false];
        missionNamespace setVariable ["COMSPEC_LogisticsLastAt", -1e9, false];
    };

    private _trackingHandle = _vehicle getVariable ["COMSPEC_TrackingHandle", -1];
    if (_trackingHandle isEqualTo -1) then {
        private _handle = [{
            params ["_args", "_handle"];
            _args params ["_vehicle"];

            // Arrêt aussi quand le joueur a quitté le véhicule : sinon le PFH tournait tant qu'il restait
            // un équipage (un PFH de plus par véhicule emprunté, jusqu'à la fin de la partie).
            if (isNull _vehicle || {vehicle player != _vehicle} || {({alive _x} count (crew _vehicle)) isEqualTo 0}) then {
                [_handle] call CBA_fnc_removePerFrameHandler;
                _vehicle setVariable ["COMSPEC_TrackingHandle", -1];
            } else {
                if (diag_tickTime < (missionNamespace getVariable ["COMSPEC_RespawnGraceUntil", -1e9])) exitWith {};
                private _last = missionNamespace getVariable ["COMSPEC_VehTrackLastAt", -1e9];
                if ((diag_tickTime - _last) < 2.5) exitWith {};
                missionNamespace setVariable ["COMSPEC_VehTrackLastAt", diag_tickTime, false];
                [_vehicle] call comspec_overwatch_connect_fnc_updateVehicleTracking;
            };
        }, 3, [_vehicle]] call CBA_fnc_addPerFrameHandler;

        _vehicle setVariable ["COMSPEC_TrackingHandle", _handle];
        // Pas de systemChat : le suivi reste silencieux (journal / milsim).
    };
}];
missionNamespace setVariable ["COMSPEC_VehTrackEh_GetInMan", _evh, false];

_evh = player addEventHandler ["GetOutMan", {
    params ["_unit", "_role", "_vehicle", "_turret"];
    if (_thisEventHandler != (missionNamespace getVariable ["COMSPEC_VehTrackEh_GetOutMan", -1])) exitWith {
        _unit removeEventHandler ["GetOutMan", _thisEventHandler];
    };
    if (isNull _unit || {!local _unit}) exitWith {};
    if (diag_tickTime < (missionNamespace getVariable ["COMSPEC_RespawnGraceUntil", -1e9])) exitWith {};
    if (!isNil "comspec_overwatch_connect_fnc_emitTelemetryEvent") then {
        private _cs = [] call comspec_overwatch_connect_fnc_getCallsign;
        if (_cs isEqualTo "") then { _cs = name _unit; };
        private _pos = getPosASL _unit;
        private _vehName = if (isNull _vehicle) then { "" } else { getText (configOf _vehicle >> "displayName") };
        ["unit", createHashMapFromArray [
            ["action", "exit"],
            ["call_sign", _cs],
            ["role", _role],
            ["vehicle", _vehName],
            ["x", _pos select 0],
            ["y", _pos select 1]
        ], 1] call comspec_overwatch_connect_fnc_emitTelemetryEvent;
    };
}];
missionNamespace setVariable ["COMSPEC_VehTrackEh_GetOutMan", _evh, false];

_evh = player addEventHandler ["Killed", {
    params ["_unit"];
    if (_thisEventHandler != (missionNamespace getVariable ["COMSPEC_VehTrackEh_Killed", -1])) exitWith {
        _unit removeEventHandler ["Killed", _thisEventHandler];
    };

    if (missionNamespace getVariable ["COMSPEC_DeathThenRespawn", false]) exitWith {};
    if (diag_tickTime < (missionNamespace getVariable ["COMSPEC_RespawnGraceUntil", -1e9])) exitWith {};

    private _vehicle = vehicle _unit;
    if (!(_vehicle isEqualTo _unit) && {!alive _vehicle}) then {
        private _vehicleData = createHashMap;
        _vehicleData set ["vehicle_callsign", getText (configOf _vehicle >> "displayName")];
        _vehicleData set ["status", "DESTROYED"];
        private _jsonString = [_vehicleData] call comspec_overwatch_connect_fnc_hashMapToJson;
        // POST hors du thread de jeu (spawn + extAwait) : retour inutilisé, l'appel synchrone figeait le jeu.
        [_jsonString] spawn {
            ["COMSPECExtension", "UpdateVehicleTracking", _this] call comspec_overwatch_connect_fnc_extAwait;
        };
    };
}];
missionNamespace setVariable ["COMSPEC_VehTrackEh_Killed", _evh, false];

_evh = player addEventHandler ["Respawn", {
    if (_thisEventHandler != (missionNamespace getVariable ["COMSPEC_VehTrackEh_Respawn", -1])) exitWith {
        (_this select 0) removeEventHandler ["Respawn", _thisEventHandler];
    };
    // Grâce déjà posée par initATAK (Respawn / EntityRespawned) — ici on rebind seulement.
    [{
        [] call comspec_overwatch_connect_fnc_initVehicleTracking;
        if (!isNil "comspec_overwatch_connect_fnc_applyCtabBftCallsign") then {
            [] call comspec_overwatch_connect_fnc_applyCtabBftCallsign;
        };
    }, [], 0.5] call CBA_fnc_waitAndExecute;
}];
missionNamespace setVariable ["COMSPEC_VehTrackEh_Respawn", _evh, false];

true
