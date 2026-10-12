/*
    Actions de l'app Relief : champ de vision, heatmap d'altitude, ligne de vue, bâtiments visibles.
      "set"       : [clé d'état, valeur] (rlTool, rlRadius, rlObst, rlHeat, rlBldRadius) puis redessine la page.
      "compute"   : grille 41 x 41 autour du joueur, en tâche de fond : case visible si rien ne coupe la ligne
                    œil → sol de la case + 1,7 m (terrain seul, ou terrain + objets si rlObst = "OBJ").
                    Sert au champ de vision (vert / rouge) et à la heatmap (altitude absolue ou par rapport à moi).
      "clear"     : efface la grille.
      "show"      : affiche / masque les calques Relief sur la carte.
      "locate"    : centre la carte sur le point le plus haut (ou sur le centre du calcul avec "center").
      "los"       : ligne de vue de mes yeux vers un point ("grid" : carroyage saisi, "look" : point visé, "pos" : position donnée),
                    profil du terrain sur 64 points, premier obstacle (terrain, bâtiment, végétation, véhicule).
      "losMap"    : outil LIGNE DE VUE de la carte (clic sur la carte), puis ouvre la carte.
      "losClear"  : efface la ligne de vue.
      "bld"       : bâtiments vus depuis mes yeux dans le rayon rlBldRadius (tâche de fond).
      "bldClear"  : efface les bâtiments.
      "focus"     : [position] centre la carte sur ce point.
    Résultats (missionNamespace) :
      COMSPEC_ATAK_Viewshed = [centre, taille de case, [[pos, visible, altitude, couleur absolue, couleur relative]...],
                               rayon, altitude mini, altitude maxi, % visible, index du point le plus haut, heure,
                               obstacles, mon altitude] ;
      COMSPEC_ATAK_ReliefLos = [œil ASL, cible ASL, point d'arrêt ASL ou [], nature de l'obstacle, [[distance, sol, visée]...], heure] ;
      COMSPEC_ATAK_ReliefBld = [centre, rayon, [[bâtiment, vu, distance, azimut]...], heure].
*/
params [["_act", ""], ["_k", ""], ["_v", ""]];
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _render = { if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "RELIEF") then { [{ ["RELIEF"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame; }; };
// Nature d'un objet touché par une ligne de vue.
private _kind = {
    params ["_o"];
    if (isNull _o) exitWith { "objet" };
    if (_o isKindOf "AllVehicles") exitWith { "véhicule" };
    if (_o isKindOf "House" || {_o isKindOf "Building"}) exitWith { "bâtiment" };
    if ((typeOf _o) isEqualTo "" && {(toLower ((getModelInfo _o) param [0, ""])) regexMatch "^(t_|b_|str_|d_|bush|tree).*"}) exitWith { "végétation" };
    if ((typeOf _o) isEqualTo "") exitWith { "végétation ou décor" };
    "objet"
};
switch (_act) do {
    case "set": {
        _s set [_k, _v];
        // La heatmap et le champ de vision partagent la grille : l'outil choisit l'affichage sur la carte.
        if (_k isEqualTo "rlTool" && {_v in ["VIS", "ALT"]}) then { _s set ["rlMode", _v]; };
        call _render;
    };
    case "compute": {
        if (missionNamespace getVariable ["COMSPEC_ATAK_ViewshedBusy", false]) exitWith { ["INFO", "Calcul du relief déjà en cours", 3, 20] call comspec_atak_native_fnc_notify; };
        private _radius = _s getOrDefault ["rlRadius", 1000];
        private _obst = (_s getOrDefault ["rlObst", "TER"]) isEqualTo "OBJ";
        missionNamespace setVariable ["COMSPEC_ATAK_ViewshedBusy", true];
        missionNamespace setVariable ["COMSPEC_ATAK_ViewshedProgress", 0];
        missionNamespace setVariable ["COMSPEC_ATAK_ViewshedShow", true];
        call _render;
        [_radius, _render, _obst] spawn {
            params ["_radius", "_render", "_obst"];
            private _n = 41;
            private _half = floor (_n / 2);
            private _cell = (2 * _radius) / (_n - 1);
            private _eye = eyePos player;
            private _c = getPosASL player;
            private _center = [_c select 0, _c select 1];
            private _myH = getTerrainHeightASL _center;
            private _cells = [];
            private _minH = 1e9;
            private _maxH = -1e9;
            private _vis = 0;
            private _total = 0;
            private _best = -1;
            for "_i" from 0 to (_n - 1) do {
                for "_j" from 0 to (_n - 1) do {
                    private _dx = (_i - _half) * _cell;
                    private _dy = (_j - _half) * _cell;
                    // Cercle plutôt que carré : on ignore les coins hors du rayon.
                    if (sqrt (_dx * _dx + _dy * _dy) > _radius + _cell / 2) then { continue };
                    private _p = [(_center select 0) + _dx, (_center select 1) + _dy];
                    private _h = getTerrainHeightASL _p;
                    // Mer : on prend la surface de l'eau comme sol.
                    private _ground = _h max 0;
                    private _to = [_p select 0, _p select 1, _ground + 1.7];
                    private _seen = (_i isEqualTo _half && {_j isEqualTo _half}) || {!(terrainIntersectASL [_eye, _to])};
                    // Bâtiments, arbres et véhicules : seulement si demandé (plus lent).
                    if (_seen && _obst && {!(_i isEqualTo _half && {_j isEqualTo _half})}) then {
                        _seen = (count (lineIntersectsSurfaces [_eye, _to, player, vehicle player, true, 1, "VIEW", "FIRE"])) isEqualTo 0;
                    };
                    if (_seen) then { _vis = _vis + 1; };
                    _total = _total + 1;
                    if (_h < _minH) then { _minH = _h; };
                    if (_h > _maxH) then { _maxH = _h; _best = count _cells; };
                    _cells pushBack [[_p select 0, _p select 1, _h], _seen, _h, [], []];
                };
                missionNamespace setVariable ["COMSPEC_ATAK_ViewshedProgress", (_i + 1) / _n];
            };
            // Couleurs précalculées, la carte n'a plus qu'à dessiner :
            //  absolue : bleu nuit (bas), cyan, vert, jaune, rouge (haut) ;
            //  par rapport à moi : bleu en dessous, gris à mon niveau, orange puis rouge au-dessus (ce qui me domine).
            private _ramp = [[0.10, 0.18, 0.55], [0.10, 0.65, 0.85], [0.25, 0.80, 0.35], [0.95, 0.85, 0.20], [0.92, 0.22, 0.15]];
            private _span = (_maxH - _minH) max 1;
            {
                private _t = ((((_x select 2) - _minH) / _span) max 0) min 1;
                private _f = _t * 4;
                private _i0 = (floor _f) min 3;
                private _u = _f - _i0;
                private _a = _ramp select _i0;
                private _b = _ramp select (_i0 + 1);
                _x set [3, [(_a select 0) + ((_b select 0) - (_a select 0)) * _u, (_a select 1) + ((_b select 1) - (_a select 1)) * _u, (_a select 2) + ((_b select 2) - (_a select 2)) * _u, 0.45]];
                private _d = (((_x select 2) - _myH) / 40) max -1 min 1;
                _x set [4, if (_d < -0.05) then { [0.15, 0.35 + 0.2 * (1 + _d), 0.95, 0.15 + 0.35 * (abs _d)] } else {
                    if (_d < 0.05) then { [0.7, 0.75, 0.72, 0.15] } else { [0.95, 0.65 - 0.45 * _d, 0.15, 0.2 + 0.4 * _d] } }];
            } forEach _cells;
            missionNamespace setVariable ["COMSPEC_ATAK_Viewshed", [_center, _cell, _cells, _radius, _minH, _maxH, round (100 * _vis / (_total max 1)), _best, dayTime, _obst, _myH]];
            missionNamespace setVariable ["COMSPEC_ATAK_ViewshedBusy", false];
            ["SUCCESS", format ["Relief calculé : %1 %% visible sur %2 m", round (100 * _vis / (_total max 1)), _radius], 4, 30] call comspec_atak_native_fnc_notify;
            call _render;
        };
    };
    case "clear": {
        missionNamespace setVariable ["COMSPEC_ATAK_Viewshed", nil];
        call _render;
    };
    case "show": {
        missionNamespace setVariable ["COMSPEC_ATAK_ViewshedShow", !(missionNamespace getVariable ["COMSPEC_ATAK_ViewshedShow", true])];
        call _render;
    };
    case "locate": {
        private _vs = missionNamespace getVariable ["COMSPEC_ATAK_Viewshed", []];
        private _target = if ((count _vs) > 7 && {(_vs select 7) >= 0}) then { ((_vs select 2) select (_vs select 7)) select 0 } else { player };
        if (_k isEqualTo "center" && {(count _vs) > 0}) then { _target = _vs select 0; };
        ["MAP"] call comspec_atak_native_fnc_navigate;
        [{ _this call comspec_atak_native_fnc_mapCenter; }, [_target, 0.06]] call CBA_fnc_execNextFrame;
    };
    case "focus": {
        ["MAP"] call comspec_atak_native_fnc_navigate;
        [{ _this call comspec_atak_native_fnc_mapCenter; }, [_k, 0.04]] call CBA_fnc_execNextFrame;
    };
    case "los": {
        // Cible : carroyage saisi (gardé dans rlGrid) ou point sous mon regard.
        private _target = [];
        if (_k isEqualTo "pos") then { _target = _v; _s set ["rlGrid", [_target, 8] call comspec_atak_native_fnc_gridRef]; };
        if (_k isEqualTo "look") then {
            _target = if (isNil "comspec_overwatch_connect_fnc_reconLookPos") then { screenToWorld [0.5, 0.5] } else { [] call comspec_overwatch_connect_fnc_reconLookPos };
            _s set ["rlGrid", [_target, 8] call comspec_atak_native_fnc_gridRef];
        };
        if (_k isEqualTo "grid") then {
            private _g = ["rlGrid", _s getOrDefault ["rlGrid", ""]] call comspec_atak_native_fnc_formValue;
            _s set ["rlGrid", _g];
            private _gc = (_g splitString " ") joinString "";
            if (_gc regexMatch "^[0-9]{6,10}$" && {((count _gc) mod 2) isEqualTo 0}) then { _target = ([_gc] call BIS_fnc_gridToPos) param [0, []]; };
        };
        if ((count _target) < 2) exitWith {
            ["WARNING", "Ligne de vue : carroyage illisible (6 à 10 chiffres)", 4, 30] call comspec_atak_native_fnc_notify;
            call _render;
        };
        private _from = eyePos player;
        private _to = AGLToASL [_target select 0, _target select 1, 1.7];
        private _block = [];
        private _what = "";
        private _hits = lineIntersectsSurfaces [_from, _to, player, vehicle player, true, 1, "VIEW", "FIRE"];
        if ((count _hits) > 0) then {
            _block = (_hits select 0) select 0;
            private _o = (_hits select 0) select 3;
            if (isNull _o) then { _o = (_hits select 0) select 2; };
            _what = [_o] call _kind;
        };
        private _terrain = terrainIntersectAtASL [_from, _to];
        if (_terrain isNotEqualTo [0, 0, 0] && {(count _block) isEqualTo 0 || {(_from distance _terrain) < (_from distance _block)}}) then { _block = _terrain; _what = "relief"; };
        // Profil : sol et ligne de visée sur 64 points.
        private _len = _from distance2D _to;
        private _prof = [];
        for "_i" from 0 to 63 do {
            private _t = _i / 63;
            private _p = _from vectorAdd ((_to vectorDiff _from) vectorMultiply _t);
            _prof pushBack [_len * _t, (getTerrainHeightASL [_p select 0, _p select 1]) max 0, _p select 2];
        };
        missionNamespace setVariable ["COMSPEC_ATAK_ReliefLos", [_from, _to, _block, _what, _prof, dayTime]];
        // Même tracé que l'outil LIGNE DE VUE de la carte.
        _s set ["mapLos", [ASLToAGL _from, [_target select 0, _target select 1, 0], _block]];
        ["TACTICAL", ["Ligne de vue dégagée", format ["Vue bloquée à %1 m (%2)", round (_from distance2D _block), _what]] select ((count _block) > 0), 3, 20] call comspec_atak_native_fnc_notify;
        call _render;
    };
    case "losMap": {
        ["MAP"] call comspec_atak_native_fnc_navigate;
        [{ ["LOS"] call comspec_atak_native_fnc_mapToolSet; ["INFO", "Touchez la carte : ligne de vue depuis mes yeux vers ce point", 4, 20] call comspec_atak_native_fnc_notify; }] call CBA_fnc_execNextFrame;
    };
    case "losClear": {
        missionNamespace setVariable ["COMSPEC_ATAK_ReliefLos", nil];
        _s set ["mapLos", []];
        call _render;
    };
    case "bld": {
        if (missionNamespace getVariable ["COMSPEC_ATAK_BldBusy", false]) exitWith { ["INFO", "Recherche des bâtiments déjà en cours", 3, 20] call comspec_atak_native_fnc_notify; };
        missionNamespace setVariable ["COMSPEC_ATAK_BldBusy", true];
        missionNamespace setVariable ["COMSPEC_ATAK_ViewshedShow", true];
        call _render;
        [_s getOrDefault ["rlBldRadius", 500], _render] spawn {
            params ["_radius", "_render"];
            private _eye = eyePos player;
            private _c = getPosASL player;
            // Vrais bâtiments seulement : on écarte murs, poteaux et petits objets (moins de 4 m de large).
            private _list = (nearestObjects [ASLToAGL _c, ["House", "Building"], _radius]) select {
                !isObjectHidden _x && {damage _x < 1} && {((boundingBoxReal _x) select 2) > 4} && {(_x distance2D _c) > 3}
            };
            _list = _list select [0, 300];
            private _out = [];
            {
                private _b = _x;
                (boundingBoxReal _b) params ["_p1", "_p2"];
                private _top = (_p2 select 2) - (_p1 select 2);
                private _base = getPosASL _b;
                // Trois points du bâtiment : milieu de façade, haut, centre. Vu si l'un d'eux est atteint sans obstacle avant lui.
                private _seen = false;
                {
                    private _pt = _base vectorAdd [0, 0, _top * _x];
                    if !(terrainIntersectASL [_eye, _pt]) then {
                        private _h = lineIntersectsSurfaces [_eye, _pt, player, vehicle player, true, 1, "VIEW", "GEOM"];
                        if ((count _h) isEqualTo 0 || {((_h select 0) select 2) isEqualTo _b} || {((_h select 0) select 3) isEqualTo _b}) exitWith { _seen = true; };
                    };
                    if (_seen) exitWith {};
                } forEach [0.4, 0.85, 0.15];
                _out pushBack [_b, _seen, player distance2D _b, round (player getDir _b)];
                if ((_forEachIndex mod 20) isEqualTo 0) then { missionNamespace setVariable ["COMSPEC_ATAK_BldProgress", _forEachIndex / ((count _list) max 1)]; };
            } forEach _list;
            missionNamespace setVariable ["COMSPEC_ATAK_ReliefBld", [[_c select 0, _c select 1], _radius, _out, dayTime]];
            missionNamespace setVariable ["COMSPEC_ATAK_BldBusy", false];
            ["SUCCESS", format ["%1 bâtiment(s) vu(s) sur %2 à %3 m", { _x select 1 } count _out, count _out, _radius], 4, 30] call comspec_atak_native_fnc_notify;
            call _render;
        };
    };
    case "bldClear": {
        missionNamespace setVariable ["COMSPEC_ATAK_ReliefBld", nil];
        call _render;
    };
};
true
