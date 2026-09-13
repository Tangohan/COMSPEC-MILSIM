/*
    BOOT → restore session ou fenêtre de connexion. Ne démarre pas les flux opérationnels.
*/
if (!hasInterface) exitWith {};
if (missionNamespace getVariable ["COMSPEC_AuthInitStarted", false]) exitWith {};
missionNamespace setVariable ["COMSPEC_AuthInitStarted", true, false];
missionNamespace setVariable ["comspec_overwatch_auth_state", "INITIALIZING", false];
missionNamespace setVariable ["COMSPEC_AthenaReady", false, false];

private _url = [] call comspec_overwatch_connect_fnc_portalUrl;
missionNamespace setVariable ["comspec_overwatch_api_url", _url];

"COMSPECExtension" callExtension "Warmup";
private _init = ["COMSPECExtension" callExtension ["Init", [_url]]] call comspec_overwatch_connect_fnc_extResult;
["INFO", "Athena", format ["Init extension %1", _init]] call comspec_overwatch_connect_fnc_log;

[] call comspec_overwatch_connect_fnc_restoreSession;
true
