/*
    Ancienne tablette Chromium Overwatch.
    Avec « UI uniquement via ATAK » : ouvre la connexion native (Rsc), jamais le HTML.
*/
if (!hasInterface) exitWith {};

if (missionNamespace getVariable ["comspec_overwatch_atak_ui_only", true]) exitWith {
    if (!isNil "comspec_overwatch_connect_fnc_openLogin") then {
        [] call comspec_overwatch_connect_fnc_openLogin;
    };
};

if (!isNil "comspec_overwatch_connect_fnc_webBrowserShow") then {
    [true] call comspec_overwatch_connect_fnc_webBrowserShow;
} else {
    ["ATHENA", "Tablette Overwatch indisponible.", 5] call comspec_overwatch_connect_fnc_addScreenToast;
};
