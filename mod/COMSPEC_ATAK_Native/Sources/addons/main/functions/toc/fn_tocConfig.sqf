/*
    Écrans TOC : qui tient les écrans (serveur ou joueurs), réglage unique d'un écran (Eden et Zeus), ouverture du PC.
    Retour de TTRD du 11/11 (4.5.0, serveur dédié plus ancien que les joueurs) : écran noir, « L'écran n'a pas répondu »,
    pas d'action « Ouvrir le PC du poste », rien pour régler un écran dans Eden ni dans Zeus.

    Serveur ou joueurs. Le serveur publie sa version (COMSPEC_ATAK_ServerVersion, depuis la 4.4.1). Version absente ou
    plus ancienne que celle du joueur (MAJEUR.MINEUR) : repli, le joueur désigne l'écran lui-même, par les mêmes
    variables publiques de l'objet (COMSPEC_ATAK_TocScr…), que tous les joueurs qui ont le mod dessinent chacun chez
    eux. Même version mais demande restée sans effet 3 s (objet inconnu du serveur) : repli pour cet objet seulement
    (COMSPEC_ATAK_TocFb). En repli, rien n'est gardé par le serveur d'une partie à l'autre, et le message le dit avec
    les deux versions. Essai sur un serveur à jour : missionNamespace COMSPEC_ATAK_TocForceFallback = true.

    Réglage d'un écran, même tableau pour Eden (attributs de l'objet) et Zeus (fenêtre « Configurer l'écran ») :
      [nom, camp, affichage, poste TOC, satellite (0 | 1), escouade, caméra ("" | netId | "CAM:nom" | "UAV:classe"),
       mosaïque [grille 2 | 3, groupe ("" : tout le camp)], éteint, luminosité (25 à 100), distance d'affichage en m
       (0 : réglage de chaque joueur), salle (identifiant ou nom, "" : aucune), dalles du mur (« SAT1, CLOCK, SQ:Alpha 1-1 »)]
    Variables publiques ajoutées sur l'écran : COMSPEC_ATAK_TocOpt [luminosité, distance], COMSPEC_ATAK_TocFb (géré par
    les joueurs), COMSPEC_ATAK_TocEd (attributs Eden déjà appliqués).

    Modes :
      ["mode"]                     -> [ "server" | "fallback", version du serveur ("" : inconnue), ma version, réponse du serveur ? ]
      ["local", objet]             -> true si ce poste doit faire lui-même la désignation (serveur, repli, objet en repli)
      ["reason"]                   -> « Le serveur n'a pas la même version du mod (serveur X, vous Y) »
      ["ack", [objet, "toc" | "cast", arguments de « add »]]  client : demande partie au serveur, contrôle 3 s plus tard
      ["initServer"] / ["initClient"]   demande de version (journal à la connexion, une ligne)
      ["keys"]                     -> noms des champs du réglage, dans l'ordre
      ["make", valeurs]            -> réglage normalisé (textes d'Eden, choix de la fenêtre Zeus)
      ["read", objet]              -> réglage actuel de l'écran
      ["apply", [objet, réglage]]  applique le réglage (demandes au serveur, ou sur ce poste en repli)
      ["eden", [objet, usage]]     expression de l'attribut Eden « Usage de l'écran » (toutes les machines)
      ["viewList", objet]          -> [[libellé, clé]...] affichages possibles pour cet écran (liste de fn_tocScreen)
      ["isScreen", objet]          -> écran COMSPEC, écran déclaré (CfgComspecTocScreens) ou objet déjà désigné
      ["zeus", [objet, message, action, brouillon]]   fenêtre « Configurer l'écran » (ZEN s'il est là, sinon fn_zeusForm)
      ["openPc", objet]            ouvre le PC du poste (objet nul : poste le plus proche, sinon sans objet)
      ["acePos", …]                position du menu ACE : centre de l'écran (modèles sans point mémoire)
      ["dim", [objet, display]]    luminosité : voile sombre au-dessus de l'image (fn_tocScreen « tick »)
      ["selfTest"]                 -> [réussis, échecs] : contrôle du code sans effet (console de débogage)
*/
params [["_op", ""], ["_a", []]];
disableSerialization;
private _fn = comspec_atak_native_fnc_tocConfig;
private _ts = comspec_atak_native_fnc_tocScreen;
private _keys = ["name", "side", "view", "toc", "sat", "squad", "cam", "mos", "off", "bright", "range", "hall", "panels"];
private _short = {
    private _p = ["parse", _this] call comspec_atak_native_fnc_verCmp;
    if (_p isEqualTo []) exitWith { "" };
    (_p apply { str _x }) joinString "."
};
private _hostOf = { params ["_o"]; private _h = _o getVariable ["COMSPEC_ATAK_TocHost", objNull]; [_o, _h] select (!isNull _h) };
private _has = { params ["_o"]; (count (_o getVariable ["COMSPEC_ATAK_TocScr", []])) >= 8 };
private _sides = [["Celui de chaque joueur", ""], ["BLUFOR", "WEST"], ["OPFOR", "EAST"], ["Indépendant", "GUER"], ["Civil", "CIV"]];

switch (_op) do {
    case "keys": { +_keys };
    case "mode": {
        private _my = (missionNamespace getVariable ["COMSPEC_ATAK_NativeVersion", ""]) call _short;
        if (isServer) exitWith { ["server", _my, _my, true] };
        private _srv = (missionNamespace getVariable ["COMSPEC_ATAK_ServerVersion", ""]) call _short;
        private _pong = (missionNamespace getVariable ["COMSPEC_ATAK_TocSrvPong", ""]) isNotEqualTo "";
        private _s = ["parse", _srv] call comspec_atak_native_fnc_verCmp;
        private _m = ["parse", _my] call comspec_atak_native_fnc_verCmp;
        private _old = _s isEqualTo [] || {_m isNotEqualTo [] && {([[_s select 0, _s select 1, 0], [_m select 0, _m select 1, 0]] call comspec_atak_native_fnc_verCmp) isEqualTo -1}};
        if (missionNamespace getVariable ["COMSPEC_ATAK_TocForceFallback", false]) then { _old = true; };
        [["server", "fallback"] select _old, _srv, _my, _pong]
    };
    case "reason": {
        (["mode"] call _fn) params ["", "_srv", "_my"];
        format [("Le serveur n'a pas la même version du mod (serveur %1, vous %2)") call comspec_atak_native_fnc_tr,
            [_srv, ("inconnue") call comspec_atak_native_fnc_tr] select (_srv isEqualTo ""), _my]
    };
    case "local": {
        private _o = _a;
        if (isServer) exitWith { true };
        if !(_o isEqualType objNull) then { _o = objNull; };
        private _fb = ((["mode"] call _fn) select 0) isEqualTo "fallback";
        if (!_fb && {isNull _o || {!(([_o] call _hostOf) getVariable ["COMSPEC_ATAK_TocFb", false])}}) exitWith { false };
        if (!isNull _o && {!(_o getVariable ["COMSPEC_ATAK_TocFb", false])}) then { _o setVariable ["COMSPEC_ATAK_TocFb", true, true]; };
        // La raison est dite une fois toutes les 5 min au plus (téléphone et journal), jamais une fenêtre du jeu.
        if (hasInterface && {diag_tickTime - (uiNamespace getVariable ["COMSPEC_ATAK_TocFbSaid", -1e9]) > 300}) then {
            uiNamespace setVariable ["COMSPEC_ATAK_TocFbSaid", diag_tickTime];
            private _why = ["reason"] call _fn;
            ["WARNING", "TOC", format ["Écran TOC géré par ce poste : %1. Rien n'est gardé par le serveur d'une partie à l'autre.", _why]] call comspec_atak_native_fnc_log;
            ["WARNING", format ["%1. %2", _why, ("Serveur ancien : écran géré par les joueurs, non sauvegardé par le serveur") call comspec_atak_native_fnc_tr], 8, 30] call comspec_atak_native_fnc_notify;
        };
        true
    };
    case "ack": {
        // La demande est partie au serveur : désignation attendue en moins d'une seconde. Toujours rien après 3 s
        // (objet inconnu du serveur, gestionnaire absent) : ce poste désigne l'écran lui-même et dit pourquoi.
        _a params [["_o", objNull], ["_kind", "toc"], ["_args", []]];
        if (isServer || {isNull _o} || {!hasInterface}) exitWith { false };
        private _flag = "COMSPEC_ATAK_TocAck_" + _kind;
        if (_o getVariable [_flag, false]) exitWith { false };
        _o setVariable [_flag, true];
        [{
            params ["_o", "_kind", "_args", "_flag"];
            if (isNull _o) exitWith {};
            _o setVariable [_flag, false];
            private _done = if (_kind isEqualTo "cast") then { (count (_o getVariable ["COMSPEC_ATAK_Screen", []])) >= 5 } else { (count (_o getVariable ["COMSPEC_ATAK_TocScr", []])) >= 8 };
            if (_done) exitWith {};
            _o setVariable ["COMSPEC_ATAK_TocFb", true, true];
            ["WARNING", "TOC", format ["Écran %1 : le serveur n'a pas désigné l'écran en 3 s (%2), désignation par ce poste", typeOf _o, ["reason"] call comspec_atak_native_fnc_tocConfig]] call comspec_atak_native_fnc_log;
            if (_kind isEqualTo "cast") then { ["add", _args] call comspec_atak_native_fnc_screenCast; } else { ["add", _args] call comspec_atak_native_fnc_tocScreen; };
        }, [_o, _kind, _args, _flag], 3] call comspec_atak_native_fnc_waitAndExec;
        true
    };
    case "initServer": {
        if (!isServer) exitWith { false };
        ["comspec_atak_native_tocPing", {
            params [["_p", objNull]];
            if (_p isEqualType objNull && {!isNull _p}) then { ["comspec_atak_native_tocPong", [missionNamespace getVariable ["COMSPEC_ATAK_NativeVersion", ""]], _p] call CBA_fnc_targetEvent; };
        }] call CBA_fnc_addEventHandler;
        true
    };
    case "initClient": {
        if (!hasInterface) exitWith { false };
        ["comspec_atak_native_tocPong", { missionNamespace setVariable ["COMSPEC_ATAK_TocSrvPong", _this param [0, "?"]]; }] call CBA_fnc_addEventHandler;
        // Demande envoyée une fois l'unité du joueur en place (la réponse lui est adressée).
        [{ !isNull player }, {
            ["comspec_atak_native_tocPing", [player]] call CBA_fnc_serverEvent;
            [{
                (["mode"] call comspec_atak_native_fnc_tocConfig) params ["_mode", "_srv", "_my", "_pong"];
                private _funcs = switch (true) do {
                    case (isServer): { "disponibles (ce poste est le serveur)" };
                    case (_pong): { "disponibles (le serveur a répondu)" };
                    case (_srv isEqualTo ""): { "absentes (aucune version publiée par le serveur, aucune réponse)" };
                    default { "non confirmées (aucune réponse à la demande de version : serveur d'avant cette mise à jour)" };
                };
                ["INFO", "TOC", format ["Connexion : ATAK du serveur %1 · ATAK de ce poste %2 · fonctions TOC du serveur %3 · écrans TOC %4",
                    [_srv, "inconnu"] select (_srv isEqualTo ""), _my, _funcs,
                    ["tenus par le serveur", "gérés par les joueurs (non sauvegardés par le serveur)"] select (_mode isEqualTo "fallback")]] call comspec_atak_native_fnc_log;
            }, [], 6] call comspec_atak_native_fnc_waitAndExec;
        }] call CBA_fnc_waitUntilAndExecute;
        true
    };
    case "isScreen": {
        private _o = _a;
        if !(_o isEqualType objNull) exitWith { false };
        if (isNull _o) exitWith { false };
        private _cfg = configOf _o;
        if ((getNumber (_cfg >> "comspec_screen")) > 0 || {(getNumber (_cfg >> "comspec_projBoard")) > 0} || {[_o] call _has} || {(count (_o getVariable ["COMSPEC_ATAK_Screen", []])) >= 5}) exitWith { true };
        // Écran du jeu ou d'un autre mod : sa classe ou une classe parente déclarée dans CfgComspecTocScreens.
        private _found = false;
        for "_i" from 0 to 8 do {
            if (isNull _cfg || {_found}) exitWith {};
            if (isClass (configFile >> "CfgComspecTocScreens" >> configName _cfg)) then { _found = true; } else { _cfg = inheritsFrom _cfg; };
        };
        _found
    };
    case "viewList": {
        // Liste de fn_tocScreen (« views ») : un affichage ajouté là-bas apparaît ici sans rien changer.
        private _o = _a;
        // Affichages permis par le format de la dalle (fn_tocScreen « format ») : 16:9, bandeau 4:1, bandeau LED 8:1 et
        // 16:1 (texte seulement), colonne d'un écran incurvé. Sans objet : ceux d'un écran 16:9.
        if !(_o isEqualType objNull) then { _o = objNull; };
        private _okViews = (["format", _o] call _ts) param [4, []];
        private _out = [];
        {
            if (_x in _okViews) then {
                private _l = ((["title", _x] call _ts) param [1, toLower _x]) call comspec_atak_native_fnc_tr;
                private _n = (toArray _l) param [0, 0];
                if (_n >= 97 && {_n <= 122}) then { _l = (toUpper (_l select [0, 1])) + (_l select [1]); };
                _out pushBack [_l, _x];
            };
        } forEach (["views"] call _ts);
        _out
    };
    case "make": {
        // Valeurs dans l'ordre de « keys », venues d'Eden (textes) ou de la fenêtre Zeus : types et bornes remis.
        private _v = +_a;
        if !(_v isEqualType []) then { _v = []; };
        _v params [["_name", ""], ["_side", ""], ["_view", "SPLIT"], ["_toc", true], ["_sat", 0], ["_squad", ""], ["_cam", ""], ["_mos", []], ["_off", false],
            ["_bright", 100], ["_range", 0], ["_hall", ""], ["_pans", ""]];
        private _num = { params ["_n", "_def"]; if (_n isEqualType "") then { _n = parseNumber _n; }; if !(_n isEqualType 0) then { _n = _def; }; _n };
        private _txt = { params ["_t", "_max"]; if !(_t isEqualType "") then { _t = ""; }; (trim _t) select [0, _max] };
        _side = toUpper ([_side, 8] call _txt);
        _side = (createHashMapFromArray [["BLUFOR", "WEST"], ["BLU", "WEST"], ["OPFOR", "EAST"], ["RED", "EAST"], ["INDEP", "GUER"], ["INDEPENDENT", "GUER"], ["RESISTANCE", "GUER"], ["IND", "GUER"], ["CIVILIAN", "CIV"]]) getOrDefault [_side, _side];
        if !(_side in ["", "WEST", "EAST", "GUER", "CIV"]) then { _side = ""; };
        _view = toUpper ([_view, 16] call _txt);
        if !(_off isEqualType true) then { _off = false; };
        // « Éteint » se choisit dans la liste des affichages : l'écran garde carte et flux, en veille.
        if (_view isEqualTo "OFF") then { _off = true; _view = "SPLIT"; };
        if !(_view in (["views"] call _ts)) then { _view = "SPLIT"; };
        if !(_toc isEqualType true) then { _toc = true; };
        if !(_mos isEqualType []) then { _mos = []; };
        private _grid = [[_mos param [0, 2], 2] call _num, 2] select (_mos isEqualTo []);
        _bright = (round ((([_bright, 100] call _num) max 25) min 100));
        _range = round ([_range, 0] call _num);
        if (_range > 0) then { _range = (_range max 5) min 150; } else { _range = 0; };
        [[_name, 32] call _txt, _side, _view, _toc, [0, 1] select (([_sat, 0] call _num) >= 1), [_squad, 40] call _txt, [_cam, 60] call _txt,
            [[2, 3] select (_grid >= 3), [_mos param [1, ""], 40] call _txt], _off, _bright, _range, [_hall, 32] call _txt, [_pans, 200] call _txt]
    };
    case "panelStr": {
        // Contenu actuel des dalles 2 et suivantes d'un mur, dans l'écriture du champ « Dalles du mur ».
        private _h = _a;
        private _rel = (_h getVariable ["COMSPEC_ATAK_TocPanels", []]) select { !isNull _x };
        private _rows = _rel apply {
            private _c = _x getVariable ["COMSPEC_ATAK_TocScr", []];
            private _v = _c param [3, "SPLIT"];
            private _tok = switch (true) do {
                case (_x getVariable ["COMSPEC_ATAK_TocOff", false]): { "OFF" };
                case (_v isEqualTo "SAT"): { ["SAT1", "SAT2"] select ((_c param [8, 0]) isEqualTo 1) };
                case (_v isEqualTo "SQUAD"): { "SQ:" + (_c param [9, ""]) };
                case (_v isEqualTo "MOSAIC"): { ["MOS2", "MOS3"] select (((_x getVariable ["COMSPEC_ATAK_TocMosaic", []]) param [0, 2]) isEqualTo 3) };
                default { _v };
            };
            [_x getVariable ["COMSPEC_ATAK_TocPanel", 0], _tok]
        };
        _rows sort true;
        (_rows apply { _x select 1 }) joinString ", "
    };
    case "read": {
        private _o = _a;
        if !(_o isEqualType objNull) exitWith { ["make", []] call _fn };
        private _c = _o getVariable ["COMSPEC_ATAK_TocScr", []];
        private _nm = _c param [0, getText (configOf _o >> "displayName")];
        private _m = _o getVariable ["COMSPEC_ATAK_TocMosaic", []];
        private _opt = ([_o] call _hostOf) getVariable ["COMSPEC_ATAK_TocOpt", []];
        ["make", [_nm, _c param [1, ""], _c param [3, "SPLIT"], _c param [6, true], _c param [8, 0], _c param [9, ""], _o getVariable ["COMSPEC_ATAK_TocCam", ""],
            [_m param [0, 2], _m param [2, ""]], _o getVariable ["COMSPEC_ATAK_TocOff", false], _opt param [0, 100], _opt param [1, 0],
            ["of", _o] call comspec_atak_native_fnc_tocHall, ["panelStr", _o] call _fn]] call _fn
    };
    case "apply": {
        _a params [["_o", objNull], ["_c", []]];
        if (isNull _o || {!(_c isEqualType [])} || {(count _c) < (count _keys)}) exitWith { false };
        _c params ["_name", "_side", "_view", "_toc", "_sat", "_squad", "_cam", "_mos", "_off", "_bright", "_range", "_hall", "_pans"];
        private _old = _o getVariable ["COMSPEC_ATAK_TocScr", []];
        // 1. Dalles d'un mur : lues par « panels » à la création des relais (serveur, ou ce poste en repli).
        private _spec = ["panelSpec", _pans] call _ts;
        private _had = (_o getVariable ["COMSPEC_ATAK_TocPanels", []]) select { !isNull _x };
        if (_pans isNotEqualTo "") then { _o setVariable ["COMSPEC_ATAK_TocPanelCfg", _spec, true]; };
        // 2. Mosaïque choisie avant la désignation (même écriture que le menu ACE, fn_tocMosaic « key »).
        private _mosCfg = ["key", format ["MOS|%1|%2", _mos select 0, ["AUTO", "G|" + (_mos select 1)] select ((_mos select 1) isNotEqualTo "")]] call comspec_atak_native_fnc_tocMosaic;
        if (_view isEqualTo "MOSAIC") then { _o setVariable ["COMSPEC_ATAK_TocMosaic", _mosCfg, true]; };
        // 3. Désignation : nom, camp, affichage, poste TOC, satellite, escouade (cadrage de la carte gardé).
        ["add", [_o, _name, _side, _old param [2, 0], _view, _toc, false, _sat, _squad]] call _ts;
        // 4. Réglages portés par l'objet, lus par chaque joueur : veille, luminosité, distance d'affichage.
        if ((_o getVariable ["COMSPEC_ATAK_TocOff", false]) isNotEqualTo _off) then { _o setVariable ["COMSPEC_ATAK_TocOff", _off, true]; };
        if ((_o getVariable ["COMSPEC_ATAK_TocOpt", [100, 0]]) isNotEqualTo [_bright, _range]) then { _o setVariable ["COMSPEC_ATAK_TocOpt", [_bright, _range], true]; };
        // 5. Caméra : source en jeu (netId), ou source décrite (Eden) cherchée jusqu'à ce qu'elle existe.
        if (_cam isNotEqualTo "") then {
            if ((_cam select [0, 4]) in ["CAM:", "UAV:", "cam:", "uav:"]) then {
                ["camWant", [_o, toUpper (_cam select [0, 3]), trim (_cam select [4]), 0]] call _fn;
            } else {
                if ((_o getVariable ["COMSPEC_ATAK_TocCam", ""]) isNotEqualTo _cam) then { ["view", [_o, "cam", _cam]] call _ts; };
            };
        };
        // 6. Salle : liée par son identifiant ou son nom ; "" : déliée.
        private _curHall = ["of", _o] call comspec_atak_native_fnc_tocHall;
        if (_hall isEqualTo "") then {
            if (_curHall isNotEqualTo "") then {
                if (["local", _o] call _fn && {!isServer}) then { _o setVariable ["COMSPEC_ATAK_TocHall", "", true]; } else { ["do", ["unlink", _o]] call comspec_atak_native_fnc_tocHall; };
            };
        } else {
            private _all = (missionNamespace getVariable ["COMSPEC_ATAK_TocHalls", []]) select { _x isEqualType [] && {(count _x) >= 3} };
            private _i = _all findIf { (_x select 0) isEqualTo _hall || {(_x select 1) isEqualTo (toUpper _hall)} };
            private _hid = if (_i >= 0) then { (_all select _i) select 0 } else { "" };
            // Salle inconnue : créée par le serveur au nom donné (attribut Eden), comme le fait le module Écran TOC.
            if (_hid isEqualTo "" && {isServer}) then {
                _hid = format ["E-%1", toUpper _hall];
                ["do", ["create", [_hall, _side, objNull, _hid]]] call comspec_atak_native_fnc_tocHall;
            };
            if (_hid isNotEqualTo "" && {_hid isNotEqualTo _curHall}) then {
                if (["local", _o] call _fn && {!isServer}) then { _o setVariable ["COMSPEC_ATAK_TocHall", _hid, true]; } else { ["do", ["link", [_hid, _o]]] call comspec_atak_native_fnc_tocHall; };
            };
        };
        // 7. Dalles déjà créées (mur déjà désigné) : contenu remis dalle par dalle, sans notification.
        if (_pans isNotEqualTo "" && {_had isNotEqualTo []}) then {
            {
                private _rel = ["panelOf", [_o, _forEachIndex + 1]] call _ts;
                if (isNull _rel) then { continue };
                _x params [["_pv", "SPLIT"], ["_ps", 0], ["_pq", ""], "", ["_pm", []], ["_po", false]];
                if (_pv isEqualTo "SAT") then { ["view", [_rel, "sat", _ps]] call _ts; };
                if (_pv isEqualTo "SQUAD") then { ["view", [_rel, "squad", _pq]] call _ts; };
                if (_pv isEqualTo "MOSAIC" && {(count _pm) >= 5}) then { _rel setVariable ["COMSPEC_ATAK_TocMosaic", _pm, true]; };
                ["view", [_rel, "mode", _pv]] call _ts;
                if ((_rel getVariable ["COMSPEC_ATAK_TocOff", false]) isNotEqualTo (_po isEqualTo true)) then { _rel setVariable ["COMSPEC_ATAK_TocOff", _po isEqualTo true, true]; };
            } forEach _spec;
        };
        _o setVariable ["COMSPEC_ATAK_TocAt", -10];
        true
    };
    case "camWant": {
        // Caméra décrite dans Eden (« CAM:Entrée nord », « UAV:B_UAV_02_dynamicLoadout_F ») : cherchée toutes les 10 s
        // (5 min au plus) tant que l'écran existe ; trouvée, elle passe par le chemin habituel (« view » cam).
        _a params [["_o", objNull], ["_kind", "CAM"], ["_id", ""], ["_n", 0]];
        if (isNull _o || {_id isEqualTo ""} || {_n > 30}) exitWith { false };
        private _side = (_o getVariable ["COMSPEC_ATAK_TocScr", []]) param [1, ""];
        private _src = if (_kind isEqualTo "UAV") then {
            (allUnitsUAV select { alive _x && {(toLower (typeOf _x)) isEqualTo (toLower _id)} && {_side isEqualTo "" || {(str side group _x) isEqualTo _side}} }) param [0, objNull]
        } else {
            ((missionNamespace getVariable ["COMSPEC_ATAK_Cams", []]) select { _x isEqualType objNull && {!isNull _x} && {(toLower (_x getVariable ["COMSPEC_ATAK_CamName", ""])) isEqualTo (toLower _id)} }) param [0, objNull]
        };
        if (!isNull _src && {[_o] call _has}) exitWith { ["view", [_o, "cam", netId _src]] call _ts; true };
        [{ ["camWant", _this] call comspec_atak_native_fnc_tocConfig; }, [_o, _kind, _id, _n + 1], 10] call comspec_atak_native_fnc_waitAndExec;
        false
    };
    case "eden": {
        // Attribut Eden « Usage de l'écran », sur chaque machine au lancement. Serveur à jour : il applique, 3 s après
        // (les autres attributs sont alors posés). Serveur ancien ou sans le mod : un joueur applique à sa place.
        _a params [["_o", objNull], ["_m", ""], ["_try", 0]];
        if (isNull _o || {!(_m isEqualType "")} || {_m isEqualTo ""} || {is3DEN}) exitWith { false };
        if (isServer) exitWith {
            [{ ["edenApply", _this] call comspec_atak_native_fnc_tocConfig; }, [_o, _m], 3] call comspec_atak_native_fnc_waitAndExec;
            true
        };
        if (!hasInterface || {_try > 2}) exitWith { false };
        [{
            params ["_o", "_m", "_try"];
            if (isNull _o || {isNull player} || {_o getVariable ["COMSPEC_ATAK_TocEd", false]}) exitWith {};
            if ((((["mode"] call comspec_atak_native_fnc_tocConfig) select 0)) isNotEqualTo "fallback") exitWith {};
            // Un seul joueur applique : le premier par identifiant ; les autres repassent plus tard s'il ne l'a pas fait.
            private _ids = (allPlayers select { !isNull _x }) apply { getPlayerUID _x };
            _ids sort true;
            if (_try < 2 && {(_ids param [0, getPlayerUID player]) isNotEqualTo (getPlayerUID player)}) exitWith { ["eden", [_o, _m, _try + 1]] call comspec_atak_native_fnc_tocConfig; };
            ["edenApply", [_o, _m]] call comspec_atak_native_fnc_tocConfig;
        }, [_o, _m, _try], 10 + random 4] call comspec_atak_native_fnc_waitAndExec;
        true
    };
    case "edenApply": {
        _a params [["_o", objNull], ["_m", ""]];
        if (isNull _o) exitWith { false };
        _o setVariable ["COMSPEC_ATAK_TocEd", true, true];
        private _g = { params ["_k", "_def"]; _o getVariable [format ["COMSPEC_ATAK_EdScreen%1", _k], _def] };
        if (_m isNotEqualTo "toc") exitWith {
            ["add", [_o, ["Name", ""] call _g, 25, ["Side", ""] call _g, 0, true]] call comspec_atak_native_fnc_screenCast;
            true
        };
        // Même tableau que la fenêtre Zeus (« make »), mêmes champs dans le même ordre (« keys »).
        private _c = ["make", [["Name", ""] call _g, ["Side", ""] call _g, ["View", "SPLIT"] call _g, ["Toc", true] call _g, ["Sat", 0] call _g, ["Squad", ""] call _g,
            ["Cam", ""] call _g, [["MosGrid", 2] call _g, ["MosSrc", ""] call _g], false, ["Bright", 100] call _g, ["Range", 0] call _g, ["Hall", ""] call _g, ["Panels", ""] call _g]] call _fn;
        ["INFO", "TOC", format ["Écran TOC %1 : attributs Eden appliqués (%2)", typeOf _o, _c]] call comspec_atak_native_fnc_log;
        private _done = ["apply", [_o, _c]] call _fn;
        // Affichage « Texte à LED » : texte, couleur, vitesse et défilement des attributs (mêmes champs que le module
        // Écran TOC). Posés après la désignation ; sans texte, le réglage par défaut de l'affichage reste.
        private _edTxt = ["Text", ""] call _g;
        if ((_c select 2) isEqualTo "TEXT" && {_edTxt isEqualType ""} && {(trim _edTxt) isNotEqualTo ""}) then {
            ["view", [_o, "text", ["fromFields", [_edTxt, ["TextColor", "G"] call _g, ["TextSpeed", 1] call _g, ["TextScroll", 0] call _g]] call comspec_atak_native_fnc_tocText]] call _ts;
        };
        _done
    };
    case "form": {
        // Fenêtre du Zeus : ZEN s'il est chargé, sinon le formulaire du mod (fn_zeusForm). Mêmes champs, même code.
        //   champ : [genre, libellé, valeur ou choix, choix courant, aide]
        _a params [["_title", ""], ["_fields", []], ["_code", {}], ["_args", []], ["_help", ""], ["_msg", ""]];
        if (!isNil "zen_dialog_fnc_create" && {!isNull (findDisplay 312)} && {missionNamespace getVariable ["COMSPEC_ATAK_TocZenForm", true]}) exitWith {
            private _content = _fields apply {
                _x params ["_k", "_lab", ["_v", ""], ["_cur", ""], ["_tip", ""]];
                private _l = [_lab call comspec_atak_native_fnc_tr, _tip call comspec_atak_native_fnc_tr];
                switch (_k) do {
                    case "COMBO": { ["COMBO", _l, [_v apply { _x select 1 }, _v apply { (_x select 0) call comspec_atak_native_fnc_tr }, (_v findIf { (_x select 1) isEqualTo _cur }) max 0]] };
                    case "CHECK": { ["CHECKBOX", _l, _v isEqualTo true] };
                    case "SLIDER": { ["SLIDER", _l, [_v select 0, _v select 1, _v select 2, 0]] };
                    default { ["EDIT", _l, _v] };
                }
            };
            private _t = _title call comspec_atak_native_fnc_tr;
            if (_msg isNotEqualTo "") then { _t = format ["%1 · %2", _t, (_msg call comspec_atak_native_fnc_tr) select [0, 70]]; };
            [_t, _content, _code, {}, _args] call zen_dialog_fnc_create
        };
        private _rows = _fields apply {
            _x params ["_k", "_lab", ["_v", ""], ["_cur", ""]];
            if (_k isEqualTo "COMBO") then { [_k, _lab, _v, _cur] } else { [_k, _lab, _v] }
        };
        [_title, _rows, _code, _args, ([_msg, _help] select { _x isNotEqualTo "" }) joinString " · "] call comspec_atak_native_fnc_zeusForm
    };
    case "zeus": {
        // Fenêtre « Configurer l'écran » : mêmes champs que les attributs Eden. Chaque action applique puis rouvre la
        // fenêtre avec son résultat écrit dedans (jamais de message par-dessus le jeu).
        _a params [["_o", objNull], ["_msg", ""], ["_act", "apply"], ["_draft", []]];
        if (isNull _o || {!hasInterface}) exitWith { false };
        _o = [_o] call _hostOf;
        private _c = if (_draft isEqualType [] && {(count _draft) >= (count _keys)}) then { _draft } else { ["read", _o] call _fn };
        _c params ["_name", "_side", "_view", "_toc", "_sat", "_squad", "_cam", "_mos", "_off", "_bright", "_range", "_hall", "_pans"];
        private _np = getNumber (configOf _o >> "comspec_screenPanels");
        private _views = (["viewList", _o] call _fn) + [["Éteint (veille)", "OFF"]];
        private _grps = [["Aucune", ""]];
        {
            if (({alive _x} count (units _x)) > 0 && {({isPlayer _x} count (units _x)) > 0 || {((units _x) findIf { [_x] call comspec_atak_native_fnc_hasDevice }) >= 0}}) then {
                _grps pushBackUnique [format ["%1 (%2)", groupId _x, str side _x], groupId _x];
            };
        } forEach allGroups;
        if (_squad isNotEqualTo "" && {(_grps findIf { (_x select 1) isEqualTo _squad }) < 0}) then { _grps pushBack [_squad, _squad]; };
        private _mosG = [["Tout le camp", ""]] + ((_grps select { (_x select 1) isNotEqualTo "" }) apply { [format ["Groupe %1", _x select 0], _x select 1] });
        if ((_mos select 1) isNotEqualTo "" && {(_mosG findIf { (_x select 1) isEqualTo (_mos select 1) }) < 0}) then { _mosG pushBack [_mos select 1, _mos select 1]; };
        private _cams = [["Aucune", ""]] + ((["sources"] call comspec_atak_native_fnc_tocCam) apply { [_x select 0, _x select 1] });
        if (_cam isNotEqualTo "" && {(_cams findIf { (_x select 1) isEqualTo _cam }) < 0}) then { _cams pushBack [format ["Caméra en cours (%1)", _cam], _cam]; };
        private _halls = [["Aucune salle", ""]] + (((missionNamespace getVariable ["COMSPEC_ATAK_TocHalls", []]) select { _x isEqualType [] && {(count _x) >= 3} }) apply { [format ["Salle %1", _x select 1], _x select 0] });
        if (_hall isNotEqualTo "" && {(_halls findIf { (_x select 1) isEqualTo _hall }) < 0}) then { _halls pushBack [_hall, _hall]; };
        private _acts = [["Appliquer les réglages", "apply"], ["Tester l'image (8 s)", "test"], ["Identifier l'écran (il clignote 2 s)", "blink"],
            ["Copier la configuration vers…", "copy"], ["Surface de l'image (écran d'un autre mod)…", "surface"], ["Écran de diapositives : nom, portée, camp…", "cast"],
            ["Retirer l'affichage TOC", "remove"], ["Supprimer l'écran", "delete"]];
        private _fields = [
            ["COMBO", "Action", _acts, _act, "Appliquer : les réglages ci-dessous sont posés tout de suite, la fenêtre revient avec le résultat."],
            ["EDIT", "Nom", _name, "", "Nom affiché sur l'écran et dans les menus. Exemple : TOC Bravo."],
            ["COMBO", "Camp affiché", _sides, _side, "Camp dont l'écran montre le réseau. « Celui de chaque joueur » : chacun voit son camp."],
            ["COMBO", "Affichage", _views, [_view, "OFF"] select _off, "Ce que montre l'écran. Exemple : Vue satellite pour suivre un élément."],
            ["COMBO", "Escouade montrée", _grps, _squad, "Groupe suivi par l'affichage « écran d'escouade ». Exemple : Alpha 1-1."],
            ["COMBO", "Satellite montré", [["SAT 1", 0], ["SAT 2", 1]], _sat, "Satellite du poste montré par l'affichage « vue satellite »."],
            ["COMBO", "Caméra en direct", _cams, _cam, "Drone, caméra fixe ou caméra poitrine montrée par l'affichage « caméra en direct »."],
            ["COMBO", "Mosaïque : grille", [["2 × 2", 2], ["3 × 3", 3]], _mos select 0, "Nombre de caméras de la mosaïque."],
            ["COMBO", "Mosaïque : caméras", _mosG, _mos select 1, "Caméras de tout le camp, ou d'un seul groupe."]
        ];
        if (_np > 1) then {
            _fields pushBack ["EDIT", format [("Dalles 2 à %1 du mur") call comspec_atak_native_fnc_tr, _np], _pans, "", "Contenu des dalles 2 et suivantes, dans l'ordre de lecture. Exemple : SAT1, CLOCK, SQ:Alpha 1-1, LOG, MOS2, OFF."];
        };
        _fields append [
            ["COMBO", "Luminosité", [["100 %", 100], ["75 %", 75], ["50 %", 50], ["25 %", 25]], _bright, "Image assombrie pour une salle dans le noir. Exemple : 50 % de nuit."],
            ["CHECK", "Poste TOC (ouvre le PC du poste)", _toc, "", "Coché : l'écran donne le PC du poste et l'app Poste TOC à qui s'en approche."],
            ["COMBO", "Salle TOC", _halls, _hall, "Salle à laquelle l'écran est lié (plan du mur et scènes du PC maître)."],
            ["SLIDER", "Distance d'affichage (m, 0 : auto)", [0, 150, _range], "", "Distance à laquelle cet écran s'allume. Exemple : 60 pour un mur d'images vu de loin."]
        ];
        // État écrit dans la fenêtre : qui tient l'écran, et le résultat de la dernière action.
        (["mode"] call _fn) params ["_mode", "_srv", "_my"];
        private _state = if (_mode isEqualTo "fallback" || {_o getVariable ["COMSPEC_ATAK_TocFb", false]}) then {
            format ["%1. %2", ["reason"] call _fn, ("Serveur ancien : écran géré par les joueurs, non sauvegardé par le serveur") call comspec_atak_native_fnc_tr]
        } else {
            format [("Écran tenu par le serveur (ATAK %1), gardé pour la partie suivante") call comspec_atak_native_fnc_tr, _srv]
        };
        private _desig = [("Pas encore désigné comme écran TOC") call comspec_atak_native_fnc_tr, format [("Écran TOC « %1 »") call comspec_atak_native_fnc_tr, _name]] select ([_o] call _has);
        ["form", ["Configurer l'écran", _fields, {
            params ["_v", "_o"];
            ["zeusRun", [_o, _v]] call comspec_atak_native_fnc_tocConfig;
        }, _o, format ["%1 · %2", _desig, _state], _msg]] call _fn
    };
    case "zeusRun": {
        _a params [["_o", objNull], ["_v", []]];
        if (isNull _o) exitWith { false };
        private _np = getNumber (configOf _o >> "comspec_screenPanels");
        // Champ « Dalles » présent seulement sur un mur : remis à sa place pour lire les valeurs dans un seul ordre.
        if (_np <= 1) then { _v = (_v select [0, 9]) + [""] + (_v select [9]); };
        _v params [["_act", "apply"], ["_name", ""], ["_side", ""], ["_view", "SPLIT"], ["_squad", ""], ["_sat", 0], ["_cam", ""], ["_mg", 2], ["_ms", ""], ["_pans", ""],
            ["_bright", 100], ["_toc", true], ["_hall", ""], ["_range", 0]];
        private _c = ["make", [_name, _side, _view, _toc, _sat, _squad, _cam, [_mg, _ms], false, _bright, _range, _hall, _pans]] call _fn;
        private _reopen = {
            params ["_msg", ["_next", "apply"], ["_wait", 0.7], ["_keep", []]];
            [{ ["zeus", _this] call comspec_atak_native_fnc_tocConfig; }, [_o, _msg, _next, _keep], _wait] call comspec_atak_native_fnc_waitAndExec;
        };
        switch (_act) do {
            case "test": {
                private _face = ((["surfaces", _o] call _ts) param [1, 0]) max 0;
                ["test", [_o, _face]] call _ts;
                ["Image de test posée 8 s sur ce poste", "apply", 0.3, _c] call _reopen;
            };
            case "blink": {
                if !([_o] call _has) exitWith { ["Appliquez d'abord les réglages : seul un écran désigné clignote", "apply", 0.3, _c] call _reopen; };
                if (["local", _o] call _fn && {!isServer}) then { ["comspec_atak_native_tocBlink", [_o]] call CBA_fnc_globalEvent; } else { ["do", ["blink", _o]] call comspec_atak_native_fnc_tocHall; };
                ["L'écran clignote 2 s chez les joueurs proches", "apply", 0.3, _c] call _reopen;
            };
            case "copy": { [{ ["zeusCopy", _this] call comspec_atak_native_fnc_tocConfig; }, [_o, _c], 0.3] call comspec_atak_native_fnc_waitAndExec; };
            case "surface": { [{ ["run", ["toc", _this]] call comspec_atak_native_fnc_zeusObject; }, _o, 0.3] call comspec_atak_native_fnc_waitAndExec; };
            case "cast": { [{ ["run", ["screen", _this]] call comspec_atak_native_fnc_zeusObject; }, _o, 0.3] call comspec_atak_native_fnc_waitAndExec; };
            case "remove": {
                ["remove", _o] call _ts;
                ["Affichage TOC retiré"] call _reopen;
            };
            case "delete": {
                ["remove", _o] call _ts;
                [{ if (!isNull _this) then { deleteVehicle _this; }; }, _o, 1] call comspec_atak_native_fnc_waitAndExec;
            };
            default {
                ["apply", [_o, _c]] call _fn;
                private _lbl = ((["title", _c select 2] call _ts) param [1, ""]) call comspec_atak_native_fnc_tr;
                [format [("Réglages appliqués : %1") call comspec_atak_native_fnc_tr, [_lbl, ("éteint (veille)") call comspec_atak_native_fnc_tr] select (_c select 8)]] call _reopen;
            };
        };
        true
    };
    case "zeusCopy": {
        // « Copier la configuration vers… » : un écran, les écrans de la même salle, ou tous ceux à 50 m. Le nom de
        // chaque écran visé est gardé ; les dalles ne sont copiées que vers un mur.
        _a params [["_o", objNull], ["_c", []]];
        if (isNull _o) exitWith { false };
        private _others = ((missionNamespace getVariable ["COMSPEC_ATAK_TocScreens", []]) select { !isNull _x && {alive _x} && {_x isNotEqualTo _o} && {isNull (_x getVariable ["COMSPEC_ATAK_TocHost", objNull])} });
        _others = [_others, [], { _x distance _o }, "ASCEND"] call BIS_fnc_sortBy;
        private _opts = [["Tous les écrans à moins de 50 m", "near"], ["Tous les écrans de la même salle", "hall"]];
        { _opts pushBack [format ["%1 · %2 m", (_x getVariable ["COMSPEC_ATAK_TocScr", []]) param [0, "TOC"], round (_x distance _o)], netId _x]; } forEach (_others select [0, 30]);
        ["form", ["Copier la configuration vers…", [["COMBO", "Écrans visés", _opts, "near", "Le nom de chaque écran visé est gardé."]], {
            params ["_v", "_args"];
            _args params ["_o", "_c"];
            private _pick = _v param [0, "near"];
            private _all = (missionNamespace getVariable ["COMSPEC_ATAK_TocScreens", []]) select { !isNull _x && {alive _x} && {_x isNotEqualTo _o} && {isNull (_x getVariable ["COMSPEC_ATAK_TocHost", objNull])} };
            private _hid = ["of", _o] call comspec_atak_native_fnc_tocHall;
            private _to = switch (_pick) do {
                case "near": { _all select { (_x distance _o) < 50 } };
                case "hall": { _all select { _hid isNotEqualTo "" && {(["of", _x] call comspec_atak_native_fnc_tocHall) isEqualTo _hid} } };
                default { [objectFromNetId _pick] select { !isNull _x } };
            };
            {
                private _cc = +_c;
                _cc set [0, (_x getVariable ["COMSPEC_ATAK_TocScr", []]) param [0, _c select 0]];
                if ((getNumber (configOf _x >> "comspec_screenPanels")) <= 1) then { _cc set [12, ""]; };
                ["apply", [_x, _cc]] call comspec_atak_native_fnc_tocConfig;
            } forEach _to;
            [{ ["zeus", _this] call comspec_atak_native_fnc_tocConfig; }, [_o, format [("Configuration copiée vers %1 écran(s)") call comspec_atak_native_fnc_tr, count _to]], 0.5] call comspec_atak_native_fnc_waitAndExec;
        }, [_o, _c], "Affichage, camp, luminosité, distance et salle sont copiés."]] call _fn;
        true
    };
    case "openPc": {
        // « Ouvrir le PC du poste » : menu ACE de l'écran, molette, app Poste TOC du téléphone, clic droit du Zeus.
        // Écran COMSPEC pas encore désigné : il le devient d'abord (carte et flux), le PC s'ouvre dès que c'est fait.
        private _o = _a;
        if !(_o isEqualType objNull) then { _o = objNull; };
        if (!hasInterface || {isNull player} || {!alive player}) exitWith { false };
        if (isNull _o) then { _o = missionNamespace getVariable ["COMSPEC_ATAK_TocActObj", objNull]; };
        if (isNull _o) then { _o = ["near"] call comspec_atak_native_fnc_tocPost; };
        // Sans objet (téléphone, Zeus) : le PC s'ouvre sur la position du joueur.
        if (isNull _o) exitWith {
            player setVariable ["COMSPEC_ATAK_TocOn", ["callsign"] call comspec_atak_native_fnc_tocPost, true];
            if (!isNull ([] call comspec_atak_native_fnc_display) && {((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["interactive", false])}) then { [] call comspec_atak_native_fnc_interactToggle; };
            ["open", player] call comspec_atak_native_fnc_tocLaptop
        };
        _o = [_o] call _hostOf;
        if (["isToc", _o] call comspec_atak_native_fnc_tocPost) exitWith { ["open", _o] call comspec_atak_native_fnc_tocPost; true };
        if ([_o] call _has || {(getNumber (configOf _o >> "comspec_screen")) < 1}) exitWith { ["open", _o] call comspec_atak_native_fnc_tocPost; false };
        ["ensure", [_o, "SPLIT"]] call _ts;
        private _wait = {
            params ["_o", "_n", "_wait"];
            if (isNull _o) exitWith {};
            if (["isToc", _o] call comspec_atak_native_fnc_tocPost) exitWith { ["open", _o] call comspec_atak_native_fnc_tocPost; };
            if (_n >= 12) exitWith { ["WARNING", format ["%1 : %2", ("PC du poste indisponible") call comspec_atak_native_fnc_tr, ["reason"] call comspec_atak_native_fnc_tocConfig], 6, 30] call comspec_atak_native_fnc_notify; };
            [_wait, [_o, _n + 1, _wait], 0.4] call comspec_atak_native_fnc_waitAndExec;
        };
        [_wait, [_o, 0, _wait], 0.2] call comspec_atak_native_fnc_waitAndExec;
        true
    };
    case "acePos": {
        // Position du point ACE (config des écrans) : centre de la boîte du modèle. Les modèles du mod n'ont pas de
        // point mémoire, et leur origine est au sol ou au plafond.
        private _t = objNull;
        if (!isNil "_target" && {_target isEqualType objNull}) then { _t = _target; };
        if (isNull _t && {_a isEqualType objNull}) then { _t = _a; };
        if (isNull _t && {_a isEqualType []}) then { { if (!isNil "_x" && {_x isEqualType objNull} && {!isNull _x}) exitWith { _t = _x; }; } forEach _a; };
        if (isNull _t) exitWith { [0, 0, 1] };
        private _m = _t getVariable "COMSPEC_ATAK_TocMid";
        if (isNil "_m") then {
            (boundingBoxReal _t) params [["_b0", [0, 0, 0]], ["_b1", [0, 0, 0]]];
            _m = (_b0 vectorAdd _b1) vectorMultiply 0.5;
            _t setVariable ["COMSPEC_ATAK_TocMid", _m];
        };
        // Écran suspendu ou posé haut (bandeau à LED sous plafond, écran 98 pouces au plafond) : le centre du modèle
        // est hors de portée de main, le menu ACE n'apparaissait jamais (retour de TTRD, 4.8.0 : « j'ai posé le texte
        // défilant, j'ai plus d'action ACE du tout »). Le point est descendu à 1,2 m au plus au-dessus des yeux du
        // joueur, à la verticale du centre ; l'écran lui-même ne bouge pas.
        if (hasInterface && {!isNull player}) then {
            private _w = _t modelToWorldWorld _m;
            private _top = ((eyePos player) select 2) + 1.2;
            if ((_w select 2) > _top) then { _m = _t worldToModel (ASLToAGL [_w select 0, _w select 1, _top]); };
        };
        _m
    };
    case "dim": {
        // Luminosité de l'écran (COMSPEC_ATAK_TocOpt) : voile de la couleur de fond du thème au-dessus de l'image,
        // recréé quand l'affichage a ajouté des contrôles (il doit rester le dernier, donc au-dessus).
        _a params [["_o", objNull], ["_d", displayNull]];
        if (isNull _o || {isNull _d}) exitWith { false };
        private _b = (([_o] call _hostOf) getVariable ["COMSPEC_ATAK_TocOpt", []]) param [0, 100];
        if !(_b isEqualType 0) then { _b = 100; };
        private _ov = _d getVariable ["COMSPEC_ATAK_TocDim", controlNull];
        if (_b >= 100) exitWith {
            if (!isNull _ov) then { ctrlDelete _ov; _d setVariable ["COMSPEC_ATAK_TocDim", controlNull]; };
            false
        };
        if (isNull _ov || {(_d getVariable ["COMSPEC_ATAK_TocDimN", -1]) isNotEqualTo (count (allControls _d))}) then {
            if (!isNull _ov) then { ctrlDelete _ov; };
            _ov = _d ctrlCreate ["RscText", -1];
            _ov ctrlSetPosition [0, 0, 1, 1];
            _ov ctrlCommit 0;
            _d setVariable ["COMSPEC_ATAK_TocDim", _ov];
            _d setVariable ["COMSPEC_ATAK_TocDimN", count (allControls _d)];
        };
        _ov ctrlSetBackgroundColor (["rgba", ["bg0", 1 - ((_b max 25) / 100)]] call comspec_atak_native_fnc_tocTheme);
        true
    };
    case "selfTest": {
        // Contrôle du code sans effet sur la partie. Exemple : ["selfTest"] call comspec_atak_native_fnc_tocConfig;
        private _ok = 0;
        private _bad = [];
        private _t = { params ["_name", "_cond"]; if (_cond) then { _ok = _ok + 1; } else { _bad pushBack _name; }; };
        private _def = ["make", []] call _fn;
        ["réglage par défaut complet", (count _def) isEqualTo (count _keys)] call _t;
        ["affichage inconnu ramené à carte et flux", (_def select 2) isEqualTo "SPLIT" && {((["make", ["", "", "???"]] call _fn) select 2) isEqualTo "SPLIT"}] call _t;
        private _edn = ["make", ["  TOC Bravo ", "blufor", "off", true, "1", "Alpha 1-1", "CAM:Entrée nord", ["3", ""], false, "40", "500", "Salle A", "SAT1, CLOCK"]] call _fn;
        ["textes d'Eden convertis", _edn isEqualTo ["TOC Bravo", "WEST", "SPLIT", true, 1, "Alpha 1-1", "CAM:Entrée nord", [3, ""], true, 40, 150, "Salle A", "SAT1, CLOCK"]] call _t;
        ["réglage stable (make de make)", (["make", _edn] call _fn) isEqualTo _edn] call _t;
        private _vl = (["viewList", objNull] call _fn) apply { _x select 1 };
        ["tous les affichages 16:9 proposés", ((["views"] call _ts) - ["STRIP", "PLATE", "COLA", "COLB", "COLC", "COLD", "COLE", "COLF"]) isEqualTo _vl && {"TEXT" in _vl}] call _t;
        private _sp = ["panelSpec", "SAT2, OFF, SQ:Alpha 1-1"] call _ts;
        ["dalles lues", (count _sp) isEqualTo 3 && {((_sp select 0) select [0, 2]) isEqualTo ["SAT", 1]} && {((_sp select 1) param [5, false]) isEqualTo true} && {((_sp select 2) select 2) isEqualTo "Alpha 1-1"}] call _t;
        private _keep = [missionNamespace getVariable "COMSPEC_ATAK_ServerVersion", missionNamespace getVariable "COMSPEC_ATAK_TocForceFallback"];
        if (!isServer) then {
            missionNamespace setVariable ["COMSPEC_ATAK_TocForceFallback", false];
            missionNamespace setVariable ["COMSPEC_ATAK_ServerVersion", ""];
            ["serveur sans version : repli", ((["mode"] call _fn) select 0) isEqualTo "fallback"] call _t;
            missionNamespace setVariable ["COMSPEC_ATAK_ServerVersion", "4.2.3"];
            ["serveur plus ancien : repli, versions dans le message", ((["mode"] call _fn) select 0) isEqualTo "fallback" && {((["reason"] call _fn) find "4.2.3") >= 0}] call _t;
            missionNamespace setVariable ["COMSPEC_ATAK_ServerVersion", missionNamespace getVariable ["COMSPEC_ATAK_NativeVersion", ""]];
            ["même version : serveur", ((["mode"] call _fn) select 0) isEqualTo "server"] call _t;
            missionNamespace setVariable ["COMSPEC_ATAK_ServerVersion", _keep select 0];
            missionNamespace setVariable ["COMSPEC_ATAK_TocForceFallback", _keep select 1];
        };
        ["INFO", "TOC", format ["Contrôle des écrans TOC : %1 réussi(s), échec(s) : %2", _ok, _bad]] call comspec_atak_native_fnc_log;
        [_ok, _bad]
    };
    default { false };
};
