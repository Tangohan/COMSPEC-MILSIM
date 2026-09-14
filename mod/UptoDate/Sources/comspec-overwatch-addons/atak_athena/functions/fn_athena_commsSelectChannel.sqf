/*
    Clic sur un canal : ouvre le fil et marque les messages comme lus.
    Ignoré pendant le rafraîchissement de la liste (sinon le fil se rouvre tout seul).
*/
params ["_ctrl", "_index"];

if (uiNamespace getVariable ["COMSPEC_ATAK_Comms_rebuilding", false]) exitWith {};
if (diag_tickTime < (uiNamespace getVariable ["COMSPEC_ATAK_Comms_ignoreSelUntil", -1])) exitWith {};
if (isNull _ctrl || {_index < 0}) exitWith {};

private _key = toLower (trim (_ctrl lbData _index));
if (_key isEqualTo "") exitWith {};

if (
    (missionNamespace getVariable ["COMSPEC_Comms_View", "list"]) isEqualTo "thread"
    && {(toLower (trim (missionNamespace getVariable ["COMSPEC_Comms_Channel", ""]))) isEqualTo _key}
) exitWith {};

missionNamespace setVariable ["COMSPEC_Comms_Channel", _key, false];
missionNamespace setVariable ["COMSPEC_Comms_View", "thread", false];

private _unread = missionNamespace getVariable ["COMSPEC_Comms_Unread", createHashMap];
if (_unread isEqualType createHashMap) then {
    _unread set [_key, 0];
    missionNamespace setVariable ["COMSPEC_Comms_Unread", _unread, false];
};

uiNamespace setVariable ["COMSPEC_ATAK_Comms_renderSig", ""];
[] call comspec_overwatch_atak_athena_fnc_athena_updateComms;
[] call comspec_overwatch_atak_athena_fnc_athena_commsApplyChrome;
