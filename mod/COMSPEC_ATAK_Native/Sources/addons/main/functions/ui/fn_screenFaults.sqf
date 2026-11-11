/*
    Dessin des bris de verre et des pannes de dalle du modèle fn_screenDamage, pour fn_screenSurface (qui tient l'ordre des
    calques : dalle, puis verre, puis liquides et sang, puis reflet). Textures : tools/gen_screen_damage.py.
    Chaque panne est posée à sa place enregistrée dans le repère du verre (portrait) : en paysage, (x, y) devient
    (y, 1 - x) et le dessin tourne d'un quart de tour, le dégât reste donc au même endroit du verre. Jamais d'étirement :
    les textures carrées sont dessinées carrées à l'écran (côté = petit côté de l'écran), quel que soit l'appareil.
    Gabarits : « point » (impact au point 0,3 ; 0,3 de la texture, quart de tour choisi pour le placer du bon côté),
    « corner » (origine dans un coin de l'écran), « strip » (bande 1 x 2 centrée), « free » (centrée sur l'impact).
    Les fêlures accrochent la lumière : une seconde passe de la même texture, décalée d'un pixel et demi, dont l'opacité
    suit la lumière du jour et le reflet du verre (plus rien de brillant la nuit ou aux JVN).
    Params :
      ["build", calque ("panel" | "glass"), pannes, [x, y, l, h] de l'écran, rapport, paysage, contrôles, code image, code case]
      ["update", contrôles, état matériel] : opacités, ligne qui clignote, tache qui grandit, eau qui bouge (chaque seconde).
    Plafond : 90 contrôles pour toutes les pannes (au-delà, les suivantes ne sont pas dessinées).
*/
params [["_mode", "build"]];
private _tex = { format ["\z\comspec_atak_native\addons\main\data\%1.paa", _this] };
if (_mode isEqualTo "update") exitWith {
    _this params ["", "_ctrls", ["_hp", createHashMap]];
    private _off = (_hp getOrDefault ["state", "OK"]) isEqualTo "OFF";
    // Lumière qui tombe sur le verre : jour 1, nuit 0,3 ; aux JVN les arêtes ne brillent pas.
    private _light = 0.3 + 0.7 * sunOrMoon;
    if ((currentVisionMode player) isEqualTo 1) then { _light = _light * 0.5; };
    private _glare = [0.6, 1] select ((missionNamespace getVariable ["comspec_atak_native_glass_glare", true]) isEqualTo true);
    private _soft = [1, 0.75] select ((["level"] call comspec_atak_native_fnc_screenDamage) isEqualTo 1);
    {
        _x params ["_kind", "_c", ["_d", []]];
        switch (_kind) do {
            case "sfG": { _c ctrlSetTextColor [1, 1, 1, (0.62 + 0.34 * _light) * _soft]; };
            case "sfg": { _c ctrlSetTextColor [1, 1, 1, 0.22 * _light * _glare * _soft]; };
            case "sfA": { _c ctrlSetTextColor [1, 1, 1, (0.2 + 0.11 * (_d param [0, 1])) * (0.3 + 0.7 * _light * _glare)]; };
            case "sfS": { _c ctrlSetTextColor [1, 1, 1, ([0.6, 0.8, 1] select (((_d param [0, 1]) max 1 min 3) - 1)) * (0.6 + 0.4 * _light)]; };
            // Eau sous le verre : l'auréole dérive lentement (quelques pixels par seconde, aller et retour).
            case "sfW": {
                _d params [["_sev", 1], ["_pos", [0, 0, 0, 0]]];
                _c ctrlSetTextColor [1, 1, 1, [0.55, 0.78, 0.95] select ((_sev max 1 min 3) - 1)];
                _c ctrlSetPosition [(_pos select 0) + pixelW * 5 * sin (time * 9), (_pos select 1) + pixelH * 4 * cos (time * 7), _pos select 2, _pos select 3];
                _c ctrlCommit 1;
            };
            // Dalle : rien d'allumé écran éteint.
            case "sfP": { _c ctrlShow !_off; };
            case "sfF": { _c ctrlShow !_off; _c ctrlSetFade ([0, 1] select (random 1 < 0.45)); _c ctrlCommit (0.05 + random 0.2); };
            // Tache d'encre : grandit lentement (jusqu'à plus du double en un quart d'heure), autour du même point.
            case "sfB": {
                _d params [["_t0", time], ["_pos", [0, 0, 0, 0]]];
                private _g = 1 + ((((time - _t0) / 60) * 0.09) min 1.3);
                _pos params ["_bx", "_by", "_bw", "_bh"];
                _c ctrlShow !_off;
                _c ctrlSetPosition [_bx - _bw * (_g - 1) / 2, _by - _bh * (_g - 1) / 2, _bw * _g, _bh * _g];
                _c ctrlCommit 1;
            };
        };
    } forEach _ctrls;
    true
};
_this params ["", ["_layer", "glass"], ["_faults", []], ["_rect", [0, 0, 1, 1]], ["_ratio", 1], ["_land", false], ["_ctrls", []], ["_pic", {}], ["_box", {}]];
_rect params ["_rx", "_ry", "_rw", "_rh"];
private _short = _rh min (_rw * _ratio);
private _long = _rh max (_rw * _ratio);
private _turn = [0, -90] select _land;
private _cat = ["catalog"] call comspec_atak_native_fnc_screenDamage;
private _data = { (_ctrls select ((count _ctrls) - 1)) pushBack _this; };
// Repère du verre -> point de l'écran.
private _at = {
    params ["_gx", "_gy"];
    if (_land) then { [_rx + _rw * _gy, _ry + _rh * (1 - _gx)] } else { [_rx + _rw * _gx, _ry + _rh * _gy] }
};
// Carré de côté _s (unités verticales) centré, gardé dans l'écran.
private _square = {
    params ["_cx", "_cy", "_s"];
    private _w = _s / _ratio;
    [(_cx - _w / 2) max _rx min (_rx + _rw - _w), (_cy - _s / 2) max _ry min (_ry + _rh - _s), _w, _s]
};
{
    if ((count _ctrls) >= 90) exitWith {};
    _x params ["_id", "_var", "_gx", "_gy", "_sev", "_ang", "_t0", ["_draw", 1]];
    private _i = _cat findIf { (_x select 0) isEqualTo _id };
    if (_i < 0) then { continue };
    (_cat select _i) params ["", "_fam", "", "", "", "_tpl", "_vars"];
    if (_fam isNotEqualTo _layer) then { continue };
    _sev = (round _sev) max 1 min 3;
    _var = _var mod (_vars max 1);
    // Tirages propres à la panne (positions des pixels, des lignes) : fixes pour toujours.
    private _kk = 0;
    private _rnd = { _kk = _kk + 1; private _v = (sin (_draw * 12.9898 + _kk * 78.233)) * 43758.5; _v - floor _v };
    ([_gx, _gy] call _at) params ["_cx", "_cy"];
    switch (_tpl) do {
        case "point": {
            // Quart de tour qui place l'impact (0,3 ; 0,3) du bon côté de l'écran ; le carré couvre toute la largeur
            // du verre, les fêlures courent donc jusqu'à ses bords.
            private _q = if (_gx < 0.5) then { [0, 3] select (_gy >= 0.5) } else { [1, 2] select (_gy >= 0.5) };
            ([[-0.2, -0.2], [0.2, -0.2], [0.2, 0.2], [-0.2, 0.2]] select _q) params ["_ox", "_oy"];
            private _dx = [_ox, _oy] select _land;
            private _dy = [_oy, -_ox] select _land;
            private _p = [_cx - _dx * _short / _ratio, _cy - _dy * _short, _short] call _square;
            private _t = format ["gx_%1_%2_%3", _id, _var, _sev] call _tex;
            ["sfg", _t, (_p select 0) + pixelW * 1.5, (_p select 1) + pixelH, _p select 2, _p select 3, 90 * _q + _turn] call _pic;
            ["sfG", _t, _p select 0, _p select 1, _p select 2, _p select 3, 90 * _q + _turn] call _pic;
        };
        case "corner": {
            private _c = if (_gx < 0.5) then { [0, 3] select (_gy >= 0.5) } else { [1, 2] select (_gy >= 0.5) };
            (([[0, 0], [1, 0], [1, 1], [0, 1]] select _c) call _at) params ["_px", "_py"];
            private _s = _short * ([0.95, 0.8] select (_id isEqualTo "peel"));
            private _w = _s / _ratio;
            private _x0 = [_rx, _rx + _rw - _w] select (_px > _rx + _rw / 2);
            private _y0 = [_ry, _ry + _rh - _s] select (_py > _ry + _rh / 2);
            if (_id isEqualTo "peel") then {
                ["sfS", "gx_peel_0" call _tex, _x0, _y0, _w, _s, 90 * _c + _turn] call _pic;
                [_sev] call _data;
            } else {
                private _t = format ["gx_%1_%2_%3", _id, _var, _sev] call _tex;
                ["sfg", _t, _x0 + pixelW * 1.5, _y0 + pixelH, _w, _s, 90 * _c + _turn] call _pic;
                ["sfG", _t, _x0, _y0, _w, _s, 90 * _c + _turn] call _pic;
            };
        };
        case "strip": {
            // Bande 1 x 2 centrée : cadre à la forme de la texture, tourné avec le verre en paysage.
            private _h = _long min (2 * _short);
            private _w = _h / 2 / _ratio;
            private _t = format ["gx_%1_%2_%3", _id, _var, _sev] call _tex;
            private _x0 = _rx + (_rw - _w) / 2;
            private _y0 = _ry + (_rh - _h) / 2;
            ["sfg", _t, _x0 + pixelW * 1.5, _y0 + pixelH, _w, _h, _turn] call _pic;
            ["sfG", _t, _x0, _y0, _w, _h, _turn] call _pic;
        };
        case "free": {
            private _s = _short * (createHashMapFromArray [["press", 0.98], ["abrade", 0.95], ["water", 0.68 + 0.11 * _sev], ["heat", 0.45 + 0.08 * _sev]] getOrDefault [_id, 0.9]);
            private _p = [_cx, _cy, _s] call _square;
            switch (_id) do {
                case "press": {
                    private _t = format ["gx_press_%1_%2", _var, _sev] call _tex;
                    ["sfg", _t, (_p select 0) + pixelW * 1.5, (_p select 1) + pixelH, _p select 2, _p select 3, _ang + _turn] call _pic;
                    ["sfG", _t, _p select 0, _p select 1, _p select 2, _p select 3, _ang + _turn] call _pic;
                };
                case "abrade": { ["sfA", format ["gx_abrade_%1", _var] call _tex, _p select 0, _p select 1, _p select 2, _p select 3, _ang + _turn] call _pic; [_sev] call _data; };
                case "water": { ["sfW", format ["gx_water_%1", _var] call _tex, _p select 0, _p select 1, _p select 2, _p select 3, _ang + _turn] call _pic; [_sev, +_p] call _data; };
                default { ["sfS", "gx_heat_0" call _tex, _p select 0, _p select 1, _p select 2, _p select 3, _ang + _turn] call _pic; [_sev] call _data; };
            };
        };
        default {
            // Dalle. Taille d'un « pixel » visible : 2 px d'écran au moins, 3 ou 4 en 4K.
            private _px = (round ((_short / pixelH) / 300)) max 2;
            private _pw = pixelW * _px;
            private _ph = pixelH * _px;
            private _col = { [[0, 0, 0, 1], [1, 0.06, 0.06, 1], [0.06, 1, 0.12, 1], [0.12, 0.22, 1, 1], [1, 1, 1, 1]] select _this };
            // Ligne le long d'une colonne ou d'une rangée de la dalle (repère du verre : la colonne devient rangée en paysage).
            private _line = {
                params ["_kind", "_isCol", "_g", "_c4", ["_thick", 1]];
                if (_isCol isNotEqualTo _land) then {
                    [_kind, _rx + _rw * _g, _ry, _pw * _thick, _rh, _c4] call _box;
                } else {
                    [_kind, _rx, _ry + _rh * ([_g, 1 - _g] select (_land && _isCol)), _rw, _ph * _thick, _c4] call _box;
                };
            };
            switch (_id) do {
                case "deadpx"; case "stuckpx": {
                    for "_j" from 1 to (2 + 2 * _sev) do {
                        ([0.04 + 0.92 * call _rnd, 0.04 + 0.92 * call _rnd] call _at) params ["_qx", "_qy"];
                        ["sfP", _qx, _qy, _pw, _ph, ([0, 1 + floor ((call _rnd) * 4)] select (_id isEqualTo "stuckpx")) call _col] call _box;
                    };
                };
                case "cluster": {
                    for "_j" from 1 to (8 + 6 * _sev) do {
                        // Nuage serré autour de l'impact (somme de deux tirages : dense au centre).
                        private _ddx = ((call _rnd) + (call _rnd) - 1) * (10 + 6 * _sev);
                        private _ddy = ((call _rnd) + (call _rnd) - 1) * (10 + 6 * _sev);
                        private _m = 1 + floor ((call _rnd) * 1.4);
                        ["sfP", _cx + pixelW * _ddx, _cy + pixelH * _ddy, _pw * _m, _ph * _m, ([0, 0, 0, 2, 4] select (floor ((call _rnd) * 5))) call _col] call _box;
                    };
                };
                case "deadline": { ["sfP", (call _rnd) < 0.7, _gx, [[0.1, 1, 0.3, 0.95], [1, 0.1, 0.9, 0.95], [0, 0, 0, 1], [0.95, 0.95, 0.95, 0.85]] select (floor ((call _rnd) * 4))] call _line; };
                case "lines": {
                    for "_j" from 1 to (2 + _sev) do {
                        ["sfP", (call _rnd) < 0.7, 0.05 + 0.9 * call _rnd, [[0.1, 1, 0.3, 0.9], [1, 0.1, 0.9, 0.95], [0, 0, 0, 1], [0.2, 0.6, 1, 0.9], [1, 1, 1, 0.8]] select (floor ((call _rnd) * 5)), 1 + floor ((call _rnd) * 1.3)] call _line;
                    };
                };
                case "flickline": {
                    ["sfF", false, _gy, [1, 1, 1, 0.85]] call _line;
                    ["sfF", false, _gy + 0.004, [0, 0, 0, 0.6]] call _line;
                };
                case "band": {
                    // Texture verticale (colonnes) : tournée de 90° en paysage, cadre aux côtés échangés.
                    private _t = format ["fx_band_%1", _var] call _tex;
                    if (_land) then {
                        private _bh = _rh * (0.04 + 0.02 * _sev);
                        ["sfP", _t, _rx + (_rw - _bh / _ratio) / 2, (_ry + _bh / 2 + (_rh - _bh) * (1 - _gx)) - _rw * _ratio / 2, _bh / _ratio, _rw * _ratio, 90] call _pic;
                    } else {
                        private _bw = _rw * (0.04 + 0.02 * _sev);
                        ["sfP", _t, _rx + (_rw - _bw) * _gx, _ry, _bw, _rh] call _pic;
                    };
                };
                case "blot": {
                    private _p = [_cx, _cy, _short * (0.12 + 0.07 * _sev)] call _square;
                    ["sfB", format ["fx_bleed_%1", _var] call _tex, _p select 0, _p select 1, _p select 2, _p select 3, _ang] call _pic;
                    [_t0, +_p] call _data;
                };
                case "burnin": {
                    // Plein écran dans le repère du verre : cadre portrait, tourné en paysage.
                    private _t = "pf_burn_0" call _tex;
                    if (_land) then {
                        ["sfP", _t, _rx + (_rw - _rh / _ratio) / 2, _ry + (_rh - _rw * _ratio) / 2, _rh / _ratio, _rw * _ratio, -90] call _pic;
                    } else {
                        ["sfP", _t, _rx, _ry, _rw, _rh] call _pic;
                    };
                    ((_ctrls select ((count _ctrls) - 1)) select 1) ctrlSetTextColor [1, 1, 1, 0.12 + 0.06 * _sev];
                };
                case "edgeleak": {
                    ["sfP", format ["fx_backlight_%1_%2", _var, ["port", "land"] select _land] call _tex, _rx, _ry, _rw, _rh] call _pic;
                    ((_ctrls select ((count _ctrls) - 1)) select 1) ctrlSetTextColor [1, 0.98, 0.92, 0.2 + 0.12 * _sev];
                };
                case "dimzone": {
                    private _p = [_cx, _cy, _short * 0.95] call _square;
                    ["sfP", "pf_dim_0" call _tex, _p select 0, _p select 1, _p select 2, _p select 3, _ang] call _pic;
                    ((_ctrls select ((count _ctrls) - 1)) select 1) ctrlSetTextColor [1, 1, 1, 0.35 + 0.15 * _sev];
                };
                case "tint": { ["sfP", _rx, _ry, _rw, _rh, [[0.1, 0.85, 0.3, 0.05 + 0.03 * _sev], [0.9, 0.1, 0.7, 0.05 + 0.03 * _sev], [0.95, 0.8, 0.2, 0.06 + 0.03 * _sev]] select _var] call _box; };
                case "touchdead": {
                    // Rectangle du verre où les appuis ne passent plus (mêmes mesures que fn_screenDamage « deadAt »).
                    private _hw = 0.22;
                    private _hh = 0.05 + 0.03 * _sev;
                    ([_gx - _hw, _gy - _hh] call _at) params ["_ax", "_ay"];
                    ([_gx + _hw, _gy + _hh] call _at) params ["_bx", "_by"];
                    private _zr = [_ax min _bx, _ay min _by, abs (_bx - _ax), abs (_by - _ay)];
                    _zr set [0, (_zr select 0) max _rx];
                    _zr set [1, (_zr select 1) max _ry];
                    _zr set [2, (_zr select 2) min (_rx + _rw - (_zr select 0))];
                    _zr set [3, (_zr select 3) min (_ry + _rh - (_zr select 1))];
                    private _imp = ["impact"] call comspec_atak_native_fnc_screenDamage;
                    // Indice discret (voile à peine visible), sauf en Milsim : là, on la découvre en appuyant.
                    if (_imp < 2) then { ["sfP", _zr select 0, _zr select 1, _zr select 2, _zr select 3, [0.75, 0.85, 1, 0.045]] call _box; };
                    if (_imp >= 1) then {
                        private _z = ["COMSPEC_RscButtonInvisible", _zr, "", false] call comspec_atak_native_fnc_pageCtrl;
                        _ctrls pushBack ["sfZ", _z];
                    };
                };
            };
        };
    };
} forEach _faults;
true
