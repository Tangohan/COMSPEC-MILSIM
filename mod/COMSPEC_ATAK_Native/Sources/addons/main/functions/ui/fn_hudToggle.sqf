/* Ctrl+U : sortir / ranger le téléphone porté (mini, on continue à jouer). */
if (!hasInterface) exitWith { false };
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _d = [] call comspec_atak_native_fnc_display;
if (!isNull _d && {_s getOrDefault ["interactive", false]}) exitWith {
    uiNamespace setVariable ["COMSPEC_ATAK_HudWanted", false];
    [] call comspec_atak_native_fnc_close;
    false
};
private _want = !(uiNamespace getVariable ["COMSPEC_ATAK_HudWanted", false]);
if (_want && {!([] call comspec_atak_native_fnc_hasDevice)}) exitWith { [] call comspec_atak_native_fnc_deviceDenied };
uiNamespace setVariable ["COMSPEC_ATAK_HudWanted", _want];
if (_want) then { [false] call comspec_atak_native_fnc_open; } else { [] call comspec_atak_native_fnc_close; };
_want
