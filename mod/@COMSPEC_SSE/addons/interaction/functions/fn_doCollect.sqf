/*
    [_target, _player] call comspec_sse_fnc_doCollect
*/
params [
    ["_target", objNull, [objNull]],
    ["_player", player, [objNull]]
];

if !([_player, "evidence_bag"] call comspec_sse_fnc_hasEquipment) exitWith {
    hint "Sachet de preuve SSE (ou kit médical ACE compatible) requis.";
};

[
    8,
    "Collecte SSE...",
    {
        params ["_target", "_player"];
        [_target] call comspec_sse_fnc_ensureGenerated;

        private _data = [_target] call comspec_sse_fnc_getData;
        private _uid = if (isNil "_data") then { ["SSE-EVD"] call comspec_sse_fnc_generateUID } else {
            format ["SSE-EVD-%1", [_data, "uid", "X"] call BIS_fnc_getFromPairs]
        };
        private _quality = [65, true, 1, 1] call comspec_sse_fnc_calcQuality;
        private _pos = getPosATL _target;

        private _entry = createHashMapFromArray [
            ["evidenceId", _uid],
            ["type", [_data, "type", typeOf _target] call BIS_fnc_getFromPairs],
            ["collector", name _player],
            ["callsign", name _player],
            ["time", time],
            ["missionTime", [daytime, "HH:MM:SS"] call BIS_fnc_timeToString],
            ["position", _pos],
            ["grid", mapGridPosition _pos],
            ["action", "COLLECT"],
            ["quality", _quality],
            ["container", [_player, "evidence_bag"] call comspec_sse_fnc_resolveEquipment]
        ];

        ["collect", _target, [_quality, _entry]] call comspec_sse_fnc_requestServerOp;

        hint format [
            "Preuve collectée\n%1\nPar : %2\nHeure : %3\nGrid : %4\nQualité : %5%%",
            _uid,
            name _player,
            _entry get "missionTime",
            _entry get "grid",
            _quality
        ];

        [
            _uid,
            "collect",
            [_data, "type", "OBJECT"] call BIS_fnc_getFromPairs,
            format ["Collecté grid %1", _entry get "grid"],
            _quality,
            "QUEUED"
        ] call comspec_sse_fnc_addJournalEntry;
    },
    [_target, _player]
] call comspec_sse_fnc_progressAction;
