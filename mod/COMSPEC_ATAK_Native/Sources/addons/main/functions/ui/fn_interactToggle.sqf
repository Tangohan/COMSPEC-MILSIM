/*
    Touche « prendre en main / interagir » (Ctrl+Maj+U par défaut, aussi la touche principale Alt+U), comme le mod d'origine :
    - téléphone rangé ou porté en miniature : il passe directement en grand (plein écran, souris), sans fermer d'abord ;
    - téléphone en main : il redevient la miniature portée (on continue à jouer), il ne se range pas.
    Ranger complètement : touche « porter » (Ctrl+U) depuis la miniature.
*/
if (!hasInterface) exitWith { false };
// Même appui vu deux fois dans la même image (raccourci CBA et fenêtre de bord, fn_vehicleKeys ; ou deux touches réglées
// pareil) : un seul basculement.
if ((uiNamespace getVariable ["COMSPEC_ATAK_ToggleFrame", -1]) isEqualTo diag_frameNo) exitWith { false };
uiNamespace setVariable ["COMSPEC_ATAK_ToggleFrame", diag_frameNo];
// État réparé avant d'ouvrir (displays orphelins, interaction miniature restée active : fn_vehicleKeys « heal »).
["heal"] call comspec_atak_native_fnc_vehicleKeys;
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _d = [] call comspec_atak_native_fnc_display;
// En main mais caché sous une fenêtre de cockpit ou d'un autre mod (2.9.41) : remis au premier plan au lieu d'être rangé.
if (!isNull _d && {_s getOrDefault ["interactive", false]} && {["covered", _d] call comspec_atak_native_fnc_vehicleKeys}) exitWith {
    ["INFO", "ATAK", "Téléphone en main caché sous une autre fenêtre : rouvert au premier plan"] call comspec_atak_native_fnc_log;
    [true] call comspec_atak_native_fnc_open
};
// En main -> miniature : fn_displayUnload rouvre le téléphone porté (COMSPEC_ATAK_HudWanted).
if (!isNull _d && {_s getOrDefault ["interactive", false]}) exitWith {
    ["INFO", "ATAK", "Prendre en main : en main -> miniature"] call comspec_atak_native_fnc_log;
    uiNamespace setVariable ["COMSPEC_ATAK_HudWanted", (missionNamespace getVariable ["COMSPEC_ATAK_PhoneActive", ""]) isEqualTo ""];
    [] call comspec_atak_native_fnc_close;
    false
};
// Téléphone d'un autre sur moi (fn_phoneOwn) : choix du téléphone, ou le sien si je n'ai que lui.
private _why = if ((["prepare"] call comspec_atak_native_fnc_phoneOwn) isEqualTo "") then { [true] call comspec_atak_native_fnc_canUse } else { "" };
if (_why isNotEqualTo "") exitWith { [_why] call comspec_atak_native_fnc_deviceDenied };
uiNamespace setVariable ["COMSPEC_ATAK_HudWanted", true];
// Interaction miniature restée notée (fn_miniInteract) : terminée, sinon le grand s'ouvrait avec la bordure de Maj+U à la
// taille de l'écran et un clic hors du téléphone le refermait (2.9.41).
uiNamespace setVariable ["COMSPEC_ATAK_MiniPrevMode", ""];
profileNamespace setVariable ["COMSPEC_ATAK_Mode", "FULL"];
["INFO", "ATAK", format ["Prendre en main : %1 -> en main (grand)%2", ["rangé", "miniature"] select !isNull _d, ["", " à bord"] select !isNull objectParent player]] call comspec_atak_native_fnc_log;
[true] call comspec_atak_native_fnc_open
