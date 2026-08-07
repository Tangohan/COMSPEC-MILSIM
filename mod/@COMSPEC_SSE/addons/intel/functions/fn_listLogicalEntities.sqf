params [
    ["_filterKind", "", [""]]
];
if (isNil "comspec_sse_logicalEntities") exitWith { [] };
private _out = [];
{
    private _rec = comspec_sse_logicalEntities get _x;
    if (_filterKind == "" || {(_rec getOrDefault ["kind", ""]) == toUpper _filterKind}) then {
        _out pushBack _rec;
    };
} forEach (keys comspec_sse_logicalEntities);
_out
