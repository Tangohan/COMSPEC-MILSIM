#include "..\script_component.hpp"

params ["_mode"];
private _state = call Iceman_fnc_route_getState;
_state set ["selectMode", _mode];
if (_mode == "waypoint") then {_state set ["tab", "waypoints"]};
if (_mode in ["start", "end"]) then {_state set ["tab", "route"]};
call Iceman_fnc_route_updatePanel;

private _label = switch (_mode) do {
    case "start": {"start point"};
    case "end": {"end point"};
    case "waypoint": {"waypoint"};
    default {_mode};
};
["ROUTE", format ["Tap the ATAK map to set %1.", _label], 4] call cTab_fnc_addNotification;
