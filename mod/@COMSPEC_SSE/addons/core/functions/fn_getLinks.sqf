/*
    Retourne les liens du graphe pour une entité (ou tous si nil).
    [_entity] call comspec_sse_fnc_getLinks
*/
params [
    ["_entity", objNull, [objNull]]
];

if (isNil "comspec_sse_linkGraph") exitWith { [] };

if (isNull _entity) exitWith { +comspec_sse_linkGraph };

private _data = [_entity] call comspec_sse_fnc_getData;
private _uid = if (isNil "_data") then { "" } else { [_data, "uid", ""] call BIS_fnc_getFromPairs };
private _nid = netId _entity;

comspec_sse_linkGraph select {
    private _s = _x getOrDefault ["source", ""];
    private _t = _x getOrDefault ["target", ""];
    private _sn = _x getOrDefault ["sourceNetId", ""];
    private _tn = _x getOrDefault ["targetNetId", ""];
    (_uid != "" && {_s == _uid || {_t == _uid}}) || {_sn == _nid || {_tn == _nid}}
}
