/*
    Le téléphone du joueur peut-il s'allumer ? Renvoie "" si oui, sinon la raison :
    "down" (joueur mort ou inconscient : le téléphone se range), "item" (pas de terminal), "battery" (batterie vide),
    "broken" (téléphone détruit, sauf avec _allowBroken).
    Params : [_allowBroken (false)] — true pour sortir / afficher le téléphone : détruit, il s'affiche quand même, écran
    éclaté et inutilisable (fn_deviceOverlay), jusqu'à réparation ou changement. Les apps (musique, live cam) restent refusées.
    Équipage d'aéronef (pilote, copilote, tourelle) : oui (sauf mort ou inconscient), l'ATAK tourne sur la tablette de bord (fn_aircrewTerminal).
*/
params [["_allowBroken", false]];
if (!alive player || {lifeState player isEqualTo "INCAPACITATED"} || {player getVariable ["ACE_isUnconscious", false]}) exitWith { "down" };
if ([player] call comspec_atak_native_fnc_aircrewTerminal) exitWith { "" };
if !([player] call comspec_atak_native_fnc_hasDevice) exitWith { "item" };
if ((profileNamespace getVariable ["COMSPEC_ATAK_BatterySim", true]) && {(missionNamespace getVariable ["COMSPEC_ATAK_Battery", 100]) <= 0}) exitWith { "battery" };
if (!_allowBroken && {(([] call comspec_atak_native_fnc_deviceHealth) get "state") isEqualTo "BROKEN"}) exitWith { "broken" };
""
