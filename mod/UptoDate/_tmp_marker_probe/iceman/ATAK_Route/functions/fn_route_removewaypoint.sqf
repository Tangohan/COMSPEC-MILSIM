#include "..\script_component.hpp"

params [["_index", -1]];
private _state = call Iceman_fnc_route_getState;
private _waypoints = +(_state getOrDefault ["waypoints", []]);
if (_waypoints isEqualTo []) exitWith {
    ["ROUTE", "No waypoints to remove.", 3] call cTab_fnc_addNotification;
};

if (_index < 0) then {_index = (count _waypoints) - 1};
if (_index < 0 || {_index >= count _waypoints}) exitWith {};
_waypoints deleteAt _index;

_state set ["waypoints", _waypoints];
_state set ["route", []];
_state set ["turns", []];
_state set ["distance", 0];
_state set ["remaining", 0];
_state set ["active", false];
_state set ["planning", false];
_state set ["planningId", -1];
_state set ["nextTurn", 0];
_state set ["lastPromptTurn", -1];
_state set ["tab", "waypoints"];
call Iceman_fnc_route_updatePanel;
["ROUTE", format ["Waypoint removed. %1 remaining.", count _waypoints], 3] call cTab_fnc_addNotification;
