/*
    Module « Semer des preuves » (Zeus et Eden).
    Pose des pièces de preuve (téléphones, clés USB, documents, carnets…) sur les sols des bâtiments du
    rayon, à défaut au sol. Elles partagent un cluster avec les personnes non joueurs du rayon.
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
    params ["_pos", "_radius", "_count", "_profile", "_complexity"];
    private _args = [_pos, _radius, _count, [["profile", _profile], ["complexity", _complexity]]];
    if (isServer) then {
        ["SEED", _args] call comspec_sse_fnc_siteRequest;
    } else {
        ["SEED", _args] remoteExecCall ["comspec_sse_fnc_siteRequest", 2];
        ["Semis demandé au serveur…"] call comspec_sse_fnc_siteNotify;
    };
};

private _pos = _ctx get "pos";
private _radius = _logic getVariable ["Radius", 30];
private _count = _logic getVariable ["Count", 6];
private _profile = _logic getVariable ["Profile", "INSURGENT"];
private _complexity = _logic getVariable ["Complexity", "STANDARD"];

if (_ctx get "zeus") then {
    [
        "Semer des preuves",
        [
            ["SLIDER", "Rayon (m)", "Les pièces vont d'abord dans les bâtiments du rayon.", [10, 150, _radius, 0]],
            ["NUMBER", "Nombre de pièces", "De 1 à 20.", [_count, 1, 20]],
            ["COMBO", "Profil", "Profil narratif du contenu.", ["profile", _profile] call comspec_sse_fnc_zeusComboData],
            ["COMBO", "Richesse", "Quantité d'indices.", ["complexity", _complexity] call comspec_sse_fnc_zeusComboData]
        ],
        {
            params ["_values", "_args"];
            ([_args select 0] + _values) call (_args select 1);
        },
        [_pos, _apply],
        format ["Centre : grille %1.", mapGridPosition _pos]
    ] call comspec_sse_fnc_uiForm;
} else {
    [_pos, _radius, _count, _profile, _complexity] call _apply;
};

deleteVehicle _logic;
true
