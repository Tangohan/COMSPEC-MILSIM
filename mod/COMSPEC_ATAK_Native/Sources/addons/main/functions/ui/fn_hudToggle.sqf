/*
    Touche « porter » (Ctrl+U par défaut) : miniature dans un coin de l'écran, on continue à jouer.
    - rangé : la miniature sort ; miniature affichée : rangée complètement ;
    - en main (grand) : le téléphone redevient la miniature (il ne se range pas).
*/
if (!hasInterface) exitWith { false };
// Mesure des performances (fn_perfLog) : l'ouverture se chronomètre depuis l'appui.
if (missionNamespace getVariable ["COMSPEC_ATAK_PerfLog", false]) then { ["key"] call comspec_atak_native_fnc_perfLog; };
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _d = [] call comspec_atak_native_fnc_display;
// Téléphone en train de redescendre (4.2.1, fn_close animé) : rangé tout de suite.
if (!isNull _d && {(uiNamespace getVariable ["COMSPEC_ATAK_Closing", displayNull]) isEqualTo _d}) then {
    [] call comspec_atak_native_fnc_close;
    _d = [] call comspec_atak_native_fnc_display;
};
if (!isNull _d && {_s getOrDefault ["interactive", false]}) exitWith {
    // fn_displayUnload rouvre la miniature au prochain frame.
    uiNamespace setVariable ["COMSPEC_ATAK_HudWanted", true];
    [true] call comspec_atak_native_fnc_close;
    true
};
private _want = isNull _d;
private _why = [true] call comspec_atak_native_fnc_canUse;
if (_want && {_why isNotEqualTo ""}) exitWith { [_why] call comspec_atak_native_fnc_deviceDenied };
uiNamespace setVariable ["COMSPEC_ATAK_HudWanted", _want];
if (_want) then { [false] call comspec_atak_native_fnc_open; } else { [] call comspec_atak_native_fnc_close; };
_want
