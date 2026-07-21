/*
    Retourne "health|blood|pain|heartRate|cardiacArrest"
    health: stable | wounded | unconscious | cardiac_arrest
    pain: 0|1
    cardiacArrest: 0|1
*/
params ["_unit"];
if (isNull _unit) exitWith { "stable|100|0|80|0" };

private _health = "stable";
private _blood = 100;
private _pain = false;
private _hr = 80;
private _cardiac = false;

if (isClass (configFile >> "CfgPatches" >> "ace_medical")) then {
    _pain = _unit getVariable ["ace_medical_inPain", false];
    _blood = _unit getVariable ["ace_medical_bloodVolume", 6];
    // ACE stocke parfois le volume en litres (~6) ; normaliser en % approximatif pour l'affichage.
    if (_blood <= 12) then { _blood = round ((_blood / 6) * 100); };

    _hr = _unit getVariable ["ace_medical_heartRate", 80];
    if (!isNil "ace_medical_status_fnc_getHeartRate") then {
        private _hrFn = [_unit] call ace_medical_status_fnc_getHeartRate;
        if (!isNil "_hrFn" && {_hrFn isEqualType 0}) then { _hr = _hrFn; };
    };

    _cardiac = _unit getVariable ["ace_medical_inCardiacArrest", false];
    if (!_cardiac && {_hr <= 0}) then { _cardiac = true; };

    private _uncon = _unit getVariable ["ACE_isUnconscious", false];
    if (!_uncon) then { _uncon = _unit getVariable ["ace_medical_incapacitated", false]; };
    if (!_uncon && {lifeState _unit == "INCAPACITATED"}) then { _uncon = true; };
    if (!_uncon && {!isNil "ace_common_fnc_isAwake"}) then {
        _uncon = !([_unit] call ace_common_fnc_isAwake);
    };

    if (_cardiac || {_hr <= 0}) then {
        _health = "cardiac_arrest";
        _hr = 0;
    } else {
        if (_uncon) then {
            _health = "unconscious";
        } else {
            _health = if (_blood < 60) then { "wounded" } else { "stable" };
        };
    };
} else {
    if (!alive _unit || {lifeState _unit == "INCAPACITATED"}) then {
        _health = "unconscious";
        _hr = 0;
    } else {
        if (damage _unit > 0.5) then { _health = "wounded"; };
    };
};

format [
    "%1|%2|%3|%4|%5",
    _health,
    round _blood,
    if (_pain) then { "1" } else { "0" },
    round _hr,
    if (_cardiac) then { "1" } else { "0" }
]
