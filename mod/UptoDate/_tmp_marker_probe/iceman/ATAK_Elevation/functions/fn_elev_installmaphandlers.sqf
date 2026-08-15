#include "..\script_component.hpp"

params ["_display"];
{
    private _map = _display displayCtrl _x;
    if (isNull _map) then {continue};

    if ((_map getVariable ["Iceman_Elev_MouseEH", -1]) < 0) then {
        private _new = _map ctrlAddEventHandler ["MouseButtonClick", {_this call Iceman_fnc_elev_onMapClick}];
        _map setVariable ["Iceman_Elev_MouseEH", _new];
    };

    if ((_map getVariable ["Iceman_Elev_DrawEH", -1]) < 0) then {
        private _draw = _map ctrlAddEventHandler ["Draw", {_this call Iceman_fnc_elev_draw}];
        _map setVariable ["Iceman_Elev_DrawEH", _draw];
    };
} forEach [1201, 1202, 1203];
