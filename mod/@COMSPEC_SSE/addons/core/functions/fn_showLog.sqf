/*
    Affiche les dernières entrées du journal technique SSE.
*/
private _buf = [] call comspec_sse_fnc_getLog;
if (count _buf == 0) exitWith {
    hint "Journal SSE vide.\nActivez le debug CBA ou reproduisez une action pour journaliser.";
};

private _lines = ["=== JOURNAL TECHNIQUE SSE ==="];
private _slice = _buf select [((count _buf) - 20) max 0, 20];
{
    _lines pushBack format [
        "%1 [%2] %3",
        _x getOrDefault ["clock", "?"],
        _x getOrDefault ["level", "INFO"],
        _x getOrDefault ["message", ""]
    ];
} forEach _slice;

hint (_lines joinString endl);
["showLog: journal affiché"] call comspec_sse_fnc_log;
true
