/*
    Retourne "health|blood|pain|heartRate|cardiacArrest|spo2|airway|pneumothorax"
    Champs KAM (6-8) : "-" si mod absent ou donnée indisponible.
*/
params ["_unit"];
if (isNull _unit) exitWith { "stable|100|0|80|0|-|-|-|" };

private _health = "stable";
private _blood = 100;
private _pain = false;
private _hr = 80;
private _cardiac = false;
private _spo2 = -1;
private _airway = "-";
private _pneumo = "-";

if (isClass (configFile >> "CfgPatches" >> "ace_medical")) then {
    _pain = _unit getVariable ["ace_medical_inPain", false];
    _blood = _unit getVariable ["ace_medical_bloodVolume", 6];
    if (_blood <= 12) then { _blood = round ((_blood / 6) * 100); };

    _hr = _unit getVariable ["ace_medical_heartRate", 80];
    if (!isNil "ace_medical_status_fnc_getHeartRate") then {
        private _hrFn = [_unit] call ace_medical_status_fnc_getHeartRate;
        if (!isNil "_hrFn" && {_hrFn isEqualType 0}) then { _hr = _hrFn; };
    };

    private _uncon = _unit getVariable ["ACE_isUnconscious", false];
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

// Branche KAM optionnelle (kat_advancedMedical / kat_medical)
private _hasKam = isClass (configFile >> "CfgPatches" >> "kat_advancedMedical")
    || {isClass (configFile >> "CfgPatches" >> "kat_medical")};
if (_hasKam) then {
    private _spo2Raw = _unit getVariable ["kat_bloodGas_spo2", -1];
    if (_spo2Raw isEqualType 0 && {_spo2Raw >= 0}) then { _spo2 = round _spo2Raw; };

    private _airwayObstructed = _unit getVariable ["kat_airway_occluded", false];
    private _airwayStatus = _unit getVariable ["kat_airway_status", -1];
    if (_airwayObstructed) then {
        _airway = "obstructed";
    } else {
        if (_airwayStatus isEqualType 0) then {
            _airway = switch (true) do {
                case (_airwayStatus <= 0): { "clear" };
                case (_airwayStatus == 1): { "partial" };
                default { "secured" };
            };
        };
    };

    if (_unit getVariable ["kat_hemonpneumothorax", false]) then {
        _pneumo = "tension";
    } else {
        if (_unit getVariable ["kat_pneumothorax", false]) then { _pneumo = "open"; };
    };

    // Capteur HR défaillant si SpO2 critique (roleplay capteur)
    if (_spo2 >= 0 && {_spo2 < 80} && {missionNamespace getVariable ["comspec_overwatch_roleplay_enabled", false]}) then {
        if (random 100 < 35) then { _hr = 0; };
    };
};

format [
    "%1|%2|%3|%4|%5|%6|%7|%8",
    _health,
    round _blood,
    if (_pain) then { "1" } else { "0" },
    round _hr,
    if (_cardiac) then { "1" } else { "0" },
    if (_spo2 < 0) then { "-" } else { str _spo2 },
    _airway,
    _pneumo
]
