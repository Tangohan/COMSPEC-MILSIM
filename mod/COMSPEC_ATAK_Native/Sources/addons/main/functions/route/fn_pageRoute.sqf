/*
    App Routes (page ROUTE). Vues (uiNamespace COMSPEC_ATAK_RteUi, clé view) :
      LIST    mes routes / routes reçues (équipe, groupe, camp), route suivie, nouvelle route, import de l'app Itinéraire ;
      EDIT    carte de l'app (clic : ajouter, insérer après l'étape choisie, déplacer ; fn_routeMap) et fiche de la route :
              étapes, calculs, type de tracé, style, affichage, heure H, partage, dupliquer / inverser / supprimer ;
      STEP    fiche d'une étape : nom, type, action attendue, H+, consigne, position ;
      MARKERS ajouter une étape sur un marqueur existant ;
      FOLLOW  guidage « Suivre la route » : étape visée, distance, cap, arrivée, reste, progression (mis à jour sur place
              chaque seconde, sans rendu de page).
    Dans la vue EDIT, un clic (carte ou fiche) ne refait que la fiche : la carte garde sa vue (pas de rendu complet).
    Édition (fn_routeAction editBegin… editCommit) : ANNULER / RÉTABLIR (Ctrl+Z / Ctrl+Y, 30 pas), VALIDER (un seul
    envoi), ANNULER LES MODIFICATIONS (état d'ouverture ; nouvelle route : brouillon abandonné). Modes de saisie de la
    carte (CHOISIR par défaut, AJOUTER, INSÉRER, DÉPLACER) : barre sur la carte (TERMINER, ANNULER), Échap ou clic
    droit pour en sortir. Suppression d'étape ou de route : deux clics, puis bandeau « ANNULER » 8 s.
    Téléphone porté (sans souris) : guidage si une route est suivie, sinon la liste.
    Params : [page, rectangle] (app de module, fn_pageRender) | ["#refresh"] | ["#live"] | ["#ui", [op, valeur]]
             | ["#key", paramètres de KeyDown] -> touche gardée
*/
params [["_page", "ROUTE"], ["_arg", []]];
disableSerialization;
private _ui = uiNamespace getVariable "COMSPEC_ATAK_RteUi";
if (isNil "_ui") then {
    _ui = createHashMapFromArray [["view", "LIST"], ["tab", "MINE"], ["id", ""], ["sel", -1], ["mode", "SEL"], ["draft", createHashMap], ["confirm", ""], ["undoBar", []], ["modeMark", 0]];
    uiNamespace setVariable ["COMSPEC_ATAK_RteUi", _ui];
};
// Bandeau « … · ANNULER » (8 s) : [texte, opération d'annulation, valeur, heure].
private _bar = {
    params ["_t", "_op", ["_v", ""]];
    private _at = diag_tickTime;
    _ui set ["undoBar", [_t, _op, _v, _at]];
    [{ private _u = uiNamespace getVariable ["COMSPEC_ATAK_RteUi", createHashMap]; if (((_u getOrDefault ["undoBar", []]) param [3, -1]) isEqualTo _this) then { _u set ["undoBar", []]; if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "ROUTE") then { ["#refresh"] call comspec_atak_native_fnc_pageRoute; }; }; }, _at, 8] call comspec_atak_native_fnc_waitAndExec;
};
private _tr = { _this call comspec_atak_native_fnc_tr };
private _RA = comspec_atak_native_fnc_routeAction;
private _fmt = { params ["_m"]; [format ["%1 m", round _m], format ["%1 km", (_m / 1000) toFixed 1]] select (_m >= 1000) };
private _dur = {
    params ["_s"];
    private _m = round (_s / 60);
    if (_m < 60) exitWith { format ["%1 min", _m max 1] };
    format ["%1 h %2", floor (_m / 60), [str (_m mod 60), "0" + str (_m mod 60)] select ((_m mod 60) < 10)]
};
private _clock = { params ["_s"]; [(dayTime + _s / 3600) mod 24, "HH:MM"] call BIS_fnc_timeToString };
// Cap sur trois chiffres et point cardinal (16 directions, fn_cardinal) : « 045° NE ».
private _card = {
    params ["_a"];
    private _t = str ((round _a) mod 360);
    while { (count _t) < 3 } do { _t = "0" + _t; };
    format ["%1° %2", _t, [_a] call comspec_atak_native_fnc_cardinal]
};
private _esc = { params ["_t"]; [_t, "&", "&amp;"] call CBA_fnc_replace };
// Heure prévue d'une étape : « H+15 », et l'heure si l'heure H est posée.
private _plan = {
    params ["_r", "_hp"];
    if (_hp < 0) exitWith { "" };
    private _hh = _r get "hh";
    if (_hh isEqualTo "") exitWith { format ["H+%1", _hp] };
    (_hh splitString ":") params [["_h", "0"], ["_mn", "0"]];
    format ["H+%1 (%2)", _hp, [((parseNumber _h) + ((parseNumber _mn) + _hp) / 60) mod 24, "HH:MM"] call BIS_fnc_timeToString]
};
// Rendu complet à l'image suivante, sauté si la page vient d'être dessinée à cette image (rafraîchissement de
// fn_routeAction et clic qui demandent tous deux un rendu : un seul).
private _rerender = { [{
    if ((uiNamespace getVariable ["COMSPEC_ATAK_RteDrawnAt", -1]) isEqualTo diag_frameNo) exitWith {};
    if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "ROUTE") then { ["ROUTE"] call comspec_atak_native_fnc_pageRender; };
}] call comspec_atak_native_fnc_nextFrame; };

// Texte du guidage (vue FOLLOW, mis à jour chaque seconde).
private _liveText = {
    private _fi = ["followInfo"] call _RA;
    if ((count _fi) isEqualTo 0) exitWith { format ["<t color='" + (COMSPEC_ATAK_Th get "hexTextDim") + "'>%1</t>", ("Aucune route suivie.") call _tr] };
    private _r = _fi get "route";
    (_fi get "step") params ["", "_k", "_n", ["_note", ""], ["_hp", -1], ["_ac", ""]];
    private _tl = (["type", _k] call _RA) select 1;
    private _l1 = format ["<t font='COMSPEC_BarlowBold' size='1.3'>%1</t>  <t color='" + (COMSPEC_ATAK_Th get "hexTextDim") + "'>%2 · %3 / %4</t>", [[_n] call comspec_atak_native_fnc_markerAutoName] call _esc, (_tl) call _tr, (_fi get "idx") + 1, _fi get "n"];
    private _l2 = format ["<t font='EtelkaMonospaceProBold' size='1.6' color='" + (COMSPEC_ATAK_Th get "hexOk") + "'>%1</t>   <t font='EtelkaMonospacePro' size='1.25'>%2</t>", [_fi get "dist"] call _fmt, [_fi get "brg"] call _card];
    private _pl = [_r, _hp] call _plan;
    private _l3 = format ["%1 %2 (%3)%4", ("Arrivée à l'étape :") call _tr, [_fi get "etaNext"] call _dur, [_fi get "etaNext"] call _clock, ["", format [" · %1 %2", ("prévu") call _tr, _pl]] select (_pl isNotEqualTo "")];
    private _l4 = format ["%1 %2 · %3 %4 (%5) · %6 %%", ("Reste :") call _tr, [_fi get "rest"] call _fmt, ("fin") call _tr, [_fi get "etaEnd"] call _dur, [_fi get "etaEnd"] call _clock, round ((_fi get "prog") * 100)];
    private _what = [];
    if (_ac isNotEqualTo "") then { _what pushBack format ["<t color='" + (COMSPEC_ATAK_Th get "hexWarn") + "'>%1</t> %2", ("Action :") call _tr, (["actName", _ac] call _RA) call _tr]; };
    if (_note isNotEqualTo "") then { _what pushBack format ["<t color='" + (COMSPEC_ATAK_Th get "hexWarn") + "'>%1</t> %2", ("Consigne :") call _tr, [_note select [0, 90]] call _esc]; };
    private _l5 = [format ["<t color='" + (COMSPEC_ATAK_Th get "hexTextDim") + "'>%1</t>", ("Pas d'action ni de consigne à cette étape.") call _tr], _what joinString "<br/>"] select ((count _what) > 0);
    format ["%1<br/>%2<br/><t size='0.95'>%3<br/>%4</t><br/><t size='0.95'>%5</t>", _l1, _l2, _l3, _l4, _l5]
};

// Actions de l'interface (boutons, champs).
if (_page isEqualTo "#ui") exitWith {
    _arg params [["_op", ""], ["_v", ""]];
    private _id = _ui get "id";
    private _full = true;
    switch (_op) do {
        case "view": {
            // Retour à la liste : l'édition en cours est validée (un seul envoi).
            if (_v isEqualTo "LIST" && {(count (["editInfo"] call _RA)) > 0}) then { ["editCommit"] call _RA; };
            _ui set ["view", _v]; _ui set ["confirm", ""];
        };
        case "tab": { _ui set ["tab", _v]; };
        case "open": {
            _ui set ["id", _v]; _ui set ["sel", -1]; _ui set ["mode", "SEL"]; _ui set ["modeMark", 0]; _ui set ["view", "EDIT"]; _ui set ["draft", createHashMap]; _ui set ["confirm", ""]; _ui set ["undoBar", []];
            uiNamespace setVariable ["COMSPEC_ATAK_RteView", []];
            ["editBegin", [_v, false]] call _RA;
        };
        case "new": {
            private _nid = ["new", [_v]] call _RA;
            if (_nid isEqualTo "") exitWith { _full = false; };
            ["#ui", ["open", _nid]] call comspec_atak_native_fnc_pageRoute;
            ["editBegin", [_nid, true]] call _RA;
            _ui set ["mode", "ADD"];
            ["INFO", "Route créée : cliquez sur la carte pour poser ses étapes (ANNULER l'abandonne)", 4, 20] call comspec_atak_native_fnc_notify;
            _full = false;
        };
        // Annuler / rétablir (Ctrl+Z / Ctrl+Y).
        case "undo";
        case "redo": {
            if !([_op] call _RA) exitWith { [[("Rien à rétablir") call _tr, ("Rien à annuler") call _tr] select (_op isEqualTo "undo"), 2] call comspec_atak_native_fnc_toast; _full = false; };
            _ui set ["undoBar", []]; _ui set ["confirm", ""];
            playSound "ClickSoft";
            if ((_ui get "view") isEqualTo "EDIT") then { _full = false; };
        };
        // VALIDER : fin de l'édition, un seul envoi si la route est partagée ou sur la carte d'Arma.
        case "commit": {
            private _r0 = ["get", _id] call _RA;
            private _dirty = ["editCommit"] call _RA;
            ["SUCCESS", ["Route validée", "Route validée et envoyée"] select (_dirty && {(_r0 getOrDefault ["scope", 0]) > 0} && {_r0 getOrDefault ["live", false]}), 2, 10] call comspec_atak_native_fnc_notify;
            _ui set ["view", "LIST"]; _ui set ["confirm", ""]; _ui set ["mode", "SEL"];
        };
        // ANNULER LES MODIFICATIONS (deux clics si la route a changé) ; nouvelle route : brouillon abandonné.
        case "revert": {
            private _e = ["editInfo"] call _RA;
            if ((_e getOrDefault ["dirty", false]) && {(_ui get "confirm") isNotEqualTo "revert"}) exitWith { _ui set ["confirm", "revert"]; _full = false; ["#refresh"] call comspec_atak_native_fnc_pageRoute; };
            private _isNew = _e getOrDefault ["isNew", false];
            ["editRevert"] call _RA;
            ["INFO", ["Modifications annulées : route comme à l'ouverture", "Nouvelle route abandonnée"] select _isNew, 2, 10] call comspec_atak_native_fnc_notify;
            _ui set ["view", "LIST"]; _ui set ["confirm", ""]; _ui set ["mode", "SEL"];
        };
        // Mode de saisie terminé (Échap, clic droit, TERMINER) : ce qui est posé reste ; ANNULER le retire.
        case "modeEnd": { _ui set ["mode", "SEL"]; _full = false; ["#refresh"] call comspec_atak_native_fnc_pageRoute; };
        case "modeCancel": {
            private _n = (count ((["editInfo"] call _RA) getOrDefault ["undo", []])) - (_ui getOrDefault ["modeMark", 0]);
            for "_k" from 1 to _n do { ["undo"] call _RA; };
            if (_n > 0) then { [format [("%1 modification(s) annulée(s)") call _tr, _n], 2] call comspec_atak_native_fnc_toast; };
            _ui set ["mode", "SEL"]; _ui set ["undoBar", []]; _full = false; ["#refresh"] call comspec_atak_native_fnc_pageRoute;
        };
        case "itinDel": {
            if ((_ui get "confirm") isNotEqualTo ("itin" + _v)) exitWith { _ui set ["confirm", "itin" + _v]; };
            _ui set ["confirm", ""];
            ["itinDel", _v] call _RA;
            [[("Itinéraire effacé") call _tr, ("Guidage GPS arrêté et effacé") call _tr] select (_v isEqualTo "GPS"), "itinUndo", _v] call _bar;
        };
        case "itinUndo": {
            if (["itinUndo", _v] call _RA) then { ["INFO", "Itinéraire rétabli", 2, 10] call comspec_atak_native_fnc_notify; };
            _ui set ["undoBar", []];
        };
        case "untrash": {
            if (["untrash", _v] call _RA) then { ["INFO", "Route rétablie", 2, 10] call comspec_atak_native_fnc_notify; };
            _ui set ["undoBar", []];
        };
        case "import": {
            private _nid = ["import"] call _RA;
            if (_nid isNotEqualTo "") then { ["#ui", ["open", _nid]] call comspec_atak_native_fnc_pageRoute; };
            _full = false;
        };
        case "step": { _ui set ["sel", _v]; _ui set ["view", "STEP"]; };
        case "sel": { _ui set ["sel", _v]; _full = false; ["#refresh"] call comspec_atak_native_fnc_pageRoute; };
        case "mode": {
            if (_v in ["INSERT", "MOVE"] && {(_ui get "sel") < 0}) exitWith { ["INFO", "Choisissez d'abord une étape (clic sur la carte ou dans la liste)", 3, 10] call comspec_atak_native_fnc_notify; _full = false; };
            _ui set ["mode", _v]; _ui set ["modeMark", count ((["editInfo"] call _RA) getOrDefault ["undo", []])];
            _full = false; ["#refresh"] call comspec_atak_native_fnc_pageRoute;
        };
        case "follow": { ["follow", [_v, [_v]] select (_v isEqualType "")] call _RA; _ui set ["view", "FOLLOW"]; };
        case "unfollow": { ["unfollow"] call _RA; _ui set ["view", ["LIST", "EDIT"] select (_id isNotEqualTo "")]; };
        case "toggle": {
            // Interrupteurs de la fiche (mis à jour sur place) : [clé, id de ligne].
            _v params ["_key", "_rowId"];
            private _r = ["get", _id] call _RA;
            if ((count _r) isEqualTo 0) exitWith {};
            ["set", [_id, _key, !(_r get _key)]] call _RA;
            [_rowId, (["get", _id] call _RA) get _key] call comspec_atak_native_fnc_formSet;
            playSound "ClickSoft";
            _full = false;
        };
        case "set": { _v params ["_key", "_val"]; ["set", [_id, _key, _val]] call _RA; playSound "ClickSoft"; _full = false; };
        case "field": {
            _v params ["_key", "_fk"];
            ["set", [_id, _key, [_fk, ""] call comspec_atak_native_fnc_formValue]] call _RA;
            (_ui get "draft") deleteAt _fk;
            playSound "ClickSoft";
            _full = false;
        };
        case "addMe": {
            private _p = [] call comspec_atak_native_fnc_gpsPos;
            private _at = [-1, (_ui get "sel") + 1] select ((_ui get "mode") isEqualTo "INSERT" && {(_ui get "sel") >= 0});
            private _i = ["stepAdd", [_id, _p, _at]] call _RA;
            if (_i >= 0) then { _ui set ["sel", _i]; };
            _full = false;
        };
        case "addGrid": {
            private _g = ["rteGrid", ""] call comspec_atak_native_fnc_formValue;
            private _res = [_g] call comspec_atak_native_fnc_gridParse;
            if ((count _res) isEqualTo 0) exitWith { ["WARNING", "Grille illisible ou hors de la carte", 3, 10] call comspec_atak_native_fnc_notify; _full = false; };
            private _at = [-1, (_ui get "sel") + 1] select ((_ui get "mode") isEqualTo "INSERT" && {(_ui get "sel") >= 0});
            private _i = ["stepAdd", [_id, _res select 0, _at]] call _RA;
            if (_i >= 0) then {
                _ui set ["sel", _i];
                (_ui get "draft") deleteAt "rteGrid";
                // Carte de l'app amenée sur la grille (sans rendu).
                private _m = uiNamespace getVariable ["COMSPEC_ATAK_RteMapCtrl", controlNull];
                if (!isNull _m) then { _m ctrlMapAnimAdd [0.4, ctrlMapScale _m, [(_res select 0) select 0, (_res select 0) select 1, 0]]; ctrlMapAnimCommit _m; };
            };
            _full = false;
        };
        case "addMarker": {
            private _at = [-1, (_ui get "sel") + 1] select ((_ui get "mode") isEqualTo "INSERT" && {(_ui get "sel") >= 0});
            private _i = ["stepAdd", [_id, markerPos _v, _at, markerText _v]] call _RA;
            if (_i >= 0) then { _ui set ["sel", _i]; };
            _ui set ["view", "EDIT"];
        };
        case "stepSave": {
            private _hpT = trim (["stHp", ""] call comspec_atak_native_fnc_formValue);
            ["stepSet", [_id, _ui get "sel", [
                ["stName", ""] call comspec_atak_native_fnc_formValue, ["stType", ""] call comspec_atak_native_fnc_formValue,
                ["stNote", ""] call comspec_atak_native_fnc_formValue, [-1, parseNumber (_hpT regexReplace ["[^0-9]", ""])] select ((_hpT regexReplace ["[^0-9]", ""]) isNotEqualTo ""),
                ["stAct", ""] call comspec_atak_native_fnc_formValue
            ]]] call _RA;
            ["SUCCESS", "Étape enregistrée", 2, 10] call comspec_atak_native_fnc_notify;
            _ui set ["view", "EDIT"];
        };
        case "stepHere": { ["stepMove", [_id, _ui get "sel", [] call comspec_atak_native_fnc_gpsPos]] call _RA; ["INFO", "Étape placée sur ma position", 2, 10] call comspec_atak_native_fnc_notify; };
        case "stepUp";
        case "stepDown": {
            private _j = [["stepUp", "stepDown"] select (_op isEqualTo "stepDown"), [_id, _v]] call _RA;
            if (_j >= 0) then { _ui set ["sel", _j]; };
            _full = false;
        };
        case "stepDel": {
            // Deux clics (CONFIRMER), puis bandeau « Étape supprimée · ANNULER ».
            private _ck = "st" + str _v;
            if ((_ui get "confirm") isNotEqualTo _ck) exitWith { _ui set ["confirm", _ck]; _full = (_ui get "view") isNotEqualTo "EDIT"; if (!_full) then { ["#refresh"] call comspec_atak_native_fnc_pageRoute; }; };
            _ui set ["confirm", ""];
            private _nm = (((["get", _id] call _RA) getOrDefault ["steps", []]) param [_v, []]) param [2, ""];
            if !(["stepDel", [_id, _v]] call _RA) exitWith { _full = false; };
            private _s = _ui get "sel";
            _ui set ["sel", [[_s, _s - 1] select (_v < _s), -1] select (_s isEqualTo _v)];
            [format [("Étape %1 supprimée") call _tr, [[_nm] call comspec_atak_native_fnc_markerAutoName] call _esc], "undo"] call _bar;
            if ((_ui get "view") isEqualTo "STEP") then { _ui set ["view", "EDIT"]; } else { _full = false; ["#refresh"] call comspec_atak_native_fnc_pageRoute; };
        };
        case "dup": { private _nid = ["dup", _id] call _RA; if (_nid isNotEqualTo "") then { ["#ui", ["open", _nid]] call comspec_atak_native_fnc_pageRoute; }; _full = false; };
        case "reverse": { ["reverse", _id] call _RA; _ui set ["sel", -1]; _full = false; };
        case "del": {
            if ((_ui get "confirm") isNotEqualTo _id) exitWith { _ui set ["confirm", _id]; _full = false; ["#refresh"] call comspec_atak_native_fnc_pageRoute; };
            private _nm = (["get", _id] call _RA) getOrDefault ["name", ""];
            ["trash", _id] call _RA;
            [format [("Route %1 supprimée") call _tr, [_nm] call _esc], "untrash", _id] call _bar;
            _ui set ["id", ""]; _ui set ["view", "LIST"]; _ui set ["confirm", ""];
        };
        // Partager valide d'abord l'édition (le partage envoie la route), puis l'édition reprend sur ce nouvel état.
        case "share": {
            ["editCommit", true] call _RA;
            ["share", [_id, _v]] call _RA;
            ["editBegin", [_id, false]] call _RA;
            _full = false;
        };
        case "unshare": { ["unshare", _id] call _RA; ["INFO", "Partage retiré", 2, 10] call comspec_atak_native_fnc_notify; _full = false; };
        // Ligne de la liste. SUPPRIMER en deux appuis ; route à moi : annulable (barre « Route supprimée »).
        case "rowDel": {
            _full = false;
            private _ck = "row:" + _v;
            if ((_ui get "confirm") isNotEqualTo _ck) exitWith { _ui set ["confirm", _ck]; ["#refresh"] call comspec_atak_native_fnc_pageRoute; };
            _ui set ["confirm", ""];
            private _nm = (["get", _v] call _RA) getOrDefault ["name", ""];
            private _isM = ["isMine", _v] call _RA;
            ["trash", _v] call _RA;
            if (_isM) then { [format [("Route %1 supprimée") call _tr, [_nm] call _esc], "untrash", _v] call _bar; };
            ["#refresh"] call comspec_atak_native_fnc_pageRoute;
        };
        // PARTAGER : ouvre le choix des destinataires sous la ligne.
        case "rowShare": {
            _full = false;
            _ui set ["shareRow", ["", _v] select ((_ui getOrDefault ["shareRow", ""]) isNotEqualTo _v)];
            ["#refresh"] call comspec_atak_native_fnc_pageRoute;
        };
        // Route reçue : le partage part d'une copie à moi (seul son auteur peut renvoyer l'originale).
        case "rowSend": {
            _full = false;
            _v params ["_rid", "_rsc"];
            if !(["isMine", _rid] call _RA) then { _rid = ["dup", _rid] call _RA; };
            if (_rid isNotEqualTo "") then { ["share", [_rid, _rsc]] call _RA; };
            _ui set ["shareRow", ""];
            ["#refresh"] call comspec_atak_native_fnc_pageRoute;
        };
        case "rowUnshare": {
            _full = false;
            ["unshare", _v] call _RA;
            ["INFO", "Partage retiré", 2, 10] call comspec_atak_native_fnc_notify;
            _ui set ["shareRow", ""];
            ["#refresh"] call comspec_atak_native_fnc_pageRoute;
        };
        case "vis": { private _r = ["get", _v] call _RA; if ((count _r) > 0) then { ["set", [_v, "vis", !(_r get "vis")]] call _RA; }; _full = false; };
        case "map": { ["MAP"] call comspec_atak_native_fnc_navigate; _full = false; };
    };
    if (_full) then { call _rerender; };
    true
};

// Clavier (vue EDIT, STEP, MARKERS) : Ctrl+Z / Ctrl+Y (aussi Ctrl+W, le Z d'un clavier AZERTY), Échap sort du mode de
// saisie, d'une confirmation, ou revient à la fiche de la route. Saisie dans un champ : touches laissées au champ.
if (_page isEqualTo "#key") exitWith {
    _arg params ["_disp", "_key", ["_shift", false], ["_ctrl", false]];
    private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
    if ((_s getOrDefault ["activePage", ""]) isNotEqualTo "ROUTE" || {!(_s getOrDefault ["interactive", false])}) exitWith { false };
    private _view = _ui get "view";
    if !(_view in ["EDIT", "STEP", "MARKERS"]) exitWith { false };
    private _f = focusedCtrl _disp;
    if (!isNull _f && {(ctrlType _f) isEqualTo 2}) exitWith { false };
    switch (true) do {
        case (_ctrl && {_key in [0x2C, 0x11]} && {!_shift}): { [{ ["#ui", ["undo"]] call comspec_atak_native_fnc_pageRoute; }] call comspec_atak_native_fnc_nextFrame; true };
        case (_ctrl && {_key isEqualTo 0x15 || {_shift && {_key in [0x2C, 0x11]}}}): { [{ ["#ui", ["redo"]] call comspec_atak_native_fnc_pageRoute; }] call comspec_atak_native_fnc_nextFrame; true };
        case (_key isEqualTo 0x01 && {_view isEqualTo "EDIT"} && {(_ui get "mode") isNotEqualTo "SEL"}): { [{ ["#ui", ["modeEnd"]] call comspec_atak_native_fnc_pageRoute; }] call comspec_atak_native_fnc_nextFrame; true };
        case (_key isEqualTo 0x01 && {(_ui get "confirm") isNotEqualTo ""}): { _ui set ["confirm", ""]; [{ ["#refresh"] call comspec_atak_native_fnc_pageRoute; }] call comspec_atak_native_fnc_nextFrame; true };
        case (_key isEqualTo 0x01 && {_view in ["STEP", "MARKERS"]}): { [{ ["#ui", ["view", "EDIT"]] call comspec_atak_native_fnc_pageRoute; }] call comspec_atak_native_fnc_nextFrame; true };
        default { false };
    }
};

// Guidage : textes mis à jour sur place (fn_routeAction "tick").
if (_page isEqualTo "#live") exitWith {
    private _c = uiNamespace getVariable ["COMSPEC_ATAK_RteLive", controlNull];
    if (isNull _c) exitWith {};
    if ((_ui get "view") isNotEqualTo "FOLLOW" || {((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["pageDrawn", ""]) isNotEqualTo "ROUTE"}) exitWith { uiNamespace setVariable ["COMSPEC_ATAK_RteLive", controlNull]; };
    _c ctrlSetStructuredText parseText ((call _liveText) call _tr); // i18n:brut
};

private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["_bx", "_by", "_bw", "_bh"];
private _pad = (_l get "pad") * 2;
private _font = _l get "font";
private _fs = _l get "fontSmall";
private _inter = _l get "interactive";
private _view = _ui get "view";
private _r = ["get", _ui get "id"] call _RA;
if (_view in ["EDIT", "STEP", "MARKERS"] && {(count _r) isEqualTo 0}) then { _view = "LIST"; _ui set ["view", "LIST"]; };
private _following = (count (missionNamespace getVariable ["COMSPEC_ATAK_RteFollow", createHashMap])) > 0;
if (!_inter) then { _view = ["LIST", "FOLLOW"] select _following; };
if (_view isEqualTo "FOLLOW" && {!_following}) then { _view = "LIST"; _ui set ["view", "LIST"]; };
private _mine = (count _r) > 0 && {["isMine", _r get "id"] call _RA};
private _ui1 = { params ["_op", ["_v", ""]]; compile format ["['#ui', [%1, %2]] call comspec_atak_native_fnc_pageRoute;", str _op, str _v] };

// Fiche de la route (vue EDIT) : refaite seule à chaque clic, la carte reste.
private _editRows = {
    private _rows = [];
    private _st = _r get "steps";
    private _s = ["stats", _r] call _RA;
    private _sel = _ui get "sel";
    if (_sel >= count _st) then { _sel = -1; _ui set ["sel", -1]; };
    private _draft = _ui get "draft";
    private _kindRow = ["kind", _r get "kind"] call _RA;
    if (!_mine) then {
        _rows pushBack ["text", format ["<t color='" + (COMSPEC_ATAK_Th get "hexWarn") + "'>%1</t>", format [("Route reçue de %1 (%2) : lecture seule. DUPLIQUER pour en faire une copie modifiable.") call _tr, [_r get "from"] call _esc, ["scopeName", _r get "scope"] call _RA]], [0.10, 0.09, 0.05, 1]];
        _rows pushBack ["buttons", [["DUPLIQUER", ["dup"] call _ui1, true], ["SUIVRE", ["follow", _r get "id"] call _ui1]]];
    } else {
        // Édition : annuler, rétablir, valider (un seul envoi), annuler les modifications.
        private _e = ["editInfo"] call _RA;
        private _nU = count (_e getOrDefault ["undo", []]);
        private _nR = count (_e getOrDefault ["redo", []]);
        private _isNew = _e getOrDefault ["isNew", false];
        private _cRev = (_ui get "confirm") isEqualTo "revert";
        _rows pushBack ["buttons", [
            [format ["↶ %1%2", ("ANNULER") call _tr, ["", format [" (%1)", _nU]] select (_nU > 0)], ["undo"] call _ui1, false, _nU > 0, "Annule la dernière modification (Ctrl+Z)"],
            ["↷ RÉTABLIR", ["redo"] call _ui1, false, _nR > 0, "Refait ce qui vient d'être annulé (Ctrl+Y)"],
            ["✓ VALIDER", ["commit"] call _ui1, true, true, "Termine l'édition ; une route partagée part une seule fois, maintenant"]
        ]];
        _rows pushBack ["buttons", [
            [[["ANNULER LES MODIFICATIONS", "ABANDONNER LA NOUVELLE ROUTE"] select _isNew, "CONFIRMER : TOUT ANNULER"] select _cRev, ["revert"] call _ui1, ["red", false] select !_cRev, _isNew || {_e getOrDefault ["dirty", false]},
                ["Remet la route comme à l'ouverture", "Supprime ce brouillon de route"] select _isNew]
        ]];
        (_ui get "undoBar") params [["_ubT", ""], ["_ubOp", ""], ["_ubV", ""], ["_ubAt", -1]];
        if (_ubT isNotEqualTo "" && {(diag_tickTime - _ubAt) < 8}) then {
            _rows pushBack ["person", "", format ["<t color='" + (COMSPEC_ATAK_Th get "hexWarn") + "'>%1</t>", _ubT], [["ANNULER", [_ubOp, _ubV] call _ui1, true]], [0.95, 0.67, 0.2, 1]];
        };
        _rows pushBack ["segment", "Clic sur la carte", [
            ["CHOISIR", ["mode", "SEL"] call _ui1, (_ui get "mode") isEqualTo "SEL", "Un clic choisit une étape, sans rien poser"],
            ["AJOUTER", ["mode", "ADD"] call _ui1, (_ui get "mode") isEqualTo "ADD", "Chaque clic ajoute une étape en fin de route"],
            ["INSÉRER APRÈS", ["mode", "INSERT"] call _ui1, (_ui get "mode") isEqualTo "INSERT", "Chaque clic ajoute une étape après l'étape choisie"],
            ["DÉPLACER", ["mode", "MOVE"] call _ui1, (_ui get "mode") isEqualTo "MOVE", "Le prochain clic déplace l'étape choisie"]
        ], ["Clic sur une étape : la choisir", format [("Étape choisie : %1"), _sel + 1]] select (_sel >= 0)];
        _rows pushBack ["buttons", [
            ["+ MA POSITION", ["addMe"] call _ui1, false, true, "Ajoute ma position GPS"],
            ["+ MARQUEUR", ["view", "MARKERS"] call _ui1, false, true, "Ajoute une étape sur un marqueur de la carte"]
        ]];
        _rows pushBack ["field", "rteGrid", "Ajouter une grille MGRS (4 à 10 chiffres)", _draft getOrDefault ["rteGrid", ""], "AJOUTER", ["addGrid"] call _ui1, false];
    };
    // Calculs.
    private _sub = format ["%1 · %2 %3", [_s getOrDefault ["total", 0]] call _fmt, ("à") call _tr, format ["%1 km/h", _r get "speed"]];
    _rows pushBack ["section", format [("Étapes (%1)") call _tr, count _st], _sub];
    if ((count _st) isEqualTo 0) then {
        _rows pushBack ["text", format ["<t color='" + (COMSPEC_ATAK_Th get "hexTextDim") + "'>%1</t>", ("Aucune étape : cliquez sur la carte, ajoutez ma position, un marqueur ou une grille.") call _tr]];
    } else {
        _rows pushBack ["info", "Distance totale", [_s get "total"] call _fmt];
        _rows pushBack ["info", "Durée estimée", format ["%1 (%2, %3 km/h)", [_s get "dur"] call _dur, ((_kindRow select 1)) call _tr, _r get "speed"]];
        _rows pushBack ["info", "Arrivée si départ maintenant", [_s get "dur"] call _clock];
        if (_r getOrDefault ["busy", false]) then { _rows pushBack ["text", format ["<t color='" + (COMSPEC_ATAK_Th get "hexWarn") + "'>%1</t>", ("Calcul du tracé par les routes…") call _tr]]; };
    };
    {
        _x params ["_p", "_k", "_n", ["_note", ""], ["_hp", -1], ["_ac", ""]];
        private _i = _forEachIndex;
        private _tRow = ["type", _k] call _RA;
        private _leg = if (_i > 0) then { format ["%1 · %2 · ~%3", [(_s get "dist") select (_i - 1)] call _fmt, [(_s get "brg") select (_i - 1)] call _card, [((_s get "dist") select (_i - 1)) / (_s get "v")] call _dur] } else { ("départ") call _tr };
        private _extra = [];
        private _pl = [_r, _hp] call _plan;
        if (_pl isNotEqualTo "") then { _extra pushBack _pl; };
        if (_ac isNotEqualTo "") then { _extra pushBack ((["actName", _ac] call _RA) call _tr); };
        if (_note isNotEqualTo "") then { _extra pushBack format ["« %1 »", [_note select [0, 40]] call _esc]; };
        private _txt = format ["<t font='COMSPEC_BarlowBold' color='%1'>%2. %3</t>  <t color='" + (COMSPEC_ATAK_Th get "hexTextDim") + "'>%4</t><br/>%5%6",
            ["#e6ece8", (COMSPEC_ATAK_Th get "hexOk")] select (_i isEqualTo _sel), _i + 1, [[_n] call comspec_atak_native_fnc_markerAutoName] call _esc, (_tRow select 1) call _tr, _leg,
            ["", format ["<br/><t color='" + (COMSPEC_ATAK_Th get "hexWarn") + "'>%1</t>", _extra joinString " · "]] select ((count _extra) > 0)];
        private _btns = if (_mine) then { [
            ["FICHE", ["step", _i] call _ui1, false, true, "Nom, type, action, H+, consigne"],
            ["▲", ["stepUp", _i] call _ui1, false, _i > 0, "Monter"],
            ["▼", ["stepDown", _i] call _ui1, false, _i < ((count _st) - 1), "Descendre"],
            [["X", "OK ?"] select ((_ui get "confirm") isEqualTo ("st" + str _i)), ["stepDel", _i] call _ui1, ["red", false] select ((_ui get "confirm") isNotEqualTo ("st" + str _i)), true, "Supprimer l'étape (deux clics)"]
        ] } else { [["FICHE", ["step", _i] call _ui1]] };
        _rows pushBack ["person", ["icon", _k] call _RA, _txt, _btns, _tRow select 3];
    } forEach _st;
    if (_mine) then {
        _rows pushBack ["section", "Tracé", ""];
        _rows pushBack ["segment", "Type de tracé", (["kinds"] call _RA) apply {
            [toUpper (_x select 1), ["set", ["kind", _x select 0]] call _ui1, (_x select 0) isEqualTo (_r get "kind")]
        }];
        if ((_r get "kind") isEqualTo "VEH") then {
            _rows pushBack ["switch", "Suivre les routes", _r get "roads", ["toggle", ["roads", "rteRoads"]] call _ui1, "Chaque tronçon passe par le réseau routier (calcul en arrière-plan) ; sinon ligne droite", false, "rteRoads"];
        };
        if ((_r get "kind") isEqualTo "AIR") then {
            _rows pushBack ["field", "rteCorr", "Largeur du couloir (m)", _draft getOrDefault ["rteCorr", str (_r get "corr")], "OK", ["field", ["corr", "rteCorr"]] call _ui1, false];
        };
        _rows pushBack ["field", "rteSpeed", "Vitesse prévue (km/h)", _draft getOrDefault ["rteSpeed", str (_r get "speed")], "OK", ["field", ["speed", "rteSpeed"]] call _ui1, false];
        _rows pushBack ["lineseg", "", "Couleur", (["colors"] call _RA) apply {
            [_x select 2, ["set", ["color", _x select 0]] call _ui1, (_x select 0) isEqualTo (_r get "color"), _x select 1]
        }, "Couleur du trait, des repères d'Arma et d'Athena"];
        _rows pushBack ["segment", "Épaisseur", [["FINE", ["set", ["width", 1]] call _ui1, (_r get "width") isEqualTo 1], ["NORMALE", ["set", ["width", 2]] call _ui1, (_r get "width") isEqualTo 2], ["ÉPAISSE", ["set", ["width", 3]] call _ui1, (_r get "width") isEqualTo 3]]];
        _rows pushBack ["segment", "Style", [["PLEIN", ["set", ["style", "SOLID"]] call _ui1, (_r get "style") isEqualTo "SOLID"], ["POINTILLÉ", ["set", ["style", "DASH"]] call _ui1, (_r get "style") isEqualTo "DASH"], ["FLÈCHES", ["set", ["style", "ARROW"]] call _ui1, (_r get "style") isEqualTo "ARROW", "Flèches de direction sur chaque tronçon"]]];
        _rows pushBack ["field", "rteHh", "Heure H (HH:MM, vide : aucune) : les étapes donnent leur heure prévue", _draft getOrDefault ["rteHh", _r get "hh"], "OK", ["field", ["hh", "rteHh"]] call _ui1, false];
    };
    _rows pushBack ["section", "Affichage", ""];
    _rows pushBack ["switch", "Carte ATAK", _r get "vis", ["toggle", ["vis", "rteVis"]] call _ui1, "Route affichée sur la carte du téléphone", false, "rteVis"];
    if (_mine) then {
        _rows pushBack ["switch", "Carte d'Arma et Athena", _r get "arma", ["toggle", ["arma", "rteArma"]] call _ui1, "Tracé et étapes en marqueurs, sur le canal du groupe (du camp si partagée au camp), aussi envoyés au site Athena", !(missionNamespace getVariable ["comspec_atak_native_route_arma", true]), "rteArma"];
    };
    _rows pushBack ["switch", "Affichage 3D (JVN uniquement)", _r get "d3", ["toggle", ["d3", "rteD3"]] call _ui1, "Pendant « Suivre la route », JVN allumée : trait et étapes restantes affichés dans le monde (à moins de 1,5 km)", !(missionNamespace getVariable ["comspec_atak_native_route_3d", true]), "rteD3"];
    if (_mine) then {
        private _sc = _r get "scope";
        _rows pushBack ["section", "Partage", [("Non partagée") call _tr, format [("Partagée : %1, chaque modification est renvoyée") call _tr, ["scopeName", _sc] call _RA]] select (_sc > 0)];
        _rows pushBack ["segment", "Envoyer à", [
            ["ÉQUIPE", ["share", 1] call _ui1, _sc isEqualTo 1, "Mon équipe de feu (le groupe si je n'en ai pas)"],
            ["GROUPE", ["share", 2] call _ui1, _sc isEqualTo 2, "Tout mon groupe"],
            ["CAMP", ["share", 3] call _ui1, _sc isEqualTo 3, "Tout mon camp"]
        ], "Par le réseau ATAK : sans réseau, l'envoi part au retour du signal"];
        if (_sc > 0) then { _rows pushBack ["buttons", [["RETIRER LE PARTAGE", ["unshare"] call _ui1]]]; };
        _rows pushBack ["section", "Route", ""];
        _rows pushBack ["field", "rteName", "Nom", _draft getOrDefault ["rteName", _r get "name"], "RENOMMER", ["field", ["name", "rteName"]] call _ui1, false];
        _rows pushBack ["buttons", [
            ["DUPLIQUER", ["dup"] call _ui1],
            ["INVERSER", ["reverse"] call _ui1, false, (count _st) > 1, "Parcourir la route dans l'autre sens"],
            [["SUPPRIMER", "CONFIRMER"] select ((_ui get "confirm") isEqualTo (_r get "id")), ["del"] call _ui1, ["red", false] select ((_ui get "confirm") isNotEqualTo (_r get "id")), true, "Supprimer la route (deux clics)"]
        ]];
    };
    _rows
};

// Barre du mode de saisie, sur la carte de l'app (AJOUTER, INSÉRER, DÉPLACER) : consigne, TERMINER, ANNULER. Refaite
// sans rendu de page (fiche refaite seule) ; rien en mode CHOISIR.
private _mapBar = {
    // Masqués tout de suite, supprimés à l'image suivante (même raison que fn_pageClear : jamais pendant un clic).
    private _oldBar = uiNamespace getVariable ["COMSPEC_ATAK_RteMapBar", []];
    { _x ctrlShow false; _x ctrlEnable false; } forEach (_oldBar select { !isNull _x });
    [{ { ctrlDelete _x; } forEach _this; }, _oldBar] call comspec_atak_native_fnc_nextFrame;
    uiNamespace setVariable ["COMSPEC_ATAK_RteMapBar", []];
    uiNamespace setVariable ["COMSPEC_ATAK_RteMapBarRect", []];
    private _mr = uiNamespace getVariable ["COMSPEC_ATAK_RteMapRect", []];
    private _mode = _ui get "mode";
    if ((count _mr) < 4 || {!_mine} || {_mode isEqualTo "SEL"} || {(_ui get "view") isNotEqualTo "EDIT"}) exitWith {};
    _mr params ["_mx", "_my", "_mw", "_mh"];
    private _sel = _ui get "sel";
    private _txt = switch (_mode) do {
        case "INSERT": { format [("Insertion après l'étape %1 : chaque clic pose une étape") call _tr, _sel + 1] };
        case "MOVE": { format [("Déplacement de l'étape %1 : cliquez sa nouvelle place") call _tr, _sel + 1] };
        default { ("Ajout : chaque clic pose une étape en fin de route") call _tr };
    };
    private _h = _font * 2.1;
    private _y = _my + _mh - _h - _pad * 0.5;
    private _bwB = (_mw * 0.2) max (_font * 4.2);
    private _nB = [2, 1] select (_mode isEqualTo "MOVE");
    private _list = [];
    uiNamespace setVariable ["COMSPEC_ATAK_RteMapBarRect", [_mx + _pad * 0.5, _y, _mw - _pad, _h]];
    private _bgc = ["COMSPEC_RscText", [_mx + _pad * 0.5, _y, _mw - _pad, _h], "", false] call comspec_atak_native_fnc_pageCtrl;
    _bgc ctrlSetBackgroundColor [0.03, 0.04, 0.035, 0.9];
    _list pushBack _bgc;
    private _t = ["COMSPEC_RscStructuredText", [_mx + _pad, _y + _h * 0.08, _mw - _pad * 2 - _nB * (_bwB + _pad * 0.4), _h * 0.9], "", false] call comspec_atak_native_fnc_pageCtrl;
    _t ctrlSetStructuredText parseText format ["<t size='0.95' font='COMSPEC_BarlowBold'>%1</t><br/><t size='0.8' color='" + (COMSPEC_ATAK_Th get "hexTextDim") + "'>%2</t>", _txt, ("Échap ou clic droit : terminer · Ctrl+Z : annuler") call _tr]; // i18n:brut
    _list pushBack _t;
    private _bx0 = _mx + _mw - _pad * 0.9 - _bwB;
    {
        _x params ["_lbl", "_code", "_main", "_tip"];
        private _b = ["COMSPEC_RscButton", [_bx0, _y + _h * 0.15, _bwB, _h * 0.7], _lbl, false] call comspec_atak_native_fnc_pageCtrl;
        _b ctrlSetFontHeight ((_fs * 0.95) max COMSPEC_ATAK_FontMin);
        _b ctrlSetTooltip ((((_tip) call _tr)) call comspec_atak_native_fnc_tr);
        if (_main) then { _b ctrlSetBackgroundColor [0.36, 0.78, 0.42, 0.95]; _b ctrlSetTextColor [0.03, 0.05, 0.04, 1]; };
        _b setVariable ["comspec_click", _code];
        _b ctrlAddEventHandler ["ButtonClick", { params ["_c"]; [{ call (_this getVariable ["comspec_click", {}]); }, _c] call comspec_atak_native_fnc_nextFrame; }];
        _list pushBack _b;
        _bx0 = _bx0 - _bwB - _pad * 0.4;
    } forEach ([[["ANNULER", ["modeCancel"] call _ui1, false, "Sort du mode et retire ce qui a été posé depuis"]],
        [["TERMINER", ["modeEnd"] call _ui1, true, "Sort du mode ; les étapes posées restent"], ["ANNULER", ["modeCancel"] call _ui1, false, "Sort du mode et retire ce qui a été posé depuis"]]] select (_nB isEqualTo 2));
    uiNamespace setVariable ["COMSPEC_ATAK_RteMapBar", _list];
};

// Rafraîchissement : vue EDIT affichée, fiche seule refaite (champs saisis gardés) ; sinon rendu de la page.
if (_page isEqualTo "#refresh") exitWith {
    if ((uiNamespace getVariable ["COMSPEC_ATAK_RteDrawnAt", -1]) isEqualTo diag_frameNo) exitWith {};
    private _grp = uiNamespace getVariable ["COMSPEC_ATAK_RteForm", controlNull];
    private _rect = uiNamespace getVariable ["COMSPEC_ATAK_RteFormRect", []];
    if (_view isNotEqualTo "EDIT" || {isNull _grp} || {(count _rect) isNotEqualTo 4} || {((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["pageDrawn", ""]) isNotEqualTo "ROUTE"}) exitWith {
        ["ROUTE"] call comspec_atak_native_fnc_pageRender;
    };
    private _draft = _ui get "draft";
    { private _c = (uiNamespace getVariable ["COMSPEC_ATAK_Form", createHashMap]) getOrDefault [_x, controlNull]; if (!isNull _c) then { _draft set [_x, ctrlText _c]; }; } forEach ["rteGrid", "rteName", "rteSpeed", "rteCorr", "rteHh"];
    // Valeur enregistrée entre-temps (bouton OK) : plus de brouillon.
    if ((_draft getOrDefault ["rteName", ""]) isEqualTo (_r get "name")) then { _draft deleteAt "rteName"; };
    private _sv = (ctrlScrollValues _grp) select 0;
    private _bg = _grp getVariable ["bg", controlNull];
    // Ancienne fiche masquée tout de suite, supprimée à l'image suivante (jamais pendant un clic, fn_pageClear).
    { if (!isNull _x) then { _x ctrlShow false; _x ctrlEnable false; }; } forEach [_grp, _bg];
    [{ { ctrlDelete _x; } forEach _this; }, [_grp, _bg]] call comspec_atak_native_fnc_nextFrame;
    uiNamespace setVariable ["COMSPEC_ATAK_RteDrawnAt", diag_frameNo];
    private _ng = [call _editRows, _rect] call comspec_atak_native_fnc_formRender;
    uiNamespace setVariable ["COMSPEC_ATAK_RteForm", _ng];
    if (_sv > 0) then {
        _ng ctrlSetScrollValues [_sv, -1];
        [{ params ["_g", "_v"]; if (!isNull _g) then { _g ctrlSetScrollValues [_v, -1]; }; }, [_ng, _sv]] call comspec_atak_native_fnc_nextFrame;
    };
    call _mapBar;
    true
};

uiNamespace setVariable ["COMSPEC_ATAK_RteDrawnAt", diag_frameNo];
uiNamespace setVariable ["COMSPEC_ATAK_RteForm", controlNull];
uiNamespace setVariable ["COMSPEC_ATAK_RteLive", controlNull];
uiNamespace setVariable ["COMSPEC_ATAK_RteMapCtrl", controlNull];
private _rows = [];
switch (_view) do {
    case "EDIT": {
        // Barre du haut, carte de l'app (absolue sur l'écran, hors zone défilante) et fiche à côté (paysage) ou dessous.
        private _rowH = (_font * 1.5) min (_bh * 0.08);
        private _topH = _pad / 2 + _rowH + _pad / 3;
        private _land = _l get "landscape";
        private _btn = {
            params ["_rect", "_label", "_code", ["_on", false]];
            private _b = ["COMSPEC_RscButton", _rect, _label] call comspec_atak_native_fnc_pageCtrl;
            _b ctrlSetFontHeight ((_fs * 0.95) max COMSPEC_ATAK_FontMin);
            if (_on) then { _b ctrlSetBackgroundColor [0.36, 0.78, 0.42, 0.95]; _b ctrlSetTextColor [0.03, 0.05, 0.04, 1]; };
            _b setVariable ["comspec_click", _code];
            _b ctrlAddEventHandler ["ButtonClick", { params ["_c"]; [{ call (_this getVariable ["comspec_click", {}]); }, _c] call comspec_atak_native_fnc_nextFrame; }];
            _b
        };
        private _bw3 = (_bw - _pad * 2 - _pad) / 3;
        [[_pad, _pad / 2, _bw3 * 0.8, _rowH], "◀ MES ROUTES", ["view", "LIST"] call _ui1] call _btn;
        private _tt = ["COMSPEC_RscStructuredText", [_pad + _bw3 * 0.8 + _pad / 2, _pad / 2, _bw3 * 1.4, _rowH]] call comspec_atak_native_fnc_pageCtrl;
        _tt ctrlSetStructuredText parseText (((format ["<t font='COMSPEC_BarlowBold' size='1.1'>%1</t><br/><t size='0.85' color='" + (COMSPEC_ATAK_Th get "hexTextDim") + "'>%2</t>", [_r get "name"] call _esc, ((["kind", _r get "kind"] call _RA) select 1) call _tr])) call comspec_atak_native_fnc_tr);
        private _isF = ((missionNamespace getVariable ["COMSPEC_ATAK_RteFollow", createHashMap]) getOrDefault ["id", ""]) isEqualTo (_r get "id");
        [[_bw - _pad - _bw3 * 0.8, _pad / 2, _bw3 * 0.8, _rowH], ["SUIVRE", "GUIDAGE"] select _isF, [["follow", _r get "id"] call _ui1, ["view", "FOLLOW"] call _ui1] select _isF, true] call _btn;
        private _mapRect = if (_land) then { [_bx, _by + _topH, _bw * 0.56, _bh - _topH] } else { [_bx, _by + _topH, _bw, (_bh - _topH) * 0.46] };
        private _formRect = if (_land) then { [_bw * 0.56, _topH, _bw * 0.44, _bh - _topH] } else { [0, _topH + (_bh - _topH) * 0.46, _bw, (_bh - _topH) * 0.54] };
        private _map = ["COMSPEC_RscMap", _mapRect, "", false] call comspec_atak_native_fnc_pageCtrl;
        uiNamespace setVariable ["COMSPEC_ATAK_RteMapCtrl", _map];
        _map ctrlAddEventHandler ["Draw", { private _t = diag_tickTime; ["draw", _this] call comspec_atak_native_fnc_routeMap; ["carte Routes (image)", _t] call comspec_atak_native_fnc_perfAcc; }];
        _map ctrlAddEventHandler ["MouseButtonDown", { ["down", _this] call comspec_atak_native_fnc_routeMap; }];
        _map ctrlAddEventHandler ["MouseButtonUp", { ["up", _this] call comspec_atak_native_fnc_routeMap; }];
        _map ctrlAddEventHandler ["MouseMoving", { ["move", _this] call comspec_atak_native_fnc_routeMap; }];
        // Vue gardée d'un rendu à l'autre ; première ouverture : sur la route, sinon sur moi.
        private _v = uiNamespace getVariable ["COMSPEC_ATAK_RteView", []];
        if ((count _v) isNotEqualTo 2) then {
            private _st = _r get "steps";
            private _c = if ((count _st) > 0) then { (_st select 0) select 0 } else { getPosATL player };
            private _scl = 0.06;
            if ((count _st) > 1) then {
                (["geo", _r] call _RA) params ["", "", "_bb"];
                _c = [((_bb select 0) + (_bb select 2)) / 2, ((_bb select 1) + (_bb select 3)) / 2];
                _scl = ((((_bb select 2) - (_bb select 0)) max ((_bb select 3) - (_bb select 1))) / worldSize * 1.4) max 0.008 min 1;
            };
            _v = [_scl, [_c select 0, _c select 1]];
        };
        _map setVariable ["comspec_rteAim", _v];
        _map setVariable ["comspec_rteBorn", diag_tickTime];
        _map ctrlMapAnimAdd [0, _v select 0, [(_v select 1) select 0, (_v select 1) select 1, 0]];
        ctrlMapAnimCommit _map;
        uiNamespace setVariable ["COMSPEC_ATAK_RteFormRect", _formRect];
        uiNamespace setVariable ["COMSPEC_ATAK_RteMapRect", _mapRect];
        uiNamespace setVariable ["COMSPEC_ATAK_RteForm", [call _editRows, _formRect] call comspec_atak_native_fnc_formRender];
        call _mapBar;
        // Clavier de l'app (Ctrl+Z / Ctrl+Y, Échap) : un gestionnaire par écran du téléphone, actif sur cette page seulement.
        private _disp = [] call comspec_atak_native_fnc_display;
        if (!isNull _disp && {(_disp getVariable ["COMSPEC_RteKeyEh", -1]) < 0}) then {
            _disp setVariable ["COMSPEC_RteKeyEh", _disp displayAddEventHandler ["KeyDown", { ["#key", _this] call comspec_atak_native_fnc_pageRoute }]];
        };
        (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) set ["backSub", ["ROUTE", { ['#ui', ['view', 'LIST']] call comspec_atak_native_fnc_pageRoute; }]];
    };
    case "STEP": {
        private _st = _r get "steps";
        private _i = _ui get "sel";
        if (_i < 0 || {_i >= count _st}) exitWith { _ui set ["view", "EDIT"]; call _rerender; };
        (_st select _i) params ["_p", "_k", "_n", ["_note", ""], ["_hp", -1], ["_ac", ""]];
        private _s = ["stats", _r] call _RA;
        _rows pushBack ["buttons", [["◀ ROUTE", ["view", "EDIT"] call _ui1]]];
        _rows pushBack ["section", format [("Étape %1 / %2 · %3") call _tr, _i + 1, count _st, [_r get "name"] call _esc], [_p, 10] call comspec_atak_native_fnc_gridRef];
        if (_i > 0) then { _rows pushBack ["info", "Depuis l'étape précédente", format ["%1 · %2", [(_s get "dist") select (_i - 1)] call _fmt, [(_s get "brg") select (_i - 1)] call _card]]; };
        if (_i < ((count _st) - 1)) then { _rows pushBack ["info", "Vers l'étape suivante", format ["%1 · %2", [(_s get "dist") select _i] call _fmt, [(_s get "brg") select _i] call _card]]; };
        _rows pushBack ["info", "Depuis le départ", format ["%1 · ~%2", [(_s get "cum") select _i] call _fmt, [((_s get "cum") select _i) / (_s get "v")] call _dur]];
        _rows pushBack ["info", "Altitude", format ["%1 m", round (getTerrainHeightASL _p)]];
        _rows pushBack ["info", "Depuis moi", format ["%1 · %2", [player distance2D _p] call _fmt, [player getDir _p] call _card]];
        if (_mine) then {
            _rows append [
                ["edit", "stName", "Nom", _n],
                ["combo", "stType", "Type d'étape", (["types"] call _RA) apply { [(_x select 1) call _tr, _x select 0, ["icon", _x select 0] call _RA, _x select 3] }, _k],
                ["combo", "stAct", "Action attendue", (["acts"] call _RA) apply { [(_x select 1) call _tr, _x select 0] }, _ac],
                ["edit", "stHp", "Heure prévue : H+ en minutes (vide : aucune)", ["", str _hp] select (_hp >= 0)],
                ["memo", "stNote", "Consigne", _note, 2],
                ["buttons", [["ENREGISTRER", ["stepSave"] call _ui1, true]]],
                ["buttons", [
                    ["MA POSITION ICI", ["stepHere"] call _ui1, false, true, "Place l'étape sur ma position GPS"],
                    ["DÉPLACER SUR LA CARTE", compile format ["['#ui', ['view', 'EDIT']] call comspec_atak_native_fnc_pageRoute; ['#ui', ['mode', 'MOVE']] call comspec_atak_native_fnc_pageRoute;"], false, true, "Le prochain clic sur la carte déplace l'étape"]
                ]],
                ["buttons", [
                    ["INSÉRER APRÈS", compile format ["['#ui', ['view', 'EDIT']] call comspec_atak_native_fnc_pageRoute; ['#ui', ['mode', 'INSERT']] call comspec_atak_native_fnc_pageRoute;"], false, true, "Les clics sur la carte ajoutent des étapes après celle-ci"],
                    ["SUIVRE D'ICI", compile format ["['#ui', ['follow', [%1, %2]]] call comspec_atak_native_fnc_pageRoute;", str (_r get "id"), _i]],
                    ["SUPPRIMER", ["stepDel", _i] call _ui1, "red"]
                ]]
            ];
        } else {
            _rows pushBack ["info", "Nom", [[_n] call comspec_atak_native_fnc_markerAutoName] call _esc];
            _rows pushBack ["info", "Type", ((["type", _k] call _RA) select 1) call _tr];
            _rows pushBack ["info", "Action attendue", (["actName", _ac] call _RA) call _tr];
            private _pl = [_r, _hp] call _plan;
            if (_pl isNotEqualTo "") then { _rows pushBack ["info", "Heure prévue", _pl]; };
            if (_note isNotEqualTo "") then { _rows pushBack ["text", format ["<t color='" + (COMSPEC_ATAK_Th get "hexWarn") + "'>%1</t> %2", ("Consigne :") call _tr, [_note] call _esc]]; };
            _rows pushBack ["buttons", [["SUIVRE D'ICI", compile format ["['#ui', ['follow', [%1, %2]]] call comspec_atak_native_fnc_pageRoute;", str (_r get "id"), _i]]]];
        };
        (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) set ["backSub", ["ROUTE", { ['#ui', ['view', 'EDIT']] call comspec_atak_native_fnc_pageRoute; }]];
    };
    case "MARKERS": {
        _rows pushBack ["buttons", [["◀ ROUTE", ["view", "EDIT"] call _ui1]]];
        private _me = getPosATL player;
        private _mk = (allMapMarkers select { (markerShape _x) isEqualTo "ICON" && {(markerType _x) isNotEqualTo ""} && {(markerAlpha _x) > 0} && {(markerType _x) isNotEqualTo "Empty"} }) apply { [_me distance2D (markerPos _x), _x] };
        _mk sort true;
        _rows pushBack ["section", "Ajouter une étape sur un marqueur", [("Marqueurs les plus proches de moi") call _tr, ("La nouvelle étape est insérée après l'étape choisie") call _tr] select ((_ui get "mode") isEqualTo "INSERT")];
        if ((count _mk) isEqualTo 0) then { _rows pushBack ["empty", "\a3\ui_f\data\map\markers\military\flag_ca.paa", "Aucun marqueur", "Posez un marqueur sur la carte, puis revenez ici."]; };
        {
            _x params ["_d", "_m"];
            private _t = markerText _m;
            if (_t isEqualTo "") then { _t = getText (configFile >> "CfgMarkers" >> markerType _m >> "name"); };
            _rows pushBack ["person", getText (configFile >> "CfgMarkers" >> markerType _m >> "icon"), format ["<t font='COMSPEC_BarlowBold'>%1</t><br/><t color='" + (COMSPEC_ATAK_Th get "hexTextDim") + "'>%2 · %3</t>", [_t select [0, 40]] call _esc, [_d] call _fmt, [markerPos _m, 8] call comspec_atak_native_fnc_gridRef],
                [["AJOUTER", ["addMarker", _m] call _ui1, true]]];
        } forEach (_mk select [0, 25]);
        (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) set ["backSub", ["ROUTE", { ['#ui', ['view', 'EDIT']] call comspec_atak_native_fnc_pageRoute; }]];
    };
    case "FOLLOW": {
        private _fi = ["followInfo"] call _RA;
        if ((count _fi) isEqualTo 0) exitWith { _ui set ["view", "LIST"]; call _rerender; };
        private _fr = _fi get "route";
        private _st = _fr get "steps";
        private _i = _fi get "idx";
        _rows pushBack ["section", format [("Suivi : %1") call _tr, [_fr get "name"] call _esc], format ["%1 · %2", ((["kind", _fr get "kind"] call _RA) select 1) call _tr, [(_fi get "stats") get "total"] call _fmt]];
        // Frise des étapes : celles autour de l'étape visée (12 au plus).
        private _a = ((_i - 4) max 0) min (((count _st) - 12) max 0);
        private _frise = [];
        for "_j" from _a to ((_a + 11) min ((count _st) - 1)) do {
            _frise pushBack [([(_st select _j) select 2] call comspec_atak_native_fnc_markerAutoName) select [0, 8], ["todo", "now", "done"] select ([_j > _i, _j isEqualTo _i, _j < _i] find true)];
        };
        _rows pushBack ["steps", _frise];
        _rows pushBack ["text", call _liveText, [0.05, 0.075, 0.06, 1], "COMSPEC_ATAK_RteLive"];
        _rows pushBack ["buttons", [
            ["◀ PRÉC.", { ["prev"] call comspec_atak_native_fnc_routeAction; }, false, _i > 0],
            ["ARRÊTER", ["unfollow"] call _ui1, "red"],
            ["SUIV. ▶", { ["next"] call comspec_atak_native_fnc_routeAction; }, false, _i < ((count _st) - 1)]
        ]];
        _rows pushBack ["buttons", [["VOIR SUR LA CARTE", ["map"] call _ui1], ["ROUTE", ["open", _fr get "id"] call _ui1]]];
        _rows pushBack ["text", format ["<t color='" + (COMSPEC_ATAK_Th get "hexTextDim") + "' size='0.9'>%1</t>", ("Le téléphone vibre à chaque étape atteinte ; la montre Garmin (boussole) pointe l'étape visée.") call _tr]];
    };
    default {
        // Liste.
        private _tab = _ui get "tab";
        private _mineL = values (["mine"] call _RA);
        private _rxL = values (["rx"] call _RA);
        private _itL = ["itins"] call _RA;
        _rows pushBack ["segment", "", [
            [format [("MES ROUTES (%1)") call _tr, count _mineL], ["tab", "MINE"] call _ui1, _tab isEqualTo "MINE"],
            [format [("REÇUES (%1)") call _tr, count _rxL], ["tab", "RX"] call _ui1, _tab isEqualTo "RX", "Routes partagées par l'équipe, le groupe ou le camp"],
            [format [("ITINÉRAIRES (%1)") call _tr, count _itL], ["tab", "ITIN"] call _ui1, _tab isEqualTo "ITIN", "Itinéraire de l'app Itinéraire et guidage GPS « Y aller » (ligne jaune)"]
        ]];
        // Bandeau « … supprimé(e) · ANNULER » (8 s).
        (_ui get "undoBar") params [["_ubT", ""], ["_ubOp", ""], ["_ubV", ""], ["_ubAt", -1]];
        if (_ubT isNotEqualTo "" && {(diag_tickTime - _ubAt) < 8}) then {
            _rows pushBack ["person", "", format ["<t color='" + (COMSPEC_ATAK_Th get "hexWarn") + "'>%1</t>", _ubT], [["ANNULER", [_ubOp, _ubV] call _ui1, true]], [0.95, 0.67, 0.2, 1]];
        };
        if (_following) then {
            private _fi = ["followInfo"] call _RA;
            if ((count _fi) > 0) then {
                _rows pushBack ["text", format ["<t color='" + (COMSPEC_ATAK_Th get "hexOk") + "' font='COMSPEC_BarlowBold'>%1</t> %2 · %3 %4 · %5", ("SUIVI EN COURS") call _tr, [(_fi get "route") get "name"] call _esc,
                    ("cap sur") call _tr, [[(_fi get "step") select 2] call comspec_atak_native_fnc_markerAutoName] call _esc, [_fi get "dist"] call _fmt], [0.06, 0.11, 0.07, 1]];
                _rows pushBack ["buttons", [["GUIDAGE", ["view", "FOLLOW"] call _ui1, true], ["ARRÊTER", ["unfollow"] call _ui1]]];
            };
        };
        if (_tab isEqualTo "ITIN") exitWith {
            if ((count _itL) isEqualTo 0) then {
                _rows pushBack ["empty", "\z\comspec_atak_native\addons\main\data\app_route.paa", "Aucun itinéraire", "L'itinéraire de l'app Itinéraire et le guidage GPS « Y aller » apparaissent ici, pour les effacer."];
            };
            {
                _x params ["_k", "_n", "_d"];
                private _ck = "itin" + _k;
                private _cf = (_ui get "confirm") isEqualTo _ck;
                _rows pushBack ["person", ["\z\comspec_atak_native\addons\main\data\app_waypoints.paa", "\z\comspec_atak_native\addons\main\data\map_route.paa"] select (_k isEqualTo "GPS"),
                    format ["<t font='COMSPEC_BarlowBold'>%1</t><br/><t color='" + (COMSPEC_ATAK_Th get "hexTextDim") + "'>%2</t>", [_n] call _esc, _d],
                    ([[], [["COPIER DANS LES ROUTES", ["import"] call _ui1, false, true, "Reprend ses étapes dans une nouvelle route"]]] select (_k isEqualTo "WP")) + [
                        [["SUPPRIMER", "CONFIRMER"] select _cf, ["itinDel", _k] call _ui1, ["red", false] select !_cf, true, ["Arrête le guidage et efface la ligne (deux clics)", "Efface l'itinéraire, aussi chez ceux qui le partagent (deux clics)"] select (_k isEqualTo "WP")]
                    ], [[0.95, 0.75, 0.18, 1], [1, 0.85, 0.15, 1]] select (_k isEqualTo "GPS")];
            } forEach _itL;
        };
        private _list = [_rxL, _mineL] select (_tab isEqualTo "MINE");
        _list = _list apply { [toLower (_x get "name"), _x] };
        _list sort true;
        if (_tab isEqualTo "MINE") then {
            _rows pushBack ["buttons", [["+ NOUVELLE ROUTE", ["new", "FOOT"] call _ui1, true], ["IMPORTER L'ITINÉRAIRE", ["import"] call _ui1, false, true, "Reprend les étapes de l'app Itinéraire"]]];
        };
        if ((count _list) isEqualTo 0) then {
            _rows pushBack ["empty", "\z\comspec_atak_native\addons\main\data\app_route.paa", ["Aucune route reçue", "Aucune route"] select (_tab isEqualTo "MINE"),
                ["Les routes partagées par votre équipe, votre groupe ou votre camp arrivent ici.", "Créez une route, puis posez ses étapes sur la carte de l'app."] select (_tab isEqualTo "MINE")];
        };
        {
            private _x2 = _x select 1;
            private _s = ["stats", _x2] call _RA;
            private _k = ["kind", _x2 get "kind"] call _RA;
            private _tags = [];
            if ((_x2 get "scope") > 0) then { _tags pushBack ([format [("partagée : %1") call _tr, ["scopeName", _x2 get "scope"] call _RA], format [("de %1 · %2") call _tr, [_x2 get "from"] call _esc, ["scopeName", _x2 get "scope"] call _RA]] select (_tab isNotEqualTo "MINE")); };
            if (_x2 get "arma") then { _tags pushBack (("carte d'Arma") call _tr); };
            if (_x2 get "d3") then { _tags pushBack "3D"; };
            if !(_x2 get "vis") then { _tags pushBack (("masquée") call _tr); };
            private _txt = format ["<t font='COMSPEC_BarlowBold'>%1</t>  <t color='" + (COMSPEC_ATAK_Th get "hexTextDim") + "'>%2 · %3</t><br/>%4 · ~%5%6",
                [_x2 get "name"] call _esc, (_k select 1) call _tr, format [("%1 étape(s)") call _tr, count (_x2 get "steps")], [_s getOrDefault ["total", 0]] call _fmt, [_s getOrDefault ["dur", 0]] call _dur,
                ["", format ["<br/><t color='#8fd19a'>%1</t>", _tags joinString " · "]] select ((count _tags) > 0)];
            // Boutons sous le texte, sur toute la largeur (ils passent sur deux lignes si l'écran est étroit) : cinq à droite du
            // texte ne laissaient plus de place au nom sur le petit écran.
            private _rid = _x2 get "id";
            private _delArm = (_ui get "confirm") isEqualTo ("row:" + _rid);
            private _isMineR = _tab isEqualTo "MINE";
            _rows pushBack ["person", _k select 2, _txt, [], ["rgba", _x2 get "color"] call _RA];
            _rows pushBack ["buttons", [
                ["OUVRIR", ["open", _rid] call _ui1, true],
                ["SUIVRE", ["follow", _rid] call _ui1, false, (count (_x2 get "steps")) > 0],
                [["AFFICHER", "MASQUER"] select (_x2 get "vis"), ["vis", _rid] call _ui1, false, true, "Sur la carte ATAK"],
                ["PARTAGER", ["rowShare", _rid] call _ui1, false, (count (_x2 get "steps")) > 0, ["Envoie une copie à mon équipe, mon groupe ou mon camp", "Choisir à qui envoyer cette route"] select _isMineR],
                [["SUPPRIMER", "CONFIRMER"] select _delArm, ["rowDel", _rid] call _ui1, ["red", false] select !_delArm, true, "Supprimer la route (deux clics)"]
            ]];
            if ((_ui getOrDefault ["shareRow", ""]) isEqualTo _rid) then {
                private _sc = [0, _x2 get "scope"] select _isMineR;
                _rows pushBack ["segment", "Envoyer à", [
                    ["ÉQUIPE", ["rowSend", [_rid, 1]] call _ui1, _sc isEqualTo 1, "Mon équipe de feu (le groupe si je n'en ai pas)"],
                    ["GROUPE", ["rowSend", [_rid, 2]] call _ui1, _sc isEqualTo 2, "Tout mon groupe"],
                    ["CAMP", ["rowSend", [_rid, 3]] call _ui1, _sc isEqualTo 3, "Tout mon camp"]
                ], "Par le réseau ATAK : sans réseau, l'envoi part au retour du signal"];
                if (_sc > 0) then { _rows pushBack ["buttons", [["RETIRER LE PARTAGE", ["rowUnshare", _rid] call _ui1]]]; };
            };
        } forEach _list;
    };
};
if (_view isNotEqualTo "EDIT") then { [_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender; };
true
