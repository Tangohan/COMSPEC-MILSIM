/*
    Console du poste TOC, refonte 4.9.0 (fn_tocLaptop) : ce que la rubrique SITUATION (onglet CARTE), la rubrique PLANS
    (onglets DESSIN et PLAN) et les saisies « au poste » ajoutent, sans téléphone :
      CARTE     calques (joueurs, IA, sans téléphone, véhicules, aéronefs, comptes rendus, plan et traits, relais,
                couverture), liste des unités du camp (téléphone ou source directe, fn_tocAppData), fiche de l'unité
                choisie (grille, vitesse, altitude, véhicule, santé, munitions, liaison) et ses actions : SUIVRE (la carte
                reste centrée), MESSAGE, ORDRE, TRACE (120 points, un toutes les 5 s) ;
      carte     boutons ZOOM − / ZOOM +, échelle et nord dessinés sur la carte, grille sous le curseur ;
      DESSIN    mode d'emploi des outils (fn_tocDraw, mêmes objets que l'ATAK) ;
      PLAN      éléments du plan du camp (fn_planServer) : centrer, supprimer, montrer sur les écrans, prévenir le camp ;
      FLUX      compte rendu du poste ; DEMANDES : demande inscrite au poste ; MAIN COURANTE : entrées rapides ;
      clavier   F1 à F4 rubriques, Page préc. / suiv. onglets, + et − du pavé numérique zoom, Ctrl+F recherche.
    Les contrôles sont créés par fn_tocLaptop (« load ») et posés par sa grille ; rien ne tourne console fermée.
    Aucune fenêtre du jeu : tout message va à la ligne d'état de la console.
    Modes : ["act", clé | [clé, argument]] | ["refresh", [onglet, nœuds]] | ["tick", [nœuds, compteurs, sans téléphone]]
            | ["mapDraw", carte] | ["zoom", sens] | ["key", événement KeyDown] -> vrai si la touche est prise
*/
params [["_mode", "refresh"], ["_a", []]];
disableSerialization;
private _fn = comspec_atak_native_fnc_tocApp;
private _lap = comspec_atak_native_fnc_tocLaptop;
private _st = uiNamespace getVariable ["COMSPEC_ATAK_TocLap", createHashMap];
private _ui = _st getOrDefault ["ui", createHashMap];
if ((count _ui) isEqualTo 0) exitWith { false };
private _draft = uiNamespace getVariable ["COMSPEC_ATAK_TocLapDraft", createHashMap];
private _side = str side group player;
private _ly = _st getOrDefault ["ly", createHashMap];
private _status = { ["status", _this] call comspec_atak_native_fnc_tocLaptop; };
private _tcol = { params ["_k", ["_al", 1]]; ["rgba", [_k, _al]] call comspec_atak_native_fnc_tocTheme };
private _ink = { ["ink", _this call comspec_atak_native_fnc_tr] call comspec_atak_native_fnc_tocTheme };
private _esc = { params ["_t"]; if !(_t isEqualType "") then { _t = str _t; }; { _t = [_t, _x select 0, _x select 1] call CBA_fnc_replace; } forEach [["&", "&amp;"], ["<", "&lt;"], [">", "&gt;"]]; _t };
private _nodesOf = { (["feed", _side] call comspec_atak_native_fnc_tocAppData) param [1, []] };
private _planOf = { (missionNamespace getVariable [format ["COMSPEC_ATAK_Plan_%1", side group player], [0, [], ""]]) };
// Texte structuré réécrit seulement s'il change.
private _setTxt = {
    params ["_key", "_txt"];
    private _c = _ui getOrDefault [_key, controlNull];
    if (isNull _c || {(_c getVariable ["COMSPEC_ATAK_Txt", ""]) isEqualTo _txt}) exitWith {};
    _c setVariable ["COMSPEC_ATAK_Txt", _txt];
    _c ctrlSetStructuredText parseText (_txt call _ink);
};
// Position d'une grille écrite, sinon le point désigné sur la carte (clic droit).
private _posOf = {
    params ["_grid"];
    private _gp = [_grid] call comspec_atak_native_fnc_gridParse;
    if ((count _gp) >= 3) exitWith { [(_gp select 0) select 0, (_gp select 0) select 1] };
    _draft getOrDefault ["pos", []]
};
switch (_mode) do {
    case "act": {
        private _k = _a;
        private _arg = "";
        if (_a isEqualType []) then { _k = _a select 0; _arg = _a param [1, ""]; };
        // Sélection posée par la reconstruction d'une liste : rien à faire.
        if (_k in ["pickU", "pickP"] && {_st getOrDefault ["mute", false]}) exitWith {};
        private _selU = _st getOrDefault ["selU", ""];
        private _nodes = call _nodesOf;
        private _node = _nodes param [_nodes findIf { (_x select 0) isEqualTo _selU }, []];
        switch (true) do {
            case ((_k select [0, 2]) isEqualTo "ly"): { private _l = _k select [2]; _ly set [_l, !(_ly getOrDefault [_l, true])]; };
            case (_k isEqualTo "pickU"): {
                _st set ["selU", _arg];
                private _n = _nodes param [_nodes findIf { (_x select 0) isEqualTo _arg }, []];
                if ((count _n) > 7) then { _st set ["selG", _n select 7]; };
                ["frame", "sel"] call _lap;
            };
            case (_k in ["uFollow", "uTrack", "uMsg", "uOrder"] && {(count _node) isEqualTo 0}): { "Choisissez d'abord une unité sur la carte ou dans la liste" call _status; };
            case (_k isEqualTo "uFollow"): {
                private _on = (_st getOrDefault ["follow", ""]) isEqualTo _selU;
                _st set ["follow", [_selU, ""] select _on];
                (format [["La carte suit %1", "Suivi de %1 arrêté"] select _on, _node select 1]) call _status;
            };
            case (_k isEqualTo "uTrack"): {
                private _on = ((_st getOrDefault ["track", ["", []]]) select 0) isEqualTo _selU;
                _st set ["track", [[_selU, [+(_node select 2)]], ["", []]] select _on];
                _st set ["trackAt", diag_tickTime];
                (format [["Trace de %1 : un point toutes les 5 s", "Trace de %1 effacée"] select _on, _node select 1]) call _status;
            };
            case (_k isEqualTo "uMsg"): {
                private _u = objectFromNetId _selU;
                // Véhicule : le joueur chef de bord, sinon le premier joueur à bord.
                private _pl = (crew (vehicle _u)) select { isPlayer _x && {_x isNotEqualTo player} };
                if ((count _pl) isEqualTo 0) exitWith { "Aucun autre joueur dans cette unité : envoyez un ordre à son groupe" call _status; };
                _draft set ["dest", getPlayerUID (_pl select 0)];
                ["keep"] call _lap;
                ["tab", "CAMP"] call _lap;
            };
            case (_k isEqualTo "uOrder"): {
                private _u = objectFromNetId _selU;
                if (isNull _u) exitWith { "Cette unité n'est plus connue du poste" call _status; };
                ["keep"] call _lap;
                _draft set ["target", format ["group|%1|%2", [leader group _u] call comspec_atak_native_fnc_unitGroup, groupId group _u]];
                ["tab", "ORDRES"] call _lap;
            };
            case (_k isEqualTo "fAdd"): {
                // Compte rendu du poste : même événement de camp que ceux des téléphones (flux, main courante, écrans),
                // envoyé par le réseau filaire du poste (ni signal ni file d'envoi).
                private _t = trim (ctrlText (_ui get "fText"));
                if (_t isEqualTo "") exitWith { "Écrivez le texte du compte rendu" call _status; };
                private _kc = _ui get "fKindNew";
                private _kind = _kc lbData ((lbCurSel _kc) max 0);
                private _grid = trim (ctrlText (_ui get "fGrid"));
                private _p = [_grid] call _posOf;
                if (_grid isEqualTo "" && {(count _p) >= 2}) then { _grid = [[_p select 0, _p select 1, 0], 8] call comspec_atak_native_fnc_gridRef; };
                private _cs = ["callsign"] call comspec_atak_native_fnc_tocPost;
                private _r = createHashMapFromArray [
                    ["id", format ["CR%1%2", floor (random 9000) + 1000, floor (diag_tickTime * 10) mod 1000]],
                    ["code", _kind], ["label", _kind], ["tag", _kind], ["web_type", ""], ["prio", ["ROUTINE", "URGENT"] select (_kind isEqualTo "CONTACT")], ["dest", "CAMP"],
                    ["by", format ["TOC %1", _cs]], ["uid", getPlayerUID player], ["time", [dayTime, "HH:MM"] call BIS_fnc_timeToString], ["grid", _grid],
                    ["pos", [[], [_p select 0, _p select 1, 0]] select ((count _p) >= 2)], ["summary", format ["%1 · %2", _kind, _t select [0, 200]]],
                    ["lines", [["text", "Texte", _t select [0, 200]]]], ["grp", netId group player], ["web", ""], ["jnet", ""], ["photo", ""]
                ];
                ["comspec_atak_native_sideEvent", ["comspec_atak_native_report", [_r, _side], _side]] call CBA_fnc_serverEvent;
                (_ui get "fText") ctrlSetText (("") call comspec_atak_native_fnc_tr);
                (format ["Compte rendu %1 envoyé au camp", _kind]) call _status;
            };
            case (_k isEqualTo "qAdd"): {
                private _t = trim (ctrlText (_ui get "qText"));
                if (_t isEqualTo "") exitWith { "Écrivez le détail de la demande" call _status; };
                private _kc = _ui get "qKind";
                private _kind = _kc lbData ((lbCurSel _kc) max 0);
                private _grid = trim (ctrlText (_ui get "qGrid"));
                private _p = [_grid] call _posOf;
                ["send", ["reqAdd", [_kind, ["me"] call comspec_atak_native_fnc_tocRoom, getPlayerUID player, _grid, _t, _p]]] call comspec_atak_native_fnc_tocRoom;
                (_ui get "qText") ctrlSetText (("") call comspec_atak_native_fnc_tr);
                (format ["Demande %1 inscrite au nom du poste", _kind]) call _status;
            };
            case (_k in ["lQ1", "lQ2", "lQ3", "lQ4"]): {
                private _w = ["RAS", "CONTACT", "EN POSITION", "RELÈVE"] select ((parseNumber (_k select [2])) - 1);
                private _t = trim (ctrlText (_ui get "lText"));
                ["send", ["log", ["NOTE", [_w, format ["%1 : %2", _w, _t]] select (_t isNotEqualTo "")]]] call comspec_atak_native_fnc_tocRoom;
                (_ui get "lText") ctrlSetText (("") call comspec_atak_native_fnc_tr);
                (format ["Entrée %1 inscrite à la main courante", _w]) call _status;
            };
            case (_k isEqualTo "pickP"): { _st set ["selP", _arg]; };
            case (_k in ["pCenter", "pDel"]): {
                private _id = _st getOrDefault ["selP", ""];
                private _els = (call _planOf) param [1, []];
                private _el = _els param [_els findIf { (_x select 0) isEqualTo _id }, []];
                if ((count _el) < 3) exitWith { "Choisissez d'abord un élément du plan dans la liste" call _status; };
                if (_k isEqualTo "pCenter") exitWith {
                    private _pt = (_el select 2) param [0, []];
                    if ((count _pt) >= 2) then { ["frame", [_pt select 0, _pt select 1]] call _lap; };
                };
                ["comspec_atak_native_planSrv", [player, "del", [_id], format ["TOC%1", floor (diag_tickTime * 10)], ""]] call CBA_fnc_serverEvent;
                _st set ["selP", ""];
                "Élément du plan supprimé pour tous" call _status;
            };
            case (_k isEqualTo "pShow"): {
                private _scr = ["screens"] call comspec_atak_native_fnc_tocWall;
                if ((count _scr) isEqualTo 0) exitWith { "Aucun écran dans la salle de ce poste ni à moins de 30 m" call _status; };
                { ["show", [_x, "MAP"]] call comspec_atak_native_fnc_tocScreen; } forEach _scr;
                (format ["%1 écran(s) en carte seule : le plan y est dessiné", count _scr]) call _status;
            };
            case (_k isEqualTo "pTell"): {
                private _n = count ((call _planOf) param [1, []]);
                private _ev = [["callsign"] call comspec_atak_native_fnc_tocPost, format ["Plan du camp mis à jour : %1 élément(s). Ouvrez l'app Plan ou regardez la carte.", _n], [dayTime, "HH:MM"] call BIS_fnc_timeToString, "ROUTINE", _side,
                    format ["T-%1-%2", getPlayerUID player, floor (serverTime * 10)], getPlayerUID player, ""];
                ["comspec_atak_native_sideEvent", ["comspec_atak_native_tocMsg", _ev, _side]] call CBA_fnc_serverEvent;
                "Message « plan mis à jour » envoyé au camp" call _status;
            };
        };
        _st set ["sig", ""];
        _st set ["uSig", []];
        ["refresh"] call _lap;
    };
    case "zoom": {
        private _map = _ui getOrDefault ["map", controlNull];
        if (isNull _map || {!ctrlShown _map}) exitWith { false };
        (ctrlPosition _map) params ["_mx", "_my", "_mw", "_mh"];
        private _ctr = _map ctrlMapScreenToWorld [_mx + _mw / 2, _my + _mh / 2];
        private _sc = (((ctrlMapScale _map) * ([0.6, 1 / 0.6] select (_a < 0))) max 0.002) min 1;
        _map ctrlMapAnimAdd [0.2, _sc, [_ctr select 0, _ctr select 1, 0]];
        ctrlMapAnimCommit _map;
        true
    };
    case "key": {
        _a params ["_d", "_dik", "", ["_ctrlKey", false]];
        private _typing = (ctrlType (focusedCtrl _d)) isEqualTo 2;
        switch (true) do {
            case (_dik in [59, 60, 61, 62]): { ["section", ["SITUATION", "CONDUITE", "PLANS", "SALLE"] select (_dik - 59)] call _lap; true };
            case (_dik in [201, 209] && {!_typing}): { ["tabStep", [-1, 1] select (_dik isEqualTo 209)] call _lap; true };
            case (_dik in [78, 74] && {!_typing}): { ["zoom", [1, -1] select (_dik isEqualTo 74)] call _fn; true };
            case (_ctrlKey && {_dik isEqualTo 33}): { private _s = _ui getOrDefault ["search", controlNull]; if (ctrlShown _s) then { ctrlSetFocus _s; }; true };
            default { false };
        }
    };
    case "tick": {
        _a params [["_nodes", []], ["_cnt", [0, 0, 0, 0]], ["_nDir", 0]];
        private _map = _ui getOrDefault ["map", controlNull];
        // SUIVRE : la carte reste centrée sur l'unité (échelle gardée).
        private _fk = _st getOrDefault ["follow", ""];
        if (_fk isNotEqualTo "" && {ctrlShown _map}) then {
            private _n = _nodes param [_nodes findIf { (_x select 0) isEqualTo _fk }, []];
            if ((count _n) > 2) then {
                _map ctrlMapAnimAdd [0.3, ctrlMapScale _map, [(_n select 2) select 0, (_n select 2) select 1, 0]];
                ctrlMapAnimCommit _map;
            } else { _st set ["follow", ""]; };
        };
        // TRACE : un point toutes les 5 s, 120 au plus.
        (_st getOrDefault ["track", ["", []]]) params ["_tk", "_pts"];
        if (_tk isNotEqualTo "" && {diag_tickTime - (_st getOrDefault ["trackAt", 0]) >= 5}) then {
            _st set ["trackAt", diag_tickTime];
            private _n = _nodes param [_nodes findIf { (_x select 0) isEqualTo _tk }, []];
            if ((count _n) > 2 && {((_pts param [(count _pts) - 1, [0, 0]]) distance2D (_n select 2)) > 5}) then {
                _pts pushBack +(_n select 2);
                if ((count _pts) > 120) then { _pts deleteAt 0; };
            };
        };
        // Bas du rail : unités connues du poste, raccourcis.
        private _nPh = (_cnt select 0) + (_cnt select 1) + (_cnt select 2) + (_cnt select 3);
        ["railInfo", format ["<t size='1.3' font='EtelkaMonospaceProBold' color='@text'>%1</t><br/><t size='0.75' color='@text2'>unités du camp</t><br/><t size='0.75' color='@accent'>%2 au téléphone</t><br/><t size='0.75' color='@text2'>%3 sans téléphone</t><br/><br/><t size='0.7' color='@muted'>F1 à F4 : rubriques<br/>Page préc. / suiv. : onglets<br/>+ et − : zoom<br/>Ctrl+F : chercher<br/>Échap : fermer</t>",
            _nPh + _nDir, _nPh, _nDir]] call _setTxt;
    };
    case "refresh": {
        _a params [["_tab", ""], ["_nodes", []]];
        switch (_tab) do {
            case "CARTE": {
                // Calques : pastille pleine = affiché ; libellé remesuré seulement s'il change.
                {
                    _x params ["_lk", "_long", "_short"];
                    private _on = _ly getOrDefault [_lk, true];
                    private _dot = ["○ ", "● "] select _on;
                    ["label", ["ly" + _lk, _dot + (_long call comspec_atak_native_fnc_tr), ["", _dot + (_short call comspec_atak_native_fnc_tr)] select (_short isNotEqualTo ""), true]] call _lap; // i18n:brut (déjà traduit)
                    (_ui get ("ly" + _lk)) ctrlSetTextColor ([(["muted"] call _tcol), (["accent"] call _tcol)] select _on);
                } forEach [["PL", "JOUEURS", ""], ["AI", "IA", ""], ["DIR", "SANS TÉLÉPHONE", "SANS TÉL."], ["VEH", "VÉHICULES", "VÉHIC."], ["AIR", "AÉRONEFS", "AÉRO."],
                    ["EV", "COMPTES RENDUS", "C.R."], ["PLAN", "PLAN ET TRAITS", "PLAN"], ["REL", "RELAIS", ""], ["COV", "COUVERTURE", "COUV."]];
                // Unités du camp : reconstruite seulement quand la liste ou un état change.
                private _lb = _ui get "uList";
                private _selU = _st getOrDefault ["selU", ""];
                private _sig = _nodes apply { [_x select 0, _x select 1, _x select 4, _x param [9, "P"]] };
                if ((_st getOrDefault ["uSig", []]) isNotEqualTo _sig || {(lbSize _lb) isEqualTo 0}) then {
                    _st set ["uSig", _sig];
                    _st set ["mute", true];
                    lbClear _lb;
                    {
                        _x params ["_nk", "_cs", "", "", "_fr", "_kind", "_ai", ["_grp", ""], ["_occ", 1], ["_src", "P"]];
                        (["label", [_fr, _src]] call comspec_atak_native_fnc_tocAppData) params ["_sl", "_tok"];
                        private _i = _lb lbAdd ((format ["%1 · %2%3", _cs, _grp, ["", format [" · %1 à bord", _occ]] select (_occ > 1)]) call comspec_atak_native_fnc_tr);
                        _lb lbSetData [_i, _nk];
                        _lb lbSetTextRight [_i, _sl call comspec_atak_native_fnc_tr];
                        _lb lbSetColor [_i, [_tok] call _tcol];
                        _lb lbSetColorRight [_i, [_tok] call _tcol];
                        if (_nk isEqualTo _selU) then { _lb lbSetCurSel _i; };
                    } forEach _nodes;
                    if ((count _nodes) isEqualTo 0) then { _lb lbAdd (("Aucune unité du camp connue du poste pour l'instant") call comspec_atak_native_fnc_tr); };
                    _st set ["mute", false];
                };
                // Fiche de l'unité choisie.
                private _node = _nodes param [_nodes findIf { (_x select 0) isEqualTo _selU }, []];
                private _has = (count _node) > 0;
                if (!_has) then {
                    ["uInfo", "<t size='0.85' color='@muted'>Cliquez une unité sur la carte ou dans la liste : sa fiche s'affiche ici (grille, vitesse, santé, munitions).<br/>SUIVRE garde la carte centrée sur elle, TRACE dessine son chemin, MESSAGE et ORDRE ouvrent la rédaction.</t>"] call _setTxt;
                } else {
                    private _i = ["unit", _selU] call comspec_atak_native_fnc_tocAppData;
                    _node params ["", "_cs", "_np", "", "_fr", "", "", ["_grp", ""], "", ["_src", "P"]];
                    (["label", [_fr, _src]] call comspec_atak_native_fnc_tocAppData) params ["_sl", "_tok"];
                    if ((count _i) isEqualTo 0) then {
                        ["uInfo", format ["<t size='1.05' font='COMSPEC_BarlowBold'>%1</t><br/><t size='0.85' color='@amber'>Cette unité n'est plus connue du poste.</t>", [_cs] call _esc]] call _setTxt;
                    } else {
                        // Unité au téléphone : grille du dernier point reçu (le brouillage garde son effet) ; vitesse et altitude
                        // seulement en direct. Unité sans téléphone : valeurs du jeu.
                        private _live = _src isEqualTo "D" || {_fr isEqualTo 0};
                        private _grid = [[[_np select 0, _np select 1, 0], 8] call comspec_atak_native_fnc_gridRef, _i get "grid"] select (_src isEqualTo "D");
                        (_i get "health") params ["_hl", "_ht"];
                        ["uInfo", format ["<t size='1.05' font='COMSPEC_BarlowBold'>%1</t> <t size='0.85' color='@muted'>%2 · groupe %3</t><br/><t size='0.9'>Grille <t font='EtelkaMonospacePro'>%4</t>%5%6<br/>Santé : <t color='@%7'>%8</t> · %9<br/>Liaison : <t color='@%10'>%11</t></t>",
                            [_cs] call _esc, [_i get "type"] call _esc, [_grp] call _esc, _grid,
                            ["", format [" · %1 km/h · hauteur %2 m", _i get "speed", _i get "alt"]] select _live,
                            ["", format ["<br/>À bord : %1 (%2 à bord)", [_i get "veh"] call _esc, _i get "crew"]] select ((_i get "veh") isNotEqualTo ""),
                            _ht, _hl, [format ["%1 chargeur(s) de rechange", _i get "ammo"], "sans arme principale"] select ((_i get "ammo") < 0), _tok, _sl]] call _setTxt;
                    };
                };
                private _fol = _has && {(_st getOrDefault ["follow", ""]) isEqualTo _selU};
                private _trk = _has && {((_st getOrDefault ["track", ["", []]]) select 0) isEqualTo _selU};
                ["label", [["uFollow", "SUIVRE", ""], ["uFollow", "NE PLUS SUIVRE", "ARRÊTER"]] select _fol] call _lap;
                ["label", [["uTrack", "TRACE", ""], ["uTrack", "EFFACER LA TRACE", "EFFACER"]] select _trk] call _lap;
                { (_ui get _x) ctrlEnable _has; (_ui get _x) setVariable ["why", "Choisissez d'abord une unité sur la carte ou dans la liste"]; } forEach ["uFollow", "uMsg", "uOrder", "uTrack"];
            };
            case "DESSIN": {
                ["pHow", "<t size='0.9' color='@text2'>Les outils sont sous la carte ; leurs réglages apparaissent sur la ligne du dessous.</t><br/><br/><t size='0.9'><t font='COMSPEC_BarlowBold'>MARQUEUR</t> : un symbole avec son nom et sa durée. Exemple : CP NORD, 30 min.<br/><t font='COMSPEC_BarlowBold'>TRAIT</t> : clic sur A, Ctrl+clic pour chaque étape, clic sur B. ROUTES suit les routes, FLÈCHE met une pointe.<br/><t font='COMSPEC_BarlowBold'>DESSIN</t> : clic gauche maintenu, à main levée. FERMÉ referme la forme en zone.<br/><t font='COMSPEC_BarlowBold'>PLAN</t> : zones, lignes de phase, limites, axes et points du plan. Exemple : PL BLUE, OBJ RAVEN. Clic droit ou TERMINER pour poser.<br/><t font='COMSPEC_BarlowBold'>EFFACER</t> : clic sur un marqueur, un trait ou un élément du plan. ANNULER retire mon dernier geste.</t><br/><br/><t size='0.85' color='@muted'>Tout ce qui est dessiné ici est partagé en direct : cartes des joueurs, écrans de la salle en carte, console web. Aucun téléphone n'est nécessaire.</t>"] call _setTxt;
            };
            case "PLAN": {
                (call _planOf) params [["_rev", 0], ["_els", []], ["_name", ""]];
                private _cat = [] call comspec_atak_native_fnc_planCatalog;
                private _lb = _ui get "pList";
                private _sel = _st getOrDefault ["selP", ""];
                private _sig = [_rev, count _els];
                if ((_lb getVariable ["COMSPEC_ATAK_Sig", []]) isNotEqualTo _sig || {(lbSize _lb) isEqualTo 0}) then {
                    _lb setVariable ["COMSPEC_ATAK_Sig", _sig];
                    _st set ["mute", true];
                    lbClear _lb;
                    {
                        _x params [["_id", ""], ["_kind", ""], ["_pts", []], ["_text", ""], "", "", "", "", ["_num", 0], ["_author", ""]];
                        private _kl = ((_cat get "kinds") getOrDefault [_kind, []]) param [2, _kind];
                        private _i = _lb lbAdd ((format ["%1 · %2", _kl, [_text, "sans nom"] select (_text isEqualTo "")]) call comspec_atak_native_fnc_tr);
                        _lb lbSetData [_i, _id];
                        _lb lbSetTextRight [_i, _author];
                        if (_id isEqualTo _sel) then { _lb lbSetCurSel _i; };
                    } forEach _els;
                    if ((count _els) isEqualTo 0) then { _lb lbAdd (("Plan vide : dessinez dans l'onglet DESSIN, outil PLAN") call comspec_atak_native_fnc_tr); };
                    _st set ["mute", false];
                };
                private _el = _els param [_els findIf { (_x select 0) isEqualTo _sel }, []];
                if ((count _el) < 3) then {
                    ["pInfo", format ["<t size='0.85' color='@muted'>Le plan du camp est commun aux joueurs (app Plan), aux écrans de la salle en carte et à la console web : un plan envoyé du site arrive ici tout seul.<br/>%1 élément(s)%2.</t>", count _els, ["", " · " + ([_name] call _esc)] select (_name isNotEqualTo "")]] call _setTxt;
                } else {
                    _el params ["", ["_kind", ""], ["_pts", []], ["_text", ""], "", "", "", "", "", ["_author", ""], ["_desc", ""]];
                    private _pt = _pts param [0, [0, 0]];
                    ["pInfo", format ["<t size='1.05' font='COMSPEC_BarlowBold'>%1</t> <t size='0.85' color='@muted'>%2</t><br/><t size='0.9'>%3 point(s) · grille %4 · posé par %5%6</t>",
                        [[_text, "sans nom"] select (_text isEqualTo "")] call _esc, [((_cat get "kinds") getOrDefault [_kind, []]) param [2, _kind]] call _esc, count _pts,
                        [[_pt select 0, _pt select 1, 0], 6] call comspec_atak_native_fnc_gridRef, [[_author, "le site"] select (_author isEqualTo "")] call _esc,
                        ["", "<br/>" + ([_desc] call _esc)] select (_desc isNotEqualTo "")]] call _setTxt;
                };
                { (_ui get _x) ctrlEnable ((count _el) >= 3); (_ui get _x) setVariable ["why", "Choisissez d'abord un élément du plan dans la liste"]; } forEach ["pCenter", "pDel"];
            };
        };
    };
    case "mapDraw": {
        private _map = _a;
        private _ts = 0.03 * (safeZoneH / 1.2);
        private _clear = "#(argb,8,8,3)color(0,0,0,0)";
        // Relais du réseau et portée (calques RELAIS et COUVERTURE).
        if (_ly getOrDefault ["REL", false] || {_ly getOrDefault ["COV", false]}) then {
            {
                if (_x isEqualType objNull && {!isNull _x} && {alive _x} && {_x getVariable ["COMSPEC_AtakRelayOn", true]}) then {
                    private _p = getPosASL _x;
                    private _r = _x getVariable ["COMSPEC_AtakRelayRange", 0];
                    if (_ly getOrDefault ["COV", false] && {_r > 0}) then { _map drawEllipse [_p, _r, _r, 0, (["amber", 0.7] call _tcol), ""]; };
                    if (_ly getOrDefault ["REL", false]) then { _map drawIcon ["\A3\ui_f\data\map\markers\military\triangle_CA.paa", (["amber"] call _tcol), _p, 20, 20, 0, "RELAIS", 2, _ts * 0.85, "RobotoCondensedBold", "right"]; };
                };
            } forEach (missionNamespace getVariable ["COMSPEC_AtakRelays", []]);
        };
        // Trace de l'unité suivie à la trace.
        (_st getOrDefault ["track", ["", []]]) params ["_tk", "_pts"];
        if (_tk isNotEqualTo "" && {(count _pts) > 1}) then {
            private _col = ["accent"] call _tcol;
            for "_i" from 1 to ((count _pts) - 1) do {
                _map drawLine [[(_pts select (_i - 1)) select 0, (_pts select (_i - 1)) select 1, 0], [(_pts select _i) select 0, (_pts select _i) select 1, 0], _col];
            };
        };
        // Échelle (bas gauche) et nord (haut droite) : la carte du jeu n'en montre pas dans une fenêtre.
        (ctrlPosition _map) params ["_mx", "_my", "_mw", "_mh"];
        private _sy = _my + _mh - 0.03 * safeZoneH;
        private _w0 = _map ctrlMapScreenToWorld [_mx + _mw * 0.02, _sy];
        private _w1 = _map ctrlMapScreenToWorld [_mx + _mw * 0.22, _sy];
        private _upW = _map ctrlMapScreenToWorld [_mx + _mw * 0.02, _sy - 0.004 * safeZoneH];
        private _span = ((_w1 select 0) - (_w0 select 0)) max 1;
        private _len = 10;
        { if (_x <= _span) then { _len = _x; }; } forEach [10, 20, 50, 100, 200, 500, 1000, 2000, 5000, 10000, 20000, 50000];
        private _th = (abs ((_upW select 1) - (_w0 select 1))) max 0.01;
        private _white = "#(rgb,8,8,3)color(1,1,1,1)";
        private _dark = "#(rgb,8,8,3)color(0.027,0.039,0.051,1)";
        _map drawRectangle [[(_w0 select 0) + _span * 0.62, _w0 select 1, 0], _span * 0.68, _th * 4.5, 0, (["bg0", 0.8] call _tcol), _dark];
        _map drawRectangle [[(_w0 select 0) + _len / 2, _w0 select 1, 0], _len / 2, _th / 2, 0, [1, 1, 1, 1], _white];
        _map drawRectangle [[_w0 select 0, _w0 select 1, 0], _th / 2, _th * 2, 0, [1, 1, 1, 1], _white];
        _map drawRectangle [[(_w0 select 0) + _len, _w0 select 1, 0], _th / 2, _th * 2, 0, [1, 1, 1, 1], _white];
        _map drawIcon [_clear, [1, 1, 1, 1], [(_w0 select 0) + _len, _w0 select 1, 0], 10, 10, 0, [format ["%1 m", _len], format ["%1 km", _len / 1000]] select (_len >= 1000), 2, _ts, "EtelkaMonospaceProBold", "right"];
        private _nw = _map ctrlMapScreenToWorld [_mx + _mw - 0.045 * safeZoneH * pixelW / pixelH, _my + 0.035 * safeZoneH];
        _map drawIcon ["\A3\ui_f\data\map\markers\military\triangle_CA.paa", [1, 1, 1, 1], [_nw select 0, _nw select 1, 0], 22, 22, 0, "N", 2, _ts, "RobotoCondensedBold", "right"];
        // Ligne sous la carte : grille sous le curseur et largeur montrée, 5 fois par seconde au plus.
        if (diag_tickTime - (_st getOrDefault ["mapInfoAt", 0]) >= 0.2) then {
            _st set ["mapInfoAt", diag_tickTime];
            private _cur = _st getOrDefault ["cursorW", []];
            private _edgeL = _map ctrlMapScreenToWorld [_mx, _my + _mh / 2];
            private _edgeR = _map ctrlMapScreenToWorld [_mx + _mw, _my + _mh / 2];
            private _wide = (_edgeR select 0) - (_edgeL select 0);
            ["mapInfo", format ["<t size='0.8' color='@text2'>Curseur <t font='EtelkaMonospacePro' color='@text'>%1</t> · carte large de %2 · <t color='@muted'>clic droit : point désigné</t></t>",
                if ((count _cur) >= 2) then { [[_cur select 0, _cur select 1, 0], 8] call comspec_atak_native_fnc_gridRef } else { "--- ---" },
                [format ["%1 m", round _wide], format ["%1 km", (round (_wide / 100)) / 10]] select (_wide >= 1000)]] call _setTxt;
        };
    };
    default { false };
};
