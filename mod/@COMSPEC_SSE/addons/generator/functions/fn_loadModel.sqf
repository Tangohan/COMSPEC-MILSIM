/*
    Charge un modèle par ID (mission > user > builtin).
    [_modelId] call comspec_sse_fnc_loadModel
*/
params [
    ["_modelId", "", [""]]
];

if (_modelId isEqualTo "") exitWith { nil };

private _resolve = {
    params ["_id"];

    if (!isNil "comspec_sse_models_mission" && {comspec_sse_models_mission isEqualType createHashMap}) then {
        if (_id in comspec_sse_models_mission) exitWith {
            comspec_sse_models_mission get _id
        };
    };

    if (hasInterface) then {
        private _user = profileNamespace getVariable ["comspec_sse_models_user", createHashMap];
        if (_user isEqualType createHashMap && {_id in _user}) exitWith {
            _user get _id
        };
    };

    if (!isNil "comspec_sse_models_builtin" && {comspec_sse_models_builtin isEqualType []}) then {
        private _idx = comspec_sse_models_builtin findIf { (_x getOrDefault ["id", ""]) == _id };
        if (_idx >= 0) exitWith { comspec_sse_models_builtin select _idx };
    };

    nil
};

private _model = [_modelId] call _resolve;
if (!isNil "_model" && {_model isEqualType createHashMap} && {count _model > 0}) exitWith { _model };

// Registre absent / partiel (PreInit raté, reload Eden, PBO partiel) → reconstruire puis réessayer
[] call comspec_sse_fnc_registerBuiltinModels;
_model = [_modelId] call _resolve;
if (!isNil "_model" && {_model isEqualType createHashMap} && {count _model > 0}) exitWith { _model };

// Dernier recours : rebuild forcé (écrase entrées corrompues / partielles)
[true] call comspec_sse_fnc_registerBuiltinModels;
_model = [_modelId] call _resolve;
if (!isNil "_model" && {_model isEqualType createHashMap} && {count _model > 0}) exitWith { _model };

nil
