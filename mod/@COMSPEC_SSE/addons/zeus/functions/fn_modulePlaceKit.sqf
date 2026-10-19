/*
    Module « Poser une caisse de kit » (Zeus et Eden).
    Kit SSE d'équipe, kit biométrie, kit exploitation numérique ou valise DOMEX de terrain : caisse réelle
    à ouvrir, au lieu d'objets qui apparaissent dans l'inventaire. Création sur le serveur.
*/
params [
    ["_logic", objNull, [objNull]],
    ["_units", [], [[]]],
    ["_activated", true, [true]]
];

private _ctx = [_logic, _units, _activated] call comspec_sse_fnc_moduleContext;
if !(_ctx get "run") exitWith { true };

private _apply = {
    params ["_pos", "_dir", "_kit"];
    if (isServer) then {
        ["KIT", [_kit, _pos, _dir]] call comspec_sse_fnc_siteRequest;
    } else {
        ["KIT", [_kit, _pos, _dir]] remoteExecCall ["comspec_sse_fnc_siteRequest", 2];
    };
};

private _pos = _ctx get "pos";
private _kit = _logic getVariable ["Kit", "TEAM"];

if (_ctx get "zeus") then {
    private _dir = if (isNull curatorCamera) then { 0 } else { getDir curatorCamera };
    [
        "Poser une caisse de kit",
        [
            ["COMBO", "Kit", "Contenu de la caisse.", ["kit", _kit] call comspec_sse_fnc_zeusComboData]
        ],
        {
            params ["_values", "_args"];
            ([_args select 0, _args select 1] + _values) call (_args select 2);
        },
        [_pos, _dir, _apply],
        format ["Position : grille %1.", mapGridPosition _pos]
    ] call comspec_sse_fnc_uiForm;
} else {
    [_pos, getDir _logic, _kit] call _apply;
};

deleteVehicle _logic;
true
