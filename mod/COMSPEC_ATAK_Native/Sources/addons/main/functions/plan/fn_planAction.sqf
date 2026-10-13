/*
    App Plan (carte de briefing) : commandes de l'interface, brouillon local, plans enregistrés, export / import.
    Params : [action, argument]
      "ui"                        -> état de l'interface (uiNamespace COMSPEC_ATAK_PlanUi)
      "shared" / "draft"          -> éléments du plan partagé du camp / du brouillon local
      "can"                       -> droit de modifier le plan partagé (fn_planServer « can »)
      "tab" cat | "tool" type | "color" clé | "width" n | "dash" | "arrow" | "pen" | "target" | "view" vue | "layer"
      "toDraft" [éléments]        ajoute au brouillon (plan partagé sans réponse du serveur) ; "pending" -> éléments en attente du serveur
      "commit" [points]           crée un élément avec l'outil actif (texte, couleur, tirets, flèche, épaisseur choisis)
      "finish" / "cancel"         termine / abandonne le tracé en cours (clic droit, double clic, boutons)
      "undo"                      défait le dernier élément (brouillon : le dernier ; partagé : le dernier posé par moi)
      "select" [id, couche]       sélection (couche "S" partagé, "D" brouillon) ; "delete" ; "toggleClear" (CLEARED <-> UNCLEARED)
      "clearTarget"               vide la couche visée (deux appuis)
      "save" source               enregistre le partagé ("S") ou le brouillon ("D") sous le nom saisi (profil)
      "load" i | "preview" i | "previewOff" | "previewAdd" | "libDel" i     plans enregistrés
      "publish" [source, remplacer]   brouillon ("D"), aperçu ("P") ou plan enregistré (index) vers le plan partagé
      "export" source | "import"  chaîne portable « COMSPECPLAN1|[carte, nom, éléments] » (presse-papiers et champ texte)
      "text" texte                texte des prochains éléments ; "rerender"
      "clip" texte                copie dans le presse-papiers (aussi en multijoueur, par la DLL du mod)
    Brouillon : missionNamespace COMSPEC_ATAK_PlanDraft (gardé dans le profil, COMSPEC_ATAK_PlanDraftSave).
    Plans enregistrés : profil COMSPEC_ATAK_PlanLib = [[nom, carte, éléments, date]...].
*/
params [["_act", ""], ["_arg", []]];
private _ui = uiNamespace getVariable "COMSPEC_ATAK_PlanUi";
if (isNil "_ui") then {
    _ui = createHashMapFromArray [["view", "MAP"], ["cat", "AREA"], ["tool", ""], ["color", ""], ["width", 2], ["dash", false], ["arrow", false],
        ["pen", false], ["target", "S"], ["draft", []], ["sel", ""], ["selLayer", ""], ["text", ""]];
    uiNamespace setVariable ["COMSPEC_ATAK_PlanUi", _ui];
};
private _cat = [] call comspec_atak_native_fnc_planCatalog;
private _rerender = { [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "PLAN") then { ["PLAN"] call comspec_atak_native_fnc_pageRender; }; }] call CBA_fnc_execNextFrame; };
private _sharedVar = format ["COMSPEC_ATAK_Plan_%1", side group player];
private _shared = { (missionNamespace getVariable [_sharedVar, [0, [], ""]]) param [1, []] };
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
private _draftSet = {
    params ["_its"];
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
private _can = { [objNull, "can"] call comspec_atak_native_fnc_planServer };
private _srv = { params ["_op", ["_a", []]]; ["comspec_atak_native_planSrv", [player, _op, _a]] call CBA_fnc_serverEvent; };
private _lib = { +(profileNamespace getVariable ["COMSPEC_ATAK_PlanLib", []]) };
// Éléments de la couche visée ; ajout (partagé : par le serveur, refusé sans droit -> brouillon).
private _addItems = {
    params ["_list", ["_toShared", true], ["_replace", false], ["_name", ""]];
    if ((count _list) isEqualTo 0) exitWith {};
    // Serveur sans le mod natif (mod chargé seulement chez les joueurs) : personne n'écrit le plan partagé.
    // Le drapeau du serveur peut manquer (solo, partie hébergée, drapeau pas encore reçu) : on essaie quand même
    // le serveur, et seulement sans réponse sous 5 s les éléments vont au brouillon.
    private _srvOn = isServer || {missionNamespace getVariable ["COMSPEC_ATAK_PlanSrvOn", false]} || {!isNil { missionNamespace getVariable _sharedVar }};
    // Serveur déjà resté muet sans drapeau dans cette mission : directement au brouillon.
    private _noSrv = !_srvOn && {missionNamespace getVariable ["COMSPEC_ATAK_PlanNoSrv", false]};
    if (_toShared && {!_noSrv} && {call _can}) then {
        private _rev = (missionNamespace getVariable [_sharedVar, [0, [], ""]]) param [0, 0];
        [["merge", "replace"] select _replace, [_list, _name]] call _srv;
        // Affichés en attente jusqu'à la réponse du serveur ; sans réponse (refus, serveur muet), gardés dans le brouillon.
        private _pend = uiNamespace getVariable ["COMSPEC_ATAK_PlanPending", []];
        _pend append _list;
        uiNamespace setVariable ["COMSPEC_ATAK_PlanPending", _pend];
        [{ ((missionNamespace getVariable [_this select 0, [0]]) param [0, 0]) isNotEqualTo (_this select 1) }, {
            uiNamespace setVariable ["COMSPEC_ATAK_PlanPending", []];
        }, [_sharedVar, _rev, _list, _srvOn], 5, {
            uiNamespace setVariable ["COMSPEC_ATAK_PlanPending", []];
            ["WARN", "PLAN", format ["Plan partagé : pas de réponse du serveur (%1 éléments gardés dans le brouillon)", count (_this select 2)]] call comspec_atak_native_fnc_log;
            [["Serveur sans le mod ATAK natif : plan partagé indisponible, éléments placés dans votre brouillon", "Plan partagé : pas de réponse du serveur, gardé dans votre brouillon"] select (_this select 3), 5] call comspec_atak_native_fnc_toast;
            if !(_this select 3) then { missionNamespace setVariable ["COMSPEC_ATAK_PlanNoSrv", true]; };
            ["toDraft", _this select 2] call comspec_atak_native_fnc_planAction;
        }] call CBA_fnc_waitUntilAndExecute;
    } else {
        if (_toShared) then {
            [["Édition du plan partagé réservée : éléments placés dans votre brouillon", "Serveur sans le mod ATAK natif : plan partagé indisponible, éléments placés dans votre brouillon"] select _noSrv, 5] call comspec_atak_native_fnc_toast;
        };
        private _d = [[], +(call _draftGet)] select !_replace;
        { private _c = +_x; _c set [0, call _newId]; if ((_c select 9) isEqualTo "") then { _c set [9, name player]; }; _d pushBack _c; } forEach _list;
        [_d] call _draftSet;
    };
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
        case (_src isEqualTo "D"): { +(call _draftGet) };
        case (_src isEqualTo "P"): { +((uiNamespace getVariable ["COMSPEC_ATAK_PlanPreview", []]) param [1, []]) };
        case (_src isEqualType 0): { +(((call _lib) param [_src, []]) param [2, []]) };
        default { [] };
    }
};

switch (_act) do {
    case "ui": { _ui };
    case "shared": { call _shared };
    case "draft": { call _draftGet };
    case "can": { call _can };
    case "tab": { _ui set ["cat", _arg]; _ui set ["tool", ""]; _ui set ["draft", []]; call _rerender; };
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
        };
        _ui set ["draft", []];
        call _rerender;
    };
    case "color": { _ui set ["color", _arg]; call _rerender; };
    case "width": { _ui set ["width", _arg]; call _rerender; };
    case "dash": { _ui set ["dash", !(_ui get "dash")]; call _rerender; };
    case "arrow": { _ui set ["arrow", !(_ui get "arrow")]; call _rerender; };
    case "pen": { _ui set ["pen", !(_ui get "pen")]; _ui set ["draft", []]; call _rerender; };
    case "target": { _ui set ["target", ["S", "D"] select ((_ui get "target") isEqualTo "S")]; call _rerender; };
    case "view": { _ui set ["view", _arg]; call _rerender; };
    case "layer": {
        profileNamespace setVariable ["COMSPEC_ATAK_PlanOnMap2", !(profileNamespace getVariable ["COMSPEC_ATAK_PlanOnMap2", true])];
        saveProfileNamespace;
        call _rerender;
    };
    case "toDraft": { [_arg, false] call _addItems; call _rerender; };
    case "pending": { uiNamespace getVariable ["COMSPEC_ATAK_PlanPending", []] };
    case "text": { _ui set ["text", (trim _arg) select [0, 40]]; };
    case "commit": {
        private _kind = _ui get "tool";
        private _def = (_cat get "kinds") getOrDefault [_kind, []];
        if ((count _def) isEqualTo 0) exitWith {};
        private _toShared = (_ui get "target") isEqualTo "S";
        // Numéro automatique (bâtiments : CLEARED et UNCLEARED partagent la même suite ; CP, RP, TRP chacun la leur).
        private _num = 0;
        if (_kind in ["CLEARED", "UNCLEARED", "CP", "RP", "TRP"]) then {
            private _fam = [[_kind], ["CLEARED", "UNCLEARED"]] select (_kind in ["CLEARED", "UNCLEARED"]);
            { if ((_x select 1) in _fam) then { _num = _num max (_x select 8); }; } forEach ((call _shared) + (call _draftGet));
            _num = _num + 1;
        };
        private _it = [objNull, "clean", ["", _kind, _arg apply { [_x select 0, _x select 1] }, _ui get "text", _ui get "color", _ui get "dash", _ui get "arrow", _ui get "width", _num, name player]] call comspec_atak_native_fnc_planServer;
        if ((count _it) isEqualTo 0) exitWith {};
        [[_it], _toShared] call _addItems;
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
            if ((_ui get "tool") isNotEqualTo "") then { _ui set ["tool", ""]; call _rerender; };
        };
    };
    case "undo": {
        if ((count (_ui get "draft")) > 0) exitWith { private _d = _ui get "draft"; _d deleteAt ((count _d) - 1); ["refresh"] call comspec_atak_native_fnc_planAction; };
        if ((_ui get "target") isEqualTo "S" && {call _can}) then { ["undo"] call _srv; } else {
            private _d = +(call _draftGet);
            if ((count _d) > 0) then { _d deleteAt ((count _d) - 1); [_d] call _draftSet; };
        };
    };
    case "select": {
        _arg params [["_id", ""], ["_layer", ""]];
        _ui set ["sel", _id];
        _ui set ["selLayer", _layer];
        ["refresh"] call comspec_atak_native_fnc_planAction;
    };
    case "delete";
    case "toggleClear": {
        private _id = _ui get "sel";
        private _layer = _ui get "selLayer";
        if (_id isEqualTo "") exitWith {};
        private _list = [call _draftGet, call _shared] select (_layer isEqualTo "S");
        private _i = _list findIf { (_x select 0) isEqualTo _id };
        if (_i < 0) exitWith {};
        private _it = +(_list select _i);
        if (_act isEqualTo "toggleClear") then {
            if !((_it select 1) in ["CLEARED", "UNCLEARED"]) exitWith {};
            private _to = ["CLEARED", "UNCLEARED"] select ((_it select 1) isEqualTo "CLEARED");
            _it set [1, _to];
            _it set [4, ((_cat get "kinds") get _to) select 4];
            if ((_it select 3) in ["CLEARED", "UNCLEARED"]) then { _it set [3, ""]; };
            if (_layer isEqualTo "S") then { ["upd", [_id, _it]] call _srv; } else { private _d = +(call _draftGet); _d set [_i, _it]; [_d] call _draftSet; };
        } else {
            if (_layer isEqualTo "S") then { ["del", [_id]] call _srv; } else { private _d = +(call _draftGet); _d deleteAt _i; [_d] call _draftSet; };
            _ui set ["sel", ""];
        };
        ["refresh"] call comspec_atak_native_fnc_planAction;
    };
    case "clearTarget": {
        // Deux appuis en moins de 4 s : rien ne s'efface par erreur.
        if (diag_tickTime - (uiNamespace getVariable ["COMSPEC_ATAK_PlanClearArm", -10]) > 4) exitWith {
            uiNamespace setVariable ["COMSPEC_ATAK_PlanClearArm", diag_tickTime];
            ["Appuyez encore pour tout effacer", 3] call comspec_atak_native_fnc_toast;
        };
        uiNamespace setVariable ["COMSPEC_ATAK_PlanClearArm", -10];
        if ((_ui get "target") isEqualTo "S") then { if (call _can) then { ["clear"] call _srv; } else { ["Édition du plan partagé réservée", 3] call comspec_atak_native_fnc_toast; }; } else { [[]] call _draftSet; };
        _ui set ["sel", ""];
        call _rerender;
    };
    case "save": {
        private _name = trim (["planName", ""] call comspec_atak_native_fnc_formValue);
        if (_name isEqualTo "") exitWith { ["Donnez un nom au plan", 3] call comspec_atak_native_fnc_toast; };
        private _its = [_arg] call _sourceItems;
        if ((count _its) isEqualTo 0) exitWith { ["Plan vide : rien à enregistrer", 3] call comspec_atak_native_fnc_toast; };
        private _l = call _lib;
        private _i = _l findIf { (_x select 0) isEqualTo _name };
        private _row = [_name select [0, 40], worldName, _its, (systemTime select [0, 5]) apply { [str _x, "0" + str _x] select (_x < 10) } call { format ["%1-%2-%3 %4:%5", _this select 0, _this select 1, _this select 2, _this select 3, _this select 4] }];
        if (_i >= 0) then { _l set [_i, _row]; } else { _l pushBack _row; };
        profileNamespace setVariable ["COMSPEC_ATAK_PlanLib", _l select [((count _l) - 30) max 0]];
        saveProfileNamespace;
        [format ["Plan « %1 » enregistré (%2 éléments)", _name, count _its], 3] call comspec_atak_native_fnc_toast;
        call _rerender;
    };
    case "load": {
        private _row = (call _lib) param [_arg, []];
        if ((count _row) < 3) exitWith {};
        [+(_row select 2)] call _draftSet;
        _ui set ["target", "D"];
        _ui set ["view", "MAP"];
        [format ["« %1 » chargé dans le brouillon", _row select 0], 3] call comspec_atak_native_fnc_toast;
        call _rerender;
    };
    case "preview": {
        private _row = (call _lib) param [_arg, []];
        if ((count _row) < 3) exitWith {};
        uiNamespace setVariable ["COMSPEC_ATAK_PlanPreview", [_row select 0, +(_row select 2)]];
        _ui set ["view", "MAP"];
        call _rerender;
    };
    case "previewOff": { uiNamespace setVariable ["COMSPEC_ATAK_PlanPreview", []]; call _rerender; };
    case "previewAdd": {
        private _pv = uiNamespace getVariable ["COMSPEC_ATAK_PlanPreview", []];
        if ((count _pv) < 2) exitWith {};
        [_pv select 1, (_ui get "target") isEqualTo "S", false, _pv select 0] call _addItems;
        uiNamespace setVariable ["COMSPEC_ATAK_PlanPreview", []];
        [format ["« %1 » ajouté au plan", _pv select 0], 3] call comspec_atak_native_fnc_toast;
        call _rerender;
    };
    case "publish": {
        _arg params [["_src", "D"], ["_replace", false]];
        private _its = [_src] call _sourceItems;
        if ((count _its) isEqualTo 0) exitWith { ["Rien à publier", 3] call comspec_atak_native_fnc_toast; };
        if !(call _can) exitWith { ["Édition du plan partagé réservée (réglage serveur)", 4] call comspec_atak_native_fnc_toast; };
        private _name = if (_src isEqualType 0) then { ((call _lib) param [_src, []]) param [0, ""] } else { "" };
        [_its, true, _replace, _name] call _addItems;
        [format ["%1 éléments %2 le plan partagé", count _its, ["ajoutés dans", "remplacent"] select _replace], 3] call comspec_atak_native_fnc_toast;
        call _rerender;
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
        private _name = if (_arg isEqualType 0) then { ((call _lib) param [_arg, []]) param [0, "Plan"] } else { ["Brouillon", "Plan partagé"] select (_arg isEqualTo "S") };
        // Auteur retiré : la chaîne circule entre joueurs.
        private _s = "COMSPECPLAN1|" + str [worldName, _name, _its apply { private _c = +_x; _c set [9, ""]; _c }];
        uiNamespace setVariable ["COMSPEC_ATAK_PlanExport", _s];
        [_s] call _clipSet;
        [format ["Plan copié (%1 caractères) : collez-le dans un autre ATAK, Discord ou un fichier", count _s], 4] call comspec_atak_native_fnc_toast;
        call _rerender;
    };
    case "import": {
        private _s = trim (["planImport", ""] call comspec_atak_native_fnc_formValue);
        if (_s isEqualTo "") then { _s = call _clipGet; };
        private _i0 = _s find "[";
        if ((_s find "COMSPECPLAN1") < 0 || {_i0 < 0}) exitWith { ["Aucun plan COMSPECPLAN1 dans le champ ni le presse-papiers", 4] call comspec_atak_native_fnc_toast; };
        private _data = parseSimpleArray (_s select [_i0]);
        _data params [["_w", ""], ["_name", ""], ["_its", []]];
        if !(_its isEqualType []) exitWith { ["Plan illisible", 3] call comspec_atak_native_fnc_toast; };
        private _ok = (_its apply { [objNull, "clean", _x] call comspec_atak_native_fnc_planServer }) select { (count _x) > 0 };
        if ((count _ok) isEqualTo 0) exitWith { ["Plan vide ou illisible", 3] call comspec_atak_native_fnc_toast; };
        [_ok, false] call _addItems;
        _ui set ["target", "D"];
        _ui set ["view", "MAP"];
        [format ["« %1 » importé dans le brouillon : %2 éléments%3", _name, count _ok, ["", format [" (préparé sur %1)", _w]] select (_w isNotEqualTo worldName)], 5] call comspec_atak_native_fnc_toast;
        call _rerender;
    };
    case "refresh": {
        // Consigne et boutons sans redessiner la page (la carte garde sa vue, aucun clignotement).
        private _c = uiNamespace getVariable ["COMSPEC_ATAK_PlanCtrls", createHashMap];
        private _hint = _c getOrDefault ["hint", controlNull];
        if (!isNull _hint) then { _hint ctrlSetStructuredText parseText (((["hintText"] call comspec_atak_native_fnc_planAction)) call comspec_atak_native_fnc_tr); };
        private _has = (_ui get "sel") isNotEqualTo "";
        { private _b = _c getOrDefault [_x, controlNull]; if (!isNull _b) then { _b ctrlEnable _has; }; } forEach ["del", "toggle"];
    };
    case "hintText": {
        private _kind = _ui get "tool";
        private _def = (_cat get "kinds") getOrDefault [_kind, []];
        private _n = count (_ui get "draft");
        private _t = if ((count _def) isEqualTo 0) then {
            ["Clic : sélectionner · glisser droit : déplacer la carte", "Élément sélectionné : SUPPR pour l'effacer · clic droit : désélectionner"] select ((_ui get "sel") isNotEqualTo "")
        } else {
            switch (_def select 3) do {
                case "POINT": { "Clic : poser · clic droit : quitter l'outil" };
                case "DIR": { ["Clic : position · clic droit : quitter l'outil", "Clic : direction de tir · clic droit : annuler"] select (_n > 0) };
                case "AREA": { format ["Clic : sommets (%1) · clic sur le 1er ou clic droit : fermer · 2 points = rectangle", _n] };
                default { if (_ui get "pen") then { "Maintenir le clic gauche et dessiner · clic droit : quitter l'outil" } else { format ["Clic : points (%1) · double clic ou clic droit : terminer", _n] } };
            }
        };
        format ["<t size='0.8' color='#8a9a93'>%1</t>", _t]
    };
    case "clip": { [_arg] call _clipSet; };
    case "rerender": { call _rerender; };
};
