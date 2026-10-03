/*
    GPS routier : calcule l'itinéraire par la route entre ma position et une destination (A* sur le réseau routier
    d'Arma : roadsConnectedTo, pistes comprises). Lancé en spawn : peut prendre une seconde sur une grande carte.
    Params : [destination ATL, libellé, silencieux (recalcul)]
    Résultat dans missionNamespace COMSPEC_ATAK_Route (HashMap) : pts, cum (distances cumulées), turns, dest, label, total.
*/
params [["_dest", [0, 0, 0]], ["_label", "Destination"], ["_quiet", false]];
if (missionNamespace getVariable ["COMSPEC_ATAK_RouteBusy", false]) exitWith { false };
missionNamespace setVariable ["COMSPEC_ATAK_RouteBusy", true];
private _from = getPosATL vehicle player;
private _nearest = {
    params ["_p"];
    private _rs = (_p nearRoads 250) select { !isNull _x };
    if ((count _rs) isEqualTo 0) then { _rs = (_p nearRoads 900) select { !isNull _x }; };
    if ((count _rs) isEqualTo 0) exitWith { objNull };
    private _k = _rs apply { [_p distance2D _x, _x] };
    _k sort true;
    (_k select 0) select 1
};
private _r0 = [_from] call _nearest;
private _r1 = [_dest] call _nearest;
private _pts = [];
if (isNull _r0 || {isNull _r1} || {(_from distance2D _dest) < 150}) then {
    // Pas de route utile : ligne droite (à pied, en tout-terrain).
    _pts = [_from, _dest];
} else {
    // A* : tas binaire [f, clé], g et parent par clé (str de l'objet route).
    private _key = { str _this };
    private _g = createHashMap; private _par = createHashMap; private _obj = createHashMap; private _closed = createHashMap;
    private _heap = [];
    private _push = {
        params ["_f", "_k"];
        _heap pushBack [_f, _k];
        private _i = (count _heap) - 1;
        while { _i > 0 } do {
            private _p = floor ((_i - 1) / 2);
            if (((_heap select _p) select 0) <= _f) exitWith {};
            private _t = _heap select _p; _heap set [_p, _heap select _i]; _heap set [_i, _t]; _i = _p;
        };
    };
    private _pop = {
        private _top = _heap select 0;
        private _last = _heap deleteAt ((count _heap) - 1);
        if ((count _heap) > 0) then {
            _heap set [0, _last];
            private _i = 0; private _n = count _heap;
            while { true } do {
                private _l = 2 * _i + 1; private _r = _l + 1; private _m = _i;
                if (_l < _n && {((_heap select _l) select 0) < ((_heap select _m) select 0)}) then { _m = _l; };
                if (_r < _n && {((_heap select _r) select 0) < ((_heap select _m) select 0)}) then { _m = _r; };
                if (_m isEqualTo _i) exitWith {};
                private _t = _heap select _m; _heap set [_m, _heap select _i]; _heap set [_i, _t]; _i = _m;
            };
        };
        _top
    };
    private _k0 = _r0 call _key; private _k1 = _r1 call _key;
    private _goal = getPosATL _r1;
    _g set [_k0, 0]; _obj set [_k0, _r0];
    [_r0 distance2D _goal, _k0] call _push;
    private _found = false; private _n = 0;
    while { (count _heap) > 0 && {_n < 60000} } do {
        private _cur = (call _pop) select 1;
        if (_cur in _closed) then { continue };
        _closed set [_cur, true];
        _n = _n + 1;
        if (_cur isEqualTo _k1) exitWith { _found = true; };
        private _ro = _obj get _cur;
        private _gc = _g get _cur;
        {
            private _k = _x call _key;
            if !(_k in _closed) then {
                // Pistes un peu pénalisées : on préfère les routes.
                private _cost = (_ro distance2D _x) * ([1, 1.35] select (((getRoadInfo _x) param [0, ""]) in ["TRACK", "TRAIL"]));
                private _ng = _gc + _cost;
                if (_ng < (_g getOrDefault [_k, 1e12])) then {
                    _g set [_k, _ng]; _par set [_k, _cur]; _obj set [_k, _x];
                    [_ng + (_x distance2D _goal), _k] call _push;
                };
            };
        } forEach (roadsConnectedTo [_ro, true]);
    };
    if (_found) then {
        private _chain = [];
        private _k = _k1;
        while { _k isNotEqualTo "" } do { _chain pushBack getPosATL (_obj get _k); _k = _par getOrDefault [_k, ""]; };
        reverse _chain;
        _pts = [_from] + _chain + [_dest];
    } else {
        _pts = [_from, _dest];
        if !(_quiet) then { ["WARNING", "Pas d'itinéraire routier trouvé : cap direct affiché", 4, 30] call comspec_atak_native_fnc_notify; };
    };
};
// Simplifie (points trop proches ou alignés) et calcule les virages.
private _simple = [_pts select 0];
for "_i" from 1 to ((count _pts) - 2) do {
    private _a = _simple select ((count _simple) - 1); private _b = _pts select _i; private _c = _pts select (_i + 1);
    private _turn = abs ((((_b getDir _c) - (_a getDir _b)) + 540) mod 360 - 180);
    if ((_a distance2D _b) > 12 && {_turn > 4 || {(_a distance2D _b) > 250}}) then { _simple pushBack _b; };
};
_simple pushBack (_pts select ((count _pts) - 1));
private _cum = [0];
for "_i" from 1 to ((count _simple) - 1) do { _cum pushBack ((_cum select (_i - 1)) + ((_simple select (_i - 1)) distance2D (_simple select _i))); };
private _turns = [];
for "_i" from 1 to ((count _simple) - 2) do {
    private _t = ((((_simple select _i) getDir (_simple select (_i + 1))) - ((_simple select (_i - 1)) getDir (_simple select _i))) + 540) mod 360 - 180;
    if ((abs _t) > 28) then { _turns pushBack [_i, _t]; };
};
missionNamespace setVariable ["COMSPEC_ATAK_Route", createHashMapFromArray [
    ["pts", _simple], ["cum", _cum], ["turns", _turns], ["dest", _dest], ["label", _label], ["total", _cum select ((count _cum) - 1)], ["idx", 0], ["off", 0], ["at", time]
]];
missionNamespace setVariable ["COMSPEC_ATAK_RouteBusy", false];
if !(_quiet) then {
    private _t = _cum select ((count _cum) - 1);
    ["SUCCESS", format ["Itinéraire vers %1 : %2", _label, [format ["%1 m", round _t], format ["%1 km", (_t / 1000) toFixed 1]] select (_t >= 1000)], 4, 30] call comspec_atak_native_fnc_notify;
    private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
    _s set ["mapFollow", true];
    [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "MAP") then { ["MAP"] call comspec_atak_native_fnc_pageRender; }; }] call CBA_fnc_execNextFrame;
};
true
