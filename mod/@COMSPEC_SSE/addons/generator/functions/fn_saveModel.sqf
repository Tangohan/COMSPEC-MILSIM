/*
    Sauvegarde un modèle (mission + optionnellement profil joueur).
    [_model, _persistLocal] call comspec_sse_fnc_saveModel
*/
params [
    ["_model", createHashMap, [createHashMap]],
    ["_persistLocal", true, [true]]
];

if (count _model == 0) exitWith { false };

private _id = _model getOrDefault ["id", ""];
if (_id isEqualTo "") exitWith { false };

_model set ["updatedAt", time];

if (isNil "comspec_sse_models_mission") then { comspec_sse_models_mission = createHashMap; };
comspec_sse_models_mission set [_id, _model];
if (isServer) then {
    publicVariable "comspec_sse_models_mission";
} else {
    // Client → serveur
    ["comspec_sse_saveModel", [_model]] call CBA_fnc_serverEvent;
};

if (_persistLocal && {hasInterface}) then {
    private _user = profileNamespace getVariable ["comspec_sse_models_user", createHashMap];
    if !(_user isEqualType createHashMap) then { _user = createHashMap; };
    _user set [_id, _model];
    profileNamespace setVariable ["comspec_sse_models_user", _user];
    saveProfileNamespace;
};

[format ["saveModel %1 (%2)", _id, _model getOrDefault ["name", "?"]]] call comspec_sse_fnc_log;
true
