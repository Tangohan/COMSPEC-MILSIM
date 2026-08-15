#include "..\script_component.hpp"

params ["_seconds"];
_seconds = 0 max round _seconds;
private _minutes = floor (_seconds / 60);
private _hours = floor (_minutes / 60);
private _secs = _seconds mod 60;
_minutes = _minutes mod 60;
private _pad = {
    params ["_value"];
    if (_value < 10) exitWith {format ["0%1", _value]};
    str _value
};

format ["%1:%2:%3", [_hours] call _pad, [_minutes] call _pad, [_secs] call _pad]
