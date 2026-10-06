/*
    Module « Enregistrer comme modèle SSE » : capture la cible (générée au
    besoin) en modèle réutilisable (mission + profil local du Zeus).
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
    ["Enregistrer un modèle : posez le module sur l'entité à capturer.", "error"] call comspec_sse_fnc_zeusNotify;
    deleteVehicle _logic;
    false
};

private _apply = {
    params ["_targets", "_name"];
    _name = trim _name;
    if (_name isEqualTo "") then { _name = "Modèle SSE"; };
    missionNamespace setVariable ["comspec_sse_zeusSaveModelCount", 0];
    private _jobs = _targets apply { [_x, _name, count _targets] };
    [
        _jobs,
        {
            params ["_ent", "_name", "_total"];
            if (isNull _ent) exitWith {};
            if (isNil {[_ent] call comspec_sse_fnc_getData}) then {
                if !(_ent getVariable ["comspec_sse_generating", false]) then {
                    [_ent, "INSURGENT", "DETAILED", "ZEUS"] call comspec_sse_fnc_generateData;
                };
            };
            private _n = missionNamespace getVariable ["comspec_sse_zeusSaveModelCount", 0];
            private _label = if (_total > 1) then { format ["%1 (%2)", _name, _n + 1] } else { _name };
            private _model = [_ent, _label] call comspec_sse_fnc_modelFromEntity;
            if (!isNil "_model") then {
                missionNamespace setVariable ["comspec_sse_zeusSaveModelCount", _n + 1];
            };
        },
        0.15
    ] call comspec_sse_fnc_queueEntityJobs;

    if (!isNil "CBA_fnc_waitAndExecute") then {
        [{
            private _n = missionNamespace getVariable ["comspec_sse_zeusSaveModelCount", 0];
            [format ["%1 modèle(s) SSE enregistré(s) (mission + profil local)", _n], if (_n > 0) then { "info" } else { "warn" }] call comspec_sse_fnc_zeusNotify;
        }, [], ((count _jobs) * 0.15) + 0.5] call CBA_fnc_waitAndExecute;
    };
};

private _name = _logic getVariable ["ModelName", "Mon modèle SSE"];

if (_ctx get "zeus") then {
    [
        "Enregistrer comme modèle SSE",
        [
            ["EDIT", "Nom du modèle", "Nom affiché dans la liste des modèles. Plusieurs cibles : numérotées.", _name]
        ],
        {
            params ["_values", "_args"];
            [_args select 0, _values select 0] call (_args select 1);
        },
        [_targets, _apply],
        format ["%1 cible(s) à capturer. Une cible sans données est d'abord générée (insurgé, détaillé).", count _targets]
    ] call comspec_sse_fnc_uiForm;
} else {
    [_targets, _name] call _apply;
};

deleteVehicle _logic;
true
