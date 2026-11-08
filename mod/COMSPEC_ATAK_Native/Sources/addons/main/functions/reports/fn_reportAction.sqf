/*
    App Comptes rendus. Params : [action, argument, valeur]
      "tab" onglet (NEW, DRAFTS, SENT, RECV)   "type" code : ouvre la fiche (son brouillon s'il y en a un), ou l'app qui
                                                gère ce C.R. pour un code @…
      "back" : retour à la liste, la fiche entamée reste en brouillon       "go" page : ouvre une autre app (CAS, Médical)
      "set" clé valeur : choix d'un bouton      "here" clé : grille = ma position
      "pick" clé / "picked" position : grille pointée sur la carte          "autoKey" clé : proposition du jeu pour ce champ
      "auto" outil (enemy, group, target, here) : propositions du jeu pour toute la fiche
      "clear" : fiche vide                      "draft" : enregistre le brouillon      "dropDraft" code : le supprime
      "photo" : liste des photos du téléphone   "photoSet" n° : joint la photo          "photoClear" : la retire
      "send" : contrôle la fiche (lignes requises, nombres, grilles), la diffuse en jeu (camp ou groupe) et l'enregistre
               sur Athena (POST /api/atak/reports, avec la remontée JNET choisie et la photo jointe)
      "recv" [C.R., camp] : réception (événement comspec_atak_native_report, rejoué par la synchro serveur)
      "open" id : déplie / replie un C.R. ; un C.R. reçu ouvert pour la première fois renvoie « lu » à son auteur
      "ack" id : accusé de réception à l'auteur  "rcpt" [id, camp, uid, nom, LU|ACK, heure] : accusé reçu (le mien)
      "map" id : carte sur le C.R.   "del" id : le retire de ma liste   "web" id : le renvoie à Athena
      "dup" id : nouvelle fiche reprenant ce C.R.  "orderAck" id : accuse réception d'un ordre du commandement (app Tâches)
      "mobile" avis : fiche remplie sur le vrai téléphone (« Sur mon téléphone », avis mobile_report), envoyée comme ici
      "prefill" code valeurs : ouvre la fiche de ce C.R. déjà remplie (HashMap clé du catalogue → valeur), les champs
               laissés vides reçoivent les propositions du jeu ; appelé à l'ouverture de l'app quand un autre mod a posé
               uiNamespace COMSPEC_ATAK_SpotrepDraft (fn_pageReports)
    État : uiNamespace COMSPEC_ATAK_RepUi (onglet, type, valeurs, propositions, lignes en défaut, C.R. déplié, photo)
           missionNamespace COMSPEC_ATAK_Reports (id → C.R., les miens et ceux reçus)
           missionNamespace COMSPEC_ATAK_RepDrafts (code → [valeurs, heure, propositions])
*/
params [["_act", ""], ["_arg", ""], ["_val", ""]];
private _ui = uiNamespace getVariable ["COMSPEC_ATAK_RepUi", createHashMap];
uiNamespace setVariable ["COMSPEC_ATAK_RepUi", _ui];
private _vals = _ui getOrDefault ["vals", createHashMap];
_ui set ["vals", _vals];
private _auto = _ui getOrDefault ["auto", createHashMap];
_ui set ["auto", _auto];
private _list = missionNamespace getVariable ["COMSPEC_ATAK_Reports", createHashMap];
missionNamespace setVariable ["COMSPEC_ATAK_Reports", _list];
private _drafts = missionNamespace getVariable ["COMSPEC_ATAK_RepDrafts", createHashMap];
missionNamespace setVariable ["COMSPEC_ATAK_RepDrafts", _drafts];
private _rerender = { [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "REPORTS") then { ["REPORTS"] call comspec_atak_native_fnc_pageRender; }; }] call comspec_atak_native_fnc_nextFrame; };
private _types = [] call comspec_atak_native_fnc_reportTypes;
private _def = { params ["_code"]; private _i = _types findIf { (_x select 0) isEqualTo _code }; if (_i < 0) then { [] } else { _types select _i } };
private _textKinds = ["edit", "memo", "num", "dtg", "grid", "combo"];
// Garde la saisie (textes, listes) avant de redessiner : les boutons sont déjà dans _vals.
private _save = {
    // Fiche venue du vrai téléphone (« mobile ») : rien à relire dans les champs de la page.
    if (_ui getOrDefault ["mobile", false]) exitWith {};
    private _t = [_ui getOrDefault ["type", ""]] call _def;
    if ((count _t) isEqualTo 0 || {(_t select 3) isEqualTo ""}) exitWith {};
    {
        _x params ["_k", "_kind"];
        if (_kind in _textKinds) then {
            private _v = ["rp_" + _k, "§"] call comspec_atak_native_fnc_formValue;
            if (_v isNotEqualTo "§") then {
                // Une liste lue pour la première fois (valeur par défaut) ne compte pas comme une saisie.
                if (_v isNotEqualTo (_vals getOrDefault [_k, ""]) && {_kind isNotEqualTo "combo" || {_k in _vals}}) then {
                    _ui set ["touched", true];
                    _ui set ["errors", (_ui getOrDefault ["errors", []]) - [_k]];
                };
                _vals set [_k, _v];
            };
        };
    } forEach (_t select 5);
};
// Brouillon : gardé dès qu'on a touché la fiche.
private _keepDraft = {
    private _code = _ui getOrDefault ["type", ""];
    if (_code isEqualTo "" || {!(_ui getOrDefault ["touched", false])}) exitWith {};
    _drafts set [_code, [+_vals, [dayTime, "HH:MM"] call BIS_fnc_timeToString, +_auto]];
};
// Propositions du jeu pour les champs de la fiche ; _force : remplace aussi ce qui est déjà saisi.
private _applyAuto = {
    params ["_t", ["_only", []], ["_force", false]];
    private _want = (_t select 5) select { ((_x param [7, ""]) isNotEqualTo "") && {(_only isEqualTo []) || {(_x param [7, ""]) in _only}} };
    if ((count _want) isEqualTo 0) exitWith { createHashMap };
    private _res = [_want apply { _x select 7 }] call comspec_atak_native_fnc_reportAuto;
    {
        _x params ["_k", "_kind"];
        private _p = _res getOrDefault [_x select 7, ""];
        if (_p isNotEqualTo "" && {_force || {(_vals getOrDefault [_k, ""]) isEqualTo ""}}) then {
            _vals set [_k, _p];
            _auto set [_k, _p];
        };
    } forEach _want;
    _res
};
private _toolIds = createHashMapFromArray [
    ["enemy", ["enemyGrid", "enemyDir", "enemyDist", "enemySize", "enemySizeClass", "enemyCount", "enemyKind", "enemyUnit", "enemyEquip", "enemySummary", "activity"]],
    ["group", ["fit", "wia", "kia", "groupLoss", "friendlySummary", "capability", "ammo", "fuel", "medical", "vehicles", "route", "missionStart"]],
    ["target", ["targetGrid"]],
    ["here", ["here", "dtg", "contact", "casualty", "casualtySigns"]]
];
private _openType = {
    params ["_code", ["_from", createHashMap]];
    private _t = [_code] call _def;
    private _d = _drafts getOrDefault [_code, []];
    _vals = createHashMap;
    _auto = createHashMap;
    _ui set ["touched", false];
    if ((count _from) > 0) then { _vals = +_from; _ui set ["touched", true]; } else {
        if ((count _d) > 0) then { _vals = +(_d select 0); _auto = +(_d param [2, createHashMap]); _ui set ["touched", true]; };
    };
    _ui set ["vals", _vals];
    _ui set ["auto", _auto];
    _ui set ["errors", []];
    _ui set ["photoPick", false];
    _ui set ["type", _code];
    _ui set ["tab", "NEW"];
    if ((count _d) isEqualTo 0) then { [_t] call _applyAuto; };
};
// Accusé de lecture ou de réception, renvoyé au camp (l'auteur le garde, les autres l'ignorent).
private _sendRcpt = {
    params ["_r", "_kind"];
    private _args = [_r getOrDefault ["id", ""], str side group player, getPlayerUID player, [player, true] call comspec_atak_native_fnc_unitCallsign, _kind, [dayTime, "HH:MM"] call BIS_fnc_timeToString];
    [{ ["comspec_atak_native_sideEvent", ["comspec_atak_native_reportRcpt", _this, _this select 1]] call CBA_fnc_serverEvent; }, _args, "Accusé", 1] call comspec_atak_native_fnc_netSend;
};
// Exécution du C.R. sur Athena (Overwatch embarqué). Renvoie "OK", "ÉCHEC" ou "HORS LIGNE".
private _toWeb = {
    params ["_r"];
    if (isNil "comspec_overwatch_connect_fnc_submitTacticalReport") exitWith { "HORS LIGNE" };
    // Sans Athena (mode full local, service « Non ») : le C.R. ne vit qu'en jeu, rien n'est tenté.
    if (([] call comspec_atak_native_fnc_athenaMode) isNotEqualTo "on") exitWith { "LOCAL" };
    private _sd = createHashMapFromArray [["kind", toLower (_r get "code")], ["grid", _r get "grid"], ["source", "COMSPEC ATAK · Comptes rendus"]];
    { _sd set [_x select 0, _x select 2]; } forEach (_r get "lines");
    // Remontée JNET (relais du site vers les unités au-dessus de la mienne) et photo jointe.
    private _up = _r getOrDefault ["jnet", ""];
    if (_up isNotEqualTo "") then { _sd set ["jnet_escalate", switch (_up) do { case "+2 ÉCHELONS": { "2" }; case "SOMMET": { "top" }; default { "1" } }]; };
    if ((_r getOrDefault ["photo", ""]) isNotEqualTo "") then { _sd set ["photo", _r get "photo"]; };
    private _prio = switch (_r get "prio") do { case "PRIORITAIRE": { "PRIORITY" }; case "IMMÉDIAT": { "IMMEDIATE" }; case "FLASH": { "FLASH" }; default { "ROUTINE" } };
    // Une seule ligne : le JSON d'Overwatch (hashMapToJson) n'échappe pas les retours à la ligne.
    private _details = ((_r get "lines") apply { format ["%1 : %2", _x select 1, _x select 2] }) joinString " | ";
    private _ok = [_r get "web_type", _prio, _r get "summary", _details, _sd, _r get "pos"] call comspec_overwatch_connect_fnc_submitTacticalReport;
    ["ÉCHEC", "OK"] select (_ok isEqualType true && {_ok})
};
switch (_act) do {
    case "tab": { call _save; call _keepDraft; _ui set ["tab", _arg]; _ui set ["open", ""]; if (_arg isNotEqualTo "NEW") then { _ui set ["type", ""]; }; call _rerender; };
    case "type": {
        private _t = [_arg] call _def;
        if ((count _t) isEqualTo 0) exitWith {};
        // Raccourci : ce C.R. a déjà son app (Feux, JTAC, MEDEVAC du Médical, Logistique).
        if ((_t select 3) isEqualTo "") exitWith {
            (_t select 5) params ["_page", ["_tab", ""]];
            if (_page isEqualTo "MEDICAL" && {_tab isNotEqualTo ""}) then { (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) set ["medTab", _tab]; };
            [_page] call comspec_atak_native_fnc_navigate;
        };
        call _save;
        call _keepDraft;
        [_arg] call _openType;
        call _rerender;
    };
    case "go": {
        call _save;
        call _keepDraft;
        if (_arg isEqualTo "MEDICAL") then { (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) set ["medTab", "MEDEVAC"]; };
        [_arg] call comspec_atak_native_fnc_navigate;
    };
    case "back": { call _save; call _keepDraft; _ui set ["type", ""]; call _rerender; };
    case "prefill": {
        if ((count ([_arg] call _def)) isEqualTo 0 || {!(_val isEqualType createHashMap)}) exitWith {};
        call _save;
        call _keepDraft;
        // Valeurs en texte (les champs de la fiche sont des chaînes) ; un brouillon déjà commencé de ce C.R. est remplacé.
        private _from = createHashMap;
        { _from set [_x, [str _y, _y] select (_y isEqualType "")]; } forEach _val;
        _drafts deleteAt _arg;
        [_arg, _from] call _openType;
        call _rerender;
    };
    case "set": {
        call _save;
        _vals set [_arg, _val];
        _auto deleteAt _arg;
        _ui set ["touched", true];
        _ui set ["errors", (_ui getOrDefault ["errors", []]) - [_arg]];
        call _rerender;
    };
    case "here": { call _save; _vals set [_arg, [getPosASL player, 8] call comspec_atak_native_fnc_gridRef]; _auto deleteAt _arg; _ui set ["touched", true]; call _rerender; };
    case "autoKey": {
        call _save;
        private _t = [_ui getOrDefault ["type", ""]] call _def;
        private _f = (_t param [5, []]) select { (_x select 0) isEqualTo _arg };
        if ((count _f) > 0) then {
            private _res = [_t, [(_f select 0) param [7, ""]], true] call _applyAuto;
            if (((_res getOrDefault [(_f select 0) param [7, ""], ""]) isEqualTo "")) then { ["INFO", "Aucun ennemi connu de votre groupe à moins de 1,5 km", 4, 20] call comspec_atak_native_fnc_notify; };
        };
        call _rerender;
    };
    case "auto": {
        call _save;
        private _t = [_ui getOrDefault ["type", ""]] call _def;
        if ((count _t) isEqualTo 0) exitWith {};
        private _res = [_t, _toolIds getOrDefault [_arg, []], true] call _applyAuto;
        if (_arg isEqualTo "enemy" && {(_res getOrDefault ["enemyFound", ""]) isEqualTo ""}) then {
            ["INFO", "Aucun ennemi connu de votre groupe à moins de 1,5 km : visez-le ou faites-le repérer", 5, 20] call comspec_atak_native_fnc_notify;
        };
        _ui set ["touched", true];
        call _rerender;
    };
    case "clear": {
        private _code = _ui getOrDefault ["type", ""];
        _drafts deleteAt _code;
        [_code] call _openType;
        call _rerender;
    };
    case "draft": {
        call _save;
        _ui set ["touched", true];
        call _keepDraft;
        ["SUCCESS", "Brouillon enregistré (onglet BROUILLONS)", 3, 20] call comspec_atak_native_fnc_notify;
        call _rerender;
    };
    case "dropDraft": { _drafts deleteAt _arg; call _rerender; };
    case "photo": {
        call _save;
        private _open = !(_ui getOrDefault ["photoPick", false]);
        _ui set ["photoPick", _open];
        if (_open) then { ["list"] call comspec_atak_native_fnc_photoLibrary; };
        call _rerender;
    };
    case "photoSet": {
        call _save;
        private _p = (uiNamespace getVariable ["COMSPEC_ATAK_PhotoLib", []]) param [parseNumber _arg, []];
        if ((count _p) >= 2) then { _vals set ["photo", [_p select 0, _p select 1]]; _ui set ["touched", true]; };
        _ui set ["photoPick", false];
        call _rerender;
    };
    case "photoClear": { call _save; _vals deleteAt "photo"; call _rerender; };
    case "pick": {
        call _save;
        call _keepDraft;
        _ui set ["pick", _arg];
        (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) set ["reportPick", _arg];
        ["MAP"] call comspec_atak_native_fnc_navigate;
        ["INFO", "Touchez la carte pour placer la position du compte rendu", 4, 20] call comspec_atak_native_fnc_notify;
    };
    case "picked": {
        private _k = _ui getOrDefault ["pick", ""];
        if (_k isNotEqualTo "") then { _vals set [_k, [_arg, 8] call comspec_atak_native_fnc_gridRef]; _auto deleteAt _k; _ui set ["touched", true]; };
        _ui set ["pick", ""];
        ["REPORTS"] call comspec_atak_native_fnc_navigate;
    };
    case "send": {
        call _save;
        private _t = [_ui getOrDefault ["type", ""]] call _def;
        if ((count _t) isEqualTo 0 || {(_t select 3) isEqualTo ""}) exitWith {};
        _t params ["_code", "_label", "", "_webType", "", "_fields", "_m"];
        // Contrôle : lignes requises, nombres entiers, grilles lisibles.
        private _bad = [];
        private _why = [];
        {
            _x params ["_k", "_kind", "_flabel", ["_farg", ""], "", ["_tag", ""], ["_req", false]];
            private _v = _vals getOrDefault [_k, ""];
            if !(_v isEqualType "") then { _v = str _v; };
            _v = trim _v;
            private _name = [format ["%1 · %2", _tag, _flabel], _flabel] select (_tag isEqualTo "");
            if (_name isEqualTo "") then { _name = _k; };
            switch (true) do {
                case (_kind isEqualTo "sec"): {};
                case (_req && {_kind isEqualTo "big"} && {!(_k in _vals)}): { _bad pushBack _k; _why pushBack (_name call comspec_atak_native_fnc_tr); };
                case (_req && {_kind in ["edit", "memo", "num", "dtg", "grid", "dir"]} && {_v isEqualTo ""}): { _bad pushBack _k; _why pushBack (_name call comspec_atak_native_fnc_tr); };
                case (_kind isEqualTo "num" && {_v isNotEqualTo ""} && {!(_v regexMatch "^[0-9]{1,4}$")}): { _bad pushBack _k; _why pushBack ((format ["%1 (nombre)", _name]) call comspec_atak_native_fnc_tr); };
                case (_kind isEqualTo "grid" && {_v isNotEqualTo ""} && {
                    private _g = (_v splitString " ,.-") joinString "";
                    !(_g regexMatch "^[0-9]{6,10}$") || {((count _g) mod 2) isEqualTo 1}
                }): { _bad pushBack _k; _why pushBack ((format ["%1 (grille)", _name]) call comspec_atak_native_fnc_tr); };
            };
        } forEach _fields;
        if ((count _bad) > 0) exitWith {
            _ui set ["errors", _bad];
            ["WARNING", format ["Il manque : %1", (_why select [0, 4]) joinString ", "], 6, 30] call comspec_atak_native_fnc_notify;
            call _rerender;
        };
        private _lines = [];
        private _grid = "";
        private _secT = "";
        {
            _x params ["_k", "_kind", "_flabel", ["_farg", ""], "", ["_tag", ""]];
            // Paragraphe sans libellé (FRAGO, SITREP) : il prend le titre de sa section, sans son numéro.
            if (_kind isEqualTo "sec") then { _secT = trim ((_flabel splitString "·") param [1, _flabel]); };
            if (_flabel isEqualTo "") then { _flabel = [_secT, _tag] select (_secT isEqualTo ""); };
            if (_kind isNotEqualTo "sec") then {
                private _v = _vals getOrDefault [_k, ""];
                if (_v isEqualTo "" && {_kind in ["seg", "combo", "level"]}) then { _v = _farg select 0; };
                if (_v isEqualTo "" && {_kind isEqualTo "big"}) then { _v = (_farg select 0) select 0; };
                if (_kind isEqualTo "big" && {!(_k in _vals)}) then { _v = ""; };
                if !(_v isEqualType "") then { _v = str _v; };
                _v = trim ((_v splitString toString [13, 10]) joinString " / ");
                // @zone : zone de l'app Zones sous ma position (comme dans les messages).
                if ((_v find "@zone") >= 0) then { _v = [_v, "@zone", ["citeAt", getPosASL player] call comspec_atak_native_fnc_zoneAction] call CBA_fnc_replace; };
                if (_kind isEqualTo "grid" && {_grid isEqualTo ""} && {_v isNotEqualTo ""}) then { _grid = _v; };
                if (_v isNotEqualTo "") then { _lines pushBack [_k, [_flabel, _k] select (_flabel isEqualTo ""), _v, _tag]; };
            };
        } forEach _fields;
        // Un C.R. vide n'apprend rien à personne.
        if (({ !((_x select 0) in ["grid", "location", "time", "end", "start"]) } count _lines) isEqualTo 0) exitWith {
            ["WARNING", "Remplissez au moins une ligne du compte rendu", 4, 30] call comspec_atak_native_fnc_notify;
        };
        private _pos = getPosASL player;
        private _g = (_grid splitString " ,.-") joinString "";
        if (_g isNotEqualTo "") then {
            private _p = ([_g] call BIS_fnc_gridToPos) param [0, []];
            if ((count _p) >= 2) then { _pos = [_p select 0, _p select 1, 0]; };
        };
        if (_grid isEqualTo "") then { _grid = [_pos, 8] call comspec_atak_native_fnc_gridRef; };
        private _prio = _vals getOrDefault ["prio", _m getOrDefault ["prio", "ROUTINE"]];
        private _dest = _vals getOrDefault ["dest", "CAMP"];
        // Résumé : type, grille et les trois premiers éléments renseignés.
        private _head = (_lines select { !((_x select 0) in ["grid", "location", "time", "end", "start", "impact"]) }) apply { _x select 2 };
        _head resize ((count _head) min 3);
        private _summary = format ["%1 · %2%3", _label, _grid, (_head apply { format [" · %1", (_x select [0, 60])] }) joinString ""];
        private _ph = _vals getOrDefault ["photo", []];
        private _r = createHashMapFromArray [
            ["id", format ["CR%1%2", floor (random 9000) + 1000, floor (diag_tickTime * 10) mod 1000]],
            ["code", _code], ["label", _label], ["tag", _m getOrDefault ["tag", _code]], ["web_type", _webType], ["prio", _prio], ["dest", _dest],
            ["by", [player, true] call comspec_atak_native_fnc_unitCallsign], ["uid", getPlayerUID player],
            ["time", [dayTime, "HH:MM"] call BIS_fnc_timeToString], ["grid", _grid], ["pos", [_pos select 0, _pos select 1, 0]],
            ["summary", _summary], ["lines", _lines], ["grp", netId group player], ["web", ""],
            ["jnet", [_vals getOrDefault ["jnet", "ÉCHELON SUP."], ""] select !(_m getOrDefault ["jnet", false])],
            ["photo", _ph param [1, ""]]
        ];
        _list set [_r get "id", _r];
        if (_dest isNotEqualTo "AUCUNE") then {
            [{ params ["_r", "_side"]; ["comspec_atak_native_sideEvent", ["comspec_atak_native_report", [_r, _side], _side]] call CBA_fnc_serverEvent; }, [+_r, str side group player], _label, 2] call comspec_atak_native_fnc_netSend;
        };
        // App Zones : contact, SALUTE ou SPOTREP dans une zone affichée : la zone passe « ennemi signalé ».
        if (_code in ["CONTACT", "SALUTE", "SPOTREP"]) then {
            private _val = { params ["_k"]; private _i = _lines findIf { (_x select 0) isEqualTo _k }; [(_lines select _i) select 2, ""] select (_i < 0) };
            ["contact", [_pos, "UNK", ((["size"] call _val) + (["count"] call _val)) select [0, 12], _summary]] call comspec_atak_native_fnc_zoneAction;
        };
        _r set ["web", [_r] call _toWeb];
        // Photo jointe : elle part vers la photothèque d'Athena, légendée avec le C.R.
        if ((count _ph) >= 2 && {!isNil "comspec_overwatch_connect_fnc_captureReconImage"}) then {
            [_ph select 0, format ["%1 · %2 · %3", _r get "tag", _r get "by", _grid], "CTAB", "", true, false, false] call comspec_overwatch_connect_fnc_captureReconImage;
        };
        ["SUCCESS", format ["%1 transmis%2", _label, switch (_r get "web") do {
            case "OK": { " · enregistré sur Athena" };
            case "LOCAL": { " au camp" };
            case "HORS LIGNE": { " en jeu · Athena non connecté" };
            default { " en jeu · Athena n'a pas répondu (RENVOYER dans Envoyés)" };
        }], 5, 45] call comspec_atak_native_fnc_notify;
        _drafts deleteAt _code;
        _ui set ["vals", createHashMap];
        _ui set ["auto", createHashMap];
        _ui set ["errors", []];
        _ui set ["touched", false];
        _ui set ["type", ""];
        _ui set ["tab", "SENT"];
        _ui set ["open", _r get "id"];
        call _rerender;
    };
    case "mobile": {
        // « Sur mon téléphone » (2.9.39) : fiche remplie sur le vrai téléphone (page Athena /m/cr), reçue par l'avis
        // « mobile_report » (fn_adminNotices) : ["MCR1", code, [[clé, valeur]...]], lu par parseSimpleArray (aucun code
        // exécuté). Envoyée par « send » comme une fiche remplie ici ; la fiche en cours du joueur est gardée telle quelle.
        if !(_arg isEqualType "") exitWith {};
        if ((_arg select [0, 7]) isNotEqualTo "[""MCR1""") exitWith {};
        private _a = parseSimpleArray _arg;
        if !(_a isEqualType []) exitWith {};
        _a params ["", ["_code", ""], ["_pairs", []]];
        private _t = [_code] call _def;
        if ((count _t) isEqualTo 0 || {(_t select 3) isEqualTo ""} || {!(_pairs isEqualType [])}) exitWith {};
        call _save;
        call _keepDraft;
        private _keepUi = [_ui getOrDefault ["type", ""], +_vals, +_auto, _ui getOrDefault ["touched", false], +(_ui getOrDefault ["errors", []]),
            _ui getOrDefault ["tab", "NEW"], _ui getOrDefault ["open", ""], _ui getOrDefault ["photoPick", false]];
        private _oldDraft = _drafts getOrDefault [_code, []];
        private _mv = createHashMap;
        { if (_x isEqualType [] && {(_x param [0, 0]) isEqualType ""} && {(_x param [1, 0]) isEqualType ""}) then { _mv set [_x select 0, _x select 1]; }; } forEach _pairs;
        // Heures (genre dtg) : celle du jeu, comme la proposition d'une fiche ouverte ici.
        private _dtgKeys = ((_t select 5) select { (_x select 1) isEqualTo "dtg" && {((_mv getOrDefault [_x select 0, ""]) isEqualTo "")} }) apply { _x select 0 };
        if (_dtgKeys isNotEqualTo []) then {
            private _now = (([["dtg"]] call comspec_atak_native_fnc_reportAuto) getOrDefault ["dtg", ""]);
            { _mv set [_x, _now]; } forEach _dtgKeys;
        };
        _ui set ["type", _code];
        _ui set ["vals", _mv];
        _ui set ["auto", createHashMap];
        _ui set ["errors", []];
        _ui set ["mobile", true];
        private _before = count _list;
        ["send"] call comspec_atak_native_fnc_reportAction;
        private _sent = (count _list) > _before;
        // Accusé « envoyé » vers Athena (fn_adminNotices) : seulement si la fiche est partie.
        missionNamespace setVariable ["COMSPEC_ATAK_MobileSent", _sent];
        _ui set ["mobile", false];
        _keepUi params ["_kType", "_kVals", "_kAuto", "_kTouched", "_kErr", "_kTab", "_kOpen", "_kPick"];
        { _ui set [_x select 0, _x select 1]; } forEach [["type", _kType], ["vals", _kVals], ["auto", _kAuto], ["touched", _kTouched], ["errors", _kErr], ["tab", _kTab], ["open", _kOpen], ["photoPick", _kPick]];
        if ((count _oldDraft) > 0) then { _drafts set [_code, _oldDraft]; };
        if (!_sent) then {
            // Fiche incomplète pour l'ATAK : gardée en brouillon, à finir ici.
            _drafts set [_code, [_mv, [dayTime, "HH:MM"] call BIS_fnc_timeToString, createHashMap]];
            ["WARNING", "Compte rendu reçu de votre téléphone, à compléter ici (onglet BROUILLONS)", 8, 45, "REPORTS"] call comspec_atak_native_fnc_notify;
        };
        call _rerender;
    };
    case "recv": {
        _arg params [["_r", createHashMap], ["_side", ""]];
        if !(_r isEqualType createHashMap) exitWith {};
        if (_side isNotEqualTo str side group player) exitWith {};
        if ((_r getOrDefault ["dest", "CAMP"]) isEqualTo "MON GROUPE" && {(_r getOrDefault ["grp", ""]) isNotEqualTo netId group player}) exitWith {};
        private _id = _r getOrDefault ["id", ""];
        if (_id isEqualTo "" || {_id in _list}) exitWith {};
        _list set [_id, _r];
        if ((_r getOrDefault ["uid", ""]) isNotEqualTo getPlayerUID player) then {
            _r set ["new", true];
            private _urgent = (_r getOrDefault ["prio", ""]) in ["IMMÉDIAT", "FLASH"];
            [["TACTICAL", "WARNING"] select _urgent, format ["%1%2 de %3 · %4", ["", (_r get "prio") + " · "] select _urgent, _r getOrDefault ["label", "C.R."], _r getOrDefault ["by", "?"], _r getOrDefault ["grid", ""]], [5, 8] select _urgent, [40, 70] select _urgent] call comspec_atak_native_fnc_notify;
        };
        call _rerender;
    };
    case "rcpt": {
        _arg params [["_id", ""], ["_side", ""], ["_uid", ""], ["_name", "?"], ["_kind", "LU"], ["_at", ""]];
        private _r = _list getOrDefault [_id, createHashMap];
        if ((count _r) isEqualTo 0 || {(_r getOrDefault ["uid", ""]) isNotEqualTo getPlayerUID player} || {_uid isEqualTo getPlayerUID player}) exitWith {};
        private _rc = _r getOrDefault ["rcpt", createHashMap];
        // Un « lu » n'efface jamais un accusé de réception.
        if (((_rc getOrDefault [_uid, []]) param [1, ""]) isEqualTo "ACK" && {_kind isEqualTo "LU"}) exitWith {};
        _rc set [_uid, [_name, _kind, _at]];
        _r set ["rcpt", _rc];
        if (_kind isEqualTo "ACK" && {!(missionNamespace getVariable ["COMSPEC_ATAK_Replaying", false])}) then { ["INFO", format ["%1 a accusé réception de votre %2", _name, _r getOrDefault ["label", "C.R."]], 4, 15] call comspec_atak_native_fnc_notify; };
        call _rerender;
    };
    case "open": {
        call _save;
        _ui set ["open", ["", _arg] select ((_ui getOrDefault ["open", ""]) isNotEqualTo _arg)];
        private _r = _list getOrDefault [_arg, createHashMap];
        if ((_r getOrDefault ["new", false]) && {(_r getOrDefault ["uid", ""]) isNotEqualTo getPlayerUID player}) then {
            _r set ["new", false];
            [_r, "LU"] call _sendRcpt;
        };
        call _rerender;
    };
    case "ack": {
        private _r = _list getOrDefault [_arg, createHashMap];
        if ((count _r) isEqualTo 0) exitWith {};
        _r set ["new", false];
        _r set ["acked", true];
        [_r, "ACK"] call _sendRcpt;
        ["SUCCESS", format ["Réception accusée à %1", _r getOrDefault ["by", "?"]], 3, 15] call comspec_atak_native_fnc_notify;
        call _rerender;
    };
    case "map": {
        private _p = (_list getOrDefault [_arg, createHashMap]) getOrDefault ["pos", []];
        if ((count _p) < 2) exitWith {};
        ["MAP"] call comspec_atak_native_fnc_navigate;
        [{ [_this, 0.05] call comspec_atak_native_fnc_mapCenter; }, [_p select 0, _p select 1]] call comspec_atak_native_fnc_nextFrame;
    };
    case "del": { _list deleteAt _arg; call _rerender; };
    case "web": {
        private _r = _list getOrDefault [_arg, createHashMap];
        if ((count _r) isEqualTo 0) exitWith {};
        _r set ["web", [_r] call _toWeb];
        call _rerender;
    };
    case "dup": {
        private _r = _list getOrDefault [_arg, createHashMap];
        private _code = _r getOrDefault ["code", ""];
        if ((count ([_code] call _def)) isEqualTo 0) exitWith {};
        call _save;
        call _keepDraft;
        private _from = createHashMap;
        { _from set [_x select 0, _x select 2]; } forEach (_r getOrDefault ["lines", []]);
        _from set ["prio", _r getOrDefault ["prio", "ROUTINE"]];
        [_code, _from] call _openType;
        call _rerender;
    };
    case "orderAck": {
        (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) set ["selectedTask", _arg];
        ["ACK"] call comspec_atak_native_fnc_taskAction;
        call _rerender;
    };
};
true
