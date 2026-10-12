/*
    Surface de l'écran, par-dessus l'app et les fêlures (appelé à la fin de fn_deviceOverlay, chaque seconde) :
      - dégâts : rayures (fines lignes claires), pixels morts (points fixes), lignes verticales colorées ;
      - saleté (fn_screenDirt, réglage serveur comspec_atak_native_dirt_sim et réglage Réalisme du joueur) :
        voile de poussière, traces de doigts, traînées et gouttes de sang sur l'écran, sang sur la coque, gouttes de pluie.
    Disposition tirée d'une graine propre à l'appareil (COMSPEC_ATAK_Device dirtSeed) : même dessin d'une seconde à l'autre,
    les contrôles ne sont recréés que si le nombre de taches change ; sinon seule l'opacité suit l'état.
    Contrôles de page hors zone de contenu, inactifs (les clics passent au travers). Écran éteint : pixels et lignes cachés.
    Params : [état matériel (fn_deviceHealth)]
*/
params [["_hp", createHashMap]];
disableSerialization;
private _d = [] call comspec_atak_native_fnc_display;
if (isNull _d) exitWith { false };
private _l = [] call comspec_atak_native_fnc_layoutGet;
private _rect = _l get "device";
private _phone = _l get "phone";
private _n = missionNamespace getVariable ["COMSPEC_ATAK_Device", createHashMap];
private _parts = _hp getOrDefault ["parts", createHashMap];
private _dirt = ["enabled"] call comspec_atak_native_fnc_screenDirt;
private _g = { params ["_k"]; if (_dirt) then { (_n getOrDefault [_k, 0]) max 0 min 1 } else { 0 } };
private _dust = ["dust"] call _g;
private _prints = ["prints"] call _g;
private _blood = ["blood"] call _g;
private _frame = ["frameBlood"] call _g;
private _drops = ["drops"] call _g;
private _scratch = _parts getOrDefault ["scratch", 0];
private _pix = _parts getOrDefault ["pixels", 0];
private _counts = [
    parseNumber (_dust > 0.08),
    ceil (_prints * 6), ceil (_blood * 5), ceil (_frame * 4), round (_drops * 12),
    ceil (_scratch * 8), ceil (_pix * 14), [0, 1 + floor ((_pix - 0.5) * 4)] select (_pix >= 0.5)
];
private _seed = _n getOrDefault ["dirtSeed", 1];
private _sig = format ["%1|%2|%3|%4|%5", _counts, _seed, _rect, _phone, _l get "model"];
(uiNamespace getVariable ["COMSPEC_ATAK_SurfOverlay", ["", []]]) params ["_oldSig", "_ctrls"];
if (_sig isNotEqualTo _oldSig || {(_ctrls findIf { isNull (_x select 1) }) >= 0}) then {
    { ctrlDelete (_x select 1); } forEach _ctrls;
    _ctrls = [];
    _rect params ["_rx", "_ry", "_rw", "_rh"];
    _phone params ["_px", "_py", "_pw", "_ph"];
    private _ratio = pixelH / pixelW;
    private _k = 0;
    private _rnd = { _k = _k + 1; private _v = (sin (_seed * 0.731 + _k * 97.13)) * 4375.85; _v - floor _v };
    private _tex = { format ["\z\comspec_atak_native\addons\main\data\%1.paa", _this] };
    private _pic = {
        params ["_kind", "_t", "_cx", "_cy", "_w", "_h", ["_a", 0]];
        private _c = ["COMSPEC_RscPhone", [_cx, _cy, _w, _h], _t, false] call comspec_atak_native_fnc_pageCtrl;
        _c ctrlEnable false;
        if (_a isNotEqualTo 0) then { _c ctrlSetAngle [_a, 0.5, 0.5]; };
        _ctrls pushBack [_kind, _c];
    };
    private _box = {
        params ["_kind", "_cx", "_cy", "_w", "_h", "_col"];
        private _c = ["COMSPEC_RscText", [_cx, _cy, _w, _h], "", false] call comspec_atak_native_fnc_pageCtrl;
        _c ctrlEnable false;
        _c ctrlSetBackgroundColor _col;
        _ctrls pushBack [_kind, _c];
    };
    _counts params ["_cDust", "_cPrints", "_cBlood", "_cFrame", "_cDrops", "_cScratch", "_cPix", "_cLines"];
    // Pixels morts et lignes (sous la saleté, qui est sur le verre). Visibles à l'écran du jeu : amas de pixels de
    // 2 à 6 px plutôt qu'un point isolé, taches noires (dalle morte) et lignes de 2 à 4 px quand l'écran est très abîmé.
    for "_i" from 1 to _cPix do {
        private _col = [[0, 0, 0, 1], [0, 0, 0, 1], [0.95, 0.1, 0.9, 1], [0.1, 0.95, 0.3, 1], [1, 1, 1, 1]] select (floor ((call _rnd) * 5));
        private _cx = _rx + _rw * (call _rnd);
        private _cy = _ry + _rh * (call _rnd);
        for "_j" from 1 to (1 + floor ((call _rnd) * 4)) do {
            private _s = 2 + floor ((call _rnd) * 5);
            ["pix", _cx + pixelW * 8 * ((call _rnd) - 0.5), _cy + pixelH * 8 * ((call _rnd) - 0.5), pixelW * _s, pixelH * _s, _col] call _box;
        };
    };
    if (_pix >= 0.35) then {
        for "_i" from 1 to (1 + floor ((_pix - 0.35) * 5)) do {
            private _h = _rh * (0.04 + 0.08 * call _rnd);
            private _w = _h * (0.6 + 0.8 * call _rnd) / _ratio;
            ["pix", _rx + (_rw - _w) * (call _rnd), _ry + (_rh - _h) * (call _rnd), _w, _h, [0, 0, 0, 0.94]] call _box;
        };
    };
    for "_i" from 1 to _cLines do {
        private _col = [[0.1, 0.95, 0.35, 0.9], [0.95, 0.15, 0.85, 0.9], [0.9, 0.9, 0.9, 0.75], [0, 0, 0, 0.95]] select (floor ((call _rnd) * 4));
        if (call _rnd < 0.7) then {
            ["pix", _rx + _rw * (0.05 + 0.9 * call _rnd), _ry, pixelW * (2 + floor ((call _rnd) * 3)), _rh, _col] call _box;
        } else {
            ["pix", _rx, _ry + _rh * (0.05 + 0.9 * call _rnd), _rw, pixelH * (2 + floor ((call _rnd) * 2)), _col] call _box;
        };
    };
    // Rayures : traits fins et clairs, en biais.
    for "_i" from 1 to _cScratch do {
        private _len = _rh * (0.05 + 0.25 * call _rnd);
        private _w = _len / _ratio;
        ["scratch", "#(argb,8,8,3)color(1,1,1,1)", _rx + (_rw - _w) * (call _rnd), _ry + _rh * (0.05 + 0.9 * call _rnd), _w, pixelH, 360 * call _rnd] call _pic;
    };
    if (_cDust > 0) then {
        ["dust", format ["dirt_dust_%1", _seed mod 2] call _tex, _rx, _ry, _rw, _rh] call _pic;
        // Coque aussi : poussière plus dense contre les arêtes, même cadre que la texture du modèle.
        ["caseDust", format ["dirt_case_%1_%2", _l getOrDefault ["model", "s7"], ["port", "land"] select (_l getOrDefault ["landscape", true])] call _tex, _px, _py, _pw, _ph] call _pic;
    };
    for "_i" from 1 to _cPrints do {
        // Pouce : ovale allongé (texture 128x256, ovale ~200 px de haut), un peu incliné vers la main qui tient le téléphone.
        private _h = _rh * (0.17 + 0.09 * call _rnd);
        private _w = _h * 0.5 / _ratio;
        // Surtout là où l'on touche : barre latérale, boutons du bas, milieu ; rarement en haut.
        private _zx = selectRandomWeighted [0.04, 3, 0.5, 2, 0.85, 3, call _rnd, 1];
        private _zy = 0.35 + 0.6 * call _rnd;
        private _side = [1, -1] select (_zx < 0.5);
        ["prints", format ["dirt_print_%1", floor ((call _rnd) * 4)] call _tex, _rx + (_rw - _w) * _zx, _ry + (_rh - _h) * _zy, _w, _h, _side * (10 + 35 * call _rnd)] call _pic;
    };
    for "_i" from 1 to _cBlood do {
        private _h = _rh * (0.07 + 0.06 * call _rnd);
        private _w = 2 * _h / _ratio;
        if (call _rnd < 0.7) then {
            ["blood", format ["dirt_smear_%1", floor ((call _rnd) * 3)] call _tex, _rx + (_rw - _w) * (call _rnd), _ry + (_rh - _h) * (call _rnd), _w, _h, (call _rnd) * 360] call _pic;
        } else {
            private _s = _h * 1.2;
            ["blood", "dirt_splat" call _tex, _rx + (_rw - _s / _ratio) * (call _rnd), _ry + (_rh - _s) * (call _rnd), _s / _ratio, _s, (call _rnd) * 360] call _pic;
        };
    };
    // Sang sur la coque : sur le rebord autour de l'écran (la texture du téléphone est carrée et en grande partie
    // transparente : on se cale sur l'écran, pas sur le cadre de la texture, sinon le sang flotte dans le vide).
    private _bx = _rw * 0.055;
    private _by = _rh * 0.075;
    for "_i" from 1 to _cFrame do {
        private _side = floor ((call _rnd) * 4);
        private _h = _rh * (0.05 + 0.04 * call _rnd);
        private _w = 2 * _h / _ratio;
        // Côtés gauche et droit : traînée verticale (image tournée de 90°, cadre carré de la longueur de la traînée).
        private _fx = [_rx - _bx - _w / 2, _rx + _rw + _bx - _w / 2, _rx + (_rw - _w) * (call _rnd), _rx + (_rw - _w) * (call _rnd)] select _side;
        private _fy = [_ry + (_rh - _h) * (call _rnd), _ry + (_rh - _h) * (call _rnd), _ry - _by - _h / 2, _ry + _rh + _by - _h / 2] select _side;
        ["frame", format ["dirt_smear_%1", floor ((call _rnd) * 3)] call _tex, _fx, _fy, _w, _h, [90, 90, 0, 0] select _side] call _pic;
    };
    for "_i" from 1 to _cDrops do {
        private _s = _rh * (0.012 + 0.022 * call _rnd);
        ["drops", "dirt_drop" call _tex, _rx + (_rw - _s / _ratio) * (call _rnd), _ry + (_rh - _s) * (call _rnd), _s / _ratio, _s] call _pic;
    };
};
// Opacité selon l'état (chaque seconde, sans recréer).
private _off = (_hp getOrDefault ["state", "OK"]) isEqualTo "OFF";
{
    _x params ["_kind", "_c"];
    switch (_kind) do {
        case "pix": { _c ctrlShow !_off; };
        case "scratch": { _c ctrlSetTextColor [0.9, 0.95, 1, 0.14 + 0.12 * _scratch]; };
        // Voile progressif : à peine visible au début, épais seulement après une longue exposition.
        case "dust": { _c ctrlSetTextColor [0.6, 0.53, 0.4, (0.05 + 0.7 * _dust) min 0.75]; };
        case "caseDust": { _c ctrlSetTextColor [0.55, 0.48, 0.36, (0.1 + 0.85 * _dust) min 0.9]; };
        case "prints": { _c ctrlSetTextColor [0.82, 0.82, 0.76, 0.1 + 0.25 * _prints]; };
        case "blood": { _c ctrlSetTextColor [0.4, 0.02, 0.02, 0.5 + 0.4 * _blood]; };
        case "frame": { _c ctrlSetTextColor [0.33, 0.02, 0.02, 0.55 + 0.4 * _frame]; };
        case "drops": { _c ctrlSetTextColor [0.85, 0.92, 1, 0.35 + 0.3 * _drops]; };
    };
} forEach _ctrls;
uiNamespace setVariable ["COMSPEC_ATAK_SurfOverlay", [_sig, _ctrls]];
if ((count _ctrls) > 0) then {
    uiNamespace setVariable ["COMSPEC_ATAK_DevFront", true];
    [] call comspec_atak_native_fnc_overlayFront;
};
true
