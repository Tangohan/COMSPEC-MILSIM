/*
    Choix du joueur « mon unité utilise le service Athéna » (profil Arma, COMSPEC_AthenaMode).
    Retour : "on" (Athena, COMSPEC Link, synchro), "off" (ATAK local, aucune connexion) ou "" (pas encore choisi).
*/
private _m = profileNamespace getVariable ["COMSPEC_AthenaMode", ""];
if !(_m isEqualType "") exitWith { "" };
_m = toLower _m;
if (_m in ["on", "off"]) exitWith { _m };
""
