/*
    Pose un marqueur joueur sur le canal courant, comme la carte Arma : il est partagé avec le canal
    et Overwatch connect l'envoie à Athena (EH MarkerCreated).
*/
params ["_pos", ["_kind", ""]];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
if (_kind isEqualTo "") then { _kind = _s getOrDefault ["markerKind", "ENI"]; };
private _def = createHashMapFromArray [
    ["ENI", ["o_inf", "ColorEAST", "ENI"]],
    ["AMI", ["b_inf", "ColorWEST", "AMI"]],
    ["OBJ", ["mil_objective", "ColorOrange", "OBJ"]],
    ["DNG", ["mil_warning", "ColorRed", "DANGER"]],
    ["PT", ["mil_dot", "ColorGreen", "PT"]]
];
(_def getOrDefault [_kind, _def get "PT"]) params ["_type", "_color", "_prefix"];
private _index = (missionNamespace getVariable ["COMSPEC_ATAK_MarkerIndex", 0]) + 1;
missionNamespace setVariable ["COMSPEC_ATAK_MarkerIndex", _index];
private _channel = currentChannel;
// Même schéma de nom que les marqueurs posés à la main : visible sur le canal, supprimable par le joueur.
private _name = format ["_USER_DEFINED #%1/%2/%3", clientOwner, 9000 + _index, _channel];
private _m = createMarker [_name, _pos, _channel, player];
if (_m isEqualTo "") exitWith {
    ["WARNING", "Marqueur refusé sur ce canal", 3, 20] call comspec_atak_native_fnc_notify;
    ""
};
_m setMarkerTypeLocal _type;
_m setMarkerColorLocal _color;
_m setMarkerText format ["%1 %2", _prefix, _index];
["SUCCESS", format ["%1 %2 · %3", _prefix, _index, mapGridPosition _pos], 3, 20] call comspec_atak_native_fnc_notify;
_m
