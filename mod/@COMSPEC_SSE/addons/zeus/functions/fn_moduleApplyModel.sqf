/*
    Module « Appliquer un modèle SSE ».
    Zeus : liste des modèles si aucun identifiant n'est renseigné.
    Eden : applique l'identifiant renseigné aux entités synchronisées.
*/
params [
    ["_logic", objNull, [objNull]],
    ["_units", [], [[]]],
    ["_activated", true, [true]]
];

private _ctx = [_logic, _units, _activated] call comspec_sse_fnc_moduleContext;
if !(_ctx get "run") exitWith { true };

private _targets = _ctx get "targets";
private _modelId = trim (_logic getVariable ["ModelId", ""]);

if (_targets isEqualTo []) exitWith {
    ["Appliquer un modèle : aucune cible.\nPosez le module sur une personne ou un objet.", "error"] call comspec_sse_fnc_zeusNotify;
    deleteVehicle _logic;
    false
};

if (_modelId isNotEqualTo "") then {
    { [_x, _modelId, "ZEUS"] call comspec_sse_fnc_applyModel; } forEach _targets;
    [_targets, 2] call comspec_sse_fnc_zeusBroadcastEnabled;
    [format ["Modèle %1 appliqué sur %2 cible(s)", _modelId, count _targets]] call comspec_sse_fnc_zeusNotify;
} else {
    if (_ctx get "zeus") then {
        missionNamespace setVariable ["comspec_sse_zeusPendingTargets", _targets];
        [] call comspec_sse_fnc_openModelDialog;
    } else {
        ["Appliquer un modèle : identifiant de modèle vide (attribut du module).", "warn"] call comspec_sse_fnc_zeusNotify;
    };
};

deleteVehicle _logic;
true
