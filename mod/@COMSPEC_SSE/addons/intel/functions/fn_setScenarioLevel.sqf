/*
    Définit le niveau de scénario Zeus (0–3).
    [_level, _announce] call comspec_sse_fnc_setScenarioLevel
*/
params [
    ["_level", 1, [0]],
    ["_announce", true, [true]]
];

_level = (round _level) max 0 min 3;
missionNamespace setVariable ["comspec_sse_scenarioLevel", _level, true];

private _labels = ["Surface", "Tactique", "Terrain", "Vérité complète"];
private _label = _labels select _level;

if (_announce && {hasInterface}) then {
    systemChat format ["[SSE] Niveau scénario : %1 (%2)", _level, _label];
    hint format ["Scenario Director\nNiveau %1 — %2", _level, _label];
};

[format ["setScenarioLevel %1 (%2)", _level, _label], "WARN"] call comspec_sse_fnc_log;
_level
