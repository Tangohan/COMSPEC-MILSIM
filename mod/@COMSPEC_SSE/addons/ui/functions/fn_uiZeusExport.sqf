if (!isNil "comspec_sse_fnc_exportMissionGraph") then {
    private _g = [] call comspec_sse_fnc_exportMissionGraph;
    hint ((format ["Graphe exporté (%1 entités).", count (_g getOrDefault ["entities", []])]) call comspec_sse_fnc_tr);
};
true
