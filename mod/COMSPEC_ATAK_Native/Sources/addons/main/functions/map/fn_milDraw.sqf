/*
    Dessin d'un symbole MIL-STD-2525D / APP-6D (descripteur de fn_milSym) : couches blanches de tools/gen_milstd.py
    (items.pbo, data\milstd), superposées au même point et à la même taille, colorées par drawIcon. 3 à 7 dessins par symbole,
    couches et couleurs préparées une fois par descripteur et par thème (rien à recalculer par image).
    Thèmes (couleurs de la norme) : "L" clair (fonds clairs, carte topographique), "D" sombre (fonds sombres, ardoise,
    photo : teintes moyennes), "N" nuit (teintes sombres, traits vert pâle). Pictogramme et contour noirs comme dans la
    norme ; échelon, hampe et textes à l'encre du thème (lisibles hors du cadre, sur le fond de carte).
    Params : [opération, argument]
      "draw" [carte, descripteur, position, taille px, alpha, cap (-1 : sans flèche), textes [gauche haut, gauche bas]]
      "layers" [descripteur, thème]       -> [[texture, couleur]...] (gardé)
      "preview" descripteur               -> couches [[texture, couleur, échelle]...] d'une case « shapes » (fn_formRender)
      "theme"                             -> thème de la carte affichée
      "tex" nom                           -> chemin d'une texture du jeu de symboles
*/
params [["_op", ""], ["_a", []]];
private _root = "\z\comspec_atak_native\addons\items\data\milstd\";
switch (_op) do {
    case "tex": { _root + _a + "_ca.paa" };
    case "theme": {
        private _bg = [] call comspec_atak_native_fnc_mapStyle;
        switch (true) do {
            case (_bg isEqualTo "NIGHT"): { "N" };
            case (([_bg] call comspec_atak_native_fnc_mapColors) getOrDefault ["dark", false]): { "D" };
            default { "L" };
        }
    };
    case "layers": {
        _a params ["_d", ["_th", "L"]];
        private _cache = uiNamespace getVariable "COMSPEC_ATAK_MilLayers";
        if (isNil "_cache" || {(count _cache) > 300}) then { _cache = createHashMap; uiNamespace setVariable ["COMSPEC_ATAK_MilLayers", _cache]; };
        private _key = str _d + _th;
        private _hit = _cache get _key;
        if (!isNil "_hit") exitWith { _hit };
        _d params ["_aff", "_dim", "_pic", "_ech", "_hq", "_plan", "_cond", "_civ"];
        // Teintes de fond 2525D (clair, moyen, sombre) ; violet des civils.
        private _fills = switch (_th) do {
            case "D": { createHashMapFromArray [["f", [0, 0.66, 0.86]], ["h", [1, 0.19, 0.19]], ["n", [0, 0.89, 0]], ["u", [1, 1, 0]], ["c", [0.78, 0.4, 0.78]]] };
            case "N": { createHashMapFromArray [["f", [0, 0.42, 0.55]], ["h", [0.78, 0, 0]], ["n", [0, 0.63, 0]], ["u", [0.88, 0.86, 0]], ["c", [0.5, 0.25, 0.5]]] };
            default { createHashMapFromArray [["f", [0.5, 0.88, 1]], ["h", [1, 0.5, 0.5]], ["n", [0.67, 1, 0.67]], ["u", [1, 1, 0.5]], ["c", [1, 0.63, 1]]] };
        };
        private _ink = switch (_th) do { case "D": { [0.96, 0.96, 0.96, 1] }; case "N": { [0.72, 0.86, 0.72, 1] }; default { [0, 0, 0, 1] }; };
        private _black = [0, 0, 0, 1];
        private _base = switch (_aff) do { case "h"; case "s": { "h" }; case "n": { "n" }; case "u": { "u" }; default { "f" }; };
        private _frame = _base + (switch (_dim) do { case "a": { "a" }; case "s": { ["l", "s"] select (_base isEqualTo "f") }; default { "l" }; });
        private _fc = +(_fills get ([_base, "c"] select _civ)); _fc pushBack 1;
        private _l = [[_root + "f_" + _frame + "_fill_ca.paa", _fc]];
        _l pushBack [_root + "f_" + _frame + (["_line", "_dash"] select (_plan || {_aff in ["af", "s"]})) + "_ca.paa", _black];
        if (_pic isNotEqualTo "") then { _l pushBack [_root + "p_" + _pic + "_" + _frame + "_ca.paa", _black]; };
        if (_frame in ["fl", "hl", "nl", "ul"]) then {
            if (_ech isNotEqualTo "") then { _l pushBack [_root + "e_" + _ech + "_" + _frame + "_ca.paa", _ink]; };
            if (_hq) then { _l pushBack [_root + "hq_" + _frame + "_ca.paa", _ink]; };
            if (_cond > 0) then { _l pushBack [_root + "bar_" + _frame + "_ca.paa", [[1, 0.85, 0, 1], [1, 0.16, 0.1, 1]] select (_cond > 1)]; };
        };
        _l = [_l, _ink];
        _cache set [_key, _l];
        _l
    };
    case "preview": {
        ((["layers", [_a, "L"]] call comspec_atak_native_fnc_milDraw) select 0) apply { [_x select 0, _x select 1, 1.25] }
    };
    case "draw": {
        _a params ["_map", "_d", "_pos", "_size", ["_al", 1], ["_dir", -1], ["_txt", []]];
        private _th = uiNamespace getVariable ["COMSPEC_ATAK_MilTheme", "L"];
        (["layers", [_d, _th]] call comspec_atak_native_fnc_milDraw) params ["_l", "_ink"];
        {
            _x params ["_t", "_c"];
            _map drawIcon [_t, [_c select 0, _c select 1, _c select 2, (_c select 3) * _al], _pos, _size, _size, 0, "", 0];
        } forEach _l;
        private _ik = [_ink select 0, _ink select 1, _ink select 2, _al];
        // Direction du mouvement (2525 : flèche depuis le symbole), seulement si l'unité se déplace.
        if (_dir >= 0) then { _map drawIcon [_root + "dir_ca.paa", _ik, _pos, _size, _size, _dir, "", 0]; };
        // Modificateurs de texte à gauche du cadre (désignation, unité supérieure, vitesse) : seulement quand la place le
        // permet (symbole d'au moins 44 px), texte de 14 px au moins.
        if ((count _txt) > 0 && {_size >= 44}) then {
            private _ts = (14 * pixelH) max (_size * pixelH * 0.26);
            private _mpx = ["mpx", _map] call comspec_atak_native_fnc_planGfx;
            {
                if (_x isEqualTo "") then { continue };
                private _p = [_pos select 0, (_pos select 1) + (0.5 - _forEachIndex) * _size * 0.3 * _mpx, 0];
                _map drawIcon ["#(argb,8,8,3)color(0,0,0,0)", _ik, _p, _size * 0.75, _size * 0.3, 0, _x, 2, _ts, "EtelkaMonospaceProBold", "left"];
            } forEach _txt;
        };
    };
    default { nil };
};
