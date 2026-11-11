/*
    Surface de l'écran, par-dessus l'app et les fêlures (appelé à la fin de fn_deviceOverlay, chaque seconde) :
      - dégâts : rayures (fines lignes claires), pixels morts (points fixes), lignes verticales colorées ;
      - saleté (fn_screenDirt, réglage serveur comspec_atak_native_dirt_sim et réglage Réalisme du joueur) :
        voile de poussière, traces de doigts, traînées et gouttes de sang sur l'écran, sang sur la coque, gouttes de pluie ;
      - verre (4.2.1) : reflet discret de la vitre (désactivable) et coins arrondis de l'écran, propres à chaque coque.
    Disposition tirée d'une graine propre à l'appareil (COMSPEC_ATAK_Device dirtSeed) : même dessin d'une seconde à l'autre,
    les contrôles ne sont recréés que si le nombre de taches change ; sinon seule l'opacité suit l'état. Chaque genre et
    chaque tache a sa propre suite de tirages : une trace de doigt de plus ne déplace plus les autres.
    Sang (1.97.0) : taches gardées dans COMSPEC_ATAK_Device "stains" (fn_screenDirt), dessinées à leur place enregistrée ;
    taille en part du petit côté de l'écran, tournées avec le verre si l'orientation a changé, version séchée (bloodd_*)
    échangée sans recréer le contrôle.
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
private _frame = ["frameBlood"] call _g;
private _drops = ["drops"] call _g;
private _scratch = _parts getOrDefault ["scratch", 0];
private _pix = _parts getOrDefault ["pixels", 0];
// Gouttes de pluie (refaites : lentilles d'eau de tools/gen_screen_damage.py, posées par plaques, jamais en pavage) et
// boue ; pannes du modèle fn_screenDamage (verre et dalle), dessinées par fn_screenFaults.
private _mud = ["mud"] call _g;
private _faults = ["list"] call comspec_atak_native_fnc_screenDamage;
private _wetNow = _dirt && {(_n getOrDefault ["wetUntil", -1]) > time};
private _stains = if (_dirt) then { _n getOrDefault ["stains", []] } else { [] };
private _counts = [
    parseNumber (_dust > 0.08),
    ceil (_prints * 6), count _stains, ceil (_frame * 4), [0, 1 + parseNumber (_drops > 0.5)] select (_drops > 0.12), ceil (_mud * 3),
    ceil (_scratch * 8), ceil (_pix * 14), [0, 1 + floor ((_pix - 0.5) * 4)] select (_pix >= 0.5)
];
// Verre (4.2.1) : reflet de la vitre (réglage CBA comspec_atak_native_glass_glare, aussi dans Réglages > Thème et écran)
// et coins arrondis de l'écran. Écran de la tablette du JPC (texture) : le modèle 3D a son propre verre.
private _tex3d = _l getOrDefault ["texture", false];
private _glare = !_tex3d && {(missionNamespace getVariable ["comspec_atak_native_glass_glare", true]) isEqualTo true};
// Coins de l'ouverture de chaque coque, mesurés sur ses textures (tools/gen_glass.py) : rayon par coin en part du côté
// de la texture [haut gauche, haut droit, bas droit, bas gauche] du paysage, gris du cadre qui borde le coin, de jour
// puis de nuit (0 à 255). S7 (et ses teintes) : ouverture à coins droits, rien à poser. Tablette à touches = tablette.
private _corner = if (_tex3d) then { [] } else {
    (createHashMapFromArray [
        ["rugged", [[0.015, 0.015, 0.015, 0.015], [4, 8, 36, 27], [0, 2, 10, 8]]],
        ["iphone", [[0.0067, 0.0067, 0.005, 0.0067], [45, 35, 57, 60], [11, 8, 16, 16]]],
        ["tablet", [[0.0133, 0.0117, 0.01, 0.01], [21, 21, 24, 24], [5, 5, 6, 7]]],
        ["keys", [[0.0133, 0.0117, 0.01, 0.01], [21, 21, 24, 24], [5, 5, 6, 7]]],
        ["sonim", [[0.02, 0.0184, 0.02, 0.0117], [21, 18, 19, 27], [5, 5, 5, 7]]],
        ["s23", [[0.0067, 0.0083, 0.0083, 0.0067], [35, 27, 33, 51], [8, 7, 8, 14]]],
        ["ip17", [[0, 0.0167, 0.0167, 0], [5, 29, 71, 5], [1, 8, 19, 0]]]
    ]) getOrDefault [_l getOrDefault ["model", "s7"], []]
};
private _seed = _n getOrDefault ["dirtSeed", 1];
private _sig = format ["%1|%2|%3|%4|%5|%6|%7|%8|%9|%10", _counts, _seed, _rect, _phone, _l get "model", _stains apply { [_x select 0, _x select 1, round ((_x select 2) * 200), round ((_x select 3) * 200)] }, _glare, _l get "phoneTexture",
    _faults apply { [_x select 0, _x select 1, _x select 4, round ((_x select 2) * 200), round ((_x select 3) * 200)] }, ["impact"] call comspec_atak_native_fnc_screenDamage];
(uiNamespace getVariable ["COMSPEC_ATAK_SurfOverlay", ["", []]]) params ["_oldSig", "_ctrls"];
if (_sig isNotEqualTo _oldSig || {(_ctrls findIf { isNull (_x select 1) }) >= 0}) then {
    // Anciens contrôles supprimés après la création des nouveaux (fin du bloc) : pas d'image sans reflet ni coins.
    private _oldCtrls = _ctrls;
    _ctrls = [];
    _rect params ["_rx", "_ry", "_rw", "_rh"];
    _phone params ["_px", "_py", "_pw", "_ph"];
    private _ratio = (uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW]);
    private _k = 0;
    private _rnd = { _k = _k + 1; private _v = (sin (_seed * 0.731 + _k * 97.13)) * 4375.85; _v - floor _v };
    // Suite de tirages propre à un genre et à un numéro de tache (rien ne bouge quand une autre tache apparaît).
    private _from = { params ["_cat", "_i"]; _k = _cat * 1000 + _i * 23; };
    private _tex = { format ["\z\comspec_atak_native\addons\main\data\%1.paa", _this] };
    // Nom de la texture fraîche d'une tache (la version séchée : même nom en bloodd_).
    private _bloodTex = {
        params ["_kind", "_var", "_sl"];
        switch (_kind) do {
            case "print": { format ["bloodc_print_%1", _var mod 4] };
            case "cover": { format ["bloodc_cover_%1_%2", _var mod 2, ["port", "land"] select _sl] };
            case "veil": { format ["bloodc_veil_0_%1", ["port", "land"] select _sl] };
            default { format ["bloodc_%1_%2", _kind, _var mod ((createHashMapFromArray [["smear", 3], ["splat", 4], ["hand", 4], ["spatter", 3], ["pool", 4], ["run", 3], ["wipe", 3], ["puddle", 1]]) getOrDefault [_kind, 2])] };
        };
    };
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
    _counts params ["_cDust", "_cPrints", "_cBlood", "_cFrame", "_cDrops", "_cMud", "_cScratch", "_cPix", "_cLines"];
    // Ordre des calques, du fond vers l'œil (fn_screenDamage « layers ») : dalle, verre, liquides et saleté, sang,
    // reflet, coins. Les pannes de dalle sont sous le verre : dessinées en premier.
    ["build", "panel", _faults, _rect, _ratio, _l getOrDefault ["landscape", true], _ctrls, _pic, _box] call comspec_atak_native_fnc_screenFaults;
    // Pixels morts et lignes (sous la saleté, qui est sur le verre). Visibles à l'écran du jeu : amas de pixels de
    // 2 à 6 px plutôt qu'un point isolé, taches noires (dalle morte) et lignes de 2 à 4 px quand l'écran est très abîmé.
    for "_i" from 1 to _cPix do {
        [1, _i] call _from;
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
            [2, _i] call _from;
            private _h = _rh * (0.04 + 0.08 * call _rnd);
            private _w = _h * (0.6 + 0.8 * call _rnd) / _ratio;
            ["pix", _rx + (_rw - _w) * (call _rnd), _ry + (_rh - _h) * (call _rnd), _w, _h, [0, 0, 0, 0.94]] call _box;
        };
    };
    for "_i" from 1 to _cLines do {
        [3, _i] call _from;
        private _col = [[0.1, 0.95, 0.35, 0.9], [0.95, 0.15, 0.85, 0.9], [0.9, 0.9, 0.9, 0.75], [0, 0, 0, 0.95]] select (floor ((call _rnd) * 4));
        if (call _rnd < 0.7) then {
            ["pix", _rx + _rw * (0.05 + 0.9 * call _rnd), _ry, pixelW * (2 + floor ((call _rnd) * 3)), _rh, _col] call _box;
        } else {
            ["pix", _rx, _ry + _rh * (0.05 + 0.9 * call _rnd), _rw, pixelH * (2 + floor ((call _rnd) * 2)), _col] call _box;
        };
    };
    // Rayures : traits fins et clairs, en biais.
    for "_i" from 1 to _cScratch do {
        [4, _i] call _from;
        private _len = _rh * (0.05 + 0.25 * call _rnd);
        private _w = _len / _ratio;
        ["scratch", "#(argb,8,8,3)color(1,1,1,1)", _rx + (_rw - _w) * (call _rnd), _ry + _rh * (0.05 + 0.9 * call _rnd), _w, pixelH, 360 * call _rnd] call _pic;
    };
    // Bris de verre : au-dessus de la dalle, sous la saleté et le sang (qui sont posés sur le verre).
    ["build", "glass", _faults, _rect, _ratio, _l getOrDefault ["landscape", true], _ctrls, _pic, _box] call comspec_atak_native_fnc_screenFaults;
    if (_cDust > 0) then {
        ["dust", format ["dirt_dust_%1", _seed mod 2] call _tex, _rx, _ry, _rw, _rh] call _pic;
        // Coque aussi : poussière plus dense contre les arêtes, même cadre que la texture du modèle.
        // Tablette à touches : mêmes coques que la tablette, donc même poussière (4.2.1, textures en double retirées).
        private _dm = _l getOrDefault ["model", "s7"];
        if (_dm isEqualTo "keys") then { _dm = "tablet"; };
        ["caseDust", format ["dirt_case_%1_%2", _dm, ["port", "land"] select (_l getOrDefault ["landscape", true])] call _tex, _px, _py, _pw, _ph] call _pic;
    };
    for "_i" from 1 to _cPrints do {
        [5, _i] call _from;
        // Pouce : ovale allongé (texture 128x256, ovale ~200 px de haut), un peu incliné vers la main qui tient le téléphone.
        private _h = _rh * (0.17 + 0.09 * call _rnd);
        private _w = _h * 0.5 / _ratio;
        // Surtout là où l'on touche : barre latérale, boutons du bas, milieu ; rarement en haut.
        private _zx = selectRandomWeighted [0.04, 3, 0.5, 2, 0.85, 3, call _rnd, 1];
        private _zy = 0.35 + 0.6 * call _rnd;
        private _side = [1, -1] select (_zx < 0.5);
        ["prints", format ["dirt_print_%1", floor ((call _rnd) * 4)] call _tex, _rx + (_rw - _w) * _zx, _ry + (_rh - _h) * _zy, _w, _h, _side * (10 + 35 * call _rnd)] call _pic;
    };
    // Taches de sang, chacune à sa place enregistrée (centre x / y en part de l'écran, taille en part du petit côté :
    // même taille réelle en portrait et en paysage, en main et en miniature). Une tache posée dans l'autre orientation
    // tourne avec le verre (paysage -> portrait : quart de tour horaire). Ordre : voile, couche continue, nappes,
    // traînées d'essuyage, le reste, coulures par-dessus.
    private _short = _rh min (_rw * _ratio);
    private _land = _l getOrDefault ["landscape", true];
    // Boue : éclaboussures épaisses, posées une fois (graine de l'appareil), sous le sang.
    for "_i" from 1 to _cMud do {
        [8, _i] call _from;
        private _s = _short * (0.3 + 0.3 * call _rnd);
        private _w = _s / _ratio;
        ["mud", format ["dirt_mud_%1", _i mod 2] call _tex, _rx + (_rw - _w) * (call _rnd), _ry + (_rh - _s) * (call _rnd), _w, _s, 360 * call _rnd] call _pic;
    };
    private _layer = createHashMapFromArray [["veil", 0], ["cover", 1], ["pool", 2], ["wipe", 3], ["seep", 4], ["run", 5], ["puddle", 6]];
    private _order = [];
    { _order pushBack [_layer getOrDefault [_x select 0, 4], _forEachIndex]; } forEach _stains;
    _order sort true;
    {
        private _i = _x select 1;
        (_stains select _i) params ["_kind", "_var", "_sx", "_sy", "_size", "_ang", "", "", ["_sl", _land]];
        private _turn = 0;
        if (_sl isNotEqualTo _land) then {
            if (_land) then { private _t = _sx; _sx = _sy; _sy = 1 - _t; _turn = -90; } else { private _t = _sx; _sx = 1 - _sy; _sy = _t; _turn = 90; };
        };
        // Textures en couleur (tools/gen_screen_fx.py) : film mince rouge vif et translucide, cœur bordeaux, bord séché,
        // reflets humides ; version séchée (bloodd_*) quand le sang a séché (plus bas, sans recréer le contrôle).
        private _base = [_kind, _var, _sl] call _bloodTex;
        if (_kind in ["cover", "veil"]) then {
            // Plein écran, texture de l'orientation où la tache a été posée ; autre orientation : tournée d'un quart.
            if (_turn isEqualTo 0) then {
                [format ["stain%1", _i], _base call _tex, _rx, _ry, _rw, _rh] call _pic;
            } else {
                private _w = _rh / _ratio;
                private _h = _rw * _ratio;
                [format ["stain%1", _i], _base call _tex, _rx + (_rw - _w) / 2, _ry + (_rh - _h) / 2, _w, _h, _turn] call _pic;
            };
        } else {
            private _h = _short * _size;
            private _w = _h * ((createHashMapFromArray [["print", 0.5], ["drip", 0.5], ["drag", 0.5], ["run", 0.125], ["smear", 2], ["arterial", 2], ["wipe", 2], ["puddle", 4]]) getOrDefault [_kind, 1]) / _ratio;
            // Centre gardé dans l'écran (une grande tache ne déborde pas sur le décor) ; coulures : à leur place
            // exacte (segments bout à bout).
            private _cx = _rx + _rw * _sx;
            private _cy = _ry + _rh * _sy;
            if !(_kind in ["run", "puddle"]) then {
                _cx = _cx max (_rx + (_w min _rw) / 2) min (_rx + _rw - (_w min _rw) / 2);
                _cy = _cy max (_ry + (_h min _rh) / 2) min (_ry + _rh - (_h min _rh) / 2);
            };
            [format ["stain%1", _i], _base call _tex, _cx - _w / 2, _cy - _h / 2, _w, _h, (_ang + _turn) mod 360] call _pic;
        };
        (_ctrls select ((count _ctrls) - 1)) pushBack _base;
    } forEach _order;
    // Sang sur la coque : sur le rebord autour de l'écran (la texture du téléphone est carrée et en grande partie
    // transparente : on se cale sur l'écran, pas sur le cadre de la texture, sinon le sang flotte dans le vide).
    private _bx = _rw * 0.055;
    private _by = _rh * 0.075;
    for "_i" from 1 to _cFrame do {
        [6, _i] call _from;
        private _side = floor ((call _rnd) * 4);
        private _h = _rh * (0.05 + 0.04 * call _rnd);
        private _w = 2 * _h / _ratio;
        // Côtés gauche et droit : traînée verticale (image tournée de 90°, cadre carré de la longueur de la traînée).
        private _fx = [_rx - _bx - _w / 2, _rx + _rw + _bx - _w / 2, _rx + (_rw - _w) * (call _rnd), _rx + (_rw - _w) * (call _rnd)] select _side;
        private _fy = [_ry + (_rh - _h) * (call _rnd), _ry + (_rh - _h) * (call _rnd), _ry - _by - _h / 2, _ry + _rh + _by - _h / 2] select _side;
        ["frame", format ["bloodc_smear_%1", floor ((call _rnd) * 3)] call _tex, _fx, _fy, _w, _h, [90, 90, 0, 0] select _side] call _pic;
    };
    // Pluie : une ou deux plaques de gouttes (carrées, tournées, à des places tirées une fois), par-dessus le sang.
    for "_i" from 1 to _cDrops do {
        [7, _i] call _from;
        private _s = _short * (0.8 + 0.2 * call _rnd);
        private _w = _s / _ratio;
        ["drops", format ["dirt_rain_%1", _i mod 2] call _tex, _rx + (_rw - _w) * (call _rnd), _ry + (_rh - _s) * (call _rnd), _w, _s, 90 * floor (4 * call _rnd)] call _pic;
    };
    // Reflet de la vitre : blanc, l'alpha de la texture dessine une bande douce en biais en haut à gauche ; opacité
    // faible, suivie plus bas (jour / nuit). Par-dessus la saleté, sous les coins.
    if (_glare) then {
        ["glass", "screen_glass" call _tex, _rx, _ry, _rw, _rh] call _pic;
    };
    // Coins arrondis : l'écran est un rectangle posé par-dessus la coque, ses coins carrés cachaient l'arrondi de
    // l'ouverture. Un quart de disque évidé, teint au gris du cadre, refait l'arrondi à chaque coin (en dernier :
    // devant la saleté et le reflet). L'écran déborde de 0.003 (fn_layoutGet) : le coin part du bord débordant,
    // rayon + débord, et l'arrondi suit l'ouverture décalée d'autant, comme les bords droits.
    if (_corner isNotEqualTo []) then {
        _corner params ["_radii", "_grayDay", "_grayNight"];
        private _gray = [_grayDay, _grayNight] select (((_l getOrDefault ["phoneTexture", ""]) find "_night") >= 0);
        private _land = _l getOrDefault ["landscape", true];
        {
            // Portrait : texture tournée d'un quart de tour horaire, le coin haut gauche affiché est le bas gauche du paysage.
            private _i = [(_forEachIndex + 3) mod 4, _forEachIndex] select _land;
            private _r = _radii select _i;
            if (_r > 0.004) then {
                private _w = _pw * (_r + 0.003);
                private _h = _ph * (_r + 0.003);
                private _cx = [_rx, _rx + _rw - _w] select (_x in ["tr", "br"]);
                private _cy = [_ry, _ry + _rh - _h] select (_x in ["br", "bl"]);
                private _v = (_gray select _i) / 255;
                ["corner", format ["screen_corner_%1", _x] call _tex, _cx, _cy, _w, _h] call _pic;
                ((_ctrls select ((count _ctrls) - 1)) select 1) ctrlSetTextColor [_v, _v, _v, 1];
            };
        } forEach ["tl", "tr", "br", "bl"];
    };
    { ctrlDelete (_x select 1); } forEach _oldCtrls;
};
// Opacité selon l'état (chaque seconde, sans recréer).
private _off = (_hp getOrDefault ["state", "OK"]) isEqualTo "OFF";
private _texPath = { format ["\z\comspec_atak_native\addons\main\data\%1.paa", _this] };
private _style = (missionNamespace getVariable ["comspec_atak_native_blood_style", 1]) max 0 min 2;
{
    _x params ["_kind", "_c"];
    switch (_kind) do {
        case "pix": { _c ctrlShow !_off; };
        case "scratch": { _c ctrlSetTextColor [0.9, 0.95, 1, 0.14 + 0.12 * _scratch]; };
        // Voile progressif : à peine visible au début, épais seulement après une longue exposition.
        // Fine poussière (retours de jeu : la saleté noircissait l'écran et la coque) : discrète même au maximum.
        case "dust": { _c ctrlSetTextColor [0.62, 0.56, 0.44, (0.04 + 0.4 * _dust) min 0.42]; };
        case "caseDust": { _c ctrlSetTextColor [0.58, 0.51, 0.39, (0.05 + 0.5 * _dust) min 0.55]; };
        case "prints": { _c ctrlSetTextColor [0.82, 0.82, 0.76, 0.06 + 0.18 * _prints]; };
        case "frame": { _c ctrlSetTextColor [0.8, 0.7, 0.68, 0.55 + 0.4 * _frame]; };
        case "drops": { _c ctrlSetTextColor [1, 1, 1, (0.3 + 0.6 * _drops) min 0.9]; };
        case "mud": { _c ctrlSetTextColor [1, 1, 1, (0.5 + 0.5 * _mud) min 0.96]; };
        // Reflet : discret, plus faible la nuit (moins de lumière à refléter) ; jamais un voile sur la carte ou le texte.
        case "glass": { _c ctrlSetTextColor [1, 1, 1, 0.22 * (0.45 + 0.55 * sunOrMoon)]; };
        case "corner": {};
        default {
            // Tache de sang : opacité propre (elle pâlit au nettoyage, à la pluie) ; style discret plus léger.
            // Le sang sèche (fn_screenDirt _dryOf) : rouge vif et brillant la première minute, s'assombrit, puis vers
            // 4 min passe à la texture séchée (mate, brune, bord épais, craquelée), qui fonce encore un peu jusqu'à 6 min.
            if ((_kind select [0, 5]) isEqualTo "stain") then {
                private _st = _stains param [parseNumber (_kind select [5]), []];
                if (_st isNotEqualTo []) then {
                    private _a = (_st select 6) * ([0.7, 0.92, 1] select _style);
                    private _dry = (((time - (_st param [7, time - 600])) - 60) / 300) max 0 min 1;
                    private _base = _x param [2, ""];
                    if (_base isNotEqualTo "") then {
                        private _want = [_base, "bloodd_" + (_base select [7])] select (_dry >= 0.6);
                        if ((_x param [3, _base]) isNotEqualTo _want) then { _c ctrlSetText (_want call _texPath); _x set [3, _want]; };
                    };
                    if (_wetNow) then {
                        // Sous la pluie ou à la sortie de l'eau : le sang se dilue en voile rosé, plus transparent.
                        _c ctrlSetTextColor [1, 0.84, 0.88, _a * 0.62];
                        continue;
                    };
                    if (_dry < 0.6) then {
                        private _k = _dry / 0.6;
                        _c ctrlSetTextColor [1 - 0.25 * _k, 1 - 0.4 * _k, 1 - 0.45 * _k, _a * (1 - 0.08 * _k)];
                    } else {
                        private _k = (_dry - 0.6) / 0.4;
                        _c ctrlSetTextColor [1 - 0.12 * _k, 1 - 0.12 * _k, 1 - 0.12 * _k, _a * 0.92];
                    };
                };
            };
        };
    };
} forEach _ctrls;
// Bris de verre et pannes de dalle : lumière sur les arêtes, ligne qui clignote, tache qui grandit, eau qui bouge.
if (_faults isNotEqualTo []) then { ["update", _ctrls select { ((_x select 0) select [0, 2]) isEqualTo "sf" }, _hp] call comspec_atak_native_fnc_screenFaults; };
uiNamespace setVariable ["COMSPEC_ATAK_SurfOverlay", [_sig, _ctrls]];
if ((count _ctrls) > 0) then {
    uiNamespace setVariable ["COMSPEC_ATAK_DevFront", true];
    [] call comspec_atak_native_fnc_overlayFront;
};
true
