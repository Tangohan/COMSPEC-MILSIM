if (!hasInterface) exitWith {};

[] call comspec_overwatch_connect_fnc_connect;
[] call comspec_overwatch_connect_fnc_initACE;

[] spawn {
    while { true } do {
        if (comspec_overwatch_enabled) then {
            [player] call comspec_overwatch_connect_fnc_updatePosition;
        };
        sleep (missionNamespace getVariable ["comspec_overwatch_update_interval", 5]);
    };
};
