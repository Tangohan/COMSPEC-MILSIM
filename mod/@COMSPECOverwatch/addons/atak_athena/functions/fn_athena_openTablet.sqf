/*
    Ouvre la tablette Chromium Overwatch (hub Athena complet).
*/
if (!hasInterface) exitWith {};

if (!isNil "comspec_overwatch_connect_fnc_webBrowserShow") then {
    [] call comspec_overwatch_connect_fnc_webBrowserShow;
} else {
    ["ATHENA", "Tablette Overwatch indisponible.", 5] call cTab_fnc_addNotification;
};
