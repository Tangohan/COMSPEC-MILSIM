/*
    Moteur de compositions SSE (serveur) : pose un site préfabriqué et le rend exploitable.
    [_type, _posATL, _dir, _options] call comspec_sse_fnc_spawnComposition -> HashMap du site (vide si échec)

    _type    : voir comspec_sse_fnc_compositionData (SAFEHOUSE, IED_LAB, FORGERY, WEAPONS_CACHE, CELL_HQ,
               DEAD_DROP, HAWALA)
    _options : HashMap ou paires [clé, valeur] (clés en minuscules)
      occupants   nombre d'occupants, -1 = valeur du site (0 à 8)
      side        "EAST" | "GUER" | "CIV"            armed : occupants armés (défaut true, jamais pour CIV)
      complexity  richesse ("" = celle du site)      traps : pose les pièges du site (défaut false)
      building    bâtiment imposé (objet)            usebuilding : chercher un bâtiment proche (défaut : selon le site)
      buildingradius rayon de recherche du bâtiment (défaut 40 m)
      region      région narrative                   related : [[grid, libellé], ...] autres sites cités par le dossier
      cluegrid / clueplace : lieu cité par le thème (site suivant d'un réseau)
      networkid   identifiant de réseau multi-sites

    Tout est créé sur le serveur : fiches écrites par le greffier, liens tenus par le serveur,
    menus et terminaux des clients prévenus par comspec_sse_recordChanged (JIP compris).
    Mobilier : dans un bâtiment, chaque ancre prend une position du bâtiment (buildingPos) ; au sol,
    décalage relatif tourné selon _dir. Les objets posés « sur » une ancre trouvent sa surface par lancer
    de rayon : aucune hauteur de table écrite en dur.
*/
params [
    ["_type", "", [""]],
    ["_posATL", [0, 0, 0], [[]]],
    ["_dir", 0, [0]],
    ["_options", [], [[], createHashMap]]
];

if (!isServer) exitWith {
    ["spawnComposition : serveur uniquement (passer par comspec_sse_fnc_siteRequest)", "WARNING"] call comspec_sse_fnc_log;
    createHashMap
};

_type = toUpper _type;
private _def = [_type] call comspec_sse_fnc_compositionData;
if (count _def == 0) exitWith {
    [format ["spawnComposition : type inconnu %1", _type], "ERROR"] call comspec_sse_fnc_log;
    createHashMap
};

private _opt = if (_options isEqualType createHashMap) then { _options } else {
    private _h = createHashMap;
    { _x params ["_k", "_v"]; _h set [toLower _k, _v]; } forEach _options;
    _h
};

_posATL = [_posATL param [0, 0], _posATL param [1, 0], 0];
private _seed = [[] call comspec_sse_fnc_missionSeed, format ["comp_%1_%2_%3", _type, round (_posATL select 0), round (_posATL select 1)]] call comspec_sse_fnc_hash;
private _siteId = format ["SITE-%1", [_seed, "site", 6] call comspec_sse_fnc_idToken];

// ------------------------------------------------------------------ bâtiment
private _building = _opt getOrDefault ["building", objNull];
if (_building isEqualType objNull && {isNull _building} && {_opt getOrDefault ["usebuilding", _def get "building"]}) then {
    private _near = (nearestObjects [_posATL, ["House"], _opt getOrDefault ["buildingradius", 40]]) select {
        alive _x && {count (_x buildingPos -1) >= 2}
    };
    _building = _near param [0, objNull];
};
if !(_building isEqualType objNull) then { _building = objNull; };
private _bpos = if (isNull _building) then { [] } else { _building buildingPos -1 };
if (count _bpos < 2) then { _building = objNull; _bpos = []; };
private _center = _posATL;
if (!isNull _building) then {
    _center = getPosATL _building;
    _center set [2, 0];
    _dir = getDir _building;
    // Ordre des pièces tiré de la graine : deux sites du même type ne se ressemblent pas.
    private _keyed = _bpos apply { [[_seed, str _x] call comspec_sse_fnc_hash, _x] };
    _keyed sort true;
    _bpos = _keyed apply { _x select 1 };
};

// Décalage local [x, y] (x à droite, y devant) tourné selon _dir, position ATL au sol.
private _rot = {
    params ["_off", "_heading"];
    _off params ["_ox", "_oy"];
    [
        (_center select 0) + (_ox * cos _heading) + (_oy * sin _heading),
        (_center select 1) - (_ox * sin _heading) + (_oy * cos _heading),
        0
    ]
};

private _objects = [];
private _create = {
    params ["_cls", "_pATL", "_d", ["_sim", false]];
    if !(isClass (configFile >> "CfgVehicles" >> _cls)) exitWith {
        [format ["spawnComposition %1 : classe absente %2", _type, _cls], "WARNING"] call comspec_sse_fnc_log;
        objNull
    };
    private _o = createVehicle [_cls, [0, 0, 0], [], 0, "CAN_COLLIDE"];
    // Lu par l'init des pièces posables : c'est le moteur qui génère leur fiche, avec le cluster du site.
    _o setVariable ["comspec_sse_siteManaged", true];
    _o setVariable ["comspec_sse_siteId", _siteId, true];
    _o setDir _d;
    _o setPosATL _pATL;
    if (!_sim) then { _o enableSimulationGlobal false; };
    _objects pushBack _o;
    _o
};

// Pose relative à une ancre : surface trouvée par lancer de rayon (dessus de l'ancre, ou sol à côté).
private _placeOn = {
    params ["_anchor", "_cls", "_off", "_rdir", "_mode", ["_sim", false]];
    if (isNull _anchor) exitWith { objNull };
    private _w = _anchor modelToWorldWorld [_off select 0, _off select 1, 0];
    private _base = (getPosASL _anchor) select 2;
    private _ignore = [objNull, _anchor] select (_mode isEqualTo "floor");
    private _hits = lineIntersectsSurfaces [
        [_w select 0, _w select 1, _base + 2.2],
        [_w select 0, _w select 1, _base - 1.5],
        _ignore, objNull, true, 1, "GEOM", "NONE"
    ];
    private _z = if (_hits isEqualTo []) then { _base } else { ((_hits select 0) select 0) select 2 };
    private _o = [_cls, [0, 0, 0], (getDir _anchor) + _rdir, _sim] call _create;
    if (!isNull _o) then { _o setPosASL [_w select 0, _w select 1, _z + 0.01]; };
    _o
};

// ------------------------------------------------------------------ mobilier porteur
private _anchors = createHashMap;
private _anchorList = _def get "anchors";
{
    _x params ["_id", "_cls", "_off", "_adir"];
    private _p = [];
    if (_bpos isNotEqualTo []) then {
        _p = +(_bpos select (_forEachIndex mod (count _bpos)));
        // Plus d'ancres que de positions : la suivante se pose à côté.
        if (_forEachIndex >= count _bpos) then { _p = _p vectorAdd [1.2, 0.8, 0]; };
    } else {
        _p = [_off, _dir] call _rot;
    };
    private _o = [_cls, _p, _dir + _adir] call _create;
    if (!isNull _o) then { _anchors set [_id, _o]; };
} forEach _anchorList;

{
    _x params ["_aid", "_cls", "_off", "_rdir", "_mode"];
    [_anchors getOrDefault [_aid, objNull], _cls, _off, _rdir, _mode] call _placeOn;
} forEach (_def get "props");

// ------------------------------------------------------------------ pièces SSE
private _queueObjects = [];
private _evidence = [];
private _carrier = objNull;
{
    _x params ["_aid", "_cls", "_off", "_rdir", "_mode", "_rel"];
    private _o = [_anchors getOrDefault [_aid, objNull], _cls, _off, _rdir, _mode] call _placeOn;
    if (!isNull _o) then {
        private _kind = getText (configFile >> "CfgVehicles" >> _cls >> "comspec_sse_type");
        if (_kind isEqualTo "") then { _kind = [_o] call comspec_sse_fnc_resolveEntityType; };
        // Le dossier (documents qui citent les autres sites) va sur la première pièce papier « référence ».
        if (isNull _carrier && {_kind isEqualTo "DOCUMENT"} && {_rel isEqualTo "REFERENCES"}) then { _carrier = _o; };
        _queueObjects pushBack [_o, _kind, _rel];
        _evidence pushBack _o;
    };
} forEach (_def get "evidence");

{
    _x params ["_aid", "_cls", "_off", "_rdir"];
    private _o = [_anchors getOrDefault [_aid, objNull], _cls, _off, _rdir, "floor", true] call _placeOn;
    if (!isNull _o) then {
        _queueObjects pushBack [_o, "CONTAINER", "REFERENCES"];
        _evidence pushBack _o;
    };
} forEach (_def get "containers");
if (isNull _carrier) then { _carrier = _evidence param [0, objNull]; };

// ------------------------------------------------------------------ occupants
private _nOcc = _opt getOrDefault ["occupants", -1];
if (_nOcc < 0) then { _nOcc = _def get "occupants"; };
_nOcc = (_nOcc min 8) max 0;
private _sideKey = toUpper (_opt getOrDefault ["side", "GUER"]);
private _side = switch (_sideKey) do {
    case "EAST": { east };
    case "CIV": { civilian };
    default { independent };
};
private _classes = switch (_sideKey) do {
    case "EAST": { ["O_G_Soldier_F", "O_G_Soldier_lite_F"] };
    case "CIV": { ["C_man_1", "C_man_polo_1_F", "C_man_polo_2_F", "C_man_polo_4_F"] };
    default { ["I_G_Soldier_F", "I_G_Soldier_lite_F"] };
};
private _armed = (_opt getOrDefault ["armed", true]) && {_sideKey isNotEqualTo "CIV"};
private _profiles = _def get "profiles";
private _persons = [];
if (_nOcc > 0) then {
    private _grp = createGroup [_side, true];
    for "_i" from 0 to (_nOcc - 1) do {
        private _p = if (_bpos isNotEqualTo []) then {
            +(_bpos select ((count _anchorList + _i) mod (count _bpos)))
        } else {
            [[(_i - (_nOcc / 2)) * 1.6, -3], _dir] call _rot
        };
        private _cls = _classes select (([_seed, format ["occ%1", _i]] call comspec_sse_fnc_hash) mod (count _classes));
        private _u = _grp createUnit [_cls, _p, [], 0, "CAN_COLLIDE"];
        _u setPosATL _p;
        _u setDir (([_seed, format ["occdir%1", _i]] call comspec_sse_fnc_hash) mod 360);
        _u setVariable ["comspec_sse_siteManaged", true];
        _u setVariable ["comspec_sse_siteId", _siteId, true];
        _u setVariable ["comspec_sse_siteProfile", _profiles select (_i min ((count _profiles) - 1))];
        if (!_armed) then { removeAllWeapons _u; };
        // À l'intérieur, les occupants restent dans leurs pièces : la fouille trouve qui vit là.
        if (!isNull _building) then { _u disableAI "PATH"; };
        _persons pushBack _u;
        _objects pushBack _u;
    };
};

// ------------------------------------------------------------------ pièges
private _mines = [];
if (_opt getOrDefault ["traps", false]) then {
    {
        _x params ["_mineCls", "_where"];
        private _mp = [];
        if (_where isEqualTo "entry") then {
            if (!isNull _building) then {
                _mp = _building buildingExit 0;
                if (_mp isEqualTo [0, 0, 0]) then { _mp = +(_bpos select 0); };
            } else {
                _mp = [[0, -5], _dir] call _rot;
            };
        } else {
            private _a = _anchors getOrDefault [_where, objNull];
            if (!isNull _a) then { _mp = ASLToATL (_a modelToWorldWorld [0, -0.9, 0]); };
        };
        if (_mp isNotEqualTo []) then {
            private _m = createMine [_mineCls, _mp, [], 0];
            _m setPosATL _mp;
            _m setDir _dir;
            if (_persons isNotEqualTo []) then { (side group (_persons select 0)) revealMine _m; };
            _mines pushBack _m;
        };
    } forEach (_def get "traps");
};

// Tout est éditable par les Zeus, mines comprises.
{ _x addCuratorEditableObjects [_objects + _mines, true]; } forEach allCurators;

// ------------------------------------------------------------------ fiches SSE
private _profile = _def get "profile";
private _complexity = toUpper (_opt getOrDefault ["complexity", ""]);
if (_complexity isEqualTo "") then { _complexity = _def get "complexity"; };
private _region = _opt getOrDefault ["region", missionNamespace getVariable ["comspec_sse_region", "IRAQ"]];
private _cluster = [_seed, _profile, _complexity, _region] call comspec_sse_fnc_generateCluster;
// Thème imposé par le type de site : documents, SMS et fichiers racontent la même histoire.
_cluster set ["theme", _def get "theme"];
private _clue = _opt getOrDefault ["cluegrid", ""];
if (_clue isNotEqualTo "") then {
    _cluster set ["depotGrid", _clue];
    _cluster set ["depotPlace", _opt getOrDefault ["clueplace", ""]];
};
_cluster set ["siteId", _siteId];
_cluster set ["networkId", _opt getOrDefault ["networkid", ""]];

// Dossier d'un site isolé : deux lieux réels de la carte, cités comme autres sites de la cellule.
private _related = _opt getOrDefault ["related", []];
if (_related isEqualTo [] && {(_opt getOrDefault ["networkid", ""]) isEqualTo ""}) then {
    {
        ([_seed, format ["rel%1", _forEachIndex], _center, 6000] call comspec_sse_fnc_seededMapGrid) params ["_g", "", "_n"];
        _related pushBack [_g, [_x, format ["%1 (%2)", _x, _n]] select (_n isNotEqualTo "")];
    } forEach ["Point de rendez-vous", "Dépôt de matériel"];
};

private _site = createHashMapFromArray [
    ["id", _siteId],
    ["type", _type],
    ["label", _def get "label"],
    ["pos", _center],
    ["grid", mapGridPosition _center],
    ["dir", _dir],
    ["building", _building],
    ["objects", _objects],
    ["persons", _persons],
    ["evidence", _evidence],
    ["mines", _mines],
    ["chief", objNull],
    ["cluster", _cluster],
    ["seed", _seed],
    ["profile", _profile],
    ["complexity", _complexity],
    ["related", _related],
    ["dossierCarrier", _carrier]
];

private _queue = (_persons apply { [_x, "PERSON", "SUBORDINATE"] }) + _queueObjects;
private _readyIn = [_site, _queue] call comspec_sse_fnc_siteProcess;
_site set ["readyIn", _readyIn];

if (isNil "comspec_sse_siteRegistry") then { comspec_sse_siteRegistry = createHashMap; };
comspec_sse_siteRegistry set [_siteId, _site];

[format ["spawnComposition %1 %2 grid=%3 bâtiment=%4 objets=%5 occupants=%6 pièges=%7",
    _siteId, _type, _site get "grid", typeOf _building, count _objects, count _persons, count _mines]] call comspec_sse_fnc_log;
_site
