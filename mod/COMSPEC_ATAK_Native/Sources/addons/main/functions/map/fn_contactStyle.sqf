/*
    Contacts de la carte du téléphone (pistes ennemies, neutres, inconnues, civiles, repères o_ n_ u_ b_ c_) : l'affiliation se
    lit à la FORME, jamais à la couleur seule (norme APP-6 / 2525) :
      ami         cercle     ennemi    losange     neutre    carré     inconnu   carré à quatre bras     civil    triangle
    Chaque symbole = un liseré à la couleur de l'affiliation et un fond sombre dessus (2 dessins, textures de data\ déjà
    livrées : map_keyring, map_pawn_disc, map_shape_*). Aucune géométrie à calculer par image : la table des couches est
    gardée en uiNamespace (COMSPEC_ATAK_ContactShapes). Les symboles MIL-STD-2525D (fn_milDraw) restent entiers : cette
    fonction habille les pions du style tactique et les repères, pas les cadres 2525D.
    Fraîcheur : piste figée ou perdue = estompée (alpha de fn_mapColors) + âge court écrit sous le symbole.
    Params : [opération, argument]
      "layers" affiliation                    -> [[texture, rôle (COL | DARK), échelle]...]
      "frame" [carte, affiliation, position, taille px, couleur rgba]       (liseré + fond, à chaque image)
      "draw" [carte, affiliation, position, taille px, couleur, cap (-1 : inconnu), âge s (-1 : direct), taille de texte,
              mètres par pixel]               -> symbole, triangle de cap seulement si le cap est connu, âge si périmée
      "aff" entité                            -> clé : friend | hostile | neutral | unknown | civil
      "markerAff" classe de marqueur          -> clé de l'affiliation d'un repère o_ n_ u_ b_ c_, "" sinon
      "rgb" affiliation                       -> couleur de la carte (fn_mapColors)
      "ink" affiliation                       -> encre de la fiche (jetons mapFriend… de fn_theme)
      "hex" affiliation                       -> "#rrggbb" de cette encre
      "label" affiliation                     -> ENNEMI, AMI, NEUTRE, INCONNU, CIVIL (traduit)
      "ageShort" secondes                     -> "12 s", "4 min", "1 h 05" (même texte dans les deux langues)
      "age" secondes                          -> « à l'instant », « il y a 4 min » (traduit)
      "scaleK" échelle de la carte            -> facteur de taille par paliers de 5 % (plus grand en zoom rapproché)
      "mark" entité                           -> pose un repère partagé (fn_markerDrop) à la position de la piste
*/
params [["_op", ""], ["_a", []]];
private _d = "\z\comspec_atak_native\addons\main\data\";
private _tb = uiNamespace getVariable "COMSPEC_ATAK_ContactShapes";
if (isNil "_tb") then {
    _tb = createHashMapFromArray [
        ["friend", [[_d + "map_keyring.paa", "COL", 1.7], [_d + "map_pawn_disc.paa", "DARK", 1.5]]],
        ["hostile", [[_d + "map_shape_diamond.paa", "COL", 1.9], [_d + "map_shape_diamond.paa", "DARK", 1.38]]],
        ["neutral", [[_d + "map_shape_square.paa", "COL", 1.7], [_d + "map_shape_square.paa", "DARK", 1.26]]],
        ["unknown", [[_d + "map_shape_cross.paa", "COL", 2.0], [_d + "map_shape_square.paa", "DARK", 1.12]]],
        ["civil", [[_d + "map_shape_tri.paa", "COL", 1.9], [_d + "map_shape_tri.paa", "DARK", 1.38]]]
    ];
    uiNamespace setVariable ["COMSPEC_ATAK_ContactShapes", _tb];
};
private _dark = [0.03, 0.045, 0.05];
switch (_op) do {
    case "layers": { _tb getOrDefault [_a, _tb get "unknown"] };
    case "frame": {
        _a params ["_map", "_aff", "_pos", "_px", "_c"];
        private _al = _c select 3;
        {
            _x params ["_tex", "_role", "_sc"];
            private _s = _px * _sc;
            _map drawIcon [_tex, [_c, [_dark select 0, _dark select 1, _dark select 2, (_al * 0.94) max 0.5]] select (_role isEqualTo "DARK"), _pos, _s, _s, 0, "", 0];
        } forEach (_tb getOrDefault [_aff, _tb get "unknown"]);
    };
    case "draw": {
        _a params ["_map", "_aff", "_pos", "_px", "_c", ["_dir", -1], ["_age", -1], ["_tsz", 0.02], ["_mpx", 1]];
        ["frame", [_map, _aff, _pos, _px, _c]] call comspec_atak_native_fnc_contactStyle;
        private _out = _px * (((_tb getOrDefault [_aff, _tb get "unknown"]) select 0) select 2);
        if (_dir >= 0) then { _map drawIcon [_d + "map_pawn_tick.paa", [1, 1, 1, _c select 3], _pos, _out * 1.5, _out * 1.5, _dir, "", 0]; };
        // Piste périmée : âge écrit sous le symbole (chiffres à chasse fixe, cerné de noir), seulement en vue rapprochée.
        if (_age > 20 && {_tsz > 0}) then {
            _map drawIcon ["#(argb,8,8,3)color(0,0,0,0)", [_c select 0, _c select 1, _c select 2, 1], [_pos select 0, (_pos select 1) - _out * 0.62 * _mpx, 0], 0, 0, 0, ["ageShort", _age] call comspec_atak_native_fnc_contactStyle, 2, _tsz, "EtelkaMonospacePro", "center"];
        };
    };
    case "aff": {
        switch (toLower (_a getOrDefault ["affiliation", "unknown"])) do {
            case "friend"; case "friendly"; case "ally"; case "blue": { "friend" };
            case "hostile"; case "enemy"; case "red": { "hostile" };
            case "neutral"; case "green": { "neutral" };
            case "civil"; case "civilian": { "civil" };
            default { "unknown" };
        }
    };
    case "markerAff": {
        switch ((toLower _a) select [0, 2]) do {
            case "o_": { "hostile" };
            case "b_": { "friend" };
            case "n_": { "neutral" };
            case "u_": { "unknown" };
            case "c_": { "civil" };
            default { "" };
        }
    };
    case "rgb": {
        private _pal = [] call comspec_atak_native_fnc_mapColors;
        +(switch (_a) do {
            case "friend": { _pal get "friend" };
            case "hostile": { _pal get "hostile" };
            case "neutral": { _pal get "neutral" };
            case "civil": { [0.85, 0.42, 0.76, 1] };
            default { _pal get "unknown" };
        })
    };
    case "ink": {
        private _th = COMSPEC_ATAK_Th;
        _th get (switch (_a) do { case "friend": { "mapFriend" }; case "hostile": { "mapHostile" }; case "neutral": { "mapNeutral" }; case "civil": { "mapCivil" }; default { "mapUnknown" }; })
    };
    case "hex": {
        private _th = COMSPEC_ATAK_Th;
        _th get (switch (_a) do { case "friend": { "hexMapFriend" }; case "hostile": { "hexMapHostile" }; case "neutral": { "hexMapNeutral" }; case "civil": { "hexMapCivil" }; default { "hexMapUnknown" }; })
    };
    case "label": {
        (switch (_a) do { case "friend": { "AMI" }; case "hostile": { "ENNEMI" }; case "neutral": { "NEUTRE" }; case "civil": { "CIVIL" }; default { "INCONNU" }; }) call comspec_atak_native_fnc_tr
    };
    case "ageShort": {
        private _s = round (_a max 0);
        switch (true) do {
            case (_s < 60): { format ["%1 s", _s] };
            case (_s < 3600): { format ["%1 min", floor (_s / 60)] };
            default {
                private _mn = floor ((_s mod 3600) / 60);
                format ["%1 h %2", floor (_s / 3600), (["", "0"] select (_mn < 10)) + str _mn]
            };
        }
    };
    case "age": {
        private _s = round (_a max 0);
        switch (true) do {
            case (_s < 5): { ("à l'instant") call comspec_atak_native_fnc_tr };
            case (_s < 60): { format [("il y a %1 s") call comspec_atak_native_fnc_tr, _s] };
            case (_s < 3600): { format [("il y a %1 min") call comspec_atak_native_fnc_tr, floor (_s / 60)] };
            default { format [("il y a %1 h %2 min") call comspec_atak_native_fnc_tr, floor (_s / 3600), floor ((_s mod 3600) / 60)] };
        }
    };
    case "scaleK": { (round ((((1.2 - _a * 0.8) max 0.9) min 1.25) * 20)) / 20 };
    case "mark": {
        private _p = _a getOrDefault ["position", []];
        if ((count _p) < 2) exitWith { "" };
        private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
        private _keep = [_s getOrDefault ["markerAff", "o"], _s getOrDefault ["markerType", ""], _s getOrDefault ["markerText", ""]];
        // Camp de la palette d'après l'affiliation de la piste ; type : le premier du camp (fn_markerDrop), nom : l'indicatif.
        _s set ["markerAff", switch (["aff", _a] call comspec_atak_native_fnc_contactStyle) do { case "friend": { "b" }; case "hostile": { "o" }; case "neutral": { "n" }; default { "u" }; }];
        _s set ["markerType", ""];
        _s set ["markerText", (trim (_a getOrDefault ["callsign", ""])) select [0, 24]];
        private _m = [[_p select 0, _p select 1, 0]] call comspec_atak_native_fnc_markerDrop;
        _s set ["markerAff", _keep select 0];
        _s set ["markerType", _keep select 1];
        _s set ["markerText", _keep select 2];
        _m
    };
    default { nil };
};
