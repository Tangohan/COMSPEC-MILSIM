/*
    Journal modules / données (tablette + cTab Athena).
    Params: [_line]
*/
params [["_line", "", [""]]];
if (_line isEqualTo "") exitWith {};

private _time = [daytime, "HH:MM:SS"] call BIS_fnc_timeToString;
private _entry = format ["[%1] %2", _time, _line];

private _log = missionNamespace getVariable ["COMSPEC_ModuleLog", []];
if (!(_log isEqualType [])) then { _log = []; };
_log pushBack _entry;
if ((count _log) > 60) then {
    _log = _log select [(count _log) - 60, 60];
};
missionNamespace setVariable ["COMSPEC_ModuleLog", _log, false];

// Rafraîchir panneau cTab si l’onglet modules est ouvert
private _group = uiNamespace getVariable ["COMSPEC_ATAK_Athena_group", controlNull];
if (!isNull _group && {ctrlShown _group}) then {
    private _tab = missionNamespace getVariable ["COMSPEC_Athena_PanelTab", "all"];
    if (_tab isEqualTo "modules") then {
        [] call comspec_overwatch_atak_athena_fnc_athena_updatePanel;
    };
};
