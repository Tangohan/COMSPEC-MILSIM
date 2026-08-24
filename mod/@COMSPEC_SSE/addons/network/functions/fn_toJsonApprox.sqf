/*
    Sérialisation JSON approximative (SQF) pour payloads SSE.
    [_value] call comspec_sse_fnc_toJsonApprox
    [_value, _depth, _visited] call comspec_sse_fnc_toJsonApprox  (interne)

    Garde anti stack overflow :
    - profondeur maximale (conteneurs imbriqués)
    - détection de cycles via isEqualRef sur tableaux / HashMap déjà visités
*/
params [
    "_value",
    ["_depth", 0, [0]],
    ["_visited", [], [[]]]
];

private _maxDepth = 32;

// Échapper via toString [92] : les littéraux "\" / "\"" cassent le parseur SQF
private _esc = {
    params ["_s"];
    if (isNil "_s") exitWith { """""" };
    if (!(_s isEqualType "")) then { _s = format ["%1", _s]; };
    private _b = toString [92];
    _s = _s replaceString [_b, _b + _b];
    _s = _s replaceString ["""", _b + """"];
    _s = _s replaceString [endl, _b + "n"];
    format ["""%1""", _s]
};

if (_depth > _maxDepth) exitWith {
    """[MAX_DEPTH]"""
};

if (_value isEqualType true) exitWith { if (_value) then {"true"} else {"false"} };
if (_value isEqualType 0) exitWith { str _value };
if (_value isEqualType "") exitWith { [_value] call _esc };

private _isContainer = (_value isEqualType createHashMap) || {_value isEqualType []};
private _circular = false;
if (_isContainer) then {
    {
        if (_x isEqualRef _value) exitWith {
            _circular = true;
        };
    } forEach _visited;
    if (!_circular) then {
        // Copie du chemin courant : les frères ne s'infectent pas mutuellement
        _visited = +_visited;
        _visited pushBack _value;
    };
};
if (_circular) exitWith {
    """[CIRCULAR_REFERENCE]"""
};

// ------------------------------------------------------------------
// HASHMAP
// ------------------------------------------------------------------
if (_value isEqualType createHashMap) exitWith {
    private _parts = [];
    {
        private _child = [_y, _depth + 1, _visited] call comspec_sse_fnc_toJsonApprox;
        if (isNil "_child" || {!(_child isEqualType "")}) then { _child = "null"; };
        _parts pushBack format ["%1:%2", [_x] call _esc, _child];
    } forEach _value;
    "{" + (_parts joinString ",") + "}"
};

// ------------------------------------------------------------------
// ARRAY
// ------------------------------------------------------------------
if (_value isEqualType []) exitWith {
    // Tableau représentant des paires [["key", value], ...]
    if (
        count _value > 0
        && {(_value select 0) isEqualType []}
        && {count (_value select 0) == 2}
        && {((_value select 0) select 0) isEqualType ""}
    ) then {
        private _parts = [];
        {
            _x params ["_k", "_v"];
            private _child = [_v, _depth + 1, _visited] call comspec_sse_fnc_toJsonApprox;
            if (isNil "_child" || {!(_child isEqualType "")}) then { _child = "null"; };
            _parts pushBack format ["%1:%2", [_k] call _esc, _child];
        } forEach _value;
        "{" + (_parts joinString ",") + "}"
    } else {
        private _parts = [];
        {
            private _item = [_x, _depth + 1, _visited] call comspec_sse_fnc_toJsonApprox;
            if (isNil "_item" || {!(_item isEqualType "")}) then { _item = "null"; };
            _parts pushBack _item;
        } forEach _value;
        "[" + (_parts joinString ",") + "]"
    };
};

// ------------------------------------------------------------------
// TYPE NON JSON
// ------------------------------------------------------------------
[str _value] call _esc
