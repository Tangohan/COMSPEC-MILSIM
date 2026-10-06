/*
    Touche « porter » (Ctrl+U par défaut) : miniature dans un coin de l'écran, on continue à jouer.
    - rangé : la miniature sort ; miniature affichée : rangée complètement ;
    - en main (grand) : le téléphone redevient la miniature (il ne se range pas).
*/
if (!hasInterface) exitWith { false };
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _d = [] call comspec_atak_native_fnc_display;
if (!isNull _d && {_s getOrDefault ["interactive", false]}) exitWith {
    // fn_displayUnload rouvre la miniature au prochain frame.
    uiNamespace setVariable ["COMSPEC_ATAK_HudWanted", true];
    [] call comspec_atak_native_fnc_close;
    true
};
private _want = isNull _d;
private _why = [true] call comspec_atak_native_fnc_canUse;
if (_want && {_why isNotEqualTo ""}) exitWith { [_why] call comspec_atak_native_fnc_deviceDenied };
uiNamespace setVariable ["COMSPEC_ATAK_HudWanted", _want];
if (_want) then { [false] call comspec_atak_native_fnc_open; } else { [] call comspec_atak_native_fnc_close; };
_want
