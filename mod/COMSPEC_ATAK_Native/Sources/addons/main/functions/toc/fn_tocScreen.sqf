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
                           | "CAM" | "SAT" | "CLOCK" | "SQUAD" | "LOG" | "EXCHECK" | "KILLS" | "REQ" | "TEXT" (texte à LED,
                           fn_tocText) | "COLA" | "COLB" | "COLC" | "COLD" | "COLE" | "COLF" (colonnes d'un écran incurvé ;
                           dispositions d'un écran à plusieurs dalles : fn_tocLayout)),
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
                                           | "text" réglage de l'affichage TEXTE (fn_tocText)
      ["squads", camp]                     groupes proposés pour l'affichage SQUAD (noms, camp du joueur si "")
      ["classes"]                          écrans proposés à la pose [[nom, classe, aperçu]...] (classes présentes) :
                                           écrans COMSPEC puis CfgComspecTocScreens (jeu et autres mods)
      ["surfaces", objet]                  surfaces d'image d'un objet [[[nom, index]...], index proposé] ([[], -1] : aucune)
      ["test", [objet, dalle]]             client : image de test 8 s sur la dalle (fenêtre Zeus « Tester l'image »)
    Écran d'un autre mod : CfgComspecTocScreens (toc_screens_tiers.hpp) donne sa surface, ses dalles recopiées, son
    format et sa matière ; COMSPEC_ATAK_TocSurf [index, format, matière] (fenêtre Zeus) l'emporte sur tout.
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
      ["format", objet]                    format de la dalle : [rapport, texture, l / h de la texture, format, affichages permis, défaut]
      ["params", affichage]                paramètres propres à l'affichage [[clé, libellé, type, défaut, choix]...] (TEXT)
      ["views"]                            liste ordonnée des affichages (fenêtre Zeus et attributs Eden : fn_tocConfig)
    Serveur plus ancien que le joueur, ou sans le mod (fn_tocConfig « local ») : « add », « panels », « remove »,
    « spawn » et « view » se font sur le poste du joueur, par les mêmes variables publiques ; rien n'est alors gardé
    par le serveur. COMSPEC_ATAK_TocOpt [luminosité, distance d'affichage] : réglages de l'écran lus par « tick ».
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
private _views = ["SPLIT", "MAP", "FEED", "NET", "ORBAT", "TOC", "CAM", "SAT", "CLOCK", "SQUAD", "LOG", "EXCHECK", "KILLS", "REQ", "RADIO", "STRIP", "PLATE", "MOSAIC", "TEXT", "BROADCAST", "COLA", "COLB", "COLC", "COLD", "COLE", "COLF"];
// Formats d'image selon le rapport largeur / hauteur de la dalle (comspec_screenRatio, comspec_screenPanelRatio par dalle,
// 16:9 sans indication). Une texture procédurale n'est acceptée qu'en puissances de 2 : la texture la plus proche du
// format est prise (pixels carrés pour 4:1, 8:1 et 16:1 ; 16:9 sur 2:1 et colonne 8:9 sur 1:1, même léger tassement).
// ---- TABLE _formats (JSON) : [rapport maximal (exclu), format, texture, largeur / hauteur de la texture, affichages
// permis ([] : tous ceux d'un écran 16:9), affichage par défaut].
private _formats = [
[1.3, "COL", "#(rgb,1024,1024,1)", 1, ["COLA", "COLB", "COLC", "COLD", "COLE", "COLF", "TEXT"], "COLA"],
[3, "STD", "#(rgb,2048,1024,1)", 2, [], "SPLIT"],
[6, "STRIP", "#(rgb,2048,512,1)", 4, ["STRIP", "PLATE", "TEXT"], "STRIP"],
[12, "LED", "#(rgb,2048,256,1)", 8, ["TEXT"], "TEXT"],
[999, "LED", "#(rgb,4096,256,1)", 16, ["TEXT"], "TEXT"]
];
// ---- FIN
// Affichages dessinés en tableau (fn_tocBoard) au lieu de la carte du poste ; STRIP et PLATE : faces 4:1 seulement.
// Thème TOC (fn_tocTheme, docs/TOC-THEME.md) : seule source des couleurs. _tcol : RGBA d'un jeton ("bg0", "friend"…).
private _tcol = { params ["_k", ["_al", 1]]; ["rgba", [_k, _al]] call comspec_atak_native_fnc_tocTheme };
private _boards = ["SAT", "CLOCK", "LOG", "EXCHECK", "KILLS", "REQ", "RADIO", "STRIP", "PLATE", "MOSAIC", "FEED", "NET", "ORBAT", "COLA", "COLB", "COLC", "COLD", "COLE", "COLF"];
// Dalle d'un mur multi-écrans : objet relais (fn_tocScreen « panels ») qui porte son propre affichage ; l'image va
// sur la dalle de l'écran hôte (COMSPEC_ATAK_TocHost), à l'index de la dalle.
private _hostOf = { params ["_o"]; private _h = _o getVariable ["COMSPEC_ATAK_TocHost", objNull]; [_o, _h] select (!isNull _h) };
// Écran du jeu ou d'un autre mod déclaré dans CfgComspecTocScreens (toc_screens_tiers.hpp) : entrée de sa classe ou de
// la classe parente la plus proche (variantes d'Eden Expansion, etc.) ; configNull sinon. Objet ou nom de classe.
private _entryOf = {
    params ["_o"];
    // Jamais les deux lectures dans un même tableau : les deux y sont évaluées, et « config >> objet » (comme « configOf
    // texte ») est une erreur qui arrête tout le script appelant (menu ACE de l'écran vide, pose d'une salle arrêtée au
    // premier écran : journal de TTRD, 4.7.0, « Type Objet, Chaîne attendu »).
    private _c = if (_o isEqualType "") then { configFile >> "CfgVehicles" >> _o } else { configOf _o };
    private _e = configNull;
    for "_i" from 0 to 8 do {
        if (isNull _c || {!isNull _e}) exitWith {};
        private _t = configFile >> "CfgComspecTocScreens" >> configName _c;
        if (isClass _t) then { _e = _t; } else { _c = inheritsFrom _c; };
    };
    _e
};
// Index d'une sélection cachée par son nom (-1 : absente).
private _selIdx = { params ["_o", "_n"]; (getArray (configOf _o >> "hiddenSelections")) findIf { (toLower _x) isEqualTo (toLower _n) } };
// Surface imposée à la désignation (fenêtre Zeus sur un objet) : COMSPEC_ATAK_TocSurf [index, format, matière d'écran].
// Face 4:1 (bandeau horloge, plaque de nom, ou format « bandeau » choisi) : texture 2048 x 512.
private _ratioOf = {
    params ["_o"];
    private _h = [_o] call _hostOf;
    // Mur dont les dalles n'ont pas toutes le même format (écran incurvé : zone centrale 16:9, colonnes 8:9).
    private _pr = getArray (configOf _h >> "comspec_screenPanelRatio");
    if (_pr isNotEqualTo []) exitWith { _pr param [[0, _o getVariable ["COMSPEC_ATAK_TocPanel", 0]] select (_o isNotEqualTo _h), 1.778] };
    (getNumber (configOf _h >> "comspec_screenRatio")) max ((_h getVariable ["COMSPEC_ATAK_TocSurf", []]) param [1, 0]) max (getNumber (([_h] call _entryOf) >> "ratio"))
};
// Format de la dalle d'un écran : [rapport, texture, largeur / hauteur de la texture, format, affichages permis, défaut].
private _fmtOf = {
    params ["_o"];
    private _rat = [_o] call _ratioOf;
    if (_rat <= 0) then { _rat = 1.778; };
    (_formats select ((_formats findIf { _rat < (_x select 0) }) max 0)) params ["", "_kind", "_spec", "_pxA", "_ok", "_def"];
    private _h = [_o] call _hostOf;
    if (_kind isEqualTo "STD") then {
        _ok = _views - ["STRIP", "PLATE", "COLA", "COLB", "COLC", "COLD", "COLE", "COLF"];
        // Budget de rendu des grands murs : dalle 55" d'un mur de 7 zones ou plus en 1024 x 512 (même finesse au mètre
        // qu'un mur 2x2 en 2048 x 1024) ; le bloc central d'un mur à zones garde la grande texture.
        private _np = getNumber (configOf _h >> "comspec_screenPanels");
        if (_np >= 7 && {_o isNotEqualTo _h || {_np >= 12}}) then { _spec = "#(rgb,1024,512,1)"; };
    };
    if (_kind isEqualTo "STRIP" && {((typeOf _h) find "NamePlate") >= 0}) then { _ok = ["PLATE"]; _def = "PLATE"; };
    // Colonne : affichage par défaut de la dalle donné par la config (colonne horloge à gauche, colonne flux à droite).
    if (_kind isEqualTo "COL") then {
        private _pv = (getArray (configOf _h >> "comspec_screenPanelViews")) param [[0, _o getVariable ["COMSPEC_ATAK_TocPanel", 0]] select (_o isNotEqualTo _h), ""];
        if (_pv in _ok) then { _def = _pv; };
    };
    // Texture 4096 refusée sur ce poste (jamais chargée) : même format en 2048.
    if (_o getVariable ["COMSPEC_ATAK_TocTexLow", false] && {(_spec find "4096") >= 0}) then { _spec = "#(rgb,2048,128,1)"; };
    [_rat, _spec, _pxA, _kind, _ok, _def]
};
// Affichage demandé ramené à un affichage permis par le format de la dalle (une carte 16:9 serait écrasée sur un bandeau).
private _fitView = {
    params ["_o", "_v"];
    ([_o] call _fmtOf) params ["", "", "", "_kind", "_ok", "_def"];
    if (_v in _ok) exitWith { _v };
    // Affichage jumeau du format (fn_tocLayout « twin » : horloge <-> colonne horloge, mosaïque <-> colonne caméras…).
    private _tw = ["twin", [_v, _kind]] call comspec_atak_native_fnc_tocLayout;
    if (_tw in _ok) exitWith { _tw };
    if (_kind isNotEqualTo "STD") exitWith { _def };
    ["SPLIT", "CLOCK"] select (_v in ["STRIP", "PLATE", "COLA"])
};
// Dalles qui recopient l'image : config de l'écran COMSPEC (index), sinon entrée CfgComspecTocScreens (noms).
private _mirrorOf = {
    params ["_o"];
    private _m = getArray (configOf _o >> "comspec_screenMirror");
    if (_m isNotEqualTo [] || {(count (_o getVariable ["COMSPEC_ATAK_TocSurf", []])) >= 1}) exitWith { _m };
    ((getArray (([_o] call _entryOf) >> "mirror")) apply { [_o, _x] call _selIdx }) select { _x >= 0 }
};
// Matière d'écran du mod (jour / nuit) sur la dalle : oui, sauf si la désignation ou l'entrée demande la matière d'origine.
private _matOn = {
    params ["_o"];
    private _f = _o getVariable ["COMSPEC_ATAK_TocSurf", []];
    if ((count _f) >= 3) exitWith { _f select 2 };
    private _e = [_o] call _entryOf;
    isNull _e || {!isNumber (_e >> "material")} || {(getNumber (_e >> "material")) > 0}
};
// Nom de sélection qui évoque un écran (proposition automatique de la surface).
private _screenish = { params ["_n"]; _n = toLower _n; (["screen", "display", "monitor", "lcd", "ecran", "écran"] findIf { (_n find _x) >= 0 }) >= 0 || {_n in ["tv", "tv_1", "tv1"]} };
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
// Ordre : surface choisie à la désignation, puis surface connue (CfgComspecTocScreens), puis nom évoquant un écran.
private _face = {
    params ["_o", "_sel"];
    private _forced = _o getVariable ["COMSPEC_ATAK_TocSurf", []];
    if ((count _forced) >= 1) exitWith { _forced select 0 };
    private _known = [_o, getText (([_o] call _entryOf) >> "selection")] call _selIdx;
    if (_known >= 0) exitWith { _known };
    private _names = getArray (configOf _o >> "hiddenSelections");
    private _named = _names findIf { [_x] call _screenish };
    if (_named >= 0 && {!([_names param [_sel, ""]] call _screenish)}) then { _named } else { _sel }
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
    private _rv = missionNamespace getVariable ["comspec_atak_native_toc_view_range", 25];
    if !(_rv isEqualType 0) then { _rv = 25; };
    (_rv max 10) min 100
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
    private _cw = (_c getVariable ["COMSPEC_ATAK_Fpx", 28]) * ([[0.46, 0.52] select (_c getVariable ["COMSPEC_ATAK_Bold", false]), 0.5] select (_c getVariable ["COMSPEC_ATAK_Mono", false]));
    private _room = _w - 8;
    private _s = [_t call comspec_atak_native_fnc_tr, floor (_room / _cw)] call _cut;
    _c ctrlSetText _s; // i18n:brut (déjà traduit)
    for "_i" from 1 to 6 do {
        private _m = (ctrlTextWidth _c) * _texW;
        forceUnicode 0;
        private _n = count _s;
        // Mesure du jeu crue dès qu'elle existe (avant : ignorée au-delà de 2 x l'estimation, et le texte débordait de
        // sa case, coupé au bord sans « … », quand la police sortait plus large que prévu sur le poste du joueur).
        if (_m <= 0 || {_m <= _room} || {_n <= 2}) exitWith {};
        _s = [_s, (floor (_n * _room / _m)) min (_n - 1)] call _cut;
        _c ctrlSetText _s; // i18n:brut (déjà traduit)
    };
};

switch (_mode) do {
    case "initServer": {
        if (!isServer) exitWith {};
        // « view » et « remove » venus de l'ancien événement : droits d'un simple opérateur (fn_tocAccess « legacy »).
        ["comspec_atak_native_tocScreen", { if (["legacy", ["screen", _this]] call comspec_atak_native_fnc_tocAccess) then { _this call comspec_atak_native_fnc_tocScreen; }; }] call CBA_fnc_addEventHandler;
        ["init"] call comspec_atak_native_fnc_tocFeed;
        ["init"] call comspec_atak_native_fnc_tocRoom;
        ["init"] call comspec_atak_native_fnc_tocHall;
        ["initServer"] call comspec_atak_native_fnc_tocConfig;
        // Écrans et postes PC réglés tout seuls à la pose (fn_tocAuto), sans attendre un joueur.
        ["init"] call comspec_atak_native_fnc_tocAuto;
        // Écrans gardés de la partie précédente : remis une fois les modules Eden passés.
        [{ ["restore"] call comspec_atak_native_fnc_tocPersist; }, [], 3] call comspec_atak_native_fnc_waitAndExec;
        true
    };
    case "classes": {
        // Ordre de la liste : bureau, postes PC, trépied, mur, suspendus, murs vidéo, géants, incurvé, bandeaux LED et plaque.
        // Écrans COMSPEC en tête (modèles réglés pour l'image, fiables jour et nuit), puis les écrans du jeu et des mods
        // déclarés dans CfgComspecTocScreens (toc_screens_tiers.hpp et ceux des autres mods), seulement les classes
        // présentes. [nom de l'objet (langue du joueur), classe, aperçu Eden ("" sans)] : la classe passe telle quelle
        // jusqu'à la pose (« spawn »).
        private _out = [];
        private _add = {
            params ["_c"];
            private _cfg = configFile >> "CfgVehicles" >> _c;
            if (!isClass _cfg || {(getNumber (_cfg >> "scope")) < 1} || {(_out findIf { (_x select 1) isEqualTo _c }) >= 0}) exitWith {};
            private _n = getText (_cfg >> "displayName");
            _out pushBack [[_n, _c] select (_n isEqualTo ""), _c, getText (_cfg >> "editorPreview")];
        };
        {
            [_x] call _add;
        } forEach [
            "COMSPEC_ATAK_Screen_Desk", "COMSPEC_ATAK_Screen_Desk2", "COMSPEC_ATAK_Screen_Desk3", "COMSPEC_ATAK_TocPC_1",
            "COMSPEC_ATAK_TocPC_2", "COMSPEC_ATAK_TocPC_3", "COMSPEC_ATAK_TocPC_Curved", "COMSPEC_ATAK_Screen_Tripod",
            "COMSPEC_ATAK_Screen_Dual", "COMSPEC_ATAK_Screen_Wall", "COMSPEC_ATAK_Screen_Wall75", "COMSPEC_ATAK_Screen_98",
            "COMSPEC_ATAK_Screen_98_Stand", "COMSPEC_ATAK_Screen_65_Ceiling", "COMSPEC_ATAK_Screen_65_Ceiling2", "COMSPEC_ATAK_Screen_65_Ceiling3",
            "COMSPEC_ATAK_Screen_65_Ceiling3_Arc", "COMSPEC_ATAK_Screen_98_Ceiling", "COMSPEC_ATAK_Screen_Wall2x2", "COMSPEC_ATAK_Screen_Wall2x2_Quad",
            "COMSPEC_ATAK_Screen_Wall3x2", "COMSPEC_ATAK_Screen_Wall3x2_Mixed", "COMSPEC_ATAK_Screen_Wall4x3", "COMSPEC_ATAK_Screen_Wall4x3_Panels",
            "COMSPEC_ATAK_Screen_Wall5x3", "COMSPEC_ATAK_Screen_Wall5x3_Panels", "COMSPEC_ATAK_Screen_Wall6x4", "COMSPEC_ATAK_Screen_Wall6x4_Zones",
            "COMSPEC_ATAK_Screen_136", "COMSPEC_ATAK_Screen_136_Stand", "COMSPEC_ATAK_Screen_Led220", "COMSPEC_ATAK_Screen_Led220_Truss",
            "COMSPEC_ATAK_Screen_Curved", "COMSPEC_ATAK_Screen_Curved_Duo", "COMSPEC_ATAK_Screen_Curved_Wall", "COMSPEC_ATAK_Screen_Curved_Wall_Duo",
            "COMSPEC_ATAK_Screen_ClockStrip", "COMSPEC_ATAK_Screen_LedText8", "COMSPEC_ATAK_Screen_LedText16", "COMSPEC_ATAK_Screen_LedText8_Ceiling",
            "COMSPEC_ATAK_Screen_LedText16_Ceiling", "COMSPEC_ATAK_Screen_NamePlate"
        ];
        { [configName _x] call _add; } forEach ("true" configClasses (configFile >> "CfgComspecTocScreens"));
        _out
    };
    case "surfaces": {
        // Surfaces possibles d'un objet (fenêtre Zeus « Écran TOC » sur un objet) : [[[nom, index]...], index proposé].
        // Proposé : surface déjà choisie, sinon surface connue (CfgComspecTocScreens), sinon nom évoquant un écran, sinon 0.
        // Aucune sélection cachée : [[], -1] (l'objet ne peut pas recevoir d'image).
        private _o = _a;
        if (isNull _o) exitWith { [[], -1] };
        private _names = getArray (configOf _o >> "hiddenSelections");
        if (_names isEqualTo []) exitWith { [[], -1] };
        private _list = [];
        { _list pushBack [_x, _forEachIndex]; } forEach _names;
        [_list, (([_o, 0] call _face) max 0) min ((count _names) - 1)]
    };
    case "test": {
        // « Tester l'image » : fond COMSPEC (orientation lisible) sur la surface choisie, 8 s, sur ce poste seulement,
        // puis texture d'avant remise. L'objet n'est pas désigné.
        _a params [["_o", objNull], ["_sel", 0]];
        if (isNull _o || {!hasInterface}) exitWith { false };
        private _old = (getObjectTextures _o) param [_sel, ""];
        _o setObjectTexture [_sel, "\z\comspec_atak_native\addons\main\data\wall_athena_land.paa"];
        // Remise seulement si l'image de test est toujours là (une image TOC posée entre-temps n'est pas écrasée).
        [{ params ["_o", "_sel", "_old"]; if (!isNull _o && {((toLower ((getObjectTextures _o) param [_sel, ""])) find "wall_athena_land") >= 0}) then { _o setObjectTexture [_sel, _old]; }; }, [_o, _sel, _old], 8] call comspec_atak_native_fnc_waitAndExec;
        ["INFO", "TOC", format ["Écran TOC : test de l'image sur %1, surface %2 (%3)", typeOf _o, _sel, (getArray (configOf _o >> "hiddenSelections")) param [_sel, "?"]]] call comspec_atak_native_fnc_log;
        true
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
        // Objet inconnu de ce serveur (classe absente d'un mod plus ancien) : dit au journal, plus jamais muet.
        if (isNull _o) exitWith {
            if (isDedicated) then { ["WARNING", "TOC", "Écran TOC : désignation demandée pour un objet inconnu de ce serveur (mod du serveur plus ancien que celui du joueur ?)"] call comspec_atak_native_fnc_log; };
            false
        };
        // Serveur à jour : il désigne, et le joueur vérifie 3 s plus tard que c'est fait (« ack »). Serveur ancien ou
        // sans le mod : le joueur désigne lui-même (fn_tocConfig « local »), avec la raison au téléphone.
        if (!isServer && {!(["local", _o] call comspec_atak_native_fnc_tocConfig)}) exitWith {
            ["comspec_atak_native_tocScreen", ["add", _a]] call CBA_fnc_serverEvent;
            ["ack", [_o, "toc", _a]] call comspec_atak_native_fnc_tocConfig;
            true
        };
        if (_name isEqualTo "") then { _name = "TOC"; };
        private _old = [_o] call _cfgOf;
        if !(_view in _views) then {
            ["WARNING", "TOC", format ["Écran TOC %1 : affichage « %2 » inconnu de ce serveur, carte et flux à la place", typeOf _o, _view]] call comspec_atak_native_fnc_log;
            _view = "SPLIT";
        };
        // Affichage permis par le format de la dalle : bandeau 4:1 (horloge, plaque, texte), bandeau LED (texte),
        // colonne (colonne horloge, colonne flux, texte) ; un écran 16:9 ne prend ni bandeau, ni plaque, ni colonne.
        _view = [_o, _view] call _fitView;
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
        if (isNull _h || {!isServer && {!(["local", _h] call comspec_atak_native_fnc_tocConfig)}}) exitWith { [] };
        private _n = getNumber (configOf _h >> "comspec_screenPanels");
        private _px = (_h getVariable ["COMSPEC_ATAK_TocPanels", []]) select { !isNull _x };
        // Deux relais pour la même dalle (mur réglé par deux machines à la fois) : le premier reste, l'autre est retiré.
        private _seenK = [];
        {
            private _pk = _x getVariable ["COMSPEC_ATAK_TocPanel", 0];
            if (_pk in _seenK) then { _px = _px - [_x]; ["remove", _x] call _fn; } else { _seenK pushBack _pk; };
        } forEach +_px;
        private _hc = [_h] call _cfgOf;
        private _pc = _h getVariable ["COMSPEC_ATAK_TocPanelCfg", []];
        for "_k" from 1 to (_n - 1) do {
            if ((_px findIf { (_x getVariable ["COMSPEC_ATAK_TocPanel", 0]) isEqualTo _k }) < 0) then {
                private _rv = createVehicle ["Land_HelipadEmpty_F", getPosATL _h, [], 0, "CAN_COLLIDE"];
                _rv attachTo [_h, [0, 0, 1.5]];
                _rv setVariable ["COMSPEC_ATAK_TocHost", _h, true];
                _rv setVariable ["COMSPEC_ATAK_TocPanel", _k, true];
                _rv setVariable ["COMSPEC_ATAK_TocOwned", true, true];
                // Sans réglage : affichage par défaut de la dalle (comspec_screenPanelViews), sinon carte et flux.
                private _dv = (getArray (configOf _h >> "comspec_screenPanelViews")) param [_k, ""];
                (_pc param [_k - 1, []]) params [["_v", [_dv, "SPLIT"] select (_dv isEqualTo "")], ["_sl", 0], ["_sq", ""], ["_cam", []], ["_mos", []], ["_off", false], ["_txt", []], ["_bc", []]];
                if (_bc isEqualType [] && {(count _bc) >= 4}) then { _rv setVariable ["COMSPEC_ATAK_TocBc", ["sanitize", _bc] call comspec_atak_native_fnc_tocBroadcast, true]; };
                if (_txt isEqualType [] && {(count _txt) >= 5}) then { _rv setVariable ["COMSPEC_ATAK_TocText", ["sanitize", _txt] call comspec_atak_native_fnc_tocText, true]; };
                if (_mos isEqualType [] && {(count _mos) >= 5}) then { _rv setVariable ["COMSPEC_ATAK_TocMosaic", _mos, true]; };
                if (_off isEqualTo true) then { _rv setVariable ["COMSPEC_ATAK_TocOff", true, true]; };
                ["add", [_rv, format ["%1 · %2", (_hc param [0, "TOC"]) select [0, 22], _k + 1], _hc param [1, ""], _k, _v, false, false, _sl, _sq]] call _fn;
                if (_cam isEqualType [] && {(count _cam) >= 2}) then { _rv setVariable ["COMSPEC_ATAK_TocCamWant", _cam]; };
                _px pushBack _rv;
            };
        };
        _h setVariable ["COMSPEC_ATAK_TocPanels", _px, true];
        _px
    };
    case "panelSpec": {
        // Contenu des dalles 2 et suivantes écrit à la main (module) : « SAT1, CLOCK, SQ:Alpha 1-1, LOG, TXT:Bienvenue ».
        if !(_a isEqualType "") exitWith { [] };
        (_a splitString ",") apply {
            private _t = trim _x;
            private _u = toUpper _t;
            switch (true) do {
                case (_u isEqualTo "SAT1"): { ["SAT", 0, ""] };
                case (_u isEqualTo "SAT2"): { ["SAT", 1, ""] };
                // Dalle éteinte (veille) : carte et flux gardés dessous.
                case (_u isEqualTo "OFF"): { ["SPLIT", 0, "", [], [], true] };
                case ((_u select [0, 3]) isEqualTo "SQ:"): { ["SQUAD", 0, trim (_t select [3])] };
                // « TXT:message » : affichage TEXTE avec ce message (réglages par défaut).
                case ((_u select [0, 4]) isEqualTo "TXT:"): { ["TEXT", 0, "", [], [], false, ["parse", [_t select [4]]] call comspec_atak_native_fnc_tocText] };
                case (_u in ["MOS2", "MOS3"]): { ["MOSAIC", 0, "", [], [[2, 3] select (_u isEqualTo "MOS3"), "AUTO", "", [], 10]] };
                case (_u in _views): { [_u, 0, ""] };
                default { ["SPLIT", 0, ""] };
            }
        }
    };
    case "format": {
        // Format de la dalle d'un écran (ou d'une dalle de mur) : [rapport, texture, largeur / hauteur de la texture,
        // "STD" | "STRIP" | "LED" | "COL", affichages permis, affichage par défaut]. Sans objet : celui d'un écran 16:9.
        if (_a isEqualType objNull && {!isNull _a}) then { [_a] call _fmtOf } else { [1.778, _texSpec, 2, "STD", _views - ["STRIP", "PLATE", "COLA", "COLB", "COLC", "COLD", "COLE", "COLF"], "SPLIT"] }
    };
    case "params": {
        // Paramètres propres à un affichage, pour les fenêtres de réglage (module, Zeus, PC) : [[clé, libellé, type,
        // défaut, choix]...] ; [] : aucun. Seul l'affichage TEXTE en a (fn_tocText « schema »).
        [[], ["schema"] call comspec_atak_native_fnc_tocText] select (_a isEqualTo "TEXT")
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
        if (!isServer && {!(["local", _a] call comspec_atak_native_fnc_tocConfig)}) exitWith { ["send", ["screen", ["remove", _a]]] call comspec_atak_native_fnc_tocAccess; true };
        private _owned = _a getVariable ["COMSPEC_ATAK_TocOwned", false];
        // Mur à plusieurs dalles : ses relais partent avec lui.
        { if (!isNull _x) then { ["remove", _x] call _fn; }; } forEach (_a getVariable ["COMSPEC_ATAK_TocPanels", []]);
        _a setVariable ["COMSPEC_ATAK_TocPanels", nil, true];
        _a setVariable ["COMSPEC_ATAK_TocScr", nil, true];
        _a setVariable ["COMSPEC_ATAK_Toc", nil, true];
        _a setVariable ["COMSPEC_ATAK_TocSurf", nil, true];
        _a setVariable ["COMSPEC_ATAK_TocBc", nil, true];
        missionNamespace setVariable ["COMSPEC_ATAK_TocScreens", (missionNamespace getVariable ["COMSPEC_ATAK_TocScreens", []]) select { !isNull _x && {_x isNotEqualTo _a} }, true];
        // Écran créé par le module (ou recréé par la sauvegarde) : l'objet part avec lui.
        if (_owned) then { deleteVehicle _a; };
        ["save"] call comspec_atak_native_fnc_tocPersist;
        true
    };
    case "spawn": {
        _a params [["_pos", [0, 0, 0]], ["_dir", 0], ["_cls", ""], ["_name", ""], ["_side", ""], ["_view", "SPLIT"], ["_toc", true], ["_slot", 0], ["_squad", ""], ["_pans", ""], ["_extra", []]];
        if (!isServer && {!(["local", objNull] call comspec_atak_native_fnc_tocConfig)}) exitWith { ["comspec_atak_native_tocScreen", ["spawn", _a]] call CBA_fnc_serverEvent; objNull };
        // La classe choisie (module Eden, fenêtre Zeus, salle complète) passe telle quelle jusqu'à createVehicle : écran de
        // la liste, écran COMSPEC ou écran déclaré dans CfgComspecTocScreens. Classe inconnue de ce serveur (mod du serveur
        // plus ancien que celui du Zeus) : premier écran de la liste, et le journal le dit. Avant, ce repli était muet et
        // posait toujours le même grand écran sur trépied, quel que soit le choix (journal de TTRD, 11/11).
        private _cands = (["classes"] call _fn) apply { _x select 1 };
        private _want = _cls;
        if !(_cls isEqualType "" && {_cls isNotEqualTo ""} && {isClass (configFile >> "CfgVehicles" >> _cls)}
            && {(_cls in _cands) || {(getNumber (configFile >> "CfgVehicles" >> _cls >> "comspec_screen")) > 0} || {!isNull ([_cls] call _entryOf)}}) then {
            _cls = _cands param [0, ""];
            ["WARNING", "TOC", format ["Écran TOC : écran « %1 » inconnu de ce serveur (mod du serveur pas à jour ?), « %2 » posé à la place", _want, _cls]] call comspec_atak_native_fnc_log;
        };
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
        // Réglages propres à un affichage, donnés à la pose : [[clé de « view », valeur]...] (« text » : affichage TEXTE).
        { if (_x isEqualType [] && {(count _x) >= 2}) then { ["view", [_o, _x select 0, _x select 1]] call _fn; }; } forEach _extra;
        // Trace de la pose : la classe réellement créée et l'affichage demandé (vérifiable dans le journal du serveur).
        ["INFO", "TOC", format ["Écran TOC posé : %1 (%2), affichage %3, nom « %4 »", _cls, getText (configOf _o >> "displayName"), _view, [_name, "TOC"] select (_name isEqualTo "")]] call comspec_atak_native_fnc_log;
        // Zeus : l'écran créé se manipule comme un objet posé à la main.
        { _x addCuratorEditableObjects [[_o], false]; } forEach allCurators;
        _o
    };
    case "view": {
        _a params [["_o", objNull], ["_k", ""], ["_v", 0]];
        if (isNull _o) exitWith { false };
        if (!isServer && {!(["local", _o] call comspec_atak_native_fnc_tocConfig)}) exitWith { ["send", ["screen", ["view", _a]]] call comspec_atak_native_fnc_tocAccess; true };
        private _c = +([_o] call _cfgOf);
        if ((count _c) < 8) exitWith { false };
        switch (_k) do {
            // Seulement un affichage permis par le format de la dalle (une carte 16:9 serait écrasée sur un bandeau).
            case "mode": { if (_v in _views && {_v in (([_o] call _fmtOf) select 4)}) then { _c set [3, _v]; }; };
            // Affichage TEXTE (fn_tocText) : réglage nettoyé par le serveur avant d'être publié.
            case "text": { _o setVariable ["COMSPEC_ATAK_TocText", ["sanitize", _v] call comspec_atak_native_fnc_tocText, true]; };
            // Caméra en direct (fn_tocCam) : seule la source choisie circule (netId), chaque joueur fait son rendu.
            case "cam": { if (_v isEqualType "" && {(count _v) < 40}) then { _o setVariable ["COMSPEC_ATAK_TocCam", _v, true]; _o setVariable ["COMSPEC_ATAK_TocCamWant", nil]; }; };
            case "zoom": { _c set [4, ((_v max 100) min (worldSize max 1000))]; };
            case "center": { if (_v isEqualType [] && {(count _v) >= 2}) then { _c set [5, [_v select 0, _v select 1]]; }; };
            case "auto": { _c set [4, 0]; _c set [5, []]; };
            case "sat": { if (_v in [0, 1]) then { _c set [8, _v]; }; };
            case "squad": { if (_v isEqualType "") then { _c set [9, _v select [0, 40]]; }; };
            // Mosaïque de caméras (fn_tocMosaic) : [grille, choix, groupe, cases, rotation].
            case "mosaic": { if (_v isEqualType [] && {(count _v) >= 5}) then { _o setVariable ["COMSPEC_ATAK_TocMosaic", _v, true]; }; };
            // Diffusion (fn_tocBroadcast) : source, titre, témoin, mire, groupe ; nettoyé par le serveur avant d'être publié.
            case "bc": { _o setVariable ["COMSPEC_ATAK_TocBc", ["sanitize", _v] call comspec_atak_native_fnc_tocBroadcast, true]; };
            // Luminosité (25 à 100) et distance d'affichage (0 : réglage de chaque joueur), sur l'écran hôte.
            case "opt": {
                if (_v isEqualType [] && {(count _v) >= 2} && {(_v select 0) isEqualType 0} && {(_v select 1) isEqualType 0}) then {
                    ([_o] call _hostOf) setVariable ["COMSPEC_ATAK_TocOpt", [(round (_v select 0)) max 25 min 100, [0, (round (_v select 1)) max 5 min 150] select ((_v select 1) > 0)], true];
                };
            };
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
            { if ((_mats param [_x, ""]) isNotEqualTo "") then { _o setObjectMaterial [_x, _mats select _x]; }; } forEach ([_o] call _mirrorOf);
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
            // Dalles recopiées (poste à trois moniteurs) : gardées, elles montrent la même image.
            private _sibs = [];
            private _mirs = [_o] call _mirrorOf;
            if ((count _panes) > 2 && {_sel in _panes}) then {
                { if (_x isNotEqualTo _sel && {!(_x in _mirs)}) then { _sibs pushBack [_x, _texs param [_x, ""]]; _o setObjectTexture [_x, ""]; }; } forEach _panes;
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
        // Matière d'origine demandée (écran d'un autre mod déjà lumineux) : rien n'est imposé.
        if ([_o] call _matOn && {(_prep select 3) isNotEqualTo _mat || {_cur isNotEqualTo "" && {_cur isNotEqualTo (_mat call _norm)}}}) then {
            _prep set [3, _mat];
            _o setObjectMaterial [_sel, _mat];
            { _o setObjectMaterial [_x, _mat]; } forEach ([_o] call _mirrorOf);
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
        private _mir = [_o] call _mirrorOf;
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
        _o setVariable ["COMSPEC_ATAK_TocFails", 0];
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
            ["dim", [_o, _d]] call comspec_atak_native_fnc_tocConfig;
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
        // Écran à distance d'affichage propre (COMSPEC_ATAK_TocOpt, 150 m au plus) : cases voisines assez larges pour lui.
        private _span = ceil ((_hold max 225) / 100);
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
            // Distance d'affichage de cet écran (fenêtre Zeus, attribut Eden) ; 0 : réglage du joueur.
            private _own = (([_o] call _hostOf) getVariable ["COMSPEC_ATAK_TocOpt", []]) param [1, 0];
            private _rg = [_range, _own] select (_own isEqualType 0 && {_own > 0});
            if (_ok && {_dist < _rg}) then {
                _seen = _dist < 3 || {((vectorNormalized (_ctr vectorDiff _eye)) vectorDotProduct _dir) > -0.17};
                // Derrière un mur : point visé à 15 cm devant la dalle, l'écran lui-même ignoré ; jamais tout près.
                if (_seen && {_dist > 5}) then {
                    private _vis = _o getVariable ["COMSPEC_ATAK_TocVis", [-1e9, true, 0]];
                    if (_now - (_vis select 0) > 3) then {
                        private _to = _ctr vectorAdd ((vectorNormalized (_eye vectorDiff _ctr)) vectorMultiply 0.15);
                        // Retenue : « caché » seulement après deux contrôles ratés de suite (6 s). Un seul contrôle raté
                        // (joueur qui passe, pied d'écran, chaise) éteignait l'écran puis le rallumait 3 s plus tard.
                        private _hit = ([vehicle player, "VIEW", [_o] call _hostOf] checkVisibility [_eye, _to]) > 0.02;
                        private _miss = [(_vis param [2, 0]) + 1, 0] select _hit;
                        _vis = [_now, _hit || {_miss < 2}, _miss];
                        _o setVariable ["COMSPEC_ATAK_TocVis", _vis];
                    };
                    _seen = _vis select 1;
                };
            };
            _rows pushBack [_o, _cfg, _ok, _dist, _seen, _rg + (10 max (_rg * 0.5))];
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
        private _drawn = 0;
        private _claimed = [];
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
            _x params ["_o", "_cfg", "_ok", "_dist", "_seen", ["_holdO", _hold]];
            private _tex = _o getVariable ["COMSPEC_ATAK_TocTex", ""];
            private _on = _ok && {_seen};
            // Une dalle, une seule image : deux inscriptions au registre qui visent la même dalle du même écran (relais
            // d'un mur créé deux fois, écran désigné par deux machines) se la disputaient, une image sur deux chacune.
            // La première inscrite garde la dalle, l'autre ne dessine rien et ne remet pas la texture d'origine.
            private _slot = [[_o] call _hostOf, _o getVariable ["COMSPEC_ATAK_TocSel", [_o, _cfg param [2, 0]] call _face]];
            private _dup = _ok && {_slot in _claimed};
            if (_ok && {!_dup}) then { _claimed pushBack _slot; };
            if (_dup) then {
                _o setVariable ["COMSPEC_ATAK_TocOn", false];
                if (_tex isNotEqualTo "") then { _o setVariable ["COMSPEC_ATAK_TocTex", ""]; _disps deleteAt _tex; _texObj deleteAt _tex; _local = _local - [_o]; };
                if !(_o getVariable ["COMSPEC_ATAK_TocDupLog", false]) then {
                    _o setVariable ["COMSPEC_ATAK_TocDupLog", true];
                    ["WARNING", "TOC", format ["Écran TOC %1 : dalle %2 déjà tenue par une autre inscription, celle-ci ne dessine pas", typeOf (_slot select 0), _slot select 1]] call comspec_atak_native_fnc_log;
                };
                continue
            };
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
                        ["paint", [_o, _sel, format ["%1ui(""COMSPEC_RscTocScreenTexture"",""%2"")", ([_o] call _fmtOf) select 1, _tex]]] call _fn;
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
                            // Nouvel essai de plus en plus espacé (4, 8, 16… 60 s) : une image qui ne se charge pas ne
                            // fait plus repasser la dalle par le noir toutes les 10 s, sans fin.
                            private _fails = (_o getVariable ["COMSPEC_ATAK_TocFails", 0]) + 1;
                            _o setVariable ["COMSPEC_ATAK_TocFails", _fails];
                            _o setVariable ["COMSPEC_ATAK_TocRetry", _now + ((4 * (2 ^ ((_fails - 1) min 4))) min 60)];
                            // Très large texture (bandeau 16:1) jamais chargée : nouvel essai en 2048.
                            _o setVariable ["COMSPEC_ATAK_TocTexLow", true];
                        };
                    } else {
                        // Horloge, satellite, bandeau, mosaïque, texte, colonne horloge : chaque seconde ; le reste toutes les 2 s.
                        // Budget des grands murs : huit images redessinées par tour au plus (les autres au tour suivant) ; la
                        // première image d'un écran passe toujours.
                        private _last = _o getVariable ["COMSPEC_ATAK_TocAt", -10];
                        private _due = _now - _last >= ([1.9, 0.9] select ((_cfg select 3) in ["CLOCK", "SAT", "STRIP", "MOSAIC", "TEXT", "BROADCAST", "RADIO", "COLA", "COLC", "COLE"]));
                        if (_due && {_drawn < 8 || {_last < 0}}) then {
                            _drawn = _drawn + 1;
                            // Matière jour / nuit suivie (rien ne change tant que le soleil ne passe pas l'horizon).
                            ["surface", [_o, _o getVariable ["COMSPEC_ATAK_TocSel", 0], true]] call _fn;
                            _o setVariable ["COMSPEC_ATAK_TocAt", _now];
                            _o setVariable ["COMSPEC_ATAK_TocDraws", (_o getVariable ["COMSPEC_ATAK_TocDraws", 0]) + 1];
                            ["draw", [_o, _disp]] call _fn;
                            // Luminosité de l'écran (voile au-dessus de l'image).
                            ["dim", [_o, _disp]] call comspec_atak_native_fnc_tocConfig;
                            displayUpdate _disp;
                        };
                    };
                };
                // Figé : proche mais pas regardé, ou juste au-delà de la distance d'affichage. Dernière image gardée.
                case (_ok && {_dist < _holdO} && {_tex isNotEqualTo ""}): {
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
        // Contrôles de la carte des affichages SPLIT, MAP, TOC, SQUAD (groupe « grp »), créés une fois par display et
        // réutilisés à chaque image. Tout le reste (en-tête, pied, FLASH, panneau, tableaux) : fn_tocBoard, au-dessus.
        // Pixels virtuels 1600 x 900 (_R), polices fixes du thème (fn_tocTheme), jamais selon la taille d'interface.
        private _d = _a;
        disableSerialization;
        // Rendu du tableau créé avant la carte : supprimé, il sera recréé par-dessus elle.
        private _brd = _d getVariable ["COMSPEC_ATAK_TocBoard", controlNull];
        if (!isNull _brd) then { ctrlDelete _brd; _d setVariable ["COMSPEC_ATAK_TocBoard", controlNull]; _d setVariable ["COMSPEC_ATAK_TocBoardUi", createHashMap]; };
        private _mk = {
            params ["_cls", ["_grp", controlNull], ["_st", ""], ["_tok", "text"]];
            private _c = if (isNull _grp) then { _d ctrlCreate [_cls, -1] } else { _d ctrlCreate [_cls, -1, _grp] };
            if (_st isNotEqualTo "") then { ["style", [_c, _st, _tok]] call comspec_atak_native_fnc_tocTheme; };
            _c ctrlSetPosition [0, 0, 0, 0];
            _c ctrlCommit 0;
            _c
        };
        private _grp = ["RscControlsGroupNoScrollbars"] call _mk;
        private _sea = ["RscText", _grp] call _mk;
        _sea ctrlSetBackgroundColor (["bg1"] call _tcol);
        private _tiles = []; for "_i" from 1 to 36 do { _tiles pushBack (["RscPicture", _grp] call _mk); };
        // Voile sombre sur le fond (photo ou carte d'ensemble assombrie) : symboles et plaques ressortent.
        private _dim = ["RscText", _grp] call _mk;
        _dim ctrlSetBackgroundColor (["bg0", 0.35] call _tcol);
        private _lines = []; for "_i" from 1 to 28 do { private _l = ["RscText", _grp] call _mk; _l ctrlSetBackgroundColor (["lineStrong", 0.45] call _tcol); _lines pushBack _l; };
        private _gLbls = []; for "_i" from 1 to 28 do { private _t = ["COMSPEC_RscTocLabelCenter", _grp, "data", "text2"] call _mk; _t ctrlSetBackgroundColor (["bg0", 0.78] call _tcol); _gLbls pushBack _t; };
        private _places = []; for "_i" from 1 to 16 do { private _t = ["COMSPEC_RscTocLabelCenter", _grp, "body", "text2"] call _mk; _t ctrlSetBackgroundColor (["bg0", 0.5] call _tcol); _places pushBack _t; };
        private _segs = []; for "_i" from 1 to 260 do { private _c = ["RscPicture", _grp] call _mk; _c ctrlSetText "#(argb,8,8,3)color(1,1,1,1)"; _segs pushBack _c; }; // i18n:brut (texture blanche teintée)
        private _segLbls = []; for "_i" from 1 to 24 do { private _t = ["COMSPEC_RscTocLabel", _grp, "bodyBold", "text"] call _mk; _t ctrlSetBackgroundColor (["bg0", 0.82] call _tcol); _segLbls pushBack _t; };
        private _mkIcons = []; private _mkLbls = [];
        for "_i" from 1 to 40 do { _mkIcons pushBack (["RscPicture", _grp] call _mk); private _t = ["COMSPEC_RscTocLabel", _grp, "bodyBold", "text"] call _mk; _t ctrlSetBackgroundColor (["bg0", 0.82] call _tcol); _mkLbls pushBack _t; };
        private _evIcons = []; for "_i" from 1 to 12 do { _evIcons pushBack (["RscPicture", _grp] call _mk); };
        // Nœuds : filet de rappel (plaque décalée), symbole, liseré d'état, plaque.
        private _leads = []; for "_i" from 1 to 100 do { private _c = ["RscPicture", _grp] call _mk; _c ctrlSetText "#(argb,8,8,3)color(1,1,1,1)"; _c ctrlSetTextColor (["text2", 0.7] call _tcol); _leads pushBack _c; }; // i18n:brut (texture blanche teintée)
        private _uIcons = []; private _uLbls = []; private _uBars = [];
        for "_i" from 1 to 100 do {
            _uIcons pushBack (["RscPicture", _grp] call _mk);
            private _t = ["COMSPEC_RscTocLabel", _grp, "bodyBold", "text"] call _mk;
            _t ctrlSetBackgroundColor (["bg0", 0.82] call _tcol);
            _uLbls pushBack _t;
            _uBars pushBack (["RscText", _grp] call _mk);
        };
        private _scBg = ["RscText", _grp] call _mk; _scBg ctrlSetBackgroundColor (["bg0", 0.82] call _tcol);
        private _scBar = ["RscText", _grp] call _mk; _scBar ctrlSetBackgroundColor (["text"] call _tcol);
        private _scT0 = ["RscText", _grp] call _mk; _scT0 ctrlSetBackgroundColor (["text"] call _tcol);
        private _scT1 = ["RscText", _grp] call _mk; _scT1 ctrlSetBackgroundColor (["text"] call _tcol);
        private _scTxt = ["COMSPEC_RscTocLabel", _grp, "data", "text2"] call _mk;
        private _noBg = ["RscText", _grp] call _mk; _noBg ctrlSetBackgroundColor (["bg0", 0.82] call _tcol);
        private _noArrow = ["COMSPEC_RscTocLabelCenter", _grp, "body", "text"] call _mk; _noArrow ctrlSetText "▲"; // i18n:brut (symbole)
        private _noTxt = ["COMSPEC_RscTocLabelCenter", _grp, "bodyBold", "text"] call _mk; _noTxt ctrlSetText (("N") call comspec_atak_native_fnc_tr);
        // Légende : fond, 4 pastilles et leurs libellés.
        private _lgBg = ["RscText", _grp] call _mk; _lgBg ctrlSetBackgroundColor (["bg0", 0.82] call _tcol);
        private _lg = []; for "_i" from 1 to 4 do { _lg pushBack [["RscText", _grp] call _mk, ["COMSPEC_RscTocLabel", _grp, "body", "text2"] call _mk]; };
        private _empty = ["COMSPEC_RscTocText", _grp] call _mk;
        _empty ctrlSetBackgroundColor (["bg0", 0.82] call _tcol);
        createHashMapFromArray [["grp", _grp], ["sea", _sea], ["tiles", _tiles], ["dim", _dim],
            ["lines", _lines], ["gLbls", _gLbls], ["places", _places], ["segs", _segs], ["segLbls", _segLbls], ["mkIcons", _mkIcons], ["mkLbls", _mkLbls],
            ["evIcons", _evIcons], ["leads", _leads], ["uIcons", _uIcons], ["uLbls", _uLbls], ["uBars", _uBars], ["scale", [_scBg, _scBar, _scT0, _scT1, _scTxt]], ["north", [_noBg, _noArrow, _noTxt]],
            ["legend", [_lgBg, _lg]], ["empty", _empty]]
    };
    case "draw": {
        _a params ["_o", "_d"];
        disableSerialization;
        private _cfg = [_o] call _cfgOf;
        _cfg params [["_name", "TOC"], ["_cside", ""], "", ["_view", "SPLIT"], ["_half", 0], ["_center", []]];
        // Aucun flux du serveur pour le camp de l'écran : carte fixe « AUCUN SIGNAL » (fn_tocBroadcast, bascule avec
        // retenue) au lieu d'un contenu à moitié vide qui changeait d'état d'une image à l'autre.
        if !(["signal", [_o, _d, _view]] call comspec_atak_native_fnc_tocBroadcast) exitWith { ["nosig", [_o, _d]] call comspec_atak_native_fnc_tocBroadcast };
        // Diffusion : image d'un autre écran recopiée, mire, ou AUCUN SIGNAL (fn_tocBroadcast « draw »).
        if (_view isEqualTo "BROADCAST") exitWith { ["draw", [_o, _d]] call comspec_atak_native_fnc_tocBroadcast };
        ["hide", _d] call comspec_atak_native_fnc_tocBroadcast;
        // Vue caméra sans rendu en direct sur ce poste (trop loin, pas regardée, limite atteinte) : carte et flux.
        if (_view isEqualTo "CAM") then { _view = "SPLIT"; };
        // Affichage TEXTE (fn_tocText) : panneau à LED seul, ni carte ni tableau ; son calque est masqué sous tout autre affichage.
        private _txg = (_d getVariable ["COMSPEC_ATAK_TxtUi", createHashMap]) getOrDefault ["g", controlNull];
        if (!isNull _txg && {_view isNotEqualTo "TEXT"}) then { _txg ctrlShow false; _d setVariable ["COMSPEC_ATAK_TxtUiAnim", []]; };
        if (_view isEqualTo "TEXT") exitWith {
            { if (!isNull _x) then { _x ctrlShow false; }; } forEach [(_d getVariable ["COMSPEC_ATAK_TocUi", createHashMap]) getOrDefault ["grp", controlNull], _d getVariable ["COMSPEC_ATAK_TocBoard", controlNull]];
            ["draw", [_o, _d, [_o] call _fmtOf]] call comspec_atak_native_fnc_tocText
        };
        // Satellite, horloge, tableaux du poste : dessinés par fn_tocBoard (la carte n'est pas dessinée).
        if (_view in _boards) exitWith {
            private _mg = (_d getVariable ["COMSPEC_ATAK_TocUi", createHashMap]) getOrDefault ["grp", controlNull];
            if (!isNull _mg) then { _mg ctrlShow false; };
            private _perfT = diag_tickTime;
            private _perfR = ["draw", [_o, _d, _view]] call comspec_atak_native_fnc_tocBoard;
            ["TOC tableau (dessin)", _perfT] call comspec_atak_native_fnc_perfAcc;
            _perfR
        };
        private _squad = ["", _cfg param [9, ""]] select (_view isEqualTo "SQUAD");
        private _side = [_cside, str side group player] select (_cside isEqualTo "");
        // Flux du camp, état du nœud de ce joueur corrigé sur ce poste (fn_tocStatus « feed »).
        private _feed = ["feed", _side] call comspec_atak_native_fnc_tocStatus;
        _feed params [["_ft", -1], ["_nodes", []], ["_events", []], ["_stats", []]];
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
        private _place2 = { params ["_c", "_rect"]; _c ctrlSetPosition (_rect call _R); _c ctrlCommit 0; _c ctrlShow true; };
        // ---- Mise en page : carte des affichages à carte (régions du thème fn_tocTheme, descendues sous un FLASH) ----
        // Maquettes : tools/toc_mockups.py (mur_split, mur_carte, mur_escouade, mur_pip), mêmes régions.
        private _layouts = createHashMapFromArray [["SPLIT", "SPLIT"], ["TOC", "SPLIT"], ["MAP", "MAP"], ["SQUAD", "SQUAD"]];
        private _isFl = (count (["flash", _side] call comspec_atak_native_fnc_tocBoard)) > 0;
        private _mapR = ["region", [_layouts getOrDefault [_view, "SPLIT"], "map", _isFl]] call comspec_atak_native_fnc_tocTheme;
        private _isMap = true;
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
                private _rv = [_z] call _rng;
                while { _z > _zMin && {(((_rv select 2) - (_rv select 1) + 1) * ((_rv select 4) - (_rv select 3) + 1)) > 36} } do { _z = _z - 1; _rv = [_z] call _rng; };
                _rv params ["_T", "_x0", "_x1", "_y0", "_y1"];
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
                            _c ctrlSetTextColor (["mapTint"] call _tcol);
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
                    _c ctrlSetTextColor (["mapTintPic"] call _tcol);
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
            // Huit places autour du point (droite, gauche, dessus, dessous, diagonales), puis deux plus loin à droite et à
            // gauche (un filet de rappel relie alors la plaque au symbole) ; mode 1 : centrée sur le point (noms de lieux).
            // Jamais hors du cadre de la carte (plaques coupées au bord). Sans place libre : rien. [x, y, n° de la place].
            private _place = {
                params ["_px", "_py", "_w", "_h", "_rv", ["_mode", 0]];
                private _q = _rv * 0.7;
                private _cands = [
                    [[_px + _rv, _py - _h / 2], [_px - _rv - _w, _py - _h / 2], [_px - _w / 2, _py - _rv - _h], [_px - _w / 2, _py + _rv],
                     [_px + _q, _py - _q - _h], [_px + _q, _py + _q], [_px - _q - _w, _py - _q - _h], [_px - _q - _w, _py + _q],
                     [_px + _rv + 36, _py - _h / 2], [_px - _rv - 36 - _w, _py - _h / 2]],
                    [[_px - _w / 2, _py - _h / 2], [_px - _w / 2, _py - _h - 8], [_px - _w / 2, _py + 8]]
                ] select _mode;
                private _res = [];
                {
                    _x params ["_x0", "_y0"];
                    if (_x0 >= 2 && {_y0 >= 2} && {(_x0 + _w) <= (_mwPx - 2)} && {(_y0 + _h) <= (_mhPx - 2)} && {[_x0, _y0, _w, _h] call _free}) exitWith {
                        _res = [_x0, _y0, _forEachIndex];
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
            private _sTxt = [format ["%1 m", _nice], format ["%1 km", _nice / 1000]] select (_nice >= 1000);
            private _sTw = ((count _sTxt) * 0.8 * 28) + 32;
            (_ui get "scale") params ["_scBg", "_scBar", "_scT0", "_scT1", "_scTxt"];
            private _sy = _mhPx - 58;
            [_scBg, [16, _sy, 28 + _barPx + 14 + _sTw, 46]] call _place2;
            [_scBar, [30, _sy + 21, _barPx, 4]] call _place2;
            [_scT0, [30, _sy + 12, 3, 22]] call _place2;
            [_scT1, [30 + _barPx - 3, _sy + 12, 3, 22]] call _place2;
            [_scTxt, _sTxt, _sTw] call _lbl;
            [_scTxt, [30 + _barPx + 14, _sy + 4, _sTw, 38]] call _place2;
            _taken pushBack [16, _sy, 28 + _barPx + 14 + _sTw, 46];
            (_ui get "north") params ["_noBg", "_noArrow", "_noTxt"];
            [_noBg, [_mwPx - 72, 12, 56, 70]] call _place2;
            [_noArrow, [_mwPx - 72, 14, 56, 32]] call _place2;
            [_noTxt, [_mwPx - 72, 44, 56, 36]] call _place2;
            _taken pushBack [_mwPx - 72, 12, 56, 70];
            // Légende (bas droite) : pastilles de couleur et libellés ; masquée si la carte est trop étroite pour elle.
            (_ui get "legend") params ["_lgBg", "_lg"];
            private _chips = [[["friend"] call _tcol, "En direct"], [["friend", 0.55] call _tcol, "Ancien"], [["lost"] call _tcol, "Perdu"], [["red"] call _tcol, "Alerte"]];
            private _lws = _chips apply { ((count (((_x select 1) call comspec_atak_native_fnc_tr))) * 0.46 * 28) + 44 };
            private _lgW = 16; { _lgW = _lgW + _x; } forEach _lws;
            private _lgX = _mwPx - 16 - _lgW;
            private _lgOn = _lgX > (16 + 28 + _barPx + 14 + _sTw + 24);
            _lgBg ctrlShow _lgOn;
            {
                _x params ["_sw", "_sl"];
                _sw ctrlShow _lgOn; _sl ctrlShow _lgOn;
                if (_lgOn) then {
                    private _cx0 = _lgX + 12; for "_k" from 0 to (_forEachIndex - 1) do { _cx0 = _cx0 + (_lws select _k); };
                    (_chips select _forEachIndex) params ["_ccol", "_clbl"];
                    _sw ctrlSetBackgroundColor _ccol;
                    [_sw, [_cx0, _sy + 16, 14, 14]] call _place2;
                    [_sl, _clbl, (_lws select _forEachIndex) - 18] call _lbl;
                    [_sl, [_cx0 + 18, _sy, (_lws select _forEachIndex) - 18, 46]] call _place2;
                };
            } forEach _lg;
            if (_lgOn) then { [_lgBg, [_lgX, _sy, _lgW, 46]] call _place2; _taken pushBack [_lgX, _sy, _lgW, 46]; };
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
                        [_t, [_px - 44, 8, 88, 34]] call _place2;
                        _taken pushBack [_px - 44, 8, 88, 34];
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
                        [_t, [8, _py - 17, 88, 34]] call _place2;
                        _taken pushBack [8, _py - 17, 88, 34];
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
                params ["_m", ["_def", ["text"] call _tcol]];
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
                            _c ctrlSetTextColor ([_x, ["bg0"] call _tcol] call _mkCol);
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
                    _c ctrlSetTextColor (["red"] call _tcol);
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
            private _uBars = _ui get "uBars";
            private _leads = _ui get "leads";
            private _ui0 = 0;
            private _li3 = 0;
            {
                _x params ["", "_lead", "_sum", "_mem"];
                _lead params ["", "_cs", "_p", "", "_fr", "_kind"];
                ([_p select 0, _p select 1] call _toG) params ["_gx", "_gy"];
                private _tex = format ["\A3\ui_f\data\map\markers\nato\b_%1.paa", (createHashMapFromArray [["veh", "motor_inf"], ["air", "air"], ["boat", "naval"], ["armor", "armor"]]) getOrDefault [_kind, "inf"]];
                // Couleur OTAN amie du thème : en direct pleine, ancien pâli, perdu et hors ligne en gris.
                private _col = switch (_fr) do {
                    case 0: { ["friend"] call _tcol };
                    case 1: { ["friend", 0.6] call _tcol };
                    case 2: { ["lost"] call _tcol };
                    default { ["lost", 0.7] call _tcol };
                };
                // PIP : le groupe choisi par l'opérateur ressort en jaune ; écran d'escouade : son groupe.
                private _pickG = _op param [4, ""];
                private _picked = ((count _op) >= 5 && {(_op param [3, ""]) isEqualTo "GROUPES"} && {_pickG isNotEqualTo ""} && {(_mem findIf { (_x param [7, ""]) isEqualTo _pickG }) >= 0})
                    || {_squad isNotEqualTo "" && {(_mem findIf { (_x param [7, ""]) isEqualTo _squad }) >= 0}};
                private _c = _uIcons select _ui0;
                _c ctrlSetText ((_tex) call comspec_atak_native_fnc_tr);
                _c ctrlSetTextColor ([_col, ["amber"] call _tcol] select _picked);
                _c ctrlSetPosition [_gx - 20 / _texW, _gy - 20 / _texH, 40 / _texW, 40 / _texH]; _c ctrlCommit 0; _c ctrlShow true;
                private _lt = ([_cs, 18] call _cut) + (["", format [" +%1", _sum - 1]] select (_sum > 1)) + ([["", " · ancien", " · perdu", " · hors ligne"] select (_fr min 3), ""] select _far);
                private _lw = [_lt, 28] call _plateW;
                private _t = _uLbls select _ui0;
                private _bar = _uBars select _ui0;
                private _at = [_gx * _texW, _gy * _texH, _lw, 36, 26] call _place;
                if ((count _at) > 0) then {
                    // Plaque : texte et liseré à l'état du nœud (en direct, ancien en ambre, perdu en gris) ; choisi en ambre.
                    private _stc = [[["text", "amber", "lost", "lost"] select ((_fr min 3) max 0)] call _tcol, ["amber"] call _tcol] select _picked;
                    [_t, _lt, _lw] call _lbl;
                    _t ctrlSetTextColor _stc;
                    [_t, [(_at select 0) + 4, _at select 1, _lw - 4, 36]] call _place2;
                    _bar ctrlSetBackgroundColor ([[["friend", "amber", "lost", "lost"] select ((_fr min 3) max 0)] call _tcol, ["amber"] call _tcol] select _picked);
                    [_bar, [_at select 0, _at select 1, 4, 36]] call _place2;
                    // Plaque décalée (places 9 et 10) : filet de rappel du symbole au bord de la plaque.
                    if ((_at select 2) >= 8 && {_li3 < (count _leads)}) then {
                        private _lx0 = _gx * _texW + ([-20, 20] select ((_at select 0) > _gx * _texW));
                        private _lx1 = [(_at select 0) + _lw, _at select 0] select ((_at select 0) > _gx * _texW);
                        private _ly = _gy * _texH;
                        private _lc = _leads select _li3;
                        _lc ctrlSetPosition [(_lx0 min _lx1) / _texW, (_ly - 1) / _texH, (abs (_lx1 - _lx0)) / _texW, 2 / _texH];
                        _lc ctrlCommit 0;
                        _lc ctrlShow true;
                        _li3 = _li3 + 1;
                    };
                } else { _t ctrlShow false; _bar ctrlShow false; };
                _ui0 = _ui0 + 1;
            } forEach _clusters;
            // PIP : objectif de l'ordre en cours de rédaction sur le PC.
            if ((count _op) >= 9) then {
                private _opos = _op param [8, []];
                if ((count _opos) >= 2 && {[_opos select 0, _opos select 1] call _inView} && {_ui0 < 100}) then {
                    ([_opos select 0, _opos select 1] call _toG) params ["_gx", "_gy"];
                    private _c = _uIcons select _ui0;
                    _c ctrlSetText (("\A3\ui_f\data\map\markers\military\objective_CA.paa") call comspec_atak_native_fnc_tr);
                    _c ctrlSetTextColor (["amber"] call _tcol);
                    _c ctrlSetPosition [_gx - 22 / _texW, _gy - 22 / _texH, 44 / _texW, 44 / _texH]; _c ctrlCommit 0; _c ctrlShow true;
                    private _t = _uLbls select _ui0;
                    (_uBars select _ui0) ctrlShow false;
                    private _lw = ["OBJECTIF", 28] call _plateW;
                    private _oAt = [_gx * _texW, _gy * _texH, _lw, 36, 26] call _place;
                    if ((count _oAt) > 0) then {
                        [_t, "OBJECTIF", _lw] call _lbl;
                        _t ctrlSetTextColor (["amber"] call _tcol);
                        [_t, [_oAt select 0, _oAt select 1, _lw, 36]] call _place2;
                    } else { _t ctrlShow false; };
                    _ui0 = _ui0 + 1;
                };
            };
            for "_i" from _ui0 to 99 do { (_uIcons select _i) ctrlShow false; (_uLbls select _i) ctrlShow false; (_uBars select _i) ctrlShow false; };
            for "_i" from _li3 to ((count _leads) - 1) do { (_leads select _i) ctrlShow false; };
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
                    [_pts param [0, [0, 0]], (_sk param [3, markerText _m]), ["text"] call _tcol] call _segLbl;
                };
            } forEach allMapMarkers;
            private _pcat = [] call comspec_atak_native_fnc_planCatalog;
            {
                _x params ["", ["_kind", ""], ["_pts", []], ["_txt", ""], ["_ck", "WHITE"], "", "", ["_w", 2]];
                private _col = +((_pcat get "colors") getOrDefault [_ck, ["text"] call _tcol]);
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
                        _t ctrlSetTextColor ([["text2"] call _tcol, ["text"] call _tcol] select (_ty in ["NameCityCapital", "NameCity"]));
                        [_t, [_at select 0, _at select 1, _w, 36]] call _place2;
                        _pi = _pi + 1;
                    };
                };
            } forEach (_locs param [1, []]);
            for "_i" from _pi to ((count _places) - 1) do { (_places select _i) ctrlShow false; };
            private _em = _ui get "empty";
            _em ctrlShow ((count _nodes) isEqualTo 0);
            // État vide : une phrase et ce qu'il faut faire, sur une plaque au centre de la carte.
            if ((count _nodes) isEqualTo 0 && {(_em getVariable ["COMSPEC_ATAK_Txt", ""]) isEqualTo ""}) then {
                _em setVariable ["COMSPEC_ATAK_Txt", "vide"];
                _em ctrlSetStructuredText parseText (["ink", ("<t align='center' size='1.15' font='RobotoCondensedBold' color='@text'>Aucun nœud ATAK de ce camp sur le réseau</t><br/><t align='center' size='1' color='@text2'>Les téléphones ATAK du camp apparaissent ici dès qu'ils envoient leur position</t>") call comspec_atak_native_fnc_tr] call comspec_atak_native_fnc_tocTheme);
            };
            _em ctrlSetPosition ([(_mwPx / 2) - 420, (_mhPx / 2) - 44, 840, 88] call _R); _em ctrlCommit 0;
        };

        // ---- En-tête, pied, FLASH, panneau : fn_tocBoard (au-dessus de la carte) ----
        private _perfT = diag_tickTime;
        ["draw", [_o, _d, _view, createHashMapFromArray [["op", _op], ["squad", _squad], ["sqNodes", _sqNodes]]]] call comspec_atak_native_fnc_tocBoard;
        ["TOC en-tête et panneau (dessin)", _perfT] call comspec_atak_native_fnc_perfAcc;
        true
    };
    case "squadText": {
        // Membres d'un groupe (une ligne chacun, coupée à _chars caractères), puis son dernier compte rendu.
        _a params ["_squad", "_sqNodes", "_events", ["_max", 12], ["_withCr", true], ["_chars", 43]];
        private _esc = { params ["_t"]; if !(_t isEqualType "") then { _t = str _t; }; { _t = [_t, _x select 0, _x select 1] call CBA_fnc_replace; } forEach [["&", "&amp;"], ["<", "&lt;"], [">", "&gt;"]]; _t };
        if (_squad isEqualTo "") exitWith { ["ink", "<t size='1' color='@muted'>Aucun groupe choisi : choisissez l'escouade au PC du poste, dans l'app Poste TOC ou au menu ACE de l'écran</t>"] call comspec_atak_native_fnc_tocTheme };
        private _s = "";
        if ((count _sqNodes) isEqualTo 0) then {
            _s = "<t size='1' color='@amber'>Aucun ATAK de ce groupe sur le réseau : le poste ne connaît ni ses membres ni sa position</t><br/>";
        };
        {
            _x params ["", "_cs", "_p", "", "_fr", "_kind", "", "", ["_n", 1]];
            private _col = ["@accent", "@amber", "@lost", "@muted"] select ((_fr min 3) max 0);
            _s = _s + format ["<t size='1'><t color='%1'>●</t> <t font='RobotoCondensedBold'>%2</t>%3 <t color='@muted'>· %4 · %5</t></t><br/>", _col, [[_cs, 14] call _cut] call _esc,
                ["", format [" +%1", _n - 1]] select (_n > 1), [[_p select 0, _p select 1, 0], 6] call comspec_atak_native_fnc_gridRef,
                ["en direct", "ancien", "perdu", "hors ligne"] select ((_fr min 3) max 0)];
        } forEach (_sqNodes select [0, _max]);
        if (!_withCr) exitWith { ["ink", _s] call comspec_atak_native_fnc_tocTheme };
        // Dernier compte rendu d'un membre du groupe (auteur reconnu à son indicatif), deux lignes au plus.
        private _names = _sqNodes apply { toLower (_x select 1) };
        private _ev = _events select { (toLower (_x param [3, ""])) in _names };
        if ((count _ev) > 0) then {
            (_ev select ((count _ev) - 1)) params [["_time", ""], ["_kind", ""], ["_prio", ""], ["_who", ""], ["_grid", ""], ["_text", ""]];
            _s = _s + format ["<br/><t size='1.1' font='RobotoCondensedBold'>%1 %2</t> <t size='1' color='@muted'>%3 · %4%5</t><br/><t size='1'>%6</t>",
                [[_kind, 14] call _cut] call _esc, [[_prio, 10] call _cut] call _esc, _time, [[_who, 16] call _cut] call _esc, ["", " · grille " + _grid] select (_grid isNotEqualTo ""), [[_text, 2 * _chars - 4] call _cut] call _esc];
        } else {
            _s = _s + "<br/><t size='1' color='@muted'>Aucun compte rendu de ce groupe pour l'instant</t>";
        };
        ["ink", _s] call comspec_atak_native_fnc_tocTheme
    };
    case "title": {
        // Titre de chaque affichage : [mot d'en-tête de l'écran, libellé des menus et notifications], en français
        // (traduit à l'affichage). "" : la table entière (contrôle « audit », test tests/Unit/AtakTocScreenDisplaysAssetTest.php).
        private _t = createHashMapFromArray [
            ["SPLIT", ["TOC", "carte et flux"]], ["MAP", ["TOC", "carte seule"]], ["FEED", ["FLUX", "flux seul"]],
            ["NET", ["RÉSEAU", "réseau"]], ["ORBAT", ["ORBAT", "ORBAT du site"]], ["TOC", ["TOC", "PIP du poste"]],
            ["CAM", ["CAMÉRA", "caméra en direct"]], ["SAT", ["SATELLITE", "vue satellite"]], ["CLOCK", ["HORLOGE", "horloge et chronos"]],
            ["SQUAD", ["ESCOUADE", "écran d'escouade"]], ["LOG", ["MAIN COURANTE", "main courante"]], ["EXCHECK", ["EXÉCUTION", "liste d'exécution"]],
            ["KILLS", ["PERTES ET RÉSULTATS", "pertes et résultats"]], ["REQ", ["DEMANDES", "demandes des éléments"]], ["RADIO", ["RADIO", "activité radio"]],
            ["STRIP", ["BANDEAU", "bandeau horloge"]], ["PLATE", ["PLAQUE", "plaque de nom"]], ["MOSAIC", ["CAMÉRAS", "mosaïque de caméras"]],
            ["TEXT", ["TEXTE", "texte à LED"]], ["BROADCAST", ["DIFFUSION", "diffusion et mire (BROADCAST)"]], ["COLA", ["HORLOGE", "colonne horloge et chronos"]], ["COLB", ["FLUX", "colonne réseau et flux"]],
            ["COLC", ["CAMÉRAS", "colonne caméras"]], ["COLD", ["MAIN COURANTE", "colonne main courante"]], ["COLE", ["EXÉCUTION", "colonne exécution"]], ["COLF", ["DEMANDES", "colonne demandes et pertes"]]
        ];
        if (_a isEqualTo "") exitWith { _t };
        _t getOrDefault [_a, [_a, toLower _a]]
    };
    case "views": { +_views };
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
                private _rv = ["draw", [_o, uiNamespace getVariable ["COMSPEC_ATAK_TocAuditD", displayNull]]] call comspec_atak_native_fnc_tocScreen;
                missionNamespace setVariable ["COMSPEC_ATAK_TocAuditDone", !isNil "_rv"];
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
                case "cola": { ["ensure", [_o, "COLA"]] call comspec_atak_native_fnc_tocScreen; };
                case "colb": { ["ensure", [_o, "COLB"]] call comspec_atak_native_fnc_tocScreen; };
                // « Afficher un texte… » : fenêtre de saisie (fn_tocText), à l'image suivante (le menu ACE se referme d'abord).
                case "text": { [{ ["edit", _this] call comspec_atak_native_fnc_tocText; }, _o] call comspec_atak_native_fnc_nextFrame; };
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
                case "big": { ["openPc", _o] call comspec_atak_native_fnc_tocConfig; };
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
                // Clés du menu revu (luminosité, salle, diagnostic, disposition, diffusion, tableau, dalles) : fn_tocMenu « do ».
                default { ["do", [_o, _k]] call comspec_atak_native_fnc_tocMenu; };
            };
        };
        missionNamespace setVariable ["COMSPEC_ATAK_TocDo", _do];
        // Sans ACE : seules les actions de secours (« actions ») s'en servent ; avant, COMSPEC_ATAK_TocDo restait vide
        // et aucune action d'écran (dont Ouvrir le PC du poste) ne faisait rien.
        if (isNil "ace_interact_menu_fnc_createAction") exitWith { false };
        // Un seul arbre ACE par écran (retour de jeu 2.9.24 : tout en double, listes superposées), revu en 4.8.0 (« sois
        // plus clair dans les actions ACE ») : l'arbre est décrit dans fn_tocMenu (« tree »), le même pour ACE, pour la
        // molette sans ACE (« actions ») et pour le tableau des écrans.
        //   Écran COMSPEC > Ouvrir le PC du poste · Afficher… · Disposition… · Source / caméra… · Écran… · Salle : nom
        //                 · Gérer les écrans (tableau)…
        // Racine posée une fois : sur la classe exacte des écrans du jeu (jamais par héritage : une variante « noire » ou
        // « sable » héritant d'une classe listée recevait les deux racines), sinon sur l'objet (« aceObj »), une fois.
        private _kids = { ["ace", _target] call comspec_atak_native_fnc_tocMenu };
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
        // Sans ACE : actions de la molette, une fois par objet. Même arbre que le menu ACE (fn_tocMenu), un niveau à
        // la fois : « Écran TOC · Afficher · Carte et flux ».
        if (_o getVariable ["COMSPEC_ATAK_TocWheel", false]) exitWith { false };
        _o setVariable ["COMSPEC_ATAK_TocWheel", true];
        ["wheel", [_o, []]] call comspec_atak_native_fnc_tocMenu
    };
    default { false };
};
