#include "..\script_component.hpp"

params [["_button", controlNull], ["_menuGroup", controlNull]];

private _pageGroup = if (!isNull _menuGroup) then {_menuGroup} else {uiNamespace getVariable ["Iceman_ATAK_Route_pageGroup", controlNull]};
private _display = if (!isNull _pageGroup) then {ctrlParent _pageGroup} else {uiNamespace getVariable ["cTab_Android_dlg", displayNull]};
if (isNull _display && {isNull _pageGroup}) exitWith {
    ["ROUTE", "Route page was not found.", 4] call cTab_fnc_addNotification;
};

private _controls = if (!isNull _pageGroup) then {allControls _pageGroup} else {allControls _display};
private _wpCtrl = controlNull;
{
    if (ctrlIDC _x == 131) exitWith {_wpCtrl = _x};
} forEach _controls;

if (isNull _wpCtrl) exitWith {
    ["ROUTE", "Waypoint input was not found. Reopen the Route page and try again.", 5] call cTab_fnc_addNotification;
};

private _pos = [ctrlText _wpCtrl] call Iceman_fnc_route_gridToPos;
if (_pos isEqualTo []) exitWith {
    ["ROUTE", "Enter a valid 6, 8, or 10 digit waypoint grid.", 5] call cTab_fnc_addNotification;
};

[_pos] call Iceman_fnc_route_addWaypoint;
_wpCtrl ctrlSetText "";
