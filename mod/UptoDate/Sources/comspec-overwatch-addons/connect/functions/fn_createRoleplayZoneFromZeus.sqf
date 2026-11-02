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

// Un seul PFH (10 s) pour toutes les zones ancrées, une seule diffusion de la liste par passage (avant : un PFH de 2 s
// par zone, et un publicVariable de toute la liste par zone déplacée).
private _anchors = missionNamespace getVariable ["COMSPEC_RoleplayAnchors", []];
_anchors pushBack [_anchor, _zoneId];
missionNamespace setVariable ["COMSPEC_RoleplayAnchors", _anchors];
if (isNil "COMSPEC_RoleplayAnchorPfh") then {
    COMSPEC_RoleplayAnchorPfh = [{
        private _keep = [];
        private _moved = false;
        {
            _x params ["_anchor", "_zoneId"];
            if (isNull _anchor || {!alive _anchor}) then { [_zoneId] call comspec_overwatch_connect_fnc_deleteRoleplayZone; continue };
            if (isNil "COMSPEC_RoleplayZones") then { _keep pushBack _x; continue };
            private _idx = COMSPEC_RoleplayZones findIf { (_x getOrDefault ["id", ""]) isEqualTo _zoneId };
            if (_idx < 0) then { continue };
            _keep pushBack _x;
            private _zone = COMSPEC_RoleplayZones select _idx;
            private _newPos = getPosATL _anchor;
            // Ancre immobile : rien à diffuser.
            private _oldPos = _zone getOrDefault ["position", []];
            if (_oldPos isEqualType [] && {count _oldPos >= 2} && {(_oldPos distance2D _newPos) <= (((_zone getOrDefault ["radius", 100]) * 0.1) max 10)}) then { continue };
            _zone set ["position", _newPos];
            private _marker = _zone getOrDefault ["marker", ""];
            if (_marker isNotEqualTo "") then { _marker setMarkerPos _newPos; };
            _moved = true;
        } forEach (missionNamespace getVariable ["COMSPEC_RoleplayAnchors", []]);
        missionNamespace setVariable ["COMSPEC_RoleplayAnchors", _keep];
        if (_moved) then { publicVariable "COMSPEC_RoleplayZones"; };
    }, 10] call CBA_fnc_addPerFrameHandler;
};

_zone
