/*
    JSON fiche personne — champs explicites, échappement type Overwatch.
    Évite toJsonApprox / forEach HashMap (corps vide → 422 identity_required).
    [_payload] call comspec_sse_fnc_toJsonPerson
*/
params [
    ["_p", createHashMap, [createHashMap]]
];

private _esc = {
    params ["_s"];
    if (isNil "_s") exitWith { "" };
    if (!(_s isEqualType "")) then { _s = format ["%1", _s]; };
    if (_s isEqualTo "") exitWith { "" };
    private _o = "";
    private _dq = toString [34];
    private _bs = toString [92];
    {
        switch (true) do {
            case (_x == 34): { _o = _o + _bs + _dq; };
            case (_x == 92): { _o = _o + _bs + _bs; };
            case (_x == 10): { _o = _o + _bs + "n"; };
            case (_x == 13): { _o = _o + _bs + "r"; };
            case (_x == 9):  { _o = _o + _bs + "t"; };
            case (_x < 32):  { _o = _o + " "; };
            default { _o = _o + toString [_x]; };
        };
    } forEach toArray _s;
    _o
};

private _str = {
    params ["_k", "_d"];
    private _v = _p getOrDefault [_k, _d];
    if (isNil "_v") then { _v = _d; };
    if (!(_v isEqualType "")) then { _v = format ["%1", _v]; };
    format ["""%1"":""%2""", _k, [_v] call _esc]
};

private _num = {
    params ["_k", "_d"];
    private _v = _p getOrDefault [_k, _d];
    if (!(_v isEqualType 0)) then { _v = parseNumber format ["%1", _v]; };
    if (!(_v isEqualType 0)) then { _v = _d; };
    format ["""%1"":%2", _k, _v toFixed 2]
};

private _int = {
    params ["_k", "_d"];
    private _v = _p getOrDefault [_k, _d];
    if (!(_v isEqualType 0)) then { _v = parseNumber format ["%1", _v]; };
    if (!(_v isEqualType 0)) then { _v = _d; };
    if (_k isEqualTo "mapId" && {_v < 1}) then { _v = 1; };
    format ["""%1"":%2", _k, floor _v]
};

private _bool = {
    params ["_k", "_d"];
    private _v = _p getOrDefault [_k, _d];
    format ["""%1"":%2", _k, ["false", "true"] select (_v isEqualTo true)]
};

private _first = _p getOrDefault ["first_name", ""];
private _last = _p getOrDefault ["last_name", ""];
private _alias = _p getOrDefault ["alias", ""];
if (!(_first isEqualType "")) then { _first = format ["%1", _first]; };
if (!(_last isEqualType "")) then { _last = format ["%1", _last]; };
if (!(_alias isEqualType "")) then { _alias = format ["%1", _alias]; };
_first = trim _first;
_last = trim _last;
_alias = trim _alias;
if (_first isEqualTo "" && {_last isEqualTo ""} && {_alias isEqualTo ""}) then {
    _alias = trim (_p getOrDefault ["sse_uid", "SSE"]);
    _p set ["alias", _alias];
};

"{" + ([
    ["mapId", 1] call _int,
    format ["""last_name"":""%1""", [_last] call _esc],
    format ["""first_name"":""%1""", [_first] call _esc],
    format ["""alias"":""%1""", [_alias] call _esc],
    ["status", "civil"] call _str,
    ["nationality", ""] call _str,
    ["language_spoken", ""] call _str,
    ["affiliation", ""] call _str,
    ["circumstances", "perquisition"] call _str,
    ["confidence_level", "moyenne"] call _str,
    ["biometrics_simulated", true] call _bool,
    ["consent_recorded", false] call _bool,
    ["capture_pos_x", 0] call _num,
    ["capture_pos_y", 0] call _num,
    ["capture_pos_z", 0] call _num,
    ["grid_reference", ""] call _str,
    ["location_description", ""] call _str,
    ["submitter_callsign", ""] call _str,
    ["target_unit_netid", ""] call _str,
    ["sse_uid", ""] call _str,
    ["case_reference", ""] call _str,
    ["idempotency_key", ""] call _str,
    ["mission_id", ""] call _str,
    ["schema", "comspec_sse_athena_person_v0.4"] call _str
] joinString ",") + "}"
