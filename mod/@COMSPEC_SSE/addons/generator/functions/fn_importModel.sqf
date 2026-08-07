/*
    Importe un modèle depuis pairs / hashmap et le sauvegarde.
    [_serialized, _newName] call comspec_sse_fnc_importModel
*/
params [
    ["_serialized", [], [[], createHashMap]],
    ["_newName", "", [""]]
];

private _model = if (_serialized isEqualType createHashMap) then {
    +_serialized
} else {
    private _restored = [_serialized] call comspec_sse_fnc_deserializeData;
    if (_restored isEqualType createHashMap) then { _restored } else {
        // pairs -> hashmap
        private _h = createHashMap;
        if (_restored isEqualType []) then {
            { _x params ["_k", "_v"]; _h set [_k, _v]; } forEach _restored;
        };
        _h
    };
};

if (count _model == 0) exitWith { nil };

if (_newName != "") then { _model set ["name", _newName]; };
_model set ["source", "USER"];
_model set ["id", format ["mdl_import_%1_%2", round time, floor random 9999]];
_model set ["author", profileName];

[_model, true] call comspec_sse_fnc_saveModel;
_model
