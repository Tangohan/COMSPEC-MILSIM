/*
    Retourne une chaîne "health|blood|pain" pour cache / payload.
    health: stable | wounded | unconscious
*/
params ["_unit"];
private _health = "stable";
private _blood = 100;
private _pain = false;

if (isClass (configFile >> "CfgPatches" >> "ace_medical")) then {
    _pain = _unit getVariable ["ace_medical_inPain", false];
    _blood = _unit getVariable ["ace_medical_bloodVolume", 100];
    if (_unit getVariable ["ace_medical_incapacitated", false]) then {
        _health = "unconscious";
    } else {
        _health = if (_blood < 60) then { "wounded" } else { "stable" };
    };
} else {
    if (damage _unit > 0.5) then { _health = "wounded"; };
};

format ["%1|%2|%3", _health, _blood, if (_pain) then { "1" } else { "0" }]
