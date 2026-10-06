/*
    Module « Rendre exploitable (SSE) ».
    Marque la cible comme exploitable ; le contenu détaillé est généré au
    premier examen (génération paresseuse).
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
    ["Rendre exploitable : aucune cible.\nPosez le module sur un objet ou une personne.", "error"] call comspec_sse_fnc_zeusNotify;
    deleteVehicle _logic;
    false
};

private _apply = {
    params ["_targets", "_type", "_profile", "_complexity"];
    {
        if (_type isNotEqualTo "AUTO") then {
            _x setVariable ["comspec_sse_forcedType", _type, true];
        };
        private _resolved = [_x] call comspec_sse_fnc_resolveEntityType;
        [_x, _resolved, _profile, _complexity] call comspec_sse_fnc_makeSearchable;
    } forEach _targets;
    [_targets, 1] call comspec_sse_fnc_zeusBroadcastEnabled;
    [format ["SSE activé sur %1 cible(s) — contenu généré au premier examen.", count _targets]] call comspec_sse_fnc_zeusNotify;
};

private _type = toUpper (_logic getVariable ["EntityType", "AUTO"]);
private _profile = _logic getVariable ["Profile", "RANDOM"];
private _complexity = _logic getVariable ["Complexity", "STANDARD"];

if (_ctx get "zeus") then {
    [
        "Rendre exploitable (SSE)",
        [
            ["COMBO", "Nature de l'objet", "Automatique : déduit de la classe de l'objet (téléphone, ordinateur, document…).", ["entityType", _type] call comspec_sse_fnc_zeusComboData],
            ["COMBO", "Profil", "Profil narratif du contenu.", ["profile", _profile] call comspec_sse_fnc_zeusComboData],
            ["COMBO", "Richesse", "Quantité d'indices.", ["complexity", _complexity] call comspec_sse_fnc_zeusComboData]
        ],
        {
            params ["_values", "_args"];
            ([_args select 0] + _values) call (_args select 1);
        },
        [_targets, _apply],
        format ["%1 cible(s). Les menus ACE « SSE » apparaissent sur l'objet.", count _targets]
    ] call comspec_sse_fnc_uiForm;
} else {
    [_targets, _type, _profile, _complexity] call _apply;
};

deleteVehicle _logic;
true
