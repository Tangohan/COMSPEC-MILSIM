/*
    Mémorise et journalise un retour de liaison (messages, marqueurs, ordres…).
    Pendant le dépannage : visible dans le journal. Sinon : niveau debug.
*/
params [
    ["_kind", "", [""]],
    ["_count", 0, [0]],
    ["_extra", "", [""]]
];

if (_kind isEqualTo "") exitWith { false };

missionNamespace setVariable [format ["COMSPEC_UplinkLast_%1", _kind], _count, false];
missionNamespace setVariable ["COMSPEC_UplinkLastKind", _kind, false];
missionNamespace setVariable ["COMSPEC_UplinkLastAt", diag_tickTime, false];

private _isolate = missionNamespace getVariable ["COMSPEC_DiagIsolateActive", false];
private _lvl = if (_isolate) then { "INFO" } else { "DEBUG" };
[_lvl, "Diag", format ["Retour %1 : %2%3", _kind, _count, _extra]] call comspec_overwatch_connect_fnc_log;
true
