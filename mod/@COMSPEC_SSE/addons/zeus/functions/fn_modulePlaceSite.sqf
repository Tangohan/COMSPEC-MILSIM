/*
    Module « Poser un site SSE » (Zeus et Eden).
    Site préfabriqué (planque, labo d'IED, faux papiers, cache, PC, boîte aux lettres morte, hawala) :
    mobilier, pièces de preuve, occupants et pièges optionnels, dossier qui cite d'autres lieux.
    Création sur le serveur (comspec_sse_fnc_siteRequest) ; en Zeus, le site suit l'orientation de la caméra.
*/
params [
    ["_logic", objNull, [objNull]],
    ["_units", [], [[]]],
    ["_activated", true, [true]]
];

private _ctx = [_logic, _units, _activated] call comspec_sse_fnc_moduleContext;
if !(_ctx get "run") exitWith { true };

private _apply = {
    params ["_pos", "_dir", "_type", "_occupants", "_side", "_armed", "_complexity", "_traps", "_useBuilding"];
    private _args = [_type, _pos, _dir, [
        ["occupants", _occupants],
        ["side", _side],
        ["armed", _armed],
        ["complexity", _complexity],
        ["traps", _traps],
        ["usebuilding", _useBuilding]
    ]];
    if (isServer) then {
        ["SITE", _args] call comspec_sse_fnc_siteRequest;
    } else {
        ["SITE", _args] remoteExecCall ["comspec_sse_fnc_siteRequest", 2];
        ["Site demandé au serveur…"] call comspec_sse_fnc_siteNotify;
    };
};

private _pos = _ctx get "pos";
private _type = _logic getVariable ["SiteType", "SAFEHOUSE"];
private _occupants = _logic getVariable ["Occupants", -1];
private _side = _logic getVariable ["Side", "GUER"];
private _armed = _logic getVariable ["Armed", true];
private _complexity = _logic getVariable ["Complexity", ""];
private _traps = _logic getVariable ["Traps", false];
private _useBuilding = _logic getVariable ["UseBuilding", true];

if (_ctx get "zeus") then {
    private _dir = if (isNull curatorCamera) then { 0 } else { getDir curatorCamera };
    [
        "Poser un site SSE",
        [
            ["COMBO", "Type de site", "Mobilier, pièces de preuve et thème narratif propres à chaque site.", ["siteType", _type] call comspec_sse_fnc_zeusComboData],
            ["COMBO", "Occupants", "Personnes présentes : la première devient le chef de la cellule.", ["occupants", _occupants] call comspec_sse_fnc_zeusComboData],
            ["COMBO", "Camp des occupants", "Camp des occupants créés.", ["siteSide", _side] call comspec_sse_fnc_zeusComboData],
            ["CHECK", "Occupants armés", "Décoché : occupants sans arme (jamais armés pour des civils).", _armed],
            ["COMBO", "Richesse", "Quantité d'indices des fiches.", ["siteComplexity", _complexity] call comspec_sse_fnc_zeusComboData],
            ["CHECK", "Pièges", "Mine à fil à l'entrée, engin piégé dans un labo d'IED.", _traps],
            ["CHECK", "Dans un bâtiment proche", "Cherche un bâtiment habitable à moins de 40 m ; sinon le site est posé au sol.", _useBuilding]
        ],
        {
            params ["_values", "_args"];
            ([_args select 0, _args select 1] + _values) call (_args select 2);
        },
        [_pos, _dir, _apply],
        format ["Position : grille %1. Le site suit l'orientation de la caméra.", mapGridPosition _pos]
    ] call comspec_sse_fnc_uiForm;
} else {
    [_pos, getDir _logic, _type, _occupants, _side, _armed, _complexity, _traps, _useBuilding] call _apply;
};

deleteVehicle _logic;
true
