#include "..\script_component.hpp"

params [["_tab", "route"]];
if !(_tab in ["route", "waypoints"]) then {_tab = "route"};

private _state = call Iceman_fnc_route_getState;
_state set ["tab", _tab];
if (_tab == "waypoints" && {(_state getOrDefault ["selectMode", ""]) in ["start", "end"]}) then {
    _state set ["selectMode", ""];
};
if (_tab == "route" && {(_state getOrDefault ["selectMode", ""]) == "waypoint"}) then {
    _state set ["selectMode", ""];
};
call Iceman_fnc_route_updatePanel;
