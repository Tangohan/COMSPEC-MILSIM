/* Supprime un marqueur de joueur (les marqueurs de la mission sont protégés). Params : [nom] */
params [["_name", ""]];
if (_name isEqualTo "" || {(markerShape _name) isEqualTo ""}) exitWith { false };
if ((_name find "_USER_DEFINED") isNotEqualTo 0) exitWith {
    ["WARNING", "Marqueur de la mission : suppression impossible", 3, 20] call comspec_atak_native_fnc_notify;
    false
};
deleteMarker _name;
private _notes = missionNamespace getVariable ["COMSPEC_ATAK_MarkerNotes", createHashMap];
if (_name in _notes) then { _notes deleteAt _name; missionNamespace setVariable ["COMSPEC_ATAK_MarkerNotes", _notes, true]; };
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
_s set ["selectedMarker", ""];
_s set ["markerEdit", createHashMap];
["INFO", "Marqueur supprimé", 2, 10] call comspec_atak_native_fnc_notify;
[{ ["MAP"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
true
