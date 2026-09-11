/*
    Remplit les 3 lignes IceMan (2620/2621/2622) : Indicatif / Nom / Rôle.
    Ces cases sont la superposition native bas-droite de la carte ATAK.
*/
params [
    ["_disp", displayNull],
    ["_unit", objNull, [objNull]]
];

if (!hasInterface) exitWith { false };
if (isNull _disp) then {
    _disp = uiNamespace getVariable ["cTab_Android_dlg", displayNull];
};
if (isNull _disp) then {
    _disp = uiNamespace getVariable ["cTab_Android_dsp", displayNull];
};
if (isNull _disp) exitWith { false };

if (isNull _unit) then {
    _unit = if (!isNil "cTab_player" && {!isNull cTab_player}) then { cTab_player } else { player };
};
if (isNull _unit) exitWith { false };

private _fncClean = {
    params ["_v"];
    if (!(_v isEqualType "")) then { _v = str _v; };
    _v = trim _v;
    if (_v isEqualTo "" || {(toLower _v) in ["<null>", "any", "nil", "-", "none", "n/a"]}) then { "" } else { _v }
};

private _cs = "";
if (!isNil "comspec_overwatch_atak_athena_fnc_athena_bftUnitLabel") then {
    _cs = [_unit] call comspec_overwatch_atak_athena_fnc_athena_bftUnitLabel;
};
_cs = [_cs] call _fncClean;
if (_cs isEqualTo "" && {!isNil "comspec_overwatch_connect_fnc_getCallsign"}) then {
    if (_unit isEqualTo player || {_unit isEqualTo (missionNamespace getVariable ["cTab_player", objNull])}) then {
        _cs = [[true] call comspec_overwatch_connect_fnc_getCallsign] call _fncClean;
    };
};
if (_cs isEqualTo "") then { _cs = "—"; };

private _name = "";
if (_unit isEqualTo player || {_unit isEqualTo (missionNamespace getVariable ["cTab_player", objNull])}) then {
    _name = [missionNamespace getVariable ["comspec_profile_name", ""]] call _fncClean;
};
if (_name isEqualTo "" && {!isNil "comspec_overwatch_connect_fnc_collectOperatorIdentity"}) then {
    private _ident = [_unit] call comspec_overwatch_connect_fnc_collectOperatorIdentity;
    if (_ident isEqualType createHashMap) then {
        private _fn = [_ident getOrDefault ["first_name_detected", ""]] call _fncClean;
        private _ln = [_ident getOrDefault ["last_name_detected", ""]] call _fncClean;
        _name = trim (format ["%1 %2", _fn, _ln]);
    };
};
if (_name isEqualTo "") then {
    _name = [name _unit] call _fncClean;
};
if (_name isEqualTo "") then { _name = "—"; };

private _role = "";
if (_unit isEqualTo player || {_unit isEqualTo (missionNamespace getVariable ["cTab_player", objNull])}) then {
    _role = [missionNamespace getVariable ["comspec_profile_function", ""]] call _fncClean;
    if (_role isEqualTo "") then {
        _role = [missionNamespace getVariable ["comspec_profile_role", ""]] call _fncClean;
    };
};
if (_role isEqualTo "" && {!isNil "comspec_overwatch_connect_fnc_getUnitRole"}) then {
    _role = [[_unit] call comspec_overwatch_connect_fnc_getUnitRole] call _fncClean;
};
if (_role isEqualTo "" || {(toLower _role) in ["operator", "operateur"]}) then { _role = "—"; };

private _lines = [
    [2620, _cs],
    [2621, _name],
    [2622, _role]
];
{
    _x params ["_idc", "_txt"];
    private _c = _disp displayCtrl (17000 + _idc);
    if (isNull _c) then { continue };
    _c ctrlShow true;
    _c ctrlSetFade 0;
    _c ctrlSetText _txt;
    _c ctrlCommit 0;
} forEach _lines;

true
