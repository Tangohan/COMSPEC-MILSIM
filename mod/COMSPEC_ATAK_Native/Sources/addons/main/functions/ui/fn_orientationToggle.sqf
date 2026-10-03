/* Téléphone vertical / horizontal (mode mini). Mémorisé dans le profil. */
private _o = toUpper (profileNamespace getVariable ["COMSPEC_ATAK_Orientation", "PORTRAIT"]);
private _next = ["PORTRAIT", "LANDSCAPE"] select (_o isEqualTo "PORTRAIT");
profileNamespace setVariable ["COMSPEC_ATAK_Orientation", _next];
saveProfileNamespace;
if (!isNull ([] call comspec_atak_native_fnc_display)) then {
    private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
    [_s getOrDefault ["activePage", "LAUNCHER"], false] call comspec_atak_native_fnc_navigate;
};
_next
