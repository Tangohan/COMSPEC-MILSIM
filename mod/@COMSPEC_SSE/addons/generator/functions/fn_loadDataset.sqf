/*
    Charge un dataset par ID.
    [_datasetId] call comspec_sse_fnc_loadDataset
*/
params [
    ["_datasetId", "falcon", [""]]
];

if (isNil "comspec_sse_datasets") then {
    [] call comspec_sse_fnc_registerDatasets;
};

private _id = toLower _datasetId;
if (_id in ["falcon", "falcon_iraq", "falcon_iq_2012"]) then { _id = "falcon"; };

private _ds = comspec_sse_datasets getOrDefault [_id, createHashMap];
if (count _ds == 0 && {_id == "falcon"}) then {
    _ds = [] call comspec_sse_fnc_datasetFalcon;
    comspec_sse_datasets set ["falcon", _ds];
};

if (count _ds == 0) exitWith { nil };
_ds
