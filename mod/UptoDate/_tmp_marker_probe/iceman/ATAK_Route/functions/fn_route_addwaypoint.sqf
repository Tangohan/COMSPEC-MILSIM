#include "..\script_component.hpp"

private _clearPlanned = {
    params ["_state"];
    _state set ["route", []];
    _state set ["turns", []];
    _state set ["distance", 0];
    _state set ["remaining", 0];
    _state set ["active", false];
    _state set ["planning", false];
    _state set ["planningId", -1];
    _state set ["nextTurn", 0];
    _state set ["lastPromptTurn", -1];
};

params ["_pos"];
if (_pos isEqualTo []) exitWith {};

private _state = call Iceman_fnc_route_getState;
private _waypoints = +(_state getOrDefault ["waypoints", []]);
_waypoints pushBack _pos;
_state set ["waypoints", _waypoints];
_state set ["tab", "waypoints"];
_state set ["selectMode", ""];
[_state] call _clearPlanned;

call Iceman_fnc_route_updatePanel;
["ROUTE", format ["Waypoint %1 added.", count _waypoints], 3] call cTab_fnc_addNotification;
