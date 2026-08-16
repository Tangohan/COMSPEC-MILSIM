/*
    Breadcrumb unique avant une opération critique.

    Usage: ["SSE.ACE.001", "begin root person"] call comspec_debug_fnc_breadcrumb;
*/
params [
    ["_id", "", [""]],
    ["_message", "", [""]]
];

missionNamespace setVariable ["COMSPEC_DEBUG_LAST_BREADCRUMB", [_id, _message, diag_tickTime]];

["DEBUG", "BREADCRUMB", _id, _message] call comspec_debug_fnc_log;

private _list = missionNamespace getVariable ["COMSPEC_DEBUG_BREADCRUMBS", []];
if (!(_list isEqualType [])) then { _list = []; };
_list pushBack [_id, _message, diag_tickTime];
if ((count _list) > 200) then {
    _list deleteRange [0, (count _list) - 200];
};
missionNamespace setVariable ["COMSPEC_DEBUG_BREADCRUMBS", _list];

true
