/*
    Module Zeus — Scenario Director (dataset + niveau).
*/
params ["_logic", "_units", "_activated"];
if (!_activated) exitWith { true };

private _datasetId = _logic getVariable ["DatasetId", "falcon"];
private _level = _logic getVariable ["ScenarioLevel", 1];
private _radius = _logic getVariable ["Radius", 50];
private _action = toUpper (_logic getVariable ["Action", "APPLY"]);

[_level, false] call comspec_sse_fnc_setScenarioLevel;

switch (_action) do {
    case "LEVEL_ONLY": {
        [_level, true] call comspec_sse_fnc_setScenarioLevel;
    };
    case "LIST": {
        private _list = [] call comspec_sse_fnc_listDatasets;
        private _txt = "Datasets SSE :";
        { _txt = _txt + format ["%1- %2 (%3)", endl, _x getOrDefault ["name", "?"], _x getOrDefault ["id", ""]]; } forEach _list;
        hint _txt;
    };
    default {
        // APPLY
        private _center = if (count _units > 0) then { _units select 0 } else { _logic };
        [_datasetId, _center, _radius, _level] call comspec_sse_fnc_applyDataset;
    };
};

if (!isNull _logic) then { deleteVehicle _logic; };
true
