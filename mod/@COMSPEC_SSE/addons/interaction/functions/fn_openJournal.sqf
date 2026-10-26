/*
    Affiche le journal SSE du joueur (notification du téléphone ATAK : rien par-dessus l'écran de jeu).
*/
private _journal = [] call comspec_sse_fnc_getJournal;
if (count _journal == 0) exitWith {
    ["Journal SSE vide.", "INFO"] call comspec_sse_fnc_notify;
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

[_lines joinString endl, "INFO"] call comspec_sse_fnc_notify;
["Journal SSE ouvert"] call comspec_sse_fnc_log;
