/*
    Ajoute ou efface un point d’itinéraire local (tracé sous JVN).
    Avec l’ATAK natif (app Routes, 2.9.34) : le point regardé va dans la route « Itinéraire JVN » de l’app (carte du
    téléphone, vue 3D, partage, guidage) ; « effacer » supprime cette route. Sans l’ATAK natif : tracé local comme avant.
    Params: ["add"|"clear"]
*/
params [["_mode", "add", [""]]];
if (!hasInterface) exitWith {};
if (!([] call comspec_overwatch_connect_fnc_ecotiIsAvailable)) exitWith {
    ["Affichage situation indisponible.", "system", "info"] call comspec_overwatch_connect_fnc_announce;
};

private _native = !isNil "comspec_atak_native_fnc_routeAction";
if (_mode isEqualTo "clear") exitWith {
    if (_native) exitWith { ["jvnClear"] call comspec_atak_native_fnc_routeAction; };
    missionNamespace setVariable ["COMSPEC_EcotiRoutePoints", [], false];
    ["Itinéraire situation effacé.", "system", "info"] call comspec_overwatch_connect_fnc_announce;
};

private _camPos = AGLToASL (positionCameraToWorld [0, 0, 0]);
private _camEnd = AGLToASL (positionCameraToWorld [0, 0, 120]);
private _hits = lineIntersectsSurfaces [_camPos, _camEnd, player, vehicle player, true, 1, "GEOM", "NONE"];
private _pos = positionCameraToWorld [0, 0, 40];
if ((count _hits) > 0) then {
    _pos = ASLToAGL ((_hits select 0) select 0);
};
_pos set [2, (_pos select 2) + 0.4];
if (_native) exitWith { ["jvnAdd", _pos] call comspec_atak_native_fnc_routeAction; };

private _pts = missionNamespace getVariable ["COMSPEC_EcotiRoutePoints", []];
if (!(_pts isEqualType [])) then { _pts = []; };
_pts pushBack _pos;
if ((count _pts) > 24) then {
    _pts = _pts select [(count _pts) - 24, 24];
};
missionNamespace setVariable ["COMSPEC_EcotiRoutePoints", _pts, false];
[
    format ["Point d’itinéraire ajouté (%1).", count _pts],
    "system",
    "info"
] call comspec_overwatch_connect_fnc_announce;
