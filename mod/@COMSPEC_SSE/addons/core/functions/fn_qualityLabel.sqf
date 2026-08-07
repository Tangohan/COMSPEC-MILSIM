/*
    [_qualityNumber] call comspec_sse_fnc_qualityLabel -> "POOR"|"PARTIAL"|"GOOD"|"EXCELLENT"
*/
params [
    ["_q", 0, [0]]
];

if (_q < 30) exitWith { "POOR" };
if (_q < 55) exitWith { "PARTIAL" };
if (_q < 80) exitWith { "GOOD" };
"EXCELLENT"
