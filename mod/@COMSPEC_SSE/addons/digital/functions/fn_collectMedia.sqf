/*
    Collecte un support numérique dans l'inventaire joueur.
    [_target, _player] call comspec_sse_fnc_collectMedia
*/
params [
    ["_target", objNull, [objNull]],
    ["_player", player, [objNull]]
];

if !([_player, "evidence_bag"] call comspec_sse_fnc_hasEquipment) exitWith {
    hint "Sachet de preuve SSE (ou kit médical ACE compatible) requis pour collecter un support.";
    false
};

[
    10,
    "Collecte support numérique...",
    {
        params ["_target", "_player"];
        [_target] call comspec_sse_fnc_ensureGenerated;

        private _seed = [_target] call comspec_sse_fnc_getSeed;
        private _data = [_target] call comspec_sse_fnc_getData;
        private _clusterId = if (isNil "_data") then {""} else {[_data, "clusterId", ""] call BIS_fnc_getFromPairs};

        // Récupérer cluster approximatif
        private _cluster = createHashMapFromArray [
            ["clusterId", _clusterId],
            ["primaryName", ""],
            ["theme", _target getVariable ["comspec_sse_theme", "fuel_delivery"]],
            ["region", _target getVariable ["comspec_sse_region", "IRAQ"]]
        ];
        private _id = [_target, "identity"] call comspec_sse_fnc_getSection;
        if (!isNil "_id" && {_id isEqualType createHashMap}) then {
            _cluster set ["primaryName", _id getOrDefault ["name", ""]];
        };

        private _kind = ["USB", "SDCARD"] select (([_seed, "medkind"] call comspec_sse_fnc_hash) mod 2);
        private _media = [_seed + 50, _kind, _cluster] call comspec_sse_fnc_generateUSB;

        private _item = if (_kind == "USB") then {"COMSPEC_SSE_USB"} else {"COMSPEC_SSE_SDCard"};
        _player addItem _item;

        // Stocker payload sur le joueur pour transmission ultérieure
        private _bag = _player getVariable ["comspec_sse_collectedMedia", []];
        _bag pushBack _media;
        _player setVariable ["comspec_sse_collectedMedia", _bag];

        private _coc = createHashMapFromArray [
            ["evidenceId", _media get "uid"],
            ["type", _kind],
            ["collector", name _player],
            ["time", time],
            ["missionTime", [daytime, "HH:MM:SS"] call BIS_fnc_timeToString],
            ["position", getPosATL _target],
            ["grid", mapGridPosition (getPosATL _target)],
            ["action", "COLLECT_MEDIA"],
            ["quality", 70],
            ["container", [_player, "evidence_bag"] call comspec_sse_fnc_resolveEquipment]
        ];
        ["collect", _target, [70, _coc]] call comspec_sse_fnc_requestServerOp;

        hint format ["Support %1 collecté\n%2\nFichiers : %3", _kind, _media get "uid", count (_media getOrDefault ["files", []])];
        [_media get "uid", "collect_media", _kind, _media getOrDefault ["summary", ""], 70, "QUEUED"] call comspec_sse_fnc_addJournalEntry;
    },
    [_target, _player]
] call comspec_sse_fnc_progressAction;
true
