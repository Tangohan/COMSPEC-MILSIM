disableSerialization;
private _d = [] call comspec_atak_native_fnc_display;
if (isNull _d) exitWith { true };
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
if (_s getOrDefault ["interactive", false]) then {
    _d closeDisplay 2;
} else {
    ("COMSPEC_ATAK_Hud" call BIS_fnc_rscLayer) cutText ["", "PLAIN"];
};
true
