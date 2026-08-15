#include "..\script_component.hpp"

private _group = uiNamespace getVariable ["Iceman_ATAK_BDA_group", controlNull];
if (isNull _group) exitWith {false};

{
    private _ctrl = _group controlsGroupCtrl _x;
    if (!isNull _ctrl) then {
        _ctrl ctrlSetText "";
    };
} forEach [9711, 9715, 9717, 9719, 9721, 9723];

private _grid = _group controlsGroupCtrl 9713;
if (!isNull _grid) then {
    _grid ctrlSetText (mapGridPosition (getPosASL player));
};

call Iceman_fnc_bda_updatePanel;
["BDA", "BDA form cleared.", 3] call cTab_fnc_addNotification;
true
