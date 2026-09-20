/*
    Enregistre le Draw3D de l’affichage situation (JVN) et le cadencement du tube.
    Ré-enregistrement sûr : retire l’ancien EH / PFH s’il existe.
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

private _pfh = missionNamespace getVariable ["COMSPEC_EcotiPostFxPFH", -1];
if (_pfh isEqualType 0 && {_pfh >= 0}) then {
    [_pfh] call CBA_fnc_removePerFrameHandler;
};
private _newPfh = [{
    [] call comspec_overwatch_connect_fnc_ecotiTickPostFX;
}, 0.12] call CBA_fnc_addPerFrameHandler;
missionNamespace setVariable ["COMSPEC_EcotiPostFxPFH", _newPfh, false];

["INFO", "ECOTI", "Affichage situation JVN enregistré (Draw3D + tube)"] call comspec_overwatch_connect_fnc_log;
