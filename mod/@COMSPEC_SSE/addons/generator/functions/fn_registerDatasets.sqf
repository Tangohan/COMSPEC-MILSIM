/*
    Enregistre les datasets mission (FALCON + extensions).
*/
if (!isNil "comspec_sse_datasets" && {comspec_sse_datasets isEqualType createHashMap} && {count comspec_sse_datasets > 0}) exitWith {
    // Fusion : garantir FALCON même si registre partiel
    if !( "falcon" in comspec_sse_datasets ) then {
        comspec_sse_datasets set ["falcon", [] call comspec_sse_fnc_datasetFalcon];
    };
    true
};

comspec_sse_datasets = createHashMap;
comspec_sse_datasets set ["falcon", [] call comspec_sse_fnc_datasetFalcon];

if (isServer) then { publicVariable "comspec_sse_datasets"; };
[format ["registerDatasets: %1 pack(s)", count comspec_sse_datasets], "WARN"] call comspec_sse_fnc_log;
true
