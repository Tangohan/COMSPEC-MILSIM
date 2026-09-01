if (!hasInterface) exitWith { false };
private _url = [] call comspec_overwatch_connect_fnc_portalUrl;
private _modVer = "1.5.0";
missionNamespace setVariable ["comspec_overwatch_auth_state", "RESTORING_SESSION", false];
private _raw = ["COMSPECExtension" callExtension ["RestoreSession", [_url, _modVer]]] call comspec_overwatch_connect_fnc_extResult;
if (_raw find "OK|READY" == 0) then {
    [] call comspec_overwatch_connect_fnc_applyBootstrap;
    true
} else {
    [] spawn {
        uiSleep 0.4;
        if !([] call comspec_overwatch_connect_fnc_isReady) then {
            [] call comspec_overwatch_connect_fnc_openLogin;
        };
    };
    false
};
