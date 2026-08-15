params [["_target", objNull], ["_surfaceIndex", 0], ["_label", ""], ["_vision", "normal"], ["_zoom", 1]];

if (isNull _target) exitWith {};

private _snapshot = [
    format ["%1:%2:%3", clientOwner, round diag_tickTime, floor random 10000],
    _label,
    _target,
    _surfaceIndex,
    name player,
    diag_tickTime,
    _vision,
    _zoom
];

[_snapshot] call Iceman_fnc_toc_addSnapshotLocal;
["Iceman_TOC_snapshot", _snapshot] call CBA_fnc_globalEvent;
