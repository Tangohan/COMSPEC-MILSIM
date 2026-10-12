/*
    Reconstruit le tableau des liens (données, filtre, disposition).
    [_fit] call comspec_sse_fnc_chartRefresh
      _fit : recadrer la vue sur l'ensemble
*/
params [["_fit", false, [false]]];
private _disp = uiNamespace getVariable ["comspec_sse_chartDisplay", displayNull];
if (isNull _disp) exitWith { false };
private _st = uiNamespace getVariable ["comspec_sse_chartState", createHashMap];
private _zeus = _st getOrDefault ["zeus", false];
private _mode = _st getOrDefault ["mode", "graph"];
private _filter = _st getOrDefault ["filter", "all"];

([_zeus] call comspec_sse_fnc_chartBuild) params ["_nodes", "_edges"];

// Filtres.
private _keepKinds = switch (true) do {
    case (_mode isEqualTo "orbat"): { ["org", "person"] };
    case (_filter isEqualTo "people"): { ["org", "person"] };
    case (_filter isEqualTo "orgs"): { ["org"] };
    default { [] };
};
{
    private _n = _nodes get _x;
    if ((_keepKinds isNotEqualTo [] && {!((_n get "kind") in _keepKinds)})
        || {(_st getOrDefault ["confirmed", false]) && {(_n get "known") < 2}}) then {
        _nodes deleteAt _x;
    };
} forEach (keys _nodes);
if (_filter isEqualTo "things" && {_mode isNotEqualTo "orbat"}) then {
    // Objets et données, plus les personnes et organisations qui y touchent.
    private _keep = createHashMap;
    {
        _x params ["_a", "_b"];
        private _ka = (_nodes getOrDefault [_a, createHashMap]) getOrDefault ["kind", ""];
        private _kb = (_nodes getOrDefault [_b, createHashMap]) getOrDefault ["kind", ""];
        if (!(_ka in ["org", "person", ""]) || {!(_kb in ["org", "person", ""])}) then { _keep set [_a, true]; _keep set [_b, true]; };
    } forEach _edges;
    { if (!((_y get "kind") in ["org", "person"])) then { _keep set [_x, true]; }; } forEach _nodes;
    { if (!(_x in _keep)) then { _nodes deleteAt _x; }; } forEach (keys _nodes);
};
_edges = _edges select { ((_x select 0) in _nodes) && {(_x select 1) in _nodes} };
{ if (!((_y get "parent") in _nodes)) then { _y set ["parent", ""]; }; } forEach _nodes;

([_nodes, _edges, _mode] call comspec_sse_fnc_chartLayout) params ["_center", "_extent"];
_st set ["nodes", _nodes];
_st set ["edges", _edges];
if (!((_st getOrDefault ["sel", ""]) in _nodes)) then { _st set ["sel", ""]; };

private _map = _disp displayCtrl 93510;
if (_fit) then {
    private _scale = ((_extent * 1.25) / worldSize) max 0.004 min 1;
    _map ctrlMapAnimAdd [0, _scale, _center];
    ctrlMapAnimCommit _map;
};

// Boutons : mode et filtre courants surlignés.
{
    _x params ["_idc", "_on"];
    (_disp displayCtrl _idc) ctrlSetTextColor ([[0.62, 0.68, 0.64, 1], [0.55, 1, 0.6, 1]] select _on);
} forEach [
    [93520, _mode isEqualTo "graph"], [93521, _mode isEqualTo "orbat"],
    [93522, _filter isEqualTo "all"], [93523, _filter isEqualTo "people"],
    [93524, _filter isEqualTo "orgs"], [93525, _filter isEqualTo "things"],
    [93526, _st getOrDefault ["confirmed", false]]
];

private _np = { (_x get "kind") isEqualTo "person" } count (values _nodes);
private _no = { (_x get "kind") isEqualTo "org" } count (values _nodes);
(_disp displayCtrl 93503) ctrlSetText format ["  %1 organisation(s) · %2 personne(s) · %3 autre(s) · %4 lien(s)%5",
    _no, _np, (count _nodes) - _np - _no, count _edges,
    ["", "   — rien de connu pour l'instant : contrôlez, identifiez, exploitez"] select ((count _nodes) isEqualTo 0)];
[_st getOrDefault ["sel", ""]] call comspec_sse_fnc_chartDetail;
true
