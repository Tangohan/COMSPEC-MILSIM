/*
    Identifiant de groupe pour le suivi d’effectif (téléphone / poste).
    Indicatif + affectation Athena. Jamais le titre de communauté.
    Repli : nom de groupe Arma s’il n’est pas le nom de communauté.
*/
params [["_unit", objNull, [objNull]]];

if (isNull _unit) then { _unit = player; };
if (isNull _unit) exitWith { "" };

private _fncSameOrTruncated = {
    params ["_a", "_b"];
    if (_a isEqualTo "" || {_b isEqualTo ""}) exitWith { false };
    if (_a isEqualTo _b) exitWith { true };
    if ((count _a) >= 16 && {(count _b) >= 16} && {((_a find _b) == 0) || {(_b find _a) == 0}}) exitWith { true };
    false
};

private _fncIsTenant = {
    params ["_s"];
    if (!(_s isEqualType "")) then { _s = str _s; };
    _s = toLower (trim _s);
    if (_s isEqualTo "" || {_s in ["error", "grpnull", "-", "none", "n/a"]}) exitWith { false };
    private _tenant = toLower (trim (str (missionNamespace getVariable ["comspec_tenant_name", ""])));
    [_s, _tenant] call _fncSameOrTruncated
};

private _fncAssignment = {
    private _u = missionNamespace getVariable ["comspec_profile_unit", ""];
    if (!(_u isEqualType "")) then { _u = str _u; };
    _u = trim _u;
    if (_u isEqualTo "" || {_u isEqualTo "-"}) exitWith { "" };
    if ([_u] call _fncIsTenant) exitWith { "" };
    private _low = toLower _u;
    if ((_low find "http://") == 0 || {(_low find "https://") == 0} || {(_u find "://") >= 0}) exitWith { "" };
    _u
};

private _fncCallsignOf = {
    params ["_u"];
    if (_u isEqualTo player || {_u isEqualTo (missionNamespace getVariable ["cTab_player", objNull])}) exitWith {
        [true] call comspec_overwatch_connect_fnc_getCallsign
    };
    private _cs = trim (_u getVariable ["COMSPEC_CallsignPublic", ""]);
    if (_cs isEqualTo "") then {
        _cs = trim (_u getVariable ["COMSPEC_Callsign", ""]);
    };
    if ([_cs] call comspec_overwatch_connect_fnc_isUsableCallsign) then { _cs } else { "" }
};

private _cs = [_unit] call _fncCallsignOf;
private _asg = "";
if (_unit isEqualTo player || {_unit isEqualTo (missionNamespace getVariable ["cTab_player", objNull])}) then {
    _asg = [] call _fncAssignment;
};

if (_cs isNotEqualTo "" && {_asg isNotEqualTo ""}) exitWith { format ["%1 · %2", _cs, _asg] };
if (_cs isNotEqualTo "" && {_asg isEqualTo ""}) exitWith { _cs };
if (_cs isEqualTo "" && {_asg isNotEqualTo ""}) exitWith { _asg };

private _gid = trim (groupId (group _unit));
if (!(_gid isEqualType "")) then { _gid = str _gid; };
_gid = trim _gid;
if (_gid isEqualTo "" || {(toLower _gid) in ["error", "grpnull"]}) exitWith { "" };
if ([_gid] call _fncIsTenant) exitWith { "" };
if ((count _gid) > 24 && {!([_gid] call comspec_overwatch_connect_fnc_isUsableCallsign)}) exitWith { "" };

_gid
