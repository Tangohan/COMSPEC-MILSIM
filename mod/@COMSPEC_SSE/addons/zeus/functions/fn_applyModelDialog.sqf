/*
    Valide le dialogue Zeus « Appliquer un modèle SSE » (idd 93030).
*/
private _display = findDisplay 93030;
if (isNull _display) exitWith { false };

private _lb = _display displayCtrl 93031;
private _sel = lbCurSel _lb;
private _id = if (_sel < 0) then { "" } else { _lb lbData _sel };
if (_id isEqualTo "") exitWith {
    ["Sélectionnez un modèle dans la liste.", "warn"] call comspec_sse_fnc_zeusNotify;
    false
};

private _targets = (missionNamespace getVariable ["comspec_sse_zeusPendingTargets", []]) select { !isNull _x };
if (_targets isEqualTo []) exitWith {
    ["Aucune cible : posez le module sur une personne ou un objet.", "error"] call comspec_sse_fnc_zeusNotify;
    false
};

// Étalé (generateData + ACE par entité) : même file que zeusGenerateTargets.
private _interval = 0.28;
[
    _targets apply { [_x, _id, "ZEUS"] },
    {
        params ["_ent", "_modelId", "_by"];
        if (isNull _ent) exitWith {};
        [_ent, _modelId, _by] call comspec_sse_fnc_applyModel;
    },
    _interval
] call comspec_sse_fnc_queueEntityJobs;
[_targets, (count _targets) * _interval + 2] call comspec_sse_fnc_zeusBroadcastEnabled;
missionNamespace setVariable ["comspec_sse_zeusPendingTargets", []];

closeDialog 1;
private _model = [_id] call comspec_sse_fnc_loadModel;
private _name = if (isNil "_model") then { _id } else { _model getOrDefault ["name", _id] };
[format ["Modèle « %1 » appliqué sur %2 cible(s)", _name, count _targets]] call comspec_sse_fnc_zeusNotify;
true
