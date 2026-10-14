/*
    App Plan (carte de briefing) : commandes de l'interface, couches, historique, salles, plans enregistrés, export.
    Couches : "S" plan partagé du camp, "R" salle collaborative rejointe (code), "D" brouillon local.
    Params : [action, argument]
      "ui"                        -> état de l'interface (uiNamespace COMSPEC_ATAK_PlanUi)
      "shared" / "room" / "draft" -> éléments du plan du camp / de la salle / du brouillon ; "items" couche -> éléments
      "can" [couche]              -> droit de modifier la couche (camp : fn_planServer « can » ; salle : avoir le code)
      "tab" cat | "tool" type | "color" clé | "width" n | "dash" | "arrow" | "pen" | "target" couche | "view" vue | "layer"
      "pal"                       ouvre / ferme la palette d'outils ; "menu" nom ("EXPORT", "ROOM" ; "" : fermer)
      "ctxOpen" [position, id, couche] | "ctxAct" clé     menu du clic droit (fn_planUi le dessine)
      "help" | "tuto" étape | "tutoNext" | "tutoPrev" | "tutoEnd"   aide (touche H) et tutoriel (fn_planHelp)
      "toDraft" [éléments]        ajoute au brouillon (plan partagé sans réponse du serveur) ; "pending" -> éléments en attente du serveur
      "commit" [points]           crée un élément avec l'outil actif (texte, couleur, tirets, flèche, épaisseur choisis)
      "finish" / "cancel"         termine / abandonne le tracé en cours (clic droit, double clic, Entrée, boutons)
      "undo" / "redo"             ANNULER (dernier point du tracé, sinon dernière modification) / RÉTABLIR
      "histPush" [repère, salle, avant, après]   modification faite (serveur ou brouillon) rangée dans l'historique
      "select" [id, couche] ; "selItem" -> élément sélectionné ; "selSet" [champ, valeur] (texte, desc, color)
      "delete" ; "toggleClear" (CLEARED <-> UNCLEARED) ; "dup" (copie décalée) ; "copyTo" couche
      "clearTarget"               efface le brouillon et la couche partagée visée si autorisé (deux appuis, annulable)
      "roomNew" | "roomJoin" code | "roomLeave" | "roomCopy" | "roomInput" texte   salle collaborative
      "save" source | [source, nom]   enregistre une couche sous un nom (profil)
      "load" i | "preview" i | "previewOff" | "previewAdd" | "libDel" i     plans enregistrés
      "publish" [source, remplacer]   brouillon ("D"), salle ("R"), aperçu ("P") ou plan enregistré (index) vers le camp
      "export" source | "exportText" source | "import"   code « COMSPECPLAN1|[carte, nom, éléments] » / texte de briefing
      "expSrc"                    change la source du menu EXPORTER
      "text" texte                texte des prochains éléments ; "chrome" (barres et menus) ; "rerender" (page entière)
      "clip" texte                copie dans le presse-papiers (aussi en multijoueur, par la DLL du mod)
    Brouillon : missionNamespace COMSPEC_ATAK_PlanDraft (gardé dans le profil, COMSPEC_ATAK_PlanDraftSave).
    Plans enregistrés : profil COMSPEC_ATAK_PlanLib = [[nom, carte, éléments, date]...].
    Historique : uiNamespace COMSPEC_ATAK_PlanUndo / PlanRedo = [[couche, salle, avant, après]...] (40 au plus).
*/
params [["_act", ""], ["_arg", []]];
private _ui = uiNamespace getVariable "COMSPEC_ATAK_PlanUi";
if (isNil "_ui") then {
    _ui = createHashMapFromArray [["view", "MAP"], ["cat", "AREA"], ["tool", ""], ["color", ""], ["width", 2], ["dash", false], ["arrow", false],
        ["pen", false], ["target", "S"], ["draft", []], ["sel", ""], ["selLayer", ""], ["text", ""], ["pal", true], ["menu", ""],
        ["ctx", createHashMap], ["expSrc", ""], ["help", false], ["tuto", -1]];
    uiNamespace setVariable ["COMSPEC_ATAK_PlanUi", _ui];
};
private _cat = [] call comspec_atak_native_fnc_planCatalog;
private _rerender = { [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "PLAN") then { ["PLAN"] call comspec_atak_native_fnc_pageRender; }; }] call CBA_fnc_execNextFrame; };
// Barres, palette et menus redessinés sans toucher la carte (elle garde sa vue, aucun saut ni clignotement).
private _chrome = {
    [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "PLAN") then { ["build"] call comspec_atak_native_fnc_planUi; }; }] call CBA_fnc_execNextFrame;
};
private _room = uiNamespace getVariable ["COMSPEC_ATAK_PlanRoomId", ""];
private _varOf = { params ["_layer", ["_r", _room]]; if (_layer isEqualTo "R") then { "COMSPEC_ATAK_PlanRoom_" + _r } else { format ["COMSPEC_ATAK_Plan_%1", side group player] } };
private _sharedVar = ["S"] call _varOf;
private _shared = { (missionNamespace getVariable [_sharedVar, [0, [], ""]]) param [1, []] };
private _roomItems = { if (_room isEqualTo "") then { [] } else { (missionNamespace getVariable [["R"] call _varOf, [0, [], ""]]) param [1, []] } };
private _draftGet = {
    private _d = missionNamespace getVariable "COMSPEC_ATAK_PlanDraft";
    if (isNil "_d") then {
        // Brouillon de la session précédente, sur la même carte.
        (profileNamespace getVariable ["COMSPEC_ATAK_PlanDraftSave", []]) params [["_w", ""], ["_its", []]];
        _d = [[], +_its] select (_w isEqualTo worldName && {_its isEqualType []});
        missionNamespace setVariable ["COMSPEC_ATAK_PlanDraft", _d];
    };
    _d
};
private _itemsOf = {
    params ["_layer"];
    switch (_layer) do { case "S": { call _shared }; case "R": { call _roomItems }; case "D": { call _draftGet }; default { [] }; }
};
// Repère d'historique : "" action nouvelle (efface RÉTABLIR), "U" annulation, "R" rétablissement, "-" hors historique.
private _draftSet = {
    params ["_its", ["_tag", ""]];
    if (_tag isNotEqualTo "-") then {
        ([objNull, "diff", [call _draftGet, _its]] call comspec_atak_native_fnc_planServer) params ["_b", "_a"];
        if ((count _b) + (count _a) > 0) then { ["histPush", [_tag, "", _b, _a, "D"]] call comspec_atak_native_fnc_planAction; };
    };
    missionNamespace setVariable ["COMSPEC_ATAK_PlanDraft", _its];
    profileNamespace setVariable ["COMSPEC_ATAK_PlanDraftSave", [worldName, _its]];
    // Écriture du profil regroupée (5 s) : un dessin à main levée enregistrait tout le profil à chaque trait.
    if !(missionNamespace getVariable ["COMSPEC_ATAK_PlanSavePending", false]) then {
        missionNamespace setVariable ["COMSPEC_ATAK_PlanSavePending", true];
        [{ missionNamespace setVariable ["COMSPEC_ATAK_PlanSavePending", false]; saveProfileNamespace; }, [], 5] call CBA_fnc_waitAndExecute;
    };
};
private _newId = {
    private _n = (uiNamespace getVariable ["COMSPEC_ATAK_PlanSeqLocal", 0]) + 1;
    uiNamespace setVariable ["COMSPEC_ATAK_PlanSeqLocal", _n];
    format ["d%1_%2", floor (diag_tickTime * 10) mod 100000, _n]
};
private _canOf = {
    params [["_layer", "S"]];
    switch (_layer) do {
        case "S": { [objNull, "can"] call comspec_atak_native_fnc_planServer };
        case "R": { _room isNotEqualTo "" && {!isNil { missionNamespace getVariable (["R"] call _varOf) }} };
        default { true };
    }
};
private _can = { ["S"] call _canOf };
private _srv = {
    params ["_op", ["_a", []], ["_tag", ""], ["_r", ""]];
    ["comspec_atak_native_planSrv", [player, _op, _a, _tag, _r]] call CBA_fnc_serverEvent;
};
private _srvOnF = { isServer || {missionNamespace getVariable ["COMSPEC_ATAK_PlanSrvOn", false]} || {!isNil { missionNamespace getVariable _sharedVar }} };
private _lib = { +(profileNamespace getVariable ["COMSPEC_ATAK_PlanLib", []]) };
private _layerName = { params ["_l"]; switch (_l) do { case "S": { "plan du camp" }; case "R": { format ["salle %1", _room] }; case "D": { "brouillon" }; default { "aperçu" }; } };
// Ajout à une couche (partagée : par le serveur, refusé sans droit -> brouillon).
private _addItems = {
    params ["_list", ["_layer", "S"], ["_replace", false], ["_name", ""]];
    if ((count _list) isEqualTo 0) exitWith {};
    if (_layer isEqualType true) then { _layer = ["D", "S"] select _layer; };
    // Serveur sans le mod natif (mod chargé seulement chez les joueurs) : personne n'écrit le plan partagé.
    // Le drapeau du serveur peut manquer (solo, partie hébergée, drapeau pas encore reçu) : on essaie quand même
    // le serveur, et seulement sans réponse sous 5 s les éléments vont au brouillon.
    private _srvOn = call _srvOnF;
    // Serveur déjà resté muet sans drapeau dans cette mission : directement au brouillon.
    private _noSrv = !_srvOn && {missionNamespace getVariable ["COMSPEC_ATAK_PlanNoSrv", false]};
    if (_layer in ["S", "R"] && {!_noSrv} && {[_layer] call _canOf}) then {
        private _var = [_layer] call _varOf;
        private _rev = (missionNamespace getVariable [_var, [0, [], ""]]) param [0, 0];
        [["merge", "replace"] select _replace, [_list, _name], "", ["", _room] select (_layer isEqualTo "R")] call _srv;
        // Affichés en attente jusqu'à la réponse du serveur ; sans réponse (refus, serveur muet), gardés dans le brouillon.
        private _pend = uiNamespace getVariable ["COMSPEC_ATAK_PlanPending", []];
        _pend append _list;
        uiNamespace setVariable ["COMSPEC_ATAK_PlanPending", _pend];
        [{ ((missionNamespace getVariable [_this select 0, [0]]) param [0, 0]) isNotEqualTo (_this select 1) }, {
            uiNamespace setVariable ["COMSPEC_ATAK_PlanPending", []];
        }, [_var, _rev, _list, _srvOn], 5, {
            uiNamespace setVariable ["COMSPEC_ATAK_PlanPending", []];
            ["WARN", "PLAN", format ["Plan partagé : pas de réponse du serveur (%1 éléments gardés dans le brouillon)", count (_this select 2)]] call comspec_atak_native_fnc_log;
            [["Serveur sans le mod ATAK natif : plan partagé indisponible, éléments placés dans votre brouillon", "Plan partagé : pas de réponse du serveur, gardé dans votre brouillon"] select (_this select 3), 5] call comspec_atak_native_fnc_toast;
            if !(_this select 3) then { missionNamespace setVariable ["COMSPEC_ATAK_PlanNoSrv", true]; };
            ["toDraft", _this select 2] call comspec_atak_native_fnc_planAction;
        }] call CBA_fnc_waitUntilAndExecute;
    } else {
        if (_layer in ["S", "R"]) then {
            [["Édition du plan partagé réservée : éléments placés dans votre brouillon", "Serveur sans le mod ATAK natif : plan partagé indisponible, éléments placés dans votre brouillon"] select _noSrv, 5] call comspec_atak_native_fnc_toast;
        };
        private _d = [[], +(call _draftGet)] select !_replace;
        { private _c = +_x; _c set [0, call _newId]; if ((_c select 9) isEqualTo "") then { _c set [9, name player]; }; _d pushBack _c; } forEach _list;
        [_d] call _draftSet;
    };
};
// Modifie ou retire un élément d'une couche (serveur pour le partagé, directement pour le brouillon).
private _updItem = {
    params ["_layer", "_id", "_it"];
    if (_layer isEqualTo "D") exitWith {
        private _d = +(call _draftGet);
        private _i = _d findIf { (_x select 0) isEqualTo _id };
        if (_i < 0) exitWith {};
        if ((count _it) isEqualTo 0) then { _d deleteAt _i; } else { _d set [_i, _it]; };
        [_d] call _draftSet;
    };
    if !([_layer] call _canOf) exitWith { ["Édition du plan partagé réservée (réglage serveur)", 4] call comspec_atak_native_fnc_toast; };
    private _r = ["", _room] select (_layer isEqualTo "R");
    if ((count _it) isEqualTo 0) then { ["del", [_id], "", _r] call _srv; } else { ["upd", [_id, _it], "", _r] call _srv; };
};
// Chaîne portable : préfixe de version, puis tableau simple (parseSimpleArray : aucun code exécuté à l'import).
private _clipSet = {
    params ["_s"];
    copyToClipboard _s;
    // En multijoueur copyToClipboard est sans effet : la DLL du mod écrit le presse-papiers Windows (par morceaux).
    if (((["ClipBegin"] call comspec_atak_native_fnc_extensionCall) select [0, 2]) isEqualTo "OK") then {
        for "_i" from 0 to ((count _s) - 1) step 4000 do { ["ClipAppend", [_s select [_i, 4000]]] call comspec_atak_native_fnc_extensionCall; };
        ["ClipCommit"] call comspec_atak_native_fnc_extensionCall;
    };
};
private _clipGet = {
    private _s = copyFromClipboard;
    if (_s isEqualTo "") then {
        private _off = 0;
        private _total = 1;
        while { _off < _total && {_off < 200000} } do {
            private _r = ["ClipGet", [str _off]] call comspec_atak_native_fnc_extensionCall;
            if ((_r select [0, 3]) isNotEqualTo "OK|") exitWith {};
            private _rest = _r select [3];
            private _bar = _rest find "|";
            if (_bar < 0) exitWith {};
            _total = parseNumber (_rest select [0, _bar]);
            private _chunk = _rest select [_bar + 1];
            if (_chunk isEqualTo "") exitWith {};
            _s = _s + _chunk;
            _off = _off + (count _chunk);
        };
    };
    _s
};
private _sourceItems = {
    params ["_src"];
    switch (true) do {
        case (_src isEqualTo "S"): { +(call _shared) };
        case (_src isEqualTo "R"): { +(call _roomItems) };
        case (_src isEqualTo "D"): { +(call _draftGet) };
        case (_src isEqualTo "P"): { +((uiNamespace getVariable ["COMSPEC_ATAK_PlanPreview", []]) param [1, []]) };
        case (_src isEqualType 0): { +(((call _lib) param [_src, []]) param [2, []]) };
        default { [] };
    }
};
private _sourceName = {
    params ["_src"];
    if (_src isEqualType 0) exitWith { ((call _lib) param [_src, []]) param [0, "Plan"] };
    switch (_src) do { case "S": { "Plan du camp" }; case "R": { format ["Salle %1", _room] }; case "D": { "Brouillon" }; default { "Plan" }; }
};
private _selFind = {
    private _id = _ui get "sel";
    if (_id isEqualTo "") exitWith { [] };
    private _list = [_ui get "selLayer"] call _itemsOf;
    private _i = _list findIf { (_x select 0) isEqualTo _id };
    if (_i < 0) exitWith { [] };
    +(_list select _i)
};
private _stamp = { (systemTime select [0, 5]) apply { [str _x, "0" + str _x] select (_x < 10) } call { format ["%1-%2-%3 %4:%5", _this select 0, _this select 1, _this select 2, _this select 3, _this select 4] } };

switch (_act) do {
    case "ui": { _ui };
    case "shared": { call _shared };
    case "room": { call _roomItems };
    case "draft": { call _draftGet };
    case "items": { [_arg] call _itemsOf };
    case "can": { [["S", _arg] select (_arg isEqualType "")] call _canOf };
    case "roomId": { _room };
    case "tab": { _ui set ["cat", _arg]; _ui set ["pal", true]; call _chrome; };
    case "pal": { _ui set ["pal", !(_ui get "pal")]; _ui set ["menu", ""]; call _chrome; };
    case "menu": { _ui set ["menu", [_arg, ""] select ((_ui get "menu") isEqualTo _arg)]; _ui set ["ctx", createHashMap]; call _chrome; };
    case "tool": {
        private _def = (_cat get "kinds") getOrDefault [_arg, []];
        if ((_ui get "tool") isEqualTo _arg || {(count _def) isEqualTo 0}) then {
            _ui set ["tool", ""];
        } else {
            // Réglages par défaut du type choisi (couleur, tirets, flèche ; crayon pour le dessin libre).
            _ui set ["tool", _arg];
            _ui set ["color", _def select 4];
            _ui set ["dash", _def select 6];
            _ui set ["arrow", _def select 7];
            if (_arg isEqualTo "FREE") then { _ui set ["pen", true]; };
            _ui set ["sel", ""];
        };
        _ui set ["draft", []];
        _ui set ["menu", ""];
        _ui set ["ctx", createHashMap];
        call _chrome;
    };
    case "color": { _ui set ["color", _arg]; call _chrome; };
    case "width": { _ui set ["width", _arg]; call _chrome; };
    case "dash": { _ui set ["dash", !(_ui get "dash")]; call _chrome; };
    case "arrow": { _ui set ["arrow", !(_ui get "arrow")]; call _chrome; };
    case "pen": { _ui set ["pen", !(_ui get "pen")]; _ui set ["draft", []]; call _chrome; };
    case "target": {
        if (_arg isEqualTo "R" && {_room isEqualTo ""}) exitWith { _ui set ["menu", "ROOM"]; call _chrome; };
        _ui set ["target", _arg];
        _ui set ["menu", ""];
        [format ["Nouveaux éléments : %1", [_arg] call _layerName], 2] call comspec_atak_native_fnc_toast;
        call _chrome;
    };
    case "view": { _ui set ["view", _arg]; _ui set ["menu", ""]; call _rerender; };
    case "layer": {
        profileNamespace setVariable ["COMSPEC_ATAK_PlanOnMap2", !(profileNamespace getVariable ["COMSPEC_ATAK_PlanOnMap2", true])];
        saveProfileNamespace;
        call _rerender;
    };
    case "toDraft": { [_arg, "D"] call _addItems; call _chrome; };
    case "pending": { uiNamespace getVariable ["COMSPEC_ATAK_PlanPending", []] };
    case "text": { _ui set ["text", (trim _arg) select [0, 40]]; };
    case "commit": {
        private _kind = _ui get "tool";
        private _def = (_cat get "kinds") getOrDefault [_kind, []];
        if ((count _def) isEqualTo 0) exitWith {};
        // Numéro automatique (bâtiments : CLEARED et UNCLEARED partagent la même suite ; CP, RP, TRP chacun la leur).
        private _num = 0;
        if (_kind in ["CLEARED", "UNCLEARED", "CP", "RP", "TRP"]) then {
            private _fam = [[_kind], ["CLEARED", "UNCLEARED"]] select (_kind in ["CLEARED", "UNCLEARED"]);
            { if ((_x select 1) in _fam) then { _num = _num max (_x select 8); }; } forEach ((call _shared) + (call _roomItems) + (call _draftGet));
            _num = _num + 1;
        };
        private _it = [objNull, "clean", ["", _kind, _arg apply { [_x select 0, _x select 1] }, _ui get "text", _ui get "color", _ui get "dash", _ui get "arrow", _ui get "width", _num, name player, ""]] call comspec_atak_native_fnc_planServer;
        if ((count _it) isEqualTo 0) exitWith {};
        [[_it], _ui get "target"] call _addItems;
        _ui set ["draft", []];
        playSound "ClickSoft";
        ["refresh"] call comspec_atak_native_fnc_planAction;
    };
    case "finish": {
        private _def = (_cat get "kinds") getOrDefault [_ui get "tool", []];
        private _pts = +(_ui get "draft");
        // Points doublés (double clic) retirés.
        private _clean = [];
        { if ((count _clean) isEqualTo 0 || {((_clean select -1) distance2D _x) > 0.5}) then { _clean pushBack _x; }; } forEach _pts;
        if ((count _def) > 0 && {(count _clean) >= 2}) then { ["commit", _clean] call comspec_atak_native_fnc_planAction; } else { _ui set ["draft", []]; ["refresh"] call comspec_atak_native_fnc_planAction; };
    };
    case "cancel": {
        if ((count (_ui get "draft")) > 0) then { _ui set ["draft", []]; ["refresh"] call comspec_atak_native_fnc_planAction; } else {
            if ((_ui get "tool") isNotEqualTo "") then { _ui set ["tool", ""]; call _chrome; };
        };
    };
    case "histPush": {
        _arg params [["_tag", ""], ["_r", ""], ["_b", []], ["_a", []], ["_layer", ""]];
        if (_layer isEqualTo "") then { _layer = ["R", "S"] select (_r isEqualTo ""); };
        private _undo = uiNamespace getVariable ["COMSPEC_ATAK_PlanUndo", []];
        private _redo = uiNamespace getVariable ["COMSPEC_ATAK_PlanRedo", []];
        private _e = [_layer, _r, _b, _a];
        switch (_tag) do {
            case "U": { _redo pushBack _e; };
            case "R": { _undo pushBack _e; };
            default { _undo pushBack _e; _redo = []; };
        };
        uiNamespace setVariable ["COMSPEC_ATAK_PlanUndo", _undo select [((count _undo) - 40) max 0]];
        uiNamespace setVariable ["COMSPEC_ATAK_PlanRedo", _redo select [((count _redo) - 40) max 0]];
        // Élément sélectionné retiré par la modification : sélection rendue.
        if ((_ui get "sel") isNotEqualTo "" && {(([_ui get "selLayer"] call _itemsOf) findIf { (_x select 0) isEqualTo (_ui get "sel") }) < 0}) then { _ui set ["sel", ""]; };
        call _chrome;
    };
    case "undo";
    case "redo": {
        if (_act isEqualTo "undo" && {(count (_ui get "draft")) > 0}) exitWith { private _d = _ui get "draft"; _d deleteAt ((count _d) - 1); ["refresh"] call comspec_atak_native_fnc_planAction; };
        private _key = ["COMSPEC_ATAK_PlanRedo", "COMSPEC_ATAK_PlanUndo"] select (_act isEqualTo "undo");
        private _st = uiNamespace getVariable [_key, []];
        if ((count _st) isEqualTo 0) exitWith { [["Rien à rétablir", "Rien à annuler"] select (_act isEqualTo "undo"), 2] call comspec_atak_native_fnc_toast; };
        private _e = _st deleteAt ((count _st) - 1);
        uiNamespace setVariable [_key, _st];
        _e params ["_layer", "_r", "_b", "_a"];
        // Retour à l'état « avant » de l'entrée : ce qu'elle a ajouté part, ce qu'elle a changé ou retiré revient.
        private _bIds = _b apply { _x select 0 };
        private _del = (_a select { !((_x select 0) in _bIds) }) apply { _x select 0 };
        private _tag = ["R", "U"] select (_act isEqualTo "undo");
        if (_layer isEqualTo "D") exitWith {
            [[objNull, "patchList", [+(call _draftGet), _del, _b]] call comspec_atak_native_fnc_planServer, _tag] call _draftSet;
            playSound "ClickSoft";
        };
        private _ok = if (_layer isEqualTo "R") then { !isNil { missionNamespace getVariable (["R", _r] call _varOf) } } else { call _can };
        if (!_ok) exitWith { [["Salle fermée : modification perdue", "Édition du plan partagé réservée (réglage serveur)"] select (_layer isEqualTo "S"), 3] call comspec_atak_native_fnc_toast; call _chrome; };
        ["patch", [_del, _b], _tag, _r] call _srv;
        playSound "ClickSoft";
    };
    case "select": {
        _arg params [["_id", ""], ["_layer", ""]];
        _ui set ["sel", _id];
        _ui set ["selLayer", _layer];
        _ui set ["ctx", createHashMap];
        _ui set ["menu", ""];
        call _chrome;
    };
    case "selItem": { call _selFind };
    case "selSet": {
        _arg params [["_field", ""], ["_v", ""]];
        private _it = call _selFind;
        if ((count _it) isEqualTo 0) exitWith {};
        private _i = createHashMapFromArray [["text", 3], ["color", 4], ["desc", 10]] getOrDefault [_field, -1];
        if (_i < 0) exitWith {};
        if (_field isNotEqualTo "color") then { _v = (trim _v) select [0, [40, 300] select (_field isEqualTo "desc")]; };
        if ((_it param [_i, ""]) isEqualTo _v) exitWith {};
        if ((count _it) < 11) then { _it resize 11; _it set [10, ""]; };
        _it set [_i, _v];
        [_ui get "selLayer", _ui get "sel", _it] call _updItem;
        if (_field isEqualTo "color") then { call _chrome; };
    };
    case "delete";
    case "toggleClear": {
        private _it = call _selFind;
        if ((count _it) isEqualTo 0) exitWith {};
        private _id = _ui get "sel";
        private _layer = _ui get "selLayer";
        if (_act isEqualTo "toggleClear") then {
            if !((_it select 1) in ["CLEARED", "UNCLEARED"]) exitWith {};
            private _to = ["CLEARED", "UNCLEARED"] select ((_it select 1) isEqualTo "CLEARED");
            _it set [1, _to];
            _it set [4, ((_cat get "kinds") get _to) select 4];
            if ((_it select 3) in ["CLEARED", "UNCLEARED"]) then { _it set [3, ""]; };
            [_layer, _id, _it] call _updItem;
        } else {
            [_layer, _id, []] call _updItem;
            _ui set ["sel", ""];
            [format ["Élément supprimé (%1) · Ctrl+Z pour annuler", [_layer] call _layerName], 3] call comspec_atak_native_fnc_toast;
        };
        call _chrome;
    };
    case "dup";
    case "copyTo": {
        private _it = call _selFind;
        if ((count _it) isEqualTo 0) exitWith {};
        private _c = +_it;
        _c set [0, ""];
        _c set [9, ""];
        private _to = _ui get "selLayer";
        if (_act isEqualTo "dup") then {
            // Copie décalée d'environ 20 pixels vers le bas à droite, visible tout de suite.
            private _map = (uiNamespace getVariable ["COMSPEC_ATAK_PlanCtrls", createHashMap]) getOrDefault ["map", controlNull];
            private _off = if (isNull _map) then { 30 } else { (["mpx", _map] call comspec_atak_native_fnc_planGfx) * 20 };
            _c set [2, (_c select 2) apply { [(_x select 0) + _off, (_x select 1) - _off] }];
        } else { _to = _arg; };
        [[_c], _to] call _addItems;
        [format ["Copié dans : %1", [_to] call _layerName], 2] call comspec_atak_native_fnc_toast;
        call _chrome;
    };
    case "ctxOpen": {
        _arg params ["_pos", ["_id", ""], ["_layer", ""]];
        if (_id isNotEqualTo "") then { _ui set ["sel", _id]; _ui set ["selLayer", _layer]; };
        _ui set ["ctx", createHashMapFromArray [["pos", _pos], ["id", _id], ["layer", _layer], ["at", diag_tickTime]]];
        _ui set ["menu", ""];
        playSound "ClickSoft";
        call _chrome;
    };
    case "ctxAct": {
        private _c = _ui get "ctx";
        _ui set ["ctx", createHashMap];
        private _pos = _c getOrDefault ["pos", []];
        playSound "ClickSoft";
        switch (_arg) do {
            case "CENTER": {
                private _map = (uiNamespace getVariable ["COMSPEC_ATAK_PlanCtrls", createHashMap]) getOrDefault ["map", controlNull];
                if (!isNull _map && {(count _pos) >= 2}) then { _map ctrlMapAnimAdd [0.3, ctrlMapScale _map, [_pos select 0, _pos select 1, 0]]; ctrlMapAnimCommit _map; };
            };
            case "COPY": {
                private _g = [_pos, 10] call comspec_atak_native_fnc_gridRef;
                [_g] call _clipSet;
                [format ["Grille %1 copiée", _g], 2] call comspec_atak_native_fnc_toast;
            };
            case "EDIT": { uiNamespace setVariable ["COMSPEC_ATAK_PlanFocusEdit", true]; };
            case "DUP": { ["dup"] call comspec_atak_native_fnc_planAction; };
            case "TOGGLE": { ["toggleClear"] call comspec_atak_native_fnc_planAction; };
            case "DELETE": { ["delete"] call comspec_atak_native_fnc_planAction; };
            case "TOD": { ["copyTo", "D"] call comspec_atak_native_fnc_planAction; };
            case "TOS": { ["copyTo", "S"] call comspec_atak_native_fnc_planAction; };
            case "TOR": { ["copyTo", "R"] call comspec_atak_native_fnc_planAction; };
            case "HELP": { ["help"] call comspec_atak_native_fnc_planAction; };
            default {
                // Pose rapide au point cliqué : NOTE, UNITH (ennemi), CP, RP.
                if (_arg in ["NOTE", "UNITH", "CP", "RP"] && {(count _pos) >= 2}) then {
                    private _prev = [_ui get "tool", _ui get "color", _ui get "dash", _ui get "arrow"];
                    private _def = (_cat get "kinds") get _arg;
                    _ui set ["tool", _arg]; _ui set ["color", _def select 4]; _ui set ["dash", false]; _ui set ["arrow", false];
                    ["commit", [[_pos select 0, _pos select 1]]] call comspec_atak_native_fnc_planAction;
                    _ui set ["tool", _prev select 0]; _ui set ["color", _prev select 1]; _ui set ["dash", _prev select 2]; _ui set ["arrow", _prev select 3];
                };
            };
        };
        call _chrome;
    };
    case "help": { _ui set ["help", !(_ui get "help")]; _ui set ["menu", ""]; _ui set ["ctx", createHashMap]; playSound "ClickSoft"; call _chrome; };
    case "tuto": { _ui set ["tuto", _arg]; _ui set ["help", false]; _ui set ["menu", ""]; _ui set ["ctx", createHashMap]; if (_arg >= 0) then { _ui set ["pal", true]; }; call _chrome; };
    case "tutoNext": { ["tuto", (_ui get "tuto") + 1] call comspec_atak_native_fnc_planAction; };
    case "tutoPrev": { ["tuto", ((_ui get "tuto") - 1) max 0] call comspec_atak_native_fnc_planAction; };
    case "tutoEnd": {
        profileNamespace setVariable ["COMSPEC_ATAK_PlanTuto", 1];
        saveProfileNamespace;
        ["tuto", -1] call comspec_atak_native_fnc_planAction;
    };
    case "clearTarget": {
        // Deux appuis en moins de 4 s (le bouton passe à « CONFIRMER ? ») : rien ne s'efface par erreur.
        if (diag_tickTime - (uiNamespace getVariable ["COMSPEC_ATAK_PlanClearArm", -10]) > 4) exitWith {
            uiNamespace setVariable ["COMSPEC_ATAK_PlanClearArm", diag_tickTime];
            ["Appuyez encore pour tout effacer (Ctrl+Z annule)", 3] call comspec_atak_native_fnc_toast;
            call _chrome;
            [{ if (diag_tickTime - (uiNamespace getVariable ["COMSPEC_ATAK_PlanClearArm", -10]) > 4) then { ["chrome"] call comspec_atak_native_fnc_planAction; }; }, [], 4.2] call CBA_fnc_waitAndExecute;
        };
        uiNamespace setVariable ["COMSPEC_ATAK_PlanClearArm", -10];
        // Tracé en cours, brouillon (toujours le mien) et couche partagée visée (camp, ou salle si c'est elle).
        _ui set ["draft", []];
        private _nd = count (call _draftGet);
        if (_nd > 0) then { [[]] call _draftSet; };
        private _layer = ["S", "R"] select ((_ui get "target") isEqualTo "R");
        private _ns = count ([_layer] call _itemsOf);
        private _msg = "";
        if (_ns > 0) then {
            if ([_layer] call _canOf) then {
                ["clear", [], "", ["", _room] select (_layer isEqualTo "R")] call _srv;
                _msg = format ["Effacé : %1 élément(s) (%2), %3 du brouillon · Ctrl+Z annule", _ns, [_layer] call _layerName, _nd];
            } else {
                _msg = format ["Brouillon effacé (%1 élément(s)) ; plan partagé : édition réservée", _nd];
            };
        } else { _msg = ["Plan déjà vide", format ["Brouillon effacé (%1 élément(s)) · Ctrl+Z annule", _nd]] select (_nd > 0); };
        [_msg, 4] call comspec_atak_native_fnc_toast;
        _ui set ["sel", ""];
        call _chrome;
    };
    case "roomNew": {
        if !(call _srvOnF) exitWith { ["Salle impossible : le serveur n'a pas le mod ATAK natif", 4] call comspec_atak_native_fnc_toast; };
        ["roomNew", [format ["Salle de %1", name player]]] call _srv;
        ["Création de la salle…", 2] call comspec_atak_native_fnc_toast;
    };
    case "roomInput": { uiNamespace setVariable ["COMSPEC_ATAK_PlanRoomInput", toUpper (trim _arg)]; };
    case "roomJoin": {
        private _id = toUpper (trim ([_arg, uiNamespace getVariable ["COMSPEC_ATAK_PlanRoomInput", ""]] select (_arg isEqualTo "" || {!(_arg isEqualType "")})));
        _id = (_id regexReplace ["[^A-Z0-9]", ""]) select [0, 8];
        if (_id isEqualTo "") exitWith { ["Tapez le code de la salle (ex. K7Q2M)", 3] call comspec_atak_native_fnc_toast; };
        // Variable publique : connue de tous dès qu'elle existe (JIP compris), un code faux ne mène nulle part.
        if (isNil { missionNamespace getVariable ("COMSPEC_ATAK_PlanRoom_" + _id) }) exitWith { [format ["Salle %1 introuvable", _id], 3] call comspec_atak_native_fnc_toast; };
        uiNamespace setVariable ["COMSPEC_ATAK_PlanRoomId", _id];
        player setVariable ["COMSPEC_PlanRoom", _id, true];
        _ui set ["target", "R"];
        _ui set ["menu", ""];
        ["INFO", "PLAN", format ["Salle de plan %1 rejointe", _id]] call comspec_atak_native_fnc_log;
        [format ["Salle %1 rejointe : vos tracés y vont, donnez le code aux autres", _id], 4] call comspec_atak_native_fnc_toast;
        call _chrome;
    };
    case "roomLeave": {
        if (_room isEqualTo "") exitWith {};
        uiNamespace setVariable ["COMSPEC_ATAK_PlanRoomId", ""];
        player setVariable ["COMSPEC_PlanRoom", nil, true];
        if ((_ui get "target") isEqualTo "R") then { _ui set ["target", "S"]; };
        if ((_ui get "selLayer") isEqualTo "R") then { _ui set ["sel", ""]; };
        _ui set ["menu", ""];
        [format ["Salle %1 quittée (elle reste ouverte pour les autres)", _room], 3] call comspec_atak_native_fnc_toast;
        call _chrome;
    };
    case "roomCopy": {
        if (_room isEqualTo "") exitWith {};
        [_room] call _clipSet;
        [format ["Code %1 copié", _room], 2] call comspec_atak_native_fnc_toast;
    };
    case "expSrc": {
        private _list = ["S", "D"] + ([[], ["R"]] select (_room isNotEqualTo ""));
        private _cur = [_ui get "expSrc", _ui get "target"] select ((_ui get "expSrc") isEqualTo "");
        _ui set ["expSrc", _list select (((_list find _cur) + 1) mod (count _list))];
        call _chrome;
    };
    case "save": {
        private _src = _arg;
        private _name = "";
        if (_arg isEqualType []) then { _src = _arg param [0, "D"]; _name = _arg param [1, ""]; } else {
            _name = trim (["planName", ""] call comspec_atak_native_fnc_formValue);
        };
        if (_name isEqualTo "") exitWith { ["Donnez un nom au plan", 3] call comspec_atak_native_fnc_toast; };
        private _its = [_src] call _sourceItems;
        if ((count _its) isEqualTo 0) exitWith { ["Plan vide : rien à enregistrer", 3] call comspec_atak_native_fnc_toast; };
        private _l = call _lib;
        private _i = _l findIf { (_x select 0) isEqualTo _name };
        private _row = [_name select [0, 40], worldName, _its, call _stamp];
        if (_i >= 0) then { _l set [_i, _row]; } else { _l pushBack _row; };
        profileNamespace setVariable ["COMSPEC_ATAK_PlanLib", _l select [((count _l) - 30) max 0]];
        saveProfileNamespace;
        [format ["Plan « %1 » enregistré (%2 éléments)", _name, count _its], 3] call comspec_atak_native_fnc_toast;
        if ((_ui get "view") isEqualTo "LIB") then { call _rerender; } else { call _chrome; };
    };
    case "load": {
        private _row = (call _lib) param [_arg, []];
        if ((count _row) < 3) exitWith {};
        [+(_row select 2)] call _draftSet;
        _ui set ["target", "D"];
        _ui set ["view", "MAP"];
        [format ["« %1 » chargé dans le brouillon (Ctrl+Z annule)", _row select 0], 3] call comspec_atak_native_fnc_toast;
        call _rerender;
    };
    case "preview": {
        private _row = (call _lib) param [_arg, []];
        if ((count _row) < 3) exitWith {};
        uiNamespace setVariable ["COMSPEC_ATAK_PlanPreview", [_row select 0, +(_row select 2)]];
        _ui set ["view", "MAP"];
        call _rerender;
    };
    case "previewOff": { uiNamespace setVariable ["COMSPEC_ATAK_PlanPreview", []]; call _chrome; };
    case "previewAdd": {
        private _pv = uiNamespace getVariable ["COMSPEC_ATAK_PlanPreview", []];
        if ((count _pv) < 2) exitWith {};
        [_pv select 1, _ui get "target", false, _pv select 0] call _addItems;
        uiNamespace setVariable ["COMSPEC_ATAK_PlanPreview", []];
        [format ["« %1 » ajouté : %2", _pv select 0, [_ui get "target"] call _layerName], 3] call comspec_atak_native_fnc_toast;
        call _chrome;
    };
    case "publish": {
        _arg params [["_src", "D"], ["_replace", false]];
        private _its = [_src] call _sourceItems;
        if ((count _its) isEqualTo 0) exitWith { ["Rien à publier", 3] call comspec_atak_native_fnc_toast; };
        if !(call _can) exitWith { ["Édition du plan du camp réservée (réglage serveur)", 4] call comspec_atak_native_fnc_toast; };
        private _name = if (_src isEqualType 0) then { ((call _lib) param [_src, []]) param [0, ""] } else { "" };
        [_its, "S", _replace, _name] call _addItems;
        [format ["%1 éléments %2 le plan du camp", count _its, ["ajoutés dans", "remplacent"] select _replace], 3] call comspec_atak_native_fnc_toast;
        _ui set ["menu", ""];
        if ((_ui get "view") isEqualTo "LIB") then { call _rerender; } else { call _chrome; };
    };
    case "libDel": {
        private _l = call _lib;
        if (_arg < 0 || {_arg >= (count _l)}) exitWith {};
        _l deleteAt _arg;
        profileNamespace setVariable ["COMSPEC_ATAK_PlanLib", _l];
        saveProfileNamespace;
        call _rerender;
    };
    case "export": {
        private _its = [_arg] call _sourceItems;
        if ((count _its) isEqualTo 0) exitWith { ["Plan vide : rien à exporter", 3] call comspec_atak_native_fnc_toast; };
        // Auteur retiré : la chaîne circule entre joueurs.
        private _s = "COMSPECPLAN1|" + str [worldName, [_arg] call _sourceName, _its apply { private _c = +_x; _c set [9, ""]; _c }];
        uiNamespace setVariable ["COMSPEC_ATAK_PlanExport", _s];
        [_s] call _clipSet;
        [format ["Code du plan copié (%1 caractères) : collez-le dans un autre ATAK (EXPORTER > Importer)", count _s], 4] call comspec_atak_native_fnc_toast;
        _ui set ["menu", ""];
        if ((_ui get "view") isEqualTo "LIB") then { call _rerender; } else { call _chrome; };
    };
    case "exportText": {
        // Texte de briefing lisible (Discord, ordre écrit) : un élément par ligne avec sa grille et sa description.
        private _its = [_arg] call _sourceItems;
        if ((count _its) isEqualTo 0) exitWith { ["Plan vide : rien à exporter", 3] call comspec_atak_native_fnc_toast; };
        private _nl = toString [13, 10];
        private _lines = [format ["PLAN : %1 (%2, %3)", [_arg] call _sourceName, worldName, call _stamp]];
        {
            _x params ["", "_kind", "_pts", ["_text", ""], "", "", "", "", ["_num", 0], "", ["_desc", ""]];
            private _def = (_cat get "kinds") getOrDefault [_kind, []];
            if ((count _def) isEqualTo 0) then { continue };
            private _lab = [_text, _def select 1] select (_text isEqualTo "");
            if (_num > 0 && {(_lab find (str _num)) < 0}) then { _lab = format ["%1 %2", _lab, _num]; };
            private _where = switch (_def select 3) do {
                case "AREA": {
                    private _c = [0, 0];
                    { _c = [(_c select 0) + (_x select 0), (_c select 1) + (_x select 1)]; } forEach _pts;
                    format ["centre %1", [[(_c select 0) / (count _pts), (_c select 1) / (count _pts)], 8] call comspec_atak_native_fnc_gridRef]
                };
                case "LINE": { format ["de %1 à %2", [_pts select 0, 8] call comspec_atak_native_fnc_gridRef, [_pts select -1, 8] call comspec_atak_native_fnc_gridRef] };
                case "DIR": { format ["%1, vers %2°", [_pts select 0, 8] call comspec_atak_native_fnc_gridRef, round ((_pts select 0) getDir (_pts select -1))] };
                default { [_pts select 0, 8] call comspec_atak_native_fnc_gridRef };
            };
            _lines pushBack (format ["- %1 (%2) : %3%4", _lab, _def select 2, _where, ["", format [" - %1", _desc]] select (_desc isNotEqualTo "")]);
        } forEach _its;
        private _s = _lines joinString _nl;
        uiNamespace setVariable ["COMSPEC_ATAK_PlanExport", _s];
        [_s] call _clipSet;
        [format ["Texte de briefing copié (%1 lignes) : collez-le dans Discord ou un ordre écrit", (count _lines) - 1], 4] call comspec_atak_native_fnc_toast;
        _ui set ["menu", ""];
        call _chrome;
    };
    case "import": {
        private _s = trim (["planImport", ""] call comspec_atak_native_fnc_formValue);
        if (_s isEqualTo "") then { _s = call _clipGet; };
        private _i0 = _s find "[";
        if ((_s find "COMSPECPLAN1") < 0 || {_i0 < 0}) exitWith { ["Aucun code de plan (COMSPECPLAN1) dans le champ ni le presse-papiers", 4] call comspec_atak_native_fnc_toast; };
        private _data = parseSimpleArray (_s select [_i0]);
        _data params [["_w", ""], ["_name", ""], ["_its", []]];
        if !(_its isEqualType []) exitWith { ["Plan illisible", 3] call comspec_atak_native_fnc_toast; };
        private _ok = (_its apply { [objNull, "clean", _x] call comspec_atak_native_fnc_planServer }) select { (count _x) > 0 };
        if ((count _ok) isEqualTo 0) exitWith { ["Plan vide ou illisible", 3] call comspec_atak_native_fnc_toast; };
        [_ok, "D"] call _addItems;
        _ui set ["target", "D"];
        _ui set ["view", "MAP"];
        _ui set ["menu", ""];
        [format ["« %1 » importé dans le brouillon : %2 éléments%3", _name, count _ok, ["", format [" (préparé sur %1)", _w]] select (_w isNotEqualTo worldName)], 5] call comspec_atak_native_fnc_toast;
        call _rerender;
    };
    case "refresh": {
        // Consigne seule, sans rien redessiner (pose de sommets) : la carte garde sa vue, aucun clignotement.
        private _c = uiNamespace getVariable ["COMSPEC_ATAK_PlanCtrls", createHashMap];
        private _hint = _c getOrDefault ["hint", controlNull];
        if (!isNull _hint) then { _hint ctrlSetStructuredText parseText (((["hintText"] call comspec_atak_native_fnc_planAction)) call comspec_atak_native_fnc_tr); };
    };
    case "hintText": {
        private _kind = _ui get "tool";
        private _def = (_cat get "kinds") getOrDefault [_kind, []];
        private _n = count (_ui get "draft");
        private _t = if ((count _def) isEqualTo 0) then {
            "Clic : sélectionner · clic droit : menu · glisser droit : déplacer la carte · H : aide"
        } else {
            private _how = switch (_def select 3) do {
                case "POINT": { "Clic : poser · clic droit : quitter l'outil" };
                case "DIR": { ["Clic : position · clic droit : quitter l'outil", "Clic : direction de tir · clic droit : annuler"] select (_n > 0) };
                case "AREA": { format ["Clic : sommets (%1) · clic sur le 1er, Entrée ou clic droit : fermer · 2 points = rectangle", _n] };
                default { if (_ui get "pen") then { "Maintenir le clic gauche et dessiner · clic droit : quitter l'outil" } else { format ["Clic : points (%1) · double clic, Entrée ou clic droit : terminer", _n] } };
            };
            format ["<t font='COMSPEC_BarlowBold' color='#e6efe9'>%1</t> · %2", _def select 2, _how]
        };
        format ["<t size='0.8' color='#8a9a93'>%1</t>", _t]
    };
    case "clip": { [_arg] call _clipSet; };
    case "chrome": { call _chrome; };
    case "rerender": { call _rerender; };
};
