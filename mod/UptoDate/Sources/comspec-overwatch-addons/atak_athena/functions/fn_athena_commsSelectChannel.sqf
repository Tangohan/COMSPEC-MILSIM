/*
    Clic sur un canal : ouvre le fil et marque les messages comme lus.
*/
params ["_ctrl", "_index"];

if (uiNamespace getVariable ["COMSPEC_ATAK_Comms_rebuilding", false]) exitWith {};
if (isNull _ctrl || {_index < 0}) exitWith {};

private _key = toLower (trim (_ctrl lbData _index));
if (_key isEqualTo "") exitWith {};

missionNamespace setVariable ["COMSPEC_Comms_Channel", _key, false];
missionNamespace setVariable ["COMSPEC_Comms_View", "thread", false];

private _unread = missionNamespace getVariable ["COMSPEC_Comms_Unread", createHashMap];
if (_unread isEqualType createHashMap) then {
    _unread set [_key, 0];
    missionNamespace setVariable ["COMSPEC_Comms_Unread", _unread, false];
};

uiNamespace setVariable ["COMSPEC_ATAK_Comms_renderSig", ""];
[] call comspec_overwatch_atak_athena_fnc_athena_updateComms;
