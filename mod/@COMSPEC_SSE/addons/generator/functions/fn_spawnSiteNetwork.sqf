/*
    Réseau multi-sites (serveur) : 3 à 6 sites reliés, posés près de lieux réels de la carte.
    [_centerATL, _count, _radius, _options] call comspec_sse_fnc_spawnSiteNetwork -> [site, ...]
      _options : mêmes clés que comspec_sse_fnc_spawnComposition (occupants, side, complexity, traps…)

    Chaîne d'enquête : chaque site cite le suivant (thème et dossier), le dernier est le PC de la
    cellule, dont la carte cite tous les autres. Les chefs sont reliés entre eux (CONTACT) et au chef
    du PC (SUBORDINATE) une fois toutes les fiches générées.
*/
params [
    ["_centerATL", [0, 0, 0], [[]]],
    ["_count", 4, [0]],
    ["_radius", 3000, [0]],
    ["_options", [], [[], createHashMap]]
];

if (!isServer) exitWith { [] };

private _opt = if (_options isEqualType createHashMap) then { +_options } else {
    private _h = createHashMap;
    { _x params ["_k", "_v"]; _h set [toLower _k, _v]; } forEach _options;
    _h
};
_count = (round _count max 3) min 6;
_radius = _radius max 500;

private _seed = [[] call comspec_sse_fnc_missionSeed, format ["net_%1_%2_%3", round (_centerATL select 0), round (_centerATL select 1), _count]] call comspec_sse_fnc_hash;
private _netId = format ["NET-%1", [_seed, "net", 6] call comspec_sse_fnc_idToken];

// Types : tirage déterministe parmi les sites périphériques, PC de cellule en bout de chaîne.
private _pool = ["SAFEHOUSE", "DEAD_DROP", "IED_LAB", "FORGERY", "WEAPONS_CACHE", "HAWALA"];
private _keyed = _pool apply { [[_seed, _x] call comspec_sse_fnc_hash, _x] };
_keyed sort true;
private _types = (_keyed apply { _x select 1 }) select [0, _count - 1];
_types pushBack "CELL_HQ";

// Emplacements : lieux réels (villes, villages, lieux-dits) à 400 m au moins les uns des autres,
// puis bâtiment habitable le plus proche pour les sites qui en veulent un.
private _plans = [];
{
    private _type = _x;
    private _i = _forEachIndex;
    private _pos = [];
    private _place = "";
    for "_j" from 0 to 7 do {
        ([_seed, format ["net%1_%2", _i, _j], _centerATL, _radius] call comspec_sse_fnc_seededMapGrid) params ["", "_p", "_n"];
        if (_j == 7 || {(_plans findIf { ((_x select 1) distance2D _p) < 400 }) < 0}) exitWith {
            _pos = _p;
            _place = _n;
        };
    };
    private _building = objNull;
    if (([_type] call comspec_sse_fnc_compositionData) get "building") then {
        private _near = (nearestObjects [_pos, ["House"], 150]) select { alive _x && {count (_x buildingPos -1) >= 2} };
        _building = _near param [0, objNull];
        if (!isNull _building) then { _pos = getPosATL _building; _pos set [2, 0]; };
    };
    private _label = format ["%1 — %2", ([_type] call comspec_sse_fnc_compositionData) get "label", [_place, "?"] select (_place isEqualTo "")];
    _plans pushBack [_type, _pos, _building, _label, mapGridPosition _pos, _place];
} forEach _types;

private _sites = [];
private _last = (count _plans) - 1;
{
    _x params ["_type", "_pos", "_building", "_label", "_grid", "_place"];
    private _i = _forEachIndex;
    private _next = _plans select ((_i + 1) mod (count _plans));
    private _prev = _plans select ((_i - 1 + (count _plans)) mod (count _plans));
    // Le PC cite tous les autres sites ; les autres citent le suivant et le précédent.
    private _related = if (_i == _last) then {
        (_plans select [0, _last]) apply { [_x select 4, _x select 3] }
    } else {
        [[_next select 4, _next select 3], [_prev select 4, _prev select 3]]
    };
    if (_i == 0) then { _related deleteAt 1; };

    private _o = +_opt;
    _o set ["building", _building];
    _o set ["usebuilding", !isNull _building];
    _o set ["related", _related];
    _o set ["cluegrid", _next select 4];
    _o set ["clueplace", _next select 5];
    _o set ["networkid", _netId];
    private _dir = if (isNull _building) then { ([_seed, format ["dir%1", _i]] call comspec_sse_fnc_hash) mod 360 } else { getDir _building };
    private _site = [_type, _pos, _dir, _o] call comspec_sse_fnc_spawnComposition;
    if (count _site > 0) then {
        _site set ["networkId", _netId];
        _sites pushBack _site;
    };
} forEach _plans;

// Liens entre sites, une fois toutes les fiches écrites (sinon les UID ne sont pas encore connus).
private _wait = 2;
{ _wait = _wait max ((_x getOrDefault ["readyIn", 0]) + 1.5); } forEach _sites;
[{
    params ["_sites"];
    if (_sites isEqualTo []) exitWith {};
    // Tête de chaque site : son chef, ou sa première pièce s'il n'a pas d'occupant.
    private _heads = _sites apply {
        private _c = _x getOrDefault ["chief", objNull];
        if (isNull _c) then { _x getOrDefault ["hub", objNull] } else { _c }
    };
    private _hq = _heads param [(count _heads) - 1, objNull];
    {
        private _h = _x;
        private _n = _heads select ((_forEachIndex + 1) mod (count _heads));
        if (!isNull _h && {!isNull _n} && {_h isNotEqualTo _n}) then {
            [_h, _n, ["REFERENCES", "CONTACT"] select (_h isKindOf "CAManBase" && {_n isKindOf "CAManBase"}), 0.7, "NETWORK"] call comspec_sse_fnc_linkEntities;
        };
        if (!isNull _h && {!isNull _hq} && {_h isNotEqualTo _hq} && {_h isKindOf "CAManBase"} && {_hq isKindOf "CAManBase"}) then {
            [_h, _hq, "SUBORDINATE", 0.65, "NETWORK"] call comspec_sse_fnc_linkEntities;
        };
    } forEach _heads;
    [format ["réseau %1 : %2 site(s) reliés", (_sites select 0) getOrDefault ["networkId", "?"], count _sites]] call comspec_sse_fnc_log;
}, [_sites], _wait] call CBA_fnc_waitAndExecute;

[format ["spawnSiteNetwork %1 : %2", _netId, _plans apply { format ["%1@%2", _x select 0, _x select 4] }]] call comspec_sse_fnc_log;
_sites
