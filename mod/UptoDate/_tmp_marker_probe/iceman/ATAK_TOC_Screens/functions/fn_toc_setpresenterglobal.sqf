params [["_enabled", true], ["_target", objNull], ["_surfaceIndex", 0], ["_label", ""]];

private _state = [_enabled, _target, _surfaceIndex, name player, _label, diag_tickTime];
missionNamespace setVariable ["Iceman_TOC_presenterState", _state, true];
["Iceman_TOC_present", _state] call CBA_fnc_globalEvent;
