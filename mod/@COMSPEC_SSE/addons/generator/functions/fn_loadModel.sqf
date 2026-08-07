/*
    Charge un modèle par ID (mission > user > builtin).
    [_modelId] call comspec_sse_fnc_loadModel
*/
params [
    ["_modelId", "", [""]]
];

if (_modelId isEqualTo "") exitWith { nil };

if (!isNil "comspec_sse_models_mission" && {comspec_sse_models_mission isEqualType createHashMap}) then {
    if (_modelId in comspec_sse_models_mission) exitWith {
        comspec_sse_models_mission get _modelId
    };
};

if (hasInterface) then {
    private _user = profileNamespace getVariable ["comspec_sse_models_user", createHashMap];
    if (_user isEqualType createHashMap && {_modelId in _user}) exitWith {
        _user get _modelId
    };
};

if (!isNil "comspec_sse_models_builtin") then {
    private _idx = comspec_sse_models_builtin findIf { (_x getOrDefault ["id", ""]) == _modelId };
    if (_idx >= 0) exitWith { comspec_sse_models_builtin select _idx };
};

nil
