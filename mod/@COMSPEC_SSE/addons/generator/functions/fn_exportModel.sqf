/*
    Exporte un modèle en structure sérialisable (pairs).
    [_modelId] call comspec_sse_fnc_exportModel
*/
params [
    ["_modelId", "", [""]]
];

private _model = [_modelId] call comspec_sse_fnc_loadModel;
if (isNil "_model") exitWith { [] };

[_model] call comspec_sse_fnc_serializeData
