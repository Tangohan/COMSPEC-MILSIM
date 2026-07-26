/*
    Ouvre la tablette Chromium Overwatch (hub Athena complet).
    Depuis ATAK Enhanced → contourne le réglage « UI uniquement via ATAK ».
*/
if (!hasInterface) exitWith {};

if (!isNil "comspec_overwatch_connect_fnc_webBrowserShow") then {
    [true] call comspec_overwatch_connect_fnc_webBrowserShow;
} else {
    ["ATHENA", "Tablette Overwatch indisponible.", 5] call comspec_overwatch_connect_fnc_addScreenToast;
};
