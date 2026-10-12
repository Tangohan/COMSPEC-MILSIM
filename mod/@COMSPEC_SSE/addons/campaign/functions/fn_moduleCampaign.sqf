/*
    Module Eden « Campagne SSE » : charge (ou crée) la campagne au lancement.
    Attributs : Id, Name, Type (permanente | recurrente | temporaire), CopyFrom,
    Autosave (minutes, 0 = jamais), SaveAtEnd (sauvegarde à la fin de la mission).
*/
params [["_logic", objNull, [objNull]], ["_units", [], [[]]], ["_activated", true, [true]]];
if (!isServer || {isNull _logic}) exitWith { false };

private _auto = _logic getVariable ["Autosave", 0];
if (_auto isEqualType "") then { _auto = parseNumber _auto; };
["load", [
    _logic getVariable ["Id", ""],
    _logic getVariable ["Name", ""],
    _logic getVariable ["Type", "permanente"],
    _logic getVariable ["CopyFrom", ""],
    _auto
]] call comspec_sse_fnc_campaignOp;

private _end = _logic getVariable ["SaveAtEnd", 1];
if (_end isEqualTo 1 || {_end isEqualTo true}) then {
    missionNamespace setVariable ["comspec_sse_campSaveAtEnd", true];
};
true
