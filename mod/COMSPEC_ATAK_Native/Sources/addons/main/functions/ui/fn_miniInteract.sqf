/*
    Touche « Interagir avec la miniature (sans l'agrandir) » (Maj+U par défaut, 2.9.38) :
    - téléphone rangé ou porté en miniature : il passe en main à la taille et à la place de la miniature (souris, clics,
      molette et saisie sur la miniature, le personnage ne bouge plus, comme en grand) ; bordure à l'accent tant que
      l'on interagit (fn_displayLoad) ;
    - en main (miniature ou grand) : rend la main au jeu, la miniature reste affichée. Échap ou un clic hors du téléphone
      font de même (fn_displayLoad).
    Le mode en main choisi par le joueur (miniature / grand, profil COMSPEC_ATAK_Mode) est gardé : remis à la fermeture
    (fn_displayUnload), la touche principale garde donc son comportement.
*/
if (!hasInterface) exitWith { false };
// Même appui vu deux fois dans la même image (raccourci CBA et fenêtre de bord, fn_vehicleKeys) : un seul basculement.
if ((uiNamespace getVariable ["COMSPEC_ATAK_ToggleFrame", -1]) isEqualTo diag_frameNo) exitWith { false };
uiNamespace setVariable ["COMSPEC_ATAK_ToggleFrame", diag_frameNo];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _d = [] call comspec_atak_native_fnc_display;
if (!isNull _d && {_s getOrDefault ["interactive", false]}) exitWith {
    uiNamespace setVariable ["COMSPEC_ATAK_HudWanted", true];
    [] call comspec_atak_native_fnc_close;
    false
};
private _why = [true] call comspec_atak_native_fnc_canUse;
if (_why isNotEqualTo "") exitWith { [_why] call comspec_atak_native_fnc_deviceDenied };
if ((uiNamespace getVariable ["COMSPEC_ATAK_MiniPrevMode", ""]) isEqualTo "") then {
    uiNamespace setVariable ["COMSPEC_ATAK_MiniPrevMode", toUpper (profileNamespace getVariable ["COMSPEC_ATAK_Mode", "FULL"])];
};
profileNamespace setVariable ["COMSPEC_ATAK_Mode", "MINI"];
uiNamespace setVariable ["COMSPEC_ATAK_HudWanted", true];
[true] call comspec_atak_native_fnc_open
