/*
    Returns aircraft type string for Flight Manifest: "plane" | "helicopter" | "uav"
    params: [_vehicle] (object)
*/
params ["_vehicle"];
if (isNull _vehicle) exitWith { "unknown" };
if (_vehicle isKindOf "Plane") exitWith { "plane" };
if (_vehicle isKindOf "Helicopter") exitWith { "helicopter" };
if (_vehicle isKindOf "UAV" || _vehicle isKindOf "UAV_01_base_F") exitWith { "uav" };
"unknown"
