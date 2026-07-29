/*
    Retourne "health|blood|pain|heartRate|cardiacArrest|spo2|airway|pneumothorax"
    Source de vérité : ACE Medical.
    Champs 6-8 conservés pour compatibilité (toujours "-").
*/
params ["_unit"];
if (isNull _unit) exitWith { "stable|100|0|80|0|-|-|-|" };

private _health = "stable";
private _blood = 100;
private _pain = false;
private _hr = 80;
private _cardiac = false;

private _hasAce = isClass (configFile >> "CfgPatches" >> "ace_medical");

if (_hasAce) then {
    _pain = _unit getVariable ["ace_medical_inPain", false];

    if (!isNil "ace_medical_status_fnc_getBloodVolume") then {
        private _bv = [_unit] call ace_medical_status_fnc_getBloodVolume;
        if (_bv isEqualType 0) then { _blood = round ((_bv / 6) * 100) max 0 min 100; };
    } else {
        _blood = _unit getVariable ["ace_medical_bloodVolume", 6];
        if (_blood isEqualType 0 && {_blood <= 12}) then { _blood = round ((_blood / 6) * 100) max 0 min 100; };
    };

    _hr = _unit getVariable ["ace_medical_heartRate", 80];
    if (!isNil "ace_medical_status_fnc_getHeartRate") then {
        private _hrFn = [_unit] call ace_medical_status_fnc_getHeartRate;
        if (_hrFn isEqualType 0) then { _hr = _hrFn; };
    };

    private _uncon = _unit getVariable ["ACE_isUnconscious", false];
    if (!_uncon) then { _uncon = _unit getVariable ["ace_medical_unconscious", false]; };
    if (!_uncon) then { _uncon = _unit getVariable ["ace_medical_incapacitated", false]; };
    if (!_uncon && {lifeState _unit == "INCAPACITATED"}) then { _uncon = true; };

    _cardiac = _unit getVariable ["ace_medical_inCardiacArrest", false];
    if (!_cardiac && {_hr <= 0} && {_uncon}) then { _cardiac = true; };

    if (_cardiac) then {
        _health = "cardiac_arrest";
        _hr = 0;
    } else {
        if (_uncon) then {
            _health = "unconscious";
        } else {
            if (_blood < 75) then {
                _health = if (_blood < 50) then { "critical" } else { "wounded" };
            } else {
                _health = "stable";
            };
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
    "%1|%2|%3|%4|%5|%6|%7|%8",
    _health,
    round _blood,
    if (_pain) then { "1" } else { "0" },
    round _hr,
    if (_cardiac) then { "1" } else { "0" },
    "-",
    "-",
    "-"
]
