/*
    [_obj] call comspec_overwatch_connect_fnc_isHatchetVehicle

    Appareil à tableau de bord Hatchet (H-60 et similaires).
    Les menus ACE Overwatch ne doivent pas rester ouverts ni viser l’équipage :
    le clic et la molette servent au démarrage et aux interrupteurs.
*/
params [
    ["_obj", objNull, [objNull]]
];

if (isNull _obj) exitWith { false };
if (_obj isKindOf "CAManBase") then { _obj = vehicle _obj; };
if (isNull _obj) exitWith { false };
if (!(_obj isKindOf "Air") && {!(_obj isKindOf "LandVehicle")}) exitWith { false };

private _cfg = configOf _obj;
if (isClass (_cfg >> "hct")) exitWith { true };
if (isClass (_cfg >> "hct_driver")) exitWith { true };
if (isClass (_cfg >> "hct_copilot")) exitWith { true };
if (isClass (_cfg >> "hct_gunner")) exitWith { true };
if (!isNil {_obj getVariable "hct_interaction"}) exitWith { true };
if (!isNil {_obj getVariable "hct_modules"}) exitWith { true };

false
