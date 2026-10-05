/*
    Le téléphone du joueur peut-il s'allumer ? Renvoie "" si oui, sinon la raison :
    "item" (pas de terminal), "battery" (batterie vide), "broken" (téléphone détruit : réalisme Overwatch ou dégâts natifs).
    Équipage d'aéronef (pilote, copilote, tourelle) : toujours oui, l'ATAK tourne sur la tablette de bord (fn_aircrewTerminal).
*/
if ([player] call comspec_atak_native_fnc_aircrewTerminal) exitWith { "" };
if !([player] call comspec_atak_native_fnc_hasDevice) exitWith { "item" };
if ((profileNamespace getVariable ["COMSPEC_ATAK_BatterySim", true]) && {(missionNamespace getVariable ["COMSPEC_ATAK_Battery", 100]) <= 0}) exitWith { "battery" };
if ((([] call comspec_atak_native_fnc_deviceHealth) get "state") isEqualTo "BROKEN") exitWith { "broken" };
""
