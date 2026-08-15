params ["_display", ["_drawContacts", false]];

{
    private _map = _display displayCtrl _x;
    if (!isNull _map) then {

        if ((_map getVariable ["Iceman_DroneOps_MouseEH", -1]) < 0) then {
            private _new = _map ctrlAddEventHandler ["MouseButtonClick", {_this call Iceman_fnc_drone_onMapClick}];
            _map setVariable ["Iceman_DroneOps_MouseEH", _new];
        };

        if (_drawContacts && {(_map getVariable ["Iceman_DroneOps_DrawEH", -1]) < 0}) then {
            private _draw = _map ctrlAddEventHandler ["Draw", {_this call Iceman_fnc_drone_draw}];
            _map setVariable ["Iceman_DroneOps_DrawEH", _draw];
        };
    };
} forEach [1201, 1202, 1203];
