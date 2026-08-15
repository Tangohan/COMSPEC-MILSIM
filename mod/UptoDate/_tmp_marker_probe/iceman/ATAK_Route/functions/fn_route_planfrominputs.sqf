#include "..\script_component.hpp"

params [["_button", controlNull], ["_menuGroup", controlNull]];

private _display = if (!isNull _menuGroup) then {
    ctrlParent _menuGroup
} else {
    uiNamespace getVariable ["cTab_Android_dlg", displayNull]
};
if (isNull _display) exitWith {
    ["ROUTE", "Route page display was not found.", 4] call cTab_fnc_addNotification;
};

private _startCtrl = controlNull;
private _endCtrl = controlNull;
private _motCtrl = controlNull;
private _controls = if (!isNull _menuGroup) then {allControls _menuGroup} else {allControls _display};
{
    if (ctrlIDC _x == 121) then {_startCtrl = _x};
    if (ctrlIDC _x == 123) then {_endCtrl = _x};
    if (ctrlIDC _x == 125) then {_motCtrl = _x};
} forEach _controls;

if (isNull _startCtrl || {isNull _endCtrl}) exitWith {
    ["ROUTE", "Route input fields were not found. Reopen the Route page and try again.", 5] call cTab_fnc_addNotification;
};

private _start = [ctrlText _startCtrl] call Iceman_fnc_route_gridToPos;
private _end = [ctrlText _endCtrl] call Iceman_fnc_route_gridToPos;
if (_start isEqualTo [] || {_end isEqualTo []}) exitWith {
    ["ROUTE", "Enter valid 6, 8, or 10 digit start and end grids.", 5] call cTab_fnc_addNotification;
};

private _state = call Iceman_fnc_route_getState;
private _motIndex = if (isNull _motCtrl) then {0} else {lbCurSel _motCtrl};
private _mot = ["foot", "vehicle"] param [_motIndex max 0, "foot"];
_state set ["start", _start];
_state set ["end", _end];
_state set ["mot", _mot];
_state set ["route", []];
_state set ["turns", []];
_state set ["distance", 0];
_state set ["remaining", 0];
_state set ["active", false];
_state set ["planning", false];
_state set ["planningId", -1];
_state set ["nextTurn", 0];
_state set ["lastPromptTurn", -1];

call Iceman_fnc_route_updatePanel;
call Iceman_fnc_route_startNavigation;
