/*
    Classe de plateforme pour le symbole BFT (carte Athena).
    Params: unité ou véhicule
    Retour: INFANTRY | TANK | APC | IFV | TRUCK | LIGHT_VEHICLE | ARTILLERY | MORTAR |
            HELICOPTER | FIXED_WING | UAV | BOAT
*/
params [["_obj", objNull, [objNull]]];
if (isNull _obj) exitWith { "INFANTRY" };

private _veh = _obj;
if (_obj isKindOf "Man") then {
    _veh = vehicle _obj;
};
if (_obj isKindOf "Man" && {_veh isEqualTo _obj}) exitWith { "INFANTRY" };
if (isNull _veh) exitWith { "INFANTRY" };
if (_veh isKindOf "Man") exitWith { "INFANTRY" };

private _class = "LIGHT_VEHICLE";
if (_veh isKindOf "Car") then { _class = "LIGHT_VEHICLE" };
if (_veh isKindOf "Tank") then { _class = "TANK" };
if (_veh isKindOf "Wheeled_APC_F") then { _class = "APC" };
if (_veh isKindOf "APC_Tracked_01_base_F") then { _class = "APC" };
if (_veh isKindOf "APC_Tracked_02_base_F") then { _class = "APC" };
if (_veh isKindOf "APC_Wheeled_01_base_F") then { _class = "APC" };
if (_veh isKindOf "APC_Wheeled_02_base_F") then { _class = "APC" };
if (_veh isKindOf "APC_Wheeled_03_base_F") then { _class = "APC" };
if (_veh isKindOf "IFV") then { _class = "IFV" };
if (_veh isKindOf "Truck_F") then { _class = "TRUCK" };
if (_veh isKindOf "Ship") then { _class = "BOAT" };
if (_veh isKindOf "Boat") then { _class = "BOAT" };
if (_veh isKindOf "Helicopter") then { _class = "HELICOPTER" };
if (_veh isKindOf "Plane") then { _class = "FIXED_WING" };
if (_veh isKindOf "Air" && {!(_veh isKindOf "Helicopter")}) then { _class = "FIXED_WING" };
if (_veh isKindOf "UAV" || {unitIsUAV _veh}) then { _class = "UAV" };
if (_veh isKindOf "StaticMortar") then { _class = "MORTAR" };
if (_veh isKindOf "Artillery") then { _class = "ARTILLERY" };
if (getNumber (configOf _veh >> "artilleryScanner") > 0) then {
    if (_veh isKindOf "StaticMortar") then {
        _class = "MORTAR";
    } else {
        _class = "ARTILLERY";
    };
};

_class
