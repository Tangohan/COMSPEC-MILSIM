/*
    Écrans TOC (demande de TTRD, 26/10) : un grand écran du jeu (TV, écran sur trépied, écran de projection, poste
    informatique) qui affiche en direct le réseau ATAK d'un camp, tel que le serveur de la mission le voit
    (fn_tocFeed) : carte avec les nœuds ATAK (joueurs et IA équipées, à leur dernier point PLI, en direct / ancien /
    perdu / hors ligne), marqueurs de la mission, derniers comptes rendus, alertes et messages du poste, état du réseau.
    L'écran est une texture UI (#(rgb,1600,900,1)ui("COMSPEC_RscTocScreenTexture", nom unique)) posée par chaque joueur
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
      COMSPEC_ATAK_TocScr [nom, camp ("" = celui de chaque joueur), index de texture, affichage ("SPLIT" | "MAP" | "FEED" | "TOC"),
                           demi-largeur de la carte en m (0 = cadrage automatique sur le réseau), centre ([] = auto),
                           poste TOC (l'écran sert aussi de PC de TOC), à recréer à la partie suivante (posé en cours de partie)]
      COMSPEC_ATAK_TocOwned   objet créé par le module ou la sauvegarde (supprimé avec l'écran)
    Registre : missionNamespace COMSPEC_ATAK_TocScreens (objets), publié par le serveur.
    Ces variables publiques ne servent qu'à l'affichage chez les joueurs. Le serveur tient sa propre copie, jamais
    diffusée (localNamespace COMSPEC_ATAK_TocSrv [[objet, état]...] et COMSPEC_ATAK_TocMade, objets créés par « spawn »),
    seule lue pour juger une demande, sauver (fn_tocPersist), recréer ou supprimer (lot mpauth3, SEC-033).

    Modes :
      ["add", [objet, nom, camp, index, affichage, poste TOC, à recréer]]   (serveur ; ailleurs, demande au serveur)
      ["remove", objet]                    retire l'écran (et supprime l'objet s'il a été créé par le module)
      ["spawn", [position ATL, direction, classe, nom, camp, affichage, poste TOC]]   crée l'écran (serveur)
      ["view", [objet, clé, valeur]]       "mode" affichage | "zoom" demi-largeur | "center" position | "auto"
      ["classes"]                          écrans du jeu proposés à la pose [[libellé, classe]...] (classes présentes)
      ["tick"]                             client, chaque seconde (XEH_postInitClient) : textures et rafraîchissement
      ["load", display]                    onLoad du display de la texture
      ["initServer"]                       serveur (XEH_postInitServer)
    Demande d'un client : [mode, argument] remoteExecCall ["comspec_atak_native_fnc_tocScreen", 2] (CfgRemoteExec), jugée
    sur le demandeur retrouvé par remoteExecutedOwner (fn_reqUnit). « add », « remove » et « spawn » : Zeus, hôte, admin
    connecté ou UID autorisé (fn_adminAllowed) ; « view » : en plus, joueur du camp de l'écran, à moins de 25 m.
    Plus d'événement CBA comspec_atak_native_tocScreen.
    Poste client (lot mpauth3, SEC-035) : « add », « remove », « spawn » et « view » y relaient la demande au serveur sous
    le nom du joueur du poste. Un appel distant qui ne vient pas du serveur, ou rejoué depuis la file JIP, y est refusé.
    Exemple de mission : if (isServer) then { ["add", [this, "TOC Bravo", "WEST"]] call comspec_atak_native_fnc_tocScreen; };
*/
params [["_mode", "tick"], ["_a", objNull]];
// Poste client : seul le serveur (poste 2) y entre par un appel distant, jamais un rejeu de la file JIP (même garde que
// fn_adminClient). En mode 2 (sans liste blanche), un autre client faisait jouer « add », « remove », « spawn » ou
// « view » au poste d'un Zeus, qui relayait la demande au serveur sous son nom (contre-expertise v5, N18). Le serveur,
// hôte compris, juge lui-même les demandes des clients plus bas.
if (!isServer && {isRemoteExecutedJIP || {isRemoteExecuted && {remoteExecutedOwner isNotEqualTo 2}}}) exitWith {
    diag_log format ["[COMSPEC ATAK NATIVE][WARN][TOC] Appel « %1 » refusé : il ne vient pas du serveur ou il est rejoué depuis la file JIP (poste %2, file JIP : %3)", _mode, remoteExecutedOwner, isRemoteExecutedJIP];
    false
};
private _fn = comspec_atak_native_fnc_tocScreen;
private _texW = 1600;
private _texH = 900;
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
// Lot mpauth3 (SEC-033) : état des écrans tenu par le serveur lui-même, jamais diffusé : localNamespace
// COMSPEC_ATAK_TocSrv ([[objet, état]...]) et COMSPEC_ATAK_TocMade (objets créés par « spawn »). Le registre public
// COMSPEC_ATAK_TocScreens et les variables publiques COMSPEC_ATAK_TocScr et COMSPEC_ATAK_TocOwned n'en sont que la copie
// pour l'affichage chez les joueurs : un client peut les écrire, le serveur ne les lit plus pour juger une demande,
// sauver (fn_tocPersist), recréer ou supprimer.
private _srvList = { (localNamespace getVariable ["COMSPEC_ATAK_TocSrv", []]) select { !isNull (_x select 0) } };
private _srvOf = {
    params ["_o"];
    private _l = call _srvList;
    private _i = _l findIf { (_x select 0) isEqualTo _o };
    if (_i < 0) then { [] } else { +((_l select _i) select 1) }
};
// État d'un écran écrit dans la copie du serveur ([] : écran retiré), puis publié avec le registre.
private _srvSet = {
    params ["_o", "_c"];
    private _l = (call _srvList) select { (_x select 0) isNotEqualTo _o };
    if (_c isNotEqualTo []) then { _l pushBack [_o, +_c]; };
    localNamespace setVariable ["COMSPEC_ATAK_TocSrv", _l];
    if (_c isEqualTo []) then { _o setVariable ["COMSPEC_ATAK_TocScr", nil, true]; } else { _o setVariable ["COMSPEC_ATAK_TocScr", +_c, true]; };
    missionNamespace setVariable ["COMSPEC_ATAK_TocScreens", _l apply { _x select 0 }, true];
};
// Écrans du jeu proposés à la pose (« classes », et « spawn » sans se ré-appeler).
private _classes = {
    ([
        ["Grand écran sur trépied", "Land_TripodScreen_01_large_F"],
        ["Grand écran sur trépied (noir)", "Land_TripodScreen_01_large_black_F"],
        ["Grand écran sur trépied (sable)", "Land_TripodScreen_01_large_sand_F"],
        ["Double écran sur trépied", "Land_TripodScreen_01_dual_v1_F"],
        ["TV à écran plat", "Land_FlatTV_01_F"],
        ["Écran de projection", "Land_ProjectorScreen_01_F"],
        ["Poste informatique", "Land_PCSet_01_screen_F"],
        ["Ordinateur portable", "Land_Laptop_unfolded_F"]
    ]) select { isClass (configFile >> "CfgVehicles" >> (_x select 1)) }
};
// Affichage d'un écran demandé par un joueur : écran que tient le serveur, joueur de son camp, à moins de 25 m (les
// actions de l'écran portent à 5 m).
private _viewer = {
    params ["_v", "_u"];
    if !(_v isEqualType []) exitWith { false };
    private _o = _v param [0, objNull];
    if (!(_o isEqualType objNull) || {isNull _o}) exitWith { false };
    private _s = ([_o] call _srvOf) param [1, -1];
    _s isEqualType "" && {_s isEqualTo "" || {_s isEqualTo str side group _u}} && {(_u distance _o) <= 25}
};
// Demande venue d'un client (remoteExecCall vers 2) : seul remoteExecutedOwner fait foi (fn_reqUnit). Plus d'événement
// CBA comspec_atak_native_tocScreen : il ne disait pas qui l'avait levé, et tout client faisait poser au serveur des
// écrans sans limite, désigner comme écran n'importe quel objet (recréé aux parties suivantes, quelle que soit sa
// classe), supprimer un objet marqué COMSPEC_ATAK_TocOwned ou changer l'affichage de tout écran (contre-expertise v4,
// N15). Poser, désigner, retirer : Zeus, hôte, admin connecté ou UID autorisé (fn_adminAllowed), comme le module Zeus.
// Hôte d'une partie locale et partie solo : appel non distant, rien ne change. Dans la pile d'une demande distante, le
// ré-appel « add » de « spawn » voit le même appel distant : il est jugé sur le même demandeur, déjà admis.
private _remote = isServer && {isMultiplayer} && {isRemoteExecuted} && {remoteExecutedOwner isNotEqualTo 2};
private _req = objNull;
if (_remote) then { _req = [remoteExecutedOwner] call comspec_atak_native_fnc_reqUnit; };
if (_remote && {!(_mode in ["add", "remove", "spawn", "view"])}) exitWith {
    diag_log format ["[COMSPEC ATAK NATIVE][WARN][TOC] Action « %1 » refusée au poste %2 (réservée au serveur)", _mode, remoteExecutedOwner];
    false
};
if (_remote && {isNull _req}) exitWith {
    diag_log format ["[COMSPEC ATAK NATIVE][WARN][TOC] Demande « %1 » refusée au poste %2 (demandeur introuvable)", _mode, remoteExecutedOwner];
    false
};
if (_remote && {!([_req] call comspec_atak_native_fnc_adminAllowed)} && {!(_mode isEqualTo "view" && {[_a, _req] call _viewer})}) exitWith {
    diag_log format ["[COMSPEC ATAK NATIVE][WARN][TOC] Demande « %1 » refusée à %2 (ni Zeus ni admin, ni joueur du camp près de l'écran)", _mode, name _req];
    false
};

switch (_mode) do {
    case "initServer": {
        if (!isServer) exitWith {};
        // Pas de gestionnaire d'événement CBA : les demandes des clients arrivent par remoteExecCall vers 2, seule voie
        // qui laisse le serveur reconnaître le poste émetteur (contrôle avant ce switch).
        ["init"] call comspec_atak_native_fnc_tocFeed;
        // Écrans gardés de la partie précédente : remis une fois les modules Eden passés.
        [{ ["restore"] call comspec_atak_native_fnc_tocPersist; }, [], 3] call CBA_fnc_waitAndExecute;
        true
    };
    case "classes": { call _classes };
    case "add": {
        _a params [["_o", objNull], ["_name", ""], ["_side", ""], ["_sel", 0], ["_view", "SPLIT"], ["_toc", true], ["_spawned", false]];
        if (isNull _o) exitWith { false };
        if (!isServer) exitWith { ["add", _a] remoteExecCall ["comspec_atak_native_fnc_tocScreen", 2]; true };
        if (_name isEqualTo "") then { _name = "TOC"; };
        private _old = [_o] call _srvOf;
        if !(_view in ["SPLIT", "MAP", "FEED", "TOC"]) then { _view = "SPLIT"; };
        [_o, [_name select [0, 32], toUpper _side, (round _sel) max 0, _view, _old param [4, 0], _old param [5, []], _toc, _spawned || {_old param [7, false]}]] call _srvSet;
        // Poste TOC : l'écran donne aussi l'ATAK du poste et l'app Poste TOC à qui s'en approche (fn_tocPost).
        _o setVariable ["COMSPEC_ATAK_Toc", _toc, true];
        _o setVariable ["COMSPEC_ATAK_TocSide", toUpper _side, true];
        ["save"] call comspec_atak_native_fnc_tocPersist;
        true
    };
    case "remove": {
        if (isNull _a) exitWith { false };
        if (!isServer) exitWith { ["remove", _a] remoteExecCall ["comspec_atak_native_fnc_tocScreen", 2]; true };
        private _made = (localNamespace getVariable ["COMSPEC_ATAK_TocMade", []]) select { !isNull _x };
        private _owned = _a in _made;
        localNamespace setVariable ["COMSPEC_ATAK_TocMade", _made - [_a]];
        [_a, []] call _srvSet;
        _a setVariable ["COMSPEC_ATAK_Toc", nil, true];
        // Écran créé par le module (ou recréé par la sauvegarde), d'après le serveur seul : l'objet part avec lui.
        if (_owned) then { deleteVehicle _a; };
        ["save"] call comspec_atak_native_fnc_tocPersist;
        true
    };
    case "spawn": {
        _a params [["_pos", [0, 0, 0]], ["_dir", 0], ["_cls", ""], ["_name", ""], ["_side", ""], ["_view", "SPLIT"], ["_toc", true]];
        if (!isServer) exitWith { ["spawn", _a] remoteExecCall ["comspec_atak_native_fnc_tocScreen", 2]; objNull };
        private _cands = (call _classes) apply { _x select 1 };
        if !(_cls in _cands) then { _cls = _cands param [0, ""]; };
        if (_cls isEqualTo "") exitWith {
            ["WARNING", "TOC", "Écran TOC : aucun écran du jeu disponible pour la pose."] call comspec_atak_native_fnc_log;
            objNull
        };
        private _o = createVehicle [_cls, _pos, [], 0, "CAN_COLLIDE"];
        _o setDir _dir;
        _o setPosATL _pos;
        _o setVariable ["COMSPEC_ATAK_TocOwned", true, true];
        localNamespace setVariable ["COMSPEC_ATAK_TocMade", ((localNamespace getVariable ["COMSPEC_ATAK_TocMade", []]) select { !isNull _x }) + [_o]];
        ["add", [_o, _name, _side, 0, _view, _toc, true]] call _fn;
        // Zeus : l'écran créé se manipule comme un objet posé à la main.
        { _x addCuratorEditableObjects [[_o], false]; } forEach allCurators;
        _o
    };
    case "view": {
        _a params [["_o", objNull], ["_k", ""], ["_v", 0]];
        if (isNull _o) exitWith { false };
        if (!isServer) exitWith { ["view", _a] remoteExecCall ["comspec_atak_native_fnc_tocScreen", 2]; true };
        private _c = [_o] call _srvOf;
        if ((count _c) < 8) exitWith { false };
        switch (_k) do {
            case "mode": { if (_v in ["SPLIT", "MAP", "FEED", "TOC"]) then { _c set [3, _v]; }; };
            case "zoom": { _c set [4, ((_v max 100) min (worldSize max 1000))]; };
            case "center": { if (_v isEqualType [] && {(count _v) >= 2}) then { _c set [5, [_v select 0, _v select 1]]; }; };
            case "auto": { _c set [4, 0]; _c set [5, []]; };
        };
        [_o, _c] call _srvSet;
        ["save"] call comspec_atak_native_fnc_tocPersist;
        true
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
            if (!isNull (_pend select 0)) then { (_pend select 0) setVariable ["COMSPEC_ATAK_TocTex", ""]; };
            _pend = [];
            uiNamespace setVariable ["COMSPEC_ATAK_TocPending", []];
        };
        private _cam = positionCameraToWorld [0, 0, 0];
        {
            private _o = _x;
            private _cfg = [_o] call _cfgOf;
            private _want = (count _cfg) >= 8 && {_o in _reg} && {alive _o} && {(_cam distance _o) < 150} && {[_o] call _sideOk};
            private _tex = _o getVariable ["COMSPEC_ATAK_TocTex", ""];
            if (_want) then {
                if !(_o getVariable ["COMSPEC_ATAK_TocAct", false]) then {
                    _o setVariable ["COMSPEC_ATAK_TocAct", true];
                    ["actions", _o] call _fn;
                };
                if (_tex isEqualTo "" && {(count _pend) isEqualTo 0}) then {
                    private _n = (uiNamespace getVariable ["COMSPEC_ATAK_TocSeq", 0]) + 1;
                    uiNamespace setVariable ["COMSPEC_ATAK_TocSeq", _n];
                    _tex = format ["comspec_atak_toc_%1", _n];
                    private _sel = _cfg select 2;
                    if (isNil { _o getVariable "COMSPEC_ATAK_TocOrig" }) then { _o setVariable ["COMSPEC_ATAK_TocOrig", (getObjectTextures _o) param [_sel, ""]]; };
                    _o setVariable ["COMSPEC_ATAK_TocTex", _tex];
                    _o setVariable ["COMSPEC_ATAK_TocSel", _sel];
                    _pend = [_o, diag_tickTime, _tex];
                    uiNamespace setVariable ["COMSPEC_ATAK_TocPending", _pend];
                    _o setObjectTexture [_sel, format ["#(rgb,%1,%2,1)ui(""COMSPEC_RscTocScreenTexture"",""%3"")", _texW, _texH, _tex]];
                    _local pushBackUnique _o;
                };
                private _disp = _disps getOrDefault [_tex, displayNull];
                if (!isNull _disp) then {
                    private _draws = _o getVariable ["COMSPEC_ATAK_TocDraws", 0];
                    private _due = diag_tickTime - (_o getVariable ["COMSPEC_ATAK_TocAt", -10]) >= 2;
                    // Toujours les premières images (sinon noir tant qu'on ne l'a pas regardé), puis seulement vu et proche.
                    private _seen = (_cam distance _o) < 80 && {(worldToScreen (ASLToAGL getPosASL _o)) isNotEqualTo []};
                    if (_due && {_draws < 3 || {_seen}}) then {
                        _o setVariable ["COMSPEC_ATAK_TocAt", diag_tickTime];
                        _o setVariable ["COMSPEC_ATAK_TocDraws", _draws + 1];
                        ["draw", [_o, _disp]] call _fn;
                        displayUpdate _disp;
                    };
                };
            } else {
                // Trop loin, retiré ou détruit : texture d'origine remise, display abandonné.
                if (_tex isNotEqualTo "") then {
                    _o setObjectTexture [_o getVariable ["COMSPEC_ATAK_TocSel", 0], _o getVariable ["COMSPEC_ATAK_TocOrig", ""]];
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
            _title ctrlSetPosition [0.012, 0.012, 0.6, 0.06]; _title ctrlCommit 0;
            private _clock = ["COMSPEC_RscStructuredText"] call _mk;
            _clock ctrlSetPosition [0.5, 0.012, 0.488, 0.06]; _clock ctrlCommit 0;
            private _grp = ["RscControlsGroupNoScrollbars"] call _mk;
            private _sea = ["RscText", _grp] call _mk;
            _sea ctrlSetBackgroundColor [0.16, 0.22, 0.28, 1];
            private _tiles = []; for "_i" from 1 to 36 do { _tiles pushBack (["RscPicture", _grp] call _mk); };
            private _lines = []; for "_i" from 1 to 28 do { private _l = ["RscText", _grp] call _mk; _l ctrlSetBackgroundColor [0, 0, 0, 0.28]; _lines pushBack _l; };
            private _mkIcons = []; private _mkLbls = [];
            for "_i" from 1 to 40 do { _mkIcons pushBack (["RscPicture", _grp] call _mk); private _t = ["RscText", _grp] call _mk; _t ctrlSetFontHeight 0.024; _t ctrlSetFont "RobotoCondensedBold"; _t ctrlSetTextColor [0.05, 0.05, 0.05, 1]; _mkLbls pushBack _t; };
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
            _ui = createHashMapFromArray [["accent", _accent], ["title", _title], ["clock", _clock], ["grp", _grp], ["sea", _sea], ["tiles", _tiles], ["lines", _lines],
                ["mkIcons", _mkIcons], ["mkLbls", _mkLbls], ["evIcons", _evIcons], ["uIcons", _uIcons], ["uLbls", _uLbls], ["scale", _scale], ["north", _north], ["empty", _empty], ["panel", _panel]];
            _d setVariable ["COMSPEC_ATAK_TocUi", _ui];
        };
        (_ui get "accent") ctrlSetBackgroundColor (_sideRgb + [1]);
        private _age = [-1, round ((serverTime - _ft) max 0)] select (_ft >= 0);
        (_ui get "title") ctrlSetStructuredText parseText (((format ["<t size='1.9' font='COMSPEC_BarlowBold'>TOC · %1</t>   <t size='1.5' color='#8a9a93'>%2 · réseau ATAK</t>", [_name] call _esc, _sideName])) call comspec_atak_native_fnc_tr);
        if ((count _op) >= 8) then {
            (_ui get "title") ctrlSetStructuredText parseText (((format ["<t size='1.9' font='COMSPEC_BarlowBold'>TOC · %1</t>   <t size='1.5' color='#e0b040'>PIP du poste · %2</t>   <t size='1.5' color='#8a9a93'>opérateur %3 · %4</t>",
                [_op param [5, _name]] call _esc, [_sideName] call _esc, [_op param [6, ""]] call _esc, [_op param [3, ""]] call _esc])) call comspec_atak_native_fnc_tr);
        };
        private _live = [["<t color='#e05a4a'>● HORS LIGNE</t>", "<t color='#e0b040'>● RETARD</t>"] select (_age >= 0), "<t color='#5cc76b'>● EN DIRECT</t>"] select (_age >= 0 && {_age < 12});
        (_ui get "clock") ctrlSetStructuredText parseText (((format ["<t align='right' size='1.5'>%1   <t color='#8a9a93'>%2</t>   %3</t>",
            [dayTime, "HH:MM"] call BIS_fnc_timeToString, [format ["données il y a %1 s", _age], "aucune donnée du serveur"] select (_age < 0), _live])) call comspec_atak_native_fnc_tr);

        // Mise en page selon l'affichage : carte + flux, carte seule, flux seul.
        private _top = 0.08;
        private _mapR = switch (_view) do { case "MAP": { [0, _top, 1, 1 - _top] }; case "FEED": { [0, 0, 0, 0] }; default { [0, _top, 0.64, 1 - _top] }; };
        private _panR = switch (_view) do { case "MAP": { [0, 0, 0, 0] }; case "FEED": { [0.015, _top + 0.01, 0.97, 0.9 - _top] }; default { [0.648, _top + 0.008, 0.344, 0.984 - _top] }; };
        // PIP : cadrage de la carte imposé par le PC de l'opérateur.
        if ((count _op) >= 8) then { _center = _op select 1; _half = _op select 2; };
        private _grp = _ui get "grp";
        _grp ctrlSetPosition _mapR; _grp ctrlCommit 0;
        _grp ctrlShow (_view isNotEqualTo "FEED");
        (_ui get "panel") ctrlSetPosition _panR; (_ui get "panel") ctrlCommit 0;
        (_ui get "panel") ctrlShow (_view isNotEqualTo "MAP");

        // ---- Carte -------------------------------------------------------------------------------------------
        if (_view isNotEqualTo "FEED") then {
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
            // Marqueurs de la mission visibles par ce joueur (icônes seulement).
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
                        if (_icon isNotEqualTo "") then {
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
                            _t ctrlSetPosition [_gx + 15 / _texW, _gy - 12 / _texH, ((count _txt) * 11 + 8) / _texW, 24 / _texH]; _t ctrlCommit 0; _t ctrlShow (_txt isNotEqualTo "");
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
                _x params ["", "_cs", "_p", "_dir", "_fr", "_kind", "_ai", "", ["_n", 1]];
                if (_ui0 < 100 && {[_p select 0, _p select 1] call _inView}) then {
                    ([_p select 0, _p select 1] call _toG) params ["_gx", "_gy"];
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
                    private _lbl = _cs + (["", format [" +%1", _n - 1]] select (_n > 1)) + (["", " · IA"] select _ai) + (["", " · ancien", " · perdu", " · hors ligne"] select (_fr min 3));
                    _t ctrlSetText ((_lbl) call comspec_atak_native_fnc_tr);
                    // PIP : le groupe choisi par l'opérateur ressort en jaune.
                    private _picked = (count _op) >= 5 && {(_op param [3, ""]) isEqualTo "GROUPES"} && {(_op param [4, ""]) isNotEqualTo ""} && {(_x param [7, ""]) isEqualTo (_op select 4)};
                    _t ctrlSetTextColor ([[[1, 1, 1, 1], [0.8, 0.8, 0.8, 1]] select (_fr >= 2), [1, 0.85, 0.3, 1]] select _picked);
                    if (_picked) then { _c ctrlSetTextColor [1, 0.85, 0.3, 1]; };
                    _t ctrlSetPosition [_gx + 20 / _texW, _gy - 13 / _texH, ((count _lbl) * 12 + 10) / _texW, 26 / _texH]; _t ctrlCommit 0; _t ctrlShow true;
                    _ui0 = _ui0 + 1;
                };
            } forEach _nodes;
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
                    _t ctrlSetPosition [_gx + 22 / _texW, _gy - 13 / _texH, 120 / _texW, 26 / _texH]; _t ctrlCommit 0; _t ctrlShow true;
                    _ui0 = _ui0 + 1;
                };
            };
            for "_i" from _ui0 to 99 do { (_uIcons select _i) ctrlShow false; (_uLbls select _i) ctrlShow false; };
            private _sc = _ui get "scale";
            _sc ctrlSetText (((format ["Carreaux de %1 · largeur %2", [format ["%1 m", _step], format ["%1 km", _step / 1000]] select (_step >= 1000), [format ["%1 m", round (2 * _hw)], format ["%1 km", (round (2 * _hw / 100)) / 10]] select (_hw >= 500)])) call comspec_atak_native_fnc_tr);
            _sc ctrlSetPosition [10 / _texW, _mh - 36 / _texH, 360 / _texW, 30 / _texH]; _sc ctrlCommit 0;
            private _no = _ui get "north";
            _no ctrlSetPosition [_mw - 70 / _texW, 10 / _texH, 60 / _texW, 44 / _texH]; _no ctrlCommit 0;
            private _em = _ui get "empty";
            _em ctrlShow ((count _nodes) isEqualTo 0);
            _em ctrlSetStructuredText parseText (((("<t align='center' size='1.6' color='#cfd8d3'>Aucun nœud ATAK de ce camp sur le réseau</t>"))) call comspec_atak_native_fnc_tr);
            _em ctrlSetPosition [0, _mh / 2 - 0.03, _mw, 0.06]; _em ctrlCommit 0;
        };

        // ---- Panneau : état du réseau, groupes, flux ----------------------------------------------------------
        if (_view isNotEqualTo "MAP") then {
            private _cnt = [0, 0, 0, 0];
            { private _f = (_x select 4) min 3; _cnt set [_f, (_cnt select _f) + (_x param [8, 1])]; } forEach _nodes;
            _stats params [["_jam", 0], ["_relays", 0], ["_players", 0], ["_ais", 0]];
            private _s = "";
            // PIP : ce que l'opérateur a sous les yeux sur le PC du poste.
            if ((count _op) >= 8) then {
                _s = _s + format ["<t size='1.45' font='COMSPEC_BarlowBold' color='#e0b040'>PIP DU POSTE</t><br/><t size='1.2'>Poste %1 · opérateur %2<br/>Onglet %3 %4</t><br/>%5<br/>",
                    [_op param [5, ""]] call _esc, [_op param [6, ""]] call _esc, [_op param [3, ""]] call _esc,
                    ["", format [" · %1", [_op param [4, ""]] call _esc]] select ((_op param [4, ""]) isNotEqualTo "" && {(_op param [3, ""]) isEqualTo "GROUPES"}),
                    ["", format ["<t size='1.2' color='#ffd47f'>Ordre en préparation : %1</t><br/>", [_op param [7, ""]] call _esc]] select ((_op param [7, ""]) isNotEqualTo "")];
            };
            _s = _s + format ["<t size='1.45' font='COMSPEC_BarlowBold'>RÉSEAU</t><br/><t size='1.25'><t color='#5cc76b'>%1 en ligne</t> · <t color='#e0b040'>%2 anciens</t> · <t color='#9aa39f'>%3 perdus</t> · <t color='#6f7773'>%4 hors ligne</t></t><br/><t size='1.1' color='#8a9a93'>%5 joueurs · %6 IA · %7 relais · %8 brouilleurs actifs</t><br/><br/>",
                _cnt select 0, _cnt select 1, _cnt select 2, _cnt select 3, _players, _ais, _relays, _jam];
            // Groupes : nœuds par groupe, grille de leur premier nœud.
            private _gm = createHashMap;
            {
                private _g = _x param [7, ""];
                private _e = _gm getOrDefault [_g, [0, _x select 2, 9]];
                _gm set [_g, [(_e select 0) + (_x param [8, 1]), _e select 1, (_e select 2) min (_x select 4)]];
            } forEach _nodes;
            private _gk = keys _gm;
            _gk sort true;
            private _gMax = [8, 14] select (_view isEqualTo "FEED");
            _s = _s + format ["<t size='1.45' font='COMSPEC_BarlowBold'>GROUPES</t> <t size='1.1' color='#8a9a93'>%1</t><br/>", count _gk];
            {
                (_gm get _x) params ["_n", "_p", "_best"];
                _s = _s + format ["<t size='1.2'><t color='%4'>■</t> %1 · %2 nœud(s) · grille %3</t><br/>", [_x] call _esc, _n, [[_p select 0, _p select 1, 0], 6] call comspec_atak_native_fnc_gridRef, ["#5cc76b", "#e0b040", "#9aa39f", "#6f7773"] select (_best min 3)];
            } forEach (_gk select [0, _gMax]);
            if ((count _gk) > _gMax) then { _s = _s + format ["<t size='1.1' color='#8a9a93'>et %1 autre(s)</t><br/>", (count _gk) - _gMax]; };
            private _eMax = [9, 16] select (_view isEqualTo "FEED");
            _s = _s + "<br/><t size='1.45' font='COMSPEC_BarlowBold'>FLUX</t><br/>";
            if ((count _events) isEqualTo 0) then { _s = _s + "<t size='1.15' color='#8a9a93'>Aucun compte rendu ni message pour l'instant</t>"; };
            private _ev = _events select [0 max ((count _events) - _eMax)];
            reverse _ev;
            {
                _x params [["_time", ""], ["_kind", ""], ["_prio", ""], ["_who", ""], ["_grid", ""], ["_text", ""], "", "", ["_old", false]];
                private _pc = switch (toUpper _prio) do { case "FLASH": { "#ff5a4a" }; case "URGENT": { "#e0b040" }; default { "#cfd8d3" }; };
                _s = _s + format ["<t size='1.15'><t color='#8a9a93'>%1</t> <t color='%7' font='COMSPEC_BarlowBold'>%2</t>%3 · %4%5%6</t><br/>",
                    _time, [_kind] call _esc, ["", format [" <t color='%2'>%1</t>", _prio, _pc]] select (_prio isNotEqualTo "" && {(toUpper _prio) isNotEqualTo "ROUTINE"}),
                    [_who] call _esc, ["", format [" · %1", _grid]] select (_grid isNotEqualTo ""), ["", format ["<br/>   <t color='#cfd8d3'>%1</t>", [_text select [0, [90, 160] select (_view isEqualTo "FEED")]] call _esc]] select (_text isNotEqualTo ""),
                    ["#7fd0b0", "#6f7773"] select _old];
            } forEach _ev;
            (_ui get "panel") ctrlSetStructuredText parseText (((_s)) call comspec_atak_native_fnc_tr);
        };
        true
    };
    case "actions": {
        private _o = _a;
        // Affichage, cadrage et carte en grand : tout joueur du camp de l'écran, à 5 m.
        private _do = {
            params ["_o", "_k", ["_v", 0]];
            private _shown = _o getVariable ["COMSPEC_ATAK_TocShown", [getPosASL _o, 1500]];
            switch (_k) do {
                case "cycle": {
                    private _m = (_o getVariable ["COMSPEC_ATAK_TocScr", []]) param [3, "SPLIT"];
                    private _modes = ["SPLIT", "MAP", "FEED", "TOC"];
                    private _next = _modes select ((((_modes find _m) + 1) max 0) mod 4);
                    ["view", [_o, "mode", _next]] call comspec_atak_native_fnc_tocScreen;
                    ["INFO", format ["Écran TOC : %1", (createHashMapFromArray [["SPLIT", "carte et flux"], ["MAP", "carte seule"], ["FEED", "flux seul"], ["TOC", "PIP du poste (ce que voit l'opérateur)"]]) get _next], 2, 20] call comspec_atak_native_fnc_notify;
                };
                case "in": {
                    ["view", [_o, "center", _shown select 0]] call comspec_atak_native_fnc_tocScreen;
                    ["view", [_o, "zoom", (_shown select 1) / 2]] call comspec_atak_native_fnc_tocScreen;
                };
                case "out": {
                    ["view", [_o, "center", _shown select 0]] call comspec_atak_native_fnc_tocScreen;
                    ["view", [_o, "zoom", (_shown select 1) * 2]] call comspec_atak_native_fnc_tocScreen;
                };
                case "me": {
                    ["view", [_o, "center", getPosASL player]] call comspec_atak_native_fnc_tocScreen;
                    ["view", [_o, "zoom", _shown select 1]] call comspec_atak_native_fnc_tocScreen;
                };
                case "auto": { ["view", [_o, "auto"]] call comspec_atak_native_fnc_tocScreen; };
                case "big": {
                    // Écran du PC du poste (fn_tocLaptop) : carte en grand, groupes, flux et ordres, sans téléphone.
                    ["open", _o] call comspec_atak_native_fnc_tocPost;
                };
            };
            _o setVariable ["COMSPEC_ATAK_TocAt", -10];
        };
        missionNamespace setVariable ["COMSPEC_ATAK_TocDo", _do];
        private _cond = "private _o = _target; private _c = _o getVariable ['COMSPEC_ATAK_TocScr', []]; (count _c) >= 8 && {(_c select 1) isEqualTo '' || {(_c select 1) isEqualTo str side group player}}";
        if (!isNil "ace_interact_menu_fnc_createAction") then {
            private _icon = "\z\comspec_atak_native\addons\main\data\app_toc.paa";
            private _root = ["COMSPEC_ATAK_TocScreen", "Écran TOC", _icon, {}, { private _c = _target getVariable ["COMSPEC_ATAK_TocScr", []]; (count _c) >= 8 && {(_c select 1) isEqualTo "" || {(_c select 1) isEqualTo str side group _player}} }, {
                private _out = [];
                {
                    _x params ["_id", "_lbl", "_k"];
                    _out pushBack [[_id, _lbl, "", compile format ["[_target, %1] call COMSPEC_ATAK_TocDo;", str _k], { true }] call comspec_atak_native_fnc_aceAction, [], _target];
                } forEach [
                    ["COMSPEC_ATAK_TocCycle", "Changer d'affichage (carte et flux, carte, flux, PIP du poste)", "cycle"],
                    ["COMSPEC_ATAK_TocIn", "Zoomer", "in"],
                    ["COMSPEC_ATAK_TocOut", "Dézoomer", "out"],
                    ["COMSPEC_ATAK_TocMe", "Centrer sur ma position", "me"],
                    ["COMSPEC_ATAK_TocAuto", "Cadrer tout le réseau", "auto"]
                ];
                if (_target getVariable ["COMSPEC_ATAK_Toc", false]) then {
                    _out pushBack [["COMSPEC_ATAK_TocBig", "Ouvrir le PC du poste (carte en grand)", "", { [_target, "big"] call COMSPEC_ATAK_TocDo; }, { isNull objectParent _player }] call comspec_atak_native_fnc_aceAction, [], _target];
                };
                _out
            }, [], [0, 0, 0], 5] call comspec_atak_native_fnc_aceAction;
            [_o, 0, [], _root] call ace_interact_menu_fnc_addActionToObject;
        } else {
            {
                _x params ["_lbl", "_k", "_prio"];
                _o addAction [format ["<t color='#7fd0b0'>%1</t>", _lbl call comspec_atak_native_fnc_tr], { [_this select 0, _this select 3] call COMSPEC_ATAK_TocDo; }, _k, _prio, false, true, "",
                    _cond + ([" ", " && {_o getVariable ['COMSPEC_ATAK_Toc', false]}"] select (_k isEqualTo "big")), 5];
            } forEach [
                ["Écran TOC : changer d'affichage", "cycle", 1.45], ["Écran TOC : zoomer", "in", 1.44], ["Écran TOC : dézoomer", "out", 1.43],
                ["Écran TOC : cadrer tout le réseau", "auto", 1.42], ["Écran TOC : ouvrir le PC du poste", "big", 1.41]
            ];
        };
        true
    };
    default { false };
};
