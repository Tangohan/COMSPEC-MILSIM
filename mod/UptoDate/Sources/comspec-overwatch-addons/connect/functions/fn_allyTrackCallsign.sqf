/*
    Nom affiché sur l’ATAK pour une IA suivie comme unité alliée.
*/
params [
    ["_unit", objNull, [objNull]]
];
if (isNull _unit) exitWith { "Allié" };

private _custom = _unit getVariable ["COMSPEC_AllyCallsign", ""];
if (!(_custom isEqualType "")) then { _custom = str _custom };
_custom = trim _custom;
if (_custom isNotEqualTo "") exitWith { _custom };

private _n = name _unit;
if (!(_n isEqualType "")) then { _n = str _n };
_n = trim _n;
if (_n isEqualTo "" || {(toLower _n) find "error:" == 0}) then {
    private _id = _unit getVariable ["COMSPEC_AllyTrackId", ""];
    if (_id isEqualTo "") then {
        _id = format ["ALLY-%1", ((netId _unit) splitString ":") joinString "-"];
        _unit setVariable ["COMSPEC_AllyTrackId", _id, true];
    };
    _n = _id;
} else {
    private _gid = trim (groupId (group _unit));
    if (_gid isNotEqualTo "" && {!((toLower _gid) in ["error", "grpnull"])} && {(toLower _n) isNotEqualTo (toLower _gid)}) then {
        _n = format ["%1 — %2", _gid, _n];
    };
};
_n
