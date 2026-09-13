/*
    Si le joueur est chef de groupe et que l’identifiant Arma est encore
    le nom de profil (NewPl, etc.), le remplace par l’indicatif Effectifs.
    Ne touche pas un nom de groupe déjà choisi (équipe, section, indicatif).
*/
params [
    ["_callsign", "", [""]],
    ["_unit", objNull, [objNull]]
];

if (!hasInterface) exitWith { false };
if (isNull _unit) then { _unit = player; };
if (isNull _unit) exitWith { false };

_callsign = trim _callsign;
if (_callsign isEqualTo "") then {
    if (_unit isEqualTo player) then {
        _callsign = [true] call comspec_overwatch_connect_fnc_getCallsign;
    } else {
        _callsign = trim (_unit getVariable ["COMSPEC_CallsignPublic", ""]);
        if (_callsign isEqualTo "") then {
            _callsign = trim (_unit getVariable ["COMSPEC_Callsign", ""]);
        };
    };
};
if (!([_callsign] call comspec_overwatch_connect_fnc_isUsableCallsign)) exitWith { false };

private _grp = group _unit;
if (isNull _grp) exitWith { false };
if ((leader _grp) isNotEqualTo _unit) exitWith { false };

private _gid = groupId _grp;
if (!(_gid isEqualType "")) then { _gid = str _gid; };
_gid = trim _gid;
if ((toLower _gid) isEqualTo (toLower _callsign)) exitWith { true };

private _fncDefault = {
    params ["_g", "_u"];
    private _gl = toLower (trim _g);
    if (_gl isEqualTo "" || {_gl in ["error", "grpnull", "none", "n/a"]}) exitWith { true };
    private _nm = toLower (trim (name _u));
    if (_nm isNotEqualTo "" && {_gl isEqualTo _nm}) exitWith { true };
    if (_u isEqualTo player) then {
        private _pn = toLower (trim profileName);
        if (_pn isNotEqualTo "" && {_gl isEqualTo _pn}) exitWith { true };
    };
    false
};

if (!([_gid, _unit] call _fncDefault)) exitWith { false };

_grp setGroupIdGlobal [_callsign];
true
