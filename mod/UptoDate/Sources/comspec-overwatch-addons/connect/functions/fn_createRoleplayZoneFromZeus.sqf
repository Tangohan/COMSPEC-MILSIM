/*
    Création serveur d’une zone roleplay depuis Zeus (ZEN / module).
    Params: [_pos, _radius, _type, _intensity, _anchor]
*/
params [
    ["_pos", [0, 0, 0], [[]]],
    ["_radius", 200, [0]],
    ["_type", "degraded", [""]],
    ["_intensity", 50, [0]],
    ["_anchor", objNull, [objNull]]
];

if (!isServer) exitWith {};

private _zone = [_pos, _radius, _type, _intensity] call comspec_overwatch_connect_fnc_createRoleplayZone;
if (!(_zone isEqualType createHashMap)) exitWith {};

if (isNull _anchor) exitWith { _zone };

private _zoneId = _zone getOrDefault ["id", ""];
if (_zoneId isEqualTo "") exitWith { _zone };

_anchor setVariable ["COMSPEC_RoleplayZoneId", _zoneId, true];

[{
    params ["_args", "_pfhId"];
    _args params ["_anchor", "_zoneId"];
    if (isNull _anchor || {!alive _anchor}) exitWith {
        [_zoneId] call comspec_overwatch_connect_fnc_deleteRoleplayZone;
        [_pfhId] call CBA_fnc_removePerFrameHandler;
    };
    if (isNil "COMSPEC_RoleplayZones") exitWith {};
    private _idx = COMSPEC_RoleplayZones findIf {
        (_x getOrDefault ["id", ""]) isEqualTo _zoneId
    };
    if (_idx < 0) exitWith { [_pfhId] call CBA_fnc_removePerFrameHandler; };
    private _zone = COMSPEC_RoleplayZones select _idx;
    private _newPos = getPosATL _anchor;
    _zone set ["position", _newPos];
    private _marker = _zone getOrDefault ["marker", ""];
    if (_marker isNotEqualTo "") then { _marker setMarkerPos _newPos; };
    publicVariable "COMSPEC_RoleplayZones";
}, 2, [_anchor, _zoneId]] call CBA_fnc_addPerFrameHandler;

_zone
