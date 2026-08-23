/*
    Nom affiché sur l’ATAK pour une IA suivie comme unité alliée.
    Toujours préfixé ALLY- (netId) pour ne jamais coller l’indicatif du joueur relais.
*/
params [
    ["_unit", objNull, [objNull]]
];
if (isNull _unit) exitWith { "ALLY-inconnu" };

private _id = _unit getVariable ["COMSPEC_AllyTrackId", ""];
if (!(_id isEqualType "")) then { _id = str _id };
_id = trim _id;
if (_id isEqualTo "" || {(toLower _id) find "ally-" != 0}) then {
    _id = format ["ALLY-%1", ((netId _unit) splitString ":") joinString "-"];
    _unit setVariable ["COMSPEC_AllyTrackId", _id, true];
};

private _custom = _unit getVariable ["COMSPEC_AllyCallsign", ""];
if (!(_custom isEqualType "")) then { _custom = str _custom };
_custom = trim _custom;
if (_custom isNotEqualTo "" && {(toLower _custom) find "ally-" == 0}) exitWith { _custom };

private _pretty = "";
if (_custom isNotEqualTo "") then {
    _pretty = _custom;
} else {
    private _n = name _unit;
    if (!(_n isEqualType "")) then { _n = str _n };
    _n = trim _n;
    if (_n isEqualTo "" || {(toLower _n) find "error:" == 0}) then {
        _pretty = "";
    } else {
        private _gid = trim (groupId (group _unit));
        if (_gid isNotEqualTo "" && {!((toLower _gid) in ["error", "grpnull"])} && {(toLower _n) isNotEqualTo (toLower _gid)}) then {
            _pretty = format ["%1 — %2", _gid, _n];
        } else {
            _pretty = _n;
        };
    };
};

if (_pretty isEqualTo "" || {(toLower _pretty) isEqualTo (toLower _id)}) exitWith { _id };
format ["%1 · %2", _id, _pretty]
