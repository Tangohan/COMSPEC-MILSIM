/*
    Déduplique une liste d'intel / contacts par texte normalisé.
    [_list] call comspec_sse_fnc_deduplicateIntel
*/
params [
    ["_list", [], [[]]]
];

private _seen = createHashMap;
private _out = [];
{
    private _item = _x;
    private _key = if (_item isEqualType createHashMap) then {
        toLower (_item getOrDefault ["text", _item getOrDefault ["id", str _item]])
    } else {
        toLower str _item
    };
    if !(_key in _seen) then {
        _seen set [_key, true];
        _out pushBack _item;
    } else {
        // fusion légère si hashmap
        if (_item isEqualType createHashMap) then {
            private _id = _item getOrDefault ["id", _key];
            [_id, ["dedup"]] call comspec_sse_fnc_fuseIntel;
        };
    };
} forEach _list;
_out
