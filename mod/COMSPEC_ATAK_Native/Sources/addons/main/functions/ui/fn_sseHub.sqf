/*
    Dossier SSE du lanceur : une seule tuile « SSE » (class SseHub, page SSEHUB, fn_pageSseHub) à la place des huit tuiles
    des apps liées au portail SSE. Retour de jeu : seize tuiles dans RENSEIGNEMENT, plusieurs avec la même icône et des noms
    proches (« SSE », « SSE terrain », « Fiches SSE », « Fichiers ») : on ne savait pas laquelle ouvrir.
    Rien n'est fusionné ni renommé : chaque app garde sa classe et sa page (COMSPEC_ATAK_Apps de l'ATAK ou du pack SSE), donc
    les touches, les apps épinglées (accueil, dock, carte), les groupes du joueur, les notifications et les liens entre apps
    (fn_navigate, fn_openApp) les ouvrent comme avant. Seule la racine des sections du lanceur change (fn_launcherRender) et la
    vue regroupée (fn_appSpaces).
    Le dossier ne montre que les apps présentes et visibles (fn_appVisible) : sans le pack SSE il reste les apps de l'ATAK ;
    avec moins de deux apps il disparaît et l'app qui reste garde sa tuile. Désinstallé (App Store), retiré ou interdit par
    l'encadrement : les apps reviennent une à une à la racine.
    Params : [action, argument]
      ["ids"]                 -> classes regroupées, dans l'ordre du dossier
      ["apps"]                -> [[app (fn_appList), nom dans le dossier, description]...] des apps visibles
      ["count"]               -> nombre d'apps visibles (fn_appVisible : la tuile du dossier demande au moins 2)
      ["badge"]               -> somme des pastilles des apps du dossier (fn_appBadge)
      ["rootHidden", apps]    -> classes à ne pas répéter à la racine quand le dossier fait partie de cette liste d'apps, [] sinon
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
// Table unique du dossier : [classe, nom affiché dans le dossier, une ligne qui dit à quoi sert l'app].
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
    default { [] };
}
