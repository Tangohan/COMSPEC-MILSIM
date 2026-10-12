/*
    Date réelle lisible (UTC) pour les sauvegardes de campagne.
    [systemTimeUTC] call comspec_sse_fnc_campaignDate → "12/10/2026 08:30 UTC"
*/
params [["_t", [], [[]]]];
if (count _t < 5) then { _t = systemTimeUTC; };
_t params ["_y", "_m", "_d", "_h", "_mi"];
private _two = { params ["_n"]; [str _n, "0" + str _n] select (_n < 10) };
format ["%1/%2/%3 %4:%5 UTC", [_d] call _two, [_m] call _two, _y, [_h] call _two, [_mi] call _two]
