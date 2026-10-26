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
    // groupOwner ne fonctionne que sur le serveur (renvoie 0 côté client => diffusion à tous, boucle infinie).
    // Côté client : on relaie au serveur, qui résout le propriétaire et relaie à son tour.
    if (isServer) then {
        private _owner = groupOwner _grp;
        if (_owner > 0) then {
            [_posX, _posY, _orderId, _label, _unit] remoteExecCall ["comspec_overwatch_connect_fnc_applyAiMoveOrder", _owner];
        };
    } else {
        [_posX, _posY, _orderId, _label, _unit] remoteExecCall ["comspec_overwatch_connect_fnc_applyAiMoveOrder", 2];
    };
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
