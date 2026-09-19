/*
    Module Zeus/Eden : pose un mât relais ATAK détruisible.
*/
private _args = if (_this isEqualType []) then { _this } else { [_this] };
private _logic = objNull;
private _activated = true;
private _a0 = _args param [0, objNull];
if (_a0 isEqualType objNull) then {
    _logic = _a0;
    _activated = _args param [2, true];
} else {
    if (_a0 isEqualType "" && {(_args param [1, objNull]) isEqualType objNull}) then {
        _logic = _args param [1, objNull];
        _activated = _args param [3, true];
    };
};
if (isNull _logic) exitWith { false };
if (!_activated) exitWith { true };

private _pos = getPosATL _logic;
private _name = _logic getVariable ["RelayName", "Relais ATAK"];
if (!(_name isEqualType "") || {_name isEqualTo ""}) then { _name = "Relais ATAK"; };
private _range = _logic getVariable ["RangeM", 2000];
if (!(_range isEqualType 0)) then { _range = 2000; };
_range = (_range max 50) min 8000;

private _meta = createHashMap;
_meta set ["identity", _logic getVariable ["RelayIdentity", _name]];
_meta set ["ip", _logic getVariable ["RelayIp", ""]];
_meta set ["gateway", _logic getVariable ["RelayGateway", ""]];
_meta set ["certificate", _logic getVariable ["RelayCertificate", ""]];
_meta set ["slots", _logic getVariable ["RelaySlots", 8]];
_meta set ["power_w", _logic getVariable ["RelayPowerW", 25]];
_meta set ["throughput_mbps", _logic getVariable ["RelayThroughput", 12]];
_meta set ["reliability_pct", _logic getVariable ["RelayReliability", 92]];

[_pos, _range, _name, _meta] call comspec_overwatch_connect_fnc_placeAtakRelay;
deleteVehicle _logic;
true
