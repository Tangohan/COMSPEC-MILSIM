/*
    Liste tous les modèles disponibles.
    [_filterSource] call comspec_sse_fnc_listModels
    _filterSource: "" | "BUILTIN" | "USER" | "MISSION"
    Retourne array de HashMaps (copies légères)
*/
params [
    ["_filterSource", "", [""]]
];

private _out = [];

private _push = {
    params ["_m"];
    if (isNil "_m") exitWith {};
    private _src = _m getOrDefault ["source", "USER"];
    if (_filterSource != "" && {toUpper _src != toUpper _filterSource}) exitWith {};
    _out pushBack _m;
};

if (!isNil "comspec_sse_models_builtin") then {
    { [_x] call _push } forEach comspec_sse_models_builtin;
};

if (!isNil "comspec_sse_models_mission" && {comspec_sse_models_mission isEqualType createHashMap}) then {
    { [_y] call _push } forEach comspec_sse_models_mission;
};

if (hasInterface) then {
    private _user = profileNamespace getVariable ["comspec_sse_models_user", createHashMap];
    if (_user isEqualType createHashMap) then {
        {
            private _m = _y;
            // éviter doublons déjà en mission
            private _id = _m getOrDefault ["id", ""];
            private _already = _out findIf { (_x getOrDefault ["id", ""]) == _id };
            if (_already < 0) then { [_m] call _push };
        } forEach _user;
    };
};

_out
