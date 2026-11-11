/*
    PC portable du poste TOC (retour de TTRD, 26/10 : « le POSTE TOC doit ouvrir le PC portable, pas le téléphone,
    c'est totalement indépendant ») : l'écran du PC, ouvert par l'action Poste TOC (fn_tocPost « open ») sur un PC de
    TOC ou un écran TOC qui sert de poste (fn_tocScreen). Aucun téléphone n'est nécessaire : tout vient du serveur
    (fn_tocFeed), le PC est sur le réseau filaire du poste (pas de signal, de batterie ni de file d'envoi du téléphone).
    Écran :
      - la vraie carte d'Arma, en grand : marqueurs de la mission et de l'ATAK, nœuds ATAK du camp (joueurs et IA
        équipées, symbole OTAN, en direct / ancien / perdu / hors ligne), comptes rendus et alertes géolocalisés,
        objectif de l'ordre en cours (clic droit sur la carte), poste TOC ;
      - GROUPES : groupes du camp (chef, effectif, nœuds, grille), ordre au groupe, message au chef, et pour un groupe
        IA, ORDRE IA exécuté pour de vrai (aller, patrouiller, attaquer, tenir, embarquer, posture… au point du clic droit,
        fn_aiOrder) ; état de l'ordre sur la carte ;
      - FLUX : comptes rendus, alertes, MEDEVAC, BDA, ordres et messages du camp ;
      - ORDRES : rédacteur d'ordre (type, destinataire, priorité, consigne, objectif), ordres émis et leurs réponses ;
      - CAMP : message à tout le camp ou à un chef de groupe (arrive dans Messages), indicatif du poste ;
      - ORBAT : ORBAT du site (fn_bftOrbat), membres en ligne ou non ; mêmes droits que le téléphone (lu avec le compte du joueur) ;
      - RÉSEAU : état du réseau, météo, contenu des écrans du poste (carte, réseau, ORBAT, diapositive) ;
      - SAT (salle TOC, 11/11) : vue satellite en direct à la place de la carte (fn_tocSat), suivi des alliés du réseau,
        SAT 1 / SAT 2, prise de main (une main par satellite ; toute commande d'un satellite libre la donne), image AUTO
        (jour le jour, vision nocturne la nuit) / jour (éclairci la nuit) / nuit / thermique, déplacement, zoom (boutons ou
        molette sur l'image), glisser pour déplacer, clic sur l'image pour centrer, suivi d'un groupe ou d'une unité ;
        les autres PC et écrans le montrent en lecture seule ;
      - CHRONOS : chronos du poste (chrono, compte à rebours, heure H en ZULU), communs au camp (fn_tocRoom) ;
      - RADIO : radios ACRE2 de l'opérateur (fn_mpu5, lecture seule) : canal, fréquences, voyants RX / TX, alternat ;
        TFAR : radios actives. Pas de bouton d'alternat : aucune API publique d'ACRE2 ni de TFAR ne presse l'alternat ;
      - SALLE : gestion de la salle TOC du poste (fn_tocHallUi, fn_tocHall) : plan du mur, contenu, nom, allumage et
        identification de chaque écran ou dalle, liens, mosaïques, scènes ;
      - MAIN COURANTE : entrées horodatées ZULU (automatiques et à la main), communes au camp ;
      - EXCHECK : liste d'exécution (n°, événement, mot de code, élément, heure prévue, heure réelle), PASSÉ ;
      - PERTES : pertes et résultats par élément (réseau ATAK, BDA, correction à la main) ;
      - DEMANDES : MEDEVAC, appels de feu, ravitaillements reçus, statut et réponse renvoyés au téléphone du demandeur.
    Barre du haut : poste et opérateur (PRENDRE LE POSTE / LIBÉRER LE POSTE), heures ZULU et locale, liaison radio
    (ACRE2 / TFAR, lecture seule), FLASH du poste (rouge sur tous les écrans et PC du camp, 3 minutes).
    Outils de la carte, un à la fois : SÉLECTION (clic gauche : fiche du groupe), MESURE (A puis B), MARQUEUR (fn_markerDrop,
    comme le téléphone : canal, nom et envoi à Athena identiques). Recherche : grille, groupe, indicatif, nom de marqueur.
    Ce que l'opérateur regarde (cadrage de la carte, onglet, sélection) est publié sur son unité
    (COMSPEC_ATAK_TocView, toutes les 2 s au plus quand ça change) : les écrans TOC en affichage « TOC » le reprennent,
    c'est le PIP du TOC sur n'importe quel écran du jeu (fn_tocScreen).
    Mise en page (retour de TTRD, 11/11 : boutons mal placés, textes invisibles ou coupés, barres qui se chevauchent) :
    une seule grille (_grid, fractions de safeZoneH, largeurs × pixelW / pixelH : même rendu à toutes les tailles d'interface
    et tous les formats) et une seule routine qui pose (_place). Chaque zone (barre de titre, onglets, gauche, panneau de
    droite, aide, ligne d'état) est décrite en lignes dans _layout ; un contrôle est montré seulement si la mise en page en
    cours le nomme : rien ne peut être oublié à l'écran. Chaque bouton vient de _btnDefs (libellé long, court, aide) et est
    mesuré à chaque placement (_fitC : police réduite jusqu'au plancher, puis libellé court). Pas d'infobulle sur les
    panneaux denses : l'aide du contrôle survolé s'écrit sur la ligne d'aide, au-dessus de la ligne d'état. « audit » liste
    les défauts de l'onglet affiché (autotest).
    Modes (client) :
      ["open", objet]   ouvre le PC du poste          ["load", display] / ["unload"] (display COMSPEC_RscTocLaptop)
      ["refresh"]       chaque seconde                ["draw", carte] (Draw de la carte)
      ["tab", nom]      "GROUPES" | "ORBAT" | "FLUX" | "ORDRES" | "CAMP" | "RÉSEAU" | "SAT" | "CHRONOS" | "RADIO" | "SALLE"
                        | "MAIN COURANTE" | "EXCHECK" | "PERTES" | "DEMANDES"
      ["layout"]        pose la mise en page en cours (onglet, outil de la carte)     ["audit"] -> défauts de l'onglet affiché
      ["mk", [classe, clé]] contrôle créé pour fn_tocDraw / fn_tocHallUi (même registre)   ["tip", [contrôle, aide, brut]]
      ["label", [clé, long, court, brut]] libellé d'un bouton changé (mesuré de nouveau)     ["press", clé] boutons du PC
      ["satClick", [u, v]] centre le satellite (clic sur l'image)   ["satOverlay"] cadres de suivi (chaque image)
      ["satDown"|"satUp", [x, y]] glisser sur l'image : déplace la vue     ["satWheel", z] molette sur l'image : zoom
      ["tool", nom]     "SELECT" | "MEASURE" | "MARKER"     ["layer", clé] "ai" | "ev"     ["search"]     ["left", [x, y]]
      ["frame", quoi]   "net" (tout le réseau) | "me" | "post" | [x, y]
      ["click", [x, y]] objectif de l'ordre           ["act", clé] boutons des onglets
      ["status", texte] ligne d'état du PC (6 s)
*/
params [["_mode", "refresh"], ["_a", objNull]];
disableSerialization;
private _fn = comspec_atak_native_fnc_tocLaptop;
private _st = uiNamespace getVariable ["COMSPEC_ATAK_TocLap", createHashMap];
uiNamespace setVariable ["COMSPEC_ATAK_TocLap", _st];
private _ui = _st getOrDefault ["ui", createHashMap];
private _draft = uiNamespace getVariable ["COMSPEC_ATAK_TocLapDraft", createHashMap];
uiNamespace setVariable ["COMSPEC_ATAK_TocLapDraft", _draft];
private _side = str side group player;
// Aides du fichier : chaque nom n'est déclaré qu'une fois, casse comprise (le SQF ne distingue pas _h de _H : en 4.4.0,
// « private _h = _H * 0.03 » de fn_tocDraw écrasait la hauteur d'écran et les libellés de la barre de dessin tombaient
// à un demi-pixel). Vérifié par AtakTocLaptopLayoutTest.
private _tr = { _this call comspec_atak_native_fnc_tr };
private _feedOf = { (missionNamespace getVariable [format ["COMSPEC_ATAK_TocFeed_%1", _side], []]) };
private _esc = { params ["_t"]; if !(_t isEqualType "") then { _t = str _t; }; { _t = [_t, _x select 0, _x select 1] call CBA_fnc_replace; } forEach [["&", "&amp;"], ["<", "&lt;"], [">", "&gt;"]]; _t };
private _sideRgb = (createHashMapFromArray [["WEST", [0.28, 0.62, 1]], ["EAST", [0.95, 0.3, 0.25]], ["GUER", [0.36, 0.85, 0.42]], ["CIV", [0.75, 0.5, 0.95]]]) getOrDefault [_side, [0.28, 0.62, 1]];
// Onglets, en rangées : poste, salle TOC (satellite, chronos, radio, diffusion), registres du poste.
private _tabRows = [["GROUPES", "ORBAT", "FLUX", "ORDRES", "CAMP"], ["RÉSEAU", "SAT", "CHRONOS", "RADIO", "SALLE"], ["MAIN COURANTE", "EXCHECK", "PERTES", "DEMANDES"]];
// Grille du PC, seule source des tailles : fractions de la hauteur d'écran (safeZoneH). Largeurs × pixelW / pixelH.
private _grid = createHashMapFromArray [
    ["gap", 0.005], ["pad", 0.013], ["brand", 0.016], ["title", 0.058], ["titleRow", 0.052], ["bar", 0.034], ["tab", 0.034], ["row", 0.034],
    ["tall", 0.04], ["label", 0.022], ["help", 0.05], ["strip", 0.032], ["toolHint", 0.046], ["info2", 0.05], ["info3", 0.075],
    ["flexMin", 0.06], ["font", 0.019], ["fontTab", 0.017], ["fontMin", 0.0145], ["panel", 0.36], ["panelMin", 0.5], ["panelMax", 0.62]
];
// Écrans du camp (toute la salle) et écrans du poste (30 m).
private _sideScreens = { (missionNamespace getVariable ["COMSPEC_ATAK_TocScreens", []]) select { !isNull _x && {alive _x} && {((_x getVariable ["COMSPEC_ATAK_TocScr", []]) param [1, ""]) in ["", _side]} } };
private _nearScreens = { (call _sideScreens) select { (player distance _x) < 30 } };
private _showOn = {
    params ["_view"];
    private _scr = call _nearScreens;
    if ((count _scr) isEqualTo 0) exitWith { ["Aucun écran TOC à moins de 30 m du poste"] call _setStatus; };
    { ["show", [_x, _view]] call comspec_atak_native_fnc_tocScreen; } forEach _scr;
    [format ["%1 écran(s) : %2", count _scr, (createHashMapFromArray [["LOG", "main courante"], ["EXCHECK", "liste d'exécution"], ["KILLS", "pertes et résultats"], ["REQ", "demandes"], ["CLOCK", "horloge et chronos"]]) getOrDefault [_view, _view]]] call _setStatus;
};
private _allTabs = [];
{ _allTabs append _x; } forEach _tabRows;
private _kinds = [["DÉPLACER", "MOVE"], ["TENIR", "HOLD"], ["RECO", "RECON"], ["APPUI AÉRIEN", "CAS"], ["RENFORT", "QRF"], ["FRAGO", "FRAGO"], ["DEM-SSE", "DEMSSE"]];
// Groupes du camp : ceux qui ont des joueurs, ceux dont un membre est un nœud ATAK (IA équipées) et les groupes IA (ordres IA).
private _groups = {
    params [["_nodes", []]];
    private _names = _nodes apply { _x param [7, ""] };
    private _out = [];
    {
        private _g = _x;
        if ((str side _g) isEqualTo _side && {({alive _x} count (units _g)) > 0} && {({isPlayer _x} count (units _g)) > 0 || {(groupId _g) in _names} || {!unitIsUAV (vehicle leader _g)}}) then {
            private _gn = groupId _g;
            private _mine = _nodes select { (_x param [7, ""]) isEqualTo _gn };
            private _n = 0; private _best = 9; private _p = [];
            { _n = _n + (_x param [8, 1]); _best = _best min (_x select 4); if (_p isEqualTo []) then { _p = _x select 2; }; } forEach _mine;
            private _ld = leader _g;
            _out pushBack [_gn, [_ld] call comspec_atak_native_fnc_unitGroup, _ld, {isPlayer _x && {alive _x}} count (units _g), {alive _x} count (units _g), _n, _best, _p];
        };
    } forEach allGroups;
    _out sort true;
    _out
};
private _setStatus = { params ["_msg"]; _st set ["status", [_msg, diag_tickTime + 6]]; };
// Libellé d'un bouton qui tient dans sa largeur : police de la grille réduite jusqu'au plancher, puis libellé court ;
// le libellé long et l'aide restent sur la ligne d'aide au survol. Brut : libellé déjà traduit (compteurs des onglets).
private _fitC = {
    params ["_c"];
    (_c getVariable ["fit", ["", "", ""]]) params ["_long", ["_short", ""]];
    private _raw = _c getVariable ["raw", false];
    private _wMax = ((ctrlPosition _c) select 2) * 0.92;
    private _f0 = (_grid get (["font", "fontTab"] select (_c getVariable ["tabBtn", false]))) * safeZoneH;
    private _try = {
        params ["_lbl"];
        private _f = _f0;
        _c ctrlSetText ([_lbl call _tr, _lbl] select _raw);
        _c ctrlSetFontHeight _f;
        while { (ctrlTextWidth _c) > _wMax && {_f > (_grid get "fontMin") * safeZoneH} } do { _f = _f - 0.0005 * safeZoneH; _c ctrlSetFontHeight _f; };
        (ctrlTextWidth _c) <= _wMax
    };
    private _ok = [_long] call _try;
    _c setVariable ["short", false];
    if (!_ok && {_short isNotEqualTo ""}) then { [_short] call _try; _c setVariable ["short", true]; };
};
private _relabel = {
    params ["_key", "_long", ["_short", ""], ["_raw", false]];
    private _c = _ui getOrDefault [_key, controlNull];
    if (isNull _c) exitWith {};
    private _f = _c getVariable ["fit", ["", "", ""]];
    if ((_f select 0) isEqualTo _long && {(_f select 1) isEqualTo _short} && {(_c getVariable ["raw", false]) isEqualTo _raw}) exitWith {};
    _c setVariable ["fit", [_long, _short, _f param [2, ""]]];
    _c setVariable ["raw", _raw];
    [_c] call _fitC;
};
// Aide d'un contrôle : écrite sur la ligne d'aide au survol (une infobulle d'Arma s'ouvre sous la souris et couvrait
// le contrôle voisin, ex. ZOOM + sur « SUIVRE UN GROUPE »).
private _tipOf = {
    params ["_c", "_t", ["_raw", false]];
    _c setVariable ["tip", _t];
    _c setVariable ["tipRaw", _raw];
    if (_c getVariable ["tipEh", false]) exitWith {};
    _c setVariable ["tipEh", true];
    _c ctrlAddEventHandler ["MouseEnter", { ["hover", [_this select 0, true]] call comspec_atak_native_fnc_tocLaptop; }];
    _c ctrlAddEventHandler ["MouseExit", { ["hover", [_this select 0, false]] call comspec_atak_native_fnc_tocLaptop; }];
};
// Pose des lignes d'une zone [x, y, l, h] : ligne = [hauteur, colonnes, cellules]. Hauteur : clé de la grille (fixe) ou
// nombre (part de la place restante). Colonne : poids > 0 (part de la largeur restante) ou < 0 (largeur fixe en hauteurs
// d'écran). Cellule : clé, ou [clé, colonnes couvertes, clé de hauteur (centrée dans la ligne)] ; "" laisse la colonne vide.
// Une marge entre deux colonnes et entre deux lignes : rien ne se chevauche.
private _place = {
    params ["_rows", "_rx", "_ry", "_rw", "_rh"];
    private _u = safeZoneH;
    private _ux = safeZoneH * pixelW / pixelH;
    private _gy = (_grid get "gap") * _u;
    private _gx = (_grid get "gap") * _ux;
    private _fixed = 0;
    private _flex = 0;
    { private _hk = _x select 0; if (_hk isEqualType "") then { _fixed = _fixed + (_grid get _hk) * _u; } else { _flex = _flex + _hk; }; } forEach _rows;
    private _fu = ((_rh - _fixed - (((count _rows) - 1) max 0) * _gy) max 0) / (_flex max 0.0001);
    private _cy = _ry;
    {
        _x params ["_hk", "_wts", "_cells"];
        private _hRow = if (_hk isEqualType "") then { (_grid get _hk) * _u } else { _fu * _hk };
        private _fw = 0;
        private _share = 0;
        { if (_x < 0) then { _fw = _fw - _x * _ux; } else { _share = _share + _x; }; } forEach _wts;
        private _free = (_rw - _fw - ((count _wts) - 1) * _gx) max 0;
        private _xs = [];
        private _ws = [];
        private _cx = _rx;
        { private _cw = [_free * _x / (_share max 0.0001), -_x * _ux] select (_x < 0); _xs pushBack _cx; _ws pushBack _cw; _cx = _cx + _cw + _gx; } forEach _wts;
        private _col = 0;
        {
            private _cell = [_x, [_x, 1, ""]] select (_x isEqualType "");
            _cell params ["_key", ["_span", 1], ["_hc", ""]];
            private _last = (_col + _span - 1) min ((count _wts) - 1);
            private _c = _ui getOrDefault [_key, controlNull];
            if (!isNull _c) then {
                private _ch = if (_hc isEqualTo "") then { _hRow } else { (_grid get _hc) * _u };
                _c ctrlSetPosition [_xs select _col, _cy + (_hRow - _ch) / 2, (_xs select _last) + (_ws select _last) - (_xs select _col), _ch];
                _c ctrlCommit 0;
                if ((_c getVariable ["fit", []]) isNotEqualTo []) then { [_c] call _fitC; };
            };
            _col = _last + 1;
        } forEach _cells;
        _cy = _cy + _hRow + _gy;
    } forEach _rows;
};

switch (_mode) do {
    case "open": {
        if (!hasInterface || {isNull player} || {!alive player}) exitWith { false };
        if (!isNull (uiNamespace getVariable ["COMSPEC_ATAK_TocLapDisp", displayNull])) exitWith { true };
        _st set ["obj", _a];
        // Le serveur publie le réseau du camp dès qu'un PC est ouvert (fn_tocFeed) ; publié tout de suite.
        player setVariable ["COMSPEC_ATAK_TocView", [_side, [round ((getPosASL _a) select 0), round ((getPosASL _a) select 1)], 1500, "GROUPES", "", ["callsign"] call comspec_atak_native_fnc_tocPost, name player, ""], true];
        createDialog "COMSPEC_RscTocLaptop";
        ["pipOn"] call _fn;
        true
    };
    case "pipOn": {
        // Le PC du poste en PIP sur les écrans TOC du poste (20 m, même camp) : tous les joueurs autour voient la vue de
        // l'opérateur (carte, onglet, sélection) sans se mettre derrière lui. Seul l'état de vue circule
        // (COMSPEC_ATAK_TocView sur l'opérateur, affichage des écrans tenu par le serveur) : JIP compris.
        private _o = _st getOrDefault ["obj", objNull];
        if (isNull _o) exitWith { [] };
        private _done = [];
        {
            private _c = _x getVariable ["COMSPEC_ATAK_TocScr", []];
            private _v = _c param [3, ""];
            // Caméra en direct ou diapositives en cours : l'écran garde ce qu'il montre.
            if ((count _c) >= 8 && {alive _x} && {(_x distance _o) < 20} && {(_c select 1) in ["", _side]} && {_v in ["SPLIT", "MAP", "FEED"]}
                && {(count (_x getVariable ["COMSPEC_ATAK_ScreenCast", []])) < 5}) then {
                _done pushBack [_x, _v];
                ["view", [_x, "mode", "TOC"]] call comspec_atak_native_fnc_tocScreen;
                _x setVariable ["COMSPEC_ATAK_TocAt", -10];
            };
        } forEach ((missionNamespace getVariable ["COMSPEC_ATAK_TocScreens", []]) select { !isNull _x });
        _st set ["pip", _done];
        _done
    };
    case "pipOff": {
        {
            _x params ["_o", "_v"];
            if (!isNull _o && {((_o getVariable ["COMSPEC_ATAK_TocScr", []]) param [3, ""]) isEqualTo "TOC"}) then { ["view", [_o, "mode", _v]] call comspec_atak_native_fnc_tocScreen; };
        } forEach (_st getOrDefault ["pip", []]);
        _st set ["pip", []];
    };
    case "load": {
        private _d = _a;
        uiNamespace setVariable ["COMSPEC_ATAK_TocLapDisp", _d];
        _ui = createHashMap;
        _st set ["ui", _ui];
        _st set ["keys", []];
        _st set ["laid", false];
        _st set ["toolSet", false];
        _st set ["hoverC", controlNull];
        _st set ["satDrag", []];
        _st set ["tab", _st getOrDefault ["tab", "GROUPES"]];
        _st set ["sig", ""];
        private _u = safeZoneH;
        private _ux = safeZoneH * pixelW / pixelH;
        private _keys = _st get "keys";
        // Création : chaque contrôle a sa clé dans le registre ; la grille le pose ensuite (« layout »). Fond (« bg ») :
        // ignoré par l'audit des chevauchements.
        private _mk = {
            params ["_cls", "_key", ["_bg", false]];
            private _c = _d ctrlCreate [_cls, -1];
            _ui set [_key, _c];
            _keys pushBack _key;
            if (_bg) then { _c setVariable ["bg", true]; };
            _c
        };
        private _stx = { params ["_key"]; ["COMSPEC_RscStructuredText", _key] call _mk };
        private _lblOf = {
            params ["_key", "_txt"];
            private _c = ["COMSPEC_RscLabel", _key] call _mk;
            _c ctrlSetText (_txt call _tr);
            _c
        };
        private _combo = {
            params ["_key", ["_opts", []], ["_tip", ""]];
            private _c = ["COMSPEC_RscCombo", _key] call _mk;
            { private _i = _c lbAdd ((_x select 0) call _tr); _c lbSetData [_i, _x select 1]; } forEach _opts;
            if (_tip isNotEqualTo "") then { [_c, _tip] call _tipOf; };
            _c
        };
        private _edit = {
            params ["_key", ["_tip", ""], ["_cls", "COMSPEC_RscEdit"]];
            private _c = [_cls, _key] call _mk;
            if (_tip isNotEqualTo "") then { [_c, _tip] call _tipOf; };
            _c
        };
        // Coque du PC : bord sombre, écran, marque en bas ; zones posées une fois (rectangles gardés pour la grille).
        private _sw = safeZoneW;
        private _x0 = safeZoneX + _sw * 0.025; private _y0 = safeZoneY + _u * 0.022; private _w0 = _sw * 0.95; private _h0 = _u * 0.956;
        private _pdy = (_grid get "pad") * _u; private _pdx = (_grid get "pad") * _ux;
        private _gy = (_grid get "gap") * _u; private _gx = (_grid get "gap") * _ux;
        private _shell = ["COMSPEC_RscText", "shellOut", true] call _mk;
        _shell ctrlSetPosition [_x0, _y0, _w0, _h0];
        _shell ctrlSetBackgroundColor [0.085, 0.09, 0.095, 1];
        private _rim = ["COMSPEC_RscText", "shellIn", true] call _mk;
        _rim ctrlSetPosition [_x0 + _pdx * 0.4, _y0 + _pdy * 0.4, _w0 - _pdx * 0.8, _h0 - _pdy * 0.8];
        _rim ctrlSetBackgroundColor [0.12, 0.125, 0.13, 1];
        private _xi = _x0 + _pdx; private _yi = _y0 + _pdy; private _wi = _w0 - 2 * _pdx; private _hi = _h0 - 2 * _pdy - (_grid get "brand") * _u;
        private _scrn = ["COMSPEC_RscText", "screen", true] call _mk;
        _scrn ctrlSetPosition [_xi, _yi, _wi, _hi];
        _scrn ctrlSetBackgroundColor [0.025, 0.03, 0.027, 1];
        _st set ["inner", [_xi, _yi, _wi, _hi]];
        private _brand = ["COMSPEC_RscTextCenter", "brand", true] call _mk;
        _brand ctrlSetPosition [_x0, _yi + _hi + 0.001 * _u, _w0, (_grid get "brand") * _u];
        _brand ctrlSetText (("COMSPEC · POSTE DE COMMANDEMENT") call _tr);
        _brand ctrlSetFontHeight ((_grid get "fontMin") * _u);
        _brand ctrlSetTextColor [0.5, 0.53, 0.55, 1];
        // Barre de titre et ligne d'état.
        private _titleH = (_grid get "title") * _u;
        private _tBg = ["COMSPEC_RscText", "titleBg", true] call _mk;
        _tBg ctrlSetPosition [_xi, _yi, _wi, _titleH];
        _tBg ctrlSetBackgroundColor [0.06, 0.08, 0.07, 1];
        private _tLine = ["COMSPEC_RscText", "titleLine", true] call _mk;
        _tLine ctrlSetPosition [_xi, _yi + _titleH - 0.003 * _u, _wi, 0.003 * _u];
        _tLine ctrlSetBackgroundColor (_sideRgb + [1]);
        _st set ["rTitle", [_xi + 2 * _gx, _yi + 0.001 * _u, _wi - 4 * _gx, _titleH - 0.005 * _u]];
        private _barH = (_grid get "bar") * _u;
        _st set ["rBar", [_xi, _yi + _hi - _barH, _wi, _barH]];
        // Corps : gauche (carte, satellite ou plan de la salle), panneau de droite (onglets, contenu, aide).
        private _by = _yi + _titleH + _gy;
        private _bh = _hi - _titleH - _barH - 2 * _gy;
        private _pw = ((((_wi / _ux) * (_grid get "panel")) max (_grid get "panelMin")) min (_grid get "panelMax")) * _ux;
        private _px = _xi + _wi - _pw;
        _st set ["rLeft", [_xi + _gx, _by, (_px - _gx) - (_xi + _gx), _bh]];
        private _pBg = ["COMSPEC_RscText", "rBg", true] call _mk;
        _pBg ctrlSetPosition [_px, _by, _pw, _bh];
        _pBg ctrlSetBackgroundColor [0.045, 0.055, 0.048, 1];
        private _cx = _px + _gx; private _cw = _pw - 2 * _gx;
        private _tabsH = 3 * (_grid get "tab") * _u + 2 * _gy;
        _st set ["rTabs", [_cx, _by + _gy, _cw, _tabsH]];
        private _helpH = (_grid get "help") * _u;
        _st set ["rHelp", [_cx, _by + _bh - _gy - _helpH, _cw, _helpH]];
        private _cy = _by + _gy + _tabsH + 2 * _gy;
        _st set ["rRight", [_cx, _cy, _cw, (_by + _bh - _gy - _helpH - 2 * _gy) - _cy]];
        { (_ui get _x) ctrlCommit 0; } forEach ["shellOut", "shellIn", "screen", "brand", "titleBg", "titleLine", "rBg"];
        // Fond noir de l'image satellite (sous l'image, l'image garde le format 16:9 des écrans).
        (["COMSPEC_RscText", "sBack", true] call _mk) ctrlSetBackgroundColor [0, 0, 0, 1];
        // Barre de titre : poste et opérateur, heures et liaison, FLASH, prise du poste.
        ["title"] call _stx;
        ["clock"] call _stx;
        ["flashTxt", "Texte du FLASH : affiché en rouge sur tous les écrans et PC du poste pendant 3 minutes, et inscrit à la main courante. Exemple : CONTACT NORD, TOUS AUX ABRIS"] call _edit;
        // Carte.
        private _map = ["COMSPEC_RscMapToc", "map"] call _mk;
        _map ctrlAddEventHandler ["Draw", { private _t0 = diag_tickTime; ["draw", _this select 0] call comspec_atak_native_fnc_tocLaptop; ["carte PC du poste (image)", _t0] call comspec_atak_native_fnc_perfAcc; }];
        _map ctrlAddEventHandler ["MouseButtonClick", {
            params ["_m", "_b", "_mx", "_my"];
            if (_b isEqualTo 1) then { ["click", _m ctrlMapScreenToWorld [_mx, _my]] call comspec_atak_native_fnc_tocLaptop; };
            if (_b isEqualTo 0) then { ["left", _m ctrlMapScreenToWorld [_mx, _my]] call comspec_atak_native_fnc_tocLaptop; };
        }];
        // Dessin à la souris (TRAIT, DESSIN) : mêmes appels que la carte du téléphone (fn_tocDraw, fn_markerStroke).
        _map ctrlAddEventHandler ["MouseButtonDown", { params ["_m", "_b", "_mx", "_my", "", "_ctrl"]; if (_b isEqualTo 0) then { ["down", [_m ctrlMapScreenToWorld [_mx, _my], _ctrl]] call comspec_atak_native_fnc_tocDraw } else { false } }];
        _map ctrlAddEventHandler ["MouseButtonUp", { params ["_m", "_b", "_mx", "_my"]; if (_b isEqualTo 0) then { ["up", _m ctrlMapScreenToWorld [_mx, _my]] call comspec_atak_native_fnc_tocDraw } else { false } }];
        _map ctrlAddEventHandler ["MouseMoving", { params ["_m", "_mx", "_my"]; ["move", _m ctrlMapScreenToWorld [_mx, _my]] call comspec_atak_native_fnc_tocDraw; }];
        ["search", "Grille, groupe, indicatif ou nom de marqueur"] call _edit;
        // Barre de dessin sous la carte (réglages de l'outil choisi) et consigne de l'outil.
        (["dInfo"] call _stx) ctrlSetStructuredText parseText format ["<t size='0.8' color='#8a9a93'>%1</t>", ("Réglages de l'outil : choisissez TRAIT, DESSIN, PLAN ou MARQUEUR.") call _tr];
        ["toolHint"] call _stx;
        ["build"] call comspec_atak_native_fnc_tocDraw;
        // GROUPES
        private _gl = ["COMSPEC_RscListBoxDense", "gList"] call _mk;
        _gl ctrlAddEventHandler ["LBSelChanged", { params ["_c", "_i"]; ["pick", ["GROUPES", _c lbData _i]] call comspec_atak_native_fnc_tocLaptop; }];
        ["gInfo"] call _stx;
        // Ordre réel à un groupe IA (fn_aiOrder), au point choisi d'un clic droit sur la carte.
        private _ga = ["gAi", (((["kinds"] call comspec_atak_native_fnc_aiOrder) select { (_x select 0) isNotEqualTo "FOLLOW" }) apply { [_x select 1, _x select 0] }), "Ordre exécuté par le groupe IA choisi, au point du clic droit sur la carte"] call _combo;
        // ORBAT du site
        private _bl = ["COMSPEC_RscListBoxDense", "bList"] call _mk;
        _bl ctrlAddEventHandler ["LBSelChanged", { params ["_c", "_i"]; ["pick", ["ORBAT", _c lbData _i]] call comspec_atak_native_fnc_tocLaptop; }];
        ["bInfo"] call _stx;
        // RÉSEAU : état, météo, contenu des écrans
        ["rInfo"] call _stx;
        ["rL1", "CONTENU DES ÉCRANS DU POSTE"] call _lblOf;
        private _rs = ["rShow", [["Carte et flux", "SPLIT"], ["Carte seule", "MAP"], ["Flux seul", "FEED"], ["Réseau", "NET"], ["ORBAT du site", "ORBAT"], ["PIP du poste", "TOC"], ["Vue satellite", "SAT"], ["Horloge et chronos", "CLOCK"], ["Diapositive du Briefing", "SLIDES"]], "Contenu montré par les écrans TOC à moins de 30 m du poste"] call _combo;
        _rs lbSetCurSel 0;
        // FLUX
        private _ff = ["fFilter", [["Tout le flux", ""], ["Alertes (PANIQUE, TIC, MEDEVAC…)", "A"], ["Comptes rendus", "R"], ["Ordres et messages", "M"]]] call _combo;
        _ff lbSetCurSel 0;
        _ff ctrlAddEventHandler ["LBSelChanged", { params ["_c", "_i"]; (uiNamespace getVariable ["COMSPEC_ATAK_TocLap", createHashMap]) set ["fKind", _c lbData _i]; (uiNamespace getVariable ["COMSPEC_ATAK_TocLap", createHashMap]) set ["sig", ""]; }];
        private _fl = ["COMSPEC_RscListBoxDense", "fList"] call _mk;
        _fl ctrlAddEventHandler ["LBSelChanged", { params ["_c", "_i"]; ["pick", ["FLUX", _c lbData _i]] call comspec_atak_native_fnc_tocLaptop; }];
        ["fInfo"] call _stx;
        // ORDRES
        ["oL1", "TYPE"] call _lblOf;
        ["oL2", "PRIORITÉ"] call _lblOf;
        private _ok = ["oKind", _kinds] call _combo;
        private _op = ["oPrio", ["ROUTINE", "IMPORTANT", "URGENT", "FLASH"] apply { [_x, _x] }] call _combo;
        ["oL3", "DESTINATAIRE"] call _lblOf;
        ["oTarget"] call _combo;
        ["oL4", "CONSIGNE"] call _lblOf;
        ["oText", "Consigne de l'ordre, 400 signes au plus. Exemple : tenir le carrefour jusqu'à la relève", "COMSPEC_RscEditMulti"] call _edit;
        ["oL5", "OBJECTIF (CLIC DROIT SUR LA CARTE OU GRILLE)"] call _lblOf;
        ["oGrid", "Grille de l'objectif : clic droit sur la carte, ou tapez-la"] call _edit;
        ["oInfo"] call _stx;
        // CAMP
        ["cL1", "DESTINATAIRE"] call _lblOf;
        ["cDest"] call _combo;
        private _cp = ["cPrio", ["ROUTINE", "URGENT", "FLASH"] apply { [_x, _x] }] call _combo;
        ["cL2", "MESSAGE"] call _lblOf;
        ["cText", "Message, 400 signes au plus : il arrive dans Messages, conversation TOC du poste", "COMSPEC_RscEditMulti"] call _edit;
        ["cL3", "INDICATIF DU POSTE (CONFIG.JSON, TOC.CALLSIGN)"] call _lblOf;
        private _cs = ["cCs", "Indicatif du poste, en capitales (24 signes au plus)"] call _edit;
        _cs ctrlSetText (["callsign"] call comspec_atak_native_fnc_tocPost); // i18n:brut (saisi, réenregistré tel quel)
        // Caméra en direct sur les écrans TOC à côté du poste (fn_tocCam).
        ["cL4", "CAMÉRA SUR LES ÉCRANS DU POSTE"] call _lblOf;
        ["cCam", [], "Les écrans TOC à moins de 30 m montrent cette caméra en direct ; « Carte et flux » les remet à l'affichage du poste"] call _combo;
        ["cInfo"] call _stx;
        // SAT : image satellite à la place de la carte (16:9, cadres de suivi par-dessus), bandeau d'état au-dessus,
        // légende dessous ; commandes à droite.
        ["sStrip"] call _stx;
        (_ui get "sStrip") ctrlSetBackgroundColor [0, 0, 0, 0.7];
        ["RscPicture", "sPic"] call _mk;
        ["sLegend"] call _stx;
        ["sInfo"] call _stx;
        ["sL1", "SUIVRE UN GROUPE OU UNE UNITÉ DU RÉSEAU"] call _lblOf;
        ["sFollow", [], "Le satellite suit le groupe (centre de ses nœuds ATAK) ou l'unité choisie, à la position que le réseau connaît"] call _combo;
        // CHRONOS : chronos du poste (fn_tocRoom), affichés aussi sur les écrans en affichage horloge.
        private _tl = ["COMSPEC_RscListBoxDense", "tList"] call _mk;
        _tl ctrlAddEventHandler ["LBSelChanged", { params ["_c", "_i"]; if (_i >= 0) then { (uiNamespace getVariable ["COMSPEC_ATAK_TocLap", createHashMap]) set ["selT", _c lbData _i]; }; }];
        ["tL1", "NOUVEAU CHRONO"] call _lblOf;
        ["tName", "Nom affiché sur le tableau, en capitales. Exemple : H-HOUR, ALPHA EN POSITION, RELÈVE"] call _edit;
        (["tKind", [["Chrono (temps écoulé)", "UP"], ["Compte à rebours", "DOWN"], ["Heure H (ZULU)", "AT"]]] call _combo) lbSetCurSel 0;
        ["tVal", "Compte à rebours : minutes:secondes ou heures:minutes:secondes (exemple 30:00). Heure H : heure ZULU HH:MM (exemple 22:30). Chrono : rien à saisir"] call _edit;
        ["tInfo"] call _stx;
        // RADIO : réseaux radio de l'opérateur (ACRE2 / TFAR, lecture seule), un bloc par radio.
        ["kInfo"] call _stx;
        // MAIN COURANTE : entrées du poste (automatiques et à la main), heure ZULU.
        private _ll = ["COMSPEC_RscListBoxDense", "lList"] call _mk;
        _ll ctrlAddEventHandler ["LBSelChanged", { params ["_c", "_i"]; if (_i >= 0) then { (uiNamespace getVariable ["COMSPEC_ATAK_TocLap", createHashMap]) set ["selL", _c lbData _i]; }; }];
        ["lText", "Entrée à la main, inscrite à l'heure ZULU avec votre nom. Exemple : BRAVO signale le pont nord détruit"] call _edit;
        ["lInfo"] call _stx;
        // EXCHECK : liste d'exécution (n°, événement, mot de code, élément, heure prévue, heure réelle).
        private _xl = ["COMSPEC_RscListBoxDense", "xList"] call _mk;
        _xl ctrlAddEventHandler ["LBSelChanged", { params ["_c", "_i"]; if (_i >= 0) then { (uiNamespace getVariable ["COMSPEC_ATAK_TocLap", createHashMap]) set ["selX", _c lbData _i]; }; }];
        ["xL1", "NOUVELLE LIGNE"] call _lblOf;
        ["xEv", "Événement. Exemple : Franchissement de la ligne de départ"] call _edit;
        ["xCw", "Mot de code. Exemple : ROMEO"] call _edit;
        ["xEl", "Élément. Exemple : ALPHA"] call _edit;
        ["xAt", "Heure prévue (ZULU ou H+). Exemple : 22:30"] call _edit;
        ["xInfo"] call _stx;
        // PERTES : tableau par élément (connu du réseau ou déclaré par compte rendu), correction à la main.
        ["kcInfo"] call _stx;
        ["kcL1", "COMPTE RENDU À LA MAIN"] call _lblOf;
        ["kcEl", [], "Élément (groupe) dont le compte change"] call _combo;
        (["kcKind", [["Ami blessé", "wia"], ["Ami tué", "kia"], ["Ennemi neutralisé", "ekia"], ["Ennemi blessé ou capturé", "ewia"]]] call _combo) lbSetCurSel 0;
        // DEMANDES : appuis, MEDEVAC, ravitaillements reçus des éléments, statut et réponse renvoyée au téléphone.
        private _ql = ["COMSPEC_RscListBoxDense", "qList"] call _mk;
        _ql ctrlAddEventHandler ["LBSelChanged", { params ["_c", "_i"]; if (_i >= 0) then { (uiNamespace getVariable ["COMSPEC_ATAK_TocLap", createHashMap]) set ["selQ", _c lbData _i]; }; }];
        ["qInfo"] call _stx;
        ["qAns", "Réponse envoyée au téléphone du demandeur avec le statut. Exemple : hélico décolle, ETA 8 min, fumée verte"] call _edit;
        // SALLE : plan du mur à la place de la carte, gestion de la salle à droite (fn_tocHallUi).
        ["build"] call comspec_atak_native_fnc_tocHallUi;
        // Boutons du PC : une seule table (libellé long, libellé court, aide, principal). Cible : L ce PC (« press »),
        // T onglet, D barre de dessin (fn_tocDraw), H salle (fn_tocHallUi). Libellés jamais vides (AtakTocLaptopLayoutTest).
        private _btnDefs = [
            ["flashGo", "L", "FLASH", "", "Diffuse le FLASH sur tous les écrans et PC du poste du camp", 1],
            ["postGo", "L", "PRENDRE LE POSTE", "POSTE", "Inscrit votre nom au poste (barre du haut des PC, main courante) ; un second clic libère le poste", 0],
            ["close", "L", "FERMER", "", "Ferme le PC (Échap)", 0],
            ["tabGROUPES", "T", "GROUPES", "", "Groupes du camp, ordres au groupe et ordres IA", 0],
            ["tabORBAT", "T", "ORBAT", "", "ORBAT du site, membres en jeu ou non", 0],
            ["tabFLUX", "T", "FLUX", "", "Comptes rendus, alertes, ordres et messages du camp", 0],
            ["tabORDRES", "T", "ORDRES", "", "Rédiger un ordre ; ordres émis et réponses", 0],
            ["tabCAMP", "T", "CAMP", "", "Message au camp ou à un chef, indicatif du poste, caméra sur les écrans", 0],
            ["tabRÉSEAU", "T", "RÉSEAU", "", "État du réseau, météo, contenu des écrans du poste", 0],
            ["tabSAT", "T", "SAT", "", "Vue satellite en direct : SAT 1 et SAT 2", 0],
            ["tabCHRONOS", "T", "CHRONOS", "", "Chronos, comptes à rebours et heure H du poste", 0],
            ["tabRADIO", "T", "RADIO", "", "Radios de l'opérateur, en lecture seule", 0],
            ["tabSALLE", "T", "SALLE", "", "Écrans de la salle TOC : contenu, allumage, scènes", 0],
            ["tabMAIN COURANTE", "T", "MAIN COURANTE", "MAIN COUR.", "Main courante du poste, en ZULU", 0],
            ["tabEXCHECK", "T", "EXCHECK", "", "Liste d'exécution (mots de code)", 0],
            ["tabPERTES", "T", "PERTES", "", "Pertes et résultats par élément", 0],
            ["tabDEMANDES", "T", "DEMANDES", "", "MEDEVAC, appels de feu et ravitaillements reçus", 0],
            ["framenet", "L", "RÉSEAU", "", "Cadre tout le réseau du camp", 0],
            ["frameme", "L", "POSTE", "", "Centre la carte sur le poste", 0],
            ["framesel", "L", "SÉLECTION", "SÉLECT.", "Centre la carte sur le groupe ou le compte rendu choisi", 0],
            ["toolMEASURE", "L", "MESURE", "", "Clic gauche sur A puis sur B", 0],
            ["toolMARKER", "L", "MARQUEUR", "MARQ.", "Clic gauche : pose un marqueur, comme le téléphone", 0],
            ["layerai", "L", "IA", "", "Afficher ou masquer les nœuds IA", 0],
            ["layerev", "L", "ALERTES", "", "Afficher ou masquer les comptes rendus et alertes", 0],
            ["searchGo", "L", "CHERCHER", "", "Cherche une grille, un groupe, un indicatif ou un nom de marqueur", 0],
            ["toolLINE", "D", "TRAIT", "", "Clic A, Ctrl+clic pour chaque étape, clic B : trait partagé, comme l'outil TRAIT de l'ATAK", 0],
            ["toolDRAW", "D", "DESSIN", "", "Clic gauche maintenu : dessin libre partagé, comme l'ATAK", 0],
            ["toolPLAN", "D", "PLAN", "", "Graphismes de l'app Plan (zones, lignes de phase, axes…) : clics, puis clic droit ou TERMINER", 0],
            ["toolERASE", "D", "EFFACER", "", "Clic sur un marqueur, un trait ou un élément du plan : supprimé pour tous", 0],
            ["dUndo", "D", "ANNULER", "", "Efface mon dernier trait (TRAIT, DESSIN) ou mon dernier élément du plan (PLAN)", 0],
            ["dArrow", "D", "FLÈCHE", "", "Pointe de flèche au bout du trait, dans le sens du tracé", 0],
            ["dRoads", "D", "ROUTES", "", "Le trait suit les routes entre les points (convoi, patrouille)", 0],
            ["dClosed", "D", "FERMÉ", "", "Le dessin libre revient à son point de départ (zone)", 0],
            ["dClear", "D", "EFFACER MES TRAITS", "EFF. MES TRAITS", "Efface tous mes traits et dessins", 0],
            ["dPlanEnd", "D", "TERMINER", "", "Pose le graphisme en cours (aussi : clic droit)", 0],
            ["dPlanCancel", "D", "ABANDONNER", "ABAND.", "Abandonne le graphisme en cours", 0],
            ["gAiGo", "L", "ORDRE IA", "", "Le groupe IA choisi exécute l'ordre au point du clic droit (sinon sur place)", 1],
            ["gOrder", "L", "ORDRE AU GROUPE", "ORDRE", "Rédige un ordre pour ce groupe (onglet ORDRES)", 1],
            ["gMsg", "L", "MESSAGE AU CHEF", "MESSAGE", "Écrit au chef de ce groupe (onglet CAMP)", 0],
            ["gCenter", "L", "CENTRER", "", "Centre la carte sur le groupe choisi", 0],
            ["bMsg", "L", "MESSAGE", "", "Message à ce membre, s'il est en jeu", 0],
            ["bCenter", "L", "CENTRER", "", "Centre la carte sur ce membre, s'il est sur le réseau ATAK", 0],
            ["rGo", "L", "AFFICHER", "", "Sur tous les écrans TOC à moins de 30 m du poste", 1],
            ["fCenter", "L", "CENTRER", "", "Centre la carte sur le compte rendu choisi", 0],
            ["oClear", "L", "EFFACER", "", "Efface l'objectif de l'ordre", 0],
            ["oSend", "L", "ENVOYER L'ORDRE", "ENVOYER", "L'ordre arrive dans l'app Tâches des destinataires", 1],
            ["cSend", "L", "ENVOYER", "", "Arrive dans Messages, conversation TOC du poste", 1],
            ["cSave", "L", "ENREGISTRER", "ENREG.", "Garde l'indicatif dans config.json (toc.callsign)", 0],
            ["cCamGo", "L", "AFFICHER", "", "Les écrans TOC à moins de 30 m montrent la caméra choisie", 0],
            ["sSlot0", "L", "SAT 1", "", "Satellite piloté et affiché par ce PC", 0],
            ["sSlot1", "L", "SAT 2", "", "Satellite piloté et affiché par ce PC", 0],
            ["sTake", "L", "PRENDRE LA MAIN", "PRENDRE", "Un seul opérateur pilote un satellite à la fois ; les autres écrans le montrent en lecture seule", 1],
            ["sRelease", "L", "RENDRE LA MAIN", "RENDRE", "Laisse le satellite à un autre opérateur", 0],
            ["sAUTO", "L", "AUTO", "", "Image en couleurs le jour, vision nocturne la nuit", 0],
            ["sDAY", "L", "JOUR", "", "Image en couleurs (éclaircie la nuit)", 0],
            ["sNV", "L", "NUIT", "", "Vision nocturne verte", 0],
            ["sTI", "L", "THERMIQUE", "THERM.", "Image thermique (chaud en blanc)", 0],
            ["sN", "L", "NORD", "", "Déplace la vue d'un tiers vers le nord (ou : glisser l'image)", 0],
            ["sS", "L", "SUD", "", "Déplace la vue d'un tiers vers le sud (ou : glisser l'image)", 0],
            ["sW", "L", "OUEST", "", "Déplace la vue d'un tiers vers l'ouest (ou : glisser l'image)", 0],
            ["sE", "L", "EST", "", "Déplace la vue d'un tiers vers l'est (ou : glisser l'image)", 0],
            ["sIn", "L", "ZOOM +", "", "Rapproche la vue (largeur divisée par 1,5 ; 80 m de large au plus près) ; aussi : molette sur l'image", 0],
            ["sOut", "L", "ZOOM −", "", "Éloigne la vue (largeur multipliée par 1,5 ; 5 km de large au plus) ; aussi : molette sur l'image", 0],
            ["sHome", "L", "POSTE", "", "Centre le satellite sur le poste", 0],
            ["sFollowGo", "L", "SUIVRE", "", "Le satellite suit le groupe ou l'unité choisi", 1],
            ["sFollowOff", "L", "ARRÊTER LE SUIVI", "ARRÊTER", "Le satellite reste sur place", 0],
            ["sShow", "L", "AFFICHER CE SATELLITE SUR LES ÉCRANS DU POSTE", "SUR LES ÉCRANS", "Écrans TOC à moins de 30 m du poste : vue satellite en lecture seule", 0],
            ["tStart", "L", "LANCER", "", "Lance ou reprend le chrono choisi", 1],
            ["tStop", "L", "ARRÊTER", "", "Arrête le chrono choisi à sa valeur", 0],
            ["tReset", "L", "ZÉRO", "", "Remet le chrono choisi à zéro (compte à rebours : à sa durée)", 0],
            ["tDel", "L", "SUPPRIMER", "SUPPR.", "Retire le chrono choisi", 0],
            ["tAdd", "L", "CRÉER", "", "Crée le chrono", 1],
            ["tShow", "L", "HORLOGE SUR LES ÉCRANS", "SUR LES ÉCRANS", "Écrans TOC à moins de 30 m du poste : heures ZULU et locale, tableau des chronos", 0],
            ["lAdd", "L", "INSCRIRE", "", "Inscrit l'entrée à la main courante", 1],
            ["lShow", "L", "MAIN COURANTE SUR LES ÉCRANS", "SUR LES ÉCRANS", "Écrans TOC à moins de 30 m du poste", 0],
            ["xDone", "L", "PASSÉ", "", "Le mot de code de la ligne choisie est passé : heure ZULU inscrite, entrée à la main courante", 1],
            ["xUndo", "L", "ANNULER", "", "Retire l'heure réelle de la ligne choisie", 0],
            ["xDel", "L", "SUPPRIMER", "SUPPR.", "Retire la ligne choisie", 0],
            ["xShow", "L", "ÉCRANS", "", "Liste d'exécution sur les écrans TOC à moins de 30 m", 0],
            ["xAdd", "L", "AJOUTER LA LIGNE", "AJOUTER", "Ajoute la ligne à la liste d'exécution", 1],
            ["kcPlus", "L", "+1", "", "Ajoute un au compte de l'élément choisi", 1],
            ["kcMinus", "L", "−1 (CORRECTION)", "−1", "Retire un au compte de l'élément choisi", 0],
            ["kcShow", "L", "PERTES ET RÉSULTATS SUR LES ÉCRANS", "SUR LES ÉCRANS", "Écrans TOC à moins de 30 m du poste", 0],
            ["qPRISE", "L", "PRISE EN COMPTE", "PRISE", "Le statut et la réponse partent au téléphone du demandeur", 1],
            ["qREFUSEE", "L", "REFUSÉE", "", "Le statut et la réponse partent au téléphone du demandeur", 0],
            ["qTERMINEE", "L", "TERMINÉE", "", "Le statut et la réponse partent au téléphone du demandeur", 0],
            ["qShow", "L", "DEMANDES SUR LES ÉCRANS", "SUR LES ÉCRANS", "Écrans TOC à moins de 30 m du poste", 0],
            ["hCreate", "H", "CRÉER LA SALLE", "CRÉER", "Ce PC devient le poste maître d'une nouvelle salle", 1],
            ["hLinkAll", "H", "LIER TOUT", "", "Lie à cette salle tous les écrans du camp dans le rayon choisi", 0],
            ["hAim", "H", "VISER UN ÉCRAN", "VISER", "Le PC se ferme : regardez un écran pendant 20 s au plus, il est lié à la salle et le PC se rouvre", 0],
            ["hShow", "H", "AFFICHER", "", "L'écran ou la dalle choisi montre ce contenu", 1],
            ["hScrRen", "H", "RENOMMER", "", "Renomme l'écran choisi", 0],
            ["hPower", "H", "ÉTEINDRE", "", "Éteint ou rallume l'écran choisi", 0],
            ["hBlink", "H", "IDENTIFIER", "IDENT.", "L'écran choisi clignote 2 s dans la salle", 0],
            ["hLink", "H", "LIER", "", "Lie ou délie l'écran choisi", 0],
            ["hMosPut", "H", "PLACER", "", "Met la caméra choisie dans la case choisie (la mosaïque passe en choix case par case)", 0],
            ["hSceneGo", "H", "REMETTRE", "", "Remet tout le mur comme dans la scène choisie", 1],
            ["hSceneDel", "H", "SUPPRIMER", "SUPPR.", "Supprime la scène choisie", 0],
            ["hSceneSave", "H", "ENREGISTRER", "ENREG.", "Enregistre tout le mur tel qu'il est sous ce nom", 0],
            ["hAllOn", "H", "TOUT ALLUMER", "", "Allume tous les écrans de la salle", 0],
            ["hAllOff", "H", "TOUT ÉTEINDRE", "", "Éteint tous les écrans de la salle", 0]
        ];
        {
            _x params ["_key", "_to", "_long", "_short", "_tip", "_prim"];
            private _c = [["COMSPEC_RscButton", "COMSPEC_RscButtonPrimary"] select (_prim isEqualTo 1), _key] call _mk;
            _c setVariable ["fit", [_long, _short, _tip]];
            _c setVariable ["tabBtn", _to isEqualTo "T"];
            _c ctrlSetText (_long call _tr);
            _c ctrlAddEventHandler ["ButtonClick", compile (switch (_to) do {
                case "T": { format ["['tab', %1] call comspec_atak_native_fnc_tocLaptop;", str (_key select [3])] };
                case "D": { format ["['act', %1] call comspec_atak_native_fnc_tocDraw;", str _key] };
                case "H": { format ["['act', %1] call comspec_atak_native_fnc_tocHallUi;", str _key] };
                default { format ["['press', %1] call comspec_atak_native_fnc_tocLaptop;", str _key] };
            })];
            [_c, _tip] call _tipOf;
        } forEach _btnDefs;
        // Cadres de suivi posés sur l'image satellite (au-dessus d'elle), message d'attente.
        private _boxes = [];
        for "_i" from 1 to 40 do {
            private _b = [];
            for "_j" from 1 to 4 do { _b pushBack (_d ctrlCreate ["COMSPEC_RscText", -1]); };
            _b pushBack (_d ctrlCreate ["RscPicture", -1]);
            private _bl2 = _d ctrlCreate ["COMSPEC_RscText", -1];
            _bl2 ctrlSetFontHeight ((_grid get "fontMin") * _u); _bl2 ctrlSetBackgroundColor [0, 0, 0, 0.55];
            _b pushBack _bl2;
            { _x ctrlShow false; } forEach _b;
            _boxes pushBack _b;
        };
        _st set ["satBoxes", _boxes];
        ["sWait"] call _stx;
        // Aide du contrôle survolé (ou de l'onglet) et ligne d'état.
        private _help = ["help"] call _stx;
        _help ctrlSetBackgroundColor [0.03, 0.04, 0.035, 1];
        private _bar = ["bar"] call _stx;
        _bar ctrlSetBackgroundColor [0.06, 0.08, 0.07, 1];
        // Image satellite : clic pour centrer, glisser pour déplacer, molette pour zoomer (fn_tocSat).
        _d displayAddEventHandler ["MouseButtonDown", { params ["", "_b", "_mx", "_my"]; if (_b isEqualTo 0) then { ["satDown", [_mx, _my]] call comspec_atak_native_fnc_tocLaptop; }; }];
        _d displayAddEventHandler ["MouseButtonUp", { params ["", "_b", "_mx", "_my"]; if (_b isEqualTo 0) then { ["satUp", [_mx, _my]] call comspec_atak_native_fnc_tocLaptop; }; }];
        _d displayAddEventHandler ["MouseZChanged", { params ["", "_z"]; ["satWheel", _z] call comspec_atak_native_fnc_tocLaptop; }];
        // Choix des listes déroulantes gardés tout de suite dans le brouillon (listes reconstruites quand le camp change).
        {
            _x params ["_k", "_dk"];
            (_ui get _k) ctrlAddEventHandler ["LBSelChanged", compile format ["params ['_c', '_i']; if (_i >= 0) then { (uiNamespace getVariable ['COMSPEC_ATAK_TocLapDraft', createHashMap]) set [%1, _c lbData _i]; };", str _dk]];
        } forEach [["oKind", "kind"], ["oPrio", "prio"], ["oTarget", "target"], ["cDest", "dest"], ["cPrio", "mprio"], ["gAi", "aikind"]];
        // Brouillon remis (PC refermé puis rouvert).
        _ok lbSetCurSel ((_kinds findIf { (_x select 1) isEqualTo (_draft getOrDefault ["kind", "MOVE"]) }) max 0);
        _op lbSetCurSel ((["ROUTINE", "IMPORTANT", "URGENT", "FLASH"] find (_draft getOrDefault ["prio", "ROUTINE"])) max 0);
        _cp lbSetCurSel ((["ROUTINE", "URGENT", "FLASH"] find (_draft getOrDefault ["mprio", "ROUTINE"])) max 0);
        private _gaN = (lbSize _ga) - 1;
        for "_i" from 0 to _gaN do { if ((_ga lbData _i) isEqualTo (_draft getOrDefault ["aikind", "MOVE"])) then { _ga lbSetCurSel _i; }; };
        if ((lbCurSel _ga) < 0) then { _ga lbSetCurSel 0; };
        (_ui get "oText") ctrlSetText (_draft getOrDefault ["text", ""]); // i18n:brut
        (_ui get "oGrid") ctrlSetText (_draft getOrDefault ["grid", ""]); // i18n:brut
        (_ui get "cText") ctrlSetText (_draft getOrDefault ["msg", ""]); // i18n:brut
        ["tool", _st getOrDefault ["tool", "SELECT"]] call _fn;
        _st set ["laid", true];
        ["tab", _st get "tab"] call _fn;
        ["frame", ["net", "post"] select ((count ((call _feedOf) param [1, []])) isEqualTo 0)] call _fn;
        private _pfh = [{ ["refresh"] call comspec_atak_native_fnc_tocLaptop; }, 1] call CBA_fnc_addPerFrameHandler;
        _st set ["pfh", _pfh];
        ["INFO", "TOC", format ["PC du poste %1 ouvert", ["callsign"] call comspec_atak_native_fnc_tocPost]] call comspec_atak_native_fnc_log;
    };
    case "mk": {
        // Contrôle créé pour fn_tocDraw / fn_tocHallUi : même registre, posé par la même grille. Clé vide : contrôle
        // posé par son propriétaire (dalles du plan de la salle), hors registre.
        _a params ["_cls", ["_key", ""]];
        private _d = uiNamespace getVariable ["COMSPEC_ATAK_TocLapDisp", displayNull];
        if (isNull _d) exitWith { controlNull };
        private _c = _d ctrlCreate [_cls, -1];
        if (_key isNotEqualTo "") then { _ui set [_key, _c]; (_st get "keys") pushBack _key; };
        _c
    };
    case "tip": { _a call _tipOf; };
    case "label": { _a call _relabel; };
    case "hover": {
        _a params ["_c", "_in"];
        if (!_in) exitWith { if ((_st getOrDefault ["hoverC", controlNull]) isEqualTo _c) then { _st set ["hoverC", controlNull]; ["help"] call _fn; }; };
        _st set ["hoverC", _c];
        ["help"] call _fn;
    };
    case "help": {
        // Ligne d'aide : contrôle survolé (libellé complet s'il est raccourci, aide, raison s'il est grisé), sinon l'onglet.
        private _h = _ui getOrDefault ["help", controlNull];
        if (isNull _h) exitWith {};
        private _c = _st getOrDefault ["hoverC", controlNull];
        private _s = "";
        if (!isNull _c && {ctrlShown _c}) then {
            (_c getVariable ["fit", ["", "", ""]]) params ["_long"];
            private _tip = _c getVariable ["tip", ""];
            if !(_c getVariable ["tipRaw", false]) then { _tip = _tip call _tr; };
            private _head = if (_c getVariable ["short", false]) then { format ["<t font='COMSPEC_BarlowBold'>%1</t> · ", [[_long call _tr, _long] select (_c getVariable ["raw", false])] call _esc] } else { "" };
            private _why = _c getVariable ["why", ""];
            _s = format ["<t size='0.8' color='#cfd8d3'>%1%2%3</t>", _head, [_tip] call _esc, ["", format ["<br/><t color='#e0b040'>%1</t>", [_why call _tr] call _esc]] select (!ctrlEnabled _c && {_why isNotEqualTo ""})];
        } else {
            private _tabHelp = createHashMapFromArray [
                ["GROUPES", "Choisissez un groupe : la carte se centre sur lui. Clic droit sur la carte : point de l'ORDRE IA."],
                ["ORBAT", "ORBAT du site, lu avec votre compte. Choisissez un membre pour lui écrire ou centrer la carte."],
                ["FLUX", "Comptes rendus, alertes et messages du camp. Choisissez une ligne : la carte se centre dessus."],
                ["ORDRES", "Clic droit sur la carte : objectif de l'ordre. L'ordre arrive dans l'app Tâches des destinataires."],
                ["CAMP", "Message au camp ou à un chef de groupe, indicatif du poste, caméra sur les écrans du poste."],
                ["RÉSEAU", "État du réseau du camp et contenu des écrans TOC à moins de 30 m."],
                ["SAT", "Molette sur l'image : zoom · clic : centre la vue · clic maintenu puis glisser : déplace la vue. Une commande sur un satellite libre vous en donne la main."],
                ["CHRONOS", "Chronos, comptes à rebours et heure H, communs à tous les PC du camp."],
                ["RADIO", "Radios de l'opérateur, en lecture seule : parlez avec votre touche d'alternat."],
                ["SALLE", "Cliquez un écran ou une dalle sur le plan, puis choisissez son contenu."],
                ["MAIN COURANTE", "Entrées horodatées en ZULU, communes au camp."],
                ["EXCHECK", "Choisissez une ligne puis PASSÉ : l'heure réelle est inscrite."],
                ["PERTES", "Pertes et résultats par élément ; corrigez à la main si besoin."],
                ["DEMANDES", "Choisissez une demande, écrivez la réponse, puis son statut."]
            ];
            _s = format ["<t size='0.8' color='#8a9a93'>%1</t>", [(_tabHelp getOrDefault [_st getOrDefault ["tab", "GROUPES"], ""]) call _tr] call _esc];
        };
        _h ctrlSetStructuredText parseText _s;
    };
    case "layout": {
        // Mise en page en cours : barre de titre, onglets, gauche (carte et barre de l'outil, satellite ou plan de la
        // salle), panneau de l'onglet, aide, ligne d'état. Seuls les contrôles nommés ici sont montrés.
        if ((count _ui) isEqualTo 0) exitWith {};
        private _tab = _st getOrDefault ["tab", "GROUPES"];
        private _tool = _st getOrDefault ["tool", "SELECT"];
        private _left = switch (true) do {
            case (_tab isEqualTo "SAT"): { "L:SAT" };
            case (_tab isEqualTo "SALLE"): { "L:SALLE" };
            case (_tool in ["LINE", "DRAW", "PLAN", "MARKER"]): { "L:MAP:" + _tool };
            default { "L:MAP:OTHER" };
        };
        private _layout = createHashMapFromArray [
["F:TITLE", [["titleRow", [1, -0.3, -0.14, -0.08, -0.17, -0.09], ["title", "clock", ["flashTxt", 1, "row"], ["flashGo", 1, "row"], ["postGo", 1, "row"], ["close", 1, "row"]]]]],
["F:TABS", [["tab", [1, 1, 1, 1, 1], ["tabGROUPES", "tabORBAT", "tabFLUX", "tabORDRES", "tabCAMP"]], ["tab", [1, 1, 1, 1, 1], ["tabRÉSEAU", "tabSAT", "tabCHRONOS", "tabRADIO", "tabSALLE"]], ["tab", [1, 1, 1, 1], ["tabMAIN COURANTE", "tabEXCHECK", "tabPERTES", "tabDEMANDES"]]]],
["F:HELP", [["help", [1], ["help"]]]],
["F:BAR", [["bar", [1], ["bar"]]]],
["L:MAP:OTHER", [["row", [1, 1, 1, 1, 1, 0.6, 1, 2, 1.1], ["framenet", "frameme", "framesel", "toolMEASURE", "toolMARKER", "layerai", "layerev", "search", "searchGo"]], [1, [1], ["map"]], ["row", [1, 1, 1, 1, 1], ["toolLINE", "toolDRAW", "toolPLAN", "toolERASE", "dUndo"]], ["row", [1], ["dInfo"]], ["toolHint", [1], ["toolHint"]]]],
["L:MAP:LINE", [["row", [1, 1, 1, 1, 1, 0.6, 1, 2, 1.1], ["framenet", "frameme", "framesel", "toolMEASURE", "toolMARKER", "layerai", "layerev", "search", "searchGo"]], [1, [1], ["map"]], ["row", [1, 1, 1, 1, 1], ["toolLINE", "toolDRAW", "toolPLAN", "toolERASE", "dUndo"]], ["row", [1.2, 1, 1, 1, 1.5], ["dColor", "dWidth", "dArrow", "dRoads", "dClear"]], ["toolHint", [1], ["toolHint"]]]],
["L:MAP:DRAW", [["row", [1, 1, 1, 1, 1, 0.6, 1, 2, 1.1], ["framenet", "frameme", "framesel", "toolMEASURE", "toolMARKER", "layerai", "layerev", "search", "searchGo"]], [1, [1], ["map"]], ["row", [1, 1, 1, 1, 1], ["toolLINE", "toolDRAW", "toolPLAN", "toolERASE", "dUndo"]], ["row", [1.2, 1, 1, 1, 1.5], ["dColor", "dWidth", "dArrow", "dClosed", "dClear"]], ["toolHint", [1], ["toolHint"]]]],
["L:MAP:PLAN", [["row", [1, 1, 1, 1, 1, 0.6, 1, 2, 1.1], ["framenet", "frameme", "framesel", "toolMEASURE", "toolMARKER", "layerai", "layerev", "search", "searchGo"]], [1, [1], ["map"]], ["row", [1, 1, 1, 1, 1], ["toolLINE", "toolDRAW", "toolPLAN", "toolERASE", "dUndo"]], ["row", [2.2, 1.4, 1, 1.1], ["dKind", "dPlanTxt", "dPlanEnd", "dPlanCancel"]], ["toolHint", [1], ["toolHint"]]]],
["L:MAP:MARKER", [["row", [1, 1, 1, 1, 1, 0.6, 1, 2, 1.1], ["framenet", "frameme", "framesel", "toolMEASURE", "toolMARKER", "layerai", "layerev", "search", "searchGo"]], [1, [1], ["map"]], ["row", [1, 1, 1, 1, 1], ["toolLINE", "toolDRAW", "toolPLAN", "toolERASE", "dUndo"]], ["row", [1, 1.5, 1, 1.3, 0.9, 1.1], ["mAff", "mType", "mColor", "mText", "mTtl", "mChan"]], ["toolHint", [1], ["toolHint"]]]],
["L:SAT", [["strip", [1], ["sStrip"]], [1, [1], ["sBack"]], ["info2", [1], ["sLegend"]]]],
["L:SALLE", [["row", [1], ["hPlanTitle"]], [1, [1], ["hPlanBg"]]]],
["R:GROUPES", [[3, [1], ["gList"]], [2, [1], ["gInfo"]], ["row", [2, 1], ["gAi", "gAiGo"]], ["row", [1, 1, 1], ["gOrder", "gMsg", "gCenter"]]]],
["R:ORBAT", [[3, [1], ["bList"]], [1.3, [1], ["bInfo"]], ["row", [1, 1, 1], ["bMsg", "bCenter", ""]]]],
["R:FLUX", [["row", [1], ["fFilter"]], [3, [1], ["fList"]], [1.2, [1], ["fInfo"]], ["row", [1, 1, 1], ["fCenter", "", ""]]]],
["R:ORDRES", [["label", [1, 1], ["oL1", "oL2"]], ["row", [1, 1], ["oKind", "oPrio"]], ["label", [1], ["oL3"]], ["row", [1], ["oTarget"]], ["label", [1], ["oL4"]], ["info3", [1], ["oText"]], ["label", [1], ["oL5"]], ["row", [2, 1], ["oGrid", "oClear"]], ["tall", [1], ["oSend"]], [1, [1], ["oInfo"]]]],
["R:CAMP", [["label", [1], ["cL1"]], ["row", [2, 1], ["cDest", "cPrio"]], ["label", [1], ["cL2"]], ["info3", [1], ["cText"]], ["tall", [1], ["cSend"]], ["label", [1], ["cL3"]], ["row", [2, 1], ["cCs", "cSave"]], ["label", [1], ["cL4"]], ["row", [2, 1], ["cCam", "cCamGo"]], [1, [1], ["cInfo"]]]],
["R:RÉSEAU", [[1, [1], ["rInfo"]], ["label", [1], ["rL1"]], ["row", [2, 1], ["rShow", "rGo"]]]],
["R:SAT", [["row", [1, 1], ["sSlot0", "sSlot1"]], ["info3", [1], ["sInfo"]], ["row", [1, 1], ["sTake", "sRelease"]], ["row", [1, 1, 1, 1], ["sAUTO", "sDAY", "sNV", "sTI"]], ["row", [1, 1, 1, 1], ["sN", "sS", "sW", "sE"]], ["row", [1, 1, 1], ["sIn", "sOut", "sHome"]], ["label", [1], ["sL1"]], ["row", [1], ["sFollow"]], ["row", [1, 1], ["sFollowGo", "sFollowOff"]], ["row", [1], ["sShow"]]]],
["R:CHRONOS", [[2, [1], ["tList"]], ["row", [1, 1, 1, 1], ["tStart", "tStop", "tReset", "tDel"]], ["label", [1], ["tL1"]], ["row", [1], ["tName"]], ["row", [1, 1], ["tKind", "tVal"]], ["row", [1, 1], ["tAdd", "tShow"]], [1, [1], ["tInfo"]]]],
["R:RADIO", [[1, [1], ["kInfo"]]]],
["R:SALLE", [["info2", [1], ["hInfo"]], ["row", [2, 1], ["hName", "hCreate"]], ["row", [1, 1.6, 1.6], ["hRad", "hLinkAll", "hAim"]], ["info2", [1], ["hSel"]], ["row", [2, 1], ["hWhat", "hShow"]], ["row", [2, 1], ["hScrName", "hScrRen"]], ["row", [1, 1, 1], ["hPower", "hBlink", "hLink"]], ["row", [1, 1, 1, 1], ["hMosRot", "hMosCase", "hMosSrc", "hMosPut"]], ["row", [2, 1, 1], ["hScene", "hSceneGo", "hSceneDel"]], ["row", [2, 1], ["hSceneName", "hSceneSave"]], ["row", [1, 1], ["hAllOn", "hAllOff"]]]],
["R:MAIN COURANTE", [[3, [1], ["lList"]], ["row", [2, 1], ["lText", "lAdd"]], ["row", [1], ["lShow"]], [0.7, [1], ["lInfo"]]]],
["R:EXCHECK", [[2, [1], ["xList"]], ["row", [1, 1, 1, 1], ["xDone", "xUndo", "xDel", "xShow"]], ["label", [1], ["xL1"]], ["row", [1], ["xEv"]], ["row", [1, 1, 1], ["xCw", "xEl", "xAt"]], ["row", [1], ["xAdd"]], [1, [1], ["xInfo"]]]],
["R:PERTES", [[2, [1], ["kcInfo"]], ["label", [1], ["kcL1"]], ["row", [1, 1], ["kcEl", "kcKind"]], ["row", [1, 1], ["kcPlus", "kcMinus"]], ["row", [1], ["kcShow"]]]],
["R:DEMANDES", [[2, [1], ["qList"]], [1, [1], ["qInfo"]], ["row", [1], ["qAns"]], ["row", [1, 1, 1], ["qPRISE", "qREFUSEE", "qTERMINEE"]], ["row", [1], ["qShow"]]]]
        ];
        // Fonds toujours montrés (posés une fois au chargement).
        private _on = ["shellOut", "shellIn", "screen", "brand", "titleBg", "titleLine", "rBg"];
        {
            _x params ["_lk", "_rk"];
            private _rows = _layout getOrDefault [_lk, []];
            (_st get _rk) params ["_rx", "_ry", "_rw", "_rh"];
            [_rows, _rx, _ry, _rw, _rh] call _place;
            { { _on pushBack (if (_x isEqualType "") then { _x } else { _x select 0 }); } forEach (_x select 2); } forEach _rows;
        } forEach [["F:TITLE", "rTitle"], ["F:TABS", "rTabs"], ["F:HELP", "rHelp"], ["F:BAR", "rBar"], [_left, "rLeft"], ["R:" + _tab, "rRight"]];
        { (_ui get _x) ctrlShow (_x in _on); } forEach (_st getOrDefault ["keys", []]);
        // Satellite : image au format 16:9 des écrans, en haut de sa zone, centrée ; message d'attente au milieu.
        private _sat = _tab isEqualTo "SAT";
        if (_sat) then {
            (ctrlPosition (_ui get "sBack")) params ["_bx", "_by", "_bw", "_bh"];
            private _ar = (_bw / pixelW) / ((_bh max pixelH) / pixelH);
            private _sw = _bw; private _sh = _bh;
            if (_ar > 16 / 9) then { _sw = _bw * (16 / 9) / _ar; } else { _sh = _bh * _ar / (16 / 9); };
            private _sRect = [_bx + (_bw - _sw) / 2, _by, _sw, _sh];
            _st set ["satRect", _sRect];
            (_ui get "sPic") ctrlSetPosition _sRect;
            (_ui get "sPic") ctrlCommit 0;
            (_ui get "sWait") ctrlSetPosition [_sRect select 0, (_sRect select 1) + _sh * 0.4, _sw, 0.08 * safeZoneH];
            (_ui get "sWait") ctrlCommit 0;
            (_ui get "sPic") ctrlShow true;
        };
        (_ui get "sPic") ctrlShow _sat;
        (_ui get "sWait") ctrlShow false;
        if (!_sat) then { { { _x ctrlShow false; } forEach _x; } forEach (_st getOrDefault ["satBoxes", []]); };
        // Salle : plan du mur sous son titre (fn_tocHallUi place ses dalles dedans).
        if (_tab isEqualTo "SALLE") then {
            (ctrlPosition (_ui get "hPlanTitle")) params ["_tx", "_ty", "_tw"];
            (ctrlPosition (_ui get "hPlanBg")) params ["", "_gy0", "", "_gh"];
            _st set ["hallPlan", [_tx, _ty, _tw, (_gy0 + _gh) - _ty]];
        } else {
            { _x ctrlShow false; } forEach (_st getOrDefault ["hallTiles", []]);
        };
        _st set ["layoutKey", [_left, _tab]];
    };
    case "press": {
        // Boutons du PC (table _btnDefs, cible L) : cadrage, outils, calques, recherche, fermeture ; le reste passe par « act ».
        switch (true) do {
            case (_a in ["framenet", "frameme", "framesel"]): { ["frame", _a select [5]] call _fn; };
            case (_a in ["toolMEASURE", "toolMARKER"]): { ["tool", _a select [4]] call _fn; };
            case (_a in ["layerai", "layerev"]): { ["layer", _a select [5]] call _fn; };
            case (_a in ["gCenter", "fCenter"]): { ["frame", "sel"] call _fn; };
            case (_a isEqualTo "searchGo"): { ["search"] call _fn; };
            case (_a isEqualTo "close"): { closeDialog 0; };
            case (_a isEqualTo "flashGo"): { ["act", "flash"] call _fn; };
            case (_a isEqualTo "postGo"): { ["act", "login"] call _fn; };
            default { ["act", _a] call _fn; };
        };
    };
    case "audit": {
        // Défauts de l'onglet affiché (autotest, console de débogage) : bouton sans texte, texte coupé (bouton, libellé,
        // texte structuré), deux contrôles qui se chevauchent, contrôle hors de l'écran du PC. Vide : tout va bien.
        if ((count _ui) isEqualTo 0) exitWith { ["PC du poste fermé"] };
        private _out = [];
        (_st getOrDefault ["inner", [0, 0, 1, 1]]) params ["_fx", "_fy", "_fw", "_fh"];
        private _vis = (_st getOrDefault ["keys", []]) select { ctrlShown (_ui get _x) };
        private _solid = _vis select { !((_ui get _x) getVariable ["bg", false]) };
        {
            private _c = _ui get _x;
            (ctrlPosition _c) params ["_cx", "_cy", "_cw", "_ch"];
            private _ty = ctrlType _c;
            if (_ty isEqualTo 1) then {
                if ((ctrlText _c) isEqualTo "") then { _out pushBack format ["bouton sans texte : %1", _x]; };
                if ((ctrlTextWidth _c) > _cw * 1.02) then { _out pushBack format ["texte coupé : %1 (%2)", _x, ctrlText _c]; };
            };
            if (_ty isEqualTo 0 && {(ctrlText _c) isNotEqualTo ""} && {(ctrlTextWidth _c) > _cw * 1.02}) then { _out pushBack format ["texte coupé : %1 (%2)", _x, ctrlText _c]; };
            if (_ty isEqualTo 13 && {(ctrlTextHeight _c) > _ch * 1.05}) then { _out pushBack format ["texte coupé : %1 (hauteur %2 pour %3)", _x, (ctrlTextHeight _c) toFixed 3, _ch toFixed 3]; };
            if (_cw <= 0 || {_ch <= 0}) then { _out pushBack format ["contrôle sans taille : %1", _x]; };
            if (_cx < _fx - 0.0005 || {_cy < _fy - 0.0005} || {_cx + _cw > _fx + _fw + 0.0005} || {_cy + _ch > _fy + _fh + 0.0005}) then { _out pushBack format ["hors de l'écran du PC : %1", _x]; };
        } forEach (_vis select { !((_ui get _x) getVariable ["bg", false]) || {_x isEqualTo "sBack"} });
        {
            private _ka = _x;
            (ctrlPosition (_ui get _ka)) params ["_ax", "_ay", "_aw", "_ah"];
            for "_j" from (_forEachIndex + 1) to ((count _solid) - 1) do {
                private _kb = _solid select _j;
                (ctrlPosition (_ui get _kb)) params ["_bx", "_by", "_bw", "_bh"];
                if (_ax < _bx + _bw - 0.0005 && {_bx < _ax + _aw - 0.0005} && {_ay < _by + _bh - 0.0005} && {_by < _ay + _ah - 0.0005}) then {
                    _out pushBack format ["chevauchement : %1 / %2", _ka, _kb];
                };
            };
        } forEach _solid;
        _out
    };
    case "satDown": {
        // Appui sur l'image satellite : début d'un glisser (ou d'un clic).
        if ((_st getOrDefault ["tab", ""]) isNotEqualTo "SAT") exitWith {};
        (_st getOrDefault ["satRect", [0, 0, 0, 0]]) params ["_rx", "_ry", "_rw", "_rh"];
        _a params ["_mx", "_my"];
        if (_mx < _rx || {_mx > _rx + _rw} || {_my < _ry} || {_my > _ry + _rh}) exitWith { _st set ["satDrag", []]; };
        _st set ["satDrag", [_mx, _my]];
    };
    case "satUp": {
        // Relâché : petit déplacement = clic (centre la vue sur ce point) ; sinon la vue suit le glisser.
        private _p0 = _st getOrDefault ["satDrag", []];
        _st set ["satDrag", []];
        if ((count _p0) < 2 || {(_st getOrDefault ["tab", ""]) isNotEqualTo "SAT"}) exitWith {};
        (_st getOrDefault ["satRect", [0, 0, 1, 1]]) params ["_rx", "_ry", "_rw", "_rh"];
        _a params ["_mx", "_my"];
        private _du = (_mx - (_p0 select 0)) / (_rw max 0.001);
        private _dv = (_my - (_p0 select 1)) / (_rh max 0.001);
        if ((abs _du) + (abs _dv) < 0.015) exitWith { ["satClick", [((_p0 select 0) - _rx) / _rw, ((_p0 select 1) - _ry) / _rh]] call _fn; };
        private _n = _st getOrDefault ["satN", 0];
        if !(["satCan", _n] call _fn) exitWith {};
        ["send", ["sat", [_n, "pan", [-_du, _dv]]]] call comspec_atak_native_fnc_tocRoom;
        [format ["SAT %1 : vue déplacée", _n + 1]] call _setStatus;
    };
    case "satWheel": {
        // Molette sur l'image : zoom (une demande toutes les 0,15 s au plus).
        if ((_st getOrDefault ["tab", ""]) isNotEqualTo "SAT" || {_a isEqualTo 0}) exitWith {};
        (_st getOrDefault ["satRect", [0, 0, 0, 0]]) params ["_rx", "_ry", "_rw", "_rh"];
        getMousePosition params ["_mx", "_my"];
        if (_mx < _rx || {_mx > _rx + _rw} || {_my < _ry} || {_my > _ry + _rh}) exitWith {};
        if (diag_tickTime - (_st getOrDefault ["wheelAt", -1]) < 0.15) exitWith {};
        _st set ["wheelAt", diag_tickTime];
        private _n = _st getOrDefault ["satN", 0];
        if !(["satCan", _n] call _fn) exitWith {};
        ["send", ["sat", [_n, "zoom", [1.25, 1 / 1.25] select (_a > 0)]]] call comspec_atak_native_fnc_tocRoom;
    };
    case "satCan": {
        // Satellite pilotable par ce PC : j'ai la main, ou il est libre (la commande me la donne). Sinon la raison
        // s'écrit sur la ligne d'état.
        private _s = ((["state", _side] call comspec_atak_native_fnc_tocRoom) select 2) param [_a, []];
        private _who = _s param [6, ""];
        private _can = (_s param [7, ""]) isEqualTo getPlayerUID player || {_who isEqualTo ""};
        if (!_can) then { [format ["SAT %1 piloté par %2 : lecture seule", _a + 1, _who]] call _setStatus; };
        _can
    };
    case "unload": {
        ["keep"] call _fn;
        ["unload"] call comspec_atak_native_fnc_tocDraw;
        ["pipOff"] call _fn;
        if ((_st getOrDefault ["satPfh", -1]) >= 0) then { [_st get "satPfh"] call CBA_fnc_removePerFrameHandler; _st set ["satPfh", -1]; };
        // PC refermé : la main sur les satellites est rendue (un autre opérateur peut la prendre).
        {
            if ((_x param [7, ""]) isEqualTo getPlayerUID player) then { ["send", ["sat", [_forEachIndex, "release", []]]] call comspec_atak_native_fnc_tocRoom; };
        } forEach ((["state", _side] call comspec_atak_native_fnc_tocRoom) select 2);
        [_st getOrDefault ["pfh", -1]] call CBA_fnc_removePerFrameHandler;
        _st set ["pfh", -1];
        _st set ["ui", createHashMap];
        uiNamespace setVariable ["COMSPEC_ATAK_TocLapDisp", displayNull];
        player setVariable ["COMSPEC_ATAK_TocView", nil, true];
    };
    case "keep": {
        // Saisie gardée dans le brouillon (changement d'onglet, fermeture).
        if ((count _ui) isEqualTo 0) exitWith {};
        private _ok = _ui get "oKind"; private _op = _ui get "oPrio"; private _cp = _ui get "cPrio";
        if (lbCurSel _ok >= 0) then { _draft set ["kind", _ok lbData (lbCurSel _ok)]; };
        if (lbCurSel _op >= 0) then { _draft set ["prio", _op lbData (lbCurSel _op)]; };
        if (lbCurSel _cp >= 0) then { _draft set ["mprio", _cp lbData (lbCurSel _cp)]; };
        private _ot = _ui get "oTarget"; if (lbCurSel _ot >= 0) then { _draft set ["target", _ot lbData (lbCurSel _ot)]; };
        private _cd = _ui get "cDest"; if (lbCurSel _cd >= 0) then { _draft set ["dest", _cd lbData (lbCurSel _cd)]; };
        _draft set ["text", ctrlText (_ui get "oText")];
        _draft set ["grid", ctrlText (_ui get "oGrid")];
        _draft set ["msg", ctrlText (_ui get "cText")];
    };
    case "tab": {
        if ((count _ui) isEqualTo 0) exitWith {};
        private _t = [_a, "GROUPES"] select !(_a in _allTabs);
        // Onglet SAT quitté : la caméra du satellite reste prête 30 s (retour sans nouvelle acquisition). En 4.4.0 le bail
        // de 3 s expirait au premier changement d'onglet : « rendu arrêté » 3 s après avoir quitté SAT (journal du 11/11).
        if ((_st getOrDefault ["tab", ""]) isEqualTo "SAT" && {_t isNotEqualTo "SAT"}) then { ["need", [_side, _st getOrDefault ["satN", 0], 30]] call comspec_atak_native_fnc_tocSat; };
        _st set ["tab", _t];
        _st set ["hoverC", controlNull];
        { (_ui get ("tab" + _x)) ctrlSetBackgroundColor ([[0.07, 0.085, 0.075, 1], [0.18, 0.38, 0.21, 1]] select (_x isEqualTo _t)); } forEach _allTabs;
        // Mise en page de l'onglet (gauche : carte, satellite ou plan de la salle) ; rien d'autre n'est montré.
        ["layout"] call _fn;
        if (_t isEqualTo "SALLE") then { ["refresh"] call comspec_atak_native_fnc_tocHallUi; };
        if !(_t in ["SAT", "SALLE"]) then { ["show"] call comspec_atak_native_fnc_tocDraw; };
        // SAT : cadres de suivi posés à chaque image.
        private _sat = _t isEqualTo "SAT";
        if (_sat && {(_st getOrDefault ["satPfh", -1]) < 0}) then {
            _st set ["satPfh", [{ ["satOverlay"] call comspec_atak_native_fnc_tocLaptop; }, 0] call CBA_fnc_addPerFrameHandler];
        };
        if (!_sat && {(_st getOrDefault ["satPfh", -1]) >= 0}) then { [_st get "satPfh"] call CBA_fnc_removePerFrameHandler; _st set ["satPfh", -1]; };
        _st set ["sig", ""];
        ["refresh"] call _fn;
        ["help"] call _fn;
    };
    case "pick": {
        // Sélection posée par la reconstruction de la liste : rien à recentrer.
        if (_st getOrDefault ["mute", false]) exitWith {};
        _a params ["_tab", "_v"];
        if (_tab isEqualTo "ORBAT") exitWith { _st set ["selB", _v]; ["refresh"] call _fn; };
        _st set [["selG", "selF"] select (_tab isEqualTo "FLUX"), _v];
        ["frame", "sel"] call _fn;
        ["refresh"] call _fn;
    };
    case "frame": {
        private _map = _ui getOrDefault ["map", controlNull];
        if (isNull _map) exitWith {};
        private _nodes = (call _feedOf) param [1, []];
        private _post = getPosASL (_st getOrDefault ["obj", player]);
        private _ctr = [_post select 0, _post select 1];
        private _span = 1500;
        switch (true) do {
            case (_a isEqualType []): { _ctr = _a; _span = 800; };
            case (_a isEqualTo "net"): {
                if ((count _nodes) > 0) then {
                    private _xs = _nodes apply { (_x select 2) select 0 }; private _ys = _nodes apply { (_x select 2) select 1 };
                    _ctr = [((selectMin _xs) + (selectMax _xs)) / 2, ((selectMin _ys) + (selectMax _ys)) / 2];
                    _span = ((((selectMax _xs) - (selectMin _xs)) max ((selectMax _ys) - (selectMin _ys))) * 1.3) max 800;
                } else { ["status", "Aucun nœud ATAK du camp sur le réseau : carte centrée sur le poste"] call _fn; };
            };
            case (_a isEqualTo "sel"): {
                private _p = [];
                if ((_st getOrDefault ["tab", ""]) isEqualTo "FLUX") then {
                    private _ev = (call _feedOf) param [2, []];
                    private _i = parseNumber (_st getOrDefault ["selF", "-1"]);
                    if (_i >= 0 && {_i < (count _ev)}) then { _p = (_ev select _i) param [6, []]; };
                } else {
                    private _g = ([_nodes] call _groups) select { (_x select 0) isEqualTo (_st getOrDefault ["selG", ""]) };
                    if ((count _g) > 0) then { _p = (_g select 0) select 7; };
                };
                if ((count _p) >= 2) then { _ctr = [_p select 0, _p select 1]; _span = 700; } else { _ctr = []; };
            };
        };
        if ((count _ctr) < 2) exitWith {
            ["status", [
                "Choisissez d'abord un groupe ou un compte rendu dans la liste",
                "Aucun ATAK dans ce groupe sur le réseau, ou compte rendu sans grille : le poste ne connaît pas sa position"
            ] select (((_st getOrDefault ["selG", ""]) isNotEqualTo "") || {(_st getOrDefault ["selF", ""]) isNotEqualTo ""})] call _fn;
        };
        _map ctrlMapAnimAdd [0.4, ((_span / (worldSize max 1000)) max 0.008) min 1, [_ctr select 0, _ctr select 1, 0]];
        ctrlMapAnimCommit _map;
    };
    case "click": {
        // Outil PLAN : le clic droit pose le graphisme en cours.
        if (["right", _a] call comspec_atak_native_fnc_tocDraw) exitWith {};
        private _p = _a;
        _draft set ["pos", [_p select 0, _p select 1]];
        if ((count _ui) > 0) then { (_ui get "oGrid") ctrlSetText ([[_p select 0, _p select 1, 0], 8] call comspec_atak_native_fnc_gridRef); }; // i18n:brut
        // Onglet GROUPES : le point sert à l'ORDRE IA, on y reste.
        if !((_st getOrDefault ["tab", ""]) in ["ORDRES", "GROUPES"]) then { ["keep"] call _fn; ["tab", "ORDRES"] call _fn; };
        ["status", format ["Objectif de l'ordre : grille %1", [[_p select 0, _p select 1, 0], 8] call comspec_atak_native_fnc_gridRef]] call _fn;
    };
    case "status": { [_a] call _setStatus; };
    case "satClick": {
        _a params ["_u", "_v"];
        private _n = _st getOrDefault ["satN", 0];
        private _p = ["unproject", [_side, _n, _u, _v]] call comspec_atak_native_fnc_tocSat;
        if ((count _p) < 2) exitWith {};
        ["send", ["sat", [_n, "center", _p]]] call comspec_atak_native_fnc_tocRoom;
        [format ["SAT %1 : centré sur la grille %2", _n + 1, [[_p select 0, _p select 1, 0], 6] call comspec_atak_native_fnc_gridRef]] call _setStatus;
    };
    case "satOverlay": {
        // Chaque image : cadres de suivi posés sur l'image satellite (projection, fn_tocSat).
        if ((count _ui) isEqualTo 0) exitWith {};
        private _n = _st getOrDefault ["satN", 0];
        (_st getOrDefault ["satRect", [0, 0, 0, 0]]) params ["_rx", "_ry", "_rw", "_rh"];
        private _boxes = _st getOrDefault ["satBoxes", []];
        private _used = 0;
        if ((["rt", [_side, _n]] call comspec_atak_native_fnc_tocSat) isNotEqualTo "") then {
            {
                _x params ["", "_cs", "_fr", "_kind", "_pos", "_size"];
                if (_used >= (count _boxes)) exitWith {};
                private _pr = ["project", [_side, _n, _pos]] call comspec_atak_native_fnc_tocSat;
                if ((count _pr) < 3) then { continue };
                _pr params ["_u", "_v", "_span"];
                if (_u < 0 || {_u > 1} || {_v < 0} || {_v > 1}) then { continue };
                private _gx = _rx + _u * _rw; private _gy = _ry + _v * _rh;
                private _w = ((_size / _span) * _rw) max (pixelW * 14);
                private _h = _w * (pixelH / pixelW);
                private _col = switch (_fr) do { case 0: { _sideRgb + [1] }; case 1: { (_sideRgb apply { _x * 0.75 }) + [0.85] }; default { [0.7, 0.7, 0.7, 0.8] }; };
                (_boxes select _used) params ["_l1", "_l2", "_l3", "_l4", "_ic", "_lb"];
                { _x ctrlSetBackgroundColor _col; } forEach [_l1, _l2, _l3, _l4];
                _l1 ctrlSetPosition [_gx - _w / 2, _gy - _h / 2, _w, pixelH * 2];
                _l2 ctrlSetPosition [_gx - _w / 2, _gy + _h / 2 - pixelH * 2, _w, pixelH * 2];
                _l3 ctrlSetPosition [_gx - _w / 2, _gy - _h / 2, pixelW * 2, _h];
                _l4 ctrlSetPosition [_gx + _w / 2 - pixelW * 2, _gy - _h / 2, pixelW * 2, _h];
                _ic ctrlSetText (format ["\A3\ui_f\data\map\markers\nato\b_%1.paa", (createHashMapFromArray [["veh", "motor_inf"], ["air", "air"], ["boat", "naval"], ["armor", "armor"]]) getOrDefault [_kind, "inf"]]); // i18n:brut (image)
                _ic ctrlSetTextColor _col;
                _ic ctrlSetPosition [_gx + _w / 2 + pixelW * 3, _gy - _h / 2 - pixelH * 20, pixelW * 18, pixelH * 18];
                private _txt = _cs + (["", " · ancien", " · perdu", " · hors ligne"] select ((_fr min 3) max 0));
                _lb ctrlSetText ((_txt) call comspec_atak_native_fnc_tr);
                _lb ctrlSetTextColor ([[1, 1, 1, 1], [0.8, 0.8, 0.8, 1]] select (_fr >= 2));
                _lb ctrlSetPosition [_gx + _w / 2 + pixelW * 23, _gy - _h / 2 - pixelH * 20, (ctrlTextWidth _lb) + pixelW * 8, pixelH * 20];
                { _x ctrlCommit 0; _x ctrlShow true; } forEach [_l1, _l2, _l3, _l4, _ic, _lb];
                _used = _used + 1;
            } forEach (["targets", _side] call comspec_atak_native_fnc_tocSat);
        };
        for "_i" from _used to ((count _boxes) - 1) do { { _x ctrlShow false; } forEach (_boxes select _i); };
    };
    case "tool": {
        // Un outil à la fois ; le même bouton rend la sélection.
        private _t = [_a, "SELECT"] select ((_st getOrDefault ["tool", "SELECT"]) isEqualTo _a && {(count _ui) > 0} && {(_st getOrDefault ["toolSet", false])});
        if !(_t in ["SELECT", "MEASURE", "MARKER", "LINE", "DRAW", "PLAN", "ERASE"]) then { _t = "SELECT"; };
        _st set ["tool", _t];
        _st set ["toolSet", true];
        _st set ["measure", []];
        { (_ui getOrDefault ["tool" + _x, controlNull]) ctrlSetBackgroundColor ([[0.07, 0.085, 0.075, 1], [0.18, 0.38, 0.21, 1]] select (_x isEqualTo _t)); } forEach ["MEASURE", "MARKER"];
        ["show"] call comspec_atak_native_fnc_tocDraw;
        // Consigne de l'outil, sous la barre de dessin (deux lignes au plus) ; la barre montre les réglages de l'outil.
        (_ui getOrDefault ["toolHint", controlNull]) ctrlSetStructuredText parseText format ["<t size='0.8' color='#cfd8d3'>%1</t>", [(switch (_t) do {
            case "MEASURE": { "MESURE : clic gauche sur A, puis sur B · clic droit : objectif de l'ordre" };
            case "MARKER": { "MARQUEUR : choisissez camp, type, couleur, nom, durée et canal en bas, puis clic gauche pour poser" };
            case "LINE": { "TRAIT : clic sur A, Ctrl+clic pour chaque étape, clic sur B · ROUTES : le trait suit les routes" };
            case "DRAW": { "DESSIN : clic gauche maintenu pour dessiner, relâcher pour poser · FERMÉ : forme fermée" };
            case "PLAN": { "PLAN : choisissez le graphisme, cliquez ses points, puis clic droit ou TERMINER · 2 clics pour une zone = rectangle" };
            case "ERASE": { "EFFACER : clic sur un marqueur, un trait ou un élément du plan (supprimé pour tous)" };
            default { "Molette : zoom · clic gauche : fiche d'une unité · clic gauche maintenu : déplacer · clic droit : objectif de l'ordre" };
        }) call _tr] call _esc];
        if (_st getOrDefault ["laid", false]) then { ["layout"] call _fn; };
    };
    case "layer": {
        private _k = "hide" + _a;
        _st set [_k, !(_st getOrDefault [_k, false])];
        (_ui getOrDefault ["layer" + _a, controlNull]) ctrlSetBackgroundColor ([[0.07, 0.085, 0.075, 1], [0.3, 0.12, 0.1, 1]] select (_st get _k));
    };
    case "left": {
        switch (_st getOrDefault ["tool", "SELECT"]) do {
            case "MEASURE": {
                private _ms = _st getOrDefault ["measure", []];
                if ((count _ms) >= 2) then { _ms = []; };
                _ms pushBack [_a select 0, _a select 1];
                _st set ["measure", _ms];
            };
            // Marqueur (palette du téléphone), point du plan, effacement : fn_tocDraw ; TRAIT et DESSIN agissent à l'appui.
            case "MARKER"; case "PLAN"; case "ERASE": { ["left", _a] call comspec_atak_native_fnc_tocDraw; };
            case "LINE"; case "DRAW": {};
            default {
                // Unité la plus proche du clic (32 pixels environ) : sa fiche, dans l'onglet GROUPES.
                private _map = _ui getOrDefault ["map", controlNull];
                if (isNull _map) exitWith {};
                private _cl = _map ctrlMapWorldToScreen _a;
                private _best = 0.04;
                private _g = "";
                {
                    private _p = _x select 2;
                    private _sp = _map ctrlMapWorldToScreen [_p select 0, _p select 1, 0];
                    private _d = ((_sp select 0) - (_cl select 0)) * ((_sp select 0) - (_cl select 0)) + ((_sp select 1) - (_cl select 1)) * ((_sp select 1) - (_cl select 1));
                    if (_d < (_best * _best)) then { _best = sqrt _d; _g = _x param [7, ""]; };
                } forEach ((call _feedOf) param [1, []]);
                if (_g isNotEqualTo "") then {
                    _st set ["selG", _g];
                    ["keep"] call _fn;
                    ["tab", "GROUPES"] call _fn;
                };
            };
        };
    };
    case "search": {
        private _q = trim (ctrlText (_ui getOrDefault ["search", controlNull]));
        if (_q isEqualTo "") exitWith { ["status", "Tapez une grille, un groupe, un indicatif ou un nom de marqueur"] call _fn; };
        private _ql = toLower _q;
        private _pos = [];
        private _lbl = "";
        // Grille d'abord (chiffres seuls), puis nœuds du réseau, groupes, marqueurs.
        if (((_q splitString "0123456789 -") joinString "") isEqualTo "") then {
            private _gp = [_q] call comspec_atak_native_fnc_gridParse;
            if (_gp isEqualType [] && {(count _gp) >= 2}) then { _pos = [_gp select 0, _gp select 1]; _lbl = "grille " + _q; };
        };
        if ((count _pos) isEqualTo 0) then {
            {
                if (((toLower (_x select 1)) find _ql) >= 0 || {((toLower (_x param [7, ""])) find _ql) >= 0}) exitWith { _pos = _x select 2; _lbl = _x select 1; };
            } forEach ((call _feedOf) param [1, []]);
        };
        if ((count _pos) isEqualTo 0) then {
            {
                if (((toLower (markerText _x)) find _ql) >= 0) exitWith { private _mp = getMarkerPos _x; _pos = [_mp select 0, _mp select 1]; _lbl = markerText _x; };
            } forEach allMapMarkers;
        };
        if ((count _pos) isEqualTo 0) exitWith { ["status", format ["Rien trouvé pour « %1 » sur le réseau du poste", _q]] call _fn; };
        _st set ["found", [_pos, _lbl, diag_tickTime + 15]];
        ["frame", _pos] call _fn;
        ["status", format ["Trouvé : %1", _lbl]] call _fn;
    };
    case "act": {
        ["keep"] call _fn;
        private _room0 = ["state", _side] call comspec_atak_native_fnc_tocRoom;
        private _nodes = (call _feedOf) param [1, []];
        private _cs = ["callsign"] call comspec_atak_native_fnc_tocPost;
        switch (_a) do {
            case "gOrder": {
                private _g = ([_nodes] call _groups) select { (_x select 0) isEqualTo (_st getOrDefault ["selG", ""]) };
                if ((count _g) isEqualTo 0) exitWith { ["Choisissez d'abord un groupe"] call _setStatus; };
                _draft set ["target", format ["group|%1|%2", (_g select 0) select 1, (_g select 0) select 0]];
                ["tab", "ORDRES"] call _fn;
            };
            case "gAiGo": {
                private _g = ([_nodes] call _groups) select { (_x select 0) isEqualTo (_st getOrDefault ["selG", ""]) };
                if ((count _g) isEqualTo 0) exitWith { ["Choisissez d'abord un groupe"] call _setStatus; };
                private _grp = group ((_g select 0) select 2);
                if (((_g select 0) select 3) > 0) exitWith { ["Groupe de joueurs : envoyez-lui un ordre (ORDRE AU GROUPE)"] call _setStatus; };
                ["send", [_grp, _draft getOrDefault ["aikind", "MOVE"], _draft getOrDefault ["pos", []], true]] call comspec_atak_native_fnc_aiOrder;
            };
            case "gMsg": {
                private _g = ([_nodes] call _groups) select { (_x select 0) isEqualTo (_st getOrDefault ["selG", ""]) };
                if ((count _g) isEqualTo 0) exitWith { ["Choisissez d'abord un groupe"] call _setStatus; };
                private _ld = (_g select 0) select 2;
                if (!isPlayer _ld) exitWith { ["Le chef de ce groupe n'est pas un joueur : envoyez un ordre"] call _setStatus; };
                _draft set ["dest", getPlayerUID _ld];
                ["tab", "CAMP"] call _fn;
            };
            case "bMsg": {
                private _o = ((["presence"] call comspec_atak_native_fnc_bftOrbat) select 1) getOrDefault [_st getOrDefault ["selB", ""], objNull];
                if (isNull _o || {_o isEqualTo player}) exitWith { ["Ce membre n'est pas en jeu : message impossible"] call _setStatus; };
                _draft set ["dest", getPlayerUID _o];
                ["tab", "CAMP"] call _fn;
            };
            case "bCenter": {
                private _o = ((["presence"] call comspec_atak_native_fnc_bftOrbat) select 1) getOrDefault [_st getOrDefault ["selB", ""], objNull];
                private _i = if (isNull _o) then { -1 } else { private _gn = groupId group _o; _nodes findIf { (_x param [7, ""]) isEqualTo _gn } };
                if (_i < 0) exitWith { ["Ce membre n'est pas sur le réseau ATAK : position inconnue du poste"] call _setStatus; };
                ["frame", (_nodes select _i) select 2] call _fn;
            };
            case "rGo": {
                private _rs = _ui get "rShow";
                private _k = _rs lbData (lbCurSel _rs);
                private _scr = (missionNamespace getVariable ["COMSPEC_ATAK_TocScreens", []]) select { !isNull _x && {alive _x} && {(player distance _x) < 30} && {((_x getVariable ["COMSPEC_ATAK_TocScr", []]) param [1, ""]) in ["", _side]} };
                if ((count _scr) isEqualTo 0) exitWith { ["Aucun écran TOC à moins de 30 m du poste"] call _setStatus; };
                { ["show", [_x, _k]] call comspec_atak_native_fnc_tocScreen; } forEach _scr;
                [format ["%1 écran(s) : %2", count _scr, _rs lbText (lbCurSel _rs)]] call _setStatus;
            };
            // Satellite (fn_tocRoom : le serveur garde une seule main par satellite).
            case "sSlot0"; case "sSlot1": { _st set ["satN", parseNumber (_a select [5])]; _st set ["sig", ""]; };
            case "sTake": {
                private _o = _st getOrDefault ["obj", player];
                ["send", ["sat", [_st getOrDefault ["satN", 0], "take", [(getPosASL _o) select 0, (getPosASL _o) select 1]]]] call comspec_atak_native_fnc_tocRoom;
            };
            case "sRelease": { ["send", ["sat", [_st getOrDefault ["satN", 0], "release", []]]] call comspec_atak_native_fnc_tocRoom; };
            case "sAUTO"; case "sDAY"; case "sNV"; case "sTI": {
                ["send", ["sat", [_st getOrDefault ["satN", 0], "mode", _a select [1]]]] call comspec_atak_native_fnc_tocRoom;
                [format ["SAT %1 : image %2", (_st getOrDefault ["satN", 0]) + 1, (createHashMapFromArray [["sAUTO", "auto"], ["sDAY", "jour"], ["sNV", "vision nocturne"], ["sTI", "thermique"]]) get _a]] call _setStatus;
            };
            case "sHome": {
                private _o = _st getOrDefault ["obj", player];
                ["send", ["sat", [_st getOrDefault ["satN", 0], "center", [(getPosASL _o) select 0, (getPosASL _o) select 1]]]] call comspec_atak_native_fnc_tocRoom;
            };
            case "sN"; case "sS"; case "sW"; case "sE": {
                private _d = (createHashMapFromArray [["sN", [0, 0.33]], ["sS", [0, -0.33]], ["sW", [-0.33, 0]], ["sE", [0.33, 0]]]) get _a;
                ["send", ["sat", [_st getOrDefault ["satN", 0], "pan", _d]]] call comspec_atak_native_fnc_tocRoom;
            };
            case "sIn"; case "sOut": {
                private _n = _st getOrDefault ["satN", 0];
                private _hw = ((((_room0 select 2) param [_n, []]) param [2, 600]) * ([1.5, 1 / 1.5] select (_a isEqualTo "sIn"))) max 40 min 2500;
                ["send", ["sat", [_n, "zoom", [1.5, 1 / 1.5] select (_a isEqualTo "sIn")]]] call comspec_atak_native_fnc_tocRoom;
                [format ["SAT %1 : largeur de la vue %2 m", _n + 1, round (2 * _hw)]] call _setStatus;
            };
            case "sFollowGo": {
                private _sf = _ui get "sFollow";
                private _v = (_sf lbData (lbCurSel _sf)) splitString "|";
                if ((count _v) < 2) exitWith { ["Choisissez d'abord un groupe ou une unité du réseau"] call _setStatus; };
                ["send", ["sat", [_st getOrDefault ["satN", 0], "follow", [_v select 0, _v select 1]]]] call comspec_atak_native_fnc_tocRoom;
            };
            case "sFollowOff": { ["send", ["sat", [_st getOrDefault ["satN", 0], "unfollow", []]]] call comspec_atak_native_fnc_tocRoom; };
            case "sShow": {
                private _n = _st getOrDefault ["satN", 0];
                private _scr = (missionNamespace getVariable ["COMSPEC_ATAK_TocScreens", []]) select { !isNull _x && {alive _x} && {(player distance _x) < 30} && {((_x getVariable ["COMSPEC_ATAK_TocScr", []]) param [1, ""]) in ["", _side]} };
                if ((count _scr) isEqualTo 0) exitWith { ["Aucun écran TOC à moins de 30 m du poste"] call _setStatus; };
                { ["view", [_x, "sat", _n]] call comspec_atak_native_fnc_tocScreen; ["ensure", [_x, "SAT"]] call comspec_atak_native_fnc_tocScreen; } forEach _scr;
                [format ["%1 écran(s) : vue satellite SAT %2", count _scr, _n + 1]] call _setStatus;
            };
            // Chronos du poste.
            case "tAdd": {
                private _name = toUpper trim (ctrlText (_ui get "tName"));
                if (_name isEqualTo "") exitWith { ["Donnez un nom au chrono"] call _setStatus; };
                private _tk = _ui get "tKind";
                private _k = _tk lbData ((lbCurSel _tk) max 0);
                private _p = ((trim (ctrlText (_ui get "tVal"))) splitString ":") apply { parseNumber _x };
                private _v = 0;
                if (_k isEqualTo "DOWN") then {
                    _v = switch (count _p) do { case 3: { (_p select 0) * 3600 + (_p select 1) * 60 + (_p select 2) }; case 2: { (_p select 0) * 60 + (_p select 1) }; case 1: { (_p select 0) * 60 }; default { 0 }; };
                    if (_v <= 0) then { _v = -1; };
                };
                if (_k isEqualTo "AT") then { _v = [-1, (_p select 0) * 60 + (_p param [1, 0])] select ((count _p) >= 1 && {(_p select 0) < 24}); };
                if (_v < 0) exitWith { [["Durée illisible : écrivez par exemple 30:00 (minutes:secondes)", "Heure H illisible : écrivez l'heure ZULU, par exemple 22:30"] select (_k isEqualTo "AT")] call _setStatus; };
                ["send", ["timerAdd", [_name, _k, _v]]] call comspec_atak_native_fnc_tocRoom;
                (_ui get "tName") ctrlSetText (("") call comspec_atak_native_fnc_tr);
                [format ["Chrono %1 créé", _name]] call _setStatus;
            };
            case "tStart"; case "tStop"; case "tReset"; case "tDel": {
                private _id = _st getOrDefault ["selT", ""];
                if (_id isEqualTo "") exitWith { ["Choisissez d'abord un chrono dans la liste"] call _setStatus; };
                ["send", [(createHashMapFromArray [["tStart", "timerStart"], ["tStop", "timerStop"], ["tReset", "timerReset"], ["tDel", "timerDel"]]) get _a, _id]] call comspec_atak_native_fnc_tocRoom;
                if (_a isEqualTo "tDel") then { _st set ["selT", ""]; };
            };
            case "tShow": {
                private _scr = (missionNamespace getVariable ["COMSPEC_ATAK_TocScreens", []]) select { !isNull _x && {alive _x} && {(player distance _x) < 30} && {((_x getVariable ["COMSPEC_ATAK_TocScr", []]) param [1, ""]) in ["", _side]} };
                if ((count _scr) isEqualTo 0) exitWith { ["Aucun écran TOC à moins de 30 m du poste"] call _setStatus; };
                { ["ensure", [_x, "CLOCK"]] call comspec_atak_native_fnc_tocScreen; } forEach _scr;
                [format ["%1 écran(s) : horloge et chronos", count _scr]] call _setStatus;
            };
            case "lShow": { ["LOG"] call _showOn; };
            case "kcShow": { ["KILLS"] call _showOn; };
            case "qShow": { ["REQ"] call _showOn; };
            case "xShow": { ["EXCHECK"] call _showOn; };
            // Main courante : entrée à la main.
            case "lAdd": {
                private _t = trim (ctrlText (_ui get "lText"));
                if (_t isEqualTo "") exitWith { ["Écrivez l'entrée de la main courante"] call _setStatus; };
                ["send", ["log", ["NOTE", _t]]] call comspec_atak_native_fnc_tocRoom;
                (_ui get "lText") ctrlSetText (("") call comspec_atak_native_fnc_tr);
                ["Entrée inscrite à la main courante"] call _setStatus;
            };
            // Liste d'exécution.
            case "xAdd": {
                private _ev = trim (ctrlText (_ui get "xEv"));
                if (_ev isEqualTo "") exitWith { ["Écrivez l'événement de la ligne"] call _setStatus; };
                ["send", ["exAdd", [_ev, trim (ctrlText (_ui get "xCw")), trim (ctrlText (_ui get "xEl")), trim (ctrlText (_ui get "xAt"))]]] call comspec_atak_native_fnc_tocRoom;
                { (_ui get _x) ctrlSetText (("") call comspec_atak_native_fnc_tr); } forEach ["xEv", "xCw", "xEl", "xAt"];
                [format ["Ligne ajoutée : %1", _ev]] call _setStatus;
            };
            case "xDone"; case "xUndo"; case "xDel": {
                private _id = _st getOrDefault ["selX", ""];
                if (_id isEqualTo "") exitWith { ["Choisissez d'abord une ligne de la liste"] call _setStatus; };
                ["send", [(createHashMapFromArray [["xDone", "exDone"], ["xUndo", "exUndo"], ["xDel", "exDel"]]) get _a, _id]] call comspec_atak_native_fnc_tocRoom;
                if (_a isEqualTo "xDel") then { _st set ["selX", ""]; };
            };
            // Pertes et résultats, à la main.
            case "kcPlus"; case "kcMinus": {
                private _ce = _ui get "kcEl"; private _ck = _ui get "kcKind";
                private _el = _ce lbData (lbCurSel _ce);
                if (_el isEqualTo "") exitWith { ["Choisissez d'abord l'élément"] call _setStatus; };
                ["send", ["kill", [_el, _ck lbData ((lbCurSel _ck) max 0), [1, -1] select (_a isEqualTo "kcMinus")]]] call comspec_atak_native_fnc_tocRoom;
            };
            // Demandes : statut et réponse au demandeur.
            case "qPRISE"; case "qREFUSEE"; case "qTERMINEE": {
                private _id = _st getOrDefault ["selQ", ""];
                if (_id isEqualTo "") exitWith { ["Choisissez d'abord une demande de la liste"] call _setStatus; };
                ["send", ["reqSet", [_id, _a select [1], trim (ctrlText (_ui get "qAns"))]]] call comspec_atak_native_fnc_tocRoom;
                (_ui get "qAns") ctrlSetText (("") call comspec_atak_native_fnc_tr);
                ["Réponse envoyée au téléphone du demandeur"] call _setStatus;
            };
            case "flash": {
                private _t = trim (ctrlText (_ui get "flashTxt"));
                if (_t isEqualTo "") exitWith { ["Écrivez le texte du FLASH à côté du bouton"] call _setStatus; };
                ["send", ["flash", _t]] call comspec_atak_native_fnc_tocRoom;
                (_ui get "flashTxt") ctrlSetText (("") call comspec_atak_native_fnc_tr);
                ["FLASH diffusé sur les écrans et PC du poste"] call _setStatus;
            };
            case "login": {
                private _ps = (["state", _side] call comspec_atak_native_fnc_tocRoom) select 7;
                if ((_ps findIf { (_x select 2) isEqualTo getPlayerUID player }) >= 0) then {
                    ["send", ["logout", ""]] call comspec_atak_native_fnc_tocRoom;
                    ["Poste libéré"] call _setStatus;
                } else {
                    ["send", ["login", ["callsign"] call comspec_atak_native_fnc_tocPost]] call comspec_atak_native_fnc_tocRoom;
                    [format ["Poste %1 pris", ["callsign"] call comspec_atak_native_fnc_tocPost]] call _setStatus;
                };
            };
            case "oClear": { _draft set ["grid", ""]; _draft set ["pos", []]; (_ui get "oGrid") ctrlSetText (("") call comspec_atak_native_fnc_tr); };
            case "oSend": {
                private _kind = _draft getOrDefault ["kind", "MOVE"];
                private _text = trim (_draft getOrDefault ["text", ""]);
                if (_text isEqualTo "") exitWith { ["Écrivez la consigne de l'ordre"] call _setStatus; };
                if ((count _text) > 400) then { _text = _text select [0, 400]; };
                private _grid = trim (_draft getOrDefault ["grid", ""]);
                private _tgt = (_draft getOrDefault ["target", "all||Tous"]) splitString "|";
                private _tType = _tgt param [0, "all"];
                private _tLabel = _tgt param [2, _tgt param [1, "Tous"]];
                private _payload = [_text, format ["%1 — Grille: %2", _text, _grid]] select (_grid isNotEqualTo "");
                private _label = (_kinds select ((_kinds findIf { (_x select 1) isEqualTo _kind }) max 0)) select 0;
                ["send", [_kind, _label, _tType, _tgt param [1, ""], _tLabel, _payload, _draft getOrDefault ["prio", "ROUTINE"], true, _draft getOrDefault ["pos", []]]] call comspec_atak_native_fnc_orderLocal;
                _draft set ["text", ""]; _draft set ["grid", ""]; _draft set ["pos", []];
                (_ui get "oText") ctrlSetText (("") call comspec_atak_native_fnc_tr); (_ui get "oGrid") ctrlSetText (("") call comspec_atak_native_fnc_tr);
                [format ["Ordre %1 envoyé à %2", _label, ["tout le camp", _tLabel] select (_tType isNotEqualTo "all")]] call _setStatus;
            };
            case "cSend": {
                private _body = trim (_draft getOrDefault ["msg", ""]);
                if (_body isEqualTo "") exitWith { ["Écrivez le message à envoyer"] call _setStatus; };
                if ((count _body) > 400) then { _body = _body select [0, 400]; };
                private _to = _draft getOrDefault ["dest", ""];
                private _ev = [_cs, _body, [dayTime, "HH:MM"] call BIS_fnc_timeToString, _draft getOrDefault ["mprio", "ROUTINE"], _side,
                    format ["T-%1-%2", getPlayerUID player, floor (serverTime * 10)], getPlayerUID player, _to];
                // Réseau filaire du poste : envoi direct par le serveur (ni signal ni file du téléphone).
                ["comspec_atak_native_sideEvent", ["comspec_atak_native_tocMsg", _ev, _side]] call CBA_fnc_serverEvent;
                _draft set ["msg", ""];
                (_ui get "cText") ctrlSetText (("") call comspec_atak_native_fnc_tr);
                private _who = if (_to isEqualTo "") then { "tout le camp" } else {
                    private _u = allPlayers select { getPlayerUID _x isEqualTo _to };
                    [_to, name (_u select 0)] select ((count _u) > 0)
                };
                [format ["Message envoyé à %1", _who]] call _setStatus;
            };
            case "cCamGo": {
                private _cc = _ui get "cCam";
                private _src = _cc lbData (lbCurSel _cc);
                private _scr = (missionNamespace getVariable ["COMSPEC_ATAK_TocScreens", []]) select { !isNull _x && {alive _x} && {(player distance _x) < 30} && {((_x getVariable ["COMSPEC_ATAK_TocScr", []]) param [1, ""]) in ["", _side]} };
                if ((count _scr) isEqualTo 0) exitWith { ["Aucun écran TOC à moins de 30 m du poste"] call _setStatus; };
                {
                    if (_src isEqualTo "") then { ["ensure", [_x, "SPLIT"]] call comspec_atak_native_fnc_tocScreen; } else { ["set", [_x, _src]] call comspec_atak_native_fnc_tocCam; };
                } forEach _scr;
                [format ["%1 écran(s) : %2", count _scr, [_cc lbText (lbCurSel _cc), "carte et flux"] select (_src isEqualTo "")]] call _setStatus;
            };
            case "cSave": {
                private _new = toUpper trim (ctrlText (_ui get "cCs"));
                if (_new isEqualTo "") exitWith { ["Indicatif vide"] call _setStatus; };
                ((["get"] call comspec_atak_native_fnc_localCfg) get "toc") set ["callsign", _new select [0, 24]];
                ["save"] call comspec_atak_native_fnc_localCfg;
                player setVariable ["COMSPEC_ATAK_TocOn", _new select [0, 24], true];
                [format ["Indicatif du poste : %1", _new select [0, 24]]] call _setStatus;
            };
        };
        _st set ["sig", ""];
        ["refresh"] call _fn;
    };
    case "refresh": {
        private _d = uiNamespace getVariable ["COMSPEC_ATAK_TocLapDisp", displayNull];
        if (isNull _d || {(count _ui) isEqualTo 0}) exitWith {};
        if (!alive player) exitWith { closeDialog 0; };
        private _feed = call _feedOf;
        _feed params [["_ft", -1], ["_nodes", []], ["_events", []], ["_stats", []]];
        private _cs = ["callsign"] call comspec_atak_native_fnc_tocPost;
        private _tab = _st getOrDefault ["tab", "GROUPES"];
        private _sideName = (createHashMapFromArray [["WEST", "BLUFOR"], ["EAST", "OPFOR"], ["GUER", "Indépendant"], ["CIV", "Civil"]]) getOrDefault [_side, _side];
        private _age = [-1, round ((serverTime - _ft) max 0)] select (_ft >= 0);
        private _room = ["state", _side] call comspec_atak_native_fnc_tocRoom;
        // Opérateur inscrit au poste (prise du poste) : sinon le joueur devant le PC.
        private _post = (_room select 7) select { (_x select 2) isEqualTo getPlayerUID player };
        // Barre de titre sur deux lignes (poste ; camp et opérateur), heures et liaison à droite sur deux lignes : tout tient
        // aussi en 4:3. Tailles de texte communes au PC : titre 1.1, corps 0.85, aide 0.8 (base 0.024 × safeZoneH).
        (_ui get "title") ctrlSetStructuredText parseText ((format ["<t size='1.1' font='COMSPEC_BarlowBold'>POSTE TOC · %1</t><br/><t size='0.8' color='%4'>%2 · %3</t>", [_cs] call _esc, _sideName,
            [format ["opérateur %1", [name player] call _esc], format ["poste pris par %1 à %2Z", [name player] call _esc, (_post param [0, []]) param [3, ""]]] select ((count _post) > 0), ["#8a9a93", "#5cc76b"] select ((count _post) > 0)]) call comspec_atak_native_fnc_tr);
        ([["postGo", "PRENDRE LE POSTE", "POSTE"], ["postGo", "LIBÉRER LE POSTE", "LIBÉRER"]] select ((count _post) > 0)) call _relabel;
        private _live = [["<t color='#e05a4a'>HORS LIGNE</t>", "<t color='#e0b040'>RETARD</t>"] select (_age >= 0), "<t color='#5cc76b'>EN DIRECT</t>"] select (_age >= 0 && {_age < 12});
        // Liaison radio du poste : ACRE2 ou TFAR chargé, radios de l'opérateur (lecture seule, fn_mpu5).
        private _radio = switch (true) do {
            case (!isNil "acre_api_fnc_getCurrentRadioList"): {
                private _nr = count (["radios"] call comspec_atak_native_fnc_mpu5);
                format ["<t color='%1'>ACRE2</t>", ["#e0b040", "#5cc76b"] select (_nr > 0)]
            };
            case (!isNil "TFAR_fnc_activeSwRadio"): { "<t color='#5cc76b'>TFAR</t>" };
            default { "<t color='#6f7773'>SANS RADIO</t>" };
        };
        (_ui get "clock") ctrlSetStructuredText parseText ((format ["<t align='right' size='0.85'><t font='COMSPEC_BarlowBold'>%1</t> <t color='#8a9a93'>ZULU</t>  <t font='COMSPEC_BarlowBold'>%2</t> <t color='#8a9a93'>LOCAL</t><br/>%3 · %4</t>",
            ["zulu"] call comspec_atak_native_fnc_tocRoom, [dayTime, "HH:MM:SS"] call BIS_fnc_timeToString, _radio, _live]) call comspec_atak_native_fnc_tr);
        private _cnt = [0, 0, 0, 0];
        { private _f = (_x select 4) min 3; _cnt set [_f, (_cnt select _f) + (_x param [8, 1])]; } forEach _nodes;
        _stats params [["_jam", 0], ["_relays", 0], ["_players", 0], ["_ais", 0]];
        private _msg = (_st getOrDefault ["status", ["", 0]]);
        private _pipN = count ((_st getOrDefault ["pip", []]) select { ((((_x select 0) getVariable ["COMSPEC_ATAK_TocScr", []]) param [3, ""]) isEqualTo "TOC") });
        private _bar = _ui get "bar";
        private _nAl = {(_x param [2, ""]) in ["FLASH", "URGENT"] && {serverTime - (_x param [7, -1e9]) < 1200}} count _events;
        private _sMsg = ["", format ["   <t color='#7fd0b0'>%1</t>", [_msg select 0] call _esc]] select (diag_tickTime < (_msg select 1));
        private _sPip = [format ["<t color='%2'>%1 alerte(s) sur 20 min</t>", _nAl, ["#6f7773", "#e05a4a"] select (_nAl > 0)], format ["<t color='#e0b040'>PIP : %1 écran(s)</t>", _pipN]] select (_pipN > 0);
        _bar ctrlSetStructuredText parseText ((format ["<t size='0.85'>  RÉSEAU  <t color='#5cc76b'>%1 en ligne</t> · <t color='#e0b040'>%2 anciens</t> · <t color='#9aa39f'>%3 perdus</t> · <t color='#6f7773'>%4 hors ligne</t>   <t color='#8a9a93'>%5 joueurs · %6 IA · %7 relais · %8 brouilleurs</t>   %10%9</t>",
            _cnt select 0, _cnt select 1, _cnt select 2, _cnt select 3, _players, _ais, _relays, _jam, _sMsg, _sPip]) call comspec_atak_native_fnc_tr);
        // Écran étroit (4:3) : la ligne d'état garde l'essentiel sur une ligne, sans texte coupé.
        if ((ctrlTextHeight _bar) > ((ctrlPosition _bar) select 3) * 1.1) then {
            _bar ctrlSetStructuredText parseText ((format ["<t size='0.85'>  <t color='#5cc76b'>%1 en ligne</t> · <t color='#e0b040'>%2 anciens</t> · <t color='#9aa39f'>%3 perdus</t>%4</t>",
                _cnt select 0, _cnt select 1, (_cnt select 2) + (_cnt select 3), [_sPip, _sMsg] select (_sMsg isNotEqualTo "")]) call comspec_atak_native_fnc_tr);
        };
        // FLASH du poste (fn_tocRoom) : la ligne d'état passe en rouge pendant 3 minutes.
        private _fl = ["flash", _side] call comspec_atak_native_fnc_tocBoard;
        _bar ctrlSetBackgroundColor ([[0.06, 0.08, 0.07, 1], [0.55, 0.07, 0.05, 1]] select ((count _fl) > 0));
        if ((count _fl) > 0) then {
            _bar ctrlSetStructuredText parseText ((format ["<t size='0.9'>  <t font='COMSPEC_BarlowBold'>FLASH</t> · %1 <t color='#ffd0c8'>· %2 · %3Z</t></t>", [_fl select 0] call _esc, [_fl select 1] call _esc, _fl select 2]) call comspec_atak_native_fnc_tr);
        };
        private _groupsL = [_nodes] call _groups;
        private _nOrd = count (values ((uiNamespace getVariable ["COMSPEC_ATAK_Data", createHashMap]) getOrDefault ["localTasks", createHashMap]) select { (_x getOrDefault ["issuerUid", ""]) isEqualTo getPlayerUID player });
        // Compteurs des onglets : libellé mesuré de nouveau seulement quand il change.
        {
            _x params ["_k", "_n"];
            private _lt = _k call _tr;
            ["tab" + _k, [_lt, format ["%1 (%2)", _lt, _n]] select (_n > 0), "", true] call _relabel; // i18n:brut (déjà traduit)
        } forEach [["GROUPES", count _groupsL], ["ORBAT", ((["store"] call comspec_atak_native_fnc_bftOrbat) get "total")], ["FLUX", count _events], ["ORDRES", _nOrd], ["CAMP", 0], ["RÉSEAU", 0], ["DEMANDES", { (_x select 7) isEqualTo "RECUE" } count (_room select 5)], ["CHRONOS", count (_room select 1)]];
        // Aide de l'onglet ou du contrôle survolé (raison d'un bouton grisé comprise).
        ["help"] call _fn;
        // Listes reconstruites seulement quand leur contenu change (sélection et défilement gardés).
        private _sig = str [_tab, _groupsL apply { [_x select 0, _x select 3, _x select 5, _x select 6] }, count _events, _events param [(count _events) - 1, []]];
        private _changed = _sig isNotEqualTo (_st getOrDefault ["sig", ""]);
        _st set ["sig", _sig];
        switch (_tab) do {
            case "GROUPES": {
                private _lb = _ui get "gList";
                if (_changed) then {
                    _st set ["mute", true];
                    lbClear _lb;
                    {
                        _x params ["_gn", "", "_ld", "_np", "_nAll", "_n", "_best"];
                        private _i = _lb lbAdd ((format ["%1 · %2 · %3 nœud(s) ATAK", _gn, [format ["%1 joueur(s)", _np], format ["%1 IA", _nAll]] select (_np isEqualTo 0), _n]) call comspec_atak_native_fnc_tr);
                        _lb lbSetData [_i, _gn];
                        _lb lbSetColor [_i, [[0.36, 0.85, 0.42, 1], [0.88, 0.69, 0.25, 1], [0.6, 0.64, 0.62, 1], [0.44, 0.47, 0.45, 1], [0.55, 0.55, 0.55, 1]] select ((_best min 4) max 0)];
                        if (_gn isEqualTo (_st getOrDefault ["selG", ""])) then { _lb lbSetCurSel _i; };
                    } forEach _groupsL;
                    if ((count _groupsL) isEqualTo 0) then { _lb lbAdd (("Aucun groupe du camp") call comspec_atak_native_fnc_tr); };
                    _st set ["mute", false];
                };
                private _g = _groupsL select { (_x select 0) isEqualTo (_st getOrDefault ["selG", ""]) };
                (_ui get "gInfo") ctrlSetStructuredText parseText (((if ((count _g) isEqualTo 0) then {
                    format ["<t size='0.9' color='#8a9a93'>%1 groupe(s) dans le camp. Choisissez un groupe : la carte se centre sur lui, puis ORDRE AU GROUPE ou MESSAGE AU CHEF.</t>", count _groupsL]
                } else {
                    (_g select 0) params ["_gn", "", "_ld", "_np", "_nAll", "_n", "_best", "_p"];
                    private _ao = (missionNamespace getVariable ["COMSPEC_ATAK_AiOrds", createHashMap]) getOrDefault [["netId", group _ld] call comspec_atak_native_fnc_aiOrder, createHashMap];
                    format ["<t size='1.05' font='COMSPEC_BarlowBold'>%1</t><br/><t size='0.9'>Chef %2 %3<br/>%4 vivant(s) dont %5 joueur(s) · %6 nœud(s) ATAK<br/>%7%8</t>",
                        [_gn] call _esc, [name _ld] call _esc, ["", format [" · n° %1", ([_ld] call comspec_atak_native_fnc_phoneIdent) select 0]] select (isPlayer _ld), _nAll, _np, _n,
                        // if, pas [a, b] select : les deux branches seraient évaluées et _p vide (groupe sans ATAK) planterait le rafraîchissement.
                        if ((count _p) < 2) then { "<t color='#e0b040'>Position inconnue du poste : aucun ATAK dans ce groupe</t>" } else {
                            format ["Grille %1 · %2", [[_p select 0, _p select 1, 0], 6] call comspec_atak_native_fnc_gridRef, ["en direct", "point ancien", "point perdu", "téléphone hors ligne"] select ((_best min 3) max 0)] },
                        if (_np > 0) then { "" } else { format ["<br/><t color='#e0b040'>Ordre IA : %1</t>", [["aucun", format ["%1 (%2)", _ao get "lbl", _ao getOrDefault ["text", ""]]] select ("lbl" in _ao)] call _esc] }]
                })) call comspec_atak_native_fnc_tr);
            };
            case "FLUX": {
                private _lb = _ui get "fList";
                if (_changed) then {
                    _st set ["mute", true];
                    lbClear _lb;
                    private _fKind = _st getOrDefault ["fKind", ""];
                    for "_k" from ((count _events) - 1) to 0 step -1 do {
                        (_events select _k) params [["_time", ""], ["_kind", ""], ["_prio", ""], ["_who", ""], ["_grid", ""], ["_text", ""], ["_p", []], "", ["_old", false]];
                        private _isAlert = _prio in ["FLASH", "URGENT"] || {_kind in ["TIC", "SOS", "MAN DOWN", "CONTACT", "MEDEVAC", "PANIQUE"]};
                        private _isMsg = _kind in ["TOC", "ORDRE", "ORDER"];
                        if (_fKind isEqualTo "A" && {!_isAlert}) then { continue };
                        if (_fKind isEqualTo "M" && {!_isMsg}) then { continue };
                        if (_fKind isEqualTo "R" && {_isMsg || {_isAlert}}) then { continue };
                        private _i = _lb lbAdd ((format ["%1  %2%3 · %4%5", _time, _kind, ["", " " + _prio] select (_prio isNotEqualTo "" && {_prio isNotEqualTo "ROUTINE"}), _who, ["", " · " + _grid] select (_grid isNotEqualTo "")]) call comspec_atak_native_fnc_tr);
                        _lb lbSetData [_i, str _k];
                        _lb lbSetColor [_i, switch (true) do { case (_old): { [0.45, 0.48, 0.46, 1] }; case (_prio isEqualTo "FLASH"): { [1, 0.36, 0.3, 1] }; case (_prio isEqualTo "URGENT"): { [0.88, 0.69, 0.25, 1] }; default { [0.85, 0.89, 0.86, 1] }; }];
                        if ((str _k) isEqualTo (_st getOrDefault ["selF", ""])) then { _lb lbSetCurSel _i; };
                    };
                    if ((count _events) isEqualTo 0) then { _lb lbAdd (("Aucun compte rendu ni message pour l'instant") call comspec_atak_native_fnc_tr); };
                    _st set ["mute", false];
                };
                private _i = parseNumber (_st getOrDefault ["selF", "-1"]);
                (_ui get "fInfo") ctrlSetStructuredText parseText (((if (_i < 0 || {_i >= (count _events)}) then {
                    "<t size='0.9' color='#8a9a93'>Comptes rendus, alertes, MEDEVAC, BDA, ordres et messages du camp (30 derniers, gardés d'une partie à l'autre).</t>"
                } else {
                    (_events select _i) params [["_time", ""], ["_kind", ""], ["_prio", ""], ["_who", ""], ["_grid", ""], ["_text", ""], ["_p", []], "", ["_old", false]];
                    format ["<t size='1.05' font='COMSPEC_BarlowBold'>%1</t> <t size='0.9' color='#8a9a93'>%2 · %3%4%5</t><br/><t size='0.9'>%6</t>",
                        [_kind] call _esc, _time, [_who] call _esc, ["", " · grille " + _grid] select (_grid isNotEqualTo ""), ["", " · partie précédente"] select _old, [_text] call _esc]
                })) call comspec_atak_native_fnc_tr);
            };
            case "ORBAT": {
                // Même lecture et mêmes droits que le BFT du téléphone : l'API ne répond qu'au compte du joueur.
                ["load"] call comspec_atak_native_fnc_bftOrbat;
                private _store = ["store"] call comspec_atak_native_fnc_bftOrbat;
                (["presence"] call comspec_atak_native_fnc_bftOrbat) params ["", "_onKey"];
                (["notice"] call comspec_atak_native_fnc_bftOrbat) params ["_nTxt"];
                private _people = _store get "people";
                private _lb = _ui get "bList";
                private _bSig = [_store get "rev", keys _onKey];
                if (_bSig isNotEqualTo (_st getOrDefault ["bSig", []]) || {(lbSize _lb) isEqualTo 0}) then {
                    _st set ["bSig", _bSig];
                    _st set ["mute", true];
                    lbClear _lb;
                    {
                        _x params ["", "_ud", "_uName", "_uCode", "_uRows"];
                        private _ui0 = _lb lbAdd format ["%1%2%3", ["", "   ", "      ", "         "] select (_ud min 3), _uName, ["", format ["  (%1)", _uCode]] select (_uCode isNotEqualTo "")];
                        _lb lbSetColor [_ui0, [0.5, 0.72, 0.92, 1]];
                        private _pad = ["   ", "      ", "         ", "            "] select (_ud min 3);
                        {
                            private _pe = _people getOrDefault [_x select 1, []];
                            private _vac = (_x select 0) isEqualTo "v";
                            private _on = !_vac && {(_x select 1) in _onKey};
                            private _r = _lb lbAdd format ["%1%2%3", _pad, ["", (_x select 2) + " : "] select ((_x select 2) isNotEqualTo "" && {!_vac}), _pe param [0, ""]];
                            _lb lbSetData [_r, _x select 1];
                            _lb lbSetTextRight [_r, (([["hors ligne", "poste vacant"] select _vac, "en jeu"] select _on)) call comspec_atak_native_fnc_tr];
                            _lb lbSetColor [_r, [[0.55, 0.6, 0.58, 1], [0.36, 0.85, 0.42, 1]] select _on];
                        } forEach _uRows;
                    } forEach (_store get "units");
                    _st set ["mute", false];
                };
                private _pe = _people getOrDefault [_st getOrDefault ["selB", ""], []];
                (_ui get "bInfo") ctrlSetStructuredText parseText ((if ((count _pe) isEqualTo 0) then {
                    format ["<t size='0.85' color='#8a9a93'>%1</t>", [_nTxt] call _esc]
                } else {
                    private _o = _onKey getOrDefault [_st getOrDefault ["selB", ""], objNull];
                    format ["<t size='1.05' font='COMSPEC_BarlowBold'>%1</t><br/><t size='0.85'>%2</t><br/><t size='0.85' color='%3'>%4</t>", [_pe select 0] call _esc,
                        (((_pe select 5) select [0, 3]) apply { [(_x select 0) + (["", " · " + (_x select 1)] select ((_x select 1) isNotEqualTo ""))] call _esc }) joinString "<br/>",
                        ["#8a9a93", "#5cc76b"] select (!isNull _o), ["Hors ligne ou état non communiqué", "En jeu"] select (!isNull _o)]
                }) call comspec_atak_native_fnc_tr);
            };
            case "RÉSEAU": {
                private _w = windDir;
                (_ui get "rInfo") ctrlSetStructuredText parseText ((format ["<t size='1.05' font='COMSPEC_BarlowBold'>RÉSEAU</t><br/><t size='0.9'><t color='#5cc76b'>%1 en ligne</t> · <t color='#e0b040'>%2 anciens</t> · <t color='#9aa39f'>%3 perdus</t> · <t color='#6f7773'>%4 hors ligne</t><br/>%5 joueurs · %6 IA<br/>%7 relais · %8 brouilleurs actifs</t><br/><br/><t size='1.05' font='COMSPEC_BarlowBold'>MÉTÉO</t><br/><t size='0.9'>Nuages %9 %% · pluie %10 %% · brouillard %11 %%<br/>Vent %12 m/s du %13°</t><br/><br/><t size='0.85' color='#8a9a93'>Carte de couverture, brouilleurs en détail et météo prévue : apps Réseau et Météo du téléphone.</t>",
                    _cnt select 0, _cnt select 1, _cnt select 2, _cnt select 3, _players, _ais, _relays, _jam,
                    round (overcast * 100), round (rain * 100), round (fog * 100), round (vectorMagnitude wind), round ((_w + 180) mod 360)]) call comspec_atak_native_fnc_tr);
            };
            case "ORDRES": {
                private _ot = _ui get "oTarget";
                private _want = _draft getOrDefault ["target", "all||Tous"];
                private _opts = [["Tout le camp", "all||Tous"]] + (_groupsL apply { [format ["Groupe %1", _x select 0], format ["group|%1|%2", _x select 1, _x select 0]] });
                if (_changed || {(lbSize _ot) isEqualTo 0}) then {
                    lbClear _ot;
                    { private _i = _ot lbAdd (((_x select 0)) call comspec_atak_native_fnc_tr); _ot lbSetData [_i, _x select 1]; } forEach _opts;
                    _ot lbSetCurSel ((_opts findIf { (_x select 1) isEqualTo _want }) max 0);
                };
                private _mine = values ((uiNamespace getVariable ["COMSPEC_ATAK_Data", createHashMap]) getOrDefault ["localTasks", createHashMap]) select { (_x getOrDefault ["issuerUid", ""]) isEqualTo getPlayerUID player };
                private _s = format ["<t size='1.05' font='COMSPEC_BarlowBold'>ORDRES ÉMIS</t> <t size='0.85' color='#8a9a93'>%1</t><br/>", count _mine];
                if ((count _mine) isEqualTo 0) then { _s = _s + "<t size='0.85' color='#8a9a93'>Aucun ordre émis depuis ce poste</t>"; };
                {
                    private _acks = ["list", [_x getOrDefault ["id", ""]]] call comspec_atak_native_fnc_orderAck;
                    private _n = createHashMap;
                    { private _k = _y select 1; _n set [_k, (_n getOrDefault [_k, 0]) + 1]; } forEach _acks;
                    _s = _s + format ["<t size='0.85'><t color='#7fd0b0'>%1</t> %2 → %3 · %4<br/><t color='#8a9a93'>   vu %5 · reçu %6 · en cours %7 · terminé %8</t></t><br/>",
                        _x getOrDefault ["time", ""], [_x getOrDefault ["typeLabel", _x getOrDefault ["type", ""]]] call _esc, [["tout le camp", _x getOrDefault ["target", ""]] select ((_x getOrDefault ["target", ""]) isNotEqualTo "")] call _esc,
                        _x getOrDefault ["priority", ""], _n getOrDefault ["SEEN", 0], _n getOrDefault ["ACK", 0], _n getOrDefault ["EXEC", 0], _n getOrDefault ["DONE", 0]];
                } forEach ((_mine select [0 max ((count _mine) - 5)]) apply { _x });
                // Missions de tir du camp (appui feu typé, fn_fireSupport) : texte déjà traduit, ajouté après.
                (_ui get "oInfo") ctrlSetStructuredText parseText (((((_s) call comspec_atak_native_fnc_tr) + (["tocText"] call comspec_atak_native_fnc_fireSupport))) call comspec_atak_native_fnc_tr);
            };
            case "SAT": {
                private _n = _st getOrDefault ["satN", 0];
                // Console de l'opérateur : ce satellite est rendu sur ce poste tant que l'onglet est ouvert (30 s de plus après).
                ["need", [_side, _n]] call comspec_atak_native_fnc_tocSat;
                { (_ui get ("sSlot" + str _forEachIndex)) ctrlSetBackgroundColor ([[0.07, 0.085, 0.075, 1], [0.18, 0.38, 0.21, 1]] select (_forEachIndex isEqualTo _n)); } forEach [0, 1];
                private _s = (_room select 2) param [_n, []];
                private _mine = (_s param [7, ""]) isEqualTo getPlayerUID player;
                private _who = _s param [6, ""];
                // Pilotable : j'ai la main, ou le satellite est libre (la première commande me la donne, fn_tocRoom).
                private _can = _mine || {_who isEqualTo ""};
                private _tex = ["tex", [_side, _n]] call comspec_atak_native_fnc_tocSat;
                private _pic = _ui get "sPic";
                if ((_pic getVariable ["COMSPEC_ATAK_Tex", ""]) isNotEqualTo _tex) then {
                    _pic setVariable ["COMSPEC_ATAK_Tex", _tex];
                    _pic ctrlSetText ([_tex, "#(argb,8,8,3)color(0,0,0,1)"] select (_tex isEqualTo "")); // i18n:brut (texture)
                };
                (_ui get "sWait") ctrlShow (_tex isEqualTo "");
                if (_tex isEqualTo "") then { (_ui get "sWait") ctrlSetStructuredText parseText (("<t align='center' size='1.05' color='#cfd8d3'>Image satellite en cours d'acquisition…</t><br/><t align='center' size='0.8' color='#8a9a93'>Image noire : activez « Image dans l'image » (Options > Vidéo)</t>") call comspec_atak_native_fnc_tr); };
                // Image : AUTO (jour le jour, vision nocturne la nuit), JOUR (éclairci la nuit), NUIT, THERMIQUE (fn_tocSat).
                private _m = _s param [3, "AUTO"];
                private _night = sunOrMoon < 0.5;
                { (_ui get ("s" + _x)) ctrlSetBackgroundColor ([[0.07, 0.085, 0.075, 1], [0.18, 0.38, 0.21, 1]] select (_x isEqualTo _m)); } forEach ["AUTO", "DAY", "NV", "TI"];
                private _lab = ["label", [_side, _n]] call comspec_atak_native_fnc_tocSat;
                (_ui get "sStrip") ctrlSetStructuredText parseText ((format ["<t size='0.85'>  %1 · %2%3</t>", [["<t color='#e0b040'>Lecture seule</t>", "<t color='#5cc76b'>Vous avez la main</t>"] select _mine, "<t color='#7fd0b0'>Libre</t>"] select (_who isEqualTo ""), [_lab] call _esc,
                    ["", "   <t color='#e0b040'>Nuit : JOUR est éclairci ; NUIT ou THERMIQUE montrent mieux</t>"] select (_night && {_m isEqualTo "DAY"})]) call comspec_atak_native_fnc_tr);
                private _aim = ["aim", [_side, _n]] call comspec_atak_native_fnc_tocSat;
                private _where = if ((count _aim) < 2) then { "au-dessus du poste" } else { "grille " + ([[(_aim select 0) select 0, (_aim select 0) select 1, 0], 6] call comspec_atak_native_fnc_gridRef) };
                private _hw = _s param [2, 600];
                (_ui get "sInfo") ctrlSetStructuredText parseText ((format ["<t size='1.05' font='COMSPEC_BarlowBold'>SAT %1</t><br/><t size='0.85'>%2<br/>Largeur de la vue %3 · %4</t>", _n + 1,
                    [[format ["Piloté par %1 : lecture seule", [_who] call _esc], "Vous avez la main"] select _mine, "Libre : la première commande vous donne la main"] select (_who isEqualTo ""),
                    [format ["%1 m", round (2 * _hw)], format ["%1 km", (round (2 * _hw / 100)) / 10]] select (_hw >= 500), _where]) call comspec_atak_native_fnc_tr);
                // Commandes grisées quand un autre opérateur a la main ; la raison s'écrit sur la ligne d'aide au survol.
                private _why = ["", format ["Piloté par %1 : lecture seule. Attendez qu'il rende la main.", _who]] select !_can;
                { (_ui get _x) ctrlEnable _can; (_ui get _x) setVariable ["why", _why]; } forEach ["sAUTO", "sDAY", "sNV", "sTI", "sHome", "sN", "sS", "sW", "sE", "sIn", "sOut", "sFollowGo", "sFollowOff"];
                (_ui get "sTake") ctrlEnable (!_mine);
                (_ui get "sRelease") ctrlEnable _mine;
                (_ui get "sRelease") setVariable ["why", "Vous n'avez pas la main sur ce satellite"];
                // Liste de suivi : groupes puis unités du réseau du camp (jamais une unité sans ATAK).
                private _sf = _ui get "sFollow";
                private _opts = [];
                { _opts pushBackUnique [format ["Groupe %1", _x param [7, ""]], "G|" + (_x param [7, ""])]; } forEach (_nodes select { (_x param [7, ""]) isNotEqualTo "" });
                { _opts pushBack [format ["%1 · %2", _x select 1, _x param [7, ""]], "U|" + (_x select 0)]; } forEach _nodes;
                if ((count _opts) isEqualTo 0) then { _opts = [["Aucun allié sur le réseau ATAK", ""]]; };
                if ((_sf getVariable ["COMSPEC_ATAK_Sig", []]) isNotEqualTo _opts) then {
                    _sf setVariable ["COMSPEC_ATAK_Sig", _opts];
                    private _keep = _sf lbData ((lbCurSel _sf) max 0);
                    lbClear _sf;
                    { private _i = _sf lbAdd ((_x select 0) call comspec_atak_native_fnc_tr); _sf lbSetData [_i, _x select 1]; } forEach _opts;
                    _sf lbSetCurSel ((_opts findIf { (_x select 1) isEqualTo _keep }) max 0);
                };
                (_ui get "sLegend") ctrlSetStructuredText parseText (("<t size='0.8' color='#8a9a93'>Cadres : alliés du réseau ATAK (en couleur : en direct ; en gris : point ancien, perdu ou téléphone hors ligne). Une unité sans ATAK n'apparaît jamais. Molette : zoom · clic : centrer · glisser : déplacer.</t>") call comspec_atak_native_fnc_tr);
            };
            case "CHRONOS": {
                private _tm = _room select 1;
                private _lb = _ui get "tList";
                private _ids = _tm apply { _x select 0 };
                if ((_lb getVariable ["COMSPEC_ATAK_Sig", []]) isNotEqualTo _ids) then {
                    _lb setVariable ["COMSPEC_ATAK_Sig", _ids];
                    lbClear _lb;
                    { private _i = _lb lbAdd ((_x select 1) call comspec_atak_native_fnc_tr); _lb lbSetData [_i, _x select 0]; if ((_x select 0) isEqualTo (_st getOrDefault ["selT", ""])) then { _lb lbSetCurSel _i; }; } forEach _tm;
                    if ((count _tm) isEqualTo 0) then { _lb lbAdd (("Aucun chrono") call comspec_atak_native_fnc_tr); };
                };
                {
                    (["timerText", _x] call comspec_atak_native_fnc_tocRoom) params ["_txt", "_col"];
                    _lb lbSetTextRight [_forEachIndex, _txt];
                    _lb lbSetColorRight [_forEachIndex, _col];
                } forEach _tm;
                (_ui get "tInfo") ctrlSetStructuredText parseText (("<t size='0.8' color='#8a9a93'>Chrono : temps écoulé depuis le lancement. Compte à rebours : temps restant, rouge une fois dépassé. Heure H : temps jusqu'à une heure ZULU (H-), puis depuis (H+), toujours en marche. Les chronos sont communs à tous les PC du camp et gardés d'une partie à l'autre.</t>") call comspec_atak_native_fnc_tr);
            };
            case "RADIO": {
                // Radios de l'opérateur lues dans ACRE2 (fn_mpu5, lecture seule) : canal, fréquences, voyants RX / TX.
                // Pas de bouton d'alternat : ACRE2 et TFAR n'offrent pas d'appel public pour presser l'alternat à la place du joueur.
                private _s = "";
                private _now = ["speakers"] call comspec_atak_native_fnc_mpu5;
                private _rad = ["radios"] call comspec_atak_native_fnc_mpu5;
                if ((count _rad) > 0) then {
                    {
                        _x params ["", "_name", "_ch", "_lbl", "_tx", "_rx", "_ptt", "_cur", "_txing"];
                        private _rxOn = (_now findIf { (_x select 0) isNotEqualTo player && {abs ((_x select 2) - _rx) < 0.001} }) >= 0;
                        _s = _s + format ["<t size='1.05' font='COMSPEC_BarlowBold'>%1</t> <t size='0.85' color='#8a9a93'>canal %2%3</t><br/><t size='0.9'><t color='%4'>● RX</t>   <t color='%5'>● TX</t>   %6 MHz%7 · %8%9</t><br/><br/>",
                            [_name] call _esc, _ch, ["", " · " + ([_lbl] call _esc)] select (_lbl isNotEqualTo ""), ["#3a423e", "#5cc76b"] select _rxOn, ["#3a423e", "#ff5a4a"] select _txing,
                            [_tx toFixed 3, "?"] select (_tx <= 0), ["", format [" (réception %1)", _rx toFixed 3]] select (_rx > 0 && {abs (_rx - _tx) > 0.0005}),
                            [format ["alternat %1", _ptt], "sans alternat"] select (_ptt < 1), ["", " · radio active"] select _cur];
                    } forEach _rad;
                    private _who = _now select { (_x select 0) isNotEqualTo player };
                    if ((count _who) > 0) then {
                        _s = _s + "<t size='0.95' font='COMSPEC_BarlowBold'>EN ÉMISSION</t><br/>";
                        { _s = _s + format ["<t size='0.9'><t color='#5cc76b'>●</t> %1 · %2 MHz%3 · %4 s</t><br/>", [[_x select 0, true] call comspec_atak_native_fnc_unitCallsign] call _esc, (_x select 2) toFixed 3, ["", " · " + ([_x select 3] call _esc)] select ((_x select 3) isNotEqualTo ""), round (_x select 4)]; } forEach _who;
                    };
                } else {
                    _s = switch (true) do {
                        case (!isNil "acre_api_fnc_getCurrentRadioList"): { "<t size='0.9' color='#e0b040'>ACRE2 est chargé, mais vous ne portez aucune radio. Prenez une radio ACRE2 pour voir ses canaux ici.</t>" };
                        case (!isNil "TFAR_fnc_activeSwRadio"): {
                            private _sw = ["api", ["TFAR_fnc_activeSwRadio", [], ""]] call comspec_atak_native_fnc_radioGuard;
                            private _lr = ["api", ["TFAR_fnc_activeLrRadio", [], []]] call comspec_atak_native_fnc_radioGuard;
                            format ["<t size='1.05' font='COMSPEC_BarlowBold'>TFAR</t><br/><t size='0.9'>Courte portée : %1<br/>Longue portée : %2</t>",
                                [[getText (configFile >> "CfgWeapons" >> _sw >> "displayName")] call _esc, "aucune"] select (!(_sw isEqualType "") || {_sw isEqualTo ""}),
                                [[getText (configOf (_lr select 0) >> "displayName")] call _esc, "aucune"] select (!(_lr isEqualType []) || {(count _lr) < 1} || {!((_lr select 0) isEqualType objNull)})]
                        };
                        default { "<t size='0.9' color='#8a9a93'>Aucun mod radio (ACRE2 ou TFAR) sur ce serveur.</t>" };
                    };
                };
                (_ui get "kInfo") ctrlSetStructuredText parseText ((_s + "<br/><t size='0.8' color='#8a9a93'>Voyant RX : un équipier émet sur cette fréquence. Voyant TX : vous émettez. Pour parler, utilisez votre touche d'alternat ACRE2 ou TFAR : le PC ne presse pas l'alternat à votre place.</t>") call comspec_atak_native_fnc_tr);
            };
            case "SALLE": { ["refresh"] call comspec_atak_native_fnc_tocHallUi; };
            case "MAIN COURANTE": {
                private _l = +(_room select 3);
                reverse _l;
                private _lb = _ui get "lList";
                private _sig = [count (_room select 3), (_room select 3) param [(count (_room select 3)) - 1, []]];
                if ((_lb getVariable ["COMSPEC_ATAK_Sig", []]) isNotEqualTo _sig) then {
                    _lb setVariable ["COMSPEC_ATAK_Sig", _sig];
                    lbClear _lb;
                    {
                        _x params ["_t", "_k", "_w", "_txt"];
                        private _i = _lb lbAdd ((format ["%1Z  %2  %3", _t select [0, 8], _k, _txt]) call comspec_atak_native_fnc_tr);
                        _lb lbSetTooltip [_i, (format ["%1Z · %2 · %3", _t, _w, _txt]) call comspec_atak_native_fnc_tr];
                        _lb lbSetColor [_i, (createHashMapFromArray [["FLASH", [1, 0.36, 0.3, 1]], ["DEMANDE", [0.88, 0.69, 0.25, 1]], ["EXCHECK", [0.5, 0.82, 0.69, 1]], ["POSTE", [0.5, 0.72, 0.88, 1]], ["CR", [0.88, 0.69, 0.25, 1]]]) getOrDefault [_k, [0.85, 0.89, 0.86, 1]]];
                    } forEach _l;
                    if ((count _l) isEqualTo 0) then { _lb lbAdd (("Main courante vide") call comspec_atak_native_fnc_tr); };
                };
                (_ui get "lInfo") ctrlSetStructuredText parseText (("<t size='0.8' color='#8a9a93'>Heures en ZULU. S'inscrivent seuls : comptes rendus et alertes reçus, demandes, prises de poste, FLASH, chronos, lignes d'exécution passées. Commune à tous les PC du camp et gardée d'une partie à l'autre.</t>") call comspec_atak_native_fnc_tr);
            };
            case "EXCHECK": {
                private _ex = _room select 4;
                private _lb = _ui get "xList";
                if ((_lb getVariable ["COMSPEC_ATAK_Sig", []]) isNotEqualTo _ex) then {
                    _lb setVariable ["COMSPEC_ATAK_Sig", +_ex];
                    lbClear _lb;
                    {
                        _x params ["_id", "_n", "_ev", "_cw", "_el", "_at", "_done"];
                        private _i = _lb lbAdd ((format ["%1. %2 · %3 · %4", _n, _cw, _ev, _el]) call comspec_atak_native_fnc_tr);
                        _lb lbSetData [_i, _id];
                        _lb lbSetTextRight [_i, [format ["prévu %1", _at], format ["passé %1", _done]] select (_done isNotEqualTo "")];
                        _lb lbSetColor [_i, [[0.85, 0.89, 0.86, 1], [0.36, 0.85, 0.42, 1]] select (_done isNotEqualTo "")];
                        if (_id isEqualTo (_st getOrDefault ["selX", ""])) then { _lb lbSetCurSel _i; };
                    } forEach _ex;
                    if ((count _ex) isEqualTo 0) then { _lb lbAdd (("Aucune ligne") call comspec_atak_native_fnc_tr); };
                };
                (_ui get "xInfo") ctrlSetStructuredText parseText (("<t size='0.8' color='#8a9a93'>PASSÉ inscrit l'heure ZULU réelle et une entrée à la main courante. La liste se prépare aussi dans le module Écran TOC (Eden ou Zeus). Commune au camp et gardée d'une partie à l'autre.</t>") call comspec_atak_native_fnc_tr);
            };
            case "PERTES": {
                (["boardText", ["KILLS", _room, _side]] call comspec_atak_native_fnc_tocBoard) params ["_l"];
                // Rien de compté : un texte d'attente plutôt qu'un tableau vide.
                if ((count (_room select 8)) isEqualTo 0) then { _l = _l + "<br/><t size='0.8' color='#8a9a93'>Aucune perte ni résultat pour l'instant : les comptes rendus du réseau (BDA, porteur d'ATAK tué) et la correction à la main les inscrivent ici.</t>"; };
                (_ui get "kcInfo") ctrlSetStructuredText parseText ((_l) call comspec_atak_native_fnc_tr);
                private _ce = _ui get "kcEl";
                private _els = ((["squads", _side] call comspec_atak_native_fnc_tocScreen) + ((_room select 8) apply { _x select 0 }));
                _els = _els arrayIntersect _els;
                if ((_ce getVariable ["COMSPEC_ATAK_Sig", []]) isNotEqualTo _els) then {
                    _ce setVariable ["COMSPEC_ATAK_Sig", _els];
                    private _keep = _ce lbData ((lbCurSel _ce) max 0);
                    lbClear _ce;
                    { private _i = _ce lbAdd ((_x) call comspec_atak_native_fnc_tr); _ce lbSetData [_i, _x]; } forEach _els;
                    _ce lbSetCurSel ((_els find _keep) max 0);
                };
            };
            case "DEMANDES": {
                private _rq = +(_room select 5);
                reverse _rq;
                private _lb = _ui get "qList";
                private _sig = _rq apply { [_x select 0, _x select 7] };
                private _stLbl = createHashMapFromArray [["RECUE", "REÇUE"], ["PRISE", "PRISE EN COMPTE"], ["REFUSEE", "REFUSÉE"], ["TERMINEE", "TERMINÉE"]];
                if ((_lb getVariable ["COMSPEC_ATAK_Sig", []]) isNotEqualTo _sig) then {
                    _lb setVariable ["COMSPEC_ATAK_Sig", _sig];
                    lbClear _lb;
                    {
                        _x params ["_id", "_t", "_k", "_by", "", "_grid", "", "_stt"];
                        private _i = _lb lbAdd ((format ["%1Z  %2 · %3%4", _t, _k, _by, ["", " · " + _grid] select (_grid isNotEqualTo "")]) call comspec_atak_native_fnc_tr);
                        _lb lbSetData [_i, _id];
                        _lb lbSetTextRight [_i, (_stLbl getOrDefault [_stt, _stt]) call comspec_atak_native_fnc_tr];
                        _lb lbSetColor [_i, (createHashMapFromArray [["RECUE", [0.88, 0.69, 0.25, 1]], ["PRISE", [0.5, 0.72, 0.88, 1]], ["REFUSEE", [1, 0.36, 0.3, 1]], ["TERMINEE", [0.36, 0.85, 0.42, 1]]]) getOrDefault [_stt, [0.85, 0.89, 0.86, 1]]];
                        if (_id isEqualTo (_st getOrDefault ["selQ", ""])) then { _lb lbSetCurSel _i; };
                    } forEach _rq;
                    if ((count _rq) isEqualTo 0) then { _lb lbAdd (("Aucune demande reçue") call comspec_atak_native_fnc_tr); };
                };
                private _q = _rq select { (_x select 0) isEqualTo (_st getOrDefault ["selQ", ""]) };
                (_ui get "qInfo") ctrlSetStructuredText parseText ((if ((count _q) isEqualTo 0) then {
                    "<t size='0.8' color='#8a9a93'>Les demandes envoyées par les téléphones du camp (MEDEVAC, appel de feu, ravitaillement) arrivent ici. Choisissez-en une, écrivez une réponse si besoin, puis son statut : il part au téléphone du demandeur.</t>"
                } else {
                    (_q select 0) params ["", "_t", "_k", "_by", "", "_grid", "_txt", "_stt", "_ans"];
                    format ["<t size='1.05' font='COMSPEC_BarlowBold'>%1</t> <t size='0.85' color='#8a9a93'>%2Z · %3%4 · %5</t><br/><t size='0.9'>%6</t>%7", [_k] call _esc, _t, [_by] call _esc,
                        ["", " · grille " + _grid] select (_grid isNotEqualTo ""), _stLbl getOrDefault [_stt, _stt], [_txt] call _esc, ["", format ["<br/><t size='0.85' color='#7fd0b0'>Réponse : %1</t>", [_ans] call _esc]] select (_ans isNotEqualTo "")]
                }) call comspec_atak_native_fnc_tr);
            };
            case "CAMP": {
                private _cd = _ui get "cDest";
                private _want = _draft getOrDefault ["dest", ""];
                private _opts = [["Tout le camp", ""]];
                { private _ld = _x select 2; if (isPlayer _ld && {_ld isNotEqualTo player}) then { _opts pushBack [format ["Chef de %1 · %2", _x select 0, name _ld], getPlayerUID _ld]; }; } forEach _groupsL;
                // Destinataire choisi dans l'onglet ORBAT, pas chef de groupe : ajouté, sinon le message partirait à tout le camp.
                if (_want isNotEqualTo "" && {(_opts findIf { (_x select 1) isEqualTo _want }) < 0}) then {
                    private _wu = allPlayers select { (getPlayerUID _x) isEqualTo _want };
                    if ((count _wu) > 0) then { _opts pushBack [name (_wu select 0), _want]; };
                };
                if (_changed || {(lbSize _cd) isEqualTo 0}) then {
                    lbClear _cd;
                    { private _i = _cd lbAdd (((_x select 0)) call comspec_atak_native_fnc_tr); _cd lbSetData [_i, _x select 1]; } forEach _opts;
                    _cd lbSetCurSel ((_opts findIf { (_x select 1) isEqualTo _want }) max 0);
                };
                private _tm = _events select { (_x param [1, ""]) isEqualTo "TOC" };
                private _s = "<t size='1.05' font='COMSPEC_BarlowBold'>MESSAGES DU POSTE</t><br/>";
                if ((count _tm) isEqualTo 0) then { _s = _s + "<t size='0.85' color='#8a9a93'>Aucun message envoyé au camp</t>"; };
                private _last = _tm select [0 max ((count _tm) - 5)];
                reverse _last;
                { _s = _s + format ["<t size='0.85'><t color='#7fd0b0'>%1</t> %2 · %3<br/>   %4</t><br/>", _x param [0, ""], _x param [2, ""], [_x param [3, ""]] call _esc, [(_x param [5, ""]) select [0, 120]] call _esc]; } forEach _last;
                (_ui get "cInfo") ctrlSetStructuredText parseText ((_s) call comspec_atak_native_fnc_tr);
                private _cc = _ui get "cCam";
                private _srcs = [["Carte et flux du poste", ""]] + ((["sources"] call comspec_atak_native_fnc_tocCam) apply { [_x select 0, _x select 1] });
                // Liste refaite seulement quand les caméras du camp changent (le choix en cours reste sélectionné).
                if ((_cc getVariable ["COMSPEC_ATAK_CamSig", []]) isNotEqualTo _srcs) then {
                    _cc setVariable ["COMSPEC_ATAK_CamSig", _srcs];
                    private _keep = _cc lbData ((lbCurSel _cc) max 0);
                    lbClear _cc;
                    { private _i = _cc lbAdd ((_x select 0) call comspec_atak_native_fnc_tr); _cc lbSetData [_i, _x select 1]; } forEach _srcs;
                    _cc lbSetCurSel ((_srcs findIf { (_x select 1) isEqualTo _keep }) max 0);
                };
            };
        };
        // Ce que regarde l'opérateur, pour les écrans TOC en PIP (fn_tocScreen, affichage « TOC »).
        private _map = _ui get "map";
        (ctrlPosition _map) params ["_mx", "_my", "_mw", "_mh"];
        private _c = _map ctrlMapScreenToWorld [_mx + _mw / 2, _my + _mh / 2];
        private _e = _map ctrlMapScreenToWorld [_mx + _mw, _my + _mh / 2];
        private _hw = (round ((((_c distance2D _e) max 100) min (worldSize max 1000)) / 10)) * 10;
        private _sel = [_st getOrDefault ["selG", ""], _st getOrDefault ["selF", ""]] select (_tab isEqualTo "FLUX");
        private _dr = if (_tab isEqualTo "ORDRES") then {
            private _k = _draft getOrDefault ["kind", "MOVE"];
            format ["%1 → %2", (_kinds select ((_kinds findIf { (_x select 1) isEqualTo _k }) max 0)) select 0, ((_draft getOrDefault ["target", "all||Tous"]) splitString "|") param [2, "Tous"]]
        } else { "" };
        private _view = [_side, [(round ((_c select 0) / 10)) * 10, (round ((_c select 1) / 10)) * 10], _hw, _tab, _sel, _cs, name player, _dr, _draft getOrDefault ["pos", []]];
        if (_view isNotEqualTo (player getVariable ["COMSPEC_ATAK_TocView", []]) && {diag_tickTime - (_st getOrDefault ["pubAt", -10]) >= 2}) then {
            _st set ["pubAt", diag_tickTime];
            player setVariable ["COMSPEC_ATAK_TocView", _view, true];
        };
    };
    case "draw": {
        private _map = _a;
        // Traits, plan du camp, zones et tracé en cours, comme la carte de l'ATAK (fn_tocDraw).
        ["draw", _map] call comspec_atak_native_fnc_tocDraw;
        private _feed = call _feedOf;
        _feed params [["_ft", -1], ["_nodes", []], ["_events", []]];
        // Taille des textes : celle d'un écran de PC, jamais sous le plancher en pixels réels des cartes du téléphone
        // (Réglages > Carte, « Taille minimale des textes », fn_mapLbl).
        private _ts = (0.032 * (safeZoneH / 1.2)) max (((profileNamespace getVariable ["COMSPEC_ATAK_MapTextMinPx", 14]) max 12) * 1.1 * pixelH);
        private _selG = _st getOrDefault ["selG", ""];
        // Poste TOC.
        private _o = _st getOrDefault ["obj", objNull];
        private _clear = "#(argb,8,8,3)color(0,0,0,0)";
        // Étiquettes posées une à une : celle qui chevaucherait une étiquette déjà posée n'est pas écrite (icône seule).
        private _taken = [];
        private _lbl = {
            params ["_pos", "_txt", ["_k", 1]];
            if (_txt isEqualTo "") exitWith { "" };
            private _sp = _map ctrlMapWorldToScreen _pos;
            private _w = (count _txt) * _ts * 0.42 * _k + _ts;
            private _r = [(_sp select 0) + _w / 2, _sp select 1, _w, _ts * 1.1 * _k];
            if ((_taken findIf { abs ((_x select 0) - (_r select 0)) < ((_x select 2) + (_r select 2)) / 2 && {abs ((_x select 1) - (_r select 1)) < ((_x select 3) + (_r select 3)) / 2} }) >= 0) exitWith { "" };
            _taken pushBack _r;
            _txt
        };
        // Objectif et poste réservent leur place d'abord ; texte blanc cerné de noir, lisible sur tous les fonds.
        private _text = {
            params ["_pos", "_txt", ["_rgba", [1, 1, 1, 1]], ["_w", 30]];
            if (_txt isNotEqualTo "") then { _map drawIcon [_clear, _rgba, _pos, _w, _w, 0, _txt, 2, _ts, "RobotoCondensedBold", "right"]; };
        };
        private _op = _draft getOrDefault ["pos", []];
        private _opTxt = if ((count _op) >= 2) then { [[_op select 0, _op select 1, 0], "OBJECTIF"] call _lbl } else { "" };
        if (!isNull _o) then {
            private _tocPos = getPosASL _o;
            _map drawIcon ["\A3\ui_f\data\map\markers\nato\b_hq.paa", _sideRgb + [1], _tocPos, 30, 30, 0, "", 0];
            [_tocPos, [_tocPos, "TOC"] call _lbl] call _text;
        };
        // Comptes rendus et alertes géolocalisés des 20 dernières minutes : dessinés après les nœuds (les noms d'abord).
        private _evs = [];
        {
            _x params ["", ["_kind", ""], ["_prio", ""], "", "", "", ["_p", []], ["_at", -1e9]];
            if ((count _p) >= 2 && {serverTime - _at < 1200} && {!(_st getOrDefault ["hideev", false])}) then {
                _evs pushBack [[[1, 0.55, 0.2, 1], [1, 0.3, 0.22, 1]] select (_prio in ["FLASH", "URGENT"]), [_p select 0, _p select 1, 0], _kind];
            };
        } forEach _events;
        // Nœuds ATAK du camp : symbole OTAN ami, couleur du camp ; ancien pâli, perdu et hors ligne en gris.
        // Groupe choisi puis nœuds en direct d'abord : ce sont eux qui gardent leur nom quand la carte est chargée.
        _nodes = [_nodes, [], { [(_x select 4) min 3, -10] select (((_x param [7, ""]) isEqualTo _selG) && {_selG isNotEqualTo ""}) }, "ASCEND"] call BIS_fnc_sortBy;
        {
            _x params ["", "_cs", "_p", "_dir", "_fr", "_kind", "_ai", ["_grp", ""], ["_n", 1]];
            if (_ai && {_st getOrDefault ["hideai", false]} && {_grp isNotEqualTo _selG}) then { continue };
            private _tex = format ["\A3\ui_f\data\map\markers\nato\b_%1.paa", (createHashMapFromArray [["veh", "motor_inf"], ["air", "air"], ["boat", "naval"], ["armor", "armor"]]) getOrDefault [_kind, "inf"]];
            private _col = switch (_fr) do {
                case 0: { _sideRgb + [1] };
                case 1: { (_sideRgb apply { _x * 0.75 }) + [0.8] };
                case 2: { [0.62, 0.62, 0.62, 0.85] };
                default { [0.45, 0.45, 0.45, 0.75] };
            };
            private _pos = [_p select 0, _p select 1, 0];
            if (_grp isEqualTo _selG && {_selG isNotEqualTo ""}) then {
                _map drawIcon ["\A3\ui_f\data\map\markers\military\circle_CA.paa", [1, 0.85, 0.3, 0.9], _pos, 44, 44, 0, "", 0];
            };
            // « IA » seulement sur le groupe choisi : la carte n'est plus couverte de suffixes.
            private _txt = _cs + (["", format [" +%1", _n - 1]] select (_n > 1)) + (["", " · IA"] select (_ai && {_grp isEqualTo _selG})) + (["", " · ancien", " · perdu", " · hors ligne"] select (_fr min 3));
            _map drawIcon [_tex, _col, _pos, 28, 28, 0, "", 0];
            [_pos, [_pos, _txt] call _lbl] call _text;
            if (_fr < 2) then { _map drawArrow [_pos, _pos getPos [45, _dir], _col]; };
        } forEach _nodes;
        { _x params ["_c", "_p", "_k"]; _map drawIcon ["\A3\ui_f\data\map\markers\military\warning_CA.paa", _c, _p, 22, 22, 0, "", 0]; [_p, [_p, _k, 0.85] call _lbl, _c, 22] call _text; } forEach _evs;
        // Ordres aux groupes IA du camp (fn_aiOrder).
        ["draw", [_map, _ts * 0.9, "RobotoCondensedBold"]] call comspec_atak_native_fnc_aiOrder;
        // Appui feu typé (fn_fireSupport) : missions de tir du camp, portées et distance de sécurité.
        ["draw", [_map, _ts * 0.9, "RobotoCondensedBold"]] call comspec_atak_native_fnc_fireSupport;
        // UCAV commandés du camp (fn_ucav) : orbite, zone de Rechercher et tuer, frappe en cours.
        ["draw", _map] call comspec_atak_native_fnc_ucav;
        // Objectif de l'ordre en cours de rédaction (clic droit).
        if ((count _op) >= 2) then {
            private _opP = [_op select 0, _op select 1, 0];
            _map drawIcon ["\A3\ui_f\data\map\markers\military\objective_CA.paa", [1, 0.85, 0.2, 1], _opP, 34, 34, 0, "", 0];
            [_opP, _opTxt, [1, 0.9, 0.5, 1], 34] call _text;
        };
        // Mesure A-B, et résultat de la recherche (15 s).
        private _ms = _st getOrDefault ["measure", []];
        if ((count _ms) >= 1) then {
            private _pa = [(_ms select 0) select 0, (_ms select 0) select 1, 0];
            _map drawIcon ["\A3\ui_f\data\map\markers\military\dot_CA.paa", [1, 1, 1, 1], _pa, 14, 14, 0, "", 0];
            if ((count _ms) >= 2) then {
                private _pb = [(_ms select 1) select 0, (_ms select 1) select 1, 0];
                _map drawLine [_pa, _pb, [1, 1, 1, 1]];
                _map drawIcon ["\A3\ui_f\data\map\markers\military\dot_CA.paa", [1, 1, 1, 1], _pb, 14, 14, 0, "", 0];
                private _dm = _pa distance2D _pb;
                [_pb, format ["%1 · %2°", [format ["%1 m", round _dm], format ["%1 km", (_dm / 1000) toFixed 2]] select (_dm >= 1000), round (_pa getDir _pb)]] call _text;
            };
        };
        (_st getOrDefault ["found", [[], "", 0]]) params ["_fp", "_fl", "_fu"];
        if ((count _fp) >= 2 && {diag_tickTime < _fu}) then {
            private _fpos = [_fp select 0, _fp select 1, 0];
            _map drawIcon ["\A3\ui_f\data\map\markers\military\circle_CA.paa", [1, 0.85, 0.3, 1], _fpos, 44, 44, 0, "", 0];
        };
    };
};
true
