/*
    Lecture / exploitation des documents trouvés sur une cible.
    [_target, _player] call comspec_sse_fnc_doReadDocuments
*/
params [
    ["_target", objNull, [objNull]],
    ["_player", player, [objNull]]
];

[
    6,
    "Lecture des documents...",
    {
        params ["_target", "_player"];
        [_target] call comspec_sse_fnc_ensureGenerated;
        private _quality = [65, true, 1, 1] call comspec_sse_fnc_calcQuality;
        private _docs = [_target, "documents"] call comspec_sse_fnc_getSection;
        if (isNil "_docs" || {!(_docs isEqualType [])} || {count _docs == 0}) exitWith {
            hint "Aucun document exploitable.";
        };

        // Lignes compactes (journal / fallback) — la visionneuse utilise surtout `docs`.
        private _lines = [];
        {
            if (_x isEqualType createHashMap) then {
                private _t = _x getOrDefault ["title", "Document"];
                private _sum = _x getOrDefault ["summary", ""];
                if (_sum != "" && {_quality >= 55}) then {
                    _lines pushBack format ["%1 — %2", _t, _sum];
                } else {
                    _lines pushBack _t;
                };
            };
        } forEach _docs;

        private _data = [_target] call comspec_sse_fnc_getData;
        private _uid = if (isNil "_data") then {"?"} else {
            [_data, "uid", "?"] call BIS_fnc_getFromPairs
        };

        private _fog = createHashMapFromArray [
            ["title", "Dossier documentaire"],
            ["kind", "documents"],
            ["level", "documents"],
            ["docs", _docs],
            ["lines", _lines],
            ["quality", _quality],
            ["qualityLabel", if (_quality >= 80) then {"Bonne"} else { if (_quality >= 55) then {"Correcte"} else {"Partielle"} }],
            ["uid", _uid],
            ["type", "DOCUMENTS"]
        ];

        [_uid, "documents", typeOf _target, format ["%1 doc(s)", count _docs], _quality, "LOCAL"] call comspec_sse_fnc_addJournalEntry;
        if (!isNil "comspec_sse_fnc_showResult") then {
            [_fog] call comspec_sse_fnc_showResult;
        } else {
            hint (_lines joinString endl);
        };
    },
    [_target, _player]
] call comspec_sse_fnc_progressAction;
true
