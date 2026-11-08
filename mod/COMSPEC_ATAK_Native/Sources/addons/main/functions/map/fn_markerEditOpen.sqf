/*
    Ouvre l'éditeur de marqueur (nouveau ou existant) dans la carte, en mini comme en plein écran.
    Params : [nom ("" = nouveau), position monde]
*/
params [["_name", ""], ["_pos", []]];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _notes = missionNamespace getVariable ["COMSPEC_ATAK_MarkerNotes", createHashMap];
private _edit = if (_name isEqualTo "") then {
    createHashMapFromArray [
        // Nom proposé, modifiable : numéro de repère neutre (fn_markerDrop), jamais le type d'unité.
        ["name", ""], ["pos", _pos], ["text", format ["%1 %2", ["MK", "REP"] select ((missionNamespace getVariable ["COMSPEC_ATAK_Lang", "en"]) isEqualTo "fr"), (missionNamespace getVariable ["COMSPEC_ATAK_MarkerIndex", 0]) + 1]], ["desc", ""],
        ["type", profileNamespace getVariable ["COMSPEC_ATAK_LastMarkerType", "mil_dot"]],
        ["color", profileNamespace getVariable ["COMSPEC_ATAK_LastMarkerColor", "ColorRed"]],
        ["size", "1"], ["dir", "0"], ["alpha", "1"], ["channel", str (["channel"] call comspec_atak_native_fnc_markerPlace)], ["shape", "ICON"]
    ]
} else {
    createHashMapFromArray [
        ["name", _name], ["pos", getMarkerPos _name], ["text", ([markerText _name] call comspec_atak_native_fnc_markerAutoName)], ["desc", (_notes getOrDefault [_name, ["", ""]]) select 0],
        ["type", markerType _name], ["color", markerColor _name], ["size", str ((markerSize _name) select 0)],
        ["dir", str round markerDir _name], ["alpha", str markerAlpha _name], ["channel", str markerChannel _name], ["shape", markerShape _name]
    ]
};
_s set ["markerEdit", _edit];
_s set ["selectedMarker", _name];
_s set ["mapToolsOpen", false];
[{ ["MAP"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame;
true
