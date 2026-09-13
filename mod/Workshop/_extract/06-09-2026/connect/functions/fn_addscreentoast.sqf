/*
    Toast ATAK (cTab) si les notifications à l’écran sont activées.
    Usage : ["ATHENA", "Message", 4] call comspec_overwatch_connect_fnc_addScreenToast;
*/
params ["_tag", "_text", ["_duration", 5]];

if !([] call comspec_overwatch_connect_fnc_shouldShowScreenNotification) exitWith {};
if (isNil "cTab_fnc_addNotification") exitWith {};
[_tag, _text, _duration] call cTab_fnc_addNotification;
