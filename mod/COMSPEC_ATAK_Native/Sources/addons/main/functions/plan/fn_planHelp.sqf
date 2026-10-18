/*
    App Plan : aide (touche H ou ? AIDE) et tutoriel pas à pas (premier lancement, rejouable depuis l'aide, la barre
    du bas et la vue PLANS). Textes en français, traduits à l'affichage (fn_tr, i18n/en.txt).
    Params : ["draw"]   appelé par fn_planUi après les barres : ajoute ses contrôles à COMSPEC_ATAK_PlanChrome
             ["steps"]  -> [[titre, texte, zone]...] (zone : clé de COMSPEC_ATAK_PlanRects)
    Aide : panneau à droite de la carte, propre à l'outil ou à l'état en cours, puis les raccourcis.
    Tutoriel : la zone expliquée est encadrée à la couleur d'accent, la fiche de l'étape se place à côté.
*/
params [["_mode", "draw"]];
disableSerialization;
private _steps = [
    ["Bienvenue dans l'app Plan", "Préparez le briefing sur une carte fixe, nord en haut : zones, lignes, points et symboles APP-6A. Ce tour prend une minute ; vous pourrez le revoir depuis ? AIDE.", "map"],
    ["Choisir un outil", "☰ OUTILS ouvre la palette : ZONES (ENY, OBJ, INFIL, HLZ…), LIGNES (PL, axe, attaque, crayon) et POINTS (unités, CP, bâtiments nettoyés). Touchez un symbole pour le prendre, encore une fois pour le lâcher.", "pal"],
    ["Dessiner", "Zone : un clic par sommet, puis clic sur le premier, Entrée ou clic droit pour fermer ; 2 points font un rectangle. Ligne : clic point par point (double clic pour finir) ou CRAYON pour dessiner à main levée. Point : un clic.", "map"],
    ["Régler le tracé", "En bas : couleur, épaisseur, tirets, flèche, et le texte affiché (vide : le nom du symbole). TERMINER ferme le tracé, ✕ l'abandonne (Échap).", "bottom"],
    ["Sélection et clic droit", "Sans outil, un clic sur un élément ouvre sa fiche en bas : texte, description pour le briefing, couleur. Le clic droit ouvre un menu : modifier, dupliquer, copier vers une autre couche, supprimer. Sur la carte vide : poser une note, une unité ennemie, un CP.", "map"],
    ["Couches", "CAMP : le plan partagé de votre camp (qui le modifie dépend du serveur). BROUILLON : votre plan personnel, préparé à l'avance. Les nouveaux éléments vont dans la couche allumée.", "layers"],
    ["Travailler à plusieurs", "SALLE : créez une salle, un code s'affiche (ex. K7Q2M). Donnez-le : tous ceux qui le tapent dessinent sur le même plan, même hors de votre groupe. Publiez-le ensuite sur le plan du camp.", "room"],
    ["Annuler, rétablir", "↶ ANNULER (Ctrl+Z) défait votre dernière modification, même sur le plan partagé ; ↷ RÉTABLIR (Ctrl+Y) la refait. Pendant un tracé, ANNULER retire le dernier point.", "history"],
    ["Exporter et enregistrer", "EXPORTER : copier le code du plan (à coller dans un autre ATAK), copier un texte de briefing avec les grilles (Discord, ordre écrit), enregistrer, publier, importer. PLANS : vos plans enregistrés.", "export"],
    ["L'aide est toujours là", "Touche H : l'aide de l'outil en cours et tous les raccourcis. Bonne préparation de mission.", "help"]
];
if (_mode isEqualTo "steps") exitWith { _steps };
if (_mode isNotEqualTo "draw") exitWith { false };
private _ui = ["ui"] call comspec_atak_native_fnc_planAction;
private _tuto = _ui get "tuto";
if (!(_ui get "help") && {_tuto < 0}) exitWith { false };
private _rects = uiNamespace getVariable ["COMSPEC_ATAK_PlanRects", createHashMap];
(_rects getOrDefault ["map", []]) params [["_mx", 0], ["_my", 0], ["_mw", 0], ["_mh", 0]];
if (_mw <= 0) exitWith { false };
private _l = [] call comspec_atak_native_fnc_layoutGet;
private _font = _l get "font";
private _fs = _l get "fontSmall";
private _ratio = (uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW]);
(_l get "body") params ["_bx", "_by", "_bw", "_bh"];
([] call comspec_atak_native_fnc_accent) params ["_acc"];
private _list = uiNamespace getVariable ["COMSPEC_ATAK_PlanChrome", []];
private _mk = {
    params ["_class", "_p", ["_text", ""]];
    private _c = [_class, _p, _text, false] call comspec_atak_native_fnc_pageCtrl;
    _list pushBack _c;
    _c
};
private _btn = {
    params ["_r", "_t", "_code", ["_on", false]];
    private _b = ["COMSPEC_RscButton", _r, _t] call _mk;
    [_b, (_fs * 0.86) max COMSPEC_ATAK_FontMin] call comspec_atak_native_fnc_textFit;
    if (_on) then { _b ctrlSetBackgroundColor [0.36, 0.78, 0.42, 0.95]; _b ctrlSetTextColor [0.03, 0.05, 0.04, 1]; };
    _b ctrlAddEventHandler ["ButtonClick", _code];
    _b ctrlAddEventHandler ["ButtonClick", { [{ [] call comspec_atak_native_fnc_overlayFront; }] call CBA_fnc_execNextFrame; }];
    _b
};
private _card = {
    params ["_r"];
    private _br = ["COMSPEC_RscText", [(_r select 0) - pixelW, (_r select 1) - pixelH, (_r select 2) + pixelW * 2, (_r select 3) + pixelH * 2]] call _mk;
    _br ctrlSetBackgroundColor [_acc select 0, _acc select 1, _acc select 2, 0.9];
    private _bg = ["COMSPEC_RscText", _r] call _mk;
    _bg ctrlSetBackgroundColor [0.03, 0.045, 0.038, 0.97];
    [_br, _bg]
};
private _bh2 = (_font * 1.3) min (_bh * 0.075);
private _ip = pixelW * 12;

if (_tuto >= 0) then {
    if (_tuto >= (count _steps)) exitWith { ["tutoEnd"] call comspec_atak_native_fnc_planAction; };
    (_steps select _tuto) params ["_title", "_text", "_zone"];
    private _z = _rects getOrDefault [_zone, _rects get "map"];
    // Cadre autour de la zone expliquée (quatre filets épais), sans bloquer les clics dedans.
    _z params ["_zx", "_zy", "_zw", "_zh"];
    private _t = pixelH * 3;
    private _tw = pixelW * 3;
    {
        private _f = ["COMSPEC_RscText", _x] call _mk;
        _f ctrlSetBackgroundColor _acc;
    } forEach [[_zx, _zy, _zw, _t], [_zx, _zy + _zh - _t, _zw, _t], [_zx, _zy, _tw, _zh], [_zx + _zw - _tw, _zy, _tw, _zh]];
    // Fiche à côté de la zone, dans la carte, sans la couvrir si possible. Sa hauteur suit le texte de l'étape (mesuré) :
    // une hauteur fixe coupait les longues étapes (« … et POINTS » rogné), surtout en TRÈS GRAND ou en anglais.
    private _cw = ((_font * 24) / _ratio) min (_mw * 0.7);
    private _cx = if ((_zx + _zw + _cw + pixelW * 20) < (_mx + _mw)) then { _zx + _zw + pixelW * 16 } else { _mx + (_mw - _cw) / 2 };
    if (_zone isEqualTo "map") then { _cx = _mx + (_mw - _cw) / 2; };
    _cx = (_cx max (_mx + pixelW * 8)) min (_mx + _mw - _cw - pixelW * 8);
    private _cardCtrls = [[_cx, _my, _cw, _font * 6]] call _card;
    private _st = ["COMSPEC_RscStructuredText", [_cx + _ip, _my, _cw - _ip * 2, _font * 6]] call _mk;
    _st ctrlSetFontHeight _fs;
    _st ctrlSetStructuredText parseText ((format ["<t size='0.72' color='%1' font='COMSPEC_BarlowBold'>TUTORIEL · ÉTAPE %2 / %3</t><br/><t font='COMSPEC_BarlowBold' size='1.25' color='#eef3ef'>%4</t><br/><t size='0.92' color='#c9d6cf'>%5</t>",
        ([_acc] call { params ["_c"]; private _h = "0123456789abcdef"; "#" + (((_c select [0, 3]) apply { private _v = round ((_x max 0 min 1) * 255); (_h select [floor (_v / 16), 1]) + (_h select [_v mod 16, 1]) }) joinString "") }),
        _tuto + 1, count _steps, _title, _text]) call comspec_atak_native_fnc_tr);
    private _textH = (ctrlTextHeight _st) + pixelH * 4;
    private _ch = ((pixelH * 10) + _textH + pixelH * 14 + _bh2 + pixelH * 10) min (_mh - pixelH * 16);
    private _cy = if (_zone in ["bottom"]) then { _my + _mh - _ch - pixelH * 16 } else { _my + (_mh - _ch) / 3 };
    _cy = (_cy max (_my + pixelH * 8)) min (_my + _mh - _ch - pixelH * 8);
    private _r = [_cx, _cy, _cw, _ch];
    _cardCtrls params ["_br", "_bg"];
    _br ctrlSetPosition [_cx - pixelW, _cy - pixelH, _cw + pixelW * 2, _ch + pixelH * 2];
    _bg ctrlSetPosition _r;
    _st ctrlSetPosition [_cx + _ip, _cy + pixelH * 10, _cw - _ip * 2, _ch - _bh2 - pixelH * 24];
    { _x ctrlCommit 0; } forEach [_br, _bg, _st];
    (_rects getOrDefault ["block", []]) pushBack _r;
    private _by2 = _cy + _ch - _bh2 - pixelH * 10;
    private _w3 = (_cw - _ip * 2 - pixelW * 16) / 3;
    [[_cx + _ip, _by2, _w3, _bh2], "PASSER", { ["tutoEnd"] call comspec_atak_native_fnc_planAction; }] call _btn;
    private _pb = [[_cx + _ip + _w3 + pixelW * 8, _by2, _w3, _bh2], "◀ PRÉCÉDENT", { ["tutoPrev"] call comspec_atak_native_fnc_planAction; }] call _btn;
    _pb ctrlEnable (_tuto > 0);
    private _last = _tuto >= ((count _steps) - 1);
    [[_cx + _ip + (_w3 + pixelW * 8) * 2, _by2, _w3, _bh2], ["SUIVANT ▶", "TERMINER ✓"] select _last, [{ ["tutoNext"] call comspec_atak_native_fnc_planAction; }, { ["tutoEnd"] call comspec_atak_native_fnc_planAction; }] select _last, true] call _btn;
} else {
    // Aide propre à l'état : outil en cours, sélection, ou repos.
    private _cat = [] call comspec_atak_native_fnc_planCatalog;
    private _def = (_cat get "kinds") getOrDefault [_ui get "tool", []];
    private _ctxTitle = "";
    private _ctxText = "";
    if ((count _def) > 0) then {
        _ctxTitle = format ["Outil : %1", _def select 2];
        _ctxText = switch (_def select 3) do {
            case "AREA": { "Un clic par sommet. Fermez la zone par un clic sur le premier sommet, Entrée, TERMINER, clic droit ou double clic. Deux points seulement : rectangle. ANNULER (Ctrl+Z) retire le dernier sommet." };
            case "LINE": { "SEGMENTS : clic point par point, double clic, Entrée ou clic droit pour finir. CRAYON : maintenez le clic gauche et dessinez, le trait est lissé au relâchement. FLÈCHE pose une pointe au bout." };
            case "DIR": { "Premier clic : la position. Deuxième clic : la direction de tir. Clic droit : annuler." };
            default { "Un clic pose le symbole. Les bâtiments (CLEARED / UNCLEARED), CP, RP et TRP sont numérotés tout seuls. Clic droit : lâcher l'outil." };
        };
    } else {
        if ((_ui get "sel") isNotEqualTo "") then {
            _ctxTitle = "Élément sélectionné";
            _ctxText = "La fiche en bas modifie le texte, la description et la couleur. DUPLIQUER (Ctrl+D) en fait une copie, SUPPRIMER (Suppr) l'efface ; Ctrl+Z annule. Clic droit : copier vers une autre couche, copier la grille.";
        } else {
            _ctxTitle = "Carte de briefing";
            _ctxText = "☰ OUTILS pour dessiner. Un clic sur un élément l'ouvre ; clic droit pour le menu (ou, sur la carte vide, poser une note). La couche allumée en haut (CAMP, BROUILLON, SALLE) reçoit les nouveaux éléments. EXPORTER copie le plan ou un texte de briefing.";
        };
    };
    private _keys = [
        ["Clic", "poser, sélectionner"], ["Clic droit", "menu, terminer, lâcher l'outil"], ["Glisser droit", "déplacer la carte"], ["Molette", "zoom"],
        ["Entrée", "terminer le tracé"], ["Échap", "annuler, fermer"], ["Ctrl+Z / Ctrl+Y", "annuler / rétablir"], ["Suppr", "effacer la sélection"],
        ["Ctrl+D", "dupliquer"], ["H", "afficher / masquer l'aide"]
    ];
    private _kt = (_keys apply { format ["<t font='EtelkaMonospaceProBold' size='0.8' color='#e6efe9'>%1</t><t size='0.8' color='#8a9a93'>  %2</t>", _x select 0, _x select 1] }) joinString "<br/>";
    private _cw = ((_font * 17) / _ratio) min (_mw * 0.5);
    private _ch = _mh - pixelH * 24;
    private _cx = _mx + _mw - _cw - pixelW * 12;
    private _cy = _my + pixelH * 12;
    [[_cx, _cy, _cw, _ch]] call _card;
    (_rects getOrDefault ["block", []]) pushBack [_cx, _cy, _cw, _ch];
    private _st = ["COMSPEC_RscStructuredText", [_cx + _ip, _cy + pixelH * 10, _cw - _ip * 2, _ch - _bh2 - pixelH * 24]] call _mk;
    _st ctrlSetFontHeight _fs;
    _st ctrlSetStructuredText parseText ((format ["<t size='0.72' color='#8a9a93' font='COMSPEC_BarlowBold'>AIDE · TOUCHE H</t><br/><t font='COMSPEC_BarlowBold' size='1.1' color='#eef3ef'>%1</t><br/><t size='0.86' color='#c9d6cf'>%2</t><br/><br/><t size='0.72' color='#8a9a93' font='COMSPEC_BarlowBold'>RACCOURCIS</t><br/>%3", _ctxTitle, _ctxText, _kt]) call comspec_atak_native_fnc_tr);
    private _by2 = _cy + _ch - _bh2 - pixelH * 10;
    private _w2 = (_cw - _ip * 2 - pixelW * 8) / 2;
    [[_cx + _ip, _by2, _w2, _bh2], "TUTORIEL", { ["tuto", 0] call comspec_atak_native_fnc_planAction; }, true] call _btn;
    [[_cx + _ip + _w2 + pixelW * 8, _by2, _w2, _bh2], "FERMER", { ["help"] call comspec_atak_native_fnc_planAction; }] call _btn;
};
uiNamespace setVariable ["COMSPEC_ATAK_PlanChrome", _list];
true
