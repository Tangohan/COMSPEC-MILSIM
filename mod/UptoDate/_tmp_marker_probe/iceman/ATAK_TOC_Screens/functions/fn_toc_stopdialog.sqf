private _target = uiNamespace getVariable ["Iceman_TOC_target", objNull];
if (isNull _target) exitWith {};

private _settings = call Iceman_fnc_toc_readDialog;
private _surfaceIndex = _settings param [9, 0];

[_target, _surfaceIndex] call Iceman_fnc_toc_syncStopGlobal;
[format ["Stream stopped on surface %1.", _surfaceIndex]] call Iceman_fnc_toc_setStatus;
