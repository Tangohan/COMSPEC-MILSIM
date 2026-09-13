/*
    Ouvre l’app Athena dans ATAK Enhanced (couche cTAB), avec repli téléphone ATAK.
    Params: [_tab] — all|bda|photo|order|messages|urgences|liaison|modules|notif|…
*/
params [["_tab", "all", [""]]];

if (!hasInterface) exitWith { false };
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith { false };

if (!isNil "comspec_overwatch_atak_athena_fnc_athena_openFeature") exitWith {
    [_tab] call comspec_overwatch_atak_athena_fnc_athena_openFeature;
    true
};

[] call comspec_overwatch_connect_fnc_openAtakEnhanced
