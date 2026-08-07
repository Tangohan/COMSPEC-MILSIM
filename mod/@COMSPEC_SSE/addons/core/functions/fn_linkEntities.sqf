/*
    Lie deux entités dans le graphe SSE.
    [_source, _target, "OWNER", _confidence, _origin] call comspec_sse_fnc_linkEntities
*/
params [
    ["_source", objNull, [objNull]],
    ["_target", objNull, [objNull]],
    ["_relationType", "ASSOCIATE", [""]],
    ["_confidence", 0.8, [0]],
    ["_origin", "SCRIPT", [""]]
];

if (isNull _source || {isNull _target}) exitWith { false };

private _srcData = [_source] call comspec_sse_fnc_getData;
private _tgtData = [_target] call comspec_sse_fnc_getData;
private _srcUid = if (isNil "_srcData") then { str _source } else { [_srcData, "uid", str _source] call BIS_fnc_getFromPairs };
private _tgtUid = if (isNil "_tgtData") then { str _target } else { [_tgtData, "uid", str _target] call BIS_fnc_getFromPairs };

private _link = createHashMapFromArray [
    ["source", _srcUid],
    ["target", _tgtUid],
    ["sourceNetId", netId _source],
    ["targetNetId", netId _target],
    ["relationType", toUpper _relationType],
    ["confidence", _confidence],
    ["origin", _origin],
    ["createdAt", time]
];

if (isNil "comspec_sse_linkGraph") then {
    comspec_sse_linkGraph = [];
};
comspec_sse_linkGraph pushBack _link;
if (isServer) then {
    publicVariable "comspec_sse_linkGraph";
};

private _assoc = [_source, "associations"] call comspec_sse_fnc_getSection;
if (isNil "_assoc" || {!(_assoc isEqualType [])}) then { _assoc = []; };
_assoc pushBack _link;
[_source, "associations", _assoc, true] call comspec_sse_fnc_setSection;

[format ["link %1 -[%2]-> %3", _srcUid, _relationType, _tgtUid]] call comspec_sse_fnc_log;
true
