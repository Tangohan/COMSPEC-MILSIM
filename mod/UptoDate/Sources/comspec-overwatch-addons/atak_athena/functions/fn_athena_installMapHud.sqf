/*
    Chrome HUD carte ATAK Enhanced (IceMan / BCE) : cartouches curseur + unité,
    fonds charbon / cyan. Pas un GCS Reaper : on habille la carte
    et le tiroir que COMSPEC peut toucher. Le bouton Map Tools IceMan n’est pas touché.
*/
if (!hasInterface) exitWith {};
if (!isNil "COMSPEC_ATAK_MapHud_PFH") exitWith {};

diag_log "[COMSPEC][MAP] Waiting for ATAK map display";
if (!isNil "comspec_overwatch_atak_athena_fnc_mapUIInit") then {
    [] call comspec_overwatch_atak_athena_fnc_mapUIInit;
};

COMSPEC_ATAK_MapHud_PFH = [{
    [] call comspec_overwatch_atak_athena_fnc_athena_updateMapHud;
}, 0.5, []] call CBA_fnc_addPerFrameHandler;

diag_log "[COMSPEC][MAP] pollMarkersAndUnits n'est pas utilisé — HUD ATAK + mapUI";

[{
    [] call comspec_overwatch_atak_athena_fnc_athena_updateMapHud;
}, [], 0.4] call CBA_fnc_waitAndExecute;
[{
    [] call comspec_overwatch_atak_athena_fnc_athena_updateMapHud;
}, [], 1.6] call CBA_fnc_waitAndExecute;
