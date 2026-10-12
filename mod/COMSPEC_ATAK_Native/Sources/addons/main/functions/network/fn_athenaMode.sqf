/*
    Service Athéna choisi par le joueur au premier lancement (COMSPEC Overwatch, fn_athenaChoiceShow).
    Retour : "on" (COMSPEC Link, synchro), "off" (ATAK local, aucune connexion) ou "" (pas encore choisi).
*/
if (!isNil "comspec_overwatch_connect_fnc_athenaMode") exitWith { [] call comspec_overwatch_connect_fnc_athenaMode };
private _m = profileNamespace getVariable ["COMSPEC_AthenaMode", ""];
if !(_m isEqualType "") exitWith { "" };
_m = toLower _m;
["", _m] select (_m in ["on", "off"])
