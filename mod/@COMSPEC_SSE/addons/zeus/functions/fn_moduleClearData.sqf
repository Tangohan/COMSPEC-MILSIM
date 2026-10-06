/*
    Module « Effacer les données SSE » de la cible (confirmation en Zeus).
*/
params [
    ["_logic", objNull, [objNull]],
    ["_units", [], [[]]],
    ["_activated", true, [true]]
];

private _ctx = [_logic, _units, _activated] call comspec_sse_fnc_moduleContext;
if !(_ctx get "run") exitWith { true };

private _targets = _ctx get "targets";
if (_targets isEqualTo []) exitWith {
    ["Effacer : posez le module sur une entité SSE.", "error"] call comspec_sse_fnc_zeusNotify;
    deleteVehicle _logic;
    false
};

private _apply = {
    params ["_targets"];
    private _n = [_targets] call comspec_sse_fnc_zeusClearEntities;
    [format ["Données SSE effacées sur %1 entité(s).", _n], if (_n > 0) then { "info" } else { "warn" }] call comspec_sse_fnc_zeusNotify;
};

if (_ctx get "zeus") then {
    [
        "Effacer les données SSE",
        [
            ["CHECK", "Confirmer l'effacement", "Les données générées ou saisies sont perdues. Le graphe garde les liens déjà créés.", false]
        ],
        {
            params ["_values", "_args"];
            if !(_values select 0) exitWith {
                ["Effacement annulé : cochez la confirmation.", "warn"] call comspec_sse_fnc_zeusNotify;
            };
            [_args select 0] call (_args select 1);
        },
        [_targets, _apply],
        format ["%1 cible(s) concernée(s).", count _targets]
    ] call comspec_sse_fnc_uiForm;
} else {
    [_targets] call _apply;
};

deleteVehicle _logic;
true
