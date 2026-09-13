/*
    Applique un déplacement ATAK à un groupe d’IA (là où le groupe est local).
    Params: [_posX, _posY, _orderId, _label]
*/
params [
    ["_posX", 0, [0]],
    ["_posY", 0, [0]],
    ["_orderId", "", [""]],
    ["_label", "Déplacement", [""]],
    ["_unit", objNull, [objNull]]
];

if (isNull _unit || {!alive _unit}) exitWith { false };
if ((abs _posX) < 0.5 && {(abs _posY) < 0.5}) exitWith { false };

private _grp = group _unit;
if (isNull _grp) exitWith { false };

if (!local _grp) exitWith {
    [_posX, _posY, _orderId, _label, _unit] remoteExecCall ["comspec_overwatch_connect_fnc_applyAiMoveOrder", groupOwner _grp];
    true
};

private _z = getTerrainHeightASL [_posX, _posY];
private _pos = [_posX, _posY, _z];

while { (count (waypoints _grp)) > 0 } do {
    deleteWaypoint [_grp, 0];
};

private _wp = _grp addWaypoint [_pos, 8];
_wp setWaypointType "MOVE";
_wp setWaypointBehaviour "AWARE";
_wp setWaypointCombatMode "YELLOW";
_wp setWaypointSpeed "NORMAL";
_wp setWaypointCompletionRadius 12;
if (_label isNotEqualTo "") then {
    _wp setWaypointDescription _label;
};

(leader _grp) doMove _pos;
{
    if (!isPlayer _x && {alive _x} && {vehicle _x isEqualTo _x}) then {
        _x doMove _pos;
    };
} forEach (units _grp);

_grp setVariable ["COMSPEC_AiMoveOrderId", _orderId, false];
_grp setVariable ["COMSPEC_AiMovePos", [_posX, _posY], false];
true
