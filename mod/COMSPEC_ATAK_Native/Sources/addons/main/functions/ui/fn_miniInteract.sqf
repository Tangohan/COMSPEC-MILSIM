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
["heal"] call comspec_atak_native_fnc_vehicleKeys;
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _d = [] call comspec_atak_native_fnc_display;
// En main mais caché sous une fenêtre de cockpit ou d'un autre mod : remis au premier plan (fn_vehicleKeys, 2.9.41).
if (!isNull _d && {_s getOrDefault ["interactive", false]} && {["covered", _d] call comspec_atak_native_fnc_vehicleKeys}) exitWith {
    ["INFO", "ATAK", "Téléphone en main caché sous une autre fenêtre : rouvert au premier plan"] call comspec_atak_native_fnc_log;
    [true] call comspec_atak_native_fnc_open
};
if (!isNull _d && {_s getOrDefault ["interactive", false]}) exitWith {
    ["INFO", "ATAK", "Interagir avec la miniature : fin, retour au jeu"] call comspec_atak_native_fnc_log;
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
["INFO", "ATAK", format ["Interagir avec la miniature : début%1", ["", " à bord"] select !isNull objectParent player]] call comspec_atak_native_fnc_log;
// Ouverture refusée : l'interaction n'a pas commencé, le mode du joueur est remis tout de suite.
if !([true] call comspec_atak_native_fnc_open) exitWith {
    profileNamespace setVariable ["COMSPEC_ATAK_Mode", uiNamespace getVariable ["COMSPEC_ATAK_MiniPrevMode", "FULL"]];
    uiNamespace setVariable ["COMSPEC_ATAK_MiniPrevMode", ""];
    ["WARNING", "ATAK", "Interagir avec la miniature : téléphone impossible à ouvrir"] call comspec_atak_native_fnc_log;
    false
};
true
