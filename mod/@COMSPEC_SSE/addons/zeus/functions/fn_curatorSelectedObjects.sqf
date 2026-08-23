/*
    Aplatit la sélection Zeus (ZEN / curatorSelected) en liste d’objets.
    ZEN peut exposer une structure [[objets],[groupes],…] : isNull sur un sous-tableau plante.
    [] call comspec_sse_fnc_curatorSelectedObjects
*/
private _out = [];
private _push = {
    params ["_item"];
    if (_item isEqualType []) exitWith {
        { [_x] call _push } forEach _item;
    };
    if (_item isEqualType objNull && {!isNull _item}) then {
        _out pushBackUnique _item;
    };
};

private _pool = [];
if (!isNil "zen_context_menu_selectedObjects") then {
    _pool = zen_context_menu_selectedObjects;
};
if (!(_pool isEqualType []) || {_pool isEqualTo []}) then {
    _pool = missionNamespace getVariable ["zen_context_menu_selected", []];
};
if (!(_pool isEqualType []) || {_pool isEqualTo []}) then {
    if (!isNil "curatorSelected") then {
        _pool = curatorSelected;
    };
};

if (_pool isEqualType []) then {
    { [_x] call _push } forEach _pool;
} else {
    [_pool] call _push;
};
_out
