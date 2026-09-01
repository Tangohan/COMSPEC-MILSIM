/*
    Rôle lisible pour un opérateur (effectifs / Athena).
    Ordre : rôle personnalisé → description de slot Arma → équipe de feu → groupe → « Opérateur ».
*/
params [["_unit", objNull, [objNull]]];

if (isNull _unit) exitWith { "Operator" };

private _result = "";

// 1) Rôle personnalisé
if (_unit isEqualTo player) then {
    _result = trim (missionNamespace getVariable ["COMSPEC_Role", ""]);
    if (_result isEqualTo "") then {
        _result = trim (profileNamespace getVariable ["COMSPEC_Role", ""]);
    };
} else {
    private _uCustom = _unit getVariable ["COMSPEC_Role", ""];
    if (!(_uCustom isEqualType "")) then { _uCustom = str _uCustom; };
    _result = trim _uCustom;
};

if (!(_result isEqualTo "")) exitWith { _result };

// 2) Description de rôle Arma (éditeur / slot)
private _raw = roleDescription _unit;
if (!(_raw isEqualType "")) then { _raw = str _raw; };
_raw = trim _raw;
if (!(_raw isEqualTo "")) then {
    if ((_raw find "@") >= 0) then {
        _raw = trim ((_raw splitString "@") select 0);
    };
    _raw = trim ((_raw splitString ":") joinString " - ");
    // Écarter les classnames type B_Soldier_F
    private _low = toLower _raw;
    private _looksLikeClass = ((_raw find " ") < 0) && {(_raw find "_") >= 0} && {
        ((_low find "soldier") >= 0) || {(_low select [(count _low) - 2, 2]) isEqualTo "_f"}
    };
    if (!_looksLikeClass) then { _result = _raw; };
};

if (!(_result isEqualTo "")) exitWith { _result };

// 3) Équipe de feu Athena
private _cs = if (_unit isEqualTo player) then {
    [] call comspec_overwatch_connect_fnc_getCallsign
} else {
    private _c = _unit getVariable ["COMSPEC_Callsign", ""];
    if (!(_c isEqualType "")) then { _c = str _c; };
    _c = trim _c;
    if (_c isEqualTo "") then { _c = name _unit; };
    _c
};
private _csLow = toLower _cs;
private _nameLow = toLower (name _unit);
private _teams = missionNamespace getVariable ["COMSPEC_FireTeams", []];
{
    if (!(_result isEqualTo "")) exitWith {};
    private _team = _x;
    if ((count _team) < 6) then { continue };
    private _label = _team select 1;
    private _members = _team select 5;
    {
        if (!(_result isEqualTo "")) exitWith {};
        _x params [["_mCs", ""], ["_mRole", ""], ["_mName", ""]];
        if ((toLower (trim _mCs)) isEqualTo _csLow || {(toLower (trim _mName)) isEqualTo _nameLow}) then {
            private _roleFr = switch (toLower (trim _mRole)) do {
                case "leader";
                case "chef";
                case "ftl": { "Team leader" };
                default { "Membre" };
            };
            if (!(_label isEqualTo "")) then {
                _result = format ["%1 — %2", _roleFr, _label];
            } else {
                _result = _roleFr;
            };
        };
    } forEach _members;
} forEach _teams;

if (!(_result isEqualTo "")) exitWith { _result };

"Operator"
