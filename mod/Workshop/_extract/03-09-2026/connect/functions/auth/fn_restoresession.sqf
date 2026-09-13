if (!hasInterface) exitWith { false };
private _url = [] call comspec_overwatch_connect_fnc_portalUrl;
private _modVer = [] call comspec_overwatch_connect_fnc_packVersion;
missionNamespace setVariable ["comspec_overwatch_auth_state", "RESTORING_SESSION", false];
private _raw = ["COMSPECExtension" callExtension ["RestoreSession", [_url, _modVer]]] call comspec_overwatch_connect_fnc_extResult;
if (_raw find "OK|READY" == 0) then {
    [] call comspec_overwatch_connect_fnc_applyBootstrap;
    true
} else {
    false
};
