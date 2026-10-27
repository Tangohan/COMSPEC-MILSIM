/*
    Remet les bris d'écran et la surface du verre (fêlures, verre éclaté, lignes mortes, pixels, rayures, saleté, sang,
    effets Zeus, parasites du brouillage) au-dessus de tout ce qu'une app a créé après eux.
    Arma dessine les contrôles d'un écran dans leur ordre de création : une app qui ajoute des contrôles hors de la zone
    de contenu après le dernier rendu (vue drone, Garmin, recherche de la carte, palette des marqueurs, JTAC, Plan,
    notifications…) passait par-dessus les fêlures. fn_pageCtrl compte ces créations (COMSPEC_ATAK_CtrlSeq) ; une fois
    par image au plus, les contrôles des effets sont supprimés puis recréés par fn_deviceOverlay, donc en dernier.
    Rien à faire tant que l'écran n'a ni dégât, ni filtre, ni saleté.
    Appelé par fn_pageCtrl et par ce qui crée ses contrôles directement sur l'écran du téléphone (notifications, bandeaux).
*/
uiNamespace setVariable ["COMSPEC_ATAK_CtrlSeq", (uiNamespace getVariable ["COMSPEC_ATAK_CtrlSeq", 0]) + 1];
if (uiNamespace getVariable ["COMSPEC_ATAK_RaiseQueued", false]) exitWith { false };
uiNamespace setVariable ["COMSPEC_ATAK_RaiseQueued", true];
[{
    uiNamespace setVariable ["COMSPEC_ATAK_RaiseQueued", false];
    if (isNull ([] call comspec_atak_native_fnc_display)) exitWith {};
    // Déjà recréés après la dernière création (rendu de page complet) : rien à remonter.
    if ((uiNamespace getVariable ["COMSPEC_ATAK_CtrlSeq", 0]) <= (uiNamespace getVariable ["COMSPEC_ATAK_OvSeq", 0])) exitWith {};
    private _surf = (uiNamespace getVariable ["COMSPEC_ATAK_SurfOverlay", ["", []]]) param [1, []];
    private _fx = (uiNamespace getVariable ["COMSPEC_ATAK_ScreenFx", ["", []]]) param [1, []];
    // Verrou à distance du Zeus : l'écran rouge (fn_zeusOverlay) doit rester devant les toasts et bandeaux créés après lui.
    if !((uiNamespace getVariable ["COMSPEC_ATAK_DevFront", false]) || {(count _surf) > 0} || {(count _fx) > 0}
        || {(["lock", ""] call comspec_atak_native_fnc_zeusFx) isNotEqualTo ""}) exitWith {
        uiNamespace setVariable ["COMSPEC_ATAK_OvSeq", uiNamespace getVariable ["COMSPEC_ATAK_CtrlSeq", 0]];
    };
    private _kill = {
        {
            if (_x isEqualType controlNull) then { ctrlDelete _x; } else {
                if (_x isEqualType []) then { _x call _kill; };
            };
        } forEach _this;
    };
    (uiNamespace getVariable ["COMSPEC_ATAK_DevOverlay", []]) call _kill;
    (_surf apply { _x param [1, controlNull] }) call _kill;
    (_fx apply { _x param [1, controlNull] }) call _kill;
    (uiNamespace getVariable ["COMSPEC_ATAK_ZeusOv", []]) call _kill;
    uiNamespace setVariable ["COMSPEC_ATAK_DevOverlay", []];
    uiNamespace setVariable ["COMSPEC_ATAK_SurfOverlay", ["", []]];
    uiNamespace setVariable ["COMSPEC_ATAK_ScreenFx", ["", []]];
    uiNamespace setVariable ["COMSPEC_ATAK_ZeusOv", []];
    [] call comspec_atak_native_fnc_deviceOverlay;
    uiNamespace setVariable ["COMSPEC_ATAK_OvSeq", uiNamespace getVariable ["COMSPEC_ATAK_CtrlSeq", 0]];
}] call CBA_fnc_execNextFrame;
true
