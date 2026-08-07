/*
    Triage automatique des éléments SSE d'un site.
    [_center, _radius] call comspec_sse_fnc_triageSite
*/
params [
    ["_center", objNull, [objNull, []]],
    ["_radius", 50, [0]]
];

private _pos = if (_center isEqualType []) then { _center } else { getPosATL _center };
private _objs = nearestObjects [_pos, [], _radius];
private _results = [];

{
    private _o = _x;
    if (!isNull _o && {!isNil {[_o] call comspec_sse_fnc_getData}}) then {
        [_o] call comspec_sse_fnc_ensureGenerated;
        private _sec = [_o, "sections"] call comspec_sse_fnc_getSection;
        if (isNil {_sec getOrDefault ["intelLayers", nil]}) then {
            [_o] call comspec_sse_fnc_attachIntelLayers;
            _sec = [_o, "sections"] call comspec_sse_fnc_getSection;
        };

        private _best = 0;
        private _triage = "UNKNOWN";
        private _tags = [];
        private _layers = _sec getOrDefault ["intelLayers", createHashMap];
        if (_layers isEqualType createHashMap) then {
            {
                private _items = _layers getOrDefault [_x, []];
                {
                    if (_x isEqualType createHashMap) then {
                        private _v = _x getOrDefault ["INTEL_VALUE", 0];
                        if (_v > _best) then {
                            _best = _v;
                            _triage = _x getOrDefault ["triage", "UNKNOWN"];
                        };
                        { _tags pushBackUnique _x } forEach (_x getOrDefault ["tags", []]);
                    };
                } forEach _items;
            } forEach ["TACTICAL", "FIELD", "DETAILED", "FUSION"];
        };

        // HVT phone boost
        private _type = if (isNil {[_o] call comspec_sse_fnc_getData}) then {""} else {
            [[_o] call comspec_sse_fnc_getData, "type", ""] call BIS_fnc_getFromPairs
        };
        if (_type in ["PHONE", "SMARTPHONE", "PERSON"] && {_best >= 60}) then {
            _triage = "EXPLOIT_NOW";
        };
        if (_best < 25) then { _triage = "LOW_VALUE"; };

        private _entry = createHashMapFromArray [
            ["entity", _o],
            ["netId", netId _o],
            ["type", _type],
            ["triage", _triage],
            ["INTEL_VALUE", _best],
            ["tags", _tags],
            ["level", [_o] call comspec_sse_fnc_getExploitationLevel],
            ["label", [_o] call comspec_sse_fnc_makeEvidenceLabel]
        ];
        _results pushBack _entry;
    };
} forEach _objs;

// Tri décroissant par valeur
_results = [_results, [], { -(_x getOrDefault ["INTEL_VALUE", 0]) }, "ASCEND"] call BIS_fnc_sortBy;

["SSE_TriageDone", [_pos, _results]] call comspec_sse_fnc_emitEvent;
_results
