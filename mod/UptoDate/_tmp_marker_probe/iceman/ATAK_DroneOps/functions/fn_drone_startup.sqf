params [["_drone", objNull]];

if (!([_drone] call Iceman_fnc_drone_isSupported)) exitWith {false};

if (!local _drone) exitWith {
    [_drone] remoteExecCall ["Iceman_fnc_drone_startup", _drone];
    true
};

if ((crew _drone) isEqualTo []) then {
    createVehicleCrew _drone;
};

_drone enableSimulationGlobal true;
_drone setFuel 1;
_drone engineOn true;
_drone setVehicleReceiveRemoteTargets true;
_drone setVehicleReportRemoteTargets true;
_drone setVehicleRadar 1;

{
    _x enableAI "ALL";
    _x setBehaviourStrong "CARELESS";
    _x setCombatMode "BLUE";
} forEach crew _drone;

true
