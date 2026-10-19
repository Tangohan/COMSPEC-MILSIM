/*
    File SSE d'un site (serveur) : fiches générées avec un cluster partagé, puis liens.
    [_site, _queue] call comspec_sse_fnc_siteProcess -> secondes avant la fin de la file
      _site  : HashMap (cluster, profile, complexity, related, dossierCarrier, chief…) ; complété en place
      _queue : [[objet, "PERSON" | type forcé, relation vers le chef], ...] ; occupants en tête
    Le premier PERSON traité prend l'identité du chef (primary* du cluster) : les suivants lui sont
    subordonnés, les pièces lui sont reliées (OWNER, REFERENCES…). Sans occupant, les pièces sont
    reliées à la première d'entre elles. Étalé (une entité / 0,2 s) : generateData est lourd.
*/
params [
    ["_site", createHashMap, [createHashMap]],
    ["_queue", [], [[]]]
];

if (!isServer) exitWith { 0 };

private _job = {
    params ["_o", "_kind", "_rel", "_site"];
    if (isNull _o) exitWith {};
    private _cluster = _site get "cluster";
    private _profile = _site getOrDefault ["profile", "INSURGENT"];
    private _complexity = _site getOrDefault ["complexity", "STANDARD"];

    // Fiche déjà générée (personne présente avant un semis de preuves) : on la garde.
    private _existing = [_o] call comspec_sse_fnc_getData;
    private _done = !isNil "_existing" && {[_existing, "generated", false] call comspec_sse_fnc_getPair};
    if (!_done) then {
        if (_kind isEqualTo "PERSON") then {
            _profile = _o getVariable ["comspec_sse_siteProfile", _profile];
        } else {
            _o setVariable ["comspec_sse_forcedType", _kind, true];
        };
        [_o, _profile, _complexity, "SITE", _cluster] call comspec_sse_fnc_generateData;
    };

    private _chief = _site getOrDefault ["chief", objNull];
    if (_kind isEqualTo "PERSON") then {
        if (isNull _chief) then {
            _site set ["chief", _o];
        } else {
            [_o, _chief, "SUBORDINATE", 0.8, "SITE"] call comspec_sse_fnc_linkEntities;
        };
    } else {
        if (!isNull _chief) then {
            [_o, _chief, _rel, 0.75, "SITE"] call comspec_sse_fnc_linkEntities;
        } else {
            private _hub = _site getOrDefault ["hub", objNull];
            if (isNull _hub) then {
                _site set ["hub", _o];
            } else {
                [_o, _hub, "ASSOCIATE", 0.6, "SITE"] call comspec_sse_fnc_linkEntities;
            };
        };
        if (_o isEqualTo (_site getOrDefault ["dossierCarrier", objNull])) then {
            [_o, _site] call comspec_sse_fnc_siteDossier;
        };
    };
};

private _step = 0.2;
{
    _x params ["_o", "_kind", ["_rel", "ASSOCIATE"]];
    [{
        params ["_o", "_kind", "_rel", "_site", "_fn"];
        [_o, _kind, _rel, _site] call _fn;
    }, [_o, _kind, _rel, _site, _job], 0.1 + (_forEachIndex * _step)] call CBA_fnc_waitAndExecute;
} forEach _queue;

private _readyIn = 0.6 + ((count _queue) * _step);
[{
    params ["_site"];
    _site set ["ready", true];
    [format ["site %1 prêt : chef %2", _site getOrDefault ["id", "?"], _site getOrDefault ["chief", objNull]]] call comspec_sse_fnc_log;
}, [_site], _readyIn] call CBA_fnc_waitAndExecute;
_readyIn
