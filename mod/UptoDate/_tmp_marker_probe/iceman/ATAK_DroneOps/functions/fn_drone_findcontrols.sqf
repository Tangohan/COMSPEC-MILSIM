private _display = uiNamespace getVariable ["cTab_Android_dlg", displayNull];
private _pageGroup = uiNamespace getVariable ["Iceman_ATAK_DroneOps_group", controlNull];
if (isNull _display && {!isNull _pageGroup}) then {
    _display = ctrlParent _pageGroup;
};

private _scanControls = if (!isNull _pageGroup) then {
    allControls _pageGroup
} else {
    if (!isNull _display) then {allControls _display} else {[]}
};

private _find = {
    params ["_idc", "_controls"];
    private _found = controlNull;
    {
        if ((ctrlIDC _x) == _idc) exitWith {_found = _x};
    } forEach _controls;
    _found
};

private _result = createHashMap;
{
    _x params ["_name", "_idc"];
    _result set [_name, [_idc, _scanControls] call _find];
} forEach [
    ["drone", 8801],
    ["grid", 8811],
    ["altitude", 8821],
    ["function", 8831],
    ["radius", 8841],
    ["status", 8860],
    ["info", 8861]
];

_result
