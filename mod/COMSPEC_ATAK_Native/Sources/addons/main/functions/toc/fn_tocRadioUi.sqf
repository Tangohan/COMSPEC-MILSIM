/*
    Affichages de l'activité radio (modèle : fn_tocRadio, « model ») : qui parle, sur quel réseau, quelle fréquence.
      - Écran mural RADIO (fn_tocBoard, affichage « RADIO ») : cases de compteurs, émission en cours en grand (indicatif
        et fréquence), couloirs des réseaux (état, dernier émetteur, dernière écoute, occupation sur 10 min). Indicateurs
        fixes : rien ne clignote, l'image est redessinée chaque seconde par la boucle des écrans.
      - Console radio du poste : panneau autonome que la console du poste peut monter dans sa section RADIO
        (« mount » rend la table des contrôles, « refresh » la redessine), et fenêtre « Activité radio » qui le montre
        seul (menu ACE des écrans TOC, module Zeus). En transmission (indicatif, réseau, fréquence, durée, barre de durée
        sur 30 s), couloirs des réseaux, liste de veille à modifier, historique filtré par réseau ou par collisions.
      - Encadré « Qui parle » à l'écran du jeu (calque RscTitles COMSPEC_RscTitleTocRadioHud) : réglage du joueur,
        décoché par défaut (réseaux de ses radios seulement) ; au Zeus : coché par défaut, tous les camps.
    Aucun message par-dessus le jeu : les résultats s'écrivent dans la ligne d'état du panneau.
    Coût : rien ne tourne fenêtre fermée et encadré vide. Fenêtre ouverte : durées reprises 4 fois par seconde, listes
    refaites seulement quand l'état change (ou toutes les 5 s pour les âges). Encadré : 2 fois par seconde tant que
    quelqu'un émet.
    Modes :
      ["wall", [camp, outils de fn_tocBoard]]           contenu de l'affichage RADIO (appelé par fn_tocBoard)
      ["open", [camp, tout voir]] / ["close"]            fenêtre « Activité radio »
      ["mount", [display, [x, y, l, h], camp, tout voir, groupe parent]] -> table des contrôles (panneau à monter)
      ["refresh", vrai : listes aussi] / ["tick"] / ["act", action] / ["say", texte]   panneau monté
      ["dock", [display, [x, y, l, h], montré]]   panneau dans la console du poste (fn_tocLaptop, onglet RADIO)
      ["initClient"] / ["changed", camp] / ["hudWant"] / ["hudSync"] / ["hudDraw"] / ["hudHide"]   encadré
*/
params [["_mode", ""], ["_a", []]];
disableSerialization;
private _rTr = { _this call comspec_atak_native_fnc_tr };
private _rEsc = { params ["_t"]; if !(_t isEqualType "") then { _t = str _t; }; { _t = [_t, _x select 0, _x select 1] call CBA_fnc_replace; } forEach [["&", "&amp;"], ["<", "&lt;"], [">", "&gt;"]]; _t };
private _rCol = { params ["_k", ["_al", 1]]; ["rgba", [_k, _al]] call comspec_atak_native_fnc_tocTheme };
private _rInk = { ["ink", _this] call comspec_atak_native_fnc_tocTheme };
private _rad = comspec_atak_native_fnc_tocRadio;
private _sides = ["WEST", "EAST", "GUER", "CIV"];
// ---- TABLE _kinds (JSON) : genre d'émission -> libellé.
private _kinds = [["SW", "portative"], ["LR", "longue portée"], ["VEH", "véhicule"], ["VON", "voix du jeu"], ["STN", "station"]];
// ---- FIN
// ---- TABLE _states (JSON) : état d'un réseau -> [libellé, jeton de couleur du thème].
private _states = [["TX", ["émission", "accent"]], ["CLASH", ["collision", "red"]], ["SILENT", ["silence %1 min", "amber"]], ["IDLE", ["veille", "muted"]]];
// ---- FIN
private _kindOf = { ((createHashMapFromArray _kinds) getOrDefault [_this, "portative"]) call _rTr };
private _stateOf = {
    params ["_st", "_sil"];
    ((createHashMapFromArray _states) getOrDefault [_st, ["veille", "muted"]]) params ["_lbl", "_tok"];
    [format [_lbl call _rTr, _sil], _tok]
};
private _sideName = { ((createHashMapFromArray [["WEST", "BLUFOR"], ["EAST", "OPFOR"], ["GUER", "Indépendants"], ["CIV", "Civils"]]) getOrDefault [_this, _this]) call _rTr };
private _isZeus = { !isNull (getAssignedCuratorLogic player) || {serverCommandAvailable "#kick"} };
private _pan = uiNamespace getVariable ["COMSPEC_ATAK_TocRadioPanel", createHashMap];
switch (_mode) do {
    // ================= ÉCRAN MURAL =================
    case "wall": {
        _a params ["_side", "_hp"];
        ["sub", ""] call _rad;
        private _text = _hp get "text";
        private _box = _hp get "box";
        private _tiles = _hp get "tiles";
        private _section = _hp get "section";
        private _table = _hp get "table";
        private _reg = _hp get "rg";
        private _mdl = ["model", [_side, false, "", false]] call _rad;
        (_mdl get "kpi") params ["_nLive", "_nMon", "_nClash", "_nQuiet"];
        ["kpi" call _reg, [[_nLive, "en émission", "accent"], [_nMon, "réseaux veillés", "text"], [_nClash, "collisions", "red"], [_nQuiet, "silencieux", "amber"]], 4] call _tiles;
        // ---- Émission en cours, en grand : une seule en pleine largeur, deux côte à côte ; les autres dans les couloirs.
        ("live" call _reg) params ["_lx", "_ly", "_lw", "_lh"];
        private _live = _mdl get "live";
        private _now = _mdl get "now";
        if (_live isEqualTo []) then {
            [[_lx, _ly, _lw, _lh], "bg1"] call _box;
            [[_lx, _ly, 8, _lh], "lineStrong", 1, "b1"] call _box;
            [[_lx + 32, _ly, _lw - 64, _lh], ["Aucune émission sur les réseaux veillés", "Aucun réseau veillé : ajoutez une fréquence à la liste de veille (console radio du poste)"] select (_nMon isEqualTo 0 && {!(_mdl get "wide")}), "heading", "muted"] call _text;
        } else {
            private _n = (count _live) min 2;
            private _tw = (_lw - (_n - 1) * 16) / _n;
            private _big = _n isEqualTo 1;
            {
                _x params ["", "_cs", "", "_grp", "_k", "", "_kind", "_mesh", "", "_t0", "", "_hit", "_net"];
                private _tx = _lx + _forEachIndex * (_tw + 16);
                private _tok = ["accent", "red"] select _hit;
                private _isF = ["isFreq", _k] call _rad;
                private _fw = [[0, 0], [300, 460]] select _isF select _big;
                [[_tx, _ly, _tw, _lh], "bg2"] call _box;
                [[_tx, _ly, 8, _lh], _tok, 1, "b1"] call _box;
                [[_tx + 28, _ly + 6, _tw - _fw - 52, 84], _cs, ["squadName", "hero"] select _big, "text", "L", true] call _text;
                if (_isF) then { [[_tx + _tw - _fw - 16, _ly + 6, _fw, 84], _k, ["num", "hero"] select _big, _tok, "R", true] call _text; };
                private _parts = [_net call _rTr];
                if (_hit) then { _parts = [("COLLISION") call _rTr, _net call _rTr]; };
                if (_grp isNotEqualTo "") then { _parts pushBack _grp; };
                [[_tx + 28, _ly + 98, _tw * 0.52, 40], _parts joinString " · ", "heading", ["text2", "red"] select _hit, "L", true] call _text;
                [[_tx + _tw * 0.52 + 28, _ly + 98, _tw * 0.48 - 44, 40], format ["%1%2 · %3", _kind call _kindOf, ["", " · " + (("maillage") call _rTr)] select _mesh, ["dur", _now - _t0] call _rad], "data", "text2", "R", true] call _text;
            } forEach (_live select [0, _n]);
        };
        // ---- Couloirs des réseaux ----
        private _more = (count _live) - 2;
        ["RÉSEAUX", [("occupation sur 10 min") call _rTr, format [("%1 autres en émission · occupation sur 10 min") call _rTr, _more]] select (_more > 0)] call _section;
        private _lanes = _mdl get "lanes";
        if (_lanes isEqualTo []) exitWith { ["area" call _reg, "Aucun réseau à montrer pour l'instant", "body", "muted"] call _text; };
        ["RADIO", "cols" call _reg, "area" call _reg, _lanes apply {
            _x params ["", "_name", "_freq", "_st", "_sil", "_who", "_last", "_cnt", "_occ", "_mon"];
            ([_st, _sil] call _stateOf) params ["_lbl", "_tok"];
            [["●", _tok], [_name, ["text2", "text"] select _mon], [[_freq, "—"] select (_freq isEqualTo ""), "text2"], [_lbl, _tok], [_who, ["text2", "text"] select (_st in ["TX", "CLASH"])],
             [["—", ["ago", _now - _last] call _rad] select (_last >= 0), "text2"], [str _cnt, "text2"], [format ["%1 %2", _occ, "%"], ["text2", "amber"] select (_occ >= 50)]]
        }, "+ %1 autres réseaux"] call _table;
        true
    };
    // ================= CONSOLE RADIO =================
    case "open": {
        if (!hasInterface) exitWith { false };
        if (!canSuspend) exitWith { [_mode, _a] spawn { uiSleep 0.08; _this call comspec_atak_native_fnc_tocRadioUi; }; true };
        _a params [["_side", ""], ["_all", false]];
        private _parent = [findDisplay 46, findDisplay 312] select (!isNull (findDisplay 312));
        if (isNull _parent) exitWith { false };
        private _old = uiNamespace getVariable ["COMSPEC_ATAK_TocRadioWin", displayNull];
        if (!isNull _old) then { _old closeDisplay 2; };
        private _d = _parent createDisplay "RscDisplayEmpty";
        if (isNull _d) exitWith { false };
        uiNamespace setVariable ["COMSPEC_ATAK_TocRadioWin", _d];
        private _w = safeZoneW * 0.86;
        private _h = safeZoneH * 0.8;
        private _x0 = safeZoneX + (safeZoneW - _w) / 2;
        private _y0 = safeZoneY + (safeZoneH - _h) / 2;
        private _u = safeZoneH * 0.006;
        private _mk = { params ["_cls", "_pos"]; private _c = _d ctrlCreate [_cls, -1]; _c ctrlSetPosition _pos; _c ctrlCommit 0; _c };
        private _dim = ["ctrlStatic", [safeZoneX, safeZoneY, safeZoneW, safeZoneH]] call _mk;
        _dim ctrlSetBackgroundColor [0, 0, 0, 0.5];
        private _bg = ["ctrlStatic", [_x0, _y0, _w, _h]] call _mk;
        _bg ctrlSetBackgroundColor (["bg0", 0.98] call _rCol);
        private _title = ["ctrlStatic", [_x0, _y0, _w, 6 * _u]] call _mk;
        _title ctrlSetBackgroundColor (["bg2"] call _rCol);
        _title ctrlSetTextColor (["text"] call _rCol);
        _title ctrlSetText (("  Activité radio · qui parle, sur quelle fréquence") call _rTr);
        private _rule = ["ctrlStatic", [_x0, _y0 + 6 * _u, _w, 0.4 * _u]] call _mk;
        _rule ctrlSetBackgroundColor (["accent"] call _rCol);
        private _close = ["ctrlButton", [_x0 + _w - 17 * _u, _y0 + 0.6 * _u, 16 * _u, 4.8 * _u]] call _mk;
        _close ctrlSetText (("Fermer") call _rTr);
        _close ctrlAddEventHandler ["ButtonClick", { ["close"] call comspec_atak_native_fnc_tocRadioUi; }];
        if (_side isEqualTo "" || {!(_side in _sides)}) then { _side = str side group player; };
        if !(_side in _sides) then { _side = "WEST"; };
        ["mount", [_d, [_x0 + _u, _y0 + 8 * _u, _w - 2 * _u, _h - 9 * _u], _side, _all && {call _isZeus}, controlNull]] call comspec_atak_native_fnc_tocRadioUi;
        true
    };
    case "close": {
        private _d = uiNamespace getVariable ["COMSPEC_ATAK_TocRadioWin", displayNull];
        if (!isNull _d) then { _d closeDisplay 2; };
        true
    };
    case "mount": {
        _a params [["_d", displayNull], ["_rect", [0, 0, 1, 1]], ["_side", "WEST"], ["_all", false], ["_grp", controlNull]];
        if (isNull _d) exitWith { createHashMap };
        _rect params ["_x0", "_y0", "_w", "_h"];
        // Dans un groupe (console du poste) : fond plein, le panneau couvre ce qui est dessous.
        if (!isNull _grp) then { private _under = _d ctrlCreate ["ctrlStatic", -1, _grp]; _under ctrlSetPosition [0, 0, _w + _x0 * 2, _h + _y0 * 2]; _under ctrlSetBackgroundColor (["bg0"] call _rCol); _under ctrlCommit 0; };
        private _u = safeZoneH * 0.006;
        private _mk = {
            params ["_cls", "_pos"];
            private _c = if (isNull _grp) then { _d ctrlCreate [_cls, -1] } else { _d ctrlCreate [_cls, -1, _grp] };
            _c ctrlSetPosition _pos;
            _c ctrlCommit 0;
            _c
        };
        private _headOf = {
            params ["_pos", "_txt"];
            private _c = ["ctrlStructuredText", _pos] call _mk;
            _c ctrlSetStructuredText parseText format ["<t size='1.15' font='RobotoCondensedBold' color='%1'>%2</t>", ["hex", "text"] call comspec_atak_native_fnc_tocTheme, _txt call _rTr];
            _c
        };
        private _act = {
            params ["_c", "_ev", "_key"];
            _c setVariable ["COMSPEC_ATAK_Act", _key];
            _c ctrlAddEventHandler [_ev, { ["act", (_this select 0) getVariable ["COMSPEC_ATAK_Act", ""]] call comspec_atak_native_fnc_tocRadioUi; }];
        };
        private _lw = _w * 0.6;
        private _rx = _x0 + _lw + 2 * _u;
        private _rw = _w - _lw - 2 * _u;
        // ---- Gauche : en transmission (3 lignes), puis couloirs des réseaux ----
        [[_x0, _y0, _lw, 5 * _u], "EN TRANSMISSION"] call _headOf;
        private _rows = [];
        for "_i" from 0 to 2 do {
            private _ry = _y0 + 5 * _u + _i * 12 * _u;
            private _back = ["ctrlStatic", [_x0, _ry, _lw, 11 * _u]] call _mk;
            _back ctrlSetBackgroundColor (["bg1"] call _rCol);
            private _mark = ["ctrlStatic", [_x0, _ry, 0.8 * _u, 11 * _u]] call _mk;
            private _txt = ["ctrlStructuredText", [_x0 + 2 * _u, _ry + 0.6 * _u, _lw - 3 * _u, 8 * _u]] call _mk;
            private _rail = ["ctrlStatic", [_x0 + 2 * _u, _ry + 9 * _u, _lw - 4 * _u, 1.2 * _u]] call _mk;
            _rail ctrlSetBackgroundColor (["bg3"] call _rCol);
            private _fill = ["ctrlStatic", [_x0 + 2 * _u, _ry + 9 * _u, 0, 1.2 * _u]] call _mk;
            _rows pushBack [_back, _mark, _txt, _rail, _fill, [_x0 + 2 * _u, _ry + 9 * _u, _lw - 4 * _u, 1.2 * _u]];
        };
        private _ny = _y0 + 41 * _u;
        [[_x0, _ny, _lw, 5 * _u], "RÉSEAUX"] call _headOf;
        private _lanesH = ["ctrlListNBox", [_x0, _ny + 5 * _u, _lw, 4.4 * _u]] call _mk;
        private _lanes = ["ctrlListNBox", [_x0, _ny + 9.4 * _u, _lw, _h - 41 * _u - 9.4 * _u - 7 * _u]] call _mk;
        private _lc = [0, 0.03, 0.3, 0.44, 0.6, 0.8, 0.91];
        { _x lnbSetColumnsPos _lc; } forEach [_lanesH, _lanes];
        _lanesH lnbAddRow (["", "Réseau", "Fréquence", "État", "Dernier émetteur", "Entendu", "Occupation"] apply { _x call _rTr });
        for "_i" from 0 to 6 do { _lanesH lnbSetColor [[0, _i], ["muted"] call _rCol]; };
        _lanesH ctrlEnable false;
        // ---- Droite : liste de veille, puis historique ----
        [[_rx, _y0, _rw, 5 * _u], "LISTE DE VEILLE"] call _headOf;
        private _mon = ["ctrlListNBox", [_rx, _y0 + 5 * _u, _rw, 24 * _u]] call _mk;
        _mon lnbSetColumnsPos [0, 0.5, 0.78];
        [_mon, "LBSelChanged", "monPick"] call _act;
        private _fy = _y0 + 30 * _u;
        private _field = {
            params ["_lbl", "_cls", "_help"];
            private _l = ["ctrlStatic", [_rx, _fy, _rw * 0.34, 4.6 * _u]] call _mk;
            _l ctrlSetText (_lbl call _rTr);
            _l ctrlSetTextColor (["text2"] call _rCol);
            private _c = [_cls, [_rx + _rw * 0.35, _fy, [_rw * 0.63, 4.6 * _u] select (_cls isEqualTo "ctrlCheckbox"), 4.6 * _u]] call _mk;
            _c ctrlSetTooltip (_help call _rTr);
            _fy = _fy + 5.4 * _u;
            _c
        };
        private _eName = ["Nom du réseau", "ctrlEdit", "Nom montré à la place de la fréquence. Exemple : CMD NET."] call _field;
        private _eFreq = ["Fréquence (MHz)", "ctrlEdit", "Fréquence à veiller, ou nom d'un canal du jeu. Exemple : 31.000 ; sans mod radio : VON-CAMP."] call _field;
        private _cChk = ["Contrôle radio attendu", "ctrlCheckbox", "Coché : le réseau est signalé quand il reste silencieux plus longtemps que le réglage « Silence d'un réseau ». Exemple : le réseau de commandement, 10 min."] call _field;
        private _bAdd = ["ctrlButton", [_rx, _fy + 0.4 * _u, _rw * 0.48, 5 * _u]] call _mk;
        _bAdd ctrlSetText (("Ajouter à la veille") call _rTr);
        _bAdd ctrlSetTooltip (("Ajoute le réseau, ou change son nom s'il est déjà veillé. Exemple : CMD NET sur 31.000.") call _rTr);
        [_bAdd, "ButtonClick", "netAdd"] call _act;
        private _bDel = ["ctrlButton", [_rx + _rw * 0.5, _fy + 0.4 * _u, _rw * 0.48, 5 * _u]] call _mk;
        _bDel ctrlSetText (("Retirer de la veille") call _rTr);
        _bDel ctrlSetTooltip (("Retire le réseau choisi dans la liste : le poste ne le voit plus. Exemple : un réseau qui ne sert plus.") call _rTr);
        [_bDel, "ButtonClick", "netDel"] call _act;
        _fy = _fy + 6.4 * _u;
        private _cAll = ["Tout surveiller", "ctrlCheckbox", "Zeus et administrateur : tous les réseaux de tous les camps, sans tenir compte de la liste de veille. Exemple : vérifier que la radio du camp adverse fonctionne."] call _field;
        _cAll ctrlEnable (call _isZeus);
        _cAll cbSetChecked _all;
        [_cAll, "CheckedChanged", "all"] call _act;
        private _cSide = ["Camp", "ctrlCombo", "Camp dont le poste montre les réseaux. Exemple : BLUFOR. Les autres camps sont réservés au Zeus et à l'administrateur."] call _field;
        { private _i = _cSide lbAdd (_x call _sideName); _cSide lbSetData [_i, _x]; if (_x isEqualTo _side) then { _cSide lbSetCurSel _i; }; } forEach _sides;
        _cSide ctrlEnable (call _isZeus);
        [_cSide, "LBSelChanged", "side"] call _act;
        [[_rx, _fy, _rw, 5 * _u], "HISTORIQUE"] call _headOf;
        private _cFil = ["ctrlCombo", [_rx, _fy + 5 * _u, _rw, 4.6 * _u]] call _mk;
        _cFil ctrlSetTooltip (("Filtre de l'historique : tous les réseaux, un seul réseau, ou les collisions seulement. Exemple : CMD NET.") call _rTr);
        [_cFil, "LBSelChanged", "filter"] call _act;
        private _hist = ["ctrlListNBox", [_rx, _fy + 10.4 * _u, _rw, _y0 + _h - 7 * _u - (_fy + 10.4 * _u)]] call _mk;
        _hist lnbSetColumnsPos [0, 0.2, 0.52, 0.86];
        private _status = ["ctrlStructuredText", [_x0, _y0 + _h - 6 * _u, _w, 5.6 * _u]] call _mk;
        _pan = createHashMapFromArray [["d", _d], ["grp", _grp], ["bAdd", _bAdd], ["bDel", _bDel], ["side", _side], ["all", _all], ["rows", _rows], ["lanes", _lanes], ["mon", _mon], ["name", _eName], ["freq", _eFreq], ["chk", _cChk],
            ["cAll", _cAll], ["cSide", _cSide], ["fil", _cFil], ["hist", _hist], ["status", _status], ["only", ""], ["sig", []], ["at", -10], ["filSig", []], ["monSig", []], ["u", _u]];
        uiNamespace setVariable ["COMSPEC_ATAK_TocRadioPanel", _pan];
        ["sub", ["", "*"] select _all] call _rad;
        ["refresh", true] call comspec_atak_native_fnc_tocRadioUi;
        ["say", "Les émissions s'inscrivent ici dès qu'un joueur du camp presse son alternat sur un réseau veillé."] call comspec_atak_native_fnc_tocRadioUi;
        // Durées et barres : 4 fois par seconde tant que le panneau existe, puis plus rien.
        if ((missionNamespace getVariable ["COMSPEC_ATAK_TocRadioPfh", -1]) < 0) then {
            missionNamespace setVariable ["COMSPEC_ATAK_TocRadioPfh", [{ ["tick"] call comspec_atak_native_fnc_tocRadioUi; }, 0.25] call CBA_fnc_addPerFrameHandler];
        };
        _pan
    };
    case "say": {
        private _c = _pan getOrDefault ["status", controlNull];
        if (isNull _c) exitWith { false };
        _c ctrlSetStructuredText parseText format ["<t size='0.9' color='%1'>%2</t>", ["hex", "text2"] call comspec_atak_native_fnc_tocTheme, [_a call _rTr] call _rEsc];
        true
    };
    case "tick": {
        if (isNull (_pan getOrDefault ["d", displayNull])) exitWith {
            private _h = missionNamespace getVariable ["COMSPEC_ATAK_TocRadioPfh", -1];
            if (_h >= 0) then { [_h] call CBA_fnc_removePerFrameHandler; };
            missionNamespace setVariable ["COMSPEC_ATAK_TocRadioPfh", -1];
            uiNamespace setVariable ["COMSPEC_ATAK_TocRadioPanel", createHashMap];
        };
        // Panneau de la console du poste sur un autre onglet : rien à redessiner.
        private _dockGrp = _pan getOrDefault ["grp", controlNull];
        if (!isNull _dockGrp && {!ctrlShown _dockGrp}) exitWith {};
        ["refresh", diag_tickTime - (_pan getOrDefault ["at", -10]) > 5] call comspec_atak_native_fnc_tocRadioUi;
    };
    case "refresh": {
        if (isNull (_pan getOrDefault ["d", displayNull])) exitWith { false };
        private _side = _pan get "side";
        private _mdl = ["model", [_side, _pan get "all", "", false]] call _rad;
        private _now = _mdl get "now";
        private _live = _mdl get "live";
        // ---- En transmission : chaque quart de seconde (durée, barre sur 30 s : ambre à 20 s, rouge en collision).
        {
            _x params ["_back", "_mark", "_txt", "_rail", "_fill", "_rr"];
            private _e = _live param [_forEachIndex, []];
            private _on = _e isNotEqualTo [];
            { _x ctrlShow _on; } forEach [_mark, _rail, _fill];
            if (!_on) then {
                private _idle = ["", ["Personne n'émet sur les réseaux veillés", "Aucun réseau veillé : ajoutez une fréquence à droite, par exemple 31.000"] select ((_mdl get "tbl") isEqualTo [] && {!(_mdl get "wide")})] select (_forEachIndex isEqualTo 0);
                if ((_txt getVariable ["COMSPEC_ATAK_Txt", "-"]) isNotEqualTo _idle) then {
                    _txt setVariable ["COMSPEC_ATAK_Txt", _idle];
                    _txt ctrlSetStructuredText parseText format ["<t size='1' color='%1'>%2</t>", ["hex", "muted"] call comspec_atak_native_fnc_tocTheme, _idle call _rTr];
                };
            } else {
                _e params ["", "_cs", "", "_grp", "_k", "", "_kind", "_mesh", "_lvl", "_t0", "", "_hit", "_net"];
                private _len = (_now - _t0) max 0;
                private _tok = switch (true) do { case (_hit): { "red" }; case (_len >= 20): { "amber" }; default { "accent" }; };
                private _s = format ["<t size='1.7' font='RobotoCondensedBold' color='@text'>%1</t>   <t size='1.15' color='@text2'>%2%3</t><t align='right'><t size='1.5' font='EtelkaMonospaceProBold' color='@%4'>%5</t></t><br/><t size='0.95' color='@muted'>%6%7%8%9</t><t align='right' size='1.05' font='EtelkaMonospacePro' color='@text'>%10</t>",
                    [_cs] call _rEsc, [_net call _rTr] call _rEsc, ["", " · " + ([_grp] call _rEsc)] select (_grp isNotEqualTo ""), _tok, [["", _k + " MHz"] select (["isFreq", _k] call _rad)] call _rEsc,
                    _kind call _kindOf, ["", " · " + (("maillage") call _rTr)] select _mesh, ["", format [(" · volume %1 %2") call _rTr, round (_lvl * 100), "%"]] select (_lvl >= 0),
                    ["", " · " + (("COLLISION : deux émetteurs sur ce réseau") call _rTr)] select _hit, ["dur", _len] call _rad];
                _txt setVariable ["COMSPEC_ATAK_Txt", "-"];
                _txt ctrlSetStructuredText parseText (_s call _rInk);
                _mark ctrlSetBackgroundColor ([_tok] call _rCol);
                _fill ctrlSetBackgroundColor ([_tok] call _rCol);
                _fill ctrlSetPosition [_rr select 0, _rr select 1, (_rr select 2) * ((_len / 30) min 1), _rr select 3];
                _fill ctrlCommit 0;
            };
        } forEach (_pan get "rows");
        // ---- Listes : seulement quand l'état change, ou toutes les 5 s (âges).
        private _st = ["state", _side] call _rad;
        private _sig = [_side, _st select 0, _mdl get "tbl", _pan get "only", _pan get "all"];
        if (_a isNotEqualTo true && {_sig isEqualTo (_pan get "sig")}) exitWith { true };
        _pan set ["sig", _sig];
        _pan set ["at", diag_tickTime];
        // Liste de veille : boutons actifs pour qui a le droit de régler la salle (fn_tocAccess ; le serveur fait foi).
        private _can = ["canEdit", _side] call _rad;
        { (_pan get _x) ctrlEnable _can; } forEach ["bAdd", "bDel"];
        private _lb = _pan get "lanes";
        lnbClear _lb;
        {
            _x params ["", "_name", "_freq", "_state", "_sil", "_who", "_last", "", "_occ", "_isMon"];
            ([_state, _sil] call _stateOf) params ["_lbl", "_tok"];
            private _i = _lb lnbAddRow ["●", _name call _rTr, [_freq, "—"] select (_freq isEqualTo ""), _lbl, _who, ["—", ["ago", _now - _last] call _rad] select (_last >= 0), format ["%1 %2", _occ, "%"]];
            _lb lnbSetColor [[_i, 0], [_tok] call _rCol];
            _lb lnbSetColor [[_i, 3], [_tok] call _rCol];
            if (!_isMon) then { _lb lnbSetColor [[_i, 1], ["text2"] call _rCol]; };
        } forEach (_mdl get "lanes");
        // Liste de veille : refaite seulement si elle a changé (le choix de la ligne est gardé sinon).
        private _tbl = ["nets", _side] call _rad;
        if (_tbl isNotEqualTo (_pan get "monSig")) then {
            _pan set ["monSig", +_tbl];
            _pan set ["busy", true];
            private _ml = _pan get "mon";
            lnbClear _ml;
            { private _i = _ml lnbAddRow [[_x select 0, "—"] select ((_x select 0) isEqualTo ""), _x select 1, ["", ("contrôle radio") call _rTr] select (_x select 2)]; _ml lnbSetData [[_i, 0], _x select 1]; } forEach _tbl;
            _pan set ["busy", false];
        };
        // Filtre de l'historique : tous, collisions, puis chaque réseau montré.
        private _fs = (_mdl get "lanes") apply { [(_x select 1) call _rTr, _x select 0] };
        if (_fs isNotEqualTo (_pan get "filSig")) then {
            _pan set ["filSig", _fs];
            _pan set ["busy", true];
            private _cf = _pan get "fil";
            lbClear _cf;
            { private _i = _cf lbAdd (_x select 0); _cf lbSetData [_i, _x select 1]; if ((_x select 1) isEqualTo (_pan get "only")) then { _cf lbSetCurSel _i; }; } forEach ([[("Tous les réseaux") call _rTr, ""], [("Collisions seulement") call _rTr, "!"]] + _fs);
            if ((lbCurSel _cf) < 0) then { _cf lbSetCurSel 0; };
            _pan set ["busy", false];
        };
        private _only = _pan get "only";
        private _hl = _pan get "hist";
        lnbClear _hl;
        {
            _x params ["_cs", "", "_k", "", "", "_z", "_len", "", "_hit", "_net"];
            if (_only isEqualTo "" || {_only isEqualTo _k} || {_only isEqualTo "!" && {_hit}}) then {
                private _i = _hl lnbAddRow [(_z select [0, 8]) + "Z", _cs, _net call _rTr, ["dur", _len] call _rad];
                if (_hit) then { _hl lnbSetColor [[_i, 2], ["red"] call _rCol]; _hl lnbSetColor [[_i, 3], ["red"] call _rCol]; };
            };
        } forEach (_mdl get "hist");
        true
    };
    case "act": {
        // Liste ou filtre en cours de remplissage par « refresh » : ce n'est pas un geste du joueur.
        if (isNull (_pan getOrDefault ["d", displayNull]) || {_pan getOrDefault ["busy", false]}) exitWith { false };
        private _side = _pan get "side";
        private _say = { ["say", _this] call comspec_atak_native_fnc_tocRadioUi; };
        switch (_a) do {
            case "monPick": {
                private _ml = _pan get "mon";
                private _k = _ml lnbData [lnbCurSelRow _ml, 0];
                private _row = ((["nets", _side] call _rad) select { (_x select 1) isEqualTo _k }) param [0, []];
                if (_row isEqualTo []) exitWith {};
                (_pan get "name") ctrlSetText (_row select 0); // i18n:brut (saisie)
                (_pan get "freq") ctrlSetText (_row select 1); // i18n:brut (saisie)
                (_pan get "chk") cbSetChecked (_row select 2);
            };
            case "netAdd": {
                if !(["canEdit", _side] call _rad) exitWith { ("Liste de veille réservée au chef de poste et à l'administrateur Zeus (réglage « Droits de la salle TOC »)") call _say; };
                private _k = ["key", ctrlText (_pan get "freq")] call _rad;
                if (_k isEqualTo "") exitWith { ("Écrivez une fréquence en MHz. Exemple : 31.000") call _say; };
                private _name = trim (ctrlText (_pan get "name"));
                private _tbl = (["nets", _side] call _rad) select { (_x select 1) isNotEqualTo _k };
                if ((count _tbl) >= 24) exitWith { ("Liste de veille pleine : 24 réseaux au plus. Retirez-en un d'abord.") call _say; };
                _tbl pushBack [_name, _k, cbChecked (_pan get "chk")];
                ["setNets", [_side, _tbl]] call _rad;
                (format [("Réseau %1 ajouté à la veille du camp %2") call _rTr, [_name, _k] select (_name isEqualTo ""), _side call _sideName]) call _say;
            };
            case "netDel": {
                if !(["canEdit", _side] call _rad) exitWith { ("Liste de veille réservée au chef de poste et à l'administrateur Zeus (réglage « Droits de la salle TOC »)") call _say; };
                private _ml = _pan get "mon";
                private _k = _ml lnbData [lnbCurSelRow _ml, 0];
                if (_k isEqualTo "") exitWith { ("Choisissez d'abord un réseau dans la liste de veille") call _say; };
                ["setNets", [_side, (["nets", _side] call _rad) select { (_x select 1) isNotEqualTo _k }]] call _rad;
                (format [("Réseau %1 retiré de la veille") call _rTr, _k]) call _say;
            };
            case "all": {
                private _on = (cbChecked (_pan get "cAll")) && {call _isZeus};
                _pan set ["all", _on];
                if (_on) then { ["sub", "*"] call _rad; };
                (["Liste de veille du camp appliquée", "Tout est surveillé : tous les réseaux, sans tenir compte de la liste de veille"] select _on) call _say;
            };
            case "side": {
                private _c = _pan get "cSide";
                private _sd = _c lbData (lbCurSel _c);
                if (!(_sd in _sides) || {_sd isEqualTo _side}) exitWith {};
                if (call _isZeus) then { ["sub", "*"] call _rad; };
                _pan set ["side", _sd];
                _pan set ["only", ""];
            };
            case "filter": {
                private _c = _pan get "fil";
                _pan set ["only", _c lbData (lbCurSel _c)];
            };
        };
        ["refresh", true] call comspec_atak_native_fnc_tocRadioUi;
        true
    };
    case "dock": {
        // Console du poste (fn_tocLaptop « layout ») : le panneau prend la place de la carte sur l'onglet RADIO, dans un
        // groupe créé une fois par ouverture de la console, masqué sur les autres onglets.
        _a params [["_d", displayNull], ["_rect", [0, 0, 1, 1]], ["_show", false]];
        if (isNull _d) exitWith { false };
        private _g = _d getVariable ["COMSPEC_ATAK_TocRadioDock", controlNull];
        if (!_show) exitWith { if (!isNull _g) then { _g ctrlShow false; }; true };
        if (isNull _g || {(_pan getOrDefault ["grp", controlNull]) isNotEqualTo _g}) then {
            if (!isNull _g) then { ctrlDelete _g; };
            _g = _d ctrlCreate ["RscControlsGroupNoScrollbars", -1];
            _d setVariable ["COMSPEC_ATAK_TocRadioDock", _g];
            _g ctrlSetPosition _rect;
            _g ctrlCommit 0;
            private _pad = safeZoneH * 0.006;
            ["mount", [_d, [_pad, _pad, (_rect select 2) - 2 * _pad, (_rect select 3) - 2 * _pad], str side group player, false, _g]] call comspec_atak_native_fnc_tocRadioUi;
        };
        _g ctrlSetPosition _rect;
        _g ctrlCommit 0;
        _g ctrlShow true;
        ["refresh", true] call comspec_atak_native_fnc_tocRadioUi;
        true
    };
    // ================= ENCADRÉ « QUI PARLE » =================
    case "initClient": {
        if (!hasInterface) exitWith { false };
        ["comspec_atak_native_tocRadioChanged", { ["changed", _this] call comspec_atak_native_fnc_tocRadioUi; }] call CBA_fnc_addEventHandler;
        // Ouverture et fermeture du Zeus : l'encadré suit le réglage du Zeus.
        ["featureCamera", { ["hudSync"] call comspec_atak_native_fnc_tocRadioUi; }] call CBA_fnc_addPlayerEventHandler;
        [{ ["hudSync"] call comspec_atak_native_fnc_tocRadioUi; }, [], 8] call comspec_atak_native_fnc_waitAndExec;
        true
    };
    case "changed": {
        if (!isNull (_pan getOrDefault ["d", displayNull])) then { ["refresh", false] call comspec_atak_native_fnc_tocRadioUi; };
        ["hudDraw"] call comspec_atak_native_fnc_tocRadioUi;
        true
    };
    case "hudWant": {
        // 0 : pas d'encadré ; 1 : mes réseaux ; 2 : Zeus, tous les camps.
        if (!isNull (findDisplay 312) && {!isNull (getAssignedCuratorLogic player)}) exitWith { [0, 2] select ((missionNamespace getVariable ["comspec_atak_native_toc_radio_hud_zeus", true]) isEqualTo true) };
        parseNumber ((missionNamespace getVariable ["comspec_atak_native_toc_radio_hud", false]) isEqualTo true)
    };
    case "hudSync": {
        private _want = ["hudWant"] call comspec_atak_native_fnc_tocRadioUi;
        if (_want > 0) then { ["sub", ["", "*"] select (_want isEqualTo 2)] call _rad; };
        ["hudDraw"] call comspec_atak_native_fnc_tocRadioUi;
        true
    };
    case "hudHide": {
        if (uiNamespace getVariable ["COMSPEC_ATAK_TocRadioHudUp", false]) then {
            uiNamespace setVariable ["COMSPEC_ATAK_TocRadioHudUp", false];
            ("COMSPEC_ATAK_TocRadioHud" call BIS_fnc_rscLayer) cutFadeOut 0;
        };
        private _h = missionNamespace getVariable ["COMSPEC_ATAK_TocRadioHudPfh", -1];
        if (_h >= 0) then { [_h] call CBA_fnc_removePerFrameHandler; missionNamespace setVariable ["COMSPEC_ATAK_TocRadioHudPfh", -1]; };
        true
    };
    case "hudDraw": {
        private _want = ["hudWant"] call comspec_atak_native_fnc_tocRadioUi;
        private _lines = [];
        if (_want > 0 && {!visibleMap} && {!isNull (findDisplay 46)}) then {
            {
                private _sd = _x;
                private _mdl = ["model", [_sd, _want isEqualTo 2, "", _want isEqualTo 1]] call _rad;
                private _now = _mdl get "now";
                {
                    _x params ["", "_cs", "", "", "_k", "", "", "", "", "_t0", "", "_hit", "_net"];
                    private _isF = ["isFreq", _k] call _rad;
                    _lines pushBack format ["<t color='@%1'>●</t> <t font='RobotoCondensedBold' color='@text'>%2</t>%3 <t font='EtelkaMonospacePro' color='@%1'>%4</t> <t color='@muted'>%5</t>",
                        ["accent", "red"] select _hit, [_cs] call _rEsc, ["", " <t color='@text2'>" + ([_net call _rTr] call _rEsc) + "</t>"] select (!_isF || {_net isNotEqualTo (_k + " MHz")}),
                        ["", _k] select _isF, ["dur", _now - _t0] call _rad];
                } forEach (_mdl get "live");
            } forEach ([[str side group player], _sides] select (_want isEqualTo 2));
        };
        if (_lines isEqualTo []) exitWith { ["hudHide"] call comspec_atak_native_fnc_tocRadioUi };
        private _more = (count _lines) - 5;
        _lines = _lines select [0, 5];
        if (_more > 0) then { _lines pushBack format ["<t color='@muted'>%1</t>", format [("+ %1 autres") call _rTr, _more]]; };
        if !(uiNamespace getVariable ["COMSPEC_ATAK_TocRadioHudUp", false]) then {
            uiNamespace setVariable ["COMSPEC_ATAK_TocRadioHudUp", true];
            ("COMSPEC_ATAK_TocRadioHud" call BIS_fnc_rscLayer) cutRsc ["COMSPEC_RscTitleTocRadioHud", "PLAIN", 0, false];
        };
        private _d = uiNamespace getVariable ["COMSPEC_ATAK_TocRadioHudDisplay", displayNull];
        if (isNull _d) exitWith { false };
        private _lh = 0.026 * safeZoneH;
        private _w = 0.2 * safeZoneW;
        private _h = _lh * (count _lines) + 0.012 * safeZoneH;
        private _x0 = safeZoneX + safeZoneW - _w - 0.012 * safeZoneW;
        private _y0 = safeZoneY + 0.3 * safeZoneH;
        { (_d displayCtrl (_x select 0)) ctrlSetPosition (_x select 1); (_d displayCtrl (_x select 0)) ctrlCommit 0; } forEach [[7781, [_x0, _y0, _w, _h]], [7782, [_x0, _y0, 0.0025 * safeZoneW, _h]], [7783, [_x0 + 0.006 * safeZoneW, _y0 + 0.006 * safeZoneH, _w - 0.008 * safeZoneW, _h]]];
        (_d displayCtrl 7783) ctrlSetStructuredText parseText (((_lines apply { format ["<t size='0.95'>%1</t>", _x] }) joinString "<br/>") call _rInk);
        // Durées : 2 fois par seconde tant que quelqu'un émet.
        if ((missionNamespace getVariable ["COMSPEC_ATAK_TocRadioHudPfh", -1]) < 0) then {
            missionNamespace setVariable ["COMSPEC_ATAK_TocRadioHudPfh", [{ ["hudDraw"] call comspec_atak_native_fnc_tocRadioUi; }, 0.5] call CBA_fnc_addPerFrameHandler];
        };
        true
    };
    default { false };
};
