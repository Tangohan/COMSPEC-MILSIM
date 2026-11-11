/*
    Thème des écrans TOC (demande de TTRD, 11/11 : « un rendu pro et propre » sur tous les écrans du mur et le PC du poste).
    Seule source des couleurs, des tailles de texte, des polices, des espacements et de la mise en page des écrans muraux
    (pixels virtuels 1600 x 900 ; faces 4:1 : 1600 x 400). Les tables entre « TABLE » et « FIN » sont du JSON strict :
    elles sont lues telles quelles par mod/COMSPEC_ATAK_Native/tools/toc_mockups.py (maquettes PNG, mêmes nombres) et par
    tests/Unit/AtakTocThemeTest.php (contraste, tailles, régions dans le cadre et sans chevauchement). Mode d'emploi :
    docs/TOC-THEME.md. Couleurs des contrôles de config du PC : ui/toc_theme.hpp (mêmes valeurs, vérifiées par le test).
    Modes :
      ["get"]                       table complète (gardée en cache) : "c" (RGBA), "hex", "type", "font", "lay", "cols", "sp"
      ["rgba", [jeton, alpha]]      couleur RGBA d'un jeton ("muted", "accent"…), alpha 1 par défaut
      ["hex", jeton]                "#rrggbb"
      ["ink", texte]                remplace color='@jeton' par color='#rrggbb' dans un texte structuré
      ["lay", affichage]            régions de l'affichage : table nom -> [x, y, l, h] (pixels virtuels)
      ["region", [affichage, nom, FLASH]]   région, descendue ou raccourcie sous un FLASH (table _flashRule)
      ["cols", table]               colonnes d'un tableau : [[clé, en-tête, x, l, alignement L|R|C|P (pastille), style]...]
      ["style", [contrôle, style, jeton, hauteur]]   police, taille (px virtuels sur la hauteur donnée, 900 par défaut), couleur
*/
params [["_mode", "get"], ["_a", ""]];
private _thm = missionNamespace getVariable ["COMSPEC_ATAK_TocTheme", createHashMap];
if ((count _thm) isEqualTo 0) then {
    // ---- TABLE _tokens (JSON) : couleurs. Fond 0 = écran, 1 = panneau, 2 = case en relief, 3 = survol ; line = filet.
    private _tokens = [
["bg0", "#070a0d"], ["bg1", "#0d1318"], ["bg2", "#141c23"], ["bg3", "#1c2630"],
["line", "#24303a"], ["lineStrong", "#3a4a57"],
["text", "#e8eef2"], ["text2", "#aab7c1"], ["muted", "#8494a0"],
["accent", "#34d399"], ["accentDim", "#0e3a2b"], ["amber", "#f5b841"], ["red", "#ff5f56"], ["redDim", "#3a1110"], ["blue", "#62b0f5"],
["friend", "#6cb8ff"], ["hostile", "#ff6b6b"], ["neutral", "#7ee08a"], ["unknown", "#f2d36b"], ["lost", "#8a97a2"]
];
    // ---- FIN
    // ---- TABLE _type (JSON) : [style, px virtuels sur 900, police]. Écran mural : rien sous 28 px (texte courant).
    private _type = [
["display", 132, "monoBold"], ["hero", 72, "monoBold"], ["num", 48, "monoBold"], ["title", 40, "sansBold"], ["heading", 32, "sansBold"],
["body", 28, "sans"], ["bodyBold", 28, "sansBold"], ["data", 28, "mono"], ["dataBold", 28, "monoBold"], ["caption", 28, "sans"],
["stripLabel", 40, "sansBold"], ["stripBig", 130, "monoBold"], ["stripMid", 110, "monoBold"], ["stripChrono", 70, "monoBold"], ["stripSub", 40, "sans"],
["plateName", 190, "sansBold"], ["plateSub", 56, "sans"]
];
    // ---- FIN
    // ---- TABLE _fonts (JSON) : libellés en Roboto Condensed du jeu (vrai gras à toutes les tailles, contrairement à
    // COMSPEC_BarlowBold qui prend le fichier normal sous 34 px), données (heures, grilles, nombres) en monospace du jeu.
    private _fonts = [["sans", "RobotoCondensed"], ["sansBold", "RobotoCondensedBold"], ["mono", "EtelkaMonospacePro"], ["monoBold", "EtelkaMonospaceProBold"]];
    // ---- FIN
    // ---- TABLE _space (JSON) : espacements (pixels virtuels), filet, hauteur de ligne de tableau.
    private _space = [["xs", 4], ["s", 8], ["m", 12], ["l", 16], ["xl", 24], ["xxl", 32], ["hair", 1], ["row", 40], ["minText", 28]];
    // ---- FIN
    // ---- TABLE _walls (JSON) : régions de chaque affichage [nom, x, y, l, h]. FRAME : en-tête, FLASH et pied communs.
    // Sous un FLASH, tout ce qui est sous l'en-tête descend de 52 px (bandeau) et les tableaux perdent les lignes en trop.
    private _walls = [
["FRAME", [["header", 0, 0, 1600, 72], ["rule", 0, 72, 1600, 2], ["title", 24, 12, 1000, 48], ["clock", 1024, 16, 552, 40], ["flash", 0, 74, 1600, 52], ["flashBar", 0, 74, 8, 52], ["flashTxt", 24, 80, 1552, 40], ["content", 24, 90, 1552, 746], ["footRule", 0, 848, 1600, 1], ["footL", 24, 854, 960, 40], ["footR", 1000, 854, 576, 40]]],
["SPLIT", [["map", 0, 74, 1040, 774], ["divider", 1040, 74, 1, 774], ["netHead", 1064, 90, 240, 40], ["netSub", 1304, 94, 272, 36], ["netRule", 1064, 134, 512, 1], ["tiles", 1064, 146, 512, 152], ["grpHead", 1064, 314, 240, 40], ["grpSub", 1304, 318, 272, 36], ["grpRule", 1064, 358, 512, 1], ["grpCols", 1064, 366, 512, 36], ["grpArea", 1064, 404, 512, 240], ["fluxHead", 1064, 660, 240, 40], ["fluxSub", 1304, 664, 272, 36], ["fluxRule", 1064, 704, 512, 1], ["fluxArea", 1064, 712, 512, 120]]],
["MAP", [["map", 0, 74, 1600, 774]]],
["SQUAD", [["map", 0, 74, 1040, 774], ["divider", 1040, 74, 1, 774], ["plateBar", 1064, 90, 6, 64], ["plate", 1084, 90, 492, 64], ["plateSub", 1064, 162, 512, 36], ["tiles", 1064, 210, 512, 152], ["memHead", 1064, 378, 240, 40], ["memSub", 1304, 382, 272, 36], ["memRule", 1064, 422, 512, 1], ["memArea", 1064, 430, 512, 280], ["memAreaCam", 1064, 430, 512, 104], ["crHead", 1064, 722, 240, 40], ["crSub", 1304, 726, 272, 36], ["crRule", 1064, 766, 512, 1], ["crText", 1064, 772, 512, 64], ["cam", 1064, 546, 512, 288]]],
["FEED", [["kpi", 24, 90, 1552, 72], ["head", 24, 178, 700, 40], ["sub", 724, 182, 852, 36], ["rule", 24, 222, 1552, 1], ["cols", 24, 230, 1552, 36], ["area", 24, 268, 1552, 560]]],
["NET", [["kpi", 24, 90, 1552, 72], ["head", 24, 178, 700, 40], ["sub", 724, 182, 852, 36], ["rule", 24, 222, 1552, 1], ["cols", 24, 230, 764, 36], ["cols2", 812, 230, 764, 36], ["area", 24, 268, 764, 560], ["area2", 812, 268, 764, 560]]],
["ORBAT", [["head", 24, 90, 700, 40], ["sub", 724, 94, 852, 36], ["rule", 24, 134, 1552, 1], ["col1", 24, 146, 496, 684], ["col2", 552, 146, 496, 684], ["col3", 1080, 146, 496, 684]]],
["CLOCK", [["zLbl", 24, 98, 880, 36], ["zDig", 24, 138, 880, 150], ["dtg", 24, 296, 880, 36], ["lRule", 24, 352, 880, 1], ["lLbl", 24, 368, 880, 36], ["lDig", 24, 408, 880, 84], ["lDate", 24, 500, 880, 36], ["nxHead", 24, 584, 440, 40], ["nxSub", 464, 588, 440, 36], ["nxRule", 24, 628, 880, 1], ["nxCode", 24, 642, 880, 48], ["nxEv", 24, 700, 880, 36], ["nxAt", 24, 744, 880, 36], ["divider", 928, 90, 1, 746], ["tHead", 952, 90, 300, 40], ["tSub", 1252, 94, 324, 36], ["tRule", 952, 134, 624, 1], ["tArea", 952, 146, 624, 684]]],
["LOG", [["head", 24, 90, 700, 40], ["sub", 724, 94, 852, 36], ["rule", 24, 134, 1552, 1], ["cols", 24, 142, 1552, 36], ["area", 24, 182, 1552, 640]]],
["EXCHECK", [["head", 24, 90, 700, 40], ["sub", 724, 94, 852, 36], ["rule", 24, 134, 1552, 1], ["prog", 24, 133, 1552, 3], ["cols", 24, 146, 1552, 36], ["area", 24, 186, 1552, 640]]],
["KILLS", [["kpi", 24, 90, 1552, 72], ["head", 24, 178, 700, 40], ["sub", 724, 182, 852, 36], ["rule", 24, 222, 1552, 1], ["groups", 24, 230, 1552, 36], ["cols", 24, 266, 1552, 36], ["area", 24, 306, 1552, 520]]],
["REQ", [["kpi", 24, 90, 1552, 72], ["head", 24, 178, 700, 40], ["sub", 724, 182, 852, 36], ["rule", 24, 222, 1552, 1], ["cols", 24, 230, 1552, 36], ["area", 24, 268, 1552, 560]]],
["MOSAIC", [["area", 24, 90, 1552, 746]]],
["STRIP", [["zLbl", 48, 40, 640, 48], ["zDig", 48, 100, 660, 160], ["zSub", 48, 284, 640, 48], ["div1", 720, 48, 2, 304], ["lLbl", 760, 40, 350, 48], ["lDig", 760, 112, 360, 136], ["lSub", 760, 284, 350, 48], ["div2", 1140, 48, 2, 304], ["cLbl", 1176, 40, 400, 48], ["cDig", 1176, 120, 400, 100], ["cSub", 1176, 284, 400, 48]]],
["PLATE", [["bar", 0, 0, 20, 400], ["name", 72, 40, 1480, 220], ["sub", 72, 280, 1480, 72]]]
];
    // ---- FIN
    // ---- TABLE _flashRule (JSON) : sous un FLASH, [affichage, régions qui restent en place (préfixes), régions qui raccourcissent].
    // Les autres descendent de 52 px. Mêmes règles dans la maquette (tools/toc_mockups.py).
    private _flashRule = [
["SPLIT", ["flux"], ["map", "divider", "grpArea"]], ["TOC", ["flux"], ["map", "divider", "grpArea"]], ["MAP", [], ["map"]],
["SQUAD", ["cr", "cam"], ["map", "divider", "memArea", "memAreaCam"]], ["FEED", [], ["area"]], ["NET", [], ["area", "area2"]],
["ORBAT", [], ["col1", "col2", "col3"]], ["CLOCK", [], ["divider", "tArea"]], ["LOG", [], ["area"]], ["EXCHECK", [], ["area"]],
["KILLS", [], ["area"]], ["REQ", [], ["area"]], ["MOSAIC", [], ["area"]]
];
    // ---- FIN
    // ---- TABLE _cols (JSON) : colonnes [clé, en-tête, x dans le tableau, largeur, L | R | C | P (pastille), style].
    private _cols = [
["GRP_S", [["name", "ÉLÉMENT", 0, 256, "L", "bodyBold"], ["n", "NŒUDS", 264, 96, "R", "data"], ["grid", "GRILLE", 372, 140, "R", "data"]]],
["FLUX_S", [["time", "", 0, 92, "L", "data"], ["kind", "", 100, 150, "L", "bodyBold"], ["who", "", 258, 254, "L", "body"]]],
["MEM", [["dot", "", 0, 16, "C", "body"], ["cs", "", 24, 196, "L", "bodyBold"], ["grid", "", 228, 132, "R", "data"], ["state", "", 372, 140, "P", "body"]]],
["FEED", [["time", "HEURE", 0, 100, "L", "data"], ["kind", "TYPE", 112, 190, "L", "bodyBold"], ["prio", "PRIORITÉ", 314, 150, "P", "body"], ["who", "ÉMETTEUR", 476, 250, "L", "body"], ["grid", "GRILLE", 738, 140, "R", "data"], ["text", "DÉTAIL", 900, 652, "L", "body"]]],
["NET", [["name", "ÉLÉMENT", 0, 316, "L", "bodyBold"], ["n", "NŒUDS", 324, 96, "R", "data"], ["state", "ÉTAT", 430, 160, "P", "body"], ["grid", "GRILLE", 602, 162, "R", "data"]]],
["LOG", [["time", "HEURE Z", 0, 120, "L", "data"], ["kind", "TYPE", 132, 170, "P", "body"], ["text", "ENTRÉE", 314, 966, "L", "body"], ["who", "AUTEUR", 1292, 260, "R", "body"]]],
["EXCHECK", [["n", "N°", 0, 56, "R", "data"], ["cw", "MOT DE CODE", 72, 230, "L", "bodyBold"], ["ev", "ÉVÉNEMENT", 314, 570, "L", "body"], ["el", "ÉLÉMENT", 896, 220, "L", "body"], ["at", "PRÉVU", 1128, 120, "R", "data"], ["done", "RÉEL", 1260, 120, "R", "data"], ["st", "ÉTAT", 1400, 152, "P", "body"]]],
["KILLS", [["el", "ÉLÉMENT", 0, 640, "L", "bodyBold"], ["wia", "BLESSÉS", 660, 200, "R", "dataBold"], ["kia", "TUÉS", 872, 200, "R", "dataBold"], ["ekia", "NEUTRALISÉS", 1104, 220, "R", "dataBold"], ["ewia", "BLESSÉS / CAPTURÉS", 1336, 216, "R", "dataBold"]]],
["KILLS_G", [["f", "AMIS", 660, 412, "R", "caption"], ["e", "ENNEMIS", 1104, 448, "R", "caption"]]],
["REQ", [["time", "HEURE", 0, 100, "L", "data"], ["kind", "TYPE", 112, 200, "L", "bodyBold"], ["by", "DEMANDEUR", 324, 230, "L", "body"], ["grid", "GRILLE", 566, 140, "R", "data"], ["text", "DÉTAIL", 724, 640, "L", "body"], ["st", "STATUT", 1376, 176, "P", "body"]]],
["TIMER", [["name", "", 0, 360, "L", "heading"], ["sub", "", 0, 360, "L", "caption"], ["val", "", 368, 256, "R", "num"]]]
];
    // ---- FIN
    private _hexTo = {
        params ["_h"];
        private _d = "0123456789abcdef";
        private _n = { params ["_i"]; ((_d find (_h select [_i, 1])) * 16 + (_d find (_h select [_i + 1, 1]))) / 255 };
        _h = toLower _h;
        [[1] call _n, [3] call _n, [5] call _n, 1]
    };
    private _c = createHashMap;
    private _hx = createHashMap;
    { _c set [_x select 0, [_x select 1] call _hexTo]; _hx set [_x select 0, _x select 1]; } forEach _tokens;
    private _ty = createHashMap;
    private _fm = createHashMapFromArray _fonts;
    { _ty set [_x select 0, [_x select 1, _fm getOrDefault [_x select 2, "RobotoCondensed"]]]; } forEach _type;
    private _lay = createHashMap;
    { private _r = createHashMap; { _r set [_x select 0, [_x select 1, _x select 2, _x select 3, _x select 4]]; } forEach (_x select 1); _lay set [_x select 0, _r]; } forEach _walls;
    _thm = createHashMapFromArray [["c", _c], ["hex", _hx], ["type", _ty], ["font", _fm], ["lay", _lay], ["cols", createHashMapFromArray _cols], ["sp", createHashMapFromArray _space],
        ["flash", createHashMapFromArray (_flashRule apply { [_x select 0, [_x select 1, _x select 2]] })]];
    missionNamespace setVariable ["COMSPEC_ATAK_TocTheme", _thm];
};
switch (_mode) do {
    case "get": { _thm };
    case "rgba": {
        _a params [["_k", "text"], ["_al", 1]];
        private _v = +((_thm get "c") getOrDefault [_k, [1, 0, 1, 1]]);
        _v set [3, _al];
        _v
    };
    case "hex": { (_thm get "hex") getOrDefault [_a, "#ff00ff"] };
    case "ink": {
        // color='@jeton' -> color='#rrggbb' (jeton inconnu : magenta, visible tout de suite).
        private _s = _a;
        private _hx = _thm get "hex";
        private _out = "";
        private _i = _s find "'@";
        while { _i >= 0 } do {
            _out = _out + (_s select [0, _i + 1]);
            _s = _s select [_i + 2];
            private _j = _s find "'";
            if (_j < 0) exitWith { _out = _out + "@"; };
            _out = _out + (_hx getOrDefault [_s select [0, _j], "#ff00ff"]);
            _s = _s select [_j];
            _i = _s find "'@";
        };
        _out + _s
    };
    case "lay": { (_thm get "lay") getOrDefault [_a, createHashMap] };
    case "cols": { (_thm get "cols") getOrDefault [_a, []] };
    case "region": {
        _a params ["_v", "_k", ["_fl", false]];
        private _r = +(((_thm get "lay") getOrDefault [_v, createHashMap]) getOrDefault [_k, [0, 0, 0, 0]]);
        if (!_fl) exitWith { _r };
        ((_thm get "flash") getOrDefault [_v, [[], []]]) params ["_fixed", "_shrink"];
        if ((_fixed findIf { (_k find _x) isEqualTo 0 }) >= 0) exitWith { _r };
        _r set [1, (_r select 1) + 52];
        if (_k in _shrink) then { _r set [3, (_r select 3) - 52]; };
        _r
    };
    case "style": {
        _a params ["_ctl", ["_st", "body"], ["_k", "text"], ["_hPx", 900]];
        ((_thm get "type") getOrDefault [_st, [28, "RobotoCondensed"]]) params ["_px", "_font"];
        _ctl ctrlSetFont _font;
        _ctl ctrlSetFontHeight (_px / _hPx);
        _ctl ctrlSetTextColor (["rgba", [_k]] call comspec_atak_native_fnc_tocTheme);
        _ctl setVariable ["COMSPEC_ATAK_Fpx", _px];
        _ctl setVariable ["COMSPEC_ATAK_Mono", (_font find "Monospace") >= 0];
        _ctl setVariable ["COMSPEC_ATAK_Bold", (_font find "Bold") >= 0];
    };
    default { _thm };
};
