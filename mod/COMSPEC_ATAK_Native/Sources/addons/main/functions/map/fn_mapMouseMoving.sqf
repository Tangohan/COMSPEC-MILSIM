/* Position monde sous le curseur : panneau curseur, trait jaune, mesure et dessin en cours. */
params ["_map", "_mx", "_my"];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _w = _map ctrlMapScreenToWorld [_mx, _my];
_s set ["cursorPos", _w];
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
        }, [], 0.06] call CBA_fnc_waitAndExecute;
    };
};
false
