private _group = uiNamespace getVariable ["Iceman_ATAK_PhotoLibrary_group", controlNull];
if (isNull _group) exitWith {createHashMap};

private _controls = createHashMap;
{
    private _ctrl = _group controlsGroupCtrl _x;
    if (!isNull _ctrl) then {
        _controls set [str _x, _ctrl];
    };
} forEach [9400, 9401, 9410, 9420, 9421, 9430, 9431, 9440, 9441, 9442, 9443, 9444];

_controls
