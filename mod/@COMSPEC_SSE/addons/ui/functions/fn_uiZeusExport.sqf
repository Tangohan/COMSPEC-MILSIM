if (!isNil "comspec_sse_fnc_exportMissionGraph") then {
    private _g = [] call comspec_sse_fnc_exportMissionGraph;
    [format ["Graphe exporté (%1 entités).", count (_g getOrDefault ["entities", []])], "info"] call comspec_sse_fnc_zeusNotify;
};
true
