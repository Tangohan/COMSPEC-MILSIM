/*
    Libellé carte ATAK pour une unité : indicatif Athena, jamais le slot de groupe (01).
*/
params [["_unit", objNull, [objNull]]];
if (isNull _unit) exitWith { "" };

private _fncTake = {
    params ["_raw"];
    if (!(_raw isEqualType "")) then { _raw = str _raw; };
    _raw = trim _raw;
    if (_raw isEqualTo "") exitWith { "" };
    if (!isNil "comspec_overwatch_connect_fnc_isUsableCallsign") then {
        if (!([_raw] call comspec_overwatch_connect_fnc_isUsableCallsign)) then { _raw = ""; };
    };
    _raw
};

private _cs = "";
private _local = player;
if (!isNil "cTab_player" && {!isNull cTab_player}) then { _local = cTab_player; };
if (_unit isEqualTo _local || {_unit isEqualTo player}) then {
    if (!isNil "comspec_overwatch_connect_fnc_getCallsign") then {
        _cs = [true] call comspec_overwatch_connect_fnc_getCallsign;
    };
};
if (_cs isEqualTo "") then {
    _cs = [_unit getVariable ["COMSPEC_CallsignPublic", ""]] call _fncTake;
};
if (_cs isEqualTo "") then {
    _cs = [_unit getVariable ["COMSPEC_Callsign", ""]] call _fncTake;
};
if (_cs isNotEqualTo "") exitWith {
    private _mode = missionNamespace getVariable ["COMSPEC_BftLabelMode", ""];
    if (_mode isEqualTo "") then {
        _mode = profileNamespace getVariable ["COMSPEC_BftLabelMode", "cs"];
    };
    if (_mode isEqualTo "cs_role" && {!isNil "comspec_overwatch_connect_fnc_getUnitRole"}) then {
        private _role = [_unit] call comspec_overwatch_connect_fnc_getUnitRole;
        _role = trim _role;
        if (_role isNotEqualTo "" && {(toLower _role) isNotEqualTo "operator"}) then {
            _cs = format ["%1 · %2", _cs, _role];
        };
    };
    _cs
};

if (!isPlayer _unit && {!isNil "comspec_overwatch_connect_fnc_allyTrackCallsign"}) exitWith {
    [_unit] call comspec_overwatch_connect_fnc_allyTrackCallsign
};

""
