/*
    App Zones (page ZONES) : battleplan de situation. Vues (uiNamespace COMSPEC_ATAK_ZnUi, clé view) :
      LIST  onglets MES PLANS / REÇUS / SITUATION (zones par état, derniers signalements avec l'heure), affichage sur la
            carte ATAK, nouveau plan, SIGNALER ICI (zone sous ma position) ;
      EDIT  carte de l'app (zone de travail : rectangle, polygone, cercle ; clic sur une zone : la choisir, fn_zoneMap) et
            fiche du plan : zone choisie (état en un clic), découpage (grille GRG, îlots par les routes, secteurs, une
            zone), axes MSR / ASR et points de contrôle (repris dans l'app Routes), rendu, affichage, partage, nom ;
      ZONE  fiche d'une zone : état, signaler un ennemi (nature, effectif, texte), signalements, citer dans un message,
            voir sur la carte ; pour l'auteur : nom, type, rendu, bâtiments numérotés, supprimer.
    Dans la vue EDIT, un clic (carte ou fiche) ne refait que la fiche : la carte garde sa vue.
    Téléphone porté (sans souris) : onglet SITUATION.
    Params : [page, rectangle] (app de module, fn_pageRender) | ["#refresh"] | ["#ui", [op, valeur]]
             | ["#open", [id, id de zone, position du signalement]] (menu de la carte, SIGNALER ICI)
*/
params [["_page", "ZONES"], ["_arg", []]];
disableSerialization;
private _ui = uiNamespace getVariable "COMSPEC_ATAK_ZnUi";
if (isNil "_ui") then {
    _ui = createHashMapFromArray [["view", "LIST"], ["tab", "MINE"], ["id", ""], ["zid", ""], ["tool", ""], ["draftPts", []], ["draft", createHashMap], ["confirm", ""], ["repPos", []], ["repNat", "UNK"]];
    uiNamespace setVariable ["COMSPEC_ATAK_ZnUi", _ui];
};
private _tr = { _this call comspec_atak_native_fnc_tr };
private _ZA = comspec_atak_native_fnc_zoneAction;
private _esc = { params ["_t"]; [_t, "&", "&amp;"] call CBA_fnc_replace };
private _fmt = { params ["_m"]; [format ["%1 m", round _m], format ["%1 km", (_m / 1000) toFixed 1]] select (_m >= 1000) };
private _card = { params ["_a"]; private _t = str ((round _a) mod 360); while { (count _t) < 3 } do { _t = "0" + _t; }; format ["%1° %2", _t, [_a] call comspec_atak_native_fnc_cardinal] };
private _hex = { params ["_c"]; (_c select [0, 3]) call BIS_fnc_colorRGBtoHTML };
private _ago = { params ["_t"]; private _m = round ((serverTime - _t) / 60); [format [("il y a %1 min") call _tr, _m], ("à l'instant") call _tr] select (_m < 1) };
private _rerender = { [{
    if ((uiNamespace getVariable ["COMSPEC_ATAK_ZnDrawnAt", -1]) isEqualTo diag_frameNo) exitWith {};
    if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "ZONES") then { ["ZONES"] call comspec_atak_native_fnc_pageRender; };
}] call CBA_fnc_execNextFrame; };
private _ui1 = { params ["_op", ["_v", ""]]; compile format ["['#ui', [%1, %2]] call comspec_atak_native_fnc_pageZones;", str _op, str _v] };

// Ouverture d'une zone depuis la carte (menu du clic droit) ou SIGNALER ICI.
if (_page isEqualTo "#open") exitWith {
    _arg params [["_id", ""], ["_zid", ""], ["_pos", []]];
    _ui set ["id", _id]; _ui set ["zid", _zid]; _ui set ["view", "ZONE"]; _ui set ["repPos", _pos]; _ui set ["confirm", ""];
    if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "ZONES") then { call _rerender; } else { ["ZONES"] call comspec_atak_native_fnc_navigate; };
    true
};

if (_page isEqualTo "#ui") exitWith {
    _arg params [["_op", ""], ["_v", ""]];
    private _id = _ui get "id";
    private _full = true;
    private _form = { params ["_k", ["_d", ""]]; [_k, _d] call comspec_atak_native_fnc_formValue };
    switch (_op) do {
        case "view": { _ui set ["view", _v]; _ui set ["confirm", ""]; _ui set ["tool", ""]; _ui set ["draftPts", []]; };
        case "tab": { _ui set ["tab", _v]; };
        case "open": { _ui set ["id", _v]; _ui set ["zid", ""]; _ui set ["view", "EDIT"]; _ui set ["tool", ""]; _ui set ["draftPts", []]; _ui set ["draft", createHashMap]; _ui set ["confirm", ""]; uiNamespace setVariable ["COMSPEC_ATAK_ZnView", []]; };
        case "new": {
            private _nid = ["new", [""]] call _ZA;
            if (_nid isEqualTo "") exitWith { _full = false; };
            ["#ui", ["open", _nid]] call comspec_atak_native_fnc_pageZones;
            _ui set ["tool", "RECT"];
            ["INFO", "Plan créé : tracez la zone de travail sur la carte (deux coins), ou choisissez polygone ou cercle", 5, 20] call comspec_atak_native_fnc_notify;
            _full = false;
        };
        case "show": {
            private _on = !(profileNamespace getVariable ["COMSPEC_ATAK_ZonesShow", true]);
            profileNamespace setVariable ["COMSPEC_ATAK_ZonesShow", _on];
            ["znShow", _on] call comspec_atak_native_fnc_formSet;
            playSound "ClickSoft";
            _full = false;
        };
        case "vis": { private _b = ["get", _v] call _ZA; if ((count _b) > 0) then { ["set", [_v, "vis", !(_b get "vis")]] call _ZA; }; };
        case "tool": {
            _ui set ["tool", [_v, ""] select ((_ui get "tool") isEqualTo _v)]; _ui set ["draftPts", []]; _ui set ["zid", ""];
            _full = false; ["#refresh"] call comspec_atak_native_fnc_pageZones;
        };
        case "aroundMe": {
            private _r = parseNumber (["znRad", "300"] call _form);
            _r = (_r max 50) min 5000;
            ["gp", [_id, "radius", _r]] call _ZA;
            private _p = [] call comspec_atak_native_fnc_gpsPos;
            ["areaSet", [_id, "CIRCLE", [[_p select 0, _p select 1], _r]]] call _ZA;
            (_ui get "draft") deleteAt "znRad";
            ["INFO", format ["Zone de travail : %1 m autour de moi", _r], 3, 10] call comspec_atak_native_fnc_notify;
            _full = false;
        };
        case "gp": { _v params ["_k", "_val"]; ["gp", [_id, _k, _val]] call _ZA; playSound "ClickSoft"; _full = false; };
        case "gpField": { _v params ["_k", "_fk"]; ["gp", [_id, _k, [_fk, ""] call _form]] call _ZA; (_ui get "draft") deleteAt _fk; playSound "ClickSoft"; _full = false; };
        case "gen": {
            private _b = ["get", _id] call _ZA;
            if ((count (_b getOrDefault ["zones", []])) > 0 && {(_ui get "confirm") isNotEqualTo "gen"}) exitWith {
                _ui set ["confirm", "gen"]; _full = false; ["#refresh"] call comspec_atak_native_fnc_pageZones;
            };
            _ui set ["confirm", ""]; _ui set ["zid", ""];
            // Préfixe saisi sans appuyer sur OK : pris tel quel.
            if (!isNull ((uiNamespace getVariable ["COMSPEC_ATAK_Form", createHashMap]) getOrDefault ["znPre", controlNull])) then { ["gp", [_id, "prefix", ["znPre", ""] call _form]] call _ZA; (_ui get "draft") deleteAt "znPre"; };
            ["gen", [_id]] call _ZA;
            _full = false;
        };
        case "axes": { ["axes", [_id]] call _ZA; _full = false; };
        case "axisRoute": {
            private _rid = ["axisRoute", [_id, _v]] call _ZA;
            _full = false;
            if (_rid isNotEqualTo "") then { ["ROUTE"] call comspec_atak_native_fnc_navigate; ["#ui", ["open", _rid]] call comspec_atak_native_fnc_pageRoute; };
        };
        case "axisDel": { ["axisSet", [_id, _v, "del"]] call _ZA; _full = false; };
        case "axisRm": { _v params ["_aid", "_rm"]; ["axisSet", [_id, _aid, "rm", _rm]] call _ZA; _full = false; };
        case "set": { _v params ["_k", "_val"]; ["set", [_id, _k, _val]] call _ZA; playSound "ClickSoft"; _full = false; };
        case "toggle": {
            _v params ["_key", "_rowId"];
            private _b = ["get", _id] call _ZA;
            if ((count _b) isEqualTo 0) exitWith {};
            ["set", [_id, _key, !(_b get _key)]] call _ZA;
            [_rowId, (["get", _id] call _ZA) get _key] call comspec_atak_native_fnc_formSet;
            playSound "ClickSoft";
            _full = false;
        };
        case "field": { _v params ["_k", "_fk"]; ["set", [_id, _k, [_fk, ""] call _form]] call _ZA; (_ui get "draft") deleteAt _fk; playSound "ClickSoft"; _full = false; };
        case "autoName": { ["autoName", _id] call _ZA; (_ui get "draft") deleteAt "znName"; _full = false; };
        case "share": { ["share", [_id, _v]] call _ZA; _full = false; };
        case "unshare": { ["unshare", _id] call _ZA; ["INFO", "Partage retiré", 2, 10] call comspec_atak_native_fnc_notify; _full = false; };
        case "reset": {
            if ((_ui get "confirm") isNotEqualTo "reset") exitWith { _ui set ["confirm", "reset"]; _full = false; ["#refresh"] call comspec_atak_native_fnc_pageZones; };
            _ui set ["confirm", ""];
            ["reset", _id] call _ZA; _full = false;
        };
        case "del": {
            if ((_ui get "confirm") isNotEqualTo _id) exitWith { _ui set ["confirm", _id]; _full = false; ["#refresh"] call comspec_atak_native_fnc_pageZones; };
            ["del", _id] call _ZA;
            ["INFO", "Plan supprimé", 2, 10] call comspec_atak_native_fnc_notify;
            _ui set ["id", ""]; _ui set ["view", "LIST"]; _ui set ["confirm", ""];
        };
        case "zone": { _ui set ["zid", _v]; _ui set ["view", "ZONE"]; _ui set ["repPos", []]; _ui set ["confirm", ""]; };
        case "zoneSel": { _ui set ["zid", [_v, ""] select ((_ui get "zid") isEqualTo _v)]; _full = false; ["#refresh"] call comspec_atak_native_fnc_pageZones; };
        case "state": {
            // État en un clic (fiche du plan ou de la zone) : [id de zone, état].
            _v params ["_zid", "_st"];
            ["sit", [_id, _zid, _st, []]] call _ZA;
            _full = (_ui get "view") isEqualTo "ZONE";
        };
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
        };
        case "here": {
            // SIGNALER ICI : zone sous ma position.
            private _p = [] call comspec_atak_native_fnc_gpsPos;
            private _hit = ["at", [_p]] call _ZA;
            if ((count _hit) isEqualTo 0) exitWith { ["INFO", "Aucune zone affichée sous ma position", 3, 10] call comspec_atak_native_fnc_notify; _full = false; };
            ["#open", [(_hit select 0) get "id", (_hit select 1) select 0, [_p select 0, _p select 1]]] call comspec_atak_native_fnc_pageZones;
            _full = false;
        };
        case "openZone": { _v params ["_bid", "_zid"]; ["#open", [_bid, _zid, []]] call comspec_atak_native_fnc_pageZones; _full = false; };
        case "cite": {
            private _b = ["get", _id] call _ZA;
            private _z = ["zone", [_id, _ui get "zid"]] call _ZA;
            if ((count _z) isEqualTo 0) exitWith {};
            private _t = ["cite", [_b, _z]] call _ZA;
            ["clip", _t] call comspec_atak_native_fnc_planAction;
            uiNamespace setVariable ["COMSPEC_ATAK_ChatDraft", _t];
            [format ["Copié : %1", _t], 3] call comspec_atak_native_fnc_toast;
            ["CHAT"] call comspec_atak_native_fnc_navigate;
            _full = false;
        };
        case "showMap": {
            private _z = ["zone", [_id, _ui get "zid"]] call _ZA;
            if ((count _z) isEqualTo 0) exitWith {};
            ["MAP"] call comspec_atak_native_fnc_navigate;
            (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) set ["mapFollow", false];
            [{ [[(_this select 0), (_this select 1), 0]] call comspec_atak_native_fnc_mapCenter; }, _z select 3, 0.3] call CBA_fnc_waitAndExecute;
            _full = false;
        };
        case "zoneField": { _v params ["_k", "_fk"]; ["zoneSet", [_id, _ui get "zid", _k, [_fk, ""] call _form]] call _ZA; (_ui get "draft") deleteAt _fk; };
        case "zoneSet": { _v params ["_k", "_val"]; ["zoneSet", [_id, _ui get "zid", _k, _val]] call _ZA; };
        case "zoneDel": {
            if ((_ui get "confirm") isNotEqualTo "zdel") exitWith { _ui set ["confirm", "zdel"]; };
            ["zoneSet", [_id, _ui get "zid", "del"]] call _ZA;
            _ui set ["zid", ""]; _ui set ["view", "EDIT"]; _ui set ["confirm", ""];
        };
        case "bld": { ["bld", [_id, _ui get "zid"]] call _ZA; ["INFO", "Numérotation des bâtiments en cours…", 2, 10] call comspec_atak_native_fnc_notify; _full = false; };
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
private _b = ["get", _ui get "id"] call _ZA;
if (_view in ["EDIT", "ZONE"] && {(count _b) isEqualTo 0}) then { _view = "LIST"; _ui set ["view", "LIST"]; };
private _zone = if ((count _b) > 0) then { ["zone", [_b get "id", _ui get "zid"]] call _ZA } else { [] };
if (_view isEqualTo "ZONE" && {(count _zone) isEqualTo 0}) then { _view = "EDIT"; _ui set ["view", "EDIT"]; };
if (!_inter) then { _view = "LIST"; _ui set ["tab", "SIT"]; };
private _mineB = (count _b) > 0 && {["isMine", _b get "id"] call _ZA};
private _icon = "\z\comspec_atak_native\addons\main\data\app_zones.paa";
private _stateSeg = {
    params ["_zid", "_cur"];
    (["states"] call _ZA) apply { [toUpper ((_x select 1) call _tr), ["state", [_zid, _x select 0]] call _ui1, (_x select 0) isEqualTo _cur, _x select 4] }
};

// Fiche du plan (vue EDIT), refaite seule à chaque clic.
private _editRows = {
    private _rows = [];
    private _gp = _b getOrDefault ["gp", createHashMap];
    private _draft = _ui get "draft";
    private _zs = _b get "zones";
    private _busy = _b getOrDefault ["busy", ""];
    // Zone choisie sur la carte : état en un clic.
    if ((count _zone) > 0) then {
        _zone params ["_zid", "_zn", "", "", "_zt", "", "_zst", "_zstamp", "_zby", "_zreps"];
        _rows pushBack ["section", format [("Zone %1") call _tr, [_zn] call _esc], [format [("%1 signalement(s)") call _tr, count _zreps], format ["%1 · %2", ((["state", _zst] call _ZA) select 1) call _tr, _zby]] select (_zby isNotEqualTo "")];
        _rows pushBack ["lineseg", "", "État", [_zid, _zst] call _stateSeg, "Un clic : l'état part à ceux qui ont le plan"];
        _rows pushBack ["buttons", [["FICHE / SIGNALER", ["zone", _zid] call _ui1, true], ["PLUS DE ZONE CHOISIE", ["zoneSel", _zid] call _ui1]]];
    };
    if (!_mineB) then {
        _rows pushBack ["text", format ["<t color='#f2ab33'>%1</t>", format [("Plan reçu de %1 (%2) : vous pouvez donner l'état des zones et signaler ; le découpage appartient à son auteur.") call _tr, [_b get "from"] call _esc, ["scopeName", _b get "scope"] call _ZA]], [0.10, 0.09, 0.05, 1]];
    } else {
        (["areaPoly", _b] call _ZA) params ["_ap", "_ak", "_ac", "_ar"];
        private _aTxt = switch (_ak) do {
            case "RECT": { (["bbox", [_ap]] call comspec_atak_native_fnc_zoneGeom) params ["_x0", "_y0", "_x1", "_y1"]; format [("rectangle %1 x %2") call _tr, [_x1 - _x0] call _fmt, [_y1 - _y0] call _fmt] };
            case "CIRCLE": { format [("cercle de %1 de rayon") call _tr, [_ar] call _fmt] };
            case "POLY": { format [("polygone de %1 sommets") call _tr, count _ap] };
            default { ("à tracer") call _tr };
        };
        private _tool = _ui get "tool";
        _rows pushBack ["section", "1. Zone de travail", _aTxt];
        _rows pushBack ["segment", "Tracer sur la carte", [
            ["RECTANGLE", ["tool", "RECT"] call _ui1, _tool isEqualTo "RECT", "Deux clics : deux coins opposés"],
            ["POLYGONE", ["tool", "POLY"] call _ui1, _tool isEqualTo "POLY", "Un clic par sommet ; clic sur le premier sommet ou clic droit pour fermer"],
            ["CERCLE", ["tool", "CIRCLE"] call _ui1, _tool isEqualTo "CIRCLE", "Centre (objectif) puis bord"]
        ], switch (_tool) do {
            case "RECT": { ["Cliquez le premier coin", "Cliquez le coin opposé"] select ((count (_ui get "draftPts")) > 0) };
            case "CIRCLE": { ["Cliquez le centre (l'objectif)", "Cliquez le bord du cercle"] select ((count (_ui get "draftPts")) > 0) };
            case "POLY": { format [("%1 sommet(s) : clic droit pour fermer") call _tr, count (_ui get "draftPts")] };
            default { "" };
        }];
        _rows pushBack ["field", "znRad", "Autour de moi : rayon (m)", _draft getOrDefault ["znRad", str (_gp getOrDefault ["radius", 300])], "POSER", ["aroundMe"] call _ui1, false];
        // Découpage.
        private _cut = _gp getOrDefault ["cut", "GRID"];
        _rows pushBack ["section", "2. Découpage automatique", [("Nommer et numéroter les zones") call _tr, format [("%1 zone(s)") call _tr, count _zs]] select ((count _zs) > 0)];
        _rows pushBack ["segment", "Méthode", [
            ["GRILLE", ["gp", ["cut", "GRID"]] call _ui1, _cut isEqualTo "GRID", "Grille de référence (GRG) : cases régulières A1, A2… ou numérotées"],
            ["ÎLOTS", ["gp", ["cut", "BLOCK"]] call _ui1, _cut isEqualTo "BLOCK", "Quartiers délimités par les routes de la carte"],
            ["SECTEURS", ["gp", ["cut", "RADIAL"]] call _ui1, _cut isEqualTo "RADIAL", "Secteurs autour du centre : N, NE… ou 1 à 16"],
            ["UNE ZONE", ["gp", ["cut", "SINGLE"]] call _ui1, _cut isEqualTo "SINGLE", "La zone de travail devient une seule zone"]
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
                _rows pushBack ["segment", "Noms", [
                    ["POINTS CARDINAUX", ["gp", ["secFmt", "CARD"]] call _ui1, _sf isEqualTo "CARD"],
                    ["NUMÉROS", ["gp", ["secFmt", "NUM"]] call _ui1, _sf isEqualTo "NUM"]
                ]];
            };
            default {};
        };
        _rows pushBack ["field", "znPre", "Préfixe des noms (B : B-01, B-C4 ; vide : aucun)", _draft getOrDefault ["znPre", _gp getOrDefault ["prefix", ""]], "OK", ["gpField", ["prefix", "znPre"]] call _ui1, false];
        private _confirm = (_ui get "confirm") isEqualTo "gen";
        _rows pushBack ["buttons", [[[["GÉNÉRER LES ZONES", "REMPLACER LES ZONES"] select ((count _zs) > 0), "CONFIRMER : LES ZONES ACTUELLES SONT REMPLACÉES"] select _confirm, ["gen"] call _ui1, [true, "red"] select _confirm, _busy isEqualTo "", "Calcul en arrière-plan"]]];
        if (_busy isEqualTo "GEN") then { _rows pushBack ["text", format ["<t color='#f2ab33'>%1</t>", ("Découpage en cours…") call _tr]]; };
        // Axes routiers.
        private _ax = _b get "axes";
        _rows pushBack ["section", "3. Axes routiers (MSR / ASR)", [("Grands axes nommés et carrefours en points de contrôle") call _tr, format [("%1 axe(s), %2 point(s) de contrôle") call _tr, count _ax, count (_b get "cps")]] select ((count _ax) > 0)];
        private _th = _gp getOrDefault ["theme", "CITY"];
        _rows pushBack ["segment", "Noms de code", [
            ["VILLES", ["gp", ["theme", "CITY"]] call _ui1, _th isEqualTo "CITY", "TAMPA, DALLAS, BOSTON…"],
            ["ANIMAUX", ["gp", ["theme", "ANIMAL"]] call _ui1, _th isEqualTo "ANIMAL", "COBRA, VIPER, FALCON…"],
            ["OTAN", ["gp", ["theme", "NATO"]] call _ui1, _th isEqualTo "NATO", "ALPHA, BRAVO, CHARLIE…"]
        ]];
        private _na = _gp getOrDefault ["asr", 3];
        _rows pushBack ["segment", "Itinéraires secondaires (ASR)", [0, 1, 2, 3, 4] apply { [str _x, ["gp", ["asr", _x]] call _ui1, _x isEqualTo _na] }];
        _rows pushBack ["buttons", [[["NOMMER LES AXES", "RENOMMER LES AXES"] select ((count _ax) > 0), ["axes"] call _ui1, true, _busy isEqualTo "", "Le plus grand axe devient MSR, les suivants ASR ; calcul en arrière-plan"]]];
        if (_busy isEqualTo "AXES") then { _rows pushBack ["text", format ["<t color='#f2ab33'>%1</t>", ("Recherche des axes…") call _tr]]; };
        {
            _x params ["_aid", "_an", "_cls", "", "_len", ["_arm", ""]];
            private _ncp = { (_x select 3) isEqualTo _an } count (_b get "cps");
            _rows pushBack ["person", "\a3\ui_f\data\igui\cfg\simpletasks\types\car_ca.paa",
                format ["<t font='COMSPEC_BarlowBold'>%1</t>  <t color='#a9b6b0'>%2 · %3</t>", [_an] call _esc, [_len] call _fmt, format [("%1 CP") call _tr, _ncp]],
                [["ROUTE", ["axisRoute", _aid] call _ui1, true, true, "Crée cette route dans l'app Routes (véhicule, par les routes, CP en étapes)"], ["X", ["axisDel", _aid] call _ui1, false, true, "Retirer l'axe"]],
                [[0.35, 0.85, 0.40, 1], [0.30, 0.58, 1, 1]] select (_cls isEqualTo "MSR")];
        } forEach _ax;
    };
    // Rendu et affichage.
    _rows pushBack ["section", "Rendu", ""];
    private _rd = _b get "render";
    _rows pushBack ["segment", "Zones et axes", (["renders"] call _ZA) apply { [toUpper ((_x select 1) call _tr), ["set", ["render", _x select 0]] call _ui1, (_x select 0) isEqualTo _rd] }, "Surligné : rempli et bordé ; contour seul ; ou juste un point centré avec le nom"];
    if (_mineB) then {
        _rows pushBack ["lineseg", "", "Type des zones", (["types"] call _ZA) apply { [toUpper ((_x select 1) call _tr), ["set", ["aff", _x select 0]] call _ui1, (_x select 0) isEqualTo (_b get "aff"), _x select 2] }, "Couleur du contour (symbologie : ami bleu, ennemi rouge, neutre vert, inconnu jaune)"];
    };
    _rows pushBack ["switch", "Afficher ce plan", _b get "vis", ["toggle", ["vis", "znVis"]] call _ui1, "Sur la carte ATAK et dans le bandeau du curseur", false, "znVis"];
    if (_mineB) then {
        _rows pushBack ["switch", "Carte d'Arma et Athena", _b get "arma", ["toggle", ["arma", "znArma"]] call _ui1, "Cases en rectangles, autres zones en contour, étiquettes, axes et CP en marqueurs (canal du groupe, du camp si partagé au camp), aussi envoyés au site Athena", !(missionNamespace getVariable ["comspec_atak_native_zones_arma", true]), "znArma"];
        private _sc = _b get "scope";
        _rows pushBack ["section", "Partage", [("Non partagé") call _tr, format [("Partagé : %1, chaque modification est renvoyée") call _tr, ["scopeName", _sc] call _ZA]] select (_sc > 0)];
        _rows pushBack ["segment", "Envoyer à", [
            ["ÉQUIPE", ["share", 1] call _ui1, _sc isEqualTo 1, "Mon équipe de feu (le groupe si je n'en ai pas)"],
            ["GROUPE", ["share", 2] call _ui1, _sc isEqualTo 2, "Tout mon groupe"],
            ["CAMP", ["share", 3] call _ui1, _sc isEqualTo 3, "Tout mon camp"]
        ], "Par le réseau ATAK : sans réseau, l'envoi part au retour du signal ; les états et signalements suivent"];
        if (_sc > 0) then { _rows pushBack ["buttons", [["RETIRER LE PARTAGE", ["unshare"] call _ui1]]]; };
        _rows pushBack ["section", "Plan", ""];
        _rows pushBack ["field", "znName", "Nom", _draft getOrDefault ["znName", _b get "name"], "RENOMMER", ["field", ["name", "znName"]] call _ui1, false];
        private _sp = _gp getOrDefault ["setPre", "OBJ"];
        _rows pushBack ["segment", "Nom automatique", ["OBJ", "AO", "NAI", "SECT"] apply { [_x, ["gp", ["setPre", _x]] call _ui1, _x isEqualTo _sp] }];
        private _st = _gp getOrDefault ["setTheme", "NATO"];
        _rows pushBack ["segment", "", [["OTAN", ["gp", ["setTheme", "NATO"]] call _ui1, _st isEqualTo "NATO"], ["VILLES", ["gp", ["setTheme", "CITY"]] call _ui1, _st isEqualTo "CITY"], ["ANIMAUX", ["gp", ["setTheme", "ANIMAL"]] call _ui1, _st isEqualTo "ANIMAL"]]];
        _rows pushBack ["buttons", [["NOM AUTO", ["autoName"] call _ui1, false, true, "Prochain nom de code libre ; le préfixe des zones prend son initiale"]]];
        private _cr = (_ui get "confirm") isEqualTo "reset";
        private _cd = (_ui get "confirm") isEqualTo (_b get "id");
        _rows pushBack ["buttons", [
            [["RÉINITIALISER LA SITUATION", "CONFIRMER"] select _cr, ["reset"] call _ui1, ["red", false] select !_cr, true, "Toutes les zones repassent « non reconnue », signalements effacés (partout)"],
            [["SUPPRIMER", "CONFIRMER"] select _cd, ["del"] call _ui1, ["red", false] select !_cd, true, "Supprimer le plan (deux clics)"]
        ]];
    };
    // Liste des zones.
    _rows pushBack ["section", format [("Zones (%1)") call _tr, count _zs], ["", ("Clic sur une zone de la carte ou FICHE") call _tr] select ((count _zs) > 0)];
    {
        _x params ["_zid", "_zn", "", "", "_zt", "", "_zst", "", "", "_zreps", "_zb"];
        private _sr = ["state", _zst] call _ZA;
        private _extra = [];
        if ((count _zreps) > 0) then { _extra pushBack format [("%1 signalement(s)") call _tr, count _zreps]; };
        if ((count _zb) > 0) then { _extra pushBack format [("%1 bâtiment(s)") call _tr, count _zb]; };
        _rows pushBack ["person", "", format ["<t font='COMSPEC_BarlowBold' color='%1'>%2</t>  <t color='#a9b6b0'>%3</t>%4",
            ["#e6ece8", "#5cc76b"] select (_zid isEqualTo (_ui get "zid")), [_zn] call _esc, ((_sr select 1) call _tr), ["", "<br/>" + (_extra joinString " · ")] select ((count _extra) > 0)],
            [["FICHE", ["zone", _zid] call _ui1, false, true, "État, signalements, bâtiments"]], _sr select 4, (_zn select [0, 2])];
    } forEach (_zs select [0, 80]);
    if ((count _zs) > 80) then { _rows pushBack ["text", format ["<t color='#a9b6b0'>%1</t>", format [("… et %1 autres : choisissez-les sur la carte") call _tr, (count _zs) - 80]]]; };
    _rows
};

if (_page isEqualTo "#refresh") exitWith {
    if ((uiNamespace getVariable ["COMSPEC_ATAK_ZnDrawnAt", -1]) isEqualTo diag_frameNo) exitWith {};
    private _grp = uiNamespace getVariable ["COMSPEC_ATAK_ZnForm", controlNull];
    private _rect = uiNamespace getVariable ["COMSPEC_ATAK_ZnFormRect", []];
    if (_view isNotEqualTo "EDIT" || {isNull _grp} || {(count _rect) isNotEqualTo 4} || {((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["pageDrawn", ""]) isNotEqualTo "ZONES"}) exitWith {
        ["ZONES"] call comspec_atak_native_fnc_pageRender;
    };
    private _draft = _ui get "draft";
    { private _c = (uiNamespace getVariable ["COMSPEC_ATAK_Form", createHashMap]) getOrDefault [_x, controlNull]; if (!isNull _c) then { _draft set [_x, ctrlText _c]; }; } forEach ["znName", "znPre", "znRad"];
    if ((_draft getOrDefault ["znName", ""]) isEqualTo (_b get "name")) then { _draft deleteAt "znName"; };
    private _sv = (ctrlScrollValues _grp) select 0;
    private _bg = _grp getVariable ["bg", controlNull];
    ctrlDelete _grp;
    if (!isNull _bg) then { ctrlDelete _bg; };
    uiNamespace setVariable ["COMSPEC_ATAK_ZnDrawnAt", diag_frameNo];
    private _ng = [call _editRows, _rect] call comspec_atak_native_fnc_formRender;
    uiNamespace setVariable ["COMSPEC_ATAK_ZnForm", _ng];
    if (_sv > 0) then {
        _ng ctrlSetScrollValues [_sv, -1];
        [{ params ["_g", "_v"]; if (!isNull _g) then { _g ctrlSetScrollValues [_v, -1]; }; }, [_ng, _sv]] call CBA_fnc_execNextFrame;
    };
    true
};

uiNamespace setVariable ["COMSPEC_ATAK_ZnDrawnAt", diag_frameNo];
uiNamespace setVariable ["COMSPEC_ATAK_ZnForm", controlNull];
private _rows = [];
switch (_view) do {
    case "EDIT": {
        private _rowH = (_font * 1.5) min (_bh * 0.08);
        private _topH = _pad / 2 + _rowH + _pad / 3;
        private _land = _l get "landscape";
        private _btn = {
            params ["_rect", "_label", "_code", ["_on", false]];
            private _bt = ["COMSPEC_RscButton", _rect, _label] call comspec_atak_native_fnc_pageCtrl;
            _bt ctrlSetFontHeight ((_fs * 0.95) max COMSPEC_ATAK_FontMin);
            if (_on) then { _bt ctrlSetBackgroundColor [0.36, 0.78, 0.42, 0.95]; _bt ctrlSetTextColor [0.03, 0.05, 0.04, 1]; };
            _bt setVariable ["comspec_click", _code];
            _bt ctrlAddEventHandler ["ButtonClick", { params ["_c"]; [{ call (_this getVariable ["comspec_click", {}]); }, _c] call CBA_fnc_execNextFrame; }];
            _bt
        };
        private _bw3 = (_bw - _pad * 2 - _pad) / 3;
        [[_pad, _pad / 2, _bw3 * 0.8, _rowH], "◀ PLANS", ["view", "LIST"] call _ui1] call _btn;
        private _tt = ["COMSPEC_RscStructuredText", [_pad + _bw3 * 0.8 + _pad / 2, _pad / 2, _bw3 * 1.4, _rowH]] call comspec_atak_native_fnc_pageCtrl;
        _tt ctrlSetStructuredText parseText (format ["<t font='COMSPEC_BarlowBold' size='1.1'>%1</t><br/><t size='0.85' color='#a9b6b0'>%2</t>", [_b get "name"] call _esc,
            format [("%1 zone(s) · %2 axe(s)") call _tr, count (_b get "zones"), count (_b get "axes")]]); // i18n:brut
        [[_bw - _pad - _bw3 * 0.8, _pad / 2, _bw3 * 0.8, _rowH], "SITUATION", compile "['#ui', ['tab', 'SIT']] call comspec_atak_native_fnc_pageZones; ['#ui', ['view', 'LIST']] call comspec_atak_native_fnc_pageZones;", true] call _btn;
        private _mapRect = if (_land) then { [_bx, _by + _topH, _bw * 0.56, _bh - _topH] } else { [_bx, _by + _topH, _bw, (_bh - _topH) * 0.46] };
        private _formRect = if (_land) then { [_bw * 0.56, _topH, _bw * 0.44, _bh - _topH] } else { [0, _topH + (_bh - _topH) * 0.46, _bw, (_bh - _topH) * 0.54] };
        private _map = ["COMSPEC_RscMap", _mapRect, "", false] call comspec_atak_native_fnc_pageCtrl;
        _map ctrlAddEventHandler ["Draw", { ["draw", _this] call comspec_atak_native_fnc_zoneMap; }];
        _map ctrlAddEventHandler ["MouseButtonDown", { ["down", _this] call comspec_atak_native_fnc_zoneMap; }];
        _map ctrlAddEventHandler ["MouseMoving", { ["move", _this] call comspec_atak_native_fnc_zoneMap; }];
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
        uiNamespace setVariable ["COMSPEC_ATAK_ZnForm", [call _editRows, _formRect] call comspec_atak_native_fnc_formRender];
        (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) set ["backSub", ["ZONES", { ['#ui', ['view', 'LIST']] call comspec_atak_native_fnc_pageZones; }]];
    };
    case "ZONE": {
        _zone params ["_zid", "_zn", "_zp", "_lp", "_zt", "_zrm", "_zst", "_zstamp", "_zby", "_zreps", "_zb", ["_zr", []]];
        private _sr = ["state", _zst] call _ZA;
        private _draft = _ui get "draft";
        _rows pushBack ["buttons", [["◀ PLAN", ["view", "EDIT"] call _ui1], ["CITER DANS UN MESSAGE", ["cite"] call _ui1, false, true, "Copie « B-04 (OBJ BRAVO) grille · état » et l'ouvre dans Messages"], ["VOIR SUR LA CARTE", ["showMap"] call _ui1]]];
        _rows pushBack ["section", format [("Zone %1 · %2") call _tr, [_zn] call _esc, [_b get "name"] call _esc], [[_lp select 0, _lp select 1, 0], 8] call comspec_atak_native_fnc_gridRef];
        _rows pushBack ["info", "État", format ["<t color='%1'>%2</t>%3", [_sr select 4] call _hex, (_sr select 1) call _tr, ["", format [" · %1 · %2", [_zby] call _esc, [_zstamp] call _ago]] select (_zby isNotEqualTo "")]];
        _rows pushBack ["info", "Type", ((["type", _zt] call _ZA) select 1) call _tr];
        _rows pushBack ["info", "Surface", format ["%1 m²", round (abs (["area", [_zp]] call comspec_atak_native_fnc_zoneGeom))]];
        _rows pushBack ["info", "Depuis moi", format ["%1 · %2", [player distance2D [_lp select 0, _lp select 1, 0]] call _fmt, [player getDir [_lp select 0, _lp select 1, 0]] call _card]];
        _rows pushBack ["lineseg", "", "État de la zone", [_zid, _zst] call _stateSeg, "Un clic : l'état part à ceux qui ont le plan"];
        // Signaler.
        private _rp = _ui get "repPos";
        _rows pushBack ["section", "Signaler", ["Position : l'étiquette de la zone", format [("Position : %1") call _tr, [[_rp select 0, _rp select 1, 0], 8] call comspec_atak_native_fnc_gridRef]] select ((count _rp) >= 2)];
        _rows pushBack ["combo", "znNat", "Nature", (["natures"] call _ZA) apply { [(_x select 1) call _tr, _x select 0] }, _ui getOrDefault ["repNat", "UNK"]];
        _rows pushBack ["edit", "znSize", "Effectif (ex. 6, groupe, 2 véhicules)", _draft getOrDefault ["znSize", ""]];
        _rows pushBack ["memo", "znTxt", "Détails (activité, direction, armement)", _draft getOrDefault ["znTxt", ""], 2];
        _rows pushBack ["buttons", [
            ["SIGNALER UN ENNEMI", ["report", "ENY"] call _ui1, "red", true, "La zone passe « ennemi signalé » avec ce signalement"],
            ["AJOUTER UN SIGNALEMENT", ["report", ""] call _ui1, false, true, "Signalement noté, état de la zone inchangé"]
        ]];
        // Signalements, du plus récent au plus ancien.
        _rows pushBack ["section", format [("Signalements (%1)") call _tr, count _zreps], ""];
        private _rl = _zreps apply { [_x select 1, _x] };
        _rl sort false;
        {
            (_x select 1) params ["", "_t", "_hh", "_who", "_nat", "_size", "_txt"];
            _rows pushBack ["text", format ["<t font='COMSPEC_BarlowBold' color='#f2ab33'>%1</t>  %2%3%4<br/><t color='#a9b6b0' size='0.9'>%5 · %6</t>",
                _hh, ["natureName", _nat] call _ZA, ["", " · " + ([_size] call _esc)] select (_size isNotEqualTo ""), ["", "<br/>" + ([_txt] call _esc)] select (_txt isNotEqualTo ""), [_who] call _esc, [_t] call _ago], [0.06, 0.075, 0.07, 1]];
        } forEach _rl;
        if (_mineB) then {
            _rows pushBack ["section", "Zone", ""];
            _rows pushBack ["field", "znZName", "Nom", _draft getOrDefault ["znZName", _zn], "RENOMMER", ["zoneField", ["label", "znZName"]] call _ui1, false];
            _rows pushBack ["lineseg", "", "Type", (["types"] call _ZA) apply { [toUpper ((_x select 1) call _tr), ["zoneSet", ["type", _x select 0]] call _ui1, (_x select 0) isEqualTo _zt, _x select 2] }];
            _rows pushBack ["segment", "Rendu", [["COMME LE PLAN", ["zoneSet", ["rm", ""]] call _ui1, _zrm isEqualTo ""]] + ((["renders"] call _ZA) apply { [toUpper ((_x select 1) call _tr), ["zoneSet", ["rm", _x select 0]] call _ui1, (_x select 0) isEqualTo _zrm] })];
            private _cz = (_ui get "confirm") isEqualTo "zdel";
            _rows pushBack ["buttons", [
                [["NUMÉROTER LES BÂTIMENTS", "RENUMÉROTER"] select ((count _zb) > 0), ["bld"] call _ui1, false, (_b getOrDefault ["busy", ""]) isEqualTo "", "Du nord au sud puis d'ouest en est, avec les étages (99 au plus)"],
                [["SUPPRIMER LA ZONE", "CONFIRMER"] select _cz, ["zoneDel"] call _ui1, ["red", false] select !_cz]
            ]];
        };
        if ((count _zb) > 0) then {
            _rows pushBack ["section", format [("Bâtiments (%1)") call _tr, count _zb], ("Numéros affichés sur la carte au zoom rapproché") call _tr];
            private _lines = [];
            { _lines pushBack format ["<t font='COMSPEC_BarlowBold'>%1</t> %2", _forEachIndex + 1, format [("%1 ét.") call _tr, _x param [5, 1]]]; } forEach _zb;
            _rows pushBack ["text", _lines joinString "   "];
        };
        (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) set ["backSub", ["ZONES", { ['#ui', ['view', 'EDIT']] call comspec_atak_native_fnc_pageZones; }]];
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
        _rows pushBack ["switch", "Zones sur la carte ATAK", profileNamespace getVariable ["COMSPEC_ATAK_ZonesShow", true], ["show"] call _ui1, "Bascule de tous les plans sur la carte de l'app Carte", false, "znShow"];
        _rows pushBack ["buttons", [["+ NOUVEAU PLAN", ["new"] call _ui1, true], ["SIGNALER ICI", ["here"] call _ui1, false, true, "Ouvre la zone sous ma position pour signaler"]]];
        if (_tab isEqualTo "SIT") then {
            private _all = (_mL + _rL) select { _x get "vis" };
            private _cnt = createHashMap;
            private _hot = [];
            private _reps = [];
            // Tri par heure : un compteur unique en second, le tri ne compare jamais les plans (HashMap).
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
                    _rows pushBack ["person", "", format ["<t font='COMSPEC_BarlowBold' color='%1'>%2</t>  <t color='#a9b6b0'>%3</t><br/>%4%5",
                        [_sr select 4] call _hex, [_z select 1] call _esc, [_bb get "name"] call _esc, (_sr select 1) call _tr, ["", format [" · %1 · %2", [_z select 8] call _esc, [_t] call _ago]] select ((_z select 8) isNotEqualTo "")],
                        [["OUVRIR", ["openZone", [_bb get "id", _z select 0]] call _ui1, true]], _sr select 4, ((_z select 1) select [0, 2])];
                } forEach (_hot select [0, 20]);
            };
            _reps sort false;
            _rows pushBack ["section", "Derniers signalements", ["", format [("%1 au total") call _tr, count _reps]] select ((count _reps) > 0)];
            if ((count _reps) isEqualTo 0) then { _rows pushBack ["text", format ["<t color='#a9b6b0'>%1</t>", ("Aucun signalement. Un contact ennemi posé dans une zone (marqueur, TIC, SALUTE, compte rendu) y arrive tout seul.") call _tr]]; };
            {
                _x params ["_t", "", "_bb", "_z", "_r"];
                _r params ["", "", "_hh", "_who", "_nat", "_size", "_txt"];
                _rows pushBack ["person", "", format ["<t font='COMSPEC_BarlowBold' color='#f2ab33'>%1</t>  <t font='COMSPEC_BarlowBold'>%2</t> <t color='#a9b6b0'>(%3)</t><br/>%4%5%6 · <t color='#a9b6b0'>%7</t>",
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
                    ["Les plans partagés par votre équipe, votre groupe ou votre camp arrivent ici.", "Créez un plan : tracez la zone de travail, découpez-la en zones nommées, nommez les axes, puis partagez-le."] select (_tab isEqualTo "MINE")];
            };
            {
                private _x2 = _x select 2;
                private _zs = _x2 get "zones";
                private _nE = { (_x select 6) in ["ENY", "CONT"] } count _zs;
                private _tags = [];
                if ((_x2 get "scope") > 0) then { _tags pushBack ([format [("partagé : %1") call _tr, ["scopeName", _x2 get "scope"] call _ZA], format [("de %1 · %2") call _tr, [_x2 get "from"] call _esc, ["scopeName", _x2 get "scope"] call _ZA]] select (_tab isNotEqualTo "MINE")); };
                if (_x2 get "arma") then { _tags pushBack (("carte d'Arma") call _tr); };
                if !(_x2 get "vis") then { _tags pushBack (("masqué") call _tr); };
                private _txt = format ["<t font='COMSPEC_BarlowBold'>%1</t>  <t color='#a9b6b0'>%2 · %3</t>%4%5",
                    [_x2 get "name"] call _esc, format [("%1 zone(s)") call _tr, count _zs], format [("%1 axe(s)") call _tr, count (_x2 get "axes")],
                    ["", format ["<br/><t color='#e5483a'>%1</t>", format [("%1 zone(s) avec contact") call _tr, _nE]]] select (_nE > 0),
                    ["", format ["<br/><t color='#8fd19a'>%1</t>", _tags joinString " · "]] select ((count _tags) > 0)];
                _rows pushBack ["person", _icon, _txt, [
                    ["OUVRIR", ["open", _x2 get "id"] call _ui1, true],
                    [["AFFICHER", "MASQUER"] select (_x2 get "vis"), ["vis", _x2 get "id"] call _ui1, false, true, "Sur la carte ATAK"]
                ]];
            } forEach _list;
        };
    };
};
if (_view isNotEqualTo "EDIT") then { [_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender; };
true
