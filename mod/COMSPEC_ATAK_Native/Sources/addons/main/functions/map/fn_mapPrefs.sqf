/*
    Personnalisation de la carte par le joueur (profil), réglée dans le volet de configuration de l'app Carte :
      - bandeau du curseur : style NEW (2.9.34, par défaut) | CLASSIC (rendu d'avant 2.9.34, bugs corrigés) | COMPACT
        (une ligne) | HIDDEN ; éléments affichés (GRID grille, DIST distance et gisement, ALT altitude, HDG mon cap,
        ME indicatif et position, ZONE zone de l'app Zones) ; grille MGRS (complète, précision des Réglages) | 8 | 10
        chiffres | LL latitude-longitude ; unités M (m, km) | IMP (ft, mi) ; position BOTTOM | TOP ; opacité du fond
        (-1 : celle du style) ; taille du texte ;
      - mon point GPS : forme ARROW (symbole d'unité, par défaut) | CHEVRON | DOT | CIRCLE | TRIANGLE | NATO (ami APP-6) | PAWN |
        TRI_RING | DIAMOND_RING | SQUARE | CROSS (fn_mapShape, même jeu pour les alliés : COMSPEC_ATAK_AllyShape, AUTO par défaut),
        couleur (Réglages > Carte « Ma couleur », la même), taille, cône de cap, trace (fil d'Ariane) et sa durée,
        indicatif AUTO (comme avant) | ON | OFF.
    Tout est préparé au changement de réglage (uiNamespace COMSPEC_ATAK_BandCfg / COMSPEC_ATAK_SelfDraw) : rien de plus
    par image.
    Params : [opération, argument]
      "band"                -> HashMap du bandeau          "bandSet" [clé, valeur] | "bandShow" élément (bascule)
      "self"                -> [icône ("" : symbole d'unité), orientée, facteur de taille, cône, trace, indicatif, forme, forme des alliés]
      "selfSet" [clé, valeur]
      "trail"               relevé de la trace (toutes les 2 s, XEH_postInitClient) ; "trailPts" -> points [x, y, 0]
      "rows" "band" | "self" -> lignes de fn_formRender pour le volet de l'app Carte
      "dist" m -> texte de distance ; "alt" m -> texte d'altitude ; "grid" position -> texte de la grille (bandeau)
*/
params [["_op", ""], ["_arg", []]];
private _prof = profileNamespace;
private _redraw = {
    [] call comspec_atak_native_fnc_saveProfile;
    [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "MAP") then { ["MAP"] call comspec_atak_native_fnc_pageRender; }; }] call comspec_atak_native_fnc_nextFrame;
};
// CURDIST (2.9.36, bandeau NOUVEAU) : distance et gisement vers le curseur dans le bandeau ; absent par défaut (la flèche
// et le cap donnent alors la vue du personnage, le gisement vers le curseur reste dans l'étiquette de survol).
private _allShow = ["GRID", "DIST", "ALT", "HDG", "ME", "ZONE", "CURDIST"];
private _defShow = _allShow - ["CURDIST"];
switch (_op) do {
    case "band": {
        private _c = uiNamespace getVariable ["COMSPEC_ATAK_BandCfg", createHashMap];
        if ((count _c) > 0) exitWith { _c };
        private _pick = { params ["_var", "_def", "_ok"]; private _v = _prof getVariable [_var, _def]; [_def, _v] select (_v isEqualType _def && {_v in _ok}) };
        private _show = _prof getVariable ["COMSPEC_ATAK_BandShow", _defShow];
        if !(_show isEqualType []) then { _show = _defShow; };
        private _al = _prof getVariable ["COMSPEC_ATAK_BandAlpha", -1];
        if !(_al isEqualType 0) then { _al = -1; };
        private _tx = _prof getVariable ["COMSPEC_ATAK_BandText", 1];
        if !(_tx isEqualType 0) then { _tx = 1; };
        _c = createHashMapFromArray [
            ["style", ["COMSPEC_ATAK_BandStyle", "NEW", ["NEW", "CLASSIC", "COMPACT", "HIDDEN"]] call _pick],
            ["show", _show select { _x in _allShow }],
            ["grid", ["COMSPEC_ATAK_BandGrid", "MGRS", ["MGRS", "8", "10", "LL"]] call _pick],
            ["unit", ["COMSPEC_ATAK_BandUnit", "M", ["M", "IMP"]] call _pick],
            ["pos", ["COMSPEC_ATAK_BandPos", "BOTTOM", ["BOTTOM", "TOP"]] call _pick],
            ["alpha", [(_al max 0.2) min 1, -1] select (_al < 0)],
            ["text", (_tx max 0.8) min 1.5]
        ];
        uiNamespace setVariable ["COMSPEC_ATAK_BandCfg", _c];
        _c
    };
    case "bandSet": {
        _arg params ["_k", "_v"];
        private _var = createHashMapFromArray [["style", "COMSPEC_ATAK_BandStyle"], ["grid", "COMSPEC_ATAK_BandGrid"], ["unit", "COMSPEC_ATAK_BandUnit"], ["pos", "COMSPEC_ATAK_BandPos"], ["alpha", "COMSPEC_ATAK_BandAlpha"], ["text", "COMSPEC_ATAK_BandText"]];
        if !(_k in _var) exitWith {};
        _prof setVariable [_var get _k, _v];
        uiNamespace setVariable ["COMSPEC_ATAK_BandCfg", createHashMap];
        (uiNamespace getVariable ["COMSPEC_ATAK_MapOverlay", createHashMap]) set ["meL2", ""];
        call _redraw;
    };
    case "bandShow": {
        private _show = +((["band"] call comspec_atak_native_fnc_mapPrefs) get "show");
        if (_arg in _show) then { _show = _show - [_arg]; } else { _show pushBack _arg; };
        _prof setVariable ["COMSPEC_ATAK_BandShow", _show];
        uiNamespace setVariable ["COMSPEC_ATAK_BandCfg", createHashMap];
        (uiNamespace getVariable ["COMSPEC_ATAK_MapOverlay", createHashMap]) set ["meL2", ""];
        call _redraw;
    };
    case "bandShowSet": {
        _arg params ["_k", "_v"];
        private _show = +((["band"] call comspec_atak_native_fnc_mapPrefs) get "show");
        if (_v isEqualTo (_k in _show)) exitWith {};
        ["bandShow", _k] call comspec_atak_native_fnc_mapPrefs;
    };
    case "self": {
        private _c = uiNamespace getVariable ["COMSPEC_ATAK_SelfDraw", []];
        if ((count _c) > 0) exitWith { _c };
        // Valeur illisible d'un ancien profil : forme par défaut, sans erreur.
        private _shape = _prof getVariable ["COMSPEC_ATAK_SelfShape", "ARROW"];
        if !(_shape isEqualType "") then { _shape = "ARROW"; };
        private _allyS = _prof getVariable ["COMSPEC_ATAK_AllyShape", "AUTO"];
        if !(_allyS isEqualType "") then { _allyS = "AUTO"; };
        private _m = "\A3\ui_f\data\map\markers\military\";
        (switch (_shape) do {
            case "CHEVRON": { [_m + "arrow2_CA.paa", true] };
            case "DOT": { [_m + "dot_CA.paa", false] };
            case "CIRCLE": { [_m + "circle_CA.paa", false] };
            case "TRIANGLE": { [_m + "triangle_CA.paa", true] };
            case "NATO": { ["\A3\ui_f\data\map\markers\nato\b_inf.paa", false] };
            default { ["", true] };
        }) params ["_icon", "_orient"];
        private _sz = _prof getVariable ["COMSPEC_ATAK_SelfSize", 1];
        if !(_sz isEqualType 0) then { _sz = 1; };
        private _lbl = _prof getVariable ["COMSPEC_ATAK_SelfLabel", "AUTO"];
        if !(_lbl in ["AUTO", "ON", "OFF"]) then { _lbl = "AUTO"; };
        _c = [_icon, _orient, (_sz max 0.6) min 2, _prof getVariable ["COMSPEC_ATAK_SelfCone", false], _prof getVariable ["COMSPEC_ATAK_SelfTrail", false], _lbl, _shape, _allyS];
        uiNamespace setVariable ["COMSPEC_ATAK_SelfDraw", _c];
        _c
    };
    case "selfSet": {
        _arg params ["_k", "_v"];
        private _var = createHashMapFromArray [["shape", "COMSPEC_ATAK_SelfShape"], ["size", "COMSPEC_ATAK_SelfSize"], ["cone", "COMSPEC_ATAK_SelfCone"], ["trail", "COMSPEC_ATAK_SelfTrail"], ["trailMin", "COMSPEC_ATAK_SelfTrailMin"], ["label", "COMSPEC_ATAK_SelfLabel"], ["color", "COMSPEC_ATAK_SelfColor"], ["allyShape", "COMSPEC_ATAK_AllyShape"], ["markerType", "COMSPEC_ATAK_LastMarkerType"]];
        if !(_k in _var) exitWith {};
        _prof setVariable [_var get _k, _v];
        uiNamespace setVariable ["COMSPEC_ATAK_SelfDraw", []];
        if (_k isEqualTo "trail" && {!_v}) then { missionNamespace setVariable ["COMSPEC_ATAK_SelfTrailPts", []]; };
        call _redraw;
    };
    // Trace : un point toutes les 2 s si j'ai bougé de 4 m, gardé pendant la durée choisie (400 points au plus).
    case "trail": {
        if !((["self"] call comspec_atak_native_fnc_mapPrefs) select 4) exitWith {};
        private _pts = missionNamespace getVariable ["COMSPEC_ATAK_SelfTrailPts", []];
        private _p = uiNamespace getVariable ["COMSPEC_ATAK_SelfGpsPos", getPosASL (vehicle player)];
        private _ttl = ((_prof getVariable ["COMSPEC_ATAK_SelfTrailMin", 10]) max 1) * 60;
        if ((count _pts) isEqualTo 0 || {((_pts select -1) distance2D _p) > 4}) then { _pts pushBack [_p select 0, _p select 1, time]; };
        while { (count _pts) > 0 && {((_pts select 0) select 2) < (time - _ttl)} } do { _pts deleteAt 0; };
        if ((count _pts) > 400) then { _pts deleteRange [0, (count _pts) - 400]; };
        missionNamespace setVariable ["COMSPEC_ATAK_SelfTrailPts", _pts];
    };
    case "dist": {
        private _d = _arg;
        if (((["band"] call comspec_atak_native_fnc_mapPrefs) get "unit") isEqualTo "IMP") exitWith {
            [format ["%1 ft", round (_d * 3.28084)], format ["%1 mi", (_d / 1609.344) toFixed 2]] select (_d >= 1609.344)
        };
        [format ["%1 m", round _d], format ["%1 km", (_d / 1000) toFixed 2]] select (_d >= 1000)
    };
    case "alt": {
        if (((["band"] call comspec_atak_native_fnc_mapPrefs) get "unit") isEqualTo "IMP") exitWith { format ["%1 ft", round (_arg * 3.28084)] };
        format ["%1 m", round _arg]
    };
    // Grille du bandeau : [position, préfixe de zone MGRS] -> texte.
    case "grid": {
        _arg params ["_p", ["_zone", ""]];
        switch ((["band"] call comspec_atak_native_fnc_mapPrefs) get "grid") do {
            case "8": { [_p, 8] call comspec_atak_native_fnc_gridRef };
            case "10": { [_p, 10] call comspec_atak_native_fnc_gridRef };
            case "LL": {
                // Latitude et longitude approchées d'après le centre de la carte (CfgWorlds, latitude comptée au sud).
                private _cfg = configFile >> "CfgWorlds" >> worldName;
                private _lat0 = -(getNumber (_cfg >> "latitude"));
                private _lon0 = getNumber (_cfg >> "longitude");
                private _lat = _lat0 + ((_p select 1) - worldSize / 2) / 111320;
                private _lon = _lon0 + ((_p select 0) - worldSize / 2) / (111320 * ((cos _lat) max 0.01));
                format ["%1%2 %3%4", (abs _lat) toFixed 5, ["S", "N"] select (_lat >= 0), (abs _lon) toFixed 5, ["W", "E"] select (_lon >= 0)]
            };
            default { format ["%1%2", _zone, [_p] call comspec_atak_native_fnc_gridRef] };
        }
    };
    case "trailPts": { missionNamespace getVariable ["COMSPEC_ATAK_SelfTrailPts", []] };
    case "rows": {
        private _rows = [];
        private _seg = { params ["_op2", "_k", "_now", "_opts"]; _opts apply { _x params ["_t", "_v", ["_tip", ""]]; [_t, format ["[%1, [%2, %3]] call comspec_atak_native_fnc_mapPrefs;", str _op2, str _k, str _v], _v isEqualTo _now, _tip] } };
        if (_arg isEqualTo "band") then {
            private _c = ["band"] call comspec_atak_native_fnc_mapPrefs;
            _rows pushBack ["segment", "Style", ["bandSet", "style", _c get "style", [
                ["NOUVEAU", "NEW", "Deux cases : position en grand, détails dessous, gisement fléché (2.9.34)"],
                ["CLASSIQUE", "CLASSIC", "Rendu d'avant 2.9.34 : valeur en grand et détails sur la même case"],
                ["COMPACT", "COMPACT", "Une seule ligne"],
                ["MASQUÉ", "HIDDEN", "Pas de bandeau"]
            ]] call _seg];
            if ((_c get "style") isNotEqualTo "HIDDEN") then {
                private _show = _c get "show";
                _rows pushBack ["segment", "Éléments affichés", [
                    ["GRILLE", "GRID", "Position du curseur (ou la mienne en porté)"], ["DIST. / GIS.", "DIST", "Distance et gisement depuis moi"], ["ALT", "ALT", "Altitude du terrain"],
                    ["CAP", "HDG", "Mon cap"], ["MOI", "ME", "Mon indicatif, mon équipe et ma grille"], ["ZONE", "ZONE", "Zone de l'app Zones sous le curseur"]
                ] apply { _x params ["_t", "_k", "_tip"]; [_t, format ["['bandShow', %1] call comspec_atak_native_fnc_mapPrefs;", str _k], _k in _show, _tip] }];
                if ((_c get "style") isEqualTo "NEW") then {
                    _rows pushBack ["segment", "Distance / gisement vers le curseur dans le bandeau", [
                        ["NON", "['bandShowSet', ['CURDIST', false]] call comspec_atak_native_fnc_mapPrefs;", !("CURDIST" in _show), "La flèche et le cap du bandeau donnent la direction de ma vue ; distance et gisement vers le curseur restent dans l'étiquette de survol"],
                        ["OUI", "['bandShowSet', ['CURDIST', true]] call comspec_atak_native_fnc_mapPrefs;", "CURDIST" in _show, "Comportement de la 2.9.35 : la flèche suit le curseur"]
                    ]];
                };
                _rows pushBack ["segment", "Grille", ["bandSet", "grid", _c get "grid", [
                    ["MGRS", "MGRS", "Zone et carré MGRS, précision de Réglages > Carte"], ["8 CHIFFRES", "8"], ["10 CHIFFRES", "10"], ["LAT / LONG", "LL", "Latitude et longitude approchées"]
                ]] call _seg];
                _rows pushBack ["segment", "Unités", ["bandSet", "unit", _c get "unit", [["M / KM", "M"], ["FT / MI", "IMP"]]] call _seg];
                _rows pushBack ["segment", "Position", ["bandSet", "pos", _c get "pos", [["EN BAS", "BOTTOM"], ["EN HAUT", "TOP"]]] call _seg];
                _rows pushBack ["segment", "Fond", ["bandSet", "alpha", _c get "alpha", [["AUTO", -1, "Celui du style"], ["LÉGER", 0.5], ["NORMAL", 0.74], ["OPAQUE", 0.95]]] call _seg];
                _rows pushBack ["segment", "Texte", ["bandSet", "text", _c get "text", [["PETIT", 0.85], ["NORMAL", 1], ["GRAND", 1.2], ["TRÈS GRAND", 1.4]]] call _seg];
            };
        } else {
            (["self"] call comspec_atak_native_fnc_mapPrefs) params ["", "", "_sz", "_cone", "_trail", "_lbl", "_shape", "_allyS"];
            private _palS = [] call comspec_atak_native_fnc_mapColors;
            // Lignes à aperçu : chaque choix montre le dessin, à sa couleur, sur un fond de carte.
            private _cells = {
                params ["_list", "_key", "_now", "_col"];
                _list apply {
                    _x params ["_k", "_t", ["_tip", ""]];
                    [_t, format ["['selfSet', [%1, %2]] call comspec_atak_native_fnc_mapPrefs;", str _key, str _k], _k isEqualTo _now, _tip, ["preview", [_k, _col]] call comspec_atak_native_fnc_mapShape]
                }
            };
            _rows pushBack ["shapes", "Forme", [["self"] call comspec_atak_native_fnc_mapShape, "shape", _shape, _palS get "self"] call _cells];
            private _selfNow = profileNamespace getVariable ["COMSPEC_ATAK_SelfColor", "CYAN"];
            _rows pushBack ["lineseg", "", "Couleur", [["CYAN", "CYAN"], ["BLEU", "BLUE"], ["VERT", "GREEN"], ["BLANC", "WHITE"], ["JAUNE", "YELLOW"], ["ORANGE", "ORANGE"], ["ROSE", "PINK"]] apply {
                [_x select 0, format ["['selfSet', ['color', %1]] call comspec_atak_native_fnc_mapPrefs;", str (_x select 1)], (_x select 1) isEqualTo _selfNow, _palS getOrDefault [_x select 1, [1, 1, 1, 1]]]
            }];
            _rows pushBack ["shapes", "Forme des alliés", [["ally"] call comspec_atak_native_fnc_mapShape, "allyShape", _allyS, _palS get "friend"] call _cells, "Couleur : Réglages > Carte, « Couleur des alliés »"];
            // Repères posés depuis le téléphone : symboles du moteur, lisibles aussi sur la carte d'Arma et sur Athena.
            private _mkNow = profileNamespace getVariable ["COMSPEC_ATAK_LastMarkerType", "mil_dot"];
            _rows pushBack ["shapes", "Forme de mes nouveaux repères", [["mil_dot", "POINT"], ["mil_triangle", "TRIANGLE"], ["mil_box", "CARRÉ"], ["mil_destroy", "CROIX"], ["mil_circle", "CERCLE"], ["mil_objective", "OBJECTIF"]] apply {
                [_x select 1, format ["['selfSet', ['markerType', %1]] call comspec_atak_native_fnc_mapPrefs;", str (_x select 0)], (_x select 0) isEqualTo _mkNow, "", [[getText (configFile >> "CfgMarkers" >> (_x select 0) >> "icon"), [1, 0.82, 0.25, 1], 0.7]]]
            }, "Aussi sur la carte d'Arma et sur Athena ; modifiable sur chaque repère"];
            _rows pushBack ["segment", "Taille", ["selfSet", "size", _sz, [["PETIT", 0.8], ["NORMAL", 1], ["GRAND", 1.3], ["TRÈS GRAND", 1.6]]] call _seg];
            _rows pushBack ["segment", "Cône de cap", ["selfSet", "cone", _cone, [["NON", false], ["OUI", true, "Cône clair dans la direction où je regarde"]]] call _seg];
            _rows pushBack ["segment", "Trace", ["selfSet", "trail", _trail, [["NON", false], ["OUI", true, "Fil d'Ariane de mes positions"]]] call _seg];
            if (_trail) then {
                _rows pushBack ["segment", "Durée de la trace", ["selfSet", "trailMin", profileNamespace getVariable ["COMSPEC_ATAK_SelfTrailMin", 10], [["2 MIN", 2], ["5 MIN", 5], ["10 MIN", 10], ["30 MIN", 30]]] call _seg];
            };
            _rows pushBack ["segment", "Mon indicatif", ["selfSet", "label", _lbl, [["AUTO", "AUTO", "Comme les autres unités (petite carte)"], ["TOUJOURS", "ON"], ["JAMAIS", "OFF"]]] call _seg];
        };
        _rows
    };
    default { nil };
};
