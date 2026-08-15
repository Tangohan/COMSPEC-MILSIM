params [["_snapshot", []]];

if (_snapshot isEqualTo []) exitWith {};

private _snapshots = missionNamespace getVariable ["Iceman_TOC_snapshots", []];
private _id = _snapshot param [0, ""];
if (_id != "" && {(_snapshots findIf {(_x param [0, ""]) == _id}) < 0}) then {
    _snapshots pushBack _snapshot;
};

if ((count _snapshots) > 40) then {
    _snapshots = _snapshots select [(count _snapshots) - 40, 40];
};

missionNamespace setVariable ["Iceman_TOC_snapshots", _snapshots];

private _display = uiNamespace getVariable ["Iceman_TOC_viewDeviceDisplay", displayNull];
if (!isNull _display) then {
    [_display] call Iceman_fnc_toc_viewDeviceRefresh;
};
