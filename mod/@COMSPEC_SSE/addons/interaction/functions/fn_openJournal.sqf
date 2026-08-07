/*
    Affiche le journal SSE du joueur (hint structuré V0.1).
*/
private _journal = [] call comspec_sse_fnc_getJournal;
if (count _journal == 0) exitWith {
    hint "Journal SSE vide.";
};

private _lines = ["=== JOURNAL SSE ==="];
private _slice = _journal select [((count _journal) - 12) max 0, 12];
{
    _lines pushBack format [
        "%1 | %2 | %3 | Q%4%% | %5",
        _x getOrDefault ["clock", "?"],
        _x getOrDefault ["type", "?"],
        _x getOrDefault ["id", "?"],
        _x getOrDefault ["quality", 0],
        _x getOrDefault ["txStatus", "LOCAL"]
    ];
    private _sum = _x getOrDefault ["summary", ""];
    if (_sum != "") then {
        _lines pushBack format ["  → %1", _sum];
    };
} forEach _slice;

hint (_lines joinString endl);
["Journal SSE ouvert"] call comspec_sse_fnc_log;
