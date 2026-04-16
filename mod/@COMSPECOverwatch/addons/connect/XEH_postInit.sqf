if (!hasInterface) exitWith {};

// Warmup extension (charge la DLL)
"COMSPECExtension" callExtension "Warmup";

["CBA_settingsInitialized", {
    if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};

    [] call comspec_overwatch_connect_fnc_connect;
    [] call comspec_overwatch_connect_fnc_initACE;

    private _interval = missionNamespace getVariable ["comspec_overwatch_position_interval", 0.25];
    [comspec_overwatch_connect_fnc_updatePosition, _interval] call CBA_fnc_addPerFrameHandler;

    // CAS polling: every 10s check for CAS assigned to this callsign
    private _casPollInterval = 10;
    [{
        params ["_args", "_pfhId"];
        if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};
        private _callsign = missionNamespace getVariable ["COMSPEC_Callsign", name player];
        if (_callsign isEqualTo "") then { _callsign = "Pilot"; };
        private _raw = "COMSPECExtension" callExtension ["GetCASForCallsign", [_callsign, "1"]];
        if (_raw isEqualTo "" || {(_raw select [0, 3]) != "OK|"}) exitWith {};
        private _payload = _raw select [3, count _raw - 3];
        private _lastPayload = missionNamespace getVariable ["COMSPEC_LastCASPayload", ""];
        if (_payload != "" && {_payload != _lastPayload}) then {
            missionNamespace setVariable ["COMSPEC_LastCASPayload", _payload];
            missionNamespace setVariable ["COMSPEC_CAS_Raw", _payload];
            [] call comspec_overwatch_connect_fnc_receiveCASRequest;
            ["Nouvelle demande CAS reçue"] call BIS_fnc_showNotification;
        };
    }, _casPollInterval, []] call CBA_fnc_addPerFrameHandler;

    // Map shapes polling: every 10s fetch shapes and update local markers
    [{
        if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};
        [] call comspec_overwatch_connect_fnc_pollMapShapes;
    }, 10, []] call CBA_fnc_addPerFrameHandler;

    [] spawn comspec_overwatch_connect_fnc_playtimeTracker;
}] call CBA_fnc_addEventHandler;
