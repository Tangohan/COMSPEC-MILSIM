/* Ctrl+Maj+U : prendre le téléphone en main (souris) ou le reposer (retour au HUD s'il était sorti). */
if (!hasInterface) exitWith { false };
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _d = [] call comspec_atak_native_fnc_display;
if (!isNull _d && {_s getOrDefault ["interactive", false]}) exitWith { [] call comspec_atak_native_fnc_close; false };
[true] call comspec_atak_native_fnc_open
