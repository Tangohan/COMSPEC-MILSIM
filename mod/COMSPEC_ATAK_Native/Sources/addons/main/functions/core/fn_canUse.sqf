/*
    Le téléphone du joueur peut-il s'allumer ? Renvoie "" si oui, sinon la raison :
    "item" (pas de terminal), "battery" (batterie vide), "broken" (téléphone détruit, réalisme Overwatch).
*/
if !([player] call comspec_atak_native_fnc_hasDevice) exitWith { "item" };
if ((profileNamespace getVariable ["COMSPEC_ATAK_BatterySim", true]) && {(missionNamespace getVariable ["COMSPEC_ATAK_Battery", 100]) <= 0}) exitWith { "battery" };
private _st = missionNamespace getVariable ["COMSPEC_AtakState", createHashMap];
if (_st isEqualType createHashMap && {_st getOrDefault ["device_destroyed", false]}) exitWith { "broken" };
""
