#include "..\script_component.hpp"

private _display = uiNamespace getVariable ["cTab_Android_dlg", displayNull];
if (isNull _display) exitWith {};

private _pageGroup = uiNamespace getVariable ["Iceman_ATAK_Route_pageGroup", controlNull];
private _scanControls = if (isNull _pageGroup) then {
    allControls _display
} else {
    allControls _pageGroup
};

private _ctrl = {
    params ["_idc"];
    private _found = controlNull;
    {
        if (ctrlIDC _x == _idc) exitWith {_found = _x};
    } forEach _scanControls;
    _found
};

private _modeCtrl = [30] call _ctrl;
private _infoCtrl = [31] call _ctrl;
private _startCtrl = [121] call _ctrl;
private _endCtrl = [123] call _ctrl;
private _motCtrl = [125] call _ctrl;
private _routeTabCtrl = [110] call _ctrl;
private _waypointTabCtrl = [111] call _ctrl;
if (isNull _modeCtrl && {isNull _infoCtrl}) exitWith {};

private _state = call Iceman_fnc_route_getState;
private _start = _state getOrDefault ["start", []];
private _end = _state getOrDefault ["end", []];
private _mot = _state getOrDefault ["mot", "foot"];
private _mode = _state getOrDefault ["selectMode", ""];
private _planning = _state getOrDefault ["planning", false];
private _tab = _state getOrDefault ["tab", "route"];
private _waypoints = _state getOrDefault ["waypoints", []];

{
    private _c = [_x] call _ctrl;
    if (!isNull _c) then {_c ctrlShow (_tab == "route")};
} forEach [120,121,122,123,124,125,126,127];
{
    private _c = [_x] call _ctrl;
    if (!isNull _c) then {_c ctrlShow (_tab == "waypoints")};
} forEach [130,131,132,133,134,135];

if (!isNull _routeTabCtrl) then {_routeTabCtrl ctrlSetText (["Route", "> Route"] select (_tab == "route"))};
if (!isNull _waypointTabCtrl) then {_waypointTabCtrl ctrlSetText (["Waypoints", "> Waypoints"] select (_tab == "waypoints"))};

if (!isNull _startCtrl) then {
    _startCtrl ctrlSetText (["", [_start] call Iceman_fnc_route_posToGrid] select !(_start isEqualTo []));
};
if (!isNull _endCtrl) then {
    _endCtrl ctrlSetText (["", [_end] call Iceman_fnc_route_posToGrid] select !(_end isEqualTo []));
};
if (!isNull _motCtrl) then {
    if (lbSize _motCtrl == 0) then {
        _motCtrl lbAdd "Foot";
        _motCtrl lbAdd "Vehicle";
    };
    _motCtrl lbSetCurSel ([0, 1] select (_mot == "vehicle"));
};

if (!isNull _modeCtrl) then {
    private _modeText = if (_planning) then {
        "Planning foot route..."
    } else {
        if (_mode == "") then {
            if (_tab == "waypoints") then {
                format ["Waypoints - %1 via point(s)", count _waypoints]
            } else {
                format ["Ready - %1", ["Foot / concealed", "Vehicle / road"] select (_mot == "vehicle")]
            }
        } else {
            private _targetText = switch (_mode) do {
                case "start": {"start point"};
                case "end": {"end point"};
                case "waypoint": {"waypoint"};
                default {_mode};
            };
            format ["Tap %1", _targetText]
        }
    };
    _modeCtrl ctrlSetStructuredText parseText format ["<t align='center'>%1</t>", _modeText];
};

if (!isNull _infoCtrl) then {
    private _lines = [] call Iceman_fnc_route_getInfoLines;
    _infoCtrl ctrlSetStructuredText parseText (_lines joinString "<br/>");
};
