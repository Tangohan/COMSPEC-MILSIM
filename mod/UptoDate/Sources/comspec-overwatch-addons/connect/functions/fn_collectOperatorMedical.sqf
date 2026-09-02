/*
    Données médicales d’identité observées (groupe sanguin, état utile).
    Ne remonte que ce qu’ACE / la mission fournit. Pas de SpO2 inventé.
    Params: [_unit]
    Retour: HashMap
*/
params [["_unit", objNull, [objNull]]];

private _out = createHashMap;
_out set ["ace_present", false];
_out set ["blood_type", ""];
_out set ["blood_type_source", ""];
_out set ["state", "unknown"];
_out set ["blood_volume_pct", -1];
_out set ["heart_rate", -1];
_out set ["in_pain", false];
_out set ["cardiac_arrest", false];
_out set ["unconscious", false];
_out set ["medic_class", -1];
_out set ["custom", createHashMap];

if (isNull _unit) exitWith { _out };

private _hasAce = isClass (configFile >> "CfgPatches" >> "ace_medical");
_out set ["ace_present", _hasAce];

private _bt = "";
private _src = "";
if (!isNil "ace_dogtags_fnc_getDogtagData") then {
    private _data = [_unit] call ace_dogtags_fnc_getDogtagData;
    if (_data isEqualType [] && {count _data >= 3}) then {
        private _dBt = _data select 2;
        if (_dBt isEqualType "") then { _dBt = trim _dBt; };
        if (_dBt isNotEqualTo "") then {
            _bt = _dBt;
            _src = "dogtag";
        };
    };
};
if (_bt isEqualTo "") then {
    if (_unit isEqualTo player && {!isNil "comspec_overwatch_connect_fnc_getBloodType"}) then {
        _bt = [] call comspec_overwatch_connect_fnc_getBloodType;
        if (_bt isNotEqualTo "") then { _src = "ace_medical"; };
    } else {
        private _idx = _unit getVariable ["ace_medical_bloodType", nil];
        if (!isNil "_idx" && {_idx isEqualType 0}) then {
            _bt = switch (_idx) do {
                case 0: { "O-" };
                case 1: { "O+" };
                case 2: { "A-" };
                case 3: { "A+" };
                case 4: { "B-" };
                case 5: { "B+" };
                case 6: { "AB-" };
                case 7: { "AB+" };
                default { "" };
            };
            if (_bt isNotEqualTo "") then { _src = "ace_medical"; };
        };
    };
};
if (!(_bt isEqualType "")) then { _bt = str _bt; };
_out set ["blood_type", trim _bt];
_out set ["blood_type_source", _src];

if (!isNil "comspec_overwatch_connect_fnc_getMedicalState") then {
    private _raw = [_unit] call comspec_overwatch_connect_fnc_getMedicalState;
    if (_raw isEqualType "") then {
        private _p = _raw splitString "|";
        if ((count _p) >= 1) then { _out set ["state", _p select 0]; };
        if ((count _p) >= 2) then { _out set ["blood_volume_pct", round (parseNumber (_p select 1))]; };
        if ((count _p) >= 3) then { _out set ["in_pain", (_p select 2) isEqualTo "1"]; };
        if ((count _p) >= 4) then { _out set ["heart_rate", round (parseNumber (_p select 3))]; };
        if ((count _p) >= 5) then { _out set ["cardiac_arrest", (_p select 4) isEqualTo "1"]; };
    };
};

private _uncon = _unit getVariable ["ACE_isUnconscious", false];
if (!_uncon) then { _uncon = _unit getVariable ["ace_medical_unconscious", false]; };
_out set ["unconscious", _uncon isEqualTo true];

private _medic = _unit getVariable ["ace_medical_medicClass", nil];
if (!isNil "_medic" && {_medic isEqualType 0}) then {
    _out set ["medic_class", round _medic];
};

private _custom = createHashMap;
{
    _x params ["_key", "_var"];
    private _v = _unit getVariable [_var, nil];
    if (!isNil "_v") then {
        if (_v isEqualType "") then { _custom set [_key, _v]; };
        if (_v isEqualType 0) then { _custom set [_key, _v]; };
        if (_v isEqualType true) then { _custom set [_key, _v]; };
    };
} forEach [
    ["ace_blood_type_index", "ace_medical_bloodType"],
    ["ace_medical_status", "ace_medical_status"],
    ["kat_blood_type", "KAT_circulation_bloodType"]
];
_out set ["custom", _custom];

_out
