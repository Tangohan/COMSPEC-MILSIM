private _group = uiNamespace getVariable ["Iceman_ATAK_Aware_group", controlNull];
if (isNull _group) exitWith {createHashMap};

private _controls = createHashMap;
{
    private _ctrl = _group controlsGroupCtrl _x;
    if (!isNull _ctrl) then {
        _controls set [str _x, _ctrl];
    };
} forEach [9201, 9210, 9211, 9212, 9220, 9230];

_controls
