/*
    Enregistre le Draw3D de l’affichage situation (JVN).
*/
if (!hasInterface) exitWith {};
if (!isNil "COMSPEC_EcotiDrawEH") exitWith {};

COMSPEC_EcotiDrawEH = addMissionEventHandler ["Draw3D", {
    call comspec_overwatch_connect_fnc_ecotiDraw;
}];

["INFO", "ECOTI", "Affichage situation JVN enregistré (Draw3D)"] call comspec_overwatch_connect_fnc_log;
