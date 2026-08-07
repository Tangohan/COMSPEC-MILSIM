/*
    Inspecter une cible SSE.
    [_target, _player] call comspec_sse_fnc_doInspect
*/
params [
    ["_target", objNull, [objNull]],
    ["_player", player, [objNull]]
];

private _time = missionNamespace getVariable ["comspec_sse_timeInspect", 5];

[
    _time,
    "Inspection SSE...",
    {
        params ["_target", "_player"];

        if (isNil {[_target] call comspec_sse_fnc_getData}) then {
            [_target] call comspec_sse_fnc_ensureGenerated;
        } else {
            [_target] call comspec_sse_fnc_ensureGenerated;
        };

        ["setstate", _target, ["DISCOVERED"]] call comspec_sse_fnc_requestServerOp;

        private _quality = [40, true, 0.7, 1] call comspec_sse_fnc_calcQuality;
        private _fog = [_target, "inspect", _quality] call comspec_sse_fnc_revealFog;

        private _lines = _fog getOrDefault ["lines", []];
        private _msg = (_fog getOrDefault ["title", "SSE"]) + endl + (_lines joinString endl);
        hint _msg;

        [
            _fog getOrDefault ["uid", "?"],
            "inspect",
            typeOf _target,
            _lines joinString " | ",
            _quality,
            "LOCAL"
        ] call comspec_sse_fnc_addJournalEntry;

        if (!isNil "comspec_sse_fnc_showResult") then {
            [_fog] call comspec_sse_fnc_showResult;
        };
    },
    [_target, _player]
] call comspec_sse_fnc_progressAction;
