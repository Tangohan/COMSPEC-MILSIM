/*
    Supprime un marqueur de joueur ou un repère d'alerte reçu (fn_markerDeletable) ; les marqueurs de la mission sont
    protégés. Params : [nom]
*/
params [["_name", ""]];
if (_name isEqualTo "" || {(markerShape _name) isEqualTo ""}) exitWith { false };
private _kind = [_name] call comspec_atak_native_fnc_markerDeletable;
if (_kind isEqualTo 0) exitWith {
    ["WARNING", "Marqueur de la mission : suppression impossible", 3, 20] call comspec_atak_native_fnc_notify;
    false
};
if (_kind isEqualTo 2) then {
    // Repère d'alerte : local à ce téléphone (créé à la réception), rien à prévenir côté Athena.
    deleteMarkerLocal _name;
} else {
    deleteMarker _name;
    [_name, true] call comspec_atak_native_fnc_markerWeb;
};
private _notes = missionNamespace getVariable ["COMSPEC_ATAK_MarkerNotes", createHashMap];
if (_name in _notes) then { _notes deleteAt _name; missionNamespace setVariable ["COMSPEC_ATAK_MarkerNotes", _notes, true]; };
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
_s set ["selectedMarker", ""];
_s set ["markerEdit", createHashMap];
["INFO", ["Marqueur supprimé", "Alerte effacée de la carte"] select (_kind isEqualTo 2), 2, 10] call comspec_atak_native_fnc_notify;
[{ ["MAP"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
true
