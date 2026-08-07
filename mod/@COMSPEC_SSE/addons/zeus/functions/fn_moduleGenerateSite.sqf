/*
    Module Zeus — Générer site SSE
*/
params [
    ["_logic", objNull, [objNull]],
    ["_units", [], [[]]],
    ["_activated", true, [true]]
];

if (!_activated) exitWith { true };

private _radius = _logic getVariable ["Radius", 35];
private _profile = _logic getVariable ["Profile", "INSURGENT"];
private _complexity = _logic getVariable ["Complexity", "DETAILED"];
private _maxObjects = _logic getVariable ["MaxObjects", 8];
private _pos = getPosATL _logic;

// Si zone définie via module area
private _area = _logic getVariable ["objectarea", []];
if (count _area >= 1) then {
    _radius = _area select 0;
};

private _processed = [
    _pos,
    _radius,
    _profile,
    _complexity,
    createHashMapFromArray [
        ["maxobjects", _maxObjects],
        ["digital", true],
        ["documents", true],
        ["network", true]
    ]
] call comspec_sse_fnc_generateSite;

if (hasInterface) then {
    hint format ["Site SSE généré — %1 entités (rayon %2 m)", count _processed, _radius];
};

deleteVehicle _logic;
true
