/*
    Module « Site d'entraînement » : pack scénario (aléatoire ou choisi)
    autour du module — et non plus autour du joueur.
*/
params [
    ["_logic", objNull, [objNull]],
    ["_units", [], [[]]],
    ["_activated", true, [true]]
];

private _ctx = [_logic, _units, _activated] call comspec_sse_fnc_moduleContext;
if !(_ctx get "run") exitWith { true };

private _apply = {
    params ["_pos", "_pack", "_radius"];
    if (_pack in ["", "RANDOM"]) then {
        _pack = selectRandom ["INSURGENT_CELL", "SAFEHOUSE", "IED_WORKSHOP", "WEAPONS_DEPOT", "FINANCIAL_NODE"];
    };
    [_pack, _pos, _radius] call comspec_sse_fnc_loadScenarioPack;
    [nearestObjects [_pos, ["ThingX", "ReammoBox_F", "WeaponHolder", "LandVehicle", "Static"], _radius], 8] call comspec_sse_fnc_zeusBroadcastEnabled;
    [format ["Site d'entraînement : %1 — rayon %2 m", _pack, _radius]] call comspec_sse_fnc_zeusNotify;
};

private _pos = _ctx get "pos";
private _pack = toUpper (_logic getVariable ["Pack", "RANDOM"]);
private _radius = _logic getVariable ["Radius", 60];

if (_ctx get "zeus") then {
    (["pack"] call comspec_sse_fnc_zeusChoices) params ["_vals", "_labs"];
    _vals = +_vals; _labs = +_labs;
    _vals set [0, "RANDOM"];
    _labs set [0, "Aléatoire"];
    private _idx = (_vals find _pack) max 0;
    [
        "Site d'entraînement SSE",
        [
            ["COMBO", "Scénario", "Aléatoire : tiré parmi cellule, planque, atelier IED, dépôt, nœud financier.", [_vals, _labs, _idx]],
            ["SLIDER", "Rayon (m)", "Personnes et objets dans ce rayon.", [10, 200, _radius, 0]]
        ],
        {
            params ["_values", "_args"];
            ([_args select 0] + _values) call (_args select 1);
        },
        [_pos, _apply]
    ] call comspec_sse_fnc_uiForm;
} else {
    [_pos, _pack, _radius] call _apply;
};

deleteVehicle _logic;
true
