/*
    Sème des pièces de preuve dans un rayon (serveur) : sur les sols des bâtiments proches
    (buildingPos), à défaut au sol. Les pièces partagent un cluster ; les personnes non joueurs
    du rayon (6 au plus) entrent dans le réseau : la plus proche du centre en est le chef, une fiche
    déjà générée est gardée telle quelle.
    [_centerATL, _radius, _count, _options] call comspec_sse_fnc_seedEvidence -> HashMap du semis
      _options : profile, complexity, region (clés en minuscules)
*/
params [
    ["_centerATL", [0, 0, 0], [[]]],
    ["_radius", 30, [0]],
    ["_count", 5, [0]],
    ["_options", [], [[], createHashMap]]
];

if (!isServer) exitWith { createHashMap };

private _opt = if (_options isEqualType createHashMap) then { _options } else {
    private _h = createHashMap;
    { _x params ["_k", "_v"]; _h set [toLower _k, _v]; } forEach _options;
    _h
};
_radius = (_radius max 5) min 300;
_count = (round _count max 1) min 20;

private _seed = [[] call comspec_sse_fnc_missionSeed, format ["seed_%1_%2_%3", round (_centerATL select 0), round (_centerATL select 1), _radius]] call comspec_sse_fnc_hash;
private _siteId = format ["SEED-%1", [_seed, "seed", 6] call comspec_sse_fnc_idToken];

private _pool = [
    "COMSPEC_SSE_Obj_Phone", "COMSPEC_SSE_Obj_Smartphone", "COMSPEC_SSE_Obj_Documents", "COMSPEC_SSE_Obj_Notebook",
    "COMSPEC_SSE_Obj_USB", "COMSPEC_SSE_Obj_SDCard", "COMSPEC_SSE_Obj_Photos", "COMSPEC_SSE_Obj_Laptop",
    "COMSPEC_SSE_Obj_HardDrive", "COMSPEC_SSE_Obj_Cash", "COMSPEC_SSE_Obj_Map", "COMSPEC_SSE_Obj_Radio"
];

// Emplacements : positions des bâtiments du rayon, dans un ordre tiré de la graine.
// Borné : on choisit d'abord quelques bâtiments (ordre de graine), puis leurs positions,
// pour ne pas hacher des milliers de positions en ville (rayon 300 m, serveur non planifié).
// Bâtiments habitables seulement (cabanes, conteneurs, ruines : aucune position intérieure).
private _houses = (nearestObjects [_centerATL, ["House"], _radius]) select { alive _x && {(_x buildingPos 0) isNotEqualTo [0, 0, 0]} };
// Clé [hash, index] : sort n'accepte pas d'objet dans les sous-tableaux (égalité de hash).
private _hKeyed = [];
{
    private _hp = getPosATL _x;
    _hKeyed pushBack [[_seed, format ["b%1_%2", round (_hp select 0), round (_hp select 1)]] call comspec_sse_fnc_hash, _forEachIndex];
} forEach _houses;
_hKeyed sort true;
private _spots = [];
{
    _spots append ((_houses select (_x select 1)) buildingPos -1);
    if (count _spots >= _count * 4) exitWith {};
} forEach _hKeyed;
private _keyed = _spots apply { [[_seed, str _x] call comspec_sse_fnc_hash, _x] };
_keyed sort true;
_spots = _keyed apply { _x select 1 };

private _objects = [];
for "_i" from 0 to (_count - 1) do {
    private _p = if (_i < count _spots) then { _spots select _i } else {
        private _a = ([_seed, format ["ang%1", _i]] call comspec_sse_fnc_hash) mod 360;
        private _d = ([_seed, format ["dst%1", _i]] call comspec_sse_fnc_hash) mod (round _radius);
        [(_centerATL select 0) + (_d * sin _a), (_centerATL select 1) + (_d * cos _a), 0]
    };
    private _asl = AGLToASL _p;
    // Sol (plancher ou terrain) sous la position : lancer de rayon court.
    private _hits = lineIntersectsSurfaces [_asl vectorAdd [0, 0, 1.2], _asl vectorAdd [0, 0, -1.5], objNull, objNull, true, 1, "GEOM", "NONE"];
    if (_hits isNotEqualTo []) then { _asl = (_hits select 0) select 0; };
    private _cls = [_seed, format ["cls%1", _i], _pool] call comspec_sse_fnc_pickFromSeed;
    private _o = createVehicle [_cls, [0, 0, 0], [], 0, "CAN_COLLIDE"];
    _o setVariable ["comspec_sse_siteManaged", true];
    _o setVariable ["comspec_sse_siteId", _siteId, true];
    _o setDir (([_seed, format ["dir%1", _i]] call comspec_sse_fnc_hash) mod 360);
    _o setPosASL (_asl vectorAdd [0, 0, 0.01]);
    _o enableSimulationGlobal false;
    _objects pushBack _o;
};
{ _x addCuratorEditableObjects [_objects, true]; } forEach allCurators;

private _men = ((nearestObjects [_centerATL, ["CAManBase"], _radius]) select { alive _x && {!isPlayer _x} && {!(_x in playableUnits)} }) select [0, 6];
private _profile = _opt getOrDefault ["profile", "INSURGENT"];
private _complexity = _opt getOrDefault ["complexity", "STANDARD"];
private _cluster = [_seed, _profile, _complexity, _opt getOrDefault ["region", missionNamespace getVariable ["comspec_sse_region", "IRAQ"]]] call comspec_sse_fnc_generateCluster;

private _site = createHashMapFromArray [
    ["id", _siteId],
    ["type", "SEED"],
    ["pos", _centerATL],
    ["grid", mapGridPosition _centerATL],
    ["objects", _objects],
    ["persons", _men],
    ["chief", objNull],
    ["cluster", _cluster],
    ["seed", _seed],
    ["profile", _profile],
    ["complexity", _complexity],
    ["related", []]
];
private _queue = (_men apply { [_x, "PERSON", "SUBORDINATE"] }) + (_objects apply {
    [_x, getText (configOf _x >> "comspec_sse_type"), "OWNER"]
});
_site set ["readyIn", [_site, _queue] call comspec_sse_fnc_siteProcess];

[format ["seedEvidence %1 : %2 pièce(s), %3 personne(s), grid %4", _siteId, count _objects, count _men, mapGridPosition _centerATL]] call comspec_sse_fnc_log;
_site
