/* Bascule mini / plein écran ; mémorisé dans le profil du joueur. */
private _mode = toUpper (profileNamespace getVariable ["COMSPEC_ATAK_Mode", "MINI"]);
private _next = ["MINI", "FULL"] select (_mode isEqualTo "MINI");
profileNamespace setVariable ["COMSPEC_ATAK_Mode", _next];
saveProfileNamespace;
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
if (!isNull (findDisplay 88500)) then {
    [_s getOrDefault ["activePage", "LAUNCHER"], false] call comspec_atak_native_fnc_navigate;
};
_next
