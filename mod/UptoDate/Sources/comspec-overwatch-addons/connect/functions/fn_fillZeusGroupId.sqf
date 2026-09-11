/*
    Panneau Zeus « Identifiant du groupe » :
    - affiche Indicatif / Nom / Rôle au-dessus du champ ;
    - préremplit si le champ contient encore le nom de profil ;
    - à la validation, synchronise le groupe BFT (variable + remontée position).
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

private _fncClean = {
    params ["_v"];
    if (!(_v isEqualType "")) then { _v = str _v; };
    _v = trim _v;
    if (_v isEqualTo "" || {(toLower _v) in ["<null>", "any", "nil", "-", "none", "n/a"]}) then { "" } else { _v }
};

private _obj = [_display] call comspec_overwatch_connect_fnc_zeusAttributesTarget;
if (isNull _obj) then { _obj = player; };

private _cs = [_obj] call _fncCallsignOf;
if (_cs isEqualTo "" && {!isNull (group _obj)}) then {
    _cs = [leader (group _obj)] call _fncCallsignOf;
};

private _opName = "";
if (_obj isEqualTo player) then {
    _opName = [missionNamespace getVariable ["comspec_profile_name", ""]] call _fncClean;
};
if (_opName isEqualTo "") then { _opName = [name _obj] call _fncClean; };

private _role = "";
if (_obj isEqualTo player) then {
    _role = [missionNamespace getVariable ["comspec_profile_function", ""]] call _fncClean;
    if (_role isEqualTo "") then {
        _role = [missionNamespace getVariable ["comspec_profile_role", ""]] call _fncClean;
    };
};
if (_role isEqualTo "" && {!isNil "comspec_overwatch_connect_fnc_getUnitRole"}) then {
    _role = [[_obj] call comspec_overwatch_connect_fnc_getUnitRole] call _fncClean;
};
if ((toLower _role) in ["operator", "operateur"]) then { _role = ""; };

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
    || {(_hay find "données techniques") >= 0}
    || {(_hay find "donnees techniques") >= 0}
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

// Superposition Indicatif / Nom / Rôle au-dessus du champ groupe.
if (isNull (_display displayCtrl 86210)) then {
    (ctrlPosition _target) params ["_ex", "_ey", "_ew", "_eh"];
    private _lineH = (_eh max 0.018) min 0.028;
    private _gap = 0.003 * safezoneH;
    private _specs = [
        [86210, format ["Indicatif  %1", if (_cs isEqualTo "") then { "—" } else { _cs }]],
        [86211, format ["Nom        %1", if (_opName isEqualTo "") then { "—" } else { _opName }]],
        [86212, format ["Rôle       %1", if (_role isEqualTo "") then { "—" } else { _role }]]
    ];
    private _y0 = _ey - (3 * _lineH + 2 * _gap) - 0.004;
    if (_y0 < (safezoneY + 0.01)) then { _y0 = _ey + _eh + 0.006; };
    {
        _x params ["_idc", "_txt"];
        private _i = _forEachIndex;
        private _lbl = _display ctrlCreate ["RscText", _idc];
        _lbl ctrlSetPosition [_ex, _y0 + _i * (_lineH + _gap), _ew, _lineH];
        _lbl ctrlSetText _txt;
        _lbl ctrlSetFont "EtelkaMonospacePro";
        _lbl ctrlSetTextColor [0.85, 0.95, 0.9, 1];
        _lbl ctrlSetBackgroundColor [0.02, 0.08, 0.07, 0.88];
        _lbl ctrlEnable false;
        _lbl ctrlCommit 0;
    } forEach _specs;
};

if (!(_display getVariable ["COMSPEC_BftGroupUnloadWired", false])) then {
    _display setVariable ["COMSPEC_BftGroupUnloadWired", true];
    _display setVariable ["COMSPEC_BftGroupEdit", _target];
    _display setVariable ["COMSPEC_BftGroupUnit", _obj];
    _display displayAddEventHandler ["Unload", {
        params ["_d", "_exit"];
        if (_exit isNotEqualTo 1) exitWith {};
        private _edit = _d getVariable ["COMSPEC_BftGroupEdit", controlNull];
        private _unit = _d getVariable ["COMSPEC_BftGroupUnit", objNull];
        if (isNull _unit) then { _unit = player; };
        private _grpName = if (isNull _edit) then { "" } else { trim (ctrlText _edit) };
        if (_grpName isEqualTo "") then {
            private _g = group _unit;
            if (!isNull _g) then { _grpName = trim (groupId _g); };
        };
        if (_grpName isEqualTo "") exitWith {};
        _unit setVariable ["COMSPEC_BftGroup", _grpName, true];
        if (_unit isEqualTo player) then {
            missionNamespace setVariable ["COMSPEC_BftGroup", _grpName, false];
        };
        private _g = group _unit;
        if (!isNull _g && {(leader _g) isEqualTo _unit}) then {
            private _cur = trim (groupId _g);
            if ((toLower _cur) isNotEqualTo (toLower _grpName)) then {
                _g setGroupIdGlobal [_grpName];
            };
        };
        if (_unit isEqualTo player && {!isNil "comspec_overwatch_connect_fnc_updatePosition"}) then {
            [{ [] call comspec_overwatch_connect_fnc_updatePosition; }, [], 0.4] call CBA_fnc_waitAndExecute;
        };
        if (_unit isEqualTo player && {!isNil "comspec_overwatch_connect_fnc_forcePlaytimeReport"}) then {
            [{ [] call comspec_overwatch_connect_fnc_forcePlaytimeReport; }, [], 0.7] call CBA_fnc_waitAndExecute;
        };
        ["Groupe BFT synchronisé : " + _grpName, "system", "info"] call comspec_overwatch_connect_fnc_ambientHint;
    }];
};

if ([_cs] call comspec_overwatch_connect_fnc_isUsableCallsign) then {
    private _cur = trim (ctrlText _target);
    if ((toLower _cur) isNotEqualTo (toLower _cs)) then {
        if ([_cur, _obj] call _fncDefault || {_cur isEqualTo _gid && {[_gid, _obj] call _fncDefault}}) then {
            _target ctrlSetText _cs;
            [_cs, _obj] call comspec_overwatch_connect_fnc_applyGroupIdFromCallsign;
        };
    };
};

true
