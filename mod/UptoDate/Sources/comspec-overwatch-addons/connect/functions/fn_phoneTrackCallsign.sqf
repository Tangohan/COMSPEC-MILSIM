/*
    Nom affiché / clé Athena pour une personne géolocalisée par téléphone.
*/
params [
    ["_unit", objNull, [objNull]]
];
if (isNull _unit) exitWith { "Contact" };

private _custom = _unit getVariable ["COMSPEC_PhoneCallsign", ""];
if (!(_custom isEqualType "")) then { _custom = str _custom };
_custom = trim _custom;
if (_custom isNotEqualTo "") exitWith { _custom };

if (isPlayer _unit && {_unit isEqualTo player}) exitWith {
    [] call comspec_overwatch_connect_fnc_getCallsign
};

private _n = name _unit;
if (!(_n isEqualType "")) then { _n = str _n };
_n = trim _n;
if (_n isEqualTo "" || {(toLower _n) find "error:" == 0}) then {
    private _id = _unit getVariable ["COMSPEC_PhoneTrackId", ""];
    if (_id isEqualTo "") then {
        _id = format ["TEL-%1", ((netId _unit) splitString ":") joinString "-"];
        _unit setVariable ["COMSPEC_PhoneTrackId", _id, true];
    };
    _n = _id;
} else {
    if (!isPlayer _unit) then {
        _n = format ["Tél. %1", _n];
    };
};
_n
