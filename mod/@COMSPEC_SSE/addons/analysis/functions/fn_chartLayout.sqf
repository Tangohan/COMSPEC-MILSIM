/*
    Place les nœuds du tableau des liens (coordonnées de la carte de fond).

    « graph » : disposition rayonnante, chaque organisation au centre de ses
                cellules et membres, chaque objet autour de son détenteur ;
                les nœuds isolés sont rangés sur une ligne en bas.
    « orbat » : organigramme, organisation en haut, cellules en dessous,
                membres en colonne sous leur cellule, du chef au sympathisant.

    [_nodes, _edges, _mode] call comspec_sse_fnc_chartLayout → [centre, étendue en mètres]
*/
params ["_nodes", "_edges", ["_mode", "graph", [""]]];

// Parent d'arbre : appartenance pour les personnes et organisations,
// premier voisin personne ou organisation pour le reste.
{ _y set ["tp", _y get "parent"]; } forEach _nodes;
{
    _x params ["_a", "_b"];
    private _na = _nodes getOrDefault [_a, createHashMap];
    private _nb = _nodes getOrDefault [_b, createHashMap];
    if (count _na > 0 && {count _nb > 0}) then {
        {
            _x params ["_leaf", "_anchor"];
            if (((_leaf get "tp") isEqualTo "") && {!((_leaf get "kind") in ["org", "person"])} && {(_anchor get "kind") in ["org", "person"]}) then {
                _leaf set ["tp", _anchor get "id"];
            };
        } forEach [[_na, _nb], [_nb, _na]];
    };
} forEach _edges;
// Objets reliés seulement à d'autres objets : rattachés au premier déjà ancré.
{
    _x params ["_a", "_b"];
    private _na = _nodes getOrDefault [_a, createHashMap];
    private _nb = _nodes getOrDefault [_b, createHashMap];
    if (count _na > 0 && {count _nb > 0}) then {
        if ((_na get "tp") isEqualTo "" && {(_nb get "tp") isNotEqualTo ""} && {!((_na get "kind") in ["org", "person"])}) then { _na set ["tp", _b]; };
        if ((_nb get "tp") isEqualTo "" && {(_na get "tp") isNotEqualTo ""} && {!((_nb get "kind") in ["org", "person"])}) then { _nb set ["tp", _a]; };
    };
} forEach _edges;

private _children = createHashMap;
private _roots = [];
{
    private _tp = _y get "tp";
    if (_tp isEqualTo "" || {!(_tp in _nodes)}) then { _roots pushBack _x; } else {
        private _l = _children getOrDefault [_tp, []];
        _l pushBack _x;
        _children set [_tp, _l];
    };
} forEach _nodes;

private _rank = {
    params ["_id"];
    private _n = _nodes get _id;
    if ((_n get "kind") isEqualTo "org") exitWith { -1 };
    private _key = _id select [2];
    private _m = (missionNamespace getVariable ["comspec_sse_orgMembers", createHashMap]) getOrDefault [_key, createHashMap];
    ["roleRank", _m getOrDefault ["role", "membre"]] call comspec_sse_fnc_orgTables
};
private _sortKids = {
    params ["_list"];
    private _k = _list apply { [[_x] call _rank, (_nodes get _x) get "label", _x] };
    _k sort true;
    _k apply { _x select 2 }
};
private _count = {
    params ["_id"];
    private _c = 1;
    { _c = _c + ([_x] call _count); } forEach (_children getOrDefault [_id, []]);
    _c
};

if (_mode isEqualTo "orbat") then {
    private _unit = 300;
    private _width = {
        params ["_id"];
        private _kids = _children getOrDefault [_id, []];
        private _orgKids = _kids select { ((_nodes get _x) get "kind") isEqualTo "org" };
        private _w = 0;
        { _w = _w + ([_x] call _width); } forEach _orgKids;
        if ((count _kids) > (count _orgKids)) then { _w = _w + 1; };
        _w max 1
    };
    private _placeT = {
        params ["_id", "_x0", "_y"];
        private _w = [_id] call _width;
        (_nodes get _id) set ["pos", [(_x0 + _w / 2) * _unit, _y, 0]];
        private _kids = [_children getOrDefault [_id, []]] call _sortKids;
        private _orgKids = _kids select { ((_nodes get _x) get "kind") isEqualTo "org" };
        private _people = _kids - _orgKids;
        private _cur = _x0;
        if (_people isNotEqualTo []) then {
            { (_nodes get _x) set ["pos", [(_cur + 0.5) * _unit, _y - 150 - _forEachIndex * 75, 0]]; } forEach _people;
            _cur = _cur + 1;
        };
        { [_x, _cur, _y - 320] call _placeT; _cur = _cur + ([_x] call _width); } forEach _orgKids;
    };
    private _x0 = 0;
    private _loose = [];
    {
        if (((_nodes get _x) get "kind") isEqualTo "org") then {
            [_x, _x0, 0] call _placeT;
            _x0 = _x0 + ([_x] call _width) + 0.5;
        } else { _loose pushBack _x; };
    } forEach ([_roots] call _sortKids);
    // Personnes sans organisation connue : ligne du bas.
    private _yMin = 0;
    { _yMin = _yMin min (((_y get "pos") select 1)); } forEach _nodes;
    { (_nodes get _x) set ["pos", [(_forEachIndex mod 8) * 200, _yMin - 300 - (floor (_forEachIndex / 8)) * 90, 0]]; } forEach _loose;
} else {
    private _place = {
        params ["_id", "_pos", "_dir", "_spread", "_depth"];
        (_nodes get _id) set ["pos", _pos];
        private _kids = [_children getOrDefault [_id, []]] call _sortKids;
        private _n = count _kids;
        if (_n isEqualTo 0) exitWith {};
        private _r = ([420, 260, 150, 90, 70] select (_depth min 4)) max (_n * 26);
        private _sub = ((_spread / _n) * 1.6) max 70 min 170;
        {
            private _a = if (_spread >= 360) then { _dir + 360 * _forEachIndex / _n } else { _dir - _spread / 2 + _spread * (_forEachIndex + 0.5) / _n };
            [_x, _pos vectorAdd [_r * (sin _a), _r * (cos _a), 0], _a, _sub, _depth + 1] call _place;
        } forEach _kids;
    };
    private _big = _roots select { (_children getOrDefault [_x, []]) isNotEqualTo [] };
    private _leaves = _roots - _big;
    private _bigSorted = _big apply { [[_x] call _count, _x] };
    _bigSorted sort false;
    private _cols = ceil (sqrt (count _bigSorted)) max 1;
    {
        _x params ["", "_id"];
        private _cx = (_forEachIndex mod _cols) * 1300;
        private _cy = -(floor (_forEachIndex / _cols)) * 1300;
        [_id, [_cx, _cy, 0], 0, 360, 0] call _place;
    } forEach _bigSorted;
    private _yMin = 0;
    { _yMin = _yMin min (((_y get "pos") select 1)); } forEach _nodes;
    { (_nodes get _x) set ["pos", [(_forEachIndex mod 8) * 200, _yMin - 350 - (floor (_forEachIndex / 8)) * 90, 0]]; } forEach ([_leaves] call _sortKids);
};

// Recentre dans la carte de fond, au milieu du monde.
private _minX = 1e9; private _maxX = -1e9; private _minY = 1e9; private _maxY = -1e9;
{
    (_y get "pos") params ["_px", "_py"];
    _minX = _minX min _px; _maxX = _maxX max _px; _minY = _minY min _py; _maxY = _maxY max _py;
} forEach _nodes;
if (_minX > _maxX) exitWith { [[worldSize / 2, worldSize / 2, 0], 600] };
private _c = [worldSize / 2, worldSize / 2, 0];
private _off = [(_c select 0) - (_minX + _maxX) / 2, (_c select 1) - (_minY + _maxY) / 2, 0];
{ _y set ["pos", (_y get "pos") vectorAdd _off]; } forEach _nodes;
[_c, ((_maxX - _minX) max (_maxY - _minY)) max 600]
