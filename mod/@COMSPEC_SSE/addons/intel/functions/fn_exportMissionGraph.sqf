/*
    Export graphe SSE mission (structure sérialisable).
    [] call comspec_sse_fnc_exportMissionGraph
*/
private _entities = [];
// allMissionObjects contient déjà unités et véhicules : dédoublonner pour n'exporter chaque entité qu'une fois.
private _all = allUnits + vehicles + (allMissionObjects "All");
_all = _all arrayIntersect _all;
{
    private _data = [_x] call comspec_sse_fnc_getData;
    if (!isNil "_data") then {
        _entities pushBack (createHashMapFromArray [
            ["netId", netId _x],
            ["class", typeOf _x],
            ["pos", getPosATL _x],
            ["uid", [_data, "uid", ""] call BIS_fnc_getFromPairs],
            ["type", [_data, "type", ""] call BIS_fnc_getFromPairs],
            ["level", [_x] call comspec_sse_fnc_getExploitationLevel],
            ["data", _data]
        ]);
    };
} forEach _all;

private _graph = createHashMapFromArray [
    ["format", "comspec_sse_mission_graph"],
    ["version", 1],
    ["exportedAt", time],
    ["entities", _entities],
    ["logical", [] call comspec_sse_fnc_listLogicalEntities],
    ["discovery", if (isNil "comspec_sse_discoveryStates") then {createHashMap} else {+comspec_sse_discoveryStates}],
    ["biometrics", if (isNil "comspec_sse_biometricIndex") then {createHashMap} else {+comspec_sse_biometricIndex}]
];

comspec_sse_missionGraph = _graph;
if (missionNamespace getVariable ["comspec_sse_crossMissionPersist", false]) then {
    profileNamespace setVariable ["comspec_sse_persistedGraph", [_graph] call comspec_sse_fnc_serializeData];
    saveProfileNamespace;
};

_graph
