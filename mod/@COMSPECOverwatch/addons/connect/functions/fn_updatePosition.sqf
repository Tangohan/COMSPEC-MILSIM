params ["_unit"];
if (isNull _unit || !alive _unit) exitWith {};

// Alertes KO / rythme cardiaque à zéro (chaque tick, avant le filtre de batch position)
[_unit] call comspec_overwatch_connect_fnc_checkMedicalAlerts;

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

private _threshold = missionNamespace getVariable ["comspec_overwatch_position_threshold", 5];
private _batchInterval = missionNamespace getVariable ["comspec_overwatch_batch_interval", 1];
private _lastPos = missionNamespace getVariable ["COMSPEC_lastPos", [0,0,0]];
private _lastName = missionNamespace getVariable ["COMSPEC_lastName", ""];
private _lastRole = missionNamespace getVariable ["COMSPEC_lastRole", ""];
private _lastRadio = missionNamespace getVariable ["COMSPEC_lastRadio", ""];
private _lastMedical = missionNamespace getVariable ["COMSPEC_lastMedical", ""];
private _lastSendTime = missionNamespace getVariable ["COMSPEC_lastSendTime", 0];
private _now = diag_tickTime;

private _distance = _pos distance _lastPos;
private _distanceOk = _distance > _threshold;
private _nameChanged = _callSign != _lastName;
private _roleChanged = _role != _lastRole;
private _radioChanged = _radioState != _lastRadio;
private _medicalChanged = _medicalState != _lastMedical;
private _batchOk = (_now - _lastSendTime) >= _batchInterval;

private _shouldSend = _batchOk && (_distanceOk || _nameChanged || _roleChanged || _radioChanged || _medicalChanged);
if (!_shouldSend) exitWith {};

private _velocity = velocity _unit;
private _speed = vectorMagnitude _velocity;
private _heading = getDir _unit;
private _future = [
    (_pos select 0) + ((_velocity select 0) * 10),
    (_pos select 1) + ((_velocity select 1) * 10),
    _pos select 2
];

private _stealthMode = if ((unitPos _unit) in ["DOWN", "MIDDLE"] || {captive _unit}) then { "ON" } else { "OFF" };
private _reportedPos = if (_stealthMode == "ON") then {
    [(_pos select 0) + (random 20) - 10, (_pos select 1) + (random 20) - 10, _pos select 2]
} else {
    _pos
};

"COMSPECExtension" callExtension ["UpdatePosition", [
    str (_reportedPos select 0), str (_reportedPos select 1), str _heading,
    _callSign, _role, _health, _fuel, _ammo, _radioFreq
]];

private _trail = missionNamespace getVariable ["COMSPEC_PositionTrail", []];
_trail pushBack [_now, _callSign, _pos, _speed, _heading, _future];
if (count _trail > 150) then {
    _trail deleteRange [0, (count _trail) - 150];
};
missionNamespace setVariable ["COMSPEC_PositionTrail", _trail, true];

private _immobileTime = missionNamespace getVariable ["COMSPEC_ImmobileSince", _now];
if (_distance < 0.5 && {_speed < 0.2}) then {
    // keep existing timer
} else {
    _immobileTime = _now;
};
missionNamespace setVariable ["COMSPEC_ImmobileSince", _immobileTime, true];

if ((_now - _immobileTime) > 180) then {
    private _alert = createHashMapFromArray [["kind", "IMMOBILE"], ["unit", _callSign], ["duration", _now - _immobileTime], ["position", _pos]];
    ["OnTrackingAnomaly", _alert] call comspec_overwatch_connect_fnc_publishEvent;
};

if (_distance > 250 && {_batchInterval > 2}) then {
    private _alert2 = createHashMapFromArray [["kind", "INCOHERENT_MOVE"], ["unit", _callSign], ["distance", _distance], ["from", _lastPos], ["to", _pos]];
    ["OnTrackingAnomaly", _alert2] call comspec_overwatch_connect_fnc_publishEvent;
};

missionNamespace setVariable ["COMSPEC_lastPos", _pos, true];
missionNamespace setVariable ["COMSPEC_lastName", _callSign, true];
missionNamespace setVariable ["COMSPEC_lastRole", _role, true];
missionNamespace setVariable ["COMSPEC_lastRadio", _radioState, true];
missionNamespace setVariable ["COMSPEC_lastMedical", _medicalState, true];
missionNamespace setVariable ["COMSPEC_lastSendTime", _now, true];
missionNamespace setVariable ["COMSPEC_LastPositionSync", _now, false];
// Ne pas forcer « Lié à Athena » ici : UpdatePosition est fire-and-forget côté extension.
// Seuls Connect / refreshLinkStatus (whoami) confirment vraiment la liaison.
[] call comspec_overwatch_connect_fnc_updateStatusBadges;
