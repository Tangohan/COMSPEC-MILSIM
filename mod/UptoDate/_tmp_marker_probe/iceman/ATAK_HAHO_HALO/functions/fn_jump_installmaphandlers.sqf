#include "..\script_component.hpp"

params ["_display"];

{
    private _map = _display displayCtrl _x;
    if (isNull _map) then {continue};

    private _old = _map getVariable ["Iceman_Jump_MouseEH", -1];
    if (_old >= 0) then {
        _map ctrlRemoveEventHandler ["MouseButtonClick", _old];
    };
    private _new = _map ctrlAddEventHandler ["MouseButtonClick", {_this call Iceman_fnc_jump_onMapClick}];
    _map setVariable ["Iceman_Jump_MouseEH", _new];

    private _drawOld = _map getVariable ["Iceman_Jump_DrawEH", -1];
    if (_drawOld >= 0) then {
        _map ctrlRemoveEventHandler ["Draw", _drawOld];
    };
    private _drawNew = _map ctrlAddEventHandler ["Draw", {_this call Iceman_fnc_jump_draw}];
    _map setVariable ["Iceman_Jump_DrawEH", _drawNew];
} forEach [1201, 1202];
