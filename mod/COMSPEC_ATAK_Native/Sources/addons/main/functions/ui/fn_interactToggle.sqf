/*
    Touche « prendre en main / interagir » (Ctrl+Maj+U par défaut, aussi la touche principale Alt+U), comme le mod d'origine :
    - téléphone rangé ou porté en miniature : il passe directement en grand (plein écran, souris), sans fermer d'abord ;
    - téléphone en main : il redevient la miniature portée (on continue à jouer), il ne se range pas.
    Ranger complètement : touche « porter » (Ctrl+U) depuis la miniature.
*/
if (!hasInterface) exitWith { false };
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _d = [] call comspec_atak_native_fnc_display;
// En main -> miniature : fn_displayUnload rouvre le téléphone porté (COMSPEC_ATAK_HudWanted).
if (!isNull _d && {_s getOrDefault ["interactive", false]}) exitWith {
    uiNamespace setVariable ["COMSPEC_ATAK_HudWanted", true];
    [] call comspec_atak_native_fnc_close;
    false
};
private _why = [true] call comspec_atak_native_fnc_canUse;
if (_why isNotEqualTo "") exitWith { [_why] call comspec_atak_native_fnc_deviceDenied };
uiNamespace setVariable ["COMSPEC_ATAK_HudWanted", true];
profileNamespace setVariable ["COMSPEC_ATAK_Mode", "FULL"];
[true] call comspec_atak_native_fnc_open
