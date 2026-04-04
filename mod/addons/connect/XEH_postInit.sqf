if (!hasInterface) exitWith {};

// Warmup extension (charge la DLL)
"COMSPECExtension" callExtension "Warmup";

["CBA_settingsInitialized", {
    if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};

    [] call comspec_overwatch_connect_fnc_connect;
    [] call comspec_overwatch_connect_fnc_initACE;

    missionNamespace setVariable ["comspec_overwatch_ready", true];
    diag_log "[COMSPEC] Overwatch postInit complete.";

    private _interval = missionNamespace getVariable ["comspec_overwatch_position_interval", 0.25];
    [comspec_overwatch_connect_fnc_updatePosition, _interval] call CBA_fnc_addPerFrameHandler;
}] call CBA_fnc_addEventHandler;
