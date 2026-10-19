/*
    Module « Réseau multi-sites » (Zeus et Eden).
    3 à 6 sites reliés autour du module, près de lieux réels de la carte : chaque site cite le suivant
    (thème, dossier), le PC de la cellule cite tous les autres, les chefs sont reliés entre eux.
    Création sur le serveur (comspec_sse_fnc_siteRequest).
*/
params [
    ["_logic", objNull, [objNull]],
    ["_units", [], [[]]],
    ["_activated", true, [true]]
];

private _ctx = [_logic, _units, _activated] call comspec_sse_fnc_moduleContext;
if !(_ctx get "run") exitWith { true };

private _apply = {
    params ["_pos", "_count", "_radiusKm", "_occupants", "_side", "_armed", "_complexity", "_traps"];
    private _args = [_pos, _count, _radiusKm * 1000, [
        ["occupants", _occupants],
        ["side", _side],
        ["armed", _armed],
        ["complexity", _complexity],
        ["traps", _traps]
    ]];
    if (isServer) then {
        ["NETWORK", _args] call comspec_sse_fnc_siteRequest;
    } else {
        ["NETWORK", _args] remoteExecCall ["comspec_sse_fnc_siteRequest", 2];
        ["Réseau demandé au serveur…"] call comspec_sse_fnc_siteNotify;
    };
};

private _pos = _ctx get "pos";
private _count = _logic getVariable ["Count", 4];
private _radiusKm = _logic getVariable ["RadiusKm", 3];
private _occupants = _logic getVariable ["Occupants", -1];
private _side = _logic getVariable ["Side", "GUER"];
private _armed = _logic getVariable ["Armed", true];
private _complexity = _logic getVariable ["Complexity", ""];
private _traps = _logic getVariable ["Traps", false];

if (_ctx get "zeus") then {
    [
        "Réseau multi-sites",
        [
            ["NUMBER", "Nombre de sites", "De 3 à 6 ; le dernier est toujours le PC de la cellule.", [_count, 3, 6]],
            ["SLIDER", "Rayon (km)", "Les sites sont posés près de villes et villages réels dans ce rayon.", [1, 10, _radiusKm, 1]],
            ["COMBO", "Occupants par site", "Selon le site : planque et PC occupés, cache et boîte aux lettres vides.", ["occupants", _occupants] call comspec_sse_fnc_zeusComboData],
            ["COMBO", "Camp des occupants", "Camp des occupants créés.", ["siteSide", _side] call comspec_sse_fnc_zeusComboData],
            ["CHECK", "Occupants armés", "Décoché : occupants sans arme (jamais armés pour des civils).", _armed],
            ["COMBO", "Richesse", "Quantité d'indices des fiches.", ["siteComplexity", _complexity] call comspec_sse_fnc_zeusComboData],
            ["CHECK", "Pièges", "Pièges de chaque site.", _traps]
        ],
        {
            params ["_values", "_args"];
            ([_args select 0] + _values) call (_args select 1);
        },
        [_pos, _apply],
        format ["Centre : grille %1. La pose prend quelques secondes.", mapGridPosition _pos]
    ] call comspec_sse_fnc_uiForm;
} else {
    [_pos, _count, _radiusKm, _occupants, _side, _armed, _complexity, _traps] call _apply;
};

deleteVehicle _logic;
true
