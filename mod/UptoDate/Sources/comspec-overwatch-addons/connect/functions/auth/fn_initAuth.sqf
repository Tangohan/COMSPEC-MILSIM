/*
    BOOT → restaure la session jeu, sinon Steam, sinon clé Appairer (profil Arma).
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
if ([true] call comspec_overwatch_connect_fnc_loginSteam) exitWith { true };

// Appairer persiste la clé communauté dans le profil : la reprendre après retour lobby / JIP.
private _savedKey = profileNamespace getVariable ["comspec_overwatch_saved_api_key", ""];
if (!(_savedKey isEqualType "")) then { _savedKey = ""; };
_savedKey = trim _savedKey;
if ((count _savedKey) < 16) exitWith { true };

["INFO", "Athena", "Reprise liaison Appairer (profil)"] call comspec_overwatch_connect_fnc_log;
[] call comspec_overwatch_connect_fnc_connect;
[] call comspec_overwatch_connect_fnc_applyBootstrap;
if ([] call comspec_overwatch_connect_fnc_isReady) then {
    ["INFO", "Athena", "Liaison Appairer reprise — pas de nouveau code"] call comspec_overwatch_connect_fnc_log;
};
true
