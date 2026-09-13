/*
    [] call comspec_overwatch_connect_fnc_playerInHatchetVehicle
*/
if (!hasInterface) exitWith { false };
if (isNull player) exitWith { false };

private _veh = vehicle player;
if (_veh isEqualTo player) exitWith { false };
[_veh] call comspec_overwatch_connect_fnc_isHatchetVehicle
