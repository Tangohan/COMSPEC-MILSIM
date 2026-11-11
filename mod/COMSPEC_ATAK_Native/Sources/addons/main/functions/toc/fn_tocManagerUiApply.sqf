/*
    Fenêtre « Gérer les écrans » du TOC : lecture du jeu et exécution (la fenêtre : fn_tocManagerUi ; les tables et les
    règles sans effet : fn_tocManagerUiData). Tout passe par les chemins existants des écrans (fn_tocManager « setSource »
    et « setView », fn_tocScreen « view », fn_tocHall « do », fn_tocLayout, fn_tocBroadcast, fn_tocRoom) : même effet
    avec un serveur à jour ou en repli sur le poste du joueur. Dans Eden : attributs des objets seulement.
    Fonctions des autres lots, utilisées seulement si elles sont chargées (sinon l'état vide dit pourquoi) :
    comspec_atak_native_fnc_camList (état des caméras fixes), fn_tocAccess (rôle, verrou, retour du serveur
    COMSPEC_ATAK_TocFeedback), fn_tocJournal (journal). Aucun message par-dessus le jeu : chaque mode rend son texte.
    Modes de lecture :
      ["signature"]             texte qui change quand un écran ou une salle change (boucle de la fenêtre)
      ["state", ligne]          état du rendu sur ce poste : [libellé, jeton] (en direct, figée, lointaine, en veille, éteinte)
      ["thumb", ligne]          texture déjà rendue de la dalle ("" : aucune, case sans image)
      ["allowed", ligne]        affichages permis par le format de la dalle
      ["items", ligne]          sources proposées : [[libellé, clé, état "" | "ok" | "off" | "no", genre, détail]...]
      ["values", ligne]         valeurs des champs de l'inspecteur (table), options des listes, champs cachés, textes
      ["mixed", lignes]         champs dont les valeurs diffèrent entre les dalles choisies
      ["refusal", ligne] / ["layoutHelp", clé]   textes d'aide
      ["halls"] / ["hallId", nom] / ["sceneNames", id] / ["presetNames"] / ["links"] / ["log"] / ["logAsk"] / ["feedback"]
    Modes d'écriture (rendent [texte, jeton, délai avant de relire]) :
      ["run", opérations]       exécute le plan de fn_tocManagerUiData « plan »
      ["place", [prêt, ligne]]  pose une source prête ou un réglage copié sur une dalle
      ["powerCycle", lignes]    allumée -> veille -> éteinte -> allumée
      ["act", clé]              bouton ; ["#ask", genre, cible, question, bouton, saisie] : à confirmer dans la fenêtre
      ["confirmed", [action, saisie]]   action confirmée (fn_tocManagerUiData « confirm »)
*/
params [["_mode", ""], ["_a", []]];
disableSerialization;
private _fn = comspec_atak_native_fnc_tocManagerUiApply;
private _dat = comspec_atak_native_fnc_tocManagerUiData;
private _mgr = comspec_atak_native_fnc_tocManager;
private _ts = comspec_atak_native_fnc_tocScreen;
private _th = comspec_atak_native_fnc_tocHall;
private _tr = { _this call comspec_atak_native_fnc_tr };
// Fonctions des autres lots : lues par leur nom, absentes tant que leur lot n'est pas chargé (isNil avant chaque appel).
private _camList = missionNamespace getVariable ["comspec_atak_native_fnc_camList", {}];
private _access = missionNamespace getVariable ["comspec_atak_native_fnc_tocAccess", {}];
private _journal = missionNamespace getVariable ["comspec_atak_native_fnc_tocJournal", {}];
private _st = uiNamespace getVariable ["COMSPEC_ATAK_TocMgrUi", createHashMap];
private _c = _st getOrDefault ["c", createHashMap];
private _rows = _st getOrDefault ["rows", []];
private _rowOf = { params ["_rk"]; (_rows select { (_x select 0) isEqualTo _rk }) param [0, []] };
private _reg = { (missionNamespace getVariable ["COMSPEC_ATAK_TocScreens", []]) select { !isNull _x && {alive _x} && {(count (_x getVariable ["COMSPEC_ATAK_TocScr", []])) >= 8} } };
private _halls = { (missionNamespace getVariable ["COMSPEC_ATAK_TocHalls", []]) select { _x isEqualType [] && {(count _x) >= 3} } };
private _mySide = { if (is3DEN || {isNull player}) then { "WEST" } else { str side group player } };
private _sideOf = { params ["_o"]; private _s = (_o getVariable ["COMSPEC_ATAK_TocScr", []]) param [1, ""]; [_s, call _mySide] select (_s isEqualTo "") };
// Point de vue et endroit de pose : caméra d'Eden, caméra du Zeus, sinon le joueur.
private _eye = {
    if (is3DEN) exitWith { getPosATL get3DENCamera };
    if (!isNull curatorCamera && {!isNull (findDisplay 312)}) exitWith { getPosATL curatorCamera };
    getPosATL player
};
private _spot = {
    if (is3DEN || {!isNull curatorCamera && {!isNull (findDisplay 312)}}) exitWith { private _p = screenToWorld [0.5, 0.5]; [_p select 0, _p select 1, 0] };
    player modelToWorld [0, 3, 0]
};
// Heure écrite après un résultat (ZULU du poste ; Eden : rien).
private _stamp = { if (is3DEN) then { "" } else { format [" · %1 Z", ["zulu"] call comspec_atak_native_fnc_tocRoom] } };
private _plural = { params ["_n", "_one", "_many"]; format [([_one, _many] select (_n > 1)) call _tr, _n] };
// Alimentation d'une dalle : 0 allumée, 1 en veille (éteinte, rallumée par le serveur quand quelqu'un approche), 2 éteinte.
private _setPower = {
    params ["_o", "_pm"];
    if (isNull _o || {is3DEN}) exitWith {};
    private _off = _pm > 0;
    if ((_o getVariable ["COMSPEC_ATAK_TocOff", false]) isNotEqualTo _off) then {
        if (["local", _o] call comspec_atak_native_fnc_tocConfig && {!isServer}) then { _o setVariable ["COMSPEC_ATAK_TocOff", _off, true]; } else { ["do", ["power", [_o, !_off]]] call _th; };
    };
    ["set", [_o, "standby", _pm isEqualTo 1]] call comspec_atak_native_fnc_tocManagerUiPower;
};
private _powerOf = { params ["_o"]; if (_o getVariable ["COMSPEC_ATAK_TocOff", false]) then { [2, 1] select (_o getVariable ["COMSPEC_ATAK_TocStandby", false]) } else { 0 } };
// Demande au satellite du camp de l'écran (fn_tocRoom, serveur) : [écran, clé, valeur].
private _sat = {
    params ["_o", "_k", "_v"];
    private _n = (((_o getVariable ["COMSPEC_ATAK_TocScr", []]) param [8, 0]) max 0) min 1;
    ["do", [[_o] call _sideOf, "sat", [_n, _k, _v], ["me"] call comspec_atak_native_fnc_tocRoom, getPlayerUID player]] call comspec_atak_native_fnc_tocRoom;
};
private _isSat = { params ["_o"]; ((_o getVariable ["COMSPEC_ATAK_TocScr", []]) param [3, ""]) isEqualTo "SAT" };
// Plan du camp de l'écran : éléments [id, genre, points, texte…] (fn_planAction).
private _planOf = { params ["_o"]; (missionNamespace getVariable [format ["COMSPEC_ATAK_Plan_%1", [_o] call _sideOf], [0, [], ""]]) param [1, []] };
// Flux standard d'un lot de lignes, puis allumées à 100 % : RÉINITIALISER et TOUT RÉINITIALISER.
private _resetRows = {
    params ["_list"];
    private _n = 0;
    { if (["setSource", _x] call _mgr) then { _n = _n + 1; }; } forEach (["feeds", _list apply { _x select 1 }] call _mgr);
    if (!is3DEN) then {
        private _done = [];
        {
            [_x select 1, 0] call _setPower;
            private _h = _x select 11;
            if !(_h in _done) then { _done pushBack _h; ["view", [_h, "opt", [100, ((_h getVariable ["COMSPEC_ATAK_TocOpt", [100, 0]]) param [1, 0])]]] call _ts; };
        } forEach _list;
    };
    _n
};
// Préréglages du profil du joueur, par carte : [[nom, [[nom de l'écran, dalle, source, luminosité]...]]...] (4.8.0).
private _presetVar = format ["COMSPEC_ATAK_TocMgrPresets_%1", worldName];

switch (_mode) do {
    case "signature": {
        str [(call _reg) apply { [_x getVariable ["COMSPEC_ATAK_TocScr", []], _x getVariable ["COMSPEC_ATAK_TocOff", false], _x getVariable ["COMSPEC_ATAK_TocStandby", false], _x getVariable ["COMSPEC_ATAK_TocOpt", []],
            _x getVariable ["COMSPEC_ATAK_TocHall", ""], _x getVariable ["COMSPEC_ATAK_TocCam", ""], _x getVariable ["COMSPEC_ATAK_TocOn", false], (_x getVariable ["COMSPEC_ATAK_TocTex", ""]) isNotEqualTo ""] },
            (call _halls) apply { [_x select 0, _x select 1, count (_x param [3, []])] }]
    };
    case "state": {
        private _r = _a;
        if (_r isEqualTo []) exitWith { ["", "muted"] };
        if (is3DEN) exitWith { [_r select 9, _r select 10] };
        private _o = _r select 1;
        switch (true) do {
            case (_o getVariable ["COMSPEC_ATAK_TocOff", false]): { [[("éteinte") call _tr, "muted"], [("en veille") call _tr, "amber"]] select (_o getVariable ["COMSPEC_ATAK_TocStandby", false]) };
            case ((_r select 10) isEqualTo "amber"): { [_r select 9, "amber"] };
            case (_o getVariable ["COMSPEC_ATAK_TocOn", false]): { [("en direct") call _tr, "accent"] };
            case ((_o getVariable ["COMSPEC_ATAK_TocTex", ""]) isNotEqualTo ""): { [("figée") call _tr, "amber"] };
            default { [("lointaine") call _tr, "text2"] };
        }
    };
    case "thumb": {
        // Texture UI de la dalle, telle que fn_tocScreen l'a posée sur l'objet : montrée telle quelle, sans rendu de plus.
        private _o = _a param [1, objNull];
        if (is3DEN || {!(_o isEqualType objNull)} || {isNull _o}) exitWith { "" };
        private _tex = _o getVariable ["COMSPEC_ATAK_TocTex", ""];
        if (_tex isEqualTo "" || {_o getVariable ["COMSPEC_ATAK_TocOff", false]}) exitWith { "" };
        private _spec = _o getVariable ["COMSPEC_ATAK_TocMgrSpec", ""];
        if (_spec isEqualTo "") then { _spec = (["format", _o] call _ts) param [1, "#(rgb,2048,1024,1)"]; _o setVariable ["COMSPEC_ATAK_TocMgrSpec", _spec]; };
        format ["%1ui(""COMSPEC_RscTocScreenTexture"",""%2"")", _spec, _tex]
    };
    case "allowed": {
        if (is3DEN) exitWith { ["views"] call _ts };
        (["format", _a select 1] call _ts) param [4, []]
    };
    case "items": {
        private _r = _a;
        private _o = _r select 1;
        private _ok = ["allowed", _r] call _fn;
        private _mine = (["sources", _o] call _mgr) select { ((_x select 1) select [0, 4]) isNotEqualTo "CAM|" };
        private _all = +_mine;
        // Sources d'un écran 16:9 que cette dalle ne peut pas montrer : listées en gris, avec la raison dans l'aide.
        if (!is3DEN) then {
            { private _k = _x select 1; if ((_all findIf { (_x select 1) isEqualTo _k }) < 0 && {(_k select [0, 4]) isNotEqualTo "CAM|"}) then { _all pushBack _x; }; } forEach (["sources", objNull] call _mgr);
        };
        private _out = _all apply {
            private _k = _x select 1;
            [_x select 0, _k, ["no", ""] select ((_mine findIf { (_x select 1) isEqualTo _k }) >= 0), "", ""]
        };
        if (is3DEN) exitWith { _out };
        // Caméras : genre, nom, état et distance ; les caméras fixes hors ligne restent listées (fn_camList s'il est chargé).
        private _camOk = "CAM" in _ok;
        private _from = call _eye;
        private _camRows = if (isNil "comspec_atak_native_fnc_camList") then { [] } else { [] call _camList };
        if !(_camRows isEqualType []) then { _camRows = []; };
        private _seen = [];
        {
            _x params [["_lbl", ""], ["_id", ""], ["_genre", "CAM"]];
            private _src = objectFromNetId _id;
            private _cr = (_camRows select { (_x param [0, ""]) isEqualTo _id }) param [0, []];
            private _live = alive _src && {(_cr param [3, "OK"]) isEqualTo "OK"};
            _seen pushBack _id;
            _out pushBack [_lbl call _tr, "CAM|" + _id, ["no", ["off", "ok"] select _live] select _camOk, _genre,
                format ["%1 · %2 m", ([("hors ligne") call _tr, ("en ligne") call _tr] select _live), round (_src distance _from)]];
        } forEach (["sources"] call comspec_atak_native_fnc_tocCam);
        {
            _x params [["_id", ""], ["_nm", ""], "", ["_stt", "OK"]];
            if !(_id in _seen) then {
                _seen pushBack _id;
                private _live = _stt isEqualTo "OK";
                _out pushBack [format [("Caméra fixe · %1") call _tr, _nm], "CAM|" + _id, ["no", ["off", "ok"] select _live] select _camOk, "CAM", ([("hors ligne") call _tr, ("en ligne") call _tr] select _live)];
            };
        } forEach _camRows;
        private _curCam = _o getVariable ["COMSPEC_ATAK_TocCam", ""];
        if ((_r select 6) isEqualTo ("CAM|" + _curCam) && {!(_curCam in _seen)}) then { _out pushBack [("caméra perdue") call _tr, _r select 6, "off", "CAM", ("hors ligne") call _tr]; };
        // Plan du camp : en entier, puis ses éléments nommés (la carte est cadrée dessus).
        private _plan = [_o] call _planOf;
        if (_plan isNotEqualTo []) then {
            private _pState = ["no", ""] select ("MAP" in _ok);
            _out pushBack [[count _plan, "Plan du camp en entier (%1 élément)", "Plan du camp en entier (%1 éléments)"] call _plural, "PLAN|ALL", _pState, "", ""];
            private _n = 0;
            {
                _x params ["", ["_kind", ""], ["_pts", []], ["_txt", ""]];
                if (_txt isNotEqualTo "" && {_pts isNotEqualTo []} && {_n < 12}) then { _n = _n + 1; _out pushBack [format ["%1 · %2", _kind, _txt], format ["PLAN|%1", _forEachIndex], _pState, "", ""]; };
            } forEach _plan;
        };
        _out
    };
    case "refusal": {
        // Pourquoi une source est grisée : ce que le format de la dalle permet.
        private _kind = if (is3DEN) then { "STD" } else { (["format", _a select 1] call _ts) param [3, "STD"] };
        switch (_kind) do {
            case "STRIP": { "Cette dalle est un bandeau : elle ne montre que l'horloge en bandeau, la plaque de nom ou un texte. Exemple : choisissez TEMPS, puis Bandeau horloge." };
            case "LED": { "Cette dalle est un panneau à LED : elle ne montre que du texte. Exemple : choisissez TEXTE, puis écrivez le message dans l'onglet TEXTE." };
            case "COL": { "Cette dalle est une colonne d'écran incurvé : elle montre les colonnes (horloge, réseau, caméras, journal). Exemple : choisissez TEMPS, puis Colonne horloge." };
            default { "Cette dalle ne peut pas montrer cette source : son format ne s'y prête pas. Exemple : une plaque de nom ne se pose que sur un bandeau." };
        }
    };
    case "layoutHelp": {
        if ((_a select [0, 2]) isEqualTo "P|") exitWith { format [("Disposition gardée « %1 ». Double-clic : appliquer tout de suite ; OUBLIER la retire. Exemple : la disposition du briefing.") call _tr, _a select [2]] };
        private _row = ((["catalog"] call comspec_atak_native_fnc_tocLayout) select { (_x select 0) isEqualTo _a }) param [0, []];
        format ["%1. %2. %3", (_row param [1, ""]) call _tr, (_row param [2, ""]) call _tr, ("Double-clic : appliquer tout de suite.") call _tr]
    };
    case "values": {
        private _r = _a;
        private _v = createHashMap;
        if (_r isEqualTo []) exitWith { _v };
        private _o = _r select 1;
        private _h = _r select 11;
        private _hide = [];
        private _opts = [];
        private _texts = [];
        _v set ["pBright", _r select 8];
        _v set ["aThumbs", profileNamespace getVariable ["COMSPEC_ATAK_TocMgrThumbs", true]];
        if (is3DEN) exitWith {
            // Eden : seuls les attributs de l'objet se règlent (source, luminosité, nom, salle).
            _hide = ["vFrame", "vWidth", "vCenter", "vFollow", "vSensor", "vJit", "vTerr", "vPan", "vGrid", "vRot", "vQual", "lName", "lBtns", "tBtns", "tColor", "tSecs", "tBlink", "tSpeed", "tScroll", "tAlign", "tBorder", "tSaved", "tVar", "tPrev",
                "pPower", "pDist", "pAuto", "pAll", "aTitle", "aTally", "aBc", "aAccess", "aLock", "aThumbs"];
            _texts = [["vNone", ("Dans Eden, la vue se règle en jeu : ouvrez cette fenêtre au Zeus ou depuis le menu de l'écran.") call _tr], ["tNone", ("Dans Eden, le texte d'un écran s'écrit dans son attribut Texte ou avec le module Afficher un texte.") call _tr],
                ["pState", ("Réglé dans la mission : l'écran s'allume au lancement.") call _tr], ["aDiag", format ["%1 · %2", _r select 3, _r select 9]]];
            _v set ["aName", _r select 2];
            private _hn = _r select 4;
            private _names = [];
            { if ((_x select 4) isNotEqualTo "") then { _names pushBackUnique (_x select 4); }; } forEach _rows;
            _opts pushBack ["aHall", [[("aucune salle") call _tr, ""]] + (_names apply { [_x, _x] }), true];
            _v set ["aHall", _hn];
            _v set ["#hide", _hide];
            _v set ["#opts", _opts];
            _v set ["#texts", _texts];
            _v
        };
        private _scr = _o getVariable ["COMSPEC_ATAK_TocScr", []];
        private _src = _r select 6;
        private _kind = ["kindOf", [_src, _scr param [3, ""]] select (_src isEqualTo "OFF")] call _dat;
        private _allView = ["vFrame", "vWidth", "vCenter", "vFollow", "vSensor", "vJit", "vTerr", "vPan", "vGrid", "vRot", "vQual", "vNone"];
        private _showView = switch (_kind) do {
            case "map": { ["vFrame", "vWidth", "vCenter", "vFollow"] };
            case "sat": { ["vFrame", "vWidth", "vCenter", "vFollow", "vSensor", "vJit", "vTerr", "vPan", "vQual"] };
            case "mos": { ["vGrid", "vRot"] };
            default { ["vNone"] };
        };
        _hide append (_allView - _showView);
        _opts pushBack ["vFrame", (["frames"] call _mgr) apply { [_x select 0, _x select 1] }];
        _v set ["vFrame", ""];
        _v set ["vCenter", 0];
        switch (_kind) do {
            case "map": {
                private _half = _scr param [4, 0];
                _v set ["#width", [2 * _half, 8000] select (_half <= 0)];
                if (_half <= 0) then { _v set ["vFrame", "AUTO"]; };
                _opts pushBack ["vFollow", [["inchangé", ""], ["tout le réseau du camp (cadrage automatique)", "AUTO"]]];
                _v set ["vFollow", ["", "AUTO"] select (_half <= 0)];
            };
            case "sat": {
                private _n = ((_scr param [8, 0]) max 0) min 1;
                private _s = (((["state", [_o] call _sideOf] call comspec_atak_native_fnc_tocRoom) param [2, []]) param [_n, []]);
                _v set ["#width", 2 * (_s param [2, 250])];
                _opts pushBack ["vSensor", [["automatique (jour, thermique la nuit)", "AUTO"]] + (((["modes"] call comspec_atak_native_fnc_tocSat) - ["AUTO"]) apply { [["name", _x] call comspec_atak_native_fnc_tocSat, _x] })];
                _v set ["vSensor", _s param [3, "AUTO"]];
                private _fk = _s param [4, ""];
                private _fl = [[("ne suit personne") call _tr, "U"]];
                if (_fk isEqualTo "U") then { _fl pushBack [("une unité (choisie au PC du poste)") call _tr, ""]; };
                { _fl pushBack [format [("suivre le groupe %1") call _tr, _x], "G:" + _x]; } forEach ((["squads", _scr param [1, ""]] call _ts) select [0, 16]);
                _opts pushBack ["vFollow", _fl, true];
                _v set ["vFollow", switch (_fk) do { case "G": { "G:" + (_s param [5, ""]) }; case "U": { "" }; default { "U" } }];
                private _so = _s param [8, [false, false]];
                _v set ["vJit", _so param [0, false]];
                _v set ["vTerr", _so param [1, false]];
                _texts pushBack ["vQual", format ["%1 : %2", ("Sur votre poste") call _tr, (["Désactivée", "Basse", "Moyenne", "Haute"] select (((missionNamespace getVariable ["comspec_atak_native_toc_sat_quality", 1]) max 0) min 3)) call _tr]];
            };
            case "mos": {
                private _m = ["cfg", _o] call comspec_atak_native_fnc_tocMosaic;
                _v set ["vGrid", parseNumber ((_m select 0) isEqualTo 3)];
                private _rl = [["aucune", "0"], ["10 s", "10"], ["20 s", "20"], ["30 s", "30"], ["60 s", "60"]];
                private _rot = str (_m param [4, 10]);
                if ((_rl findIf { (_x select 1) isEqualTo _rot }) < 0) then { _rl pushBack [format ["%1 s", _rot], _rot]; };
                _opts pushBack ["vRot", _rl];
                _v set ["vRot", _rot];
            };
            case "cam": { _texts pushBack ["vNone", ("Une caméra en direct montre ce que voit son porteur : sa visée ne se règle pas ici.") call _tr]; };
            default { _texts pushBack ["vNone", ("Cette source n'a pas de réglage de vue.") call _tr]; };
        };
        if ("#width" in _v) then { _v set ["vWidth", ["w2p", _v get "#width"] call _dat]; };
        // DISPOSITION : celles que l'écran peut montrer, puis celles gardées par le joueur.
        private _cat = ["catalog"] call comspec_atak_native_fnc_tocLayout;
        private _kept = ["presets"] call comspec_atak_native_fnc_tocLayout;
        private _now = ["current", _h] call comspec_atak_native_fnc_tocLayout;
        _v set ["#layouts", (["list", _h] call comspec_atak_native_fnc_tocLayout) apply {
            _x params ["_lbl", "_key"];
            private _content = if ((_key select [0, 2]) isEqualTo "P|") then { (((_kept select { (_x select 0) isEqualTo (_key select [2]) }) param [0, ["", []]]) select 1) joinString ", " } else { ((_cat select { (_x select 0) isEqualTo _key }) param [0, ["", "", "", ""]]) select 3 };
            [_lbl, _key, _content, _key isEqualTo _now && {_now isNotEqualTo ""}]
        }];
        _v set ["lName", ""];
        // TEXTE : seulement sur une dalle capable de l'affichage Texte.
        private _ok = ["allowed", _r] call _fn;
        if ("TEXT" in _ok) then {
            _hide pushBack "tNone";
            _opts pushBack ["tSecs", [["5 s", "5"], ["10 s", "10"], ["20 s", "20"], ["30 s", "30"], ["60 s", "60"]]];
            _opts pushBack ["tSaved", [["choisir un message…", ""]] + ((["presets"] call comspec_atak_native_fnc_tocTextTool) apply { [_x select 0, ((_x select 1) splitString ";") param [0, ""]] })];
            _opts pushBack ["tVar", [["ajouter une variable…", ""]] + ((["variables"] call comspec_atak_native_fnc_tocTextTool) apply { [format ["%1 · %2", _x select 0, (_x select 1) call _tr], _x select 0] }), true];
            _v set ["tSaved", ""];
            _v set ["tVar", ""];
            _v set ["#text", +(_o getVariable ["COMSPEC_ATAK_TocText", ["default"] call comspec_atak_native_fnc_tocText])];
            if ((_scr param [3, ""]) isNotEqualTo "TEXT") then { _texts pushBack ["tPrev", ("Cette dalle ne montre pas le texte en ce moment : choisissez la source Texte dans l'onglet SOURCE.") call _tr, "amber"]; };
        } else {
            _hide append ["tBtns", "tColor", "tSecs", "tBlink", "tSpeed", "tScroll", "tAlign", "tBorder", "tSaved", "tVar", "tPrev"];
            _texts pushBack ["tNone", ("Cette dalle ne peut pas montrer de texte : le texte se pose sur un bandeau à LED, un écran 16:9 ou une colonne d'écran incurvé.") call _tr];
        };
        // ALIMENTATION.
        _v set ["pPower", [_o] call _powerOf];
        _v set ["pDist", (_h getVariable ["COMSPEC_ATAK_TocOpt", [100, 0]]) param [1, 0]];
        _opts pushBack ["pAuto", [["jamais", "0"], ["5 min", "5"], ["15 min", "15"], ["30 min", "30"], ["60 min", "60"]]];
        _v set ["pAuto", str (_h getVariable ["COMSPEC_ATAK_TocAutoOff", 0])];
        (["state", _r] call _fn) params ["_stL"];
        private _fallback = ((["mode"] call comspec_atak_native_fnc_tocConfig) param [0, "server"]) isEqualTo "fallback";
        _texts pushBack ["pState", format ["%1%2", _stL, ["", " · " + (("veille et veille automatique sans effet : le serveur ne tient pas les écrans") call _tr)] select _fallback], ["text2", "amber"] select _fallback];
        // AVANCÉ.
        _v set ["aName", _scr param [0, ""]];
        _opts pushBack ["aHall", [[("aucune salle") call _tr, ""]] + ((call _halls) apply { [_x select 1, _x select 0] }), true];
        private _hid = _h getVariable ["COMSPEC_ATAK_TocHall", ""];
        _v set ["aHall", _hid];
        private _bc = ["cfg", _o] call comspec_atak_native_fnc_tocBroadcast;
        _v set ["aTitle", _bc param [1, ""]];
        _v set ["aTally", _bc param [2, true]];
        if (isNil "comspec_atak_native_fnc_tocAccess") then {
            _hide pushBack "aLock";
            _texts pushBack ["aAccess", ([("Vous pouvez poser et supprimer des écrans (Zeus, administrateur ou hôte).") call _tr, ("Vous pouvez régler les écrans ; poser et supprimer est réservé au Zeus, à l'administrateur et à l'hôte.") call _tr] select !(["canEdit"] call _mgr))];
        } else {
            private _role = ["roleOf", [player, _hid]] call _access;
            private _lock = ["locked", _hid] call _access;
            private _roleL = switch (_role) do { case "admin": { "administrateur Zeus" }; case "chief": { "chef de poste" }; case "operator": { "opérateur" }; default { "aucun rôle dans cette salle" } };
            _texts pushBack ["aAccess", format ["%1 : %2 · %3", ("Votre rôle") call _tr, _roleL call _tr, [("salle ouverte") call _tr, format [("salle verrouillée par %1") call _tr, _lock param [1, "?"]]] select (_lock param [0, false])], ["text2", "amber"] select (_lock param [0, false])];
        };
        private _fmt = ["format", _o] call _ts;
        _texts pushBack ["aDiag", format ["%1 · %2 %3 · %4 · %5", ([("tenu par le serveur") call _tr, ("tenu par les joueurs (repli)") call _tr] select (_fallback || {_h getVariable ["COMSPEC_ATAK_TocFb", false]})), ("format") call _tr, _fmt param [3, "STD"],
            ([("image non rendue sur ce poste") call _tr, ("image rendue sur ce poste") call _tr] select ((_o getVariable ["COMSPEC_ATAK_TocTex", ""]) isNotEqualTo "")), _h getVariable ["COMSPEC_ATAK_TocUid", "—"]]];
        _v set ["#hide", _hide];
        _v set ["#opts", _opts];
        _v set ["#texts", _texts];
        _v
    };
    case "mixed": {
        private _list = _a select { _x isNotEqualTo [] };
        if ((count _list) < 2 || {is3DEN}) exitWith { [] };
        private _out = [];
        private _diff = { params ["_vals"]; (count (_vals arrayIntersect _vals)) > 1 };
        if ([_list apply { [_x select 1] call _powerOf }] call _diff) then { _out pushBack "pPower"; };
        if ([_list apply { _x select 8 }] call _diff) then { _out pushBack "pBright"; };
        if ([_list apply { _x select 4 }] call _diff) then { _out pushBack "aHall"; };
        _out
    };
    case "hallId": { ((call _halls) select { (_x select 1) isEqualTo _a }) param [0, [""]] select 0 };
    case "sceneNames": { (((call _halls) select { (_x select 0) isEqualTo _a }) param [0, ["", "", "", []]] param [3, []]) apply { _x param [0, ""] } };
    case "presetNames": { ((profileNamespace getVariable [_presetVar, []]) select { _x isEqualType [] }) apply { _x param [0, ""] } };
    case "halls": {
        if (is3DEN) exitWith { [] };
        (call _halls) apply {
            private _scr = ["screens", _x select 0] call _th;
            [_x select 0, _x select 1, _x select 2, count _scr, { !(_x getVariable ["COMSPEC_ATAK_TocOff", false]) } count _scr, count (_x param [3, []])]
        }
    };
    case "links": {
        if (is3DEN || {isNil "comspec_atak_native_fnc_tocLinks"}) exitWith { [] };
        ["list"] call comspec_atak_native_fnc_tocLinks
    };
    case "logAsk": {
        if (!isNil "comspec_atak_native_fnc_tocJournal" && {!is3DEN}) then { ["get", [call _mySide, 100]] call _journal; };
        true
    };
    case "log": {
        // [état, lignes [heure, auteur, salle, action, détail, accordée], repère de fraîcheur]
        if (isNil "comspec_atak_native_fnc_tocJournal") exitWith { ["Le journal du TOC arrive avec les postes et les droits de salle. Aujourd'hui, chaque action s'écrit sur la ligne d'état de cette fenêtre.", [], 0] };
        private _view = ["view"] call _journal;
        if !(_view isEqualType [] && {(count _view) >= 3}) exitWith { ["Journal demandé au serveur…", [], 1] };
        private _hn = createHashMap;
        { _hn set [_x select 0, _x select 1]; } forEach (call _halls);
        private _lines = (_view select 1) select { _x isEqualType [] && {(count _x) >= 10} };
        reverse _lines;
        [["Aucune action écrite pour l'instant, ou journal réservé au chef de poste et au Zeus.", "Les plus récentes en haut."] select (_lines isNotEqualTo []),
            _lines apply { [str (_x select 1), str (_x select 5), _hn getOrDefault [_x select 4, str (_x select 4)], str (_x select 7), str (_x select 8), (_x select 9) isEqualTo true] }, 2 + (_view select 2)]
    };
    case "feedback": {
        // Retour du serveur après une demande (fn_tocAccess, s'il est chargé) : dit sur la ligne d'état, jamais ailleurs.
        private _fb = missionNamespace getVariable ["COMSPEC_ATAK_TocFeedback", []];
        if !(_fb isEqualType [] && {(count _fb) >= 3}) exitWith { [] };
        if ((_fb select 0) isEqualTo (_st getOrDefault ["fbSeen", -1])) exitWith { [] };
        _st set ["fbSeen", _fb select 0];
        if (_fb select 1) exitWith { [] };
        [str (_fb select 2), "red"]
    };
    case "run": {
        private _ops = _a;
        private _done = [];
        private _nRef = 0;
        private _nSkip = 0;
        private _optNow = createHashMap;
        private _here = screenToWorld [0.5, 0.5];
        private _note = "";
        {
            _x params ["_op", "_id", ["_val", ""]];
            if (_op isEqualTo "refuse") then { _nRef = _nRef + 1; continue };
            if (_op isEqualTo "skip") then { _nSkip = _nSkip + 1; continue };
            private _r = [_id] call _rowOf;
            if (_r isEqualTo []) then { continue };
            private _o = _r select 1;
            private _h = _r select 11;
            if (is3DEN) then {
                // Eden : attributs de l'objet ; le reste se règle en jeu. Un module se règle par ses propres attributs.
                if (((typeOf _o) select [0, 19]) isEqualTo "COMSPEC_ATAK_Module") then { _nSkip = _nSkip + 1; continue };
                switch (_op) do {
                    case "source": { if (["setSource", [_o, _val]] call _mgr) then { _done pushBackUnique _id; } else { _nRef = _nRef + 1; }; };
                    case "bright": { _o set3DENAttribute ["COMSPEC_ATAK_EdScreenBright", _val]; _done pushBackUnique _id; };
                    case "name": { _o set3DENAttribute ["COMSPEC_ATAK_EdScreenName", _val]; _done pushBackUnique _id; };
                    case "hall": { _o set3DENAttribute ["COMSPEC_ATAK_EdScreenHall", _val]; _done pushBackUnique _id; };
                    default { _nSkip = _nSkip + 1; };
                };
                continue
            };
            private _okOp = true;
            switch (_op) do {
                case "power": { [_o, _val] call _setPower; };
                case "source": {
                    if ((_val select [0, 5]) isEqualTo "PLAN|") then {
                        // Plan du camp : carte seule, cadrée sur le plan entier ou sur l'élément choisi.
                        private _plan = [_o] call _planOf;
                        private _els = if (_val isEqualTo "PLAN|ALL") then { _plan } else { [_plan param [parseNumber (_val select [5]), []]] select { _x isNotEqualTo [] } };
                        private _pts = [];
                        { _pts append ((_x param [2, []]) select { _x isEqualType [] && {(count _x) >= 2} }); } forEach _els;
                        _okOp = ["setSource", [_o, "MAP"]] call _mgr;
                        if (_okOp && {_pts isNotEqualTo []}) then {
                            private _xs = _pts apply { _x select 0 };
                            private _ys = _pts apply { _x select 1 };
                            private _minX = selectMin _xs; private _maxX = selectMax _xs; private _minY = selectMin _ys; private _maxY = selectMax _ys;
                            ["view", [_o, "zoom", ((((_maxX - _minX) max (_maxY - _minY)) * 0.6) max 250)]] call _ts;
                            ["view", [_o, "center", [(_minX + _maxX) / 2, (_minY + _maxY) / 2]]] call _ts;
                        };
                    } else { _okOp = ["setSource", [_o, _val]] call _mgr; };
                    if (!_okOp) then { _nRef = _nRef + 1; };
                };
                case "layout": { ["apply", [_h, _val]] call comspec_atak_native_fnc_tocLayout; };
                case "frame": { ["setView", [_o, _val, "", _here]] call _mgr; };
                case "width": {
                    if ([_o] call _isSat) then { [_o, "zoomTo", ((500 / (_val max 1)) max 0.25) min 8] call _sat; } else { ["view", [_o, "zoom", _val / 2]] call _ts; };
                };
                case "center": {
                    if ([_o] call _isSat) then { [_o, "center", _val] call _sat; } else { ["view", [_o, "center", _val]] call _ts; };
                };
                case "follow": {
                    switch (true) do {
                        case (_val isEqualTo "AUTO"): { if !([_o] call _isSat) then { ["view", [_o, "auto"]] call _ts; }; };
                        case (_val isEqualTo "U"): { if ([_o] call _isSat) then { [_o, "unfollow", []] call _sat; }; };
                        case ((_val select [0, 2]) isEqualTo "G:"): { if ([_o] call _isSat) then { [_o, "follow", ["G", _val select [2]]] call _sat; }; };
                    };
                };
                case "sensor": { [_o, "mode", _val] call _sat; };
                case "jit": { [_o, "opt", ["jit", _val]] call _sat; };
                case "terr": { [_o, "opt", ["terr", _val]] call _sat; };
                case "mosaic": {
                    private _m = +(["cfg", _o] call comspec_atak_native_fnc_tocMosaic);
                    _m set [0, _val param [0, 2]];
                    _m set [4, _val param [1, 10]];
                    ["view", [_o, "mosaic", _m]] call _ts;
                };
                case "bright"; case "dist": {
                    // Luminosité et distance vont ensemble sur l'écran hôte : la seconde demande reprend la première.
                    private _opt = _optNow getOrDefault [_id, +(_h getVariable ["COMSPEC_ATAK_TocOpt", [100, 0]])];
                    if ((count _opt) < 2) then { _opt = [100, 0]; };
                    _opt set [parseNumber (_op isEqualTo "dist"), _val];
                    _optNow set [_id, _opt];
                    ["view", [_h, "opt", _opt]] call _ts;
                };
                case "autoOff": { ["set", [_h, "autoOff", _val]] call comspec_atak_native_fnc_tocManagerUiPower; };
                case "text": {
                    ["view", [_o, "text", _val]] call _ts;
                    if (((_o getVariable ["COMSPEC_ATAK_TocScr", []]) param [3, ""]) isNotEqualTo "TEXT") then { _note = ("le texte se verra quand la source de la dalle sera Texte") call _tr; };
                };
                case "name": {
                    private _s = _o getVariable ["COMSPEC_ATAK_TocScr", []];
                    ["add", [_o, _val, _s param [1, ""], _s param [2, 0], _s param [3, "SPLIT"], _s param [6, true], false]] call _ts;
                };
                case "hall": { if (_val isEqualTo "") then { ["do", ["unlink", _h]] call _th; } else { ["do", ["link", [_val, _h]]] call _th; }; };
                case "title": { ["set", [_o, "title", _val]] call comspec_atak_native_fnc_tocBroadcast; };
                case "tally": { ["set", [_o, "tally", _val]] call comspec_atak_native_fnc_tocBroadcast; };
            };
            if (_okOp) then { _done pushBackUnique _id; };
            _o setVariable ["COMSPEC_ATAK_TocAt", -10];
        } forEach _ops;
        private _n = count _done;
        private _txt = if (is3DEN) then { [_n, "Enregistré dans la mission pour %1 écran", "Enregistré dans la mission pour %1 écrans"] call _plural } else { ([_n, "Appliqué à %1 dalle ou écran", "Appliqué à %1 dalles ou écrans"] call _plural) + (call _stamp) };
        if (_nRef > 0) then { _txt = _txt + " · " + ([_nRef, "%1 refus : source impossible sur ce format de dalle", "%1 refus : source impossible sur ce format de dalle"] call _plural); };
        if (_nSkip > 0) then { _txt = _txt + " · " + ([_nSkip, "%1 réglage ignoré (une seule dalle à la fois, ou en jeu seulement)", "%1 réglages ignorés (une seule dalle à la fois, ou en jeu seulement)"] call _plural); };
        if (_note isNotEqualTo "") then { _txt = _txt + " · " + _note; };
        [_txt, ["accent", "amber"] select (_nRef > 0 || {_n isEqualTo 0}), 1.2]
    };
    case "place": {
        _a params ["_ready", "_r"];
        private _o = _r select 1;
        private _ok = if ((_ready select 0) isEqualTo "src" && {((_ready select 1) select [0, 5]) isEqualTo "PLAN|"}) then { (["run", [["source", _r select 0, _ready select 1]]] call _fn) select 1 isEqualTo "accent" } else { ["setSource", [_o, _ready select 1]] call _mgr };
        if (_ok && {(_ready select 0) isEqualTo "copy"} && {!is3DEN}) then { ["view", [_r select 11, "opt", [_ready param [2, 100], ((_r select 11) getVariable ["COMSPEC_ATAK_TocOpt", [100, 0]]) param [1, 0]]]] call _ts; };
        if (_ok) then { [format [("%1 posée sur %2") call _tr, ["sourceLabel", _ready select 1] call _mgr, _r select 2] + (call _stamp), "accent"] } else { [format [("%1 refusée : cette dalle ne peut pas la montrer") call _tr, ["sourceLabel", _ready select 1] call _mgr], "amber"] }
    };
    case "powerCycle": {
        private _list = _a select { _x isNotEqualTo [] };
        if (_list isEqualTo [] || {is3DEN}) exitWith { [("Alimentation : en jeu, sur une dalle choisie") call _tr, "text2"] };
        private _next = ((([(_list select 0) select 1] call _powerOf) + 1) mod 3);
        { [_x select 1, _next] call _setPower; } forEach _list;
        [([count _list, ["%1 dalle allumée", "%1 dalle en veille : elle se rallume quand quelqu'un approche", "%1 dalle éteinte"] select _next, ["%1 dalles allumées", "%1 dalles en veille : elles se rallument quand quelqu'un approche", "%1 dalles éteintes"] select _next] call _plural) + (call _stamp), "accent"]
    };
    case "confirmed": {
        _a params [["_act", ""], ["_typed", ""]];
        private _p = _act splitString ":";
        private _what = _p param [0, ""];
        private _id = (_p select [1]) joinString ":";
        switch (_what) do {
            case "roomRemove"; case "roomDissolve": {
                if (is3DEN) exitWith { [("Dans Eden, supprimez le module Salle TOC complète ou les écrans eux-mêmes") call _tr, "amber", 0] };
                if !(["canEdit"] call _mgr) exitWith { [("Réservé au Zeus, à l'administrateur et à l'hôte") call _tr, "red", 0] };
                private _nm = ((call _halls) select { (_x select 0) isEqualTo _id }) param [0, ["", "?"]] select 1;
                ["do", [["roomRemove", "dissolve"] select (_what isEqualTo "roomDissolve"), _id]] call _th;
                [format [(["Salle %1 supprimée avec ses écrans, ses postes et son mobilier", "Salle %1 rayée, écrans et postes gardés en place"] select (_what isEqualTo "roomDissolve")) call _tr, _nm] + (call _stamp), "accent", 2.5]
            };
            case "screenDelete": {
                private _r = [_id] call _rowOf;
                if (_r isEqualTo []) exitWith { [("Écran introuvable : il a déjà été retiré") call _tr, "amber", 0.5] };
                if (is3DEN) then { delete3DENEntities [_r select 11]; } else { ["remove", _r select 11] call _ts; };
                [format [("Écran %1 retiré") call _tr, _r select 2] + (call _stamp), "accent", 1.2]
            };
            case "resetAll": {
                private _n = [_rows] call _resetRows;
                if (!is3DEN) then { { ["stop", ["hall", _x select 0]] call comspec_atak_native_fnc_tocBroadcast; } forEach (call _halls); };
                [([_n, "Tout est réinitialisé : flux standard sur %1 dalle", "Tout est réinitialisé : flux standard sur %1 dalles"] call _plural) + (call _stamp), "accent", 1.5]
            };
            case "sceneSave": {
                private _nm = (toUpper (trim _typed)) select [0, 24];
                if (_nm isEqualTo "") exitWith { [("Rien n'est enregistré : le nom était vide") call _tr, "amber", 0] };
                if (_id isNotEqualTo "" && {!is3DEN}) exitWith {
                    ["do", ["sceneSave", [_id, _nm]]] call _th;
                    [format [("Scène %1 enregistrée dans la salle") call _tr, _nm] + (call _stamp), "accent", 1.5]
                };
                // Sans salle : préréglage du profil [nom de l'écran, dalle, source, luminosité], retrouvé par nom et dalle.
                private _items = (_rows select { (_x select 6) isNotEqualTo "" }) apply { [_x select 2, _x select 12, _x select 6, _x select 8] };
                private _all = (profileNamespace getVariable [_presetVar, []]) select { _x isEqualType [] && {(_x param [0, ""]) isNotEqualTo _nm} };
                _all pushBack [_nm, _items];
                profileNamespace setVariable [_presetVar, _all select [0 max ((count _all) - 20)]];
                saveProfileNamespace;
                [format [("Préréglage %1 enregistré dans votre profil (%2 dalles)") call _tr, _nm, count _items], "accent", 0.3]
            };
            default { ["", "text2", 0] };
        }
    };
    case "act": {
        private _cur = [_st getOrDefault ["cur", ""]] call _rowOf;
        private _sel = _rows select { (_x select 0) in (_st getOrDefault ["sel", []]) };
        if (_sel isEqualTo [] && {_cur isNotEqualTo []}) then { _sel = [_cur]; };
        private _needCur = { [("Choisissez d'abord un écran dans l'arbre") call _tr, "amber", 0] };
        private _inGame = { [("En jeu seulement : ouvrez cette fenêtre au Zeus ou depuis le menu de l'écran") call _tr, "text2", 0] };
        // Salle visée : celle choisie dans la vue SALLES, sinon celle de la dalle choisie, sinon celle de la liste du haut.
        private _hallOf = {
            private _id = "";
            if ((_st getOrDefault ["view", 0]) isEqualTo 1) then { private _t = _c getOrDefault ["rooms", controlNull]; _id = _t lnbData [lnbCurSelRow _t, 0]; };
            if (_id isEqualTo "" && {_cur isNotEqualTo []} && {!is3DEN}) then { _id = (_cur select 11) getVariable ["COMSPEC_ATAK_TocHall", ""]; };
            if (_id isEqualTo "") then { _id = ["hallId", _st getOrDefault ["hallSel", ""]] call _fn; };
            _id
        };
        private _hallName = { params ["_id"]; ((call _halls) select { (_x select 0) isEqualTo _id }) param [0, ["", ""]] select 1 };
        switch (_a) do {
            case "links": {
                if (is3DEN) exitWith { call _inGame };
                private _on = ["toggle"] call comspec_atak_native_fnc_tocLinks;
                [([("Liaisons cachées") call _tr, ("Liaisons montrées dans le monde, interface Zeus ouverte : écran vert, poste bleu, caméra orange, mobilier gris, non lié rouge") call _tr] select _on), "accent", 0]
            };
            case "load": {
                private _ctl = _c getOrDefault ["scene", controlNull];
                private _data = _ctl lbData (lbCurSel _ctl);
                private _nm = _data select [2];
                if (_data isEqualTo "") exitWith { [("Aucune scène à charger : enregistrez-en une avec ENREGISTRER") call _tr, "amber", 0] };
                if ((_data select [0, 2]) isEqualTo "S|") exitWith {
                    private _hid = ["hallId", _st getOrDefault ["hallSel", ""]] call _fn;
                    ["do", ["sceneApply", [_hid, _nm]]] call _th;
                    [format [("Scène %1 remise sur la salle") call _tr, _nm] + (call _stamp), "accent", 1.5]
                };
                private _preset = ((profileNamespace getVariable [_presetVar, []]) select { _x isEqualType [] && {(_x param [0, ""]) isEqualTo _nm} }) param [0, []];
                private _n = 0;
                {
                    _x params [["_pn", ""], ["_pk", 0], ["_ps", ""], ["_pb", 100]];
                    private _r = (_rows select { (_x select 2) isEqualTo _pn && {(_x select 12) isEqualTo _pk} }) param [0, []];
                    if (_r isNotEqualTo [] && {["setSource", [_r select 1, _ps]] call _mgr}) then {
                        _n = _n + 1;
                        if (!is3DEN && {(_r select 8) isNotEqualTo _pb}) then { ["view", [_r select 11, "opt", [_pb, ((_r select 11) getVariable ["COMSPEC_ATAK_TocOpt", [100, 0]]) param [1, 0]]]] call _ts; };
                    };
                } forEach (_preset param [1, []]);
                [format [("Préréglage %1 chargé sur %2") call _tr, _nm, [_n, "%1 dalle", "%1 dalles"] call _plural] + (call _stamp), ["amber", "accent"] select (_n > 0), 1.2]
            };
            case "save": {
                private _hid = if (is3DEN) then { "" } else { ["hallId", _st getOrDefault ["hallSel", ""]] call _fn };
                private _ctl = _c getOrDefault ["scene", controlNull];
                ["#ask", "scene", _hid, ([("Nom du préréglage à garder dans votre profil (toutes les dalles, par carte). Exemple : BRIEFING.") call _tr, format [("Nom de la scène à garder dans la salle %1 (ce que montre chaque écran). Exemple : VEILLE COURANTE.") call _tr, [_hid] call _hallName]] select (_hid isNotEqualTo "")),
                    "ENREGISTRER", (_ctl lbData (lbCurSel _ctl)) select [2]]
            };
            case "add": {
                private _ctl = _c getOrDefault ["kind", controlNull];
                private _cls = _ctl lbData (lbCurSel _ctl);
                if (_cls isEqualTo "") exitWith { [("Choisissez d'abord le type d'écran dans la liste au-dessus du bouton") call _tr, "amber", 0] };
                private _p = call _spot;
                if (is3DEN) then {
                    private _e = create3DENEntity ["Object", _cls, _p];
                    _e set3DENAttribute ["COMSPEC_ATAK_EdScreenMode", "toc"];
                    _e set3DENAttribute ["COMSPEC_ATAK_EdScreenName", ["uniqueName", "TOC"] call _mgr];
                } else {
                    ["spawn", [_p, _p getDir (call _eye), _cls, ["uniqueName", "TOC"] call _mgr, "", "SPLIT", true]] call _ts;
                };
                [("Écran posé devant vous : il arrive dans l'arbre") call _tr, "accent", 1.5]
            };
            case "dup": {
                if (_cur isEqualTo []) exitWith { call _needCur };
                private _h = _cur select 11;
                private _p = _h modelToWorld [2, 0, 0];
                if (is3DEN) then {
                    private _e = create3DENEntity ["Object", typeOf _h, [_p select 0, _p select 1, 0]];
                    _e set3DENAttribute ["rotation", (_h get3DENAttribute "rotation") param [0, [0, 0, 0]]];
                    { _e set3DENAttribute [_x, (_h get3DENAttribute _x) param [0, ""]]; } forEach ["COMSPEC_ATAK_EdScreenMode", "COMSPEC_ATAK_EdScreenView", "COMSPEC_ATAK_EdScreenBright", "COMSPEC_ATAK_EdScreenHall"];
                    _e set3DENAttribute ["COMSPEC_ATAK_EdScreenName", ["uniqueName", (_h get3DENAttribute "COMSPEC_ATAK_EdScreenName") param [0, "TOC"]] call _mgr];
                } else {
                    private _s = _h getVariable ["COMSPEC_ATAK_TocScr", []];
                    ["spawn", [[_p select 0, _p select 1, (getPosATL _h) select 2], getDir _h, typeOf _h, ["uniqueName", _s param [0, "TOC"]] call _mgr, _s param [1, ""], _s param [3, "SPLIT"], _s param [6, true],
                        _s param [8, 0], _s param [9, ""], ["panelStr", _h] call comspec_atak_native_fnc_tocConfig]] call _ts;
                };
                [("Copie posée 2 m à droite de l'écran") call _tr, "accent", 1.5]
            };
            case "del": {
                if (_cur isEqualTo []) exitWith { call _needCur };
                private _host = (_rows select { (_x select 1) isEqualTo (_cur select 11) }) param [0, _cur];
                private _n = { (_x select 11) isEqualTo (_cur select 11) } count _rows;
                ["#ask", "screen", _cur select 0, format [("Retirer l'écran %1 (%2) ? Un écran posé par le mod est supprimé ; un objet de la mission redevient un simple objet. Ce n'est pas annulable.") call _tr, _host select 2, [_n, "%1 dalle", "%1 dalles"] call _plural], "RETIRER L'ÉCRAN", ""]
            };
            case "go": {
                if (_cur isEqualTo []) exitWith { call _needCur };
                private _h = _cur select 11;
                if (is3DEN) exitWith { move3DENCamera [getPosATL _h, true]; [("Caméra d'Eden amenée sur l'écran") call _tr, "accent", 0] };
                if (!isNull curatorCamera && {!isNull (findDisplay 312)}) exitWith {
                    private _p = _h modelToWorld [0, 5, 2];
                    curatorCamera setPosATL _p;
                    curatorCamera setVectorDirAndUp [(getPosATL _h) vectorDiff _p, [0, 0, 1]];
                    [("Caméra du Zeus amenée devant l'écran") call _tr, "accent", 0]
                };
                [format [("Écran à %1 m, cap %2°") call _tr, round (player distance _h), round (player getDir _h)], "accent", 0]
            };
            case "blink": {
                if (_cur isEqualTo []) exitWith { call _needCur };
                if (is3DEN) exitWith { call _inGame };
                ["do", ["blink", _cur select 1]] call _th;
                [("L'écran clignote 2 s chez les joueurs proches") call _tr, "accent", 0]
            };
            case "reset": {
                if (_sel isEqualTo []) exitWith { call _needCur };
                private _n = [_sel] call _resetRows;
                [([_n, "Flux standard remis sur %1 dalle, allumée à 100 %", "Flux standard remis sur %1 dalles, allumées à 100 %"] call _plural) + (call _stamp), "accent", 1.2]
            };
            case "panW"; case "panN"; case "panS"; case "panE": {
                if (_cur isEqualTo [] || {is3DEN}) exitWith { call _needCur };
                if !([_cur select 1] call _isSat) exitWith { [("La visée ne se déplace que sur une dalle en satellite") call _tr, "amber", 0] };
                [_cur select 1, "pan", switch (_a) do { case "panW": { [-0.25, 0] }; case "panE": { [0.25, 0] }; case "panN": { [0, 0.25] }; default { [0, -0.25] } }] call _sat;
                (_cur select 1) setVariable ["COMSPEC_ATAK_TocAt", -10];
                [("Visée du satellite déplacée d'un quart d'image") call _tr, "accent", 0]
            };
            case "laySave": {
                if (_cur isEqualTo [] || {is3DEN}) exitWith { call _inGame };
                private _ctl = (((_st getOrDefault ["fctl", createHashMap]) getOrDefault ["lName", ["", controlNull, [controlNull]]]) select 2) param [0, controlNull];
                private _nm = ["save", [_cur select 11, trim (ctrlText _ctl)]] call comspec_atak_native_fnc_tocLayout;
                if (_nm isEqualTo "") exitWith { [("Rien n'est gardé : écrivez d'abord un nom, sur un écran à plusieurs dalles") call _tr, "amber", 0] };
                [format [("Disposition %1 gardée dans votre profil") call _tr, _nm], "accent", 0.3]
            };
            case "layDel": {
                private _t = _c getOrDefault ["layouts", controlNull];
                private _key = _t lnbData [lnbCurSelRow _t, 0];
                if ((_key select [0, 2]) isNotEqualTo "P|") exitWith { [("Choisissez d'abord une disposition gardée (en bas de la liste) : celles du mod ne s'oublient pas") call _tr, "amber", 0] };
                ["delPreset", _key select [2]] call comspec_atak_native_fnc_tocLayout;
                (_st getOrDefault ["changes", createHashMap]) deleteAt "layout";
                [format [("Disposition %1 oubliée") call _tr, _key select [2]], "accent", 0.3]
            };
            case "feeds"; case "roomFeeds": {
                private _to = _sel;
                if (_a isEqualTo "roomFeeds") then { private _hn = [call _hallOf] call _hallName; _to = _rows select { (_x select 4) isEqualTo _hn && {_hn isNotEqualTo ""} }; } else {
                    if ((count _to) <= 1 && {_cur isNotEqualTo []}) then { _to = _rows select { (_x select 11) isEqualTo (_cur select 11) }; };
                };
                if (_to isEqualTo []) exitWith { [("Aucun écran visé : choisissez une salle ou un écran") call _tr, "amber", 0] };
                private _n = 0;
                { if (["setSource", _x] call _mgr) then { _n = _n + 1; }; } forEach (["feeds", _to apply { _x select 1 }] call _mgr);
                [([_n, "Flux standard posé sur %1 dalle", "Flux standard posés sur %1 dalles"] call _plural) + (call _stamp), "accent", 1.2]
            };
            case "allOn"; case "allOff": {
                if (is3DEN) exitWith { call _inGame };
                private _hid = call _hallOf;
                if (_hid isEqualTo "") exitWith { [("Aucune salle visée : choisissez une salle, ou un écran lié à une salle") call _tr, "amber", 0] };
                ["do", ["powerAll", [_hid, _a isEqualTo "allOn"]]] call _th;
                [format [(["Salle %1 : tous les écrans éteints", "Salle %1 : tous les écrans allumés"] select (_a isEqualTo "allOn")) call _tr, [_hid] call _hallName] + (call _stamp), "accent", 1.2]
            };
            case "bcOn": {
                if (_cur isEqualTo []) exitWith { call _needCur };
                if (is3DEN) exitWith { call _inGame };
                private _hid = (_cur select 11) getVariable ["COMSPEC_ATAK_TocHall", ""];
                if (_hid isEqualTo "") exitWith { [("Cet écran n'est lié à aucune salle : liez-le d'abord (champ Salle)") call _tr, "amber", 0] };
                private _bc = ["cfg", _cur select 1] call comspec_atak_native_fnc_tocBroadcast;
                private _n = ["start", [_cur select 1, "hall", _hid, [_bc param [1, ""], _bc param [2, true], true]]] call comspec_atak_native_fnc_tocBroadcast;
                [([_n, "Diffusion lancée sur %1 dalle de la salle", "Diffusion lancée sur %1 dalles de la salle"] call _plural) + (call _stamp), "accent", 1.2]
            };
            case "bcOff": {
                if (_cur isEqualTo []) exitWith { call _needCur };
                if (is3DEN) exitWith { call _inGame };
                private _n = ["stop", ["hall", (_cur select 11) getVariable ["COMSPEC_ATAK_TocHall", ""]]] call comspec_atak_native_fnc_tocBroadcast;
                [([_n, "Diffusion arrêtée : %1 dalle a repris son affichage", "Diffusion arrêtée : %1 dalles ont repris leur affichage"] call _plural) + (call _stamp), "accent", 1.2]
            };
            case "lockOn"; case "lockOff": {
                if (isNil "comspec_atak_native_fnc_tocAccess" || {is3DEN}) exitWith { [("Le verrou de salle arrive avec les droits de salle") call _tr, "text2", 0] };
                private _hid = call _hallOf;
                if (_hid isEqualTo "") exitWith { [("Aucune salle visée : choisissez un écran lié à une salle") call _tr, "amber", 0] };
                ["lock", [_hid, _a isEqualTo "lockOn", "gestionnaire des écrans"]] remoteExecCall [format ["comspec_atak_native_fnc_%1", "tocAccess"], 2];
                [format [(["Demande envoyée : déverrouiller la salle %1", "Demande envoyée : verrouiller la salle %1"] select (_a isEqualTo "lockOn")) call _tr, [_hid] call _hallName], "accent", 1.5]
            };
            case "test": {
                if (_cur isEqualTo []) exitWith { call _needCur };
                if (is3DEN) exitWith { call _inGame };
                private _o = _cur select 1;
                ["test", [_cur select 11, _o getVariable ["COMSPEC_ATAK_TocSel", (_o getVariable ["COMSPEC_ATAK_TocScr", []]) param [2, 0]]]] call _ts;
                [("Image de test sur la dalle pendant 8 s, sur votre poste seulement") call _tr, "accent", 0]
            };
            case "roomNew": {
                if (is3DEN) exitWith { [("Dans Eden, posez le module Salle TOC complète : la salle se construit au lancement") call _tr, "text2", 0] };
                private _nm = trim (ctrlText (_c getOrDefault ["roomName", controlNull]));
                if (_nm isEqualTo "") exitWith { [("Écrivez d'abord le nom de la salle") call _tr, "amber", 0] };
                _nm = ["uniqueHall", _nm] call _mgr;
                ["do", ["create", [_nm, call _mySide, objNull, ""]]] call _th;
                [format [("Salle %1 créée : liez-y des écrans (AVANCÉ, champ Salle)") call _tr, _nm], "accent", 1.5]
            };
            case "roomName": {
                if (is3DEN) exitWith { call _inGame };
                private _hid = call _hallOf;
                private _nm = trim (ctrlText (_c getOrDefault ["roomName", controlNull]));
                if (_hid isEqualTo "" || {_nm isEqualTo ""}) exitWith { [("Choisissez la salle dans la liste et écrivez son nouveau nom") call _tr, "amber", 0] };
                ["do", ["rename", [_hid, _nm]]] call _th;
                [format [("Salle renommée %1") call _tr, toUpper _nm], "accent", 1.5]
            };
            case "roomGen": {
                private _ctl = _c getOrDefault ["roomSize", controlNull];
                private _size = _ctl lbData (lbCurSel _ctl);
                if (_size isEqualTo "") then { _size = "M"; };
                private _p = call _spot;
                if (is3DEN) exitWith {
                    private _e = create3DENEntity ["Logic", "COMSPEC_ATAK_Module_TocHall", _p];
                    _e set3DENAttribute ["COMSPEC_ATAK_Module_TocHall_Size", _size];
                    [("Module Salle TOC complète posé : la salle se construit au lancement de la mission") call _tr, "accent", 1]
                };
                ["zeus", [_p, (call _eye) getDir _p, _size, ["uniqueHall", "SALLE TOC"] call _mgr, call _mySide, ""]] call comspec_atak_native_fnc_tocHallBuild;
                [("Salle en cours de pose devant vous (quelques secondes)") call _tr, "accent", 6]
            };
            case "roomDel": {
                if (is3DEN) exitWith { [("Dans Eden, supprimez le module Salle TOC complète ou les écrans eux-mêmes") call _tr, "text2", 0] };
                private _hid = call _hallOf;
                if (_hid isEqualTo "") exitWith { [("Choisissez d'abord la salle dans la liste") call _tr, "amber", 0] };
                ["#ask", "room", _hid, format [("Supprimer la salle %1 (%2) ? Elle ne reviendra pas à la partie suivante. Ce n'est pas annulable.") call _tr, [_hid] call _hallName, [count (["screens", _hid] call _th), "%1 écran", "%1 écrans"] call _plural], "SUPPRIMER LA SALLE", ""]
            };
            case "resetAll": {
                ["#ask", "resetAll", "", format [("Remettre les %1 de la mission à leur flux standard, allumées à 100 %, et arrêter toutes les diffusions ? Les sources choisies à la main seront perdues.") call _tr, [count _rows, "%1 dalle", "%1 dalles"] call _plural], "TOUT RÉINITIALISER", ""]
            };
            case "logRefresh": {
                ["logAsk"] call _fn;
                [("Journal redemandé au serveur") call _tr, "text2", 0]
            };
            default { ["", "text2", 0] };
        }
    };
    default { [] };
};
