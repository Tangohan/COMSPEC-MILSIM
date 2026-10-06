/*
    Retire les données SSE d'entités (menus ACE laissés en place mais inactifs :
    les actions vérifient la présence de données).
    [_entities] call comspec_sse_fnc_zeusClearEntities → nombre d'entités nettoyées
*/
params [["_entities", [], [[]]]];
private _n = 0;
{
    if (!isNull _x && {!isNil {_x getVariable "comspec_sse_data"}}) then {
        _x setVariable ["comspec_sse_data", nil, true];
        _x setVariable ["comspec_sse_enabled", false, true];
        _x setVariable ["comspec_sse_searchable", false, true];
        _x setVariable ["comspec_sse_clusterId", nil, true];
        _x setVariable ["comspec_sse_forcedType", nil, true];
        _n = _n + 1;
    };
} forEach _entities;
_n
