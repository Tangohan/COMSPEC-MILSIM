/*
    Module « Directeur de scénario » : applique un dataset (ex. FALCON) et
    pilote le niveau de révélation (0 surface → 3 vérité complète).
*/
params [
    ["_logic", objNull, [objNull]],
    ["_units", [], [[]]],
    ["_activated", true, [true]]
];

private _ctx = [_logic, _units, _activated] call comspec_sse_fnc_moduleContext;
if !(_ctx get "run") exitWith { true };

private _apply = {
    params ["_pos", "_action", "_datasetId", "_level", "_radius"];
    _action = toUpper _action;
    switch (_action) do {
        case "LEVEL_ONLY": {
            [_level, true] call comspec_sse_fnc_setScenarioLevel;
        };
        case "LIST": {
            private _list = [] call comspec_sse_fnc_listDatasets;
            private _lines = [format ["%1 dataset(s) SSE :", count _list]];
            { _lines pushBack format ["• %1 (%2)", _x getOrDefault ["name", "?"], _x getOrDefault ["id", ""]]; } forEach _list;
            [_lines joinString "\n"] call comspec_sse_fnc_zeusNotify;
        };
        default {
            [_level, false] call comspec_sse_fnc_setScenarioLevel;
            private _applied = [_datasetId, _pos, _radius, _level] call comspec_sse_fnc_applyDataset;
            if (!(_applied isEqualType []) || {_applied isEqualTo []}) then {
                [format ["Dataset « %1 » : aucun rôle appliqué (dataset inconnu ou personne dans %2 m).", _datasetId, _radius], "warn"] call comspec_sse_fnc_zeusNotify;
            } else {
                [_applied select { _x isEqualType objNull }, 4] call comspec_sse_fnc_zeusBroadcastEnabled;
                [format ["Dataset « %1 » appliqué : %2 rôle(s) · niveau %3", _datasetId, count _applied, _level]] call comspec_sse_fnc_zeusNotify;
            };
        };
    };
};

private _pos = _ctx get "pos";
private _datasetId = _logic getVariable ["DatasetId", "falcon"];
private _level = _logic getVariable ["ScenarioLevel", 1];
if (_level isEqualType "") then { _level = parseNumber _level; };
private _radius = _logic getVariable ["Radius", 50];
private _action = toUpper (_logic getVariable ["Action", "APPLY"]);

if (_ctx get "zeus") then {
    private _ds = [] call comspec_sse_fnc_listDatasets;
    private _dsVals = _ds apply { _x getOrDefault ["id", ""] };
    private _dsLabs = _ds apply { format ["%1 (%2)", _x getOrDefault ["name", "?"], _x getOrDefault ["id", ""]] };
    if (_dsVals isEqualTo []) then { _dsVals = ["falcon"]; _dsLabs = ["falcon"]; };
    private _actions = ["APPLY", "LEVEL_ONLY", "LIST"];
    [
        "Directeur de scénario",
        [
            ["COMBO", "Action", "Appliquer : pose le dataset autour du module. Niveau seul : change ce que les joueurs peuvent apprendre.", [_actions, ["Appliquer le dataset", "Changer le niveau seulement", "Lister les datasets"], (_actions find _action) max 0]],
            ["COMBO", "Dataset", "Pack narratif complet (rôles, graine, documents).", [_dsVals, _dsLabs, ((_dsVals findIf { (toLower _x) isEqualTo (toLower _datasetId) }) max 0)]],
            ["COMBO", "Niveau de révélation", "0 Surface · 1 Tactique · 2 Terrain · 3 Vérité complète.", ["level", round _level] call comspec_sse_fnc_zeusComboData],
            ["SLIDER", "Rayon (m)", "Personnes concernées autour du module.", [10, 300, _radius, 0]]
        ],
        {
            params ["_values", "_args"];
            ([_args select 0] + _values) call (_args select 1);
        },
        [_pos, _apply],
        format ["Niveau actuel : %1.", missionNamespace getVariable ["comspec_sse_scenarioLevel", 1]]
    ] call comspec_sse_fnc_uiForm;
} else {
    [_pos, _action, _datasetId, _level, _radius] call _apply;
};

deleteVehicle _logic;
true
