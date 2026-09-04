/*
    Notification discrète INFO / PRIORITY / CRITICAL, sans spam.
*/
params [
    ["_prio", "INFO", [""]],
    ["_text", "", [""]]
];
if (_text isEqualTo "") exitWith {};
_prio = toUpper _prio;
if !(_prio in ["INFO", "PRIORITY", "CRITICAL"]) then { _prio = "INFO"; };
private _last = missionNamespace getVariable ["COMSPEC_MapNotifLast", createHashMap];
private _key = format ["%1|%2", _prio, _text];
private _t = _last getOrDefault [_key, -1e9];
if ((diag_tickTime - _t) < 12) exitWith {};
_last set [_key, diag_tickTime];
missionNamespace setVariable ["COMSPEC_MapNotifLast", _last, false];
[_prio, _text] call comspec_overwatch_atak_athena_fnc_pushTimelineEvent;
if (!isNil "comspec_overwatch_connect_fnc_addScreenToast") then {
    ["ATHENA", _text, [6, 8, 12] select (["INFO", "PRIORITY", "CRITICAL"] find _prio)] call comspec_overwatch_connect_fnc_addScreenToast;
};
if (!isNil "comspec_overwatch_atak_athena_fnc_athena_pushNotification") then {
    ["map", "Carte", _text, _text, format ["map_%1", diag_tickTime], [daytime, "HH:MM"] call BIS_fnc_timeToString]
        call comspec_overwatch_atak_athena_fnc_athena_pushNotification;
};
