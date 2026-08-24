/*
    Indicatif affiché sur l’ATAK pour une IA alliée.
    L’identifiant interne ALLY-… reste sur l’objet (suivi) ; il n’est pas le libellé.
*/
params [
    ["_unit", objNull, [objNull]]
];
if (isNull _unit) exitWith { "Unité alliée" };

private _fnc_isAutoId = {
    params ["_s"];
    private _low = toLower (trim _s);
    if (_low isEqualTo "") exitWith { false };
    _low regexMatch "^ally-[0-9]+-[0-9]+(-[0-9]+)*$"
};

private _fnc_readable = {
    params ["_s"];
    if (!(_s isEqualType "")) then { _s = str _s };
    _s = trim _s;
    if (_s isEqualTo "") exitWith { false };
    if ((toLower _s) find "error:" == 0) exitWith { false };
    if ((toLower _s) in ["error", "grpnull", "unknown", "inconnu"]) exitWith { false };
    if ([_s] call _fnc_isAutoId) exitWith { false };
    true
};

private _id = _unit getVariable ["COMSPEC_AllyTrackId", ""];
if (!(_id isEqualType "")) then { _id = str _id };
_id = trim _id;
if (_id isEqualTo "" || {!([_id] call _fnc_isAutoId) && {(toLower _id) find "ally-" != 0}}) then {
    _id = format ["ALLY-%1", ((netId _unit) splitString ":") joinString "-"];
    _unit setVariable ["COMSPEC_AllyTrackId", _id, true];
};

private _custom = _unit getVariable ["COMSPEC_AllyCallsign", ""];
if (!(_custom isEqualType "")) then { _custom = str _custom };
_custom = trim _custom;
if ([_custom] call _fnc_readable) exitWith { _custom };

private _gid = "";
private _grp = group _unit;
if (!isNull _grp) then {
    _gid = trim (groupId _grp);
};
private _n = name _unit;
if (!(_n isEqualType "")) then { _n = str _n };
_n = trim _n;

private _pretty = "";
if ([_gid] call _fnc_readable) then {
    if ([_n] call _fnc_readable && {(toLower _n) isNotEqualTo (toLower _gid)}) then {
        _pretty = format ["%1 - %2", _gid, _n];
    } else {
        _pretty = _gid;
    };
} else {
    if ([_n] call _fnc_readable) then {
        _pretty = _n;
    };
};

if (_pretty isEqualTo "") then {
    private _role = "";
    if (!isNil "comspec_overwatch_connect_fnc_getUnitRole") then {
        _role = [_unit] call comspec_overwatch_connect_fnc_getUnitRole;
        if (!(_role isEqualType "")) then { _role = str _role };
        _role = trim _role;
    };
    if ([_role] call _fnc_readable && {!((toLower _role) in ["operator", "operateur", "unité alliée", "unite alliee"])}) then {
        _pretty = _role;
    };
};

if (_pretty isEqualTo "" || {[_pretty] call _fnc_isAutoId}) then {
    _pretty = "Unité alliée";
};

_pretty
