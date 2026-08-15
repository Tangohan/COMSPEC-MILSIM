private _display = uiNamespace getVariable ["cTab_Android_dlg", displayNull];
private _pageGroup = uiNamespace getVariable ["Iceman_ATAK_ROIP_group", controlNull];
if (isNull _display && {!isNull _pageGroup}) then {_display = ctrlParent _pageGroup};

if (isNull _pageGroup && {!isNull _display}) then {
    private _title = controlNull;
    {
        if ((ctrlIDC _x) == 9200 && {(ctrlText _x) == "ROIP"}) exitWith {_title = _x};
    } forEach allControls _display;
    if (!isNull _title) then {
        _pageGroup = ctrlParentControlsGroup _title;
        if (!isNull _pageGroup) then {uiNamespace setVariable ["Iceman_ATAK_ROIP_group", _pageGroup]};
    };
};

private _controls = if (!isNull _pageGroup) then {allControls _pageGroup} else {if (!isNull _display) then {allControls _display} else {[]}};
private _find = {
    params ["_idc", "_controls"];
    private _found = controlNull;
    {if ((ctrlIDC _x) == _idc) exitWith {_found = _x}} forEach _controls;
    _found
};

private _result = createHashMap;
{
    _x params ["_name", "_idc"];
    _result set [_name, [_idc, _controls] call _find];
} forEach [
    ["title", 9200],
    ["status", 9201],
    ["radioList", 9210],
    ["tgList", 9220],
    ["detail", 9221],
    ["refresh", 9230],
    ["connect", 9231],
    ["disconnect", 9232]
];

_result
