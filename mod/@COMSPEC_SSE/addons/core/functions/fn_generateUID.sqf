/*
    Génère un UID SSE unique.
    [_prefix] call comspec_sse_fnc_generateUID
*/
params [
    ["_prefix", "SSE", [""]]
];

if (isNil "comspec_sse_uidCounter") then {
    comspec_sse_uidCounter = 0;
};

comspec_sse_uidCounter = comspec_sse_uidCounter + 1;
private _year = (date select 0) mod 100;
private _n = comspec_sse_uidCounter;
private _pad = str _n;
while {count _pad < 6} do { _pad = "0" + _pad; };
private _yPad = str _year;
if (count _yPad < 2) then { _yPad = "0" + _yPad; };

format ["%1-%2-%3", _prefix, _yPad, _pad]
