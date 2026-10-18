/*
    Menu principal d'Arma (RscDisplayMain, Extended_DisplayLoad de config.cpp) : petit bouton discret en haut à gauche,
    « COMSPEC ATAK · Guide réseau », qui ouvre le guide du réseau et de sa simulation (fn_netGuide) par-dessus le menu.
    Rien d'autre n'est touché sur le menu (pas d'intro, pas de fond) ; le bouton n'existe que sur cet écran.
    Libellé par la stringtable (langue du jeu) : le dictionnaire du mod n'est chargé qu'à l'ouverture du guide.
    Appelé par chemin : au chargement du menu, on ne compte pas sur les CfgFunctions ; le clic les cherche dans
    missionNamespace (scène de fond du menu) puis dans uiNamespace (compilées au démarrage du jeu).
    Params : [display]
*/
params [["_display", displayNull, [displayNull]]];
if (isNull _display) exitWith {};
private _u = safeZoneH * 0.006;
private _b = _display ctrlCreate ["ctrlButton", -1];
_b ctrlSetPosition [safeZoneX + 2 * _u, safeZoneY + 2 * _u, safeZoneW * 0.13, 4.5 * _u];
_b ctrlSetText localize "STR_COMSPEC_ATAK_CFG_NETGUIDE_BTN";
_b ctrlSetTooltip localize "STR_COMSPEC_ATAK_CFG_NETGUIDE_TIP";
_b ctrlSetBackgroundColor [0.10, 0.30, 0.16, 0.85];
_b ctrlSetFontHeight (3.2 * _u);
_b ctrlAddEventHandler ["ButtonClick", {
    params ["_c"];
    private _f = missionNamespace getVariable ["comspec_atak_native_fnc_netGuide", nil];
    if (!isNil "_f") exitWith { [0, ctrlParent _c] call _f; };
    private _d = ctrlParent _c;
    with uiNamespace do {
        private _g = uiNamespace getVariable ["comspec_atak_native_fnc_netGuide", nil];
        if (!isNil "_g") then { [0, _d] call _g; };
    };
}];
_b ctrlCommit 0;
