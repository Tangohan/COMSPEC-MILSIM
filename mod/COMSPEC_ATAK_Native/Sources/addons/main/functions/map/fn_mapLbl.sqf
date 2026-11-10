/*
    Étiquettes de la carte du téléphone : taille, fond, place et niveau de détail communs à toutes les couches (unités et
    équipages, contacts, cibles suivies, repères, lieux, mesures, routes, zones, grille).

    Taille : en pixels réels de l'écran (pixelH), quels que soient le zoom, la taille du téléphone (mini, porté, plein écran,
      portrait ou paysage, tablette), l'échelle de l'interface et la résolution d'Arma. Taille nominale par rôle (indicatif
      17 px, seconde ligne 14, repère 16, ville 19, village 17, lieu-dit 15, mesure et zone 15, grille 13) × « Taille des
      textes » × « Taille des étiquettes » (Réglages > Carte), jamais sous le plancher « Taille minimale des textes »
      (14 px par défaut), jamais au-dessus de 30 px (plafond en vue rapprochée) ; arrondie au pixel, largeur mesurée à
      cette taille exacte (fn_mapTextW) : le fond est à la taille du texte, marge de 3 px.
    Niveau de détail (mètres par pixel de l'écran, réglage « Densité des étiquettes » qui décale les seuils) :
      LOIN (0)  indicatifs courts (« SHA 2-4 »), seulement moi, la sélection, les contacts, cibles suivies et chefs de
                groupe ; repères et lieux-dits masqués, villes seulement ;
      MOYEN (1) libellés complets sur une ligne, noms des repères abrégés proprement (mots entiers, « … ») ;
      PRÈS (2)  libellés complets, seconde ligne (distance et azimut).
    Place (anti-chevauchement) : pendant une image de calcul, chaque couche dépose ses demandes (« add ») et les symboles
      dessinés (« sym ») ; « layout » les range par priorité (moi 0, sélection 1, cibles suivies et contacts 2,
      équipiers 3, autres alliés 4, repères 5, lieux 6) et essaie jusqu'à 10 places autour du symbole (droite, gauche,
      dessous, dessus, coins, centre pour les lieux) : jamais sur un symbole, une autre étiquette, le bandeau ni hors de la
      carte ; sinon l'étiquette n'est pas écrite (les unités proches sont regroupées avant : « BRAVO 2-1 +3 »). Une demande
      forcée (moi, sélection, cible suivie, LOCK) prend la première place libre, sinon la première place dans la carte.
    Coût : image de calcul quand la vue change (zoom, déplacement, taille de la carte), au plus 15 fois par seconde pendant
      un glissement, et toutes les 0,25 s (unités en mouvement, données) ; les autres images redessinent la liste gardée
      avec des additions seulement (ancres des unités relues à l'image : l'étiquette suit son symbole).
    Primitives d'une étiquette (coordonnées d'écran relatives au coin haut gauche de sa place) :
      ["R", x0, y0, x1, y1, couleur, texture ("" : contour)]   rectangle
      ["T", x, y, texte, couleur, taille, police, ombre, alignement]   texte (centré en hauteur sur y)
      ["L", couleur]                                            trait du symbole au coin le plus proche
      ["E", x, y, rayon (y), couleur]                           disque
      ["Q", x, y, demi-côté (y), angle, couleur]                carré tourné (losange)
    Opérations :
      "frame" [carte]                     -> table de l'image (uiNamespace COMSPEC_ATAK_LblF)
      "w" [texte, police, taille]         -> largeur exacte à l'écran
      "cut" [texte, longueur]             -> abrégé propre (mots entiers, « … »)
      "short" indicatif                   -> indicatif court : « Shadow 2-4 » -> « SHA 2-4 »
      "plate" [lignes [[texte, police, taille, couleur]...], fond ([] : sans fond, texte détouré), marge px]
                                          -> [largeur, hauteur, primitives]
      "sym" [position, demi-largeur px, demi-hauteur px]       obstacle (image de calcul seulement)
      "add" [priorité, position, clé d'unité ("" : fixe), demi-largeur px, demi-hauteur px, [largeur, hauteur, primitives],
             forcée, places]                                   demande (image de calcul seulement)
      "layout" ; "draw" ; "free" [rect écran] -> vrai si libre (étiquettes au curseur, dessinées à chaque image)
      "upos" [clé, position]              position de l'image d'une unité (ancre des étiquettes gardées)
*/
params [["_op", ""], ["_arg", []]];
// Dessin d'une étiquette : coin haut gauche à [ancre + décalage] (écran), primitives converties en mètres par additions.
private _drawItem = {
    params ["_map", "_kx", "_ky", "_wx", "_wy", "_dx", "_dy", "_prims", "_w", "_h", ["_tk", 1]];
    private _fill = "#(rgb,8,8,3)color(1,1,1,1)";
    private _none = "#(argb,8,8,3)color(0,0,0,0)";
    private _bx = _wx + _dx * _kx;
    private _by = _wy - _dy * _ky;
    {
        switch (_x select 0) do {
            case "R": {
                _x params ["", "_a", "_b", "_c", "_d", "_col", ["_t", _fill]];
                _map drawRectangle [[_bx + (_a + _c) / 2 * _kx, _by - (_b + _d) / 2 * _ky, 0], (_c - _a) / 2 * _kx, (_d - _b) / 2 * _ky, 0, _col, _t];
            };
            case "T": {
                _x params ["", "_a", "_b", "_t", "_col", "_s", "_f", ["_sh", 0], ["_al", "right"]];
                _map drawIcon [_none, _col, [_bx + _a * _kx, _by - _b * _ky, 0], 0, 0, 0, _t, _sh, _s * _tk, _f, _al];
            };
            case "L": {
                // Trait du symbole au coin de l'étiquette le plus proche.
                private _cx = [_dx + _w, _dx] select (_dx > 0);
                private _cy = [_dy + _h, _dy] select (_dy > 0);
                _map drawLine [[_wx, _wy, 0], [_wx + _cx * _kx, _wy - _cy * _ky, 0], _x select 1];
            };
            case "E": {
                _x params ["", "_a", "_b", "_r", "_col"];
                _map drawEllipse [[_bx + _a * _kx, _by - _b * _ky, 0], _r * _ky, _r * _ky, 0, _col, _fill];
            };
            case "Q": {
                _x params ["", "_a", "_b", "_r", "_ang", "_col"];
                _map drawRectangle [[_bx + _a * _kx, _by - _b * _ky, 0], _r * _ky, _r * _ky, _ang, _col, _fill];
            };
        };
    } forEach _prims;
};
switch (_op) do {
    case "frame": {
        _arg params ["_map"];
        (ctrlPosition _map) params ["_mx", "_my", "_mw", "_mh"];
        private _o = _map ctrlMapScreenToWorld [_mx, _my];
        private _b = _map ctrlMapScreenToWorld [_mx + 0.1, _my + 0.1];
        private _kx = (((_b select 0) - (_o select 0)) / 0.1) max 0.0001;
        private _ky = (((_o select 1) - (_b select 1)) / 0.1) max 0.0001;
        private _mpp = _ky * pixelH;
        private _prof = profileNamespace;
        // Densité : LÉGÈRE (0) détail plus tôt retiré, NORMALE (1), DENSE (2) détail gardé plus loin.
        private _dens = _prof getVariable ["COMSPEC_ATAK_LblDensity", 1];
        if !(_dens isEqualType 0) then { _dens = 1; };
        _dens = (round _dens max 0) min 2;
        private _dk = [0.6, 1, 1.6] select _dens;
        private _lod = [[0, 1] select (_mpp < 7 * _dk), 2] select (_mpp < 2 * _dk);
        private _ts = ((_prof getVariable ["COMSPEC_ATAK_MapTextScale", 1.25]) max 0.8) min 2.2;
        private _labK = ((_prof getVariable ["COMSPEC_ATAK_LabelScale", 1]) max 0.5) min 2.5;
        private _minPx = _prof getVariable ["COMSPEC_ATAK_MapTextMinPx", 14];
        if !(_minPx isEqualType 0) then { _minPx = 14; };
        _minPx = (_minPx max 10) min 24;
        private _uk = (_ts / 1.25) * ([0.95, 1, 1.08] select _lod);
        private _maxPx = 30 * ((_ts / 1.25) max 1);
        private _fs = createHashMap;
        {
            _x params ["_role", "_nom", "_minK", "_lk"];
            private _lo = _minPx * _minK;
            private _p = ((_nom * _uk * _lk) max _lo) min (_maxPx max _lo);
            _fs set [_role, (round _p) * pixelH];
        } forEach [["u", 17, 1, _labK], ["u2", 14, 0.86, _labK], ["m", 16, 1, _labK], ["m2", 14, 0.86, _labK],
            ["p0", 19, 1.12, 1], ["p1", 17, 1.05, 1], ["p2", 15, 1, 1], ["p3", 15, 1, 1], ["z", 15, 1, 1], ["ms", 15, 1, 1], ["g", 13, 0.86, 1]];
        // Fond des noms (Réglages > Carte) posé par fn_mapOnDraw juste avant : [couleur, fond clair] ou [] (texte détouré).
        private _nb = uiNamespace getVariable ["COMSPEC_ATAK_NameBgNow", []];
        private _now = diag_tickTime;
        private _out = uiNamespace getVariable ["COMSPEC_ATAK_LblOut", []];
        private _sig = [_map, round ((_o select 0) / _mpp), round ((_o select 1) / _mpp), round (_mpp * 200), _mx, _my, _mw, _mh, _lod, _fs get "u", _nb param [0, []]];
        private _last = _out param [1, -1];
        private _lay = (count _out) < 5 || {(_now - _last) > 0.25} || {(_now - _last) > 0.066 && {(_out select 0) isNotEqualTo _sig}} || {((_out select 0) param [0, controlNull]) isNotEqualTo _map};
        private _F = createHashMapFromArray [
            ["map", _map], ["s", [_mx, _my, _mw, _mh]], ["o", [_o select 0, _o select 1]], ["kx", _kx], ["ky", _ky], ["mpp", _mpp],
            ["lod", _lod], ["dens", _dens], ["fs", _fs], ["lay", _lay], ["sig", _sig], ["req", []], ["syms", []],
            ["bg", _nb param [0, []]], ["light", _nb param [1, false]],
            // Hauteur d'une ligne de texte de drawIcon rapportée à sa taille.
            ["kh", 0.92],
            // Correction du moteur : drawIcon rapetisse ses textes quand la carte est dézoomée, d'un facteur
            // racine (0,0375 / échelle) au-delà de l'échelle 0,0375 (mesuré sur les captures de l'autotest : 0,80 à l'échelle
            // 0,06, 0,685 à 0,08, aucun effet sous 0,02 ; c'est le « lisible en zoomant, illisible à mi-zoom »). Taille
            // demandée multipliée d'autant : la hauteur à l'écran reste celle choisie.
            ["tk", 1 max (sqrt ((ctrlMapScale _map) / 0.0375))]
        ];
        uiNamespace setVariable ["COMSPEC_ATAK_LblF", _F];
        uiNamespace setVariable ["COMSPEC_ATAK_LblUPos", createHashMap];
        _F
    };
    case "upos": { (uiNamespace getVariable ["COMSPEC_ATAK_LblUPos", createHashMap]) set [_arg select 0, _arg select 1]; };
    case "w": {
        _arg params ["_t", "_f", "_s"];
        // Chasse fixe Etelka : drawIcon l'écrit 10 % plus serré que le texte caché qui la mesure (captures de l'autotest).
        ([_t, _f, _s] call comspec_atak_native_fnc_mapTextW) * ([1, 0.9] select ((_f find "Etelka") >= 0))
    };
    case "cut": {
        _arg params ["_t", "_n"];
        private _a = toArray _t;
        if ((count _a) <= _n) exitWith { _t };
        // Mot entier si la coupure tombe au milieu d'un mot (au moins la moitié gardée), « … » à la fin.
        private _k = _a select [0, (_n - 1) max 1];
        private _cut = -1;
        for "_j" from ((count _k) - 1) to (floor (_n / 2)) step -1 do { if ((_k select _j) isEqualTo 32) exitWith { _cut = _j; }; };
        if (_cut > 0) then { _k = _k select [0, _cut]; };
        (trim (toString _k)) + "…"
    };
    case "short": {
        // « Shadow 2-4 » -> « SHA 2-4 », « Bravo 2-1 Reco » -> « BRA 2-1 » ; indicatif court gardé tel quel.
        private _w = (toUpper _arg) splitString " ";
        if ((count _w) < 2) exitWith { ["cut", [toUpper _arg, 8]] call comspec_atak_native_fnc_mapLbl };
        private _f = _w select 0;
        if ((count (toArray _f)) > 4 && {(_f regexFind ["[0-9]"]) isEqualTo []}) then { _f = toString ((toArray _f) select [0, 3]); };
        private _r = (_w select [1]) select { (_x regexFind ["[0-9]"]) isNotEqualTo [] };
        if (_r isEqualTo []) then { _r = [_w select 1]; };
        ["cut", [_f + " " + (_r select 0), 10]] call comspec_atak_native_fnc_mapLbl
    };
    case "plate": {
        _arg params ["_lines", ["_bg", []], ["_padPx", 3], ["_bar", []]];
        private _F = uiNamespace getVariable ["COMSPEC_ATAK_LblF", createHashMap];
        private _kh = _F getOrDefault ["kh", 0.92];
        private _pw = _padPx * pixelW;
        private _ph = (_padPx - 1) * pixelH;
        private _barW = [0, 3 * pixelW] select ((count _bar) > 0);
        private _w = 0;
        private _h = 0;
        private _ws = _lines apply {
            // Texte qui change à chaque image (distance) : mesuré chiffres remplacés par 0 (_tw), une mesure par longueur.
            _x params ["_t", "_f", "_s", "", ["_tw", ""]];
            private _lw = ["w", [[_tw, _t] select (_tw isEqualTo ""), _f, _s]] call comspec_atak_native_fnc_mapLbl;
            _w = _w max _lw;
            _h = _h + _s * _kh;
            _lw
        };
        _w = _w + _pw * 2 + _barW;
        _h = _h + _ph * 2;
        private _prims = [];
        if ((count _bg) > 0) then { _prims pushBack ["R", 0, 0, _w, _h, _bg, "#(rgb,8,8,3)color(1,1,1,1)"]; };
        if (_barW > 0) then { _prims pushBack ["R", 0, 0, _barW, _h, _bar, "#(rgb,8,8,3)color(1,1,1,1)"]; };
        private _y = _ph;
        {
            _x params ["_t", "_f", "_s", "_c"];
            private _lh = _s * _kh;
            // Sans fond : texte détouré (ombre 2), lisible sur une carte claire comme sombre.
            _prims pushBack ["T", _barW + _pw, _y + _lh / 2, _t, _c, _s, _f, [0, 2] select ((count _bg) isEqualTo 0), "right"];
            _y = _y + _lh;
        } forEach _lines;
        [_w, _h, _prims]
    };
    case "sym": {
        private _F = uiNamespace getVariable ["COMSPEC_ATAK_LblF", createHashMap];
        if !(_F getOrDefault ["lay", false]) exitWith {};
        _arg params ["_p", "_hx", ["_hy", -1]];
        if (_hy < 0) then { _hy = _hx; };
        (_F get "s") params ["_mx", "_my"];
        (_F get "o") params ["_ox", "_oy"];
        (_F get "syms") pushBack [_mx + ((_p select 0) - _ox) / (_F get "kx"), _my + (_oy - (_p select 1)) / (_F get "ky"), _hx * pixelW, _hy * pixelH];
    };
    case "add": {
        private _F = uiNamespace getVariable ["COMSPEC_ATAK_LblF", createHashMap];
        if !(_F getOrDefault ["lay", false]) exitWith {};
        _arg params ["_prio", "_p", ["_key", ""], ["_hx", 8], ["_hy", -1], ["_plate", []], ["_force", false], ["_slots", [0, 1, 2, 3, 4, 5, 6, 7]]];
        if ((count _plate) < 3) exitWith {};
        if (_hy < 0) then { _hy = _hx; };
        private _req = _F get "req";
        _req pushBack [_prio * 1e6 + (count _req), _p, _key, _hx * pixelW, _hy * pixelH, _plate, _force, _slots];
    };
    case "layout": {
        private _F = uiNamespace getVariable ["COMSPEC_ATAK_LblF", createHashMap];
        if !(_F getOrDefault ["lay", false]) exitWith {};
        private _t0 = diag_tickTime;
        private _map = _F get "map";
        (_F get "s") params ["_mx", "_my", "_mw", "_mh"];
        (_F get "o") params ["_ox", "_oy"];
        private _kx = _F get "kx";
        private _ky = _F get "ky";
        private _grid = createHashMap;
        private _hud = [_map] call comspec_atak_native_fnc_mapHudRects;
        // Fiches posées sur la carte (cible localisée, unité) : aucune étiquette dessous.
        {
            if (!isNull _x && {ctrlShown _x}) then { (ctrlPosition _x) params ["_cx", "_cy", "_cw", "_ch"]; _hud pushBack [_cx - 4 * pixelW, _cy - 4 * pixelH, _cx + _cw + 4 * pixelW, _cy + _ch + 4 * pixelH]; };
        } forEach [uiNamespace getVariable ["COMSPEC_ATAK_MapSearchBg", controlNull], ((uiNamespace getVariable ["COMSPEC_ATAK_UnitCard", createHashMap]) getOrDefault ["ctrls", []]) param [1, controlNull]];
        { [_grid, _x, 2] call comspec_atak_native_fnc_mapLblGrid; } forEach _hud;
        { _x params ["_sx", "_sy", "_hx", "_hy"]; [_grid, [_sx - _hx, _sy - _hy, _sx + _hx, _sy + _hy], 2] call comspec_atak_native_fnc_mapLblGrid; } forEach (_F get "syms");
        private _req = _F get "req";
        _req sort true;
        private _g = 3 * pixelW;
        private _gy = 3 * pixelH;
        private _x1m = _mx + _mw - pixelW;
        private _y1m = _my + _mh - pixelH;
        private _items = [];
        private _rects = [];
        {
            _x params ["_sk", "_p", "_key", "_hx", "_hy", "_plate", "_force", "_slots"];
            _plate params ["_w", "_h", "_prims"];
            private _sx = _mx + ((_p select 0) - _ox) / _kx;
            private _sy = _my + (_oy - (_p select 1)) / _ky;
            if (_sx < _mx || {_sx > (_mx + _mw)} || {_sy < _my} || {_sy > (_my + _mh)}) then { continue };
            private _first = [];
            private _r = [];
            {
                private _o = switch (_x) do {
                    case 0: { [_sx + _hx + _g, _sy - _h / 2] };
                    case 1: { [_sx - _hx - _g - _w, _sy - _h / 2] };
                    case 2: { [_sx - _w / 2, _sy + _hy + _gy] };
                    case 3: { [_sx - _w / 2, _sy - _hy - _gy - _h] };
                    case 4: { [_sx + _hx * 0.7 + _g, _sy - _hy * 0.7 - _gy - _h] };
                    case 5: { [_sx + _hx * 0.7 + _g, _sy + _hy * 0.7 + _gy] };
                    case 6: { [_sx - _hx * 0.7 - _g - _w, _sy - _hy * 0.7 - _gy - _h] };
                    case 7: { [_sx - _hx * 0.7 - _g - _w, _sy + _hy * 0.7 + _gy] };
                    case 8: { [_sx - _w / 2, _sy - _h / 2] };
                    default { [_sx - _w / 2, _sy - _hy - _h] };
                };
                private _c = [_o select 0, _o select 1, (_o select 0) + _w, (_o select 1) + _h];
                if ((_c select 0) < (_mx + pixelW) || {(_c select 2) > _x1m} || {(_c select 1) < (_my + pixelH)} || {(_c select 3) > _y1m}) then { continue };
                if (_first isEqualTo []) then { _first = _c; };
                if !([_grid, _c, 0] call comspec_atak_native_fnc_mapLblGrid) exitWith { _r = _c; };
            } forEach _slots;
            if (_r isEqualTo [] && {_force}) then { _r = _first; };
            if (_r isEqualTo []) then { continue };
            [_grid, _r, 2] call comspec_atak_native_fnc_mapLblGrid;
            _rects pushBack _r;
            _items pushBack [_key, _p select 0, _p select 1, (_r select 0) - _sx, (_r select 1) - _sy, _prims, _w, _h, floor (_sk / 1e6)];
        } forEach _req;
        uiNamespace setVariable ["COMSPEC_ATAK_LblOut", [_F get "sig", diag_tickTime, _items, _grid, _rects, count _req]];
        // Relevé pour l'onglet PERFS du Debug et l'autotest (rectangles posés, demandes).
        uiNamespace setVariable ["COMSPEC_ATAK_LblRects", _hud + _rects];
        ["carte étiquettes placement", _t0] call comspec_atak_native_fnc_perfAcc;
    };
    case "free": {
        private _out = uiNamespace getVariable ["COMSPEC_ATAK_LblOut", []];
        if ((count _out) < 4) exitWith { true };
        !([_out select 3, _arg, 0] call comspec_atak_native_fnc_mapLblGrid)
    };
    case "draw": {
        private _F = uiNamespace getVariable ["COMSPEC_ATAK_LblF", createHashMap];
        private _out = uiNamespace getVariable ["COMSPEC_ATAK_LblOut", []];
        if ((count _out) < 3 || {((_out select 0) param [0, controlNull]) isNotEqualTo (_F getOrDefault ["map", controlNull])}) exitWith {};
        private _map = _F get "map";
        private _kx = _F get "kx";
        private _ky = _F get "ky";
        private _up = uiNamespace getVariable ["COMSPEC_ATAK_LblUPos", createHashMap];
        private _tk = _F getOrDefault ["tk", 1];
        {
            _x params ["_key", "_wx", "_wy", "_dx", "_dy", "_prims", "_w", "_h"];
            if (_key isNotEqualTo "") then {
                private _lp = _up getOrDefault [_key, []];
                if (_lp isEqualTo []) then { continue };
                _wx = _lp select 0; _wy = _lp select 1;
            };
            [_map, _kx, _ky, _wx, _wy, _dx, _dy, _prims, _w, _h, _tk] call _drawItem;
        } forEach (_out select 2);
    };
    case "now": {
        // Étiquette qui change à chaque image (distance au curseur, point B de la mesure) : place libre d'après le dernier
        // calcul (aucune étiquette gardée recouverte), dessinée tout de suite. [position, plaque, demi-taille px, places]
        _arg params ["_p", "_plate", ["_hpx", 6], ["_slots", [5, 0, 7, 1, 4, 6, 2, 3]]];
        private _F = uiNamespace getVariable ["COMSPEC_ATAK_LblF", createHashMap];
        if ((count _F) isEqualTo 0) exitWith {};
        _plate params ["_w", "_h", "_prims"];
        (_F get "s") params ["_mx", "_my", "_mw", "_mh"];
        (_F get "o") params ["_ox", "_oy"];
        private _kx = _F get "kx";
        private _ky = _F get "ky";
        private _sx = _mx + ((_p select 0) - _ox) / _kx;
        private _sy = _my + (_oy - (_p select 1)) / _ky;
        private _g = 3 * pixelW;
        private _gy = 3 * pixelH;
        private _first = [];
        private _r = [];
        // Curseur posé sur un symbole (cible suivie et son anneau) : places essayées de plus en plus loin.
        {
            private _hx = _x * pixelW;
            private _hy = _x * pixelH;
            {
            private _o = switch (_x) do {
                case 0: { [_sx + _hx + _g, _sy - _h / 2] };
                case 1: { [_sx - _hx - _g - _w, _sy - _h / 2] };
                case 2: { [_sx - _w / 2, _sy + _hy + _gy] };
                case 3: { [_sx - _w / 2, _sy - _hy - _gy - _h] };
                case 4: { [_sx + _hx + _g, _sy - _hy - _gy - _h] };
                case 5: { [_sx + _hx + _g, _sy + _hy + _gy] };
                case 6: { [_sx - _hx - _g - _w, _sy - _hy - _gy - _h] };
                default { [_sx - _hx - _g - _w, _sy + _hy + _gy] };
            };
            private _c = [_o select 0, _o select 1, (_o select 0) + _w, (_o select 1) + _h];
            if ((_c select 0) < _mx || {(_c select 2) > (_mx + _mw)} || {(_c select 1) < _my} || {(_c select 3) > (_my + _mh)}) then { continue };
            if (_first isEqualTo []) then { _first = _c; };
            if (["free", _c] call comspec_atak_native_fnc_mapLbl) exitWith { _r = _c; };
            } forEach _slots;
            if (_r isNotEqualTo []) exitWith {};
        } forEach [_hpx, _hpx + 22, _hpx + 48];
        if (_r isEqualTo []) then { _r = _first; };
        if (_r isEqualTo []) exitWith {};
        [_F get "map", _kx, _ky, _p select 0, _p select 1, (_r select 0) - _sx, (_r select 1) - _sy, _prims, _w, _h, _F getOrDefault ["tk", 1]] call _drawItem;
        _r
    };
};
