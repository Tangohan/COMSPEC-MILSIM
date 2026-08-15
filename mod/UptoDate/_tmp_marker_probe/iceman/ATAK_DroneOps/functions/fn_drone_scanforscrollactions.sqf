{
    if ((_x distance player) < 40 && {[_x] call Iceman_fnc_drone_isSupported}) then {
        if ((_x getVariable ["Iceman_DroneOps_scrollAction", -1]) < 0) then {
            private _id = _x addAction [
                "<t color='#8FE3FF'>Connect ATAK Drone</t>",
                {
                    params ["_target"];
                    [_target] call Iceman_fnc_drone_connect;
                },
                nil,
                1.5,
                true,
                true,
                "",
                "alive _target && {[_target] call Iceman_fnc_drone_canControl}",
                20
            ];
            _x setVariable ["Iceman_DroneOps_scrollAction", _id];
        };
    };
} forEach vehicles;
