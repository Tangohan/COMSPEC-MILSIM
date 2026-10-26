/*
    Opérations atomiques sur une section, appliquées par le serveur sur SA copie
    (aucune perte quand deux joueurs travaillent sur la même fiche).
    [_op, _entity, _args] call comspec_sse_fnc_recordOp

    "merge"  : [_section, _patch(HashMap)]           fusion clé par clé dans une section HashMap
    "append" : [_section, _items(ARRAY), _idKey("")] ajout en fin de section ARRAY, sans doublon d'_idKey
    "batch"  : [[_op, _args], ...]                    plusieurs merge/append appliqués puis un seul commit public
    Sur un client : application locale immédiate (affichage) puis envoi au serveur.
*/
params [
    ["_op", "", [""]],
    ["_entity", objNull, [objNull]],
    ["_args", [], [[]]]
];

if (isNull _entity) exitWith { false };

private _apply = {
    params ["_op", "_entity", "_args", "_public"];
    switch (toLower _op) do {
        case "merge": {
            _args params [["_section", "", [""]], ["_patch", createHashMap, [createHashMap]]];
            private _cur = [_entity, _section] call comspec_sse_fnc_getSection;
            _cur = if (!isNil "_cur" && {_cur isEqualType createHashMap}) then { +_cur } else { createHashMap };
            { _cur set [_x, _y]; } forEach _patch;
            [_entity, _section, _cur, _public] call comspec_sse_fnc_setSection;
        };
        case "append": {
            _args params [["_section", "", [""]], ["_items", [], [[]]], ["_idKey", "", [""]]];
            private _cur = [_entity, _section] call comspec_sse_fnc_getSection;
            _cur = if (!isNil "_cur" && {_cur isEqualType []}) then { +_cur } else { [] };
            {
                private _item = _x;
                private _dup = _idKey isNotEqualTo "" && {_item isEqualType createHashMap} && {
                    private _id = _item getOrDefault [_idKey, ""];
                    (_cur findIf { _x isEqualType createHashMap && {(_x getOrDefault [_idKey, ""]) isEqualTo _id} }) >= 0
                };
                if (!_dup) then { _cur pushBack _item; };
            } forEach _items;
            [_entity, _section, _cur, _public] call comspec_sse_fnc_setSection;
        };
        case "batch": {
            {
                if (_x isEqualType [] && {count _x >= 2}) then {
                    _x params [["_subOp", "", [""]], ["_subArgs", [], [[]]]];
                    if ((toLower _subOp) isNotEqualTo "batch") then {
                        [_subOp, _entity, _subArgs, false] call _apply;
                    };
                };
            } forEach _args;
            if (_public) then {
                private _data = [_entity] call comspec_sse_fnc_getData;
                if (!isNil "_data" && {_data isEqualType []}) then {
                    [_entity, _data, true] call comspec_sse_fnc_setData;
                };
            };
        };
        default {
            [format ["recordOp inconnue : %1", _op], "WARN"] call comspec_sse_fnc_log;
        };
    };
};

if (isServer) exitWith {
    [_op, _entity, _args, true] call _apply;
    true
};

[_op, _entity, _args, false] call _apply;
["comspec_sse_recordOp", [_op, _entity, _args]] call CBA_fnc_serverEvent;
true
