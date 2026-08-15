#include "..\script_component.hpp"

private _state = call Iceman_fnc_route_getState;
private _start = _state getOrDefault ["start", []];
private _end = _state getOrDefault ["end", []];
private _waypoints = _state getOrDefault ["waypoints", []];
private _route = _state getOrDefault ["route", []];
private _distance = _state getOrDefault ["distance", 0];
private _remaining = _state getOrDefault ["remaining", _distance];
private _mot = _state getOrDefault ["mot", "foot"];
private _planning = _state getOrDefault ["planning", false];
private _tab = _state getOrDefault ["tab", "route"];

private _speedSource = [player, vehicle player] select (_mot == "vehicle");
private _minFoot = missionNamespace getVariable ["Iceman_ATAK_Route_footMinSpeedKph", 4.5];
private _minVehicle = missionNamespace getVariable ["Iceman_ATAK_Route_vehicleMinSpeedKph", 5];
private _minSpeed = [_minFoot, _minVehicle] select (_mot == "vehicle");
private _speedMS = (((speed _speedSource) max _minSpeed) max 1) / 3.6;

if (!(_route isEqualTo [])) then {
    _remaining = ([getPosATL vehicle player, _route] call Iceman_fnc_route_measureRemaining) # 0;
    _state set ["remaining", _remaining];
};

private _eta = [_remaining / _speedMS] call Iceman_fnc_route_formatEta;
private _turns = _state getOrDefault ["turns", []];

private _lines = [];
_lines pushBack format ["Start: %1", ["not set", [_start] call Iceman_fnc_route_posToGrid] select !(_start isEqualTo [])];
_lines pushBack format ["End: %1", ["not set", [_end] call Iceman_fnc_route_posToGrid] select !(_end isEqualTo [])];
_lines pushBack format ["MoT: %1", ["Foot", "Vehicle"] select (_mot == "vehicle")];

if (_tab == "waypoints") then {
    _lines pushBack format ["Waypoints: %1", count _waypoints];
    if (_waypoints isEqualTo []) then {
        _lines pushBack "Add a grid or tap MAP to force the route through a via point.";
    } else {
        {
            _lines pushBack format ["Via %1: %2", _forEachIndex + 1, [_x] call Iceman_fnc_route_posToGrid];
        } forEach _waypoints;
    };
} else {
    _lines pushBack format ["Via: %1", count _waypoints];
};

if (_planning) then {
    _lines pushBack "Route: planning...";
} else {
    if (_route isEqualTo []) then {
        _lines pushBack "Route: not planned";
    } else {
        _lines pushBack format ["Distance: %1 km", (_distance / 1000) toFixed 1];
        _lines pushBack format ["Remaining: %1 km", (_remaining / 1000) toFixed 1];
        _lines pushBack format ["ETA: %1", _eta];
        _lines pushBack format ["Turns: %1", count _turns];
    };
};

_lines
