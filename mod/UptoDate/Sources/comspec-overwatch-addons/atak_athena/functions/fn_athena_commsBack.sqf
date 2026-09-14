/*
    Retour à la liste des canaux depuis un fil.
*/
if (!hasInterface) exitWith {};

missionNamespace setVariable ["COMSPEC_Comms_View", "list", false];
uiNamespace setVariable ["COMSPEC_ATAK_Comms_renderSig", ""];
[] call comspec_overwatch_atak_athena_fnc_athena_updateComms;
