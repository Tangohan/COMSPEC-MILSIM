/*
    Formes du joueur et des alliés sur la carte du téléphone, et leur aperçu dans les réglages. Textures blanches générées
    (tools/gen_map_pawn.py), colorées par drawIcon : 1 à 4 dessins par symbole, rien d'autre à calculer.
    Couches d'une forme : [texture, rôle (COL couleur | DARK fond sombre | WHITE blanc), échelle, tourne avec le cap, lettre].
    Formes d'origine (FLÈCHE, CHEVRON, POINT, CERCLE, TRIANGLE, AMI APP-6) : gardées par fn_mapPrefs « self », un ancien
    profil garde la sienne ; AUTO (alliés) : symbole d'unité, comme avant.
    Params : [opération, argument]
      "draw" [carte, forme, position, taille px, couleur, cap, lettre]  (à chaque image : traité avant tout le reste)
      "isNew" forme -> vrai si cette fonction la dessine
      "self" -> [[clé, libellé, aide]...]     "ally" -> idem      "label" clé -> libellé
      "preview" clé -> couches [[texture, couleur, échelle]...] pour une ligne « shapes » de fn_formRender
      "cell" [clé, libellé, code, choisie, aide, couleur] -> cellule d'une ligne « shapes »
*/
params [["_op", ""], ["_a", []]];
private _d = "\z\comspec_atak_native\addons\main\data\";
private _tb = uiNamespace getVariable "COMSPEC_ATAK_ShapeTab";
if (isNil "_tb") then {
    _tb = createHashMapFromArray [
        ["PAWN", [[_d + "map_pawn_disc.paa", "DARK", 1, false, false], [_d + "map_pawn_ring.paa", "COL", 1, false, true], [_d + "map_pawn_tick.paa", "WHITE", 1.5, true, false]]],
        ["TRI_RING", [[_d + "map_keyring.paa", "COL", 1.7, false, false], [_d + "map_shape_tri.paa", "COL", 0.8, true, false]]],
        ["DIAMOND_RING", [[_d + "map_keyring.paa", "COL", 1.7, false, false], [_d + "map_shape_diamond.paa", "COL", 0.8, false, false]]],
        ["SQUARE", [[_d + "map_shape_square.paa", "COL", 0.85, false, false]]],
        ["CROSS", [[_d + "map_shape_cross.paa", "COL", 1, false, false]]]
    ];
    uiNamespace setVariable ["COMSPEC_ATAK_ShapeTab", _tb];
};
if (_op isEqualTo "draw") exitWith {
    _a params ["_map", "_k", "_pos", "_size", "_c", ["_dir", 0], ["_ltr", ""]];
    {
        _x params ["_tex", "_role", "_sc", "_turn", "_lt"];
        private _col = switch (_role) do { case "DARK": { [0.03, 0.045, 0.05, _c select 3] }; case "WHITE": { [1, 1, 1, _c select 3] }; default { _c }; };
        private _s = _size * _sc;
        _map drawIcon [_tex, _col, _pos, _s, _s, [0, _dir] select _turn, "", 0];
        if (_lt && {_size >= 14}) then { _map drawIcon ["#(argb,8,8,3)color(0,0,0,0)", [1, 1, 1, _c select 3], _pos, 0, 0, 0, _ltr, 0, _size * pixelH * 0.6, "EtelkaMonospaceProBold", "center"]; };
    } forEach (_tb getOrDefault [_k, []]);
};
switch (_op) do {
    case "isNew": { _a in _tb };
    case "self": { [
        ["ARROW", "FLÈCHE", "Symbole d'unité orienté (par défaut)"], ["CHEVRON", "CHEVRON", ""], ["DOT", "POINT", ""], ["CIRCLE", "CERCLE", ""], ["TRIANGLE", "TRIANGLE", ""], ["NATO", "AMI APP-6", "Symbole ami de la symbologie"],
        ["PAWN", "PION", "Anneau, lettre d'équipe et petit triangle de direction"], ["TRI_RING", "TRIANGLE CERCLÉ", "Triangle plein orienté dans un cercle fin"],
        ["DIAMOND_RING", "LOSANGE CERCLÉ", "Losange dans un cercle fin"], ["SQUARE", "CARRÉ", ""], ["CROSS", "CROIX", ""]
    ] };
    case "ally": { [
        ["AUTO", "AUTO", "Symbole d'unité, ou symbole OTAN selon « Icônes des unités » (par défaut)"],
        ["PAWN", "PION", "Anneau, lettre d'équipe et petit triangle de direction"], ["TRI_RING", "TRIANGLE CERCLÉ", "Triangle plein orienté dans un cercle fin"],
        ["DIAMOND_RING", "LOSANGE CERCLÉ", "Losange dans un cercle fin"], ["SQUARE", "CARRÉ", ""], ["CROSS", "CROIX", ""]
    ] };
    case "label": {
        private _i = ((["self"] call comspec_atak_native_fnc_mapShape) + [["AUTO", "AUTO", ""]]) select { (_x select 0) isEqualTo _a };
        (_i param [0, ["", "FLÈCHE"]]) select 1
    };
    case "preview": {
        _a params ["_k", "_c"];
        private _m = "\A3\ui_f\data\map\markers\military\";
        if (_k in _tb) exitWith {
            (_tb get _k) apply {
                _x params ["_tex", "_role", "_sc"];
                [_tex, switch (_role) do { case "DARK": { [0.03, 0.045, 0.05, 1] }; case "WHITE": { [1, 1, 1, 1] }; default { _c }; }, _sc * 0.5]
            }
        };
        switch (_k) do {
            case "CHEVRON": { [[_m + "arrow2_CA.paa", _c, 0.7]] };
            case "DOT": { [[_m + "dot_CA.paa", _c, 0.9]] };
            case "CIRCLE": { [[_m + "circle_CA.paa", _c, 0.75]] };
            case "TRIANGLE": { [[_m + "triangle_CA.paa", _c, 0.7]] };
            case "NATO": { [["\A3\ui_f\data\map\markers\nato\b_inf.paa", _c, 0.8]] };
            default { [["\A3\ui_f\data\map\vehicleicons\iconMan_ca.paa", _c, 0.7]] };
        }
    };
    default { nil };
};
