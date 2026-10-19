/*
    Lie deux entités dans le graphe SSE.
    [_source, _target, "OWNER", _confidence, _origin] call comspec_sse_fnc_linkEntities
    Le graphe est tenu par le serveur : appelé sur un client (Zeus, site généré localement),
    le lien est transmis au serveur, qui l'inscrit et le diffuse.
*/
params [
    ["_source", objNull, [objNull]],
    ["_target", objNull, [objNull]],
    ["_relationType", "ASSOCIATE", [""]],
    ["_confidence", 0.8, [0]],
    ["_origin", "SCRIPT", [""]]
];

if (isNull _source || {isNull _target}) exitWith { false };

if (!isServer) exitWith {
    ["comspec_sse_link", [_source, _target, _relationType, _confidence, _origin]] call CBA_fnc_serverEvent;
    true
};

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
    ["createdAt", serverTime]
];

if (isNil "comspec_sse_linkGraph") then {
    comspec_sse_linkGraph = [];
};
// Lien déjà connu : rien à refaire (un site généré reliait et rediffusait le même graphe des dizaines de fois).
if (isNil "comspec_sse_linkKeys") then { comspec_sse_linkKeys = createHashMap; };
private _linkKey = format ["%1|%2|%3", _srcUid, _tgtUid, toUpper _relationType];
if (_linkKey in comspec_sse_linkKeys) exitWith { true };
comspec_sse_linkKeys set [_linkKey, true];
comspec_sse_linkGraph pushBack _link;
if ((count comspec_sse_linkGraph) > 600) then {
    comspec_sse_linkGraph deleteRange [0, (count comspec_sse_linkGraph) - 600];
    // Clés réalignées sur le graphe tronqué : un lien éjecté peut être recréé.
    comspec_sse_linkKeys = createHashMap;
    { comspec_sse_linkKeys set [format ["%1|%2|%3", _x get "source", _x get "target", _x get "relationType"], true]; } forEach comspec_sse_linkGraph;
};
// Une seule diffusion du graphe toutes les 2 s au plus, au lieu d'une par lien.
if (isNil "comspec_sse_linkPvPending") then {
    comspec_sse_linkPvPending = true;
    [{ comspec_sse_linkPvPending = nil; publicVariable "comspec_sse_linkGraph"; }, [], 2] call CBA_fnc_waitAndExecute;
};

["append", _source, ["associations", [_link]]] call comspec_sse_fnc_recordOp;

[format ["link %1 -[%2]-> %3", _srcUid, _relationType, _tgtUid]] call comspec_sse_fnc_log;
true
