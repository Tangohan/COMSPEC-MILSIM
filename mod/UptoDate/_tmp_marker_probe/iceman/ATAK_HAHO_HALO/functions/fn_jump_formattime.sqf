#include "..\script_component.hpp"

params [["_seconds", 0]];

_seconds = round (_seconds max 0);
private _minutes = floor (_seconds / 60);
private _remSeconds = _seconds mod 60;

format ["%1:%2", _minutes, [_remSeconds, 2] call BIS_fnc_padNumber]
