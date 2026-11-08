/*
    Routes sur une carte (événement Draw, à chaque image) : carte de l'app Carte (fn_mapOnDraw) et carte de l'app Routes
    (fn_routeMap). Seules les routes affichées (ou modifiée, ou suivie) et dans la vue sont dessinées ; leur tracé est
    gardé par révision (fn_routeAction "geo") : rien n'est recalculé à chaque image.
      - trait à la couleur, l'épaisseur et le style de la route (plein, pointillé, flèches de direction), liseré sombre ;
      - aérien : couloir de vol en bande claire ; ligne de phase : trait perpendiculaire à la route ;
      - style tactique (fn_mapColors « tac ») : route à sa couleur d'origine passée en orange, épaisse, halo léger au lieu du
        liseré sombre, flèche au bout ; couleur choisie par le joueur gardée ;
      - étapes : symbole de leur type, « numéro. nom », nom de la route sur la première ;
      - zoom rapproché : distance et cap de chaque tronçon ;
      - route suivie : tronçon en cours en vert, tirets de ma position à l'étape visée ;
      - route modifiée (app Routes) : étape choisie cerclée de blanc, aperçu du prochain point sous le curseur.
    Params : [carte, échelle des symboles, échelle des textes, route modifiée (id, "" : aucune), étape choisie, curseur,
              mode de clic de l'app ("ADD" | "INSERT" | "MOVE")]
*/
params ["_map", ["_sc", 1], ["_ts", 1], ["_edit", ""], ["_sel", -1], ["_cursor", []], ["_mode", "ADD"]];
if (isNil {missionNamespace getVariable "COMSPEC_ATAK_RteMine"}) then { ["mine"] call comspec_atak_native_fnc_routeAction; };
private _mine = missionNamespace getVariable ["COMSPEC_ATAK_RteMine", createHashMap];
private _rx = missionNamespace getVariable ["COMSPEC_ATAK_RteRx", createHashMap];
if (((count _mine) + (count _rx)) isEqualTo 0) exitWith {};
private _f = missionNamespace getVariable ["COMSPEC_ATAK_RteFollow", createHashMap];
private _fid = _f getOrDefault ["id", ""];
private _list = ((values _mine) + (values _rx)) select { (_x get "vis") || {(_x get "id") in [_edit, _fid]} };
if ((count _list) isEqualTo 0) exitWith {};
(ctrlPosition _map) params ["_cx", "_cy", "_cw", "_ch"];
private _tl = _map ctrlMapScreenToWorld [_cx, _cy];
private _br = _map ctrlMapScreenToWorld [_cx + _cw, _cy + _ch];
private _mpx = ["mpx", _map] call comspec_atak_native_fnc_planGfx;
private _mg = 60 * _mpx;
private _vx0 = ((_tl select 0) min (_br select 0)) - _mg; private _vx1 = ((_tl select 0) max (_br select 0)) + _mg;
private _vy0 = ((_tl select 1) min (_br select 1)) - _mg; private _vy1 = ((_tl select 1) max (_br select 1)) + _mg;
private _font = COMSPEC_ATAK_FontSet select 1;
private _clear = "#(argb,8,8,3)color(0,0,0,0)";
private _tex = "#(rgb,8,8,3)color(1,1,1,1)";
private _ring = "\A3\ui_f\data\map\markers\military\circle_CA.paa";
private _zoomed = (ctrlMapScale _map) < 0.12;
private _fmt = { params ["_m"]; [format ["%1 m", round _m], format ["%1 km", (_m / 1000) toFixed 1]] select (_m >= 1000) };
// Couleur et icône des types d'étape : table fixe, faite une fois.
private _tmap = uiNamespace getVariable ["COMSPEC_ATAK_RteTypeMap", createHashMap];
if ((count _tmap) isEqualTo 0) then {
    { _tmap set [_x select 0, [_x select 3, ["icon", _x select 0] call comspec_atak_native_fnc_routeAction]]; } forEach (["types"] call comspec_atak_native_fnc_routeAction);
    uiNamespace setVariable ["COMSPEC_ATAK_RteTypeMap", _tmap];
};
private _pal = [] call comspec_atak_native_fnc_mapColors;
private _tac = _pal get "tac";
// Couleur d'origine de chaque genre de route : table fixe, faite une fois.
private _kindCol = uiNamespace getVariable ["COMSPEC_ATAK_RteKindCol", createHashMap];
if ((count _kindCol) isEqualTo 0) then {
    { _kindCol set [_x select 0, _x select 5]; } forEach (["kinds"] call comspec_atak_native_fnc_routeAction);
    uiNamespace setVariable ["COMSPEC_ATAK_RteKindCol", _kindCol];
};
private _rgbaCache = uiNamespace getVariable ["COMSPEC_ATAK_RteRgba", createHashMap];
if ((count _rgbaCache) isEqualTo 0) then {
    { _rgbaCache set [_x select 0, _x select 1]; } forEach (["colors"] call comspec_atak_native_fnc_routeAction);
    uiNamespace setVariable ["COMSPEC_ATAK_RteRgba", _rgbaCache];
};
{
    private _r = _x;
    private _id = _r get "id";
    private _st = _r get "steps";
    if ((count _st) isEqualTo 0) then { continue };
    (["geo", _r] call comspec_atak_native_fnc_routeAction) params ["", "_pts", "_bb", ["_ld", []], ["_lb", []]];
    _bb params ["_x0", "_y0", "_x1", "_y1"];
    if (_x1 < _vx0 || {_x0 > _vx1} || {_y1 < _vy0} || {_y0 > _vy1}) then { continue };
    private _isEdit = _id isEqualTo _edit;
    private _col = +(_rgbaCache getOrDefault [_r get "color", [0.15, 0.75, 0.25, 1]]);
    if (_tac && {(_r get "color") isEqualTo (_kindCol getOrDefault [_r get "kind", ""])}) then { _col = +(_pal get "tacOrange"); };
    // Route d'un autre (reçue) un peu plus discrète que les miennes ; route modifiée bien franche.
    _col set [3, [[0.8, 0.95] select (_id in _mine), 1] select _isEdit];
    private _w = [2, 3.5, 5] select (((_r get "width") - 1) max 0 min 2);
    if (_tac) then { _w = _w max 3.5; };
    private _style = _r get "style";
    private _kind = _r get "kind";
    // 1. Couloir aérien.
    if (_kind isEqualTo "AIR" && {(count _st) >= 2}) then {
        private _hw = (_r get "corr") / 2;
        private _cc = [_col select 0, _col select 1, _col select 2, 0.13];
        for "_i" from 1 to ((count _st) - 1) do {
            private _a = (_st select (_i - 1)) select 0; private _b = (_st select _i) select 0;
            private _len = _a distance2D _b;
            if (_len > 1) then { _map drawRectangle [[((_a select 0) + (_b select 0)) / 2, ((_a select 1) + (_b select 1)) / 2, 0], _hw, _len / 2, _a getDir _b, _cc, _tex]; };
        };
    };
    // 2. Trait : liseré sombre puis couleur.
    if ((count _pts) >= 2) then {
        private _dash = _style isEqualTo "DASH";
        // Style tactique : halo de la couleur de la route, sans jointures (autant de traits qu'avec le liseré sombre).
        if (_tac) then {
            ["line", _map, [_pts, [_col select 0, _col select 1, _col select 2, 0.25 * (_col select 3)], _w + 5, _dash, false, false, _mpx, false]] call comspec_atak_native_fnc_planGfx;
        } else {
            ["line", _map, [_pts, [0, 0, 0, 0.45 * (_col select 3)], _w + 2.5, _dash, false, false, _mpx]] call comspec_atak_native_fnc_planGfx;
        };
        ["line", _map, [_pts, _col, _w, _dash, _style isEqualTo "ARROW" || {_tac && {!_dash}}, true, _mpx]] call comspec_atak_native_fnc_planGfx;
    };
    // 3. Tronçon en cours de la route suivie, et tirets de ma position à l'étape visée.
    if (_id isEqualTo _fid) then {
        private _fi = ((_f getOrDefault ["idx", 0]) min ((count _st) - 1)) max 0;
        private _tp = (_st select _fi) select 0;
        if (_fi > 0) then {
            private _path = (_r get "legs") param [_fi - 1, []];
            if ((count _path) < 2) then { _path = [(_st select (_fi - 1)) select 0, _tp]; };
            ["line", _map, [_path, [0.36, 0.85, 0.42, 0.95], _w + 1.5, false, false, false, _mpx]] call comspec_atak_native_fnc_planGfx;
        };
        private _me = [] call comspec_atak_native_fnc_gpsPos; _me set [2, 0];
        ["line", _map, [[_me, [_tp select 0, _tp select 1, 0]], [0.36, 0.85, 0.42, 0.9], 2, true, true, true, _mpx]] call comspec_atak_native_fnc_planGfx;
    };
    // 4. Sens de marche (style FLÈCHES) et étiquettes des tronçons au zoom rapproché.
    for "_i" from 1 to ((count _st) - 1) do {
        private _a = (_st select (_i - 1)) select 0; private _b = (_st select _i) select 0;
        private _path = (_r get "legs") param [_i - 1, []];
        private _mid = (_a vectorAdd _b) vectorMultiply 0.5;
        private _h = _a getDir _b;
        if ((count _path) >= 3) then {
            private _m = floor ((count _path) / 2);
            _mid = _path select _m;
            _h = (_path select (_m - 1)) getDir (_path select _m);
        };
        _mid = [_mid select 0, _mid select 1, 0];
        private _lenPx = (_ld param [_i - 1, _a distance2D _b]) / _mpx;
        if (_style isEqualTo "ARROW" && {_lenPx > 60}) then {
            ["head", _map, [_mid getPos [6 * _mpx, _h], _h, [1, 1, 1, 0.95 * (_col select 3)], 9 + _w, true, 2, _mpx]] call comspec_atak_native_fnc_planGfx;
        };
        if (_zoomed && {_lenPx > 150}) then {
            private _n = _mid getPos [12 * _mpx, _h - 90];
            _map drawIcon [_clear, [1, 1, 1, 0.9], _n, 0, 0, 0, format ["%1 · %2°", [_ld param [_i - 1, 0]] call _fmt, round (_lb param [_i - 1, _h])], 2, 0.022 * _ts, _font, "center"];
        };
    };
    // 5. Étapes (après les traits : jamais recouvertes).
    {
        _x params ["_p", "_k", "_n"];
        private _tm = _tmap getOrDefault [_k, _tmap getOrDefault ["WP", [[1, 1, 1, 1], ""]]];
        private _c = +(_tm select 0);
        _c set [3, _col select 3];
        if (_k isEqualTo "PL") then {
            // Ligne de phase : trait perpendiculaire au sens de marche, de part et d'autre de l'étape.
            private _q = if (_forEachIndex < ((count _st) - 1)) then { (_st select (_forEachIndex + 1)) select 0 } else { (_st select ((_forEachIndex - 1) max 0)) select 0 };
            private _hh = (_p getDir _q) + 90;
            ["line", _map, [[_p getPos [45 * _mpx, _hh], _p getPos [45 * _mpx, _hh + 180]], _c, 3, true, false, false, _mpx]] call comspec_atak_native_fnc_planGfx;
        };
        if (_id isEqualTo _fid && {_forEachIndex isEqualTo (_f getOrDefault ["idx", -1])}) then {
            _map drawIcon [_ring, [0.36, 0.85, 0.42, 0.5 + 0.5 * (abs sin (diag_tickTime * 150))], _p, 40 * _sc, 40 * _sc, 0, "", 0];
        };
        if (_isEdit && {_forEachIndex isEqualTo _sel}) then { _map drawIcon [_ring, [1, 1, 1, 1], _p, 38 * _sc, 38 * _sc, 0, "", 0]; };
        private _lbl = format [" %1. %2", _forEachIndex + 1, [_n] call comspec_atak_native_fnc_markerAutoName];
        if (_forEachIndex isEqualTo 0) then { _lbl = format [" %1 · %2", _r get "name", _lbl select [1]]; };
        _map drawIcon [_tm select 1, [0, 0, 0, 0.6 * (_c select 3)], _p, 28 * _sc, 28 * _sc, 0, "", 0];
        _map drawIcon [_tm select 1, _c, _p, 22 * _sc, 22 * _sc, 0, _lbl, 2, 0.028 * _ts, _font, "right"];
    } forEach _st;
    // 6. Route modifiée : aperçu du prochain point (ajout à la fin, insertion après l'étape choisie, déplacement).
    if (_isEdit && {(count _cursor) >= 2}) then {
        private _cp = [_cursor select 0, _cursor select 1, 0];
        private _from = switch (true) do {
            case (_mode isEqualTo "MOVE" && {_sel >= 0} && {_sel < count _st}): { (_st select _sel) select 0 };
            case (_mode isEqualTo "INSERT" && {_sel >= 0} && {_sel < count _st}): { (_st select _sel) select 0 };
            default { (_st select ((count _st) - 1)) select 0 };
        };
        ["line", _map, [[_from, _cp], [1, 1, 1, 0.55], 2, true, false, false, _mpx]] call comspec_atak_native_fnc_planGfx;
        if (_mode isEqualTo "INSERT" && {_sel >= 0} && {_sel < ((count _st) - 1)}) then {
            ["line", _map, [[_cp, (_st select (_sel + 1)) select 0], [1, 1, 1, 0.4], 2, true, false, false, _mpx]] call comspec_atak_native_fnc_planGfx;
        };
        _map drawIcon [_clear, [1, 1, 1, 0.9], _cp, 0, 0, 0, format ["  %1 · %2°", [_from distance2D _cp] call _fmt, round (_from getDir _cp)], 2, 0.024 * _ts, _font, "left"];
    };
} forEach _list;
