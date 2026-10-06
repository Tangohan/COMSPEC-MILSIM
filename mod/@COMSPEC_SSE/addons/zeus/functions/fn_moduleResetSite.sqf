/*
    Module « Réinitialiser un site SSE » (nouveau, V0.8).
    Retire les données SSE de toutes les entités du rayon (pour rejouer un
    site ou repartir d'un décor vierge). Confirmation obligatoire en Zeus.
*/
params [
    ["_logic", objNull, [objNull]],
    ["_units", [], [[]]],
    ["_activated", true, [true]]
];

private _ctx = [_logic, _units, _activated] call comspec_sse_fnc_moduleContext;
if !(_ctx get "run") exitWith { true };

private _apply = {
    params ["_pos", "_radius", "_confirm"];
    if (!_confirm) exitWith {
        ["Réinitialisation annulée : cochez la confirmation.", "warn"] call comspec_sse_fnc_zeusNotify;
    };
    private _n = [nearestObjects [_pos, [], _radius]] call comspec_sse_fnc_zeusClearEntities;
    [format ["Site réinitialisé : %1 entité(s) nettoyée(s) dans %2 m.", _n, _radius], if (_n > 0) then { "info" } else { "warn" }] call comspec_sse_fnc_zeusNotify;
};

private _pos = _ctx get "pos";
private _radius = _logic getVariable ["Radius", 50];

if (_ctx get "zeus") then {
    [
        "Réinitialiser un site SSE",
        [
            ["SLIDER", "Rayon (m)", "Toutes les entités SSE de ce rayon perdent leurs données.", [5, 300, _radius, 0]],
            ["CHECK", "Confirmer la réinitialisation", "Action irréversible pour la session en cours.", false]
        ],
        {
            params ["_values", "_args"];
            ([_args select 0] + _values) call (_args select 1);
        },
        [_pos, _apply],
        "Le graphe garde les liens déjà créés ; les menus ACE restent mais n'ont plus de contenu."
    ] call comspec_sse_fnc_uiForm;
} else {
    [_pos, _radius, true] call _apply;
};

deleteVehicle _logic;
true
