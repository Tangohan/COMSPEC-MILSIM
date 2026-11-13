/*
    Range le téléphone (en main : ferme l'écran ; porté : retire la miniature ; JPC : éteint l'écran 3D).
    _anim (4.2.1) : rangement demandé par le joueur (touches « prendre en main » et « porter ») : le téléphone en main
    redescend en s'effaçant (0,2 s, fn_fx « phoneOut ») puis l'écran se ferme. Sans _anim (tout le reste : Échap, mort,
    inconscience, changement de véhicule ou de place, téléphone perdu ou vide, réouverture) : fermeture immédiate, même
    pendant une animation déjà lancée. Pas d'animation vers la miniature en mode PETIT (même place, rien ne clignote).
*/
params [["_anim", false, [false]]];
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
    if ((uiNamespace getVariable ["COMSPEC_ATAK_Closing", displayNull]) isEqualTo _d && {_anim}) exitWith {};
    if (_anim && {!((uiNamespace getVariable ["COMSPEC_ATAK_HudWanted", false]) && {([] call comspec_atak_native_fnc_layoutGet) getOrDefault ["mini", true]})}
        && {["phoneOut"] call comspec_atak_native_fnc_fx}) exitWith {
        uiNamespace setVariable ["COMSPEC_ATAK_Closing", _d];
        // Plus aucun clic pendant la descente ; Échap et toute fermeture immédiate restent possibles.
        { if (isNull (ctrlParentControlsGroup _x)) then { _x ctrlEnable false; }; } forEach (allControls _d);
        // Fermeture 0,2 s plus tard en temps réel (image par image) ; l'écran déjà fermé entre-temps : rien.
        uiNamespace setVariable ["COMSPEC_ATAK_CloseWait", {
            params ["_d", "_t"];
            if (isNull _d) exitWith {};
            if (diag_tickTime < _t) exitWith { [uiNamespace getVariable ["COMSPEC_ATAK_CloseWait", {}], [_d, _t]] call comspec_atak_native_fnc_nextFrame; };
            _d closeDisplay 2;
        }];
        [uiNamespace getVariable ["COMSPEC_ATAK_CloseWait", {}], [_d, diag_tickTime + 0.2]] call comspec_atak_native_fnc_nextFrame;
    };
    _d closeDisplay 2;
} else {
    if (_s getOrDefault ["texture", false]) exitWith { ["close"] call comspec_atak_native_fnc_jpcScreen; };
    ("COMSPEC_ATAK_Hud" call BIS_fnc_rscLayer) cutText ["", "PLAIN"];
};
true
