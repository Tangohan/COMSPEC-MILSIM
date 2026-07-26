/*
    Autorise l’ouverture de la tablette / hub Overwatch (hors chrome ATAK Enhanced).
    Params: [_fromAtak] — true si l’appel vient d’ATAK Enhanced (contourne « UI ATAK only »).
*/
params [["_fromAtak", false, [true]]];

if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith { false };
if !([player] call comspec_overwatch_connect_fnc_hasTerminal) exitWith { false };
if (!_fromAtak && {missionNamespace getVariable ["comspec_overwatch_atak_ui_only", false]}) exitWith { false };
true
