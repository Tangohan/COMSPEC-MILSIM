/*
    [_profile] call comspec_sse_fnc_resolveProfile
*/
params [
    ["_profile", "RANDOM", [""]]
];

_profile = toUpper _profile;
if (_profile == "RANDOM" || {_profile == "HVT"} || {_profile == "CUSTOM"} || {_profile == "INSURGENT_CELL"}) then {
    if (_profile == "HVT") exitWith { "COMMANDER" };
    if (_profile == "INSURGENT_CELL") exitWith { "INSURGENT" };
    if (_profile == "CUSTOM") exitWith { "CIVILIAN" };
    private _all = ["CIVILIAN","INSURGENT","MILITARY","COMMANDER","COURIER","FINANCIER","TECHNICIAN","INTELLIGENCE","LOGISTICS"];
    private _h = floor random (count _all);
    _all select _h
} else {
    _profile
};
