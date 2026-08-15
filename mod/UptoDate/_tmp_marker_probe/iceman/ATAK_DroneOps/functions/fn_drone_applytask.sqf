params [["_drone", objNull], ["_pos", []], ["_altitude", 60], ["_function", "move"], ["_radius", 150], ["_owner", objNull]];

if (isNull _drone || {!alive _drone} || {_pos isEqualTo []}) exitWith {};

private _crew = crew _drone;
private _unit = _crew param [0, objNull];
if (isNull _unit) exitWith {};

private _group = group _unit;
if (isNull _group) exitWith {};

for "_i" from ((count waypoints _group) - 1) to 0 step -1 do {
    deleteWaypoint [_group, _i];
};

_drone flyInHeight _altitude;
_group setBehaviour "CARELESS";
_group setCombatMode "BLUE";

private _wp = _group addWaypoint [_pos, 0];
switch (_function) do {
    case "protect": {
        _wp setWaypointType "LOITER";
        _wp setWaypointLoiterRadius _radius;
        _wp setWaypointLoiterType "CIRCLE_L";
        _wp setWaypointSpeed "LIMITED";
    };
    case "scan": {
        _wp setWaypointType "LOITER";
        _wp setWaypointLoiterRadius _radius;
        _wp setWaypointLoiterType "CIRCLE_L";
        _wp setWaypointSpeed "LIMITED";
    };
    case "loiter": {
        _wp setWaypointType "LOITER";
        _wp setWaypointLoiterRadius _radius;
        _wp setWaypointLoiterType "CIRCLE_L";
        _wp setWaypointSpeed "LIMITED";
    };
    default {
        _wp setWaypointType "MOVE";
        _wp setWaypointCompletionRadius 20;
        _wp setWaypointSpeed "NORMAL";
    };
};

_wp setWaypointPosition [_pos, 0];
_wp setWaypointStatements ["true", format ["vehicle this flyInHeight %1;", round _altitude]];

_drone setVariable ["Iceman_DroneOps_task", [_pos, _altitude, _function, _radius], true];
_drone setVariable ["Iceman_DroneOps_ownerUnit", _owner, true];
