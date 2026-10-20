/*
    Module « Peupler un bâtiment » (Zeus et Eden).
    Le bâtiment sous le module (15 m au plus, avec des positions intérieures) devient un site SSE :
    mobilier réparti dans ses pièces (buildingPos), preuves, occupants et pièges selon le type choisi.
    Réutilisable sur toutes les cartes : aucune composition n'est figée sur un modèle de bâtiment.
*/
params [
    ["_logic", objNull, [objNull]],
    ["_units", [], [[]]],
    ["_activated", true, [true]]
];

private _ctx = [_logic, _units, _activated] call comspec_sse_fnc_moduleContext;
if !(_ctx get "run") exitWith { true };

private _pos = _ctx get "pos";
private _building = ((nearestObjects [_pos, ["House"], 15]) select { alive _x && {count (_x buildingPos -1) >= 2} }) param [0, objNull];
if (isNull _building) exitWith {
    if (_ctx get "zeus") then {
        ["Posez ce module sur un bâtiment habitable (avec des pièces intérieures).", [], "warn"] call comspec_sse_fnc_siteNotify;
    } else {
        [format ["PopulateBuilding : aucun bâtiment habitable en %1", mapGridPosition _pos], "WARNING"] call comspec_sse_fnc_log;
    };
    deleteVehicle _logic;
    true
};

private _apply = {
    params ["_bPos", "_type", "_occupants", "_side", "_armed", "_complexity", "_traps"];
    private _args = [_type, _bPos, [
        ["occupants", _occupants],
        ["side", _side],
        ["armed", _armed],
        ["complexity", _complexity],
        ["traps", _traps]
    ]];
    if (isServer) then {
        ["BUILDING", _args] call comspec_sse_fnc_siteRequest;
    } else {
        ["BUILDING", _args] remoteExecCall ["comspec_sse_fnc_siteRequest", 2];
        ["Site demandé au serveur…"] call comspec_sse_fnc_siteNotify;
    };
};

private _bPos = getPosATL _building;
private _type = _logic getVariable ["SiteType", "SAFEHOUSE"];
private _occupants = _logic getVariable ["Occupants", -1];
private _side = _logic getVariable ["Side", "GUER"];
private _armed = _logic getVariable ["Armed", true];
private _complexity = _logic getVariable ["Complexity", ""];
private _traps = _logic getVariable ["Traps", false];

if (_ctx get "zeus") then {
    [
        "Peupler un bâtiment",
        [
            ["COMBO", "Type de site", "Ce que le bâtiment abrite.", ["siteType", _type] call comspec_sse_fnc_zeusComboData],
            ["COMBO", "Occupants", "Placés dans les pièces ; la première personne devient le chef de la cellule.", ["occupants", _occupants] call comspec_sse_fnc_zeusComboData],
            ["COMBO", "Camp des occupants", "Camp des occupants créés.", ["siteSide", _side] call comspec_sse_fnc_zeusComboData],
            ["CHECK", "Occupants armés", "Décoché : occupants sans arme (jamais armés pour des civils).", _armed],
            ["COMBO", "Richesse", "Quantité d'indices des fiches.", ["siteComplexity", _complexity] call comspec_sse_fnc_zeusComboData],
            ["CHECK", "Pièges", "Mine à fil sur la sortie du bâtiment, engin piégé dans un labo d'IED.", _traps]
        ],
        {
            params ["_values", "_args"];
            ([_args select 0] + _values) call (_args select 1);
        },
        [_bPos, _apply],
        format ["Bâtiment : %1, %2 position(s) intérieure(s), grille %3.",
            getText (configOf _building >> "displayName"), count (_building buildingPos -1), mapGridPosition _bPos]
    ] call comspec_sse_fnc_uiForm;
} else {
    [_bPos, _type, _occupants, _side, _armed, _complexity, _traps] call _apply;
};

deleteVehicle _logic;
true
