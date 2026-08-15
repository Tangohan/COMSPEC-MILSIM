private _display = uiNamespace getVariable ["cTab_Android_dlg", displayNull];
private _pageGroup = uiNamespace getVariable ["Iceman_ATAK_WaveRelay_group", controlNull];
if (isNull _display && {!isNull _pageGroup}) then {
    _display = ctrlParent _pageGroup;
};

if (isNull _pageGroup && {!isNull _display}) then {
    private _title = controlNull;
    {
        if ((ctrlIDC _x) == 9000 && {(ctrlText _x) == "Wave Relay"}) exitWith {
            _title = _x;
        };
    } forEach allControls _display;

    if (!isNull _title) then {
        _pageGroup = ctrlParentControlsGroup _title;
        if (!isNull _pageGroup) then {
            uiNamespace setVariable ["Iceman_ATAK_WaveRelay_group", _pageGroup];
        };
    };
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
    ["title", 9000],
    ["status", 9001],
    ["tabHome", 9010],
    ["tabTalkgroups", 9011],
    ["tabFeeds", 9012],
    ["tabGateways", 9013],
    ["tabPli", 9014],
    ["tabDiag", 9015],
    ["list", 9020],
    ["detail", 9021],
    ["actionOne", 9030],
    ["actionTwo", 9031],
    ["actionThree", 9032],
    ["actionFour", 9033],
    ["actionFive", 9034],
    ["actionSix", 9035],
    ["frequency", 9040],
    ["profile", 9041]
];

_result
