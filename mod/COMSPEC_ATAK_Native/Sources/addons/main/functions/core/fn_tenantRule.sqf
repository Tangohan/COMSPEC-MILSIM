/*
    Règle imposée par la communauté (tenant Athena) pour un réglage, lue dans l'expérience synchronisée par COMSPEC Link.
    Params : [clé du catalogue web, par ex. "native_compass"]
    Renvoie "player" (choix laissé au joueur), "on", "off" ou une valeur de liste ("1", "2"…).
*/
params [["_key", ""]];
private _map = missionNamespace getVariable ["COMSPEC_TenantExperience", createHashMap];
if !(_map isEqualType createHashMap) exitWith { "player" };
private _v = _map getOrDefault [_key, "player"];
if !(_v isEqualType "") exitWith { "player" };
_v = toLower trim _v;
["player", _v] select (_v isNotEqualTo "")
