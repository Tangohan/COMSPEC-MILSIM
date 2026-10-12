/*
    Catalogue de la carte de briefing (app Plan) : graphismes de tâche et de manœuvre APP-6A, unités, notes,
    nettoyage des bâtiments, couleurs. Lu une fois puis gardé (uiNamespace COMSPEC_ATAK_PlanCat).
    Renvoie un HashMap :
      "kinds"  : clé -> [clé, bouton, libellé, géométrie, couleur, texte par défaut, tirets, flèche, style, catégorie]
                 géométrie : AREA (polygone, 2 clics = rectangle), LINE (trait ou crayon), POINT (1 clic), DIR (position puis visée)
                 style : rendu de fn_planDraw (PLAIN, PHASE, AXIS, ATK, CHECK, WARN, NATO_B, NATO_O, NOTE, ICON:<texture>, SBF, ABF)
      "cats"   : [[catégorie, libellé, [clés...]]...] dans l'ordre des onglets
      "colors" : clé -> RGBA ; "colorOrder" : clés dans l'ordre de la palette ; "arma" : clé -> couleur CfgMarkerColors
*/
private _cached = uiNamespace getVariable "COMSPEC_ATAK_PlanCat";
if (!isNil "_cached") exitWith { _cached };
private _m = "\A3\ui_f\data\map\markers\military\";
private _list = [
    // Zones
    ["ENY", "ENY", "Ennemi (zone)", "AREA", "RED", "ENY", false, false, "PLAIN", "AREA"],
    ["INFIL", "INFIL", "Infiltration", "AREA", "CYAN", "INFIL", false, false, "PLAIN", "AREA"],
    ["EXFIL", "EXFIL", "Exfiltration", "AREA", "CYAN", "EXFIL", false, false, "PLAIN", "AREA"],
    ["OBJ", "OBJ", "Objectif", "AREA", "YELLOW", "OBJ", false, false, "PLAIN", "AREA"],
    ["AO", "AO", "Zone d'action (AO)", "AREA", "WHITE", "AO", true, false, "PLAIN", "AREA"],
    ["NAI", "NAI", "Zone d'intérêt (NAI)", "AREA", "PURPLE", "NAI", true, false, "PLAIN", "AREA"],
    ["EA", "EA", "Zone d'engagement (EA)", "AREA", "RED", "EA", false, false, "PLAIN", "AREA"],
    ["AA", "AA", "Zone de regroupement (AA)", "AREA", "BLUE", "AA", false, false, "PLAIN", "AREA"],
    ["HLZ", "HLZ", "Zone de poser (HLZ)", "AREA", "GREEN", "HLZ", false, false, "PLAIN", "AREA"],
    ["NFA", "NFA", "Tirs interdits (NFA)", "AREA", "PURPLE", "NFA", false, false, "PLAIN", "AREA"],
    ["ZONE", "ZONE", "Zone libre", "AREA", "WHITE", "", false, false, "PLAIN", "AREA"],
    // Lignes et axes
    ["PL", "PL", "Ligne de phase (PL)", "LINE", "WHITE", "PL", false, false, "PHASE", "LINE"],
    ["LD", "LD", "Ligne de débouché (LD)", "LINE", "WHITE", "LD", false, false, "PHASE", "LINE"],
    ["LOA", "LOA", "Limite d'avance (LOA)", "LINE", "WHITE", "LOA", false, false, "PHASE", "LINE"],
    ["BDY", "LIMITE", "Limite", "LINE", "WHITE", "", true, false, "PLAIN", "LINE"],
    ["ROUTE", "ITIN.", "Itinéraire", "LINE", "YELLOW", "", true, true, "PLAIN", "LINE"],
    ["MOVE", "MOUV.", "Mouvement / infiltration", "LINE", "CYAN", "", true, true, "PLAIN", "LINE"],
    ["AXIS", "AXE", "Axe de progression", "LINE", "BLUE", "", false, true, "AXIS", "LINE"],
    ["ATK", "ATK", "Attaque principale", "LINE", "BLUE", "", false, true, "ATK", "LINE"],
    ["SPT", "SPT", "Attaque secondaire", "LINE", "BLUE", "", true, true, "ATK", "LINE"],
    ["FREE", "CRAYON", "Crayon libre", "LINE", "RED", "", false, false, "PLAIN", "LINE"],
    // Points, unités, bâtiments
    ["CLEARED", "NETTOYÉ", "Bâtiment nettoyé (CLEARED n)", "POINT", "GREEN", "CLEARED", false, false, "CHECK", "POINT"],
    ["UNCLEARED", "À NETT.", "Bâtiment non nettoyé (UNCLEARED n)", "POINT", "YELLOW", "UNCLEARED", false, false, "WARN", "POINT"],
    ["UNITF", "AMI", "Unité amie", "POINT", "BLUE", "", false, false, "NATO_B", "POINT"],
    ["UNITH", "ENI", "Unité ennemie", "POINT", "RED", "", false, false, "NATO_O", "POINT"],
    ["NOTE", "NOTE", "Note", "POINT", "WHITE", "NOTE", false, false, "NOTE", "POINT"],
    ["CP", "CP", "Point de contrôle (CP)", "POINT", "WHITE", "CP", false, false, "ICON:" + _m + "circle_CA.paa", "POINT"],
    ["RP", "RP", "Point de ralliement (RP)", "POINT", "GREEN", "RP", false, false, "ICON:" + _m + "triangle_CA.paa", "POINT"],
    ["TRP", "TRP", "Point de référence de tir (TRP)", "POINT", "RED", "TRP", false, false, "ICON:" + _m + "destroy_CA.paa", "POINT"],
    ["OBJPT", "OBJ", "Objectif (point)", "POINT", "YELLOW", "OBJ", false, false, "ICON:" + _m + "objective_CA.paa", "POINT"],
    ["SBF", "SBF", "Appui par le feu (SBF) : position puis visée", "DIR", "BLUE", "SBF", false, true, "SBF", "POINT"],
    ["ABF", "ABF", "Attaque par le feu (ABF) : position puis visée", "DIR", "BLUE", "ABF", false, true, "ABF", "POINT"]
];
private _kinds = createHashMap;
{ _kinds set [_x select 0, _x]; } forEach _list;
private _cats = [["AREA", "ZONES"], ["LINE", "LIGNES"], ["POINT", "POINTS"]] apply {
    _x params ["_c", "_t"];
    [_c, _t, (_list select { (_x select 9) isEqualTo _c }) apply { _x select 0 }]
};
private _colors = createHashMapFromArray [
    ["RED", [0.95, 0.30, 0.28, 1]], ["CYAN", [0.30, 0.80, 0.95, 1]], ["GREEN", [0.45, 0.85, 0.45, 1]], ["YELLOW", [0.98, 0.88, 0.30, 1]],
    ["BLUE", [0.35, 0.60, 1, 1]], ["WHITE", [0.95, 0.95, 0.95, 1]], ["ORANGE", [1, 0.58, 0.20, 1]], ["PURPLE", [0.78, 0.50, 0.95, 1]], ["BLACK", [0.08, 0.08, 0.08, 1]]
];
private _arma = createHashMapFromArray [
    ["RED", "ColorRed"], ["CYAN", "ColorBlue"], ["GREEN", "ColorGreen"], ["YELLOW", "ColorYellow"], ["BLUE", "ColorBlue"],
    ["WHITE", "ColorWhite"], ["ORANGE", "ColorOrange"], ["PURPLE", "ColorPink"], ["BLACK", "ColorBlack"]
];
private _cat = createHashMapFromArray [
    ["kinds", _kinds], ["cats", _cats], ["colors", _colors],
    ["colorOrder", ["RED", "ORANGE", "YELLOW", "GREEN", "CYAN", "BLUE", "PURPLE", "WHITE", "BLACK"]], ["arma", _arma]
];
uiNamespace setVariable ["COMSPEC_ATAK_PlanCat", _cat];
_cat
