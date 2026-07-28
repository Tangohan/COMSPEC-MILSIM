/*
    Returns aircraft type string for Flight Manifest: "plane" | "helicopter" | "uav" | "ground"
    params: [_vehicle] (object)
*/
params ["_vehicle"];
if (isNull _vehicle) exitWith { "ground" };
if (_vehicle isKindOf "Man") exitWith { "ground" };
if (_vehicle isKindOf "Plane") exitWith { "plane" };
if (_vehicle isKindOf "Helicopter") exitWith { "helicopter" };
if (_vehicle isKindOf "UAV" || {_vehicle isKindOf "UAV_01_base_F"}) exitWith { "uav" };
if (_vehicle isKindOf "Air") exitWith { "plane" };
"ground"
