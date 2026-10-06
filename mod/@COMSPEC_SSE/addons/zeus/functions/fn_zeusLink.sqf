/*
    Lie deux entités dans le graphe SSE depuis Zeus.
    Le graphe n'est diffusé que par le serveur (linkEntities → publicVariable
    si isServer) : depuis un client curateur, la création passe par le serveur.
    [_source, _target, _relation, _confidence, _origin] call comspec_sse_fnc_zeusLink
*/
params [
    ["_source", objNull, [objNull]],
    ["_target", objNull, [objNull]],
    ["_relation", "ASSOCIATE", [""]],
    ["_confidence", 0.7, [0]],
    ["_origin", "ZEUS", [""]]
];
if (isNull _source || {isNull _target} || {_source isEqualTo _target}) exitWith { false };
if (isServer) then {
    [_source, _target, _relation, _confidence, _origin] call comspec_sse_fnc_linkEntities;
} else {
    [_source, _target, _relation, _confidence, _origin] remoteExecCall ["comspec_sse_fnc_linkEntities", 2];
};
true
