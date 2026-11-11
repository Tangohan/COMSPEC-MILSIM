/*
    Fenêtre « Réponses de l'IA » (client du maître du jeu, dialog COMSPEC_SSE_IarpAnswers, dialogs\iarp_answers.hpp).
    Mode simple par défaut : à gauche les questions du guide (même source que l'onglet GUIDE des joueurs, fn_iarpData
    « guide ») par thème avec recherche ; à droite la question, « Réponse automatique » ou « Ma réponse », le texte (une
    réponse par ligne), « Insérer une variable » et ce que dira l'IA ; en haut ce que cette IA peut évoquer.
    « Options avancées » (replié) : thèmes interdits, réponse selon la situation, ordre, renseignement révélé, repère sur
    la carte, questions perso (déclencheurs). Format des réponses et règles : fn_iarpAnswers (inchangés). Rien n'est
    appliqué avant « Enregistrer ».
      open ["workshop"]              atelier IA ouvert (Eden ou Zeus) : édite le profil de l'atelier, repris par son bouton
                                     Appliquer (Eden : attribut de l'unité, enregistré dans la mission)
      open ["units", [ia…], parent]  Zeus (menu ZEN, fenêtre « IA de la zone ») : profil de la première IA lu sur le serveur,
                                     appliqué aux IA données (et à leur groupe si coché) par profileTweak ; en Eden (fenêtre
                                     « IA de la zone » de l'éditeur), écrit dans l'attribut « Profil IA RP » de chaque unité
      onLoad · onUnload · loaded [ia, profil texte, nom] (réponse du serveur, fn_iarpClient « profile »)
      list · pick · mode · sit · store · frame · add · remove · apply · advanced [ouvert] · vars · varPick · help [clé]
    Aucune alerte Arma : l'état s'affiche dans la fenêtre (ligne d'état en bas, à côté d'« Enregistrer »).
*/
params [["_op", ""], ["_a", []]];
if (!hasInterface) exitWith { false };
disableSerialization;
private _d = uiNamespace getVariable ["comspec_sse_iarpAnsDisplay", displayNull];
private _st = uiNamespace getVariable ["comspec_sse_iarpAnsState", createHashMap];
uiNamespace setVariable ["comspec_sse_iarpAnsState", _st];
private _nl = toString [10];
private _L = { localize ("STR_COMSPEC_IARP_A_" + _this) };
private _ctl = { _d displayCtrl _this };
private _esc = { ["esc", _this] call comspec_sse_fnc_iarpAnswers };
private _fr = language isEqualTo "French";
// Le profil garde « auto » tant que le maître du jeu ne choisit rien : la fenêtre montre le niveau qui en résulte.
private _evokes = ["none", "known", "free"];
private _themesBan = ["themes"] call comspec_sse_fnc_iarpAnswers;
private _sits = ["*", "calm", "fear", "threat", "confront", "first", "again", "accept", "refuse"];
// Variables proposées par « Insérer une variable » : [nom anglais, nom français] (fn_iarpAnswers, alias).
private _varNames = [["name", "nom"], ["first", "prenom"], ["city", "ville"], ["job", "metier"], ["employer", "employeur"], ["age", "age"],
    ["family", "famille"], ["address", "adresse"], ["travel", "motif"], ["contact", "contact"], ["phone", "telephone"], ["nation", "nationalite"],
    ["player", "joueur"], ["time", "heure"], ["here", "lieu"], ["sir", "monsieur"]];
private _adv = uiNamespace getVariable ["comspec_sse_iarpAnsAdv", false];
private _status = { params ["_t", ["_kind", "info"]]; private _c = 94802 call _ctl; _c ctrlSetText _t; _c ctrlSetTextColor ([[0.62, 0.68, 0.64, 1], [0.45, 0.8, 0.5, 1], [0.95, 0.72, 0.28, 1]] select ((["info", "ok", "warn"] find _kind) max 0)); };
// Questions du guide : [clé, thème, [exemples fr…], [exemples en…]] (sans oui / non ni commandes /me).
private _questions = {
    private _out = [];
    {
        _x params ["_th", "_entries"];
        if !(_th in ["answer", "rp"]) then {
            { _x params ["_i", ["_ex", []]]; if ((_i select [0, 1]) isNotEqualTo "_") then { _out pushBack [_i, _th, _ex apply { _x param [0, ""] }, _ex apply { _x param [1, _x param [0, ""]] }]; }; } forEach _entries;
        };
    } forEach (["guide"] call comspec_sse_fnc_iarpData);
    _out
};
private _themeName = { params ["_th"]; private _k = format ["STR_COMSPEC_IARP_G_%1_T", _th]; [_th, localize _k] select (isLocalized _k) };
// Entrée en cours d'édition (copie de travail) : [clé, libellé, déclencheurs, ordre, variantes, palier, lieu].
private _blank = { params ["_k"]; [_k, "", [], "random", [], 0, ""] };
private _isCustom = { params ["_k"]; (_k select [0, 1]) isEqualTo "c" && {(parseNumber (_k select [1])) > 0} };
private _list = { _st getOrDefault ["answers", []] };
private _isPerso = {
    private _k = (_st getOrDefault ["entry", [""]]) param [0, ""];
    _k isNotEqualTo "" && {([_k] call _isCustom) || {(lbCurSel (94831 call _ctl)) isEqualTo 1}}
};

// Rendu d'un texte avec des valeurs d'exemple (faits de l'histoire, nom, joueur, heure, lieu) : [texte, [inconnues]].
private _render = {
    params ["_t"];
    private _p = _st getOrDefault ["profile", createHashMap];
    private _facts = ["facts", _p] call comspec_sse_fnc_iarpProfile;
    private _name = _p getOrDefault ["name", ""];
    if (_name isEqualTo "") then { _name = _st getOrDefault ["name", ""]; };
    if (_name isEqualTo "") then { _name = "Nikos KARRAS"; };
    _facts set ["name", _name];
    private _parts = _name splitString " ";
    private _fi = _parts findIf { (toUpper _x) isNotEqualTo _x };
    _facts set ["first", if (_fi >= 0) then { _parts select _fi } else { _parts param [0, ""] }];
    _facts set ["player", name player];
    _facts set ["time", [daytime, "HH:MM"] call BIS_fnc_timeToString];
    _facts set ["sir", ["sir", "monsieur"] select _fr];
    private _pos = if (!isNull curatorCamera) then { getPosATL curatorCamera } else { if (is3DEN) then { getPosATL get3DENCamera } else { getPosATL player } };
    private _loc = (nearestLocations [_pos, ["NameCityCapital", "NameCity", "NameVillage", "NameLocal"], 3000]) param [0, locationNull];
    _facts set ["here", [text _loc, worldName] select (isNull _loc || {(text _loc) isEqualTo ""})];
    _facts set ["know", ((["knowTiers", _p getOrDefault ["know", ""]] call comspec_sse_fnc_iarpProfile) select 0) param [1, ""]];
    [[["vars", _t] call comspec_sse_fnc_iarpAnswers, _facts, ["en", "fr"] select _fr] call comspec_sse_fnc_iarpFill, ["unknownVars", _t] call comspec_sse_fnc_iarpAnswers]
};
// Contrôles -> entrée de travail (situation en cours) -> liste des réponses.
private _store = {
    private _e = _st getOrDefault ["entry", []];
    if (_e isEqualTo []) exitWith {};
    private _k = _e select 0;
    private _custom = [_k] call _isCustom;
    if (_custom) then {
        _e set [1, trim (ctrlText (94833 call _ctl))];
        _e set [2, ((ctrlText (94834 call _ctl)) splitString (";," + _nl)) apply { trim _x } select { _x isNotEqualTo "" }];
    };
    _e set [3, ["random", "seq"] select ((lbCurSel (94832 call _ctl)) max 0)];
    _e set [5, (lbCurSel (94837 call _ctl)) max 0];
    _e set [6, trim (ctrlText (94838 call _ctl))];
    private _sit = _st getOrDefault ["sit", "*"];
    private _txt = ((ctrlText (94836 call _ctl)) splitString _nl) apply { trim _x } select { _x isNotEqualTo "" };
    private _vs = (_e select 4) select { (_x select 0) isNotEqualTo _sit };
    if (_txt isNotEqualTo []) then { _vs pushBack [_sit, _txt]; };
    _e set [4, _vs];
    // « Réponse automatique » : l'entrée sort de la liste (ses textes restent dans la fenêtre si l'on revient à « Ma réponse »).
    private _l = (call _list) select { (_x select 0) isNotEqualTo _k };
    if (call _isPerso) then { _l = ["set", [_l, _e]] call comspec_sse_fnc_iarpAnswers; };
    _st set ["answers", _l];
    _st set ["dirty", true];
};
private _sitLabels = {
    private _e = _st getOrDefault ["entry", []];
    private _c = 94835 call _ctl;
    private _keep = lbCurSel _c;
    lbClear _c;
    {
        private _s = _x;
        private _n = count ((((_e param [4, []]) select { (_x select 0) isEqualTo _s }) param [0, ["", []]]) select 1);
        _c lbAdd ((("Sit_" + (["all", _s] select (_s isNotEqualTo "*"))) call _L) + (["", format ["  (%1)", _n]] select (_n > 0)));
    } forEach _sits;
    _c lbSetCurSel (_keep max 0);
};
// Réglages avancés en place : thèmes interdits, puis, pour la question choisie, situations, ordre, renseignement, repère.
private _advCount = {
    private _e = _st getOrDefault ["entry", []];
    private _n = count (_st getOrDefault ["banned", []]);
    if (_e isNotEqualTo [] && {call _isPerso}) then {
        _n = _n + count ((_e param [4, []]) select { (_x select 0) isNotEqualTo "*" });
        if ((_e param [3, "random"]) isEqualTo "seq") then { _n = _n + 1; };
        if ((_e param [5, 0]) > 0) then { _n = _n + 1; };
        if ((_e param [6, ""]) isNotEqualTo "") then { _n = _n + 1; };
    };
    _n
};
private _advLabel = {
    private _n = call _advCount;
    (94870 call _ctl) ctrlSetText (if (_adv) then { "AdvHide" call _L } else { if (_n > 0) then { format [(["AdvN", "AdvN1"] select (_n isEqualTo 1)) call _L, _n] } else { "Adv" call _L } });
};
private _preview = {
    if (isNull _d) exitWith {};
    private _c = 94839 call _ctl;
    if !(call _isPerso) exitWith { _c ctrlSetStructuredText parseText ""; };
    private _e = _st getOrDefault ["entry", [""]];
    private _txt = ((ctrlText (94836 call _ctl)) splitString _nl) apply { trim _x } select { _x isNotEqualTo "" };
    private _warn = [];
    if (_txt isEqualTo []) then { _warn pushBack ("PrevEmpty" call _L); };
    if ([_e param [0, ""]] call _isCustom && {(_e param [2, []]) isEqualTo []}) then { _warn pushBack ("PrevNoTrig" call _L); };
    private _unk = [];
    { { _unk pushBackUnique _x; } forEach (["unknownVars", _x] call comspec_sse_fnc_iarpAnswers); } forEach _txt;
    if (_unk isNotEqualTo []) then { _warn pushBack format ["PrevUnknown" call _L, (_unk apply { "{" + _x + "}" }) joinString " "]; };
    private _top = (_st getOrDefault ["topics", createHashMap]) getOrDefault [_e param [0, ""], ""];
    if (_top isNotEqualTo "" && {_top in (_st getOrDefault ["banned", []])}) then { _warn pushBack ("PrevBanned" call _L); };
    // Autant de répliques que la place le permet (jamais de texte coupé) : les avertissements restent visibles.
    private _h = (ctrlPosition _c) select 3;
    private _n = (count _txt) min 4;
    private _fill = {
        private _out = [];
        { _out pushBack format ["<t color='#9EE6FF'>« %1 »</t>", (([_x] call _render) select 0) call _esc]; } forEach (_txt select [0, _n]);
        _out set [0, format ["<t color='#9EAFA6'>%1</t> %2", "Preview" call _L, _out param [0, ""]]];
        if ((count _txt) > _n) then { _out pushBack format ["<t color='#9EAFA6'>%1</t>", format ["PrevMore" call _L, (count _txt) - _n]]; };
        { _out pushBack format ["<t color='#F2B847'>%1</t>", _x call _esc]; } forEach _warn;
        _c ctrlSetStructuredText parseText (_out joinString "<br/>");
    };
    call _fill;
    while { _n > 1 && {(ctrlTextHeight _c) > _h} } do { _n = _n - 1; call _fill; };
};
// Texte d'une ligne de la liste : coupé avec « … » s'il passerait sous son état (« Ma réponse », « Interdit »).
private _rowFit = {
    params ["_text", "_tag"];
    private _lb = 94821 call _ctl;
    private _pr = 94899 call _ctl;
    private _gw = (([] call comspec_sse_fnc_uiGrid) select 2);
    private _avail = ((ctrlPosition _lb) select 2) - 1.4 * _gw;
    if (_tag isNotEqualTo "") then { _pr ctrlSetText _tag; _avail = _avail - (ctrlTextWidth _pr) - 0.6 * _gw; };
    private _t = "   " + _text;
    _pr ctrlSetText _t;
    if ((ctrlTextWidth _pr) <= _avail) exitWith { [_t, ""] };
    private _n = count _t;
    while { _n > 6 && {(ctrlTextWidth _pr) > _avail} } do { _n = _n - 2; _pr ctrlSetText ((_t select [0, _n]) + "…"); };
    [ctrlText _pr, _text]
};
private _rowTag = {
    params ["_k", "_ans"];
    private _n = ["count", ["get", [_ans, _k]] call comspec_sse_fnc_iarpAnswers] call comspec_sse_fnc_iarpAnswers;
    private _ban = ((_st getOrDefault ["topics", createHashMap]) getOrDefault [_k, ""]) in (_st getOrDefault ["banned", []]);
    if (_ban) exitWith { ["BannedTag" call _L, [0.95, 0.72, 0.28, 1]] };
    if (_n > 0) exitWith { ["TagPerso" call _L, [0.45, 0.8, 0.5, 1]] };
    ["", [0.62, 0.68, 0.64, 1]]
};
private _rowSet = {
    params ["_i", "_k", "_text"];
    private _lb = 94821 call _ctl;
    ([_k, call _list] call _rowTag) params ["_tag", "_col"];
    ([_text, _tag] call _rowFit) params ["_shown", "_tip"];
    _lb lbSetText [_i, _shown];
    _lb lbSetTooltip [_i, _tip];
    _lb lbSetTextRight [_i, _tag];
    _lb lbSetColorRight [_i, _col];
};
// État d'une ligne de la liste après une modification, sans redessiner toute la liste.
private _rowState = {
    private _lb = 94821 call _ctl;
    private _e = _st getOrDefault ["entry", [""]];
    private _k = _e param [0, ""];
    for "_i" from 0 to ((lbSize _lb) - 1) do {
        if ((_lb lbData _i) isEqualTo _k) exitWith {
            private _text = if ([_k] call _isCustom) then { [_e param [1, ""], "CustomNew" call _L] select ((_e param [1, ""]) isEqualTo "") } else { (_st getOrDefault ["rowText", createHashMap]) getOrDefault [_k, _k] };
            [_i, _k, _text] call _rowSet;
        };
    };
};
// Positions selon le mode (simple ou avancé), la question (du guide ou perso) et la réponse (automatique ou la mienne).
private _layout = {
    if (isNull _d) exitWith {};
    ([] call comspec_sse_fnc_uiGrid) params ["_gx", "_gy", "_gw", "_gh"];
    private _put = {
        params ["_i", "_px", "_py", "_pw", "_ph", ["_on", true]];
        private _c = _i call _ctl;
        _c ctrlShow _on;
        if (_on) then { _c ctrlSetPosition [_gx + _px * _gw, _gy + _py * _gh, _pw * _gw, _ph * _gh]; _c ctrlCommit 0; };
    };
    private _tw = { (ctrlTextWidth (_this call _ctl)) / _gw + 0.4 };
    private _e = _st getOrDefault ["entry", []];
    private _has = _e isNotEqualTo [];
    private _custom = [_e param [0, ""]] call _isCustom;
    private _perso = call _isPerso;
    // Haut : ce que cette IA peut évoquer ; options avancées : thèmes interdits.
    private _lw = 94806 call _tw;
    [94806, 1.3, 2.3, _lw, 0.9] call _put;
    [94810, 1.3 + _lw, 2.25, 9, 1] call _put;
    [94818, 1.7 + _lw + 9, 2.3, 37.0 - _lw - 9, 0.9] call _put;
    private _bx = 1.3;
    private _bw = 94807 call _tw;
    [94807, _bx, 3.5, _bw, 0.9, _adv] call _put;
    _bx = _bx + _bw + 0.2;
    for "_i" from 0 to 5 do {
        private _w = (94861 + _i) call _tw;
        [94811 + _i, _bx, 3.5, 0.9, 0.9, _adv] call _put;
        [94861 + _i, _bx + 0.95, 3.5, _w, 0.9, _adv] call _put;
        _bx = _bx + 0.95 + _w + 0.5;
    };
    private _top = [3.75, 4.85] select _adv;
    [94803, 1.3, _top - 0.3, 37.4, 0.06] call _put;
    // Gauche : recherche, questions ; « Ajouter ma propre question » dans les options avancées.
    private _sw = 94817 call _tw;
    [94817, 1.3, _top + 0.05, _sw, 0.9] call _put;
    [94820, 1.4 + _sw, _top, 13.9 - _sw, 1] call _put;
    private _lbBot = [24.3, 23.0] select _adv;
    [94821, 1.3, _top + 1.15, 14, _lbBot - _top - 1.15] call _put;
    [94822, 1.3, 23.2, 14, 1.1, _adv] call _put;
    [94804, 15.5, _top, 0.06, 24.3 - _top] call _put;
    // Droite : question, choix, texte, aperçu ; « Options avancées » en bas, au-dessus de la barre.
    private _y = _top;
    [94830, 15.8, _y, 22.9, 1.9, _has] call _put;
    _y = _y + 2.05;
    [94831, 15.8, _y, 16, 1.1, _has && {!_custom}] call _put;
    if (_custom) then {
        private _l = (94843 call _tw) max (94844 call _tw);
        [94843, 15.8, _y, _l, 1] call _put;
        [94833, 15.9 + _l, _y, 17.6 - _l, 1] call _put;
        [94842, 33.7, _y, 5, 1] call _put;
        _y = _y + 1.15;
        [94844, 15.8, _y, _l, 1] call _put;
        [94834, 15.9 + _l, _y, 22.8 - _l, 1] call _put;
    } else {
        { [_x, 0, 0, 0, 0, false] call _put; } forEach [94843, 94833, 94842, 94844, 94834];
    };
    _y = _y + 1.3;
    private _ty = [21.9, 17.45] select _adv;
    if (_perso) then {
        [94846, 15.8, _y + 0.05, 15.2, 0.9] call _put;
        [94847, 31.2, _y, 7.5, 1] call _put;
        _y = _y + 1.15;
        private _pH = [3.4, 3.2] select _adv;
        private _tH = (_ty - 0.3 - _y) - _pH - 0.15 - ([1.0, 0] select _adv);
        [94836, 15.8, _y, 22.9, _tH] call _put;
        _y = _y + _tH + 0.1;
        [94848, 15.8, _y, 22.9, 0.9, !_adv] call _put;
        if (!_adv) then { _y = _y + 1.0; };
        [94805, 15.8, _y + 0.05, 22.9, _pH] call _put;
        [94839, 15.9, _y + 0.1, 22.7, _pH - 0.1] call _put;
        [94845, 0, 0, 0, 0, false] call _put;
    } else {
        { [_x, 0, 0, 0, 0, false] call _put; } forEach [94846, 94847, 94836, 94848, 94805, 94839, 94880, 94881];
        [94845, 15.8, _y, 22.9, 2.2, _has] call _put;
    };
    [94870, 15.8, _ty, 22.9, 1.1, _has] call _put;
    // Barre du bas : « Tout son groupe » collé à « Enregistrer », la ligne d'état prend le reste.
    private _grp = (_st getOrDefault ["mode", "workshop"]) isNotEqualTo "workshop";
    private _gl = 94852 call _tw;
    private _gx0 = [31.2, 31.2 - _gl] select _grp;
    [94852, _gx0, 23.3, _gl, 0.9, _grp] call _put;
    [94851, _gx0 - 0.95, 23.3, 0.9, 0.9, _grp] call _put;
    [94802, 15.8, 23.3, (_gx0 - ([0.3, 1.25] select _grp)) - 15.8, 0.9] call _put;
    // Options avancées de la question : deux lignes de réglages et une phrase d'aide.
    private _on = _adv && _has;
    private _l1 = (94871 call _tw) max (94873 call _tw);
    private _l2 = (94872 call _tw) max (94874 call _tw);
    private _cw = 7.6;
    private _x2 = 15.8 + _l1 + 0.1 + _cw + 0.3;
    {
        _x params ["_lab", "_c1", "_lab2", "_c2", "_ry"];
        [_lab, 15.8, _ry, _l1, 1, _on] call _put;
        [_c1, 15.9 + _l1, _ry, _cw, 1, _on] call _put;
        [_lab2, _x2, _ry, _l2, 1, _on] call _put;
        [_c2, _x2 + _l2 + 0.1, _ry, 38.7 - (_x2 + _l2 + 0.1), 1, _on] call _put;
        { (_x call _ctl) ctrlEnable _perso; } forEach [_c1, _c2];
    } forEach [[94871, 94835, 94872, 94832, 18.75], [94873, 94837, 94874, 94838, 19.9]];
    [94840, 15.8, 21.1, 22.9, 1.9, _on] call _put;
    call _advLabel;
};
// Entrée -> contrôles. Pendant ce temps (« hold ») les évènements des contrôles ne réécrivent rien : un lbSetCurSel ou un
// ctrlSetText déclenche leur gestionnaire tout de suite.
private _load = {
    _st set ["hold", true];
    private _e = _st getOrDefault ["entry", []];
    private _k = _e param [0, ""];
    private _custom = [_k] call _isCustom;
    private _inList = ((call _list) findIf { (_x select 0) isEqualTo _k }) >= 0;
    (94831 call _ctl) lbSetCurSel (parseNumber (_inList || {_custom}));
    (94832 call _ctl) lbSetCurSel ((["random", "seq"] find (_e param [3, "random"])) max 0);
    (94837 call _ctl) lbSetCurSel ((_e param [5, 0]) min 3);
    (94838 call _ctl) ctrlSetText (_e param [6, ""]);
    (94833 call _ctl) ctrlSetText (_e param [1, ""]);
    (94834 call _ctl) ctrlSetText ((_e param [2, []]) joinString "; ");
    _st set ["sit", "*"];
    (94835 call _ctl) lbSetCurSel 0;
    call _sitLabels;
    private _txt = (((_e param [4, []]) select { (_x select 0) isEqualTo "*" }) param [0, ["", []]]) select 1;
    (94836 call _ctl) ctrlSetText (_txt joinString _nl);
    { (_x call _ctl) ctrlShow false; } forEach [94880, 94881];
    // Question : autres façons de la poser (guide) ; question perso : son nom.
    private _q = (_st getOrDefault ["questions", []]) select { (_x select 0) isEqualTo _k };
    private _head = if (_custom) then {
        format ["<t font='RobotoCondensedBold' size='1.05' color='#E6EDE6'>%1</t><br/><t size='0.8' color='#9EAFA6'>%2</t>", ([_e param [1, ""], "CustomNew" call _L] select ((_e param [1, ""]) isEqualTo "")) call _esc, "CustomHead" call _L]
    } else {
        (_q param [0, [_k, "", [], []]]) params ["", "_th", "_exFr", "_exEn"];
        private _ex = [_exEn, _exFr] select _fr;
        private _also = [];
        { if ((count ((_also + [_x]) joinString " · ")) <= 70) then { _also pushBack _x; }; } forEach (_ex select [1]);
        format ["<t font='RobotoCondensedBold' size='1.05' color='#E6EDE6'>%1</t>   <t size='0.8' color='#73CC80'>%2</t><br/><t size='0.8' color='#9EAFA6'>%3</t>",
            (_ex param [0, _k]) call _esc, ([_th] call _themeName) call _esc, ([format ["Examples" call _L, _also joinString " · "], ""] select (_also isEqualTo [])) call _esc]
    };
    (94830 call _ctl) ctrlSetStructuredText parseText _head;
    (94845 call _ctl) ctrlSetStructuredText parseText format ["<t color='#9EAFA6'>%1</t>", ("AutoNote" call _L) call _esc];
    _st set ["hold", false];
    call _layout;
    call _preview;
};
private _evokeHelp = {
    private _i = (lbCurSel (94810 call _ctl)) max 0;
    (94818 call _ctl) ctrlSetText (("Evoke_" + (_evokes select _i) + "_D") call _L);
};
private _dirty = { ["Dirty" call _L, "warn"] call _status; };

switch (_op) do {
    case "open": {
        _a params [["_mode", "workshop"], ["_units", []], ["_parent", displayNull]];
        _st set ["mode", _mode];
        _st set ["units", _units select { _x isEqualType objNull && {!isNull _x} }];
        if (_mode isEqualTo "workshop") exitWith {
            private _ws = uiNamespace getVariable ["comspec_sse_iarpWsDisplay", displayNull];
            if (isNull _ws) exitWith { false };
            _st set ["profile", ["current"] call comspec_sse_fnc_iarpWorkshop];
            _ws createDisplay "COMSPEC_SSE_IarpAnswers";
            true
        };
        if ((_st get "units") isEqualTo []) exitWith { ["notice", ["STR_COMSPEC_IARP_N_NoAi"]] call comspec_sse_fnc_iarpClient; false };
        _st set ["profile", createHashMap];
        if (!isNull _d) then { _d closeDisplay 2; };
        if (!isNull _parent) then { _parent createDisplay "COMSPEC_SSE_IarpAnswers"; } else { createDialog "COMSPEC_SSE_IarpAnswers"; };
        true
    };
    case "onLoad": {
        _a params [["_disp", displayNull]];
        uiNamespace setVariable ["comspec_sse_iarpAnsDisplay", _disp];
        _d = _disp;
        private _mode = _st getOrDefault ["mode", "workshop"];
        private _units = _st getOrDefault ["units", []];
        _st set ["questions", call _questions];
        _st set ["topics", ["topics"] call comspec_sse_fnc_iarpData];
        _st set ["entry", []];
        _st set ["drafts", createHashMap];
        _st set ["dirty", false];
        _st set ["hold", true];
        private _c = 94832 call _ctl; lbClear _c; { _c lbAdd (("Order_" + _x) call _L); } forEach ["random", "seq"];
        _c = 94837 call _ctl; lbClear _c; { _c lbAdd (("Tier_" + str _x) call _L); } forEach [0, 1, 2, 3];
        _c = 94810 call _ctl; lbClear _c; { _c lbAdd (("Evoke_" + _x) call _L); } forEach _evokes;
        _st set ["hold", false];
        // Atelier : un seul profil, appliqué par l'atelier ; Zeus / IA de la zone : la case « tout son groupe ».
        private _ws = _mode isEqualTo "workshop";
        private _who = if (_ws) then { "Workshop" call _L } else {
            if ((count _units) isEqualTo 1) then { ["shown", _units select 0] call comspec_sse_fnc_iarpName } else { format [localize "STR_COMSPEC_IARP_W_NUnits", count _units] }
        };
        (94801 call _ctl) ctrlSetText format ["Title" call _L, _who];
        { (_x call _ctl) ctrlShow false; } forEach [94880, 94881];
        call _layout;
        ["help", ["Adv"]] call comspec_sse_fnc_iarpAnswersUi;
        if (_ws || {is3DEN}) then {
            if (!_ws) then {
                private _raw = ((_units select 0) get3DENAttribute "comspec_sse_iarpProfile") param [0, ""];
                _st set ["profile", ["parse", _raw] call comspec_sse_fnc_iarpProfile];
            };
            ["loaded", [objNull, "", ""]] call comspec_sse_fnc_iarpAnswersUi;
        } else {
            ["Loading" call _L] call _status;
            ["send", ["profileGet", [player, _units select 0]]] call comspec_sse_fnc_iarpClient;
        };
    };
    case "onUnload": { uiNamespace setVariable ["comspec_sse_iarpAnsDisplay", displayNull]; };
    case "loaded": {
        if (isNull _d) exitWith {};
        _a params [["_t", objNull], ["_raw", ""], ["_who", ""]];
        // Réponse du serveur : seulement pour la première IA de cette fenêtre.
        if (!isNull _t) then {
            if (_t isNotEqualTo ((_st getOrDefault ["units", []]) param [0, objNull])) exitWith { _st set ["skip", true]; };
            _st set ["profile", ["parse", _raw] call comspec_sse_fnc_iarpProfile];
            // Nom affiché seulement (la vérité du nom est dans « IA de la zone ») : le titre tient sur une ligne.
            private _shown = if (_who isEqualType []) then { _who param [0, ""] } else { _who };
            if (_shown isEqualType "" && {_shown isNotEqualTo ""} && {(count (_st getOrDefault ["units", []])) isEqualTo 1}) then { (94801 call _ctl) ctrlSetText format ["Title" call _L, _shown]; };
        };
        if (_st getOrDefault ["skip", false]) exitWith { _st set ["skip", false]; };
        private _p = ["normalize", _st getOrDefault ["profile", createHashMap]] call comspec_sse_fnc_iarpProfile;
        _st set ["profile", _p];
        _st set ["answers", +(_p get "answers")];
        _st set ["banned", +(_p get "banned")];
        _st set ["evokeAuto", (_p get "evoke") isEqualTo "auto"];
        _st set ["hold", true];
        (94810 call _ctl) lbSetCurSel ((_evokes find ((["frame", _p] call comspec_sse_fnc_iarpAnswers) select 0)) max 0);
        { (_x call _ctl) cbSetChecked ((_themesBan select _forEachIndex) in (_p get "banned")); } forEach [94811, 94812, 94813, 94814, 94815, 94816];
        _st set ["hold", false];
        call _evokeHelp;
        ["list"] call comspec_sse_fnc_iarpAnswersUi;
        private _lb = 94821 call _ctl;
        for "_i" from 0 to ((lbSize _lb) - 1) do { if ((_lb lbData _i) isNotEqualTo "") exitWith { _lb lbSetCurSel _i; }; };
        ["", "info"] call _status;
    };
    case "list": {
        if (isNull _d) exitWith {};
        private _lb = 94821 call _ctl;
        private _q = toLower (trim (ctrlText (94820 call _ctl)));
        private _qn = trim (_q call comspec_sse_fnc_iarpNorm);
        private _cur = (_st getOrDefault ["entry", [""]]) param [0, ""];
        _st set ["busy", true];
        lbClear _lb;
        private _ans = call _list;
        private _texts = createHashMap;
        private _row = {
            params ["_k", "_text"];
            private _i = _lb lbAdd "";
            _lb lbSetData [_i, _k];
            _texts set [_k, _text];
            [_i, _k, _text] call _rowSet;
            if (_k isEqualTo _cur) then { _lb lbSetCurSel _i; };
        };
        private _head = { params ["_t"]; private _i = _lb lbAdd (toUpper _t); _lb lbSetData [_i, ""]; _lb lbSetColor [_i, [0.45, 0.8, 0.5, 1]]; };
        // Mes questions d'abord, puis les thèmes du guide.
        private _cust = _ans select { [_x select 0] call _isCustom };
        private _hit = { params ["_e"]; _q isEqualTo "" || {((toLower format ["%1 %2", _e select 1, (_e select 2) joinString " "]) find _q) >= 0} };
        private _custShown = _cust select { [_x] call _hit };
        if (_custShown isNotEqualTo []) then {
            ["Custom" call _L] call _head;
            { [_x select 0, [_x select 1, "CustomNew" call _L] select ((_x select 1) isEqualTo "")] call _row; } forEach _custShown;
        };
        private _th = "";
        {
            _x params ["_k", "_theme", "_exFr", "_exEn"];
            private _ex = [_exEn, _exFr] select _fr;
            private _hay = (" " + _k + " " + ((_exFr + _exEn) joinString " ")) call comspec_sse_fnc_iarpNorm;
            if (_q isEqualTo "" || {(_hay find _qn) >= 0} || {((toLower _k) find _q) >= 0}) then {
                if (_theme isNotEqualTo _th) then { _th = _theme; [[_theme] call _themeName] call _head; };
                [_k, _ex param [0, _k]] call _row;
            };
        } forEach (_st getOrDefault ["questions", []]);
        _st set ["rowText", _texts];
        _st set ["busy", false];
    };
    case "pick": {
        if (isNull _d || {_st getOrDefault ["busy", false]} || {_st getOrDefault ["hold", false]}) exitWith {};
        private _lb = 94821 call _ctl;
        private _k = _lb lbData (lbCurSel _lb);
        if (_k isEqualTo "") exitWith {};
        private _e = ["get", [call _list, _k]] call comspec_sse_fnc_iarpAnswers;
        // Entrée gardée hors liste (passée en « Réponse automatique » sans fermer) : ses textes reviennent.
        private _kept = _st getOrDefault ["drafts", createHashMap];
        if (_e isEqualTo []) then { _e = _kept getOrDefault [_k, [_k] call _blank]; };
        _e = +_e;
        _kept set [_k, _e];
        _st set ["drafts", _kept];
        _st set ["entry", _e];
        call _load;
    };
    case "mode": {
        if (isNull _d || {(_st getOrDefault ["entry", []]) isEqualTo []} || {_st getOrDefault ["hold", false]}) exitWith {};
        call _store;
        call _layout;
        call _rowState;
        call _preview;
        call _dirty;
        if ((call _isPerso) && {(trim (ctrlText (94836 call _ctl))) isEqualTo ""}) then { ctrlSetFocus (94836 call _ctl); };
    };
    case "sit": {
        if (isNull _d || {_st getOrDefault ["hold", false]}) exitWith {};
        private _e = _st getOrDefault ["entry", []];
        if (_e isEqualTo []) exitWith {};
        private _s = _sits select ((lbCurSel (94835 call _ctl)) max 0);
        _st set ["sit", _s];
        private _txt = (((_e select 4) select { (_x select 0) isEqualTo _s }) param [0, ["", []]]) select 1;
        _st set ["hold", true];
        (94836 call _ctl) ctrlSetText (_txt joinString _nl);
        _st set ["hold", false];
        call _preview;
    };
    case "store": {
        if (isNull _d || {(_st getOrDefault ["entry", []]) isEqualTo []} || {_st getOrDefault ["hold", false]}) exitWith {};
        call _store;
        // Compte des textes par situation, puis l'état de la ligne : jamais la saisie en cours réécrite.
        _st set ["hold", true];
        call _sitLabels;
        _st set ["hold", false];
        call _rowState;
        call _preview;
        call _advLabel;
        call _dirty;
    };
    case "frame": {
        if (isNull _d || {_st getOrDefault ["hold", false]}) exitWith {};
        if ((_a param [0, ""]) isEqualTo "evoke") then { _st set ["evokeAuto", false]; };
        private _b = [];
        { if (cbChecked ((94811 + _forEachIndex) call _ctl)) then { _b pushBack _x; }; } forEach _themesBan;
        _st set ["banned", _b];
        _st set ["dirty", true];
        call _evokeHelp;
        ["list"] call comspec_sse_fnc_iarpAnswersUi;
        call _preview;
        call _advLabel;
        call _dirty;
    };
    case "advanced": {
        if (isNull _d) exitWith {};
        _adv = _a param [0, !_adv];
        uiNamespace setVariable ["comspec_sse_iarpAnsAdv", _adv];
        // Fermées : retour à « Toujours » (les autres situations gardent leurs textes).
        if (!_adv && {(_st getOrDefault ["sit", "*"]) isNotEqualTo "*"}) then {
            _st set ["hold", true];
            (94835 call _ctl) lbSetCurSel 0;
            _st set ["hold", false];
            ["sit"] call comspec_sse_fnc_iarpAnswersUi;
        };
        { (_x call _ctl) ctrlShow false; } forEach [94880, 94881];
        call _layout;
        call _preview;
        ["help", ["Adv"]] call comspec_sse_fnc_iarpAnswersUi;
    };
    case "vars": {
        if (isNull _d) exitWith {};
        private _lb = 94881 call _ctl;
        if (ctrlShown _lb) exitWith { { (_x call _ctl) ctrlShow false; } forEach [94880, 94881]; };
        // Position du curseur dans le texte, gardée pour l'insertion.
        _st set ["caret", ctrlTextSelection (94836 call _ctl)];
        ([] call comspec_sse_fnc_uiGrid) params ["_gx", "_gy", "_gw", "_gh"];
        (ctrlPosition (94847 call _ctl)) params ["", "_by", "", "_bh"];
        private _y = _by + _bh + 0.05 * _gh;
        private _h = (((_gy + 23.0 * _gh) - _y) min (8.6 * _gh)) max (3 * _gh);
        (94880 call _ctl) ctrlSetPosition [_gx + 24.2 * _gw, _y, 14.5 * _gw, _h];
        _lb ctrlSetPosition [_gx + 24.3 * _gw, _y + 0.1 * _gh, 14.3 * _gw, _h - 0.2 * _gh];
        { (_x call _ctl) ctrlCommit 0; (_x call _ctl) ctrlShow true; } forEach [94880, 94881];
        _st set ["varHold", true];
        lbClear _lb;
        {
            _x params ["_en", "_frn"];
            private _tok = "{" + ([_en, _frn] select _fr) + "}";
            private _i = _lb lbAdd format ["%1  %2", _tok, ("V_" + _en) call _L];
            _lb lbSetData [_i, _tok];
            private _v = ([_tok] call _render) select 0;
            if (_v isNotEqualTo "" && {_v isNotEqualTo "…"}) then { _lb lbSetTextRight [_i, _v]; _lb lbSetColorRight [_i, [0.62, 0.86, 1, 1]]; };
        } forEach _varNames;
        _lb lbSetCurSel -1;
        _st set ["varHold", false];
        ctrlSetFocus _lb;
    };
    case "varPick": {
        if (isNull _d || {_st getOrDefault ["varHold", false]}) exitWith {};
        private _lb = 94881 call _ctl;
        private _i = lbCurSel _lb;
        if (_i < 0) exitWith {};
        private _tok = _lb lbData _i;
        private _ed = 94836 call _ctl;
        // Positions du curseur en caractères (toArray), pas en octets : les accents ne décalent pas l'insertion.
        private _t = toArray (ctrlText _ed);
        (_st getOrDefault ["caret", []]) params [["_s", -1], ["_n", 0]];
        if (_s < 0 || {_s > count _t}) then { _s = count _t; _n = 0; };
        _ed ctrlSetText (toString ((_t select [0, _s]) + (toArray _tok) + (_t select [_s + _n])));
        _st set ["caret", [_s + count _tok, 0]];
        { (_x call _ctl) ctrlShow false; } forEach [94880, 94881];
        ctrlSetFocus _ed;
        _ed ctrlSetTextSelection [_s + count _tok, 0];
        ["store"] call comspec_sse_fnc_iarpAnswersUi;
    };
    case "add": {
        if (isNull _d) exitWith {};
        private _k = ["newKey", call _list] call comspec_sse_fnc_iarpAnswers;
        if ((count ((call _list) select { [_x select 0] call _isCustom })) >= 20) exitWith { ["CustomMax" call _L, "warn"] call _status; };
        // Nouvelle question perso : exemple prérempli (mot de passe), à remplacer.
        private _e = [_k, "Mot de passe", ["mot de passe", "password"], "random", [["*", ["Le faucon vole bas."]]], 0, ""];
        if (!_fr) then { _e = [_k, "Password", ["password", "mot de passe"], "random", [["*", ["The falcon flies low."]]], 0, ""]; };
        _st set ["answers", ["set", [call _list, _e]] call comspec_sse_fnc_iarpAnswers];
        _st set ["entry", +_e];
        _st set ["dirty", true];
        (94820 call _ctl) ctrlSetText "";
        ["list"] call comspec_sse_fnc_iarpAnswersUi;
        call _load;
        ["help", ["Custom"]] call comspec_sse_fnc_iarpAnswersUi;
        call _dirty;
    };
    case "remove": {
        if (isNull _d) exitWith {};
        private _k = (_st getOrDefault ["entry", [""]]) param [0, ""];
        if !([_k] call _isCustom) exitWith {};
        _st set ["answers", (call _list) select { (_x select 0) isNotEqualTo _k }];
        (_st getOrDefault ["drafts", createHashMap]) deleteAt _k;
        _st set ["dirty", true];
        _st set ["entry", []];
        ["list"] call comspec_sse_fnc_iarpAnswersUi;
        private _lb = 94821 call _ctl;
        for "_i" from 0 to ((lbSize _lb) - 1) do { if ((_lb lbData _i) isNotEqualTo "") exitWith { _lb lbSetCurSel _i; }; };
        call _dirty;
    };
    case "apply": {
        if (isNull _d) exitWith {};
        if ((_st getOrDefault ["entry", []]) isNotEqualTo []) then { call _store; };
        private _ans = ["normalize", call _list] call comspec_sse_fnc_iarpAnswers;
        private _ev = if (_st getOrDefault ["evokeAuto", false]) then { "auto" } else { _evokes select ((lbCurSel (94810 call _ctl)) max 0) };
        private _ban = _st getOrDefault ["banned", []];
        private _mode = _st getOrDefault ["mode", "workshop"];
        if (_mode isEqualTo "workshop") exitWith {
            ["extra", [_ans, _ev, _ban]] call comspec_sse_fnc_iarpWorkshop;
            _d closeDisplay 1;
        };
        private _units = (_st getOrDefault ["units", []]) select { !isNull _x };
        if (cbChecked (94851 call _ctl)) then {
            private _all = [];
            { { if (!isPlayer _x) then { _all pushBackUnique _x; }; } forEach (units group _x); } forEach _units;
            _units = _all;
        };
        if (_units isEqualTo []) exitWith { ["NoUnits" call _L, "warn"] call _status; };
        if (is3DEN) exitWith {
            // Eden : attribut « Profil IA RP » de chaque unité (enregistré dans la mission).
            {
                private _raw = (_x get3DENAttribute "comspec_sse_iarpProfile") param [0, ""];
                private _p = ["parse", _raw] call comspec_sse_fnc_iarpProfile;
                if ((count _p) isEqualTo 0) then { _p = ["defaults"] call comspec_sse_fnc_iarpProfile; };
                _p set ["answers", _ans]; _p set ["evoke", _ev]; _p set ["banned", _ban];
                _x set3DENAttribute ["comspec_sse_iarpProfile", ["serialize", _p] call comspec_sse_fnc_iarpProfile];
            } forEach _units;
            _st set ["dirty", false];
            [format ["AppliedEden" call _L, count _units], "ok"] call _status;
        };
        ["send", ["profileTweak", [player, _units, [["answers", _ans], ["evoke", _ev], ["banned", _ban]]]]] call comspec_sse_fnc_iarpClient;
        _st set ["dirty", false];
        [format ["Sent" call _L, count _units], "ok"] call _status;
    };
    case "help": {
        if (isNull _d) exitWith {};
        _a params [["_k", "Adv"]];
        (94840 call _ctl) ctrlSetStructuredText parseText format ["<t color='#9EAFA6'>%1</t>", (("H_" + _k) call _L) call _esc];
    };
    case "layout": { call _layout; };
};
true
