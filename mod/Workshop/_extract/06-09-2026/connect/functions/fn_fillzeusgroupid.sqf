/*
    Panneau Zeus « Identifiant du groupe » : préremplit avec l’indicatif
    de l’opérateur édité, si le champ contient encore le nom de profil.
*/
params [
    ["_display", displayNull],
    ["_retry", true, [true]]
];

if (!hasInterface) exitWith { false };
if (isNull _display) exitWith { false };

private _fncCallsignOf = {
    params ["_u"];
    if (isNull _u) exitWith { "" };
    if (_u isEqualTo player) exitWith {
        [true] call comspec_overwatch_connect_fnc_getCallsign
    };
    private _cs = trim (_u getVariable ["COMSPEC_CallsignPublic", ""]);
    if (_cs isEqualTo "") then {
        _cs = trim (_u getVariable ["COMSPEC_Callsign", ""]);
    };
    if ([_cs] call comspec_overwatch_connect_fnc_isUsableCallsign) then { _cs } else { "" }
};

private _fncDefault = {
    params ["_g", "_u"];
    private _gl = toLower (trim _g);
    if (_gl isEqualTo "" || {_gl in ["error", "grpnull", "none", "n/a"]}) exitWith { true };
    if (isNull _u) exitWith { false };
    private _nm = toLower (trim (name _u));
    if (_nm isNotEqualTo "" && {_gl isEqualTo _nm}) exitWith { true };
    if (_u isEqualTo player) then {
        private _pn = toLower (trim profileName);
        if (_pn isNotEqualTo "" && {_gl isEqualTo _pn}) exitWith { true };
    };
    false
};

private _obj = [_display] call comspec_overwatch_connect_fnc_zeusAttributesTarget;
if (isNull _obj) then { _obj = player; };

private _cs = [_obj] call _fncCallsignOf;
if (_cs isEqualTo "" && {!isNull (group _obj)}) then {
    _cs = [leader (group _obj)] call _fncCallsignOf;
};
if (!([_cs] call comspec_overwatch_connect_fnc_isUsableCallsign)) exitWith { false };

private _grp = group _obj;
private _gid = if (isNull _grp) then { "" } else { trim (groupId _grp) };
private _hay = "";
private _edits = [];
{
    _hay = _hay + " " + toLower (ctrlText _x);
    if ((ctrlType _x) isEqualTo 2) then { _edits pushBack _x; };
} forEach (allControls _display);

private _isGroupPanel = (
    (_hay find "identifiant du groupe") >= 0
    || {(_hay find "group id") >= 0}
    || {(_hay find "groupid") >= 0}
);
if (!_isGroupPanel) exitWith { false };

private _target = controlNull;
private _idcEdit = _display displayCtrl 601;
if (!isNull _idcEdit && {(ctrlType _idcEdit) isEqualTo 2}) then { _target = _idcEdit; };
if (isNull _target) then {
    {
        private _txt = trim (ctrlText _x);
        if (_txt isEqualTo _gid || {[_txt, _obj] call _fncDefault}) exitWith { _target = _x; };
    } forEach _edits;
};
if (isNull _target && {(count _edits) == 1}) then { _target = _edits select 0; };
if (isNull _target) exitWith {
    if (_retry) then {
        [{
            [_this, false] call comspec_overwatch_connect_fnc_fillZeusGroupId;
        }, _display, 0.35] call CBA_fnc_waitAndExecute;
    };
    false
};

private _cur = trim (ctrlText _target);
if ((toLower _cur) isEqualTo (toLower _cs)) exitWith { true };
if (!([_cur, _obj] call _fncDefault) && {_cur isNotEqualTo _gid || {!([_gid, _obj] call _fncDefault)}}) exitWith { true };

_target ctrlSetText _cs;
[_cs, _obj] call comspec_overwatch_connect_fnc_applyGroupIdFromCallsign;
true
