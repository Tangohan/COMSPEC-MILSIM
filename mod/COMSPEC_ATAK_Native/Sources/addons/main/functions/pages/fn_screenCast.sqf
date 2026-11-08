/*
    Écrans COMSPEC (idée d'un joueur, 25/10, WulffOperator) : les diapositives de l'app Briefing diffusées sur des écrans
    du jeu (TV, ordinateur, projecteur, tableau). Un écran est un objet désigné par le module Eden / Zeus « ATAK · Écran
    COMSPEC » (fn_moduleScreen) ou par script ; le téléphone le détecte à portée et le pilote, ACE (ou la molette) aussi.
    Chaque joueur affiche lui-même l'image sur l'objet (setObjectTexture local) : diapositive Athena téléchargée par son
    COMSPEC Link, ou la sienne du Google Slides synchronisé. Sans COMSPEC Link, l'écran reste noir pour lui.
    Arma refuse toute image hors d'un PBO (journal de TTRD du 26/10 : texture [-1,-1], écran noir) : comme dans l'app
    Briefing, la copie JPEG du site est lue en base64 par COMSPEC Link (ImageDataChunk, 2.0.96+) et montrée par un
    navigateur intégré (CT_WEBBROWSER) dans une texture UI posée sur l'objet
    (#(rgb,2048,1024,1)ui("COMSPEC_RscScreenSlideTexture", nom unique)). Le chemin direct reste utilisé quand Arma le lit
    (-filePatching).

    Variables publiques de l'objet :
      COMSPEC_ATAK_Screen     [nom, portée m, camp ("" = tous), index de texture, contrôle ACE]
      COMSPEC_ATAK_ScreenCast [diffuseur, nom du diffuseur, source "ATHENA" | "GOOGLE" | "ACADEMY", deck [[id, titre, url]...], index]
                              ([] = rien n'est diffusé, l'écran garde sa texture d'origine)
                              ACADEMY (app Academy, fn_academy) : deck vide, 6e élément = repère de la leçon ; seuls ce
                              repère et l'index changent quand l'instructeur tourne les pages.
      COMSPEC_ATAK_ScreenAcademy [repère, cours, leçon, [[genre, titre, texte]...]] leçon Academy, envoyée une fois par
                              leçon : chaque joueur dessine la page lui-même (texte du jeu, sans image ni COMSPEC Link).
    Registre : missionNamespace COMSPEC_ATAK_Screens (objets), tenu par le serveur.

    Modes :
      ["add", [objet, nom, portée, camp, index, ace]]   enregistre un écran (serveur ; ailleurs, la demande part au serveur)
      ["remove", objet]                                  retire l'écran (serveur ; ailleurs, demande au serveur)
      ["list"]                    écrans à portée de mon téléphone, de mon camp, triés par distance
      ["cast", objet]             diffuse ma diapositive en cours sur l'écran ("castAll" : tous les écrans à portée)
      ["castOn", objet]           comme cast, et désigne l'objet comme écran si besoin (ACE sur TV / écran TOC)
      ["castAcademy", [objet, [repère, cours, leçon, pages], index]]   diffuse une leçon Academy (instructeur)
      ["stop", objet]             arrête la diffusion        ["step", [objet, ±1]] : diapositive précédente / suivante
                                  (Academy : seul l'instructeur qui diffuse, ou Zeus, tourne les pages)
      ["sync"]                    ma diapositive a changé : les écrans que je diffuse suivent
      ["tick"]                    toutes les 2 s (XEH_postInitClient) : image des écrans proches, actions ACE ou molette
      ["load", display]           onLoad du display de la texture UI (diapositive par le navigateur intégré)
      ["initServer"]              événement serveur (XEH_postInitServer)
    Exemple de mission : ["add", [this, "Salle de briefing", 30]] call comspec_atak_native_fnc_screenCast;
*/
params [["_mode", ""], ["_a", objNull]];
private _fn = comspec_atak_native_fnc_screenCast;
private _canSee = {
    params ["_o"];
    private _cfg = _o getVariable ["COMSPEC_ATAK_Screen", []];
    if ((count _cfg) < 2) exitWith { false };
    private _side = _cfg param [2, ""];
    (_side isEqualTo "" || {_side isEqualTo str side group player}) && {(player distance _o) <= ((_cfg select 1) max 3)}
};
// Deck diffusable depuis le téléphone : [source, deck, index] ([] sans diapositive).
private _myDeck = {
    ([] call comspec_atak_native_fnc_briefingSignature) params ["_src", "_idx", "_total"];
    if (_total < 1) exitWith { [] };
    if (_src isEqualTo "ATHENA") exitWith {
        ["ATHENA", (missionNamespace getVariable ["COMSPEC_BriefingSlides", []]) apply { [_x select 0, _x select 1, _x param [3, ""]] }, _idx]
    };
    if (_src isEqualTo "GOOGLE") exitWith { ["GOOGLE", [], _idx] };
    []
};
// La dalle visible n'est pas toujours la sélection 0 : un portable a « camo » puis « screen ».
// On prend la sélection nommée écran quand celle demandée n'en est pas une.
// Toile de projection : image seulement quand un projecteur allumé l'éclaire (fn_projector, contrôle de ce poste).
private _lit = {
    params ["_o"];
    (getNumber (configOf _o >> "comspec_projBoard")) < 1 || {_o getVariable ["COMSPEC_ATAK_ProjLit", false]}
};
private _face = {
    params ["_o", "_sel"];
    private _names = getArray (configOf _o >> "hiddenSelections");
    private _named = _names findIf { (toLower _x) in ["screen", "display", "lcd", "monitor"] };
    private _curName = toLower (_names param [_sel, ""]);
    if (_named >= 0 && {!(_curName in ["screen", "display", "lcd", "monitor"])}) then { _named } else { _sel }
};

switch (_mode) do {
    case "initServer": {
        ["comspec_atak_native_screen", { _this call comspec_atak_native_fnc_screenCast; }] call CBA_fnc_addEventHandler;
        true
    };
    case "add": {
        _a params [["_o", objNull], ["_name", ""], ["_range", 25], ["_side", ""], ["_sel", 0], ["_ace", true]];
        if (isNull _o) exitWith { false };
        if (!isServer) exitWith { ["comspec_atak_native_screen", ["add", _a]] call CBA_fnc_serverEvent; true };
        if (_name isEqualTo "") then { _name = getText (configOf _o >> "displayName"); };
        if (_name isEqualTo "") then { _name = "Écran"; };
        private _list = (missionNamespace getVariable ["COMSPEC_ATAK_Screens", []]) select { !isNull _x };
        // Écran (re)désigné : une diffusion restée sur l'objet retiré ne reprend pas toute seule.
        if !(_o in _list) then { _o setVariable ["COMSPEC_ATAK_ScreenCast", [], true]; };
        _o setVariable ["COMSPEC_ATAK_Screen", [_name, (_range max 3) min 500, _side, _sel max 0, _ace], true];
        _list pushBackUnique _o;
        missionNamespace setVariable ["COMSPEC_ATAK_Screens", _list, true];
        private _off = missionNamespace getVariable ["COMSPEC_ATAK_ScreensOff", []];
        if (_o in _off) then { missionNamespace setVariable ["COMSPEC_ATAK_ScreensOff", _off select { !isNull _x && {_x isNotEqualTo _o} }, true]; };
        true
    };
    case "remove": {
        if (isNull _a) exitWith { false };
        if (!isServer) exitWith { ["comspec_atak_native_screen", ["remove", _a]] call CBA_fnc_serverEvent; true };
        _a setVariable ["COMSPEC_ATAK_ScreenCast", [], true];
        _a setVariable ["COMSPEC_ATAK_Screen", nil, true];
        missionNamespace setVariable ["COMSPEC_ATAK_Screens", (missionNamespace getVariable ["COMSPEC_ATAK_Screens", []]) select { !isNull _x && {_x isNotEqualTo _a} }, true];
        // Écrans retirés : chaque poste y remet la texture d'origine (le registre ne les parcourt plus).
        private _off = (missionNamespace getVariable ["COMSPEC_ATAK_ScreensOff", []]) select { !isNull _x };
        _off pushBackUnique _a;
        missionNamespace setVariable ["COMSPEC_ATAK_ScreensOff", _off, true];
        true
    };
    case "list": {
        private _l = (missionNamespace getVariable ["COMSPEC_ATAK_Screens", []]) select { !isNull _x && {alive _x} && {[_x] call _canSee} };
        [_l, [], { player distance _x }, "ASCEND"] call BIS_fnc_sortBy
    };
    case "castAll": {
        private _n = 0;
        { if (["cast", _x] call _fn) then { _n = _n + 1; }; } forEach (["list"] call _fn);
        _n
    };
    case "castOn": {
        // ACE / écran TOC : désigne l'objet comme écran de briefing s'il ne l'est pas encore, puis diffuse.
        if (isNull _a || {!alive _a}) exitWith { false };
        if ((count (_a getVariable ["COMSPEC_ATAK_Screen", []])) < 5) then {
            private _nm = getText (configOf _a >> "displayName");
            if (_nm isEqualTo "") then { _nm = "Écran"; };
            private _sel = [_a, 0] call _face;
            ["add", [_a, _nm select [0, 32], 50, "", _sel, true]] call _fn;
            // Hors serveur, la désignation revient du serveur par le réseau : un « cast » immédiat était refusé
            // (« n'est pas désigné comme écran », journal du 08/11, écran TOC). On attend qu'elle arrive.
            if (!isServer) then {
                ["INFO", "Préparation de l'écran pour la diffusion…", 2, 20] call comspec_atak_native_fnc_notify;
                ["castWait", [_a, 0]] call _fn;
            };
        };
        if ((count (_a getVariable ["COMSPEC_ATAK_Screen", []])) >= 5) then { ["cast", _a] call _fn } else { true }
    };
    case "castWait": {
        _a params [["_o", objNull], ["_n", 0]];
        if (isNull _o) exitWith { false };
        if ((count (_o getVariable ["COMSPEC_ATAK_Screen", []])) >= 5) exitWith { ["cast", _o] call _fn };
        if (_n >= 16) exitWith {
            ["WARNING", "SCREEN", format ["Diffusion impossible : %1 n'a pas été désigné comme écran par le serveur en 8 s", typeOf _o]] call comspec_atak_native_fnc_log;
            ["WARNING", "L'écran n'a pas répondu : diffusion annulée, réessayez", 5, 30] call comspec_atak_native_fnc_notify;
            false
        };
        [{ ["castWait", _this] call comspec_atak_native_fnc_screenCast; }, [_o, _n + 1], 0.5] call comspec_atak_native_fnc_waitAndExec;
        true
    };
    case "cast": {
        if (isNull _a) exitWith { ["WARNING", "SCREEN", "Diffusion refusée : aucun écran visé"] call comspec_atak_native_fnc_log; false };
        if ((count (_a getVariable ["COMSPEC_ATAK_Screen", []])) < 5) exitWith {
            ["WARNING", "SCREEN", format ["Diffusion refusée : %1 n'est pas désigné comme écran", typeOf _a]] call comspec_atak_native_fnc_log;
            ["WARNING", "Cet objet n'est pas encore un écran : utilisez ACE › Écran COMSPEC › Diffuser mes diapositives", 5, 30] call comspec_atak_native_fnc_notify;
            false
        };
        private _d = call _myDeck;
        if ((count _d) isEqualTo 0) exitWith {
            ["WARNING", "SCREEN", format ["Diffusion refusée sur %1 : aucune diapositive ouverte dans l'app Briefing", typeOf _a]] call comspec_atak_native_fnc_log;
            ["WARNING", "Aucune diapositive à diffuser : ouvrez une présentation dans l'onglet DIAPOSITIVES", 4, 20] call comspec_atak_native_fnc_notify;
            false
        };
        _d params ["_src", "_deck", "_idx"];
        ["INFO", "SCREEN", format ["Diffusion lancée : écran %1 (%2), source %3, diapositive %4 sur %5, COMSPEC Link %6", typeOf _a, (_a getVariable ["COMSPEC_ATAK_Screen", []]) param [0, "?"], _src, _idx + 1, count _deck, ["absent (écran noir pour ce poste)", "présent"] select ([] call comspec_atak_native_fnc_bridge)]] call comspec_atak_native_fnc_log;
        // Google chargé par TESTER LE LIEN (ce téléphone seul) : les autres joueurs n'avaient pas le deck et voyaient un
        // écran noir. Il leur est d'abord diffusé (même chemin que DIFFUSER À TOUS), la navigation suit ensuite pour tous.
        if (_src isEqualTo "GOOGLE") then {
            private _gUrl = uiNamespace getVariable ["COMSPEC_ATAK_BriefLocalDeck", ""];
            if (_gUrl isNotEqualTo "") then {
                uiNamespace setVariable ["COMSPEC_ATAK_BriefLocalDeck", ""];
                ["show", _gUrl, _idx] call comspec_overwatch_connect_fnc_broadcastGoogleBriefingState;
            };
        };
        // Écran TOC : libérer la dalle avant de poser la diapositive (évite d'enregistrer la texture TOC comme « origine »).
        if ((count (_a getVariable ["COMSPEC_ATAK_TocScr", []])) >= 8 && {(_a getVariable ["COMSPEC_ATAK_TocTex", ""]) isNotEqualTo ""}) then {
            ["paint", [_a, _a getVariable ["COMSPEC_ATAK_TocSel", 0], _a getVariable ["COMSPEC_ATAK_TocOrig", ""]]] call comspec_atak_native_fnc_tocScreen;
            _a setVariable ["COMSPEC_ATAK_TocTex", ""];
            _a setVariable ["COMSPEC_ATAK_ScreenOrig", nil];
            _a setVariable ["COMSPEC_ATAK_ScreenCur", ""];
        };
        _a setVariable ["COMSPEC_ATAK_ScreenCast", [player, name player, _src, _deck, _idx], true];
        ["INFO", format ["Diapositives sur %1", (_a getVariable ["COMSPEC_ATAK_Screen", []]) param [0, "l'écran"]], 2, 20] call comspec_atak_native_fnc_notify;
        true
    };
    case "castAcademy": {
        _a params [["_o", objNull], ["_lesson", []], ["_idx", 0]];
        if (isNull _o || {!alive _o} || {(count _lesson) < 4}) exitWith { false };
        // Écran TOC / TV pas encore désigné : il le devient (comme castOn).
        if ((count (_o getVariable ["COMSPEC_ATAK_Screen", []])) < 5) then {
            private _nm = getText (configOf _o >> "displayName");
            if (_nm isEqualTo "") then { _nm = "Écran"; };
            ["add", [_o, _nm select [0, 32], 50, "", [_o, 0] call _face, true]] call _fn;
        };
        if ((count (_o getVariable ["COMSPEC_ATAK_TocScr", []])) >= 8 && {(_o getVariable ["COMSPEC_ATAK_TocTex", ""]) isNotEqualTo ""}) then {
            ["paint", [_o, _o getVariable ["COMSPEC_ATAK_TocSel", 0], _o getVariable ["COMSPEC_ATAK_TocOrig", ""]]] call comspec_atak_native_fnc_tocScreen;
            _o setVariable ["COMSPEC_ATAK_TocTex", ""];
            _o setVariable ["COMSPEC_ATAK_ScreenOrig", nil];
            _o setVariable ["COMSPEC_ATAK_ScreenCur", ""];
        };
        // Texte de la leçon envoyé une seule fois ; ensuite seul l'index circule.
        if (((_o getVariable ["COMSPEC_ATAK_ScreenAcademy", []]) param [0, ""]) isNotEqualTo (_lesson select 0)) then {
            _o setVariable ["COMSPEC_ATAK_ScreenAcademy", _lesson, true];
        };
        _o setVariable ["COMSPEC_ATAK_ScreenCast", [player, name player, "ACADEMY", [], ((_idx max 0) min (((count (_lesson select 3)) - 1) max 0)), _lesson select 0], true];
        ["INFO", format ["Leçon Academy sur %1", (_o getVariable ["COMSPEC_ATAK_Screen", []]) param [0, "l'écran"]], 2, 20] call comspec_atak_native_fnc_notify;
        true
    };
    case "stop": {
        if (isNull _a) exitWith { false };
        ["INFO", "SCREEN", format ["Diffusion arrêtée : écran %1", typeOf _a]] call comspec_atak_native_fnc_log;
        _a setVariable ["COMSPEC_ATAK_ScreenCast", [], true];
        true
    };
    case "step": {
        _a params [["_o", objNull], ["_delta", 1]];
        if (isNull _o) exitWith { false };
        private _c = +(_o getVariable ["COMSPEC_ATAK_ScreenCast", []]);
        if ((count _c) < 5) exitWith { false };
        if ((_c select 2) isEqualTo "ACADEMY") exitWith {
            if ((_c select 0) isNotEqualTo player && {isNull (getAssignedCuratorLogic player)}) exitWith {
                ["INFO", format ["Seul %1, qui diffuse la leçon, tourne les pages", _c select 1], 3, 20] call comspec_atak_native_fnc_notify;
                false
            };
            private _n = count ((_o getVariable ["COMSPEC_ATAK_ScreenAcademy", []]) param [3, []]);
            if (_n < 1) exitWith { false };
            private _i = (((_c select 4) + _delta) max 0) min (_n - 1);
            _c set [4, _i];
            _o setVariable ["COMSPEC_ATAK_ScreenCast", _c, true];
            // Instructeur au menu ACE : son téléphone suit la page.
            if ((_c select 0) isEqualTo player) then { ["follow", [_c param [5, ""], _i]] call comspec_atak_native_fnc_academy; };
            true
        };
        // Google : la navigation est celle du deck synchronisé de tous (Overwatch), l'écran suit.
        if ((_c select 2) isEqualTo "GOOGLE") exitWith { ["step", _delta] call comspec_atak_native_fnc_briefingAction; true };
        private _n = count (_c select 3);
        if (_n < 1) exitWith { false };
        _c set [4, (((_c select 4) + _delta) mod _n + _n) mod _n];
        _o setVariable ["COMSPEC_ATAK_ScreenCast", _c, true];
        true
    };
    case "sync": {
        private _d = call _myDeck;
        if ((count _d) isEqualTo 0) exitWith { false };
        {
            private _c = _x getVariable ["COMSPEC_ATAK_ScreenCast", []];
            // Leçon Academy diffusée : ce n'est pas le briefing, elle ne suit pas mes diapositives.
            if ((_c param [0, objNull]) isEqualTo player && {(_c select 2) isNotEqualTo "ACADEMY"} && {[_d select 0, _d select 1, _d select 2] isNotEqualTo [_c select 2, _c select 3, _c select 4]}) then {
                _x setVariable ["COMSPEC_ATAK_ScreenCast", [player, name player, _d select 0, _d select 1, _d select 2], true];
            };
        } forEach ((missionNamespace getVariable ["COMSPEC_ATAK_Screens", []]) select { !isNull _x });
        true
    };
    case "tick": {
        if (!hasInterface) exitWith { false };
        disableSerialization;
        private _cache = uiNamespace getVariable ["COMSPEC_ATAK_ScreenTex", createHashMap];
        uiNamespace setVariable ["COMSPEC_ATAK_ScreenTex", _cache];
        private _bridge = [] call comspec_atak_native_fnc_bridge;
        {
            private _o = _x;
            // Actions sur l'objet, posées une fois par poste.
            if !(_o getVariable ["COMSPEC_ATAK_ScreenAct", false]) then {
                _o setVariable ["COMSPEC_ATAK_ScreenAct", true];
                ["actions", _o] call _fn;
            };
        private _cfg = _o getVariable ["COMSPEC_ATAK_Screen", []];
        private _sel = [_o, _cfg param [3, 0]] call _face;
        if ((_o getVariable ["COMSPEC_ATAK_ScreenSel", _sel]) isNotEqualTo _sel) then {
            _o setVariable ["COMSPEC_ATAK_ScreenCur", ""];
            _o setVariable ["COMSPEC_ATAK_ScreenOrig", nil];
        };
            private _c = _o getVariable ["COMSPEC_ATAK_ScreenCast", []];
            // Toile que plus aucun projecteur n'éclaire : diffusion gardée, image retirée (toile blanche ou enroulée).
            if !([_o] call _lit) then { _c = []; };
            // Texture voulue : "" = celle d'origine.
            private _want = "";
            // Leçon Academy : page dessinée en texte par ce poste (pas d'image à télécharger).
            private _acad = (count _c) >= 5 && {(_c select 2) isEqualTo "ACADEMY"};
            // Diapositive pas (encore) affichable : la raison s'écrit sur l'écran, jamais un écran noir muet.
            private _status = "";
            if ((count _c) >= 5) then {
                _want = "#(rgb,8,8,3)color(0,0,0,1)";
                if (!_bridge && {!_acad}) then { _status = "Diapositive invisible sur ce poste : COMSPEC Link n'est pas chargé."; };
                if (_bridge && {!_acad}) then {
                    _status = "En attente de la diapositive du diffuseur…";
                    // Source : adresse Athena, ou « local:<chemin> » de la diapositive Google téléchargée par COMSPEC Link.
                    private _key = "";
                    private _id = "g";
                    if ((_c select 2) isEqualTo "GOOGLE") then {
                        private _gp = missionNamespace getVariable ["COMSPEC_GoogleBriefingPath", ""];
                        if (_gp isNotEqualTo "") then { _key = "local:" + _gp; };
                    } else {
                        private _slide = (_c select 3) param [_c select 4, []];
                        _key = _slide param [2, ""];
                        _id = str (_slide param [0, 0]);
                    };
                    if (_key isNotEqualTo "") then {
                        // Cache : texture posable (chemin lisible ou "WEB:<source>"), ou heure d'un échec (nouvel essai après 60 s).
                        private _p = _cache getOrDefault [_key, -1e9];
                        // Image évincée du magasin base64 (vidé au-delà de 12) : « WEB:<clé> » périmé, image relue.
                        if (_p isEqualType "" && {(_p select [0, 4]) isEqualTo "WEB:"} && {!(_key in (uiNamespace getVariable ["COMSPEC_ATAK_ScreenWeb", createHashMap]))}) then { _cache deleteAt _key; _p = -1e9; };
                        _status = "Chargement de la diapositive…";
                        if (_p isEqualType "" && {_p isNotEqualTo ""}) then {
                            _want = _p;
                            _status = "";
                        } else {
                            if !(_p isEqualType 0) then { _p = -1e9; };
                            if (diag_tickTime - _p < 60) then {
                                _status = format ["Diapositive illisible : %1. Nouvel essai dans une minute. Elle reste visible dans l'app Briefing du téléphone.", (uiNamespace getVariable ["COMSPEC_ATAK_ScreenWhy", createHashMap]) getOrDefault [_key, "image refusée par le jeu"]];
                            };
                            // Téléchargement hors de l'image en cours, un à la fois (fn_extAwait : sans figer le jeu).
                            // Verrou horodaté : libre après 30 s même si le script de téléchargement s'est arrêté.
                            if (diag_tickTime - _p > 60 && {diag_tickTime - (missionNamespace getVariable ["COMSPEC_ATAK_ScreenDl", -1e9]) > 30} && {!isNil "comspec_overwatch_connect_fnc_extResult"}) then {
                                missionNamespace setVariable ["COMSPEC_ATAK_ScreenDl", diag_tickTime];
                                [_key, _id, _cache] spawn {
                                    params ["_key", "_id", "_cache"];
                                    private _local = (_key select [0, 6]) isEqualTo "local:";
                                    private _url = [_key, ""] select _local;
                                    private _p = "";
                                    // Diapositive déjà affichée par l'app Briefing de ce téléphone (même diapositive) : son image
                                    // est reprise telle quelle. Avant, l'écran la retéléchargeait dans le même fichier, verrouillé
                                    // par Arma, et restait noir (journal du 01/11 : fichier illisible puis host_not_allowed).
                                    private _ph = if (_local) then { [] } else { (uiNamespace getVariable ["COMSPEC_ATAK_SlideCache", createHashMap]) getOrDefault [_id, []] };
                                    private _phUse = _ph param [0, ""];
                                    private _phWeb = if ((_phUse select [0, 4]) isEqualTo "WEB:") then { (uiNamespace getVariable ["COMSPEC_ATAK_SlideWeb", createHashMap]) getOrDefault [_phUse select [4], []] } else { [] };
                                    if (_local) then {
                                        _p = (((_key select [6]) splitString (toString [92])) joinString "/");
                                    } else {
                                      if (_phUse isNotEqualTo "" && {(_phUse select [0, 4]) isNotEqualTo "WEB:"}) then { _p = _phUse; } else {
                                        private _raw = [(["COMSPECExtension", "DownloadBriefingSlideImage", [_url, format ["%1_%2", _id, hashValue _url]]] call comspec_atak_native_fnc_extAwait)] call comspec_overwatch_connect_fnc_extResult;
                                        if (isNil "_raw") then { _raw = ""; };
                                        if (_raw isEqualType []) then { _raw = _raw param [0, ""]; };
                                        if !(_raw isEqualType "") then { _raw = str _raw; };
                                        private _dl = _raw splitString "|";
                                        _p = if ((_dl param [0, ""]) isEqualTo "OK") then { ((_dl param [1, ""]) splitString (toString [92])) joinString "/" } else { "" };
                                      };
                                    };
                                    private _lp = toLower _p;
                                    // Arma ne lit que JPEG et PAA sur un objet, et aucune image hors d'un PBO sans -filePatching
                                    // (journal de TTRD du 26/10 : texture [-1,-1], écran noir) : chemin gardé seulement s'il est lisible.
                                    private _use = if ((_lp select [((count _lp) - 4) max 0]) in [".jpg", "jpeg", ".paa"]) then { [_p] call comspec_atak_native_fnc_texUsable } else { "" };
                                    private _how = "image posée sur l'objet";
                                    if (_use isEqualTo "" && {(count _phWeb) >= 2}) then {
                                        private _store = uiNamespace getVariable ["COMSPEC_ATAK_ScreenWeb", createHashMap];
                                        if (count _store > 12) then { _store = createHashMap; };
                                        _store set [_key, _phWeb];
                                        uiNamespace setVariable ["COMSPEC_ATAK_ScreenWeb", _store];
                                        _use = "WEB:" + _key;
                                        _how = "image reprise de l'app Briefing (navigateur intégré)";
                                    };
                                    if (_use isEqualTo "") then {
                                        // Repli, comme l'app Briefing : l'image en base64 montrée par le navigateur intégré.
                                        ([[_url, _key] select _local, format ["scr_%1", hashValue _key]] call comspec_atak_native_fnc_slideWebData) params ["_mime", "_b64", "_why"];
                                        if (_why isEqualTo "") then {
                                            private _store = uiNamespace getVariable ["COMSPEC_ATAK_ScreenWeb", createHashMap];
                                            if (count _store > 12) then { _store = createHashMap; };
                                            _store set [_key, [_mime, _b64]];
                                            uiNamespace setVariable ["COMSPEC_ATAK_ScreenWeb", _store];
                                            _use = "WEB:" + _key;
                                            _how = "affichée par le navigateur intégré (texture refusée par Arma)";
                                        } else {
                                            _how = format ["écran noir : Arma refuse l'image (hors PBO) et %1", _why];
                                            private _whyMap = uiNamespace getVariable ["COMSPEC_ATAK_ScreenWhy", createHashMap];
                                            _whyMap set [_key, _why];
                                            uiNamespace setVariable ["COMSPEC_ATAK_ScreenWhy", _whyMap];
                                        };
                                    };
                                    ["INFO", "BRIEFING", format ["Écran : diapositive %1, source %2, fichier %3, %4", _id, _key, [_p, "illisible"] select (_p isEqualTo ""), _how]] call comspec_atak_native_fnc_log;
                                    _cache set [_key, [_use, diag_tickTime] select (_use isEqualTo "")];
                                    missionNamespace setVariable ["COMSPEC_ATAK_ScreenDl", -1e9];
                                };
                            };
                        };
                    };
                };
            };
            // Diapositive par le navigateur intégré : texture UI propre à l'objet, page rechargée quand la diapositive change.
            private _web = "";
            private _uiName = "";
            if ((_want select [0, 4]) isEqualTo "WEB:" || {_acad} || {_status isNotEqualTo ""}) then {
                if (!_acad && {_status isEqualTo ""}) then { _web = _want select [4]; };
                private _ui = _o getVariable ["COMSPEC_ATAK_ScreenUi", ""];
                if (_ui isEqualTo "") then {
                    private _n = (uiNamespace getVariable ["COMSPEC_ATAK_ScreenSeq", 0]) + 1;
                    uiNamespace setVariable ["COMSPEC_ATAK_ScreenSeq", _n];
                    _ui = format ["comspec_atak_scr_%1", _n];
                    _o setVariable ["COMSPEC_ATAK_ScreenUi", _ui];
                };
                // Nom distinct pour Academy : pas de navigateur réutilisé d'une diapositive précédente.
                _uiName = [[_ui, _ui + "m"] select (_status isNotEqualTo ""), _ui + "a"] select _acad;
                // Texture en puissances de 2 : Arma refusait 1280 x 720 (« Cannot load texture », écran noir de la 2.9.31).
                _want = format ["#(rgb,2048,1024,1)ui(""COMSPEC_RscScreenSlideTexture"",""%1"")", _uiName];
                // Un écran en attente de son display à la fois (comme les écrans TOC) : les autres attendent leur tour,
                // sinon un display pourrait être rangé sous le nom d'un autre écran. Attente libérée au bout de 4 s.
                private _pend = uiNamespace getVariable ["COMSPEC_ATAK_ScreenPending", []];
                if ((count _pend) >= 3 && {(_pend select 0) isNotEqualTo _o} && {!isNull (_pend select 0)} && {diag_tickTime - (_pend select 2) < 4}
                    && {(_o getVariable ["COMSPEC_ATAK_ScreenCur", ""]) isNotEqualTo _want}) then { _want = _o getVariable ["COMSPEC_ATAK_ScreenCur", ""]; _web = ""; _uiName = ""; };
            };
            private _cur = _o getVariable ["COMSPEC_ATAK_ScreenCur", ""];
            if (_want isNotEqualTo _cur) then {
                // Texture UI posée : son display arrive dans « load ».
                if (_uiName isNotEqualTo "") then {
                    if !(uiNamespace getVariable ["COMSPEC_ATAK_ScreenPreload", false]) then {
                        uiNamespace setVariable ["COMSPEC_ATAK_ScreenPreload", true];
                        disableSerialization;
                        private _tmp = findDisplay 46 createDisplay "COMSPEC_RscScreenSlideTexture";
                        if (!isNull _tmp) then { _tmp closeDisplay 0; };
                    };
                    uiNamespace setVariable ["COMSPEC_ATAK_ScreenPending", [_o, _uiName, diag_tickTime]];
                    _o setVariable ["COMSPEC_ATAK_ScreenWebShown", ""];
                    _o setVariable ["COMSPEC_ATAK_ScreenAcadShown", []];
                };
                // Texture d'origine gardée avant le premier changement, remise à la fin de la diffusion.
                if (isNil { _o getVariable "COMSPEC_ATAK_ScreenOrig" }) then { _o setVariable ["COMSPEC_ATAK_ScreenOrig", ["orig", [_o, _sel]] call comspec_atak_native_fnc_tocScreen]; };
                ["INFO", "SCREEN", format ["Écran %1 (%2), dalle %3 (%4) : %5", typeOf _o, (_o getVariable ["COMSPEC_ATAK_Screen", []]) param [0, "?"], _sel, (getArray (configOf _o >> "hiddenSelections")) param [_sel, "?"],
                    switch (true) do {
                        case (_want isEqualTo ""): { "fin de diffusion, texture d'origine remise" };
                        case (_acad): { format ["leçon Academy, texture UI %1", _uiName] };
                        case (_uiName isNotEqualTo ""): { format ["diapositive par le navigateur intégré, texture UI %1", _uiName] };
                        case ((_want select [0, 2]) isEqualTo "#("): { "écran noir en attendant l'image (téléchargement par COMSPEC Link, ou COMSPEC Link absent)" };
                        default { format ["image posée : %1", _want] };
                    }]] call comspec_atak_native_fnc_log;
                ["paint", [_o, _sel, [_want, _o getVariable ["COMSPEC_ATAK_ScreenOrig", ""]] select (_want isEqualTo "")]] call comspec_atak_native_fnc_tocScreen;
                // Dalle préparée pour l'image (matière d'écran, dalles superposées du grand écran masquées), remise à la fin.
                ["surface", [_o, _sel, _want isNotEqualTo ""]] call comspec_atak_native_fnc_tocScreen;
                _o setVariable ["COMSPEC_ATAK_ScreenCur", _want];
                _o setVariable ["COMSPEC_ATAK_ScreenSel", _sel];
                // Fin de diffusion sur un écran TOC : la texture d'origine vient d'écraser la carte du TOC, que fn_tocScreen
                // croyait encore posée (écran noir jusqu'au rechargement). Il la reposera à son prochain passage.
                if (_want isEqualTo "" && {(count (_o getVariable ["COMSPEC_ATAK_TocScr", []])) >= 8}) then { _o setVariable ["COMSPEC_ATAK_TocTex", ""]; };
            };
            if (_acad && {_uiName isNotEqualTo ""}) then {
                private _disp = (uiNamespace getVariable ["COMSPEC_ATAK_ScreenDisps", createHashMap]) getOrDefault [_uiName, displayNull];
                if (!isNull _disp) then {
                    private _les = _o getVariable ["COMSPEC_ATAK_ScreenAcademy", []];
                    private _sig = [_c param [5, ""], _c select 4, _les param [0, ""], count (_les param [3, []])];
                    if ((_o getVariable ["COMSPEC_ATAK_ScreenAcadShown", []]) isNotEqualTo _sig) then {
                        _o setVariable ["COMSPEC_ATAK_ScreenAcadShown", _sig];
                        _o setVariable ["COMSPEC_ATAK_ScreenAcadN", 0];
                        ["academyDraw", [_disp, _o]] call _fn;
                    };
                    // Quelques recopies après le dessin (une texture UI recopiée trop tôt reste vide), puis plus rien.
                    private _k = _o getVariable ["COMSPEC_ATAK_ScreenAcadN", 0];
                    if (_k < 3) then { _o setVariable ["COMSPEC_ATAK_ScreenAcadN", _k + 1]; displayUpdate _disp; };
                };
            };
            if (_status isNotEqualTo "" && {_uiName isNotEqualTo ""}) then {
                private _disp = (uiNamespace getVariable ["COMSPEC_ATAK_ScreenDisps", createHashMap]) getOrDefault [_uiName, displayNull];
                if (!isNull _disp) then {
                    private _msgC = _disp getVariable ["COMSPEC_ATAK_ScreenMsg", controlNull];
                    if (isNull _msgC) then {
                        _msgC = _disp ctrlCreate ["COMSPEC_RscStructuredText", -1];
                        _msgC ctrlSetPosition [0.06, 0.36, 0.88, 0.3];
                        _msgC ctrlCommit 0;
                        _disp setVariable ["COMSPEC_ATAK_ScreenMsg", _msgC];
                    };
                    if ((_o getVariable ["COMSPEC_ATAK_ScreenMsgShown", ""]) isNotEqualTo _status) then {
                        _o setVariable ["COMSPEC_ATAK_ScreenMsgShown", _status];
                        _o setVariable ["COMSPEC_ATAK_ScreenMsgN", 0];
                        _msgC ctrlSetStructuredText parseText ((format ["<t align='center' size='2.2' color='#cfd8d3'>%1</t>", _status]) call comspec_atak_native_fnc_tr);
                    };
                    private _k = _o getVariable ["COMSPEC_ATAK_ScreenMsgN", 0];
                    if (_k < 3) then { _o setVariable ["COMSPEC_ATAK_ScreenMsgN", _k + 1]; displayUpdate _disp; };
                };
            };
            if (_web isNotEqualTo "") then {
                private _disp = (uiNamespace getVariable ["COMSPEC_ATAK_ScreenDisps", createHashMap]) getOrDefault [_uiName, displayNull];
                if (!isNull _disp) then {
                    if ((_o getVariable ["COMSPEC_ATAK_ScreenWebShown", ""]) isNotEqualTo _web) then {
                        _o setVariable ["COMSPEC_ATAK_ScreenWebShown", _web];
                        _o setVariable ["COMSPEC_ATAK_ScreenWebAt", diag_tickTime];
                        _o setVariable ["COMSPEC_ATAK_ScreenWebN", 0];
                        // Image absente (magasin vidé) : page non chargée, nouvel essai au prochain passage.
                        if !(["web", [_disp, _web]] call _fn) then { _o setVariable ["COMSPEC_ATAK_ScreenWebShown", ""]; };
                    };
                    // Recopier trop tôt fige une image vide. On attend que la page ait l'image, puis quelques copies, puis on fige.
                    private _wb = _disp getVariable ["COMSPEC_ATAK_ScreenWb", controlNull];
                    private _webAge = diag_tickTime - (_o getVariable ["COMSPEC_ATAK_ScreenWebAt", -1e9]);
                    // Repli data: de fn_slideWebShow (page locale non chargée) : le drapeau ne passe jamais à vrai, on recopie après 6 s.
                    private _ready = (!isNull _wb) && {(_wb getVariable ["COMSPEC_ATAK_SlideWebOk", false] && {_webAge > 0.8}) || {_webAge > 6}};
                    if (_ready && {(_o getVariable ["COMSPEC_ATAK_ScreenWebN", 0]) < 1}) then {
                        _o setVariable ["COMSPEC_ATAK_ScreenWebN", 1];
                        [_disp, _wb] spawn {
                            params ["_d", "_wb"];
                            disableSerialization;
                            for "_i" from 1 to 5 do {
                                if (isNull _d) exitWith {};
                                displayUpdate _d;
                                uiSleep 0.05;
                            };
                            if (!isNull _wb) then {
                                _wb setVariable ["COMSPEC_ATAK_ScreenFrozen", true];
                                _wb ctrlWebBrowserAction ["StopBrowser"];
                            };
                        };
                    };
                };
            };
        } forEach (call {
            private _paint = (missionNamespace getVariable ["COMSPEC_ATAK_Screens", []]) select { !isNull _x && {(player distance _x) < 300} };
            private _live = call _myDeck;
            if ((count _live) isEqualTo 0) exitWith { _paint };
            private _reg = missionNamespace getVariable ["COMSPEC_ATAK_Screens", []];
            {
                if (_x isEqualType [] && {(count _x) >= 1}) then {
                    private _bo = _x param [0, objNull];
                    if (!isNull _bo && {(player distance _bo) < 300} && {!(_bo in _paint)} && {!(_bo in _reg)}) then {
                        _bo setVariable ["COMSPEC_ATAK_Screen", ["Écran", 3000, "", _x param [1, 0], false]];
                        private _have = _bo getVariable ["COMSPEC_ATAK_ScreenCast", []];
                        if ([_live select 0, _live select 2] isNotEqualTo [_have param [2, ""], _have param [4, -1]]) then {
                            _bo setVariable ["COMSPEC_ATAK_ScreenCast", [objNull, "", _live select 0, _live select 1, _live select 2]];
                        };
                        _paint pushBack _bo;
                    };
                };
            } forEach (missionNamespace getVariable ["COMSPEC_BriefingScreens", []]);
            _paint
        });
        // Écrans retirés (Zeus « Retirer l'écran ») : texture d'origine remise une fois sur ce poste.
        {
            if ((_x getVariable ["COMSPEC_ATAK_ScreenCur", ""]) isNotEqualTo "") then {
                ["paint", [_x, _x getVariable ["COMSPEC_ATAK_ScreenSel", 0], _x getVariable ["COMSPEC_ATAK_ScreenOrig", ""]]] call comspec_atak_native_fnc_tocScreen;
                ["surface", [_x, _x getVariable ["COMSPEC_ATAK_ScreenSel", 0], false]] call comspec_atak_native_fnc_tocScreen;
                _x setVariable ["COMSPEC_ATAK_ScreenCur", ""];
            };
        } forEach ((missionNamespace getVariable ["COMSPEC_ATAK_ScreensOff", []]) select { !isNull _x });
        true
    };
    case "load": {
        // Display de la texture UI d'un écran : rangé par nom dans uiNamespace (un display ne va pas dans une variable d'objet).
        disableSerialization;
        private _p = uiNamespace getVariable ["COMSPEC_ATAK_ScreenPending", []];
        if (isNull _a || {(count _p) < 2}) exitWith { false };
        private _disps = uiNamespace getVariable ["COMSPEC_ATAK_ScreenDisps", createHashMap];
        { if (isNull _y) then { _disps deleteAt _x; }; } forEach +_disps;
        _disps set [_p select 1, _a];
        uiNamespace setVariable ["COMSPEC_ATAK_ScreenDisps", _disps];
        uiNamespace setVariable ["COMSPEC_ATAK_ScreenPending", []];
        ["INFO", "SCREEN", format ["Écran %1 : texture UI %2 chargée", typeOf (_p select 0), _p select 1]] call comspec_atak_native_fnc_log;
        true
    };
    case "academyDraw": {
        // Leçon Academy en texte sur la texture 1280 x 720 : tableau sombre, titre, texte, avancée de la leçon.
        _a params ["_d", "_o"];
        disableSerialization;
        if (isNull _d || {isNull _o}) exitWith { false };
        private _c = _o getVariable ["COMSPEC_ATAK_ScreenCast", []];
        private _les = _o getVariable ["COMSPEC_ATAK_ScreenAcademy", []];
        private _ui = _d getVariable ["COMSPEC_ATAK_AcadUi", []];
        if ((count _ui) isEqualTo 0) then {
            private _mk = {
                params ["_cls", "_pos", ["_bg", [0, 0, 0, 0]]];
                private _k = _d ctrlCreate [_cls, -1];
                _k ctrlSetPosition _pos;
                _k ctrlSetBackgroundColor _bg;
                _k ctrlCommit 0;
                _k
            };
            ["RscText", [0, 0, 1, 1], [0.07, 0.12, 0.10, 1]] call _mk;
            ["RscText", [0, 0, 1, 0.11], [0.05, 0.085, 0.075, 1]] call _mk;
            ["RscText", [0.04, 0.105, 0.92, 0.004], [0.88, 0.69, 0.25, 1]] call _mk;
            _ui = [
                ["COMSPEC_RscStructuredText", [0.04, 0.025, 0.66, 0.08]] call _mk,
                ["COMSPEC_RscStructuredText", [0.62, 0.025, 0.34, 0.08]] call _mk,
                ["COMSPEC_RscStructuredText", [0.04, 0.15, 0.92, 0.12]] call _mk,
                ["COMSPEC_RscStructuredText", [0.04, 0.28, 0.92, 0.6]] call _mk,
                ["RscText", [0.04, 0.925, 0.92, 0.008], [1, 1, 1, 0.12]] call _mk,
                ["RscText", [0.04, 0.925, 0, 0.008], [0.88, 0.69, 0.25, 1]] call _mk,
                ["COMSPEC_RscStructuredText", [0.04, 0.94, 0.92, 0.05]] call _mk
            ];
            _d setVariable ["COMSPEC_ATAK_AcadUi", _ui];
        };
        _ui params ["_course", "_count", "_title", "_body", "", "_bar", "_foot"];
        private _esc = { params ["_t"]; if !(_t isEqualType "") then { _t = str _t; }; { _t = [_t, _x select 0, _x select 1] call CBA_fnc_replace; } forEach [["&", "&amp;"], ["<", "&lt;"], [">", "&gt;"]]; _t };
        private _pages = _les param [3, []];
        private _i = _c param [4, 0];
        // Leçon pas encore reçue (repère différent) : on l'attend sans afficher l'ancienne.
        if ((count _pages) isEqualTo 0 || {(_les param [0, ""]) isNotEqualTo (_c param [5, ""])}) exitWith {
            _title ctrlSetStructuredText parseText (("<t size='2.4' font='COMSPEC_BarlowBold' color='#e8eee6'>Leçon Academy</t>") call comspec_atak_native_fnc_tr);
            _body ctrlSetStructuredText parseText (("<t size='1.6' color='#9fb3aa'>Chargement de la leçon…</t>") call comspec_atak_native_fnc_tr);
            { _x ctrlSetStructuredText parseText (("") call comspec_atak_native_fnc_tr); } forEach [_course, _count, _foot];
            true
        };
        _i = (_i max 0) min ((count _pages) - 1);
        (_pages select _i) params ["", ["_pt", ""], ["_pb", ""]];
        _course ctrlSetStructuredText parseText format ["<t size='1.5' color='#9fb3aa'>%1</t><t size='1.5' color='#e0b040'>   %2</t>", ["COMSPEC Academy" call comspec_atak_native_fnc_tr] call _esc, [_les param [1, ""]] call _esc]; // i18n:brut (texte du cours, déjà dans la langue choisie)
        _count ctrlSetStructuredText parseText format ["<t align='right' size='1.5' color='#e8eee6'>%1</t>", [format [("Page %1 sur %2") call comspec_atak_native_fnc_tr, _i + 1, count _pages]] call _esc]; // i18n:brut (texte du cours, déjà dans la langue choisie)
        _title ctrlSetStructuredText parseText format ["<t size='2.6' font='COMSPEC_BarlowBold' color='#f2f5ee'>%1</t>", [_pt] call _esc]; // i18n:brut (texte du cours, déjà dans la langue choisie)
        // Taille du texte selon sa longueur : une page longue reste entière sur l'écran.
        private _sz = if ((count _pb) < 450) then { 1.75 } else { [1.2, 1.45] select ((count _pb) < 900) };
        _body ctrlSetStructuredText parseText format ["<t size='%1' color='#e8eee6'>%2</t>", _sz, [([_pb] call _esc), "\n", "<br/>"] call CBA_fnc_replace]; // i18n:brut (texte du cours, déjà dans la langue choisie)
        _bar ctrlSetPosition [0.04, 0.925, 0.92 * (_i + 1) / (count _pages), 0.008];
        _bar ctrlCommit 0;
        _foot ctrlSetStructuredText parseText format ["<t size='1.25' color='#9fb3aa'>%1</t>", [format [("%1 · présenté par %2") call comspec_atak_native_fnc_tr, _les param [2, ""], _c param [1, ""]]] call _esc]; // i18n:brut (texte du cours, déjà dans la langue choisie)
        true
    };
    case "web": {
        // Diapositive (JPEG en base64) centrée sur fond noir, sur toute la texture (fn_slideWebShow).
        _a params ["_d", ["_url", ""]];
        disableSerialization;
        if (isNull _d) exitWith { false };
        private _wb = _d getVariable ["COMSPEC_ATAK_ScreenWb", controlNull];
        if (isNull _wb) then {
            _wb = _d ctrlCreate ["COMSPEC_RscWebBrowser", -1];
            _wb ctrlSetPosition [0, 0, 1, 1];
            _wb ctrlCommit 0;
            _wb ctrlWebBrowserAction ["SetBrowserInternalSize", 1280, 720];
            _d setVariable ["COMSPEC_ATAK_ScreenWb", _wb];
        };
        if (_wb getVariable ["COMSPEC_ATAK_ScreenFrozen", false]) then {
            _wb setVariable ["COMSPEC_ATAK_ScreenFrozen", false];
            _wb ctrlWebBrowserAction ["ResumeBrowser"];
        };
        ((uiNamespace getVariable ["COMSPEC_ATAK_ScreenWeb", createHashMap]) getOrDefault [_url, ["jpeg", ""]]) params ["_mime", "_b64"];
        if (isNull _wb || {_b64 isEqualTo ""}) exitWith { false };
        [_wb, _mime, _b64] call comspec_atak_native_fnc_slideWebShow;
        true
    };
    case "actions": {
        private _o = _a;
        private _cfg = _o getVariable ["COMSPEC_ATAK_Screen", []];
        if !(_cfg param [4, true]) exitWith { false };
        // ACE : une seule racine « Écran COMSPEC » par objet, la même que les écrans TOC (fn_tocScreen aceObj). Avant,
        // l'écran recevait ici sa propre racine en plus de celle de sa classe (et de l'« Écran TOC ») : menus en double.
        if (!isNil "ace_interact_menu_fnc_createAction") exitWith { ["aceObj", _o] call comspec_atak_native_fnc_tocScreen };
        // Molette : écran TOC ou classe d'écran du jeu, les actions de l'écran TOC couvrent déjà les diapositives.
        if ((count (_o getVariable ["COMSPEC_ATAK_TocScr", []])) >= 8) exitWith { false };
        private _cond = { private _c = _target getVariable ["COMSPEC_ATAK_ScreenCast", []]; private _s = (_target getVariable ["COMSPEC_ATAK_Screen", []]) param [2, ""]; (count _c) >= 5 && {_s isEqualTo "" || {_s isEqualTo str side group _player}} };
        // Molette, une fois par objet.
        if (_o getVariable ["COMSPEC_ATAK_ScreenWheel", false]) exitWith { false };
        _o setVariable ["COMSPEC_ATAK_ScreenWheel", true];
        _o addAction [format ["<t color='#7fd0b0'>%1</t>", ("Écran : diapositive suivante") call comspec_atak_native_fnc_tr], { ["step", [_this select 0, 1]] call comspec_atak_native_fnc_screenCast; }, nil, 1.4, false, true, "", "private _player = _this; call (missionNamespace getVariable 'COMSPEC_ATAK_ScreenCond')", 4];
        _o addAction [format ["<t color='#7fd0b0'>%1</t>", ("Écran : diapositive précédente") call comspec_atak_native_fnc_tr], { ["step", [_this select 0, -1]] call comspec_atak_native_fnc_screenCast; }, nil, 1.39, false, true, "", "private _player = _this; call (missionNamespace getVariable 'COMSPEC_ATAK_ScreenCond')", 4];
        missionNamespace setVariable ["COMSPEC_ATAK_ScreenCond", _cond];
        true
    };
    default { false };
};
