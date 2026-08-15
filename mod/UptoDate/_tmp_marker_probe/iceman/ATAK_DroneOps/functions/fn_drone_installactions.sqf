if !(isNil "ace_interact_menu_fnc_createAction") then {
    private _action = [
        "Iceman_DroneOps_Connect",
        "Connect to ATAK",
        "\A3\ui_f\data\map\markers\nato\b_uav.paa",
        {
            params ["_target"];
            [_target] call Iceman_fnc_drone_connect;
        },
        {
            params ["_target"];
            [_target] call Iceman_fnc_drone_isSupported && {[_target] call Iceman_fnc_drone_canControl}
        }
    ] call ace_interact_menu_fnc_createAction;
    _action resize 11;

    {
        [_x, 0, ["ACE_MainActions"], _action, true] call ace_interact_menu_fnc_addActionToClass;
    } forEach ["UAV", "UAV_01_base_F", "B_UAV_01_F", "O_UAV_01_F", "I_UAV_01_F"];
};

call Iceman_fnc_drone_scanForScrollActions;
