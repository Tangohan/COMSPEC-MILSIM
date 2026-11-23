/* Position monde sous le curseur : panneau curseur, trait jaune, mesure et dessin en cours. */
params ["_map", "_mx", "_my"];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _w = _map ctrlMapScreenToWorld [_mx, _my];
_s set ["cursorPos", _w];
// Clic gauche armé (fn_mapMouseButtonDown) : au-delà de 6 px, ou après 150 ms de maintien et 3 px, c'est un glisser qui
// déplace la carte (moteur Arma) : le relâchement ne posera rien et l'outil reste actif (fn_mapMouseButtonUp). En
// sélection, l'appui long (pointeur, fn_mapOnDraw, 8 px de tolérance) garde le seul seuil de 6 px. Deux lectures de
// table tant que rien n'est armé.
private _lc = _s getOrDefault ["mapLc", []];
if ((count _lc) isEqualTo 5 && {!(_s getOrDefault ["mapDrag", false])}) then {
    private _d2 = (((_mx - (_lc select 0)) / pixelW) ^ 2) + (((_my - (_lc select 1)) / pixelH) ^ 2);
    if (_d2 > 36 || {_d2 > 9 && {!(_s getOrDefault ["mapLcOk", false])} && {(diag_tickTime - (_s getOrDefault ["mapLcAt", diag_tickTime])) >= 0.15}}) then {
        _s set ["mapDrag", true];
        _s set ["mapLcOk", false];
        // La carte cliquée prend le focus et Arma la dessine devant tout : la pastille de l'outil, le menu OUTILS et la
        // barre disparaissaient pendant le glisser (« ça ferme l'outil »). Focus rendu dès le début du glisser.
        [] call comspec_atak_native_fnc_mapUnfocus;
    };
};
["MOVE", [_w select 0, _w select 1, 0]] call comspec_atak_native_fnc_markerStroke;
// Panneau curseur (grilles, texte structuré, traduction) : 20 fois par seconde au plus, l'événement arrivant
// plusieurs fois par image. Dernière position rattrapée par un rafraîchissement différé.
private _now = diag_tickTime;
if (_now - (_s getOrDefault ["cursorPanelAt", -1]) >= 0.05) then {
    _s set ["cursorPanelAt", _now];
    [true] call comspec_atak_native_fnc_mapOverlayUpdate;
} else {
    if !(_s getOrDefault ["cursorPanelLate", false]) then {
        _s set ["cursorPanelLate", true];
        [{
            private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
            _s set ["cursorPanelLate", false];
            _s set ["cursorPanelAt", diag_tickTime];
            [true] call comspec_atak_native_fnc_mapOverlayUpdate;
        }, [], 0.06] call comspec_atak_native_fnc_waitAndExec;
    };
};
false
