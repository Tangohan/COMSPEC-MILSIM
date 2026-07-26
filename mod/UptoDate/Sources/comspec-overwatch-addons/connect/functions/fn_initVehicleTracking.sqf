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

player addEventHandler ["GetInMan", {
    params ["_unit", "_role", "_vehicle", "_turret"];
    if (_vehicle isEqualTo _unit) exitWith {};

    private _trackingHandle = _vehicle getVariable ["COMSPEC_TrackingHandle", -1];
    if (_trackingHandle isEqualTo -1) then {
        private _handle = [{
            params ["_args", "_handle"];
            _args params ["_vehicle"];

            if (isNull _vehicle || {alive _x} count (crew _vehicle) isEqualTo 0) then {
                [_handle] call CBA_fnc_removePerFrameHandler;
                _vehicle setVariable ["COMSPEC_TrackingHandle", -1];
            } else {
                if (diag_tickTime < (missionNamespace getVariable ["COMSPEC_RespawnGraceUntil", -1e9])) exitWith {};
                private _last = missionNamespace getVariable ["COMSPEC_VehTrackLastAt", -1e9];
                if ((diag_tickTime - _last) < 8) exitWith {};
                missionNamespace setVariable ["COMSPEC_VehTrackLastAt", diag_tickTime, false];
                [_vehicle] call comspec_overwatch_connect_fnc_updateVehicleTracking;
            };
        }, 10, [_vehicle]] call CBA_fnc_addPerFrameHandler;

        _vehicle setVariable ["COMSPEC_TrackingHandle", _handle];
        private _vehicleName = getText (configOf _vehicle >> "displayName");
        systemChat format ["Suivi véhicule %1 activé", _vehicleName];
    };
}];

player addEventHandler ["GetOutMan", {}];

player addEventHandler ["Killed", {
    params ["_unit"];

    if (missionNamespace getVariable ["COMSPEC_DeathThenRespawn", false]) exitWith {};
    if (diag_tickTime < (missionNamespace getVariable ["COMSPEC_RespawnGraceUntil", -1e9])) exitWith {};

    private _vehicle = vehicle _unit;
    if (!(_vehicle isEqualTo _unit) && {!alive _vehicle}) then {
        private _vehicleData = createHashMap;
        _vehicleData set ["vehicle_callsign", getText (configOf _vehicle >> "displayName")];
        _vehicleData set ["status", "DESTROYED"];
        private _jsonString = [_vehicleData] call comspec_overwatch_connect_fnc_hashMapToJson;
        "COMSPECExtension" callExtension ["UpdateVehicleTracking", [_jsonString]];
    };
}];

player addEventHandler ["Respawn", {
    [] call comspec_overwatch_connect_fnc_onPlayerRespawn;
    // Nouvelle unité : rebind tracking sans empiler sur l’ancienne
    [{
        [] call comspec_overwatch_connect_fnc_initVehicleTracking;
    }, [], 0.5] call CBA_fnc_waitAndExecute;
}];

if (!(missionNamespace getVariable ["COMSPEC_VehicleTrackingBootMsg", false])) then {
    missionNamespace setVariable ["COMSPEC_VehicleTrackingBootMsg", true, false];
    systemChat "Suivi véhicules initialisé";
};

true
