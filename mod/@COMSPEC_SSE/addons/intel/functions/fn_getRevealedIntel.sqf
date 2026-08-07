/*
    Retourne l'intel visible au niveau actuel (et inférieurs).
    [_entity] call comspec_sse_fnc_getRevealedIntel
*/
params [
    ["_entity", objNull, [objNull]]
];

private _sec = [_entity, "sections"] call comspec_sse_fnc_getSection;
if (isNil "_sec" || {!(_sec isEqualType createHashMap)}) exitWith { [] };

private _order = ["TACTICAL", "FIELD", "DETAILED", "FUSION"];
private _cur = _sec getOrDefault ["exploitationLevel", "NONE"];
private _max = _order find _cur;
if (_max < 0) exitWith { [] };

private _layers = _sec getOrDefault ["intelLayers", createHashMap];
private _out = [];
if !(_layers isEqualType createHashMap) exitWith { [] };

for "_i" from 0 to _max do {
    private _lvl = _order select _i;
    { _out pushBack _x } forEach (_layers getOrDefault [_lvl, []]);
};
_out
