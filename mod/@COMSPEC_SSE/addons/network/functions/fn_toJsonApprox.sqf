/*
    Sérialisation JSON approximative (SQF) pour payloads SSE.
    [_value] call comspec_sse_fnc_toJsonApprox
*/
params ["_value"];

// Échapper via toString [92] : les littéraux "\" / "\"" cassent le parseur SQF
private _esc = {
    params ["_s"];
    private _b = toString [92];
    _s = [_s, _b, _b + _b] call BIS_fnc_replaceString;
    _s = [_s, """", _b + """"] call BIS_fnc_replaceString;
    _s = [_s, endl, _b + "n"] call BIS_fnc_replaceString;
    format ["""%1""", _s]
};

if (_value isEqualType true) exitWith { if (_value) then {"true"} else {"false"} };
if (_value isEqualType 0) exitWith { str _value };
if (_value isEqualType "") exitWith { [_value] call _esc };
if (_value isEqualType createHashMap) exitWith {
    private _parts = [];
    {
        _parts pushBack format ["%1:%2", [_x] call _esc, [_y] call comspec_sse_fnc_toJsonApprox];
    } forEach _value;
    "{" + (_parts joinString ",") + "}"
};
if (_value isEqualType []) exitWith {
    // Paires [k,v] ?
    if (count _value > 0 && {(_value select 0) isEqualType []} && {count (_value select 0) == 2} && {((_value select 0) select 0) isEqualType ""}) then {
        private _parts = [];
        {
            _x params ["_k", "_v"];
            _parts pushBack format ["%1:%2", [_k] call _esc, [_v] call comspec_sse_fnc_toJsonApprox];
        } forEach _value;
        "{" + (_parts joinString ",") + "}"
    } else {
        "[" + ((_value apply { [_x] call comspec_sse_fnc_toJsonApprox }) joinString ",") + "]"
    };
};

[str _value] call _esc
