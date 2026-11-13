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
      - SAT (salle TOC, 11/11 ; console ISR, retour de TTRD « faut vraiment ce rendu TOC SAT ») : vue satellite en direct
        à la place de la carte (fn_tocSat), 16:9 rognée pour remplir la zone, HUD du capteur incrusté (coins, réticule,
        échelle, lignes de données), pistes amies du réseau, marqueurs, quadrillage. Console : SAT 1 / SAT 2, prise de main
        (une main par satellite ; toute commande d'un satellite libre la donne), POINTAGE (grille tapée, clic sur la
        carte, suivi d'un groupe ou d'une unité, inscription à la main courante, repère du camp), zoom continu 0,25× à 8×
        (boutons ou molette), CAPTEUR (AUTO, JOUR, NUIT, WHOT, BHOT, FUS W, FUS B), DÉPLACER (ou glisser l'image, clic pour
        centrer), CALQUES de ce PC, dérive et suivi du relief, ÉCRAN ISR (un écran ou une dalle de la salle reçoit la vue) ;
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
    Refonte 4.9.0 (retours de TTRD : « l'APP POSTE TOC est totalement à refaire », « tout le TOC doit être utilisable
    sans téléphone ») : console plein écran, rail des quatre rubriques de la console web (SITUATION, CONDUITE, PLANS,
    SALLE ; F1 à F4), une rangée d'onglets par rubrique, en-tête (poste, salle, opérateur, alertes, heures, liaisons).
    Toutes les unités du camp sont lues dans le jeu (fn_tocAppData, « source directe ») et fusionnées avec le flux des
    téléphones : rien ne dépend d'un téléphone. Onglets CARTE (calques, fiche d'unité, suivi, trace), DESSIN et PLAN
    (fn_tocApp), MUR (fn_tocWall : préréglages, diffusion, mises en page et luminosité de la 4.8.0). Zoom par boutons,
    molette et pavé numérique ; échelle et nord dessinés sur la carte. Audit : docs/audit/2026-11-poste-toc-app.md.
    Modes (client) :
      ["section", nom]  "SITUATION" | "CONDUITE" | "PLANS" | "SALLE" : dernier onglet ouvert de la rubrique
      ["open", objet]   ouvre le PC du poste          ["load", display] / ["unload"] (display COMSPEC_RscTocLaptop)
      ["refresh"]       chaque seconde                ["draw", carte] (Draw de la carte)
      ["tab", nom]      "GROUPES" | "ORBAT" | "FLUX" | "ORDRES" | "CAMP" | "RÉSEAU" | "SAT" | "CHRONOS" | "RADIO" | "SALLE"
                        | "MAIN COURANTE" | "EXCHECK" | "PERTES" | "DEMANDES"
      ["layout"]        pose la mise en page en cours (onglet, outil de la carte)     ["audit"] -> défauts de l'onglet affiché
      ["mk", [classe, clé]] contrôle créé pour fn_tocDraw / fn_tocHallUi (même registre)   ["tip", [contrôle, aide, brut]]
      ["label", [clé, long, court, brut]] libellé d'un bouton changé (mesuré de nouveau)     ["press", clé] boutons du PC
      ["satClick", [u, v]] centre le satellite (clic sur l'image)   ["satOverlay"] HUD du capteur (à chaque image, onglet SAT seulement)
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
// Flux du camp fusionné avec la source directe (unités sans téléphone lues dans le jeu, fn_tocAppData).
private _feedOf = { ["feed", _side] call comspec_atak_native_fnc_tocAppData };
private _esc = { params ["_t"]; if !(_t isEqualType "") then { _t = str _t; }; { _t = [_t, _x select 0, _x select 1] call CBA_fnc_replace; } forEach [["&", "&amp;"], ["<", "&lt;"], [">", "&gt;"]]; _t };
// Thème TOC (fn_tocTheme, docs/TOC-THEME.md) : seule source des couleurs du PC. _tcol : RGBA d'un jeton ; _tink : texte
// structuré écrit avec color='@jeton', traduit puis coloré.
private _tcol = { params ["_k", ["_al", 1]]; ["rgba", [_k, _al]] call comspec_atak_native_fnc_tocTheme };
private _tink = { ["ink", _this call comspec_atak_native_fnc_tr] call comspec_atak_native_fnc_tocTheme };
// Calques de la carte (onglet CARTE, fn_tocApp) : gardés tant que le jeu tourne.
if !("ly" in _st) then { _st set ["ly", createHashMapFromArray [["PL", true], ["AI", true], ["DIR", true], ["VEH", true], ["AIR", true], ["EV", true], ["PLAN", true], ["REL", false], ["COV", false]]]; };
private _ly = _st get "ly";
// Rubriques de la console (les mêmes que la console web /atak/toc, docs/TOC-WEB.md) et leurs onglets.
private _sections = [
    ["SITUATION", ["CARTE", "GROUPES", "ORBAT", "RÉSEAU", "FLUX"]],
    ["CONDUITE", ["ORDRES", "CAMP", "DEMANDES", "CHRONOS", "EXCHECK", "MAIN COURANTE", "PERTES"]],
    ["PLANS", ["DESSIN", "PLAN"]],
    ["SALLE", ["SALLE", "MUR", "SAT", "RADIO"]]
];
// Grille du PC, seule source des tailles : fractions de la hauteur d'écran (safeZoneH). Largeurs × pixelW / pixelH.
private _grid = createHashMapFromArray [
    ["gap", 0.005], ["rail", 0.125], ["railBtn", 0.062], ["title", 0.056], ["titleRow", 0.05], ["bar", 0.032], ["tab", 0.032], ["row", 0.034], ["head", 0.034], ["hair", 0.002],
    ["tall", 0.04], ["label", 0.022], ["help", 0.05], ["strip", 0.032], ["toolHint", 0.046], ["info2", 0.05], ["info3", 0.075], ["info5", 0.17],
    ["flexMin", 0.06], ["font", 0.019], ["fontTab", 0.017], ["fontMin", 0.0145], ["panel", 0.36], ["panelMin", 0.5], ["panelMax", 0.62]
];
// Écrans du camp (toute la salle) et écrans du poste (30 m).
private _sideScreens = { (missionNamespace getVariable ["COMSPEC_ATAK_TocScreens", []]) select { !isNull _x && {alive _x} && {((_x getVariable ["COMSPEC_ATAK_TocScr", []]) param [1, ""]) in ["", _side]} } };
private _nearScreens = { (call _sideScreens) select { (player distance _x) < 30 } };
private _showOn = {
    params ["_view"];
    // Écrans de la salle du poste ; sans salle, ceux à moins de 30 m (fn_tocWall).
    private _scr = ["screens"] call comspec_atak_native_fnc_tocWall;
    if ((count _scr) isEqualTo 0) exitWith { ["Aucun écran dans la salle de ce poste ni à moins de 30 m"] call _setStatus; };
    { ["show", [_x, _view]] call comspec_atak_native_fnc_tocScreen; } forEach _scr;
    [format ["%1 écran(s) : %2", count _scr, (createHashMapFromArray [["LOG", "main courante"], ["EXCHECK", "liste d'exécution"], ["KILLS", "pertes et résultats"], ["REQ", "demandes"], ["CLOCK", "horloge et chronos"]]) getOrDefault [_view, _view]]] call _setStatus;
};
private _allTabs = [];
{ _allTabs append (_x select 1); } forEach _sections;
private _secOf = { params ["_t"]; ((_sections select ((_sections findIf { _t in (_x select 1) }) max 0)) select 0) };
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
            private _n = 0; private _best = 9; private _p = []; private _phone = false;
            { _n = _n + (_x param [8, 1]); _best = _best min (_x select 4); if (_p isEqualTo []) then { _p = _x select 2; }; if ((_x param [9, "P"]) isEqualTo "P") then { _phone = true; }; } forEach _mine;
            private _ld = leader _g;
            _out pushBack [_gn, [_ld] call comspec_atak_native_fnc_unitGroup, _ld, {isPlayer _x && {alive _x}} count (units _g), {alive _x} count (units _g), _n, _best, _p, _phone];
        };
    } forEach allGroups;
    _out sort true;
    _out
};
private _setStatus = { params ["_msg"]; _st set ["status", [_msg, diag_tickTime + 6]]; };
// Calques de l'image satellite, propres à ce PC : marqueurs, pistes amies, quadrillage, lignes de données, cadres.
private _satOv = {
    private _o = _st getOrDefault ["satOv", createHashMap];
    if ((count _o) isEqualTo 0) then { _o = createHashMapFromArray [["MRKR", true], ["BFT", true], ["GRID", false], ["DATA", true], ["BOX", true]]; _st set ["satOv", _o]; };
    _o
};
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
    if !(_c isEqualType controlNull) exitWith {};
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

// Modes qui rendent une valeur : le contrôle créé (« mk ») et la liste des défauts (« audit »). Le fichier finit par
// « true » : sans cette variable, « mk » rendait true à fn_tocDraw et fn_tocHallUi, qui le passaient à « tip »
// (setVariable sur un booléen : erreur, et l'ouverture du PC s'arrêtait là, journal de TTRD, 4.7.1).
private _ret = switch (_mode) do {
    case "open": {
        if (!hasInterface || {isNull player} || {!alive player}) exitWith { false };
        if (!isNull (uiNamespace getVariable ["COMSPEC_ATAK_TocLapDisp", displayNull])) exitWith { true };
        _st set ["obj", _a];
        // Le serveur publie le réseau du camp dès qu'un PC est ouvert (fn_tocFeed) ; publié tout de suite.
        player setVariable ["COMSPEC_ATAK_TocView", [_side, [round ((getPosASL _a) select 0), round ((getPosASL _a) select 1)], 1500, "CARTE", "", ["callsign"] call comspec_atak_native_fnc_tocPost, name player, ""], true];
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
        _st set ["tab", _st getOrDefault ["tab", "CARTE"]];
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
            private _c = ["COMSPEC_RscTocLbl", _key] call _mk;
            _c ctrlSetText (_txt call _tr);
            _c
        };
        private _combo = {
            params ["_key", ["_opts", []], ["_tip", ""]];
            private _c = ["COMSPEC_RscTocCombo", _key] call _mk;
            { private _i = _c lbAdd ((_x select 0) call _tr); _c lbSetData [_i, _x select 1]; } forEach _opts;
            if (_tip isNotEqualTo "") then { [_c, _tip] call _tipOf; };
            _c
        };
        private _edit = {
            params ["_key", ["_tip", ""], ["_cls", "COMSPEC_RscTocEdit"]];
            private _c = [_cls, _key] call _mk;
            if (_tip isNotEqualTo "") then { [_c, _tip] call _tipOf; };
            _c
        };
        // Console plein écran (refonte 4.9.0, plus de coque de PC portable autour) : en-tête sur le fond BG1 (poste, alertes,
        // heures et liaisons, FLASH, prise du poste), filet ; rail des quatre rubriques à gauche ; à sa droite les onglets
        // de la rubrique, puis la zone de gauche (carte, satellite, plan de la salle) et le panneau de l'onglet (titre et
        // état, filet, contenu, aide) ; ligne d'état en bas sur toute la largeur. Zones gardées pour la grille (« layout »).
        private _xi = safeZoneX; private _yi = safeZoneY; private _wi = safeZoneW; private _hi = safeZoneH;
        private _gy = (_grid get "gap") * _u; private _gx = (_grid get "gap") * _ux;
        private _hair = (_grid get "hair") * _u;
        private _scrn = ["COMSPEC_RscText", "screen", true] call _mk;
        _scrn ctrlSetPosition [_xi, _yi, _wi, _hi];
        _scrn ctrlSetBackgroundColor (["bg0"] call _tcol);
        _st set ["inner", [_xi, _yi, _wi, _hi]];
        private _titleH = (_grid get "title") * _u;
        private _topH = _titleH + 2 * _gy;
        private _tBg = ["COMSPEC_RscText", "titleBg", true] call _mk;
        _tBg ctrlSetPosition [_xi, _yi, _wi, _topH];
        _tBg ctrlSetBackgroundColor (["bg1"] call _tcol);
        private _tLine = ["COMSPEC_RscText", "titleLine", true] call _mk;
        _tLine ctrlSetPosition [_xi, _yi + _topH, _wi, _hair];
        _tLine ctrlSetBackgroundColor (["line"] call _tcol);
        _st set ["rTitle", [_xi + 2 * _gx, _yi + _gy, _wi - 4 * _gx, _titleH]];
        private _barH = (_grid get "bar") * _u;
        _st set ["rBar", [_xi, _yi + _hi - _barH, _wi, _barH]];
        // Rail des rubriques, du filet de l'en-tête à la ligne d'état.
        private _railW = (_grid get "rail") * _ux;
        private _bodyY = _yi + _topH + 2 * _gy;
        private _bodyH = (_yi + _hi - _barH - _gy) - _bodyY;
        private _rlBg = ["COMSPEC_RscText", "railBg", true] call _mk;
        _rlBg ctrlSetPosition [_xi, _yi + _topH + _hair, _railW, _hi - _topH - _barH - _hair];
        _rlBg ctrlSetBackgroundColor (["bg1"] call _tcol);
        _st set ["rRail", [_xi + _gx, _bodyY, _railW - 2 * _gx, _bodyH]];
        // Onglets de la rubrique, puis corps : gauche (carte, satellite ou plan de la salle), panneau de l'onglet à droite.
        private _tabH = (_grid get "tab") * _u;
        private _bx = _xi + _railW + 2 * _gx;
        _st set ["rTabs", [_bx, _bodyY, (_xi + _wi - 2 * _gx) - _bx, _tabH]];
        private _by = _bodyY + _tabH + 2 * _gy;
        private _bh = (_yi + _hi - _barH - _gy) - _by;
        private _pw = ((((_wi / _ux) * (_grid get "panel")) max (_grid get "panelMin")) min (_grid get "panelMax")) * _ux;
        private _px = _xi + _wi - _pw;
        _st set ["rLeft", [_bx, _by, (_px - _gx) - _bx, _bh]];
        private _pBg = ["COMSPEC_RscText", "rBg", true] call _mk;
        _pBg ctrlSetPosition [_px, _by, _pw, _bh];
        _pBg ctrlSetBackgroundColor (["bg1"] call _tcol);
        private _cx = _px + 2 * _gx; private _cw = _pw - 4 * _gx;
        private _helpH = (_grid get "help") * _u;
        _st set ["rHelp", [_cx, _by + _bh - _gy - _helpH, _cw, _helpH]];
        private _cy = _by + _gy;
        _st set ["rRight", [_cx, _cy, _cw, (_by + _bh - _gy - _helpH - 2 * _gy) - _cy]];
        { (_ui get _x) ctrlCommit 0; } forEach ["screen", "titleBg", "titleLine", "railBg", "rBg"];
        // Filet accent sous l'onglet choisi (posé par « tab ») ; titre du panneau et son filet (posés par la grille).
        (["COMSPEC_RscText", "tabMark", true] call _mk) ctrlSetBackgroundColor (["accent"] call _tcol);
        // Filet accent à gauche de la rubrique choisie, texte du bas du rail (unités, raccourcis), ligne de la carte.
        (["COMSPEC_RscText", "secMark", true] call _mk) ctrlSetBackgroundColor (["accent"] call _tcol);
        ["railInfo"] call _stx;
        ["mapInfo"] call _stx;
        ["pHead"] call _stx;
        (["COMSPEC_RscText", "pRule", true] call _mk) ctrlSetBackgroundColor (["line"] call _tcol);
        // Fond noir de l'image satellite (sous l'image, l'image garde le format 16:9 des écrans).
        (["COMSPEC_RscText", "sBack", true] call _mk) ctrlSetBackgroundColor (["bg0"] call _tcol);
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
        (["dInfo"] call _stx) ctrlSetStructuredText parseText (["ink", format ["<t size='0.8' color='@muted'>%1</t>", ("Réglages de l'outil : choisissez TRAIT, DESSIN, PLAN ou MARQUEUR.") call _tr]] call comspec_atak_native_fnc_tocTheme);
        ["toolHint"] call _stx;
        ["build"] call comspec_atak_native_fnc_tocDraw;
        // GROUPES
        private _gl = ["COMSPEC_RscTocList", "gList"] call _mk;
        _gl ctrlAddEventHandler ["LBSelChanged", { params ["_c", "_i"]; ["pick", ["GROUPES", _c lbData _i]] call comspec_atak_native_fnc_tocLaptop; }];
        ["gInfo"] call _stx;
        // Ordre réel à un groupe IA (fn_aiOrder), au point choisi d'un clic droit sur la carte.
        private _ga = ["gAi", (((["kinds"] call comspec_atak_native_fnc_aiOrder) select { (_x select 0) isNotEqualTo "FOLLOW" }) apply { [_x select 1, _x select 0] }), "Ordre exécuté par le groupe IA choisi, au point du clic droit sur la carte"] call _combo;
        // ORBAT du site
        private _bl = ["COMSPEC_RscTocList", "bList"] call _mk;
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
        private _fl = ["COMSPEC_RscTocList", "fList"] call _mk;
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
        ["oText", "Consigne de l'ordre, 400 signes au plus. Exemple : tenir le carrefour jusqu'à la relève", "COMSPEC_RscTocEditMulti"] call _edit;
        ["oL5", "OBJECTIF (CLIC DROIT SUR LA CARTE OU GRILLE)"] call _lblOf;
        ["oGrid", "Grille de l'objectif : clic droit sur la carte, ou tapez-la"] call _edit;
        ["oInfo"] call _stx;
        // CAMP
        ["cL1", "DESTINATAIRE"] call _lblOf;
        ["cDest"] call _combo;
        private _cp = ["cPrio", ["ROUTINE", "URGENT", "FLASH"] apply { [_x, _x] }] call _combo;
        ["cL2", "MESSAGE"] call _lblOf;
        ["cText", "Message, 400 signes au plus : il arrive dans Messages, conversation TOC du poste", "COMSPEC_RscTocEditMulti"] call _edit;
        ["cL3", "INDICATIF DU POSTE (CONFIG.JSON, TOC.CALLSIGN)"] call _lblOf;
        private _cs = ["cCs", "Indicatif du poste, en capitales (24 signes au plus)"] call _edit;
        _cs ctrlSetText (["callsign"] call comspec_atak_native_fnc_tocPost); // i18n:brut (saisi, réenregistré tel quel)
        // Caméra en direct sur les écrans TOC à côté du poste (fn_tocCam).
        ["cL4", "CAMÉRA SUR LES ÉCRANS DU POSTE"] call _lblOf;
        ["cCam", [], "Les écrans TOC à moins de 30 m montrent cette caméra en direct ; « Carte et flux » les remet à l'affichage du poste"] call _combo;
        ["cInfo"] call _stx;
        // SAT : image satellite à la place de la carte, 16:9 rognée dans un groupe qui la découpe (aucune bande, aucune
        // déformation), bandeau d'état au-dessus ; console à droite (titre de bloc sur sa propre ligne, rien au-dessus).
        ["sStrip"] call _stx;
        (_ui get "sStrip") ctrlSetBackgroundColor (["bg0", 0.7] call _tcol);
        private _clip = ["RscControlsGroupNoScrollbars", "sClip", true] call _mk;
        _ui set ["sPic", _d ctrlCreate ["RscPicture", -1, _clip]];
        ["sInfo"] call _stx;
        ["sL1", "POINTAGE"] call _lblOf;
        ["sGrid", "Grille à viser, 4 à 10 chiffres, puis VISER LA GRILLE. Exemple : 123 456"] call _edit;
        ["sFollow", [], "Le satellite suit le groupe (centre de ses nœuds ATAK) ou l'unité choisie, à la position que le réseau connaît"] call _combo;
        ["sNote", "Note inscrite avec la vue (INSCRIRE) ou nom du repère posé (MARQUER). Exemple : VÉHICULE À L'ARRÊT"] call _edit;
        ["sZoom"] call _stx;
        ["sL2", "CAPTEUR"] call _lblOf;
        ["sL3", "DÉPLACER"] call _lblOf;
        ["sL4", "CALQUES"] call _lblOf;
        ["sL5", "ÉCRAN ISR"] call _lblOf;
        ["sScr", [], "Écran ou dalle de la salle qui reçoit ce satellite (ENVOYER) ou revient à la carte (EFFACER)"] call _combo;
        // CHRONOS : chronos du poste (fn_tocRoom), affichés aussi sur les écrans en affichage horloge.
        private _tl = ["COMSPEC_RscTocList", "tList"] call _mk;
        _tl ctrlAddEventHandler ["LBSelChanged", { params ["_c", "_i"]; if (_i >= 0) then { (uiNamespace getVariable ["COMSPEC_ATAK_TocLap", createHashMap]) set ["selT", _c lbData _i]; }; }];
        ["tL1", "NOUVEAU CHRONO"] call _lblOf;
        ["tName", "Nom affiché sur le tableau, en capitales. Exemple : H-HOUR, ALPHA EN POSITION, RELÈVE"] call _edit;
        (["tKind", [["Chrono (temps écoulé)", "UP"], ["Compte à rebours", "DOWN"], ["Heure H (ZULU)", "AT"]]] call _combo) lbSetCurSel 0;
        ["tVal", "Compte à rebours : minutes:secondes ou heures:minutes:secondes (exemple 30:00). Heure H : heure ZULU HH:MM (exemple 22:30). Chrono : rien à saisir"] call _edit;
        ["tInfo"] call _stx;
        // RADIO : réseaux radio de l'opérateur (ACRE2 / TFAR, lecture seule), un bloc par radio.
        ["kInfo"] call _stx;
        // MAIN COURANTE : entrées du poste (automatiques et à la main), heure ZULU.
        private _ll = ["COMSPEC_RscTocList", "lList"] call _mk;
        _ll ctrlAddEventHandler ["LBSelChanged", { params ["_c", "_i"]; if (_i >= 0) then { (uiNamespace getVariable ["COMSPEC_ATAK_TocLap", createHashMap]) set ["selL", _c lbData _i]; }; }];
        ["lText", "Entrée à la main, inscrite à l'heure ZULU avec votre nom. Exemple : BRAVO signale le pont nord détruit"] call _edit;
        ["lInfo"] call _stx;
        // EXCHECK : liste d'exécution (n°, événement, mot de code, élément, heure prévue, heure réelle).
        private _xl = ["COMSPEC_RscTocList", "xList"] call _mk;
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
        private _ql = ["COMSPEC_RscTocList", "qList"] call _mk;
        _ql ctrlAddEventHandler ["LBSelChanged", { params ["_c", "_i"]; if (_i >= 0) then { (uiNamespace getVariable ["COMSPEC_ATAK_TocLap", createHashMap]) set ["selQ", _c lbData _i]; }; }];
        ["qInfo"] call _stx;
        ["qAns", "Réponse envoyée au téléphone du demandeur avec le statut. Exemple : hélico décolle, ETA 8 min, fumée verte"] call _edit;
        // CARTE : unités du camp (téléphone ou source directe), fiche de l'unité choisie (fn_tocApp).
        private _ul = ["COMSPEC_RscTocList", "uList"] call _mk;
        _ul ctrlAddEventHandler ["LBSelChanged", { params ["_c", "_i"]; if (_i >= 0) then { ["act", ["pickU", _c lbData _i]] call comspec_atak_native_fnc_tocApp; }; }];
        ["uInfo"] call _stx;
        // FLUX : compte rendu rédigé au poste (aucun téléphone).
        ["fL1", "COMPTE RENDU DU POSTE"] call _lblOf;
        (["fKindNew", [["SITREP", "SITREP"], ["CONTACT", "CONTACT"], ["OBSERVATION", "OBSERVATION"], ["LOGISTIQUE", "LOGISTIQUE"]], "Type du compte rendu envoyé au camp au nom du poste"] call _combo) lbSetCurSel 0;
        ["fText", "Texte du compte rendu, 200 signes au plus. Exemple : pont nord détruit, itinéraire BLEU fermé"] call _edit;
        ["fGrid", "Grille du compte rendu : clic droit sur la carte, ou tapez-la. Exemple : 123 456"] call _edit;
        // DEMANDES : demande inscrite au poste (MEDEVAC, appui, ravitaillement), sans téléphone.
        ["qL1", "NOUVELLE DEMANDE"] call _lblOf;
        (["qKind", [["MEDEVAC", "MEDEVAC"], ["Appui feu", "APPUI FEU"], ["Ravitaillement", "RAVITAILLEMENT"], ["Renfort", "RENFORT"], ["Autre demande", "DEMANDE"]], "Genre de la demande inscrite au nom du poste"] call _combo) lbSetCurSel 0;
        ["qGrid", "Grille de la demande : clic droit sur la carte, ou tapez-la. Exemple : 123 456"] call _edit;
        ["qText", "Détail de la demande. Exemple : 1 blessé urgent, zone de poser au sud, fumée verte"] call _edit;
        // PLANS : mode d'emploi des outils (DESSIN), éléments du plan du camp (PLAN).
        ["pHow"] call _stx;
        private _pl = ["COMSPEC_RscTocList", "pList"] call _mk;
        _pl ctrlAddEventHandler ["LBSelChanged", { params ["_c", "_i"]; if (_i >= 0) then { ["act", ["pickP", _c lbData _i]] call comspec_atak_native_fnc_tocApp; }; }];
        ["pInfo"] call _stx;
        // MUR : préréglages, diffusion, mise en page et luminosité des écrans de la salle (fn_tocWall, modèle de la 4.8.0).
        ["wInfo"] call _stx;
        ["wL1", "DIFFUSION D'UN ÉCRAN SUR LES AUTRES"] call _lblOf;
        ["wL2", "SCÈNES DE LA SALLE"] call _lblOf;
        ["wL3", "MISE EN PAGE ET LUMINOSITÉ DE L'ÉCRAN CHOISI"] call _lblOf;
        ["wSrc", [], "Écran source de la diffusion : ce qu'il montre est recopié sur les autres écrans 16:9 de la salle"] call _combo;
        ["wLayout", [], "Mise en page des dalles de l'écran choisi sur le plan. Exemple : Situation + Réseau sur un écran incurvé"] call _combo;
        (["wLum", [["Luminosité 100 %", "100"], ["Luminosité 75 %", "75"], ["Luminosité 50 %", "50"], ["Luminosité 25 %", "25"]], "Luminosité de l'écran choisi sur le plan. Exemple : 50 % pour une salle de nuit"] call _combo) lbSetCurSel 0;
        // SALLE : plan du mur à la place de la carte, gestion de la salle à droite (fn_tocHallUi).
        ["build"] call comspec_atak_native_fnc_tocHallUi;
        // Boutons du PC : une seule table (libellé long, libellé court, aide, principal). Cible : L ce PC (« press »),
        // T onglet, D barre de dessin (fn_tocDraw), H salle (fn_tocHallUi). Libellés jamais vides (AtakTocLaptopLayoutTest).
        private _btnDefs = [
            ["flashGo", "L", "FLASH", "", "Diffuse le FLASH sur tous les écrans et PC du poste du camp", 1],
            ["postGo", "L", "PRENDRE LE POSTE", "POSTE", "Inscrit votre nom au poste (barre du haut des PC, main courante) ; un second clic libère le poste", 0],
            ["close", "L", "FERMER", "", "Ferme le PC (Échap)", 0],
            ["alertsGo", "L", "ALERTES", "", "Ouvre le flux filtré sur les alertes ; le compteur porte sur les 20 dernières minutes", 0],
            ["secSITUATION", "S", "SITUATION", "", "Carte, groupes, ORBAT, réseau et flux du camp (touche F1)", 0],
            ["secCONDUITE", "S", "CONDUITE", "", "Ordres, messages, demandes, chronos, exécution, main courante et pertes (touche F2)", 0],
            ["secPLANS", "S", "PLANS", "", "Dessiner sur la carte et tenir le plan du camp (touche F3)", 0],
            ["secSALLE", "S", "SALLE", "", "Écrans de la salle, mur d'images, satellite et radios (touche F4)", 0],
            ["tabCARTE", "T", "CARTE", "", "Calques de la carte, unités du camp et fiche de l'unité choisie", 0],
            ["tabGROUPES", "T", "GROUPES", "", "Groupes du camp, ordres au groupe et ordres IA", 0],
            ["tabORBAT", "T", "ORBAT", "", "ORBAT du site, membres en jeu ou non", 0],
            ["tabRÉSEAU", "T", "RÉSEAU", "", "État du réseau, relais et météo", 0],
            ["tabFLUX", "T", "FLUX", "", "Comptes rendus, alertes, ordres et messages du camp ; compte rendu du poste", 0],
            ["tabORDRES", "T", "ORDRES", "", "Rédiger un ordre ; ordres émis et réponses", 0],
            ["tabCAMP", "T", "CAMP", "", "Message au camp ou à un chef, indicatif du poste", 0],
            ["tabDEMANDES", "T", "DEMANDES", "", "MEDEVAC, appels de feu et ravitaillements : reçus ou inscrits au poste", 0],
            ["tabCHRONOS", "T", "CHRONOS", "", "Chronos, comptes à rebours et heure H du poste", 0],
            ["tabEXCHECK", "T", "EXCHECK", "", "Liste d'exécution (mots de code)", 0],
            ["tabMAIN COURANTE", "T", "MAIN COURANTE", "MAIN COUR.", "Main courante du poste, en ZULU", 0],
            ["tabPERTES", "T", "PERTES", "", "Pertes et résultats par élément", 0],
            ["tabDESSIN", "T", "DESSIN", "", "Outils de dessin sous la carte : marqueur, trait, dessin libre, graphismes du plan, gomme", 0],
            ["tabPLAN", "T", "PLAN", "", "Éléments du plan du camp : centrer, supprimer, montrer sur les écrans", 0],
            ["tabSALLE", "T", "SALLE", "", "Écrans de la salle TOC : contenu, nom, allumage, liens", 0],
            ["tabMUR", "T", "MUR", "", "Tout le mur d'un geste : préréglages, diffusion, caméras, scènes", 0],
            ["tabSAT", "T", "SAT", "", "Vue satellite en direct : SAT 1 et SAT 2", 0],
            ["tabRADIO", "T", "RADIO", "", "Radios de l'opérateur, en lecture seule", 0],
            ["zoomOut", "L", "ZOOM −", "−", "Éloigne la carte (aussi : molette, touche − du pavé numérique)", 0],
            ["zoomIn", "L", "ZOOM +", "+", "Rapproche la carte (aussi : molette, touche + du pavé numérique)", 0],
            ["framenet", "L", "TOUT VOIR", "TOUT", "Cadre toutes les unités du camp", 0],
            ["frameme", "L", "POSTE", "", "Centre la carte sur le poste", 0],
            ["framesel", "L", "SÉLECTION", "SÉLECT.", "Centre la carte sur le groupe ou le compte rendu choisi", 0],
            ["toolMEASURE", "L", "MESURE", "", "Clic gauche sur A puis sur B", 0],
            ["toolMARKER", "L", "MARQUEUR", "MARQ.", "Clic gauche : pose un marqueur du type choisi dessous", 0],
            ["lyPL", "A", "JOUEURS", "", "Calque des joueurs du camp", 0],
            ["lyAI", "A", "IA", "", "Calque des unités IA du camp", 0],
            ["lyDIR", "A", "SANS TÉLÉPHONE", "SANS TÉL.", "Calque des unités sans ATAK, lues directement dans le jeu (source directe)", 0],
            ["lyVEH", "A", "VÉHICULES", "VÉHIC.", "Calque des véhicules terrestres et des bateaux", 0],
            ["lyAIR", "A", "AÉRONEFS", "AÉRO.", "Calque des hélicoptères, avions et drones", 0],
            ["lyEV", "A", "COMPTES RENDUS", "C.R.", "Calque des comptes rendus et alertes des 20 dernières minutes", 0],
            ["lyPLAN", "A", "PLAN ET TRAITS", "PLAN", "Calque du plan du camp, des traits et des zones", 0],
            ["lyREL", "A", "RELAIS", "", "Calque des relais du réseau", 0],
            ["lyCOV", "A", "COUVERTURE", "COUV.", "Calque de la portée de chaque relais (cercle)", 0],
            ["uFollow", "A", "SUIVRE", "", "La carte reste centrée sur l'unité choisie ; un second clic arrête le suivi", 0],
            ["uMsg", "A", "MESSAGE", "", "Écrit au joueur choisi (onglet CAMP)", 0],
            ["uOrder", "A", "ORDRE", "", "Rédige un ordre pour le groupe de l'unité choisie (onglet ORDRES)", 1],
            ["uTrack", "A", "TRACE", "", "Trace le chemin de l'unité choisie tant que la console est ouverte (120 points, un toutes les 5 s)", 0],
            ["fAdd", "A", "ENVOYER LE COMPTE RENDU", "ENVOYER", "Le compte rendu part au camp, au flux et à la main courante, au nom du poste", 1],
            ["qAdd", "A", "CRÉER LA DEMANDE", "CRÉER", "Inscrit la demande au nom du poste, à la grille écrite à côté", 1],
            ["lQ1", "A", "RAS", "", "Entrée rapide « RAS », suivie du texte saisi au-dessus s'il y en a un", 0],
            ["lQ2", "A", "CONTACT", "", "Entrée rapide « CONTACT », suivie du texte saisi au-dessus s'il y en a un", 0],
            ["lQ3", "A", "EN POSITION", "EN POS.", "Entrée rapide « EN POSITION », suivie du texte saisi au-dessus s'il y en a un", 0],
            ["lQ4", "A", "RELÈVE", "", "Entrée rapide « RELÈVE », suivie du texte saisi au-dessus s'il y en a un", 0],
            ["pCenter", "A", "CENTRER", "", "Centre la carte sur l'élément du plan choisi", 0],
            ["pDel", "A", "SUPPRIMER", "SUPPR.", "Supprime pour tous l'élément du plan choisi", 0],
            ["pShow", "A", "PLAN SUR LES ÉCRANS", "SUR LES ÉCRANS", "Les écrans 16:9 de la salle passent en carte seule : le plan y est dessiné", 1],
            ["pTell", "A", "PRÉVENIR LE CAMP", "PRÉVENIR", "Envoie « plan mis à jour » dans Messages, avec le nombre d'éléments", 0],
            ["wSit", "W", "TOUT SUR SITUATION", "SITUATION", "Tous les écrans 16:9 de la salle montrent la carte et le flux", 1],
            ["wOps", "W", "MUR DE CONDUITE", "CONDUITE", "Répartit sur les écrans 16:9 de la salle : carte, main courante, exécution, demandes, horloge, flux", 0],
            ["wMap", "W", "TOUT SUR CARTE", "CARTE", "Tous les écrans 16:9 de la salle montrent la carte seule", 0],
            ["wBcGo", "W", "DIFFUSER", "", "L'écran source est recopié sur tous les autres écrans 16:9 de la salle", 0],
            ["wBcStop", "W", "ARRÊTER", "", "Fin de la diffusion : chaque écran reprend son affichage d'avant", 0],
            ["wMire", "W", "MIRE", "", "L'écran choisi sur le plan montre la mire de barres de couleur", 0],
            ["wText", "W", "TEXTE…", "", "Ouvre l'éditeur de texte de l'écran ou du bandeau choisi : messages, couleur, défilement", 0],
            ["wManager", "W", "TABLEAU DES ÉCRANS", "TABLEAU", "Ouvre le tableau de tous les écrans de la mission", 0],
            ["wLayoutGo", "W", "APPLIQUER", "", "Applique la mise en page aux dalles de l'écran choisi sur le plan", 0],
            ["wLumGo", "W", "RÉGLER", "", "Applique la luminosité à l'écran choisi sur le plan", 0],
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
            ["rGo", "L", "AFFICHER", "", "Sur tous les écrans de la salle du poste (sans salle : écrans à moins de 30 m)", 0],
            ["fCenter", "L", "CENTRER", "", "Centre la carte sur le compte rendu choisi", 0],
            ["oClear", "L", "EFFACER", "", "Efface l'objectif de l'ordre", 0],
            ["oSend", "L", "ENVOYER L'ORDRE", "ENVOYER", "L'ordre arrive dans l'app Tâches des destinataires", 1],
            ["cSend", "L", "ENVOYER", "", "Arrive dans Messages, conversation TOC du poste", 1],
            ["cSave", "L", "ENREGISTRER", "ENREG.", "Garde l'indicatif dans config.json (toc.callsign)", 0],
            ["cCamGo", "L", "AFFICHER", "", "Les écrans de la salle montrent la caméra choisie (sans salle : écrans à moins de 30 m)", 0],
            ["sSlot0", "L", "SAT 1", "", "Satellite piloté et affiché par ce PC", 0],
            ["sSlot1", "L", "SAT 2", "", "Satellite piloté et affiché par ce PC", 0],
            ["sTake", "L", "PRENDRE LA MAIN", "PRENDRE", "Un seul opérateur pilote un satellite à la fois ; les autres écrans le montrent en lecture seule", 1],
            ["sRelease", "L", "RENDRE LA MAIN", "RENDRE", "Laisse le satellite à un autre opérateur", 0],
            ["sTaskGrid", "L", "VISER LA GRILLE", "GRILLE", "Le satellite vise la grille tapée à gauche", 1],
            ["sMap", "L", "SUR LA CARTE", "CARTE", "La carte remplace l'image : un clic gauche sur la carte y place le satellite", 0],
            ["sFollowGo", "L", "SUIVRE", "", "Le satellite suit le groupe ou l'unité choisi", 0],
            ["sFollowOff", "L", "ARRÊTER LE SUIVI", "ARRÊTER", "Le satellite reste sur place", 0],
            ["sLog", "L", "INSCRIRE", "", "Inscrit cette vue à la main courante : grille, capteur, zoom et note", 0],
            ["sTag", "L", "MARQUER", "", "Pose au centre de la vue un repère visible par tout le camp, au nom de la note", 0],
            ["sOut", "L", "ZOOM −", "", "Éloigne la vue (zoom divisé par 1,25 ; 0,25× au plus loin, 2 km de large) ; aussi : molette sur l'image", 0],
            ["sIn", "L", "ZOOM +", "", "Rapproche la vue (zoom multiplié par 1,25 ; 8× au plus près, 62 m de large) ; aussi : molette sur l'image", 0],
            ["sAUTO", "L", "AUTO", "", "Jour le jour, thermique blanc chaud la nuit", 0],
            ["sDAY", "L", "JOUR", "", "Image en couleurs (éclaircie la nuit)", 0],
            ["sNV", "L", "NUIT", "", "Vision nocturne verte", 0],
            ["sWHOT", "L", "WHOT", "", "Thermique blanc chaud : ce qui est chaud (hommes, moteurs) en blanc", 0],
            ["sBHOT", "L", "BHOT", "", "Thermique noir chaud : ce qui est chaud en noir", 0],
            ["sFUSW", "L", "FUS W", "", "Fusion : thermique blanc chaud et contours des bâtiments", 0],
            ["sFUSB", "L", "FUS B", "", "Fusion : thermique noir chaud et contours des bâtiments", 0],
            ["sW", "L", "OUEST", "", "Déplace la vue d'un tiers vers l'ouest (ou : glisser l'image)", 0],
            ["sN", "L", "NORD", "", "Déplace la vue d'un tiers vers le nord (ou : glisser l'image)", 0],
            ["sHome", "L", "POSTE", "", "Centre le satellite sur le poste", 0],
            ["sS", "L", "SUD", "", "Déplace la vue d'un tiers vers le sud (ou : glisser l'image)", 0],
            ["sE", "L", "EST", "", "Déplace la vue d'un tiers vers l'est (ou : glisser l'image)", 0],
            ["sOvMRKR", "L", "MARQUEURS", "MRKR", "Marqueurs de la carte et tâches de la mission sur l'image (ce PC seulement)", 0],
            ["sOvBFT", "L", "PISTES", "BFT", "Symboles et indicatifs des alliés du réseau ATAK (ce PC seulement)", 0],
            ["sOvGRID", "L", "GRILLE", "", "Quadrillage de la carte : cases de 100 m, 1 km en vue large (ce PC seulement)", 0],
            ["sOvDATA", "L", "DONNÉES", "DATA", "Lignes de données du capteur : zoom, hauteur, grille, altitude, heure (ce PC seulement)", 0],
            ["sOvBOX", "L", "CADRES", "BOX", "Cadre autour de chaque allié du réseau (ce PC seulement)", 0],
            ["sJit", "L", "DÉRIVE", "", "Simule la dérive lente de la plateforme (éteinte par défaut)", 0],
            ["sTerr", "L", "SUIVI DU RELIEF", "RELIEF", "La hauteur suit le relief sous la vue ; éteint : hauteur constante", 0],
            ["sRefresh", "L", "TOUT RAFRAÎCHIR", "RAFRAÎCHIR", "Recharge pistes, marqueurs et contours (le réseau du camp est publié toutes les 3 s)", 0],
            ["sSend", "L", "ENVOYER", "", "L'écran ou la dalle choisi montre ce satellite, en lecture seule", 1],
            ["sClear", "L", "EFFACER", "", "L'écran ou la dalle choisi revient à la carte et au flux du poste", 0],
            ["tStart", "L", "LANCER", "", "Lance ou reprend le chrono choisi", 1],
            ["tStop", "L", "ARRÊTER", "", "Arrête le chrono choisi à sa valeur", 0],
            ["tReset", "L", "ZÉRO", "", "Remet le chrono choisi à zéro (compte à rebours : à sa durée)", 0],
            ["tDel", "L", "SUPPRIMER", "SUPPR.", "Retire le chrono choisi", 0],
            ["tAdd", "L", "CRÉER", "", "Crée le chrono", 1],
            ["tShow", "L", "HORLOGE SUR LES ÉCRANS", "SUR LES ÉCRANS", "Sur tous les écrans de la salle du poste (sans salle : écrans à moins de 30 m)", 0],
            ["lAdd", "L", "INSCRIRE", "", "Inscrit l'entrée à la main courante", 1],
            ["lShow", "L", "MAIN COURANTE SUR LES ÉCRANS", "SUR LES ÉCRANS", "Sur tous les écrans de la salle du poste (sans salle : écrans à moins de 30 m)", 0],
            ["xDone", "L", "PASSÉ", "", "Le mot de code de la ligne choisie est passé : heure ZULU inscrite, entrée à la main courante", 1],
            ["xUndo", "L", "ANNULER", "", "Retire l'heure réelle de la ligne choisie", 0],
            ["xDel", "L", "SUPPRIMER", "SUPPR.", "Retire la ligne choisie", 0],
            ["xShow", "L", "ÉCRANS", "", "Sur tous les écrans de la salle du poste (sans salle : écrans à moins de 30 m)", 0],
            ["xAdd", "L", "AJOUTER LA LIGNE", "AJOUTER", "Ajoute la ligne à la liste d'exécution", 1],
            ["kcPlus", "L", "+1", "", "Ajoute un au compte de l'élément choisi", 1],
            ["kcMinus", "L", "−1 (CORRECTION)", "−1", "Retire un au compte de l'élément choisi", 0],
            ["kcShow", "L", "PERTES ET RÉSULTATS SUR LES ÉCRANS", "SUR LES ÉCRANS", "Sur tous les écrans de la salle du poste (sans salle : écrans à moins de 30 m)", 0],
            ["qPRISE", "L", "PRISE EN COMPTE", "PRISE", "Le statut et la réponse partent au demandeur", 1],
            ["qREFUSEE", "L", "REFUSÉE", "", "Le statut et la réponse partent au demandeur", 0],
            ["qTERMINEE", "L", "TERMINÉE", "", "Le statut et la réponse partent au demandeur", 0],
            ["qShow", "L", "DEMANDES SUR LES ÉCRANS", "SUR LES ÉCRANS", "Sur tous les écrans de la salle du poste (sans salle : écrans à moins de 30 m)", 0],
            ["hCreate", "H", "CRÉER LA SALLE", "CRÉER", "Ce PC devient le poste maître d'une nouvelle salle", 1],
            ["hLinkAll", "H", "LIER TOUT", "", "Lie à cette salle tous les écrans du camp dans le rayon choisi", 0],
            ["hAim", "H", "VISER UN ÉCRAN", "VISER", "Le PC se ferme : regardez un écran pendant 20 s au plus, il est lié à la salle et le PC se rouvre", 0],
            ["hShow", "H", "AFFICHER", "", "L'écran ou la dalle choisi montre ce contenu", 1],
            ["hScrRen", "H", "RENOMMER", "", "Renomme l'écran choisi", 0],
            ["hPower", "H", "ÉTEINDRE", "", "Éteint ou rallume l'écran choisi", 0],
            ["hBlink", "H", "IDENTIFIER", "IDENT.", "L'écran choisi clignote 2 s dans la salle", 0],
            ["hLink", "H", "LIER", "", "Lie ou délie l'écran choisi", 0],
            ["hMosPut", "H", "PLACER", "", "Met la caméra choisie dans la case choisie (la mosaïque passe en choix case par case)", 0],
            ["hSceneGo", "H", "REMETTRE", "", "Remet tout le mur comme dans la scène choisie", 0],
            ["hSceneDel", "H", "SUPPRIMER", "SUPPR.", "Supprime la scène choisie", 0],
            ["hSceneSave", "H", "ENREGISTRER", "ENREG.", "Enregistre tout le mur tel qu'il est sous ce nom", 0],
            ["hAllOn", "H", "TOUT ALLUMER", "", "Allume tous les écrans de la salle", 0],
            ["hAllOff", "H", "TOUT ÉTEINDRE", "", "Éteint tous les écrans de la salle", 0]
        ];
        {
            _x params ["_key", "_to", "_long", "_short", "_tip", "_prim"];
            private _c = [["COMSPEC_RscTocButton", "COMSPEC_RscTocButtonPrimary", "COMSPEC_RscTocTab"] select ([[0, 1] select (_prim isEqualTo 1), 2] select (_to in ["T", "S"])), _key] call _mk;
            _c setVariable ["fit", [_long, _short, _tip]];
            _c setVariable ["tabBtn", _to isEqualTo "T"];
            _c ctrlSetText (_long call _tr);
            _c ctrlAddEventHandler ["ButtonClick", compile (switch (_to) do {
                case "T": { format ["['tab', %1] call comspec_atak_native_fnc_tocLaptop;", str (_key select [3])] };
                case "D": { format ["['act', %1] call comspec_atak_native_fnc_tocDraw;", str _key] };
                case "H": { format ["['act', %1] call comspec_atak_native_fnc_tocHallUi;", str _key] };
                case "S": { format ["['section', %1] call comspec_atak_native_fnc_tocLaptop;", str (_key select [3])] };
                case "A": { format ["['act', %1] call comspec_atak_native_fnc_tocApp;", str _key] };
                case "W": { format ["['act', %1] call comspec_atak_native_fnc_tocWall;", str _key] };
                default { format ["['press', %1] call comspec_atak_native_fnc_tocLaptop;", str _key] };
            })];
            [_c, _tip] call _tipOf;
        } forEach _btnDefs;
        // HUD du capteur posé sur l'image satellite (au-dessus d'elle, hors registre : fn_tocSat le pose), message d'attente.
        _st set ["satHud", ["hudMake", [_d, controlNull, "PC"]] call comspec_atak_native_fnc_tocSat];
        ["sWait"] call _stx;
        // Aide du contrôle survolé (ou de l'onglet) et ligne d'état.
        private _help = ["help"] call _stx;
        _help ctrlSetBackgroundColor (["bg0"] call _tcol);
        private _bar = ["bar"] call _stx;
        _bar ctrlSetBackgroundColor (["bg1"] call _tcol);
        // Image satellite : clic pour centrer, glisser pour déplacer, molette pour zoomer (fn_tocSat).
        _d displayAddEventHandler ["MouseButtonDown", { params ["", "_b", "_mx", "_my"]; if (_b isEqualTo 0) then { ["satDown", [_mx, _my]] call comspec_atak_native_fnc_tocLaptop; }; }];
        _d displayAddEventHandler ["MouseButtonUp", { params ["", "_b", "_mx", "_my"]; if (_b isEqualTo 0) then { ["satUp", [_mx, _my]] call comspec_atak_native_fnc_tocLaptop; }; }];
        _d displayAddEventHandler ["MouseZChanged", { params ["", "_z"]; ["satWheel", _z] call comspec_atak_native_fnc_tocLaptop; }];
        // Raccourcis : F1 à F4 rubriques, Page préc. / suiv. onglets, + et − du pavé numérique zoom, Ctrl+F recherche.
        _d displayAddEventHandler ["KeyDown", { ["key", _this] call comspec_atak_native_fnc_tocApp }];
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
            _s = format ["<t size='0.8' color='@text2'>%1%2%3</t>", _head, [_tip] call _esc, ["", format ["<br/><t color='@amber'>%1</t>", [_why call _tr] call _esc]] select (!ctrlEnabled _c && {_why isNotEqualTo ""})];
        } else {
            private _tabHelp = createHashMapFromArray [
                ["CARTE", "Clic gauche sur une unité : sa fiche. Clic droit : point désigné (ordre, demande, compte rendu). Molette ou ZOOM : zoom. Clic gauche maintenu : déplacer."],
                ["GROUPES", "Choisissez un groupe : la carte se centre sur lui. Clic droit sur la carte : point de l'ORDRE IA."],
                ["ORBAT", "ORBAT du site, lu avec votre compte. Choisissez un membre pour lui écrire ou centrer la carte."],
                ["RÉSEAU", "État du réseau du camp, relais, brouilleurs et météo. Les unités sans téléphone sont lues directement dans le jeu."],
                ["FLUX", "Comptes rendus, alertes et messages du camp. Choisissez une ligne : la carte se centre dessus. En bas : compte rendu du poste."],
                ["ORDRES", "Clic droit sur la carte : objectif de l'ordre. L'ordre arrive aux destinataires et s'inscrit au flux."],
                ["CAMP", "Message au camp ou à un chef de groupe, indicatif du poste."],
                ["DEMANDES", "Choisissez une demande, écrivez la réponse, puis son statut. En bas : inscrire une demande au nom du poste."],
                ["CHRONOS", "Chronos, comptes à rebours et heure H, communs à tous les PC du camp."],
                ["EXCHECK", "Choisissez une ligne puis PASSÉ : l'heure réelle est inscrite."],
                ["MAIN COURANTE", "Entrées horodatées en ZULU, communes au camp. Entrées rapides : un clic inscrit le mot et le texte saisi."],
                ["PERTES", "Pertes et résultats par élément ; corrigez à la main si besoin."],
                ["DESSIN", "Choisissez un outil sous la carte, réglez-le sur la ligne du dessous, puis cliquez sur la carte. Tout est partagé avec le camp."],
                ["PLAN", "Éléments du plan du camp. Choisissez une ligne pour la centrer ou la supprimer ; dessinez dans l'onglet DESSIN."],
                ["SALLE", "Cliquez un écran ou une dalle sur le plan, puis choisissez son contenu."],
                ["MUR", "Préréglages de tout le mur, diffusion d'un écran sur les autres, caméras et scènes de la salle."],
                ["SAT", "Molette sur l'image : zoom · clic : centre la vue · glisser : déplace la vue. Pistes : unités du camp (gris : point ancien). Une commande sur un satellite libre vous en donne la main."],
                ["RADIO", "Radios de l'opérateur, en lecture seule : parlez avec votre touche d'alternat."]
            ];
            _s = format ["<t size='0.8' color='@muted'>%1</t>", [(_tabHelp getOrDefault [_st getOrDefault ["tab", "CARTE"], ""]) call _tr] call _esc];
        };
        _h ctrlSetStructuredText parseText (["ink", _s] call comspec_atak_native_fnc_tocTheme);
    };
    case "layout": {
        // Mise en page en cours : barre de titre, onglets, gauche (carte et barre de l'outil, satellite ou plan de la
        // salle), panneau de l'onglet, aide, ligne d'état. Seuls les contrôles nommés ici sont montrés.
        if ((count _ui) isEqualTo 0) exitWith {};
        private _tab = _st getOrDefault ["tab", "CARTE"];
        private _tool = _st getOrDefault ["tool", "SELECT"];
        private _sec = [_tab] call _secOf;
        private _left = switch (true) do {
            case (_tab isEqualTo "SAT"): { ["L:SAT", "L:SATMAP"] select (_st getOrDefault ["satMap", false]) };
            case (_tab in ["SALLE", "MUR"]): { "L:SALLE" };
            // RADIO : la console radio (fn_tocRadioUi « dock ») prend la place de la carte ; aucun contrôle d'ici à gauche.
            case (_tab isEqualTo "RADIO" && {!isNil "comspec_atak_native_fnc_tocRadioUi"}): { "L:RADIO" };
            case (_sec isEqualTo "PLANS"): { "L:MAP:" + (["SELECT", _tool] select (_tool in ["LINE", "DRAW", "PLAN", "MARKER", "ERASE"])) };
            default { "L:MAP:NAV" };
        };
        private _layout = createHashMapFromArray [
["F:TITLE", [["titleRow", [1, -0.12, -0.44, -0.13, -0.07, -0.15, -0.08], ["title", ["alertsGo", 1, "row"], "clock", ["flashTxt", 1, "row"], ["flashGo", 1, "row"], ["postGo", 1, "row"], ["close", 1, "row"]]]]],
["F:RAIL", [["railBtn", [1], ["secSITUATION"]], ["railBtn", [1], ["secCONDUITE"]], ["railBtn", [1], ["secPLANS"]], ["railBtn", [1], ["secSALLE"]], [1, [1], ["railInfo"]]]],
["F:TABS:SITUATION", [["tab", [1, 1, 1, 1, 1, 1, 1], ["tabCARTE", "tabGROUPES", "tabORBAT", "tabRÉSEAU", "tabFLUX", "", ""]]]],
["F:TABS:CONDUITE", [["tab", [1, 1, 1, 1, 1, 1, 1], ["tabORDRES", "tabCAMP", "tabDEMANDES", "tabCHRONOS", "tabEXCHECK", "tabMAIN COURANTE", "tabPERTES"]]]],
["F:TABS:PLANS", [["tab", [1, 1, 1, 1, 1, 1, 1], ["tabDESSIN", "tabPLAN", "", "", "", "", ""]]]],
["F:TABS:SALLE", [["tab", [1, 1, 1, 1, 1, 1, 1], ["tabSALLE", "tabMUR", "tabSAT", "tabRADIO", "", "", ""]]]],
["F:HELP", [["help", [1], ["help"]]]],
["F:BAR", [["bar", [1], ["bar"]]]],
["L:MAP:NAV", [["row", [-0.04, -0.04, 1, 1, 1, 1, 2.2, 1.1], ["zoomOut", "zoomIn", "framenet", "frameme", "framesel", "toolMEASURE", "search", "searchGo"]], [1, [1], ["map"]], ["row", [1], ["mapInfo"]]]],
["L:MAP:SELECT", [["row", [-0.04, -0.04, 1, 1, 1, 1, 2.2, 1.1], ["zoomOut", "zoomIn", "framenet", "frameme", "framesel", "toolMEASURE", "search", "searchGo"]], [1, [1], ["map"]], ["row", [1, 1, 1, 1, 1, 1], ["toolMARKER", "toolLINE", "toolDRAW", "toolPLAN", "toolERASE", "dUndo"]], ["row", [1], ["dInfo"]], ["toolHint", [1], ["toolHint"]]]],
["L:MAP:ERASE", [["row", [-0.04, -0.04, 1, 1, 1, 1, 2.2, 1.1], ["zoomOut", "zoomIn", "framenet", "frameme", "framesel", "toolMEASURE", "search", "searchGo"]], [1, [1], ["map"]], ["row", [1, 1, 1, 1, 1, 1], ["toolMARKER", "toolLINE", "toolDRAW", "toolPLAN", "toolERASE", "dUndo"]], ["row", [1], ["dInfo"]], ["toolHint", [1], ["toolHint"]]]],
["L:MAP:LINE", [["row", [-0.04, -0.04, 1, 1, 1, 1, 2.2, 1.1], ["zoomOut", "zoomIn", "framenet", "frameme", "framesel", "toolMEASURE", "search", "searchGo"]], [1, [1], ["map"]], ["row", [1, 1, 1, 1, 1, 1], ["toolMARKER", "toolLINE", "toolDRAW", "toolPLAN", "toolERASE", "dUndo"]], ["row", [1.2, 1, 1, 1, 1.5], ["dColor", "dWidth", "dArrow", "dRoads", "dClear"]], ["toolHint", [1], ["toolHint"]]]],
["L:MAP:DRAW", [["row", [-0.04, -0.04, 1, 1, 1, 1, 2.2, 1.1], ["zoomOut", "zoomIn", "framenet", "frameme", "framesel", "toolMEASURE", "search", "searchGo"]], [1, [1], ["map"]], ["row", [1, 1, 1, 1, 1, 1], ["toolMARKER", "toolLINE", "toolDRAW", "toolPLAN", "toolERASE", "dUndo"]], ["row", [1.2, 1, 1, 1, 1.5], ["dColor", "dWidth", "dArrow", "dClosed", "dClear"]], ["toolHint", [1], ["toolHint"]]]],
["L:MAP:PLAN", [["row", [-0.04, -0.04, 1, 1, 1, 1, 2.2, 1.1], ["zoomOut", "zoomIn", "framenet", "frameme", "framesel", "toolMEASURE", "search", "searchGo"]], [1, [1], ["map"]], ["row", [1, 1, 1, 1, 1, 1], ["toolMARKER", "toolLINE", "toolDRAW", "toolPLAN", "toolERASE", "dUndo"]], ["row", [2.2, 1.4, 1, 1.1], ["dKind", "dPlanTxt", "dPlanEnd", "dPlanCancel"]], ["toolHint", [1], ["toolHint"]]]],
["L:MAP:MARKER", [["row", [-0.04, -0.04, 1, 1, 1, 1, 2.2, 1.1], ["zoomOut", "zoomIn", "framenet", "frameme", "framesel", "toolMEASURE", "search", "searchGo"]], [1, [1], ["map"]], ["row", [1, 1, 1, 1, 1, 1], ["toolMARKER", "toolLINE", "toolDRAW", "toolPLAN", "toolERASE", "dUndo"]], ["row", [1, 1.5, 1, 1.3, 0.9, 1.1], ["mAff", "mType", "mColor", "mText", "mTtl", "mChan"]], ["toolHint", [1], ["toolHint"]]]],
["L:SAT", [["strip", [1], ["sStrip"]], [1, [1], ["sBack"]]]],
["L:SATMAP", [["strip", [1], ["sStrip"]], [1, [1], ["map"]]]],
["L:SALLE", [["row", [1], ["hPlanTitle"]], [1, [1], ["hPlanBg"]]]],
["R:CARTE", [["head", [1], ["pHead"]], ["hair", [1], ["pRule"]], ["row", [1, 1, 1], ["lyPL", "lyAI", "lyDIR"]], ["row", [1, 1, 1], ["lyVEH", "lyAIR", "lyEV"]], ["row", [1, 1, 1], ["lyPLAN", "lyREL", "lyCOV"]], [2, [1], ["uList"]], [1.5, [1], ["uInfo"]], ["row", [1, 1, 1, 1], ["uFollow", "uMsg", "uOrder", "uTrack"]]]],
["R:GROUPES", [["head", [1], ["pHead"]], ["hair", [1], ["pRule"]], [3, [1], ["gList"]], [2, [1], ["gInfo"]], ["row", [2, 1], ["gAi", "gAiGo"]], ["row", [1, 1, 1], ["gOrder", "gMsg", "gCenter"]]]],
["R:ORBAT", [["head", [1], ["pHead"]], ["hair", [1], ["pRule"]], [3, [1], ["bList"]], [1.3, [1], ["bInfo"]], ["row", [1, 1, 1], ["bMsg", "bCenter", ""]]]],
["R:RÉSEAU", [["head", [1], ["pHead"]], ["hair", [1], ["pRule"]], [1, [1], ["rInfo"]]]],
["R:FLUX", [["head", [1], ["pHead"]], ["hair", [1], ["pRule"]], ["row", [2, 1], ["fFilter", "fCenter"]], [3, [1], ["fList"]], [1.2, [1], ["fInfo"]], ["label", [1], ["fL1"]], ["row", [1, 2], ["fKindNew", "fText"]], ["row", [1, 1], ["fGrid", "fAdd"]]]],
["R:ORDRES", [["head", [1], ["pHead"]], ["hair", [1], ["pRule"]], ["label", [1, 1], ["oL1", "oL2"]], ["row", [1, 1], ["oKind", "oPrio"]], ["label", [1], ["oL3"]], ["row", [1], ["oTarget"]], ["label", [1], ["oL4"]], ["info3", [1], ["oText"]], ["label", [1], ["oL5"]], ["row", [2, 1], ["oGrid", "oClear"]], ["tall", [1], ["oSend"]], [1, [1], ["oInfo"]]]],
["R:CAMP", [["head", [1], ["pHead"]], ["hair", [1], ["pRule"]], ["label", [1], ["cL1"]], ["row", [2, 1], ["cDest", "cPrio"]], ["label", [1], ["cL2"]], ["info3", [1], ["cText"]], ["tall", [1], ["cSend"]], ["label", [1], ["cL3"]], ["row", [2, 1], ["cCs", "cSave"]], [1, [1], ["cInfo"]]]],
["R:DEMANDES", [["head", [1], ["pHead"]], ["hair", [1], ["pRule"]], [2, [1], ["qList"]], [1, [1], ["qInfo"]], ["row", [1], ["qAns"]], ["row", [1, 1, 1], ["qPRISE", "qREFUSEE", "qTERMINEE"]], ["label", [1], ["qL1"]], ["row", [1, 1], ["qKind", "qGrid"]], ["row", [2, 1], ["qText", "qAdd"]], ["row", [1], ["qShow"]]]],
["R:CHRONOS", [["head", [1], ["pHead"]], ["hair", [1], ["pRule"]], [2, [1], ["tList"]], ["row", [1, 1, 1, 1], ["tStart", "tStop", "tReset", "tDel"]], ["label", [1], ["tL1"]], ["row", [1], ["tName"]], ["row", [1, 1], ["tKind", "tVal"]], ["row", [1, 1], ["tAdd", "tShow"]], [1, [1], ["tInfo"]]]],
["R:EXCHECK", [["head", [1], ["pHead"]], ["hair", [1], ["pRule"]], [2, [1], ["xList"]], ["row", [1, 1, 1, 1], ["xDone", "xUndo", "xDel", "xShow"]], ["label", [1], ["xL1"]], ["row", [1], ["xEv"]], ["row", [1, 1, 1], ["xCw", "xEl", "xAt"]], ["row", [1], ["xAdd"]], [1, [1], ["xInfo"]]]],
["R:MAIN COURANTE", [["head", [1], ["pHead"]], ["hair", [1], ["pRule"]], [3, [1], ["lList"]], ["row", [2, 1], ["lText", "lAdd"]], ["row", [1, 1, 1, 1], ["lQ1", "lQ2", "lQ3", "lQ4"]], ["row", [1], ["lShow"]], [0.7, [1], ["lInfo"]]]],
["R:PERTES", [["head", [1], ["pHead"]], ["hair", [1], ["pRule"]], [2, [1], ["kcInfo"]], ["label", [1], ["kcL1"]], ["row", [1, 1], ["kcEl", "kcKind"]], ["row", [1, 1], ["kcPlus", "kcMinus"]], ["row", [1], ["kcShow"]]]],
["R:DESSIN", [["head", [1], ["pHead"]], ["hair", [1], ["pRule"]], [1, [1], ["pHow"]], ["row", [1, 1], ["pShow", "pTell"]]]],
["R:PLAN", [["head", [1], ["pHead"]], ["hair", [1], ["pRule"]], [3, [1], ["pList"]], [1, [1], ["pInfo"]], ["row", [1, 1], ["pCenter", "pDel"]], ["row", [1, 1], ["pShow", "pTell"]]]],
["R:SALLE", [["head", [1], ["pHead"]], ["hair", [1], ["pRule"]], ["info2", [1], ["hInfo"]], ["row", [2, 1], ["hName", "hCreate"]], ["row", [1, 1.6, 1.6], ["hRad", "hLinkAll", "hAim"]], ["info2", [1], ["hSel"]], ["row", [2, 1], ["hWhat", "hShow"]], ["row", [2, 1], ["hScrName", "hScrRen"]], ["row", [1, 1, 1], ["hPower", "hBlink", "hLink"]], ["row", [1, 1, 1.6], ["wText", "wMire", "wManager"]], ["label", [1], ["wL3"]], ["row", [2, 1], ["wLayout", "wLayoutGo"]], ["row", [2, 1], ["wLum", "wLumGo"]], ["row", [1, 1, 1, 1], ["hMosRot", "hMosCase", "hMosSrc", "hMosPut"]]]],
["R:MUR", [["head", [1], ["pHead"]], ["hair", [1], ["pRule"]], ["info5", [1], ["wInfo"]], ["row", [1.3, 1.2, 1.1], ["wSit", "wOps", "wMap"]], ["label", [1], ["wL1"]], ["row", [2, 1, 1], ["wSrc", "wBcGo", "wBcStop"]], ["label", [1], ["cL4"]], ["row", [2, 1], ["cCam", "cCamGo"]], ["label", [1], ["rL1"]], ["row", [2, 1], ["rShow", "rGo"]], ["label", [1], ["wL2"]], ["row", [2, 1, 1], ["hScene", "hSceneGo", "hSceneDel"]], ["row", [2, 1], ["hSceneName", "hSceneSave"]], ["row", [1, 1], ["hAllOn", "hAllOff"]]]],
["R:SAT", [["row", [1, 1], ["sSlot0", "sSlot1"]], ["info2", [1], ["sInfo"]], ["row", [1, 1], ["sTake", "sRelease"]], ["label", [1], ["sL1"]], ["row", [2, 1, 1], ["sGrid", "sTaskGrid", "sMap"]], ["row", [2, 1, 1], ["sFollow", "sFollowGo", "sFollowOff"]], ["row", [2, 1, 1], ["sNote", "sLog", "sTag"]], ["row", [1, 1.2, 1], ["sOut", "sZoom", "sIn"]], ["label", [1], ["sL2"]], ["row", [1, 1, 1, 1], ["sAUTO", "sDAY", "sNV", "sWHOT"]], ["row", [1, 1, 1, 1], ["sBHOT", "sFUSW", "sFUSB", ""]], ["row", [1.3, 1, 1, 1, 1, 1], ["sL3", "sW", "sN", "sHome", "sS", "sE"]], ["row", [1.3, 1, 1, 1, 1, 1], ["sL4", "sOvMRKR", "sOvBFT", "sOvGRID", "sOvDATA", "sOvBOX"]], ["row", [1, 1, 1], ["sJit", "sTerr", "sRefresh"]], ["label", [1], ["sL5"]], ["row", [2, 1, 1], ["sScr", "sSend", "sClear"]]]],
["R:RADIO", [["head", [1], ["pHead"]], ["hair", [1], ["pRule"]], [1, [1], ["kInfo"]]]]
        ];
        // Fonds toujours montrés (posés une fois au chargement).
        private _on = ["screen", "titleBg", "titleLine", "railBg", "rBg", "tabMark", "secMark"];
        {
            _x params ["_lk", "_rk"];
            private _rows = _layout getOrDefault [_lk, []];
            (_st get _rk) params ["_rx", "_ry", "_rw", "_rh"];
            [_rows, _rx, _ry, _rw, _rh] call _place;
            { { _on pushBack (if (_x isEqualType "") then { _x } else { _x select 0 }); } forEach (_x select 2); } forEach _rows;
        } forEach [["F:TITLE", "rTitle"], ["F:RAIL", "rRail"], ["F:TABS:" + _sec, "rTabs"], ["F:HELP", "rHelp"], ["F:BAR", "rBar"], [_left, "rLeft"], ["R:" + _tab, "rRight"]];
        { (_ui get _x) ctrlShow (_x in _on); } forEach (_st getOrDefault ["keys", []]);
        // Filet accent sous l'onglet choisi.
        (ctrlPosition (_ui get ("tab" + _tab))) params ["_tbx", "_tby", "_tbw", "_tbh"];
        (_ui get "tabMark") ctrlSetPosition [_tbx, _tby + _tbh - 0.003 * safeZoneH, _tbw, 0.003 * safeZoneH];
        (_ui get "tabMark") ctrlCommit 0;
        // Filet accent à gauche de la rubrique choisie.
        (ctrlPosition (_ui get ("sec" + _sec))) params ["_scx", "_scy", "", "_sch"];
        (_ui get "secMark") ctrlSetPosition [_scx, _scy, 0.004 * safeZoneH * pixelW / pixelH, _sch];
        (_ui get "secMark") ctrlCommit 0;
        // Satellite : image 16:9 qui couvre toute sa zone (rognée au bord le plus long par le groupe sClip) : ni bande
        // noire ni déformation ; zone visible (satRect) et image entière (satImg) gardées pour la souris et le HUD.
        private _sat = _tab isEqualTo "SAT" && {!(_st getOrDefault ["satMap", false])};
        if (_sat) then {
            (ctrlPosition (_ui get "sBack")) params ["_bx", "_by", "_bw", "_bh"];
            private _ar = (_bw / pixelW) / ((_bh max pixelH) / pixelH);
            private _sw = _bw; private _sh = _bh;
            if (_ar > 16 / 9) then { _sh = _bh * _ar / (16 / 9); } else { _sw = _bw * (16 / 9) / _ar; };
            _st set ["satRect", [_bx, _by, _bw, _bh]];
            _st set ["satImg", [_bx + (_bw - _sw) / 2, _by + (_bh - _sh) / 2, _sw, _sh]];
            (_ui get "sClip") ctrlSetPosition [_bx, _by, _bw, _bh];
            (_ui get "sClip") ctrlCommit 0;
            (_ui get "sClip") ctrlShow true;
            (_ui get "sPic") ctrlSetPosition [(_bw - _sw) / 2, (_bh - _sh) / 2, _sw, _sh];
            (_ui get "sPic") ctrlCommit 0;
            (_ui get "sWait") ctrlSetPosition [_bx, _by + _bh * 0.4, _bw, 0.08 * safeZoneH];
            (_ui get "sWait") ctrlCommit 0;
        };
        (_ui get "sPic") ctrlShow _sat;
        (_ui get "sWait") ctrlShow false;
        if (!_sat) then { ["hudHide", _st getOrDefault ["satHud", createHashMap]] call comspec_atak_native_fnc_tocSat; };
        // Salle : plan du mur sous son titre (fn_tocHallUi place ses dalles dedans).
        if (_tab in ["SALLE", "MUR"]) then {
            (ctrlPosition (_ui get "hPlanTitle")) params ["_tx", "_ty", "_tw"];
            (ctrlPosition (_ui get "hPlanBg")) params ["", "_gy0", "", "_gh"];
            _st set ["hallPlan", [_tx, _ty, _tw, (_gy0 + _gh) - _ty]];
        } else {
            { _x ctrlShow false; } forEach (_st getOrDefault ["hallTiles", []]);
        };
        // Activité radio du camp (qui parle, sur quelle fréquence) : panneau monté à gauche sur l'onglet RADIO, masqué ailleurs.
        if (!isNil "comspec_atak_native_fnc_tocRadioUi") then { ["dock", [ctrlParent (_ui get "screen"), _st get "rLeft", _tab isEqualTo "RADIO"]] call comspec_atak_native_fnc_tocRadioUi; };
        _st set ["layoutKey", [_left, _tab]];
    };
    case "press": {
        // Boutons du PC (table _btnDefs, cible L) : cadrage, outils, calques, recherche, fermeture ; le reste passe par « act ».
        switch (true) do {
            case (_a in ["framenet", "frameme", "framesel"]): { ["frame", _a select [5]] call _fn; };
            case (_a in ["toolMEASURE", "toolMARKER"]): { ["tool", _a select [4]] call _fn; };
            case (_a in ["zoomIn", "zoomOut"]): { ["zoom", [1, -1] select (_a isEqualTo "zoomOut")] call comspec_atak_native_fnc_tocApp; };
            case (_a isEqualTo "alertsGo"): { (_ui get "fFilter") lbSetCurSel 1; ["keep"] call _fn; ["tab", "FLUX"] call _fn; };
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
        if ((_st getOrDefault ["tab", ""]) isNotEqualTo "SAT" || {_st getOrDefault ["satMap", false]}) exitWith {};
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
        // Parts de l'image entière (16:9, rognée à l'écran).
        (_st getOrDefault ["satImg", [0, 0, 1, 1]]) params ["_rx", "_ry", "_rw", "_rh"];
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
        // Molette sur l'image : zoom continu, ×1,15 par cran (une demande toutes les 0,15 s au plus ; la caméra glisse).
        if ((_st getOrDefault ["tab", ""]) isNotEqualTo "SAT" || {_a isEqualTo 0} || {_st getOrDefault ["satMap", false]}) exitWith {};
        (_st getOrDefault ["satRect", [0, 0, 0, 0]]) params ["_rx", "_ry", "_rw", "_rh"];
        getMousePosition params ["_mx", "_my"];
        if (_mx < _rx || {_mx > _rx + _rw} || {_my < _ry} || {_my > _ry + _rh}) exitWith {};
        if (diag_tickTime - (_st getOrDefault ["wheelAt", -1]) < 0.15) exitWith {};
        _st set ["wheelAt", diag_tickTime];
        private _n = _st getOrDefault ["satN", 0];
        if !(["satCan", _n] call _fn) exitWith {};
        ["send", ["sat", [_n, "zoom", [1.15, 1 / 1.15] select (_a > 0)]]] call comspec_atak_native_fnc_tocRoom;
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
        private _t = [_a, "CARTE"] select !(_a in _allTabs);
        // Onglet SAT quitté : la caméra du satellite reste prête 30 s (retour sans nouvelle acquisition). En 4.4.0 le bail
        // de 3 s expirait au premier changement d'onglet : « rendu arrêté » 3 s après avoir quitté SAT (journal du 11/11).
        if ((_st getOrDefault ["tab", ""]) isEqualTo "SAT" && {_t isNotEqualTo "SAT"}) then { ["need", [_side, _st getOrDefault ["satN", 0], 30]] call comspec_atak_native_fnc_tocSat; };
        _st set ["tab", _t];
        _st set ["hoverC", controlNull];
        private _sec = [_t] call _secOf;
        _st set ["sec", _sec];
        _st set ["last" + _sec, _t];
        // Outils de dessin : seulement dans la rubrique PLANS (leur barre n'est montrée que là) ; ailleurs, sélection.
        if (_sec isNotEqualTo "PLANS" && {(_st getOrDefault ["tool", "SELECT"]) in ["LINE", "DRAW", "PLAN", "MARKER", "ERASE"]}) then { ["tool", "SELECT"] call _fn; };
        {
            private _on = (_x select 0) isEqualTo _sec;
            (_ui get ("sec" + (_x select 0))) ctrlSetBackgroundColor ([["bg1"] call _tcol, ["bg2"] call _tcol] select _on);
            (_ui get ("sec" + (_x select 0))) ctrlSetTextColor ([["text2"] call _tcol, ["accent"] call _tcol] select _on);
        } forEach _sections;
        // Onglet choisi : fond BG2, texte accent, filet accent dessous (posé par « layout ») ; les autres à plat.
        {
            private _on = _x isEqualTo _t;
            (_ui get ("tab" + _x)) ctrlSetBackgroundColor ([["bg1"] call _tcol, ["bg2"] call _tcol] select _on);
            (_ui get ("tab" + _x)) ctrlSetTextColor ([["text2"] call _tcol, ["accent"] call _tcol] select _on);
        } forEach _allTabs;
        // Mise en page de l'onglet (gauche : carte, satellite ou plan de la salle) ; rien d'autre n'est montré.
        ["layout"] call _fn;
        if (_t in ["SALLE", "MUR"]) then { ["refresh"] call comspec_atak_native_fnc_tocHallUi; };
        if !(_t in ["SAT", "SALLE", "MUR"]) then { ["show"] call comspec_atak_native_fnc_tocDraw; };
        // SAT : HUD du capteur reposé à chaque image (texte et symboles), rien quand l'onglet est fermé ni PC refermé (« unload »).
        private _sat = _t isEqualTo "SAT";
        if (!_sat) then { _st set ["satMap", false]; };
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
                } else { ["status", "Aucune unité du camp connue du poste : carte centrée sur le poste"] call _fn; };
            };
            case (_a isEqualTo "sel"): {
                private _p = [];
                if ((_st getOrDefault ["tab", ""]) isEqualTo "FLUX") then {
                    private _ev = (call _feedOf) param [2, []];
                    private _i = parseNumber (_st getOrDefault ["selF", "-1"]);
                    if (_i >= 0 && {_i < (count _ev)}) then { _p = (_ev select _i) param [6, []]; };
                } else {
                  if ((_st getOrDefault ["tab", ""]) isEqualTo "CARTE") then {
                    private _i = _nodes findIf { (_x select 0) isEqualTo (_st getOrDefault ["selU", ""]) };
                    if (_i >= 0) then { _p = (_nodes select _i) select 2; };
                  } else {
                    private _g = ([_nodes] call _groups) select { (_x select 0) isEqualTo (_st getOrDefault ["selG", ""]) };
                    if ((count _g) > 0) then { _p = (_g select 0) select 7; };
                  };
                };
                if ((count _p) >= 2) then { _ctr = [_p select 0, _p select 1]; _span = 700; } else { _ctr = []; };
            };
        };
        if ((count _ctr) < 2) exitWith {
            ["status", "Choisissez d'abord une unité, un groupe ou un compte rendu qui a une position"] call _fn;
        };
        _map ctrlMapAnimAdd [0.4, ((_span / (worldSize max 1000)) max 0.008) min 1, [_ctr select 0, _ctr select 1, 0]];
        ctrlMapAnimCommit _map;
    };
    case "click": {
        // Outil PLAN : le clic droit pose le graphisme en cours.
        if (["right", _a] call comspec_atak_native_fnc_tocDraw) exitWith {};
        private _p = _a;
        _draft set ["pos", [_p select 0, _p select 1]];
        // Point désigné : il sert à l'ordre, à l'ordre IA, à la demande et au compte rendu du poste. L'onglet ne change pas
        // (avant la 4.9.0, un clic droit ouvrait ORDRES : un geste caché).
        private _g8 = [[_p select 0, _p select 1, 0], 8] call comspec_atak_native_fnc_gridRef;
        if ((count _ui) > 0) then { { (_ui get _x) ctrlSetText _g8; } forEach ["oGrid", "fGrid", "qGrid"]; }; // i18n:brut
        _draft set ["grid", _g8];
        ["status", format ["Point désigné : grille %1 (ordre, ordre IA, demande, compte rendu)", _g8]] call _fn;
    };
    case "status": { [_a] call _setStatus; };
    case "satClick": {
        _a params ["_u", "_v"];
        private _n = _st getOrDefault ["satN", 0];
        private _p = ["unproject", [_side, _n, _u, _v]] call comspec_atak_native_fnc_tocSat;
        if ((count _p) < 2) exitWith {};
        if !(["satCan", _n] call _fn) exitWith {};
        ["send", ["sat", [_n, "center", _p]]] call comspec_atak_native_fnc_tocRoom;
        [format ["SAT %1 : centré sur la grille %2", _n + 1, [[_p select 0, _p select 1, 0], 6] call comspec_atak_native_fnc_gridRef]] call _setStatus;
    };
    case "satOverlay": {
        // HUD du capteur sur l'image (fn_tocSat « hudPaint ») : seulement onglet SAT ouvert, image rendue.
        if ((count _ui) isEqualTo 0) exitWith {};
        private _hud = _st getOrDefault ["satHud", createHashMap];
        private _n = _st getOrDefault ["satN", 0];
        if ((_st getOrDefault ["tab", ""]) isNotEqualTo "SAT" || {_st getOrDefault ["satMap", false]} || {(["rt", [_side, _n]] call comspec_atak_native_fnc_tocSat) isEqualTo ""}) exitWith {
            ["hudHide", _hud] call comspec_atak_native_fnc_tocSat;
        };
        ["hudPaint", [_hud, _side, _n, _st getOrDefault ["satImg", [0, 0, 1, 1]], _st getOrDefault ["satRect", [0, 0, 1, 1]], call _satOv]] call comspec_atak_native_fnc_tocSat;
    };
    case "tool": {
        // Un outil à la fois ; le même bouton rend la sélection.
        private _t = [_a, "SELECT"] select ((_st getOrDefault ["tool", "SELECT"]) isEqualTo _a && {(count _ui) > 0} && {(_st getOrDefault ["toolSet", false])});
        if !(_t in ["SELECT", "MEASURE", "MARKER", "LINE", "DRAW", "PLAN", "ERASE"]) then { _t = "SELECT"; };
        _st set ["tool", _t];
        _st set ["toolSet", true];
        _st set ["measure", []];
        { (_ui getOrDefault ["tool" + _x, controlNull]) ctrlSetBackgroundColor ([(["bg2"] call _tcol), (["accentDim"] call _tcol)] select (_x isEqualTo _t)); } forEach ["MEASURE", "MARKER"];
        ["show"] call comspec_atak_native_fnc_tocDraw;
        // Consigne de l'outil, sous la barre de dessin (deux lignes au plus) ; la barre montre les réglages de l'outil.
        (_ui getOrDefault ["toolHint", controlNull]) ctrlSetStructuredText parseText (["ink", format ["<t size='0.8' color='@text2'>%1</t>", [(switch (_t) do {
            case "MEASURE": { "MESURE : clic gauche sur A, puis sur B : distance et azimut s'écrivent sur la carte" };
            case "MARKER": { "MARQUEUR : choisissez camp, type, couleur, nom, durée et canal en bas, puis clic gauche pour poser" };
            case "LINE": { "TRAIT : clic sur A, Ctrl+clic pour chaque étape, clic sur B · ROUTES : le trait suit les routes" };
            case "DRAW": { "DESSIN : clic gauche maintenu pour dessiner, relâcher pour poser · FERMÉ : forme fermée" };
            case "PLAN": { "PLAN : choisissez le graphisme, cliquez ses points, puis clic droit ou TERMINER · 2 clics pour une zone = rectangle" };
            case "ERASE": { "EFFACER : clic sur un marqueur, un trait ou un élément du plan (supprimé pour tous)" };
            default { "Choisissez un outil ci-dessus : MARQUEUR, TRAIT, DESSIN, PLAN ou EFFACER. Sans outil : clic gauche sur une unité pour sa fiche." };
        }) call _tr] call _esc]] call comspec_atak_native_fnc_tocTheme);
        if (_st getOrDefault ["laid", false]) then { ["layout"] call _fn; };
    };
    case "tabStep": {
        // Onglet précédent ou suivant de la rubrique (Page préc. / Page suiv.).
        private _l = (_sections select ((_sections findIf { (_x select 0) isEqualTo ([_st getOrDefault ["tab", "CARTE"]] call _secOf) }) max 0)) select 1;
        ["keep"] call _fn;
        ["tab", _l select ((((_l find (_st getOrDefault ["tab", "CARTE"])) + _a) + (count _l)) mod (count _l))] call _fn;
    };
    case "section": {
        // Rubrique du rail : son dernier onglet ouvert (le premier à la première visite).
        private _i = _sections findIf { (_x select 0) isEqualTo _a };
        if (_i < 0) exitWith {};
        ["keep"] call _fn;
        ["tab", _st getOrDefault ["last" + _a, ((_sections select _i) select 1) select 0]] call _fn;
    };
    case "left": {
        // Pointage du satellite sur la carte (SUR LA CARTE) : le clic place la vue, l'image revient.
        if ((_st getOrDefault ["tab", ""]) isEqualTo "SAT" && {_st getOrDefault ["satMap", false]}) exitWith {
            _st set ["satMap", false];
            private _n = _st getOrDefault ["satN", 0];
            if (["satCan", _n] call _fn) then {
                ["send", ["sat", [_n, "center", [_a select 0, _a select 1]]]] call comspec_atak_native_fnc_tocRoom;
                [format ["SAT %1 : grille %2", _n + 1, [[_a select 0, _a select 1, 0], 6] call comspec_atak_native_fnc_gridRef]] call _setStatus;
            };
            ["layout"] call _fn;
        };
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
                private _k = "";
                private _nm = "";
                {
                    private _p = _x select 2;
                    private _sp = _map ctrlMapWorldToScreen [_p select 0, _p select 1, 0];
                    private _d = ((_sp select 0) - (_cl select 0)) * ((_sp select 0) - (_cl select 0)) + ((_sp select 1) - (_cl select 1)) * ((_sp select 1) - (_cl select 1));
                    if (_d < (_best * _best)) then { _best = sqrt _d; _g = _x param [7, ""]; _k = _x select 0; _nm = _x select 1; };
                } forEach ((call _feedOf) param [1, []]);
                if (_k isNotEqualTo "") then {
                    // Unité la plus proche du clic : choisie (fiche dans SITUATION > CARTE, groupe dans GROUPES).
                    _st set ["selG", _g];
                    _st set ["selU", _k];
                    _st set ["sig", ""];
                    if ((_st getOrDefault ["tab", ""]) in ["CARTE", "GROUPES"]) then { ["refresh"] call _fn; } else {
                        ["status", format ["Unité choisie : %1 · sa fiche est dans SITUATION, onglet CARTE", _nm]] call _fn;
                    };
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
            if (_gp isEqualType [] && {(count _gp) >= 3}) then { _pos = [(_gp select 0) select 0, (_gp select 0) select 1]; _lbl = "grille " + (_gp select 2); };
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
                private _scr = ["screens"] call comspec_atak_native_fnc_tocWall;
                if ((count _scr) isEqualTo 0) exitWith { ["Aucun écran dans la salle de ce poste ni à moins de 30 m"] call _setStatus; };
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
            case "sAUTO"; case "sDAY"; case "sNV"; case "sWHOT"; case "sBHOT"; case "sFUSW"; case "sFUSB": {
                private _n = _st getOrDefault ["satN", 0];
                ["send", ["sat", [_n, "mode", _a select [1]]]] call comspec_atak_native_fnc_tocRoom;
                [format ["SAT %1 : capteur %2", _n + 1, [["name", _a select [1]] call comspec_atak_native_fnc_tocSat, "auto"] select (_a isEqualTo "sAUTO")]] call _setStatus;
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
                private _f = [1.25, 1 / 1.25] select (_a isEqualTo "sIn");
                private _z = ["zoomOf", (((_room0 select 2) param [_n, []]) param [2, 500]) * _f] call comspec_atak_native_fnc_tocSat;
                ["send", ["sat", [_n, "zoom", _f]]] call comspec_atak_native_fnc_tocRoom;
                [format ["SAT %1 : zoom %2X, %3 m de large", _n + 1, _z toFixed 2, round (2 * (["halfOf", _z] call comspec_atak_native_fnc_tocSat))]] call _setStatus;
            };
            case "sTaskGrid": {
                private _n = _st getOrDefault ["satN", 0];
                private _gp = [trim (ctrlText (_ui get "sGrid"))] call comspec_atak_native_fnc_gridParse;
                if ((count _gp) < 3) exitWith { ["Grille illisible : 4 à 10 chiffres, par exemple 123 456"] call _setStatus; };
                if !(["satCan", _n] call _fn) exitWith {};
                ["send", ["sat", [_n, "center", [(_gp select 0) select 0, (_gp select 0) select 1]]]] call comspec_atak_native_fnc_tocRoom;
                [format ["SAT %1 : grille %2", _n + 1, _gp select 2]] call _setStatus;
            };
            case "sMap": {
                private _n = _st getOrDefault ["satN", 0];
                if (!(_st getOrDefault ["satMap", false]) && {!(["satCan", _n] call _fn)}) exitWith {};
                _st set ["satMap", !(_st getOrDefault ["satMap", false])];
                ["layout"] call _fn;
                if (_st get "satMap") then {
                    private _aim = ["aim", [_side, _n]] call comspec_atak_native_fnc_tocSat;
                    if ((count _aim) >= 2) then { ["frame", _aim select 0] call _fn; };
                    ["Cliquez sur la carte : le satellite s'y place"] call _setStatus;
                };
            };
            case "sFollowGo": {
                private _sf = _ui get "sFollow";
                private _v = (_sf lbData (lbCurSel _sf)) splitString "|";
                if ((count _v) < 2) exitWith { ["Choisissez d'abord un groupe ou une unité du réseau"] call _setStatus; };
                ["send", ["sat", [_st getOrDefault ["satN", 0], "follow", [_v select 0, _v select 1]]]] call comspec_atak_native_fnc_tocRoom;
            };
            case "sFollowOff": { ["send", ["sat", [_st getOrDefault ["satN", 0], "unfollow", []]]] call comspec_atak_native_fnc_tocRoom; };
            case "sLog"; case "sTag": {
                // Inscription à la main courante, repère du camp (canal Camp, comme un marqueur posé au téléphone).
                private _n = _st getOrDefault ["satN", 0];
                private _aim = ["aim", [_side, _n]] call comspec_atak_native_fnc_tocSat;
                if ((count _aim) < 2) exitWith { ["Satellite pas encore placé : prenez la main"] call _setStatus; };
                (_aim select 0) params ["_ax", "_ay"];
                private _grid = [[_ax, _ay, 0], 6] call comspec_atak_native_fnc_gridRef;
                private _note = trim (ctrlText (_ui get "sNote"));
                private _ss = (_room0 select 2) param [_n, []];
                if (_a isEqualTo "sLog") exitWith {
                    ["send", ["log", ["SAT", format ["SAT %1 · grille %2 · %3 · zoom %4X%5", _n + 1, _grid, ["name", ["effect", _ss param [3, "AUTO"]] call comspec_atak_native_fnc_tocSat] call comspec_atak_native_fnc_tocSat,
                        (["zoomOf", _ss param [2, 500]] call comspec_atak_native_fnc_tocSat) toFixed 2, ["", " · " + _note] select (_note isNotEqualTo "")]]]] call comspec_atak_native_fnc_tocRoom;
                    ["Vue inscrite à la main courante"] call _setStatus;
                };
                private _i = (missionNamespace getVariable ["COMSPEC_ATAK_TocTagN", 0]) + 1;
                missionNamespace setVariable ["COMSPEC_ATAK_TocTagN", _i];
                private _name = (toUpper ([_note, format ["SAT %1 REP %2", _n + 1, _i]] select (_note isEqualTo ""))) select [0, 40];
                private _m = "";
                {
                    _m = ["create", [format ["_USER_DEFINED #%1/%2/%3", clientOwner, 50000 + _i, _x], [_ax, _ay, 0], _x]] call comspec_atak_native_fnc_markerNet;
                    if (_m isNotEqualTo "") exitWith {};
                } forEach ([1, currentChannel] arrayIntersect [1, currentChannel]);
                if (_m isEqualTo "") exitWith { ["Repère refusé sur le canal Camp par le serveur"] call _setStatus; };
                _m setMarkerTypeLocal "mil_objective";
                _m setMarkerColorLocal "ColorOrange";
                _m setMarkerText _name;
                [_m] call comspec_atak_native_fnc_markerWeb;
                ["send", ["log", ["SAT", format ["Repère %1 posé, grille %2", _name, _grid]]]] call comspec_atak_native_fnc_tocRoom;
                [format ["Repère %1 posé, grille %2", _name, _grid]] call _setStatus;
            };
            case "sOvMRKR"; case "sOvBFT"; case "sOvGRID"; case "sOvDATA"; case "sOvBOX": {
                private _o = call _satOv;
                private _k = _a select [3];
                _o set [_k, !(_o getOrDefault [_k, true])];
                ["satOverlay"] call _fn;
            };
            case "sJit"; case "sTerr": {
                private _n = _st getOrDefault ["satN", 0];
                (((_room0 select 2) param [_n, []]) param [8, [false, true]]) params [["_jit", false], ["_terr", true]];
                ["send", ["sat", [_n, "opt", [["terr", "jit"] select (_a isEqualTo "sJit"), [!_terr, !_jit] select (_a isEqualTo "sJit")]]]] call comspec_atak_native_fnc_tocRoom;
            };
            case "sRefresh": {
                uiNamespace setVariable ["COMSPEC_ATAK_TocSatMk", createHashMap];
                uiNamespace setVariable ["COMSPEC_ATAK_TocSatEdge", createHashMap];
                { (_ui get _x) setVariable ["COMSPEC_ATAK_Sig", []]; } forEach ["sFollow", "sScr"];
                ["satOverlay"] call _fn;
                ["Pistes, marqueurs et contours rechargés (réseau du camp publié toutes les 3 s)"] call _setStatus;
            };
            case "sSend"; case "sClear": {
                // ÉCRAN ISR : un écran ou une dalle de la salle (fn_tocHall), ou tous les écrans du poste (30 m).
                private _n = _st getOrDefault ["satN", 0];
                private _sc = _ui get "sScr";
                private _id = _sc lbData ((lbCurSel _sc) max 0);
                private _all = _id in ["", "ALL"];
                private _scr = if (_all) then { call _nearScreens } else { [objectFromNetId _id] select { !isNull _x } };
                if ((count _scr) isEqualTo 0) exitWith { [["Écran introuvable : choisissez-le de nouveau", "Aucun écran TOC à moins de 30 m du poste"] select _all] call _setStatus; };
                if (_a isEqualTo "sClear") exitWith {
                    private _done = _scr select { ((_x getVariable ["COMSPEC_ATAK_TocScr", []]) param [3, ""]) isEqualTo "SAT" };
                    { ["apply", [_x, "SPLIT"]] call comspec_atak_native_fnc_tocScreen; } forEach _done;
                    [format ["%1 écran(s) revenu(s) à la carte et au flux", count _done]] call _setStatus;
                };
                {
                    if (_all) then { ["view", [_x, "sat", _n]] call comspec_atak_native_fnc_tocScreen; ["ensure", [_x, "SAT"]] call comspec_atak_native_fnc_tocScreen; } else {
                        ["apply", [_x, ["SAT1", "SAT2"] select (_n isEqualTo 1)]] call comspec_atak_native_fnc_tocScreen;
                        if (_x getVariable ["COMSPEC_ATAK_TocOff", false]) then { ["do", ["power", [_x, true]]] call comspec_atak_native_fnc_tocHall; };
                    };
                } forEach _scr;
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
                private _scr = ["screens"] call comspec_atak_native_fnc_tocWall;
                if ((count _scr) isEqualTo 0) exitWith { ["Aucun écran dans la salle de ce poste ni à moins de 30 m"] call _setStatus; };
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
                ["Réponse envoyée au demandeur"] call _setStatus;
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
                private _scr = ["screens"] call comspec_atak_native_fnc_tocWall;
                if ((count _scr) isEqualTo 0) exitWith { ["Aucun écran dans la salle de ce poste ni à moins de 30 m"] call _setStatus; };
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
        private _tab = _st getOrDefault ["tab", "CARTE"];
        private _sideName = (createHashMapFromArray [["WEST", "BLUFOR"], ["EAST", "OPFOR"], ["GUER", "Indépendant"], ["CIV", "Civil"]]) getOrDefault [_side, _side];
        private _age = [-1, round ((serverTime - _ft) max 0)] select (_ft >= 0);
        private _room = ["state", _side] call comspec_atak_native_fnc_tocRoom;
        // Opérateur inscrit au poste (prise du poste) : sinon le joueur devant le PC.
        private _post = (_room select 7) select { (_x select 2) isEqualTo getPlayerUID player };
        // Barre de titre sur deux lignes (poste ; camp et opérateur), heures et liaison à droite sur deux lignes : tout tient
        // aussi en 4:3. Tailles de texte communes au PC : titre 1.1, corps 0.85, aide 0.8 (base 0.024 × safeZoneH).
        private _hallRow = ["get", ["of", _st getOrDefault ["obj", objNull]] call comspec_atak_native_fnc_tocHall] call comspec_atak_native_fnc_tocHall;
        // Deuxième ligne de l'en-tête : la salle du poste (à défaut le camp), puis l'opérateur.
        private _hallTxt = [_sideName, [_hallRow param [1, ""]] call _esc] select ((_hallRow param [1, ""]) isNotEqualTo "");
        (_ui get "title") ctrlSetStructuredText parseText (["ink", ((format ["<t size='1.1' font='COMSPEC_BarlowBold'>POSTE TOC · %1</t><br/><t size='0.8' color='%4'>%2 · %3</t>", [_cs] call _esc, _hallTxt,
            [format ["opérateur %1", [name player] call _esc], format ["poste pris par %1 à %2Z", [name player] call _esc, (_post param [0, []]) param [3, ""]]] select ((count _post) > 0), ["@muted", "@accent"] select ((count _post) > 0)]) call comspec_atak_native_fnc_tr)] call comspec_atak_native_fnc_tocTheme);
        ([["postGo", "PRENDRE LE POSTE", "POSTE"], ["postGo", "LIBÉRER LE POSTE", "LIBÉRER"]] select ((count _post) > 0)) call _relabel;
        // Liaisons : flux du serveur (sans lui, la console tient sur la source directe : jamais « hors ligne » en rouge),
        // session Athena (lue, aucune requête).
        private _live = [["<t color='@amber'>● SOURCE DIRECTE SEULE</t>", "<t color='@amber'>● SERVEUR EN RETARD</t>"] select (_age >= 0), "<t color='@accent'>● SERVEUR EN DIRECT</t>"] select (_age >= 0 && {_age < 12});
        private _ath = ["<t color='@muted'>○ ATHENA NON RELIÉE</t>", "<t color='@accent'>● ATHENA</t>"] select (missionNamespace getVariable ["COMSPEC_AthenaReady", false]);
        // Liaison radio du poste : ACRE2 ou TFAR chargé, radios de l'opérateur (lecture seule, fn_mpu5).
        private _radio = switch (true) do {
            case (!isNil "acre_api_fnc_getCurrentRadioList"): {
                private _nr = count (["radios"] call comspec_atak_native_fnc_mpu5);
                format ["<t color='%1'>ACRE2</t>", ["@amber", "@accent"] select (_nr > 0)]
            };
            case (!isNil "TFAR_fnc_activeSwRadio"): { "<t color='@accent'>TFAR</t>" };
            default { "<t color='@muted'>SANS RADIO</t>" };
        };
        (_ui get "clock") ctrlSetStructuredText parseText (["ink", ((format ["<t align='right' size='0.9'><t font='EtelkaMonospaceProBold'>%1</t><t size='0.75' color='@muted'> ZULU   </t><t font='EtelkaMonospacePro' color='@text2'>%2</t><t size='0.75' color='@muted'> LOCAL</t></t><br/><t align='right' size='0.75'>%3<t color='@muted'> · </t>%4<t color='@muted'> · </t>%5</t>",
            ["zulu"] call comspec_atak_native_fnc_tocRoom, [dayTime, "HH:MM:SS"] call BIS_fnc_timeToString, _radio, _live, _ath]) call comspec_atak_native_fnc_tr)] call comspec_atak_native_fnc_tocTheme);
        private _cnt = [0, 0, 0, 0];
        private _nDir = 0;
        { if ((_x param [9, "P"]) isEqualTo "D") then { _nDir = _nDir + (_x param [8, 1]); } else { private _f = (_x select 4) min 3; _cnt set [_f, (_cnt select _f) + (_x param [8, 1])]; }; } forEach _nodes;
        _stats params [["_jam", 0], ["_relays", 0], ["_players", 0], ["_ais", 0]];
        private _msg = (_st getOrDefault ["status", ["", 0]]);
        private _pipN = count ((_st getOrDefault ["pip", []]) select { ((((_x select 0) getVariable ["COMSPEC_ATAK_TocScr", []]) param [3, ""]) isEqualTo "TOC") });
        private _bar = _ui get "bar";
        private _nAl = {(_x param [2, ""]) in ["FLASH", "URGENT"] && {serverTime - (_x param [7, -1e9]) < 1200}} count _events;
        ["alertsGo", (("ALERTES") call _tr) + format [" (%1)", _nAl], "", true] call _relabel; // i18n:brut (déjà traduit)
        (_ui get "alertsGo") ctrlSetBackgroundColor ([(["bg2"] call _tcol), (["redDim"] call _tcol)] select (_nAl > 0));
        (_ui get "alertsGo") ctrlSetTextColor ([(["text2"] call _tcol), (["red"] call _tcol)] select (_nAl > 0));
        private _sMsg = ["", format ["   <t color='@accent'>%1</t>", [_msg select 0] call _esc]] select (diag_tickTime < (_msg select 1));
        private _sPip = [format ["<t color='%2'>%1 alerte(s) sur 20 min</t>", _nAl, ["@muted", "@red"] select (_nAl > 0)], format ["<t color='@amber'>PIP : %1 écran(s)</t>", _pipN]] select (_pipN > 0);
        // Téléphones (réseau ATAK) d'un côté, unités sans téléphone lues dans le jeu de l'autre : jamais « perdu » faute de téléphone.
        _bar ctrlSetStructuredText parseText (["ink", ((format ["<t size='0.85'>  TÉLÉPHONES  <t color='@accent'>%1 en ligne</t> · <t color='@amber'>%2 anciens</t> · <t color='@lost'>%3 sans liaison</t> · <t color='@muted'>%4 éteints</t>   SOURCE DIRECTE  <t color='@text2'>%5 sans téléphone</t>   <t color='@muted'>%6 relais · %7 brouilleurs</t>   %9%8</t>",
            _cnt select 0, _cnt select 1, _cnt select 2, _cnt select 3, _nDir, _relays, _jam, _sMsg, _sPip]) call comspec_atak_native_fnc_tr)] call comspec_atak_native_fnc_tocTheme);
        // Écran étroit (4:3) : la ligne d'état garde l'essentiel sur une ligne, sans texte coupé.
        if ((ctrlTextHeight _bar) > ((ctrlPosition _bar) select 3) * 1.1) then {
            _bar ctrlSetStructuredText parseText (["ink", ((format ["<t size='0.85'>  <t color='@accent'>%1 au téléphone</t> · <t color='@text2'>%2 sans téléphone</t>%3</t>",
                (_cnt select 0) + (_cnt select 1) + (_cnt select 2) + (_cnt select 3), _nDir, [_sPip, _sMsg] select (_sMsg isNotEqualTo "")]) call comspec_atak_native_fnc_tr)] call comspec_atak_native_fnc_tocTheme);
        };
        // FLASH du poste (fn_tocRoom) : la ligne d'état passe en rouge pendant 3 minutes.
        private _fl = ["flash", _side] call comspec_atak_native_fnc_tocBoard;
        _bar ctrlSetBackgroundColor ([(["bg1"] call _tcol), (["redDim"] call _tcol)] select ((count _fl) > 0));
        if ((count _fl) > 0) then {
            _bar ctrlSetStructuredText parseText (["ink", ((format ["<t size='0.9'>  <t font='COMSPEC_BarlowBold'>FLASH</t> · %1 <t color='@text'>· %2 · %3Z</t></t>", [_fl select 0] call _esc, [_fl select 1] call _esc, _fl select 2]) call comspec_atak_native_fnc_tr)] call comspec_atak_native_fnc_tocTheme);
        };
        private _groupsL = [_nodes] call _groups;
        private _nOrd = count (values ((uiNamespace getVariable ["COMSPEC_ATAK_Data", createHashMap]) getOrDefault ["localTasks", createHashMap]) select { (_x getOrDefault ["issuerUid", ""]) isEqualTo getPlayerUID player });
        // En-tête du panneau de l'onglet : titre à gauche, état à droite (filet « pRule » dessous, posé par la grille).
        private _pStat = switch (_tab) do {
            case "CARTE": { format ["%1 sur la carte · %2 sans téléphone", count _nodes, _nDir] };
            case "DESSIN"; case "PLAN": { format ["%1 éléments au plan", count ((missionNamespace getVariable [format ["COMSPEC_ATAK_Plan_%1", side group player], [0, [], ""]]) param [1, []])] };
            case "GROUPES": { format ["%1 groupes · %2 en ligne", count _groupsL, (_cnt select 0) + _nDir] };
            case "ORBAT": { format ["%1 membres", (["store"] call comspec_atak_native_fnc_bftOrbat) get "total"] };
            case "FLUX": { format ["%1 entrées · %2 alertes", count _events, _nAl] };
            case "ORDRES": { format ["%1 émis", _nOrd] };
            case "RÉSEAU": { format ["%1 joueurs · %2 IA · %3 relais", _players, _ais, _relays] };
            case "CHRONOS": { format ["%1 chronos", count (_room select 1)] };
            case "MAIN COURANTE": { format ["%1 entrées · ZULU", count (_room select 3)] };
            case "EXCHECK": { format ["%1 / %2 passés", { (_x param [6, ""]) isNotEqualTo "" } count (_room select 4), count (_room select 4)] };
            case "PERTES": { format ["%1 éléments", count (_room select 8)] };
            case "DEMANDES": { format ["%1 reçues", { (_x select 7) isEqualTo "RECUE" } count (_room select 5)] };
            default { "" };
        };
        private _pTitle = (createHashMapFromArray [["ORBAT", "ORBAT DU SITE"], ["PERTES", "PERTES ET RÉSULTATS"], ["RADIO", "RADIOS DE L'OPÉRATEUR"], ["SALLE", "SALLE TOC"], ["CAMP", "MESSAGES AU CAMP"], ["CARTE", "UNITÉS DU CAMP"], ["DESSIN", "DESSINER SUR LA CARTE"], ["PLAN", "PLAN DU CAMP"], ["MUR", "MUR D'IMAGES"]]) getOrDefault [_tab, _tab];
        private _ph = _ui get "pHead";
        private _phs = format ["<t size='0.95' font='COMSPEC_BarlowBold' color='@text'>%1</t><t align='right' size='0.8' color='@muted'>%2</t>", _pTitle, [_pStat] call _esc];
        if ((_ph getVariable ["COMSPEC_ATAK_Txt", ""]) isNotEqualTo _phs) then { _ph setVariable ["COMSPEC_ATAK_Txt", _phs]; _ph ctrlSetStructuredText parseText (_phs call _tink); };
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
        // Bas du rail et ligne de la carte (fn_tocApp), chaque seconde.
        ["tick", [_nodes, _cnt, _nDir]] call comspec_atak_native_fnc_tocApp;
        switch (_tab) do {
            case "CARTE"; case "DESSIN"; case "PLAN": { ["refresh", [_tab, _nodes]] call comspec_atak_native_fnc_tocApp; };
            case "MUR": {
                ["refresh"] call comspec_atak_native_fnc_tocHallUi;
                ["refresh"] call comspec_atak_native_fnc_tocWall;
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
            case "GROUPES": {
                private _lb = _ui get "gList";
                if (_changed) then {
                    _st set ["mute", true];
                    lbClear _lb;
                    {
                        _x params ["_gn", "", "_ld", "_np", "_nAll", "_n", "_best"];
                        private _i = _lb lbAdd ((format ["%1 · %2", _gn, [format ["%1 joueur(s)", _np], format ["%1 IA", _nAll]] select (_np isEqualTo 0)]) call comspec_atak_native_fnc_tr);
                        _lb lbSetData [_i, _gn];
                        _lb lbSetTextRight [_i, (["source directe", "téléphone"] select (_x param [8, false])) call comspec_atak_native_fnc_tr];
                        _lb lbSetColor [_i, [(["accent"] call _tcol), (["amber"] call _tcol), (["lost"] call _tcol), (["muted"] call _tcol), (["muted"] call _tcol)] select ((_best min 4) max 0)];
                        if (_gn isEqualTo (_st getOrDefault ["selG", ""])) then { _lb lbSetCurSel _i; };
                    } forEach _groupsL;
                    if ((count _groupsL) isEqualTo 0) then { _lb lbAdd (("Aucun groupe du camp") call comspec_atak_native_fnc_tr); };
                    _st set ["mute", false];
                };
                private _g = _groupsL select { (_x select 0) isEqualTo (_st getOrDefault ["selG", ""]) };
                (_ui get "gInfo") ctrlSetStructuredText parseText (["ink", (((if ((count _g) isEqualTo 0) then {
                    format ["<t size='0.9' color='@muted'>%1 groupe(s) dans le camp. Choisissez un groupe : la carte se centre sur lui, puis ORDRE AU GROUPE ou MESSAGE AU CHEF.</t>", count _groupsL]
                } else {
                    (_g select 0) params ["_gn", "", "_ld", "_np", "_nAll", "_n", "_best", "_p", ["_phone", false]];
                    private _ao = (missionNamespace getVariable ["COMSPEC_ATAK_AiOrds", createHashMap]) getOrDefault [["netId", group _ld] call comspec_atak_native_fnc_aiOrder, createHashMap];
                    format ["<t size='1.05' font='COMSPEC_BarlowBold'>%1</t><br/><t size='0.9'>Chef %2 %3<br/>%4 vivant(s) dont %5 joueur(s) · %6<br/>%7%8</t>",
                        [_gn] call _esc, [name _ld] call _esc, ["", format [" · n° %1", ([_ld] call comspec_atak_native_fnc_phoneIdent) select 0]] select (isPlayer _ld), _nAll, _np, ["sans téléphone", "téléphone sur le réseau"] select _phone,
                        // if, pas [a, b] select : les deux branches seraient évaluées et _p vide (groupe sans ATAK) planterait le rafraîchissement.
                        if ((count _p) < 2) then { "<t color='@amber'>Position inconnue du poste : la mission a coupé la source directe et ce groupe n'a aucun téléphone</t>" } else {
                            format ["Grille %1 · %2", [[_p select 0, _p select 1, 0], 6] call comspec_atak_native_fnc_gridRef, if (_phone) then { ["en direct", "point ancien", "point perdu", "téléphone hors ligne"] select ((_best min 3) max 0) } else { "source directe" }] },
                        if (_np > 0) then { "" } else { format ["<br/><t color='@amber'>Ordre IA : %1</t>", [["aucun", format ["%1 (%2)", _ao get "lbl", _ao getOrDefault ["text", ""]]] select ("lbl" in _ao)] call _esc] }]
                })) call comspec_atak_native_fnc_tr)] call comspec_atak_native_fnc_tocTheme);
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
                        _lb lbSetColor [_i, switch (true) do { case (_old): { (["muted"] call _tcol) }; case (_prio isEqualTo "FLASH"): { (["red"] call _tcol) }; case (_prio isEqualTo "URGENT"): { (["amber"] call _tcol) }; default { (["text"] call _tcol) }; }];
                        if ((str _k) isEqualTo (_st getOrDefault ["selF", ""])) then { _lb lbSetCurSel _i; };
                    };
                    if ((count _events) isEqualTo 0) then { _lb lbAdd (("Aucun compte rendu ni message pour l'instant") call comspec_atak_native_fnc_tr); };
                    _st set ["mute", false];
                };
                private _i = parseNumber (_st getOrDefault ["selF", "-1"]);
                (_ui get "fInfo") ctrlSetStructuredText parseText (["ink", (((if (_i < 0 || {_i >= (count _events)}) then {
                    "<t size='0.9' color='@muted'>Comptes rendus, alertes, MEDEVAC, BDA, ordres et messages du camp (30 derniers, gardés d'une partie à l'autre).</t>"
                } else {
                    (_events select _i) params [["_time", ""], ["_kind", ""], ["_prio", ""], ["_who", ""], ["_grid", ""], ["_text", ""], ["_p", []], "", ["_old", false]];
                    format ["<t size='1.05' font='COMSPEC_BarlowBold'>%1</t> <t size='0.9' color='@muted'>%2 · %3%4%5</t><br/><t size='0.9'>%6</t>",
                        [_kind] call _esc, _time, [_who] call _esc, ["", " · grille " + _grid] select (_grid isNotEqualTo ""), ["", " · partie précédente"] select _old, [_text] call _esc]
                })) call comspec_atak_native_fnc_tr)] call comspec_atak_native_fnc_tocTheme);
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
                        private _ui0 = _lb lbAdd ((format ["%1%2%3", ["", "   ", "      ", "         "] select (_ud min 3), _uName, ["", format ["  (%1)", _uCode]] select (_uCode isNotEqualTo "")]) call comspec_atak_native_fnc_tr);
                        _lb lbSetColor [_ui0, (["friend"] call _tcol)];
                        private _pad = ["   ", "      ", "         ", "            "] select (_ud min 3);
                        {
                            private _pe = _people getOrDefault [_x select 1, []];
                            private _vac = (_x select 0) isEqualTo "v";
                            private _on = !_vac && {(_x select 1) in _onKey};
                            private _r = _lb lbAdd ((format ["%1%2%3", _pad, ["", (_x select 2) + " : "] select ((_x select 2) isNotEqualTo "" && {!_vac}), _pe param [0, ""]]) call comspec_atak_native_fnc_tr);
                            _lb lbSetData [_r, _x select 1];
                            _lb lbSetTextRight [_r, (([["hors ligne", "poste vacant"] select _vac, "en jeu"] select _on)) call comspec_atak_native_fnc_tr];
                            _lb lbSetColor [_r, [(["lost"] call _tcol), (["accent"] call _tcol)] select _on];
                        } forEach _uRows;
                    } forEach (_store get "units");
                    _st set ["mute", false];
                };
                private _pe = _people getOrDefault [_st getOrDefault ["selB", ""], []];
                (_ui get "bInfo") ctrlSetStructuredText parseText (["ink", ((if ((count _pe) isEqualTo 0) then {
                    format ["<t size='0.85' color='@muted'>%1</t>", [_nTxt] call _esc]
                } else {
                    private _o = _onKey getOrDefault [_st getOrDefault ["selB", ""], objNull];
                    format ["<t size='1.05' font='COMSPEC_BarlowBold'>%1</t><br/><t size='0.85'>%2</t><br/><t size='0.85' color='%3'>%4</t>", [_pe select 0] call _esc,
                        (((_pe select 5) select [0, 3]) apply { [(_x select 0) + (["", " · " + (_x select 1)] select ((_x select 1) isNotEqualTo ""))] call _esc }) joinString "<br/>",
                        ["@muted", "@accent"] select (!isNull _o), ["Hors ligne ou état non communiqué", "En jeu"] select (!isNull _o)]
                }) call comspec_atak_native_fnc_tr)] call comspec_atak_native_fnc_tocTheme);
            };
            case "RÉSEAU": {
                private _w = windDir;
                (_ui get "rInfo") ctrlSetStructuredText parseText (["ink", (((format ["<t size='1.05' font='COMSPEC_BarlowBold'>RÉSEAU</t><br/><t size='0.9'><t color='@accent'>%1 en ligne</t> · <t color='@amber'>%2 anciens</t> · <t color='@lost'>%3 perdus</t> · <t color='@muted'>%4 hors ligne</t><br/>%5 joueurs · %6 IA<br/>%7 relais · %8 brouilleurs actifs</t><br/><br/><t size='1.05' font='COMSPEC_BarlowBold'>MÉTÉO</t><br/><t size='0.9'>Nuages %9 %% · pluie %10 %% · brouillard %11 %%<br/>Vent %12 m/s du %13°</t><br/><br/><t size='0.85' color='@muted'>Relais et portée sur la carte : SITUATION, onglet CARTE, calques RELAIS et COUVERTURE.</t>",
                    _cnt select 0, _cnt select 1, _cnt select 2, _cnt select 3, _players, _ais, _relays, _jam,
                    round (overcast * 100), round (rain * 100), round (fog * 100), round (vectorMagnitude wind), round ((_w + 180) mod 360)]) + (format ["<br/><t size='0.85' color='@text2'>Unités sans téléphone lues dans le jeu : %1</t>", _nDir])) call comspec_atak_native_fnc_tr)] call comspec_atak_native_fnc_tocTheme);
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
                private _s = format ["<t size='1.05' font='COMSPEC_BarlowBold'>ORDRES ÉMIS</t> <t size='0.85' color='@muted'>%1</t><br/>", count _mine];
                if ((count _mine) isEqualTo 0) then { _s = _s + "<t size='0.85' color='@muted'>Aucun ordre émis depuis ce poste</t>"; };
                {
                    private _acks = ["list", [_x getOrDefault ["id", ""]]] call comspec_atak_native_fnc_orderAck;
                    private _n = createHashMap;
                    { private _k = _y select 1; _n set [_k, (_n getOrDefault [_k, 0]) + 1]; } forEach _acks;
                    _s = _s + format ["<t size='0.85'><t color='@accent'>%1</t> %2 → %3 · %4<br/><t color='@muted'>   vu %5 · reçu %6 · en cours %7 · terminé %8</t></t><br/>",
                        _x getOrDefault ["time", ""], [_x getOrDefault ["typeLabel", _x getOrDefault ["type", ""]]] call _esc, [["tout le camp", _x getOrDefault ["target", ""]] select ((_x getOrDefault ["target", ""]) isNotEqualTo "")] call _esc,
                        _x getOrDefault ["priority", ""], _n getOrDefault ["SEEN", 0], _n getOrDefault ["ACK", 0], _n getOrDefault ["EXEC", 0], _n getOrDefault ["DONE", 0]];
                } forEach ((_mine select [0 max ((count _mine) - 5)]) apply { _x });
                // Missions de tir du camp (appui feu typé, fn_fireSupport) : texte déjà traduit, ajouté après.
                (_ui get "oInfo") ctrlSetStructuredText parseText (["ink", (((((_s) call comspec_atak_native_fnc_tr) + (["tocText"] call comspec_atak_native_fnc_fireSupport))) call comspec_atak_native_fnc_tr)] call comspec_atak_native_fnc_tocTheme);
            };
            case "SAT": {
                private _n = _st getOrDefault ["satN", 0];
                // Console de l'opérateur : ce satellite est rendu sur ce poste en haute résolution tant que l'onglet est
                // ouvert (30 s de plus après).
                ["need", [_side, _n, 3, true]] call comspec_atak_native_fnc_tocSat;
                private _on = [0.18, 0.38, 0.21, 1];
                private _off = [0.07, 0.085, 0.075, 1];
                { (_ui get ("sSlot" + str _forEachIndex)) ctrlSetBackgroundColor ([_off, _on] select (_forEachIndex isEqualTo _n)); } forEach [0, 1];
                private _s = (_room select 2) param [_n, []];
                private _mine = (_s param [7, ""]) isEqualTo getPlayerUID player;
                private _who = _s param [6, ""];
                // Pilotable : j'ai la main, ou le satellite est libre (la première commande me la donne, fn_tocRoom).
                private _can = _mine || {_who isEqualTo ""};
                private _map = _st getOrDefault ["satMap", false];
                private _tex = ["tex", [_side, _n]] call comspec_atak_native_fnc_tocSat;
                private _pic = _ui get "sPic";
                if ((_pic getVariable ["COMSPEC_ATAK_Tex", ""]) isNotEqualTo _tex) then {
                    _pic setVariable ["COMSPEC_ATAK_Tex", _tex];
                    _pic ctrlSetText ([_tex, "#(argb,8,8,3)color(0,0,0,1)"] select (_tex isEqualTo "")); // i18n:brut (texture)
                };
                (_ui get "sWait") ctrlShow (_tex isEqualTo "" && {!_map});
                if (_tex isEqualTo "") then { (_ui get "sWait") ctrlSetStructuredText parseText (["ink", (("<t align='center' size='1.05' color='@text2'>Image satellite en cours d'acquisition…</t><br/><t align='center' size='0.8' color='@muted'>Image noire : activez « Image dans l'image » (Options > Vidéo)</t>") call comspec_atak_native_fnc_tr)] call comspec_atak_native_fnc_tocTheme); };
                // Capteur (AUTO : jour le jour, thermique la nuit), calques de ce PC, options du satellite : allumés en vert.
                private _m = _s param [3, "AUTO"];
                if (_m isEqualTo "TI") then { _m = "WHOT"; };
                { (_ui get ("s" + _x)) ctrlSetBackgroundColor ([_off, _on] select (_x isEqualTo _m)); } forEach ["AUTO", "DAY", "NV", "WHOT", "BHOT", "FUSW", "FUSB"];
                private _ov = call _satOv;
                { (_ui get ("sOv" + _x)) ctrlSetBackgroundColor ([_off, _on] select (_ov getOrDefault [_x, true])); } forEach ["MRKR", "BFT", "GRID", "DATA", "BOX"];
                (_s param [8, [false, true]]) params [["_jit", false], ["_terr", true]];
                (_ui get "sJit") ctrlSetBackgroundColor ([_off, _on] select _jit);
                (_ui get "sTerr") ctrlSetBackgroundColor ([_off, _on] select _terr);
                (_ui get "sMap") ctrlSetBackgroundColor ([_off, (["amber"] call _tcol)] select _map);
                private _lab = ["label", [_side, _n]] call comspec_atak_native_fnc_tocSat;
                (_ui get "sStrip") ctrlSetStructuredText parseText (["ink", ((format ["<t size='0.85'>  %1 · %2%3</t>", [["<t color='@amber'>Lecture seule</t>", "<t color='@accent'>Vous avez la main</t>"] select _mine, "<t color='@accent'>Libre</t>"] select (_who isEqualTo ""), [_lab] call _esc,
                    ["", "   <t color='@amber'>Cliquez sur la carte : le satellite s'y place</t>"] select _map]) call comspec_atak_native_fnc_tr)] call comspec_atak_native_fnc_tocTheme);
                // En-tête de la console : satellite et main, puis zoom, largeur au sol et grille visée.
                private _aim = ["aim", [_side, _n]] call comspec_atak_native_fnc_tocSat;
                private _where = if ((count _aim) < 2) then { "au-dessus du poste" } else { "grille " + ([[(_aim select 0) select 0, (_aim select 0) select 1, 0], 6] call comspec_atak_native_fnc_gridRef) };
                private _hw = _s param [2, 500];
                private _z = ["zoomOf", _hw] call comspec_atak_native_fnc_tocSat;
                (_ui get "sInfo") ctrlSetStructuredText parseText (["ink", ((format ["<t size='1.05' font='COMSPEC_BarlowBold'>SAT %1 · %2</t><br/><t size='0.85' color='@text2'>%3 de large · %4</t>", _n + 1,
                    [[format ["<t color='@amber'>PILOTÉ PAR %1</t>", toUpper ([_who] call _esc)], "<t color='@accent'>ASSIGNÉ ICI</t>"] select _mine, "<t color='@accent'>LIBRE</t>"] select (_who isEqualTo ""),
                    [format ["%1 m", round (2 * _hw)], format ["%1 km", (round (2 * _hw / 100)) / 10]] select (_hw >= 500), _where]) call comspec_atak_native_fnc_tr)] call comspec_atak_native_fnc_tocTheme);
                (_ui get "sZoom") ctrlSetStructuredText parseText (["ink", format ["<t align='center' size='1.05' font='COMSPEC_BarlowBold'>%1X</t>", _z toFixed 2]] call comspec_atak_native_fnc_tocTheme);
                // Commandes grisées quand un autre opérateur a la main ; la raison s'écrit sur la ligne d'aide au survol.
                private _why = ["", format ["Piloté par %1 : lecture seule. Attendez qu'il rende la main.", _who]] select !_can;
                { (_ui get _x) ctrlEnable _can; (_ui get _x) setVariable ["why", _why]; } forEach ["sAUTO", "sDAY", "sNV", "sWHOT", "sBHOT", "sFUSW", "sFUSB", "sHome", "sN", "sS", "sW", "sE", "sIn", "sOut",
                    "sFollowGo", "sFollowOff", "sTaskGrid", "sMap", "sJit", "sTerr"];
                (_ui get "sTake") ctrlEnable (!_mine);
                (_ui get "sRelease") ctrlEnable _mine;
                (_ui get "sRelease") setVariable ["why", "Vous n'avez pas la main sur ce satellite"];
                // Liste de suivi : groupes puis unités du réseau du camp (jamais une unité sans ATAK).
                private _sf = _ui get "sFollow";
                private _opts = [];
                { _opts pushBackUnique [format ["Groupe %1", _x param [7, ""]], "G|" + (_x param [7, ""])]; } forEach (_nodes select { (_x param [7, ""]) isNotEqualTo "" });
                { _opts pushBack [format ["%1 · %2", _x select 1, _x param [7, ""]], "U|" + (_x select 0)]; } forEach _nodes;
                if ((count _opts) isEqualTo 0) then { _opts = [["Aucune unité du camp connue du poste", ""]]; };
                if ((_sf getVariable ["COMSPEC_ATAK_Sig", []]) isNotEqualTo _opts) then {
                    _sf setVariable ["COMSPEC_ATAK_Sig", _opts];
                    private _keep = _sf lbData ((lbCurSel _sf) max 0);
                    lbClear _sf;
                    { private _i = _sf lbAdd ((_x select 0) call comspec_atak_native_fnc_tr); _sf lbSetData [_i, _x select 1]; } forEach _opts;
                    _sf lbSetCurSel ((_opts findIf { (_x select 1) isEqualTo _keep }) max 0);
                };
                // ÉCRAN ISR : tous les écrans du poste, puis chaque écran ou dalle de la salle (sinon les écrans à 30 m).
                private _hid = ["of", _st getOrDefault ["obj", objNull]] call comspec_atak_native_fnc_tocHall;
                private _hosts = if (_hid isEqualTo "") then { (call _nearScreens) select { isNull (_x getVariable ["COMSPEC_ATAK_TocHost", objNull]) } } else { ["screens", _hid] call comspec_atak_native_fnc_tocHall };
                private _sOpts = [["Tous les écrans du poste (30 m)", "ALL"]];
                {
                    private _h = _x;
                    if ((getNumber (configOf _h >> "comspec_screenRatio")) >= 4) then { continue };
                    private _np = (getNumber (configOf _h >> "comspec_screenPanels")) max 1;
                    for "_k" from 0 to (_np - 1) do {
                        private _po = ["panelOf", [_h, _k]] call comspec_atak_native_fnc_tocScreen;
                        if (isNull _po) then { continue };
                        private _c = _po getVariable ["COMSPEC_ATAK_TocScr", []];
                        private _what = if ((_c param [3, ""]) isEqualTo "SAT") then { format ["SAT %1", (_c param [8, 0]) + 1] } else { (["title", _c param [3, "SPLIT"]] call comspec_atak_native_fnc_tocScreen) param [1, ""] };
                        _sOpts pushBack [[format ["%1 · %2", (_h getVariable ["COMSPEC_ATAK_TocScr", []]) param [0, "TOC"], _what], format ["%1 · dalle %2 · %3", (_h getVariable ["COMSPEC_ATAK_TocScr", []]) param [0, "TOC"], _k + 1, _what]] select (_np > 1), netId _po];
                    };
                } forEach _hosts;
                private _sc = _ui get "sScr";
                if ((_sc getVariable ["COMSPEC_ATAK_Sig", []]) isNotEqualTo _sOpts) then {
                    _sc setVariable ["COMSPEC_ATAK_Sig", _sOpts];
                    private _keep = _sc lbData ((lbCurSel _sc) max 0);
                    lbClear _sc;
                    { private _i = _sc lbAdd ((_x select 0) call comspec_atak_native_fnc_tr); _sc lbSetData [_i, _x select 1]; } forEach _sOpts;
                    _sc lbSetCurSel ((_sOpts findIf { (_x select 1) isEqualTo _keep }) max 0);
                };
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
                (_ui get "tInfo") ctrlSetStructuredText parseText (["ink", (("<t size='0.8' color='@muted'>Chrono : temps écoulé depuis le lancement. Compte à rebours : temps restant, rouge une fois dépassé. Heure H : temps jusqu'à une heure ZULU (H-), puis depuis (H+), toujours en marche. Les chronos sont communs à tous les PC du camp et gardés d'une partie à l'autre.</t>") call comspec_atak_native_fnc_tr)] call comspec_atak_native_fnc_tocTheme);
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
                        _s = _s + format ["<t size='1.05' font='COMSPEC_BarlowBold'>%1</t> <t size='0.85' color='@muted'>canal %2%3</t><br/><t size='0.9'><t color='%4'>● RX</t>   <t color='%5'>● TX</t>   %6 MHz%7 · %8%9</t><br/><br/>",
                            [_name] call _esc, _ch, ["", " · " + ([_lbl] call _esc)] select (_lbl isNotEqualTo ""), ["@lineStrong", "@accent"] select _rxOn, ["@lineStrong", "@red"] select _txing,
                            [_tx toFixed 3, "?"] select (_tx <= 0), ["", format [" (réception %1)", _rx toFixed 3]] select (_rx > 0 && {abs (_rx - _tx) > 0.0005}),
                            [format ["alternat %1", _ptt], "sans alternat"] select (_ptt < 1), ["", " · radio active"] select _cur];
                    } forEach _rad;
                    private _who = _now select { (_x select 0) isNotEqualTo player };
                    if ((count _who) > 0) then {
                        _s = _s + "<t size='0.95' font='COMSPEC_BarlowBold'>EN ÉMISSION</t><br/>";
                        { _s = _s + format ["<t size='0.9'><t color='@accent'>●</t> %1 · %2 MHz%3 · %4 s</t><br/>", [[_x select 0, true] call comspec_atak_native_fnc_unitCallsign] call _esc, (_x select 2) toFixed 3, ["", " · " + ([_x select 3] call _esc)] select ((_x select 3) isNotEqualTo ""), round (_x select 4)]; } forEach _who;
                    };
                } else {
                    _s = switch (true) do {
                        case (!isNil "acre_api_fnc_getCurrentRadioList"): { "<t size='0.9' color='@amber'>ACRE2 est chargé, mais vous ne portez aucune radio. Prenez une radio ACRE2 pour voir ses canaux ici.</t>" };
                        case (!isNil "TFAR_fnc_activeSwRadio"): {
                            private _sw = ["api", ["TFAR_fnc_activeSwRadio", [], ""]] call comspec_atak_native_fnc_radioGuard;
                            private _lr = ["api", ["TFAR_fnc_activeLrRadio", [], []]] call comspec_atak_native_fnc_radioGuard;
                            format ["<t size='1.05' font='COMSPEC_BarlowBold'>TFAR</t><br/><t size='0.9'>Courte portée : %1<br/>Longue portée : %2</t>",
                                [[getText (configFile >> "CfgWeapons" >> _sw >> "displayName")] call _esc, "aucune"] select (!(_sw isEqualType "") || {_sw isEqualTo ""}),
                                [[getText (configOf (_lr select 0) >> "displayName")] call _esc, "aucune"] select (!(_lr isEqualType []) || {(count _lr) < 1} || {!((_lr select 0) isEqualType objNull)})]
                        };
                        default { "<t size='0.9' color='@muted'>Aucun mod radio (ACRE2 ou TFAR) sur ce serveur.</t>" };
                    };
                };
                (_ui get "kInfo") ctrlSetStructuredText parseText (["ink", ((_s + "<br/><t size='0.8' color='@muted'>Voyant RX : un équipier émet sur cette fréquence. Voyant TX : vous émettez. Pour parler, utilisez votre touche d'alternat ACRE2 ou TFAR : le PC ne presse pas l'alternat à votre place.</t>") call comspec_atak_native_fnc_tr)] call comspec_atak_native_fnc_tocTheme);
            };
            case "SALLE": { ["refresh"] call comspec_atak_native_fnc_tocHallUi; ["refresh"] call comspec_atak_native_fnc_tocWall; };
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
                        _lb lbSetTooltip (([_i, (format ["%1Z · %2 · %3", _t, _w, _txt]) call comspec_atak_native_fnc_tr]) call comspec_atak_native_fnc_tr);
                        _lb lbSetColor [_i, (createHashMapFromArray [["FLASH", (["red"] call _tcol)], ["DEMANDE", (["amber"] call _tcol)], ["EXCHECK", (["text2"] call _tcol)], ["POSTE", (["friend"] call _tcol)], ["CR", (["amber"] call _tcol)]]) getOrDefault [_k, (["text"] call _tcol)]];
                    } forEach _l;
                    if ((count _l) isEqualTo 0) then { _lb lbAdd (("Main courante vide") call comspec_atak_native_fnc_tr); };
                };
                (_ui get "lInfo") ctrlSetStructuredText parseText (["ink", (("<t size='0.8' color='@muted'>Heures en ZULU. S'inscrivent seuls : comptes rendus et alertes reçus, demandes, prises de poste, FLASH, chronos, lignes d'exécution passées. Commune à tous les PC du camp et gardée d'une partie à l'autre.</t>") call comspec_atak_native_fnc_tr)] call comspec_atak_native_fnc_tocTheme);
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
                        _lb lbSetColor [_i, [(["text"] call _tcol), (["accent"] call _tcol)] select (_done isNotEqualTo "")];
                        if (_id isEqualTo (_st getOrDefault ["selX", ""])) then { _lb lbSetCurSel _i; };
                    } forEach _ex;
                    if ((count _ex) isEqualTo 0) then { _lb lbAdd (("Aucune ligne") call comspec_atak_native_fnc_tr); };
                };
                (_ui get "xInfo") ctrlSetStructuredText parseText (["ink", (("<t size='0.8' color='@muted'>PASSÉ inscrit l'heure ZULU réelle et une entrée à la main courante. La liste se prépare aussi dans le module Écran TOC (Eden ou Zeus). Commune au camp et gardée d'une partie à l'autre.</t>") call comspec_atak_native_fnc_tr)] call comspec_atak_native_fnc_tocTheme);
            };
            case "PERTES": {
                (["boardText", ["KILLS", _room, _side]] call comspec_atak_native_fnc_tocBoard) params ["_l"];
                // Rien de compté : un texte d'attente plutôt qu'un tableau vide.
                if ((count (_room select 8)) isEqualTo 0) then { _l = _l + "<br/><t size='0.8' color='@muted'>Aucune perte ni résultat pour l'instant : les comptes rendus du réseau (BDA, porteur d'ATAK tué) et la correction à la main les inscrivent ici.</t>"; };
                (_ui get "kcInfo") ctrlSetStructuredText parseText (["ink", ((_l) call comspec_atak_native_fnc_tr)] call comspec_atak_native_fnc_tocTheme);
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
                        _lb lbSetColor [_i, (createHashMapFromArray [["RECUE", (["amber"] call _tcol)], ["PRISE", (["friend"] call _tcol)], ["REFUSEE", (["red"] call _tcol)], ["TERMINEE", (["accent"] call _tcol)]]) getOrDefault [_stt, (["text"] call _tcol)]];
                        if (_id isEqualTo (_st getOrDefault ["selQ", ""])) then { _lb lbSetCurSel _i; };
                    } forEach _rq;
                    if ((count _rq) isEqualTo 0) then { _lb lbAdd (("Aucune demande reçue") call comspec_atak_native_fnc_tr); };
                };
                private _q = _rq select { (_x select 0) isEqualTo (_st getOrDefault ["selQ", ""]) };
                (_ui get "qInfo") ctrlSetStructuredText parseText (["ink", ((if ((count _q) isEqualTo 0) then {
                    "<t size='0.8' color='@muted'>Les demandes des éléments (MEDEVAC, appel de feu, ravitaillement) arrivent ici ; le poste peut aussi en inscrire une lui-même, en bas. Choisissez-en une, écrivez une réponse si besoin, puis son statut : il part au demandeur.</t>"
                } else {
                    (_q select 0) params ["", "_t", "_k", "_by", "", "_grid", "_txt", "_stt", "_ans"];
                    format ["<t size='1.05' font='COMSPEC_BarlowBold'>%1</t> <t size='0.85' color='@muted'>%2Z · %3%4 · %5</t><br/><t size='0.9'>%6</t>%7", [_k] call _esc, _t, [_by] call _esc,
                        ["", " · grille " + _grid] select (_grid isNotEqualTo ""), _stLbl getOrDefault [_stt, _stt], [_txt] call _esc, ["", format ["<br/><t size='0.85' color='@accent'>Réponse : %1</t>", [_ans] call _esc]] select (_ans isNotEqualTo "")]
                }) call comspec_atak_native_fnc_tr)] call comspec_atak_native_fnc_tocTheme);
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
                if ((count _tm) isEqualTo 0) then { _s = _s + "<t size='0.85' color='@muted'>Aucun message envoyé au camp</t>"; };
                private _last = _tm select [0 max ((count _tm) - 5)];
                reverse _last;
                { _s = _s + format ["<t size='0.85'><t color='@accent'>%1</t> %2 · %3<br/>   %4</t><br/>", _x param [0, ""], _x param [2, ""], [_x param [3, ""]] call _esc, [(_x param [5, ""]) select [0, 120]] call _esc]; } forEach _last;
                (_ui get "cInfo") ctrlSetStructuredText parseText (["ink", ((_s) call comspec_atak_native_fnc_tr)] call comspec_atak_native_fnc_tocTheme);
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
        if (_ly getOrDefault ["PLAN", true] || {(_st getOrDefault ["sec", ""]) isEqualTo "PLANS"}) then { ["draw", _map] call comspec_atak_native_fnc_tocDraw; };
        private _feed = call _feedOf;
        _feed params [["_ft", -1], ["_nodes", []], ["_events", []]];
        // Taille des textes : celle d'un écran de PC, jamais sous le plancher en pixels réels des cartes du téléphone
        // (Réglages > Carte, « Taille minimale des textes », fn_mapLbl).
        private _ts = (0.032 * (safeZoneH / 1.2)) max (((profileNamespace getVariable ["COMSPEC_ATAK_MapTextMinPx", 14]) max 12) * 1.1 * pixelH);
        private _selG = _st getOrDefault ["selG", ""];
        private _selU = _st getOrDefault ["selU", ""];
        // Couleur du camp sur la carte : jeton « friend » du thème TOC, comme la console ISR (fn_tocSat). La refonte du
        // thème (4.5.0) avait retiré la définition de _sideRgb du fichier sans retirer ses trois lectures d'ici : une
        // variable jamais définie vaut « rien », drawIcon sortait « 0 éléments fournis, 4 attendus » à chaque image.
        private _sideRgb = (["rgba", ["friend"]] call comspec_atak_native_fnc_tocTheme) select [0, 3];
        if !(_sideRgb isEqualType [] && {(count _sideRgb) isEqualTo 3}) then { _sideRgb = [0.28, 0.62, 1]; };
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
            if ((count _p) >= 2 && {serverTime - _at < 1200} && {_ly getOrDefault ["EV", true]}) then {
                _evs pushBack [[[1, 0.55, 0.2, 1], [1, 0.3, 0.22, 1]] select (_prio in ["FLASH", "URGENT"]), [_p select 0, _p select 1, 0], _kind];
            };
        } forEach _events;
        // Nœuds ATAK du camp : symbole OTAN ami, couleur du camp ; ancien pâli, perdu et hors ligne en gris.
        // Groupe choisi puis nœuds en direct d'abord : ce sont eux qui gardent leur nom quand la carte est chargée.
        // Tri gardé 2 s (avant la 4.9.0 : refait à chaque image).
        private _dk = [_ft, count _nodes, _selG, floor (diag_tickTime / 2)];
        if ((_st getOrDefault ["drawKey", []]) isNotEqualTo _dk) then {
            _st set ["drawKey", _dk];
            _st set ["drawNodes", [_nodes, [], { [(_x select 4) min 3, -10] select (((_x param [7, ""]) isEqualTo _selG) && {_selG isNotEqualTo ""}) }, "ASCEND"] call BIS_fnc_sortBy];
        };
        _nodes = _st get "drawNodes";
        {
            _x params ["_nk", "_cs", "_p", "_dir", "_fr", "_kind", "_ai", ["_grp", ""], ["_n", 1], ["_src", "P"]];
            // Calques (onglet CARTE) : joueurs ou IA, véhicules, aéronefs, unités sans téléphone ; l'unité et le groupe choisis restent.
            private _picked = (_nk isEqualTo _selU) || {_grp isEqualTo _selG && {_selG isNotEqualTo ""}};
            if (!_picked && {!(_ly getOrDefault [["PL", "AI"] select _ai, true]) || {_kind in ["veh", "armor", "boat"] && {!(_ly getOrDefault ["VEH", true])}}
                || {_kind isEqualTo "air" && {!(_ly getOrDefault ["AIR", true])}} || {_src isEqualTo "D" && {!(_ly getOrDefault ["DIR", true])}}}) then { continue };
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
            if (_nk isEqualTo _selU) then { _map drawIcon ["\A3\ui_f\data\map\markers\military\circle_CA.paa", (["accent"] call _tcol), _pos, 52, 52, 0, "", 0]; };
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
        // Relais, portée, trace de l'unité suivie, échelle et nord (fn_tocApp).
        ["mapDraw", _map] call comspec_atak_native_fnc_tocApp;
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
if (_mode in ["mk", "audit"] && {!isNil "_ret"}) exitWith { _ret };
true
