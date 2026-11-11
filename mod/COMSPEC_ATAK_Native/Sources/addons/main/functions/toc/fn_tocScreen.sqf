/*
    Écrans TOC (demande de TTRD, 26/10) : un grand écran du jeu (TV, écran sur trépied, écran de projection, poste
    informatique) qui affiche en direct le réseau ATAK d'un camp, tel que le serveur de la mission le voit
    (fn_tocFeed) : carte avec les nœuds ATAK (joueurs et IA équipées, à leur dernier point PLI, en direct / ancien /
    perdu / hors ligne), marqueurs de la mission, derniers comptes rendus, alertes et messages du poste, état du réseau.
    L'écran est une texture UI (#(rgb,2048,1024,1)ui("COMSPEC_RscTocScreenTexture", nom unique)) posée par chaque joueur
    sur l'objet (setObjectTexture local), rafraîchie toutes les 1 à 2 s seulement quand l'écran est allumé pour ce
    joueur : à moins de la distance d'affichage (réglage « Distance d'affichage des écrans TOC », 25 m par défaut),
    devant lui et pas derrière un mur. Un peu plus loin ou pas regardé : dernière image figée, aucun calcul ; au-delà :
    veille (texture d'origine). Voir « tick ». Une carte Arma ne se dessine pas dans une texture UI (fn_jpcMap) : le fond
    est la photo de la carte (mod @COMSPEC_ATAK_Photo, fn_photoMap) ou, sans lui, la carte d'ensemble du terrain
    (pictureMap, assombrie), avec quadrillage numéroté, échelle, villes et villages.
    Carte en grand : l'écran passe en CARTE SEULE, et l'action « Ouvrir le PC du poste » ouvre l'écran du PC portable
    du poste (fn_tocLaptop), indépendant du téléphone.
    PIP du TOC (demande de TTRD, 26/10) : affichage « PIP DU POSTE », l'écran recopie ce que l'opérateur a devant lui
    sur le PC du poste (fn_tocLaptop, COMSPEC_ATAK_TocView) : même cadrage de carte, onglet, groupe ou compte rendu
    choisi, objectif de l'ordre en cours. Sans opérateur, l'écran revient de lui-même à carte et flux.
    Persistant : registre tenu par le serveur (joueurs qui rejoignent compris), écrans et affichage gardés d'une partie
    à l'autre sur le même serveur (fn_tocPersist, réglage « Garder les écrans TOC »).

    Variables publiques de l'objet :
      COMSPEC_ATAK_TocScr [nom, camp ("" = celui de chaque joueur), index de texture, affichage ("SPLIT" | "MAP" | "FEED" | "NET" | "ORBAT" | "TOC"
                           | "CAM" | "SAT" | "CLOCK" | "SQUAD" | "LOG" | "EXCHECK" | "KILLS" | "REQ"),
                           demi-largeur de la carte en m (0 = cadrage automatique sur le réseau), centre ([] = auto),
                           poste TOC (l'écran sert aussi de PC de TOC), à recréer à la partie suivante (posé en cours de partie),
                           satellite montré en affichage SAT (0 : SAT 1, 1 : SAT 2), groupe montré en affichage SQUAD (nom)]
      COMSPEC_ATAK_TocOwned   objet créé par le module ou la sauvegarde (supprimé avec l'écran)
    Registre : missionNamespace COMSPEC_ATAK_TocScreens (objets), tenu par le serveur.
    Salle TOC (demande de TTRD, 11/11) : SAT (vue satellite et suivi des alliés, fn_tocSat), CLOCK (heures ZULU et
    locale, chronos), SQUAD (un élément : plaque à son nom, membres et leur état ATAK, carte cadrée sur lui, dernier compte
    rendu), LOG, EXCHECK, KILLS, REQ (tableaux du poste, fn_tocBoard). Tout écran posé en cours de partie (Zeus, ACE) est
    gardé pour la partie suivante.

    Modes :
      ["add", [objet, nom, camp, index, affichage, poste TOC, à recréer, satellite (-1 : inchangé), groupe (nil : inchangé)]]
                                           (serveur ; ailleurs, demande au serveur)
      ["remove", objet]                    retire l'écran (et supprime l'objet s'il a été créé par le module)
      ["spawn", [position ATL, direction, classe, nom, camp, affichage, poste TOC, satellite, groupe]]   crée l'écran (serveur)
      ["view", [objet, clé, valeur]]       "mode" affichage | "zoom" demi-largeur | "center" position | "auto"
                                           | "sat" 0 ou 1 | "squad" nom du groupe | "cam" netId de la caméra
      ["squads", camp]                     groupes proposés pour l'affichage SQUAD (noms, camp du joueur si "")
      ["classes"]                          écrans du jeu proposés à la pose [[libellé, classe]...] (classes présentes)
      ["tick"]                             client, chaque seconde (XEH_postInitClient) : textures et rafraîchissement
      ["surface", [objet, dalle, active]]  client : prépare la dalle pour une image (matière d'écran, dalles superposées
                                           masquées) ou remet tout (aussi pour fn_screenCast et fn_tocCam)
      ["orig", [objet, dalle]]             texture d'origine de la dalle (écrans COMSPEC : celle de la config)
      ["paint", [objet, dalle, texture]]   pose l'image (fn_screenCast, fn_tocCam aussi) : dalles recopiées
                                           (comspec_screenMirror), tableau-toile : sur sa surface de projection
    Écrans COMSPEC (comspec_screen=1 : COMSPEC_ATAK_Screen_*, modèles du jeu) : dalle 0 seule (dalles superposées vides
    dans la config), matières d'écran du mod (comspec_screenMat). Toiles (comspec_projBoard=1 : tableau blanc du jeu) :
    image sur la surface posée devant le tableau (fn_projector, COMSPEC_ATAK_ProjPlane), seulement si un projecteur
    allumé l'éclaire (COMSPEC_ATAK_ProjLit sur chaque poste).
      ["load", display]                    onLoad du display de la texture
      ["initServer"]                       serveur (XEH_postInitServer)
      ["aceInit"]                          ACE sur les classes d'écran du jeu (partout) : TOC, PIP, diapositives
      ["ensure", [objet, affichage]]       désigne l'objet comme écran TOC (mode demandé) si besoin
      ["show", [objet, contenu]]           contenu choisi depuis le téléphone ou le PC : affichage, ou "SLIDES" (diapositives)
      ["center", objet]                    centre de l'image (ASL), pour savoir si l'écran est vu (fn_tocCam aussi)
      ["title", affichage]                 [mot d'en-tête, libellé des menus] ; "" : table de tous les affichages
      ["audit"]                            contrôle de chaque affichage dans un display d'essai (journal, COMSPEC_ATAK_TocAudit)
      ["build", display] / ["draw", [objet, display]]   contrôles de la carte du poste / dessin d'une image
    Rendu (1 à 2 s, écran allumé) : mise en page en pixels virtuels 1600 x 900, polices fixes (28 px pour le texte courant,
    jamais selon la taille d'interface de qui regarde), une ligne = un contrôle coupé à sa largeur (« … ») ; symboles
    regroupés (cases de 64 px, 110 px en vue d'ensemble) et plaques sans chevauchement, jamais hors du cadre ; groupes
    en pages automatiques ; textes réécrits seulement s'ils changent. ORBAT : celui du site (fn_bftOrbat).
    Exemple de mission : ["add", [this, "TOC Bravo", "WEST"]] call comspec_atak_native_fnc_tocScreen;
*/
params [["_mode", "tick"], ["_a", objNull]];
private _fn = comspec_atak_native_fnc_tocScreen;
// Mise en page en pixels virtuels 1600 x 900 (positions relatives au display). La texture elle-même est en puissances
// de 2 (2048 x 1024, _texSpec) : Arma refuse une texture procédurale de 1600 x 900 (« Cannot load texture », écrans
// noirs de la 2.9.31, journal de TTRD du 02/11).
private _texW = 1600;
private _texH = 900;
private _texSpec = "#(rgb,2048,1024,1)";
private _views = ["SPLIT", "MAP", "FEED", "NET", "ORBAT", "TOC", "CAM", "SAT", "CLOCK", "SQUAD", "LOG", "EXCHECK", "KILLS", "REQ", "STRIP", "PLATE", "MOSAIC"];
// Affichages dessinés en tableau (fn_tocBoard) au lieu de la carte du poste ; STRIP et PLATE : faces 4:1 seulement.
private _boards = ["SAT", "CLOCK", "LOG", "EXCHECK", "KILLS", "REQ", "STRIP", "PLATE", "MOSAIC"];
// Dalle d'un mur multi-écrans : objet relais (fn_tocScreen « panels ») qui porte son propre affichage ; l'image va
// sur la dalle de l'écran hôte (COMSPEC_ATAK_TocHost), à l'index de la dalle.
private _hostOf = { params ["_o"]; private _h = _o getVariable ["COMSPEC_ATAK_TocHost", objNull]; [_o, _h] select (!isNull _h) };
// Face 4:1 (bandeau horloge, plaque de nom) : texture 2048 x 512.
private _ratioOf = { params ["_o"]; getNumber (configOf ([_o] call _hostOf) >> "comspec_screenRatio") };
// [nom, camp, index, affichage, demi-largeur, centre, poste TOC, posé en jeu, satellite, groupe]
private _cfgOf = {
    params ["_o"];
    private _c = _o getVariable ["COMSPEC_ATAK_TocScr", []];
    if !(_c isEqualType []) then { _c = []; };
    _c
};
private _sideOk = {
    params ["_o", ["_u", player]];
    private _s = ([_o] call _cfgOf) param [1, ""];
    _s isEqualTo "" || {_s isEqualTo str side group _u}
};
// Dalle visible : même logique que fn_screenCast (souvent « screen » / « display », pas la sélection 0 du châssis).
private _face = {
    params ["_o", "_sel"];
    private _names = getArray (configOf _o >> "hiddenSelections");
    private _named = _names findIf { (toLower _x) in ["screen", "display", "lcd", "monitor"] };
    private _curName = toLower (_names param [_sel, ""]);
    if (_named >= 0 && {!(_curName in ["screen", "display", "lcd", "monitor"])}) then { _named } else { _sel }
};
// Diapositives en cours sur cet objet : le TOC cède la dalle (fn_screenCast).
private _casting = {
    params ["_o"];
    (count (_o getVariable ["COMSPEC_ATAK_ScreenCast", []])) >= 5
};
// Toile de projection : image seulement quand un projecteur allumé l'éclaire (fn_projector, contrôle de ce poste).
private _lit = {
    params ["_o"];
    (getNumber (configOf _o >> "comspec_projBoard")) < 1 || {_o getVariable ["COMSPEC_ATAK_ProjLit", false]}
};
// Distance d'affichage (réglage du joueur, 10 à 100 m) : au-delà, ni rendu ni rafraîchissement.
private _viewRange = {
    private _r = missionNamespace getVariable ["comspec_atak_native_toc_view_range", 25];
    if !(_r isEqualType 0) then { _r = 25; };
    (_r max 10) min 100
};
// Centre de l'image (ASL) : milieu de la boîte du modèle de l'écran hôte (relais d'un mur : le mur), gardé par objet.
private _centerOf = {
    params ["_o"];
    private _h = [_o] call _hostOf;
    private _m = _h getVariable "COMSPEC_ATAK_TocMid";
    if (isNil "_m") then {
        (boundingBoxReal _h) params [["_b0", [0, 0, 0]], ["_b1", [0, 0, 0]]];
        _m = (_b0 vectorAdd _b1) vectorMultiply 0.5;
        _h setVariable ["COMSPEC_ATAK_TocMid", _m];
    };
    _h modelToWorldVisualWorld _m
};
// Rectangle en pixels virtuels -> position du display.
private _R = { params ["_rx", "_ry", "_rw", "_rh"]; [_rx / _texW, _ry / _texH, _rw / _texW, _rh / _texH] };
// Texte coupé à n caractères avec « … » (caractères Unicode : un accent n'est jamais coupé en deux).
private _cut = {
    params ["_s", "_n"];
    if !(_s isEqualType "") then { _s = str _s; };
    forceUnicode 0;
    if ((count _s) <= _n) exitWith { _s };
    forceUnicode 0;
    (_s select [0, (_n - 1) max 1]) + "…"
};
// Libellé d'une ligne dans une case de _w px virtuels : traduit puis coupé avec « … ». Estimation : 0,46 x la police
// par caractère (0,52 en gras), plus large que la police condensée réelle ; la mesure du jeu (ctrlTextWidth), quand
// elle est plausible, coupe encore si le texte déborde. Réécrit seulement s'il change.
private _lbl = {
    params ["_c", "_t", "_w"];
    private _key = format ["%1|%2", _t, round _w];
    if ((_c getVariable ["COMSPEC_ATAK_Txt", ""]) isEqualTo _key) exitWith {};
    _c setVariable ["COMSPEC_ATAK_Txt", _key];
    private _cw = (_c getVariable ["COMSPEC_ATAK_Fpx", 28]) * ([0.46, 0.52] select (_c getVariable ["COMSPEC_ATAK_Bold", false]));
    private _room = _w - 8;
    private _s = [_t call comspec_atak_native_fnc_tr, floor (_room / _cw)] call _cut;
    _c ctrlSetText _s; // i18n:brut (déjà traduit)
    for "_i" from 1 to 6 do {
        private _m = (ctrlTextWidth _c) * _texW;
        forceUnicode 0;
        private _n = count _s;
        if (_m <= 0.5 * _n * _cw || {_m >= 2 * _n * _cw} || {_m <= _room} || {_n <= 3}) exitWith {};
        _s = [_s, _n - 2] call _cut;
        _c ctrlSetText _s; // i18n:brut (déjà traduit)
    };
};

switch (_mode) do {
    case "initServer": {
        if (!isServer) exitWith {};
        ["comspec_atak_native_tocScreen", { _this call comspec_atak_native_fnc_tocScreen; }] call CBA_fnc_addEventHandler;
        ["init"] call comspec_atak_native_fnc_tocFeed;
        ["init"] call comspec_atak_native_fnc_tocRoom;
        ["init"] call comspec_atak_native_fnc_tocHall;
        // Écrans gardés de la partie précédente : remis une fois les modules Eden passés.
        [{ ["restore"] call comspec_atak_native_fnc_tocPersist; }, [], 3] call comspec_atak_native_fnc_waitAndExec;
        true
    };
    case "classes": {
        // Écrans COMSPEC en tête (modèles du jeu réglés pour l'image, fiables jour et nuit), puis les écrans du jeu.
        ([
            ["Mur d'images 2x2, image unique (COMSPEC)", "COMSPEC_ATAK_Screen_Wall2x2"],
            ["Mur d'images 2x2, 4 dalles (COMSPEC)", "COMSPEC_ATAK_Screen_Wall2x2_Quad"],
            ["Grand mur d'images 3x2, 6 dalles (COMSPEC)", "COMSPEC_ATAK_Screen_Wall3x2"],
            ["Grand mur d'images 3x2, image et 2 dalles (COMSPEC)", "COMSPEC_ATAK_Screen_Wall3x2_Mixed"],
            ["Écran géant 98 pouces mural (COMSPEC)", "COMSPEC_ATAK_Screen_98"],
            ["Écran géant 98 pouces sur pied roulant (COMSPEC)", "COMSPEC_ATAK_Screen_98_Stand"],
            ["Écran 65 pouces suspendu (COMSPEC)", "COMSPEC_ATAK_Screen_65_Ceiling"],
            ["Bandeau horloge ZULU / LOCAL (COMSPEC)", "COMSPEC_ATAK_Screen_ClockStrip"],
            ["Plaque de nom d'élément (COMSPEC)", "COMSPEC_ATAK_Screen_NamePlate"],
            ["Grand écran TOC sur trépied (COMSPEC)", "COMSPEC_ATAK_Screen_Tripod"],
            ["Double écran TOC sur trépied (COMSPEC)", "COMSPEC_ATAK_Screen_Dual"],
            ["Téléviseur TOC (COMSPEC)", "COMSPEC_ATAK_Screen_Wall"],
            ["Moniteur TOC de bureau (COMSPEC)", "COMSPEC_ATAK_Screen_Desk"],
            ["Grand écran sur trépied", "Land_TripodScreen_01_large_F"],
            ["Grand écran sur trépied (noir)", "Land_TripodScreen_01_large_black_F"],
            ["Grand écran sur trépied (sable)", "Land_TripodScreen_01_large_sand_F"],
            ["Double écran sur trépied", "Land_TripodScreen_01_dual_v1_F"],
            ["Double écran sur trépied (noir)", "Land_TripodScreen_01_dual_v1_black_F"],
            ["Double écran sur trépied (sable)", "Land_TripodScreen_01_dual_v1_sand_F"],
            ["Double écran incliné sur trépied", "Land_TripodScreen_01_dual_v2_F"],
            ["Double écran incliné sur trépied (noir)", "Land_TripodScreen_01_dual_v2_black_F"],
            ["Double écran incliné sur trépied (sable)", "Land_TripodScreen_01_dual_v2_sand_F"],
            ["TV à écran plat", "Land_FlatTV_01_F"],
            ["Écran de projection", "Land_ProjectorScreen_01_F"],
            ["Poste informatique", "Land_PCSet_01_screen_F"],
            ["Ordinateur portable", "Land_Laptop_unfolded_F"]
        ]) select { isClass (configFile >> "CfgVehicles" >> (_x select 1)) }
    };
    case "squads": {
        // Groupes du camp à montrer sur un écran d'escouade : ceux qui ont un joueur ou un nœud ATAK (jamais l'ennemi).
        private _s = [_a, str side group player] select (!(_a isEqualType "") || {_a isEqualTo ""});
        private _feed = missionNamespace getVariable [format ["COMSPEC_ATAK_TocFeed_%1", _s], []];
        private _out = (_feed param [1, []]) apply { _x param [7, ""] };
        { if ((str side _x) isEqualTo _s && {({isPlayer _x && {alive _x}} count (units _x)) > 0}) then { _out pushBack groupId _x; }; } forEach allGroups;
        _out = (_out arrayIntersect _out) select { _x isNotEqualTo "" };
        _out sort true;
        _out
    };
    case "add": {
        _a params [["_o", objNull], ["_name", ""], ["_side", ""], ["_sel", 0], ["_view", "SPLIT"], ["_toc", true], ["_spawned", false], ["_slot", -1], ["_squad", nil]];
        if (isNull _o) exitWith { false };
        if (!isServer) exitWith { ["comspec_atak_native_tocScreen", ["add", _a]] call CBA_fnc_serverEvent; true };
        if (_name isEqualTo "") then { _name = "TOC"; };
        private _old = [_o] call _cfgOf;
        if !(_view in _views) then { _view = "SPLIT"; };
        // Face 4:1 : bandeau horloge ou plaque de nom seulement ; un écran 16:9 ne prend pas ces deux affichages.
        if (([_o] call _ratioOf) >= 4) then {
            if !(_view in ["STRIP", "PLATE"]) then { _view = ["STRIP", "PLATE"] select (((typeOf _o) find "NamePlate") >= 0); };
        } else {
            if (_view in ["STRIP", "PLATE"]) then { _view = "CLOCK"; };
        };
        if !(_slot in [0, 1]) then { _slot = _old param [8, 0]; };
        if (isNil "_squad" || {!(_squad isEqualType "")}) then { _squad = _old param [9, ""]; };
        // Index de dalle : celui demandé, sinon la sélection nommée écran (évite un TOC noir sur le châssis).
        private _faceSel = [_o, (round _sel) max 0] call _face;
        // Désigné en cours de partie (Zeus, ACE) : gardé et recréé à la partie suivante, comme un écran posé par le module.
        // Un écran de la mission (Eden) est d'abord retrouvé à sa place par la sauvegarde : il n'est pas doublé.
        private _late = time > 20;
        _o setVariable ["COMSPEC_ATAK_TocScr", [_name select [0, 32], toUpper _side, _faceSel, _view, _old param [4, 0], _old param [5, []], _toc,
            _spawned || {_late} || {_old param [7, false]}, _slot, _squad select [0, 40]], true];
        // Poste TOC : l'écran donne aussi l'ATAK du poste et l'app Poste TOC à qui s'en approche (fn_tocPost).
        _o setVariable ["COMSPEC_ATAK_Toc", _toc, true];
        _o setVariable ["COMSPEC_ATAK_TocSide", toUpper _side, true];
        // Identifiant stable (scènes des salles TOC, sauvegarde) : posé une fois.
        if ((_o getVariable ["COMSPEC_ATAK_TocUid", ""]) isEqualTo "") then { _o setVariable ["COMSPEC_ATAK_TocUid", format ["S%1-%2", floor (serverTime * 100), floor random 100000], true]; };
        private _list = (missionNamespace getVariable ["COMSPEC_ATAK_TocScreens", []]) select { !isNull _x };
        _list pushBackUnique _o;
        missionNamespace setVariable ["COMSPEC_ATAK_TocScreens", _list, true];
        // Mur à plusieurs dalles : une dalle = un affichage (relais créés pour les dalles 2 et suivantes).
        if ((getNumber (configOf _o >> "comspec_screenPanels")) > 1 && {isNull (_o getVariable ["COMSPEC_ATAK_TocHost", objNull])}) then { ["panels", _o] call _fn; };
        ["save"] call comspec_atak_native_fnc_tocPersist;
        true
    };
    case "panels": {
        // Serveur : relais des dalles 2 à N d'un mur (objet invisible accroché au mur, inscrit au registre comme un
        // écran ordinaire). Réglage des dalles : COMSPEC_ATAK_TocPanelCfg [[affichage, satellite, escouade, caméra]...]
        // (module, sauvegarde) ; sinon carte et flux.
        private _h = _a;
        if (!isServer || {isNull _h}) exitWith { [] };
        private _n = getNumber (configOf _h >> "comspec_screenPanels");
        private _px = (_h getVariable ["COMSPEC_ATAK_TocPanels", []]) select { !isNull _x };
        private _hc = [_h] call _cfgOf;
        private _pc = _h getVariable ["COMSPEC_ATAK_TocPanelCfg", []];
        for "_k" from 1 to (_n - 1) do {
            if ((_px findIf { (_x getVariable ["COMSPEC_ATAK_TocPanel", 0]) isEqualTo _k }) < 0) then {
                private _r = createVehicle ["Land_HelipadEmpty_F", getPosATL _h, [], 0, "CAN_COLLIDE"];
                _r attachTo [_h, [0, 0, 1.5]];
                _r setVariable ["COMSPEC_ATAK_TocHost", _h, true];
                _r setVariable ["COMSPEC_ATAK_TocPanel", _k, true];
                _r setVariable ["COMSPEC_ATAK_TocOwned", true, true];
                (_pc param [_k - 1, []]) params [["_v", "SPLIT"], ["_sl", 0], ["_sq", ""], ["_cam", []], ["_mos", []], ["_off", false]];
                if (_mos isEqualType [] && {(count _mos) >= 5}) then { _r setVariable ["COMSPEC_ATAK_TocMosaic", _mos, true]; };
                if (_off isEqualTo true) then { _r setVariable ["COMSPEC_ATAK_TocOff", true, true]; };
                ["add", [_r, format ["%1 · %2", (_hc param [0, "TOC"]) select [0, 22], _k + 1], _hc param [1, ""], _k, _v, false, false, _sl, _sq]] call _fn;
                if (_cam isEqualType [] && {(count _cam) >= 2}) then { _r setVariable ["COMSPEC_ATAK_TocCamWant", _cam]; };
                _px pushBack _r;
            };
        };
        _h setVariable ["COMSPEC_ATAK_TocPanels", _px, true];
        _px
    };
    case "panelSpec": {
        // Contenu des dalles 2 et suivantes écrit à la main (module) : « SAT1, CLOCK, SQ:Alpha 1-1, LOG ».
        if !(_a isEqualType "") exitWith { [] };
        (_a splitString ",") apply {
            private _t = trim _x;
            private _u = toUpper _t;
            switch (true) do {
                case (_u isEqualTo "SAT1"): { ["SAT", 0, ""] };
                case (_u isEqualTo "SAT2"): { ["SAT", 1, ""] };
                case ((_u select [0, 3]) isEqualTo "SQ:"): { ["SQUAD", 0, trim (_t select [3])] };
                case (_u in ["MOS2", "MOS3"]): { ["MOSAIC", 0, "", [], [[2, 3] select (_u isEqualTo "MOS3"), "AUTO", "", [], 10]] };
                case (_u in _views): { [_u, 0, ""] };
                default { ["SPLIT", 0, ""] };
            }
        }
    };
    case "panelOf": {
        // Objet qui porte l'affichage de la dalle k d'un écran (0 : l'écran lui-même).
        _a params [["_h", objNull], ["_k", 0]];
        if (_k <= 0) exitWith { _h };
        ((_h getVariable ["COMSPEC_ATAK_TocPanels", []]) select { !isNull _x && {(_x getVariable ["COMSPEC_ATAK_TocPanel", 0]) isEqualTo _k} }) param [0, objNull]
    };
    case "apply": {
        // Contenu choisi pour un écran ou une dalle (PC du poste, téléphone, ACE) : affichage, « SAT1 » / « SAT2 »,
        // « SQ|groupe », « CAM|netId », « SLIDES ».
        _a params [["_o", objNull], ["_k", ""]];
        if (isNull _o || {_k isEqualTo ""}) exitWith { false };
        switch (true) do {
            case (_k in ["SAT1", "SAT2"]): { [_o, toLower _k] call COMSPEC_ATAK_TocDo; };
            case ((_k select [0, 3]) in ["SQ|", "sq|"]): { [_o, "sq|" + (_k select [3])] call COMSPEC_ATAK_TocDo; };
            case ((_k select [0, 4]) isEqualTo "CAM|"): { ["set", [_o, _k select [4]]] call comspec_atak_native_fnc_tocCam; };
            case ((_k select [0, 4]) isEqualTo "MOS|"): {
                if ((count (_o getVariable ["COMSPEC_ATAK_TocScr", []])) < 8) then { ["ensure", [_o, "SPLIT"]] call _fn; };
                ["view", [_o, "mosaic", ["key", _k] call comspec_atak_native_fnc_tocMosaic]] call _fn;
                ["ensure", [_o, "MOSAIC"]] call _fn;
            };
            default { ["show", [_o, _k]] call _fn; };
        };
        true
    };
    case "remove": {
        if (isNull _a) exitWith { false };
        if (!isServer) exitWith { ["comspec_atak_native_tocScreen", ["remove", _a]] call CBA_fnc_serverEvent; true };
        private _owned = _a getVariable ["COMSPEC_ATAK_TocOwned", false];
        // Mur à plusieurs dalles : ses relais partent avec lui.
        { if (!isNull _x) then { ["remove", _x] call _fn; }; } forEach (_a getVariable ["COMSPEC_ATAK_TocPanels", []]);
        _a setVariable ["COMSPEC_ATAK_TocPanels", nil, true];
        _a setVariable ["COMSPEC_ATAK_TocScr", nil, true];
        _a setVariable ["COMSPEC_ATAK_Toc", nil, true];
        missionNamespace setVariable ["COMSPEC_ATAK_TocScreens", (missionNamespace getVariable ["COMSPEC_ATAK_TocScreens", []]) select { !isNull _x && {_x isNotEqualTo _a} }, true];
        // Écran créé par le module (ou recréé par la sauvegarde) : l'objet part avec lui.
        if (_owned) then { deleteVehicle _a; };
        ["save"] call comspec_atak_native_fnc_tocPersist;
        true
    };
    case "spawn": {
        _a params [["_pos", [0, 0, 0]], ["_dir", 0], ["_cls", ""], ["_name", ""], ["_side", ""], ["_view", "SPLIT"], ["_toc", true], ["_slot", 0], ["_squad", ""], ["_pans", ""]];
        if (!isServer) exitWith { ["comspec_atak_native_tocScreen", ["spawn", _a]] call CBA_fnc_serverEvent; objNull };
        private _cands = (["classes"] call _fn) apply { _x select 1 };
        if !(_cls in _cands) then { _cls = _cands param [0, ""]; };
        if (_cls isEqualTo "") exitWith {
            ["WARNING", "TOC", "Écran TOC : aucun écran du jeu disponible pour la pose."] call comspec_atak_native_fnc_log;
            objNull
        };
        private _o = createVehicle [_cls, _pos, [], 0, "CAN_COLLIDE"];
        _o setDir _dir;
        _o setPosATL _pos;
        _o setVariable ["COMSPEC_ATAK_TocOwned", true, true];
        if (_pans isNotEqualTo "") then { _o setVariable ["COMSPEC_ATAK_TocPanelCfg", ["panelSpec", _pans] call _fn]; };
        ["add", [_o, _name, _side, 0, _view, _toc, true, _slot, _squad]] call _fn;
        // Zeus : l'écran créé se manipule comme un objet posé à la main.
        { _x addCuratorEditableObjects [[_o], false]; } forEach allCurators;
        _o
    };
    case "view": {
        _a params [["_o", objNull], ["_k", ""], ["_v", 0]];
        if (isNull _o) exitWith { false };
        if (!isServer) exitWith { ["comspec_atak_native_tocScreen", ["view", _a]] call CBA_fnc_serverEvent; true };
        private _c = +([_o] call _cfgOf);
        if ((count _c) < 8) exitWith { false };
        switch (_k) do {
            // Face 4:1 : seulement bandeau horloge ou plaque de nom (une carte 16:9 y serait écrasée).
            case "mode": { if (_v in _views && {(([_o] call _ratioOf) < 4) || {_v in ["STRIP", "PLATE"]}}) then { _c set [3, _v]; }; };
            // Caméra en direct (fn_tocCam) : seule la source choisie circule (netId), chaque joueur fait son rendu.
            case "cam": { if (_v isEqualType "" && {(count _v) < 40}) then { _o setVariable ["COMSPEC_ATAK_TocCam", _v, true]; _o setVariable ["COMSPEC_ATAK_TocCamWant", nil]; }; };
            case "zoom": { _c set [4, ((_v max 100) min (worldSize max 1000))]; };
            case "center": { if (_v isEqualType [] && {(count _v) >= 2}) then { _c set [5, [_v select 0, _v select 1]]; }; };
            case "auto": { _c set [4, 0]; _c set [5, []]; };
            case "sat": { if (_v in [0, 1]) then { _c set [8, _v]; }; };
            case "squad": { if (_v isEqualType "") then { _c set [9, _v select [0, 40]]; }; };
            // Mosaïque de caméras (fn_tocMosaic) : [grille, choix, groupe, cases, rotation].
            case "mosaic": { if (_v isEqualType [] && {(count _v) >= 5}) then { _o setVariable ["COMSPEC_ATAK_TocMosaic", _v, true]; }; };
        };
        if ((count _c) < 10) then { _c set [8, _c param [8, 0]]; _c set [9, _c param [9, ""]]; };
        _o setVariable ["COMSPEC_ATAK_TocScr", _c, true];
        ["save"] call comspec_atak_native_fnc_tocPersist;
        true
    };
    case "surface": {
        // Dalle prête à montrer une image (TOC, diapositive, caméra), sur ce poste seulement ; false : tout est remis.
        // Grand écran sur trépied (et ses variantes noire, sable) : dix dalles Screen_0 à Screen_9 superposées, la 0
        // couvrant tout l'écran, avec la matière « Lamp_lcd » du jeu (lumineuse x200, sans profondeur, pensée pour une
        // dalle vide) : l'image posée sur la dalle 0 restait noire. Les autres dalles sont masquées et la dalle prend la
        // matière d'écran du mod (celle de la tablette JPC, jour / nuit), comme le faisait l'ancien pack TOC (matière retirée).
        _a params [["_o", objNull], ["_sel", 0], ["_on", true]];
        if (isNull _o) exitWith { false };
        _o = [_o] call _hostOf;
        // Tableau-toile : la surface de projection posée devant lui reçoit la matière.
        if ((getNumber (configOf _o >> "comspec_projBoard")) > 0) then { _o = _o getVariable ["COMSPEC_ATAK_ProjPlane", objNull]; _sel = 0; };
        if (isNull _o) exitWith { false };
        // Mur à plusieurs dalles (comspec_screenPanels) : une préparation par dalle, chacune garde son image allumée.
        private _pv = ["COMSPEC_ATAK_SurfPrep", format ["COMSPEC_ATAK_SurfPrep%1", _sel]] select ((getNumber (configOf _o >> "comspec_screenPanels")) > 1);
        private _prep = _o getVariable [_pv, []];
        if (!_on) exitWith {
            if (_prep isEqualTo []) exitWith { false };
            _prep params ["_ps", "_mat", "_sibs"];
            _o setObjectMaterial [_ps, _mat];
            { _o setObjectTexture _x; } forEach _sibs;
            // Dalles recopiées (double écran) : matière de la config remise.
            private _mats = getArray (configOf _o >> "hiddenSelectionsMaterials");
            { _o setObjectMaterial [_x, _mats param [_x, ""]]; } forEach (getArray (configOf _o >> "comspec_screenMirror"));
            _o setVariable [_pv, nil];
            true
        };
        if (_prep isNotEqualTo [] && {(_prep select 0) isNotEqualTo _sel}) then { ["surface", [_o, 0, false]] call _fn; _prep = []; };
        // Écran ou toile COMSPEC : une seule dalle, rien à masquer ; matière d'origine et matières jour / nuit de la config.
        private _own = getArray (configOf _o >> "comspec_screenMat");
        if (_prep isEqualTo [] && {(count _own) >= 2}) then {
            _prep = [_sel, (getArray (configOf _o >> "hiddenSelectionsMaterials")) param [_sel, ""], [], ""];
            _o setVariable [_pv, _prep];
        };
        if (_prep isEqualTo []) then {
            private _names = (getArray (configOf _o >> "hiddenSelections")) apply { toLower _x };
            private _texs = getObjectTextures _o;
            private _panes = [];
            { if ((_x select [0, 7]) isEqualTo "screen_") then { _panes pushBack _forEachIndex; }; } forEach _names;
            // Plus de deux dalles « screen_ » : mur d'images superposé (pas deux moniteurs côte à côte).
            private _sibs = [];
            if ((count _panes) > 2 && {_sel in _panes}) then {
                { if (_x isNotEqualTo _sel) then { _sibs pushBack [_x, _texs param [_x, ""]]; _o setObjectTexture [_x, ""]; }; } forEach _panes;
            };
            _prep = [_sel, (getObjectMaterials _o) param [_sel, ""], _sibs, ""];
            _o setVariable [_pv, _prep];
            ["INFO", "TOC", format ["Écran %1 : image sur la dalle %2 (%3), matière d'origine %4, %5 dalle(s) masquée(s)",
                typeOf _o, _sel, _names param [_sel, "?"], [_prep select 1, "aucune"] select ((_prep select 1) isEqualTo ""), count _sibs]] call comspec_atak_native_fnc_log;
        };
        private _mat = if ((count _own) >= 2) then { _own select (parseNumber (sunOrMoon <= 0.5)) } else {
            "\z\comspec_atak_native\addons\main\data\" + (["jpc_screen_night.rvmat", "jpc_screen.rvmat"] select (sunOrMoon > 0.5))
        };
        // Matière réimposée aussi quand l'objet ne la porte plus (dalle d'index > 0 restée noire de jour, 11/11).
        private _norm = { private _t = toLower _this; if ((_t select [0, 1]) isEqualTo "\") then { _t = _t select [1]; }; _t };
        private _cur = ((getObjectMaterials _o) param [_sel, ""]) call _norm;
        if ((_prep select 3) isNotEqualTo _mat || {_cur isNotEqualTo "" && {_cur isNotEqualTo (_mat call _norm)}}) then {
            _prep set [3, _mat];
            _o setObjectMaterial [_sel, _mat];
            { _o setObjectMaterial [_x, _mat]; } forEach (getArray (configOf _o >> "comspec_screenMirror"));
        };
        true
    };
    case "paint": {
        // Image (ou texture d'origine) posée sur ce poste. Double écran : même image sur les dalles recopiées.
        // Tableau-toile : sur la surface de projection de fn_projector (rien tant qu'elle n'est pas posée).
        _a params [["_o", objNull], ["_sel", 0], ["_tex", ""]];
        if (isNull _o) exitWith { false };
        // Dernière image posée (identification d'un écran : elle revient après le clignotement, fn_tocHall).
        _o setVariable ["COMSPEC_ATAK_TocPainted", [_sel, _tex]];
        _o = [_o] call _hostOf;
        if ((getNumber (configOf _o >> "comspec_projBoard")) > 0) exitWith {
            private _pl = _o getVariable ["COMSPEC_ATAK_ProjPlane", objNull];
            if (!isNull _pl) then { _pl setObjectTexture [0, _tex]; };
            true
        };
        _o setObjectTexture [_sel, _tex];
        private _mir = getArray (configOf _o >> "comspec_screenMirror");
        if (_mir isNotEqualTo []) then {
            private _def = getArray (configOf _o >> "hiddenSelectionsTextures");
            private _back = _tex isEqualTo (_def param [_sel, ""]);
            { _o setObjectTexture [_x, [_tex, _def param [_x, ""]] select _back]; } forEach _mir;
        };
        true
    };
    case "orig": {
        // Texture d'origine d'une dalle : écran ou toile COMSPEC, celle de la config (jamais l'image d'un autre affichage
        // restée posée) ; toile : fn_projector tient aussi COMSPEC_ATAK_TocOrig / ScreenOrig (vide si enroulée).
        _a params [["_o", objNull], ["_sel", 0]];
        if (isNull _o) exitWith { "" };
        _o = [_o] call _hostOf;
        if ((getNumber (configOf _o >> "comspec_projBoard")) > 0) exitWith { "" };
        if ((getNumber (configOf _o >> "comspec_screen")) > 0) exitWith { (getArray (configOf _o >> "hiddenSelectionsTextures")) param [_sel, ""] };
        (getObjectTextures _o) param [_sel, ""]
    };
    case "load": {
        // Le display de la texture arrive ici à sa création (la première fois que le jeu dessine la texture). Il est
        // rendu à l'écran qui porte CETTE texture (nom unique du display) : avant, il allait à l'écran « en attente »,
        // et une texture ancienne vue plus tard (écran regardé après coup) donnait son display au mauvais écran.
        // Displays rangés dans uiNamespace (jamais dans une variable d'objet : un display ne se sérialise pas).
        if (isNull _a) exitWith {};
        private _nm = displayUniqueName _a;
        // Display d'essai de l'op « audit » (pas une texture) : n'appartient à aucun écran.
        if (_nm isEqualTo "" && {uiNamespace getVariable ["COMSPEC_ATAK_TocAuditing", false]}) exitWith {};
        private _texObj = uiNamespace getVariable ["COMSPEC_ATAK_TocTexObj", createHashMap];
        private _o = _texObj getOrDefault [_nm, objNull];
        // Repli (nom absent) : dernière texture demandée.
        if (isNull _o) then {
            private _p = uiNamespace getVariable ["COMSPEC_ATAK_TocPending", []];
            if (_nm isEqualTo "" || {(_p param [2, ""]) isEqualTo _nm}) then { _o = _p param [0, objNull]; _nm = _p param [2, ""]; };
        };
        // Texture abandonnée entre-temps (écran quitté, nouvel essai sous un autre nom) : display ignoré.
        if (isNull _o || {(_o getVariable ["COMSPEC_ATAK_TocTex", ""]) isNotEqualTo _nm}) exitWith {};
        private _disps = uiNamespace getVariable ["COMSPEC_ATAK_TocDisps", createHashMap];
        _disps set [_nm, _a];
        uiNamespace setVariable ["COMSPEC_ATAK_TocDisps", _disps];
        _texObj deleteAt _nm;
        // Première image tout de suite (sinon noir jusqu'au tour suivant).
        _o setVariable ["COMSPEC_ATAK_TocDraws", 0];
        _o setVariable ["COMSPEC_ATAK_TocAt", -10];
        if (((uiNamespace getVariable ["COMSPEC_ATAK_TocPending", []]) param [2, ""]) isEqualTo _nm) then { uiNamespace setVariable ["COMSPEC_ATAK_TocPending", []]; };
        ["INFO", "SCREEN", format ["Écran TOC %1 : texture UI %2 chargée, image dessinée", typeOf _o, _nm]] call comspec_atak_native_fnc_log;
        // Première image à l'image suivante, sans attendre le tour de la seconde (display retrouvé par son nom).
        [{
            params ["_o", "_nm"];
            private _d = (uiNamespace getVariable ["COMSPEC_ATAK_TocDisps", createHashMap]) getOrDefault [_nm, displayNull];
            if (isNull _o || {isNull _d} || {!(_o getVariable ["COMSPEC_ATAK_TocOn", false])}) exitWith {};
            _o setVariable ["COMSPEC_ATAK_TocAt", diag_tickTime];
            ["draw", [_o, _d]] call comspec_atak_native_fnc_tocScreen;
            displayUpdate _d;
        }, [_o, _nm]] call comspec_atak_native_fnc_nextFrame;
    };
    case "center": {
        // Centre de l'image (ASL) : milieu de la boîte du modèle de l'écran hôte, gardé une fois par objet. L'origine des
        // modèles est au sol (trépied, murs d'images, 98 pouces) ou au plafond (écran suspendu) : regarder l'image ne
        // mettait pas l'origine à l'écran, l'écran passait pour « pas vu » (plus redessiné, caméras jamais lancées).
        [_a] call _centerOf
    };
    case "tick": {
        // Client, chaque seconde. Coût proportionnel aux seuls écrans proches : index par cases de 100 m, refait quand
        // le registre change (ou toutes les 10 s : écran traîné, déplacé au Zeus).
        //   allumé  : à moins de la distance d'affichage (réglage, 25 m par défaut), devant soi (100° au plus de l'axe
        //             de la caméra) et pas derrière un mur (checkVisibility toutes les 3 s) : texture UI, rafraîchie
        //             toutes les 1 à 2 s, caméras et satellite possibles ;
        //   figé    : un peu plus loin ou pas regardé : la dernière image reste, rien n'est recalculé (ni dessin, ni
        //             caméra, ni boucle par image) ;
        //   veille  : au-delà (distance + 50 %, 10 m au moins) : texture d'origine de l'écran remise, display abandonné.
        // Retour devant l'écran : première image tout de suite.
        if (!hasInterface || {isNull player}) exitWith {};
        disableSerialization;
        private _now = diag_tickTime;
        private _disps = uiNamespace getVariable ["COMSPEC_ATAK_TocDisps", createHashMap];
        uiNamespace setVariable ["COMSPEC_ATAK_TocDisps", _disps];
        private _texObj = uiNamespace getVariable ["COMSPEC_ATAK_TocTexObj", createHashMap];
        uiNamespace setVariable ["COMSPEC_ATAK_TocTexObj", _texObj];
        private _reg = missionNamespace getVariable ["COMSPEC_ATAK_TocScreens", []];
        private _local = (missionNamespace getVariable ["COMSPEC_ATAK_TocLocal", []]) select { !isNull _x };
        private _range = call _viewRange;
        private _hold = _range + (10 max (_range * 0.5));
        private _grid = uiNamespace getVariable ["COMSPEC_ATAK_TocGrid", [-1e9, -1, createHashMap]];
        if ((_grid select 1) isNotEqualTo (count _reg) || {_now - (_grid select 0) > 10}) then {
            private _h = createHashMap;
            {
                if (!isNull _x) then {
                    private _p = getPosASL _x;
                    private _k = [floor ((_p select 0) / 100), floor ((_p select 1) / 100)];
                    private _l = _h getOrDefault [_k, []];
                    _l pushBack _x;
                    _h set [_k, _l];
                };
            } forEach _reg;
            _grid = [_now, count _reg, _h];
            uiNamespace setVariable ["COMSPEC_ATAK_TocGrid", _grid];
        };
        private _eye = AGLToASL (positionCameraToWorld [0, 0, 0]);
        private _dir = vectorNormalized ((AGLToASL (positionCameraToWorld [0, 0, 1])) vectorDiff _eye);
        private _gx = floor ((_eye select 0) / 100);
        private _gy = floor ((_eye select 1) / 100);
        private _span = ceil (_hold / 100);
        private _near = [];
        for "_i" from (_gx - _span) to (_gx + _span) do {
            for "_j" from (_gy - _span) to (_gy + _span) do { _near append ((_grid select 2) getOrDefault [[_i, _j], []]); };
        };
        // Écrans proches, plus ceux qui gardent encore une texture sur ce poste (à remettre en veille).
        private _rows = [];
        {
            private _o = _x;
            if (isNull _o) then { continue };
            private _cfg = [_o] call _cfgOf;
            private _ok = (count _cfg) >= 8 && {alive _o} && {!(_o getVariable ["COMSPEC_ATAK_TocOff", false])} && {[_o] call _sideOk} && {[_o] call _lit};
            private _ctr = [_o] call _centerOf;
            private _dist = _eye distance _ctr;
            private _seen = false;
            if (_ok && {_dist < _range}) then {
                _seen = _dist < 3 || {((vectorNormalized (_ctr vectorDiff _eye)) vectorDotProduct _dir) > -0.17};
                // Derrière un mur : point visé à 15 cm devant la dalle, l'écran lui-même ignoré ; jamais tout près.
                if (_seen && {_dist > 5}) then {
                    private _vis = _o getVariable ["COMSPEC_ATAK_TocVis", [-1e9, true]];
                    if (_now - (_vis select 0) > 3) then {
                        private _to = _ctr vectorAdd ((vectorNormalized (_eye vectorDiff _ctr)) vectorMultiply 0.15);
                        _vis = [_now, ([vehicle player, "VIEW", [_o] call _hostOf] checkVisibility [_eye, _to]) > 0.02];
                        _o setVariable ["COMSPEC_ATAK_TocVis", _vis];
                    };
                    _seen = _vis select 1;
                };
            };
            _rows pushBack [_o, _cfg, _ok, _dist, _seen];
        } forEach (_near + (_local - _near));
        private _act = (_rows select { (_x select 2) && {_x select 4} }) apply { _x select 0 };
        // Image vraiment à l'écran (centre de la dalle) : caméras en direct et satellite seulement pour celles-là.
        {
            if ((worldToScreen (ASLToAGL ([_x] call _centerOf))) isNotEqualTo []) then {
                _x setVariable ["COMSPEC_ATAK_TocCamSeen", _now];
                _x setVariable ["COMSPEC_ATAK_TocSatSeen", _now];
            };
        } forEach _act;
        // Caméras d'écran, satellites, mosaïques : seulement les écrans allumés (proches et regardés).
        private _live = ["tick", _act] call comspec_atak_native_fnc_tocCam;
        ["tick", _act] call comspec_atak_native_fnc_tocSat;
        ["tick"] call comspec_atak_native_fnc_tocMosaic;
        private _release = {
            params ["_o", "_tex"];
            if (!(_o in _live) && {(_o getVariable ["COMSPEC_ATAK_ScreenCur", ""]) isEqualTo ""}) then {
                ["paint", [_o, _o getVariable ["COMSPEC_ATAK_TocSel", 0], _o getVariable ["COMSPEC_ATAK_TocOrig", ""]]] call _fn;
                ["surface", [_o, _o getVariable ["COMSPEC_ATAK_TocSel", 0], false]] call _fn;
            };
            _o setVariable ["COMSPEC_ATAK_TocTex", ""];
            _disps deleteAt _tex;
            _texObj deleteAt _tex;
            _local = _local - [_o];
        };
        {
            _x params ["_o", "_cfg", "_ok", "_dist", "_seen"];
            private _tex = _o getVariable ["COMSPEC_ATAK_TocTex", ""];
            private _on = _ok && {_seen};
            _o setVariable ["COMSPEC_ATAK_TocOn", _on];
            if (_on && {!(_o getVariable ["COMSPEC_ATAK_TocAct", false])}) then {
                _o setVariable ["COMSPEC_ATAK_TocAct", true];
                ["actions", _o] call _fn;
            };
            switch (true) do {
                // Diapositives prioritaires : la dalle va à fn_screenCast. Caméra en direct : fn_tocCam la tient.
                case (_on && {[_o] call _casting || {_o in _live}}): {
                    if (_tex isNotEqualTo "") then { [_o, _tex] call _release; };
                };
                case _on: {
                    // Retour devant l'écran : image tout de suite.
                    if !(_o getVariable ["COMSPEC_ATAK_TocWasOn", false]) then { _o setVariable ["COMSPEC_ATAK_TocWasOn", true]; _o setVariable ["COMSPEC_ATAK_TocAt", -10]; };
                    // Texture UI : une par écran, toutes demandées ensemble (chacune reconnue à son nom au chargement).
                    if (_tex isEqualTo "" && {_now >= (_o getVariable ["COMSPEC_ATAK_TocRetry", 0])}) then {
                        private _n = (uiNamespace getVariable ["COMSPEC_ATAK_TocSeq", 0]) + 1;
                        uiNamespace setVariable ["COMSPEC_ATAK_TocSeq", _n];
                        _tex = format ["comspec_atak_toc_%1", _n];
                        private _sel = [_o, _cfg select 2] call _face;
                        if (isNil { _o getVariable "COMSPEC_ATAK_TocOrig" }) then { _o setVariable ["COMSPEC_ATAK_TocOrig", ["orig", [_o, _sel]] call _fn]; };
                        _o setVariable ["COMSPEC_ATAK_TocTex", _tex];
                        _o setVariable ["COMSPEC_ATAK_TocSel", _sel];
                        _o setVariable ["COMSPEC_ATAK_TocReqAt", _now];
                        _texObj set [_tex, _o];
                        uiNamespace setVariable ["COMSPEC_ATAK_TocPending", [_o, _now, _tex]];
                        if !(_o getVariable ["COMSPEC_ATAK_TocReqLog", false]) then {
                            _o setVariable ["COMSPEC_ATAK_TocReqLog", true];
                            ["INFO", "SCREEN", format ["Écran TOC %1 (%2) : affichage %3, texture UI %4 demandée sur la dalle %5 (%6), à %7 m", typeOf _o, _cfg select 0, _cfg select 3, _tex, _sel, (getArray (configOf ([_o] call _hostOf) >> "hiddenSelections")) param [_sel, "?"], round _dist]] call comspec_atak_native_fnc_log;
                        };
                        ["paint", [_o, _sel, format ["%1ui(""COMSPEC_RscTocScreenTexture"",""%2"")", [_texSpec, "#(rgb,2048,512,1)"] select (([_o] call _ratioOf) >= 4), _tex]]] call _fn;
                        ["surface", [_o, _sel, true]] call _fn;
                        _local pushBackUnique _o;
                    };
                    private _disp = _disps getOrDefault [_tex, displayNull];
                    if (isNull _disp) then {
                        // Jamais chargée en 6 s alors que l'écran est devant soi (dalle masquée, mauvaise sélection) : trace une
                        // fois, nouvel essai sous un autre nom 4 s plus tard. Les autres écrans ne l'attendent plus.
                        if (_tex isNotEqualTo "" && {_now - (_o getVariable ["COMSPEC_ATAK_TocReqAt", _now]) > 6}) then {
                            if !(_o getVariable ["COMSPEC_ATAK_TocLoadWarn", false]) then {
                                _o setVariable ["COMSPEC_ATAK_TocLoadWarn", true];
                                ["WARNING", "TOC", format ["Écran TOC %1 : image non chargée en 6 s (dalle %2), nouvel essai", typeOf _o, _o getVariable ["COMSPEC_ATAK_TocSel", 0]]] call comspec_atak_native_fnc_log;
                            };
                            _o setVariable ["COMSPEC_ATAK_TocTex", ""];
                            _texObj deleteAt _tex;
                            _o setVariable ["COMSPEC_ATAK_TocRetry", _now + 4];
                        };
                    } else {
                        // Horloge, satellite, bandeau, mosaïque : chaque seconde ; le reste toutes les 2 s.
                        private _due = _now - (_o getVariable ["COMSPEC_ATAK_TocAt", -10]) >= ([1.9, 0.9] select ((_cfg select 3) in ["CLOCK", "SAT", "STRIP", "MOSAIC"]));
                        if (_due) then {
                            // Matière jour / nuit suivie (rien ne change tant que le soleil ne passe pas l'horizon).
                            ["surface", [_o, _o getVariable ["COMSPEC_ATAK_TocSel", 0], true]] call _fn;
                            _o setVariable ["COMSPEC_ATAK_TocAt", _now];
                            _o setVariable ["COMSPEC_ATAK_TocDraws", (_o getVariable ["COMSPEC_ATAK_TocDraws", 0]) + 1];
                            ["draw", [_o, _disp]] call _fn;
                            displayUpdate _disp;
                        };
                    };
                };
                // Figé : proche mais pas regardé, ou juste au-delà de la distance d'affichage. Dernière image gardée.
                case (_ok && {_dist < _hold} && {_tex isNotEqualTo ""}): {
                    _o setVariable ["COMSPEC_ATAK_TocWasOn", false];
                };
                // Veille : loin, éteint, retiré ou détruit. Texture d'origine remise, display abandonné.
                default {
                    _o setVariable ["COMSPEC_ATAK_TocWasOn", false];
                    if (_tex isNotEqualTo "") then { [_o, _tex] call _release; };
                };
            };
        } forEach _rows;
        missionNamespace setVariable ["COMSPEC_ATAK_TocLocal", _local];
    };
    case "build": {
        // Contrôles de la carte du poste et du panneau, créés une fois par display (réserves réutilisées à chaque image).
        // Mise en page en pixels virtuels 1600 x 900 (_R), polices fixes en pixels virtuels (28 px = texte courant) :
        // le texte ne dépend plus de la taille d'interface de qui regarde (safeZoneH, coupures du panneau RÉSEAU).
        private _d = _a;
        disableSerialization;
        // Tableau (fn_tocBoard) créé avant la carte : supprimé, il sera recréé par-dessus elle.
        private _brd = _d getVariable ["COMSPEC_ATAK_TocBoard", controlNull];
        if (!isNull _brd) then { ctrlDelete _brd; _d setVariable ["COMSPEC_ATAK_TocBoard", controlNull]; _d setVariable ["COMSPEC_ATAK_TocBoardUi", createHashMap]; };
        private _mk = {
            params ["_cls", ["_grp", controlNull], ["_fpx", 0], ["_bold", false]];
            private _c = if (isNull _grp) then { _d ctrlCreate [_cls, -1] } else { _d ctrlCreate [_cls, -1, _grp] };
            if (_fpx > 0) then {
                _c ctrlSetFontHeight (_fpx / _texH);
                _c ctrlSetFont (["RobotoCondensed", "RobotoCondensedBold"] select _bold);
                _c setVariable ["COMSPEC_ATAK_Fpx", _fpx];
                _c setVariable ["COMSPEC_ATAK_Bold", _bold];
            };
            _c ctrlSetPosition [0, 0, 0, 0];
            _c ctrlCommit 0;
            _c
        };
        private _box = { params ["_rect", "_rgba"]; private _c = ["RscText"] call _mk; _c ctrlSetBackgroundColor _rgba; _c ctrlSetPosition (_rect call _R); _c ctrlCommit 0; _c };
        [[0, 0, 1600, 900], [0.025, 0.03, 0.027, 1]] call _box;
        [[0, 0, 1600, 80], [0.045, 0.055, 0.048, 1]] call _box;
        private _accent = [[0, 80, 1600, 4], [0.28, 0.62, 1, 1]] call _box;
        private _title = ["COMSPEC_RscTocText"] call _mk;
        _title ctrlSetPosition ([24, 12, 1060, 62] call _R); _title ctrlCommit 0;
        private _clock = ["COMSPEC_RscTocText"] call _mk;
        _clock ctrlSetPosition ([1100, 20, 476, 48] call _R); _clock ctrlCommit 0;
        // ---- Carte (groupe : tout y est en pixels du groupe) ----
        private _grp = ["RscControlsGroupNoScrollbars"] call _mk;
        private _sea = ["RscText", _grp] call _mk;
        _sea ctrlSetBackgroundColor [0.05, 0.07, 0.08, 1];
        private _tiles = []; for "_i" from 1 to 36 do { _tiles pushBack (["RscPicture", _grp] call _mk); };
        // Voile sombre sur le fond (thème ATHENA) : symboles et plaques ressortent, la carte d'ensemble floue s'efface.
        private _dim = ["RscText", _grp] call _mk;
        _dim ctrlSetBackgroundColor [0.02, 0.035, 0.03, 0.22];
        private _lines = []; for "_i" from 1 to 28 do { private _l = ["RscText", _grp] call _mk; _l ctrlSetBackgroundColor [1, 1, 1, 0.13]; _lines pushBack _l; };
        private _gLbls = []; for "_i" from 1 to 28 do { private _t = ["COMSPEC_RscTocLabelCenter", _grp, 24, true] call _mk; _t ctrlSetBackgroundColor [0, 0, 0, 0.5]; _t ctrlSetTextColor [0.8, 0.86, 0.82, 1]; _gLbls pushBack _t; };
        private _places = []; for "_i" from 1 to 16 do { private _t = ["COMSPEC_RscTocLabelCenter", _grp, 28, false] call _mk; _t ctrlSetBackgroundColor [0, 0, 0, 0.4]; _t ctrlSetTextColor [0.88, 0.91, 0.89, 1]; _places pushBack _t; };
        private _segs = []; for "_i" from 1 to 260 do { private _c = ["RscPicture", _grp] call _mk; _c ctrlSetText "#(argb,8,8,3)color(1,1,1,1)"; _segs pushBack _c; }; // i18n:brut (texture)
        private _segLbls = []; for "_i" from 1 to 24 do { private _t = ["COMSPEC_RscTocLabel", _grp, 26, true] call _mk; _t ctrlSetBackgroundColor [0, 0, 0, 0.6]; _segLbls pushBack _t; };
        private _mkIcons = []; private _mkLbls = [];
        for "_i" from 1 to 40 do { _mkIcons pushBack (["RscPicture", _grp] call _mk); private _t = ["COMSPEC_RscTocLabel", _grp, 26, true] call _mk; _t ctrlSetBackgroundColor [0, 0, 0, 0.6]; _t ctrlSetTextColor [1, 1, 1, 1]; _mkLbls pushBack _t; };
        private _evIcons = []; for "_i" from 1 to 12 do { _evIcons pushBack (["RscPicture", _grp] call _mk); };
        private _uIcons = []; private _uLbls = [];
        for "_i" from 1 to 100 do {
            _uIcons pushBack (["RscPicture", _grp] call _mk);
            private _t = ["COMSPEC_RscTocLabel", _grp, 28, true] call _mk;
            _t ctrlSetBackgroundColor [0, 0, 0, 0.62];
            _uLbls pushBack _t;
        };
        private _scBg = ["RscText", _grp] call _mk; _scBg ctrlSetBackgroundColor [0, 0, 0, 0.62];
        private _scBar = ["RscText", _grp] call _mk; _scBar ctrlSetBackgroundColor [1, 1, 1, 1];
        private _scT0 = ["RscText", _grp] call _mk; _scT0 ctrlSetBackgroundColor [1, 1, 1, 1];
        private _scT1 = ["RscText", _grp] call _mk; _scT1 ctrlSetBackgroundColor [1, 1, 1, 1];
        private _scTxt = ["COMSPEC_RscTocLabel", _grp, 28, false] call _mk; _scTxt ctrlSetTextColor [1, 1, 1, 1];
        private _noBg = ["RscText", _grp] call _mk; _noBg ctrlSetBackgroundColor [0, 0, 0, 0.62];
        private _noArrow = ["COMSPEC_RscTocLabelCenter", _grp, 24, true] call _mk; _noArrow ctrlSetText "▲"; // i18n:brut (symbole)
        private _noTxt = ["COMSPEC_RscTocLabelCenter", _grp, 32, true] call _mk; _noTxt ctrlSetText (("N") call comspec_atak_native_fnc_tr);
        private _empty = ["COMSPEC_RscTocText", _grp] call _mk;
        // ---- Panneau ----
        private _pip = ["COMSPEC_RscTocLabel", controlNull, 28, true] call _mk; _pip ctrlSetTextColor [0.88, 0.69, 0.25, 1];
        private _netT = ["COMSPEC_RscTocLabel", controlNull, 36, true] call _mk; _netT ctrlSetTextColor [0.9, 0.94, 0.91, 1];
        private _netS = ["COMSPEC_RscTocLabelRight", controlNull, 28, false] call _mk; _netS ctrlSetTextColor [0.58, 0.64, 0.6, 1];
        private _cnt = [];
        {
            private _bg = ["RscText"] call _mk; _bg ctrlSetBackgroundColor [0.07, 0.082, 0.072, 1];
            private _n = ["COMSPEC_RscTocLabelCenter", controlNull, 44, true] call _mk; _n ctrlSetTextColor _x;
            private _l = ["COMSPEC_RscTocLabelCenter", controlNull, 28, false] call _mk; _l ctrlSetTextColor [0.58, 0.64, 0.6, 1];
            _cnt pushBack [_bg, _n, _l];
        } forEach [[0.36, 0.78, 0.42, 1], [0.88, 0.69, 0.25, 1], [0.66, 0.7, 0.68, 1], [0.48, 0.52, 0.5, 1]];
        private _grpT = ["COMSPEC_RscTocText"] call _mk;
        private _colH = [["COMSPEC_RscTocLabel", controlNull, 28, true] call _mk, ["COMSPEC_RscTocLabelRight", controlNull, 28, true] call _mk, ["COMSPEC_RscTocLabel", controlNull, 28, true] call _mk];
        { _x ctrlSetTextColor [0.58, 0.64, 0.6, 1]; } forEach _colH;
        private _tbl = [];
        for "_i" from 1 to 16 do {
            private _bg = ["RscText"] call _mk; _bg ctrlSetBackgroundColor [1, 1, 1, [0, 0.035] select ((_i mod 2) isEqualTo 0)];
            _tbl pushBack [_bg, ["COMSPEC_RscTocLabel", controlNull, 30, true] call _mk, ["COMSPEC_RscTocLabelRight", controlNull, 30, false] call _mk, ["COMSPEC_RscTocLabel", controlNull, 30, false] call _mk];
        };
        private _fluxBg = ["RscText"] call _mk; _fluxBg ctrlSetBackgroundColor [0.045, 0.055, 0.048, 1];
        private _fluxT = ["COMSPEC_RscTocLabel", controlNull, 32, true] call _mk; _fluxT ctrlSetTextColor [0.9, 0.94, 0.91, 1];
        private _fluxL = []; for "_i" from 1 to 16 do { _fluxL pushBack (["COMSPEC_RscTocText"] call _mk); };
        private _info = ["COMSPEC_RscTocText"] call _mk;
        private _orbN = ["COMSPEC_RscTocLabel", controlNull, 28, false] call _mk; _orbN ctrlSetTextColor [0.58, 0.64, 0.6, 1];
        private _orb1 = ["COMSPEC_RscTocText"] call _mk;
        private _orb2 = ["COMSPEC_RscTocText"] call _mk;
        private _sqT = ["COMSPEC_RscTocText"] call _mk;
        private _sqL = ["COMSPEC_RscTocText"] call _mk;
        // Écran d'escouade : caméra poitrine du chef en bas du panneau (16:9, 580 x 326).
        private _lc = ["RscPicture"] call _mk;
        private _ll = ["COMSPEC_RscTocText"] call _mk; _ll ctrlSetBackgroundColor [0, 0, 0, 0.62];
        createHashMapFromArray [["accent", _accent], ["title", _title], ["clock", _clock], ["grp", _grp], ["sea", _sea], ["tiles", _tiles], ["dim", _dim],
            ["lines", _lines], ["gLbls", _gLbls], ["places", _places], ["segs", _segs], ["segLbls", _segLbls], ["mkIcons", _mkIcons], ["mkLbls", _mkLbls],
            ["evIcons", _evIcons], ["uIcons", _uIcons], ["uLbls", _uLbls], ["scale", [_scBg, _scBar, _scT0, _scT1, _scTxt]], ["north", [_noBg, _noArrow, _noTxt]],
            ["empty", _empty], ["pip", _pip], ["netT", _netT], ["netS", _netS], ["cnt", _cnt], ["grpT", _grpT], ["colH", _colH], ["tbl", _tbl],
            ["fluxBg", _fluxBg], ["fluxT", _fluxT], ["fluxL", _fluxL], ["info", _info], ["orbN", _orbN], ["orb1", _orb1], ["orb2", _orb2],
            ["sqT", _sqT], ["sqL", _sqL], ["lead", _lc], ["leadLbl", _ll]]
    };
    case "draw": {
        _a params ["_o", "_d"];
        disableSerialization;
        private _cfg = [_o] call _cfgOf;
        _cfg params [["_name", "TOC"], ["_cside", ""], "", ["_view", "SPLIT"], ["_half", 0], ["_center", []]];
        // Vue caméra sans rendu en direct sur ce poste (trop loin, pas regardée, limite atteinte) : carte et flux.
        if (_view isEqualTo "CAM") then { _view = "SPLIT"; };
        // Satellite, horloge, tableaux du poste : dessinés par fn_tocBoard par-dessus la carte (non dessinée).
        if (_view in _boards) exitWith { ["draw", [_o, _d, _view]] call comspec_atak_native_fnc_tocBoard };
        ["hide", _d] call comspec_atak_native_fnc_tocBoard;
        private _squad = ["", _cfg param [9, ""]] select (_view isEqualTo "SQUAD");
        private _side = [_cside, str side group player] select (_cside isEqualTo "");
        private _feed = missionNamespace getVariable [format ["COMSPEC_ATAK_TocFeed_%1", _side], []];
        _feed params [["_ft", -1], ["_nodes", []], ["_events", []], ["_stats", []]];
        private _esc = { params ["_t"]; if !(_t isEqualType "") then { _t = str _t; }; { _t = [_t, _x select 0, _x select 1] call CBA_fnc_replace; } forEach [["&", "&amp;"], ["<", "&lt;"], [">", "&gt;"]]; _t };
        private _sideRgb = (createHashMapFromArray [["WEST", [0.28, 0.62, 1]], ["EAST", [0.95, 0.3, 0.25]], ["GUER", [0.36, 0.85, 0.42]], ["CIV", [0.75, 0.5, 0.95]]]) getOrDefault [_side, [0.28, 0.62, 1]];
        // PIP du poste : ce que l'opérateur du PC regarde (fn_tocLaptop). L'opérateur du poste de même nom passe devant.
        private _op = [];
        if (_view isEqualTo "TOC") then {
            private _cands = (allPlayers select { !isNull _x && {alive _x} && {((_x getVariable ["COMSPEC_ATAK_TocView", []]) param [0, ""]) isEqualTo _side} }) apply { _x getVariable "COMSPEC_ATAK_TocView" };
            private _i = _cands findIf { (_x param [5, ""]) isEqualTo _name };
            _op = _cands param [_i max 0, []];
            if ((count _op) < 8) then { _view = "SPLIT"; _op = []; };
        };
        private _ui = _d getVariable ["COMSPEC_ATAK_TocUi", createHashMap];
        if ((count _ui) isEqualTo 0) then { _ui = ["build", _d] call _fn; _d setVariable ["COMSPEC_ATAK_TocUi", _ui]; };
        private _setTxt = {
            params ["_c", "_s"];
            if ((_c getVariable ["COMSPEC_ATAK_Txt", ""]) isNotEqualTo _s) then {
                _c setVariable ["COMSPEC_ATAK_Txt", _s];
                _c ctrlSetStructuredText parseText ((_s) call comspec_atak_native_fnc_tr);
            };
        };
        private _place2 = { params ["_c", "_r"]; _c ctrlSetPosition (_r call _R); _c ctrlCommit 0; _c ctrlShow true; };

        // ---- En-tête : titre (FLASH, PIP), heure ZULU et locale, état du flux ----
        (_ui get "accent") ctrlSetBackgroundColor (_sideRgb + [1]);
        private _age = [-1, round ((serverTime - _ft) max 0)] select (_ft >= 0);
        private _head = (["title", _view] call _fn) select 0;
        private _tt = format ["<t size='1.57' font='COMSPEC_BarlowBold'>%1 · %2</t>", _head, [[_name, 34] call _cut] call _esc];
        if ((count _op) >= 8) then {
            _tt = format ["<t size='1.57' font='COMSPEC_BarlowBold'>TOC · %1</t>  <t size='1.15' color='#e0b040'>PIP du poste</t>", [[_op param [5, _name], 22] call _cut] call _esc];
        };
        if (_squad isNotEqualTo "") then {
            _tt = format ["<t size='1.57' font='COMSPEC_BarlowBold'>%1 · %2</t>", _head, [[_squad, 34] call _cut] call _esc];
        };
        // FLASH du poste (fn_tocRoom) : en tête de l'écran, liseré rouge, 3 minutes.
        private _fl = ["flash", _side] call comspec_atak_native_fnc_tocBoard;
        if ((count _fl) > 0) then {
            (_ui get "accent") ctrlSetBackgroundColor [0.9, 0.2, 0.15, 1];
            _tt = format ["<t size='1.57' font='COMSPEC_BarlowBold' color='#ff5a4a'>FLASH</t> <t size='1.3'>· %1</t>", [[_fl select 0, 52] call _cut] call _esc];
        };
        [_ui get "title", _tt] call _setTxt;
        private _live = switch (true) do {
            case (_age < 0): { "<t color='#e05a4a'>● HORS LIGNE</t>" };
            case (_age < 12): { "<t color='#5cc76b'>● EN DIRECT</t>" };
            // « 12:35Z  01:27  ● RETARD 999 s » : 29 caractères x 0,46 x 32 px = 427 px sur 476.
            default { format ["<t color='#e0b040'>● RETARD</t> <t color='#8a9a93'>%1 s</t>", _age min 999] };
        };
        [_ui get "clock", format ["<t align='right' size='1.15'>%1Z  <t color='#8a9a93'>%2</t>  %3</t>",
            (["zulu"] call comspec_atak_native_fnc_tocRoom) select [0, 5], [dayTime, "HH:MM"] call BIS_fnc_timeToString, _live]] call _setTxt;

        // ---- Mise en page par affichage (pixels virtuels) : [carte, panneau] ----
        // Preuve de tenue (1600 x 900, texte courant 28 px, ligne 38 px) dans « layout » plus bas et dans le CHANGELOG.
        private _layouts = createHashMapFromArray [
            ["SPLIT", [[0, 84, 1000, 816], [1012, 580]]],
            ["TOC", [[0, 84, 1000, 816], [1012, 580]]],
            ["MAP", [[0, 84, 1600, 816], []]],
            ["FEED", [[], [24, 1552]]],
            ["NET", [[], [24, 1552]]],
            ["ORBAT", [[], [24, 1552]]],
            ["SQUAD", [[0, 84, 1000, 816], [1012, 580]]]
        ];
        (_layouts getOrDefault [_view, _layouts get "SPLIT"]) params ["_mapR", "_panR"];
        private _isMap = (count _mapR) isEqualTo 4;
        // PIP : cadrage de la carte imposé par le PC de l'opérateur.
        if ((count _op) >= 8) then { _center = _op select 1; _half = _op select 2; };
        // Écran d'escouade : carte cadrée sur les seuls nœuds du groupe (400 m de demi-largeur au moins).
        private _sqNodes = _nodes select { (_x param [7, ""]) isEqualTo _squad && {_squad isNotEqualTo ""} };
        if ((count _sqNodes) > 0) then {
            private _xs = _sqNodes apply { (_x select 2) select 0 };
            private _ys = _sqNodes apply { (_x select 2) select 1 };
            _center = [((selectMin _xs) + (selectMax _xs)) / 2, ((selectMin _ys) + (selectMax _ys)) / 2];
            _half = ((((selectMax _xs) - (selectMin _xs)) max (((selectMax _ys) - (selectMin _ys)) * 16 / 9)) * 0.75) max 400;
        };
        private _grp = _ui get "grp";
        _grp ctrlShow _isMap;

        // ---- Carte -------------------------------------------------------------------------------------------
        if (_isMap) then {
            _grp ctrlSetPosition (_mapR call _R); _grp ctrlCommit 0;
            _mapR params ["", "", "_mwPx", "_mhPx"];
            // Cadrage : centre et demi-largeur choisis ; sinon le réseau autour de son centre médian, assez large pour
            // 85 % des nœuds (un nœud isolé au bout de l'île ne dézoome plus tout l'écran), entre 700 m et 4 km.
            private _cx = worldSize / 2; private _cy = worldSize / 2; private _hw = _half;
            private _autoC = (count _center) < 2;
            if (!_autoC) then { _cx = _center select 0; _cy = _center select 1; };
            if ((count _nodes) > 0 && {_autoC || {_hw <= 0}}) then {
                private _xs = _nodes apply { (_x select 2) select 0 }; _xs sort true;
                private _ys = _nodes apply { (_x select 2) select 1 }; _ys sort true;
                private _mx = _xs select (floor ((count _xs) / 2));
                private _my = _ys select (floor ((count _ys) / 2));
                if (_autoC) then { _cx = _mx; _cy = _my; };
                if (_hw <= 0) then {
                    private _ds = _nodes apply { private _p = _x select 2; (abs ((_p select 0) - _cx)) max ((abs ((_p select 1) - _cy)) * _mwPx / _mhPx) };
                    _ds sort true;
                    _hw = (((_ds select ((floor ((count _ds) * 0.85)) min ((count _ds) - 1))) * 1.3) max 700) min 4000;
                };
            } else {
                if (_autoC) then { private _p = getPosASL _o; _cx = _p select 0; _cy = _p select 1; };
            };
            if (_hw <= 0) then { _hw = 1500; };
            _hw = (_hw max 100) min (worldSize max 1000);
            _o setVariable ["COMSPEC_ATAK_TocShown", [[_cx, _cy], _hw]];
            // Vue d'ensemble (île entière ou presque) : symboles regroupés plus large, ni plaques de marqueurs ni villages.
            private _far = _hw > 3500;
            private _k = _mwPx / (2 * _hw);           // pixels virtuels par mètre
            private _hh = _hw * _mhPx / _mwPx;
            private _toG = { params ["_px", "_py"]; [(_px - (_cx - _hw)) * _k / _texW, ((_cy + _hh) - _py) * _k / _texH] };
            private _inView = { params ["_px", "_py"]; _px > (_cx - _hw) && {_px < (_cx + _hw)} && {_py > (_cy - _hh)} && {_py < (_cy + _hh)} };
            (_ui get "sea") ctrlSetPosition ([0, 0, _mwPx, _mhPx] call _R); (_ui get "sea") ctrlCommit 0;
            (_ui get "dim") ctrlSetPosition ([0, 0, _mwPx, _mhPx] call _R); (_ui get "dim") ctrlCommit 0;
            // Fond : photo de la carte (mod @COMSPEC_ATAK_Photo), sinon carte d'ensemble du terrain (pictureMap, assombrie).
            private _tiles = _ui get "tiles";
            private _used = 0;
            private _info = ["info"] call comspec_atak_native_fnc_photoMap;
            if ((count _info) > 0 && {(_info param [6, ""]) isEqualTo "pbo"}) then {
                _info params ["_ext", "_ts", "_zMin", "_zMax", "_path", "_fx"];
                private _zPaths = _info param [10, createHashMap];
                private _mpp = 2 * _hw / (_mwPx * 1.28);  // la texture a 1,28 pixel réel par pixel virtuel (2048 / 1600)
                private _z = (ceil (log (_ext / (_ts * _mpp)) / log 2) max _zMin) min ((_info param [11, _zMax]) min _zMax);
                private _rng = {
                    params ["_zz"];
                    private _T = _ext / (2 ^ _zz);
                    private _n = 2 ^ _zz;
                    [_T, (floor ((_cx - _hw) / _T)) max 0, (floor ((_cx + _hw - 0.001) / _T)) min (_n - 1), (floor ((_ext - (_cy + _hh)) / _T)) max 0, (floor ((_ext - (_cy - _hh) - 0.001) / _T)) min (_n - 1)]
                };
                private _r = [_z] call _rng;
                while { _z > _zMin && {(((_r select 2) - (_r select 1) + 1) * ((_r select 4) - (_r select 3) + 1)) > 36} } do { _z = _z - 1; _r = [_z] call _rng; };
                _r params ["_T", "_x0", "_x1", "_y0", "_y1"];
                private _haveP = uiNamespace getVariable ["COMSPEC_ATAK_PhotoHave", createHashMap];
                uiNamespace setVariable ["COMSPEC_ATAK_PhotoHave", _haveP];
                for "_ty" from _y0 to _y1 do {
                    for "_tx" from _x0 to _x1 do {
                        private _zp = _zPaths getOrDefault [_z, ""];
                        private _f = if (_zp isNotEqualTo "") then { _zp + str _tx + "\" + str _ty + _fx } else { _path + str _z + "\" + str _tx + "\" + str _ty + _fx };
                        private _h = _haveP get _f;
                        if (isNil "_h") then { _h = fileExists _f; _haveP set [_f, _h]; };
                        if (_h && {_used < 36}) then {
                            private _c = _tiles select _used;
                            ([_tx * _T, _ext - _ty * _T] call _toG) params ["_gx", "_gy"];
                            _c ctrlSetText ((_f) call comspec_atak_native_fnc_tr);
                            _c ctrlSetTextColor [0.92, 0.94, 0.92, 1];
                            _c ctrlSetPosition [_gx, _gy, _T * _k / _texW, _T * _k / _texH];
                            _c ctrlCommit 0;
                            _c ctrlShow true;
                            _used = _used + 1;
                        };
                    };
                };
            };
            if (_used isEqualTo 0) then {
                private _pm = getText (configFile >> "CfgWorlds" >> worldName >> "pictureMap");
                if (_pm isNotEqualTo "") then {
                    private _c = _tiles select 0;
                    ([0, worldSize] call _toG) params ["_gx", "_gy"];
                    _c ctrlSetText ((_pm) call comspec_atak_native_fnc_tr);
                    _c ctrlSetTextColor [0.74, 0.78, 0.75, 1];
                    _c ctrlSetPosition [_gx, _gy, worldSize * _k / _texW, worldSize * _k / _texH];
                    _c ctrlCommit 0;
                    _c ctrlShow true;
                    _used = 1;
                };
            };
            for "_i" from _used to ((count _tiles) - 1) do { (_tiles select _i) ctrlShow false; };
            // Plaques sans chevauchement, en pixels du groupe : cases prises (symboles, puces fixes, plaques posées).
            private _taken = [];
            private _free = {
                params ["_x0", "_y0", "_w", "_h"];
                (_taken findIf { _x0 < ((_x select 0) + (_x select 2)) && {(_x0 + _w) > (_x select 0)} && {_y0 < ((_x select 1) + (_x select 3))} && {(_y0 + _h) > (_x select 1)} }) < 0
            };
            // Huit places autour du point (droite, gauche, dessus, dessous, diagonales) ; mode 1 : centrée sur le point
            // (noms de lieux). Jamais hors du cadre de la carte (plaques coupées au bord). Sans place libre : rien.
            private _place = {
                params ["_px", "_py", "_w", "_h", "_r", ["_mode", 0]];
                private _q = _r * 0.7;
                private _cands = [
                    [[_px + _r, _py - _h / 2], [_px - _r - _w, _py - _h / 2], [_px - _w / 2, _py - _r - _h], [_px - _w / 2, _py + _r],
                     [_px + _q, _py - _q - _h], [_px + _q, _py + _q], [_px - _q - _w, _py - _q - _h], [_px - _q - _w, _py + _q]],
                    [[_px - _w / 2, _py - _h / 2], [_px - _w / 2, _py - _h - 8], [_px - _w / 2, _py + 8]]
                ] select _mode;
                private _res = [];
                {
                    _x params ["_x0", "_y0"];
                    if (_x0 >= 2 && {_y0 >= 2} && {(_x0 + _w) <= (_mwPx - 2)} && {(_y0 + _h) <= (_mhPx - 2)} && {[_x0, _y0, _w, _h] call _free}) exitWith {
                        _res = [_x0, _y0];
                        _taken pushBack [_x0, _y0, _w, _h];
                    };
                } forEach _cands;
                _res
            };
            // Largeur d'une plaque d'une ligne : 0,52 x police par caractère en gras (estimation large) + marges.
            private _plateW = { params ["_t", "_f"]; ((count _t) * 0.52 * _f) + 16 };
            // Échelle (bas gauche) et nord (haut droite) : posés d'abord, rien ne passe dessous.
            private _mpp = 2 * _hw / _mwPx;
            private _raw = 200 * _mpp;
            private _p10 = 10 ^ (floor (log _raw));
            private _nice = _p10;
            { if (_p10 * _x <= _raw) then { _nice = _p10 * _x; }; } forEach [1, 2, 5];
            private _barPx = _nice / _mpp;
            // Quadrillage : pas de 100 m à 10 km, une dizaine de lignes par axe au plus.
            private _step = 100;
            { if ((2 * _hw / _step) > 10) then { _step = _x; }; } forEach [200, 500, 1000, 2000, 5000, 10000];
            private _sTxt = format ["%1 · %2", [format ["%1 m", _nice], format ["%1 km", _nice / 1000]] select (_nice >= 1000),
                format ["quadrillage %1", [format ["%1 m", _step], format ["%1 km", _step / 1000]] select (_step >= 1000)]];
            private _sTw = ((count _sTxt) * 0.46 * 28) + 12;
            (_ui get "scale") params ["_scBg", "_scBar", "_scT0", "_scT1", "_scTxt"];
            private _sy = _mhPx - 58;
            [_scBg, [16, _sy, 28 + _barPx + 14 + _sTw, 46]] call _place2;
            [_scBar, [30, _sy + 22, _barPx, 6]] call _place2;
            [_scT0, [30, _sy + 12, 3, 22]] call _place2;
            [_scT1, [30 + _barPx - 3, _sy + 12, 3, 22]] call _place2;
            [_scTxt, _sTxt, _sTw] call _lbl;
            [_scTxt, [30 + _barPx + 14, _sy + 4, _sTw, 38]] call _place2;
            _taken pushBack [16, _sy, 28 + _barPx + 14 + _sTw, 46];
            (_ui get "north") params ["_noBg", "_noArrow", "_noTxt"];
            [_noBg, [_mwPx - 68, 12, 56, 66]] call _place2;
            [_noArrow, [_mwPx - 68, 14, 56, 28]] call _place2;
            [_noTxt, [_mwPx - 68, 40, 56, 36]] call _place2;
            _taken pushBack [_mwPx - 68, 12, 56, 66];
            // Lignes du quadrillage et leurs numéros (hectomètres, comme les grilles des comptes rendus) au bord haut et gauche.
            private _g3 = { private _s = str (floor (_this / 100)); while { (count _s) < 3 } do { _s = "0" + _s; }; _s };
            private _lines = _ui get "lines";
            private _gLbls = _ui get "gLbls";
            private _li = 0;
            private _gi = 0;
            for "_gx" from ((ceil ((_cx - _hw) / _step)) * _step) to (_cx + _hw) step _step do {
                if (_li < 14) then {
                    private _px = (([_gx, 0] call _toG) select 0) * _texW;
                    [_lines select _li, [_px - 1, 0, 2, _mhPx]] call _place2;
                    _li = _li + 1;
                    if (_px > 40 && {_px < (_mwPx - 120)}) then {
                        private _t = _gLbls select _gi;
                        _t ctrlSetText ((_gx call _g3) call comspec_atak_native_fnc_tr);
                        [_t, [_px - 26, 4, 52, 30]] call _place2;
                        _taken pushBack [_px - 26, 4, 52, 30];
                        _gi = _gi + 1;
                    };
                };
            };
            for "_gy" from ((ceil ((_cy - _hh) / _step)) * _step) to (_cy + _hh) step _step do {
                if (_li < 28) then {
                    private _py = (([0, _gy] call _toG) select 1) * _texH;
                    [_lines select _li, [0, _py - 1, _mwPx, 2]] call _place2;
                    _li = _li + 1;
                    if (_py > 44 && {_py < (_mhPx - 80)}) then {
                        private _t = _gLbls select _gi;
                        _t ctrlSetText ((_gy call _g3) call comspec_atak_native_fnc_tr);
                        [_t, [4, _py - 15, 52, 30]] call _place2;
                        _taken pushBack [4, _py - 15, 52, 30];
                        _gi = _gi + 1;
                    };
                };
            };
            for "_i" from _li to ((count _lines) - 1) do { (_lines select _i) ctrlShow false; };
            for "_i" from _gi to ((count _gLbls) - 1) do { (_gLbls select _i) ctrlShow false; };
            // Nœuds voisins (même case de 64 px, 110 px en vue d'ensemble) regroupés en un symbole : le plus frais donne
            // le nom, « +n » le reste. Symboles posés avant toute plaque.
            private _cell = [64, 110] select _far;
            private _cells = createHashMap;
            {
                private _p = _x select 2;
                if ([_p select 0, _p select 1] call _inView) then {
                    ([_p select 0, _p select 1] call _toG) params ["_gx", "_gy"];
                    private _ck = [floor (_gx * _texW / _cell), floor (_gy * _texH / _cell)];
                    private _lst = _cells getOrDefault [_ck, []];
                    _lst pushBack _x;
                    _cells set [_ck, _lst];
                };
            } forEach _nodes;
            private _clusters = (values _cells) apply {
                private _m = [_x, [], { (_x select 4) min 3 }, "ASCEND"] call BIS_fnc_sortBy;
                private _sum = 0;
                { _sum = _sum + (_x param [8, 1]); } forEach _m;
                [(_m select 0) select 4, _m select 0, _sum, _m]
            };
            _clusters = [_clusters, [], { _x select 0 }, "ASCEND"] call BIS_fnc_sortBy;
            _clusters = _clusters select [0, 99];
            {
                (_x select 1) params ["", "", "_p"];
                ([_p select 0, _p select 1] call _toG) params ["_gx", "_gy"];
                _taken pushBack [_gx * _texW - 20, _gy * _texH - 20, 40, 40];
            } forEach _clusters;
            // Marqueurs de la mission visibles par ce joueur : symboles (deux presque au même point : un seul).
            private _mkSeen = createHashMap;
            private _mkIcons = _ui get "mkIcons";
            private _mkLbls = _ui get "mkLbls";
            private _mi = 0;
            private _mkTodo = [];
            private _colCache = uiNamespace getVariable ["COMSPEC_ATAK_TocMkCol", createHashMap];
            uiNamespace setVariable ["COMSPEC_ATAK_TocMkCol", _colCache];
            private _mkCol = {
                params ["_m", ["_def", [1, 1, 1, 1]]];
                private _col = _colCache getOrDefault [markerColor _m, []];
                if (_col isEqualTo []) then {
                    _col = (getArray (configFile >> "CfgMarkerColors" >> (markerColor _m) >> "color")) apply { if (_x isEqualType "") then { call compile _x } else { _x } };
                    if ((count _col) < 4) then { _col = _def; };
                    _colCache set [markerColor _m, _col];
                };
                _col
            };
            {
                if (_mi < 40 && {(markerShape _x) isEqualTo "ICON"} && {(markerAlpha _x) > 0}) then {
                    private _p = getMarkerPos _x;
                    if ([_p select 0, _p select 1] call _inView) then {
                        private _icon = getText (configFile >> "CfgMarkers" >> (markerType _x) >> "icon");
                        ([_p select 0, _p select 1] call _toG) params ["_gx", "_gy"];
                        private _mkCk = [floor (_gx * _texW / 28), floor (_gy * _texH / 28)];
                        if (_icon isNotEqualTo "" && {!(_mkCk in _mkSeen)}) then {
                            _mkSeen set [_mkCk, true];
                            private _c = _mkIcons select _mi;
                            _c ctrlSetText ((_icon) call comspec_atak_native_fnc_tr);
                            _c ctrlSetTextColor ([_x, [0, 0, 0, 1]] call _mkCol);
                            _c ctrlSetPosition [_gx - 15 / _texW, _gy - 15 / _texH, 30 / _texW, 30 / _texH]; _c ctrlCommit 0; _c ctrlShow true;
                            _taken pushBack [_gx * _texW - 15, _gy * _texH - 15, 30, 30];
                            _mkTodo pushBack [_mi, _gx, _gy, markerText _x];
                            _mi = _mi + 1;
                        };
                    };
                };
            } forEach allMapMarkers;
            for "_i" from _mi to 39 do { (_mkIcons select _i) ctrlShow false; };
            // Comptes rendus et alertes géolocalisés des 20 dernières minutes.
            private _evIcons = _ui get "evIcons";
            private _ei = 0;
            {
                _x params ["", "", "", "", "", "", ["_p", []], ["_at", -1e9]];
                if (_ei < 12 && {(count _p) >= 2} && {serverTime - _at < 1200} && {[_p select 0, _p select 1] call _inView}) then {
                    ([_p select 0, _p select 1] call _toG) params ["_gx", "_gy"];
                    private _c = _evIcons select _ei;
                    _c ctrlSetText (("\A3\ui_f\data\map\markers\military\warning_CA.paa") call comspec_atak_native_fnc_tr);
                    _c ctrlSetTextColor [1, 0.35, 0.2, 1];
                    _c ctrlSetPosition [_gx - 17 / _texW, _gy - 17 / _texH, 34 / _texW, 34 / _texH]; _c ctrlCommit 0; _c ctrlShow true;
                    _taken pushBack [_gx * _texW - 17, _gy * _texH - 17, 34, 34];
                    _ei = _ei + 1;
                };
            } forEach _events;
            for "_i" from _ei to 11 do { (_evIcons select _i) ctrlShow false; };
            // Nœuds ATAK : symbole OTAN ami, couleur du camp ; ancien pâli, perdu et hors ligne en gris. Plaques d'abord
            // pour eux (priorité), puis marqueurs, dessins, noms de lieux.
            private _uIcons = _ui get "uIcons";
            private _uLbls = _ui get "uLbls";
            private _ui0 = 0;
            {
                _x params ["", "_lead", "_sum", "_mem"];
                _lead params ["", "_cs", "_p", "", "_fr", "_kind"];
                ([_p select 0, _p select 1] call _toG) params ["_gx", "_gy"];
                private _tex = format ["\A3\ui_f\data\map\markers\nato\b_%1.paa", (createHashMapFromArray [["veh", "motor_inf"], ["air", "air"], ["boat", "naval"], ["armor", "armor"]]) getOrDefault [_kind, "inf"]];
                private _col = switch (_fr) do {
                    case 0: { _sideRgb + [1] };
                    case 1: { (_sideRgb apply { _x * 0.75 }) + [0.75] };
                    case 2: { [0.62, 0.62, 0.62, 0.85] };
                    default { [0.45, 0.45, 0.45, 0.7] };
                };
                // PIP : le groupe choisi par l'opérateur ressort en jaune ; écran d'escouade : son groupe.
                private _pickG = _op param [4, ""];
                private _picked = ((count _op) >= 5 && {(_op param [3, ""]) isEqualTo "GROUPES"} && {_pickG isNotEqualTo ""} && {(_mem findIf { (_x param [7, ""]) isEqualTo _pickG }) >= 0})
                    || {_squad isNotEqualTo "" && {(_mem findIf { (_x param [7, ""]) isEqualTo _squad }) >= 0}};
                private _c = _uIcons select _ui0;
                _c ctrlSetText ((_tex) call comspec_atak_native_fnc_tr);
                _c ctrlSetTextColor ([_col, [1, 0.85, 0.3, 1]] select _picked);
                _c ctrlSetPosition [_gx - 20 / _texW, _gy - 20 / _texH, 40 / _texW, 40 / _texH]; _c ctrlCommit 0; _c ctrlShow true;
                private _lt = ([_cs, 18] call _cut) + (["", format [" +%1", _sum - 1]] select (_sum > 1)) + ([["", " · ancien", " · perdu", " · hors ligne"] select (_fr min 3), ""] select _far);
                private _lw = [_lt, 28] call _plateW;
                private _t = _uLbls select _ui0;
                private _at = [_gx * _texW, _gy * _texH, _lw, 36, 24] call _place;
                if ((count _at) > 0) then {
                    [_t, _lt, _lw] call _lbl;
                    _t ctrlSetTextColor ([[[1, 1, 1, 1], [0.8, 0.8, 0.8, 1]] select (_fr >= 2), [1, 0.85, 0.3, 1]] select _picked);
                    [_t, [_at select 0, _at select 1, _lw, 36]] call _place2;
                } else { _t ctrlShow false; };
                _ui0 = _ui0 + 1;
            } forEach _clusters;
            // PIP : objectif de l'ordre en cours de rédaction sur le PC.
            if ((count _op) >= 9) then {
                private _opos = _op param [8, []];
                if ((count _opos) >= 2 && {[_opos select 0, _opos select 1] call _inView} && {_ui0 < 100}) then {
                    ([_opos select 0, _opos select 1] call _toG) params ["_gx", "_gy"];
                    private _c = _uIcons select _ui0;
                    _c ctrlSetText (("\A3\ui_f\data\map\markers\military\objective_CA.paa") call comspec_atak_native_fnc_tr);
                    _c ctrlSetTextColor [1, 0.85, 0.2, 1];
                    _c ctrlSetPosition [_gx - 22 / _texW, _gy - 22 / _texH, 44 / _texW, 44 / _texH]; _c ctrlCommit 0; _c ctrlShow true;
                    private _t = _uLbls select _ui0;
                    private _lw = ["OBJECTIF", 28] call _plateW;
                    private _oAt = [_gx * _texW, _gy * _texH, _lw, 36, 26] call _place;
                    if ((count _oAt) > 0) then {
                        [_t, "OBJECTIF", _lw] call _lbl;
                        _t ctrlSetTextColor [1, 0.9, 0.5, 1];
                        [_t, [_oAt select 0, _oAt select 1, _lw, 36]] call _place2;
                    } else { _t ctrlShow false; };
                    _ui0 = _ui0 + 1;
                };
            };
            for "_i" from _ui0 to 99 do { (_uIcons select _i) ctrlShow false; (_uLbls select _i) ctrlShow false; };
            // Plaques des marqueurs (pas en vue d'ensemble).
            {
                _x params ["_i", "_gx", "_gy", "_txt"];
                private _t = _mkLbls select _i;
                private _s = [_txt, 20] call _cut;
                private _w = [_s, 26] call _plateW;
                private _at = if (_far || {_s isEqualTo ""}) then { [] } else { [_gx * _texW, _gy * _texH, _w, 32, 18] call _place };
                if ((count _at) > 0) then { [_t, _s, _w] call _lbl; [_t, [_at select 0, _at select 1, _w, 32]] call _place2; } else { _t ctrlShow false; };
            } forEach _mkTodo;
            for "_i" from _mi to 39 do { (_mkLbls select _i) ctrlShow false; };
            // Dessins et plans de l'ATAK et du PC du poste (mêmes marqueurs, même plan du camp), traits épaissis pour être
            // lus de loin : traits et formes de joueur (polylignes, rectangles, ellipses), plan du camp (fn_planServer).
            private _segs = _ui get "segs";
            private _segLbls = _ui get "segLbls";
            private _si = 0; private _li2 = 0;
            private _seg = {
                params ["_a", "_b", "_col", "_th"];
                if (_si >= (count _segs)) exitWith {};
                if !(([_a select 0, _a select 1] call _inView) || {[_b select 0, _b select 1] call _inView}) exitWith {};
                ([_a select 0, _a select 1] call _toG) params ["_ax", "_ay"];
                ([_b select 0, _b select 1] call _toG) params ["_bx", "_by"];
                private _dx = (_bx - _ax) * _texW; private _dy = (_by - _ay) * _texH;
                private _len = sqrt (_dx * _dx + _dy * _dy);
                if (_len < 1) exitWith {};
                private _mx = (_ax + _bx) / 2 * _texW; private _my = (_ay + _by) / 2 * _texH;
                private _c = _segs select _si;
                _c ctrlSetPosition [(_mx - _len / 2) / _texW, (_my - _th / 2) / _texH, _len / _texW, _th / _texH];
                _c ctrlCommit 0;
                _c ctrlSetAngle [_dy atan2 _dx, 0.5, 0.5];
                _c ctrlSetTextColor _col;
                _c ctrlShow true;
                _si = _si + 1;
            };
            private _segLbl = {
                params ["_p", "_txt", "_col"];
                if (_far || {_txt isEqualTo ""} || {_li2 >= (count _segLbls)} || {!([_p select 0, _p select 1] call _inView)}) exitWith {};
                ([_p select 0, _p select 1] call _toG) params ["_gx", "_gy"];
                private _s = [_txt, 20] call _cut;
                private _w = [_s, 26] call _plateW;
                private _at = [_gx * _texW, _gy * _texH, _w, 32, 12] call _place;
                if ((count _at) isEqualTo 0) exitWith {};
                private _t = _segLbls select _li2;
                [_t, _s, _w] call _lbl;
                _t ctrlSetTextColor _col;
                [_t, [_at select 0, _at select 1, _w, 32]] call _place2;
                _li2 = _li2 + 1;
            };
            {
                private _m = _x;
                private _sh = markerShape _m;
                if (_sh in ["POLYLINE", "RECTANGLE", "ELLIPSE"] && {(markerAlpha _m) > 0}) then {
                    private _col = [_m] call _mkCol;
                    private _sk = missionNamespace getVariable [format ["COMSPEC_ATAK_SK_%1", _m regexReplace ["[^A-Za-z0-9]", "_"]], []];
                    private _th = 3 + 2 * ((_sk param [0, 1]) max 1 min 3);
                    private _pts = [];
                    if (_sh isEqualTo "POLYLINE") then {
                        private _pl = markerPolyline _m;
                        for "_i" from 0 to ((count _pl) - 2) step 2 do { _pts pushBack [_pl select _i, _pl select (_i + 1)]; };
                        if (_sk param [2, false]) then { _pts pushBack (_pts param [0, [0, 0]]); };
                    } else {
                        // Rectangle et ellipse : contour, tourné comme le marqueur.
                        private _c0 = getMarkerPos _m; (getMarkerSize _m) params ["_sa", "_sb"]; private _dir = markerDir _m;
                        private _rot = { params ["_lx", "_ly"]; [(_c0 select 0) + _lx * cos _dir + _ly * sin _dir, (_c0 select 1) - _lx * sin _dir + _ly * cos _dir] };
                        if (_sh isEqualTo "RECTANGLE") then { _pts = [[-_sa, -_sb], [_sa, -_sb], [_sa, _sb], [-_sa, _sb], [-_sa, -_sb]] apply { _x call _rot }; } else {
                            for "_q" from 0 to 16 do { _pts pushBack ([_sa * sin (_q * 22.5), _sb * cos (_q * 22.5)] call _rot); };
                        };
                    };
                    for "_i" from 0 to ((count _pts) - 2) do { [_pts select _i, _pts select (_i + 1), _col, _th] call _seg; };
                    [_pts param [0, [0, 0]], (_sk param [3, markerText _m]), [1, 1, 1, 1]] call _segLbl;
                };
            } forEach allMapMarkers;
            private _pcat = [] call comspec_atak_native_fnc_planCatalog;
            {
                _x params ["", ["_kind", ""], ["_pts", []], ["_txt", ""], ["_ck", "WHITE"], "", "", ["_w", 2]];
                private _col = +((_pcat get "colors") getOrDefault [_ck, [1, 1, 1, 1]]);
                private _geo = ((_pcat get "kinds") getOrDefault [_kind, []]) param [3, "LINE"];
                private _ring = +_pts;
                if (_geo isEqualTo "AREA" && {(count _pts) >= 3}) then { _ring pushBack (_pts select 0); };
                for "_i" from 0 to ((count _ring) - 2) do { [_ring select _i, _ring select (_i + 1), _col, 3 + 2 * ((_w max 1) min 3)] call _seg; };
                if ((count _pts) isEqualTo 1) then { [_pts select 0, [((_pts select 0) select 0) + 1, (_pts select 0) select 1], _col, 14] call _seg; };
                [_pts param [0, [0, 0]], [_txt, _kind] select (_txt isEqualTo ""), _col] call _segLbl;
            } forEach ((missionNamespace getVariable [format ["COMSPEC_ATAK_Plan_%1", _side], [0, [], ""]]) param [1, []]);
            for "_i" from _si to ((count _segs) - 1) do { (_segs select _i) ctrlShow false; };
            for "_i" from _li2 to ((count _segLbls) - 1) do { (_segLbls select _i) ctrlShow false; };
            // Noms de lieux en dernier, là où il reste de la place : capitales et villes (villages aussi de près).
            // Cache par cadrage (nearestLocations une fois tant que la carte ne bouge pas).
            private _places = _ui get "places";
            private _pk = [round (_cx / 50), round (_cy / 50), round (_hw / 50)];
            private _locs = _d getVariable ["COMSPEC_ATAK_TocLocs", []];
            if ((_locs param [0, []]) isNotEqualTo _pk) then {
                private _types = ["NameCityCapital", "NameCity"] + ([["NameVillage"], []] select _far);
                _locs = [_pk, ((nearestLocations [[_cx, _cy], _types, _hw * 1.45]) select [0, 30]) apply { [locationPosition _x, text _x, type _x] }];
                _d setVariable ["COMSPEC_ATAK_TocLocs", _locs];
            };
            private _pi = 0;
            {
                _x params ["_lp", "_lt", "_ty"];
                if (_pi < 16 && {_lt isNotEqualTo ""} && {[_lp select 0, _lp select 1] call _inView}) then {
                    ([_lp select 0, _lp select 1] call _toG) params ["_gx", "_gy"];
                    private _s = [_lt, 18] call _cut;
                    private _w = [_s, 28] call _plateW;
                    private _at = [_gx * _texW, _gy * _texH, _w, 36, 0, 1] call _place;
                    if ((count _at) > 0) then {
                        private _t = _places select _pi;
                        [_t, _s, _w] call _lbl;
                        _t ctrlSetTextColor ([[0.78, 0.82, 0.79, 0.95], [0.92, 0.95, 0.93, 1]] select (_ty in ["NameCityCapital", "NameCity"]));
                        [_t, [_at select 0, _at select 1, _w, 36]] call _place2;
                        _pi = _pi + 1;
                    };
                };
            } forEach (_locs param [1, []]);
            for "_i" from _pi to ((count _places) - 1) do { (_places select _i) ctrlShow false; };
            private _em = _ui get "empty";
            _em ctrlShow ((count _nodes) isEqualTo 0);
            [_em, "<t align='center' size='1.3' color='#cfd8d3'>Aucun nœud ATAK de ce camp sur le réseau</t>"] call _setTxt;
            _em ctrlSetPosition ([0, _mhPx / 2 - 24, _mwPx, 48] call _R); _em ctrlCommit 0;
        };

        // ---- Panneau ----------------------------------------------------------------------------------------
        // Blocs : PIP, RÉSEAU (titre, compteurs), GROUPES (tableau), FLUX, ORBAT, ESCOUADE. Chaque ligne est un contrôle
        // d'une ligne coupé à sa largeur (« … ») : plus de texte qui passe sous le bloc suivant.
        private _hideAll = {
            { (_ui get _x) ctrlShow false; } forEach ["pip", "netT", "netS", "grpT", "fluxBg", "fluxT", "info", "orbN", "orb1", "orb2", "sqT", "sqL"];
            { { _x ctrlShow false; } forEach _x; } forEach (_ui get "cnt");
            { _x ctrlShow false; } forEach (_ui get "colH");
            { { _x ctrlShow false; } forEach _x; } forEach (_ui get "tbl");
            { _x ctrlShow false; } forEach (_ui get "fluxL");
        };
        call _hideAll;
        private _cntN = [0, 0, 0, 0];
        { private _f = (_x select 4) min 3; _cntN set [_f, (_cntN select _f) + (_x param [8, 1])]; } forEach _nodes;
        _stats params [["_jam", 0], ["_relays", 0], ["_players", 0], ["_ais", 0]];
        private _px = 0; private _pw = 0;
        if ((count _panR) >= 2) then { _px = _panR select 0; _pw = _panR select 1; };
        private _cx0 = _px + 12;            // bord gauche du texte
        private _cw = _pw - 24;             // largeur utile
        private _y = 92;
        // Compteurs : 4 cases (nombre 44 px, libellé 28 px) ; SPLIT : (556 - 3 x 8) / 4 = 133 px, « hors ligne » estimé
        // à 10 x 0,46 x 28 = 129 px.
        private _counts = {
            params ["_vals", "_y0"];
            private _tw = (_cw - 24) / 4;
            {
                (_x) params ["_bg", "_n", "_l"];
                private _x0 = _cx0 + _forEachIndex * (_tw + 8);
                [_bg, [_x0, _y0, _tw, 84]] call _place2;
                [_n, str (_vals select _forEachIndex), _tw] call _lbl;
                [_n, [_x0, _y0 + 2, _tw, 48]] call _place2;
                [_l, ["en ligne", "anciens", "perdus", "hors ligne"] select _forEachIndex, _tw] call _lbl;
                [_l, [_x0, _y0 + 48, _tw, 34]] call _place2;
            } forEach (_ui get "cnt");
        };
        private _netBlock = {
            [_ui get "netT", "RÉSEAU", 240] call _lbl;
            [_ui get "netT", [_cx0, _y, 240, 46]] call _place2;
            private _sub = [format ["%1 joueurs", _players], format ["%1 IA", _ais], format ["%1 relais", _relays]];
            if (_jam > 0) then { _sub pushBack format ["%1 brouilleurs", _jam]; };
            _sub = _sub joinString " · ";
            [_ui get "netS", _sub, _cw - 250] call _lbl;
            [_ui get "netS", [_cx0 + 250, _y + 6, _cw - 250, 38]] call _place2;
            [_cntN, _y + 50] call _counts;
            _y = _y + 144;
        };
        // Flux : une ligne par événement, coupée à la largeur (43 caractères en SPLIT : 556 / (0,46 x 28)).
        private _fluxBlock = {
            params ["_top", "_bottom", "_full"];
            [_ui get "fluxBg", [_px, _top, _pw, _bottom - _top]] call _place2;
            [_ui get "fluxT", "FLUX", 200] call _lbl;
            [_ui get "fluxT", [_cx0, _top + 8, 200, 40]] call _place2;
            private _rows = floor ((_bottom - _top - 56) / 38);
            private _ev = _events select [0 max ((count _events) - _rows)];
            reverse _ev;
            private _chars = floor (_cw / (0.46 * 28));
            private _fl = _ui get "fluxL";
            if ((count _events) isEqualTo 0) then {
                private _c = _fl select 0;
                [_c, format ["<t size='1' color='#8a9a93'>%1</t>", [["Aucun compte rendu ni message pour l'instant" call comspec_atak_native_fnc_tr, _chars] call _cut] call _esc]] call _setTxt;
                [_c, [_cx0, _top + 52, _cw, 38]] call _place2;
            };
            {
                _x params [["_time", ""], ["_kind", ""], ["_prio", ""], ["_who", ""], ["_grid", ""], ["_text", ""], "", "", ["_old", false]];
                private _pc = switch (toUpper _prio) do { case "FLASH": { "#ff5a4a" }; case "URGENT": { "#e0b040" }; default { ["#7fd0b0", "#6f7773"] select _old }; };
                private _t1 = [_time, 8] call _cut;
                private _k1 = [_kind call comspec_atak_native_fnc_tr, 14] call _cut;
                private _w1 = [_who, 16] call _cut;
                forceUnicode 0;
                private _rest = _chars - (count (_t1 + _k1 + _w1)) - 3;
                private _x1 = if (_full && {_text isNotEqualTo ""} && {_rest > 8}) then { " · " + ([_text, _rest - 3] call _cut) } else { "" };
                private _c = _fl select _forEachIndex;
                [_c, format ["<t size='1'><t color='#8a9a93'>%1</t> <t color='%2' font='COMSPEC_BarlowBold'>%3</t> %4%5</t>", [_t1] call _esc, _pc, [_k1] call _esc, [_w1] call _esc, [_x1] call _esc]] call _setTxt;
                [_c, [_cx0, _top + 52 + _forEachIndex * 38, _cw, 38]] call _place2;
            } forEach (_ev select [0, (_rows max 0) min (count _fl)]);
        };
        // Tableau des groupes : 40 px par ligne (police 30) ; colonnes 58 % / 12 % / reste.
        private _tableBlock = {
            params ["_bottom"];
            private _gm = createHashMap;
            {
                private _g = _x param [7, ""];
                private _e = _gm getOrDefault [_g, [0, _x select 2, 9]];
                _gm set [_g, [(_e select 0) + (_x param [8, 1]), _e select 1, (_e select 2) min (_x select 4)]];
            } forEach _nodes;
            private _gk = keys _gm;
            _gk sort true;
            private _nRow = (floor ((_bottom - _y - 82) / 40)) min (count (_ui get "tbl"));
            // Pages automatiques : une toutes les 8 s quand tout ne tient pas.
            private _nPg = (ceil ((count _gk) / (_nRow max 1))) max 1;
            private _pg = (floor (diag_tickTime / 8)) mod _nPg;
            [_ui get "grpT", format ["<t size='1.15' font='COMSPEC_BarlowBold'>GROUPES</t> <t size='1' color='#8a9a93'>%1%2</t>", count _gk, ["", format [" · page %1 / %2", _pg + 1, _nPg]] select (_nPg > 1)]] call _setTxt;
            [_ui get "grpT", [_cx0, _y, _cw, 42]] call _place2;
            private _wN = _cw * 0.58; private _wC = _cw * 0.12; private _wG = _cw - _wN - _wC - 12;
            (_ui get "colH") params ["_h0", "_h1", "_h2"];
            [_h0, "ÉLÉMENT", _wN] call _lbl; [_h0, [_cx0, _y + 44, _wN, 34]] call _place2;
            [_h1, "NŒUDS", _wC] call _lbl; [_h1, [_cx0 + _wN, _y + 44, _wC, 34]] call _place2;
            [_h2, "GRILLE", _wG] call _lbl; [_h2, [_cx0 + _wN + _wC + 12, _y + 44, _wG, 34]] call _place2;
            if ((count _gk) isEqualTo 0) then {
                [_ui get "info", "<t size='1' color='#8a9a93'>Aucun nœud ATAK de ce camp sur le réseau</t>"] call _setTxt;
                [_ui get "info", [_cx0, _y + 82, _cw, 38]] call _place2;
            };
            {
                private _k = _x;
                (_gm get _k) params ["_n", "_p", "_best"];
                ((_ui get "tbl") select _forEachIndex) params ["_bg", "_c0", "_c1", "_c2"];
                private _ry = _y + 82 + _forEachIndex * 40;
                [_bg, [_px + 4, _ry, _pw - 8, 40]] call _place2;
                [_c0, [_k, "(sans groupe)"] select (_k isEqualTo ""), _wN] call _lbl;
                _c0 ctrlSetTextColor ([[0.36, 0.78, 0.42, 1], [0.88, 0.69, 0.25, 1], [0.66, 0.7, 0.68, 1], [0.48, 0.52, 0.5, 1]] select ((_best min 3) max 0));
                [_c0, [_cx0, _ry + 1, _wN, 38]] call _place2;
                [_c1, str _n, _wC] call _lbl;
                _c1 ctrlSetTextColor [0.85, 0.89, 0.86, 1];
                [_c1, [_cx0 + _wN, _ry + 1, _wC, 38]] call _place2;
                [_c2, [[_p select 0, _p select 1, 0], 6] call comspec_atak_native_fnc_gridRef, _wG] call _lbl;
                _c2 ctrlSetTextColor [0.85, 0.89, 0.86, 1];
                [_c2, [_cx0 + _wN + _wC + 12, _ry + 1, _wG, 38]] call _place2;
            } forEach (_gk select [_pg * _nRow, _nRow max 0]);
        };
        switch (_view) do {
            case "MAP": {};
            case "FEED": {
                call _netBlock;
                [_y + 4, 884, true] call _fluxBlock;
            };
            case "NET": {
                call _netBlock;
                [884] call _tableBlock;
            };
            case "ORBAT": {
                // ORBAT du site : sur deux colonnes, par pages, chaque membre avec son état (20 lignes de 34 px par colonne).
                ["load"] call comspec_atak_native_fnc_bftOrbat;
                private _store = ["store"] call comspec_atak_native_fnc_bftOrbat;
                (["presence"] call comspec_atak_native_fnc_bftOrbat) params ["", "_onKey"];
                (["notice"] call comspec_atak_native_fnc_bftOrbat) params ["_nTxt"];
                private _people = _store get "people";
                private _lines = [];
                {
                    _x params ["", "_ud", "_uName", "_uCode", "_uRows"];
                    _lines pushBack format ["<t size='1.05' font='COMSPEC_BarlowBold' color='#7fb8e0'>%1%2%3</t>", ["", "· "] select (_ud > 0), [[_uName, 34] call _cut] call _esc, ["", format [" (%1)", [[_uCode, 10] call _cut] call _esc]] select (_uCode isNotEqualTo "")];
                    {
                        private _pe = _people getOrDefault [_x select 1, []];
                        if ((_x select 0) isEqualTo "v") then {
                            _lines pushBack format ["<t size='1' color='#6f7773'>○ %1 · poste vacant</t>", [[_x select 2, 28] call _cut] call _esc];
                        } else {
                            private _on = (_x select 1) in _onKey;
                            if ((_pe param [4, 0]) isEqualTo 1) then {
                                _lines pushBack format ["<t size='1' color='#6f7773'>· %1</t>", [[_pe param [0, ""], 40] call _cut] call _esc];
                            } else {
                                _lines pushBack format ["<t size='1'><t color='%1'>%2</t> %3</t>", ["#6f7773", "#5cc76b"] select _on, ["○", "●"] select _on, [[_pe param [0, ""], 40] call _cut] call _esc];
                            };
                        };
                    } forEach _uRows;
                } forEach (_store get "units");
                private _perCol = 20;
                private _nPg = (ceil ((count _lines) / (2 * _perCol))) max 1;
                private _pg = (floor (diag_tickTime / 10)) mod _nPg;
                private _page = _lines select [_pg * 2 * _perCol, 2 * _perCol];
                [_ui get "orbN", format ["%1%2", _nTxt, ["", format [" · page %1 / %2", _pg + 1, _nPg]] select (_nPg > 1)], _cw] call _lbl;
                [_ui get "orbN", [_cx0, 96, _cw, 38]] call _place2;
                [_ui get "orb1", (_page select [0, _perCol]) joinString "<br/>"] call _setTxt;
                [_ui get "orb1", [_cx0, 140, _cw / 2 - 12, 744]] call _place2;
                [_ui get "orb2", (_page select [_perCol, _perCol]) joinString "<br/>"] call _setTxt;
                [_ui get "orb2", [_cx0 + _cw / 2 + 12, 140, _cw / 2 - 12, 744]] call _place2;
            };
            case "SQUAD": {
                // Écran d'escouade : nom du groupe, compteurs du groupe, membres, dernier compte rendu ; caméra du chef
                // en bas si elle est partagée (alors 7 membres au plus et pas de compte rendu).
                [_ui get "sqT", format ["<t size='2.2' font='COMSPEC_BarlowBold'>%1</t>", [[toUpper ([_squad, "AUCUN GROUPE"] select (_squad isEqualTo "")), 17] call _cut] call _esc]] call _setTxt;
                [_ui get "sqT", [_cx0, 92, _cw, 76]] call _place2;
                private _sc = [0, 0, 0, 0];
                { private _f = (_x select 4) min 3; _sc set [_f, (_sc select _f) + (_x param [8, 1])]; } forEach _sqNodes;
                [_sc, 172] call _counts;
                private _cam = (_o getVariable ["COMSPEC_ATAK_TocVideo", false]);
                [_ui get "sqL", ["squadText", [_squad, _sqNodes, _events, [12, 7] select _cam, !_cam, floor (_cw / (0.46 * 28))]] call _fn] call _setTxt;
                [_ui get "sqL", [_cx0, 268, _cw, [616, 280] select _cam]] call _place2;
            };
            default {
                // SPLIT et PIP : RÉSEAU, GROUPES jusqu'à 632 px, FLUX de 640 à 892 px.
                if ((count _op) >= 8) then {
                    [_ui get "pip", format ["PIP · %1 · %2", _op param [6, ""], format ["onglet %1", _op param [3, ""]]], _cw] call _lbl;
                    [_ui get "pip", [_cx0, 92, _cw, 36]] call _place2;
                    _y = 132;
                };
                call _netBlock;
                [632] call _tableBlock;
                [640, 892, false] call _fluxBlock;
            };
        };
        // Écran d'escouade : caméra poitrine du chef en bas du panneau, s'il la partage (mêmes caméras que la mosaïque).
        private _lc = _ui get "lead";
        private _ll = _ui get "leadLbl";
        private _ltex = "";
        if (_view isEqualTo "SQUAD" && {_squad isNotEqualTo ""}) then {
            private _gs = allGroups select { (groupId _x) isEqualTo _squad && {(str side _x) isEqualTo _side} };
            if ((count _gs) > 0) then {
                private _lid = netId (leader (_gs select 0));
                if (((["sources"] call comspec_atak_native_fnc_tocCam) findIf { (_x select 1) isEqualTo _lid }) >= 0) then {
                    _ltex = ["tex", _lid] call comspec_atak_native_fnc_tocMosaic;
                    if (_ltex isNotEqualTo "") then {
                        [_ll, format ["<t size='1'> Caméra poitrine · %1</t>", [[[leader (_gs select 0), true] call comspec_atak_native_fnc_unitCallsign, 24] call _cut] call _esc]] call _setTxt;
                    };
                };
            };
        };
        if ((_lc getVariable ["COMSPEC_ATAK_Tex", "-"]) isNotEqualTo _ltex) then { _lc setVariable ["COMSPEC_ATAK_Tex", _ltex]; _lc ctrlSetText _ltex; }; // i18n:brut (texture)
        _lc ctrlSetPosition ([1012, 556, 580, 326] call _R); _lc ctrlCommit 0;
        _ll ctrlSetPosition ([1012, 556, 580, 40] call _R); _ll ctrlCommit 0;
        _lc ctrlShow (_ltex isNotEqualTo ""); _ll ctrlShow (_ltex isNotEqualTo "");
        _o setVariable ["COMSPEC_ATAK_TocVideo", _ltex isNotEqualTo ""];
        if (_ltex isNotEqualTo "") then { ["live", [_o, _d]] call comspec_atak_native_fnc_tocBoard; };
        true
    };
    case "squadText": {
        // Membres d'un groupe (une ligne chacun, coupée à _chars caractères), puis son dernier compte rendu.
        _a params ["_squad", "_sqNodes", "_events", ["_max", 12], ["_withCr", true], ["_chars", 43]];
        private _esc = { params ["_t"]; if !(_t isEqualType "") then { _t = str _t; }; { _t = [_t, _x select 0, _x select 1] call CBA_fnc_replace; } forEach [["&", "&amp;"], ["<", "&lt;"], [">", "&gt;"]]; _t };
        if (_squad isEqualTo "") exitWith { "<t size='1' color='#8a9a93'>Aucun groupe choisi : choisissez l'escouade au PC du poste, dans l'app Poste TOC ou au menu ACE de l'écran</t>" };
        private _s = "";
        if ((count _sqNodes) isEqualTo 0) then {
            _s = "<t size='1' color='#e0b040'>Aucun ATAK de ce groupe sur le réseau : le poste ne connaît ni ses membres ni sa position</t><br/>";
        };
        {
            _x params ["", "_cs", "_p", "", "_fr", "_kind", "", "", ["_n", 1]];
            private _col = ["#5cc76b", "#e0b040", "#9aa39f", "#6f7773"] select ((_fr min 3) max 0);
            _s = _s + format ["<t size='1'><t color='%1'>●</t> <t font='COMSPEC_BarlowBold'>%2</t>%3 <t color='#8a9a93'>· %4 · %5</t></t><br/>", _col, [[_cs, 14] call _cut] call _esc,
                ["", format [" +%1", _n - 1]] select (_n > 1), [[_p select 0, _p select 1, 0], 6] call comspec_atak_native_fnc_gridRef,
                ["en direct", "ancien", "perdu", "hors ligne"] select ((_fr min 3) max 0)];
        } forEach (_sqNodes select [0, _max]);
        if (!_withCr) exitWith { _s };
        // Dernier compte rendu d'un membre du groupe (auteur reconnu à son indicatif), deux lignes au plus.
        private _names = _sqNodes apply { toLower (_x select 1) };
        private _ev = _events select { (toLower (_x param [3, ""])) in _names };
        if ((count _ev) > 0) then {
            (_ev select ((count _ev) - 1)) params [["_time", ""], ["_kind", ""], ["_prio", ""], ["_who", ""], ["_grid", ""], ["_text", ""]];
            _s = _s + format ["<br/><t size='1.1' font='COMSPEC_BarlowBold'>%1 %2</t> <t size='1' color='#8a9a93'>%3 · %4%5</t><br/><t size='1'>%6</t>",
                [[_kind, 14] call _cut] call _esc, [[_prio, 10] call _cut] call _esc, _time, [[_who, 16] call _cut] call _esc, ["", " · grille " + _grid] select (_grid isNotEqualTo ""), [[_text, 2 * _chars - 4] call _cut] call _esc];
        } else {
            _s = _s + "<br/><t size='1' color='#8a9a93'>Aucun compte rendu de ce groupe pour l'instant</t>";
        };
        _s
    };
    case "title": {
        // Titre de chaque affichage : [mot d'en-tête de l'écran, libellé des menus et notifications], en français
        // (traduit à l'affichage). "" : la table entière (contrôle « audit », test tests/Unit/AtakTocScreenDisplaysAssetTest.php).
        private _t = createHashMapFromArray [
            ["SPLIT", ["TOC", "carte et flux"]], ["MAP", ["TOC", "carte seule"]], ["FEED", ["FLUX", "flux seul"]],
            ["NET", ["RÉSEAU", "réseau"]], ["ORBAT", ["ORBAT", "ORBAT du site"]], ["TOC", ["TOC", "PIP du poste"]],
            ["CAM", ["CAMÉRA", "caméra en direct"]], ["SAT", ["SATELLITE", "vue satellite"]], ["CLOCK", ["HORLOGE", "horloge et chronos"]],
            ["SQUAD", ["ESCOUADE", "écran d'escouade"]], ["LOG", ["MAIN COURANTE", "main courante"]], ["EXCHECK", ["EXÉCUTION", "liste d'exécution"]],
            ["KILLS", ["PERTES ET RÉSULTATS", "pertes et résultats"]], ["REQ", ["DEMANDES", "demandes des éléments"]],
            ["STRIP", ["BANDEAU", "bandeau horloge"]], ["PLATE", ["PLAQUE", "plaque de nom"]], ["MOSAIC", ["CAMÉRAS", "mosaïque de caméras"]]
        ];
        if (_a isEqualTo "") exitWith { _t };
        _t getOrDefault [_a, [_a, toLower _a]]
    };
    case "audit": {
        // Contrôle du code des affichages, sans écran posé (console de débogage, joueur ou Zeus) : chaque affichage est
        // dessiné dans un display d'essai (visible quelques images à l'écran), chacun dans son propre script (une erreur
        // n'arrête que lui). Résultat : [[affichage, "OK" | "ERREUR" | "VIDE" | "SANS TITRE", contrôles visibles]...],
        // aussi au journal et dans uiNamespace COMSPEC_ATAK_TocAudit. Hors script suspendable : relancé à part, [] rendu.
        // Exemple : ["audit"] call comspec_atak_native_fnc_tocScreen;
        if (!hasInterface) exitWith { [] };
        if (!canSuspend) exitWith { ["audit"] spawn comspec_atak_native_fnc_tocScreen; [] };
        disableSerialization;
        private _host = findDisplay 46;
        if (isNull _host) exitWith { [] };
        private _titles = ["title", ""] call _fn;
        uiNamespace setVariable ["COMSPEC_ATAK_TocAuditing", true];
        private _o = "Land_HelipadEmpty_F" createVehicleLocal [0, 0, 0];
        private _out = [];
        {
            private _v = _x;
            // Display neuf pour chaque affichage : contrôles construits depuis zéro, comme sur un écran qui vient d'être posé.
            private _d = _host createDisplay "COMSPEC_RscTocScreenTexture";
            _o setVariable ["COMSPEC_ATAK_TocScr", ["Audit", "", 0, _v, 0, [], false, false, 0, ""]];
            uiNamespace setVariable ["COMSPEC_ATAK_TocAuditD", _d];
            missionNamespace setVariable ["COMSPEC_ATAK_TocAuditDone", false];
            private _h = [_o] spawn {
                disableSerialization;
                params ["_o"];
                private _r = ["draw", [_o, uiNamespace getVariable ["COMSPEC_ATAK_TocAuditD", displayNull]]] call comspec_atak_native_fnc_tocScreen;
                missionNamespace setVariable ["COMSPEC_ATAK_TocAuditDone", !isNil "_r"];
            };
            private _t0 = diag_tickTime;
            waitUntil { scriptDone _h || {diag_tickTime - _t0 > 5} };
            private _shown = { ctrlShown _x && {((ctrlPosition _x) select 2) > 0} } count (allControls _d);
            private _st = switch (true) do {
                case !(missionNamespace getVariable ["COMSPEC_ATAK_TocAuditDone", false]): { "ERREUR" };
                case (_shown < 5): { "VIDE" };
                case !(_v in _titles): { "SANS TITRE" };
                default { "OK" };
            };
            _out pushBack [_v, _st, _shown];
            [["INFO", "WARNING"] select (_st isNotEqualTo "OK"), "TOC", format ["Audit des écrans TOC : %1 %2 (%3 contrôles visibles)", _v, _st, _shown]] call comspec_atak_native_fnc_log;
            _d closeDisplay 2;
        } forEach _views;
        deleteVehicle _o;
        uiNamespace setVariable ["COMSPEC_ATAK_TocAuditing", false];
        uiNamespace setVariable ["COMSPEC_ATAK_TocAudit", _out];
        _out
    };
    case "ensure": {
        // Désigne l'objet comme écran TOC (ou change seulement l'affichage) : ACE sur n'importe quel écran du jeu.
        _a params [["_o", objNull], ["_view", "SPLIT"]];
        if (isNull _o || {!alive _o}) exitWith { false };
        if !(_view in _views) then { _view = "SPLIT"; };
        private _c = [_o] call _cfgOf;
        if ((count _c) >= 8) then {
            ["view", [_o, "mode", _view]] call _fn;
        } else {
            private _nm = getText (configOf _o >> "displayName");
            if (_nm isEqualTo "") then { _nm = "TOC"; };
            ["add", [_o, _nm select [0, 32], "", 0, _view, true, false]] call _fn;
        };
        _o setVariable ["COMSPEC_ATAK_TocAt", -10];
        // Notification du téléphone (jamais une fenêtre du jeu) ; libellé commun aux menus (« title »).
        ["INFO", format ["Écran TOC : %1", (["title", _view] call _fn) select 1], 2, 20] call comspec_atak_native_fnc_notify;
        if (_view isEqualTo "TOC") then {
            private _side = str side group player;
            private _ops = allPlayers select { !isNull _x && {alive _x} && {((_x getVariable ["COMSPEC_ATAK_TocView", []]) param [0, ""]) isEqualTo _side} };
            if ((count _ops) isEqualTo 0) then {
                ["INFO", "PIP du poste : ouvrez le PC du poste (action Ouvrir le PC) pour que l'écran suive votre vue", 4, 25] call comspec_atak_native_fnc_notify;
            };
        };
        true
    };
    case "show": {
        _a params [["_o", objNull], ["_k", "SPLIT"]];
        if (isNull _o) exitWith { false };
        if (_k isEqualTo "SLIDES") exitWith { ["castOn", _o] call comspec_atak_native_fnc_screenCast };
        if ([_o] call _casting) then { ["stop", _o] call comspec_atak_native_fnc_screenCast; };
        ["ensure", [_o, _k]] call _fn
    };
    case "aceInit": {
        // ACE sur chaque classe d'écran du jeu : TOC / PIP / diapositives, sans module Zeus.
        if (!hasInterface) exitWith { false };
        if (missionNamespace getVariable ["COMSPEC_ATAK_TocAceInit", false]) exitWith { true };
        missionNamespace setVariable ["COMSPEC_ATAK_TocAceInit", true];
        // Ancien pack TOC (pack FN) : il pose aussi ses images sur les écrans du jeu. Les écrans COMSPEC ne le concernent pas.
        if (isClass (configFile >> "CfgPatches" >> "atak_toc_screens")) then {
            ["INFO", "TOC", "Ancien pack ATAK_TOC_Screens chargé : préférez les écrans COMSPEC, ses actions peuvent reprendre la dalle d'un écran du jeu"] call comspec_atak_native_fnc_log;
        };
        private _do = {
            params ["_o", "_k"];
            private _shown = _o getVariable ["COMSPEC_ATAK_TocShown", [getPosASL _o, 1500]];
            // Salle TOC : satellite choisi, escouade choisie (« sq|nom du groupe »), puis l'affichage (demandes au serveur dans l'ordre).
            private _pre = {
                params ["_key", "_val", "_mode"];
                if ((count (_o getVariable ["COMSPEC_ATAK_TocScr", []])) < 8) then { ["ensure", [_o, "SPLIT"]] call comspec_atak_native_fnc_tocScreen; };
                ["view", [_o, _key, _val]] call comspec_atak_native_fnc_tocScreen;
                ["ensure", [_o, _mode]] call comspec_atak_native_fnc_tocScreen;
            };
            if ((_k select [0, 3]) isEqualTo "sq|") exitWith { ["squad", _k select [3], "SQUAD"] call _pre; };
            if ((_k select [0, 4]) isEqualTo "MOS|") exitWith { ["apply", [_o, _k]] call comspec_atak_native_fnc_tocScreen; };
            switch (_k) do {
                case "sat1": { ["sat", 0, "SAT"] call _pre; };
                case "sat2": { ["sat", 1, "SAT"] call _pre; };
                case "clock": { ["ensure", [_o, "CLOCK"]] call comspec_atak_native_fnc_tocScreen; };
                case "strip": { ["ensure", [_o, "STRIP"]] call comspec_atak_native_fnc_tocScreen; };
                case "log": { ["ensure", [_o, "LOG"]] call comspec_atak_native_fnc_tocScreen; };
                case "excheck": { ["ensure", [_o, "EXCHECK"]] call comspec_atak_native_fnc_tocScreen; };
                case "kills": { ["ensure", [_o, "KILLS"]] call comspec_atak_native_fnc_tocScreen; };
                case "req": { ["ensure", [_o, "REQ"]] call comspec_atak_native_fnc_tocScreen; };
                case "split": { ["ensure", [_o, "SPLIT"]] call comspec_atak_native_fnc_tocScreen; };
                case "pip": { ["ensure", [_o, "TOC"]] call comspec_atak_native_fnc_tocScreen; };
                case "map": { ["ensure", [_o, "MAP"]] call comspec_atak_native_fnc_tocScreen; };
                case "feed": { ["ensure", [_o, "FEED"]] call comspec_atak_native_fnc_tocScreen; };
                case "net": { ["ensure", [_o, "NET"]] call comspec_atak_native_fnc_tocScreen; };
                case "orbat": { ["ensure", [_o, "ORBAT"]] call comspec_atak_native_fnc_tocScreen; };
                case "cycle": {
                    private _m = (_o getVariable ["COMSPEC_ATAK_TocScr", []]) param [3, "SPLIT"];
                    private _modes = ["SPLIT", "MAP", "FEED", "NET", "ORBAT", "TOC"];
                    private _next = _modes select (((_modes find _m) + 1) mod 6);
                    ["ensure", [_o, _next]] call comspec_atak_native_fnc_tocScreen;
                };
                case "in": {
                    ["view", [_o, "center", _shown select 0]] call comspec_atak_native_fnc_tocScreen;
                    ["view", [_o, "zoom", (_shown select 1) / 2]] call comspec_atak_native_fnc_tocScreen;
                    _o setVariable ["COMSPEC_ATAK_TocAt", -10];
                };
                case "out": {
                    ["view", [_o, "center", _shown select 0]] call comspec_atak_native_fnc_tocScreen;
                    ["view", [_o, "zoom", (_shown select 1) * 2]] call comspec_atak_native_fnc_tocScreen;
                    _o setVariable ["COMSPEC_ATAK_TocAt", -10];
                };
                case "me": {
                    ["view", [_o, "center", getPosASL player]] call comspec_atak_native_fnc_tocScreen;
                    ["view", [_o, "zoom", _shown select 1]] call comspec_atak_native_fnc_tocScreen;
                    _o setVariable ["COMSPEC_ATAK_TocAt", -10];
                };
                case "auto": { ["view", [_o, "auto"]] call comspec_atak_native_fnc_tocScreen; _o setVariable ["COMSPEC_ATAK_TocAt", -10]; };
                case "big": { ["open", _o] call comspec_atak_native_fnc_tocPost; };
                case "slides": { ["castOn", _o] call comspec_atak_native_fnc_screenCast; };
                case "slidesStop": { ["stop", _o] call comspec_atak_native_fnc_screenCast; };
                case "slidesNext": { ["step", [_o, 1]] call comspec_atak_native_fnc_screenCast; };
                case "slidesPrev": { ["step", [_o, -1]] call comspec_atak_native_fnc_screenCast; };
                // Caméra suivante du camp (action de secours sans ACE ; ACE propose la liste).
                case "camNext": {
                    private _src = ["sources"] call comspec_atak_native_fnc_tocCam;
                    if ((count _src) isEqualTo 0) exitWith { ["INFO", "Aucune caméra du camp à afficher (drone, caméra fixe ou caméra partagée)", 3, 20] call comspec_atak_native_fnc_notify; };
                    private _i = (_src findIf { (_x select 1) isEqualTo (_o getVariable ["COMSPEC_ATAK_TocCam", ""]) }) + 1;
                    private _pick = _src select (_i mod (count _src));
                    ["set", [_o, _pick select 1]] call comspec_atak_native_fnc_tocCam;
                    ["INFO", format ["Écran TOC : %1", _pick select 0], 2, 20] call comspec_atak_native_fnc_notify;
                };
                case "remove": {
                    ["remove", _o] call comspec_atak_native_fnc_tocScreen;
                    ["INFO", "Écran TOC retiré", 2, 20] call comspec_atak_native_fnc_notify;
                };
            };
        };
        missionNamespace setVariable ["COMSPEC_ATAK_TocDo", _do];
        // Sans ACE : seules les actions de secours (« actions ») s'en servent ; avant, COMSPEC_ATAK_TocDo restait vide
        // et aucune action d'écran (dont Ouvrir le PC du poste) ne faisait rien.
        if (isNil "ace_interact_menu_fnc_createAction") exitWith { false };
        // Un seul arbre ACE par écran (retour de jeu 2.9.24 : tout en double, listes superposées) :
        //   Écran COMSPEC > Diffusion (diapositives / leçon Academy, caméra en direct, PIP du poste)
        //                 > Affichage TOC (réseau, carte seule, flux seul, PC du poste, retirer)
        //                 > Carte (zoom, centrer, cadrer ; écran TOC désigné)
        // Racine posée une fois : sur la classe exacte des écrans du jeu (jamais par héritage : une variante « noire » ou
        // « sable » héritant d'une classe listée recevait les deux racines), sinon sur l'objet (« aceObj »), une fois.
        private _kids = {
            private _o = _target;
            private _has = (count (_o getVariable ["COMSPEC_ATAK_TocScr", []])) >= 8;
            private _c = _o getVariable ["COMSPEC_ATAK_ScreenCast", []];
            private _d = "\z\comspec_atak_native\addons\main\data\";
            // Action simple : clé passée en paramètre (pas de code compilé à chaque ouverture du menu).
            private _mk = {
                params ["_id", "_txt", "_key", ["_cond", { true }]];
                [["COMSPEC_ATAK_Wall" + _id, _txt, "", { [_target, _this select 2] call COMSPEC_ATAK_TocDo; }, _cond, {}, _key] call comspec_atak_native_fnc_aceAction, [], _o]
            };
            private _grp = {
                params ["_id", "_txt", "_icon", "_list"];
                [["COMSPEC_ATAK_Wall" + _id, _txt, _icon, {}, { true }] call comspec_atak_native_fnc_aceAction, _list, _o]
            };
            // Menu de l'écran, revu le 11/11 (« les actions ACE..? ») : groupes nommés par ce qu'on fait, contenus rangés par
            // famille, trois niveaux au plus. Afficher sur l'écran · Régler les dalles (mur) · Diffuser · Régler la carte · Gérer l'écran.
            private _sideS = ((_o getVariable ["COMSPEC_ATAK_TocScr", []]) param [1, ""]) call { [_this, str side group player] select (_this isEqualTo "") };
            private _sqList = ["squads", _sideS] call comspec_atak_native_fnc_tocScreen;
            private _ratio4 = (getNumber (configOf _o >> "comspec_screenRatio")) >= 4;
            private _diff = [];
            if ((count _c) >= 5) then {
                private _acad = (_c select 2) isEqualTo "ACADEMY";
                _diff pushBack (["Next", ["Diapositive suivante", "Page suivante"] select _acad, "slidesNext"] call _mk);
                _diff pushBack (["Prev", ["Diapositive précédente", "Page précédente"] select _acad, "slidesPrev"] call _mk);
                _diff pushBack (["Stop", "Arrêter la diffusion", "slidesStop"] call _mk);
            } else {
                _diff pushBack (["Slides", "Diffuser mes diapositives", "slides", { [_player] call comspec_atak_native_fnc_hasDevice }] call _mk);
            };
            _diff pushBack (["Pip", "Montrer ce que voit le PC du poste (PIP)", "pip"] call _mk);
            private _out = [];
            if (!_has) then {
                _out pushBack (["Split", "Afficher le réseau TOC", "split"] call _mk);
            } else {
                private _show = [];
                if (_ratio4) then {
                    // Face 4:1 : bandeau horloge ou plaque au nom d'un groupe.
                    _show pushBack (["Strip", "Bandeau horloge ZULU / LOCAL", "strip"] call _mk);
                    { _show pushBack (["PlateG" + str _forEachIndex, format ["Plaque · %1", _x], "sq|" + _x] call _mk); } forEach (_sqList select [0, 12]);
                } else {
                    _show pushBack (["MapGrp", "Carte et réseau", _d + "app_map.paa", [
                        ["SplitOn", "Carte et flux", "split"] call _mk, ["MapOnly", "Carte seule", "map"] call _mk, ["FeedOnly", "Flux seul", "feed"] call _mk,
                        ["NetOnly", "Réseau", "net"] call _mk, ["OrbatOnly", "ORBAT du site", "orbat"] call _mk
                    ]] call _grp);
                    _show pushBack (["SatGrp", "Satellite", _d + "app_map.paa", [["Sat1", "SAT 1", "sat1"] call _mk, ["Sat2", "SAT 2", "sat2"] call _mk]] call _grp);
                    _show pushBack (["Clock", "Horloge et chronos", "clock"] call _mk);
                    _show pushBack (["BoardGrp", "Tableau du poste", _d + "app_toc.paa", [
                        ["Log", "Main courante", "log"] call _mk, ["Ex", "Liste d'exécution", "excheck"] call _mk,
                        ["Kills", "Pertes et résultats", "kills"] call _mk, ["Req", "Demandes des éléments", "req"] call _mk
                    ]] call _grp);
                    private _sqs = [];
                    { _sqs pushBack (["SqG" + str _forEachIndex, _x, "sq|" + _x] call _mk); } forEach (_sqList select [0, 12]);
                    if (_sqs isNotEqualTo []) then { _show pushBack (["SqGrp", "Escouade", _d + "app_group.paa", _sqs] call _grp); };
                    // Caméras : mosaïques et caméra en direct d'une source du camp.
                    private _cams = [["Mos2", "Mosaïque 2x2 · tout le camp", "MOS|2|AUTO"] call _mk, ["Mos3", "Mosaïque 3x3 · tout le camp", "MOS|3|AUTO"] call _mk];
                    { _cams pushBack (["MosG" + str _forEachIndex, format ["Mosaïque 2x2 · groupe %1", _x], "MOS|2|G|" + _x] call _mk); } forEach (_sqList select [0, 4]);
                    {
                        _x params ["_lbl", "_id"];
                        _cams pushBack [["COMSPEC_ATAK_WallCam" + str _forEachIndex, _lbl, "", { ["set", [_target, _this select 2]] call comspec_atak_native_fnc_tocCam; }, { true }, {}, _id] call comspec_atak_native_fnc_aceAction, [], _o];
                    } forEach ((["sources"] call comspec_atak_native_fnc_tocCam) select [0, 8]);
                    _show pushBack (["CamGrp", "Caméras", _d + "app_livecam.paa", _cams] call _grp);
                };
                _out pushBack (["Show", "Afficher sur l'écran", _d + "app_toc.paa", _show] call _grp);
                // Mur à plusieurs dalles : un contenu par dalle (dalle 1 en haut à gauche, ordre de lecture).
                private _np = getNumber (configOf _o >> "comspec_screenPanels");
                if (_np > 1) then {
                    private _opts = [["Carte et flux", "SPLIT"], ["SAT 1", "SAT1"], ["SAT 2", "SAT2"], ["Horloge et chronos", "CLOCK"], ["Mosaïque 2x2", "MOS|2|AUTO"],
                        ["Main courante", "LOG"], ["Liste d'exécution", "EXCHECK"], ["Pertes et résultats", "KILLS"], ["Demandes des éléments", "REQ"]];
                    { _opts pushBack [format ["Escouade %1", _x], "SQ|" + _x]; } forEach (_sqList select [0, 6]);
                    private _pans = [];
                    for "_k" from 0 to (_np - 1) do {
                        private _items = _opts apply {
                            [["COMSPEC_ATAK_WallPn" + str _k + (_x select 1), _x select 0, "", {
                                (_this select 2) params ["_k", "_key"];
                                ["apply", [["panelOf", [_target, _k]] call comspec_atak_native_fnc_tocScreen, _key]] call comspec_atak_native_fnc_tocScreen;
                            }, { true }, {}, [_k, _x select 1]] call comspec_atak_native_fnc_aceAction, [], _o]
                        };
                        _pans pushBack (["Pn" + str _k, format ["Dalle %1", _k + 1], "", _items] call _grp);
                    };
                    _out pushBack (["Pans", "Régler les dalles du mur", _d + "app_toc.paa", _pans] call _grp);
                };
            };
            _out pushBack (["Diff", "Diffuser", _d + "app_briefing.paa", _diff] call _grp);
            if (_has && {!_ratio4}) then {
                _out pushBack (["Map", "Régler la carte", _d + "app_map.paa", [
                    ["In", "Zoomer", "in"] call _mk, ["Out", "Dézoomer", "out"] call _mk,
                    ["Me", "Centrer sur ma position", "me"] call _mk, ["Auto", "Cadrer tout le réseau", "auto"] call _mk
                ]] call _grp);
            };
            // Gérer l'écran : salle TOC (lier, délier), PC du poste, retirer.
            private _halls = ["halls", _sideS] call comspec_atak_native_fnc_tocHall;
            private _cur = ["of", _o] call comspec_atak_native_fnc_tocHall;
            private _man = [];
            {
                _x params ["_hid", "_hn"];
                if (_hid isNotEqualTo _cur) then {
                    _man pushBack [["COMSPEC_ATAK_WallHall" + str _forEachIndex, format ["Lier à la salle %1", _hn], "", { ["do", ["link", [_this select 2, _target]]] call comspec_atak_native_fnc_tocHall; }, { true }, {}, _hid] call comspec_atak_native_fnc_aceAction, [], _o];
                };
            } forEach (_halls select [0, 6]);
            if (_cur isNotEqualTo "") then {
                _man pushBack [["COMSPEC_ATAK_WallUnlink", format ["Délier de la salle %1", (["get", _cur] call comspec_atak_native_fnc_tocHall) param [1, "?"]], "", { ["do", ["unlink", _target]] call comspec_atak_native_fnc_tocHall; }, { true }] call comspec_atak_native_fnc_aceAction, [], _o];
            };
            if (_has && {_o getVariable ["COMSPEC_ATAK_Toc", false]}) then { _man pushBack (["Big", "Ouvrir le PC du poste", "big", { isNull objectParent _player }] call _mk); };
            if (_has) then { _man pushBack (["Off", "Retirer l'affichage TOC", "remove"] call _mk); };
            if (_man isNotEqualTo []) then { _out pushBack (["Manage", "Gérer l'écran", _d + "app_toc.paa", _man] call _grp); };
            _out
        };
        missionNamespace setVariable ["COMSPEC_ATAK_WallKids", _kids];
        // Camp de l'écran TOC et camp de l'écran COMSPEC : la racine n'apparaît qu'au camp autorisé.
        private _root = ["COMSPEC_ATAK_Wall", "Écran COMSPEC", "\z\comspec_atak_native\addons\main\data\app_toc.paa", {}, {
            alive _target && {(_player distance _target) < 8} && {
                private _s1 = (_target getVariable ["COMSPEC_ATAK_TocScr", []]) param [1, ""];
                private _s2 = (_target getVariable ["COMSPEC_ATAK_Screen", []]) param [2, ""];
                private _me = str side group _player;
                (_s1 isEqualTo "" || {_s1 isEqualTo _me}) && {_s2 isEqualTo "" || {_s2 isEqualTo _me}}
            }
        }, _kids, [], [0, 0, 0], 5] call comspec_atak_native_fnc_aceAction;
        missionNamespace setVariable ["COMSPEC_ATAK_WallRoot", _root];
        private _classes = (["classes"] call _fn) apply { _x select 1 };
        missionNamespace setVariable ["COMSPEC_ATAK_WallClasses", _classes];
        { [_x, 0, ["ACE_MainActions"], _root] call ace_interact_menu_fnc_addActionToClass; } forEach _classes;
        true
    };
    case "aceObj": {
        // Écran d'une autre classe (moniteur, TV d'un mod) désigné par un module ou une diffusion : même racine, une fois.
        private _o = _a;
        if (isNull _o || {!hasInterface} || {isNil "ace_interact_menu_fnc_addActionToObject"}) exitWith { false };
        ["aceInit"] call _fn;
        if ((typeOf _o) in (missionNamespace getVariable ["COMSPEC_ATAK_WallClasses", []])) exitWith { false };
        // Toile de projection : pilotée depuis son projecteur (fn_projector, même sous-menus), pas de second menu.
        if ((getNumber (configOf _o >> "comspec_projBoard")) > 0) exitWith { false };
        if (_o getVariable ["COMSPEC_ATAK_WallObj", false]) exitWith { false };
        _o setVariable ["COMSPEC_ATAK_WallObj", true];
        [_o, 0, [], missionNamespace getVariable "COMSPEC_ATAK_WallRoot"] call ace_interact_menu_fnc_addActionToObject;
        true
    };
    case "actions": {
        private _o = _a;
        ["aceInit"] call _fn;
        if (!isNil "ace_interact_menu_fnc_createAction") exitWith { ["aceObj", _o] call _fn };
        // Sans ACE : actions de la molette, une fois par objet.
        if (_o getVariable ["COMSPEC_ATAK_TocWheel", false]) exitWith { false };
        _o setVariable ["COMSPEC_ATAK_TocWheel", true];
        private _cond = "private _o = _target; private _c = _o getVariable ['COMSPEC_ATAK_TocScr', []]; (count _c) >= 8 && {(_c select 1) isEqualTo '' || {(_c select 1) isEqualTo str side group player}}";
        {
            _x params ["_lbl", "_k", "_prio"];
            _o addAction [format ["<t color='#7fd0b0'>%1</t>", _lbl call comspec_atak_native_fnc_tr], { [_this select 0, _this select 3] call COMSPEC_ATAK_TocDo; }, _k, _prio, false, true, "",
                _cond + ([" ", " && {_o getVariable ['COMSPEC_ATAK_Toc', false]}"] select (_k isEqualTo "big")), 5];
        } forEach [
            ["Écran TOC : PIP du poste", "pip", 1.46], ["Écran TOC : changer d'affichage", "cycle", 1.45],
            ["Écran TOC : zoomer", "in", 1.44], ["Écran TOC : dézoomer", "out", 1.43],
            ["Écran TOC : cadrer tout le réseau", "auto", 1.42], ["Écran TOC : ouvrir le PC du poste", "big", 1.41],
            ["Écran TOC : diffuser mes diapositives", "slides", 1.40], ["Écran TOC : caméra suivante du camp", "camNext", 1.39]
        ];
        true
    };
    default { false };
};
