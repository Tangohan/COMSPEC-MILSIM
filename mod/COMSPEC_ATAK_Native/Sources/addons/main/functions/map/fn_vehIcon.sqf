/*
    Genre et silhouette d'un engin (CfgVehicleIcons : avion, hélicoptère, navire, char, arme fixe, véhicule), la même pour
    tous les engins d'un genre : plus lisible en petit que l'icône du modèle. Partagée par la fiche d'unité (fn_unitCard) et
    la carte (fn_mapOnDraw : unité ou équipage à bord dessiné avec la silhouette de son engin). Gardée par classe d'engin.
    Params : [engin] -> [genre ("plane" | "heli" | "ship" | "tank" | "static" | "car"), texture]
*/
params [["_veh", objNull]];
if (isNull _veh) exitWith { ["", ""] };
private _cache = uiNamespace getVariable "COMSPEC_ATAK_VehIconCache";
if (isNil "_cache") then { _cache = createHashMap; uiNamespace setVariable ["COMSPEC_ATAK_VehIconCache", _cache]; };
private _t = typeOf _veh;
private _hit = _cache getOrDefault [_t, []];
if ((count _hit) isEqualTo 2) exitWith { _hit };
private _k = call {
    if (_veh isKindOf "Plane") exitWith { ["plane", "iconPlane"] };
    if (_veh isKindOf "Helicopter") exitWith { ["heli", "iconHelicopter"] };
    if (_veh isKindOf "Ship") exitWith { ["ship", "iconShip"] };
    if (_veh isKindOf "Tank") exitWith { ["tank", "iconTank"] };
    if (_veh isKindOf "StaticWeapon") exitWith { ["static", "iconStaticCannon"] };
    ["car", "iconCar"]
};
private _icon = getText (configFile >> "CfgVehicleIcons" >> (_k select 1));
if (_icon isEqualTo "") then { _icon = format ["\A3\ui_f\data\map\vehicleicons\%1_ca.paa", toLower (_k select 1)]; };
_hit = [_k select 0, _icon];
_cache set [_t, _hit];
_hit
