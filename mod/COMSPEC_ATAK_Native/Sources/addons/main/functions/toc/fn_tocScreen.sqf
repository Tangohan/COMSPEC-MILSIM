/*
    Écrans TOC (demande de TTRD, 26/10) : un grand écran du jeu (TV, écran sur trépied, écran de projection, poste
    informatique) qui affiche en direct le réseau ATAK d'un camp, tel que le serveur de la mission le voit
    (fn_tocFeed) : carte avec les nœuds ATAK (joueurs et IA équipées, à leur dernier point PLI, en direct / ancien /
    perdu / hors ligne), marqueurs de la mission, derniers comptes rendus, alertes et messages du poste, état du réseau.
    L'écran est une texture UI (#(rgb,2048,1024,1)ui("COMSPEC_RscTocScreenTexture", nom unique)) posée par chaque joueur
    sur l'objet (setObjectTexture local), rafraîchie toutes les 2 s quand l'écran est proche et visible. Une carte Arma
    ne se dessine pas dans une texture UI (fn_jpcMap) : le fond est la photo de la carte (mod @COMSPEC_ATAK_Photo,
    fn_photoMap) ou, sans lui, la carte d'ensemble du terrain (pictureMap), avec le quadrillage kilométrique.
    Carte en grand : l'écran passe en CARTE SEULE, et l'action « Ouvrir le PC du poste » ouvre l'écran du PC portable
    du poste (fn_tocLaptop), indépendant du téléphone.
    PIP du TOC (demande de TTRD, 26/10) : affichage « PIP DU POSTE », l'écran recopie ce que l'opérateur a devant lui
    sur le PC du poste (fn_tocLaptop, COMSPEC_ATAK_TocView) : même cadrage de carte, onglet, groupe ou compte rendu
    choisi, objectif de l'ordre en cours. Sans opérateur, l'écran revient de lui-même à carte et flux.
    Persistant : registre tenu par le serveur (joueurs qui rejoignent compris), écrans et affichage gardés d'une partie
    à l'autre sur le même serveur (fn_tocPersist, réglage « Garder les écrans TOC »).

    Variables publiques de l'objet :
      COMSPEC_ATAK_TocScr [nom, camp ("" = celui de chaque joueur), index de texture, affichage ("SPLIT" | "MAP" | "FEED" | "NET" | "ORBAT" | "TOC"),
                           demi-largeur de la carte en m (0 = cadrage automatique sur le réseau), centre ([] = auto),
                           poste TOC (l'écran sert aussi de PC de TOC), à recréer à la partie suivante (posé en cours de partie)]
      COMSPEC_ATAK_TocOwned   objet créé par le module ou la sauvegarde (supprimé avec l'écran)
    Registre : missionNamespace COMSPEC_ATAK_TocScreens (objets), tenu par le serveur.

    Modes :
      ["add", [objet, nom, camp, index, affichage, poste TOC, à recréer]]   (serveur ; ailleurs, demande au serveur)
      ["remove", objet]                    retire l'écran (et supprime l'objet s'il a été créé par le module)
      ["spawn", [position ATL, direction, classe, nom, camp, affichage, poste TOC]]   crée l'écran (serveur)
      ["view", [objet, clé, valeur]]       "mode" affichage | "zoom" demi-largeur | "center" position | "auto"
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
    Rendu (2 s, écran vu) : symboles regroupés et plaques sans chevauchement, colonnes par groupes en pages automatiques,
    textes réécrits seulement s'ils changent. ORBAT : celui du site (fn_bftOrbat), lu par chaque joueur avec son compte.
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
// [nom, camp, index, affichage, demi-largeur, centre, poste TOC, posé en jeu]
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

switch (_mode) do {
    case "initServer": {
        if (!isServer) exitWith {};
        ["comspec_atak_native_tocScreen", { _this call comspec_atak_native_fnc_tocScreen; }] call CBA_fnc_addEventHandler;
        ["init"] call comspec_atak_native_fnc_tocFeed;
        // Écrans gardés de la partie précédente : remis une fois les modules Eden passés.
        [{ ["restore"] call comspec_atak_native_fnc_tocPersist; }, [], 3] call comspec_atak_native_fnc_waitAndExec;
        true
    };
    case "classes": {
        // Écrans COMSPEC en tête (modèles du jeu réglés pour l'image, fiables jour et nuit), puis les écrans du jeu.
        ([
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
    case "add": {
        _a params [["_o", objNull], ["_name", ""], ["_side", ""], ["_sel", 0], ["_view", "SPLIT"], ["_toc", true], ["_spawned", false]];
        if (isNull _o) exitWith { false };
        if (!isServer) exitWith { ["comspec_atak_native_tocScreen", ["add", _a]] call CBA_fnc_serverEvent; true };
        if (_name isEqualTo "") then { _name = "TOC"; };
        private _old = [_o] call _cfgOf;
        if !(_view in ["SPLIT", "MAP", "FEED", "NET", "ORBAT", "TOC", "CAM"]) then { _view = "SPLIT"; };
        // Index de dalle : celui demandé, sinon la sélection nommée écran (évite un TOC noir sur le châssis).
        private _faceSel = [_o, (round _sel) max 0] call _face;
        _o setVariable ["COMSPEC_ATAK_TocScr", [_name select [0, 32], toUpper _side, _faceSel, _view, _old param [4, 0], _old param [5, []], _toc, _spawned || {_old param [7, false]}], true];
        // Poste TOC : l'écran donne aussi l'ATAK du poste et l'app Poste TOC à qui s'en approche (fn_tocPost).
        _o setVariable ["COMSPEC_ATAK_Toc", _toc, true];
        _o setVariable ["COMSPEC_ATAK_TocSide", toUpper _side, true];
        private _list = (missionNamespace getVariable ["COMSPEC_ATAK_TocScreens", []]) select { !isNull _x };
        _list pushBackUnique _o;
        missionNamespace setVariable ["COMSPEC_ATAK_TocScreens", _list, true];
        ["save"] call comspec_atak_native_fnc_tocPersist;
        true
    };
    case "remove": {
        if (isNull _a) exitWith { false };
        if (!isServer) exitWith { ["comspec_atak_native_tocScreen", ["remove", _a]] call CBA_fnc_serverEvent; true };
        private _owned = _a getVariable ["COMSPEC_ATAK_TocOwned", false];
        _a setVariable ["COMSPEC_ATAK_TocScr", nil, true];
        _a setVariable ["COMSPEC_ATAK_Toc", nil, true];
        missionNamespace setVariable ["COMSPEC_ATAK_TocScreens", (missionNamespace getVariable ["COMSPEC_ATAK_TocScreens", []]) select { !isNull _x && {_x isNotEqualTo _a} }, true];
        // Écran créé par le module (ou recréé par la sauvegarde) : l'objet part avec lui.
        if (_owned) then { deleteVehicle _a; };
        ["save"] call comspec_atak_native_fnc_tocPersist;
        true
    };
    case "spawn": {
        _a params [["_pos", [0, 0, 0]], ["_dir", 0], ["_cls", ""], ["_name", ""], ["_side", ""], ["_view", "SPLIT"], ["_toc", true]];
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
        ["add", [_o, _name, _side, 0, _view, _toc, true]] call _fn;
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
            case "mode": { if (_v in ["SPLIT", "MAP", "FEED", "NET", "ORBAT", "TOC", "CAM"]) then { _c set [3, _v]; }; };
            // Caméra en direct (fn_tocCam) : seule la source choisie circule (netId), chaque joueur fait son rendu.
            case "cam": { if (_v isEqualType "" && {(count _v) < 40}) then { _o setVariable ["COMSPEC_ATAK_TocCam", _v, true]; }; };
            case "zoom": { _c set [4, ((_v max 100) min (worldSize max 1000))]; };
            case "center": { if (_v isEqualType [] && {(count _v) >= 2}) then { _c set [5, [_v select 0, _v select 1]]; }; };
            case "auto": { _c set [4, 0]; _c set [5, []]; };
        };
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
        // Tableau-toile : la surface de projection posée devant lui reçoit la matière.
        if ((getNumber (configOf _o >> "comspec_projBoard")) > 0) then { _o = _o getVariable ["COMSPEC_ATAK_ProjPlane", objNull]; _sel = 0; };
        if (isNull _o) exitWith { false };
        private _prep = _o getVariable ["COMSPEC_ATAK_SurfPrep", []];
        if (!_on) exitWith {
            if (_prep isEqualTo []) exitWith { false };
            _prep params ["_ps", "_mat", "_sibs"];
            _o setObjectMaterial [_ps, _mat];
            { _o setObjectTexture _x; } forEach _sibs;
            // Dalles recopiées (double écran) : matière de la config remise.
            private _mats = getArray (configOf _o >> "hiddenSelectionsMaterials");
            { _o setObjectMaterial [_x, _mats param [_x, ""]]; } forEach (getArray (configOf _o >> "comspec_screenMirror"));
            _o setVariable ["COMSPEC_ATAK_SurfPrep", nil];
            true
        };
        if (_prep isNotEqualTo [] && {(_prep select 0) isNotEqualTo _sel}) then { ["surface", [_o, 0, false]] call _fn; _prep = []; };
        // Écran ou toile COMSPEC : une seule dalle, rien à masquer ; matière d'origine et matières jour / nuit de la config.
        private _own = getArray (configOf _o >> "comspec_screenMat");
        if (_prep isEqualTo [] && {(count _own) >= 2}) then {
            _prep = [_sel, (getArray (configOf _o >> "hiddenSelectionsMaterials")) param [_sel, ""], [], ""];
            _o setVariable ["COMSPEC_ATAK_SurfPrep", _prep];
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
            _o setVariable ["COMSPEC_ATAK_SurfPrep", _prep];
            ["INFO", "TOC", format ["Écran %1 : image sur la dalle %2 (%3), matière d'origine %4, %5 dalle(s) masquée(s)",
                typeOf _o, _sel, _names param [_sel, "?"], [_prep select 1, "aucune"] select ((_prep select 1) isEqualTo ""), count _sibs]] call comspec_atak_native_fnc_log;
        };
        private _mat = if ((count _own) >= 2) then { _own select (parseNumber (sunOrMoon <= 0.5)) } else {
            "\z\comspec_atak_native\addons\main\data\" + (["jpc_screen_night.rvmat", "jpc_screen.rvmat"] select (sunOrMoon > 0.5))
        };
        if ((_prep select 3) isNotEqualTo _mat) then {
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
        if ((getNumber (configOf _o >> "comspec_projBoard")) > 0) exitWith { "" };
        if ((getNumber (configOf _o >> "comspec_screen")) > 0) exitWith { (getArray (configOf _o >> "hiddenSelectionsTextures")) param [_sel, ""] };
        (getObjectTextures _o) param [_sel, ""]
    };
    case "load": {
        // Le display de la texture arrive ici à sa création : il appartient à l'écran en attente (un seul à la fois).
        // Displays rangés dans uiNamespace (jamais dans une variable d'objet : un display ne se sérialise pas).
        private _p = uiNamespace getVariable ["COMSPEC_ATAK_TocPending", []];
        private _o = _p param [0, objNull];
        if (isNull _o || {isNull _a}) exitWith {};
        private _disps = uiNamespace getVariable ["COMSPEC_ATAK_TocDisps", createHashMap];
        _disps set [_p param [2, ""], _a];
        uiNamespace setVariable ["COMSPEC_ATAK_TocDisps", _disps];
        _o setVariable ["COMSPEC_ATAK_TocDraws", 0];
        uiNamespace setVariable ["COMSPEC_ATAK_TocPending", []];
        ["INFO", "SCREEN", format ["Écran TOC %1 : texture UI %2 chargée, image dessinée", typeOf _o, _p param [2, ""]]] call comspec_atak_native_fnc_log;
    };
    case "tick": {
        if (!hasInterface || {isNull player}) exitWith {};
        disableSerialization;
        private _disps = uiNamespace getVariable ["COMSPEC_ATAK_TocDisps", createHashMap];
        private _reg = (missionNamespace getVariable ["COMSPEC_ATAK_TocScreens", []]) select { !isNull _x };
        private _local = (missionNamespace getVariable ["COMSPEC_ATAK_TocLocal", []]) select { !isNull _x };
        private _pend = uiNamespace getVariable ["COMSPEC_ATAK_TocPending", []];
        // Attente du display plus de 4 s : l'écran suivant peut prendre son tour (nouvel essai plus tard).
        if ((count _pend) >= 2 && {diag_tickTime - (_pend select 1) > 4}) then {
            if (!isNull (_pend select 0)) then {
                (_pend select 0) setVariable ["COMSPEC_ATAK_TocTex", ""];
                // Trace une fois par écran : l'image n'a jamais été dessinée par le jeu (dalle cachée ou hors champ).
                if !((_pend select 0) getVariable ["COMSPEC_ATAK_TocLoadWarn", false]) then {
                    (_pend select 0) setVariable ["COMSPEC_ATAK_TocLoadWarn", true];
                    ["WARNING", "TOC", format ["Écran TOC %1 : image non chargée en 4 s (dalle %2), nouvel essai", typeOf (_pend select 0), (_pend select 0) getVariable ["COMSPEC_ATAK_TocSel", 0]]] call comspec_atak_native_fnc_log;
                };
            };
            _pend = [];
            uiNamespace setVariable ["COMSPEC_ATAK_TocPending", []];
        };
        private _cam = positionCameraToWorld [0, 0, 0];
        // Écrans en vue caméra rendus en direct par ce poste (proches, regardés, dans la limite) : pas de texture UI.
        private _live = ["tick", _reg] call comspec_atak_native_fnc_tocCam;
        {
            private _o = _x;
            private _cfg = [_o] call _cfgOf;
            private _want = (count _cfg) >= 8 && {_o in _reg} && {alive _o} && {(_cam distance _o) < 150} && {[_o] call _sideOk} && {[_o] call _lit};
            private _tex = _o getVariable ["COMSPEC_ATAK_TocTex", ""];
            if (_want) then {
                if !(_o getVariable ["COMSPEC_ATAK_TocAct", false]) then {
                    _o setVariable ["COMSPEC_ATAK_TocAct", true];
                    ["actions", _o] call _fn;
                };
                // Diapositives prioritaires : on libère la dalle pour fn_screenCast. Caméra en direct : fn_tocCam la tient.
                if ([_o] call _casting || {_o in _live}) then {
                    if (_tex isNotEqualTo "") then {
                        // Caméra en direct : fn_tocCam vient de poser son image, ne pas la recouvrir.
                        // Diapositive déjà posée par fn_screenCast sur ce poste (cas des autres joueurs que le diffuseur) :
                        // ne pas la recouvrir par la texture d'origine, ni défaire la préparation de la dalle.
                        if (!(_o in _live) && {(_o getVariable ["COMSPEC_ATAK_ScreenCur", ""]) isEqualTo ""}) then {
                            ["paint", [_o, _o getVariable ["COMSPEC_ATAK_TocSel", 0], _o getVariable ["COMSPEC_ATAK_TocOrig", ""]]] call _fn;
                            ["surface", [_o, _o getVariable ["COMSPEC_ATAK_TocSel", 0], false]] call _fn;
                        };
                        _o setVariable ["COMSPEC_ATAK_TocTex", ""];
                        _disps deleteAt _tex;
                        if ((_pend param [0, objNull]) isEqualTo _o) then { uiNamespace setVariable ["COMSPEC_ATAK_TocPending", []]; _pend = []; };
                        _local = _local - [_o];
                    };
                } else {
                if (_tex isEqualTo "" && {(count _pend) isEqualTo 0}) then {
                    private _n = (uiNamespace getVariable ["COMSPEC_ATAK_TocSeq", 0]) + 1;
                    uiNamespace setVariable ["COMSPEC_ATAK_TocSeq", _n];
                    _tex = format ["comspec_atak_toc_%1", _n];
                    private _sel = [_o, _cfg select 2] call _face;
                    if (isNil { _o getVariable "COMSPEC_ATAK_TocOrig" }) then { _o setVariable ["COMSPEC_ATAK_TocOrig", ["orig", [_o, _sel]] call _fn]; };
                    _o setVariable ["COMSPEC_ATAK_TocTex", _tex];
                    _o setVariable ["COMSPEC_ATAK_TocSel", _sel];
                    _pend = [_o, diag_tickTime, _tex];
                    uiNamespace setVariable ["COMSPEC_ATAK_TocPending", _pend];
                    if !(_o getVariable ["COMSPEC_ATAK_TocReqLog", false]) then {
                        _o setVariable ["COMSPEC_ATAK_TocReqLog", true];
                        ["INFO", "SCREEN", format ["Écran TOC %1 (%2) : affichage %3, texture UI %4 demandée sur la dalle %5 (%6)", typeOf _o, _cfg select 0, _cfg select 3, _tex, _sel, (getArray (configOf _o >> "hiddenSelections")) param [_sel, "?"]]] call comspec_atak_native_fnc_log;
                    };
                    ["paint", [_o, _sel, format ["%1ui(""COMSPEC_RscTocScreenTexture"",""%2"")", _texSpec, _tex]]] call _fn;
                    ["surface", [_o, _sel, true]] call _fn;
                    _local pushBackUnique _o;
                };
                private _disp = _disps getOrDefault [_tex, displayNull];
                if (!isNull _disp) then {
                    private _draws = _o getVariable ["COMSPEC_ATAK_TocDraws", 0];
                    private _due = diag_tickTime - (_o getVariable ["COMSPEC_ATAK_TocAt", -10]) >= 2;
                    // Toujours les premières images (sinon noir tant qu'on ne l'a pas regardé), puis seulement vu et proche.
                    private _seen = (_cam distance _o) < 80 && {(worldToScreen (ASLToAGL getPosASL _o)) isNotEqualTo []};
                    if (_due && {_draws < 3 || {_seen}}) then {
                        // Matière jour / nuit suivie (rien ne change tant que le soleil ne passe pas l'horizon).
                        ["surface", [_o, _o getVariable ["COMSPEC_ATAK_TocSel", 0], true]] call _fn;
                        _o setVariable ["COMSPEC_ATAK_TocAt", diag_tickTime];
                        _o setVariable ["COMSPEC_ATAK_TocDraws", _draws + 1];
                        ["draw", [_o, _disp]] call _fn;
                        displayUpdate _disp;
                    };
                };
                }; // fin hors diffusion diapositives
            } else {
                // Trop loin, retiré ou détruit : texture d'origine remise, display abandonné.
                if (_tex isNotEqualTo "") then {
                    ["paint", [_o, _o getVariable ["COMSPEC_ATAK_TocSel", 0], _o getVariable ["COMSPEC_ATAK_TocOrig", ""]]] call _fn;
                    ["surface", [_o, _o getVariable ["COMSPEC_ATAK_TocSel", 0], false]] call _fn;
                    _o setVariable ["COMSPEC_ATAK_TocTex", ""];
                    _disps deleteAt _tex;
                    if ((_pend param [0, objNull]) isEqualTo _o) then { uiNamespace setVariable ["COMSPEC_ATAK_TocPending", []]; _pend = []; };
                    _local = _local - [_o];
                };
            };
        } forEach (_reg + (_local - _reg));
        missionNamespace setVariable ["COMSPEC_ATAK_TocLocal", _local];
    };
    case "draw": {
        _a params ["_o", "_d"];
        disableSerialization;
        private _cfg = [_o] call _cfgOf;
        _cfg params ["_name", "_cside", "", "_view", "_half", "_center"];
        // Vue caméra sans rendu en direct sur ce poste (trop loin, pas regardée, limite atteinte) : carte et flux.
        if (_view isEqualTo "CAM") then { _view = "SPLIT"; };
        private _side = [_cside, str side group player] select (_cside isEqualTo "");
        private _feed = missionNamespace getVariable [format ["COMSPEC_ATAK_TocFeed_%1", _side], []];
        _feed params [["_ft", -1], ["_nodes", []], ["_events", []], ["_stats", []]];
        private _esc = { params ["_t"]; if !(_t isEqualType "") then { _t = str _t; }; { _t = [_t, _x select 0, _x select 1] call CBA_fnc_replace; } forEach [["&", "&amp;"], ["<", "&lt;"], [">", "&gt;"]]; _t };
        private _sideRgb = (createHashMapFromArray [["WEST", [0.28, 0.62, 1]], ["EAST", [0.95, 0.3, 0.25]], ["GUER", [0.36, 0.85, 0.42]], ["CIV", [0.75, 0.5, 0.95]]]) getOrDefault [_side, [0.28, 0.62, 1]];
        private _sideName = (createHashMapFromArray [["WEST", "BLUFOR"], ["EAST", "OPFOR"], ["GUER", "Indépendant"], ["CIV", "Civil"]]) getOrDefault [_side, _side];
        // PIP du poste : ce que l'opérateur du PC regarde (fn_tocLaptop). L'opérateur du poste de même nom passe devant.
        private _op = [];
        if (_view isEqualTo "TOC") then {
            private _cands = (allPlayers select { !isNull _x && {alive _x} && {((_x getVariable ["COMSPEC_ATAK_TocView", []]) param [0, ""]) isEqualTo _side} }) apply { _x getVariable "COMSPEC_ATAK_TocView" };
            private _i = _cands findIf { (_x param [5, ""]) isEqualTo _name };
            _op = _cands param [_i max 0, []];
            if ((count _op) < 8) then { _view = "SPLIT"; _op = []; };
        };
        private _ui = _d getVariable ["COMSPEC_ATAK_TocUi", createHashMap];
        // Contrôles créés une fois par display (réserves d'icônes et de textes réutilisées à chaque image).
        if ((count _ui) isEqualTo 0) then {
            private _mk = {
                params ["_cls", ["_grp", controlNull]];
                private _c = if (isNull _grp) then { _d ctrlCreate [_cls, -1] } else { _d ctrlCreate [_cls, -1, _grp] };
                _c ctrlSetPosition [0, 0, 0, 0];
                _c ctrlCommit 0;
                _c
            };
            private _bg = ["RscText"] call _mk;
            _bg ctrlSetBackgroundColor [0.03, 0.04, 0.035, 1];
            _bg ctrlSetPosition [0, 0, 1, 1]; _bg ctrlCommit 0;
            private _hdr = ["RscText"] call _mk;
            _hdr ctrlSetBackgroundColor [0.06, 0.08, 0.07, 1];
            _hdr ctrlSetPosition [0, 0, 1, 0.075]; _hdr ctrlCommit 0;
            private _accent = ["RscText"] call _mk;
            _accent ctrlSetPosition [0, 0.072, 1, 0.004]; _accent ctrlCommit 0;
            private _title = ["COMSPEC_RscStructuredText"] call _mk;
            _title ctrlSetPosition [0.012, 0.012, 0.5, 0.06]; _title ctrlCommit 0;
            private _clock = ["COMSPEC_RscStructuredText"] call _mk;
            _clock ctrlSetPosition [0.515, 0.016, 0.475, 0.056]; _clock ctrlCommit 0;
            private _grp = ["RscControlsGroupNoScrollbars"] call _mk;
            private _sea = ["RscText", _grp] call _mk;
            _sea ctrlSetBackgroundColor [0.16, 0.22, 0.28, 1];
            private _tiles = []; for "_i" from 1 to 36 do { _tiles pushBack (["RscPicture", _grp] call _mk); };
            private _lines = []; for "_i" from 1 to 28 do { private _l = ["RscText", _grp] call _mk; _l ctrlSetBackgroundColor [0, 0, 0, 0.28]; _lines pushBack _l; };
            private _mkIcons = []; private _mkLbls = [];
            for "_i" from 1 to 40 do { _mkIcons pushBack (["RscPicture", _grp] call _mk); private _t = ["RscText", _grp] call _mk; _t ctrlSetFontHeight 0.024; _t ctrlSetFont "RobotoCondensedBold"; _t ctrlSetBackgroundColor [0, 0, 0, 0.55]; _t ctrlSetTextColor [1, 1, 1, 1]; _mkLbls pushBack _t; };
            private _evIcons = []; for "_i" from 1 to 12 do { _evIcons pushBack (["RscPicture", _grp] call _mk); };
            private _uIcons = []; private _uLbls = [];
            for "_i" from 1 to 100 do {
                _uIcons pushBack (["RscPicture", _grp] call _mk);
                private _t = ["RscText", _grp] call _mk;
                _t ctrlSetFontHeight 0.026; _t ctrlSetFont "RobotoCondensedBold"; _t ctrlSetBackgroundColor [0, 0, 0, 0.55];
                _uLbls pushBack _t;
            };
            private _scale = ["RscText", _grp] call _mk;
            _scale ctrlSetFontHeight 0.026; _scale ctrlSetBackgroundColor [0, 0, 0, 0.6]; _scale ctrlSetTextColor [1, 1, 1, 1];
            private _north = ["RscText", _grp] call _mk;
            _north ctrlSetFontHeight 0.04; _north ctrlSetFont "RobotoCondensedBold"; _north ctrlSetBackgroundColor [0, 0, 0, 0.6]; _north ctrlSetTextColor [1, 1, 1, 1];
            _north ctrlSetText (("N ▲") call comspec_atak_native_fnc_tr);
            private _empty = ["COMSPEC_RscStructuredText", _grp] call _mk;
            private _panel = ["COMSPEC_RscStructuredText"] call _mk;
            _panel ctrlSetBackgroundColor [0.05, 0.065, 0.058, 1];
            private _panel2 = ["COMSPEC_RscStructuredText"] call _mk;
            _panel2 ctrlSetBackgroundColor [0.05, 0.065, 0.058, 1];
            private _flux = ["COMSPEC_RscStructuredText"] call _mk;
            _flux ctrlSetBackgroundColor [0.05, 0.065, 0.058, 1];
            private _tbl = [];
            for "_i" from 1 to 24 do {
                _tbl pushBack ((["left", "right", "left"]) apply {
                    private _tc = ["RscText"] call _mk;
                    _tc ctrlSetFontHeight 0.03;
                    _tc ctrlSetFont "RobotoCondensed";
                    _tc
                });
            };
            _ui = createHashMapFromArray [["panel2", _panel2], ["flux", _flux], ["tbl", _tbl], ["accent", _accent], ["title", _title], ["clock", _clock], ["grp", _grp], ["sea", _sea], ["tiles", _tiles], ["lines", _lines],
                ["mkIcons", _mkIcons], ["mkLbls", _mkLbls], ["evIcons", _evIcons], ["uIcons", _uIcons], ["uLbls", _uLbls], ["scale", _scale], ["north", _north], ["empty", _empty], ["panel", _panel]];
            _d setVariable ["COMSPEC_ATAK_TocUi", _ui];
        };
        (_ui get "accent") ctrlSetBackgroundColor (_sideRgb + [1]);
        private _age = [-1, round ((serverTime - _ft) max 0)] select (_ft >= 0);
        (_ui get "title") ctrlSetStructuredText parseText (((format ["<t size='1.7' font='COMSPEC_BarlowBold'>TOC · %1</t>", [_name select [0, 20]] call _esc])) call comspec_atak_native_fnc_tr);
        if ((count _op) >= 8) then {
            (_ui get "title") ctrlSetStructuredText parseText (((format ["<t size='1.7' font='COMSPEC_BarlowBold'>TOC · %1</t>  <t size='1.2' color='#e0b040'>PIP du poste</t>",
                [(_op param [5, _name]) select [0, 14]] call _esc])) call comspec_atak_native_fnc_tr);
        };
        private _live = [["<t color='#e05a4a'>● HORS LIGNE</t>", "<t color='#e0b040'>● RETARD</t>"] select (_age >= 0), "<t color='#5cc76b'>● EN DIRECT</t>"] select (_age >= 0 && {_age < 12});
        (_ui get "clock") ctrlSetStructuredText parseText (((format ["<t align='right' size='1.2'>%1  <t color='#8a9a93'>%2</t>  %3</t>",
            [dayTime, "HH:MM"] call BIS_fnc_timeToString, [format ["il y a %1 s", _age], "aucune donnée"] select (_age < 0), _live])) call comspec_atak_native_fnc_tr);

        // Mise en page selon l'affichage : carte + flux, carte seule, flux seul.
        private _top = 0.08;
        private _noMap = _view in ["FEED", "NET", "ORBAT"];
        private _mapR = switch (true) do { case (_view isEqualTo "MAP"): { [0, _top, 1, 1 - _top] }; case _noMap: { [0, 0, 0, 0] }; default { [0, _top, 0.64, 1 - _top] }; };
        private _panR = switch (true) do { case (_view isEqualTo "MAP"): { [0, 0, 0, 0] }; case _noMap: { [0.015, _top + 0.01, 0.97, 0.98 - _top] }; default { [0.648, _top + 0.008, 0.344, 0.984 - _top] }; };
        // PIP : cadrage de la carte imposé par le PC de l'opérateur.
        if ((count _op) >= 8) then { _center = _op select 1; _half = _op select 2; };
        private _grp = _ui get "grp";
        _grp ctrlSetPosition _mapR; _grp ctrlCommit 0;
        _grp ctrlShow !_noMap;

        // ---- Carte -------------------------------------------------------------------------------------------
        if (!_noMap) then {
            _mapR params ["", "", "_mw", "_mh"];
            private _pxW = _mw * _texW;
            private _pxH = _mh * _texH;
            // Cadrage : centre et demi-largeur choisis, sinon le réseau entier (marge 25 %), 600 m au moins.
            private _cx = worldSize / 2; private _cy = worldSize / 2; private _hw = _half;
            if ((count _center) >= 2) then { _cx = _center select 0; _cy = _center select 1; } else {
                if ((count _nodes) > 0) then {
                    private _xs = _nodes apply { (_x select 2) select 0 };
                    private _ys = _nodes apply { (_x select 2) select 1 };
                    private _x0 = selectMin _xs; private _x1 = selectMax _xs; private _y0 = selectMin _ys; private _y1 = selectMax _ys;
                    _cx = (_x0 + _x1) / 2; _cy = (_y0 + _y1) / 2;
                    if (_hw <= 0) then { _hw = ((((_x1 - _x0) / 2) max (((_y1 - _y0) / 2) * _pxW / _pxH)) * 1.25) max 600; };
                } else {
                    private _p = getPosASL _o; _cx = _p select 0; _cy = _p select 1;
                };
            };
            if (_hw <= 0) then { _hw = 1500; };
            _hw = (_hw max 100) min (worldSize max 1000);
            _o setVariable ["COMSPEC_ATAK_TocShown", [[_cx, _cy], _hw]];
            private _k = _pxW / (2 * _hw);           // pixels de texture par mètre
            private _hh = _hw * _pxH / _pxW;
            private _toG = { params ["_px", "_py"]; [(_px - (_cx - _hw)) * _k / _texW, ((_cy + _hh) - _py) * _k / _texH] };
            private _inView = { params ["_px", "_py"]; _px > (_cx - _hw) && {_px < (_cx + _hw)} && {_py > (_cy - _hh)} && {_py < (_cy + _hh)} };
            (_ui get "sea") ctrlSetPosition [0, 0, _mw, _mh]; (_ui get "sea") ctrlCommit 0;
            // Fond : photo de la carte (mod @COMSPEC_ATAK_Photo), sinon carte d'ensemble du terrain.
            private _tiles = _ui get "tiles";
            private _used = 0;
            private _info = ["info"] call comspec_atak_native_fnc_photoMap;
            if ((count _info) > 0 && {(_info param [6, ""]) isEqualTo "pbo"}) then {
                _info params ["_ext", "_ts", "_zMin", "_zMax", "_path", "_fx"];
                private _zPaths = _info param [10, createHashMap];
                private _mpp = 2 * _hw / _pxW;
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
                    _c ctrlSetPosition [_gx, _gy, worldSize * _k / _texW, worldSize * _k / _texH];
                    _c ctrlCommit 0;
                    _c ctrlShow true;
                    _used = 1;
                };
            };
            for "_i" from _used to ((count _tiles) - 1) do { (_tiles select _i) ctrlShow false; };
            // Quadrillage : pas de 100 m à 10 km, une dizaine de lignes par axe au plus.
            private _step = 100;
            { if ((2 * _hw / _step) > 10) then { _step = _x; }; } forEach [200, 500, 1000, 2000, 5000, 10000];
            private _lines = _ui get "lines";
            private _li = 0;
            for "_gx" from ((ceil ((_cx - _hw) / _step)) * _step) to (_cx + _hw) step _step do {
                if (_li < 14) then {
                    private _c = _lines select _li;
                    _c ctrlSetPosition [(([_gx, 0] call _toG) select 0), 0, 1.5 / _texW, _mh]; _c ctrlCommit 0; _c ctrlShow true;
                    _li = _li + 1;
                };
            };
            for "_gy" from ((ceil ((_cy - _hh) / _step)) * _step) to (_cy + _hh) step _step do {
                if (_li < 28) then {
                    private _c = _lines select _li;
                    _c ctrlSetPosition [0, (([0, _gy] call _toG) select 1), _mw, 1.5 / _texH]; _c ctrlCommit 0; _c ctrlShow true;
                    _li = _li + 1;
                };
            };
            for "_i" from _li to ((count _lines) - 1) do { (_lines select _i) ctrlShow false; };
            // Plaques posées une à une, en pixels de texture : à droite, à gauche, au-dessus, au-dessous ; sans place libre, symbole seul.
            private _taken = [];
            private _free = {
                params ["_x0", "_y0", "_w", "_h"];
                (_taken findIf { _x0 < ((_x select 0) + (_x select 2)) && {(_x0 + _w) > (_x select 0)} && {_y0 < ((_x select 1) + (_x select 3))} && {(_y0 + _h) > (_x select 1)} }) < 0
            };
            private _place = {
                params ["_px", "_py", "_w", "_h", "_r"];
                private _res = [];
                {
                    if ([_x select 0, _x select 1, _w, _h] call _free) exitWith { _res = [_x select 0, _x select 1]; _taken pushBack [_x select 0, _x select 1, _w, _h]; };
                } forEach [[_px + _r, _py - _h / 2], [_px - _r - _w, _py - _h / 2], [_px - _w / 2, _py - _r - _h], [_px - _w / 2, _py + _r]];
                _res
            };
            // Nœuds voisins (même case de 64 pixels) regroupés en un symbole : le plus frais donne le nom, « +n » le reste.
            private _cells = createHashMap;
            {
                private _p = _x select 2;
                if ([_p select 0, _p select 1] call _inView) then {
                    ([_p select 0, _p select 1] call _toG) params ["_gx", "_gy"];
                    private _ck = [floor (_gx * _texW / 64), floor (_gy * _texH / 64)];
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
            private _nodeDraw = [];
            {
                _x params ["", "_lead", "_sum", "_mem"];
                _lead params ["", "_cs", "_p", "", "_fr"];
                ([_p select 0, _p select 1] call _toG) params ["_gx", "_gy"];
                private _lbl = _cs + (["", format [" +%1", _sum - 1]] select (_sum > 1)) + (["", " · ancien", " · perdu", " · hors ligne"] select (_fr min 3));
                private _lw = (count _lbl) * 12 + 12;
                private _at = [_gx * _texW, _gy * _texH, _lw, 26, 22] call _place;
                _nodeDraw pushBack [_lead, _mem, _gx, _gy, _lbl, _lw, _at];
            } forEach (_clusters select [0, 99]);
            // Marqueurs de la mission visibles par ce joueur (icônes seulement).
            private _mkSeen = createHashMap;
            private _mkIcons = _ui get "mkIcons";
            private _mkLbls = _ui get "mkLbls";
            private _mi = 0;
            private _colCache = uiNamespace getVariable ["COMSPEC_ATAK_TocMkCol", createHashMap];
            uiNamespace setVariable ["COMSPEC_ATAK_TocMkCol", _colCache];
            {
                if (_mi < 40 && {(markerShape _x) isEqualTo "ICON"} && {(markerAlpha _x) > 0}) then {
                    private _p = getMarkerPos _x;
                    if ([_p select 0, _p select 1] call _inView) then {
                        private _icon = getText (configFile >> "CfgMarkers" >> (markerType _x) >> "icon");
                        ([_p select 0, _p select 1] call _toG) params ["_mgx", "_mgy"];
                        // Deux marqueurs presque au même point : un seul symbole.
                        private _mkCk = [floor (_mgx * _texW / 28), floor (_mgy * _texH / 28)];
                        if (_icon isNotEqualTo "" && {!(_mkCk in _mkSeen)}) then {
                            _mkSeen set [_mkCk, true];
                            private _col = _colCache getOrDefault [markerColor _x, []];
                            if (_col isEqualTo []) then {
                                _col = (getArray (configFile >> "CfgMarkerColors" >> (markerColor _x) >> "color")) apply { if (_x isEqualType "") then { call compile _x } else { _x } };
                                if ((count _col) < 4) then { _col = [0, 0, 0, 1]; };
                                _colCache set [markerColor _x, _col];
                            };
                            ([_p select 0, _p select 1] call _toG) params ["_gx", "_gy"];
                            private _c = _mkIcons select _mi;
                            _c ctrlSetText ((_icon) call comspec_atak_native_fnc_tr);
                            _c ctrlSetTextColor _col;
                            _c ctrlSetPosition [_gx - 13 / _texW, _gy - 13 / _texH, 26 / _texW, 26 / _texH]; _c ctrlCommit 0; _c ctrlShow true;
                            private _t = _mkLbls select _mi;
                            private _txt = markerText _x;
                            _t ctrlSetText ((_txt) call comspec_atak_native_fnc_tr);
                            private _mw2 = (count _txt) * 11 + 10;
                            private _mAt = if (_txt isEqualTo "") then { [] } else { [_gx * _texW, _gy * _texH, _mw2, 24, 16] call _place };
                            if ((count _mAt) > 0) then { _t ctrlSetPosition [(_mAt select 0) / _texW, (_mAt select 1) / _texH, _mw2 / _texW, 24 / _texH]; _t ctrlCommit 0; };
                            _t ctrlShow ((count _mAt) > 0);
                            _mi = _mi + 1;
                        };
                    };
                };
            } forEach allMapMarkers;
            for "_i" from _mi to 39 do { (_mkIcons select _i) ctrlShow false; (_mkLbls select _i) ctrlShow false; };
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
                    _c ctrlSetPosition [_gx - 15 / _texW, _gy - 15 / _texH, 30 / _texW, 30 / _texH]; _c ctrlCommit 0; _c ctrlShow true;
                    _ei = _ei + 1;
                };
            } forEach _events;
            for "_i" from _ei to 11 do { (_evIcons select _i) ctrlShow false; };
            // Nœuds ATAK : symbole OTAN ami, couleur du camp ; ancien pâli, perdu et hors ligne en gris.
            private _uIcons = _ui get "uIcons";
            private _uLbls = _ui get "uLbls";
            private _ui0 = 0;
            {
                _x params ["_lead", "_mem", "_gx", "_gy", "_lbl", "_lw", "_at"];
                _lead params ["", "", "", "", "_fr", "_kind"];
                private _tex = format ["\A3\ui_f\data\map\markers\nato\b_%1.paa", (createHashMapFromArray [["veh", "motor_inf"], ["air", "air"], ["boat", "naval"], ["armor", "armor"]]) getOrDefault [_kind, "inf"]];
                private _col = switch (_fr) do {
                    case 0: { _sideRgb + [1] };
                    case 1: { (_sideRgb apply { _x * 0.75 }) + [0.75] };
                    case 2: { [0.62, 0.62, 0.62, 0.85] };
                    default { [0.45, 0.45, 0.45, 0.7] };
                };
                private _c = _uIcons select _ui0;
                _c ctrlSetText ((_tex) call comspec_atak_native_fnc_tr);
                _c ctrlSetTextColor _col;
                _c ctrlSetPosition [_gx - 18 / _texW, _gy - 18 / _texH, 36 / _texW, 36 / _texH]; _c ctrlCommit 0; _c ctrlShow true;
                private _t = _uLbls select _ui0;
                _t ctrlSetText ((_lbl) call comspec_atak_native_fnc_tr);
                // PIP : le groupe choisi par l'opérateur ressort en jaune.
                private _pickG = _op param [4, ""];
                private _picked = (count _op) >= 5 && {(_op param [3, ""]) isEqualTo "GROUPES"} && {_pickG isNotEqualTo ""} && {(_mem findIf { (_x param [7, ""]) isEqualTo _pickG }) >= 0};
                _t ctrlSetTextColor ([[[1, 1, 1, 1], [0.8, 0.8, 0.8, 1]] select (_fr >= 2), [1, 0.85, 0.3, 1]] select _picked);
                if (_picked) then { _c ctrlSetTextColor [1, 0.85, 0.3, 1]; };
                if ((count _at) > 0) then { _t ctrlSetPosition [(_at select 0) / _texW, (_at select 1) / _texH, _lw / _texW, 26 / _texH]; _t ctrlCommit 0; };
                _t ctrlShow ((count _at) > 0);
                _ui0 = _ui0 + 1;
            } forEach _nodeDraw;
            // PIP : objectif de l'ordre en cours de rédaction sur le PC, et repère du groupe choisi.
            if ((count _op) >= 9) then {
                private _opos = _op param [8, []];
                if ((count _opos) >= 2 && {[_opos select 0, _opos select 1] call _inView} && {_ui0 < 100}) then {
                    ([_opos select 0, _opos select 1] call _toG) params ["_gx", "_gy"];
                    private _c = _uIcons select _ui0;
                    _c ctrlSetText (("\A3\ui_f\data\map\markers\military\objective_CA.paa") call comspec_atak_native_fnc_tr);
                    _c ctrlSetTextColor [1, 0.85, 0.2, 1];
                    _c ctrlSetPosition [_gx - 20 / _texW, _gy - 20 / _texH, 40 / _texW, 40 / _texH]; _c ctrlCommit 0; _c ctrlShow true;
                    private _t = _uLbls select _ui0;
                    _t ctrlSetText (("OBJECTIF") call comspec_atak_native_fnc_tr);
                    _t ctrlSetTextColor [1, 0.9, 0.5, 1];
                    private _oAt = [_gx * _texW, _gy * _texH, 120, 26, 24] call _place;
                    if ((count _oAt) > 0) then { _t ctrlSetPosition [(_oAt select 0) / _texW, (_oAt select 1) / _texH, 120 / _texW, 26 / _texH]; _t ctrlCommit 0; };
                    _t ctrlShow ((count _oAt) > 0);
                    _ui0 = _ui0 + 1;
                };
            };
            for "_i" from _ui0 to 99 do { (_uIcons select _i) ctrlShow false; (_uLbls select _i) ctrlShow false; };
            private _sc = _ui get "scale";
            private _scTxt = ((format ["Carreaux de %1 · largeur %2", [format ["%1 m", _step], format ["%1 km", _step / 1000]] select (_step >= 1000), [format ["%1 m", round (2 * _hw)], format ["%1 km", (round (2 * _hw / 100)) / 10]] select (_hw >= 500)])) call comspec_atak_native_fnc_tr;
            _sc ctrlSetText _scTxt; // i18n:brut (déjà traduit)
            _sc ctrlSetPosition [10 / _texW, _mh - 36 / _texH, ((count _scTxt) * 13 + 20) / _texW, 30 / _texH]; _sc ctrlCommit 0;
            private _no = _ui get "north";
            _no ctrlSetPosition [_mw - 70 / _texW, 10 / _texH, 60 / _texW, 44 / _texH]; _no ctrlCommit 0;
            private _em = _ui get "empty";
            _em ctrlShow ((count _nodes) isEqualTo 0);
            _em ctrlSetStructuredText parseText (((("<t align='center' size='1.6' color='#cfd8d3'>Aucun nœud ATAK de ce camp sur le réseau</t>"))) call comspec_atak_native_fnc_tr);
            _em ctrlSetPosition [0, _mh / 2 - 0.03, _mw, 0.06]; _em ctrlCommit 0;
        };

        // ---- Panneau : résumé du réseau, groupes en colonnes par pages, flux ; textes réécrits seulement s'ils changent ---
        private _setTxt = {
            params ["_c", "_s"];
            if ((_c getVariable ["COMSPEC_ATAK_Txt", ""]) isNotEqualTo _s) then {
                _c setVariable ["COMSPEC_ATAK_Txt", _s];
                _c ctrlSetStructuredText parseText ((_s) call comspec_atak_native_fnc_tr);
            };
        };
        private _pan = _ui get "panel";
        private _pan2 = _ui get "panel2";
        private _flx = _ui get "flux";
        private _tbl = _ui get "tbl";
        private _tblOn = _view in ["SPLIT", "TOC", "NET"];
        private _full = _view in ["FEED", "NET", "ORBAT"];
        _panR params ["_px", "_py", "_pw", "_ph"];
        _pan ctrlShow (_view isNotEqualTo "MAP");
        _pan2 ctrlShow (_view isEqualTo "ORBAT");
        _flx ctrlShow (_view in ["SPLIT", "TOC"]);
        private _used = 0;
        private _cnt = [0, 0, 0, 0];
        { private _f = (_x select 4) min 3; _cnt set [_f, (_cnt select _f) + (_x param [8, 1])]; } forEach _nodes;
        _stats params [["_jam", 0], ["_relays", 0], ["_players", 0], ["_ais", 0]];
        private _s = "";
        if ((count _op) >= 8) then {
            _s = format ["<t size='1.05' color='#e0b040'>PIP · %1 · onglet %2</t><br/>", [_op param [6, ""]] call _esc, [_op param [3, ""]] call _esc];
        };
        _s = _s + format ["<t size='1.15' font='COMSPEC_BarlowBold'>RÉSEAU</t> <t size='1.05'><t color='#5cc76b'>%1 en ligne</t> · <t color='#e0b040'>%2 anc.</t> · <t color='#9aa39f'>%3 perd.</t> · <t color='#6f7773'>%4 hors l.</t></t><br/><t size='1' color='#8a9a93'>%5 joueurs · %6 IA · %7 relais · %8 brouilleurs</t>",
            _cnt select 0, _cnt select 1, _cnt select 2, _cnt select 3, _players, _ais, _relays, _jam];
        if (_tblOn) then {
            private _gm = createHashMap;
            {
                private _g = _x param [7, ""];
                private _e = _gm getOrDefault [_g, [0, _x select 2, 9]];
                _gm set [_g, [(_e select 0) + (_x param [8, 1]), _e select 1, (_e select 2) min (_x select 4)]];
            } forEach _nodes;
            private _gk = keys _gm;
            _gk sort true;
            private _hdrH = 0.125 + ([0, 0.04] select ((count _op) >= 8));
            private _rowH = 0.036;
            private _bottom = [0.68, 0.985] select _full;
            private _nRow = (floor ((_bottom - _py - _hdrH) / _rowH)) min (count _tbl);
            // Pages automatiques : une toutes les 8 s quand tout ne tient pas.
            private _nPg = (ceil ((count _gk) / (_nRow max 1))) max 1;
            private _pg = (floor (diag_tickTime / 8)) mod _nPg;
            _s = _s + format ["<br/><t size='1.15' font='COMSPEC_BarlowBold'>GROUPES</t> <t size='1' color='#8a9a93'>%1 · nœuds · grille%2</t>", count _gk, ["", format [" · page %1 / %2", _pg + 1, _nPg]] select (_nPg > 1)];
            private _colX = [_px + 0.006, _px + _pw * 0.58, _px + _pw * 0.70];
            private _colW = [_pw * 0.57, _pw * 0.11, _pw * 0.29];
            {
                private _k = _x;
                (_gm get _k) params ["_n", "_p", "_best"];
                private _row = _tbl select _forEachIndex;
                private _rgba = [[0.36, 0.78, 0.42, 1], [0.88, 0.69, 0.25, 1], [0.6, 0.64, 0.62, 1], [0.44, 0.47, 0.45, 1]] select ((_best min 3) max 0);
                private _vals = [_k, str _n, [[_p select 0, _p select 1, 0], 6] call comspec_atak_native_fnc_gridRef];
                private _y = _py + _hdrH + _forEachIndex * _rowH;
                {
                    private _i = _forEachIndex;
                    _x ctrlSetText ((_vals select _i) call comspec_atak_native_fnc_tr);
                    _x ctrlSetTextColor ([_rgba, [0.85, 0.89, 0.86, 1]] select (_i > 0));
                    _x ctrlSetPosition [_colX select _i, _y, _colW select _i, _rowH];
                    _x ctrlCommit 0;
                    _x ctrlShow true;
                } forEach _row;
                _used = _used + 1;
            } forEach (_gk select [_pg * _nRow, _nRow]);
            if ((count _gk) isEqualTo 0) then { _s = _s + "<br/><t size='1' color='#8a9a93'>Aucun nœud ATAK de ce camp sur le réseau</t>"; };
            _pan ctrlSetPosition [_px, _py, _pw, _hdrH]; _pan ctrlCommit 0;
        };
        for "_i" from _used to ((count _tbl) - 1) do { { _x ctrlShow false; } forEach (_tbl select _i); };
        // Flux : une ligne par événement sous la table, en entier dans l'affichage FEED.
        if (_view in ["SPLIT", "TOC", "FEED"]) then {
            private _eMax = [5, 18] select (_view isEqualTo "FEED");
            private _ev = _events select [0 max ((count _events) - _eMax)];
            reverse _ev;
            private _es = "<t size='1.15' font='COMSPEC_BarlowBold'>FLUX</t><br/>";
            if ((count _events) isEqualTo 0) then { _es = _es + "<t size='1' color='#8a9a93'>Aucun compte rendu ni message pour l'instant</t>"; };
            {
                _x params [["_time", ""], ["_kind", ""], ["_prio", ""], ["_who", ""], ["_grid", ""], ["_text", ""], "", "", ["_old", false]];
                private _pc = switch (toUpper _prio) do { case "FLASH": { "#ff5a4a" }; case "URGENT": { "#e0b040" }; default { ["#7fd0b0", "#6f7773"] select _old }; };
                _es = _es + format ["<t size='1'><t color='#8a9a93'>%1</t> <t color='%2' font='COMSPEC_BarlowBold'>%3</t> %4%5</t><br/>", _time, _pc, [_kind] call _esc, [_who select [0, 16]] call _esc,
                    ["", format [" · %1", [_text select [0, 110]] call _esc]] select (_view isEqualTo "FEED" && {_text isNotEqualTo ""})];
            } forEach _ev;
            if (_view isEqualTo "FEED") then { _s = _s + "<br/><br/>" + _es; _pan ctrlSetPosition _panR; _pan ctrlCommit 0; } else {
                _flx ctrlSetPosition [_px, 0.685, _pw, 0.3]; _flx ctrlCommit 0;
                [_flx, _es] call _setTxt;
            };
        };
        // ORBAT du site : sur deux colonnes, par pages, chaque membre avec son état.
        if (_view isEqualTo "ORBAT") then {
            ["load"] call comspec_atak_native_fnc_bftOrbat;
            private _store = ["store"] call comspec_atak_native_fnc_bftOrbat;
            (["presence"] call comspec_atak_native_fnc_bftOrbat) params ["", "_onKey"];
            (["notice"] call comspec_atak_native_fnc_bftOrbat) params ["_nTxt"];
            private _people = _store get "people";
            private _lines = [];
            {
                _x params ["", "_ud", "_uName", "_uCode", "_uRows"];
                _lines pushBack format ["<t size='1.05' font='COMSPEC_BarlowBold' color='#7fb8e0'>%1%2%3</t>", ["", "· "] select (_ud > 0), [_uName select [0, 34]] call _esc, ["", format [" (%1)", [_uCode] call _esc]] select (_uCode isNotEqualTo "")];
                {
                    private _pe = _people getOrDefault [_x select 1, []];
                    if ((_x select 0) isEqualTo "v") then {
                        _lines pushBack format ["<t size='1' color='#6f7773'>○ %1 · poste vacant</t>", [(_x select 2) select [0, 28]] call _esc];
                    } else {
                        private _on = (_x select 1) in _onKey;
                        private _anon = (_pe param [4, 0]) isEqualTo 1;
                        _lines pushBack format ["<t size='1'><t color='%1'>%2</t> %3</t>", ["#6f7773", "#5cc76b"] select _on, ["○", "●"] select _on, [(_pe param [0, ""]) select [0, 38]] call _esc];
                        if (_anon) then { _lines set [(count _lines) - 1, format ["<t size='1' color='#6f7773'>· %1</t>", [(_pe param [0, ""]) select [0, 38]] call _esc]]; };
                    };
                } forEach _uRows;
            } forEach (_store get "units");
            private _perCol = 24;
            private _nPg = (ceil ((count _lines) / (2 * _perCol))) max 1;
            private _pg = (floor (diag_tickTime / 10)) mod _nPg;
            private _page = _lines select [_pg * 2 * _perCol, 2 * _perCol];
            _s = format ["<t size='1' color='#8a9a93'>%1%2</t><br/>", [_nTxt] call _esc, ["", format [" · page %1 / %2", _pg + 1, _nPg]] select (_nPg > 1)] + ((_page select [0, _perCol]) joinString "<br/>");
            _pan ctrlSetPosition [_px, _py, _pw * 0.495, _ph]; _pan ctrlCommit 0;
            _pan2 ctrlSetPosition [_px + _pw * 0.505, _py, _pw * 0.495, _ph]; _pan2 ctrlCommit 0;
            [_pan2, (_page select [_perCol, _perCol]) joinString "<br/>"] call _setTxt;
        };
        if (_view isNotEqualTo "MAP") then { [_pan, _s] call _setTxt; };
        true
    };
    case "ensure": {
        // Désigne l'objet comme écran TOC (ou change seulement l'affichage) : ACE sur n'importe quel écran du jeu.
        _a params [["_o", objNull], ["_view", "SPLIT"]];
        if (isNull _o || {!alive _o}) exitWith { false };
        if !(_view in ["SPLIT", "MAP", "FEED", "NET", "ORBAT", "TOC", "CAM"]) then { _view = "SPLIT"; };
        private _c = [_o] call _cfgOf;
        if ((count _c) >= 8) then {
            ["view", [_o, "mode", _view]] call _fn;
        } else {
            private _nm = getText (configOf _o >> "displayName");
            if (_nm isEqualTo "") then { _nm = "TOC"; };
            ["add", [_o, _nm select [0, 32], "", 0, _view, true, false]] call _fn;
        };
        _o setVariable ["COMSPEC_ATAK_TocAt", -10];
        private _lbl = (createHashMapFromArray [["SPLIT", "carte et flux"], ["MAP", "carte seule"], ["FEED", "flux seul"], ["NET", "réseau"], ["ORBAT", "ORBAT du site"], ["TOC", "PIP du poste"], ["CAM", "caméra en direct"]]) getOrDefault [_view, _view];
        ["INFO", format ["Écran TOC : %1", _lbl], 2, 20] call comspec_atak_native_fnc_notify;
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
            switch (_k) do {
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
            private _diff = [];
            if ((count _c) >= 5) then {
                private _acad = (_c select 2) isEqualTo "ACADEMY";
                _diff pushBack (["Next", ["Diapositive suivante", "Page suivante"] select _acad, "slidesNext"] call _mk);
                _diff pushBack (["Prev", ["Diapositive précédente", "Page précédente"] select _acad, "slidesPrev"] call _mk);
                _diff pushBack (["Stop", "Arrêter la diffusion", "slidesStop"] call _mk);
            } else {
                _diff pushBack (["Slides", "Diffuser mes diapositives", "slides", { [_player] call comspec_atak_native_fnc_hasDevice }] call _mk);
            };
            private _cams = [];
            {
                _x params ["_lbl", "_id"];
                _cams pushBack [["COMSPEC_ATAK_WallCam" + str _forEachIndex, _lbl, "", { ["set", [_target, _this select 2]] call comspec_atak_native_fnc_tocCam; }, { true }, {}, _id] call comspec_atak_native_fnc_aceAction, [], _o];
            } forEach ((["sources"] call comspec_atak_native_fnc_tocCam) select [0, 12]);
            if (_cams isNotEqualTo []) then { _diff pushBack (["Cam", "Caméra en direct", _d + "app_livecam.paa", _cams] call _grp); };
            _diff pushBack (["Pip", "PIP du poste", "pip"] call _mk);
            private _aff = [];
            if (!_has) then {
                _aff pushBack (["Split", "Afficher le réseau TOC", "split"] call _mk);
            } else {
                _aff pushBack (["SplitOn", "Carte et flux", "split"] call _mk);
                _aff pushBack (["MapOnly", "Carte seule", "map"] call _mk);
                _aff pushBack (["FeedOnly", "Flux seul", "feed"] call _mk);
                _aff pushBack (["NetOnly", "Réseau", "net"] call _mk);
                _aff pushBack (["OrbatOnly", "ORBAT du site", "orbat"] call _mk);
                if (_o getVariable ["COMSPEC_ATAK_Toc", false]) then { _aff pushBack (["Big", "Ouvrir le PC du poste", "big", { isNull objectParent _player }] call _mk); };
                _aff pushBack (["Off", "Retirer l'affichage TOC", "remove"] call _mk);
            };
            private _out = [["Diff", "Diffusion", _d + "app_briefing.paa", _diff] call _grp, ["Toc", "Affichage TOC", _d + "app_toc.paa", _aff] call _grp];
            if (_has) then {
                _out pushBack (["Map", "Carte", _d + "app_map.paa", [
                    ["In", "Zoomer", "in"] call _mk, ["Out", "Dézoomer", "out"] call _mk,
                    ["Me", "Centrer sur ma position", "me"] call _mk, ["Auto", "Cadrer tout le réseau", "auto"] call _mk
                ]] call _grp);
            };
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
