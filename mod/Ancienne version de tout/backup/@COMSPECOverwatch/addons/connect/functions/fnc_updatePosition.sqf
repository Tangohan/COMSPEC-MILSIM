params ["_unit"];
if (isNull _unit || !alive _unit) exitWith {};

private _pos = getPos _unit;
private _callSign = name _unit;
private _role = (roleDescription _unit) splitString ":" joinString " - ";
if (_role == "") then { _role = typeOf _unit; };

private _health = "ok";
if (isClass (configFile >> "CfgPatches" >> "ace_medical")) then {
    if (_unit getVariable ["ace_medical_incapacitated", false]) then { _health = "unconscious"; } else {
        private _blood = _unit getVariable ["ace_medical_bloodVolume", 100];
        _health = if (_blood < 60) then { "wounded" } else { "stable" };
    };
} else {
    if (damage _unit > 0.5) then { _health = "wounded"; };
};

private _fuel = if (vehicle _unit != _unit) then { str (round ((fuel vehicle _unit) * 100)) } else { "" };
private _ammo = if (vehicle _unit == _unit) then { str (count (magazines _unit)) + " mags" } else { "Vehicle" };

private _radioFreq = "N/A";
if (isClass (configFile >> "CfgPatches" >> "tfar_core")) then {
    private _freq = _unit call TFAR_fnc_getCurrentSwFrequency;
    if (!isNil "_freq") then { _radioFreq = str _freq; };
};

"COMSPECExtension" callExtension ["UpdatePosition", [
    str (_pos select 0), str (_pos select 1), str (getDir _unit),
    _callSign, _role, _health, _fuel, _ammo, _radioFreq
]];
