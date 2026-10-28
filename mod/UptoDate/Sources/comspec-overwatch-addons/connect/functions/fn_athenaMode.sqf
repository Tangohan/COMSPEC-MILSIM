/*
    Choix du joueur « mon unité utilise le service Athéna » (profil Arma, COMSPEC_AthenaMode).
    Retour : "on" (Athena, COMSPEC Link, synchro), "off" (ATAK local, aucune connexion) ou "" (pas encore choisi).
    Mode full local imposé par le serveur (ATAK natif, réglage comspec_atak_native_local_only) : toujours "off".
*/
if (missionNamespace getVariable ["comspec_atak_native_local_only", false]) exitWith { "off" };
private _m = profileNamespace getVariable ["COMSPEC_AthenaMode", ""];
if !(_m isEqualType "") exitWith { "" };
_m = toLower _m;
if (_m in ["on", "off"]) exitWith { _m };
""
