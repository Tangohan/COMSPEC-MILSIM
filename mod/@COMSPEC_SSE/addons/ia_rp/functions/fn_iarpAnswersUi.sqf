/*
    Fenêtre « Réponses de l'IA » (client du maître du jeu, dialog COMSPEC_SSE_IarpAnswers, dialogs\iarp_answers.hpp).
    Pour chaque question du guide (même source que l'onglet GUIDE des joueurs, fn_iarpData « guide ») : « Réponse auto »
    ou « Réponse perso » (un ou plusieurs textes, au hasard ou dans l'ordre, selon la situation, avec variables), palier de
    renseignement débloqué et lieu ; questions perso (déclencheurs) ; ce que l'IA peut évoquer et thèmes interdits.
    Format des réponses et règles : fn_iarpAnswers. Rien n'est appliqué avant « Appliquer » (ou « Valider » dans l'atelier).
      open ["workshop"]              atelier IA ouvert (Eden ou Zeus) : édite le profil de l'atelier, repris par son bouton
                                     Appliquer (Eden : attribut de l'unité, enregistré dans la mission)
      open ["units", [ia…], parent]  Zeus (menu ZEN, fenêtre « IA de la zone ») : profil de la première IA lu sur le serveur,
                                     appliqué aux IA données (et à leur groupe si coché) par profileTweak ; en Eden (fenêtre
                                     « IA de la zone » de l'éditeur), écrit dans l'attribut « Profil IA RP » de chaque unité
      onLoad · onUnload · loaded [ia, profil texte, nom] (réponse du serveur, fn_iarpClient « profile »)
      list · pick · sit · store · frame · add · remove · apply · help [clé]
    Aucune alerte Arma : l'état s'affiche dans la fenêtre (ligne d'état en haut à droite).
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
private _evokes = ["auto", "none", "known", "free"];
private _themesBan = ["themes"] call comspec_sse_fnc_iarpAnswers;
private _sits = ["*", "calm", "fear", "threat", "confront", "first", "again", "accept", "refuse"];
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
    // « Réponse auto » : l'entrée sort de la liste (ses textes restent dans la fenêtre si l'on revient à « perso »).
    private _perso = (lbCurSel (94831 call _ctl)) isEqualTo 1;
    private _l = (call _list) select { (_x select 0) isNotEqualTo _k };
    if (_perso || {_custom}) then { _l = ["set", [_l, _e]] call comspec_sse_fnc_iarpAnswers; };
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
private _preview = {
    if (isNull _d) exitWith {};
    private _txt = ((ctrlText (94836 call _ctl)) splitString _nl) apply { trim _x } select { _x isNotEqualTo "" };
    private _perso = (lbCurSel (94831 call _ctl)) isEqualTo 1 || {[(_st getOrDefault ["entry", [""]]) param [0, ""]] call _isCustom};
    private _out = [];
    if (!_perso) then { _out pushBack format ["<t color='#9EAFA6'>%1</t>", "PrevAuto" call _L]; } else {
        if (_txt isEqualTo []) then { _out pushBack format ["<t color='#F2B847'>%1</t>", "PrevEmpty" call _L]; };
        {
            ([_x] call _render) params ["_s", "_unk"];
            _out pushBack format ["<t color='#9EE6FF'>« %1 »</t>", _s call _esc];
            if (_unk isNotEqualTo []) then { _out pushBack format ["<t color='#F2B847' size='0.85'>%1</t>", format ["PrevUnknown" call _L, (_unk apply { "{" + _x + "}" }) joinString " "] call _esc]; };
        } forEach (_txt select [0, 3]);
        if ((count _txt) > 3) then { _out pushBack format ["<t color='#9EAFA6' size='0.85'>%1</t>", format ["PrevMore" call _L, (count _txt) - 3]]; };
    };
    private _top = (_st getOrDefault ["topics", createHashMap]) getOrDefault [(_st getOrDefault ["entry", [""]]) param [0, ""], ""];
    if (_top isNotEqualTo "" && {_top in (_st getOrDefault ["banned", []])}) then { _out pushBack format ["<t color='#F2B847' size='0.85'>%1</t>", "PrevBanned" call _L]; };
    (94839 call _ctl) ctrlSetStructuredText parseText format ["<t size='0.8' color='#7F8A84'>%1</t><br/>%2", "Preview" call _L, _out joinString "<br/>"];
};
// Entrée -> contrôles.
private _load = {
    private _e = _st getOrDefault ["entry", []];
    private _k = _e param [0, ""];
    private _custom = [_k] call _isCustom;
    private _inList = ((call _list) findIf { (_x select 0) isEqualTo _k }) >= 0;
    (94831 call _ctl) lbSetCurSel (parseNumber (_inList || {_custom}));
    (94831 call _ctl) ctrlEnable !_custom;
    (94832 call _ctl) lbSetCurSel ((["random", "seq"] find (_e param [3, "random"])) max 0);
    (94837 call _ctl) lbSetCurSel ((_e param [5, 0]) min 3);
    (94838 call _ctl) ctrlSetText (_e param [6, ""]);
    { (_x call _ctl) ctrlShow _custom; } forEach [94833, 94834, 94843, 94844];
    (94845 call _ctl) ctrlShow !_custom;
    (94833 call _ctl) ctrlSetText (_e param [1, ""]);
    (94834 call _ctl) ctrlSetText ((_e param [2, []]) joinString "; ");
    (94842 call _ctl) ctrlSetText ((["Remove", "RemoveCustom"] select _custom) call _L);
    _st set ["sit", "*"];
    (94835 call _ctl) lbSetCurSel 0;
    call _sitLabels;
    private _txt = (((_e param [4, []]) select { (_x select 0) isEqualTo "*" }) param [0, ["", []]]) select 1;
    (94836 call _ctl) ctrlSetText (_txt joinString _nl);
    // Question : exemples du guide (ce que les joueurs tapent) ; question perso : son libellé.
    private _q = (_st getOrDefault ["questions", []]) select { (_x select 0) isEqualTo _k };
    private _head = if (_custom) then {
        format ["<t font='RobotoCondensedBold' size='1.05' color='#E6EDE6'>%1</t><br/><t size='0.8' color='#9EAFA6'>%2</t>", ([_e param [1, ""], "CustomNew" call _L] select ((_e param [1, ""]) isEqualTo "")) call _esc, "CustomHead" call _L]
    } else {
        (_q param [0, [_k, "", [], []]]) params ["", "_th", "_exFr", "_exEn"];
        private _ex = [_exEn, _exFr] select _fr;
        format ["<t font='RobotoCondensedBold' size='1.05' color='#E6EDE6'>%1</t>   <t size='0.8' color='#73CC80'>%2</t><br/><t size='0.8' color='#9EAFA6'>%3</t>",
            (_ex param [0, _k]) call _esc, ([_th] call _themeName) call _esc, (format ["Examples" call _L, (_ex select [1]) joinString " · "]) call _esc]
    };
    (94830 call _ctl) ctrlSetStructuredText parseText _head;
    (94845 call _ctl) ctrlSetStructuredText parseText format ["<t color='#9EAFA6'>%1</t>", "AutoNote" call _L];
    call _preview;
};
private _frameHelp = {
    private _i = (lbCurSel (94810 call _ctl)) max 0;
    (94818 call _ctl) ctrlSetStructuredText parseText format ["<t color='#9EAFA6'>%1</t>", ("Evoke_" + (_evokes select _i) + "_D") call _L];
};

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
        _st set ["dirty", false];
        private _c = 94831 call _ctl; lbClear _c; { _c lbAdd (("Mode_" + _x) call _L); } forEach ["auto", "perso"];
        _c = 94832 call _ctl; lbClear _c; { _c lbAdd (("Order_" + _x) call _L); } forEach ["random", "seq"];
        _c = 94837 call _ctl; lbClear _c; { _c lbAdd (("Tier_" + str _x) call _L); } forEach [0, 1, 2, 3];
        _c = 94810 call _ctl; lbClear _c; { _c lbAdd (("Evoke_" + _x) call _L); } forEach _evokes;
        // Atelier : un seul profil, appliqué par l'atelier ; Zeus / IA de la zone : la case « tout son groupe ».
        private _ws = _mode isEqualTo "workshop";
        { (_x call _ctl) ctrlShow !_ws; } forEach [94851, 94852];
        (94850 call _ctl) ctrlSetText ((["Apply", "Validate"] select _ws) call _L);
        private _who = if (_ws) then { "Workshop" call _L } else {
            if ((count _units) isEqualTo 1) then { ["shown", _units select 0] call comspec_sse_fnc_iarpName } else { format [localize "STR_COMSPEC_IARP_W_NUnits", count _units] }
        };
        (94801 call _ctl) ctrlSetText format ["Title" call _L, _who];
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
        ["help", ["Intro"]] call comspec_sse_fnc_iarpAnswersUi;
    };
    case "onUnload": { uiNamespace setVariable ["comspec_sse_iarpAnsDisplay", displayNull]; };
    case "loaded": {
        if (isNull _d) exitWith {};
        _a params [["_t", objNull], ["_raw", ""], ["_who", ""]];
        // Réponse du serveur : seulement pour la première IA de cette fenêtre.
        if (!isNull _t) then {
            if (_t isNotEqualTo ((_st getOrDefault ["units", []]) param [0, objNull])) exitWith { _st set ["skip", true]; };
            _st set ["profile", ["parse", _raw] call comspec_sse_fnc_iarpProfile];
            if (_who isNotEqualTo "" && {(count (_st getOrDefault ["units", []])) isEqualTo 1}) then { (94801 call _ctl) ctrlSetText format ["Title" call _L, ["label", _who] call comspec_sse_fnc_iarpName]; };
        };
        if (_st getOrDefault ["skip", false]) exitWith { _st set ["skip", false]; };
        private _p = ["normalize", _st getOrDefault ["profile", createHashMap]] call comspec_sse_fnc_iarpProfile;
        _st set ["profile", _p];
        _st set ["answers", +(_p get "answers")];
        _st set ["banned", +(_p get "banned")];
        (94810 call _ctl) lbSetCurSel ((_evokes find (_p get "evoke")) max 0);
        { (_x call _ctl) cbSetChecked ((_themesBan select _forEachIndex) in (_p get "banned")); } forEach [94811, 94812, 94813, 94814, 94815, 94816];
        call _frameHelp;
        ["list"] call comspec_sse_fnc_iarpAnswersUi;
        private _lb = 94821 call _ctl;
        for "_i" from 0 to ((lbSize _lb) - 1) do { if ((_lb lbData _i) isNotEqualTo "") exitWith { _lb lbSetCurSel _i; }; };
        [format ["Loaded" call _L, count (_st get "answers")], "ok"] call _status;
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
        private _banned = _st getOrDefault ["banned", []];
        private _tops = _st getOrDefault ["topics", createHashMap];
        private _row = {
            params ["_k", "_text"];
            private _e = ["get", [_ans, _k]] call comspec_sse_fnc_iarpAnswers;
            private _n = ["count", _e] call comspec_sse_fnc_iarpAnswers;
            private _i = _lb lbAdd ("   " + _text);
            _lb lbSetData [_i, _k];
            private _ban = (_tops getOrDefault [_k, ""]) in _banned;
            _lb lbSetTextRight [_i, if (_ban) then { "BannedTag" call _L } else { if (_n > 0) then { format ["PersoN" call _L, _n] } else { "Auto" call _L } }];
            _lb lbSetColorRight [_i, [[0.62, 0.68, 0.64, 1], [0.45, 0.8, 0.5, 1], [0.95, 0.72, 0.28, 1]] select ([parseNumber (_n > 0), 2] select _ban)];
            if (_k isEqualTo _cur) then { _lb lbSetCurSel _i; };
        };
        private _head = { params ["_t"]; private _i = _lb lbAdd (toUpper _t); _lb lbSetData [_i, ""]; _lb lbSetColor [_i, [0.45, 0.8, 0.5, 1]]; };
        // Questions perso d'abord, puis les thèmes du guide.
        private _cust = _ans select { [_x select 0] call _isCustom };
        if (_q isEqualTo "" || {(_cust findIf { ((toLower format ["%1 %2", _x select 1, (_x select 2) joinString " "]) find _q) >= 0 }) >= 0}) then {
            ["Custom" call _L] call _head;
            { if (_q isEqualTo "" || {((toLower format ["%1 %2", _x select 1, (_x select 2) joinString " "]) find _q) >= 0}) then { [_x select 0, _x select 1] call _row; }; } forEach _cust;
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
        _st set ["busy", false];
    };
    case "pick": {
        if (isNull _d || {_st getOrDefault ["busy", false]}) exitWith {};
        private _lb = 94821 call _ctl;
        private _k = _lb lbData (lbCurSel _lb);
        if (_k isEqualTo "") exitWith {};
        private _e = ["get", [call _list, _k]] call comspec_sse_fnc_iarpAnswers;
        // Entrée gardée hors liste (passée en « auto » sans fermer) : ses textes reviennent.
        private _kept = _st getOrDefault ["drafts", createHashMap];
        if (_e isEqualTo []) then { _e = _kept getOrDefault [_k, [_k] call _blank]; };
        _e = +_e;
        _kept set [_k, _e];
        _st set ["drafts", _kept];
        _st set ["entry", _e];
        call _load;
    };
    case "sit": {
        if (isNull _d) exitWith {};
        private _e = _st getOrDefault ["entry", []];
        if (_e isEqualTo []) exitWith {};
        private _s = _sits select ((lbCurSel (94835 call _ctl)) max 0);
        _st set ["sit", _s];
        private _txt = (((_e select 4) select { (_x select 0) isEqualTo _s }) param [0, ["", []]]) select 1;
        (94836 call _ctl) ctrlSetText (_txt joinString _nl);
        ["help", ["Sit"]] call comspec_sse_fnc_iarpAnswersUi;
        call _preview;
    };
    case "store": {
        if (isNull _d || {(_st getOrDefault ["entry", []]) isEqualTo []}) exitWith {};
        call _store;
        call _sitLabels;
        // Numéro de la ligne gardé : la liste est redessinée pour son état (« Perso (2) »).
        ["list"] call comspec_sse_fnc_iarpAnswersUi;
        call _preview;
        ["Dirty" call _L, "warn"] call _status;
    };
    case "frame": {
        if (isNull _d) exitWith {};
        private _b = [];
        { if (cbChecked ((94811 + _forEachIndex) call _ctl)) then { _b pushBack _x; }; } forEach _themesBan;
        _st set ["banned", _b];
        _st set ["dirty", true];
        call _frameHelp;
        ["list"] call comspec_sse_fnc_iarpAnswersUi;
        call _preview;
        ["Dirty" call _L, "warn"] call _status;
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
        ["Dirty" call _L, "warn"] call _status;
    };
    case "remove": {
        if (isNull _d) exitWith {};
        private _k = (_st getOrDefault ["entry", [""]]) param [0, ""];
        if (_k isEqualTo "") exitWith {};
        _st set ["answers", (call _list) select { (_x select 0) isNotEqualTo _k }];
        (_st getOrDefault ["drafts", createHashMap]) deleteAt _k;
        _st set ["dirty", true];
        _st set ["entry", [_k] call _blank];
        if ([_k] call _isCustom) then { _st set ["entry", []]; };
        ["list"] call comspec_sse_fnc_iarpAnswersUi;
        if ((_st get "entry") isNotEqualTo []) then { call _load; } else {
            private _lb = 94821 call _ctl;
            for "_i" from 0 to ((lbSize _lb) - 1) do { if ((_lb lbData _i) isNotEqualTo "") exitWith { _lb lbSetCurSel _i; }; };
        };
        ["Dirty" call _L, "warn"] call _status;
    };
    case "apply": {
        if (isNull _d) exitWith {};
        if ((_st getOrDefault ["entry", []]) isNotEqualTo []) then { call _store; };
        private _ans = ["normalize", call _list] call comspec_sse_fnc_iarpAnswers;
        private _ev = _evokes select ((lbCurSel (94810 call _ctl)) max 0);
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
        _a params [["_k", "Intro"]];
        (94840 call _ctl) ctrlSetStructuredText parseText format ["<t font='RobotoCondensedBold' color='#73CC80'>%1</t><br/>%2",
            ("H_" + _k) call _L, ("H_" + _k + "_D") call _L];
    };
};
true
