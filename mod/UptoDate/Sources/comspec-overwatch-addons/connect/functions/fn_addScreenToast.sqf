/*
    Toast ATAK (cTab).
    Usage : ["ATHENA", "Message", 4] call ...
            ["ATHENA", "Message", 8, true] call ...  // force même si alertes écran OFF
*/
params ["_tag", "_text", ["_duration", 5], ["_force", false]];

if (!_force && {!([] call comspec_overwatch_connect_fnc_shouldShowScreenNotification)}) exitWith {};
if (isNil "cTab_fnc_addNotification") exitWith {};
[_tag, _text, _duration] call cTab_fnc_addNotification;
