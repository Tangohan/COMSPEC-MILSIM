disableSerialization;
private _d = [] call comspec_atak_native_fnc_display;
if (isNull _d) exitWith { true };
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
// Zoom de la carte gardé à la fermeture (fin d'« interagir avec la miniature » : la carte du HUD repart de ce zoom, pas du zoom par
// défaut). La molette zoome sans passer par fn_mapZoom ; avant, seul fn_mapZoom l'enregistrait.
private _mc = [] call comspec_atak_native_fnc_mapCtrl;
if (!isNull _mc && {_s getOrDefault ["mapCentered", false]}) then {
    private _sc = ctrlMapScale _mc;
    if (_sc > 0.0005 && {_sc <= 1}) then { uiNamespace setVariable ["COMSPEC_ATAK_MapScale", _sc]; };
};
if (_s getOrDefault ["interactive", false]) then {
    _d closeDisplay 2;
} else {
    if (_s getOrDefault ["texture", false]) exitWith { ["close"] call comspec_atak_native_fnc_jpcScreen; };
    ("COMSPEC_ATAK_Hud" call BIS_fnc_rscLayer) cutText ["", "PLAIN"];
};
true
