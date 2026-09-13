/*
    Enregistre le Draw3D de l’affichage situation (JVN).
    Ré-enregistrement sûr : retire l’ancien EH s’il existe.
*/
if (!hasInterface) exitWith {};

private _prev = missionNamespace getVariable ["COMSPEC_EcotiDrawEH", -1];
if (_prev isEqualType 0 && {_prev >= 0}) then {
    removeMissionEventHandler ["Draw3D", _prev];
};

COMSPEC_EcotiDrawEH = addMissionEventHandler ["Draw3D", {
    call comspec_overwatch_connect_fnc_ecotiDraw;
}];
missionNamespace setVariable ["COMSPEC_EcotiDrawEH", COMSPEC_EcotiDrawEH, false];

["INFO", "ECOTI", "Affichage situation JVN enregistré (Draw3D)"] call comspec_overwatch_connect_fnc_log;
