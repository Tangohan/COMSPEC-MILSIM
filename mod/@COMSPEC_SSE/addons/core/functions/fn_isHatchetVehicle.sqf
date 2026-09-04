/*
    [_obj] call comspec_sse_fnc_isHatchetVehicle

    Appareil dont le cockpit est géré par Hatchet (H-60 et similaires).
    Sur ces machines, les menus ACE SSE volent le clic / la molette du tableau de bord.
*/
params [
    ["_obj", objNull, [objNull]]
];

if (isNull _obj) exitWith { false };
if (_obj isKindOf "CAManBase") then { _obj = vehicle _obj; };
if (isNull _obj || {_obj isEqualTo player}) exitWith { false };
if (!(_obj isKindOf "Air") && {!(_obj isKindOf "LandVehicle")}) exitWith { false };

private _cfg = configOf _obj;
if (isClass (_cfg >> "hct")) exitWith { true };
if (isClass (_cfg >> "hct_driver")) exitWith { true };
if (isClass (_cfg >> "hct_copilot")) exitWith { true };
if (isClass (_cfg >> "hct_gunner")) exitWith { true };
if (!isNil {_obj getVariable "hct_interaction"}) exitWith { true };
if (!isNil {_obj getVariable "hct_modules"}) exitWith { true };

false
