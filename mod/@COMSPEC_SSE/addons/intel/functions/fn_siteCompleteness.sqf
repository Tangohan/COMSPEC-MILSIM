params [
    ["_center", objNull, [objNull, []]],
    ["_radius", 50, [0]]
];
private _list = [_center, _radius] call comspec_sse_fnc_listSiteEntities;
if (_list isEqualTo []) exitWith { 0 };
private _done = 0;
{
    private _lvl = _x getOrDefault ["level", "NONE"];
    if (_lvl in ["FIELD", "DETAILED", "FUSION"]) then { _done = _done + 1; };
} forEach _list;
round ((_done / count _list) * 100)
