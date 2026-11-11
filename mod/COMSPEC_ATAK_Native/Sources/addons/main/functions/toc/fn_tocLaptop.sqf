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
        SAT 1 / SAT 2, prise de main (une main par satellite), image jour / nuit / thermique, déplacement, zoom, suivi d'un
        groupe ou d'une unité, clic sur l'image pour centrer ; les autres PC et écrans le montrent en lecture seule ;
      - CHRONOS : chronos du poste (chrono, compte à rebours, heure H en ZULU), communs au camp (fn_tocRoom) ;
      - RADIO : radios ACRE2 de l'opérateur (fn_mpu5, lecture seule) : canal, fréquences, voyants RX / TX, alternat ;
        TFAR : radios actives. Pas de bouton d'alternat : aucune API publique d'ACRE2 ni de TFAR ne presse l'alternat ;
      - DIFFUSER : tous les écrans TOC du camp et leur contenu, contenu au choix sur l'écran choisi, renommer ;
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
    Modes (client) :
      ["open", objet]   ouvre le PC du poste          ["load", display] / ["unload"] (display COMSPEC_RscTocLaptop)
      ["refresh"]       chaque seconde                ["draw", carte] (Draw de la carte)
      ["tab", nom]      "GROUPES" | "ORBAT" | "FLUX" | "ORDRES" | "CAMP" | "RÉSEAU" | "SAT" | "CHRONOS" | "RADIO" | "DIFFUSER"
                        | "MAIN COURANTE" | "EXCHECK" | "PERTES" | "DEMANDES"
      ["satClick", [u, v]] centre le satellite (clic sur l'image)   ["satOverlay"] cadres de suivi (chaque image)
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
private _feedOf = { (missionNamespace getVariable [format ["COMSPEC_ATAK_TocFeed_%1", _side], []]) };
private _esc = { params ["_t"]; if !(_t isEqualType "") then { _t = str _t; }; { _t = [_t, _x select 0, _x select 1] call CBA_fnc_replace; } forEach [["&", "&amp;"], ["<", "&lt;"], [">", "&gt;"]]; _t };
private _sideRgb = (createHashMapFromArray [["WEST", [0.28, 0.62, 1]], ["EAST", [0.95, 0.3, 0.25]], ["GUER", [0.36, 0.85, 0.42]], ["CIV", [0.75, 0.5, 0.95]]]) getOrDefault [_side, [0.28, 0.62, 1]];
// Onglets, en rangées : poste, salle TOC (satellite, chronos, radio, diffusion), registres du poste.
private _tabRows = [["GROUPES", "ORBAT", "FLUX", "ORDRES", "CAMP"], ["RÉSEAU", "SAT", "CHRONOS", "RADIO", "DIFFUSER"], ["MAIN COURANTE", "EXCHECK", "PERTES", "DEMANDES"]];
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
private _setStatus = { params ["_t"]; _st set ["status", [_t, diag_tickTime + 6]]; };

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
        _st set ["tab", _st getOrDefault ["tab", "GROUPES"]];
        _st set ["sig", ""];
        private _W = safeZoneW; private _H = safeZoneH;
        private _mk = {
            params ["_cls", "_pos", ["_key", ""]];
            private _c = _d ctrlCreate [_cls, -1];
            _c ctrlSetPosition _pos;
            _c ctrlCommit 0;
            if (_key isNotEqualTo "") then { _ui set [_key, _c]; };
            _c
        };
        private _btn = {
            params ["_key", "_pos", "_txt", "_code", ["_primary", false], ["_tip", ""]];
            private _c = [["COMSPEC_RscButton", "COMSPEC_RscButtonPrimary"] select _primary, _pos, _key] call _mk;
            _c ctrlSetText ((_txt) call comspec_atak_native_fnc_tr);
            _c ctrlSetFontHeight (_H * 0.019);
            if (_tip isNotEqualTo "") then { _c ctrlSetTooltip ((_tip) call comspec_atak_native_fnc_tr); };
            _c ctrlAddEventHandler ["ButtonClick", _code];
            _c
        };
        private _lbl = {
            params ["_pos", "_txt", ["_key", ""]];
            private _c = ["COMSPEC_RscLabel", _pos, _key] call _mk;
            _c ctrlSetText ((_txt) call comspec_atak_native_fnc_tr);
            _c
        };
        // Coque du PC : bord sombre, écran, marque en bas.
        private _x0 = safeZoneX + _W * 0.025; private _y0 = safeZoneY + _H * 0.022; private _w0 = _W * 0.95; private _h0 = _H * 0.956;
        private _pad = _H * 0.013;
        (["COMSPEC_RscText", [_x0, _y0, _w0, _h0]] call _mk) ctrlSetBackgroundColor [0.085, 0.09, 0.095, 1];
        (["COMSPEC_RscText", [_x0 + _pad * 0.4, _y0 + _pad * 0.4, _w0 - _pad * 0.8, _h0 - _pad * 0.8]] call _mk) ctrlSetBackgroundColor [0.12, 0.125, 0.13, 1];
        private _xi = _x0 + _pad; private _yi = _y0 + _pad; private _wi = _w0 - 2 * _pad; private _hi = _h0 - 2 * _pad - _H * 0.016;
        (["COMSPEC_RscText", [_xi, _yi, _wi, _hi]] call _mk) ctrlSetBackgroundColor [0.025, 0.03, 0.027, 1];
        private _brand = ["COMSPEC_RscTextCenter", [_x0, _yi + _hi + _H * 0.002, _w0, _H * 0.016]] call _mk;
        _brand ctrlSetText (("COMSPEC · POSTE DE COMMANDEMENT") call comspec_atak_native_fnc_tr);
        _brand ctrlSetFontHeight (_H * 0.013);
        _brand ctrlSetTextColor [0.5, 0.53, 0.55, 1];
        // Barre de titre.
        private _th = _H * 0.044;
        (["COMSPEC_RscText", [_xi, _yi, _wi, _th]] call _mk) ctrlSetBackgroundColor [0.06, 0.08, 0.07, 1];
        (["COMSPEC_RscText", [_xi, _yi + _th - _H * 0.003, _wi, _H * 0.003]] call _mk) ctrlSetBackgroundColor (_sideRgb + [1]);
        private _title = ["COMSPEC_RscStructuredText", [_xi + _W * 0.006, _yi + _H * 0.006, _wi * 0.36, _th], "title"] call _mk;
        // Heures ZULU et locale, liaison radio, état du flux ; FLASH du poste ; prise du poste.
        private _clock = ["COMSPEC_RscStructuredText", [_xi + _wi * 0.35, _yi + _H * 0.008, _wi * 0.31, _th], "clock"] call _mk;
        private _fe = ["COMSPEC_RscEdit", [_xi + _wi * 0.665, _yi + _H * 0.008, _wi * 0.08, _th - _H * 0.016], "flashTxt"] call _mk;
        _fe ctrlSetTooltip (("Texte du FLASH : affiché en rouge sur tous les écrans et PC du poste pendant 3 minutes, et inscrit à la main courante. Exemple : CONTACT NORD, TOUS AU ABRI") call comspec_atak_native_fnc_tr);
        ["flashGo", [_xi + _wi * 0.748, _yi + _H * 0.006, _W * 0.045, _th - _H * 0.012], "FLASH", { ["act", "flash"] call comspec_atak_native_fnc_tocLaptop; }, true, "Diffuse le FLASH sur tous les écrans et PC du poste du camp"] call _btn;
        ["postGo", [_xi + _wi * 0.748 + _W * 0.047, _yi + _H * 0.006, _W * 0.09, _th - _H * 0.012], "PRENDRE LE POSTE", { ["act", "login"] call comspec_atak_native_fnc_tocLaptop; }, false, "Inscrit votre nom au poste (barre du haut des PC, main courante) ; un second clic libère le poste"] call _btn;
        ["close", [_xi + _wi - _W * 0.07, _yi + _H * 0.006, _W * 0.066, _th - _H * 0.012], "FERMER", { closeDialog 0; }, false, "Fermer le PC (Échap)"] call _btn;
        // Carte : 66 % de la largeur.
        private _sb = _H * 0.034;
        private _by = _yi + _th + _H * 0.004;
        private _bh = _hi - _th - _sb - _H * 0.008;
        private _mw = _wi * 0.655;
        private _map = ["COMSPEC_RscMapToc", [_xi, _by, _mw, _bh], "map"] call _mk;
        _map ctrlAddEventHandler ["Draw", { private _t = diag_tickTime; ["draw", _this select 0] call comspec_atak_native_fnc_tocLaptop; ["carte PC du poste (image)", _t] call comspec_atak_native_fnc_perfAcc; }];
        _map ctrlAddEventHandler ["MouseButtonClick", {
            params ["_m", "_b", "_mx", "_my"];
            if (_b isEqualTo 1) then { ["click", _m ctrlMapScreenToWorld [_mx, _my]] call comspec_atak_native_fnc_tocLaptop; };
            if (_b isEqualTo 0) then { ["left", _m ctrlMapScreenToWorld [_mx, _my]] call comspec_atak_native_fnc_tocLaptop; };
        }];
        private _tb = _H * 0.032; private _tw = _W * 0.062; private _tg = _W * 0.004;
        {
            _x params ["_k", "_t", "_tip"];
            [_k, [_xi + _tg + _forEachIndex * (_tw + _tg), _by + _tg, _tw, _tb], _t, compile format ["['frame', %1] call comspec_atak_native_fnc_tocLaptop;", str (_k select [5])], false, _tip] call _btn;
        } forEach [["frame" + "net", "RÉSEAU", "Cadrer tout le réseau du camp"], ["frame" + "me", "POSTE", "Centrer sur le poste"], ["frame" + "sel", "SÉLECTION", "Centrer sur le groupe ou le compte rendu choisi"]];
        // Outils et calques de la carte, à la suite des boutons de cadrage.
        {
            _x params ["_k", "_t", "_code", "_tip"];
            [_k, [_xi + _tg + (3 + _forEachIndex) * (_tw + _tg), _by + _tg, _tw, _tb], _t, _code, false, _tip] call _btn;
        } forEach [
            ["toolMEASURE", "MESURE", { ["tool", "MEASURE"] call comspec_atak_native_fnc_tocLaptop; }, "Clic gauche sur A puis sur B"],
            ["toolMARKER", "MARQUEUR", { ["tool", "MARKER"] call comspec_atak_native_fnc_tocLaptop; }, "Clic gauche : pose un marqueur, comme le téléphone"],
            ["layerai", "IA", { ["layer", "ai"] call comspec_atak_native_fnc_tocLaptop; }, "Afficher ou masquer les nœuds IA"],
            ["layerev", "ALERTES", { ["layer", "ev"] call comspec_atak_native_fnc_tocLaptop; }, "Afficher ou masquer les comptes rendus et alertes"]
        ];
        private _sx = _xi + _tg + 7 * (_tw + _tg);
        ["COMSPEC_RscEdit", [_sx, _by + _tg, _W * 0.11, _tb], "search"] call _mk;
        (_ui get "search") ctrlSetTooltip (("Grille, groupe, indicatif ou nom de marqueur") call comspec_atak_native_fnc_tr);
        ["searchGo", [_sx + _W * 0.112, _by + _tg, _W * 0.058, _tb], "CHERCHER", { ["search"] call comspec_atak_native_fnc_tocLaptop; }] call _btn;
        private _hint = ["COMSPEC_RscText", [_xi + _tg, _by + _bh - _H * 0.03, _mw - 2 * _tg, _H * 0.026], "hint"] call _mk;
        _hint ctrlSetBackgroundColor [0, 0, 0, 0.55];
        _hint ctrlSetFontHeight (_H * 0.017);
        ["tool", _st getOrDefault ["tool", "SELECT"]] call _fn;
        // Panneau de droite : onglets.
        private _px = _xi + _mw + _W * 0.004; private _pw = _wi - _mw - _W * 0.004;
        (["COMSPEC_RscText", [_px, _by, _pw, _bh]] call _mk) ctrlSetBackgroundColor [0.045, 0.055, 0.048, 1];
        // Onglets sur trois rangées : poste, salle TOC (satellite, chronos, radio, diffusion), registres du poste.
        private _rowsT = _tabRows;
        private _tabH = _H * 0.036;
        {
            private _row = _forEachIndex;
            private _tabW = _pw / (count _x);
            {
                (["tab" + _x, [_px + _forEachIndex * _tabW, _by + _row * (_tabH + _H * 0.003), _tabW - _W * 0.002, _tabH], _x, compile format ["['tab', %1] call comspec_atak_native_fnc_tocLaptop;", str _x]] call _btn) ctrlSetFontHeight (_H * 0.0155);
            } forEach _x;
        } forEach _rowsT;
        private _m = _W * 0.006;
        private _cx = _px + _m; private _cw = _pw - 2 * _m;
        private _cy = _by + (count _tabRows) * (_tabH + _H * 0.003) + _H * 0.008; private _ch = _bh - (_cy - _by) - _H * 0.006;
        private _bw3 = (_cw - 2 * _m) / 3;
        private _rh = _H * 0.034;
        // GROUPES
        private _gl = ["COMSPEC_RscListBox", [_cx, _cy, _cw, _ch * 0.56], "gList"] call _mk;
        _gl ctrlAddEventHandler ["LBSelChanged", { params ["_c", "_i"]; ["pick", ["GROUPES", _c lbData _i]] call comspec_atak_native_fnc_tocLaptop; }];
        ["COMSPEC_RscStructuredText", [_cx, _cy + _ch * 0.58, _cw, _ch * 0.27], "gInfo"] call _mk;
        // Ordre réel à un groupe IA (fn_aiOrder), au point choisi d'un clic droit sur la carte.
        private _ga = ["COMSPEC_RscCombo", [_cx, _cy + _ch - 2 * _rh - _H * 0.008, _cw - _bw3 - _m, _rh], "gAi"] call _mk;
        { if ((_x select 0) isNotEqualTo "FOLLOW") then { private _i = _ga lbAdd ((_x select 1) call comspec_atak_native_fnc_tr); _ga lbSetData [_i, _x select 0]; }; } forEach (["kinds"] call comspec_atak_native_fnc_aiOrder);
        ["gAiGo", [_cx + _cw - _bw3, _cy + _ch - 2 * _rh - _H * 0.008, _bw3, _rh], "ORDRE IA", { ["act", "gAiGo"] call comspec_atak_native_fnc_tocLaptop; }, true, "Le groupe IA choisi exécute l'ordre au point du clic droit (sinon sur place)"] call _btn;
        ["gOrder", [_cx, _cy + _ch - _rh, _bw3, _rh], "ORDRE AU GROUPE", { ["act", "gOrder"] call comspec_atak_native_fnc_tocLaptop; }, true] call _btn;
        ["gMsg", [_cx + _bw3 + _m, _cy + _ch - _rh, _bw3, _rh], "MESSAGE AU CHEF", { ["act", "gMsg"] call comspec_atak_native_fnc_tocLaptop; }] call _btn;
        ["gCenter", [_cx + 2 * (_bw3 + _m), _cy + _ch - _rh, _bw3, _rh], "CENTRER", { ['frame', 'sel'] call comspec_atak_native_fnc_tocLaptop; }] call _btn;
        // ORBAT du site
        private _bl = ["COMSPEC_RscListBox", [_cx, _cy, _cw, _ch * 0.66], "bList"] call _mk;
        _bl ctrlAddEventHandler ["LBSelChanged", { params ["_c", "_i"]; ["pick", ["ORBAT", _c lbData _i]] call comspec_atak_native_fnc_tocLaptop; }];
        ["COMSPEC_RscStructuredText", [_cx, _cy + _ch * 0.68, _cw, _ch * 0.24], "bInfo"] call _mk;
        ["bMsg", [_cx, _cy + _ch - _rh, _bw3, _rh], "MESSAGE", { ["act", "bMsg"] call comspec_atak_native_fnc_tocLaptop; }, false, "Message à ce membre, s'il est en jeu"] call _btn;
        ["bCenter", [_cx + _bw3 + _m, _cy + _ch - _rh, _bw3, _rh], "CENTRER", { ["act", "bCenter"] call comspec_atak_native_fnc_tocLaptop; }] call _btn;
        // RÉSEAU : état, météo, contenu des écrans
        ["COMSPEC_RscStructuredText", [_cx, _cy, _cw, _ch * 0.74], "rInfo"] call _mk;
        [[_cx, _cy + _ch * 0.76, _cw, _H * 0.022], "CONTENU DES ÉCRANS DU POSTE", "rL1"] call _lbl;
        private _rs = ["COMSPEC_RscCombo", [_cx, _cy + _ch * 0.76 + _H * 0.024, _cw - _bw3 - _m, _rh], "rShow"] call _mk;
        { private _i = _rs lbAdd ((_x select 0) call comspec_atak_native_fnc_tr); _rs lbSetData [_i, _x select 1]; } forEach [["Carte et flux", "SPLIT"], ["Carte seule", "MAP"], ["Flux seul", "FEED"], ["Réseau", "NET"], ["ORBAT du site", "ORBAT"], ["PIP du poste", "TOC"], ["Vue satellite", "SAT"], ["Horloge et chronos", "CLOCK"], ["Diapositive du Briefing", "SLIDES"]];
        _rs lbSetCurSel 0;
        ["rGo", [_cx + _cw - _bw3, _cy + _ch * 0.76 + _H * 0.024, _bw3, _rh], "AFFICHER", { ["act", "rGo"] call comspec_atak_native_fnc_tocLaptop; }, true, "Sur tous les écrans TOC à moins de 30 m du poste"] call _btn;
        // FLUX
        private _ff = ["COMSPEC_RscCombo", [_cx, _cy, _cw, _rh], "fFilter"] call _mk;
        { private _i = _ff lbAdd ((_x select 0) call comspec_atak_native_fnc_tr); _ff lbSetData [_i, _x select 1]; } forEach [["Tout le flux", ""], ["Alertes (PANIQUE, TIC, MEDEVAC…)", "A"], ["Comptes rendus", "R"], ["Ordres et messages", "M"]];
        _ff lbSetCurSel 0;
        _ff ctrlAddEventHandler ["LBSelChanged", { params ["_c", "_i"]; (uiNamespace getVariable ["COMSPEC_ATAK_TocLap", createHashMap]) set ["fKind", _c lbData _i]; (uiNamespace getVariable ["COMSPEC_ATAK_TocLap", createHashMap]) set ["sig", ""]; }];
        private _fl = ["COMSPEC_RscListBox", [_cx, _cy + _rh + _H * 0.004, _cw, _ch * 0.66 - _rh - _H * 0.004], "fList"] call _mk;
        _fl ctrlAddEventHandler ["LBSelChanged", { params ["_c", "_i"]; ["pick", ["FLUX", _c lbData _i]] call comspec_atak_native_fnc_tocLaptop; }];
        ["COMSPEC_RscStructuredText", [_cx, _cy + _ch * 0.68, _cw, _ch * 0.24], "fInfo"] call _mk;
        ["fCenter", [_cx, _cy + _ch - _rh, _bw3, _rh], "CENTRER", { ['frame', 'sel'] call comspec_atak_native_fnc_tocLaptop; }] call _btn;
        // ORDRES
        private _y = _cy;
        private _half = (_cw - _m) / 2;
        [[_cx, _y, _half, _H * 0.022], "TYPE", "oL1"] call _lbl;
        [[_cx + _half + _m, _y, _half, _H * 0.022], "PRIORITÉ", "oL2"] call _lbl;
        _y = _y + _H * 0.024;
        private _ok = ["COMSPEC_RscCombo", [_cx, _y, _half, _rh], "oKind"] call _mk;
        { private _i = _ok lbAdd (((_x select 0)) call comspec_atak_native_fnc_tr); _ok lbSetData [_i, _x select 1]; } forEach _kinds;
        private _op = ["COMSPEC_RscCombo", [_cx + _half + _m, _y, _half, _rh], "oPrio"] call _mk;
        { private _i = _op lbAdd ((_x) call comspec_atak_native_fnc_tr); _op lbSetData [_i, _x]; } forEach ["ROUTINE", "IMPORTANT", "URGENT", "FLASH"];
        _y = _y + _rh + _H * 0.008;
        [[_cx, _y, _cw, _H * 0.022], "DESTINATAIRE", "oL3"] call _lbl;
        _y = _y + _H * 0.024;
        ["COMSPEC_RscCombo", [_cx, _y, _cw, _rh], "oTarget"] call _mk;
        _y = _y + _rh + _H * 0.008;
        [[_cx, _y, _cw, _H * 0.022], "CONSIGNE", "oL4"] call _lbl;
        _y = _y + _H * 0.024;
        ["COMSPEC_RscEditMulti", [_cx, _y, _cw, _ch * 0.16], "oText"] call _mk;
        _y = _y + _ch * 0.16 + _H * 0.008;
        [[_cx, _y, _cw, _H * 0.022], "OBJECTIF (CLIC DROIT SUR LA CARTE OU GRILLE)", "oL5"] call _lbl;
        _y = _y + _H * 0.024;
        ["COMSPEC_RscEdit", [_cx, _y, _cw - _bw3 - _m, _rh], "oGrid"] call _mk;
        ["oClear", [_cx + _cw - _bw3, _y, _bw3, _rh], "EFFACER", { ["act", "oClear"] call comspec_atak_native_fnc_tocLaptop; }] call _btn;
        _y = _y + _rh + _H * 0.01;
        ["oSend", [_cx, _y, _cw, _rh * 1.15], "ENVOYER L'ORDRE", { ["act", "oSend"] call comspec_atak_native_fnc_tocLaptop; }, true, "L'ordre arrive dans l'app Tâches des destinataires"] call _btn;
        _y = _y + _rh * 1.15 + _H * 0.01;
        ["COMSPEC_RscStructuredText", [_cx, _y, _cw, (_cy + _ch) - _y], "oInfo"] call _mk;
        // CAMP
        _y = _cy;
        [[_cx, _y, _cw, _H * 0.022], "DESTINATAIRE", "cL1"] call _lbl;
        _y = _y + _H * 0.024;
        ["COMSPEC_RscCombo", [_cx, _y, _cw - _bw3 - _m, _rh], "cDest"] call _mk;
        private _cp = ["COMSPEC_RscCombo", [_cx + _cw - _bw3, _y, _bw3, _rh], "cPrio"] call _mk;
        { private _i = _cp lbAdd ((_x) call comspec_atak_native_fnc_tr); _cp lbSetData [_i, _x]; } forEach ["ROUTINE", "URGENT", "FLASH"];
        _y = _y + _rh + _H * 0.008;
        [[_cx, _y, _cw, _H * 0.022], "MESSAGE", "cL2"] call _lbl;
        _y = _y + _H * 0.024;
        ["COMSPEC_RscEditMulti", [_cx, _y, _cw, _ch * 0.2], "cText"] call _mk;
        _y = _y + _ch * 0.2 + _H * 0.01;
        ["cSend", [_cx, _y, _cw, _rh * 1.15], "ENVOYER", { ["act", "cSend"] call comspec_atak_native_fnc_tocLaptop; }, true, "Arrive dans Messages, conversation TOC du poste"] call _btn;
        _y = _y + _rh * 1.15 + _H * 0.016;
        [[_cx, _y, _cw, _H * 0.022], "INDICATIF DU POSTE (CONFIG.JSON, TOC.CALLSIGN)", "cL3"] call _lbl;
        _y = _y + _H * 0.024;
        private _cs = ["COMSPEC_RscEdit", [_cx, _y, _cw - _bw3 - _m, _rh], "cCs"] call _mk;
        _cs ctrlSetText (["callsign"] call comspec_atak_native_fnc_tocPost); // i18n:brut (saisi, réenregistré tel quel)
        ["cSave", [_cx + _cw - _bw3, _y, _bw3, _rh], "ENREGISTRER", { ["act", "cSave"] call comspec_atak_native_fnc_tocLaptop; }] call _btn;
        _y = _y + _rh + _H * 0.016;
        // Caméra en direct sur les écrans TOC à côté du poste (fn_tocCam).
        [[_cx, _y, _cw, _H * 0.022], "CAMÉRA SUR LES ÉCRANS DU POSTE", "cL4"] call _lbl;
        _y = _y + _H * 0.024;
        ["COMSPEC_RscCombo", [_cx, _y, _cw - _bw3 - _m, _rh], "cCam"] call _mk;
        ["cCamGo", [_cx + _cw - _bw3, _y, _bw3, _rh], "AFFICHER", { ["act", "cCamGo"] call comspec_atak_native_fnc_tocLaptop; }, false, "Les écrans TOC à moins de 30 m montrent cette caméra en direct ; « Carte et flux » les remet à l'affichage du poste"] call _btn;
        _y = _y + _rh + _H * 0.012;
        ["COMSPEC_RscStructuredText", [_cx, _y, _cw, (_cy + _ch) - _y], "cInfo"] call _mk;
        // SAT : l'image satellite prend la place de la carte (cadre 16:9), cadres de suivi par-dessus, bandeau d'état en haut.
        (["COMSPEC_RscText", [_xi, _by, _mw, _bh], "sBack"] call _mk) ctrlSetBackgroundColor [0, 0, 0, 1];
        private _ar = (_mw / pixelW) / (_bh / pixelH);
        private _sw = _mw; private _sh = _bh;
        if (_ar > 16 / 9) then { _sw = _mw * (16 / 9) / _ar; } else { _sh = _bh * _ar / (16 / 9); };
        private _sRect = [_xi + (_mw - _sw) / 2, _by + (_bh - _sh) / 2, _sw, _sh];
        _st set ["satRect", _sRect];
        ["RscPicture", _sRect, "sPic"] call _mk;
        private _boxes = [];
        for "_i" from 1 to 40 do {
            private _b = [];
            for "_j" from 1 to 4 do { _b pushBack (["COMSPEC_RscText", [0, 0, 0, 0]] call _mk); };
            _b pushBack (["RscPicture", [0, 0, 0, 0]] call _mk);
            private _t = ["COMSPEC_RscText", [0, 0, 0, 0]] call _mk;
            _t ctrlSetFontHeight (_H * 0.016); _t ctrlSetBackgroundColor [0, 0, 0, 0.55];
            _b pushBack _t;
            { _x ctrlShow false; } forEach _b;
            _boxes pushBack _b;
        };
        _st set ["satBoxes", _boxes];
        private _sStrip = ["COMSPEC_RscStructuredText", [_xi, _by, _mw, _H * 0.03], "sStrip"] call _mk;
        _sStrip ctrlSetBackgroundColor [0, 0, 0, 0.7];
        private _sWait = ["COMSPEC_RscStructuredText", [_xi, _by + _bh * 0.45, _mw, _H * 0.06], "sWait"] call _mk;
        _sWait ctrlShow false;
        // Clic sur l'image : le satellite se centre sur ce point (opérateur qui a la main).
        _d displayAddEventHandler ["MouseButtonDown", {
            params ["", "_b", "_mx", "_my"];
            private _st = uiNamespace getVariable ["COMSPEC_ATAK_TocLap", createHashMap];
            if (_b isNotEqualTo 0 || {(_st getOrDefault ["tab", ""]) isNotEqualTo "SAT"}) exitWith {};
            (_st getOrDefault ["satRect", [0, 0, 0, 0]]) params ["_rx", "_ry", "_rw", "_rh"];
            if (_mx < _rx || {_mx > _rx + _rw} || {_my < _ry + _rh * 0.06} || {_my > _ry + _rh}) exitWith {};
            ["satClick", [(_mx - _rx) / _rw, (_my - _ry) / _rh]] call comspec_atak_native_fnc_tocLaptop;
        }];
        // Commandes du satellite (panneau de droite).
        private _y2 = _cy;
        private _bw2 = (_cw - _m) / 2;
        private _bw4 = (_cw - 3 * _m) / 4;
        { ["sSlot" + str _forEachIndex, [_cx + _forEachIndex * (_bw2 + _m), _y2, _bw2, _rh], _x, compile format ["['act', 'sSlot%1'] call comspec_atak_native_fnc_tocLaptop;", _forEachIndex], false, "Satellite piloté et affiché par ce PC"] call _btn; } forEach ["SAT 1", "SAT 2"];
        _y2 = _y2 + _rh + _H * 0.008;
        ["COMSPEC_RscStructuredText", [_cx, _y2, _cw, _ch * 0.14], "sInfo"] call _mk;
        _y2 = _y2 + _ch * 0.14 + _H * 0.006;
        ["sTake", [_cx, _y2, _bw2, _rh], "PRENDRE LA MAIN", { ["act", "sTake"] call comspec_atak_native_fnc_tocLaptop; }, true, "Un seul opérateur pilote un satellite à la fois ; les autres écrans le montrent en lecture seule"] call _btn;
        ["sRelease", [_cx + _bw2 + _m, _y2, _bw2, _rh], "RENDRE LA MAIN", { ["act", "sRelease"] call comspec_atak_native_fnc_tocLaptop; }] call _btn;
        _y2 = _y2 + _rh + _H * 0.008;
        { _x params ["_k", "_t", "_tip"]; [_k, [_cx + _forEachIndex * (_bw4 + _m), _y2, _bw4, _rh], _t, compile format ["['act', %1] call comspec_atak_native_fnc_tocLaptop;", str _k], false, _tip] call _btn; } forEach [
            ["sDAY", "JOUR", "Image en couleurs"], ["sNV", "NUIT", "Vision nocturne verte"], ["sTI", "THERMIQUE", "Image thermique (chaud en blanc)"], ["sHome", "POSTE", "Centre le satellite sur le poste"]];
        _y2 = _y2 + _rh + _H * 0.008;
        { _x params ["_k", "_t", "_tip"]; [_k, [_cx + _forEachIndex * (_bw4 + _m), _y2, _bw4, _rh], _t, compile format ["['act', %1] call comspec_atak_native_fnc_tocLaptop;", str _k], false, _tip] call _btn; } forEach [
            ["sN", "▲ NORD", "Déplace la vue d'un tiers vers le nord"], ["sS", "▼ SUD", "Déplace la vue d'un tiers vers le sud"], ["sW", "◀ OUEST", "Déplace la vue d'un tiers vers l'ouest"], ["sE", "EST ▶", "Déplace la vue d'un tiers vers l'est"]];
        _y2 = _y2 + _rh + _H * 0.008;
        ["sIn", [_cx, _y2, _bw2, _rh], "ZOOM +", { ["act", "sIn"] call comspec_atak_native_fnc_tocLaptop; }, false, "Rapproche la vue (largeur divisée par 1,5 ; 80 m de large au plus près)"] call _btn;
        ["sOut", [_cx + _bw2 + _m, _y2, _bw2, _rh], "ZOOM −", { ["act", "sOut"] call comspec_atak_native_fnc_tocLaptop; }, false, "Éloigne la vue (largeur multipliée par 1,5 ; 5 km de large au plus)"] call _btn;
        _y2 = _y2 + _rh + _H * 0.012;
        [[_cx, _y2, _cw, _H * 0.022], "SUIVRE UN GROUPE OU UNE UNITÉ DU RÉSEAU", "sL1"] call _lbl;
        _y2 = _y2 + _H * 0.024;
        private _sf = ["COMSPEC_RscCombo", [_cx, _y2, _cw, _rh], "sFollow"] call _mk;
        _sf ctrlSetTooltip (("Le satellite suit le groupe (centre de ses nœuds ATAK) ou l'unité choisie, à la position que le réseau connaît") call comspec_atak_native_fnc_tr);
        _y2 = _y2 + _rh + _H * 0.006;
        ["sFollowGo", [_cx, _y2, _bw2, _rh], "SUIVRE", { ["act", "sFollowGo"] call comspec_atak_native_fnc_tocLaptop; }, true] call _btn;
        ["sFollowOff", [_cx + _bw2 + _m, _y2, _bw2, _rh], "ARRÊTER LE SUIVI", { ["act", "sFollowOff"] call comspec_atak_native_fnc_tocLaptop; }] call _btn;
        _y2 = _y2 + _rh + _H * 0.012;
        ["sShow", [_cx, _y2, _cw, _rh], "AFFICHER CE SATELLITE SUR LES ÉCRANS DU POSTE", { ["act", "sShow"] call comspec_atak_native_fnc_tocLaptop; }, false, "Écrans TOC à moins de 30 m du poste : vue satellite en lecture seule"] call _btn;
        _y2 = _y2 + _rh + _H * 0.008;
        ["COMSPEC_RscStructuredText", [_cx, _y2, _cw, ((_cy + _ch) - _y2) max (_H * 0.03)], "sHelp"] call _mk;
        // CHRONOS : chronos du poste (fn_tocRoom), affichés aussi sur les écrans en affichage horloge.
        private _tl = ["COMSPEC_RscListBox", [_cx, _cy, _cw, _ch * 0.4], "tList"] call _mk;
        _tl ctrlAddEventHandler ["LBSelChanged", { params ["_c", "_i"]; if (_i >= 0) then { (uiNamespace getVariable ["COMSPEC_ATAK_TocLap", createHashMap]) set ["selT", _c lbData _i]; }; }];
        _y2 = _cy + _ch * 0.4 + _H * 0.008;
        { _x params ["_k", "_t", "_tip"]; [_k, [_cx + _forEachIndex * (_bw4 + _m), _y2, _bw4, _rh], _t, compile format ["['act', %1] call comspec_atak_native_fnc_tocLaptop;", str _k], _forEachIndex isEqualTo 0, _tip] call _btn; } forEach [
            ["tStart", "LANCER", "Lance ou reprend le chrono choisi"], ["tStop", "ARRÊTER", "Arrête le chrono choisi à sa valeur"], ["tReset", "ZÉRO", "Remet le chrono choisi à zéro (compte à rebours : à sa durée)"], ["tDel", "SUPPRIMER", "Retire le chrono choisi"]];
        _y2 = _y2 + _rh + _H * 0.014;
        [[_cx, _y2, _cw, _H * 0.022], "NOUVEAU CHRONO", "tL1"] call _lbl;
        _y2 = _y2 + _H * 0.024;
        private _tn = ["COMSPEC_RscEdit", [_cx, _y2, _cw, _rh], "tName"] call _mk;
        _tn ctrlSetTooltip (("Nom affiché sur le tableau, en capitales. Exemple : H-HOUR, ALPHA EN POSITION, RELÈVE") call comspec_atak_native_fnc_tr);
        _y2 = _y2 + _rh + _H * 0.006;
        private _tk = ["COMSPEC_RscCombo", [_cx, _y2, _bw2, _rh], "tKind"] call _mk;
        { private _i = _tk lbAdd ((_x select 0) call comspec_atak_native_fnc_tr); _tk lbSetData [_i, _x select 1]; } forEach [["Chrono (temps écoulé)", "UP"], ["Compte à rebours", "DOWN"], ["Heure H (ZULU)", "AT"]];
        _tk lbSetCurSel 0;
        private _tv = ["COMSPEC_RscEdit", [_cx + _bw2 + _m, _y2, _bw2, _rh], "tVal"] call _mk;
        _tv ctrlSetTooltip (("Compte à rebours : minutes:secondes ou heures:minutes:secondes (exemple 30:00). Heure H : heure ZULU HH:MM (exemple 22:30). Chrono : rien à saisir") call comspec_atak_native_fnc_tr);
        _y2 = _y2 + _rh + _H * 0.006;
        ["tAdd", [_cx, _y2, _bw2, _rh], "CRÉER", { ["act", "tAdd"] call comspec_atak_native_fnc_tocLaptop; }, true] call _btn;
        ["tShow", [_cx + _bw2 + _m, _y2, _bw2, _rh], "HORLOGE SUR LES ÉCRANS", { ["act", "tShow"] call comspec_atak_native_fnc_tocLaptop; }, false, "Écrans TOC à moins de 30 m du poste : heures ZULU et locale, tableau des chronos"] call _btn;
        _y2 = _y2 + _rh + _H * 0.01;
        ["COMSPEC_RscStructuredText", [_cx, _y2, _cw, ((_cy + _ch) - _y2) max (_H * 0.03)], "tInfo"] call _mk;
        // RADIO : réseaux radio de l'opérateur (ACRE2 / TFAR, lecture seule), un bloc par radio.
        ["COMSPEC_RscStructuredText", [_cx, _cy, _cw, _ch], "kInfo"] call _mk;
        // DIFFUSER : tout le mur d'écrans du camp, et ce que chacun montre.
        private _pl = ["COMSPEC_RscListBox", [_cx, _cy, _cw, _ch * 0.46], "pList"] call _mk;
        _pl ctrlAddEventHandler ["LBSelChanged", { params ["_c", "_i"]; if (_i >= 0) then { (uiNamespace getVariable ["COMSPEC_ATAK_TocLap", createHashMap]) set ["selP", _c lbData _i]; }; }];
        _y2 = _cy + _ch * 0.46 + _H * 0.01;
        [[_cx, _y2, _cw, _H * 0.022], "CONTENU DE L'ÉCRAN CHOISI", "pL1"] call _lbl;
        _y2 = _y2 + _H * 0.024;
        ["COMSPEC_RscCombo", [_cx, _y2, _cw - _bw3 - _m, _rh], "pWhat"] call _mk;
        ["pGo", [_cx + _cw - _bw3, _y2, _bw3, _rh], "AFFICHER", { ["act", "pGo"] call comspec_atak_native_fnc_tocLaptop; }, true, "L'écran choisi dans la liste montre ce contenu"] call _btn;
        _y2 = _y2 + _rh + _H * 0.012;
        [[_cx, _y2, _cw, _H * 0.022], "NOM DE L'ÉCRAN CHOISI", "pL2"] call _lbl;
        _y2 = _y2 + _H * 0.024;
        private _pn = ["COMSPEC_RscEdit", [_cx, _y2, _cw - _bw3 - _m, _rh], "pName"] call _mk;
        _pn ctrlSetTooltip (("Nom affiché en tête de l'écran et sur les plaques. Exemple : SAT CENTRE, ALPHA, H-HOUR") call comspec_atak_native_fnc_tr);
        ["pRen", [_cx + _cw - _bw3, _y2, _bw3, _rh], "RENOMMER", { ["act", "pRen"] call comspec_atak_native_fnc_tocLaptop; }] call _btn;
        _y2 = _y2 + _rh + _H * 0.01;
        ["COMSPEC_RscStructuredText", [_cx, _y2, _cw, ((_cy + _ch) - _y2) max (_H * 0.03)], "pInfo"] call _mk;
        // MAIN COURANTE : entrées du poste (automatiques et à la main), heure ZULU.
        private _ll = ["COMSPEC_RscListBox", [_cx, _cy, _cw, _ch * 0.72], "lList"] call _mk;
        _ll ctrlAddEventHandler ["LBSelChanged", { params ["_c", "_i"]; if (_i >= 0) then { (uiNamespace getVariable ["COMSPEC_ATAK_TocLap", createHashMap]) set ["selL", _c lbData _i]; }; }];
        _y2 = _cy + _ch * 0.72 + _H * 0.01;
        private _le = ["COMSPEC_RscEdit", [_cx, _y2, _cw - _bw3 - _m, _rh], "lText"] call _mk;
        _le ctrlSetTooltip (("Entrée à la main, inscrite à l'heure ZULU avec votre nom. Exemple : BRAVO signale le pont nord détruit") call comspec_atak_native_fnc_tr);
        ["lAdd", [_cx + _cw - _bw3, _y2, _bw3, _rh], "INSCRIRE", { ["act", "lAdd"] call comspec_atak_native_fnc_tocLaptop; }, true] call _btn;
        _y2 = _y2 + _rh + _H * 0.008;
        ["lShow", [_cx, _y2, _cw, _rh], "MAIN COURANTE SUR LES ÉCRANS", { ["act", "lShow"] call comspec_atak_native_fnc_tocLaptop; }, false, "Écrans TOC à moins de 30 m du poste"] call _btn;
        _y2 = _y2 + _rh + _H * 0.008;
        ["COMSPEC_RscStructuredText", [_cx, _y2, _cw, ((_cy + _ch) - _y2) max (_H * 0.03)], "lInfo"] call _mk;
        // EXCHECK : liste d'exécution (n°, événement, mot de code, élément, heure prévue, heure réelle).
        private _xl = ["COMSPEC_RscListBox", [_cx, _cy, _cw, _ch * 0.4], "xList"] call _mk;
        _xl ctrlAddEventHandler ["LBSelChanged", { params ["_c", "_i"]; if (_i >= 0) then { (uiNamespace getVariable ["COMSPEC_ATAK_TocLap", createHashMap]) set ["selX", _c lbData _i]; }; }];
        _y2 = _cy + _ch * 0.4 + _H * 0.008;
        { _x params ["_k", "_t", "_tip"]; [_k, [_cx + _forEachIndex * (_bw4 + _m), _y2, _bw4, _rh], _t, compile format ["['act', %1] call comspec_atak_native_fnc_tocLaptop;", str _k], _forEachIndex isEqualTo 0, _tip] call _btn; } forEach [
            ["xDone", "PASSÉ", "Le mot de code de la ligne choisie est passé : heure ZULU inscrite, entrée à la main courante"], ["xUndo", "ANNULER", "Retire l'heure réelle de la ligne choisie"],
            ["xDel", "SUPPRIMER", "Retire la ligne choisie"], ["xShow", "ÉCRANS", "Liste d'exécution sur les écrans TOC à moins de 30 m"]];
        _y2 = _y2 + _rh + _H * 0.014;
        [[_cx, _y2, _cw, _H * 0.022], "NOUVELLE LIGNE", "xL1"] call _lbl;
        _y2 = _y2 + _H * 0.024;
        private _xe = ["COMSPEC_RscEdit", [_cx, _y2, _cw, _rh], "xEv"] call _mk;
        _xe ctrlSetTooltip (("Événement. Exemple : Franchissement de la ligne de départ") call comspec_atak_native_fnc_tr);
        _y2 = _y2 + _rh + _H * 0.006;
        private _bw3b = (_cw - 2 * _m) / 3;
        {
            _x params ["_k", "_tip"];
            (["COMSPEC_RscEdit", [_cx + _forEachIndex * (_bw3b + _m), _y2, _bw3b, _rh], _k] call _mk) ctrlSetTooltip ((_tip) call comspec_atak_native_fnc_tr);
        } forEach [["xCw", "Mot de code. Exemple : ROMEO"], ["xEl", "Élément. Exemple : ALPHA"], ["xAt", "Heure prévue (ZULU ou H+). Exemple : 22:30"]];
        _y2 = _y2 + _rh + _H * 0.006;
        ["xAdd", [_cx, _y2, _cw, _rh], "AJOUTER LA LIGNE", { ["act", "xAdd"] call comspec_atak_native_fnc_tocLaptop; }, true] call _btn;
        _y2 = _y2 + _rh + _H * 0.008;
        ["COMSPEC_RscStructuredText", [_cx, _y2, _cw, ((_cy + _ch) - _y2) max (_H * 0.03)], "xInfo"] call _mk;
        // PERTES : tableau par élément (connu du réseau ou déclaré par compte rendu), correction à la main.
        ["COMSPEC_RscStructuredText", [_cx, _cy, _cw, _ch * 0.6], "kcInfo"] call _mk;
        _y2 = _cy + _ch * 0.6 + _H * 0.01;
        [[_cx, _y2, _cw, _H * 0.022], "COMPTE RENDU À LA MAIN", "kcL1"] call _lbl;
        _y2 = _y2 + _H * 0.024;
        ["COMSPEC_RscCombo", [_cx, _y2, _bw2, _rh], "kcEl"] call _mk;
        private _kk = ["COMSPEC_RscCombo", [_cx + _bw2 + _m, _y2, _bw2, _rh], "kcKind"] call _mk;
        { private _i = _kk lbAdd ((_x select 0) call comspec_atak_native_fnc_tr); _kk lbSetData [_i, _x select 1]; } forEach [["Ami blessé", "wia"], ["Ami tué", "kia"], ["Ennemi neutralisé", "ekia"], ["Ennemi blessé ou capturé", "ewia"]];
        _kk lbSetCurSel 0;
        _y2 = _y2 + _rh + _H * 0.006;
        ["kcPlus", [_cx, _y2, _bw2, _rh], "+1", { ["act", "kcPlus"] call comspec_atak_native_fnc_tocLaptop; }, true, "Ajoute un au compte de l'élément choisi"] call _btn;
        ["kcMinus", [_cx + _bw2 + _m, _y2, _bw2, _rh], "−1 (CORRECTION)", { ["act", "kcMinus"] call comspec_atak_native_fnc_tocLaptop; }, false, "Retire un au compte de l'élément choisi"] call _btn;
        _y2 = _y2 + _rh + _H * 0.008;
        ["kcShow", [_cx, _y2, _cw, _rh], "PERTES ET RÉSULTATS SUR LES ÉCRANS", { ["act", "kcShow"] call comspec_atak_native_fnc_tocLaptop; }, false, "Écrans TOC à moins de 30 m du poste"] call _btn;
        // DEMANDES : appuis, MEDEVAC, ravitaillements reçus des éléments, statut et réponse renvoyée au téléphone.
        private _ql = ["COMSPEC_RscListBox", [_cx, _cy, _cw, _ch * 0.46], "qList"] call _mk;
        _ql ctrlAddEventHandler ["LBSelChanged", { params ["_c", "_i"]; if (_i >= 0) then { (uiNamespace getVariable ["COMSPEC_ATAK_TocLap", createHashMap]) set ["selQ", _c lbData _i]; }; }];
        _y2 = _cy + _ch * 0.46 + _H * 0.008;
        ["COMSPEC_RscStructuredText", [_cx, _y2, _cw, _ch * 0.2], "qInfo"] call _mk;
        _y2 = _y2 + _ch * 0.2 + _H * 0.006;
        private _qa = ["COMSPEC_RscEdit", [_cx, _y2, _cw, _rh], "qAns"] call _mk;
        _qa ctrlSetTooltip (("Réponse envoyée au téléphone du demandeur avec le statut. Exemple : hélico décolle, ETA 8 min, fumée verte") call comspec_atak_native_fnc_tr);
        _y2 = _y2 + _rh + _H * 0.006;
        private _bw3c = (_cw - 2 * _m) / 3;
        { _x params ["_k", "_t"]; [_k, [_cx + _forEachIndex * (_bw3c + _m), _y2, _bw3c, _rh], _t, compile format ["['act', %1] call comspec_atak_native_fnc_tocLaptop;", str _k], _forEachIndex isEqualTo 0, "Le statut et la réponse partent au téléphone du demandeur"] call _btn; } forEach [
            ["qPRISE", "PRISE EN COMPTE"], ["qREFUSEE", "REFUSÉE"], ["qTERMINEE", "TERMINÉE"]];
        _y2 = _y2 + _rh + _H * 0.008;
        ["qShow", [_cx, _y2, _cw, _rh], "DEMANDES SUR LES ÉCRANS", { ["act", "qShow"] call comspec_atak_native_fnc_tocLaptop; }, false, "Écrans TOC à moins de 30 m du poste"] call _btn;
        // Choix des listes déroulantes gardés tout de suite dans le brouillon (listes reconstruites quand le camp change).
        {
            _x params ["_k", "_dk"];
            (_ui get _k) ctrlAddEventHandler ["LBSelChanged", compile format ["params ['_c', '_i']; if (_i >= 0) then { (uiNamespace getVariable ['COMSPEC_ATAK_TocLapDraft', createHashMap]) set [%1, _c lbData _i]; };", str _dk]];
        } forEach [["oKind", "kind"], ["oPrio", "prio"], ["oTarget", "target"], ["cDest", "dest"], ["cPrio", "mprio"], ["gAi", "aikind"]];
        // Ligne d'état.
        private _bar = ["COMSPEC_RscStructuredText", [_xi, _yi + _hi - _sb, _wi, _sb], "bar"] call _mk;
        _bar ctrlSetBackgroundColor [0.06, 0.08, 0.07, 1];
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
        ["tab", _st get "tab"] call _fn;
        ["refresh"] call _fn;
        ["frame", ["net", "post"] select ((count ((call _feedOf) param [1, []])) isEqualTo 0)] call _fn;
        private _h = [{ ["refresh"] call comspec_atak_native_fnc_tocLaptop; }, 1] call CBA_fnc_addPerFrameHandler;
        _st set ["pfh", _h];
        ["INFO", "TOC", format ["PC du poste %1 ouvert", ["callsign"] call comspec_atak_native_fnc_tocPost]] call comspec_atak_native_fnc_log;
    };
    case "unload": {
        ["keep"] call _fn;
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
        _st set ["tab", _t];
        private _sets = createHashMapFromArray [
            ["GROUPES", ["gList", "gInfo", "gOrder", "gMsg", "gCenter", "gAi", "gAiGo"]],
            ["ORBAT", ["bList", "bInfo", "bMsg", "bCenter"]],
            ["RÉSEAU", ["rInfo", "rL1", "rShow", "rGo"]],
            ["FLUX", ["fFilter", "fList", "fInfo", "fCenter"]],
            ["ORDRES", ["oL1", "oL2", "oL3", "oL4", "oL5", "oKind", "oPrio", "oTarget", "oText", "oGrid", "oClear", "oSend", "oInfo"]],
            ["CAMP", ["cL1", "cL2", "cL3", "cDest", "cPrio", "cText", "cSend", "cCs", "cSave", "cL4", "cCam", "cCamGo", "cInfo"]],
            ["SAT", ["sSlot0", "sSlot1", "sInfo", "sTake", "sRelease", "sDAY", "sNV", "sTI", "sHome", "sN", "sS", "sW", "sE", "sIn", "sOut", "sL1", "sFollow", "sFollowGo", "sFollowOff", "sShow", "sHelp"]],
            ["CHRONOS", ["tList", "tStart", "tStop", "tReset", "tDel", "tL1", "tName", "tKind", "tVal", "tAdd", "tShow", "tInfo"]],
            ["RADIO", ["kInfo"]],
            ["DIFFUSER", ["pList", "pL1", "pWhat", "pGo", "pL2", "pName", "pRen", "pInfo"]],
            ["MAIN COURANTE", ["lList", "lText", "lAdd", "lShow", "lInfo"]],
            ["EXCHECK", ["xList", "xDone", "xUndo", "xDel", "xShow", "xL1", "xEv", "xCw", "xEl", "xAt", "xAdd", "xInfo"]],
            ["PERTES", ["kcInfo", "kcL1", "kcEl", "kcKind", "kcPlus", "kcMinus", "kcShow"]],
            ["DEMANDES", ["qList", "qInfo", "qAns", "qPRISE", "qREFUSEE", "qTERMINEE", "qShow"]]
        ];
        { private _on = _x isEqualTo _t; { (_ui getOrDefault [_x, controlNull]) ctrlShow _on; } forEach _y; } forEach _sets;
        { (_ui get ("tab" + _x)) ctrlSetBackgroundColor ([[0.07, 0.085, 0.075, 1], [0.18, 0.38, 0.21, 1]] select (_x isEqualTo _t)); } forEach _allTabs;
        // SAT : l'image satellite remplace la carte et ses outils ; cadres de suivi à chaque image.
        private _sat = _t isEqualTo "SAT";
        { (_ui getOrDefault [_x, controlNull]) ctrlShow !_sat; } forEach ["map", "framenet", "frameme", "framesel", "toolMEASURE", "toolMARKER", "layerai", "layerev", "search", "searchGo", "hint"];
        { (_ui getOrDefault [_x, controlNull]) ctrlShow _sat; } forEach ["sBack", "sPic", "sStrip"];
        if (!_sat) then { (_ui getOrDefault ["sWait", controlNull]) ctrlShow false; { { _x ctrlShow false; } forEach _x; } forEach (_st getOrDefault ["satBoxes", []]); };
        if (_sat && {(_st getOrDefault ["satPfh", -1]) < 0}) then {
            _st set ["satPfh", [{ ["satOverlay"] call comspec_atak_native_fnc_tocLaptop; }, 0] call CBA_fnc_addPerFrameHandler];
        };
        if (!_sat && {(_st getOrDefault ["satPfh", -1]) >= 0}) then { [_st get "satPfh"] call CBA_fnc_removePerFrameHandler; _st set ["satPfh", -1]; };
        _st set ["sig", ""];
        ["refresh"] call _fn;
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
                if (_u < 0 || {_u > 1} || {_v < 0.05} || {_v > 1}) then { continue };
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
        if !(_t in ["SELECT", "MEASURE", "MARKER"]) then { _t = "SELECT"; };
        _st set ["tool", _t];
        _st set ["toolSet", true];
        _st set ["measure", []];
        { (_ui getOrDefault ["tool" + _x, controlNull]) ctrlSetBackgroundColor ([[0.07, 0.085, 0.075, 1], [0.18, 0.38, 0.21, 1]] select (_x isEqualTo _t)); } forEach ["MEASURE", "MARKER"];
        (_ui getOrDefault ["hint", controlNull]) ctrlSetText ((switch (_t) do {
            case "MEASURE": { "MESURE : clic gauche sur A, puis sur B · clic droit : objectif de l'ordre" };
            case "MARKER": { "MARQUEUR : clic gauche pour poser · même marqueur que le téléphone" };
            default { "Molette : zoom · clic gauche : fiche d'une unité · clic gauche maintenu : déplacer · clic droit : objectif de l'ordre" };
        }) call comspec_atak_native_fnc_tr);
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
            case "MARKER": {
                [[_a select 0, _a select 1, 0]] call comspec_atak_native_fnc_markerDrop;
                ["status", format ["Marqueur posé : grille %1", [[_a select 0, _a select 1, 0], 8] call comspec_atak_native_fnc_gridRef]] call _fn;
                ["tool", "SELECT"] call _fn;
            };
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
            case "sDAY"; case "sNV"; case "sTI": { ["send", ["sat", [_st getOrDefault ["satN", 0], "mode", _a select [1]]]] call comspec_atak_native_fnc_tocRoom; };
            case "sHome": {
                private _o = _st getOrDefault ["obj", player];
                ["send", ["sat", [_st getOrDefault ["satN", 0], "center", [(getPosASL _o) select 0, (getPosASL _o) select 1]]]] call comspec_atak_native_fnc_tocRoom;
            };
            case "sN"; case "sS"; case "sW"; case "sE": {
                private _d = (createHashMapFromArray [["sN", [0, 0.33]], ["sS", [0, -0.33]], ["sW", [-0.33, 0]], ["sE", [0.33, 0]]]) get _a;
                ["send", ["sat", [_st getOrDefault ["satN", 0], "pan", _d]]] call comspec_atak_native_fnc_tocRoom;
            };
            case "sIn"; case "sOut": { ["send", ["sat", [_st getOrDefault ["satN", 0], "zoom", [1.5, 1 / 1.5] select (_a isEqualTo "sIn")]]] call comspec_atak_native_fnc_tocRoom; };
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
            // Diffuser : un contenu sur l'écran choisi du mur.
            case "pGo": {
                private _o = objectFromNetId (_st getOrDefault ["selP", ""]);
                if (isNull _o) exitWith { ["Choisissez d'abord un écran dans la liste"] call _setStatus; };
                private _pw = _ui get "pWhat";
                private _k = _pw lbData (lbCurSel _pw);
                switch (true) do {
                    case (_k in ["SAT1", "SAT2"]): { [_o, toLower _k] call COMSPEC_ATAK_TocDo; };
                    case ((_k select [0, 3]) isEqualTo "SQ|"): { [_o, "sq|" + (_k select [3])] call COMSPEC_ATAK_TocDo; };
                    case ((_k select [0, 4]) isEqualTo "CAM|"): { ["set", [_o, _k select [4]]] call comspec_atak_native_fnc_tocCam; };
                    case (_k isNotEqualTo ""): { ["show", [_o, _k]] call comspec_atak_native_fnc_tocScreen; };
                };
                [format ["%1 : %2", (_o getVariable ["COMSPEC_ATAK_TocScr", []]) param [0, "écran"], _pw lbText (lbCurSel _pw)]] call _setStatus;
            };
            case "pRen": {
                private _o = objectFromNetId (_st getOrDefault ["selP", ""]);
                private _n = trim (ctrlText (_ui get "pName"));
                if (isNull _o || {_n isEqualTo ""}) exitWith { ["Choisissez un écran et écrivez son nom"] call _setStatus; };
                private _c = _o getVariable ["COMSPEC_ATAK_TocScr", []];
                if ((count _c) < 8) exitWith {};
                ["add", [_o, _n select [0, 32], _c select 1, _c select 2, _c select 3, _c select 6, _c select 7]] call comspec_atak_native_fnc_tocScreen;
                [format ["Écran renommé : %1", _n]] call _setStatus;
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
        (_ui get "title") ctrlSetStructuredText parseText ((format ["<t size='1.4' font='COMSPEC_BarlowBold'>POSTE TOC · %1</t>  <t size='1.05' color='%4'>%2 · %3</t>", [_cs] call _esc, _sideName,
            [format ["opérateur %1", [name player] call _esc], format ["poste pris par %1 à %2Z", [name player] call _esc, (_post param [0, []]) param [3, ""]]] select ((count _post) > 0), ["#8a9a93", "#5cc76b"] select ((count _post) > 0)]) call comspec_atak_native_fnc_tr);
        (_ui get "postGo") ctrlSetText ((["PRENDRE LE POSTE", "LIBÉRER LE POSTE"] select ((count _post) > 0)) call comspec_atak_native_fnc_tr);
        private _live = [["<t color='#e05a4a'>● HORS LIGNE</t>", "<t color='#e0b040'>● RETARD</t>"] select (_age >= 0), "<t color='#5cc76b'>● EN DIRECT</t>"] select (_age >= 0 && {_age < 12});
        // Liaison radio du poste : ACRE2 ou TFAR chargé, radios de l'opérateur (lecture seule, fn_mpu5).
        private _radio = switch (true) do {
            case (!isNil "acre_api_fnc_getCurrentRadioList"): {
                private _nr = count (["radios"] call comspec_atak_native_fnc_mpu5);
                format ["<t color='%1'>● ACRE2</t>", ["#e0b040", "#5cc76b"] select (_nr > 0)]
            };
            case (!isNil "TFAR_fnc_activeSwRadio"): { "<t color='#5cc76b'>● TFAR</t>" };
            default { "<t color='#6f7773'>● SANS RADIO</t>" };
        };
        (_ui get "clock") ctrlSetStructuredText parseText ((format ["<t align='right' size='0.95'><t font='COMSPEC_BarlowBold'>%1</t> <t color='#8a9a93'>ZULU</t>  <t font='COMSPEC_BarlowBold'>%2</t> <t color='#8a9a93'>LOCAL</t>  %3  %4</t>",
            ["zulu"] call comspec_atak_native_fnc_tocRoom, [dayTime, "HH:MM:SS"] call BIS_fnc_timeToString, _radio, _live]) call comspec_atak_native_fnc_tr);
        private _cnt = [0, 0, 0, 0];
        { private _f = (_x select 4) min 3; _cnt set [_f, (_cnt select _f) + (_x param [8, 1])]; } forEach _nodes;
        _stats params [["_jam", 0], ["_relays", 0], ["_players", 0], ["_ais", 0]];
        private _msg = (_st getOrDefault ["status", ["", 0]]);
        private _pipN = count ((_st getOrDefault ["pip", []]) select { ((((_x select 0) getVariable ["COMSPEC_ATAK_TocScr", []]) param [3, ""]) isEqualTo "TOC") });
        (_ui get "bar") ctrlSetStructuredText parseText ((format ["<t size='1.05'>  RÉSEAU  <t color='#5cc76b'>%1 en ligne</t> · <t color='#e0b040'>%2 anciens</t> · <t color='#9aa39f'>%3 perdus</t> · <t color='#6f7773'>%4 hors ligne</t>   <t color='#8a9a93'>%5 joueurs · %6 IA · %7 relais · %8 brouilleurs</t>   %10%9</t>",
            _cnt select 0, _cnt select 1, _cnt select 2, _cnt select 3, _players, _ais, _relays, _jam,
            ["", format ["   <t color='#7fd0b0'>▶ %1</t>", [_msg select 0] call _esc]] select (diag_tickTime < (_msg select 1)),
            [format ["<t color='%2'>%1 alerte(s) sur 20 min</t>", {(_x param [2, ""]) in ["FLASH", "URGENT"] && {serverTime - (_x param [7, -1e9]) < 1200}} count _events, ["#6f7773", "#e05a4a"] select (({(_x param [2, ""]) in ["FLASH", "URGENT"] && {serverTime - (_x param [7, -1e9]) < 1200}} count _events) > 0)], format ["<t color='#e0b040'>PIP : %1 écran(s)</t>", _pipN]] select (_pipN > 0)]) call comspec_atak_native_fnc_tr);
        // FLASH du poste (fn_tocRoom) : la ligne d'état passe en rouge pendant 3 minutes.
        private _fl = ["flash", _side] call comspec_atak_native_fnc_tocBoard;
        (_ui get "bar") ctrlSetBackgroundColor ([[0.06, 0.08, 0.07, 1], [0.55, 0.07, 0.05, 1]] select ((count _fl) > 0));
        if ((count _fl) > 0) then {
            (_ui get "bar") ctrlSetStructuredText parseText ((format ["<t size='1.1'>  <t font='COMSPEC_BarlowBold'>FLASH</t> · %1 <t color='#ffd0c8'>· %2 · %3Z</t></t>", [_fl select 0] call _esc, [_fl select 1] call _esc, _fl select 2]) call comspec_atak_native_fnc_tr);
        };
        private _groupsL = [_nodes] call _groups;
        private _nOrd = count (values ((uiNamespace getVariable ["COMSPEC_ATAK_Data", createHashMap]) getOrDefault ["localTasks", createHashMap]) select { (_x getOrDefault ["issuerUid", ""]) isEqualTo getPlayerUID player });
        {
            _x params ["_k", "_n"];
            (_ui get ("tab" + _k)) ctrlSetText (format ["%1 %2", _k call comspec_atak_native_fnc_tr, ["", format ["(%1)", _n]] select (_n > 0)]); // i18n:brut (déjà traduit)
        } forEach [["GROUPES", count _groupsL], ["ORBAT", ((["store"] call comspec_atak_native_fnc_bftOrbat) get "total")], ["FLUX", count _events], ["ORDRES", _nOrd], ["CAMP", 0], ["RÉSEAU", 0], ["DEMANDES", { (_x select 7) isEqualTo "RECUE" } count (_room select 5)], ["CHRONOS", count (_room select 1)]];
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
                    format ["<t size='1.1' color='#8a9a93'>%1 groupe(s) dans le camp. Choisissez un groupe : la carte se centre sur lui, puis ORDRE AU GROUPE ou MESSAGE AU CHEF.</t>", count _groupsL]
                } else {
                    (_g select 0) params ["_gn", "", "_ld", "_np", "_nAll", "_n", "_best", "_p"];
                    private _ao = (missionNamespace getVariable ["COMSPEC_ATAK_AiOrds", createHashMap]) getOrDefault [["netId", group _ld] call comspec_atak_native_fnc_aiOrder, createHashMap];
                    format ["<t size='1.35' font='COMSPEC_BarlowBold'>%1</t><br/><t size='1.1'>Chef %2 %3<br/>%4 vivant(s) dont %5 joueur(s) · %6 nœud(s) ATAK<br/>%7%8</t>",
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
                    "<t size='1.1' color='#8a9a93'>Comptes rendus, alertes, MEDEVAC, BDA, ordres et messages du camp (30 derniers, gardés d'une partie à l'autre).</t>"
                } else {
                    (_events select _i) params [["_time", ""], ["_kind", ""], ["_prio", ""], ["_who", ""], ["_grid", ""], ["_text", ""], ["_p", []], "", ["_old", false]];
                    format ["<t size='1.3' font='COMSPEC_BarlowBold'>%1</t> <t size='1.1' color='#8a9a93'>%2 · %3%4%5</t><br/><t size='1.1'>%6</t>",
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
                    format ["<t size='1' color='#8a9a93'>%1</t>", [_nTxt] call _esc]
                } else {
                    private _o = _onKey getOrDefault [_st getOrDefault ["selB", ""], objNull];
                    format ["<t size='1.25' font='COMSPEC_BarlowBold'>%1</t><br/><t size='1'>%2</t><br/><t size='1' color='%3'>%4</t>", [_pe select 0] call _esc,
                        (((_pe select 5) select [0, 3]) apply { [(_x select 0) + (["", " · " + (_x select 1)] select ((_x select 1) isNotEqualTo ""))] call _esc }) joinString "<br/>",
                        ["#8a9a93", "#5cc76b"] select (!isNull _o), ["Hors ligne ou état non communiqué", "En jeu"] select (!isNull _o)]
                }) call comspec_atak_native_fnc_tr);
            };
            case "RÉSEAU": {
                private _w = windDir;
                (_ui get "rInfo") ctrlSetStructuredText parseText ((format ["<t size='1.25' font='COMSPEC_BarlowBold'>RÉSEAU</t><br/><t size='1.05'><t color='#5cc76b'>%1 en ligne</t> · <t color='#e0b040'>%2 anciens</t> · <t color='#9aa39f'>%3 perdus</t> · <t color='#6f7773'>%4 hors ligne</t><br/>%5 joueurs · %6 IA<br/>%7 relais · %8 brouilleurs actifs</t><br/><br/><t size='1.25' font='COMSPEC_BarlowBold'>MÉTÉO</t><br/><t size='1.05'>Nuages %9 %% · pluie %10 %% · brouillard %11 %%<br/>Vent %12 m/s du %13°</t><br/><br/><t size='1' color='#8a9a93'>Carte de couverture, brouilleurs en détail et météo prévue : apps Réseau et Météo du téléphone.</t>",
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
                private _s = format ["<t size='1.2' font='COMSPEC_BarlowBold'>ORDRES ÉMIS</t> <t size='1' color='#8a9a93'>%1</t><br/>", count _mine];
                if ((count _mine) isEqualTo 0) then { _s = _s + "<t size='1' color='#8a9a93'>Aucun ordre émis depuis ce poste</t>"; };
                {
                    private _acks = ["list", [_x getOrDefault ["id", ""]]] call comspec_atak_native_fnc_orderAck;
                    private _n = createHashMap;
                    { private _k = _y select 1; _n set [_k, (_n getOrDefault [_k, 0]) + 1]; } forEach _acks;
                    _s = _s + format ["<t size='1'><t color='#7fd0b0'>%1</t> %2 → %3 · %4<br/><t color='#8a9a93'>   vu %5 · reçu %6 · en cours %7 · terminé %8</t></t><br/>",
                        _x getOrDefault ["time", ""], [_x getOrDefault ["typeLabel", _x getOrDefault ["type", ""]]] call _esc, [["tout le camp", _x getOrDefault ["target", ""]] select ((_x getOrDefault ["target", ""]) isNotEqualTo "")] call _esc,
                        _x getOrDefault ["priority", ""], _n getOrDefault ["SEEN", 0], _n getOrDefault ["ACK", 0], _n getOrDefault ["EXEC", 0], _n getOrDefault ["DONE", 0]];
                } forEach ((_mine select [0 max ((count _mine) - 5)]) apply { _x });
                // Missions de tir du camp (appui feu typé, fn_fireSupport) : texte déjà traduit, ajouté après.
                (_ui get "oInfo") ctrlSetStructuredText parseText (((((_s) call comspec_atak_native_fnc_tr) + (["tocText"] call comspec_atak_native_fnc_fireSupport))) call comspec_atak_native_fnc_tr);
            };
            case "SAT": {
                private _n = _st getOrDefault ["satN", 0];
                // Console de l'opérateur : ce satellite est rendu sur ce poste tant que l'onglet est ouvert.
                ["need", [_side, _n]] call comspec_atak_native_fnc_tocSat;
                { (_ui get ("sSlot" + str _forEachIndex)) ctrlSetBackgroundColor ([[0.07, 0.085, 0.075, 1], [0.18, 0.38, 0.21, 1]] select (_forEachIndex isEqualTo _n)); } forEach [0, 1];
                private _s = (_room select 2) param [_n, []];
                private _mine = (_s param [7, ""]) isEqualTo getPlayerUID player;
                private _who = _s param [6, ""];
                private _tex = ["tex", [_side, _n]] call comspec_atak_native_fnc_tocSat;
                private _pic = _ui get "sPic";
                if ((_pic getVariable ["COMSPEC_ATAK_Tex", ""]) isNotEqualTo _tex) then {
                    _pic setVariable ["COMSPEC_ATAK_Tex", _tex];
                    _pic ctrlSetText ([_tex, "#(argb,8,8,3)color(0,0,0,1)"] select (_tex isEqualTo "")); // i18n:brut (texture)
                };
                (_ui get "sWait") ctrlShow (_tex isEqualTo "");
                if (_tex isEqualTo "") then { (_ui get "sWait") ctrlSetStructuredText parseText (("<t align='center' size='1.3' color='#cfd8d3'>Image satellite en cours d'acquisition…</t><br/><t align='center' size='1' color='#8a9a93'>Image noire : activez « Image dans l'image » (Options > Vidéo)</t>") call comspec_atak_native_fnc_tr); };
                private _lab = ["label", [_side, _n]] call comspec_atak_native_fnc_tocSat;
                (_ui get "sStrip") ctrlSetStructuredText parseText ((format ["<t size='1.05'>  %1 · %2</t>", [["<t color='#e0b040'>Lecture seule</t>", "<t color='#5cc76b'>Vous avez la main</t>"] select _mine, "<t color='#7fd0b0'>Libre</t>"] select (_who isEqualTo ""), [_lab] call _esc]) call comspec_atak_native_fnc_tr);
                private _aim = ["aim", [_side, _n]] call comspec_atak_native_fnc_tocSat;
                private _where = if ((count _aim) < 2) then { "au-dessus du poste" } else { "grille " + ([[(_aim select 0) select 0, (_aim select 0) select 1, 0], 6] call comspec_atak_native_fnc_gridRef) };
                private _hw = _s param [2, 600];
                (_ui get "sInfo") ctrlSetStructuredText parseText ((format ["<t size='1.25' font='COMSPEC_BarlowBold'>SAT %1</t><br/><t size='1.05'>%2<br/>Largeur de la vue %3 · %4</t>", _n + 1,
                    [[format ["Piloté par %1 : lecture seule", [_who] call _esc], "Vous avez la main"] select _mine, "Libre : prenez la main pour le piloter"] select (_who isEqualTo ""),
                    [format ["%1 m", round (2 * _hw)], format ["%1 km", (round (2 * _hw / 100)) / 10]] select (_hw >= 500), _where]) call comspec_atak_native_fnc_tr);
                { (_ui get _x) ctrlEnable (_mine || {_who isEqualTo ""}); } forEach ["sDAY", "sNV", "sTI", "sHome", "sN", "sS", "sW", "sE", "sIn", "sOut", "sFollowGo", "sFollowOff"];
                (_ui get "sTake") ctrlEnable (!_mine);
                (_ui get "sRelease") ctrlEnable _mine;
                // Liste de suivi : groupes puis unités du réseau du camp (jamais une unité sans ATAK).
                private _sf = _ui get "sFollow";
                private _opts = [];
                { _opts pushBackUnique [format ["Groupe %1", _x param [7, ""]], "G|" + (_x param [7, ""])]; } forEach (_nodes select { (_x param [7, ""]) isNotEqualTo "" });
                { _opts pushBack [format ["%1 · %2", _x select 1, _x param [7, ""]], "U|" + (_x select 0)]; } forEach _nodes;
                if ((_sf getVariable ["COMSPEC_ATAK_Sig", []]) isNotEqualTo _opts) then {
                    _sf setVariable ["COMSPEC_ATAK_Sig", _opts];
                    private _keep = _sf lbData ((lbCurSel _sf) max 0);
                    lbClear _sf;
                    { private _i = _sf lbAdd ((_x select 0) call comspec_atak_native_fnc_tr); _sf lbSetData [_i, _x select 1]; } forEach _opts;
                    _sf lbSetCurSel ((_opts findIf { (_x select 1) isEqualTo _keep }) max 0);
                };
                (_ui get "sHelp") ctrlSetStructuredText parseText (("<t size='0.95' color='#8a9a93'>Clic sur l'image : centre la vue sur ce point. Cadres : alliés du réseau ATAK (en couleur : en direct ; en gris : point ancien, perdu ou téléphone hors ligne). Une unité sans ATAK n'apparaît jamais.</t>") call comspec_atak_native_fnc_tr);
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
                (_ui get "tInfo") ctrlSetStructuredText parseText (("<t size='0.95' color='#8a9a93'>Chrono : temps écoulé depuis le lancement. Compte à rebours : temps restant, rouge une fois dépassé. Heure H : temps jusqu'à une heure ZULU (H-), puis depuis (H+), toujours en marche. Les chronos sont communs à tous les PC du camp et gardés d'une partie à l'autre.</t>") call comspec_atak_native_fnc_tr);
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
                        _s = _s + format ["<t size='1.25' font='COMSPEC_BarlowBold'>%1</t> <t size='1' color='#8a9a93'>canal %2%3</t><br/><t size='1.05'><t color='%4'>● RX</t>   <t color='%5'>● TX</t>   %6 MHz%7 · %8%9</t><br/><br/>",
                            [_name] call _esc, _ch, ["", " · " + ([_lbl] call _esc)] select (_lbl isNotEqualTo ""), ["#3a423e", "#5cc76b"] select _rxOn, ["#3a423e", "#ff5a4a"] select _txing,
                            [_tx toFixed 3, "?"] select (_tx <= 0), ["", format [" (réception %1)", _rx toFixed 3]] select (_rx > 0 && {abs (_rx - _tx) > 0.0005}),
                            [format ["alternat %1", _ptt], "sans alternat"] select (_ptt < 1), ["", " · radio active"] select _cur];
                    } forEach _rad;
                    private _who = _now select { (_x select 0) isNotEqualTo player };
                    if ((count _who) > 0) then {
                        _s = _s + "<t size='1.15' font='COMSPEC_BarlowBold'>EN ÉMISSION</t><br/>";
                        { _s = _s + format ["<t size='1.05'><t color='#5cc76b'>●</t> %1 · %2 MHz%3 · %4 s</t><br/>", [[_x select 0, true] call comspec_atak_native_fnc_unitCallsign] call _esc, (_x select 2) toFixed 3, ["", " · " + ([_x select 3] call _esc)] select ((_x select 3) isNotEqualTo ""), round (_x select 4)]; } forEach _who;
                    };
                } else {
                    _s = switch (true) do {
                        case (!isNil "acre_api_fnc_getCurrentRadioList"): { "<t size='1.1' color='#e0b040'>ACRE2 est chargé, mais vous ne portez aucune radio. Prenez une radio ACRE2 pour voir ses canaux ici.</t>" };
                        case (!isNil "TFAR_fnc_activeSwRadio"): {
                            private _sw = ["api", ["TFAR_fnc_activeSwRadio", [], ""]] call comspec_atak_native_fnc_radioGuard;
                            private _lr = ["api", ["TFAR_fnc_activeLrRadio", [], []]] call comspec_atak_native_fnc_radioGuard;
                            format ["<t size='1.25' font='COMSPEC_BarlowBold'>TFAR</t><br/><t size='1.05'>Courte portée : %1<br/>Longue portée : %2</t>",
                                [[getText (configFile >> "CfgWeapons" >> _sw >> "displayName")] call _esc, "aucune"] select (!(_sw isEqualType "") || {_sw isEqualTo ""}),
                                [[getText (configOf (_lr select 0) >> "displayName")] call _esc, "aucune"] select (!(_lr isEqualType []) || {(count _lr) < 1} || {!((_lr select 0) isEqualType objNull)})]
                        };
                        default { "<t size='1.1' color='#8a9a93'>Aucun mod radio (ACRE2 ou TFAR) sur ce serveur.</t>" };
                    };
                };
                (_ui get "kInfo") ctrlSetStructuredText parseText ((_s + "<br/><t size='0.95' color='#8a9a93'>Voyant RX : un équipier émet sur cette fréquence. Voyant TX : vous émettez. Pour parler, utilisez votre touche d'alternat ACRE2 ou TFAR : le PC ne presse pas l'alternat à votre place.</t>") call comspec_atak_native_fnc_tr);
            };
            case "DIFFUSER": {
                private _all = call _sideScreens;
                private _lb = _ui get "pList";
                private _rows = _all apply {
                    private _c = _x getVariable ["COMSPEC_ATAK_TocScr", []];
                    private _v = _c param [3, "SPLIT"];
                    private _what = (createHashMapFromArray [["SPLIT", "carte et flux"], ["MAP", "carte seule"], ["FEED", "flux seul"], ["NET", "réseau"], ["ORBAT", "ORBAT du site"], ["TOC", "PIP du poste"],
                        ["CAM", "caméra : " + (["label", _x] call comspec_atak_native_fnc_tocCam)], ["SAT", format ["vue satellite SAT %1", (_c param [8, 0]) + 1]], ["CLOCK", "horloge et chronos"],
                        ["SQUAD", "escouade " + (_c param [9, ""])], ["LOG", "main courante"], ["EXCHECK", "liste d'exécution"], ["KILLS", "pertes et résultats"], ["REQ", "demandes"]]) getOrDefault [_v, _v];
                    if ((count (_x getVariable ["COMSPEC_ATAK_ScreenCast", []])) >= 5) then { _what = "diapositives"; };
                    [netId _x, format ["%1 · %2", _c param [0, "TOC"], _what], format ["%1 m", round (player distance _x)]]
                };
                if ((_lb getVariable ["COMSPEC_ATAK_Sig", []]) isNotEqualTo _rows) then {
                    _lb setVariable ["COMSPEC_ATAK_Sig", _rows];
                    lbClear _lb;
                    { private _i = _lb lbAdd ((_x select 1) call comspec_atak_native_fnc_tr); _lb lbSetData [_i, _x select 0]; _lb lbSetTextRight [_i, _x select 2]; if ((_x select 0) isEqualTo (_st getOrDefault ["selP", ""])) then { _lb lbSetCurSel _i; }; } forEach _rows;
                    if ((count _rows) isEqualTo 0) then { _lb lbAdd (("Aucun écran TOC du camp") call comspec_atak_native_fnc_tr); };
                };
                // Contenus proposés : affichages du poste, satellites, escouades, caméras du camp.
                private _pw = _ui get "pWhat";
                private _opts = [["Carte et flux", "SPLIT"], ["Carte seule", "MAP"], ["Flux seul", "FEED"], ["Réseau", "NET"], ["ORBAT du site", "ORBAT"], ["PIP du poste", "TOC"],
                    ["Vue satellite SAT 1", "SAT1"], ["Vue satellite SAT 2", "SAT2"], ["Horloge et chronos", "CLOCK"], ["Main courante", "LOG"], ["Liste d'exécution", "EXCHECK"],
                    ["Pertes et résultats", "KILLS"], ["Demandes des éléments", "REQ"], ["Diapositive du Briefing", "SLIDES"]];
                { _opts pushBack [format ["Escouade %1", _x], "SQ|" + _x]; } forEach (["squads", _side] call comspec_atak_native_fnc_tocScreen);
                { _opts pushBack [_x select 0, "CAM|" + (_x select 1)]; } forEach (["sources"] call comspec_atak_native_fnc_tocCam);
                if ((_pw getVariable ["COMSPEC_ATAK_Sig", []]) isNotEqualTo _opts) then {
                    _pw setVariable ["COMSPEC_ATAK_Sig", _opts];
                    private _keep = _pw lbData ((lbCurSel _pw) max 0);
                    lbClear _pw;
                    { private _i = _pw lbAdd ((_x select 0) call comspec_atak_native_fnc_tr); _pw lbSetData [_i, _x select 1]; } forEach _opts;
                    _pw lbSetCurSel ((_opts findIf { (_x select 1) isEqualTo _keep }) max 0);
                };
                (_ui get "pInfo") ctrlSetStructuredText parseText ((format ["<t size='0.95' color='#8a9a93'>%1 écran(s) TOC du camp. Choisissez un écran dans la liste, puis son contenu et AFFICHER. Le choix est gardé d'une partie à l'autre.</t>", count _all]) call comspec_atak_native_fnc_tr);
            };
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
                (_ui get "lInfo") ctrlSetStructuredText parseText (("<t size='0.95' color='#8a9a93'>Heures en ZULU. S'inscrivent seuls : comptes rendus et alertes reçus, demandes, prises de poste, FLASH, chronos, lignes d'exécution passées. Commune à tous les PC du camp et gardée d'une partie à l'autre.</t>") call comspec_atak_native_fnc_tr);
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
                (_ui get "xInfo") ctrlSetStructuredText parseText (("<t size='0.95' color='#8a9a93'>PASSÉ inscrit l'heure ZULU réelle et une entrée à la main courante. La liste se prépare aussi dans le module Écran TOC (Eden ou Zeus). Commune au camp et gardée d'une partie à l'autre.</t>") call comspec_atak_native_fnc_tr);
            };
            case "PERTES": {
                (["boardText", ["KILLS", _room, _side]] call comspec_atak_native_fnc_tocBoard) params ["_l"];
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
                    "<t size='0.95' color='#8a9a93'>Les demandes envoyées par les téléphones du camp (MEDEVAC, appel de feu, ravitaillement) arrivent ici. Choisissez-en une, écrivez une réponse si besoin, puis son statut : il part au téléphone du demandeur.</t>"
                } else {
                    (_q select 0) params ["", "_t", "_k", "_by", "", "_grid", "_txt", "_stt", "_ans"];
                    format ["<t size='1.2' font='COMSPEC_BarlowBold'>%1</t> <t size='1' color='#8a9a93'>%2Z · %3%4 · %5</t><br/><t size='1.05'>%6</t>%7", [_k] call _esc, _t, [_by] call _esc,
                        ["", " · grille " + _grid] select (_grid isNotEqualTo ""), _stLbl getOrDefault [_stt, _stt], [_txt] call _esc, ["", format ["<br/><t size='1' color='#7fd0b0'>Réponse : %1</t>", [_ans] call _esc]] select (_ans isNotEqualTo "")]
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
                private _s = "<t size='1.2' font='COMSPEC_BarlowBold'>MESSAGES DU POSTE</t><br/>";
                if ((count _tm) isEqualTo 0) then { _s = _s + "<t size='1' color='#8a9a93'>Aucun message envoyé au camp</t>"; };
                private _last = _tm select [0 max ((count _tm) - 5)];
                reverse _last;
                { _s = _s + format ["<t size='1'><t color='#7fd0b0'>%1</t> %2 · %3<br/>   %4</t><br/>", _x param [0, ""], _x param [2, ""], [_x param [3, ""]] call _esc, [(_x param [5, ""]) select [0, 120]] call _esc]; } forEach _last;
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
