/*
    Ajoute une entrée au journal SSE local du joueur.
    [_id, _type, _origin, _summary, _quality, _txStatus] call comspec_sse_fnc_addJournalEntry
*/
params [
    ["_id", "?", [""]],
    ["_type", "note", [""]],
    ["_origin", "", [""]],
    ["_summary", "", [""]],
    ["_quality", 0, [0]],
    ["_txStatus", "LOCAL", [""]]
];

private _journal = missionNamespace getVariable ["comspec_sse_playerJournal", []];
_journal pushBack (createHashMapFromArray [
    ["id", _id],
    ["time", time],
    ["clock", [daytime, "HH:MM:SS"] call BIS_fnc_timeToString],
    ["type", _type],
    ["origin", _origin],
    ["summary", _summary],
    ["quality", _quality],
    ["txStatus", _txStatus]
]);

// Limiter la taille
if (count _journal > 200) then {
    _journal = _journal select [(count _journal) - 200, 200];
};
missionNamespace setVariable ["comspec_sse_playerJournal", _journal];
true
