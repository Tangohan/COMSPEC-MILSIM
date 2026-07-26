/*
    Poll les modules activables depuis Athena → COMSPEC_AthenaModules.
*/
if (!hasInterface) exitWith {};
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};
if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith {};

private _raw = ["COMSPECExtension" callExtension ["GetModModules", []]] call comspec_overwatch_connect_fnc_extResult;
if (_raw isEqualTo "" || {(_raw select [0, 3]) != "OK|"}) exitWith {};

private _payload = _raw select [3, count _raw - 3];
private _last = missionNamespace getVariable ["COMSPEC_AthenaModulesRaw", ""];
if (_payload isEqualTo _last) exitWith {};
missionNamespace setVariable ["COMSPEC_AthenaModulesRaw", _payload, false];

private _nl = toString [10];
private _lines = if (_payload isEqualTo "") then { [] } else { _payload splitString _nl };
private _map = createHashMap;
private _labels = createHashMap;
private _on = 0;
private _off = 0;

{
    private _line = trim _x;
    if (_line isEqualTo "") then { continue };
    private _parts = _line splitString toString [9];
    if ((count _parts) < 2) then { continue };
    private _id = _parts select 0;
    private _en = (_parts select 1) isEqualTo "1";
    private _label = if ((count _parts) > 2) then { _parts select 2 } else { _id };
    _map set [_id, _en];
    _labels set [_id, _label];
    if (_en) then { _on = _on + 1 } else { _off = _off + 1 };
} forEach _lines;

missionNamespace setVariable ["COMSPEC_AthenaModules", _map, false];
missionNamespace setVariable ["COMSPEC_AthenaModuleLabels", _labels, false];

[format ["Modules synchronisés · %1 actifs · %2 désactivés", _on, _off]] call comspec_overwatch_connect_fnc_appendModuleLog;

private _group = uiNamespace getVariable ["COMSPEC_ATAK_Athena_group", controlNull];
if (!isNull _group && {ctrlShown _group}) then {
    [] call comspec_overwatch_atak_athena_fnc_athena_updatePanel;
};
