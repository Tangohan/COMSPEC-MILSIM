/*
    Sélection d’un canal dans la liste Messagerie.
*/
params ["_ctrl", "_index"];

if (uiNamespace getVariable ["COMSPEC_ATAK_Comms_rebuilding", false]) exitWith {};
if (isNull _ctrl || {_index < 0}) exitWith {};

private _key = toLower (trim (_ctrl lbData _index));
if (_key isEqualTo "") exitWith {};

missionNamespace setVariable ["COMSPEC_Comms_Channel", _key, false];
[] call comspec_overwatch_atak_athena_fnc_athena_updateComms;
