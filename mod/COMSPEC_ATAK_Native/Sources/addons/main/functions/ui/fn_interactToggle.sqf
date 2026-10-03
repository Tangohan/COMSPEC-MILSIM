/*
    Touche « interagir » (Ctrl+Maj+U par défaut), comme dans le mod d'origine :
    - téléphone pas en main : il sort en mini (s'il était rangé) et prend la souris, à sa place ;
    - téléphone en main : on relâche la souris, il reste affiché en mini et l'on continue à jouer.
    Le plein écran reste accessible avec le bouton d'agrandissement du téléphone.
*/
if (!hasInterface) exitWith { false };
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _d = [] call comspec_atak_native_fnc_display;
if (!isNull _d && {_s getOrDefault ["interactive", false]}) exitWith { [] call comspec_atak_native_fnc_close; false };
private _why = [] call comspec_atak_native_fnc_canUse;
if (_why isNotEqualTo "") exitWith { [_why] call comspec_atak_native_fnc_deviceDenied };
uiNamespace setVariable ["COMSPEC_ATAK_HudWanted", true];
profileNamespace setVariable ["COMSPEC_ATAK_Mode", "MINI"];
[true] call comspec_atak_native_fnc_open
