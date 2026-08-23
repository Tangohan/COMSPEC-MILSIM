/*
    ACE insertChildren DOIT renvoyer des triplets :
      [action createAction (11 cases), enfants, cible]
    Pas des tableaux createAction bruts — sinon collectActiveActionTree
    fait « select 10 » sur l’identifiant (chaîne) et plante.
*/
params [
    ["_target", objNull, [objNull]],
    ["_actions", [], [[]]]
];

private _out = [];

{
    if (!(_x isEqualType []) || {_x isEqualTo []}) then {
    } else {
        private _act = _x;
        private _kids = [];
        private _obj = _target;
        // Triplet ACE déjà formé : [action, enfants, cible]
        if (
            ((count _x) >= 2)
            && {(_x select 0) isEqualType []}
            && {(count (_x select 0)) >= 5}
            && {((_x select 0) select 0) isEqualType ""}
        ) then {
            _act = _x select 0;
            if ((count _x) > 1 && {(_x select 1) isEqualType []}) then {
                _kids = _x select 1;
            };
            if ((count _x) > 2 && {(_x select 2) isEqualType objNull}) then {
                _obj = _x select 2;
            };
        };
        _act = [_act] call comspec_sse_fnc_acePadAction;
        if (_act isNotEqualTo []) then {
            if (_kids isNotEqualTo []) then {
                _kids = [_obj, _kids] call comspec_sse_fnc_aceWrapMenuChildren;
            };
            _out pushBack [_act, _kids, _obj];
        };
    };
} forEach _actions;

_out
