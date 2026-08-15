params [
    ["_linkId", ""],
    ["_txRadioId", ""],
    ["_txData", locationNull],
    ["_txUnit", objNull]
];

if (_linkId == "" || {_txRadioId == ""}) exitWith {false};
if !(_txData isEqualType locationNull && {!isNull _txData}) exitWith {false};
if (isNil "acre_sys_signal_fnc_getSignal") exitWith {false};

private _links = missionNamespace getVariable ["Iceman_ROIP_activeLinks", []];
private _index = _links findIf {
    private _link = _x # 0;
    _link isEqualType [] && {(count _link) >= 13} && {(_link # 1) == _linkId}
};
if (_index < 0) exitWith {false};

private _link = (_links # _index) # 0;
private _gatewayOwner = (_links # _index) # 1;
private _gatewayRadioId = _link # 4;
if (_gatewayRadioId == "") exitWith {false};
if (_gatewayRadioId == _txRadioId) exitWith {true};

private _frequency = _txData getVariable ["frequencyTX", -1];
private _power = _txData getVariable ["power", 5000];
if !(_frequency isEqualType 0 && {_frequency > 0}) exitWith {false};
if !(_power isEqualType 0 && {_power > 0}) then {_power = 5000};

private _signal = [_frequency, _power, _gatewayRadioId, _txRadioId] call acre_sys_signal_fnc_getSignal;
private _squelch = 0;
{
    if ((_x # 0) == "squelch") exitWith {_squelch = _x # 1};
} forEach (_link # 8);

private _acrePass = false;
if (_signal isEqualType [] && {(count _signal) >= 2} && {!(_signal isEqualTo [0, -992])}) then {
    _acrePass = (_signal # 1) >= (-118 + _squelch);
};
if (_acrePass) exitWith {true};

// ACRE may briefly return another receiver's cached result for the same transmission.
// Keep the fallback bounded to the physical legacy-radio path, never the whole mesh.
if (isNull _txUnit || {isNull _gatewayOwner}) exitWith {false};

private _powerScale = sqrt (((_power max 100) / 1000) max 0.1);
private _legacyRange = ((3500 * _powerScale) max 3000) min 20000;
private _terrainBlocked = terrainIntersectASL [eyePos _txUnit, eyePos _gatewayOwner];
if (_terrainBlocked) then {
    _legacyRange = (_legacyRange * 0.55) max 3000;
};

(_txUnit distance _gatewayOwner) <= _legacyRange
