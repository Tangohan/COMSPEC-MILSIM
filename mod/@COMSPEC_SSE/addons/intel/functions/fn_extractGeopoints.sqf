params [
    ["_entity", objNull, [objNull]]
];

private _points = [];
private _add = {
    params ["_label", "_grid", "_conf"];
    if (_grid != "") then {
        _points pushBack (createHashMapFromArray [
            ["label", _label],
            ["grid", _grid],
            ["confidence", _conf]
        ]);
    };
};

private _locs = [_entity, "locations"] call comspec_sse_fnc_getSection;
if (!isNil "_locs" && {_locs isEqualType []}) then {
    {
        if (_x isEqualType createHashMap) then {
            [_x getOrDefault ["label", "POI"], _x getOrDefault ["grid", ""], _x getOrDefault ["confidence", 0.5]] call _add;
        };
    } forEach _locs;
};

private _docs = [_entity, "documents"] call comspec_sse_fnc_getSection;
if (!isNil "_docs" && {_docs isEqualType []}) then {
    {
        if (_x isEqualType createHashMap) then {
            [_x getOrDefault ["title", "Doc"], _x getOrDefault ["grid", ""], 0.55] call _add;
        };
    } forEach _docs;
};

_points = [_points] call comspec_sse_fnc_deduplicateIntel;
_points
