/*
    Fenêtre « Gérer les écrans » du TOC (demande de TTRD : « il faut vraiment améliorer l'UI du menu des écrans » ;
    conception et maquettes : docs/audit/2026-11-gestionnaire-ecrans-ui.md). Une seule fenêtre et un seul code pour
    Eden, le Zeus, le jeu et le PC du poste (ouverte par-dessus) ; elle remplace le tableau de fn_tocManager « open »,
    qui reste joignable (AVANCÉ, ANCIEN TABLEAU). Sans téléphone et sans Athena.
    Quatre zones : barre du haut (salle, scène, recherche, SALLES, JOURNAL, LIAISONS, FERMER), arbre salle > écran >
    dalle à gauche, schéma du mur au centre (une case par dalle : n° et source, état, vignette = texture déjà rendue de
    la dalle, luminosité, bouton d'alimentation ; dessous les autres écrans de la salle), inspecteur à droite (six
    onglets SOURCE, VUE, DISPOSITION, TEXTE, ALIMENTATION, AVANCÉ, portée « Appliquer à », aide du champ survolé avec
    un exemple), barre d'actions en bas (fil salle > écran > dalle, résultat, RÉINITIALISER, COPIER VERS…, ANNULER,
    APPLIQUER). La vue ROUTAGE (matrice source > sortie) est remise à plus tard : son segment montre « bientôt ».
    Tailles : pixels de conception sur 1080 de haut (_uh = safeZoneH / 1080, _uw = _uh x 3/4), donc les mêmes à toutes
    les tailles d'interface ; fenêtre de 1840 x 1024, 2300 de large au plus ; sous 1500 de large et dans Eden :
    variante compacte de 1192 x 780 (arbre et inspecteur, le schéma devient une rangée de cases sans image).
    Aucun message hors de la fenêtre : résultats sur la ligne d'état, questions dans la boîte de confirmation.
    Les réglages s'accumulent (« n changements non appliqués ») jusqu'à APPLIQUER ; un champ non touché n'est pas écrit.
    Clavier : Entrée = APPLIQUER, Échap = annuler (boîte, source prête, recherche, changements) puis fermer,
    Ctrl+1 à Ctrl+6 et Ctrl+Tab = onglets, Ctrl+F = recherche, Ctrl+A = toutes les dalles de l'écran, P = alimentation,
    F5 = relire. Coût : la fenêtre est refaite sur événement ; une boucle à 2 Hz, arrêtée à la fermeture, relit une
    signature des écrans (refonte seulement si elle change) et les vignettes (2 images par seconde au plus).
    Tables et règles sans effet : fn_tocManagerUiData. Lecture du jeu et exécution : fn_tocManagerUiApply.
    Modes :
      ["open", [objet à choisir]]     ouvre la fenêtre (parent : Eden 313, PC du poste, Zeus 312, sinon 46)
      ["unload", display]             fermeture (onUnload)
      ["refresh", "" | "soft"]        relit les écrans : arbre, schéma, inspecteur
      ["select", [dalle, Ctrl, Maj]]  choisit une dalle       ["tab", n]  onglet      ["view", n]  vue du centre
      ["act", clé]                    bouton (table _acts de fn_tocManagerUiData)
      ["chg", champ]                  champ touché : changement en attente
      ["apply"] / ["cancel"] / ["esc"] / ["key", …] / ["tick"] / ["say", [texte, jeton]] / ["help", [genre, clé]]
      ["ask", [genre, cible, texte]] / ["confirm", événement]   boîte de confirmation dans la fenêtre
*/
params [["_mode", "open"], ["_a", []]];
disableSerialization;
private _fn = comspec_atak_native_fnc_tocManagerUi;
private _dat = comspec_atak_native_fnc_tocManagerUiData;
private _run = comspec_atak_native_fnc_tocManagerUiApply;
private _mgr = comspec_atak_native_fnc_tocManager;
private _tr = { _this call comspec_atak_native_fnc_tr };
private _st = uiNamespace getVariable ["COMSPEC_ATAK_TocMgrUi", createHashMap];
private _c = _st getOrDefault ["c", createHashMap];
private _uw = _st getOrDefault ["uw", 0.001];
private _uh = _st getOrDefault ["uh", 0.001];
private _col = { params ["_tok", ["_al", 1]]; ["rgba", [_tok, _al]] call comspec_atak_native_fnc_tocTheme };
private _hex = { ["hex", _this] call comspec_atak_native_fnc_tocTheme };
// Texte saisi par un joueur dans un texte structuré : jamais lu comme une balise.
private _esc = { ((_this regexReplace ["&", "&amp;"]) regexReplace ["<", "&lt;"]) regexReplace [">", "&gt;"] };
private _rowOf = { params ["_rk"]; ((_st getOrDefault ["rows", []]) select { (_x select 0) isEqualTo _rk }) param [0, []] };
// Valeur d'un champ de l'inspecteur, et sa pose (fctl : clé -> [genre, libellé, contrôles, hauteur]).
private _fget = {
    params ["_fk"];
    ((_st getOrDefault ["fctl", createHashMap]) getOrDefault [_fk, ["", controlNull, [controlNull], 0]]) params ["_kind", "", "_ctls"];
    private _ctl = _ctls param [0, controlNull];
    switch (_kind) do {
        case "cmb": { _ctl lbData (lbCurSel _ctl) };
        case "tbx": { lbCurSel _ctl };
        case "chk": { cbChecked _ctl };
        case "sld": { sliderPosition _ctl };
        case "edt"; case "mle": { ctrlText _ctl };
        default { "" };
    }
};
private _fset = {
    params ["_fk", "_fv"];
    ((_st getOrDefault ["fctl", createHashMap]) getOrDefault [_fk, ["", controlNull, [controlNull], 0]]) params ["_kind", "", "_ctls"];
    private _ctl = _ctls param [0, controlNull];
    if (isNull _ctl) exitWith {};
    switch (_kind) do {
        case "cmb": {
            private _i = -1;
            for "_j" from 0 to ((lbSize _ctl) - 1) do { if ((_ctl lbData _j) isEqualTo _fv) exitWith { _i = _j; }; };
            _ctl lbSetCurSel _i;
        };
        case "tbx": { _ctl lbSetCurSel _fv; };
        case "chk": { _ctl cbSetChecked _fv; };
        case "sld": {
            _ctl sliderSetPosition _fv;
            (_ctls param [1, controlNull]) ctrlSetText str ([round _fv, ["p2w", _fv] call _dat] select (_fk isEqualTo "vWidth")); // i18n:brut (nombre)
        };
        case "edt"; case "mle": { _ctl ctrlSetText _fv; }; // i18n:brut (texte saisi)
    };
};
// Options d'une liste déroulante : [[libellé, donnée]...], libellés traduits sauf demande contraire.
private _fopts = {
    params ["_fk", "_list", ["_raw", false]];
    private _ctl = (((_st getOrDefault ["fctl", createHashMap]) getOrDefault [_fk, ["", controlNull, [controlNull], 0]]) select 2) param [0, controlNull];
    if (isNull _ctl) exitWith {};
    lbClear _ctl;
    { private _i = _ctl lbAdd ([(_x select 0) call _tr, _x select 0] select _raw); _ctl lbSetData [_i, _x select 1]; } forEach _list;
};
// Rectangle en pixels de conception : dans un groupe, ou dans la fenêtre (origine x0, y0).
private _place = {
    params ["_ctl", "_rect", ["_inGroup", true]];
    _rect params ["_rx", "_ry", "_rw", "_rh"];
    private _ox = [_st getOrDefault ["x0", 0], 0] select _inGroup;
    private _oy = [_st getOrDefault ["y0", 0], 0] select _inGroup;
    _ctl ctrlSetPosition [_ox + _rx * _uw, _oy + _ry * _uh, _rw * _uw, _rh * _uh];
    _ctl ctrlCommit 0;
};
// Dalles visées par APPLIQUER selon la portée choisie.
private _scoped = {
    private _rows = _st getOrDefault ["rows", []];
    private _cur = [_st getOrDefault ["cur", ""]] call _rowOf;
    if (_cur isEqualTo []) exitWith { [] };
    private _sel = _st getOrDefault ["sel", []];
    switch (lbCurSel (_c getOrDefault ["scope", controlNull])) do {
        case 1: { private _l = _rows select { (_x select 0) in _sel }; [_l, [_cur]] select (_l isEqualTo []) };
        case 2: { _rows select { (_x select 11) isEqualTo (_cur select 11) } };
        case 3: { _rows select { (_x select 4) isEqualTo (_cur select 4) } };
        default { [_cur] };
    }
};

switch (_mode) do {
    case "open": {
        if (!hasInterface) exitWith { false };
        // Lancé depuis un menu (Eden, ACE) ou un formulaire qui se referme : la fenêtre est créée juste après.
        if (!canSuspend) exitWith { [_mode, _a] spawn { uiSleep 0.08; _this call comspec_atak_native_fnc_tocManagerUi; }; true };
        private _parent = switch (true) do {
            case (is3DEN): { findDisplay 313 };
            case (!isNull (findDisplay 88560)): { findDisplay 88560 };
            case (!isNull (findDisplay 312)): { findDisplay 312 };
            default { findDisplay 46 };
        };
        if (isNull _parent) exitWith { false };
        private _old = _st getOrDefault ["d", displayNull];
        if (!isNull _old) then { _old closeDisplay 2; };
        private _d = _parent createDisplay "COMSPEC_RscTocManager";
        if (isNull _d) exitWith { false };
        // ---- Géométrie (pixels de conception sur 1080 de haut) ----
        _uh = safeZoneH / 1080;
        _uw = _uh * 0.75;
        private _availW = safeZoneW / _uw;
        private _compact = is3DEN || {_availW < 1500};
        private _winW = if (_compact) then { (_availW * 0.96) min 1192 } else { (_availW * 0.958) min 2300 };
        private _winH = [1024, 780] select _compact;
        private _x0 = safeZoneX + (safeZoneW - _winW * _uw) / 2;
        private _y0 = safeZoneY + (safeZoneH - _winH * _uh) / 2;
        private _topH = [62, 108] select _compact;
        private _botH = 62;
        private _treeW = [304, 300] select _compact;
        private _inspW = [540, _winW - 300] select _compact;
        private _midH = _winH - _topH - _botH;
        private _cenW = [_winW - _treeW - _inspW, _inspW] select _compact;
        private _cenX = [_treeW, _winW - _inspW] select _compact;
        private _stripH = [0, 60] select _compact;
        private _can = ["canEdit"] call _mgr;
        _c = createHashMap;
        _st = createHashMapFromArray [["d", _d], ["c", _c], ["uw", _uw], ["uh", _uh], ["x0", _x0], ["y0", _y0], ["compact", _compact], ["rows", []], ["sel", []], ["cur", ""],
            ["tab", 0], ["view", 0], ["hallSel", "*"], ["find", ""], ["changes", createHashMap], ["ready", []], ["confirm", ["", "", true]], ["fam", "MAP"], ["items", []],
            ["fill", false], ["mods", [false, false]], ["due", 0], ["sig", ""], ["said", ["", "accent"]], ["tq", []], ["hover", -1], ["fctl", createHashMap], ["can", _can],
            ["cenW", _cenW], ["midH", _midH], ["inspW", _inspW], ["stripH", _stripH], ["tickN", 0]];
        uiNamespace setVariable ["COMSPEC_ATAK_TocMgrUi", _st];
        // Une fenêtre du TOC est ouverte : un refus du serveur s'écrit dedans (fn_tocAccess), pas dans une autre fenêtre.
        _st set ["fbUiWas", missionNamespace getVariable ["COMSPEC_ATAK_TocFeedbackUi", false]];
        _st set ["fbSeen", (missionNamespace getVariable ["COMSPEC_ATAK_TocFeedback", [-1]]) param [0, -1]];
        missionNamespace setVariable ["COMSPEC_ATAK_TocFeedbackUi", true];
        private _ids = createHashMap;
        { _ids set [_x select 0, [_x select 1, _x select 2]]; } forEach (["ids"] call _dat);
        private _mk = {
            params ["_key", "_rect", ["_grp", controlNull], ["_cls", "COMSPEC_RscTocMgrText"]];
            (_ids getOrDefault [_key, [-1, _cls]]) params ["_idc", "_class"];
            private _ctl = if (isNull _grp) then { _d ctrlCreate [_class, _idc] } else { _d ctrlCreate [_class, _idc, _grp] };
            [_ctl, _rect, !isNull _grp] call _place;
            if (_key isNotEqualTo "") then { _c set [_key, _ctl]; };
            _ctl
        };
        private _acts = createHashMap;
        { _acts set [_x select 0, _x]; } forEach (["acts"] call _dat);
        // Libellé qui tient dans son bouton : police réduite pas à pas, jamais coupé.
        private _fit = {
            params ["_ctl", "_px"];
            private _wMax = ((ctrlPosition _ctl) select 2) * 0.92;
            private _f = _px * _uh;
            _ctl ctrlSetFontHeight _f;
            while { (ctrlTextWidth _ctl) > _wMax && {_f > 11 * _uh} } do { _f = _f - 0.5 * _uh; _ctl ctrlSetFontHeight _f; };
        };
        private _btn = {
            params ["_key", "_act", "_rect", ["_grp", controlNull], ["_cls", "COMSPEC_RscTocMgrButton"]];
            private _b = [_key, _rect, _grp, _cls] call _mk;
            (_acts getOrDefault [_act, [_act, _act, "", false]]) params ["", "_lbl", "", "_need"];
            _b ctrlSetText (_lbl call _tr);
            [_b, 16] call _fit;
            _b setVariable ["COMSPEC_ATAK_Act", _act];
            _b ctrlAddEventHandler ["ButtonClick", { ["act", (_this select 0) getVariable ["COMSPEC_ATAK_Act", ""]] call comspec_atak_native_fnc_tocManagerUi; }];
            _b ctrlAddEventHandler ["MouseEnter", { ["help", ["act", (_this select 0) getVariable ["COMSPEC_ATAK_Act", ""]]] call comspec_atak_native_fnc_tocManagerUi; }];
            _b ctrlAddEventHandler ["SetFocus", { ["help", ["act", (_this select 0) getVariable ["COMSPEC_ATAK_Act", ""]]] call comspec_atak_native_fnc_tocManagerUi; }];
            if (_need && {!_can}) then { _b ctrlEnable false; };
            _b
        };
        // ---- Fond : voile sur tout l'écran, cadre, zones ----
        private _veil = _d ctrlCreate ["COMSPEC_RscTocMgrText", 8700];
        _veil ctrlSetPosition [safeZoneXAbs, safeZoneY, safeZoneWAbs, safeZoneH];
        _veil ctrlCommit 0;
        _veil ctrlSetBackgroundColor (["bg0", 0.6] call _col);
        _c set ["veil", _veil];
        (["frame", [0, 0, _winW, _winH]] call _mk) ctrlSetBackgroundColor (["bg0", 0.98] call _col);
        (["", [0, 0, _winW, _topH]] call _mk) ctrlSetBackgroundColor (["bg1"] call _col);
        (["topRule", [0, _topH - 2, _winW, 2]] call _mk) ctrlSetBackgroundColor (["accent"] call _col);
        (["", [0, _topH, _treeW, _midH]] call _mk) ctrlSetBackgroundColor (["bg1"] call _col);
        (["", [_winW - _inspW, _topH, _inspW, _midH]] call _mk) ctrlSetBackgroundColor (["bg1"] call _col);
        (["", [0, _winH - _botH, _winW, _botH]] call _mk) ctrlSetBackgroundColor (["bg1"] call _col);
        (["", [0, _winH - _botH, _winW, 1]] call _mk) ctrlSetBackgroundColor (["line"] call _col);
        // ---- Barre du haut ----
        (["title", [16, 10, 226, 42]] call _mk) ctrlSetStructuredText parseText format ["<t size='1.55' font='RobotoCondensedBold' color='%1'>%2</t>", "text" call _hex, ("ÉCRANS DU TOC") call _tr];
        private _rightW = 4 * 116;
        private _bx = _winW - 16 - _rightW + 6;
        { [_x select 0, _x select 1, [_bx + _forEachIndex * 116, 13, 110, 36]] call _btn; } forEach [["bRooms", "rooms"], ["bLog", "log"], ["bLinks", "links"], ["bClose", "close"]];
        private _row2 = [13, 62] select _compact;
        private _sx = [540, 16] select _compact;
        private _hallW = [280, (_bx - 250 - 12 - 240) max 200] select _compact;
        private _cHall = ["hall", [250, 13, _hallW, 36]] call _mk;
        private _cScene = ["scene", [_sx, _row2, 250, 36]] call _mk;
        ["bLoad", "load", [_sx + 260, _row2, 100, 36]] call _btn;
        ["bSave", "save", [_sx + 366, _row2, 130, 36]] call _btn;
        private _findRect = if (_compact) then { [250 + _hallW + 12, 13, (_bx - 250 - _hallW - 24) max 120, 36] } else { [_sx + 506, 13, (_bx - _sx - 506 - 12) max 160, 36] };
        private _eFind = ["search", _findRect] call _mk;
        _eFind ctrlSetTooltip (("Chercher un écran, une source, une salle (Ctrl+F). Exemple : sat") call _tr);
        _eFind ctrlAddEventHandler ["KeyUp", { ["find", ctrlText (_this select 0)] call comspec_atak_native_fnc_tocManagerUi; }];
        _eFind ctrlAddEventHandler ["SetFocus", { ["help", ["text", "Recherche : filtre l'arbre des écrans et la liste des sources. Échap la vide. Exemple : « sat » ne garde que les écrans et les sources satellite."]] call comspec_atak_native_fnc_tocManagerUi; }];
        _cHall ctrlAddEventHandler ["LBSelChanged", { ["hallPick", _this select 1] call comspec_atak_native_fnc_tocManagerUi; }];
        _cHall ctrlAddEventHandler ["SetFocus", { ["help", ["text", "Salle montrée dans l'arbre, et visée par les scènes et le bouton SALLES. Exemple : SALLE TOC BRAVO."]] call comspec_atak_native_fnc_tocManagerUi; }];
        _cScene ctrlAddEventHandler ["SetFocus", { ["help", ["text", "Scène de la salle choisie, ou préréglage de votre profil, à remettre avec CHARGER. Exemple : VEILLE COURANTE."]] call comspec_atak_native_fnc_tocManagerUi; }];
        // ---- Arbre (gauche) ----
        ["treeHead", [16, _topH + 10, _treeW - 32, 30]] call _mk;
        private _tree = ["tree", [8, _topH + 44, _treeW - 16, _midH - 44 - 132]] call _mk;
        _tree ctrlAddEventHandler ["MouseButtonDown", { params ["", "", "", "", "_shift", "_ctrl"]; (uiNamespace getVariable ["COMSPEC_ATAK_TocMgrUi", createHashMap]) set ["mods", [_shift, _ctrl]]; }];
        _tree ctrlAddEventHandler ["TreeSelChanged", { ["treeSel", _this select 1] call comspec_atak_native_fnc_tocManagerUi; }];
        _tree ctrlAddEventHandler ["TreeDblClick", { ["tab", 0] call comspec_atak_native_fnc_tocManagerUi; }];
        private _ay = _topH + _midH - 126;
        private _cKind = ["kind", [12, _ay, _treeW - 24, 34]] call _mk;
        _cKind ctrlAddEventHandler ["SetFocus", { ["help", ["text", "Type d'écran posé par AJOUTER. Exemple : Mur d'images 3x2."]] call comspec_atak_native_fnc_tocManagerUi; }];
        { private _i = _cKind lbAdd (_x select 0); _cKind lbSetData [_i, _x select 1]; } forEach (["classes"] call comspec_atak_native_fnc_tocScreen);
        _cKind lbSetCurSel 0;
        private _tw3 = (_treeW - 24 - 12) / 3;
        { [_x select 0, _x select 1, [12 + _forEachIndex * (_tw3 + 6), _ay + 40, _tw3, 36]] call _btn; } forEach [["bAdd", "add"], ["bDup", "dup"], ["bDel", "del"]];
        private _tw2 = (_treeW - 24 - 6) / 2;
        { [_x select 0, _x select 1, [12 + _forEachIndex * (_tw2 + 6), _ay + 82, _tw2, 36]] call _btn; } forEach [["bGo", "go"], ["bBlink", "blink"]];
        // ---- Centre : titre, vues, un groupe par vue ----
        private _viewsRect = if (_compact) then { [_sx + 506, _row2, (_winW - _sx - 506 - 16) max 300, 36] } else { [_cenX + _cenW - 16 - 440, _topH + 16, 440, 36] };
        private _tbV = ["views", _viewsRect] call _mk;
        _tbV lbSetCurSel 0;
        _tbV ctrlAddEventHandler ["ToolBoxSelChanged", { ["view", _this select 1] call comspec_atak_native_fnc_tocManagerUi; }];
        _tbV ctrlAddEventHandler ["SetFocus", { ["help", ["text", "Vue du centre : ÉCRAN (schéma du mur), SALLES (liste des salles et liaisons), JOURNAL (qui a changé quoi). ROUTAGE arrive plus tard. Exemple : SALLES pour générer une salle."]] call comspec_atak_native_fnc_tocManagerUi; }];
        private _cTitle = ["cTitle", [_cenX + 16, _topH + 10, (_cenW - 32 - 456) max 200, 66]] call _mk;
        if (_compact) then { _cTitle ctrlShow false; };
        private _grpY = _topH + ([84, 0] select _compact);
        private _grpH = _midH - ([84, 0] select _compact);
        private _gScreen = ["vScreen", [_cenX, _grpY, _cenW, _grpH]] call _mk;
        private _gRooms = ["vRooms", [_cenX, _grpY, _cenW, _grpH]] call _mk;
        private _gLog = ["vLog", [_cenX, _grpY, _cenW, _grpH]] call _mk;
        private _gRoute = ["vRoute", [_cenX, _grpY, _cenW, _grpH]] call _mk;
        { _x ctrlShow false; } forEach [_gRooms, _gLog, _gRoute];
        if (_compact) then { _gScreen ctrlShow false; };
        // Schéma du mur : 24 cases au plus (mur 6 x 4). Compact : cases posées dans l'inspecteur, sans image.
        private _tileGrp = [_gScreen, controlNull] select _compact;
        private _tiles = [];
        for "_i" from 0 to 23 do {
            private _t = ["COMSPEC_RscTocMgrText", "COMSPEC_RscTocMgrText", "COMSPEC_RscTocMgrPic", "COMSPEC_RscTocMgrStx", "COMSPEC_RscTocMgrText", "COMSPEC_RscTocMgrTextRight", "COMSPEC_RscTocMgrText", "COMSPEC_RscTocMgrHit", "COMSPEC_RscTocMgrPower"] apply { ["", [0, 0, 0, 0], _tileGrp, _x] call _mk };
            { _x ctrlShow false; } forEach _t;
            private _hit = _t select 7;
            _hit setVariable ["COMSPEC_ATAK_Tile", _i];
            _hit ctrlAddEventHandler ["MouseButtonDown", { params ["_h", "_b", "", "", "_shift", "_ctrl"]; ["tile", [_h getVariable ["COMSPEC_ATAK_Tile", 0], _b, _shift, _ctrl]] call comspec_atak_native_fnc_tocManagerUi; }];
            _hit ctrlAddEventHandler ["MouseButtonDblClick", { ["tab", 0] call comspec_atak_native_fnc_tocManagerUi; }];
            _hit ctrlAddEventHandler ["MouseEnter", { (uiNamespace getVariable ["COMSPEC_ATAK_TocMgrUi", createHashMap]) set ["hover", (_this select 0) getVariable ["COMSPEC_ATAK_Tile", -1]]; }];
            _hit ctrlAddEventHandler ["MouseExit", { (uiNamespace getVariable ["COMSPEC_ATAK_TocMgrUi", createHashMap]) set ["hover", -1]; }];
            private _pw = _t select 8;
            _pw setVariable ["COMSPEC_ATAK_Tile", _i];
            _pw ctrlAddEventHandler ["ButtonClick", { ["tilePower", (_this select 0) getVariable ["COMSPEC_ATAK_Tile", 0]] call comspec_atak_native_fnc_tocManagerUi; }];
            _pw ctrlAddEventHandler ["MouseEnter", { ["help", ["text", "Alimentation de la dalle, tout de suite : allumée, puis en veille, puis éteinte. La source est gardée. Exemple : éteindre la dalle 4 du mur."]] call comspec_atak_native_fnc_tocManagerUi; }];
            _tiles pushBack _t;
        };
        _st set ["tiles", _tiles];
        ["cHint", [16, 0, _cenW - 32, 28], _gScreen] call _mk;
        ["cReady", [16, 0, _cenW - 32, 30], _gScreen] call _mk;
        private _oHead = ["", [16, 0, _cenW - 32, 30], _gScreen, "COMSPEC_RscTocMgrStx"] call _mk;
        _c set ["oHead", _oHead];
        private _others = [];
        for "_i" from 0 to 7 do {
            private _t = ["COMSPEC_RscTocMgrText", "COMSPEC_RscTocMgrText", "COMSPEC_RscTocMgrPic", "COMSPEC_RscTocMgrStx", "COMSPEC_RscTocMgrText", "COMSPEC_RscTocMgrTextRight", "COMSPEC_RscTocMgrHit"] apply { ["", [0, 0, 0, 0], _gScreen, _x] call _mk };
            { _x ctrlShow false; } forEach _t;
            (_t select 6) setVariable ["COMSPEC_ATAK_Tile", _i];
            (_t select 6) ctrlAddEventHandler ["ButtonClick", { ["other", (_this select 0) getVariable ["COMSPEC_ATAK_Tile", 0]] call comspec_atak_native_fnc_tocManagerUi; }];
            _others pushBack _t;
        };
        _st set ["others", _others];
        // Vue SALLES : liste des salles, actions, liaisons (liés et non liés).
        private _rw = _cenW - 32;
        private _tRooms = ["rooms", [16, 0, _rw, 34 * 6], _gRooms] call _mk;
        _tRooms lnbSetColumnsPos [0, 0.4, 0.55, 0.7, 0.85];
        _tRooms ctrlAddEventHandler ["LBSelChanged", { ["roomPick", _this select 1] call comspec_atak_native_fnc_tocManagerUi; }];
        private _ry = 34 * 6 + 12;
        private _lblName = ["", [16, _ry, 60, 34], _gRooms] call _mk;
        _lblName ctrlSetText (("Nom") call _tr);
        _lblName ctrlSetTextColor (["text2"] call _col);
        private _eRoom = ["", [80, _ry, (_rw * 0.4) min 300, 34], _gRooms, "COMSPEC_RscTocMgrEdit"] call _mk;
        _c set ["roomName", _eRoom];
        _eRoom ctrlAddEventHandler ["SetFocus", { ["help", ["text", "Nom de la salle à créer, ou nouveau nom de la salle choisie. Exemple : SALLE BRIEFING."]] call comspec_atak_native_fnc_tocManagerUi; }];
        private _rbx = 80 + ((_rw * 0.4) min 300) + 8;
        private _rbw = ((_rw + 16 - _rbx - 8) / 2) min 150;
        { ["", _x, [_rbx + _forEachIndex * (_rbw + 8), _ry, _rbw, 34], _gRooms] call _btn; } forEach ["roomNew", "roomName"];
        _ry = _ry + 42;
        private _cSize = ["", [16, _ry, (_rw * 0.4) min 300, 34], _gRooms, "COMSPEC_RscTocMgrCombo"] call _mk;
        _c set ["roomSize", _cSize];
        { private _i = _cSize lbAdd ((_x select 0) call _tr); _cSize lbSetData [_i, _x select 1]; } forEach [["Salle petite, 8 x 7 m", "S"], ["Salle moyenne, 11 x 8,5 m", "M"], ["Salle grande, 16 x 9,5 m", "L"]];
        _cSize lbSetCurSel 1;
        _cSize ctrlAddEventHandler ["SetFocus", { ["help", ["text", "Taille de la salle posée par GÉNÉRER. Exemple : Salle moyenne, 11 x 8,5 m."]] call comspec_atak_native_fnc_tocManagerUi; }];
        private _gx = 16 + ((_rw * 0.4) min 300) + 8;
        { ["", _x, [_gx + _forEachIndex * (_rbw + 8), _ry, _rbw, 34], _gRooms] call _btn; } forEach ["roomGen", "roomFeeds"];
        _ry = _ry + 42;
        private _r4 = (_rw - 24) / 4;
        { ["", _x, [16 + _forEachIndex * (_r4 + 8), _ry, _r4, 34], _gRooms] call _btn; } forEach ["allOn", "allOff", "roomDel", "resetAll"];
        _ry = _ry + 50;
        private _lHead = ["", [16, _ry, _rw, 30], _gRooms, "COMSPEC_RscTocMgrStx"] call _mk;
        _c set ["linksHead", _lHead];
        private _tLinks = ["links", [16, _ry + 34, _rw, (_grpH - _ry - 46) max 68], _gRooms] call _mk;
        _tLinks lnbSetColumnsPos [0, 0.4, 0.55, 0.8];
        // Vue JOURNAL.
        private _lgHead = ["", [16, 0, _rw - 160, 34], _gLog, "COMSPEC_RscTocMgrStx"] call _mk;
        _c set ["logHead", _lgHead];
        ["", "logRefresh", [16 + _rw - 150, 0, 150, 34], _gLog] call _btn;
        private _tLog = ["log", [16, 42, _rw, _grpH - 54], _gLog] call _mk;
        _tLog lnbSetColumnsPos [0, 0.1, 0.3, 0.45, 0.62];
        // Vue ROUTAGE : remise à plus tard, dite en clair.
        private _rt = ["", [16, 8, _rw, 120], _gRoute, "COMSPEC_RscTocMgrStx"] call _mk;
        _rt ctrlSetStructuredText parseText format ["<t size='1.3' font='RobotoCondensedBold' color='%1'>%2</t><br/><t color='%3'>%4</t>", "text" call _hex, ("Routage : bientôt") call _tr, "text2" call _hex,
            ("La matrice source > sortie de la salle arrive dans une prochaine version. En attendant : l'onglet SOURCE dit sur quels écrans chaque source est déjà montrée, et AVANCÉ diffuse une dalle sur toute la salle.") call _tr];
        // ---- Inspecteur (droite) ----
        private _ix = _winW - _inspW;
        private _pw0 = _inspW - 32;
        ["iTitle", [_ix + 16, _topH + 10, _pw0, 66]] call _mk;
        _st set ["stripRect", [_ix + 16, _topH + 80, _pw0, 48]];
        private _tabY = _topH + 84 + _stripH;
        private _tabs = ["tabs"] call _dat;
        private _tabW = [78, 56, 112, 66, 124, 72];
        private _tabSum = 508;
        private _tx = _ix + 16;
        {
            private _w = (_tabW select _forEachIndex) * _pw0 / _tabSum;
            private _b = [format ["tab%1", _forEachIndex], [_tx, _tabY, _w, 36]] call _mk;
            _b ctrlSetText ((_x select 1) call _tr);
            [_b, 16] call _fit;
            _b setVariable ["COMSPEC_ATAK_Tab", _forEachIndex];
            _b ctrlAddEventHandler ["ButtonClick", { ["tab", (_this select 0) getVariable ["COMSPEC_ATAK_Tab", 0]] call comspec_atak_native_fnc_tocManagerUi; }];
            _b ctrlAddEventHandler ["MouseEnter", { ["help", ["tab", (_this select 0) getVariable ["COMSPEC_ATAK_Tab", 0]]] call comspec_atak_native_fnc_tocManagerUi; }];
            _tx = _tx + _w;
        } forEach _tabs;
        private _tabLine = ["", [_ix + 16, _tabY + 37, _pw0, 1]] call _mk;
        _tabLine ctrlSetBackgroundColor (["line"] call _col);
        _c set ["tabLine", _tabLine];
        (["tabRule", [_ix + 16, _tabY + 36, 78, 2]] call _mk) ctrlSetBackgroundColor (["accent"] call _col);
        private _pageY = _tabY + 46;
        private _footH = 150;
        private _pageH = _topH + _midH - _footH - _pageY;
        _st set ["pageH", _pageH];
        _st set ["pageW", _pw0];
        private _pages = [];
        { _pages pushBack ([format ["page%1", _forEachIndex], [_ix + 16, _pageY, _pw0, _pageH]] call _mk); } forEach _tabs;
        // Onglet SOURCE : source actuelle, recherche et filtre des caméras, familles, sources de la famille.
        private _p0 = _pages select 0;
        ["srcNow", [0, 0, _pw0, 34], _p0] call _mk;
        private _eSrc = ["srcFind", [0, 40, _pw0 * 0.34, 32], _p0] call _mk;
        _eSrc ctrlSetTooltip (("Filtrer les sources de la famille. Exemple : TA1") call _tr);
        _eSrc ctrlAddEventHandler ["KeyUp", { ["srcFill"] call comspec_atak_native_fnc_tocManagerUi; }];
        _eSrc ctrlAddEventHandler ["SetFocus", { ["help", ["text", "Filtre de la liste des sources : tapez un début de nom. Exemple : « TA1 » ne garde que les caméras de TA1."]] call comspec_atak_native_fnc_tocManagerUi; }];
        private _tbC = ["camFilter", [_pw0 * 0.36, 40, _pw0 * 0.64, 32], _p0] call _mk;
        _tbC lbSetCurSel 0;
        _tbC ctrlAddEventHandler ["ToolBoxSelChanged", { ["srcFill"] call comspec_atak_native_fnc_tocManagerUi; }];
        _tbC ctrlAddEventHandler ["SetFocus", { ["help", ["text", "Caméras montrées dans la liste : toutes, celles en ligne, les drones, les caméras fixes ou les caméras portées. Exemple : EN LIGNE avant un assaut."]] call comspec_atak_native_fnc_tocManagerUi; }];
        private _lFam = ["fams", [0, 80, 168, _pageH - 80], _p0] call _mk;
        _lFam ctrlAddEventHandler ["LBSelChanged", { ["famPick", _this select 1] call comspec_atak_native_fnc_tocManagerUi; }];
        private _tSrc = ["srcs", [176, 80, _pw0 - 176, _pageH - 80], _p0] call _mk;
        _tSrc lnbSetColumnsPos [0, 0.07, 0.6];
        _tSrc ctrlAddEventHandler ["LBSelChanged", { ["srcPick", _this select 1] call comspec_atak_native_fnc_tocManagerUi; }];
        _tSrc ctrlAddEventHandler ["LBDblClick", { ["srcDbl", _this select 1] call comspec_atak_native_fnc_tocManagerUi; }];
        // Listes propres à DISPOSITION et TEXTE, en tête de leur page.
        private _tLay = ["layouts", [0, 0, _pw0 - 14, 34 * 8], _pages select 2] call _mk;
        _tLay lnbSetColumnsPos [0, 0.07, 0.48];
        _tLay ctrlAddEventHandler ["LBSelChanged", { ["layPick", _this select 1] call comspec_atak_native_fnc_tocManagerUi; }];
        _tLay ctrlAddEventHandler ["LBDblClick", { ["layDbl", _this select 1] call comspec_atak_native_fnc_tocManagerUi; }];
        private _tQ = ["queue", [0, 0, _pw0 - 14, 34 * 4], _pages select 3] call _mk;
        _tQ lnbSetColumnsPos [0, 0.07, 0.72, 0.86];
        _tQ ctrlAddEventHandler ["LBSelChanged", { ["msgPick", _this select 1] call comspec_atak_native_fnc_tocManagerUi; }];
        private _eMsg = ["msg", [0, 34 * 4 + 8, _pw0 - 14, 78], _pages select 3] call _mk;
        _eMsg ctrlAddEventHandler ["SetFocus", { ["help", ["text", "Texte du message, 300 caractères au plus ; les variables entre accolades sont remplacées sur l'écran. Exemple : RÉUNION {zulu} · SALLE BRAVO."]] call comspec_atak_native_fnc_tocManagerUi; }];
        _eMsg ctrlAddEventHandler ["KeyUp", { ["msgPrev"] call comspec_atak_native_fnc_tocManagerUi; }];
        // Champs décrits par la table _fields : libellé à gauche, contrôle à droite, rangés à l'affichage (« stack »).
        private _fctl = _st get "fctl";
        private _tabIdx = createHashMap;
        { _tabIdx set [_x select 0, _forEachIndex]; } forEach _tabs;
        private _fev = {
            params ["_ctl", "_fk", "_ev"];
            _ctl setVariable ["COMSPEC_ATAK_Field", _fk];
            if (_ev isNotEqualTo "") then { _ctl ctrlAddEventHandler [_ev, { ["chg", (_this select 0) getVariable ["COMSPEC_ATAK_Field", ""]] call comspec_atak_native_fnc_tocManagerUi; }]; };
            _ctl ctrlAddEventHandler ["SetFocus", { ["help", ["field", (_this select 0) getVariable ["COMSPEC_ATAK_Field", ""]]] call comspec_atak_native_fnc_tocManagerUi; }];
            _ctl ctrlAddEventHandler ["MouseEnter", { ["help", ["field", (_this select 0) getVariable ["COMSPEC_ATAK_Field", ""]]] call comspec_atak_native_fnc_tocManagerUi; }];
        };
        {
            _x params ["_fk", "_ftab", "_genre", "_lbl"];
            private _grp = _pages select (_tabIdx getOrDefault [_ftab, 1]);
            private _g = _genre splitString ":";
            private _kind = _g select 0;
            private _lx = [178, 0] select (_lbl isEqualTo "");
            private _cw = _pw0 - _lx - 14;
            private _l = controlNull;
            if (_lbl isNotEqualTo "") then {
                _l = ["", [0, 0, 172, 34], _grp] call _mk;
                _l ctrlSetText (_lbl call _tr);
                _l ctrlSetTextColor (["text2"] call _col);
                _l setVariable ["COMSPEC_ATAK_R", [0, 0, 172, 34]];
                _l setVariable ["COMSPEC_ATAK_Lbl", _lbl];
            };
            private _ctls = [];
            private _h = 34;
            private _one = {
                params ["_cls", "_rect", "_ev"];
                private _ctl = ["", _rect, _grp, _cls] call _mk;
                _ctl setVariable ["COMSPEC_ATAK_R", _rect];
                [_ctl, _fk, _ev] call _fev;
                _ctls pushBack _ctl;
                _ctl
            };
            switch (_kind) do {
                case "cmb": { ["COMSPEC_RscTocMgrCombo", [_lx, 0, _cw, 34], "LBSelChanged"] call _one; };
                case "edt": { ["COMSPEC_RscTocMgrEdit", [_lx, 0, _cw, 34], "KeyUp"] call _one; };
                case "mle": { _h = 78; ["COMSPEC_RscTocMgrEditMulti", [_lx, 0, _cw, 78], "KeyUp"] call _one; };
                case "chk": { ["COMSPEC_RscTocMgrCheck", [_lx, 5, 24 * 0.9, 24], "CheckedChanged"] call _one; };
                case "tbx": { [_g select 1, [_lx, 0, _cw, 34], "ToolBoxSelChanged"] call _one; };
                case "sld": {
                    private _s = ["COMSPEC_RscTocMgrSlider", [_lx, 7, _cw - 96, 20], "SliderPosChanged"] call _one;
                    _s sliderSetRange [parseNumber (_g select 1), parseNumber (_g select 2)];
                    _s sliderSetSpeed [parseNumber (_g select 3), (parseNumber (_g select 3)) * 5];
                    private _e = ["", [_lx + _cw - 88, 0, 88, 34], _grp, "COMSPEC_RscTocMgrEdit"] call _mk;
                    _e setVariable ["COMSPEC_ATAK_R", [_lx + _cw - 88, 0, 88, 34]];
                    _e setVariable ["COMSPEC_ATAK_Field", _fk];
                    _e ctrlAddEventHandler ["KeyUp", { ["chgEdit", (_this select 0) getVariable ["COMSPEC_ATAK_Field", ""]] call comspec_atak_native_fnc_tocManagerUi; }];
                    _e ctrlAddEventHandler ["SetFocus", { ["help", ["field", (_this select 0) getVariable ["COMSPEC_ATAK_Field", ""]]] call comspec_atak_native_fnc_tocManagerUi; }];
                    _ctls pushBack _e;
                };
                case "txt": { _h = 52; ["COMSPEC_RscTocMgrStx", [_lx, 0, _cw, 52], ""] call _one; };
                case "btn": {
                    private _names = (_g param [1, ""]) splitString ",";
                    private _per = [3, 2] select (_lx > 0 || {(count _names) isEqualTo 2});
                    if ((count _names) isEqualTo 4) then { _per = 4; };
                    private _bw = (_cw - (_per - 1) * 6) / _per;
                    {
                        private _r = [_lx + (_forEachIndex mod _per) * (_bw + 6), (floor (_forEachIndex / _per)) * 40, _bw, 34];
                        private _b = ["", _x, _r, _grp] call _btn;
                        _b setVariable ["COMSPEC_ATAK_R", _r];
                        _ctls pushBack _b;
                    } forEach _names;
                    _h = (ceil ((count _names) / _per)) * 40 - 6;
                };
            };
            _fctl set [_fk, [_kind, _l, _ctls, _h]];
        } forEach (["fields"] call _dat);
        // ---- Pied de l'inspecteur : portée et aide ----
        private _fy = _topH + _midH - _footH + 6;
        private _lScope = ["", [_ix + 16, _fy, 110, 34]] call _mk;
        _c set ["lScope", _lScope];
        _lScope ctrlSetText (("Appliquer à") call _tr);
        _lScope ctrlSetTextColor (["text2"] call _col);
        private _tbS = ["scope", [_ix + 130, _fy, _inspW - 146, 34]] call _mk;
        _tbS lbSetCurSel 0;
        _tbS ctrlAddEventHandler ["ToolBoxSelChanged", { ["status"] call comspec_atak_native_fnc_tocManagerUi; }];
        _tbS ctrlAddEventHandler ["SetFocus", { ["help", ["text", "Dalles qui reçoivent APPLIQUER : la dalle choisie, les dalles choisies avec Ctrl+clic, toutes les dalles de l'écran, ou tous les écrans de la salle. Exemple : La salle pour mettre 50 % de luminosité partout."]] call comspec_atak_native_fnc_tocManagerUi; }];
        private _helpBg = ["", [_ix + 16, _fy + 42, _pw0, 96]] call _mk;
        _helpBg ctrlSetBackgroundColor (["bg0"] call _col);
        _c set ["helpBg", _helpBg];
        ["help", [_ix + 24, _fy + 46, _pw0 - 16, 88]] call _mk;
        // ---- Barre d'actions ----
        private _by = _winH - _botH + 13;
        private _bws = [150, 150, 120, 170];
        private _bxr = _winW - 16;
        private _keys = [["bReset", "reset"], ["bCopy", "copy"], ["bCancel", "cancel"], ["bApply", "apply"]];
        for "_i" from 3 to 0 step -1 do {
            _bxr = _bxr - (_bws select _i);
            [(_keys select _i) select 0, (_keys select _i) select 1, [_bxr, _by, _bws select _i, 36]] call _btn;
            _bxr = _bxr - 8;
        };
        ["status", [16, _by, _bxr - 16, 36]] call _mk;
        // ---- Boîte de confirmation (au-dessus de tout, créée en dernier) ----
        private _cv = ["cfVeil", [0, 0, _winW, _winH]] call _mk;
        _cv ctrlSetBackgroundColor (["bg0", 0.72] call _col);
        _cv ctrlShow false;
        private _cb = ["cfBox", [(_winW - 620) / 2, (_winH - 250) / 2, 620, 250]] call _mk;
        (["", [0, 0, 620, 250], _cb] call _mk) ctrlSetBackgroundColor (["bg2"] call _col);
        (["", [0, 0, 620, 2], _cb] call _mk) ctrlSetBackgroundColor (["amber"] call _col);
        _c set ["cfText", ["", [20, 16, 580, 96], _cb, "COMSPEC_RscTocMgrStx"] call _mk];
        private _cfEdit = ["", [20, 118, 580, 34], _cb, "COMSPEC_RscTocMgrEdit"] call _mk;
        _c set ["cfEdit", _cfEdit];
        private _cfChk = ["", [20, 122, 24 * 0.9, 24], _cb, "COMSPEC_RscTocMgrCheck"] call _mk;
        _cfChk ctrlAddEventHandler ["CheckedChanged", { ["confirm", "toggle"] call comspec_atak_native_fnc_tocManagerUi; }];
        _c set ["cfChk", _cfChk];
        _c set ["cfChkLbl", ["", [52, 118, 548, 34], _cb] call _mk];
        private _cfNo = ["", [20, 196, 284, 38], _cb, "COMSPEC_RscTocMgrButtonPrimary"] call _mk;
        _cfNo ctrlSetText (("ANNULER") call _tr);
        _cfNo ctrlAddEventHandler ["ButtonClick", { ["confirm", "cancel"] call comspec_atak_native_fnc_tocManagerUi; }];
        _c set ["cfNo", _cfNo];
        private _cfOk = ["", [316, 196, 284, 38], _cb, "COMSPEC_RscTocMgrButton"] call _mk;
        _cfOk ctrlAddEventHandler ["ButtonClick", { ["confirm", "ok"] call comspec_atak_native_fnc_tocManagerUi; }];
        _c set ["cfOk", _cfOk];
        _cb ctrlShow false;
        // ---- Clavier, molette, boucle de veille ----
        _d displayAddEventHandler ["KeyDown", { ["key", _this] call comspec_atak_native_fnc_tocManagerUi }];
        _d displayAddEventHandler ["MouseZChanged", { ["wheel", _this select 1] call comspec_atak_native_fnc_tocManagerUi; }];
        // Dalle à choisir à l'ouverture (menu de l'écran, clic droit du Zeus, PC du poste).
        private _want = _a param [0, objNull];
        if (_want isEqualType objNull && {!isNull _want} && {!is3DEN}) then { _st set ["cur", netId _want]; _st set ["sel", [netId _want]]; };
        ["refresh"] call _fn;
        ["tab", 0] call _fn;
        ["help", ["text", "Choisissez une dalle dans l'arbre ou sur le schéma, réglez-la à droite, puis APPLIQUER. Exemple : dalle 2, onglet SOURCE, CARTES, Carte seule."]] call _fn;
        ctrlSetFocus _tree;
        [_d] spawn {
            params ["_d"];
            while { !isNull _d } do {
                uiSleep 0.5;
                if (!isNull _d) then { isNil { ["tick"] call comspec_atak_native_fnc_tocManagerUi; }; };
            };
        };
        true
    };
    case "unload": {
        if (_a isEqualType displayNull && {(_st getOrDefault ["d", displayNull]) isEqualTo _a}) then {
            missionNamespace setVariable ["COMSPEC_ATAK_TocFeedbackUi", _st getOrDefault ["fbUiWas", false]];
            uiNamespace setVariable ["COMSPEC_ATAK_TocMgrUi", createHashMap];
        };
        true
    };
    case "tick": {
        private _d = _st getOrDefault ["d", displayNull];
        if (isNull _d) exitWith { false };
        private _n = (_st getOrDefault ["tickN", 0]) + 1;
        _st set ["tickN", _n];
        private _due = _st getOrDefault ["due", 0];
        if (_due > 0 && {diag_tickTime >= _due}) exitWith { _st set ["due", 0]; ["refresh"] call _fn; true };
        // Une fois par seconde : signature des écrans ; la fenêtre n'est refaite que si elle a changé.
        if (_n mod 2 isEqualTo 0 && {!is3DEN}) then {
            private _sig = ["signature"] call _run;
            if (_sig isNotEqualTo (_st getOrDefault ["sig", ""])) then { ["refresh", "soft"] call _fn; };
            if ((_st getOrDefault ["view", 0]) isEqualTo 2) then { ["logFill"] call _fn; };
        };
        // Refus du serveur (droits de salle, s'ils sont chargés) : dit ici, sur la ligne d'état.
        private _fb = ["feedback"] call _run;
        if (_fb isNotEqualTo []) then { ["say", _fb] call _fn; };
        ["thumbs"] call _fn;
        true
    };
    case "say": {
        _a params [["_txt", ""], ["_tok", "accent"]];
        _st set ["said", [_txt, _tok]];
        ["status"] call _fn;
        true
    };
    case "status": {
        // Fil « salle > écran > dalle », puis changements en attente (ambre) ou dernier résultat.
        private _ctl = _c getOrDefault ["status", controlNull];
        if (isNull _ctl) exitWith { false };
        private _cur = [_st getOrDefault ["cur", ""]] call _rowOf;
        private _crumb = "";
        if (_cur isNotEqualTo []) then {
            private _host = ((_st get "rows") select { (_x select 1) isEqualTo (_cur select 11) }) param [0, _cur];
            private _nSib = { (_x select 11) isEqualTo (_cur select 11) } count (_st get "rows");
            private _parts = [[(_cur select 4), ("SANS SALLE") call _tr] select ((_cur select 4) isEqualTo ""), _host select 2];
            if (_nSib > 1) then { _parts pushBack format [("Dalle %1") call _tr, (_cur select 12) + 1]; };
            _crumb = (_parts apply { _x call _esc }) joinString format [" <t color='%1'>›</t> ", "muted" call _hex];
        };
        private _nCh = count (_st get "changes");
        private _ready = _st getOrDefault ["ready", []];
        (_st getOrDefault ["said", ["", "accent"]]) params ["_txt", "_tok"];
        private _msg = switch (true) do {
            case (_ready isNotEqualTo []): { format ["<t color='%1'>● %2</t>", "accent" call _hex, (([("Réglage prêt : cliquez les dalles qui doivent le recevoir, Échap pour finir") call _tr, ("Source prête : cliquez la dalle qui doit la montrer, Échap pour abandonner") call _tr] select ((_ready select 0) isEqualTo "src"))) call _esc] };
            case (_nCh > 0): {
                private _nT = count (call _scoped);
                format ["<t color='%1'>● %2</t>", "amber" call _hex, format [(["%1 changement non appliqué · %2", "%1 changements non appliqués · %2"] select (_nCh > 1)) call _tr, _nCh, format [(["pour %1 dalle", "pour %1 dalles"] select (_nT > 1)) call _tr, _nT]]]
            };
            case (_txt isNotEqualTo ""): { format ["<t color='%1'>● %2</t>", _tok call _hex, _txt call _esc] };
            default { "" };
        };
        _ctl ctrlSetStructuredText parseText format ["<t size='1.05' color='%1'>%2</t>%3%4", "text" call _hex, _crumb, ["", "     "] select (_crumb isNotEqualTo "" && {_msg isNotEqualTo ""}), _msg];
        (_c getOrDefault ["bApply", controlNull]) ctrlEnable (_nCh > 0);
        (_c getOrDefault ["bCancel", controlNull]) ctrlEnable (_nCh > 0 || {_ready isNotEqualTo []});
        true
    };
    case "help": {
        // Cadre d'aide : ce que c'est, puis l'exemple sur sa ligne. [genre "field" | "act" | "tab" | "fam" | "text", clé].
        private _ctl = _c getOrDefault ["help", controlNull];
        if (isNull _ctl) exitWith { false };
        _a params [["_kind", "text"], ["_key", ""]];
        private _head = "";
        private _txt = switch (_kind) do {
            case "field": { private _f = ["field", _key] call _dat; _head = _f param [3, ""]; _f param [4, ""] };
            case "act": { private _r = ((["acts"] call _dat) select { (_x select 0) isEqualTo _key }) param [0, []]; _head = _r param [1, ""]; _r param [2, ""] };
            case "tab": { private _r = (["tabs"] call _dat) param [_key, []]; _head = _r param [1, ""]; _r param [4, ""] };
            case "fam": { private _r = ((["families"] call _dat) select { (_x select 1) isEqualTo _key }) param [0, []]; _head = _r param [0, ""]; _r param [2, ""] };
            default { _key };
        };
        if (_txt isEqualTo "") exitWith { false };
        _txt = _txt call _tr;
        // L'exemple passe sur sa ligne (le texte est déjà traduit : repère français ou anglais).
        private _i = _txt find "Exemple :";
        if (_i < 0) then { _i = _txt find "Example:"; };
        private _body = [_txt, _txt select [0, _i]] select (_i > 0);
        private _ex = ["", _txt select [_i]] select (_i > 0);
        _ctl ctrlSetStructuredText parseText format ["<t size='0.9'>%1<t color='%2'>%3</t>%4</t>",
            ["", format ["<t font='RobotoCondensedBold' color='%1'>%2</t> · ", "accent" call _hex, (_head call _tr) call _esc]] select (_head isNotEqualTo ""),
            "text" call _hex, _body call _esc, ["", format ["<br/><t color='%1'>%2</t>", "text2" call _hex, _ex call _esc]] select (_ex isNotEqualTo "")];
        true
    };
    case "find": {
        if (_a isEqualTo (_st getOrDefault ["find", ""])) exitWith { false };
        _st set ["find", _a];
        ["tree"] call _fn;
        ["srcFill"] call _fn;
        true
    };
    case "hallPick": {
        if (_st getOrDefault ["fill", false]) exitWith { false };
        private _ctl = _c get "hall";
        _st set ["hallSel", _ctl lbData _a];
        ["tree"] call _fn;
        ["scenes"] call _fn;
        true
    };
    case "refresh": {
        private _d = _st getOrDefault ["d", displayNull];
        if (isNull _d) exitWith { false };
        private _rows = ["rows"] call _mgr;
        // Eden : un écran sans nom porte ce qu'il montre, jamais le nom de son modèle.
        if (is3DEN) then { { if ((_x select 2) isEqualTo (_x select 3) && {(_x select 7) isNotEqualTo ""}) then { _x set [2, toUpper (_x select 7)]; }; } forEach _rows; };
        _st set ["rows", _rows];
        if (!is3DEN) then { _st set ["sig", ["signature"] call _run]; };
        private _keys = _rows apply { _x select 0 };
        _st set ["sel", (_st get "sel") select { _x in _keys }];
        if !((_st get "cur") in _keys) then { _st set ["cur", _keys param [0, ""]]; };
        if ((_st get "sel") isEqualTo [] && {(_st get "cur") isNotEqualTo ""}) then { _st set ["sel", [_st get "cur"]]; };
        // Liste des salles (refaite seulement si elle a changé : une liste ouverte n'est pas refermée).
        private _names = [];
        { if ((_x select 4) isNotEqualTo "") then { _names pushBackUnique (_x select 4); }; } forEach _rows;
        { if (_x isEqualType [] && {(count _x) >= 2}) then { _names pushBackUnique (_x select 1); }; } forEach (missionNamespace getVariable ["COMSPEC_ATAK_TocHalls", []]);
        _names sort true;
        private _opts = [[format [("Toutes les salles · %1") call _tr, format [(["%1 dalle", "%1 dalles"] select ((count _rows) > 1)) call _tr, count _rows]], "*"]];
        { private _hn = _x; _opts pushBack [format ["%1 · %2", _hn, { (_x select 4) isEqualTo _hn } count _rows], _hn]; } forEach _names;
        if ((_rows findIf { (_x select 4) isEqualTo "" }) >= 0) then { _opts pushBack [("Sans salle") call _tr, ""]; };
        if ((str _opts) isNotEqualTo (_st getOrDefault ["hallSig", ""])) then {
            _st set ["hallSig", str _opts];
            _st set ["fill", true];
            private _ctl = _c get "hall";
            lbClear _ctl;
            { private _i = _ctl lbAdd (_x select 0); _ctl lbSetData [_i, _x select 1]; } forEach _opts;
            private _i = _opts findIf { (_x select 1) isEqualTo (_st get "hallSel") };
            if (_i < 0) then { _i = 0; _st set ["hallSel", "*"]; };
            _ctl lbSetCurSel _i;
            _st set ["fill", false];
            ["scenes"] call _fn;
        };
        ["tree"] call _fn;
        private _curSig = str ([_st get "cur"] call _rowOf);
        ["wall"] call _fn;
        if (_a isNotEqualTo "soft" || {_curSig isNotEqualTo (_st getOrDefault ["curSig", ""])}) then { ["fill"] call _fn; };
        _st set ["curSig", _curSig];
        if ((_st get "view") isEqualTo 1) then { ["roomsFill"] call _fn; };
        ["status"] call _fn;
        true
    };
    case "scenes": {
        // Scènes de la salle choisie (tenues par le serveur), puis préréglages du profil du joueur (par carte).
        private _ctl = _c getOrDefault ["scene", controlNull];
        if (isNull _ctl) exitWith { false };
        lbClear _ctl;
        private _hall = ["hallId", _st get "hallSel"] call _run;
        { private _i = _ctl lbAdd format [("Scène : %1") call _tr, _x]; _ctl lbSetData [_i, "S|" + _x]; } forEach (["sceneNames", _hall] call _run);
        { private _i = _ctl lbAdd format [("Préréglage : %1") call _tr, _x]; _ctl lbSetData [_i, "P|" + _x]; } forEach (["presetNames"] call _run);
        if ((lbSize _ctl) isEqualTo 0) then { private _i = _ctl lbAdd (("Aucune scène enregistrée") call _tr); _ctl lbSetData [_i, ""]; };
        _ctl lbSetCurSel 0;
        true
    };
    case "tree": {
        // Arbre salle > écran > dalle, filtré par la salle choisie et la recherche ; pastille d'état, témoin d'alimentation.
        private _tree = _c getOrDefault ["tree", controlNull];
        if (isNull _tree) exitWith { false };
        private _rows = _st get "rows";
        private _find = toLower (trim (_st get "find"));
        private _hallSel = _st get "hallSel";
        private _shown = _rows select {
            (_hallSel isEqualTo "*" || {(_x select 4) isEqualTo _hallSel})
            && {_find isEqualTo "" || {((toLower format ["%1 %2 %3 %4", _x select 2, _x select 7, _x select 4, _x select 3]) find _find) >= 0}}
        };
        private _dot = "\a3\ui_f\data\map\markers\military\dot_ca.paa";
        private _pwr = "\a3\ui_f\data\igui\cfg\actions\engine_on_ca.paa";
        _st set ["fill", true];
        tvClear _tree;
        private _halls = [];
        { _halls pushBackUnique (_x select 4); } forEach _shown;
        private _cur = _st get "cur";
        private _curPath = [];
        {
            private _hn = _x;
            private _hRows = _shown select { (_x select 4) isEqualTo _hn };
            private _hosts = [];
            { _hosts pushBackUnique (_x select 11); } forEach _hRows;
            private _hi = _tree tvAdd [[], format ["%1  (%2)", [_hn, ("SANS SALLE") call _tr] select (_hn isEqualTo ""), count _hosts]];
            _tree tvSetData [[_hi], "H|" + _hn];
            {
                private _host = _x;
                private _pRows = _hRows select { (_x select 11) isEqualTo _host };
                private _head = (_pRows select { (_x select 1) isEqualTo _host }) param [0, _pRows select 0];
                private _multi = (count _pRows) > 1;
                private _si = _tree tvAdd [[_hi], [format ["%1 · %2", _head select 2, _head select 7], format ["%1  (%2)", _head select 2, count _pRows]] select _multi];
                private _on = (_pRows findIf { (_x select 6) isNotEqualTo "OFF" }) >= 0;
                _tree tvSetData [[_hi, _si], "S|" + (_head select 0)];
                _tree tvSetPicture [[_hi, _si], _dot];
                _tree tvSetPictureColor [[_hi, _si], [_head select 10] call _col];
                _tree tvSetPictureRight [[_hi, _si], _pwr];
                _tree tvSetPictureRightColor [[_hi, _si], [["muted", "accent"] select _on] call _col];
                _tree tvSetTooltip [[_hi, _si], format ["%1 · %2 · %3 m", _head select 3, _head select 9, _head select 5]];
                if ((_head select 0) isEqualTo _cur) then { _curPath = [_hi, _si]; };
                if (_multi) then {
                    {
                        private _pi = _tree tvAdd [[_hi, _si], format ["%1 · %2", (_x select 12) + 1, _x select 7]];
                        _tree tvSetData [[_hi, _si, _pi], "P|" + (_x select 0)];
                        _tree tvSetPicture [[_hi, _si, _pi], _dot];
                        _tree tvSetPictureColor [[_hi, _si, _pi], [_x select 10] call _col];
                        _tree tvSetPictureRight [[_hi, _si, _pi], _pwr];
                        _tree tvSetPictureRightColor [[_hi, _si, _pi], [["accent", "muted"] select ((_x select 6) isEqualTo "OFF")] call _col];
                        _tree tvSetTooltip [[_hi, _si, _pi], format ["%1 · %2", _x select 2, _x select 9]];
                        if ((_x select 0) isEqualTo _cur && {(_x select 1) isNotEqualTo _host}) then { _curPath = [_hi, _si, _pi]; };
                    } forEach _pRows;
                    if ((_pRows findIf { (_x select 0) isEqualTo _cur }) >= 0) then { _tree tvExpand [_hi, _si]; };
                };
            } forEach _hosts;
            _tree tvExpand [_hi];
        } forEach _halls;
        if (_curPath isNotEqualTo []) then { _tree tvSetCurSel _curPath; };
        _st set ["fill", false];
        private _live = { (_x select 10) isEqualTo "accent" } count _rows;
        private _headTxt = if (_rows isEqualTo []) then { ("Aucun écran. Posez-en un avec AJOUTER, ou générez une salle (SALLES).") call _tr } else {
            format [("%1 · %2 en ligne") call _tr, format [(["%1 dalle", "%1 dalles"] select ((count _rows) > 1)) call _tr, count _rows], _live]
        };
        (_c get "treeHead") ctrlSetStructuredText parseText format ["<t size='1.15' font='RobotoCondensedBold' color='%1'>%2</t>  <t size='0.85' color='%3'>%4</t>", "text" call _hex, ("Écrans") call _tr, "muted" call _hex, _headTxt call _esc];
        true
    };
    case "treeSel": {
        if (_st getOrDefault ["fill", false]) exitWith { false };
        private _data = (_c get "tree") tvData _a;
        if ((_data select [0, 2]) isEqualTo "H|") exitWith { false };
        (_st getOrDefault ["mods", [false, false]]) params ["_shift", "_ctrl"];
        _st set ["mods", [false, false]];
        ["select", [_data select [2], _ctrl, _shift, true]] call _fn;
        true
    };
    case "select": {
        _a params [["_key", ""], ["_ctrl", false], ["_shift", false], ["_fromTree", false]];
        private _row = [_key] call _rowOf;
        if (_row isEqualTo []) exitWith { false };
        // Source prête ou réglage à copier : le clic pose, la sélection ne change pas.
        private _ready = _st getOrDefault ["ready", []];
        if (_ready isNotEqualTo []) exitWith {
            private _res = ["place", [_ready, _row]] call _run;
            if ((_ready select 0) isEqualTo "src") then { _st set ["ready", []]; };
            ["say", _res] call _fn;
            _st set ["due", diag_tickTime + 1.2];
            true
        };
        private _sel = +(_st get "sel");
        private _prev = [_st get "cur"] call _rowOf;
        switch (true) do {
            case (_ctrl): { if (_key in _sel && {(count _sel) > 1}) then { _sel = _sel - [_key]; } else { _sel pushBackUnique _key; }; };
            case (_shift && {_prev isNotEqualTo []} && {(_prev select 11) isEqualTo (_row select 11)}): {
                private _lo = (_prev select 12) min (_row select 12);
                private _hi = (_prev select 12) max (_row select 12);
                _sel = ((_st get "rows") select { (_x select 11) isEqualTo (_row select 11) && {(_x select 12) >= _lo} && {(_x select 12) <= _hi} }) apply { _x select 0 };
            };
            default { _sel = [_key]; };
        };
        // Les changements en attente appartenaient à l'ancienne dalle : abandonnés, et dits.
        private _lost = count (_st get "changes");
        if (_lost > 0 && {_key isNotEqualTo (_st get "cur")}) then { _st set ["changes", createHashMap]; _st set ["said", [format [(["%1 changement abandonné : la dalle choisie a changé", "%1 changements abandonnés : la dalle choisie a changé"] select (_lost > 1)) call _tr, _lost], "amber"]]; };
        _st set ["sel", _sel];
        _st set ["cur", [_key, _sel select 0] select !(_key in _sel)];
        (_c get "scope") lbSetCurSel (parseNumber ((count _sel) > 1));
        if (!_fromTree) then { ["tree"] call _fn; };
        ["wall"] call _fn;
        ["fill"] call _fn;
        _st set ["curSig", str ([_st get "cur"] call _rowOf)];
        ["status"] call _fn;
        true
    };
    case "tile": {
        _a params ["_i", ["_b", 0], ["_shift", false], ["_ctrl", false]];
        if (_b isNotEqualTo 0) exitWith { false };
        private _key = (_st getOrDefault ["tileKeys", []]) param [_i, ""];
        if (_key isEqualTo "") exitWith { false };
        ["select", [_key, _ctrl, _shift]] call _fn;
        true
    };
    case "tilePower": {
        // Bouton de la dalle : allumée -> veille -> éteinte -> allumée, tout de suite.
        private _row = [(_st getOrDefault ["tileKeys", []]) param [_a, ""]] call _rowOf;
        if (_row isEqualTo []) exitWith { false };
        ["say", ["powerCycle", [_row]] call _run] call _fn;
        _st set ["due", diag_tickTime + 1];
        true
    };
    case "other": {
        private _key = (_st getOrDefault ["otherKeys", []]) param [_a, ""];
        if (_key isNotEqualTo "") then { ["select", [_key]] call _fn; };
        true
    };
    case "wheel": {
        // Molette sur la vignette d'une carte ou d'un satellite : largeur montrée (changement en attente).
        private _row = [(_st getOrDefault ["tileKeys", []]) param [_st getOrDefault ["hover", -1], ""]] call _rowOf;
        if (_row isEqualTo [] || {is3DEN} || {(_row select 0) isNotEqualTo (_st get "cur")}) exitWith { false };
        if !((["kindOf", _row select 6] call _dat) in ["map", "sat"]) exitWith { false };
        private _ch = _st get "changes";
        private _w = _ch getOrDefault ["width", (["values", _row] call _run) getOrDefault ["#width", 3000]];
        _w = ((_w * ([1.25, 0.8] select (_a > 0))) max 60) min 20000;
        _ch set ["width", round _w];
        _st set ["fill", true];
        ["vWidth", ["w2p", _w] call _dat] call _fset;
        _st set ["fill", false];
        ["status"] call _fn;
        true
    };
    case "wall": {
        // Centre : titre de l'écran, schéma de ses dalles, autres écrans de la salle.
        private _rows = _st get "rows";
        private _cur = [_st get "cur"] call _rowOf;
        private _tiles = _st getOrDefault ["tiles", []];
        private _others = _st getOrDefault ["others", []];
        private _compact = _st get "compact";
        private _hide = { { { _x ctrlShow false; } forEach _x; } forEach _this; };
        if (_cur isEqualTo []) exitWith {
            _tiles call _hide;
            _others call _hide;
            _st set ["tileKeys", []];
            _st set ["otherKeys", []];
            (_c get "oHead") ctrlShow false;
            (_c get "cReady") ctrlShow false;
            (_c get "cTitle") ctrlSetStructuredText parseText format ["<t size='1.5' font='RobotoCondensedBold' color='%1'>%2</t><br/><t color='%3'>%4</t>", "text" call _hex, ("Aucun écran") call _tr, "text2" call _hex,
                ([("Aucun écran dans cette salle. Posez-en un avec AJOUTER, ou générez une salle (SALLES).") call _tr, ("Aucun écran TOC dans la mission. Posez-en un avec AJOUTER.") call _tr] select is3DEN)];
            (_c get "cHint") ctrlSetStructuredText parseText "";
            false
        };
        private _host = _cur select 11;
        private _sib = _rows select { (_x select 11) isEqualTo _host };
        _sib = [_sib, [], { _x select 12 }, "ASCEND"] call BIS_fnc_sortBy;
        private _head = (_sib select { (_x select 1) isEqualTo _host }) param [0, _sib select 0];
        private _nLive = { (["state", _x] call _run) select 1 isEqualTo "accent" } count _sib;
        (_c get "cTitle") ctrlSetStructuredText parseText format ["<t size='1.5' font='RobotoCondensedBold' color='%1'>%2</t><br/><t color='%3'>%4</t>", "text" call _hex, (toUpper (_head select 2)) call _esc, "text2" call _hex,
            ([(_head select 3) splitString "·" param [0, _head select 3], format [(["%1 dalle", "%1 dalles"] select ((count _sib) > 1)) call _tr, count _sib], [_head select 4, ("sans salle") call _tr] select ((_head select 4) isEqualTo ""),
                format [("à %1 m") call _tr, _head select 5], format [("%1 en direct") call _tr, _nLive]] joinString " · ") call _esc];
        private _fmt = ["format", [_cur select 1, objNull] select is3DEN] call comspec_atak_native_fnc_tocScreen;
        private _ratio = (((_fmt param [0, 1.778]) max 0.6) min 8);
        ([["grid", [typeOf _host, count _sib]] call _dat, [1, 1]] select ((count _sib) <= 1)) params ["_gc", "_gr"];
        // Compact : une seule rangée de cases, quel que soit le mur.
        if (_compact) then { _gc = (count _sib) max 1; _gr = 1; };
        private _sel = _st get "sel";
        private _keys = [];
        private _thumbs = (profileNamespace getVariable ["COMSPEC_ATAK_TocMgrThumbs", true]) && {!is3DEN} && {!_compact} && {(count _sib) <= 12};
        // Zone du schéma : dans le groupe du centre, ou bande de cases dans l'inspecteur (compact).
        private _cenW = _st get "cenW";
        private _nOth = 0;
        private _oRows = [];
        if (!_compact) then {
            private _seen = [_host];
            { if !((_x select 11) in _seen) then { _seen pushBack (_x select 11); if ((_x select 4) isEqualTo (_cur select 4)) then { _oRows pushBack _x; }; }; } forEach (_rows select { (_x select 1) isEqualTo (_x select 11) });
            _oRows = _oRows select [0, 8];
            _nOth = count _oRows;
        };
        private _grpH = (_st get "midH") - 84;
        private _othH = [0, 44 + (ceil (_nOth / 4)) * 160] select (_nOth > 0);
        private _area = if (_compact) then { _st get "stripRect" } else { [16, 0, _cenW - 32, _grpH - 70 - _othH] };
        _area params ["_ax", "_ay", "_aw", "_ah"];
        private _cellW = (_aw - (_gc - 1) * 10) / _gc;
        private _cellH = (_ah - (_gr - 1) * 10) / _gr;
        private _imgW = if (_compact) then { _cellW - 4 } else { ((_cellW - 4) min ((_cellH - 62) * _ratio)) min 760 };
        private _imgH = if (_compact) then { _ah - 4 } else { _imgW / _ratio };
        private _tw = _imgW + 4;
        private _th = if (_compact) then { _ah } else { _imgH + 62 };
        private _ox = _ax + (_aw - (_gc * _tw + (_gc - 1) * 10)) / 2;
        private _small = _tw < 190;
        {
            private _t = _x;
            private _r = _sib param [_forEachIndex, []];
            if (_r isEqualTo [] || {_forEachIndex >= _gc * _gr}) then { { _x ctrlShow false; } forEach _t; _keys pushBack ""; continue };
            _t params ["_fr", "_bg", "_pic", "_lbl", "_hd", "_stt", "_ft", "_hit", "_pw"];
            private _tx = _ox + (_forEachIndex mod _gc) * (_tw + 10);
            private _ty = _ay + (floor (_forEachIndex / _gc)) * (_th + 10);
            private _isSel = (_r select 0) in _sel;
            (["state", _r] call _run) params ["_stL", "_stTok"];
            [_fr, [_tx, _ty, _tw, _th], !_compact] call _place;
            _fr ctrlSetBackgroundColor ([["lineStrong", "accent"] select _isSel] call _col);
            [_bg, [_tx + 2, _ty + 2, _tw - 4, _th - 4], !_compact] call _place;
            _bg ctrlSetBackgroundColor (["bg0"] call _col);
            [_hd, [_tx + 2, _ty + 2, _tw - 4, [30, _th - 4] select _compact], !_compact] call _place;
            _hd ctrlSetBackgroundColor ([["bg2", "accentDim"] select _isSel] call _col);
            _hd ctrlSetText format ["%1 · %2", (_r select 12) + 1, toUpper (_r select 7)]; // i18n:brut (numéro et source déjà traduite)
            _hd ctrlSetFontHeight (([16, 13] select _small) * _uh);
            _hd ctrlSetTextColor ([["text", "accent"] select _isSel] call _col);
            _hd ctrlSetTooltip format ["%1 · %2 · %3", _r select 2, _r select 7, _stL];
            [_hit, [_tx, _ty, _tw, _th], !_compact] call _place;
            _hit ctrlSetTooltip format ["%1 · %2 · %3", _r select 2, _r select 7, _stL];
            { _x ctrlShow true; } forEach [_fr, _bg, _hd, _hit];
            if (_compact) then { { _x ctrlShow false; } forEach [_pic, _lbl, _stt, _ft, _pw]; } else {
                [_stt, [_tx + _tw * 0.5, _ty + 2, _tw * 0.5 - 8, 30]] call _place;
                _stt ctrlSetText ([toUpper _stL, ""] select _small); // i18n:brut (état déjà traduit)
                _stt ctrlSetFontHeight (13 * _uh);
                _stt ctrlSetTextColor ([_stTok] call _col);
                [_pic, [_tx + 2, _ty + 32, _imgW, _imgH]] call _place;
                [_lbl, [_tx + 2, _ty + 32 + _imgH * 0.38, _imgW, _imgH * 0.5]] call _place;
                [_ft, [_tx + 2, _ty + 32 + _imgH, _tw - 4, 28]] call _place;
                _ft ctrlSetBackgroundColor (["bg1"] call _col);
                _ft ctrlSetText format [("LUM. %1 %2") call _tr, _r select 8, "%"];
                _ft ctrlSetFontHeight (14 * _uh);
                _ft ctrlSetTextColor (["muted"] call _col);
                [_pw, [_tx + _tw - 30, _ty + 33 + _imgH, 26 * 0.9, 26]] call _place;
                _pw ctrlSetTextColor ([_stTok] call _col);
                _pic setVariable ["COMSPEC_ATAK_Row", _r select 0];
                _pic setVariable ["COMSPEC_ATAK_Tex", "?"];
                _lbl setVariable ["COMSPEC_ATAK_Txt", format ["<t align='center' size='%1' color='%2'>%3</t>", [1.1, 0.8] select _small, "text2" call _hex, (_r select 7) call _esc]];
                { _x ctrlShow true; } forEach [_stt, _ft, _pw, _lbl];
                _pw ctrlShow !is3DEN;
            };
            _keys pushBack (_r select 0);
        } forEach _tiles;
        _st set ["tileKeys", _keys];
        _st set ["thumbsOn", _thumbs];
        // Sous le schéma : mode d'emploi, source prête, autres écrans de la salle.
        private _hy = (ceil ((count _sib) / _gc) min _gr) * (_th + 10) + 4;
        if (!_compact) then {
            [_c get "cHint", [16, _hy, _cenW - 32, 28]] call _place;
            (_c get "cHint") ctrlSetStructuredText parseText format ["<t size='0.85' color='%1'>%2</t>", "muted" call _hex, ("Clic : choisir une dalle · Ctrl + clic : en ajouter · double-clic : changer sa source · molette : largeur de la carte") call _tr];
            private _oy = _grpH - _othH;
            (_c get "oHead") ctrlShow (_nOth > 0);
            if (_nOth > 0) then {
                [_c get "oHead", [16, _oy, _cenW - 32, 30]] call _place;
                (_c get "oHead") ctrlSetStructuredText parseText format ["<t size='1.1' font='RobotoCondensedBold' color='%1'>%2</t>", "text" call _hex, ("Autres écrans de la salle") call _tr];
            };
            private _ow = (_cenW - 32 - 30) / 4;
            private _oKeys = [];
            {
                private _t = _x;
                private _r = _oRows param [_forEachIndex, []];
                if (_r isEqualTo []) then { { _x ctrlShow false; } forEach _t; _oKeys pushBack ""; continue };
                _t params ["_fr", "_bg", "_pic", "_lbl", "_hd", "_stt", "_hit"];
                private _tx = 16 + (_forEachIndex mod 4) * (_ow + 10);
                private _ty = _oy + 40 + (floor (_forEachIndex / 4)) * 160;
                (["state", _r] call _run) params ["_stL", "_stTok"];
                [_fr, [_tx, _ty, _ow, 150]] call _place;
                _fr ctrlSetBackgroundColor (["line"] call _col);
                [_bg, [_tx + 1, _ty + 1, _ow - 2, 148]] call _place;
                _bg ctrlSetBackgroundColor (["bg0"] call _col);
                [_hd, [_tx + 1, _ty + 1, _ow - 2, 28]] call _place;
                _hd ctrlSetBackgroundColor (["bg2"] call _col);
                _hd ctrlSetText toUpper (_r select 2); // i18n:brut (nom de l'écran)
                _hd ctrlSetFontHeight (14 * _uh);
                [_stt, [_tx + _ow * 0.55, _ty + 1, _ow * 0.45 - 8, 28]] call _place;
                _stt ctrlSetText _stL; // i18n:brut (état déjà traduit)
                _stt ctrlSetFontHeight (13 * _uh);
                _stt ctrlSetTextColor ([_stTok] call _col);
                [_pic, [_tx + 1, _ty + 29, _ow - 2, 120]] call _place;
                [_lbl, [_tx + 1, _ty + 72, _ow - 2, 60]] call _place;
                [_hit, [_tx, _ty, _ow, 150]] call _place;
                _pic setVariable ["COMSPEC_ATAK_Row", _r select 0];
                _pic setVariable ["COMSPEC_ATAK_Tex", "?"];
                _lbl setVariable ["COMSPEC_ATAK_Txt", format ["<t align='center' size='0.85' color='%1'>%2</t>", "text2" call _hex, (_r select 7) call _esc]];
                { _x ctrlShow true; } forEach [_fr, _bg, _hd, _stt, _lbl, _hit];
                _oKeys pushBack (_r select 0);
            } forEach _others;
            _st set ["otherKeys", _oKeys];
        };
        (_c get "cReady") ctrlShow false;
        ["thumbs"] call _fn;
        true
    };
    case "thumbs": {
        // Vignettes : la texture déjà rendue de chaque dalle (aucun rendu de plus) ; sans texture : case avec le nom de la source.
        private _on = _st getOrDefault ["thumbsOn", false];
        private _do = {
            params ["_pic", "_lbl", "_shown"];
            if (!_shown) exitWith {};
            private _tex = ["", ["thumb", [_pic getVariable ["COMSPEC_ATAK_Row", ""]] call _rowOf] call _run] select _on;
            if (_tex isEqualTo (_pic getVariable ["COMSPEC_ATAK_Tex", "?"])) exitWith {};
            _pic setVariable ["COMSPEC_ATAK_Tex", _tex];
            _pic ctrlShow (_tex isNotEqualTo "");
            if (_tex isNotEqualTo "") then { _pic ctrlSetText _tex; };
            _lbl ctrlSetStructuredText parseText ([_lbl getVariable ["COMSPEC_ATAK_Txt", ""], ""] select (_tex isNotEqualTo ""));
        };
        if !(_st getOrDefault ["compact", false]) then {
            { [_x select 2, _x select 3, ctrlShown (_x select 0)] call _do; } forEach (_st getOrDefault ["tiles", []]);
            { [_x select 2, _x select 3, ctrlShown (_x select 0)] call _do; } forEach (_st getOrDefault ["others", []]);
        };
        true
    };
    case "tab": {
        private _n = (_a max 0) min 5;
        _st set ["tab", _n];
        private _compactHide = (_st get "compact") && {(_st get "view") isNotEqualTo 0};
        for "_i" from 0 to 5 do {
            (_c get format ["page%1", _i]) ctrlShow (_i isEqualTo _n && {!_compactHide});
            (_c get format ["tab%1", _i]) ctrlSetTextColor ([["text2", "accent"] select (_i isEqualTo _n)] call _col);
        };
        private _b = _c get format ["tab%1", _n];
        (ctrlPosition _b) params ["_bx", "_by", "_bw", "_bh"];
        (_c get "tabRule") ctrlSetPosition [_bx, _by + _bh, _bw, 2 * _uh];
        (_c get "tabRule") ctrlCommit 0;
        ["help", ["tab", _n]] call _fn;
        true
    };
    case "view": {
        // Vue du centre : 0 ÉCRAN, 1 SALLES, 2 JOURNAL, 3 ROUTAGE (bientôt). Compact : la vue prend la place de l'inspecteur.
        private _n = (_a max 0) min 3;
        _st set ["view", _n];
        private _compact = _st get "compact";
        if ((lbCurSel (_c get "views")) isNotEqualTo _n) then { (_c get "views") lbSetCurSel _n; };
        { (_c get _x) ctrlShow (_forEachIndex isEqualTo _n && {!_compact || {_n > 0}}); } forEach ["vScreen", "vRooms", "vLog", "vRoute"];
        (_c get "cTitle") ctrlShow (!_compact && {_n isEqualTo 0});
        if (_compact) then {
            private _show = _n isEqualTo 0;
            { (_c get _x) ctrlShow _show; } forEach ["iTitle", "tabRule", "tabLine", "lScope", "scope", "helpBg", "help", "tab0", "tab1", "tab2", "tab3", "tab4", "tab5"];
            if (_show) then { ["wall"] call _fn; } else { { { _x ctrlShow false; } forEach _x; } forEach (_st get "tiles"); };
            ["tab", _st get "tab"] call _fn;
        };
        if (_n isEqualTo 1) then { ["roomsFill"] call _fn; };
        if (_n isEqualTo 2) then { ["logAsk"] call _run; ["logFill"] call _fn; };
        true
    };
    case "roomsFill": {
        // Salles : nom, camp, écrans, allumés, scènes ; dessous, les liaisons (liés et non liés).
        private _t = _c getOrDefault ["rooms", controlNull];
        if (isNull _t) exitWith { false };
        private _keep = lnbCurSelRow _t;
        lnbClear _t;
        private _i0 = _t lnbAddRow (["SALLE", "CAMP", "ÉCRANS", "ALLUMÉS", "SCÈNES"] apply { _x call _tr });
        for "_j" from 0 to 4 do { _t lnbSetColor [[_i0, _j], ["muted"] call _col]; };
        private _list = ["halls"] call _run;
        {
            _x params ["_id", "_nm", "_side", "_nScr", "_nOn", "_nSc"];
            private _i = _t lnbAddRow [_nm, _side, str _nScr, str _nOn, str _nSc];
            _t lnbSetData [[_i, 0], _id];
        } forEach _list;
        if (_list isEqualTo []) then {
            private _i = _t lnbAddRow [([("Aucune salle. Écrivez un nom puis CRÉER, ou GÉNÉRER une salle complète devant vous.") call _tr, ("Dans Eden, les salles sont posées par le module Salle TOC complète.") call _tr] select is3DEN), "", "", "", ""];
            _t lnbSetColor [[_i, 0], ["text2"] call _col];
        };
        private _was = _st getOrDefault ["fill", false];
        _st set ["fill", true];
        _t lnbSetCurSelRow ((_keep max 1) min ((count _list) max 1));
        _st set ["fill", _was];
        private _links = ["links"] call _run;
        private _nNo = { !(_x select 3) } count _links;
        (_c get "linksHead") ctrlSetStructuredText parseText format ["<t size='1.1' font='RobotoCondensedBold' color='%1'>%2</t>  <t size='0.85' color='%3'>%4</t>", "text" call _hex, ("Liaisons") call _tr, ["muted", "red"] select (_nNo > 0) call _hex,
            (format [("%1 liés · %2 non liés · LIAISONS (en haut) les montre au Zeus") call _tr, (count _links) - _nNo, _nNo]) call _esc];
        private _tl = _c get "links";
        lnbClear _tl;
        private _h0 = _tl lnbAddRow (["OBJET", "GENRE", "SALLE", "ÉTAT"] apply { _x call _tr });
        for "_j" from 0 to 3 do { _tl lnbSetColor [[_h0, _j], ["muted"] call _col]; };
        {
            _x params ["_nm", "_kind", "_hall", "_linked"];
            private _i = _tl lnbAddRow [_nm, _kind call _tr, _hall, ([("non lié") call _tr, ("lié") call _tr] select _linked)];
            _tl lnbSetColor [[_i, 3], [["red", "accent"] select _linked] call _col];
        } forEach _links;
        true
    };
    case "roomPick": {
        if (_st getOrDefault ["fill", false]) exitWith { false };
        private _t = _c get "rooms";
        private _nm = _t lnbText [_a, 0];
        if ((_t lnbData [_a, 0]) isNotEqualTo "") then { (_c get "roomName") ctrlSetText _nm; }; // i18n:brut (nom de salle)
        true
    };
    case "logFill": {
        private _t = _c getOrDefault ["log", controlNull];
        if (isNull _t) exitWith { false };
        (["log"] call _run) params ["_state", "_lines", "_stamp"];
        if (_stamp isEqualTo (_st getOrDefault ["logStamp", -1])) exitWith { false };
        _st set ["logStamp", _stamp];
        (_c get "logHead") ctrlSetStructuredText parseText format ["<t size='1.1' font='RobotoCondensedBold' color='%1'>%2</t>  <t size='0.85' color='%3'>%4</t>", "text" call _hex, ("Journal du TOC") call _tr, "muted" call _hex, (_state call _tr) call _esc];
        lnbClear _t;
        private _h0 = _t lnbAddRow (["HEURE Z", "AUTEUR", "SALLE", "ACTION", "DÉTAIL"] apply { _x call _tr });
        for "_j" from 0 to 4 do { _t lnbSetColor [[_h0, _j], ["muted"] call _col]; };
        { private _i = _t lnbAddRow (_x select [0, 5]); if !(_x param [5, true]) then { _t lnbSetColor [[_i, 3], ["red"] call _col]; }; } forEach _lines;
        true
    };
    case "fill": {
        // Inspecteur : titre, sources, champs de chaque onglet à leur valeur du moment (un champ touché garde sa saisie).
        private _cur = [_st get "cur"] call _rowOf;
        private _sel = _st get "sel";
        private _rows = _st get "rows";
        _st set ["fill", true];
        if (_cur isEqualTo []) exitWith {
            (_c get "iTitle") ctrlSetStructuredText parseText format ["<t size='1.35' font='RobotoCondensedBold' color='%1'>%2</t><br/><t color='%3'>%4</t>", "text" call _hex, ("Aucune dalle choisie") call _tr, "text2" call _hex, ("Choisissez un écran dans l'arbre.") call _tr];
            lbClear (_c get "fams");
            lnbClear (_c get "srcs");
            (_c get "srcNow") ctrlSetStructuredText parseText "";
            { ["stack", [_x, [], true]] call _fn; } forEach ["VIEW", "LAY", "TXT", "PWR", "ADV"];
            _st set ["fill", false];
            false
        };
        private _nSib = { (_x select 11) isEqualTo (_cur select 11) } count _rows;
        private _host = (_rows select { (_x select 1) isEqualTo (_cur select 11) }) param [0, _cur];
        (["state", _cur] call _run) params ["_stL", "_stTok"];
        private _ttl = if ((count _sel) > 1) then { format [("%1 dalles choisies") call _tr, count _sel] } else {
            [format ["%1 · %2", _cur select 2, _cur select 7], format [("Dalle %1 · %2") call _tr, (_cur select 12) + 1, _cur select 7]] select (_nSib > 1)
        };
        (_c get "iTitle") ctrlSetStructuredText parseText format ["<t size='1.35' font='RobotoCondensedBold' color='%1'>%2</t><br/><t color='%3'>%4 · </t><t color='%5'>%6</t><t color='%3'> · %7</t>", "text" call _hex, _ttl call _esc, "text2" call _hex,
            (toUpper (_host select 2)) call _esc, _stTok call _hex, _stL call _esc, (format [("luminosité %1 %2") call _tr, _cur select 8, "%"]) call _esc];
        // SOURCE : sources de la dalle, avec leur état et les écrans qui les montrent déjà.
        private _items = ["items", _cur] call _run;
        _st set ["items", _items];
        private _mixed = (count (_sel apply { ([_x] call _rowOf) param [6, ""] } arrayIntersect (_sel apply { ([_x] call _rowOf) param [6, ""] }))) > 1;
        private _pend = (_st get "changes") getOrDefault ["source", ""];
        (_c get "srcNow") ctrlSetStructuredText parseText format ["<t color='%1'>%2  </t><t font='RobotoCondensedBold' color='%3'>%4</t>  <t size='0.85' color='%5'>%6</t>%7", "text2" call _hex, ("Source actuelle") call _tr, "text" call _hex,
            ([_cur select 7, ("valeurs différentes") call _tr] select _mixed) call _esc, "accent" call _hex, (((["families"] call _dat) select { (_x select 1) isEqualTo (["family", _cur select 6] call _dat) }) param [0, [""]] select 0) call _tr,
            ["", format ["  <t color='%1'>→ %2</t>", "amber" call _hex, ((_items select { (_x select 1) isEqualTo _pend }) param [0, [_pend]] select 0) call _esc]] select (_pend isNotEqualTo "")];
        if (_pend isEqualTo "") then { _st set ["fam", ["family", _cur select 6] call _dat]; };
        ["srcFill"] call _fn;
        // Valeurs des champs (table clé -> valeur), listes d'options, champs cachés selon la source.
        private _vals = ["values", _cur] call _run;
        private _ch = _st get "changes";
        { [_x select 0, _x select 1, _x param [2, false]] call _fopts; } forEach (_vals getOrDefault ["#opts", []]);
        {
            private _fk = _x select 0;
            if (_fk in _vals) then { [_fk, _vals get _fk] call _fset; };
        } forEach (["fields"] call _dat);
        // Champs dont un changement attend : la saisie du joueur est reposée par-dessus la valeur du jeu.
        {
            _x params ["_fk", "_chk"];
            if (_chk in _ch) then {
                private _v = _ch get _chk;
                switch (_fk) do {
                    case "vWidth": { [_fk, ["w2p", _v] call _dat] call _fset; };
                    case "vCenter": {};
                    case "pAuto": { [_fk, str _v] call _fset; };
                    default { [_fk, _v] call _fset; };
                };
            };
        } forEach [["vFrame", "frame"], ["vWidth", "width"], ["vFollow", "follow"], ["vSensor", "sensor"], ["vJit", "jit"], ["vTerr", "terr"], ["pPower", "power"], ["pBright", "bright"], ["pDist", "dist"], ["pAuto", "autoOff"], ["aName", "name"], ["aHall", "hall"], ["aTitle", "title"], ["aTally", "tally"]];
        // Plusieurs dalles : un champ dont les valeurs diffèrent le dit, et n'est écrit que si on le touche.
        private _mix = ["mixed", _sel apply { [_x] call _rowOf }] call _run;
        {
            ((_st get "fctl") getOrDefault [_x, ["", controlNull]]) params ["", "_l"];
            if (!isNull _l) then {
                private _isMix = _x in _mix;
                _l ctrlSetText ([(_l getVariable ["COMSPEC_ATAK_Lbl", ""]) call _tr, ("valeurs différentes") call _tr] select _isMix);
                _l ctrlSetTextColor ([["text2", "amber"] select _isMix] call _col);
                if (_isMix && {_x isEqualTo "pPower"} && {!("power" in _ch)}) then { ["pPower", -1] call _fset; };
            };
        } forEach ["pPower", "pBright", "aHall"];
        { ["stack", [_x, _vals getOrDefault ["#hide", []], false]] call _fn; } forEach ["VIEW", "LAY", "TXT", "PWR", "ADV"];
        // Textes d'information.
        {
            _x params ["_fk", "_txt", ["_tok", "text2"]];
            private _ctl = (((_st get "fctl") getOrDefault [_fk, ["", controlNull, [controlNull]]]) select 2) param [0, controlNull];
            if (!isNull _ctl) then { _ctl ctrlSetStructuredText parseText format ["<t size='0.9' color='%1'>%2</t>", _tok call _hex, _txt call _esc]; };
        } forEach (_vals getOrDefault ["#texts", []]);
        // DISPOSITION et TEXTE : leurs listes.
        private _tLay = _c get "layouts";
        lnbClear _tLay;
        {
            _x params ["_lbl", "_key", "_content", "_isCur"];
            private _i = _tLay lnbAddRow [["", "●"] select _isCur, _lbl call _tr, _content];
            _tLay lnbSetData [[_i, 0], _key];
            _tLay lnbSetColor [[_i, 0], ["accent"] call _col];
            _tLay lnbSetColor [[_i, 2], ["muted"] call _col];
        } forEach (_vals getOrDefault ["#layouts", []]);
        if ((lnbSize _tLay) select 0 isEqualTo 0) then {
            private _i = _tLay lnbAddRow ["", ([("Cet écran n'a qu'une dalle : choisissez sa source dans l'onglet SOURCE.") call _tr, ("Dispositions : en jeu, sur un mur ou un écran incurvé.") call _tr] select is3DEN), ""];
            _tLay lnbSetColor [[_i, 1], ["text2"] call _col];
        };
        if !("text" in _ch) then { _st set ["tq", +(_vals getOrDefault ["#text", [[], 1, 0, 0, 1]])]; };
        ["msgFill", 0] call _fn;
        _st set ["fill", false];
        true
    };
    case "stack": {
        // Range les champs d'un onglet de haut en bas ; les champs cachés ne prennent pas de place.
        _a params ["_tab", ["_hidden", []], ["_all", false]];
        // TEXTE sur une dalle sans texte : la file et la saisie sont cachées, l'explication monte en tête.
        private _noTxt = _all || {"tBtns" in _hidden};
        if (_tab isEqualTo "TXT") then { { (_c get _x) ctrlShow !_noTxt; } forEach ["queue", "msg"]; };
        private _y = switch (_tab) do { case "LAY": { 34 * 8 + 10 }; case "TXT": { [34 * 4 + 8 + 78 + 10, 2] select _noTxt }; default { 2 }; };
        {
            _x params ["_fk", "_ftab"];
            if (_ftab isNotEqualTo _tab) then { continue };
            ((_st get "fctl") getOrDefault [_fk, ["", controlNull, [], 0]]) params ["", "_l", "_ctls", "_h"];
            private _show = !_all && {!(_fk in _hidden)};
            {
                if (!isNull _x) then {
                    _x ctrlShow _show;
                    if (_show) then { (_x getVariable ["COMSPEC_ATAK_R", [0, 0, 0, 0]]) params ["_rx", "_ry", "_rw", "_rh"]; [_x, [_rx, _y + _ry, _rw, _rh]] call _place; };
                };
            } forEach ([_l] + _ctls);
            if (_show) then { _y = _y + _h + 8; };
        } forEach (["fields"] call _dat);
        true
    };
    case "srcFill": {
        // Familles (avec leur nombre) et sources de la famille choisie ; la recherche du haut et le filtre de l'onglet s'appliquent.
        private _lFam = _c getOrDefault ["fams", controlNull];
        if (isNull _lFam) exitWith { false };
        private _cur = [_st get "cur"] call _rowOf;
        if (_cur isEqualTo []) exitWith { false };
        private _items = _st getOrDefault ["items", []];
        private _find = trim (ctrlText (_c get "srcFind"));
        if (_find isEqualTo "") then { _find = trim (_st get "find"); };
        private _used = [];
        { private _k = _x select 1; _used pushBack [_k, ((_st get "rows") select { (_x select 6) isEqualTo _k && {(_x select 0) isNotEqualTo (_cur select 0)} }) apply { _x select 2 }]; } forEach _items;
        private _groups = ["sourceList", [_items apply { [_x select 0, _x select 1, _x select 2] }, _used, _find]] call _dat;
        // Aucune source trouvée dans la famille par la recherche du haut : la recherche du haut est ignorée ici.
        private _fam = _st get "fam";
        private _was = _st getOrDefault ["fill", false];
        _st set ["fill", true];
        lbClear _lFam;
        private _fi = 0;
        {
            _x params ["_title", "_id", "_n"];
            private _i = _lFam lbAdd format ["%1  %2", _title call _tr, _n];
            _lFam lbSetData [_i, _id];
            if (_n isEqualTo 0) then { _lFam lbSetColor [_i, ["muted"] call _col]; };
            if (_id isEqualTo _fam) then { _fi = _i; };
        } forEach _groups;
        _lFam lbSetCurSel _fi;
        (_c get "camFilter") ctrlShow (_fam isEqualTo "CAM");
        private _tSrc = _c get "srcs";
        private _isCam = _fam isEqualTo "CAM";
        _tSrc lnbSetColumnsPos ([[0, 0.07, 0.6], [0, 0.07, 0.56, 0.78]] select _isCam);
        lnbClear _tSrc;
        private _h0 = _tSrc lnbAddRow ((["", "Source", "Aussi montrée sur"] + ([[], ["État"]] select _isCam)) apply { _x call _tr });
        for "_j" from 0 to 3 do { _tSrc lnbSetColor [[_h0, _j], ["muted"] call _col]; };
        private _camF = lbCurSel (_c get "camFilter");
        private _cur6 = _cur select 6;
        private _pend = (_st get "changes") getOrDefault ["source", ""];
        private _n = 0;
        private _selRow = -1;
        {
            _x params ["_lbl", "_key", "_state", "_also"];
            private _it = (_items select { (_x select 1) isEqualTo _key }) param [0, []];
            private _genre = _it param [3, ""];
            // Filtre des caméras : toutes, en ligne, drones, fixes, portées.
            if (_isCam && {(_key select [0, 4]) isEqualTo "CAM|"} && {(_camF isEqualTo 1 && {_state isNotEqualTo "ok"}) || {_camF isEqualTo 2 && {_genre isNotEqualTo "UAV"}} || {_camF isEqualTo 3 && {_genre isNotEqualTo "CAM"}} || {_camF isEqualTo 4 && {_genre isNotEqualTo "MAN"}}}) then { continue };
            private _tok = switch (_state) do { case "ok": { "accent" }; case "off": { "amber" }; case "no": { "muted" }; default { "text2" }; };
            private _i = _tSrc lnbAddRow ((["●", _lbl, _also]) + ([[], [_it param [4, ""]]] select _isCam));
            _tSrc lnbSetData [[_i, 0], _key];
            _tSrc lnbSetColor [[_i, 0], [[_tok, "accent"] select (_key isEqualTo _cur6)] call _col];
            if (_state isEqualTo "no") then { for "_j" from 1 to 3 do { _tSrc lnbSetColor [[_i, _j], ["muted"] call _col]; }; };
            if (_state isEqualTo "off") then { _tSrc lnbSetColor [[_i, 3], ["amber"] call _col]; };
            _tSrc lnbSetColor [[_i, 2], ["muted"] call _col];
            if (_key isEqualTo ([_pend, _cur6] select (_pend isEqualTo ""))) then { _selRow = _i; };
            _n = _n + 1;
        } forEach (((_groups select { (_x select 1) isEqualTo _fam }) param [0, ["", "", 0, []]]) select 3);
        if (_n isEqualTo 0) then {
            private _why = switch (true) do {
                case (_find isNotEqualTo ""): { "Aucune source ne correspond à la recherche dans cette famille." };
                case (_fam isEqualTo "CAM"): { "Aucune caméra du camp en vue : drone en vol, caméra fixe posée ou porteur d'ATAK avec caméra." };
                case (_fam isEqualTo "PLAN"): { "Le plan du camp est vide : dessinez-le dans l'app Plan ou au PC du poste." };
                case (is3DEN): { "Dans Eden, seuls les affichages fixes se choisissent ; les sources vivantes se règlent en jeu." };
                default { "Aucune source de cette famille pour cette dalle." };
            };
            private _i = _tSrc lnbAddRow ["", _why call _tr, ""];
            _tSrc lnbSetColor [[_i, 1], ["text2"] call _col];
        };
        if (_selRow >= 0) then { _tSrc lnbSetCurSelRow _selRow; };
        _st set ["fill", _was];
        true
    };
    case "famPick": {
        if (_st getOrDefault ["fill", false]) exitWith { false };
        private _id = (_c get "fams") lbData _a;
        _st set ["fam", _id];
        ["srcFill"] call _fn;
        ["help", ["fam", _id]] call _fn;
        true
    };
    case "srcPick": {
        // Source choisie dans la liste : changement en attente ; refusée par le format de la dalle : dite, pas retenue.
        if (_st getOrDefault ["fill", false]) exitWith { false };
        private _key = (_c get "srcs") lnbData [_a, 0];
        if (_key isEqualTo "") exitWith { false };
        private _it = ((_st get "items") select { (_x select 1) isEqualTo _key }) param [0, []];
        if ((_it param [2, ""]) isEqualTo "no") exitWith {
            ["help", ["text", ["refusal", [_st get "cur"] call _rowOf] call _run]] call _fn;
            false
        };
        private _cur = [_st get "cur"] call _rowOf;
        if (_key isEqualTo (_cur param [6, ""]) && {(count (_st get "sel")) <= 1}) then { (_st get "changes") deleteAt "source"; } else { (_st get "changes") set ["source", _key]; };
        if ((_it param [2, ""]) isEqualTo "off") then {
            ["help", ["text", "Cette caméra est hors ligne : la dalle montrera PAS DE SIGNAL jusqu'à son retour. Vous pouvez quand même l'appliquer. Exemple : une caméra fixe dont le relais est tombé."]] call _fn;
        } else { ["help", ["text", format ["%1. %2", _it param [0, ""], ("Double-clic : appliquer tout de suite. Exemple : la même source sur toute la salle avec « Appliquer à : La salle ».") call _tr]]] call _fn; };
        ["status"] call _fn;
        true
    };
    case "srcDbl": {
        private _key = (_c get "srcs") lnbData [_a, 0];
        if (_key isEqualTo "") exitWith { false };
        private _it = ((_st get "items") select { (_x select 1) isEqualTo _key }) param [0, []];
        if ((_it param [2, ""]) isEqualTo "no") exitWith { false };
        (_st get "changes") set ["source", _key];
        ["apply"] call _fn;
        true
    };
    case "layPick": {
        if (_st getOrDefault ["fill", false]) exitWith { false };
        private _key = (_c get "layouts") lnbData [_a, 0];
        if (_key isEqualTo "") exitWith { false };
        (_st get "changes") set ["layout", _key];
        ["help", ["text", ["layoutHelp", _key] call _run]] call _fn;
        ["status"] call _fn;
        true
    };
    case "layDbl": {
        private _key = (_c get "layouts") lnbData [_a, 0];
        if (_key isEqualTo "") exitWith { false };
        (_st get "changes") set ["layout", _key];
        ["apply"] call _fn;
        true
    };
    case "msgFill": {
        // File des messages du panneau (copie de travail « tq ») ; _a : ligne à choisir.
        private _t = _c getOrDefault ["queue", controlNull];
        if (isNull _t) exitWith { false };
        private _tq = _st getOrDefault ["tq", [[], 1, 0, 0, 1]];
        private _msgs = _tq param [0, []];
        private _was = _st getOrDefault ["fill", false];
        _st set ["fill", true];
        lnbClear _t;
        private _tokOf = { ["accent", "amber", "red", "text"] select ((["G", "A", "R", "W"] find _this) max 0) };
        {
            _x params [["_m", ""], ["_k", "G"], ["_secs", 10], ["_blk", 0]];
            private _i = _t lnbAddRow [str (_forEachIndex + 1), _m, format ["%1 s", _secs], ["", ("clignote") call _tr] select (_blk isEqualTo 1)];
            _t lnbSetColor [[_i, 1], [_k call _tokOf] call _col];
            _t lnbSetColor [[_i, 0], ["muted"] call _col];
        } forEach _msgs;
        if (_msgs isEqualTo []) then {
            private _i = _t lnbAddRow ["", ("Aucun message : écrivez-en un dessous, puis AJOUTER.") call _tr, "", ""];
            _t lnbSetColor [[_i, 1], ["text2"] call _col];
        };
        private _row = (_a max 0) min (((count _msgs) - 1) max 0);
        _t lnbSetCurSelRow _row;
        private _m = _msgs param [_row, ["", "G", 10, 0]];
        (_c get "msg") ctrlSetText (_m param [0, ""]); // i18n:brut (message saisi)
        ["tColor", (["G", "A", "R", "W"] find (_m param [1, "G"])) max 0] call _fset;
        ["tSecs", str (_m param [2, 10])] call _fset;
        ["tBlink", (_m param [3, 0]) isEqualTo 1] call _fset;
        ["tSpeed", _tq param [1, 1]] call _fset;
        ["tScroll", _tq param [2, 0]] call _fset;
        ["tAlign", _tq param [3, 0]] call _fset;
        ["tBorder", (_tq param [4, 1]) isEqualTo 1] call _fset;
        _st set ["fill", _was];
        ["msgPrev"] call _fn;
        true
    };
    case "msgPick": {
        if (_st getOrDefault ["fill", false]) exitWith { false };
        private _m = ((_st get "tq") param [0, []]) param [_a, []];
        if (_m isEqualTo []) exitWith { false };
        _st set ["fill", true];
        (_c get "msg") ctrlSetText (_m param [0, ""]); // i18n:brut (message saisi)
        ["tColor", (["G", "A", "R", "W"] find (_m param [1, "G"])) max 0] call _fset;
        ["tSecs", str (_m param [2, 10])] call _fset;
        ["tBlink", (_m param [3, 0]) isEqualTo 1] call _fset;
        _st set ["fill", false];
        ["msgPrev"] call _fn;
        true
    };
    case "msgPrev": {
        // Aperçu du message en cours de saisie, dans sa couleur.
        private _ctl = (((_st get "fctl") getOrDefault ["tPrev", ["", controlNull, [controlNull]]]) select 2) param [0, controlNull];
        if (isNull _ctl) exitWith { false };
        private _txt = (ctrlText (_c get "msg")) regexReplace ["[\r\n]+", " "];
        private _tok = ["accent", "amber", "red", "text"] select ((["tColor"] call _fget) max 0);
        _ctl ctrlSetStructuredText parseText format ["<t font='EtelkaMonospaceProBold' color='%1'>%2</t>", _tok call _hex, ([toUpper _txt, ("(rien à montrer)") call _tr] select (_txt isEqualTo "")) call _esc];
        true
    };
    case "msgAct": {
        // Boutons de la file : la copie de travail change, « text » devient un changement en attente.
        private _tq = _st get "tq";
        private _msgs = +(_tq param [0, []]);
        private _t = _c get "queue";
        private _i = (lnbCurSelRow _t) max 0;
        private _txt = ((trim ((ctrlText (_c get "msg")) regexReplace ["[\r\n]+", " "])) select [0, 300]);
        private _new = [_txt, ["G", "A", "R", "W"] select ((["tColor"] call _fget) max 0), (parseNumber (["tSecs"] call _fget)) max 5, parseNumber (["tBlink"] call _fget)];
        private _why = "";
        switch (_a) do {
            case "msgAdd": { if (_txt isEqualTo "") then { _why = "Écrivez d'abord le message"; } else { if ((count _msgs) >= 8) then { _why = "La file est pleine : 8 messages au plus"; } else { _msgs pushBack _new; _i = (count _msgs) - 1; }; }; };
            case "msgSet": { if (_txt isEqualTo "" || {_i >= (count _msgs)}) then { _why = "Choisissez un message et écrivez son nouveau texte"; } else { _msgs set [_i, _new]; }; };
            case "msgDel": { if (_i < (count _msgs)) then { _msgs deleteAt _i; _i = (_i - 1) max 0; }; };
            case "msgUp": { if (_i > 0 && {_i < (count _msgs)}) then { private _m = _msgs deleteAt _i; _msgs insert [_i - 1, [_m]]; _i = _i - 1; }; };
            case "msgDown": { if (_i < (count _msgs) - 1) then { private _m = _msgs deleteAt _i; _msgs insert [_i + 1, [_m]]; _i = _i + 1; }; };
            case "msgClear": { _msgs = []; _i = 0; };
        };
        if (_why isNotEqualTo "") exitWith { ["say", [_why call _tr, "amber"]] call _fn; false };
        _tq set [0, _msgs];
        (_st get "changes") set ["text", +_tq];
        ["msgFill", _i] call _fn;
        ["status"] call _fn;
        true
    };
    case "chgEdit": {
        // Saisie à côté d'un curseur : le nombre écrit déplace le curseur.
        private _fk = _a;
        ((_st get "fctl") getOrDefault [_fk, ["", controlNull, []]]) params ["", "", "_ctls"];
        private _v = parseNumber (ctrlText (_ctls param [1, controlNull]));
        if (_v <= 0 && {_fk isNotEqualTo "pDist"}) exitWith { false };
        (_ctls param [0, controlNull]) sliderSetPosition ([_v, ["w2p", _v] call _dat] select (_fk isEqualTo "vWidth"));
        private _chk = switch (_fk) do { case "vWidth": { "width" }; case "pBright": { "bright" }; default { "dist" }; };
        (_st get "changes") set [_chk, switch (_fk) do { case "vWidth": { (_v max 60) min 20000 }; case "pBright": { (_v max 25) min 100 }; default { (_v max 0) min 150 } }];
        ["status"] call _fn;
        true
    };
    case "chg": {
        // Champ touché par le joueur : sa valeur devient un changement en attente (rien n'est écrit avant APPLIQUER).
        if (_st getOrDefault ["fill", false]) exitWith { false };
        private _fk = _a;
        private _ch = _st get "changes";
        private _v = [_fk] call _fget;
        switch (_fk) do {
            case "vFrame": { if (_v isEqualTo "") then { _ch deleteAt "frame"; } else { _ch set ["frame", _v]; }; };
            case "vWidth": {
                private _w = ["p2w", _v] call _dat;
                _ch set ["width", _w];
                (((_st get "fctl") get "vWidth") select 2 param [1, controlNull]) ctrlSetText str _w; // i18n:brut (nombre)
            };
            case "vCenter": {
                // Le point est pris maintenant : celui que vise le joueur (caméra du Zeus : centre de la vue), ou sa position.
                switch (_v) do {
                    case 1: { private _p = screenToWorld [0.5, 0.5]; _ch set ["center", [_p select 0, _p select 1]]; };
                    case 2: { private _p = if (!isNull curatorCamera && {!isNull (findDisplay 312)}) then { getPosATL curatorCamera } else { getPosATL player }; _ch set ["center", [_p select 0, _p select 1]]; };
                    default { _ch deleteAt "center"; };
                };
            };
            case "vFollow": { if (_v isEqualTo "") then { _ch deleteAt "follow"; } else { _ch set ["follow", _v]; }; };
            case "vSensor": { if (_v isEqualTo "") then { _ch deleteAt "sensor"; } else { _ch set ["sensor", _v]; }; };
            case "vJit": { _ch set ["jit", _v]; };
            case "vTerr": { _ch set ["terr", _v]; };
            case "vGrid"; case "vRot": { _ch set ["mosaic", [[2, 3] select ((["vGrid"] call _fget) isEqualTo 1), parseNumber (["vRot"] call _fget)]]; };
            case "pPower": { if (_v >= 0) then { _ch set ["power", _v]; }; };
            case "pBright": { _ch set ["bright", (round (_v / 5)) * 5]; (((_st get "fctl") get "pBright") select 2 param [1, controlNull]) ctrlSetText str ((round (_v / 5)) * 5); }; // i18n:brut (nombre)
            case "pDist": { _ch set ["dist", (round (_v / 5)) * 5]; (((_st get "fctl") get "pDist") select 2 param [1, controlNull]) ctrlSetText str ((round (_v / 5)) * 5); }; // i18n:brut (nombre)
            case "pAuto": { _ch set ["autoOff", parseNumber _v]; };
            case "aName": { if ((trim _v) isEqualTo "") then { _ch deleteAt "name"; } else { _ch set ["name", (trim _v) select [0, 28]]; }; };
            case "aHall": { _ch set ["hall", _v]; };
            case "aTitle": { _ch set ["title", (trim _v) select [0, 60]]; };
            case "aTally": { _ch set ["tally", _v]; };
            case "aThumbs": {
                profileNamespace setVariable ["COMSPEC_ATAK_TocMgrThumbs", _v];
                saveProfileNamespace;
                ["wall"] call _fn;
            };
            case "tColor"; case "tSecs"; case "tBlink": { ["msgPrev"] call _fn; };
            case "tSpeed"; case "tScroll"; case "tAlign"; case "tBorder": {
                private _tq = _st get "tq";
                _tq set [1, (["tSpeed"] call _fget) max 0];
                _tq set [2, (["tScroll"] call _fget) max 0];
                _tq set [3, (["tAlign"] call _fget) max 0];
                _tq set [4, parseNumber (["tBorder"] call _fget)];
                _ch set ["text", +_tq];
            };
            case "tSaved"; case "tVar": {
                if (_v isNotEqualTo "") then {
                    private _e = _c get "msg";
                    _e ctrlSetText ([_v, (ctrlText _e) + _v] select (_fk isEqualTo "tVar")); // i18n:brut (message saisi)
                    _st set ["fill", true];
                    [_fk, ""] call _fset;
                    _st set ["fill", false];
                    ["msgPrev"] call _fn;
                };
            };
        };
        ["status"] call _fn;
        true
    };
    case "apply": {
        // Changements -> opérations dans l'ordre (fn_tocManagerUiData « plan », fonction pure) -> exécution.
        private _ch = _st get "changes";
        if ((count _ch) isEqualTo 0) exitWith { ["say", [("Rien à appliquer : changez d'abord un réglage") call _tr, "text2"]] call _fn; false };
        private _to = call _scoped;
        if (_to isEqualTo []) exitWith { ["say", [("Aucune dalle visée : choisissez un écran dans l'arbre") call _tr, "amber"]] call _fn; false };
        // L'écran hôte de chaque dalle (les opérations « une fois par écran » le visent).
        private _targets = _to apply {
            private _r = _x;
            private _h = ((_st get "rows") select { (_x select 1) isEqualTo (_r select 11) }) param [0, _r];
            [_r select 0, _h select 0, ["allowed", _r] call _run, _r select 6]
        };
        private _ops = ["plan", [(keys _ch) apply { [_x, _ch get _x] }, _targets]] call _dat;
        private _res = ["run", _ops] call _run;
        _st set ["changes", createHashMap];
        ["say", _res] call _fn;
        _st set ["due", diag_tickTime + 1.2];
        true
    };
    case "cancel": {
        private _n = count (_st get "changes");
        _st set ["changes", createHashMap];
        _st set ["ready", []];
        ["fill"] call _fn;
        ["say", [[("Rien à annuler") call _tr, format [(["%1 changement abandonné", "%1 changements abandonnés"] select (_n > 1)) call _tr, _n]] select (_n > 0), "text2"]] call _fn;
        true
    };
    case "esc": {
        // Échap : la boîte, la source prête, la recherche, les changements, puis la fenêtre.
        switch (true) do {
            case (((_st get "confirm") select 0) isNotEqualTo ""): { ["confirm", "esc"] call _fn; };
            case ((_st get "ready") isNotEqualTo []): { _st set ["ready", []]; ["say", [("Abandonné") call _tr, "text2"]] call _fn; };
            case ((_st get "find") isNotEqualTo ""): { (_c get "search") ctrlSetText ""; ["find", ""] call _fn; };
            case ((count (_st get "changes")) > 0): { ["cancel"] call _fn; };
            default { (_st get "d") closeDisplay 2; };
        };
        true
    };
    case "key": {
        _a params ["_d", "_dik", "_shift", "_ctrl"];
        private _foc = focusedCtrl _d;
        private _inEdit = !isNull _foc && {(ctrlType _foc) isEqualTo 2};
        private _confirming = ((_st get "confirm") select 0) isNotEqualTo "";
        switch (true) do {
            case (_dik isEqualTo 1): { ["esc"] call _fn; true };
            // Entrée : dans la boîte, le bouton par défaut est ANNULER ; dans le message, un retour à la ligne ; sinon APPLIQUER.
            case (_dik in [28, 156]): {
                if (_confirming) exitWith { if (_foc isEqualTo (_c get "cfOk")) then { ["confirm", "ok"] call _fn; } else { ["confirm", "enter"] call _fn; }; true };
                if (_foc isEqualTo (_c get "msg") || {_foc isEqualTo (_c get "search")} || {_foc isEqualTo (_c get "srcFind")} || {_foc isEqualTo (_c get "roomName")}) exitWith { false };
                if ((ctrlType _foc) in [1, 4, 5, 6, 12, 102]) exitWith { false };
                ["apply"] call _fn;
                true
            };
            case (_confirming): { false };
            case (_ctrl && {_dik >= 2} && {_dik <= 7}): { ["tab", _dik - 2] call _fn; true };
            case (_ctrl && {_dik isEqualTo 15}): { ["tab", ((_st get "tab") + ([1, 5] select _shift)) mod 6] call _fn; true };
            case (_ctrl && {_dik isEqualTo 33}): { ctrlSetFocus (_c get "search"); true };
            case (_ctrl && {_dik isEqualTo 30} && {!_inEdit}): {
                private _cur = [_st get "cur"] call _rowOf;
                if (_cur isNotEqualTo []) then {
                    _st set ["sel", ((_st get "rows") select { (_x select 11) isEqualTo (_cur select 11) }) apply { _x select 0 }];
                    (_c get "scope") lbSetCurSel 1;
                    ["wall"] call _fn;
                    ["fill"] call _fn;
                    ["status"] call _fn;
                };
                true
            };
            case (_dik isEqualTo 25 && {!_inEdit} && {!_ctrl}): {
                private _cur = [_st get "cur"] call _rowOf;
                if (_cur isNotEqualTo []) then { ["say", ["powerCycle", (_st get "rows") select { (_x select 0) in (_st get "sel") }] call _run] call _fn; _st set ["due", diag_tickTime + 1]; };
                true
            };
            case (_dik isEqualTo 63): { ["refresh"] call _fn; true };
            default { false };
        }
    };
    case "ask": {
        // Question dans la fenêtre : [genre "room" | "screen" | "resetAll" | "scene", cible, texte, libellé du bouton, saisie].
        _a params ["_kind", ["_id", ""], ["_txt", ""], ["_ok", "CONFIRMER"], ["_edit", ""]];
        // Case cochée avant d'ouvrir la question : son événement, s'il part, ne bascule rien.
        (_c get "cfChk") cbSetChecked true;
        (["confirm", [_st get "confirm", format ["ask:%1:%2", _kind, _id]]] call _dat) params ["_state"];
        _st set ["confirm", _state];
        (_c get "cfText") ctrlSetStructuredText parseText format ["<t size='1.05' color='%1'>%2</t>", "text" call _hex, _txt call _esc];
        (_c get "cfOk") ctrlSetText (_ok call _tr);
        (_c get "cfEdit") ctrlShow (_kind isEqualTo "scene");
        (_c get "cfEdit") ctrlSetText _edit; // i18n:brut (nom saisi)
        { (_c get _x) ctrlShow (_kind isEqualTo "room"); } forEach ["cfChk", "cfChkLbl"];
        (_c get "cfChkLbl") ctrlSetTextColor (["text"] call _col);
        (_c get "cfChkLbl") ctrlSetText (("Garder les écrans et les postes en place (la salle seule est rayée)") call _tr);
        // Le reste de la fenêtre est désactivé tant que la boîte est ouverte ; l'état d'avant est rendu à la fermeture.
        private _held = [];
        {
            if (ctrlEnabled _x && {!(_x in [_c get "cfBox", _c get "cfVeil", _c get "cfOk", _c get "cfNo", _c get "cfEdit", _c get "cfChk", _c get "cfText", _c get "cfChkLbl"])} && {(ctrlParentControlsGroup _x) isNotEqualTo (_c get "cfBox")}) then { _x ctrlEnable false; _held pushBack _x; };
        } forEach (allControls (_st get "d"));
        _st set ["held", _held];
        (_c get "cfVeil") ctrlShow true;
        (_c get "cfBox") ctrlShow true;
        ctrlSetFocus ([_c get "cfNo", _c get "cfEdit"] select (_kind isEqualTo "scene"));
        true
    };
    case "confirm": {
        (["confirm", [_st get "confirm", _a]] call _dat) params ["_state", "_act"];
        _st set ["confirm", _state];
        if (_a isEqualTo "toggle") exitWith {
            (_c get "cfChkLbl") ctrlSetText (([("Supprimer aussi les écrans, les postes et le mobilier de la salle") call _tr, ("Garder les écrans et les postes en place (la salle seule est rayée)") call _tr] select (_state select 2)));
            (_c get "cfChkLbl") ctrlSetTextColor ([["red", "text"] select (_state select 2)] call _col);
            true
        };
        { _x ctrlEnable true; } forEach (_st getOrDefault ["held", []]);
        _st set ["held", []];
        (_c get "cfVeil") ctrlShow false;
        (_c get "cfBox") ctrlShow false;
        ["status"] call _fn;
        if (_act isEqualTo "") exitWith { ["say", [("Annulé : rien n'a été changé") call _tr, "text2"]] call _fn; false };
        private _res = ["confirmed", [_act, ctrlText (_c get "cfEdit")]] call _run;
        ["say", _res select [0, 2]] call _fn;
        _st set ["due", diag_tickTime + (_res param [2, 1.5])];
        true
    };
    case "act": {
        // Boutons : ceux de la fenêtre ici, les autres dans fn_tocManagerUiApply (lecture et écriture du jeu).
        switch (true) do {
            case (_a isEqualTo "close"): { (_st get "d") closeDisplay 2; };
            case (_a isEqualTo "rooms"): { ["view", parseNumber ((_st get "view") isNotEqualTo 1)] call _fn; };
            case (_a isEqualTo "log"): { ["view", [2, 0] select ((_st get "view") isEqualTo 2)] call _fn; };
            case (_a isEqualTo "apply"): { ["apply"] call _fn; };
            case (_a isEqualTo "cancel"): { ["cancel"] call _fn; };
            case ((_a select [0, 3]) isEqualTo "msg"): { ["msgAct", _a] call _fn; };
            case (_a isEqualTo "copy"): {
                private _cur = [_st get "cur"] call _rowOf;
                if (_cur isEqualTo []) exitWith { ["say", [("Choisissez d'abord la dalle à copier") call _tr, "amber"]] call _fn; };
                _st set ["ready", ["copy", _cur select 6, _cur select 8]];
                ["status"] call _fn;
            };
            case (_a isEqualTo "classic"): {
                (_st get "d") closeDisplay 2;
                [] spawn { uiSleep 0.15; ["openTable"] call comspec_atak_native_fnc_tocManager; };
            };
            default {
                private _res = ["act", _a] call _run;
                if (_res isEqualType [] && {(count _res) >= 2}) then {
                    if ((_res select 0) isEqualTo "#ask") then { ["ask", _res select [1]] call _fn; } else {
                        ["say", _res select [0, 2]] call _fn;
                        if ((_res param [2, 0]) > 0) then { _st set ["due", diag_tickTime + (_res select 2)]; };
                    };
                };
            };
        };
        true
    };
    default { false };
};
