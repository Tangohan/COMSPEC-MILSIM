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

        private _lines = ["Documents SSE"];
        {
            if (_x isEqualType createHashMap) then {
                _lines pushBack format ["• %1", _x getOrDefault ["title", "Document"]];
                private _sum = _x getOrDefault ["summary", ""];
                if (_sum != "" && {_quality >= 55}) then { _lines pushBack format ["  %1", _sum]; };
                private _grid = _x getOrDefault ["grid", ""];
                if (_grid != "" && {_quality >= 70}) then { _lines pushBack format ["  Grid : %1", _grid]; };
                private _cw = _x getOrDefault ["codeword", ""];
                if (_cw != "" && {_quality >= 80}) then { _lines pushBack format ["  Mot de code : %1", _cw]; };
            };
        } forEach _docs;

        private _fog = createHashMapFromArray [
            ["title", "Documents"],
            ["lines", _lines],
            ["quality", _quality],
            ["uid", (if (isNil {[_target] call comspec_sse_fnc_getData}) then {"?"} else {
                [[_target] call comspec_sse_fnc_getData, "uid", "?"] call BIS_fnc_getFromPairs
            })],
            ["level", "documents"]
        ];
        hint (_lines joinString endl);
        [_fog get "uid", "documents", typeOf _target, format ["%1 doc(s)", count _docs], _quality, "LOCAL"] call comspec_sse_fnc_addJournalEntry;
        if (!isNil "comspec_sse_fnc_showResult") then { [_fog] call comspec_sse_fnc_showResult; };
    },
    [_target, _player]
] call comspec_sse_fnc_progressAction;
true
