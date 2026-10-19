/*
    Charge un modèle par ID (mission > user > builtin).
    [_modelId] call comspec_sse_fnc_loadModel
*/
params [
    ["_modelId", "", [""]]
];

if (_modelId isEqualTo "") exitWith { nil };

// exitWith dans un if-then ne sort que du bloc then : on passe par _found.
private _resolve = {
    params ["_id"];
    private _found = nil;

    if (!isNil "comspec_sse_models_mission" && {comspec_sse_models_mission isEqualType createHashMap} && {_id in comspec_sse_models_mission}) then {
        _found = comspec_sse_models_mission get _id;
    };

    if (isNil "_found" && {hasInterface}) then {
        private _user = profileNamespace getVariable ["comspec_sse_models_user", createHashMap];
        if (_user isEqualType createHashMap && {_id in _user}) then {
            _found = _user get _id;
        };
    };

    if (isNil "_found" && {!isNil "comspec_sse_models_builtin"} && {comspec_sse_models_builtin isEqualType []}) then {
        private _idx = comspec_sse_models_builtin findIf { (_x getOrDefault ["id", ""]) == _id };
        if (_idx >= 0) then { _found = comspec_sse_models_builtin select _idx; };
    };

    if (isNil "_found") then { nil } else { _found }
};

private _valid = {
    params ["_m"];
    !isNil "_m" && {_m isEqualType createHashMap} && {count _m > 0}
};

private _model = [_modelId] call _resolve;
if ([_model] call _valid) exitWith { _model };

// Registre absent / partiel (PreInit raté, reload Eden, PBO partiel) → reconstruire une seule fois puis réessayer
if !(missionNamespace getVariable ["comspec_sse_modelsRebuilt", false]) then {
    missionNamespace setVariable ["comspec_sse_modelsRebuilt", true];
    [true] call comspec_sse_fnc_registerBuiltinModels;
    _model = [_modelId] call _resolve;
};
if ([_model] call _valid) exitWith { _model };

[format ["loadModel: modèle introuvable %1", _modelId], "WARN"] call comspec_sse_fnc_log;
nil
