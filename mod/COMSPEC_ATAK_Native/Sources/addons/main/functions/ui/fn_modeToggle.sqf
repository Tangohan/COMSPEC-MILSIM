/* Mini / plein écran pour le téléphone en main ; mémorisé dans le profil. */
private _mode = toUpper (profileNamespace getVariable ["COMSPEC_ATAK_Mode", "MINI"]);
private _next = ["MINI", "FULL"] select (_mode isEqualTo "MINI");
profileNamespace setVariable ["COMSPEC_ATAK_Mode", _next];
saveProfileNamespace;
if (!isNull ([] call comspec_atak_native_fnc_display)) then {
    private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
    [_s getOrDefault ["activePage", "LAUNCHER"], false] call comspec_atak_native_fnc_navigate;
};
_next
