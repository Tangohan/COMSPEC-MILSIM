/*
    Chrome HUD carte ATAK Enhanced (IceMan / BCE) : cartouches curseur + unité,
    cap +/−, fonds charbon / cyan. Pas un GCS Reaper : on habille la carte
    et le tiroir que COMSPEC peut toucher.
*/
if (!hasInterface) exitWith {};
if (!isNil "COMSPEC_ATAK_MapHud_PFH") exitWith {};

COMSPEC_ATAK_MapHud_PFH = [{
    [] call comspec_overwatch_atak_athena_fnc_athena_updateMapHud;
}, 0.12, []] call CBA_fnc_addPerFrameHandler;

[{
    [] call comspec_overwatch_atak_athena_fnc_athena_updateMapHud;
}, [], 0.4] call CBA_fnc_waitAndExecute;
[{
    [] call comspec_overwatch_atak_athena_fnc_athena_updateMapHud;
}, [], 1.6] call CBA_fnc_waitAndExecute;
