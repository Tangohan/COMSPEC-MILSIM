/*
    Pose du cône de preuve, côté serveur (numéro unique pour toute l'opération).
    [_target, _player] call comspec_sse_fnc_placeMarkerServer
*/
params [["_target", objNull, [objNull]], ["_player", objNull, [objNull]]];
if (!isServer || {isNull _target}) exitWith { false };
if ((_target getVariable ["comspec_sse_markerNo", 0]) > 0) exitWith { false };

private _n = (missionNamespace getVariable ["comspec_sse_markerCounter", 0]) + 1;
missionNamespace setVariable ["comspec_sse_markerCounter", _n];
_target setVariable ["comspec_sse_markerNo", _n, true];

// Cône posé au sol, côté opérateur, à 60 cm de la pièce.
private _pos = getPosATL _target;
private _dir = if (isNull _player) then { random 360 } else { _target getDir _player };
private _conePos = _pos getPos [0.6, _dir];
_conePos set [2, 0];
private _cone = createVehicle ["RoadCone_L_F", _conePos, [], 0, "CAN_COLLIDE"];
_cone setDir _dir;
_cone setVariable ["comspec_sse_markerOf", _target, true];

private _mk = createMarker [format ["comspec_sse_cone_%1", _n], _pos];
// Réglages locaux puis une seule diffusion (le dernier setMarker global envoie tout).
_mk setMarkerTypeLocal "mil_dot";
_mk setMarkerColorLocal "ColorYellow";
_mk setMarkerText format ["#%1", _n];

["merge", _target, ["evidenceState", createHashMapFromArray [
    ["marker", _n], ["markerBy", if (isNull _player) then { "" } else { name _player }], ["markerAt", serverTime]
]]] call comspec_sse_fnc_recordOp;
if (!isNil "comspec_sse_fnc_custody" && {(["piece", [_target]] call comspec_sse_fnc_custody) isNotEqualTo ""}) then {
    ["event", [_target, "CÔNE DE PREUVE", _player, format ["n°%1", _n]]] call comspec_sse_fnc_custody;
};
if (!isNull _player && {isPlayer _player}) then {
    [format ["Cône de preuve n°%1 posé.", _n], "SUCCESS"] remoteExecCall ["comspec_sse_fnc_notify", owner _player];
};
true
