/*
    Pose marqueur + trait d’itinéraire après acceptation d’un ordre MOVE avec point de mission.
    Params: [_order] HashMap ordre (id, payload, issuer, …)
*/
params [["_order", createHashMap]];

if (!hasInterface) exitWith {};
if !(_order isEqualType createHashMap) exitWith {};

private _payload = _order getOrDefault ["payload", ""];
private _wp = [_payload] call comspec_overwatch_connect_fnc_orderParseWaypoint;
if ((count _wp) < 2) exitWith {};
if !(_wp getOrDefault ["pos_x", 0] isEqualType 0) exitWith {};

private _px = _wp getOrDefault ["pos_x", 0];
private _py = _wp getOrDefault ["pos_y", 0];
private _label = _wp getOrDefault ["label", "Objectif"];
private _grid = _wp getOrDefault ["grid", ""];
private _eta = _wp getOrDefault ["eta_min", -1];
private _issuer = _order getOrDefault ["issuer", "Commandement"];
private _orderId = _order getOrDefault ["id", ""];

if (_grid isEqualTo "") then {
    _grid = mapGridPosition [_px, _py, 0];
};

private _z = getTerrainHeightASL [_px, _py];
private _pos = [_px, _py, _z];

private _mkName = format ["comspec_ordwp_%1_%2", _orderId, floor (random 100000)];
private _marker = createMarkerLocal [_mkName, _pos];
_marker setMarkerTypeLocal "mil_objective";
_marker setMarkerColorLocal "ColorYellow";
_marker setMarkerTextLocal _label;
_marker setMarkerAlphaLocal 0.95;

private _pPos = getPosATL player;
private _routeName = format ["comspec_ordwp_rt_%1_%2", _orderId, floor (random 100000)];
private _route = createMarkerLocal [_routeName, [_pPos select 0, _pPos select 1, 0]];
_route setMarkerShapeLocal "POLYLINE";
_route setMarkerColorLocal "ColorBlue";
_route setMarkerBrushLocal "Solid";
_route setMarkerPolylineLocal [_pPos select 0, _pPos select 1, _px, _py];
_route setMarkerAlphaLocal 0.75;

private _etaTxt = if (_eta >= 0) then {
    format [" · arrivée estimée ~%1 min", round _eta]
} else {
    ""
};

private _human = _wp getOrDefault ["text", ""];
private _detail = if (_human != "") then { _human } else { _label };
private _msg = format [
    "Objectif confirmé — %1 (%2)%3 · ordre de %4",
    _detail,
    _grid,
    _etaTxt,
    _issuer
];

["COMSPEC_Info", [_msg]] call comspec_overwatch_connect_fnc_showNotification;
[_msg, "orders"] call comspec_overwatch_connect_fnc_appendLinkLog;

private _applied = missionNamespace getVariable ["COMSPEC_OrderWaypointsApplied", []];
if !(_orderId in _applied) then {
    _applied pushBack _orderId;
    if (count _applied > 40) then { _applied deleteRange [0, (count _applied) - 40]; };
    missionNamespace setVariable ["COMSPEC_OrderWaypointsApplied", _applied, false];
};
