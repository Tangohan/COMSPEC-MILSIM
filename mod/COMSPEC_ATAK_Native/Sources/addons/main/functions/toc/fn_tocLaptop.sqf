/*
    PC portable du poste TOC (retour de TTRD, 26/10 : « le POSTE TOC doit ouvrir le PC portable, pas le téléphone,
    c'est totalement indépendant ») : l'écran du PC, ouvert par l'action Poste TOC (fn_tocPost « open ») sur un PC de
    TOC ou un écran TOC qui sert de poste (fn_tocScreen). Aucun téléphone n'est nécessaire : tout vient du serveur
    (fn_tocFeed), le PC est sur le réseau filaire du poste (pas de signal, de batterie ni de file d'envoi du téléphone).
    Écran :
      - la vraie carte d'Arma, en grand : marqueurs de la mission et de l'ATAK, nœuds ATAK du camp (joueurs et IA
        équipées, symbole OTAN, en direct / ancien / perdu / hors ligne), comptes rendus et alertes géolocalisés,
        objectif de l'ordre en cours (clic droit sur la carte), poste TOC ;
      - GROUPES : groupes du camp (chef, effectif, nœuds, grille), ordre au groupe, message au chef ;
      - FLUX : comptes rendus, alertes, MEDEVAC, BDA, ordres et messages du camp ;
      - ORDRES : rédacteur d'ordre (type, destinataire, priorité, consigne, objectif), ordres émis et leurs réponses ;
      - CAMP : message à tout le camp ou à un chef de groupe (arrive dans Messages), indicatif du poste.
    Ce que l'opérateur regarde (cadrage de la carte, onglet, sélection) est publié sur son unité
    (COMSPEC_ATAK_TocView, toutes les 2 s au plus quand ça change) : les écrans TOC en affichage « TOC » le reprennent,
    c'est le PIP du TOC sur n'importe quel écran du jeu (fn_tocScreen).
    Modes (client) :
      ["open", objet]   ouvre le PC du poste          ["load", display] / ["unload"] (display COMSPEC_RscTocLaptop)
      ["refresh"]       chaque seconde                ["draw", carte] (Draw de la carte)
      ["tab", nom]      "GROUPES" | "FLUX" | "ORDRES" | "CAMP"
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
private _kinds = [["DÉPLACER", "MOVE"], ["TENIR", "HOLD"], ["RECO", "RECON"], ["APPUI AÉRIEN", "CAS"], ["RENFORT", "QRF"], ["FRAGO", "FRAGO"], ["DEM-SSE", "DEMSSE"]];
// Groupes du camp : ceux qui ont des joueurs, et ceux dont un membre est un nœud ATAK (IA équipées).
private _groups = {
    params [["_nodes", []]];
    private _names = _nodes apply { _x param [7, ""] };
    private _out = [];
    {
        private _g = _x;
        if ((str side _g) isEqualTo _side && {({alive _x} count (units _g)) > 0} && {({isPlayer _x} count (units _g)) > 0 || {(groupId _g) in _names}}) then {
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
        true
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
        private _title = ["COMSPEC_RscStructuredText", [_xi + _W * 0.006, _yi + _H * 0.006, _wi * 0.55, _th], "title"] call _mk;
        private _clock = ["COMSPEC_RscStructuredText", [_xi + _wi * 0.45, _yi + _H * 0.008, _wi * 0.47, _th], "clock"] call _mk;
        ["close", [_xi + _wi - _W * 0.07, _yi + _H * 0.006, _W * 0.066, _th - _H * 0.012], "FERMER", { closeDialog 0; }, false, "Fermer le PC (Échap)"] call _btn;
        // Carte : 66 % de la largeur.
        private _sb = _H * 0.034;
        private _by = _yi + _th + _H * 0.004;
        private _bh = _hi - _th - _sb - _H * 0.008;
        private _mw = _wi * 0.655;
        private _map = ["COMSPEC_RscMapToc", [_xi, _by, _mw, _bh], "map"] call _mk;
        _map ctrlAddEventHandler ["Draw", { ["draw", _this select 0] call comspec_atak_native_fnc_tocLaptop; }];
        _map ctrlAddEventHandler ["MouseButtonClick", {
            params ["_m", "_b", "_mx", "_my"];
            if (_b isEqualTo 1) then { ["click", _m ctrlMapScreenToWorld [_mx, _my]] call comspec_atak_native_fnc_tocLaptop; };
        }];
        private _tb = _H * 0.032; private _tw = _W * 0.062; private _tg = _W * 0.004;
        {
            _x params ["_k", "_t", "_tip"];
            [_k, [_xi + _tg + _forEachIndex * (_tw + _tg), _by + _tg, _tw, _tb], _t, compile format ["['frame', %1] call comspec_atak_native_fnc_tocLaptop;", str (_k select [5])], false, _tip] call _btn;
        } forEach [["frame" + "net", "RÉSEAU", "Cadrer tout le réseau du camp"], ["frame" + "me", "POSTE", "Centrer sur le poste"], ["frame" + "sel", "SÉLECTION", "Centrer sur le groupe ou le compte rendu choisi"]];
        private _hint = ["COMSPEC_RscText", [_xi + _tg, _by + _bh - _H * 0.03, _mw - 2 * _tg, _H * 0.026]] call _mk;
        _hint ctrlSetBackgroundColor [0, 0, 0, 0.55];
        _hint ctrlSetFontHeight (_H * 0.017);
        _hint ctrlSetText (("Molette : zoom · clic gauche maintenu : déplacer · clic droit : objectif de l'ordre") call comspec_atak_native_fnc_tr);
        // Panneau de droite : onglets.
        private _px = _xi + _mw + _W * 0.004; private _pw = _wi - _mw - _W * 0.004;
        (["COMSPEC_RscText", [_px, _by, _pw, _bh]] call _mk) ctrlSetBackgroundColor [0.045, 0.055, 0.048, 1];
        private _tabs = ["GROUPES", "FLUX", "ORDRES", "CAMP"];
        private _tabW = _pw / 4;
        {
            ["tab" + _x, [_px + _forEachIndex * _tabW, _by, _tabW - _W * 0.002, _H * 0.04], _x, compile format ["['tab', %1] call comspec_atak_native_fnc_tocLaptop;", str _x]] call _btn;
        } forEach _tabs;
        private _m = _W * 0.006;
        private _cx = _px + _m; private _cw = _pw - 2 * _m;
        private _cy = _by + _H * 0.05; private _ch = _bh - _H * 0.056;
        private _bw3 = (_cw - 2 * _m) / 3;
        private _rh = _H * 0.034;
        // GROUPES
        private _gl = ["COMSPEC_RscListBox", [_cx, _cy, _cw, _ch * 0.56], "gList"] call _mk;
        _gl ctrlAddEventHandler ["LBSelChanged", { params ["_c", "_i"]; ["pick", ["GROUPES", _c lbData _i]] call comspec_atak_native_fnc_tocLaptop; }];
        ["COMSPEC_RscStructuredText", [_cx, _cy + _ch * 0.58, _cw, _ch * 0.3], "gInfo"] call _mk;
        ["gOrder", [_cx, _cy + _ch - _rh, _bw3, _rh], "ORDRE AU GROUPE", { ["act", "gOrder"] call comspec_atak_native_fnc_tocLaptop; }, true] call _btn;
        ["gMsg", [_cx + _bw3 + _m, _cy + _ch - _rh, _bw3, _rh], "MESSAGE AU CHEF", { ["act", "gMsg"] call comspec_atak_native_fnc_tocLaptop; }] call _btn;
        ["gCenter", [_cx + 2 * (_bw3 + _m), _cy + _ch - _rh, _bw3, _rh], "CENTRER", { ['frame', 'sel'] call comspec_atak_native_fnc_tocLaptop; }] call _btn;
        // FLUX
        private _fl = ["COMSPEC_RscListBox", [_cx, _cy, _cw, _ch * 0.66], "fList"] call _mk;
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
        _cs ctrlSetText (((["callsign"] call comspec_atak_native_fnc_tocPost)) call comspec_atak_native_fnc_tr);
        ["cSave", [_cx + _cw - _bw3, _y, _bw3, _rh], "ENREGISTRER", { ["act", "cSave"] call comspec_atak_native_fnc_tocLaptop; }] call _btn;
        _y = _y + _rh + _H * 0.012;
        ["COMSPEC_RscStructuredText", [_cx, _y, _cw, (_cy + _ch) - _y], "cInfo"] call _mk;
        // Choix des listes déroulantes gardés tout de suite dans le brouillon (listes reconstruites quand le camp change).
        {
            _x params ["_k", "_dk"];
            (_ui get _k) ctrlAddEventHandler ["LBSelChanged", compile format ["params ['_c', '_i']; if (_i >= 0) then { (uiNamespace getVariable ['COMSPEC_ATAK_TocLapDraft', createHashMap]) set [%1, _c lbData _i]; };", str _dk]];
        } forEach [["oKind", "kind"], ["oPrio", "prio"], ["oTarget", "target"], ["cDest", "dest"], ["cPrio", "mprio"]];
        // Ligne d'état.
        private _bar = ["COMSPEC_RscStructuredText", [_xi, _yi + _hi - _sb, _wi, _sb], "bar"] call _mk;
        _bar ctrlSetBackgroundColor [0.06, 0.08, 0.07, 1];
        // Brouillon remis (PC refermé puis rouvert).
        _ok lbSetCurSel ((_kinds findIf { (_x select 1) isEqualTo (_draft getOrDefault ["kind", "MOVE"]) }) max 0);
        _op lbSetCurSel ((["ROUTINE", "IMPORTANT", "URGENT", "FLASH"] find (_draft getOrDefault ["prio", "ROUTINE"])) max 0);
        _cp lbSetCurSel ((["ROUTINE", "URGENT", "FLASH"] find (_draft getOrDefault ["mprio", "ROUTINE"])) max 0);
        (_ui get "oText") ctrlSetText (((_draft getOrDefault ["text", ""])) call comspec_atak_native_fnc_tr);
        (_ui get "oGrid") ctrlSetText (((_draft getOrDefault ["grid", ""])) call comspec_atak_native_fnc_tr);
        (_ui get "cText") ctrlSetText (((_draft getOrDefault ["msg", ""])) call comspec_atak_native_fnc_tr);
        ["tab", _st get "tab"] call _fn;
        ["refresh"] call _fn;
        ["frame", ["net", "post"] select ((count ((call _feedOf) param [1, []])) isEqualTo 0)] call _fn;
        private _h = [{ ["refresh"] call comspec_atak_native_fnc_tocLaptop; }, 1] call CBA_fnc_addPerFrameHandler;
        _st set ["pfh", _h];
        ["INFO", "TOC", format ["PC du poste %1 ouvert", ["callsign"] call comspec_atak_native_fnc_tocPost]] call comspec_atak_native_fnc_log;
    };
    case "unload": {
        ["keep"] call _fn;
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
        private _t = [_a, "GROUPES"] select !(_a in ["GROUPES", "FLUX", "ORDRES", "CAMP"]);
        _st set ["tab", _t];
        private _sets = createHashMapFromArray [
            ["GROUPES", ["gList", "gInfo", "gOrder", "gMsg", "gCenter"]],
            ["FLUX", ["fList", "fInfo", "fCenter"]],
            ["ORDRES", ["oL1", "oL2", "oL3", "oL4", "oL5", "oKind", "oPrio", "oTarget", "oText", "oGrid", "oClear", "oSend", "oInfo"]],
            ["CAMP", ["cL1", "cL2", "cL3", "cDest", "cPrio", "cText", "cSend", "cCs", "cSave", "cInfo"]]
        ];
        { private _on = _x isEqualTo _t; { (_ui getOrDefault [_x, controlNull]) ctrlShow _on; } forEach _y; } forEach _sets;
        { (_ui get ("tab" + _x)) ctrlSetBackgroundColor ([[0.07, 0.085, 0.075, 1], [0.18, 0.38, 0.21, 1]] select (_x isEqualTo _t)); } forEach ["GROUPES", "FLUX", "ORDRES", "CAMP"];
        _st set ["sig", ""];
        ["refresh"] call _fn;
    };
    case "pick": {
        // Sélection posée par la reconstruction de la liste : rien à recentrer.
        if (_st getOrDefault ["mute", false]) exitWith {};
        _a params ["_tab", "_v"];
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
        if ((count _ctr) < 2) exitWith { ["status", "Pas de position connue pour cette sélection"] call _fn; };
        _map ctrlMapAnimAdd [0.4, ((_span / (worldSize max 1000)) max 0.008) min 1, [_ctr select 0, _ctr select 1, 0]];
        ctrlMapAnimCommit _map;
    };
    case "click": {
        private _p = _a;
        _draft set ["pos", [_p select 0, _p select 1]];
        if ((count _ui) > 0) then { (_ui get "oGrid") ctrlSetText ((([[_p select 0, _p select 1, 0], 8] call comspec_atak_native_fnc_gridRef)) call comspec_atak_native_fnc_tr); };
        if ((_st getOrDefault ["tab", ""]) isNotEqualTo "ORDRES") then { ["keep"] call _fn; ["tab", "ORDRES"] call _fn; };
        ["status", format ["Objectif de l'ordre : grille %1", [[_p select 0, _p select 1, 0], 8] call comspec_atak_native_fnc_gridRef]] call _fn;
    };
    case "status": { [_a] call _setStatus; };
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
            case "gMsg": {
                private _g = ([_nodes] call _groups) select { (_x select 0) isEqualTo (_st getOrDefault ["selG", ""]) };
                if ((count _g) isEqualTo 0) exitWith { ["Choisissez d'abord un groupe"] call _setStatus; };
                private _ld = (_g select 0) select 2;
                if (!isPlayer _ld) exitWith { ["Le chef de ce groupe n'est pas un joueur : envoyez un ordre"] call _setStatus; };
                _draft set ["dest", getPlayerUID _ld];
                ["tab", "CAMP"] call _fn;
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
        (_ui get "title") ctrlSetStructuredText parseText ((format ["<t size='1.45' font='COMSPEC_BarlowBold'>POSTE TOC · %1</t>   <t size='1.15' color='#8a9a93'>%2 · opérateur %3</t>", [_cs] call _esc, _sideName, [name player] call _esc]) call comspec_atak_native_fnc_tr);
        private _live = [["<t color='#e05a4a'>● HORS LIGNE</t>", "<t color='#e0b040'>● RETARD</t>"] select (_age >= 0), "<t color='#5cc76b'>● EN DIRECT</t>"] select (_age >= 0 && {_age < 12});
        (_ui get "clock") ctrlSetStructuredText parseText ((format ["<t align='right' size='1.15'>%1   <t color='#8a9a93'>%2</t>   %3</t>",
            [dayTime, "HH:MM:SS"] call BIS_fnc_timeToString, [format ["serveur il y a %1 s", _age], "en attente du serveur"] select (_age < 0), _live]) call comspec_atak_native_fnc_tr);
        private _cnt = [0, 0, 0, 0];
        { private _f = (_x select 4) min 3; _cnt set [_f, (_cnt select _f) + (_x param [8, 1])]; } forEach _nodes;
        _stats params [["_jam", 0], ["_relays", 0], ["_players", 0], ["_ais", 0]];
        private _msg = (_st getOrDefault ["status", ["", 0]]);
        (_ui get "bar") ctrlSetStructuredText parseText ((format ["<t size='1.05'>  RÉSEAU  <t color='#5cc76b'>%1 en ligne</t> · <t color='#e0b040'>%2 anciens</t> · <t color='#9aa39f'>%3 perdus</t> · <t color='#6f7773'>%4 hors ligne</t>   <t color='#8a9a93'>%5 joueurs · %6 IA · %7 relais · %8 brouilleurs</t>%9</t>",
            _cnt select 0, _cnt select 1, _cnt select 2, _cnt select 3, _players, _ais, _relays, _jam,
            ["", format ["   <t color='#7fd0b0'>▶ %1</t>", [_msg select 0] call _esc]] select (diag_tickTime < (_msg select 1))]) call comspec_atak_native_fnc_tr);
        private _groupsL = [_nodes] call _groups;
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
                    format ["<t size='1.35' font='COMSPEC_BarlowBold'>%1</t><br/><t size='1.1'>Chef %2 %3<br/>%4 vivant(s) dont %5 joueur(s) · %6 nœud(s) ATAK<br/>%7</t>",
                        [_gn] call _esc, [name _ld] call _esc, ["", format [" · n° %1", ([_ld] call comspec_atak_native_fnc_phoneIdent) select 0]] select (isPlayer _ld), _nAll, _np, _n,
                        [format ["Grille %1 · %2", [[_p select 0, _p select 1, 0], 6] call comspec_atak_native_fnc_gridRef, ["en direct", "point ancien", "point perdu", "téléphone hors ligne"] select ((_best min 3) max 0)], "<t color='#e0b040'>Position inconnue du poste : aucun ATAK dans ce groupe</t>"] select ((count _p) < 2)]
                })) call comspec_atak_native_fnc_tr);
            };
            case "FLUX": {
                private _lb = _ui get "fList";
                if (_changed) then {
                    _st set ["mute", true];
                    lbClear _lb;
                    for "_k" from ((count _events) - 1) to 0 step -1 do {
                        (_events select _k) params [["_time", ""], ["_kind", ""], ["_prio", ""], ["_who", ""], ["_grid", ""], ["_text", ""], ["_p", []], "", ["_old", false]];
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
                (_ui get "oInfo") ctrlSetStructuredText parseText ((_s) call comspec_atak_native_fnc_tr);
            };
            case "CAMP": {
                private _cd = _ui get "cDest";
                private _want = _draft getOrDefault ["dest", ""];
                private _opts = [["Tout le camp", ""]];
                { private _ld = _x select 2; if (isPlayer _ld && {_ld isNotEqualTo player}) then { _opts pushBack [format ["Chef de %1 · %2", _x select 0, name _ld], getPlayerUID _ld]; }; } forEach _groupsL;
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
        private _ts = 0.032 * (safeZoneH / 1.2);
        private _selG = _st getOrDefault ["selG", ""];
        // Poste TOC.
        private _o = _st getOrDefault ["obj", objNull];
        if (!isNull _o) then {
            _map drawIcon ["\A3\ui_f\data\map\markers\nato\b_hq.paa", _sideRgb + [1], getPosASL _o, 30, 30, 0, "TOC", 1, _ts, "RobotoCondensedBold", "right"];
        };
        // Comptes rendus et alertes géolocalisés des 20 dernières minutes.
        {
            _x params ["", ["_kind", ""], ["_prio", ""], "", "", "", ["_p", []], ["_at", -1e9]];
            if ((count _p) >= 2 && {serverTime - _at < 1200}) then {
                _map drawIcon ["\A3\ui_f\data\map\markers\military\warning_CA.paa", [[1, 0.55, 0.2, 1], [1, 0.3, 0.22, 1]] select (_prio in ["FLASH", "URGENT"]), [_p select 0, _p select 1, 0], 26, 26, 0, _kind, 1, _ts * 0.9, "RobotoCondensedBold", "right"];
            };
        } forEach _events;
        // Nœuds ATAK du camp : symbole OTAN ami, couleur du camp ; ancien pâli, perdu et hors ligne en gris.
        {
            _x params ["", "_cs", "_p", "_dir", "_fr", "_kind", "_ai", ["_grp", ""], ["_n", 1]];
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
            _map drawIcon [_tex, _col, _pos, 28, 28, 0, _cs + (["", format [" +%1", _n - 1]] select (_n > 1)) + (["", " · IA"] select _ai) + (["", " · ancien", " · perdu", " · hors ligne"] select (_fr min 3)), 1, _ts, "RobotoCondensedBold", "right"];
            if (_fr < 2) then { _map drawArrow [_pos, _pos getPos [45, _dir], _col]; };
        } forEach _nodes;
        // Objectif de l'ordre en cours de rédaction (clic droit).
        private _op = _draft getOrDefault ["pos", []];
        if ((count _op) >= 2) then {
            _map drawIcon ["\A3\ui_f\data\map\markers\military\objective_CA.paa", [1, 0.85, 0.2, 1], [_op select 0, _op select 1, 0], 34, 34, 0, "OBJECTIF", 1, _ts, "RobotoCondensedBold", "right"];
        };
    };
};
true
