/*
    Ajoute une entrée au fil de notifications Athena (panneau cTab).
    [_kind, _typeLabel, _brief, _detail, _id, _time]
*/
params [
    ["_kind", "system", [""]],
    ["_typeLabel", "Info", [""]],
    ["_brief", "", [""]],
    ["_detail", "", [""]],
    ["_id", "", [""]],
    ["_time", "", [""]]
];

if (!hasInterface) exitWith {};

if (_brief isEqualTo "") exitWith {};

if (_id isEqualTo "") then {
    _id = format ["%1_%2_%3", _kind, _time, diag_tickTime toFixed 2];
};

if (_time isEqualTo "") then {
    _time = [daytime, "HH:MM"] call BIS_fnc_timeToString;
};

private _notifs = missionNamespace getVariable ["COMSPEC_Athena_Notifications", []];
if (!(_notifs isEqualType [])) then { _notifs = []; };

private _existing = _notifs findIf { (_x select 0) isEqualTo _id };
if (_existing >= 0) exitWith {};

_notifs pushBack [_id, _kind, _typeLabel, _brief, _time, true, _detail];
while { (count _notifs) > 30 } do { _notifs deleteAt 0; };

missionNamespace setVariable ["COMSPEC_Athena_Notifications", _notifs, false];
["COMSPEC_AthenaInboxUpdated", []] call CBA_fnc_localEvent;
