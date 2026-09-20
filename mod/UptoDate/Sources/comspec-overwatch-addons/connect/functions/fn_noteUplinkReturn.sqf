/*
    Mémorise un retour de liaison (messages, marqueurs, ordres…).
    N’écrit dans le journal que si le résultat change, ou en dépannage
    (au plus une fois par minute pour un même résultat).
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

private _sig = format ["%1|%2", _count, _extra];
private _sigKey = format ["COMSPEC_UplinkLastSig_%1", _kind];
private _atKey = format ["COMSPEC_UplinkLastLogAt_%1", _kind];
private _prevSig = missionNamespace getVariable [_sigKey, ""];
private _lastAt = missionNamespace getVariable [_atKey, -1e9];
private _changed = _sig isNotEqualTo _prevSig;
missionNamespace setVariable [_sigKey, _sig, false];

private _isolate = missionNamespace getVariable ["COMSPEC_DiagIsolateActive", false];
if (!_changed) then {
    if (!_isolate) exitWith { true };
    if ((diag_tickTime - _lastAt) < 60) exitWith { true };
};

private _isErr = ((_extra find "erreur") >= 0);
private _lvl = "DEBUG";
if (_isolate) then { _lvl = "INFO"; };
if (_isErr && {_changed}) then { _lvl = "WARN"; };

[_lvl, "Diag", format ["Retour %1 : %2%3", _kind, _count, _extra]] call comspec_overwatch_connect_fnc_log;
missionNamespace setVariable [_atKey, diag_tickTime, false];
true
