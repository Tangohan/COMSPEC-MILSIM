/*
    App Zones (page ZONES) : battleplan de situation. Vues (uiNamespace COMSPEC_ATAK_ZnUi, clé view) :
      LIST  onglets MES PLANS / REÇUS / SITUATION (zones par état, derniers signalements avec l'heure), affichage sur la
            carte ATAK, nouveau plan, SIGNALER ICI (zone sous ma position) ;
      EDIT  (2.9.38) grande carte de l'app avec sa barre d'outils (sélection, rectangle, polygone [aimanté, fermé ou
            ouvert], cercle, annuler, rétablir ; fn_zoneMap) et panneau latéral en onglets :
              TRACER    zone de travail, polygone aimanté, contours et traits (choisir, sommets, style, supprimer) ;
              DÉCOUPER  méthode (grille, îlots, secteurs, une zone, mes traits), aperçu, GÉNÉRER / ANNULER LE CALCUL, axes ;
              NOMMER    nom du plan, zones (recherche, tri, pastille d'état) ;
              STYLE     thème, rendu, couleurs par type et par état, opacité, épaisseur, trait, étiquettes, sélection ;
              SITUATION état de la zone choisie, compteurs, partage (camp par défaut), affichage, remise à zéro, suppression ;
            fiche d'une zone dans le panneau (état, signaler avec barre d'actions fixe, signalements, nom, type).
    Un clic (carte ou panneau) ne refait que le panneau et les barres de la carte : la carte garde sa vue, la liste son
    défilement (gardé par onglet), les champs saisis leur texte.
    Édition (fn_zoneAction editBegin… editCommit) : ANNULER / RÉTABLIR (Ctrl+Z / Ctrl+Y, 25 pas), VALIDER (un seul envoi).
    Clavier : Suppr / Retour arrière (dernier sommet du tracé, sommet, trait ou zone choisis, avec « ANNULER »), Échap
    (tracé annulé, sélection retirée), Entrée (tracé terminé), Alt maintenu (polygone sans aimant).
    Téléphone porté (sans souris) : onglet SITUATION.
    Params : [page, rectangle] (app de module, fn_pageRender) | ["#refresh"] | ["#ui", [op, valeur]]
             | ["#open", [id, id de zone, position du signalement]] (menu de la carte, SIGNALER ICI)
             | ["#key", paramètres de KeyDown] | ["#keyUp", paramètres de KeyUp]
*/
params [["_page", "ZONES"], ["_arg", []]];
disableSerialization;
private _ui = uiNamespace getVariable "COMSPEC_ATAK_ZnUi";
if (isNil "_ui") then {
    _ui = createHashMapFromArray [["view", "LIST"], ["tab", "MINE"], ["id", ""], ["zid", ""], ["tool", ""], ["draftPts", []], ["draft", createHashMap], ["confirm", ""], ["repPos", []], ["repNat", "UNK"],
        ["ztab", "DRAW"], ["panel", "TAB"], ["lid", ""], ["vtx", -1], ["polyMag", true], ["polyR", 30], ["polyOpen", false], ["polyFor", "AREA"], ["snap", []], ["undoBar", []],
        ["find", ""], ["sort", "NUM"], ["scroll", createHashMap], ["alt", false]];
    uiNamespace setVariable ["COMSPEC_ATAK_ZnUi", _ui];
};
private _tr = { _this call comspec_atak_native_fnc_tr };
private _ZA = comspec_atak_native_fnc_zoneAction;
private _esc = { params ["_t"]; [_t, "&", "&amp;"] call CBA_fnc_replace };
private _fmt = { params ["_m"]; [format ["%1 m", round _m], format ["%1 km", (_m / 1000) toFixed 1]] select (_m >= 1000) };
private _card = { params ["_a"]; private _t = str ((round _a) mod 360); while { (count _t) < 3 } do { _t = "0" + _t; }; format ["%1° %2", _t, [_a] call comspec_atak_native_fnc_cardinal] };
private _hex = { params ["_c"]; (_c select [0, 3]) call BIS_fnc_colorRGBtoHTML };
private _ago = { params ["_t"]; private _m = round ((serverTime - _t) / 60); [format [("il y a %1 min") call _tr, _m], ("à l'instant") call _tr] select (_m < 1) };
private _active = { ((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "ZONES" };
private _rerender = { [{
    if ((uiNamespace getVariable ["COMSPEC_ATAK_ZnDrawnAt", -1]) isEqualTo diag_frameNo) exitWith {};
    if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "ZONES") then { ["ZONES"] call comspec_atak_native_fnc_pageRender; };
}] call comspec_atak_native_fnc_nextFrame; };
private _refresh = { ["#refresh"] call comspec_atak_native_fnc_pageZones; };
private _ui1 = { params ["_op", ["_v", ""]]; compile format ["['#ui', [%1, %2]] call comspec_atak_native_fnc_pageZones;", str _op, str _v] };
// Bandeau « … · ANNULER » (8 s) : [texte, opération d'annulation, valeur].
private _bar = {
    params ["_t", "_op", ["_v", ""]];
    private _at = diag_tickTime;
    _ui set ["undoBar", [_t, _op, _v, _at]];
    [{
        private _u = uiNamespace getVariable ["COMSPEC_ATAK_ZnUi", createHashMap];
        if (((_u getOrDefault ["undoBar", []]) param [3, -1]) isEqualTo _this) then {
            _u set ["undoBar", []];
            if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "ZONES") then { ["#refresh"] call comspec_atak_native_fnc_pageZones; };
        };
    }, _at, 8] call comspec_atak_native_fnc_waitAndExec;
};
// Fin d'un tracé de polygone : zone de travail, ou contour / trait du plan.
private _finish = {
    private _pts = _ui get "draftPts";
    private _id = _ui get "id";
    private _open = _ui get "polyOpen";
    if ((count _pts) < ([3, 2] select _open)) exitWith { [[("3 sommets au moins") call _tr, ("2 points au moins") call _tr] select _open, 2] call comspec_atak_native_fnc_toast; false };
    if (!_open && {(_ui get "polyFor") isEqualTo "AREA"}) then {
        ["areaSet", [_id, "POLY", (_pts select [0, 60]) apply { [_x select 0, _x select 1] }]] call _ZA;
        _ui set ["next", "AREA"];
    } else {
        private _lid = ["lineAdd", [_id, _pts, !_open]] call _ZA;
        if (_lid isNotEqualTo "") then { _ui set ["lid", _lid]; _ui set ["ztab", "DRAW"]; };
    };
    _ui set ["tool", ""]; _ui set ["draftPts", []]; _ui set ["snap", []];
    true
};

// Ouverture d'une zone depuis la carte (menu du clic droit) ou SIGNALER ICI : fiche dans le panneau du plan.
if (_page isEqualTo "#open") exitWith {
    _arg params [["_id", ""], ["_zid", ""], ["_pos", []]];
    if ((_ui get "id") isNotEqualTo _id) then { uiNamespace setVariable ["COMSPEC_ATAK_ZnView", []]; };
    _ui set ["id", _id]; _ui set ["zid", _zid]; _ui set ["view", "EDIT"]; _ui set ["panel", "ZONE"]; _ui set ["repPos", _pos]; _ui set ["confirm", ""];
    _ui set ["tool", ""]; _ui set ["draftPts", []];
    if (["isMine", _id] call _ZA) then { ["editBegin", _id] call _ZA; };
    if (call _active) then { call _rerender; } else { ["ZONES"] call comspec_atak_native_fnc_navigate; };
    true
};

// Clavier (vue EDIT) : voir l'en-tête. Saisie dans un champ : Suppr et Retour arrière restent au texte.
if (_page isEqualTo "#keyUp") exitWith {
    _arg params ["", "_key"];
    if (_key in [0x38, 0xB8]) then { _ui set ["alt", false]; };
    false
};
if (_page isEqualTo "#key") exitWith {
    _arg params ["_disp", "_key", ["_shift", false], ["_ctrl", false], ["_alt", false]];
    if (_key in [0x38, 0xB8]) then { _ui set ["alt", true]; };
    private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
    if ((_s getOrDefault ["activePage", ""]) isNotEqualTo "ZONES" || {!(_s getOrDefault ["interactive", false])} || {(_ui get "view") isNotEqualTo "EDIT"}) exitWith { false };
    private _f = focusedCtrl _disp;
    if (!isNull _f && {(ctrlType _f) isEqualTo 2}) exitWith {
        // Entrée dans le champ « Effectif » de la fiche : signalement envoyé.
        if (_key in [28, 156] && {_f isEqualTo ((uiNamespace getVariable ["COMSPEC_ATAK_Form", createHashMap]) getOrDefault ["znSize", controlNull])}) exitWith {
            [{ ["#ui", ["report", ""]] call comspec_atak_native_fnc_pageZones; }] call comspec_atak_native_fnc_nextFrame; true
        };
        false
    };
    private _later = { [{ ["#ui", _this] call comspec_atak_native_fnc_pageZones; }, _this] call comspec_atak_native_fnc_nextFrame; true };
    switch (true) do {
        case (_ctrl && {_key in [0x2C, 0x11]} && {!_shift}): { ["undo"] call _later };
        case (_ctrl && {_key isEqualTo 0x15 || {_shift && {_key in [0x2C, 0x11]}}}): { ["redo"] call _later };
        case (_key in [0xD3, 0x0E]): { ["delKey"] call _later };
        case (_key in [28, 156] && {(_ui get "tool") isEqualTo "POLY"}): { ["polyEnd"] call _later };
        case (_key isEqualTo 0x01 && {(_ui get "tool") isNotEqualTo "" || {(_ui get "lid") isNotEqualTo ""} || {(_ui get "zid") isNotEqualTo ""} || {(_ui get "panel") isEqualTo "ZONE"} || {(_ui get "confirm") isNotEqualTo ""}}): { ["escape"] call _later };
        default { false };
    }
};

if (_page isEqualTo "#ui") exitWith {
    _arg params [["_op", ""], ["_v", ""]];
    private _id = _ui get "id";
    // Choix proposé après la pose d'une zone de travail (DÉCOUPER / TRACER, consigne de la carte) : retiré au geste suivant.
    if (_op isNotEqualTo "afterArea") then { _ui set ["next", ""]; };
    private _full = false;
    private _form = { params ["_k", ["_d", ""]]; [_k, _d] call comspec_atak_native_fnc_formValue };
    switch (_op) do {
        case "view": {
            if (_v isEqualTo "LIST" && {(count (["editInfo"] call _ZA)) > 0}) then { ["editCommit"] call _ZA; };
            _ui set ["view", _v]; _ui set ["confirm", ""]; _ui set ["tool", ""]; _ui set ["draftPts", []]; _full = true;
        };
        case "tab": { _ui set ["tab", _v]; _full = true; };
        case "open": {
            _ui set ["id", _v]; _ui set ["zid", ""]; _ui set ["lid", ""]; _ui set ["vtx", -1]; _ui set ["view", "EDIT"]; _ui set ["panel", "TAB"]; _ui set ["tool", ""]; _ui set ["draftPts", []];
            _ui set ["draft", createHashMap]; _ui set ["confirm", ""]; _ui set ["undoBar", []]; _ui set ["scroll", createHashMap];
            uiNamespace setVariable ["COMSPEC_ATAK_ZnView", []];
            if (["isMine", _v] call _ZA) then { ["editBegin", _v] call _ZA; };
            _full = true;
        };
        case "new": {
            private _nid = ["new", [""]] call _ZA;
            if (_nid isEqualTo "") exitWith {};
            ["#ui", ["open", _nid]] call comspec_atak_native_fnc_pageZones;
            _ui set ["tool", "RECT"]; _ui set ["ztab", "DRAW"];
            ["INFO", "Plan créé : tracez la zone de travail sur la carte (rectangle, polygone ou cercle)", 5, 20] call comspec_atak_native_fnc_notify;
        };
        case "ztab": { _ui set ["ztab", _v]; _ui set ["panel", "TAB"]; _ui set ["confirm", ""]; call _refresh; };
        case "panel": { _ui set ["panel", _v]; call _refresh; };
        case "show": {
            private _on = !(profileNamespace getVariable ["COMSPEC_ATAK_ZonesShow", true]);
            profileNamespace setVariable ["COMSPEC_ATAK_ZonesShow", _on];
            ["znShow", _on] call comspec_atak_native_fnc_formSet;
            playSound "ClickSoft";
        };
        case "vis": { private _b = ["get", _v] call _ZA; if ((count _b) > 0) then { ["set", [_v, "vis", !(_b get "vis")]] call _ZA; }; _full = (_ui get "view") isNotEqualTo "EDIT"; };
        // Outils de la carte : "" sélection, RECT, POLY, CIRCLE.
        case "tool": {
            _ui set ["tool", [_v, ""] select ((_ui get "tool") isEqualTo _v && {_v isNotEqualTo ""})]; _ui set ["draftPts", []]; _ui set ["snap", []]; _ui set ["vtx", -1]; _ui set ["alt", false];
            if (_v isEqualTo "POLY") then { _ui set ["polyFor", ["LINE", "AREA"] select ((count ((["get", _id] call _ZA) getOrDefault ["area", []])) isEqualTo 0)]; };
            call _refresh;
        };
        // Options du polygone : [clé, valeur].
        case "poly": {
            _v params ["_k", "_val"];
            _ui set [_k, _val];
            if (_k isEqualTo "polyOpen" && {_val}) then { _ui set ["polyFor", "LINE"]; };
            if (_k isEqualTo "polyFor" && {_val isEqualTo "AREA"}) then { _ui set ["polyOpen", false]; };
            playSound "ClickSoft";
            call _refresh;
        };
        case "polyEnd": { if (call _finish) then { playSound "ClickSoft"; }; call _refresh; };
        // Après la pose d'une zone de travail (rectangle, cercle, polygone, clic droit › Nouveau plan à partir d'ici) :
        // DÉCOUPER (onglet DÉCOUPER, aperçu sur la carte) ou TRACER (polygone : contours et traits du plan).
        case "afterArea": {
            _ui set ["next", ""];
            if (_v isEqualTo "CUT") exitWith { ["#ui", ["ztab", "CUT"]] call comspec_atak_native_fnc_pageZones; };
            _ui set ["ztab", "DRAW"];
            ["#ui", ["tool", "POLY"]] call comspec_atak_native_fnc_pageZones;
        };
        case "polyBack": {
            private _p = _ui get "draftPts";
            if ((count _p) > 0) then { _p deleteAt ((count _p) - 1); playSound "ClickSoft"; };
            call _refresh;
        };
        case "drawCancel": { _ui set ["tool", ""]; _ui set ["draftPts", []]; _ui set ["snap", []]; call _refresh; };
        case "escape": {
            switch (true) do {
                case ((_ui get "confirm") isNotEqualTo ""): { _ui set ["confirm", ""]; };
                case ((_ui get "tool") isNotEqualTo ""): { _ui set ["tool", ""]; _ui set ["draftPts", []]; _ui set ["snap", []]; };
                case ((_ui get "vtx") >= 0): { _ui set ["vtx", -1]; };
                case ((_ui get "lid") isNotEqualTo ""): { _ui set ["lid", ""]; };
                case ((_ui get "panel") isEqualTo "ZONE"): { _ui set ["panel", "TAB"]; };
                default { _ui set ["zid", ""]; };
            };
            call _refresh;
        };
        // Suppr / Retour arrière : dernier point du tracé, sommet choisi, trait choisi, zone choisie (annulable).
        case "delKey": {
            private _b = ["get", _id] call _ZA;
            if ((_ui get "tool") isNotEqualTo "") exitWith { ["#ui", ["polyBack"]] call comspec_atak_native_fnc_pageZones; };
            if !(["isMine", _id] call _ZA) exitWith { ["Plan reçu : le découpage appartient à son auteur", 2] call comspec_atak_native_fnc_toast; };
            private _lid = _ui get "lid";
            if (_lid isNotEqualTo "" && {(_ui get "vtx") >= 0}) exitWith {
                if (["lineSet", [_id, _lid, "vdel", "", _ui get "vtx"]] call _ZA) then { _ui set ["vtx", -1]; [("Sommet supprimé") call _tr, "undo"] call _bar; };
                call _refresh;
            };
            if (_lid isNotEqualTo "") exitWith {
                private _ln = (((_b getOrDefault ["lines", []]) select { (_x select 0) isEqualTo _lid }) param [0, []]) param [1, ""];
                if (["lineSet", [_id, _lid, "del"]] call _ZA) then { _ui set ["lid", ""]; [format [("%1 supprimé") call _tr, [_ln] call _esc], "undo"] call _bar; };
                call _refresh;
            };
            private _zid = _ui get "zid";
            if (_zid isNotEqualTo "") exitWith {
                private _z = ["zone", [_id, _zid]] call _ZA;
                if (["zoneSet", [_id, _zid, "del"]] call _ZA) then { _ui set ["zid", ""]; _ui set ["panel", "TAB"]; [format [("Zone %1 supprimée") call _tr, [_z param [1, ""]] call _esc], "undo"] call _bar; };
                call _refresh;
            };
            ["Rien de choisi : cliquez une zone ou un trait sur la carte", 2] call comspec_atak_native_fnc_toast;
        };
        case "undo";
        case "redo": {
            // Pendant un tracé, ANNULER retire le dernier point.
            if (_op isEqualTo "undo" && {(count (_ui get "draftPts")) > 0}) exitWith { ["#ui", ["polyBack"]] call comspec_atak_native_fnc_pageZones; };
            if !([_op] call _ZA) exitWith { [[("Rien à rétablir") call _tr, ("Rien à annuler") call _tr] select (_op isEqualTo "undo"), 2] call comspec_atak_native_fnc_toast; };
            private _b = ["get", _id] call _ZA;
            if ((count (["zone", [_id, _ui get "zid"]] call _ZA)) isEqualTo 0) then { _ui set ["zid", ""]; if ((_ui get "panel") isEqualTo "ZONE") then { _ui set ["panel", "TAB"]; }; };
            if (((_b getOrDefault ["lines", []]) findIf { (_x select 0) isEqualTo (_ui get "lid") }) < 0) then { _ui set ["lid", ""]; _ui set ["vtx", -1]; };
            _ui set ["undoBar", []];
            playSound "ClickSoft";
            call _refresh;
        };
        case "commit": {
            private _d = ["editCommit"] call _ZA;
            private _b = ["get", _id] call _ZA;
            ["SUCCESS", ["Plan validé", "Plan validé et envoyé"] select (_d && {(_b getOrDefault ["scope", 0]) > 0}), 2, 10] call comspec_atak_native_fnc_notify;
            ["editBegin", _id] call _ZA;
            call _refresh;
        };
        case "aroundMe": {
            private _r = parseNumber (["znRad", "300"] call _form);
            _r = (_r max 50) min 5000;
            ["gp", [_id, "radius", _r]] call _ZA;
            private _p = [] call comspec_atak_native_fnc_gpsPos;
            ["areaSet", [_id, "CIRCLE", [[_p select 0, _p select 1], _r]]] call _ZA;
            (_ui get "draft") deleteAt "znRad";
            ["INFO", format ["Zone de travail : %1 m autour de moi", _r], 3, 10] call comspec_atak_native_fnc_notify;
        };
        case "gp": { _v params ["_k", "_val"]; ["gp", [_id, _k, _val]] call _ZA; playSound "ClickSoft"; };
        case "gpField": { _v params ["_k", "_fk"]; ["gp", [_id, _k, [_fk, ""] call _form]] call _ZA; (_ui get "draft") deleteAt _fk; playSound "ClickSoft"; };
        case "gen": {
            private _b = ["get", _id] call _ZA;
            if ((count (_b getOrDefault ["zones", []])) > 0 && {(_ui get "confirm") isNotEqualTo "gen"}) exitWith { _ui set ["confirm", "gen"]; call _refresh; };
            _ui set ["confirm", ""]; _ui set ["zid", ""];
            if (!isNull ((uiNamespace getVariable ["COMSPEC_ATAK_Form", createHashMap]) getOrDefault ["znPre", controlNull])) then { ["gp", [_id, "prefix", ["znPre", ""] call _form]] call _ZA; (_ui get "draft") deleteAt "znPre"; };
            ["gen", [_id]] call _ZA;
        };
        case "genCancel": { ["genCancel", _id] call _ZA; };
        case "axes": { ["axes", [_id]] call _ZA; };
        // Thème de nommage (préférence du joueur et plan) : [thème, sous-thème MIL].
        case "nameTheme": { _v params ["_k", ["_sub", ""]]; ["nameTheme", [_id, _k, _sub]] call _ZA; playSound "ClickSoft"; };
        // Noms du thème appliqués : AXES (calcul des axes s'il n'y en a pas encore) | LINES | ZONES | ALL.
        case "nameApply": {
            if (_v isEqualTo "AXES" && {(count ((["get", _id] call _ZA) getOrDefault ["axes", []])) isEqualTo 0}) exitWith { ["axes", [_id]] call _ZA; };
            private _n = ["nameApply", [_id, _v]] call _ZA;
            if (_n isEqualTo 0) exitWith { [("Rien à nommer : tracez des traits, typez des zones ou calculez les axes") call _tr, 3] call comspec_atak_native_fnc_toast; };
            [format [("%1 élément(s) renommé(s)") call _tr, _n], "undo"] call _bar;
            playSound "ClickSoft";
        };
        // Abréviation suivante d'un élément : [genre (axis | line | zone | cp), id].
        case "abbr": { _v params ["_k", "_eid"]; ["abbrNext", [_id, _k, _eid]] call _ZA; playSound "ClickSoft"; };
        case "axisName": {
            private _fk = "znAx_" + _v;
            private _nv = [_fk, ""] call _form;
            if ((trim _nv) isNotEqualTo "") then { ["axisSet", [_id, _v, "name", _nv]] call _ZA; };
            (_ui get "draft") deleteAt _fk;
            playSound "ClickSoft";
        };
        case "axisRoute": {
            private _rid = ["axisRoute", [_id, _v]] call _ZA;
            if (_rid isNotEqualTo "") then { ["ROUTE"] call comspec_atak_native_fnc_navigate; ["#ui", ["open", _rid]] call comspec_atak_native_fnc_pageRoute; };
        };
        case "axisDel": { ["axisSet", [_id, _v, "del"]] call _ZA; [("Axe retiré") call _tr, "undo"] call _bar; };
        case "set": { _v params ["_k", "_val"]; ["set", [_id, _k, _val]] call _ZA; playSound "ClickSoft"; };
        case "style": { _v params ["_k", "_val"]; ["style", [_id, _k, _val]] call _ZA; playSound "ClickSoft"; };
        case "zstyle": {
            // Style de la zone choisie : [indice (0 couleur, 1 opacité, 2 épaisseur, 3 trait), valeur] ;
            // [-1, "TYPE"] : son style appliqué à toutes les zones de son type ; [-1, "RESET"] : style du plan.
            _v params ["_i", "_val"];
            private _z = ["zone", [_id, _ui get "zid"]] call _ZA;
            if ((count _z) isEqualTo 0) exitWith {};
            private _sty = +(_z param [12, ["", -1, -1, ""]]);
            if ((count _sty) < 4) then { _sty = ["", -1, -1, ""]; };
            switch (true) do {
                case (_i >= 0): { _sty set [_i, _val]; ["zoneStyle", [_id, [_z select 0], _sty]] call _ZA; };
                case (_val isEqualTo "TYPE"): {
                    private _n = ["zoneStyle", [_id, [], _sty, _z select 4]] call _ZA;
                    [format [("Style appliqué à %1 zone(s) de ce type") call _tr, _n], 2] call comspec_atak_native_fnc_toast;
                };
                default { ["zoneStyle", [_id, [_z select 0], ["", -1, -1, ""]]] call _ZA; };
            };
            playSound "ClickSoft";
        };
        case "toggle": {
            _v params ["_key", "_rowId"];
            private _b = ["get", _id] call _ZA;
            if ((count _b) isEqualTo 0) exitWith {};
            ["set", [_id, _key, !(_b get _key)]] call _ZA;
            [_rowId, (["get", _id] call _ZA) get _key] call comspec_atak_native_fnc_formSet;
            playSound "ClickSoft";
        };
        case "field": { _v params ["_k", "_fk"]; ["set", [_id, _k, [_fk, ""] call _form]] call _ZA; (_ui get "draft") deleteAt _fk; playSound "ClickSoft"; };
        case "autoName": { ["autoName", _id] call _ZA; (_ui get "draft") deleteAt "znName"; };
        case "share": {
            if (_v isEqualTo 0) exitWith { ["unshare", _id] call _ZA; ["INFO", "Partage retiré : plan privé", 2, 10] call comspec_atak_native_fnc_notify; };
            // Partager valide l'édition en cours (le plan part en entier), puis l'édition reprend.
            ["editCommit"] call _ZA;
            ["share", [_id, _v]] call _ZA;
            ["editBegin", _id] call _ZA;
        };
        case "reset": {
            if ((_ui get "confirm") isNotEqualTo "reset") exitWith { _ui set ["confirm", "reset"]; call _refresh; };
            _ui set ["confirm", ""];
            ["reset", _id] call _ZA;
        };
        case "del": {
            if ((_ui get "confirm") isNotEqualTo _id) exitWith { _ui set ["confirm", _id]; call _refresh; };
            private _nm = (["get", _id] call _ZA) getOrDefault ["name", ""];
            ["trash", _id] call _ZA;
            [format [("Plan %1 supprimé") call _tr, [_nm] call _esc], "untrash", _id] call _bar;
            _ui set ["id", ""]; _ui set ["view", "LIST"]; _ui set ["confirm", ""]; _full = true;
        };
        case "untrash": { if (["untrash", _v] call _ZA) then { ["INFO", "Plan rétabli", 2, 10] call comspec_atak_native_fnc_notify; }; _ui set ["undoBar", []]; _full = (_ui get "view") isNotEqualTo "EDIT"; };
        case "zone": { _ui set ["zid", _v]; _ui set ["panel", "ZONE"]; _ui set ["repPos", []]; _ui set ["confirm", ""]; call _refresh; };
        case "zoneSel": { _ui set ["zid", [_v, ""] select ((_ui get "zid") isEqualTo _v)]; call _refresh; };
        case "line": { _ui set ["lid", [_v, ""] select ((_ui get "lid") isEqualTo _v)]; _ui set ["vtx", -1]; call _refresh; };
        case "lineSet": {
            _v params ["_k", ["_val", ""]];
            private _lid = _ui get "lid";
            if (_k isEqualTo "del") exitWith {
                if ((_ui get "confirm") isNotEqualTo ("l" + _lid)) exitWith { _ui set ["confirm", "l" + _lid]; call _refresh; };
                _ui set ["confirm", ""];
                if (["lineSet", [_id, _lid, "del"]] call _ZA) then { _ui set ["lid", ""]; [("Trait supprimé") call _tr, "undo"] call _bar; };
            };
            if (_k isEqualTo "name") then { _val = ["znLName", ""] call _form; (_ui get "draft") deleteAt "znLName"; };
            if (_k isEqualTo "style") then {
                _val params ["_i", "_x2"];
                private _l = ((["get", _id] call _ZA) getOrDefault ["lines", []]) select { (_x select 0) isEqualTo _lid };
                private _sty = +((_l param [0, []]) param [4, ["", -1, ""]]);
                _sty set [_i, _x2];
                _val = _sty;
            };
            ["lineSet", [_id, _lid, _k, _val]] call _ZA;
            if (_k isEqualTo "asArea") then { ["INFO", "Le contour devient la zone de travail : DÉCOUPER, puis GÉNÉRER", 3, 10] call comspec_atak_native_fnc_notify; };
            playSound "ClickSoft";
        };
        case "state": {
            // État en un clic : appliqué tout de suite, envoyé dans 5 s (bandeau ANNULER entre-temps).
            _v params ["_zid", "_st"];
            if (["sitSoon", [_id, _zid, _st]] call _ZA) then {
                private _z = ["zone", [_id, _zid]] call _ZA;
                [format [("Zone %1 : %2 · part dans 5 s") call _tr, _z param [1, ""], ((["state", _st] call _ZA) select 1) call _tr], "sitUndo", [_id, _zid]] call _bar;
            };
            call _refresh;
        };
        case "sitUndo": { _v params ["_bid", "_zid"]; if (["sitUndo", [_bid, _zid]] call _ZA) then { ["État annulé : rien n'est parti", 2] call comspec_atak_native_fnc_toast; }; _ui set ["undoBar", []]; call _refresh; };
        case "report": {
            // Signalement : _v = état à donner ("ENY") ou "" (état inchangé).
            private _p = _ui get "repPos";
            private _z = ["zone", [_id, _ui get "zid"]] call _ZA;
            if ((count _z) isEqualTo 0) exitWith {};
            if ((count _p) < 2) then { _p = _z select 3; };
            _ui set ["repNat", ["znNat", "UNK"] call _form];
            ["sit", [_id, _ui get "zid", _v, [["znNat", "UNK"] call _form, ["znSize", ""] call _form, ["znTxt", ""] call _form, _p]]] call _ZA;
            { (_ui get "draft") deleteAt _x; } forEach ["znSize", "znTxt"];
            _ui set ["repPos", []];
            { private _c = (uiNamespace getVariable ["COMSPEC_ATAK_Form", createHashMap]) getOrDefault [_x, controlNull]; if (!isNull _c) then { _c ctrlSetText (("") call comspec_atak_native_fnc_tr); }; } forEach ["znSize", "znTxt"];
            [("Signalement enregistré") call _tr, 2] call comspec_atak_native_fnc_toast;
            call _refresh;
        };
        case "repClear": {
            { (_ui get "draft") deleteAt _x; private _c = (uiNamespace getVariable ["COMSPEC_ATAK_Form", createHashMap]) getOrDefault [_x, controlNull]; if (!isNull _c) then { _c ctrlSetText (("") call comspec_atak_native_fnc_tr); }; } forEach ["znSize", "znTxt"];
            _ui set ["repPos", []];
            call _refresh;
        };
        case "here": {
            private _p = [] call comspec_atak_native_fnc_gpsPos;
            private _hit = ["at", [_p]] call _ZA;
            if ((count _hit) isEqualTo 0) exitWith { ["INFO", "Aucune zone affichée sous ma position", 3, 10] call comspec_atak_native_fnc_notify; };
            ["#open", [(_hit select 0) get "id", (_hit select 1) select 0, [_p select 0, _p select 1]]] call comspec_atak_native_fnc_pageZones;
        };
        case "openZone": { _v params ["_bid", "_zid"]; ["#open", [_bid, _zid, []]] call comspec_atak_native_fnc_pageZones; };
        case "cite": {
            private _b = ["get", _id] call _ZA;
            private _z = ["zone", [_id, _ui get "zid"]] call _ZA;
            if ((count _z) isEqualTo 0) exitWith {};
            private _t = ["cite", [_b, _z]] call _ZA;
            ["clip", _t] call comspec_atak_native_fnc_planAction;
            uiNamespace setVariable ["COMSPEC_ATAK_ChatDraft", _t];
            [format ["Copié : %1", _t], 3] call comspec_atak_native_fnc_toast;
            ["CHAT"] call comspec_atak_native_fnc_navigate;
        };
        case "showMap": {
            private _z = ["zone", [_id, _ui get "zid"]] call _ZA;
            if ((count _z) isEqualTo 0) exitWith {};
            ["MAP"] call comspec_atak_native_fnc_navigate;
            (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) set ["mapFollow", false];
            [{ [[(_this select 0), (_this select 1), 0]] call comspec_atak_native_fnc_mapCenter; }, _z select 3, 0.3] call comspec_atak_native_fnc_waitAndExec;
        };
        case "zoneField": { _v params ["_k", "_fk"]; ["zoneSet", [_id, _ui get "zid", _k, [_fk, ""] call _form]] call _ZA; (_ui get "draft") deleteAt _fk; };
        case "zoneSet": { _v params ["_k", "_val"]; ["zoneSet", [_id, _ui get "zid", _k, _val]] call _ZA; playSound "ClickSoft"; };
        case "zoneDel": {
            if ((_ui get "confirm") isNotEqualTo "zdel") exitWith { _ui set ["confirm", "zdel"]; call _refresh; };
            _ui set ["confirm", ""];
            _ui set ["lid", ""];
            ["#ui", ["delKey"]] call comspec_atak_native_fnc_pageZones;
        };
        case "find": { _ui set ["find", toLower (trim (["znFind", ""] call _form))]; (_ui get "draft") set ["znFind", ["znFind", ""] call _form]; call _refresh; };
        case "sort": { _ui set ["sort", _v]; call _refresh; };
        case "ask": { _v params ["_k", "_yes"]; ["askAnswer", [_k, _yes]] call _ZA; _full = (_ui get "view") isNotEqualTo "EDIT"; };
        case "bld": { ["bld", [_id, _ui get "zid"]] call _ZA; ["INFO", "Numérotation des bâtiments en cours…", 2, 10] call comspec_atak_native_fnc_notify; };
    };
    if (_full) then { call _rerender; };
    true
};

private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["_bx", "_by", "_bw", "_bh"];
private _pad = (_l get "pad") * 2;
private _font = _l get "font";
private _fs = _l get "fontSmall";
private _inter = _l get "interactive";
private _view = _ui get "view";
if (_view isEqualTo "ZONE") then { _view = "EDIT"; _ui set ["view", "EDIT"]; _ui set ["panel", "ZONE"]; };
private _b = ["get", _ui get "id"] call _ZA;
if (_view isEqualTo "EDIT" && {(count _b) isEqualTo 0}) then { _view = "LIST"; _ui set ["view", "LIST"]; };
private _zone = if ((count _b) > 0) then { ["zone", [_b get "id", _ui get "zid"]] call _ZA } else { [] };
if ((count _zone) isEqualTo 0) then { _ui set ["zid", ""]; if ((_ui get "panel") isEqualTo "ZONE") then { _ui set ["panel", "TAB"]; }; };
if (!_inter) then { _view = "LIST"; _ui set ["tab", "SIT"]; };
private _mineB = (count _b) > 0 && {["isMine", _b get "id"] call _ZA};
if (!_mineB) then { _ui set ["tool", ""]; };
private _icon = "\z\comspec_atak_native\addons\main\data\app_zones.paa";
private _colors = ["colors"] call comspec_atak_native_fnc_routeAction;
private _stateSeg = {
    params ["_zid", "_cur"];
    (["states"] call _ZA) apply { [toUpper ((_x select 1) call _tr), ["state", [_zid, _x select 0]] call _ui1, (_x select 0) isEqualTo _cur, _x select 4] }
};

// Panneau de la vue EDIT (lignes de fn_formRender) : onglet choisi, ou fiche de la zone choisie.
private _panelRows = {
    private _rows = [];
    private _gp = _b getOrDefault ["gp", createHashMap];
    private _draft = _ui get "draft";
    private _zs = _b get "zones";
    private _busy = _b getOrDefault ["busy", ""];
    private _pal = ["pal", _b] call _ZA;
    // Barre d'édition : annuler, rétablir, valider (un seul envoi).
    if (_mineB) then {
        private _e = ["editInfo"] call _ZA;
        private _nU = count (_e getOrDefault ["undo", []]);
        _rows pushBack ["buttons", [
            [format ["↶ %1%2", ("ANNULER") call _tr, ["", format [" (%1)", _nU]] select (_nU > 0)], ["undo"] call _ui1, false, _nU > 0 || {(count (_ui get "draftPts")) > 0}, "Annule la dernière modification (Ctrl+Z) ; pendant un tracé : le dernier point"],
            ["↷ RÉTABLIR", ["redo"] call _ui1, false, (count (_e getOrDefault ["redo", []])) > 0, "Refait ce qui vient d'être annulé (Ctrl+Y)"],
            ["✓ VALIDER", ["commit"] call _ui1, true, _e getOrDefault ["dirty", false], "Enregistre ; un plan partagé part une seule fois, maintenant"]
        ]];
    };
    (_ui get "undoBar") params [["_ubT", ""], ["_ubOp", ""], ["_ubV", ""], ["_ubAt", -1]];
    if (_ubT isNotEqualTo "" && {(diag_tickTime - _ubAt) < 8}) then {
        _rows pushBack ["person", "", format ["<t color='" + (COMSPEC_ATAK_Th get "hexWarn") + "'>%1</t>", _ubT], [["ANNULER", [_ubOp, _ubV] call _ui1, true]], [0.95, 0.67, 0.2, 1], "!"];
    };
    if (!_mineB) then {
        _rows pushBack ["text", format ["<t color='" + (COMSPEC_ATAK_Th get "hexWarn") + "'>%1</t>", format [("Plan reçu de %1 (%2) : vous pouvez donner l'état des zones et signaler ; le découpage appartient à son auteur.") call _tr, [_b get "from"] call _esc, ["scopeName", _b get "scope"] call _ZA]], [0.10, 0.09, 0.05, 1]];
    };
    // Fiche d'une zone.
    if ((_ui get "panel") isEqualTo "ZONE" && {(count _zone) > 0}) exitWith {
        _zone params ["_zid", "_zn", "_zp", "_lp", "_zt", "_zrm", "_zst", "_zstamp", "_zby", "_zreps", "_zb"];
        private _sr = ["state", _zst] call _ZA;
        private _scol = (_pal get "state") getOrDefault [_zst, _sr select 4];
        _rows pushBack ["buttons", [["◀ RETOUR", ["panel", "TAB"] call _ui1], ["CITER", ["cite"] call _ui1, false, true, "Copie « B-04 (OBJ BRAVO) grille · état » et l'ouvre dans Messages"], ["APP CARTE", ["showMap"] call _ui1, false, true, "Montre la zone sur l'app Carte"]]];
        _rows pushBack ["person", "", format ["<t font='COMSPEC_BarlowBold' size='1.15'>%1</t>  <t color='" + (COMSPEC_ATAK_Th get "hexTextDim") + "'>%2</t><br/><t color='%3'>%4</t>%5",
            [_zn] call _esc, [_b get "name"] call _esc, [_scol] call _hex, (_sr select 1) call _tr, ["", format [" <t color='" + (COMSPEC_ATAK_Th get "hexTextDim") + "'>· %1 · %2</t>", [_zby] call _esc, [_zstamp] call _ago]] select (_zby isNotEqualTo "")], [], _scol, (_zn select [0, 3])];
        _rows pushBack ["info", "Grille", [[_lp select 0, _lp select 1, 0], 8] call comspec_atak_native_fnc_gridRef];
        _rows pushBack ["info", "Type", ((["type", _zt] call _ZA) select 1) call _tr];
        _rows pushBack ["info", "Surface", format ["%1 m²", round (abs (["area", [_zp]] call comspec_atak_native_fnc_zoneGeom))]];
        _rows pushBack ["info", "Depuis moi", format ["%1 · %2", [player distance2D [_lp select 0, _lp select 1, 0]] call _fmt, [player getDir [_lp select 0, _lp select 1, 0]] call _card]];
        _rows pushBack ["lineseg", "", "État de la zone", [_zid, _zst] call _stateSeg, "Un clic : l'état part dans 5 s (ANNULER entre-temps)"];
        private _rp = _ui get "repPos";
        _rows pushBack ["section", "Signaler", ["Position : l'étiquette de la zone", format [("Position : %1") call _tr, [[_rp select 0, _rp select 1, 0], 8] call comspec_atak_native_fnc_gridRef]] select ((count _rp) >= 2)];
        _rows pushBack ["combo", "znNat", "Nature", (["natures"] call _ZA) apply { [(_x select 1) call _tr, _x select 0] }, _ui getOrDefault ["repNat", "UNK"]];
        _rows pushBack ["edit", "znSize", "Effectif (ex. 6, groupe, 2 véhicules) · Entrée : signaler", _draft getOrDefault ["znSize", ""]];
        _rows pushBack ["memo", "znTxt", "Détails (activité, direction, armement)", _draft getOrDefault ["znTxt", ""], 2];
        _rows pushBack ["section", format [("Signalements (%1)") call _tr, count _zreps], ""];
        private _rl = _zreps apply { [_x select 1, _x] };
        _rl sort false;
        if ((count _rl) isEqualTo 0) then { _rows pushBack ["text", format ["<t color='" + (COMSPEC_ATAK_Th get "hexTextDim") + "'>%1</t>", ("Aucun signalement dans cette zone.") call _tr]]; };
        {
            (_x select 1) params ["", "_t", "_hh", "_who", "_nat", "_size", "_txt"];
            _rows pushBack ["text", format ["<t font='COMSPEC_BarlowBold' color='" + (COMSPEC_ATAK_Th get "hexWarn") + "'>%1</t>  %2%3%4<br/><t color='" + (COMSPEC_ATAK_Th get "hexTextDim") + "' size='0.9'>%5 · %6</t>",
                _hh, ["natureName", _nat] call _ZA, ["", " · " + ([_size] call _esc)] select (_size isNotEqualTo ""), ["", "<br/>" + ([_txt] call _esc)] select (_txt isNotEqualTo ""), [_who] call _esc, [_t] call _ago], [0.06, 0.075, 0.07, 1]];
        } forEach _rl;
        if (_mineB) then {
            _rows pushBack ["section", "Zone", ""];
            _rows pushBack ["field", "znZName", "Nom", _draft getOrDefault ["znZName", _zn], "RENOMMER", ["zoneField", ["label", "znZName"]] call _ui1, false];
            if ((_gp getOrDefault ["theme", "CITY"]) isEqualTo "MIL") then {
                _rows pushBack ["buttons", [[("ABRÉVIATION SUIVANTE ›") call _tr, ["abbr", ["zone", _ui get "zid"]] call _ui1, false, true, "AA, OBJ, NAI, TAI, EA, BP, LZ, PZ, DZ, ACA, ROZ, puis sans abréviation"]]];
            };
            _rows pushBack ["lineseg", "", "Type", (["types"] call _ZA) apply { [toUpper ((_x select 1) call _tr), ["zoneSet", ["type", _x select 0]] call _ui1, (_x select 0) isEqualTo _zt, (_pal get "type") getOrDefault [_x select 0, _x select 2]] }];
            _rows pushBack ["segment", "Rendu", [["COMME LE PLAN", ["zoneSet", ["rm", ""]] call _ui1, _zrm isEqualTo ""]] + ((["renders"] call _ZA) apply { [toUpper ((_x select 1) call _tr), ["zoneSet", ["rm", _x select 0]] call _ui1, (_x select 0) isEqualTo _zrm] })];
            private _cz = (_ui get "confirm") isEqualTo "zdel";
            _rows pushBack ["buttons", [
                [["NUMÉROTER LES BÂTIMENTS", "RENUMÉROTER"] select ((count _zb) > 0), ["bld"] call _ui1, false, _busy isEqualTo "", "Du nord au sud puis d'ouest en est, avec les étages (99 au plus)"],
                [["SUPPRIMER LA ZONE", "CONFIRMER"] select _cz, ["zoneDel"] call _ui1, ["red", false] select !_cz, true, "Deux clics, ou Suppr sur la carte ; ANNULER ensuite"]
            ]];
        };
        if ((count _zb) > 0) then {
            _rows pushBack ["section", format [("Bâtiments (%1)") call _tr, count _zb], ("Numéros affichés sur la carte au zoom rapproché") call _tr];
            private _lines = [];
            { _lines pushBack format ["<t font='COMSPEC_BarlowBold'>%1</t> %2", _forEachIndex + 1, format [("%1 ét.") call _tr, _x param [5, 1]]]; } forEach _zb;
            _rows pushBack ["text", _lines joinString "   "];
        };
        _rows
    };
    // Thème de nommage du plan (et du joueur) ; MIL-STD : sous-thème des noms qui suivent l'abréviation.
    private _themeRows = {
        private _th = _gp getOrDefault ["theme", "CITY"];
        private _all = ["themeRows"] call _ZA;
        _rows pushBack ["segment", "Thème des noms", _all apply { [toUpper ((_x select 1) call _tr), ["nameTheme", [_x select 0]] call _ui1, (_x select 0) isEqualTo _th, _x select 2] }];
        if (_th isEqualTo "MIL") then {
            private _sub = _gp getOrDefault ["milSub", "CITY"];
            _rows pushBack ["segment", "Noms après l'abréviation (MSR TAMPA, PL MARS)", (_all select { (_x select 0) isNotEqualTo "MIL" }) apply { [toUpper ((_x select 1) call _tr), ["nameTheme", ["MIL", _x select 0]] call _ui1, (_x select 0) isEqualTo _sub, _x select 2] }];
        };
        _th isEqualTo "MIL"
    };
    switch (_ui get "ztab") do {
        case "CUT": {
            if (!_mineB) exitWith { _rows pushBack ["text", format ["<t color='" + (COMSPEC_ATAK_Th get "hexTextDim") + "'>%1</t>", ("Le découpage appartient à l'auteur du plan.") call _tr]]; };
            private _cut = _gp getOrDefault ["cut", "GRID"];
            _rows pushBack ["section", "Découpage", [("Nommer et numéroter les zones") call _tr, format [("%1 zone(s)") call _tr, count _zs]] select ((count _zs) > 0)];
            _rows pushBack ["segment", "Méthode", [
                ["GRILLE", ["gp", ["cut", "GRID"]] call _ui1, _cut isEqualTo "GRID", "Grille de référence (GRG) : cases régulières A1, A2… ou numérotées"],
                ["ÎLOTS", ["gp", ["cut", "BLOCK"]] call _ui1, _cut isEqualTo "BLOCK", "Quartiers délimités par les routes de la carte"],
                ["SECTEURS", ["gp", ["cut", "RADIAL"]] call _ui1, _cut isEqualTo "RADIAL", "Secteurs autour du centre : N, NE… ou 1 à 16"],
                ["UNE ZONE", ["gp", ["cut", "SINGLE"]] call _ui1, _cut isEqualTo "SINGLE", "La zone de travail devient une seule zone"],
                ["PAR MES TRAITS", ["gp", ["cut", "LINES"]] call _ui1, _cut isEqualTo "LINES", "La zone de travail est divisée par vos traits (POLYGONE ouvert)"]
            ]];
            switch (_cut) do {
                case "GRID": {
                    private _sz = _gp getOrDefault ["size", 200];
                    _rows pushBack ["segment", "Taille des cases", [50, 100, 200, 250, 500, 1000] apply { [format ["%1 M", _x], ["gp", ["size", _x]] call _ui1, _x isEqualTo _sz] }];
                    private _f = _gp getOrDefault ["fmt", "GRG"];
                    _rows pushBack ["segment", "Noms", [
                        ["A1, B2… (GRG)", ["gp", ["fmt", "GRG"]] call _ui1, _f isEqualTo "GRG", "Colonnes en lettres d'ouest en est, rangées en chiffres du nord au sud"],
                        ["1…N EN SERPENTIN", ["gp", ["fmt", "NUM"]] call _ui1, _f isEqualTo "NUM", "Numéros du nord-ouest, une rangée sur deux en sens inverse"]
                    ]];
                    _rows pushBack ["segment", "Calage", [
                        ["SUR LE CARROYAGE", ["gp", ["snap", true]] call _ui1, _gp getOrDefault ["snap", true], "Cases alignées sur les lignes de la carte"],
                        ["SUR LA ZONE", ["gp", ["snap", false]] call _ui1, !(_gp getOrDefault ["snap", true]), "Première case au coin de la zone de travail"]
                    ]];
                };
                case "LINES": {
                    private _nl = count (_b getOrDefault ["lines", []]);
                    _rows pushBack ["text", format ["<t color='%1'>%2</t>", [(COMSPEC_ATAK_Th get "hexTextDim"), (COMSPEC_ATAK_Th get "hexWarn")] select (_nl isEqualTo 0), [format [("%1 trait(s) ou contour(s) découperont la zone de travail.") call _tr, _nl], ("Aucun trait : outil POLYGONE, OUVERT (TRAIT), tracez à travers la zone de travail.") call _tr] select (_nl isEqualTo 0)]];
                    private _ma = _gp getOrDefault ["minA", 2500];
                    _rows pushBack ["segment", "Surface minimale d'une zone", [1000, 2500, 5000, 10000] apply { [format ["%1 M²", _x], ["gp", ["minA", _x]] call _ui1, _x isEqualTo _ma] }];
                };
                case "BLOCK": {
                    private _ma = _gp getOrDefault ["minA", 2500];
                    _rows pushBack ["segment", "Surface minimale d'un îlot", [1000, 2500, 5000, 10000] apply { [format ["%1 M²", _x], ["gp", ["minA", _x]] call _ui1, _x isEqualTo _ma] }];
                    _rows pushBack ["segment", "Frontières", [
                        ["ROUTES", ["gp", ["tracks", false]] call _ui1, !(_gp getOrDefault ["tracks", false]), "Routes principales et secondaires"],
                        ["ROUTES ET PISTES", ["gp", ["tracks", true]] call _ui1, _gp getOrDefault ["tracks", false], "Les chemins de terre découpent aussi"]
                    ]];
                };
                case "RADIAL": {
                    private _k = _gp getOrDefault ["sec", 8];
                    _rows pushBack ["segment", "Secteurs", [4, 8, 16] apply { [str _x, ["gp", ["sec", _x]] call _ui1, _x isEqualTo _k] }];
                    private _rg = _gp getOrDefault ["rings", 1];
                    _rows pushBack ["segment", "Anneaux", [1, 2, 3] apply { [str _x, ["gp", ["rings", _x]] call _ui1, _x isEqualTo _rg] }];
                    private _sf = _gp getOrDefault ["secFmt", "CARD"];
                    _rows pushBack ["segment", "Noms", [["POINTS CARDINAUX", ["gp", ["secFmt", "CARD"]] call _ui1, _sf isEqualTo "CARD"], ["NUMÉROS", ["gp", ["secFmt", "NUM"]] call _ui1, _sf isEqualTo "NUM"]]];
                };
                default {};
            };
            _rows pushBack ["field", "znPre", "Préfixe des noms (B : B-01, B-C4 ; vide : aucun)", _draft getOrDefault ["znPre", _gp getOrDefault ["prefix", ""]], "OK", ["gpField", ["prefix", "znPre"]] call _ui1, false];
            // Aperçu (traits fins sur la carte, avant GÉNÉRER).
            if ((count (_b get "area")) isEqualTo 0) then {
                _rows pushBack ["text", format ["<t color='" + (COMSPEC_ATAK_Th get "hexWarn") + "'>%1</t>", ("Tracez d'abord la zone de travail (onglet TRACER ou barre d'outils de la carte).") call _tr]];
            } else {
                if (_cut in ["GRID", "RADIAL", "SINGLE"]) then {
                    private _np = count (["preview", _b] call _ZA);
                    _rows pushBack ["text", format ["<t color='%1'>%2</t>", ["#8fd19a", "#f2685c"] select (_np isEqualTo 0), [format [("Aperçu sur la carte : %1 zone(s) en pointillé fin") call _tr, _np], ("Aperçu : aucune case ne tient dans la zone de travail, prenez des cases plus petites") call _tr] select (_np isEqualTo 0)]];
                };
            };
            if (_busy isNotEqualTo "") then {
                _rows pushBack ["buttons", [["ANNULER LE CALCUL", ["genCancel"] call _ui1, "red", true, "Le calcul en cours est jeté : rien ne change"]]];
                _rows pushBack ["text", format ["<t color='" + (COMSPEC_ATAK_Th get "hexWarn") + "'>%1</t>", [("Recherche des axes…") call _tr, ("Découpage en cours…") call _tr] select (_busy isEqualTo "GEN")]];
            } else {
                private _confirm = (_ui get "confirm") isEqualTo "gen";
                _rows pushBack ["buttons", [[[["GÉNÉRER LES ZONES", "REMPLACER LES ZONES"] select ((count _zs) > 0), "CONFIRMER : LES ZONES ACTUELLES SONT REMPLACÉES"] select _confirm, ["gen"] call _ui1, [true, "red"] select _confirm, true, "Calcul en arrière-plan ; ANNULER ensuite revient à l'ancien découpage"]]];
            };
            private _ax = _b get "axes";
            _rows pushBack ["section", "Axes routiers (MSR / ASR)", [("Grands axes nommés et carrefours en points de contrôle") call _tr, format [("%1 axe(s), %2 point(s) de contrôle") call _tr, count _ax, count (_b get "cps")]] select ((count _ax) > 0)];
            call _themeRows;
            private _na = _gp getOrDefault ["asr", 3];
            _rows pushBack ["segment", "Itinéraires secondaires (ASR)", [0, 1, 2, 3, 4] apply { [str _x, ["gp", ["asr", _x]] call _ui1, _x isEqualTo _na] }];
            _rows pushBack ["buttons", [[["NOMMER LES AXES", "RENOMMER LES AXES"] select ((count _ax) > 0), ["axes"] call _ui1, false, _busy isEqualTo "", "Le plus grand axe devient MSR, les suivants ASR ; calcul en arrière-plan"]]];
            {
                _x params ["_aid", "_an", "_cls", "", "_len"];
                private _ncp = { (_x select 3) isEqualTo _an } count (_b get "cps");
                _rows pushBack ["person", "\a3\ui_f\data\igui\cfg\simpletasks\types\car_ca.paa",
                    format ["<t font='COMSPEC_BarlowBold'>%1</t>  <t color='" + (COMSPEC_ATAK_Th get "hexTextDim") + "'>%2 · %3</t>", [_an] call _esc, [_len] call _fmt, format [("%1 CP") call _tr, _ncp]],
                    [["ROUTE", ["axisRoute", _aid] call _ui1, true, true, "Crée cette route dans l'app Routes (véhicule, par les routes, CP en étapes)"], ["X", ["axisDel", _aid] call _ui1, false, true, "Retirer l'axe (ANNULER ensuite)"]],
                    [[0.35, 0.85, 0.40, 1], [0.30, 0.58, 1, 1]] select (_cls isEqualTo "MSR")];
            } forEach _ax;
        };
        case "NAME": {
            if (_mineB) then {
                _rows pushBack ["section", "Plan", ""];
                _rows pushBack ["field", "znName", "Nom", _draft getOrDefault ["znName", _b get "name"], "RENOMMER", ["field", ["name", "znName"]] call _ui1, false];
                private _sp = _gp getOrDefault ["setPre", "OBJ"];
                _rows pushBack ["segment", "Nom automatique", ["OBJ", "AO", "NAI", "SECT"] apply { [_x, ["gp", ["setPre", _x]] call _ui1, _x isEqualTo _sp] }];
                private _st = _gp getOrDefault ["setTheme", "NATO"];
                _rows pushBack ["segment", "", ((["themeRows"] call _ZA) select { (_x select 0) isNotEqualTo "MIL" }) apply { [toUpper ((_x select 1) call _tr), ["gp", ["setTheme", _x select 0]] call _ui1, (_x select 0) isEqualTo _st, _x select 2] }];
                _rows pushBack ["buttons", [["NOM AUTO", ["autoName"] call _ui1, false, true, "Prochain nom de code libre ; le préfixe des zones prend son initiale"]]];
                // Noms de code des éléments du plan : thème, application, axes (renommage et abréviation par axe).
                _rows pushBack ["section", "Noms de code des éléments", ("Jamais deux fois le même nom dans le plan ; renommage à la main toujours possible") call _tr];
                private _mil = call _themeRows;
                private _axN = _b get "axes";
                _rows pushBack ["buttons", [
                    [["CALCULER ET NOMMER LES AXES", "NOMMER LES AXES"] select ((count _axN) > 0), ["nameApply", "AXES"] call _ui1, true, _busy isEqualTo "", "MSR pour le plus grand axe, ASR pour les suivants"],
                    ["NOMMER LES TRAITS", ["nameApply", "LINES"] call _ui1, false, (count (_b getOrDefault ["lines", []])) > 0, ["Contours et traits tracés", "Traits : PL ; contours : AA (abréviation modifiable)"] select _mil],
                    ["NOMMER LES ZONES TYPÉES", ["nameApply", "ZONES"] call _ui1, false, true, ["Zones d'un type autre que Repère ; les cases de grille gardent leur nom", "OBJ, NAI, BP, ROZ, LZ… selon le type ; les cases de grille gardent leur nom"] select _mil],
                    ["TOUT NOMMER", ["nameApply", "ALL"] call _ui1, false, true, "Axes, traits, zones typées (et points de contrôle en MIL-STD)"]
                ]];
                {
                    _x params ["_aid", "_an", "_cls", "", "_len"];
                    private _btnA = [];
                    if (_mil) then { _btnA pushBack [("ABRÉV. ›") call _tr, ["abbr", ["axis", _aid]] call _ui1, false, true, "Abréviation suivante : MSR, ASR, ROUTE, PL, LD, LOA, FSCL, CFL"]; };
                    _rows pushBack ["person", "\a3\ui_f\data\igui\cfg\simpletasks\types\car_ca.paa", format ["<t font='COMSPEC_BarlowBold'>%1</t>  <t color='" + (COMSPEC_ATAK_Th get "hexTextDim") + "'>%2</t>", [_an] call _esc, [_len] call _fmt], _btnA,
                        [[0.35, 0.85, 0.40, 1], [0.30, 0.58, 1, 1]] select (_cls isEqualTo "MSR")];
                    _rows pushBack ["field", "znAx_" + _aid, "Renommer l'axe", _draft getOrDefault ["znAx_" + _aid, _an], "RENOMMER", ["axisName", _aid] call _ui1, false];
                } forEach _axN;
                // MIL-STD : points de contrôle numérotés, abréviation changée d'un clic (CP, PP, RP, ORP, SP).
                if (_mil && {(count (_b get "cps")) > 0}) then {
                    _rows pushBack ["segment", "Points de contrôle (clic : CP, PP, RP, ORP, SP)", ((_b get "cps") select [0, 16]) apply { [_x select 1, ["abbr", ["cp", str (_x select 0)]] call _ui1, false, _x select 3] }];
                };
            };
            // Zones : recherche, tri, pastille de l'état.
            private _fd = _ui get "find";
            _rows pushBack ["section", format [("Zones (%1)") call _tr, count _zs], ("Clic sur une zone : sa fiche") call _tr];
            _rows pushBack ["field", "znFind", "Chercher (nom)", _draft getOrDefault ["znFind", _fd], "CHERCHER", ["find"] call _ui1, false];
            private _so = _ui get "sort";
            _rows pushBack ["segment", "Trier par", [["NUMÉRO", ["sort", "NUM"] call _ui1, _so isEqualTo "NUM"], ["NOM", ["sort", "NAME"] call _ui1, _so isEqualTo "NAME"], ["ÉTAT", ["sort", "STATE"] call _ui1, _so isEqualTo "STATE", "Les plus urgentes d'abord"]]];
            private _order = ["ENY", "CONT", "DENY", "UNREC", "FRD", "CLEAR"];
            private _list = [];
            { if (_fd isEqualTo "" || {((toLower (_x select 1)) find _fd) >= 0}) then { _list pushBack [[_forEachIndex, toLower (_x select 1), _order find (_x select 6)] select (["NUM", "NAME", "STATE"] find _so), _forEachIndex, _x]; }; } forEach _zs;
            _list sort true;
            if ((count _list) isEqualTo 0) then { _rows pushBack ["text", format ["<t color='" + (COMSPEC_ATAK_Th get "hexTextDim") + "'>%1</t>", [("Aucune zone de ce nom.") call _tr, ("Aucune zone : onglet DÉCOUPER, GÉNÉRER.") call _tr] select ((count _zs) isEqualTo 0)]]; };
            {
                (_x select 2) params ["_zid", "_zn", "", "", "_zt", "", "_zst", "", "", "_zreps", "_zb"];
                private _sr = ["state", _zst] call _ZA;
                private _sc = (_pal get "state") getOrDefault [_zst, _sr select 4];
                private _extra = [((["type", _zt] call _ZA) select 1) call _tr];
                if ((count _zreps) > 0) then { _extra pushBack format [("%1 signalement(s)") call _tr, count _zreps]; };
                if ((count _zb) > 0) then { _extra pushBack format [("%1 bâtiment(s)") call _tr, count _zb]; };
                _rows pushBack ["person", "", format ["<t font='COMSPEC_BarlowBold' color='%1'>%2</t>  <t color='%3'>%4</t><br/><t color='" + (COMSPEC_ATAK_Th get "hexTextDim") + "'>%5</t>",
                    ["#e6ece8", (COMSPEC_ATAK_Th get "hexOk")] select (_zid isEqualTo (_ui get "zid")), [_zn] call _esc, [_sc] call _hex, (_sr select 1) call _tr, _extra joinString " · "],
                    [["FICHE", ["zone", _zid] call _ui1, false, true, "État, signalements, bâtiments"]], _sc, (_zn select [0, 3])];
            } forEach (_list select [0, 120]);
            if ((count _list) > 120) then { _rows pushBack ["text", format ["<t color='" + (COMSPEC_ATAK_Th get "hexTextDim") + "'>%1</t>", format [("… et %1 autres : cherchez-les par leur nom") call _tr, (count _list) - 120]]]; };
        };
        case "STYLE": {
            private _st = _b getOrDefault ["st", createHashMap];
            _rows pushBack ["section", "Thème et rendu", ("Aperçu immédiat sur la carte ; partagé avec le plan") call _tr];
            _rows pushBack ["segment", "Rendu", (["renders"] call _ZA) apply { [toUpper ((_x select 1) call _tr), ["set", ["render", _x select 0]] call _ui1, (_x select 0) isEqualTo (_b get "render")] }, "Surligné : rempli et bordé ; contour seul ; ou juste un point centré avec le nom"];
            if (!_mineB) exitWith {};
            _rows pushBack ["segment", "Thème", (["themes3"] call _ZA) apply { [toUpper ((_x select 1) call _tr), ["style", ["theme", _x select 0]] call _ui1, (_x select 0) isEqualTo (_pal get "theme")] }];
            private _fa = _pal get "fillA";
            _rows pushBack ["segment", "Opacité du remplissage", [0.05, 0.15, 0.25, 0.4, 0.6] apply { [format ["%1 %%", round (_x * 100)], ["style", ["fillA", _x]] call _ui1, (abs (_fa - _x)) < 0.01] }];
            private _w = _pal get "width";
            _rows pushBack ["segment", "Épaisseur du contour", [1, 2, 3, 4] apply { [format ["%1 PX", _x], ["style", ["width", _x]] call _ui1, _x isEqualTo _w] }];
            private _d = _pal get "dash";
            _rows pushBack ["segment", "Trait", [["PLEIN", ["style", ["dash", "SOLID"]] call _ui1, _d isEqualTo "SOLID"], ["TIRETS", ["style", ["dash", "DASH"]] call _ui1, _d isEqualTo "DASH"], ["POINTILLÉ", ["style", ["dash", "DOT"]] call _ui1, _d isEqualTo "DOT"], ["FLÈCHES", ["style", ["dash", "ARROW"]] call _ui1, _d isEqualTo "ARROW", "Flèche au bout des traits ouverts"]]];
            _rows pushBack ["lineseg", "", "Type des zones", (["types"] call _ZA) apply { [toUpper ((_x select 1) call _tr), ["set", ["aff", _x select 0]] call _ui1, (_x select 0) isEqualTo (_b get "aff"), (_pal get "type") getOrDefault [_x select 0, _x select 2]] }, "Toutes les zones prennent ce type"];
            _rows pushBack ["section", "Étiquettes", ""];
            private _ls = _pal get "lblSize";
            _rows pushBack ["segment", "Taille", [[0.8, "PETITE"], [1, "NORMALE"], [1.3, "GRANDE"]] apply { [_x select 1, ["style", ["lblSize", _x select 0]] call _ui1, (abs (_ls - (_x select 0))) < 0.01] }];
            private _lm = _pal get "lblMode";
            _rows pushBack ["segment", "Contenu", [["NOM ET ÉTAT", "NAME_ST"], ["NOM", "NAME"], ["NUMÉRO", "NUM"], ["AUCUNE", "NONE"]] apply { [_x select 0, ["style", ["lblMode", _x select 1]] call _ui1, (_x select 1) isEqualTo _lm] }];
            _rows pushBack ["segment", "Place", [["AU CENTRE", ["style", ["lblPos", "C"]] call _ui1, (_pal get "lblPos") isEqualTo "C"], ["AU-DESSUS", ["style", ["lblPos", "N"]] call _ui1, (_pal get "lblPos") isEqualTo "N"]]];
            _rows pushBack ["segment", "Fond", [["SANS FOND", ["style", ["lblBg", false]] call _ui1, !(_pal get "lblBg")], ["ENCADRÉE", ["style", ["lblBg", true]] call _ui1, _pal get "lblBg", "Étiquette sur un fond sombre, lisible sur toute carte"]]];
            // Couleurs par type et par état (palette de l'app Routes : même couleur sur la carte d'Arma et Athena).
            _rows pushBack ["section", "Couleurs par type de zone", ("THÈME : couleur du thème choisi") call _tr];
            private _tc = _st getOrDefault ["tcol", createHashMap];
            {
                private _k = _x select 0;
                _rows pushBack ["lineseg", "", (_x select 1) call _tr, [_tc getOrDefault [_k, ""]] call {
                    params ["_cur"];
                    [[("THÈME") call _tr, compile format ["['#ui', ['style', ['tcol', [%1, '']]]] call comspec_atak_native_fnc_pageZones;", str _k], _cur isEqualTo ""]] +
                        (_colors apply { [toUpper ((_x select 2) call _tr), compile format ["['#ui', ['style', ['tcol', [%1, %2]]]] call comspec_atak_native_fnc_pageZones;", str _k, str (_x select 0)], (_x select 0) isEqualTo _cur, _x select 1] })
                }];
            } forEach (["types"] call _ZA);
            _rows pushBack ["section", "Couleurs par état de situation", ""];
            private _sc = _st getOrDefault ["scol", createHashMap];
            {
                private _k = _x select 0;
                private _cur = _sc getOrDefault [_k, ""];
                _rows pushBack ["lineseg", "", (_x select 1) call _tr, [[("THÈME") call _tr, compile format ["['#ui', ['style', ['scol', [%1, '']]]] call comspec_atak_native_fnc_pageZones;", str _k], _cur isEqualTo ""]] +
                    (_colors apply { [toUpper ((_x select 2) call _tr), compile format ["['#ui', ['style', ['scol', [%1, %2]]]] call comspec_atak_native_fnc_pageZones;", str _k, str (_x select 0)], (_x select 0) isEqualTo _cur, _x select 1] })];
            } forEach (["states"] call _ZA);
            // Zone choisie : son propre style, puis « à toutes les zones de ce type ».
            if ((count _zone) > 0) then {
                private _zsty = _zone param [12, ["", -1, -1, ""]];
                _rows pushBack ["section", format [("Style de la zone %1") call _tr, [_zone select 1] call _esc], ("Choisie sur la carte") call _tr];
                _rows pushBack ["lineseg", "", "Couleur", [[("PLAN") call _tr, ["zstyle", [0, ""]] call _ui1, (_zsty param [0, ""]) isEqualTo ""]] + (_colors apply { [toUpper ((_x select 2) call _tr), ["zstyle", [0, _x select 0]] call _ui1, (_x select 0) isEqualTo (_zsty param [0, ""]), _x select 1] })];
                _rows pushBack ["segment", "Opacité", [[-1, "PLAN"], [0.05, "5 %"], [0.25, "25 %"], [0.5, "50 %"]] apply { [_x select 1, ["zstyle", [1, _x select 0]] call _ui1, (abs ((_zsty param [1, -1]) - (_x select 0))) < 0.01] }];
                _rows pushBack ["segment", "Épaisseur", [[-1, "PLAN"], [1, "1 PX"], [2, "2 PX"], [3, "3 PX"], [4, "4 PX"]] apply { [_x select 1, ["zstyle", [2, _x select 0]] call _ui1, (_zsty param [2, -1]) isEqualTo (_x select 0)] }];
                _rows pushBack ["segment", "Trait", [["", "PLAN"], ["SOLID", "PLEIN"], ["DASH", "TIRETS"], ["DOT", "POINTILLÉ"]] apply { [_x select 1, ["zstyle", [3, _x select 0]] call _ui1, (_zsty param [3, ""]) isEqualTo (_x select 0)] }];
                _rows pushBack ["buttons", [["À TOUTES LES ZONES DE CE TYPE", ["zstyle", [-1, "TYPE"]] call _ui1, false, true, "Le style de cette zone va à toutes les zones du même type"], ["STYLE DU PLAN", ["zstyle", [-1, "RESET"]] call _ui1, false, true, "Cette zone reprend le style du plan"]]];
            } else {
                _rows pushBack ["text", format ["<t color='" + (COMSPEC_ATAK_Th get "hexTextDim") + "'>%1</t>", ("Cliquez une zone sur la carte pour lui donner son propre style.") call _tr]];
            };
        };
        case "SIT": {
            if ((count _zone) > 0) then {
                _zone params ["_zid", "_zn", "", "", "", "", "_zst", "", "_zby", "_zreps"];
                _rows pushBack ["section", format [("Zone %1") call _tr, [_zn] call _esc], [format [("%1 signalement(s)") call _tr, count _zreps], format ["%1 · %2", ((["state", _zst] call _ZA) select 1) call _tr, _zby]] select (_zby isNotEqualTo "")];
                _rows pushBack ["lineseg", "", "État", [_zid, _zst] call _stateSeg, "Un clic : l'état part dans 5 s (ANNULER entre-temps)"];
                _rows pushBack ["buttons", [["FICHE / SIGNALER", ["zone", _zid] call _ui1, true], ["PLUS DE ZONE CHOISIE", ["zoneSel", _zid] call _ui1]]];
            } else {
                _rows pushBack ["text", format ["<t color='" + (COMSPEC_ATAK_Th get "hexTextDim") + "'>%1</t>", ("Cliquez une zone sur la carte : son état en un clic.") call _tr]];
            };
            private _cnt = createHashMap;
            { _cnt set [_x select 6, (_cnt getOrDefault [_x select 6, 0]) + 1]; } forEach _zs;
            _rows pushBack ["cells", (["states"] call _ZA) apply { ["\A3\ui_f\data\map\markers\military\dot_CA.paa", (_pal get "state") getOrDefault [_x select 0, _x select 4], (_x select 1) call _tr, str (_cnt getOrDefault [_x select 0, 0]), ""] }, 3];
            _rows pushBack ["switch", "Afficher ce plan", _b get "vis", ["toggle", ["vis", "znVis"]] call _ui1, "Sur la carte ATAK et dans le bandeau du curseur", false, "znVis"];
            if (_mineB) then {
                private _sc = _b get "scope";
                _rows pushBack ["section", "Partage", [("Privé : seulement ce téléphone") call _tr, format [("Partagé : %1, renvoyé à chaque validation") call _tr, ["scopeName", _sc] call _ZA]] select (_sc > 0)];
                _rows pushBack ["segment", "Envoyer à", [
                    ["CAMP", ["share", 3] call _ui1, _sc isEqualTo 3, "Tout mon camp (par défaut)"],
                    ["GROUPE", ["share", 2] call _ui1, _sc isEqualTo 2, "Tout mon groupe"],
                    ["ÉQUIPE", ["share", 1] call _ui1, _sc isEqualTo 1, "Mon équipe de feu (le groupe si je n'en ai pas)"],
                    ["PRIVÉ", ["share", 0] call _ui1, _sc isEqualTo 0, "Personne d'autre"]
                ], "Par le réseau ATAK : sans réseau, l'envoi part au retour du signal ; les états et signalements suivent"];
                _rows pushBack ["switch", "Carte d'Arma et Athena", _b get "arma", ["toggle", ["arma", "znArma"]] call _ui1, "Cases, contours, traits, étiquettes, axes et CP en marqueurs de la carte d'Arma (le camp pour un plan au camp), visibles sans appareil ; au site Athena si le plan est partagé", !(missionNamespace getVariable ["comspec_atak_native_zones_arma", true]), "znArma"];
                private _cr = (_ui get "confirm") isEqualTo "reset";
                private _cd = (_ui get "confirm") isEqualTo (_b get "id");
                _rows pushBack ["section", "Plan", ""];
                _rows pushBack ["buttons", [
                    [["RÉINITIALISER LA SITUATION", "CONFIRMER"] select _cr, ["reset"] call _ui1, ["red", false] select !_cr, true, "Toutes les zones repassent « non reconnue », signalements effacés (partout)"],
                    [["SUPPRIMER LE PLAN", "CONFIRMER"] select _cd, ["del"] call _ui1, ["red", false] select !_cd, true, "Deux clics ; ANNULER pendant 8 s"]
                ]];
            };
        };
        default {
            // TRACER : zone de travail, polygone (aimanté, fermé / ouvert), contours et traits.
            if (!_mineB) exitWith { _rows pushBack ["text", format ["<t color='" + (COMSPEC_ATAK_Th get "hexTextDim") + "'>%1</t>", ("Le tracé appartient à l'auteur du plan.") call _tr]]; };
            (["areaPoly", _b] call _ZA) params ["_ap", "_ak", "_ac", "_ar"];
            private _aTxt = switch (_ak) do {
                case "RECT": { (["bbox", [_ap]] call comspec_atak_native_fnc_zoneGeom) params ["_x0", "_y0", "_x1", "_y1"]; format [("rectangle %1 x %2") call _tr, [_x1 - _x0] call _fmt, [_y1 - _y0] call _fmt] };
                case "CIRCLE": { format [("cercle de %1 de rayon") call _tr, [_ar] call _fmt] };
                case "POLY": { format [("polygone de %1 sommets") call _tr, count _ap] };
                default { ("à tracer") call _tr };
            };
            private _tool = _ui get "tool";
            _rows pushBack ["section", "Zone de travail", _aTxt];
            _rows pushBack ["segment", "Tracer sur la carte", [
                ["RECTANGLE", ["tool", "RECT"] call _ui1, _tool isEqualTo "RECT", "Glisser d'un coin à l'autre, ou deux clics : deux coins opposés"],
                ["POLYGONE", ["tool", "POLY"] call _ui1, _tool isEqualTo "POLY", "Un clic par sommet ; aimanté aux routes, bâtiments et rives ; fermé ou ouvert"],
                ["CERCLE", ["tool", "CIRCLE"] call _ui1, _tool isEqualTo "CIRCLE", "Glisser, ou deux clics : centre (objectif) puis bord"]
            ]];
            _rows pushBack ["field", "znRad", "Autour de moi : rayon (m)", _draft getOrDefault ["znRad", str (_gp getOrDefault ["radius", 300])], "POSER", ["aroundMe"] call _ui1, false];
            _rows pushBack ["section", "Polygone", ("Mêmes options sur la barre de la carte") call _tr];
            _rows pushBack ["segment", "Aimant", [["AIMANTÉ", ["poly", ["polyMag", true]] call _ui1, _ui get "polyMag", "Accroche aux routes, bâtiments et rives ; Alt maintenu : sans aimant"], ["LIBRE", ["poly", ["polyMag", false]] call _ui1, !(_ui get "polyMag")]]];
            _rows pushBack ["segment", "Rayon de l'aimant", [15, 30, 60] apply { [format ["%1 M", _x], ["poly", ["polyR", _x]] call _ui1, _x isEqualTo (_ui get "polyR")] }];
            _rows pushBack ["segment", "Forme", [["FERMÉ (CONTOUR)", ["poly", ["polyOpen", false]] call _ui1, !(_ui get "polyOpen")], ["OUVERT (TRAIT)", ["poly", ["polyOpen", true]] call _ui1, _ui get "polyOpen", "Un trait : sert à « découper par mes traits »"]]];
            _rows pushBack ["segment", "Le contour fermé devient", [["LA ZONE DE TRAVAIL", ["poly", ["polyFor", "AREA"]] call _ui1, (_ui get "polyFor") isEqualTo "AREA"], ["UN DESSIN DU PLAN", ["poly", ["polyFor", "LINE"]] call _ui1, (_ui get "polyFor") isEqualTo "LINE"]]];
            // Contours et traits.
            private _ls = _b getOrDefault ["lines", []];
            _rows pushBack ["section", format [("Contours et traits (%1)") call _tr, count _ls], ("Clic sur un trait de la carte : le choisir") call _tr];
            if ((count _ls) isEqualTo 0) then { _rows pushBack ["text", format ["<t color='" + (COMSPEC_ATAK_Th get "hexTextDim") + "'>%1</t>", ("Aucun : outil POLYGONE, puis UN DESSIN DU PLAN ou OUVERT (TRAIT).") call _tr]]; };
            {
                _x params ["_lid", "_ln", "_lp", "_closed", ["_lsty", []]];
                private _isL = _lid isEqualTo (_ui get "lid");
                private _lc = if ((_lsty param [0, ""]) isNotEqualTo "") then { ["rgba", _lsty select 0] call comspec_atak_native_fnc_routeAction } else { (_pal get "type") getOrDefault [_b get "aff", [1, 1, 1, 1]] };
                _rows pushBack ["person", "", format ["<t font='COMSPEC_BarlowBold' color='%1'>%2</t>  <t color='" + (COMSPEC_ATAK_Th get "hexTextDim") + "'>%3 · %4</t>", ["#e6ece8", (COMSPEC_ATAK_Th get "hexOk")] select _isL, [_ln] call _esc, [("trait ouvert") call _tr, ("contour fermé") call _tr] select _closed, format [("%1 sommet(s)") call _tr, count _lp]],
                    [[["CHOISIR", "CHOISI"] select _isL, ["line", _lid] call _ui1, _isL, true, "Montre ses sommets : clic sur un sommet puis sur la carte pour le déplacer, Suppr pour le retirer"]], _lc, ["/", "#"] select _closed];
                if (_isL) then {
                    _rows pushBack ["field", "znLName", "Nom", _draft getOrDefault ["znLName", _ln], "RENOMMER", ["lineSet", ["name"]] call _ui1, false];
                    if ((_gp getOrDefault ["theme", "CITY"]) isEqualTo "MIL") then {
                        _rows pushBack ["buttons", [[("ABRÉVIATION SUIVANTE ›") call _tr, ["abbr", ["line", _lid]] call _ui1, false, true, ["PL, LD, LOA, ROUTE, FSCL, CFL, MSR, ASR, puis sans abréviation", "AA, OBJ, NAI, TAI, EA, BP, LZ, PZ, DZ, ACA, ROZ, puis sans abréviation"] select _closed]]];
                    };
                    _rows pushBack ["lineseg", "", "Couleur", [[("PLAN") call _tr, ["lineSet", ["style", [0, ""]]] call _ui1, (_lsty param [0, ""]) isEqualTo ""]] + (_colors apply { [toUpper ((_x select 2) call _tr), ["lineSet", ["style", [0, _x select 0]]] call _ui1, (_x select 0) isEqualTo (_lsty param [0, ""]), _x select 1] })];
                    _rows pushBack ["segment", "Épaisseur", [[-1, "PLAN"], [2, "2 PX"], [3, "3 PX"], [5, "5 PX"]] apply { [_x select 1, ["lineSet", ["style", [1, _x select 0]]] call _ui1, (_lsty param [1, -1]) isEqualTo (_x select 0)] }];
                    _rows pushBack ["segment", "Trait", [["", "PLAN"], ["SOLID", "PLEIN"], ["DASH", "TIRETS"], ["DOT", "POINTILLÉ"], ["ARROW", "FLÈCHE"]] apply { [_x select 1, ["lineSet", ["style", [2, _x select 0]]] call _ui1, (_lsty param [2, ""]) isEqualTo (_x select 0)] }];
                    private _cl = (_ui get "confirm") isEqualTo ("l" + _lid);
                    _rows pushBack ["buttons", [
                        [["FERMER", "OUVRIR"] select _closed, ["lineSet", ["closed", !_closed]] call _ui1, false, _closed || {(count _lp) >= 3}, "Contour fermé ou trait ouvert"],
                        ["EN ZONE DE TRAVAIL", ["lineSet", ["asArea", true]] call _ui1, false, _closed, "Ce contour devient la zone de travail du plan"],
                        [["SUPPRIMER", "CONFIRMER"] select _cl, ["lineSet", ["del"]] call _ui1, ["red", false] select !_cl, true, "Deux clics, ou Suppr ; ANNULER ensuite"]
                    ]];
                };
            } forEach _ls;
        };
    };
    _rows
};

// Panneau (onglets, fiche, barre d'actions de la fiche) et barres de la carte (outils, options, consigne), refaits sans
// rendu de page : anciens contrôles supprimés tout de suite (jamais deux fois à l'écran), défilement gardé par onglet.
private _panel = {
    private _old = uiNamespace getVariable ["COMSPEC_ATAK_ZnPanel", []];
    private _grp = uiNamespace getVariable ["COMSPEC_ATAK_ZnForm", controlNull];
    private _key = uiNamespace getVariable ["COMSPEC_ATAK_ZnFormKey", ""];
    if (!isNull _grp && {_key isNotEqualTo ""}) then { (_ui get "scroll") set [_key, (ctrlScrollValues _grp) select 0]; };
    // Champs saisis gardés en brouillon.
    private _draft = _ui get "draft";
    { private _c = (uiNamespace getVariable ["COMSPEC_ATAK_Form", createHashMap]) getOrDefault [_x, controlNull]; if (!isNull _c) then { _draft set [_x, ctrlText _c]; }; } forEach ["znName", "znPre", "znRad", "znZName", "znSize", "znTxt", "znLName", "znFind"];
    if ((_draft getOrDefault ["znName", ""]) isEqualTo (_b get "name")) then { _draft deleteAt "znName"; };
    { if (!isNull _x) then { ctrlDelete _x; }; } forEach _old;
    if (!isNull _grp) then { private _bg = _grp getVariable ["bg", controlNull]; ctrlDelete _grp; if (!isNull _bg) then { ctrlDelete _bg; }; };
    private _list = [];
    private _rect = uiNamespace getVariable ["COMSPEC_ATAK_ZnFormRect", []];
    private _mr = uiNamespace getVariable ["COMSPEC_ATAK_ZnMapRect", []];
    if ((count _rect) < 4 || {(count _mr) < 4}) exitWith {};
    _rect params ["_rx", "_ry", "_rw", "_rh"];
    private _mkBtn = {
        params ["_r", "_label", "_code", ["_on", false], ["_tip", ""], ["_inG", true], ["_red", false]];
        private _bt = ["COMSPEC_RscButton", _r, _label, _inG] call comspec_atak_native_fnc_pageCtrl;
        _bt ctrlSetFontHeight ((_fs * 0.9) max COMSPEC_ATAK_FontMin);
        if ((ctrlTextWidth _bt) > (_r select 2) * 0.92) then { _bt ctrlSetFontHeight (((_fs * 0.9) * (_r select 2) * 0.92 / (ctrlTextWidth _bt)) max COMSPEC_ATAK_FontMin); };
        _bt ctrlSetBackgroundColor ([[0.12, 0.145, 0.13, 0.96], [0.36, 0.78, 0.42, 0.95]] select _on);
        _bt ctrlSetTextColor ([[0.92, 0.96, 0.93, 1], [0.03, 0.05, 0.04, 1]] select _on);
        if (_red) then { _bt ctrlSetBackgroundColor [0.78, 0.2, 0.16, 0.95]; _bt ctrlSetTextColor [1, 1, 1, 1]; };
        if (_tip isNotEqualTo "") then { _bt ctrlSetTooltip ((((_tip) call _tr)) call comspec_atak_native_fnc_tr); };
        _bt setVariable ["comspec_click", _code];
        _bt ctrlAddEventHandler ["ButtonClick", { params ["_c"]; [{ call (_this getVariable ["comspec_click", {}]); }, _c] call comspec_atak_native_fnc_nextFrame; }];
        _list pushBack _bt;
        _bt
    };
    // Onglets.
    private _tabs = [["DRAW", "TRACER"], ["CUT", "DÉCOUPER"], ["NAME", "NOMMER"], ["STYLE", "STYLE"], ["SIT", "SITUATION"]];
    private _th = _font * 1.5;
    private _tw = _rw / (count _tabs);
    private _zt = _ui get "ztab";
    private _pz = (_ui get "panel") isEqualTo "ZONE" && {(count _zone) > 0};
    { [[_rx + _forEachIndex * _tw, _ry, _tw - pixelW * 2, _th], _x select 1, ["ztab", _x select 0] call _ui1, !_pz && {(_x select 0) isEqualTo _zt}] call _mkBtn; } forEach _tabs;
    // Fiche d'une zone : barre d'actions fixe en bas (SIGNALER toujours visible).
    private _abH = [0, _font * 2.1] select _pz;
    if (_pz) then {
        private _ay = _ry + _rh - _abH;
        private _bgA = ["COMSPEC_RscText", [_rx, _ay, _rw, _abH]] call comspec_atak_native_fnc_pageCtrl;
        _bgA ctrlSetBackgroundColor (COMSPEC_ATAK_Th get "surface");
        _list pushBack _bgA;
        private _w3 = (_rw - _pad * 2) / 3;
        [[_rx + _pad * 0.5, _ay + _abH * 0.15, _w3 * 1.25, _abH * 0.7], "SIGNALER UN ENNEMI", ["report", "ENY"] call _ui1, false, "La zone passe « ennemi signalé » avec ce signalement (part tout de suite)", true, true] call _mkBtn;
        [[_rx + _pad + _w3 * 1.25, _ay + _abH * 0.15, _w3 * 1.15, _abH * 0.7], "SIGNALER", ["report", ""] call _ui1, true, "Signalement noté, état de la zone inchangé (Entrée dans Effectif)"] call _mkBtn;
        [[_rx + _pad * 1.5 + _w3 * 2.4, _ay + _abH * 0.15, _w3 * 0.55, _abH * 0.7], "EFFACER", ["repClear"] call _ui1, false, "Vide le formulaire"] call _mkBtn;
    };
    private _fr = [_rx, _ry + _th + pixelH * 2, _rw, _rh - _th - pixelH * 2 - _abH];
    private _rows = call _panelRows;
    uiNamespace setVariable ["COMSPEC_ATAK_ZnDrawnAt", diag_frameNo];
    private _ng = [_rows, _fr] call comspec_atak_native_fnc_formRender;
    uiNamespace setVariable ["COMSPEC_ATAK_ZnForm", _ng];
    private _nk = [_zt, "ZONE|" + (_ui get "zid")] select _pz;
    uiNamespace setVariable ["COMSPEC_ATAK_ZnFormKey", _nk];
    private _sv = (_ui get "scroll") getOrDefault [_nk, 0];
    if (_sv > 0) then {
        _ng ctrlSetScrollValues [_sv, -1];
        [{ params ["_g", "_v"]; if (!isNull _g) then { _g ctrlSetScrollValues [_v, -1]; }; }, [_ng, _sv]] call comspec_atak_native_fnc_nextFrame;
    };
    // Barre d'outils de la carte (absolue, devant la carte) : sélection, rectangle, polygone, cercle, annuler, rétablir.
    _mr params ["_mx", "_my", "_mw", "_mh"];
    private _ratio = uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW];
    private _ih = (_font * 1.9) min (_mh / 8);
    private _iw = _ih / _ratio;
    private _tool = _ui get "tool";
    if (_mineB) then {
        {
            _x params ["_k", "_ic", "_tip"];
            private _y = _my + _pad * 0.5 + _forEachIndex * (_ih + pixelH * 3) + ([0, pixelH * 6] select (_forEachIndex >= 4));
            private _on = _k isEqualTo _tool;
            private _bgI = ["COMSPEC_RscText", [_mx + _pad * 0.5, _y, _iw, _ih], "", false] call comspec_atak_native_fnc_pageCtrl;
            _bgI ctrlSetBackgroundColor ([[0.03, 0.04, 0.035, 0.85], [0.36, 0.78, 0.42, 0.95]] select _on);
            _list pushBack _bgI;
            private _pic = ["COMSPEC_RscIcon", [_mx + _pad * 0.5 + _iw * 0.18, _y + _ih * 0.18, _iw * 0.64, _ih * 0.64], _ic, false] call comspec_atak_native_fnc_pageCtrl;
            _pic ctrlSetTextColor ([[0.92, 0.96, 0.93, 1], [0.03, 0.05, 0.04, 1]] select _on);
            _list pushBack _pic;
            private _ov = ["COMSPEC_RscButtonOverlay", [_mx + _pad * 0.5, _y, _iw, _ih], "", false] call comspec_atak_native_fnc_pageCtrl;
            _ov ctrlSetTooltip ((((_tip) call _tr)) call comspec_atak_native_fnc_tr);
            _ov setVariable ["comspec_click", switch (_k) do { case "#UNDO": { ["undo"] call _ui1 }; case "#REDO": { ["redo"] call _ui1 }; default { ["tool", _k] call _ui1 }; }];
            _ov ctrlAddEventHandler ["ButtonClick", { params ["_c"]; [{ call (_this getVariable ["comspec_click", {}]); }, _c] call comspec_atak_native_fnc_nextFrame; }];
            _list pushBack _ov;
        } forEach [
            ["", "\z\comspec_atak_native\addons\main\data\ui_hand.paa", "Sélection : clic sur une zone ou un trait ; Suppr le supprime"],
            ["RECT", "\A3\ui_f\data\map\markers\military\box_CA.paa", "Rectangle : glisser ou deux clics, coins opposés (zone de travail)"],
            ["POLY", "\A3\ui_f\data\map\markers\military\triangle_CA.paa", "Polygone : aimanté, fermé (contour) ou ouvert (trait)"],
            ["CIRCLE", "\A3\ui_f\data\map\markers\military\circle_CA.paa", "Cercle : glisser ou deux clics, centre puis bord (zone de travail)"],
            ["#UNDO", "\z\comspec_atak_native\addons\main\data\ui_prev.paa", "Annuler (Ctrl+Z)"],
            ["#REDO", "\z\comspec_atak_native\addons\main\data\ui_next.paa", "Rétablir (Ctrl+Y)"]
        ];
    };
    // Options du polygone (bandeau en haut de la carte).
    private _x0 = _mx + _pad + _iw;
    private _ow = _mw - _pad * 1.5 - _iw;
    if (_tool isEqualTo "POLY") then {
        private _opts = [
            [["AIMANT", "LIBRE"] select !(_ui get "polyMag"), ["poly", ["polyMag", !(_ui get "polyMag")]] call _ui1, _ui get "polyMag", "Aimanté aux routes, bâtiments et rives (Alt maintenu : sans aimant)"]
        ] + ([15, 30, 60] apply { [format ["%1 M", _x], ["poly", ["polyR", _x]] call _ui1, _x isEqualTo (_ui get "polyR"), "Rayon de l'aimant"] }) + [
            ["FERMÉ", ["poly", ["polyOpen", false]] call _ui1, !(_ui get "polyOpen"), "Contour fermé"],
            ["OUVERT", ["poly", ["polyOpen", true]] call _ui1, _ui get "polyOpen", "Trait ouvert (découper par mes traits)"],
            ["ZONE DE TRAVAIL", ["poly", ["polyFor", "AREA"]] call _ui1, (_ui get "polyFor") isEqualTo "AREA", "Le contour fermé devient la zone de travail"],
            ["DESSIN", ["poly", ["polyFor", "LINE"]] call _ui1, (_ui get "polyFor") isEqualTo "LINE", "Le tracé devient un contour ou un trait du plan"]
        ];
        private _n = count _opts;
        private _per = [_n, 4] select (_ow < _font * 22);
        private _cw = _ow / _per;
        private _oh = _font * 1.35;
        { [[_x0 + (_forEachIndex mod _per) * _cw, _my + _pad * 0.5 + (floor (_forEachIndex / _per)) * (_oh + pixelH * 3), _cw - pixelW * 3, _oh], _x select 0, _x select 1, _x select 2, _x select 3, false] call _mkBtn; } forEach _opts;
    };
    // Consigne (aide concise) en bas de la carte, avec TERMINER / ANNULER pendant un tracé.
    private _np = count (_ui get "draftPts");
    private _next = _mineB && {_tool isEqualTo ""} && {(_ui getOrDefault ["next", ""]) isEqualTo "AREA"} && {(count (_b getOrDefault ["area", []])) > 0};
    private _help = switch (true) do {
        case _next: { ("Zone de travail posée : DÉCOUPER en zones, ou TRACER dessus (contours, traits)") call _tr };
        case (_tool isEqualTo "RECT"): { [("Rectangle : glissez d'un coin à l'autre, ou cliquez deux coins opposés") call _tr, ("Rectangle : cliquez le coin opposé") call _tr] select (_np > 0) };
        case (_tool isEqualTo "CIRCLE"): { [("Cercle : glissez du centre au bord, ou cliquez le centre puis le bord") call _tr, ("Cercle : cliquez le bord") call _tr] select (_np > 0) };
        case (_tool isEqualTo "POLY"): { format [("Polygone %1 · %2 point(s) · clic : sommet · Retour arrière : retirer · Entrée ou clic droit : terminer · Échap : annuler") call _tr, [("libre") call _tr, ("aimanté") call _tr] select (_ui get "polyMag"), _np] };
        case ((_ui get "lid") isNotEqualTo "" && {(_ui get "vtx") >= 0}): { ("Sommet choisi : cliquez sa nouvelle place · Suppr : le retirer · Échap : laisser") call _tr };
        case ((_ui get "lid") isNotEqualTo ""): { ("Trait choisi : clic sur un sommet pour le déplacer, sur un rond pour en ajouter un · Suppr : supprimer le trait") call _tr };
        case ((_ui get "zid") isNotEqualTo ""): { ("Zone choisie : état dans le panneau · Suppr : supprimer la zone · Échap : désélectionner") call _tr };
        case (!_mineB): { ("Clic sur une zone : son état et sa fiche · clic droit : menu") call _tr };
        default { ("Clic : choisir une zone ou un trait · clic droit : menu Zones · outils à gauche") call _tr };
    };
    private _hh = _font * 1.5;
    private _hy = _my + _mh - _hh - _pad * 0.4;
    private _nb = [0, 2] select (_tool isNotEqualTo "" || _next);
    private _bwB = (_ow * 0.18) max (_font * 4);
    private _bgH = ["COMSPEC_RscText", [_x0, _hy, _ow, _hh], "", false] call comspec_atak_native_fnc_pageCtrl;
    _bgH ctrlSetBackgroundColor (((COMSPEC_ATAK_Th get "bg0") select [0, 3]) + [0.82]);
    _list pushBack _bgH;
    private _ht = ["COMSPEC_RscStructuredText", [_x0 + _pad * 0.3, _hy + _hh * 0.12, _ow - _nb * (_bwB + _pad * 0.3) - _pad * 0.4, _hh * 0.9], "", false] call comspec_atak_native_fnc_pageCtrl;
    _ht ctrlSetStructuredText parseText format ["<t size='0.82'>%1</t>", _help]; // i18n:brut
    _list pushBack _ht;
    if (_next) then {
        [[_x0 + _ow - _bwB - _pad * 0.2, _hy + _hh * 0.1, _bwB, _hh * 0.8], "TRACER", ["afterArea", "DRAW"] call _ui1, false, "Polygone : contours et traits du plan sur la zone de travail", false] call _mkBtn;
        [[_x0 + _ow - _bwB * 2 - _pad * 0.5, _hy + _hh * 0.1, _bwB, _hh * 0.8], "DÉCOUPER", ["afterArea", "CUT"] call _ui1, true, "Onglet DÉCOUPER : méthode, aperçu sur la carte, GÉNÉRER", false] call _mkBtn;
    };
    if (_nb > 0 && {!_next}) then {
        [[_x0 + _ow - _bwB - _pad * 0.2, _hy + _hh * 0.1, _bwB, _hh * 0.8], "ANNULER", ["drawCancel"] call _ui1, false, "Abandonne ce tracé (Échap)", false] call _mkBtn;
        if (_tool isEqualTo "POLY") then { [[_x0 + _ow - _bwB * 2 - _pad * 0.5, _hy + _hh * 0.1, _bwB, _hh * 0.8], "TERMINER", ["polyEnd"] call _ui1, true, "Termine le tracé (Entrée, clic droit)", false] call _mkBtn; };
    };
    uiNamespace setVariable ["COMSPEC_ATAK_ZnPanel", _list];
};

if (_page isEqualTo "#refresh") exitWith {
    if ((uiNamespace getVariable ["COMSPEC_ATAK_ZnDrawnAt", -1]) isEqualTo diag_frameNo) exitWith {};
    if !(call _active) exitWith {};
    private _grp = uiNamespace getVariable ["COMSPEC_ATAK_ZnForm", controlNull];
    if (_view isNotEqualTo "EDIT" || {isNull _grp} || {((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["pageDrawn", ""]) isNotEqualTo "ZONES"}) exitWith {
        ["ZONES"] call comspec_atak_native_fnc_pageRender;
    };
    call _panel;
    true
};

uiNamespace setVariable ["COMSPEC_ATAK_ZnDrawnAt", diag_frameNo];
// Contrôles de l'ancien rendu (déjà masqués par fn_pageClear) supprimés tout de suite : jamais deux fiches empilées.
{ if (!isNull _x) then { ctrlDelete _x; }; } forEach ((uiNamespace getVariable ["COMSPEC_ATAK_ZnPanel", []]) + (uiNamespace getVariable ["COMSPEC_ATAK_ZnLists", []]));
private _og = uiNamespace getVariable ["COMSPEC_ATAK_ZnForm", controlNull];
if (!isNull _og) then { private _obg = _og getVariable ["bg", controlNull]; ctrlDelete _og; if (!isNull _obg) then { ctrlDelete _obg; }; };
uiNamespace setVariable ["COMSPEC_ATAK_ZnPanel", []];
uiNamespace setVariable ["COMSPEC_ATAK_ZnForm", controlNull];
uiNamespace setVariable ["COMSPEC_ATAK_ZnLists", []];
uiNamespace setVariable ["COMSPEC_ATAK_ZnFormKey", ""];
private _rows = [];
switch (_view) do {
    case "EDIT": {
        private _rowH = (_font * 1.5) min (_bh * 0.08);
        private _topH = _pad / 2 + _rowH + _pad / 3;
        private _land = _l get "landscape";
        private _btn = {
            params ["_rect", "_label", "_code", ["_on", false], ["_tip", ""]];
            private _bt = ["COMSPEC_RscButton", _rect, _label] call comspec_atak_native_fnc_pageCtrl;
            _bt ctrlSetFontHeight ((_fs * 0.95) max COMSPEC_ATAK_FontMin);
            if ((ctrlTextWidth _bt) > (_rect select 2) * 0.92) then { _bt ctrlSetFontHeight (((_fs * 0.95) * (_rect select 2) * 0.92 / (ctrlTextWidth _bt)) max COMSPEC_ATAK_FontMin); };
            if (_on) then { _bt ctrlSetBackgroundColor [0.36, 0.78, 0.42, 0.95]; _bt ctrlSetTextColor [0.03, 0.05, 0.04, 1]; };
            if (_tip isNotEqualTo "") then { _bt ctrlSetTooltip ((((_tip) call _tr)) call comspec_atak_native_fnc_tr); };
            _bt setVariable ["comspec_click", _code];
            _bt ctrlAddEventHandler ["ButtonClick", { params ["_c"]; [{ call (_this getVariable ["comspec_click", {}]); }, _c] call comspec_atak_native_fnc_nextFrame; }];
            _bt
        };
        // Barre du haut : retour, nom du plan, GÉNÉRER et PARTAGER en évidence.
        private _bw4 = (_bw - _pad * 2) / 4;
        [[_pad, _pad / 2, _bw4 * 0.7, _rowH], "◀ PLANS", ["view", "LIST"] call _ui1, false, "Retour à la liste (valide le plan)"] call _btn;
        private _sc = _b get "scope";
        private _tt = ["COMSPEC_RscStructuredText", [_pad + _bw4 * 0.75, _pad / 2, _bw4 * 1.55, _rowH]] call comspec_atak_native_fnc_pageCtrl;
        _tt ctrlSetStructuredText parseText (format ["<t font='COMSPEC_BarlowBold' size='1.1'>%1</t><br/><t size='0.85' color='" + (COMSPEC_ATAK_Th get "hexTextDim") + "'>%2 · %3</t>", [_b get "name"] call _esc,
            format [("%1 zone(s) · %2 axe(s)") call _tr, count (_b get "zones"), count (_b get "axes")], [("privé") call _tr, (["scopeName", _sc] call _ZA)] select (_sc > 0)]); // i18n:brut
        if (_mineB) then {
            [[_pad + _bw4 * 2.35, _pad / 2, _bw4 * 0.8, _rowH], "GÉNÉRER", compile "['#ui', ['ztab', 'CUT']] call comspec_atak_native_fnc_pageZones; ['#ui', ['gen']] call comspec_atak_native_fnc_pageZones;", true, "Découpe la zone de travail (onglet DÉCOUPER)"] call _btn;
            [[_pad + _bw4 * 3.2, _pad / 2, _bw4 * 0.8, _rowH], [format [("PARTAGÉ : %1") call _tr, toUpper (["scopeName", _sc] call _ZA)], "PARTAGER"] select (_sc isEqualTo 0), compile format ["['#ui', ['ztab', 'SIT']] call comspec_atak_native_fnc_pageZones;%1", ["", " ['#ui', ['share', 3]] call comspec_atak_native_fnc_pageZones;"] select (_sc isEqualTo 0)], _sc isEqualTo 0, "Portée du plan (onglet SITUATION) ; un plan partagé est renvoyé à chaque validation"] call _btn;
        } else {
            [[_pad + _bw4 * 3.2, _pad / 2, _bw4 * 0.8, _rowH], "SITUATION", ["ztab", "SIT"] call _ui1, true] call _btn;
        };
        private _mapRect = if (_land) then { [_bx, _by + _topH, _bw * 0.62, _bh - _topH] } else { [_bx, _by + _topH, _bw, (_bh - _topH) * 0.56] };
        private _formRect = if (_land) then { [_bw * 0.62, _topH, _bw * 0.38, _bh - _topH] } else { [0, _topH + (_bh - _topH) * 0.56, _bw, (_bh - _topH) * 0.44] };
        private _map = ["COMSPEC_RscMap", _mapRect, "", false] call comspec_atak_native_fnc_pageCtrl;
        uiNamespace setVariable ["COMSPEC_ATAK_ZnMapCtrl", _map];
        _map ctrlAddEventHandler ["Draw", { private _t = diag_tickTime; ["draw", _this] call comspec_atak_native_fnc_zoneMap; ["carte Zones (image)", _t] call comspec_atak_native_fnc_perfAcc; }];
        _map ctrlAddEventHandler ["MouseButtonDown", { ["down", _this] call comspec_atak_native_fnc_zoneMap; }];
        _map ctrlAddEventHandler ["MouseMoving", { ["move", _this] call comspec_atak_native_fnc_zoneMap; }];
        // Rectangle et cercle en clic-glisser : second point au relâchement (fn_zoneMap « up »).
        _map ctrlAddEventHandler ["MouseButtonUp", { ["up", _this] call comspec_atak_native_fnc_zoneMap; }];
        private _v = uiNamespace getVariable ["COMSPEC_ATAK_ZnView", []];
        if ((count _v) isNotEqualTo 2) then {
            private _c = getPosATL player;
            private _scl = 0.05;
            private _g = ["geo", _b] call _ZA;
            (_g select 1) params ["_X0", "_Y0", "_X1", "_Y1"];
            if (_X1 > _X0) then {
                _c = [(_X0 + _X1) / 2, (_Y0 + _Y1) / 2];
                _scl = (((_X1 - _X0) max (_Y1 - _Y0)) / worldSize * 1.4) max 0.008 min 1;
            };
            _v = [_scl, [_c select 0, _c select 1]];
        };
        _map setVariable ["comspec_znAim", _v];
        _map setVariable ["comspec_znBorn", diag_tickTime];
        _map ctrlMapAnimAdd [0, _v select 0, [(_v select 1) select 0, (_v select 1) select 1, 0]];
        ctrlMapAnimCommit _map;
        uiNamespace setVariable ["COMSPEC_ATAK_ZnFormRect", _formRect];
        uiNamespace setVariable ["COMSPEC_ATAK_ZnMapRect", _mapRect];
        call _panel;
        // Clavier de l'app : un gestionnaire par écran du téléphone (KeyDown et KeyUp pour Alt), actif sur cette page.
        private _disp = [] call comspec_atak_native_fnc_display;
        if (!isNull _disp && {(_disp getVariable ["COMSPEC_ZnKeyEh", -1]) < 0}) then {
            _disp setVariable ["COMSPEC_ZnKeyEh", _disp displayAddEventHandler ["KeyDown", { ["#key", _this] call comspec_atak_native_fnc_pageZones }]];
            _disp displayAddEventHandler ["KeyUp", { ["#keyUp", _this] call comspec_atak_native_fnc_pageZones }];
        };
        (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) set ["backSub", ["ZONES", { ['#ui', ['view', 'LIST']] call comspec_atak_native_fnc_pageZones; }]];
    };
    default {
        private _tab = _ui get "tab";
        private _mL = values (["mine"] call _ZA);
        private _rL = values (["rx"] call _ZA);
        _rows pushBack ["segment", "", [
            [format [("MES PLANS (%1)") call _tr, count _mL], ["tab", "MINE"] call _ui1, _tab isEqualTo "MINE"],
            [format [("REÇUS (%1)") call _tr, count _rL], ["tab", "RX"] call _ui1, _tab isEqualTo "RX", "Plans partagés par l'équipe, le groupe ou le camp"],
            ["SITUATION", ["tab", "SIT"] call _ui1, _tab isEqualTo "SIT", "Zones par état et derniers signalements"]
        ]];
        (_ui get "undoBar") params [["_ubT", ""], ["_ubOp", ""], ["_ubV", ""], ["_ubAt", -1]];
        if (_ubT isNotEqualTo "" && {(diag_tickTime - _ubAt) < 8}) then {
            _rows pushBack ["person", "", format ["<t color='" + (COMSPEC_ATAK_Th get "hexWarn") + "'>%1</t>", _ubT], [["ANNULER", [_ubOp, _ubV] call _ui1, true]], [0.95, 0.67, 0.2, 1], "!"];
        };
        _rows pushBack ["switch", "Zones sur la carte ATAK", profileNamespace getVariable ["COMSPEC_ATAK_ZonesShow", true], ["show"] call _ui1, "Bascule de tous les plans sur la carte de l'app Carte", false, "znShow"];
        _rows pushBack ["buttons", [["+ NOUVEAU PLAN", ["new"] call _ui1, true], ["SIGNALER ICI", ["here"] call _ui1, false, true, "Ouvre la zone sous ma position pour signaler"]]];
        // Questions du mode « Proposer » (contact posé dans une zone) : rien ne change sans OUI.
        {
            _x params ["_qid", "_qz", "_qst", "", "_qby", "_qt"];
            private _qb = ["get", _qid] call _ZA;
            private _z = ["zone", [_qid, _qz]] call _ZA;
            if ((count _z) > 0) then {
                private _k = _qid + "|" + _qz;
                _rows pushBack ["section", format [("Contact en %1 (%2)") call _tr, _z select 1, _qb get "name"], format [("%1, %2 : passer la zone en « %3 » ?") call _tr, _qby, [_qt] call _ago, ((["state", _qst] call _ZA) select 1) call _tr]];
                _rows pushBack ["buttons", [["OUI", ["ask", [_k, true]] call _ui1, true], ["NON", ["ask", [_k, false]] call _ui1]]];
            };
        } forEach (["asks"] call _ZA);
        if (_tab isEqualTo "SIT") then {
            private _all = (_mL + _rL) select { _x get "vis" };
            private _cnt = createHashMap;
            private _hot = [];
            private _reps = [];
            private _k = 0;
            {
                private _bb = _x;
                {
                    private _st = _x select 6;
                    _cnt set [_st, (_cnt getOrDefault [_st, 0]) + 1];
                    _k = _k + 1;
                    if (_st in ["ENY", "CONT", "DENY"]) then { _hot pushBack [_x select 7, _k, _bb, _x]; };
                    private _z = _x;
                    { _k = _k + 1; _reps pushBack [_x select 1, _k, _bb, _z, _x]; } forEach (_x select 9);
                } forEach (_bb get "zones");
            } forEach _all;
            if ((count _all) isEqualTo 0) exitWith {
                _rows pushBack ["empty", _icon, "Aucun plan affiché", "Créez un plan de zones, ou attendez celui de votre chef : la situation par zone s'affiche ici."];
            };
            _rows pushBack ["section", "Zones par état", format [("%1 plan(s) affiché(s)") call _tr, count _all]];
            _rows pushBack ["cells", (["states"] call _ZA) apply { ["\A3\ui_f\data\map\markers\military\dot_CA.paa", _x select 4, (_x select 1) call _tr, str (_cnt getOrDefault [_x select 0, 0]), ""] }, 3];
            _hot sort false;
            if ((count _hot) > 0) then {
                _rows pushBack ["section", "Zones à surveiller", ""];
                {
                    _x params ["_t", "", "_bb", "_z"];
                    private _sr = ["state", _z select 6] call _ZA;
                    _rows pushBack ["person", "", format ["<t font='COMSPEC_BarlowBold' color='%1'>%2</t>  <t color='" + (COMSPEC_ATAK_Th get "hexTextDim") + "'>%3</t><br/>%4%5",
                        [_sr select 4] call _hex, [_z select 1] call _esc, [_bb get "name"] call _esc, (_sr select 1) call _tr, ["", format [" · %1 · %2", [_z select 8] call _esc, [_t] call _ago]] select ((_z select 8) isNotEqualTo "")],
                        [["OUVRIR", ["openZone", [_bb get "id", _z select 0]] call _ui1, true]], _sr select 4, ((_z select 1) select [0, 2])];
                } forEach (_hot select [0, 20]);
            };
            _reps sort false;
            _rows pushBack ["section", "Derniers signalements", ["", format [("%1 au total") call _tr, count _reps]] select ((count _reps) > 0)];
            if ((count _reps) isEqualTo 0) then { _rows pushBack ["text", format ["<t color='" + (COMSPEC_ATAK_Th get "hexTextDim") + "'>%1</t>", ("Aucun signalement : donnez l'état d'une zone ou signalez depuis sa fiche.") call _tr]]; };
            {
                _x params ["_t", "", "_bb", "_z", "_r"];
                _r params ["", "", "_hh", "_who", "_nat", "_size", "_txt"];
                _rows pushBack ["person", "", format ["<t font='COMSPEC_BarlowBold' color='" + (COMSPEC_ATAK_Th get "hexWarn") + "'>%1</t>  <t font='COMSPEC_BarlowBold'>%2</t> <t color='" + (COMSPEC_ATAK_Th get "hexTextDim") + "'>(%3)</t><br/>%4%5%6 · <t color='" + (COMSPEC_ATAK_Th get "hexTextDim") + "'>%7</t>",
                    _hh, [_z select 1] call _esc, [_bb get "name"] call _esc, ["natureName", _nat] call _ZA, ["", " · " + ([_size] call _esc)] select (_size isNotEqualTo ""), ["", " · " + ([_txt select [0, 60]] call _esc)] select (_txt isNotEqualTo ""), [_who] call _esc],
                    [["VOIR", ["openZone", [_bb get "id", _z select 0]] call _ui1]], [1, 0.6, 0.12, 1], ((_z select 1) select [0, 2])];
            } forEach (_reps select [0, 15]);
        } else {
            private _list = [_rL, _mL] select (_tab isEqualTo "MINE");
            private _i0 = -1;
            _list = _list apply { _i0 = _i0 + 1; [toLower (_x get "name"), _i0, _x] };
            _list sort true;
            if ((count _list) isEqualTo 0) then {
                _rows pushBack ["empty", _icon, ["Aucun plan reçu", "Aucun plan de zones"] select (_tab isEqualTo "MINE"),
                    ["Les plans partagés par votre équipe, votre groupe ou votre camp arrivent ici.", "Créez un plan : tracez la zone de travail, découpez-la en zones nommées, nommez les axes ; il part au camp à chaque validation."] select (_tab isEqualTo "MINE")];
            };
            {
                private _x2 = _x select 2;
                private _zs = _x2 get "zones";
                private _nE = { (_x select 6) in ["ENY", "CONT"] } count _zs;
                private _tags = [];
                if ((_x2 get "scope") > 0) then { _tags pushBack ([format [("partagé : %1") call _tr, ["scopeName", _x2 get "scope"] call _ZA], format [("de %1 · %2") call _tr, [_x2 get "from"] call _esc, ["scopeName", _x2 get "scope"] call _ZA]] select (_tab isNotEqualTo "MINE")); };
                if (_x2 get "arma") then { _tags pushBack (("carte d'Arma") call _tr); };
                if !(_x2 get "vis") then { _tags pushBack (("masqué") call _tr); };
                private _txt = format ["<t font='COMSPEC_BarlowBold'>%1</t>  <t color='" + (COMSPEC_ATAK_Th get "hexTextDim") + "'>%2 · %3</t>%4%5",
                    [_x2 get "name"] call _esc, format [("%1 zone(s)") call _tr, count _zs], format [("%1 axe(s)") call _tr, count (_x2 get "axes")],
                    ["", format ["<br/><t color='" + (COMSPEC_ATAK_Th get "hexAlert") + "'>%1</t>", format [("%1 zone(s) avec contact") call _tr, _nE]]] select (_nE > 0),
                    ["", format ["<br/><t color='#8fd19a'>%1</t>", _tags joinString " · "]] select ((count _tags) > 0)];
                _rows pushBack ["person", _icon, _txt, [
                    ["OUVRIR", ["open", _x2 get "id"] call _ui1, true],
                    [["AFFICHER", "MASQUER"] select (_x2 get "vis"), ["vis", _x2 get "id"] call _ui1, false, true, "Sur la carte ATAK"]
                ]];
            } forEach _list;
        };
    };
};
if (_view isNotEqualTo "EDIT") then {
    private _g = [_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
    uiNamespace setVariable ["COMSPEC_ATAK_ZnLists", [_g, _g getVariable ["bg", controlNull]]];
};
true
