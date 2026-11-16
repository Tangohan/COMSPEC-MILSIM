/*
    Application SSE : une seule tuile « SSE » (class SseHub, page SSEHUB, fn_pageSseHub) pour toutes les apps SSE du téléphone.
    Retour de jeu : seize tuiles dans RENSEIGNEMENT, plusieurs avec la même icône et des noms proches (« SSE », « SSE terrain »,
    « Fiches SSE », « Fichiers ») : on ne savait pas laquelle ouvrir. Puis : « une vraie application SSE qui regroupe tout ».
    Accueil de l'app (SSEHUB) : recherche d'une fonction, compteurs (avis en vigueur, envois en échec), sections repliables
    (Contrôle et identités, Terrain et analyse, Avis et recherches), une ligne par fonction avec sa pastille.
    Chaque fonction est une app SSE d'origine, rouverte telle quelle par sa page habituelle : le code des sous-écrans n'est pas
    touché. Une page SSE ouverte (tuile, notification, raccourci ACE, lien profond, touche, ancienne épingle) s'affiche dans la
    coque de l'app : barre « SOMMAIRE + fonctions de la section » au-dessus de la page (fn_layoutGet réserve la place, fn_pageRender
    dessine la barre), titre « SSE · fonction ».
    Rien n'est fusionné ni renommé : chaque app garde sa classe et sa page (COMSPEC_ATAK_Apps de l'ATAK ou du pack SSE), donc
    les touches, les groupes du joueur, les notifications et les liens entre apps (fn_navigate, fn_openApp) les ouvrent comme avant.
    Seule la racine des sections du lanceur change (fn_launcherRender) et la vue regroupée (fn_appSpaces). Les épingles et les
    récentes d'une ancienne tuile SSE sont remplacées une fois par l'app SSE (action « migrate »).
    L'app ne montre que les fonctions présentes et visibles (fn_appVisible : droits, installation, interdiction, Athena, mode
    Essentiel) : sans le pack SSE il reste les apps de l'ATAK ; sans aucune fonction visible la tuile disparaît. Rien ne tourne
    en continu : tout est lu à l'ouverture de l'accueil ou au clic.
    Params : [action, argument]
      ["ids"]                 -> classes regroupées, dans l'ordre de la table
      ["apps"]                -> [[app (fn_appList), nom dans l'app, description]...] des apps visibles
      ["count"]               -> nombre d'apps visibles (fn_appVisible : la tuile demande au moins 1)
      ["badge"]               -> somme des pastilles des apps (fn_appBadge)
      ["rootHidden", apps]    -> classes à ne pas répéter à la racine quand l'app fait partie de cette liste d'apps, [] sinon
      ["pages"]               -> pages des fonctions (mêmes que celles de fn_layoutGet et fn_pageRender)
      ["hosted", page]        -> la page est une fonction de l'app SSE
      ["sections"]            -> [[id, titre, [[app, nom, description]...]]...] des sections ayant une fonction visible
      ["strip", page]         -> lignes (fn_formRender) de la barre de la coque pour cette page
      ["drawStrip", page]     -> dessine la barre dans la place réservée (fn_layoutGet « sseStrip »)
      ["goto", page]          -> change de fonction sans empiler l'historique ; ["home"] -> accueil de l'app
      ["search", texte]       -> filtre de l'accueil ; ["fold", section] -> replie ou déplie une section
      ["counters"]            -> [avis en vigueur, envois en échec] lus dans ce que l'app Avis et vigilance garde déjà
      ["migrate"]             -> épingles et récentes d'une ancienne tuile SSE remplacées par l'app SSE (une fois par partie)
      ["recent", page]        -> page retenue dans les apps récentes (l'app SSE pour une de ses fonctions)
      ["icon", [classe, icône déclarée]] -> icône de l'app : celle déclarée, sauf pour les apps du pack SSE qui déclarent une
                                 icône déjà portée par une app de l'ATAK (fn_appList)
*/
params [["_act", "apps"], ["_arg", ""]];
private _dir = "\z\comspec_atak_native\addons\main\data\";
// Icônes propres (déclarées dans le pack SSE avec celle de « FRS / FRM » et celle de « SSE »).
if (_act isEqualTo "icon") exitWith {
    _arg params [["_id", ""], ["_icon", ""]];
    switch (_id) do {
        case "Fichiers": { _dir + "ui_folder.paa" };
        case "SseField": { _dir + "ui_hand.paa" };
        default { _icon };
    }
};
// Table unique de l'app : [classe, nom affiché dans l'app, une ligne qui dit à quoi sert la fonction].
private _table = [
    ["SseField", "Sur le terrain", "Exploiter la personne ou l'objet devant vous, scellés et journal"],
    ["IdCheck", "Contrôle ID", "Contrôler les papiers d'une personne"],
    ["Fichiers", "Fichiers", "Interroger les fichiers sur une personne, un document ou un véhicule"],
    ["Wanted", "Recherchés", "Avis de recherche et personnes surveillées"],
    ["Fiches", "Fiches", "Fiches du portail avec leurs motifs et les mesures à prendre"],
    ["SseAlert", "Avis et vigilance", "Alertes en cours, à consulter, émettre ou signaler"],
    ["DataLink", "Extraction", "Exploiter un appareil saisi au câble DL-7"],
    ["Sse", "Console d'analyse", "Dossier en cours, sujet en vue, concordances et liens"],
    ["IdCover", "Ma couverture", "Mes papiers et mon identité de couverture"]
];
// Sections de l'accueil : [id, titre, classes de la section dans l'ordre]. Chaque classe est dans la table ci-dessus.
private _sectionsDef = [
    ["control", "Contrôle et identités", ["IdCheck", "Fichiers", "IdCover"]],
    ["field", "Terrain et analyse", ["SseField", "Sse", "DataLink"]],
    ["notices", "Avis et recherches", ["SseAlert", "Wanted", "Fiches"]]
];
// Pages des fonctions : aucune n'est renommée. Même liste dans fn_layoutGet (place de la barre) et fn_pageRender (titre).
// Libellés courts des onglets de la barre de la coque (cinq au plus par section : la barre tient sur le petit écran).
private _tabName = createHashMapFromArray [["SseField", "Terrain"], ["IdCheck", "Contrôle ID"], ["Fichiers", "Fichiers"], ["IdCover", "Couverture"], ["Wanted", "Recherchés"], ["Fiches", "Fiches"], ["SseAlert", "Avis"], ["DataLink", "Extraction"], ["Sse", "Analyse"]];
private _pages = ["SSE", "WANTED", "FICHES", "SSEALERT", "SSEFIELD", "IDCHECK", "FICHIERS", "IDCOVER", "DATALINK"];
private _ids = _table apply { _x select 0 };
private _visible = {
    private _byId = createHashMap;
    {
        if ((_x get "id") in _ids && {!(_x getOrDefault ["wip", false])} && {[_x] call comspec_atak_native_fnc_appVisible}) then { _byId set [_x get "id", _x]; };
    } forEach ([] call comspec_atak_native_fnc_appList);
    (_table select { (_x select 0) in _byId }) apply { [_byId get (_x select 0), _x select 1, _x select 2] }
};
switch (_act) do {
    case "ids": { _ids };
    case "apps": { call _visible };
    case "count": { count (call _visible) };
    case "badge": {
        private _n = 0;
        { _n = _n + ([(_x select 0) get "page"] call comspec_atak_native_fnc_appBadge); } forEach (call _visible);
        _n
    };
    case "rootHidden": {
        if !(_arg isEqualType []) exitWith { [] };
        [[], _ids] select ((_arg findIf { _x isEqualType createHashMap && {(_x getOrDefault ["id", ""]) isEqualTo "SseHub"} }) >= 0)
    };
    case "pages": { _pages };
    case "hosted": { _arg isEqualType "" && {(toUpper _arg) in _pages} };
    case "sections": {
        private _vis = call _visible;
        private _out = [];
        {
            _x params ["_sid", "_title", "_members"];
            private _rows = [];
            { private _m = _x; private _r = _vis select { ((_x select 0) get "id") isEqualTo _m }; if ((count _r) > 0) then { _rows pushBack (_r select 0); }; } forEach _members;
            if ((count _rows) > 0) then { _out pushBack [_sid, _title, _rows]; };
        } forEach _sectionsDef;
        _out
    };
    case "strip": {
        private _page = toUpper _arg;
        private _tabs = [["SOMMAIRE", "['home'] call comspec_atak_native_fnc_sseHub;", false, 0]];
        {
            _x params ["", "", "_rows"];
            if ((_rows findIf { ((_x select 0) get "page") isEqualTo _page }) >= 0) then {
                {
                    _x params ["_app", "_name"]; _name = _tabName getOrDefault [_app get "id", _name];
                    private _p = _app get "page";
                    _tabs pushBack [_name, format ["['goto', %1] call comspec_atak_native_fnc_sseHub;", str _p], _p isEqualTo _page, [_p] call comspec_atak_native_fnc_appBadge];
                } forEach _rows;
            };
        } forEach (["sections"] call comspec_atak_native_fnc_sseHub);
        [["tabs", _tabs]]
    };
    case "drawStrip": {
        private _r = ([] call comspec_atak_native_fnc_layoutGet) getOrDefault ["sseStrip", []];
        if ((count _r) < 4 || {(_r select 3) <= 0}) exitWith { false };
        [["strip", _arg] call comspec_atak_native_fnc_sseHub, _r, false] call comspec_atak_native_fnc_formRender;
        true
    };
    case "goto": {
        private _page = toUpper _arg;
        private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
        if ((_s getOrDefault ["activePage", ""]) isEqualTo _page) exitWith { false };
        private _h = _s getOrDefault ["history", []];
        if ((count _h) > 0 && {(_h select ((count _h) - 1)) in _pages}) then { _h set [(count _h) - 1, _page]; } else { _h pushBack _page; };
        _s set ["history", _h];
        [_page, false, true] call comspec_atak_native_fnc_navigate
    };
    case "home": {
        private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
        private _h = _s getOrDefault ["history", []];
        private _i = -1;
        { if (_x isEqualTo "SSEHUB") then { _i = _forEachIndex; }; } forEach _h;
        if (_i >= 0) then { _h resize (_i + 1); } else { _h pushBack "SSEHUB"; };
        _s set ["history", _h];
        _s set ["sseQuery", ""];
        ["SSEHUB", false, true] call comspec_atak_native_fnc_navigate
    };
    case "search": {
        private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
        _s set ["sseQuery", [_arg, ""] select !(_arg isEqualType "")];
        [{ ["SSEHUB"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame;
        true
    };
    case "fold": {
        private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
        private _f = _s getOrDefault ["sseFolds", createHashMap];
        _f set [_arg, !(_f getOrDefault [_arg, false])];
        _s set ["sseFolds", _f];
        [{ ["SSEHUB"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame;
        true
    };
    case "counters": {
        private _store = uiNamespace getVariable ["COMSPEC_ATAK_Alertes", createHashMap];
        private _list = _store getOrDefault ["list", []];
        if !(_list isEqualType []) then { _list = []; };
        private _out = missionNamespace getVariable ["COMSPEC_ATAK_AlertesOut", []];
        if !(_out isEqualType []) then { _out = []; };
        [count _list, { _x isEqualType createHashMap && {(_x getOrDefault ["web", ""]) isEqualTo "ÉCHEC"} } count _out]
    };
    case "recent": {
        if (_arg isEqualType "" && {(toUpper _arg) in _pages} && {(["count"] call comspec_atak_native_fnc_sseHub) > 0}) then { "SSEHUB" } else { _arg }
    };
    case "migrate": {
        if (uiNamespace getVariable ["COMSPEC_ATAK_SseMigrated", false]) exitWith { false };
        if ((["count"] call comspec_atak_native_fnc_sseHub) < 1) exitWith { false };
        uiNamespace setVariable ["COMSPEC_ATAK_SseMigrated", true];
        {
            private _key = _x;
            private _cur = profileNamespace getVariable [_key, []];
            if (_cur isEqualType [] && {(_cur findIf { _x isEqualType "" && {_x in _pages} }) >= 0}) then {
                private _new = [];
                { _new pushBackUnique ([_x, "SSEHUB"] select (_x isEqualType "" && {_x in _pages})); } forEach _cur;
                profileNamespace setVariable [_key, _new];
            };
        } forEach ["COMSPEC_ATAK_PinnedApps", "COMSPEC_ATAK_AppRecent"];
        true
    };
    default { [] };
}
