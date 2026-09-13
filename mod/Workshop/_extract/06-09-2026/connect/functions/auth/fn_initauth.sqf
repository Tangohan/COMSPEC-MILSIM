/*
    BOOT → restaure la session, sinon identifiant Steam du joueur.
    Pas de fenêtre de connexion ici (tuile Connexion Athena en secours).
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

if ([] call comspec_overwatch_connect_fnc_restoreSession) exitWith { true };
// Menu principal : pas encore de joueur. La mission relancera Steam (display 46).
if (isNull player && {isNull findDisplay 46}) exitWith { true };
[true] call comspec_overwatch_connect_fnc_loginSteam;
true
