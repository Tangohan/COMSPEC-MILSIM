/*
    Marque le bâtiment sous le regard (ou le plus proche) pour le wireframe FOV.
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

missionNamespace setVariable ["COMSPEC_EcotiMarkedBuilding", _building, false];
["Bâtiment marqué pour l’affichage situation (JVN).", "system", "info"] call comspec_overwatch_connect_fnc_announce;
