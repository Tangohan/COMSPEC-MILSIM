/*
    Désigne le bâtiment regardé et coupe la silhouette à la hauteur pointée.
    Active le découpage d’étage si besoin.
*/
if (!hasInterface) exitWith {};
if (!([] call comspec_overwatch_connect_fnc_ecotiIsAvailable)) exitWith {
    ["Affichage situation indisponible (désactivé ou F-PANO déjà chargé).", "system", "info"] call comspec_overwatch_connect_fnc_announce;
};

private _camPos = AGLToASL (positionCameraToWorld [0, 0, 0]);
private _camEnd = AGLToASL (positionCameraToWorld [0, 0, 120]);
private _hits = lineIntersectsSurfaces [_camPos, _camEnd, player, vehicle player, true, 1, "GEOM", "NONE"];

private _building = objNull;
private _hitASL = [];
if ((count _hits) > 0) then {
    private _hit = _hits select 0;
    _hitASL = _hit select 0;
    private _obj = _hit select 2;
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
if (_prevMk isNotEqualTo "" && {!isNil "comspec_overwatch_connect_fnc_syncMapMarker"}) then {
    [_prevMk, true, true] call comspec_overwatch_connect_fnc_syncMapMarker;
};
if (_prevMk isNotEqualTo "" && {_prevMk in allMapMarkers}) then {
    deleteMarkerLocal _prevMk;
};

private _dn = getText (configFile >> "CfgVehicles" >> typeOf _building >> "displayName");
if (_dn isEqualTo "") then { _dn = "Bâtiment"; };
private _grid = mapGridPosition _building;
private _label = format ["%1 (%2)", _dn, _grid];
private _mkName = [] call comspec_overwatch_connect_fnc_ecotiBuildingMarkerName;
private _mk = createMarkerLocal [_mkName, getPosATL _building];
_mk setMarkerTypeLocal "mil_box";
_mk setMarkerColorLocal "ColorGreen";
_mk setMarkerTextLocal _label;
_mk setMarkerAlphaLocal 0.9;

missionNamespace setVariable ["COMSPEC_EcotiMarkedBuilding", _building, false];
missionNamespace setVariable ["COMSPEC_EcotiMarkedBuildingName", _dn, false];
missionNamespace setVariable ["COMSPEC_EcotiBuildingMarker", _mkName, false];
if (!isNil "comspec_overwatch_connect_fnc_syncMapMarker") then {
    [_mkName, false, true] call comspec_overwatch_connect_fnc_syncMapMarker;
};

[true, true] call comspec_overwatch_connect_fnc_ecotiApplyCutawaySetting;

private _bb = boundingBoxReal _building;
if (!(_bb isEqualType []) || {(count _bb) < 2}) exitWith {};
private _min = _bb select 0;
private _max = _bb select 1;
private _z0 = _min select 2;
private _z1 = _max select 2;
private _h = abs (_z1 - _z0);
if (_h < 1.2) exitWith {};

private _floors = ((round (_h / 3)) max 1) min 8;
missionNamespace setVariable ["COMSPEC_EcotiCutawayFloorCount", _floors, false];

private _hitLocalZ = _z0 + (_h * 0.35);
if ((count _hitASL) >= 3) then {
    private _local = _building worldToModel (ASLToAGL _hitASL);
    if ((count _local) >= 3) then { _hitLocalZ = _local select 2; };
};

private _ratio = ((_hitLocalZ - _z0) / _h) max 0 min 0.999;
private _sel = (floor (_ratio * _floors)) max 0 min (_floors - 1);
missionNamespace setVariable ["COMSPEC_EcotiCutawayFloor", _sel, false];
[_building, true] call comspec_overwatch_connect_fnc_ecotiRefreshBuildingFootprint;

[
    format ["Découpe visuelle : %1 — étage %2/%3 (hauteur regardée). Le curseur du téléphone suit.", _dn, _sel + 1, _floors],
    "system",
    "info"
] call comspec_overwatch_connect_fnc_announce;
if (!isNil "comspec_overwatch_atak_athena_fnc_athena_updateBuildingSheet") then {
    [] call comspec_overwatch_atak_athena_fnc_athena_updateBuildingSheet;
};
_sel
