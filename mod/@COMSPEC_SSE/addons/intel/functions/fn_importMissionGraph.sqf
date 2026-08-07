/*
    Import d'un graphe (HashMap ou pairs sérialisés).
    [_graph] call comspec_sse_fnc_importMissionGraph
*/
params [
    ["_graph", createHashMap, [createHashMap, []]]
];

private _g = if (_graph isEqualType createHashMap) then { _graph } else {
    [_graph] call comspec_sse_fnc_deserializeData
};
if !(_g isEqualType createHashMap) exitWith { false };

comspec_sse_missionGraph = _g;
private _logical = _g getOrDefault ["logical", []];
if (_logical isEqualType []) then {
    if (isNil "comspec_sse_logicalEntities") then { comspec_sse_logicalEntities = createHashMap; };
    {
        if (_x isEqualType createHashMap) then {
            comspec_sse_logicalEntities set [_x getOrDefault ["id", str _forEachIndex], _x];
        };
    } forEach _logical;
};

private _disc = _g getOrDefault ["discovery", createHashMap];
if (_disc isEqualType createHashMap) then { comspec_sse_discoveryStates = _disc; };

hint format ["Graphe SSE importé — %1 entité(s) logiques.", count (keys (missionNamespace getVariable ["comspec_sse_logicalEntities", createHashMap]))];
true
