/*
    Le téléphone du joueur peut-il recevoir un SMS maintenant ? Faux sans téléphone, batterie vide
    ou sans aucun signal (simulation de débit active). Retourne un booléen.
*/
if !([player] call comspec_atak_native_fnc_hasDevice) exitWith { false };
if ((missionNamespace getVariable ["COMSPEC_ATAK_Battery", 100]) <= 0) exitWith { false };
private _q = [] call comspec_atak_native_fnc_linkQuality;
!((_q getOrDefault ["sim", false]) && {(_q getOrDefault ["bars", 4]) isEqualTo 0})
