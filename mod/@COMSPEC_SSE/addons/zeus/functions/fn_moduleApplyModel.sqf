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
// Eden : « Autre modèle » (identifiant saisi) passe avant la liste des modèles intégrés.
private _custom = trim (_logic getVariable ["ModelIdCustom", ""]);
if (_custom isNotEqualTo "") then { _modelId = _custom; };

if (_targets isEqualTo []) exitWith {
    ["Appliquer un modèle : aucune cible.\nPosez le module sur une personne ou un objet.", "error"] call comspec_sse_fnc_zeusNotify;
    deleteVehicle _logic;
    false
};

if (_modelId isNotEqualTo "") then {
    // Étalé (generateData + ACE par entité) : même file que zeusGenerateTargets.
    private _interval = 0.28;
    [
        _targets apply { [_x, _modelId, "ZEUS"] },
        {
            params ["_ent", "_id", "_by"];
            if (isNull _ent) exitWith {};
            [_ent, _id, _by] call comspec_sse_fnc_applyModel;
        },
        _interval
    ] call comspec_sse_fnc_queueEntityJobs;
    [_targets, (count _targets) * _interval + 2] call comspec_sse_fnc_zeusBroadcastEnabled;
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
