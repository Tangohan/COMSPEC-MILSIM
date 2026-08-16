/*
    Charge un pack scénario prédéfini.
    [_packId, _center, _radius] call comspec_sse_fnc_loadScenarioPack
*/
params [
    ["_packId", "INSURGENT_CELL", [""]],
    ["_center", objNull, [objNull, []]],
    ["_radius", 40, [0]]
];

private _packs = createHashMapFromArray [
    ["INSURGENT_CELL", createHashMapFromArray [["profile", "INSURGENT"], ["theme", "weapons_cache"], ["complexity", "DETAILED"], ["count", 5]]],
    ["SMUGGLING_NETWORK", createHashMapFromArray [["profile", "COURIER"], ["theme", "smuggling"], ["complexity", "DETAILED"], ["count", 4]]],
    ["WEAPONS_DEPOT", createHashMapFromArray [["profile", "LOGISTICS"], ["theme", "weapons_cache"], ["complexity", "HIGH_VALUE"], ["count", 3]]],
    ["COMMAND_POST", createHashMapFromArray [["profile", "COMMANDER"], ["theme", "meeting_alpha"], ["complexity", "HIGH_VALUE"], ["count", 4]]],
    ["SAFEHOUSE", createHashMapFromArray [["profile", "INSURGENT"], ["theme", "safehouse"], ["complexity", "DETAILED"], ["count", 3]]],
    ["IED_WORKSHOP", createHashMapFromArray [["profile", "TECHNICIAN"], ["theme", "ied_cell"], ["complexity", "DETAILED"], ["count", 3]]],
    ["FINANCIAL_NODE", createHashMapFromArray [["profile", "FINANCIER"], ["theme", "finance_drop"], ["complexity", "DETAILED"], ["count", 2]]],
    ["INTELLIGENCE_CELL", createHashMapFromArray [["profile", "INTELLIGENCE"], ["theme", "propaganda"], ["complexity", "HIGH_VALUE"], ["count", 3]]]
];

private _id = toUpper _packId;
// Dataset FALCON (LOT 8) — rôles + graine stables
if (_id in ["FALCON", "FALCON_IRAQ", "FALCON_IQ_2012", "DATASET_FALCON"]) exitWith {
    private _level = missionNamespace getVariable ["comspec_sse_scenarioLevel", 1];
    [_packId, _center, _radius, _level] call comspec_sse_fnc_applyDataset
};

private _pack = _packs getOrDefault [_id, _packs get "INSURGENT_CELL"];
private _brief = format ["Cellule %1 — %2 personnes", _packId, _pack get "count"];
[_brief, _center, _radius, _pack] call comspec_sse_fnc_generateFromBrief
