/*
    Service Athéna choisi par le joueur au premier lancement (COMSPEC Overwatch, fn_athenaChoiceShow).
    Retour : "on" (COMSPEC Link, synchro), "off" (ATAK local, aucune connexion) ou "" (pas encore choisi).
    Mode full local imposé par le serveur (réglage comspec_atak_native_local_only) : toujours "off", sans question.
*/
if (missionNamespace getVariable ["comspec_atak_native_local_only", false]) exitWith { "off" };
if (!isNil "comspec_overwatch_connect_fnc_athenaMode") exitWith { [] call comspec_overwatch_connect_fnc_athenaMode };
private _m = profileNamespace getVariable ["COMSPEC_AthenaMode", ""];
if !(_m isEqualType "") exitWith { "" };
_m = toLower _m;
["", _m] select (_m in ["on", "off"])
