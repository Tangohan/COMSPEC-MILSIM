/*
    Supprime un modèle utilisateur / mission (pas les builtins).
    [_modelId, _fromProfile] call comspec_sse_fnc_deleteModel
*/
params [
    ["_modelId", "", [""]],
    ["_fromProfile", true, [true]]
];

if (_modelId isEqualTo "") exitWith { false };
if ((toLower _modelId) find "builtin_" == 0) exitWith {
    ["Impossible de supprimer un modèle intégré", "WARN"] call comspec_sse_fnc_log;
    false
};

if (!isNil "comspec_sse_models_mission" && {comspec_sse_models_mission isEqualType createHashMap}) then {
    comspec_sse_models_mission deleteAt _modelId;
    if (isServer) then { publicVariable "comspec_sse_models_mission"; };
};

if (_fromProfile && {hasInterface}) then {
    private _user = profileNamespace getVariable ["comspec_sse_models_user", createHashMap];
    if (_user isEqualType createHashMap) then {
        _user deleteAt _modelId;
        profileNamespace setVariable ["comspec_sse_models_user", _user];
        saveProfileNamespace;
    };
};

[format ["deleteModel %1", _modelId]] call comspec_sse_fnc_log;
true
