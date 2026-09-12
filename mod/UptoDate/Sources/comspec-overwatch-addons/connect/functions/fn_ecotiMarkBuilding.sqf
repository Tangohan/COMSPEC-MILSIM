/*
    Marque le bâtiment sous le regard (ou le plus proche) pour silhouette + badge.
*/
if (!hasInterface) exitWith {};
if (!([] call comspec_overwatch_connect_fnc_ecotiIsAvailable)) exitWith {
    ["Affichage situation indisponible (désactivé ou F-PANO déjà chargé).", "system", "info"] call comspec_overwatch_connect_fnc_announce;
};

private _camPos = AGLToASL (positionCameraToWorld [0, 0, 0]);
private _camEnd = AGLToASL (positionCameraToWorld [0, 0, 120]);
private _hits = lineIntersectsSurfaces [_camPos, _camEnd, player, vehicle player, true, 1, "GEOM", "NONE"];

private _building = objNull;
if ((count _hits) > 0) then {
    private _obj = (_hits select 0) select 2;
    if (!isNull _obj) then {
        if (_obj isKindOf "House" || {_obj isKindOf "Building"}) then {
            _building = _obj;
        } else {
            private _parent = objectParent _obj;
            if (!isNull _parent && {_parent isKindOf "House" || {_parent isKindOf "Building"}}) then {
                _building = _parent;
            };
        };
    };
};

if (isNull _building) then {
    private _near = nearestObjects [player, ["House", "Building"], 35];
    if ((count _near) > 0) then { _building = _near select 0 };
};

if (isNull _building) exitWith {
    ["Aucun bâtiment repéré sous le regard.", "system", "info"] call comspec_overwatch_connect_fnc_announce;
};

private _prevMk = missionNamespace getVariable ["COMSPEC_EcotiBuildingMarker", ""];
if (_prevMk isNotEqualTo "" && {_prevMk in allMapMarkers}) then {
    deleteMarkerLocal _prevMk;
};

private _dn = getText (configFile >> "CfgVehicles" >> typeOf _building >> "displayName");
if (_dn isEqualTo "") then { _dn = "Bâtiment"; };
private _grid = mapGridPosition _building;
private _label = format ["%1 (%2)", _dn, _grid];

private _mkName = format ["COMSPEC_ECOTI_BLDG_%1", round (random 99999)];
private _pos = getPosATL _building;
private _mk = createMarkerLocal [_mkName, _pos];
_mk setMarkerTypeLocal "mil_box";
_mk setMarkerColorLocal "ColorGreen";
_mk setMarkerTextLocal _label;
_mk setMarkerAlphaLocal 0.9;

missionNamespace setVariable ["COMSPEC_EcotiMarkedBuilding", _building, false];
missionNamespace setVariable ["COMSPEC_EcotiMarkedBuildingName", _dn, false];
missionNamespace setVariable ["COMSPEC_EcotiBuildingMarker", _mkName, false];
[
    format ["Bâtiment désigné : %1.", _dn],
    "system",
    "info"
] call comspec_overwatch_connect_fnc_announce;
