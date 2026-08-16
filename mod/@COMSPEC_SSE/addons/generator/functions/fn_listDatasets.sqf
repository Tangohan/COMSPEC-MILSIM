/*
    Liste les datasets disponibles.
    [] call comspec_sse_fnc_listDatasets
*/
if (isNil "comspec_sse_datasets") then {
    [] call comspec_sse_fnc_registerDatasets;
};

private _out = [];
{
    private _ds = _y;
    if (_ds isEqualType createHashMap) then {
        _out pushBack createHashMapFromArray [
            ["id", _x],
            ["name", _ds getOrDefault ["name", _x]],
            ["seed", _ds getOrDefault ["seed", ""]],
            ["region", _ds getOrDefault ["region", ""]],
            ["roles", count (_ds getOrDefault ["roles", []])]
        ];
    };
} forEach comspec_sse_datasets;

_out
