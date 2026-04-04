params ["_unit"];
if (isNull _unit || !alive _unit) exitWith {};

private _pos = getPos _unit;
private _callSign = name _unit;
private _role = (roleDescription _unit) splitString ":" joinString " - ";
if (_role == "") then { _role = typeOf _unit; };

private _medicalState = [_unit] call comspec_overwatch_connect_fnc_getMedicalState;
private _medicalParts = _medicalState splitString "|";
private _health = if (count _medicalParts >= 1) then { _medicalParts select 0 } else { "stable" };

private _fuel = if (vehicle _unit != _unit) then { str (round ((fuel vehicle _unit) * 100)) } else { "" };
private _ammo = if (vehicle _unit == _unit) then { str (count (magazines _unit)) + " mags" } else { "Vehicle" };

private _radioState = [_unit] call comspec_overwatch_connect_fnc_getRadioState;
private _radioFreq = "N/A";
if (_radioState != "N/A|N/A|N/A") then {
    private _rp = _radioState splitString "|";
    if (count _rp >= 2) then { _radioFreq = _rp select 1; };
};

// Cache et seuils (TASK 3 + 8)
private _threshold = missionNamespace getVariable ["comspec_overwatch_position_threshold", 5];
private _batchInterval = missionNamespace getVariable ["comspec_overwatch_batch_interval", 1];
private _lastPos = missionNamespace getVariable ["COMSPEC_lastPos", [0,0,0]];
private _lastName = missionNamespace getVariable ["COMSPEC_lastName", ""];
private _lastRole = missionNamespace getVariable ["COMSPEC_lastRole", ""];
private _lastRadio = missionNamespace getVariable ["COMSPEC_lastRadio", ""];
private _lastMedical = missionNamespace getVariable ["COMSPEC_lastMedical", ""];
private _lastSendTime = missionNamespace getVariable ["COMSPEC_lastSendTime", 0];
private _now = diag_tickTime;

private _distanceOk = (_pos distance _lastPos) > _threshold;
private _nameChanged = _callSign != _lastName;
private _roleChanged = _role != _lastRole;
private _radioChanged = _radioState != _lastRadio;
private _medicalChanged = _medicalState != _lastMedical;
private _batchOk = (_now - _lastSendTime) >= _batchInterval;

private _shouldSend = _batchOk && (_distanceOk || _nameChanged || _roleChanged || _radioChanged || _medicalChanged);

if (!_shouldSend) exitWith {};

"COMSPECExtension" callExtension ["UpdatePosition", [
    str (_pos select 0), str (_pos select 1), str (getDir _unit),
    _callSign, _role, _health, _fuel, _ammo, _radioFreq
]];

missionNamespace setVariable ["COMSPEC_lastPos", _pos, true];
missionNamespace setVariable ["COMSPEC_lastName", _callSign, true];
missionNamespace setVariable ["COMSPEC_lastRole", _role, true];
missionNamespace setVariable ["COMSPEC_lastRadio", _radioState, true];
missionNamespace setVariable ["COMSPEC_lastMedical", _medicalState, true];
missionNamespace setVariable ["COMSPEC_lastSendTime", _now, true];
