/* Mini / plein écran pour le téléphone en main ; mémorisé dans le profil. */
private _mode = toUpper (profileNamespace getVariable ["COMSPEC_ATAK_Mode", "MINI"]);
private _next = ["MINI", "FULL"] select (_mode isEqualTo "MINI");
profileNamespace setVariable ["COMSPEC_ATAK_Mode", _next];
saveProfileNamespace;
// Réduire depuis le plein écran rend la souris : le téléphone reste en mini et l'on continue à jouer.
private _s0 = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
if (_next isEqualTo "MINI" && {_s0 getOrDefault ["interactive", false]}) exitWith {
    uiNamespace setVariable ["COMSPEC_ATAK_HudWanted", true];
    [] call comspec_atak_native_fnc_close;
    _next
};
if (!isNull ([] call comspec_atak_native_fnc_display)) then {
    private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
    [_s getOrDefault ["activePage", "LAUNCHER"], false] call comspec_atak_native_fnc_navigate;
};
_next
