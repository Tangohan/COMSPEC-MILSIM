/*
    Écrans COMSPEC (idée d'un joueur, 25/10, WulffOperator) : les diapositives de l'app Briefing diffusées sur des écrans
    du jeu (TV, ordinateur, projecteur, tableau). Un écran est un objet désigné par le module Eden / Zeus « ATAK · Écran
    COMSPEC » (fn_moduleScreen) ou par script ; le téléphone le détecte à portée et le pilote, ACE (ou la molette) aussi.
    Chaque joueur affiche lui-même l'image sur l'objet (setObjectTexture local) : diapositive Athena téléchargée par son
    COMSPEC Link, ou la sienne du Google Slides synchronisé. Sans COMSPEC Link, l'écran reste noir pour lui.
    Arma refuse toute image hors d'un PBO (journal de TTRD du 26/10 : texture [-1,-1], écran noir) : comme dans l'app
    Briefing, la copie JPEG du site est lue en base64 par COMSPEC Link (ImageDataChunk, 2.0.96+) et montrée par un
    navigateur intégré (CT_WEBBROWSER) dans une texture UI posée sur l'objet
    (#(rgb,1280,720,1)ui("COMSPEC_RscScreenSlideTexture", nom unique)). Le chemin direct reste utilisé quand Arma le lit
    (-filePatching).

    Variables publiques de l'objet :
      COMSPEC_ATAK_Screen     [nom, portée m, camp ("" = tous), index de texture, contrôle ACE]
      COMSPEC_ATAK_ScreenCast [diffuseur, nom du diffuseur, source "ATHENA" | "GOOGLE", deck [[id, titre, url]...], index]
                              ([] = rien n'est diffusé, l'écran garde sa texture d'origine)
    Registre : missionNamespace COMSPEC_ATAK_Screens (objets), tenu par le serveur.

    Modes :
      ["add", [objet, nom, portée, camp, index, ace]]   enregistre un écran (serveur ; ailleurs, la demande part au serveur)
      ["remove", objet]                                  retire l'écran (serveur ; ailleurs, demande au serveur)
      ["list"]                    écrans à portée de mon téléphone, de mon camp, triés par distance
      ["cast", objet]             diffuse ma diapositive en cours sur l'écran ("castAll" : tous les écrans à portée)
      ["stop", objet]             arrête la diffusion        ["step", [objet, ±1]] : diapositive précédente / suivante
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
    case "cast": {
        if (isNull _a) exitWith { false };
        if ((count (_a getVariable ["COMSPEC_ATAK_Screen", []])) < 5) exitWith { false };
        private _d = call _myDeck;
        if ((count _d) isEqualTo 0) exitWith {
            ["WARNING", "Aucune diapositive à diffuser : ouvrez une présentation dans l'onglet DIAPOSITIVES", 4, 20] call comspec_atak_native_fnc_notify;
            false
        };
        _d params ["_src", "_deck", "_idx"];
        _a setVariable ["COMSPEC_ATAK_ScreenCast", [player, name player, _src, _deck, _idx], true];
        true
    };
    case "stop": {
        if (isNull _a) exitWith { false };
        _a setVariable ["COMSPEC_ATAK_ScreenCast", [], true];
        true
    };
    case "step": {
        _a params [["_o", objNull], ["_delta", 1]];
        if (isNull _o) exitWith { false };
        private _c = +(_o getVariable ["COMSPEC_ATAK_ScreenCast", []]);
        if ((count _c) < 5) exitWith { false };
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
            if ((_c param [0, objNull]) isEqualTo player && {[_d select 0, _d select 1, _d select 2] isNotEqualTo [_c select 2, _c select 3, _c select 4]}) then {
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
            private _sel = _cfg param [3, 0];
            private _c = _o getVariable ["COMSPEC_ATAK_ScreenCast", []];
            // Texture voulue : "" = celle d'origine.
            private _want = "";
            if ((count _c) >= 5) then {
                _want = "#(rgb,8,8,3)color(0,0,0,1)";
                if (_bridge) then {
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
                        if (_p isEqualType "" && {_p isNotEqualTo ""}) then {
                            _want = _p;
                        } else {
                            if !(_p isEqualType 0) then { _p = -1e9; };
                            // Téléchargement hors de l'image en cours, un à la fois (fn_extAwait : sans figer le jeu).
                            // Verrou horodaté : libre après 30 s même si le script de téléchargement s'est arrêté.
                            if (diag_tickTime - _p > 60 && {diag_tickTime - (missionNamespace getVariable ["COMSPEC_ATAK_ScreenDl", -1e9]) > 30} && {!isNil "comspec_overwatch_connect_fnc_extResult"}) then {
                                missionNamespace setVariable ["COMSPEC_ATAK_ScreenDl", diag_tickTime];
                                [_key, _id, _cache] spawn {
                                    params ["_key", "_id", "_cache"];
                                    private _local = (_key select [0, 6]) isEqualTo "local:";
                                    private _url = [_key, ""] select _local;
                                    private _p = "";
                                    if (_local) then {
                                        _p = (((_key select [6]) splitString (toString [92])) joinString "/");
                                    } else {
                                        private _raw = [(["COMSPECExtension", "DownloadBriefingSlideImage", [_url, format ["%1_%2", _id, hashValue _url]]] call comspec_atak_native_fnc_extAwait)] call comspec_overwatch_connect_fnc_extResult;
                                        if (isNil "_raw") then { _raw = ""; };
                                        if (_raw isEqualType []) then { _raw = _raw param [0, ""]; };
                                        if !(_raw isEqualType "") then { _raw = str _raw; };
                                        private _dl = _raw splitString "|";
                                        _p = if ((_dl param [0, ""]) isEqualTo "OK") then { ((_dl param [1, ""]) splitString (toString [92])) joinString "/" } else { "" };
                                    };
                                    private _lp = toLower _p;
                                    // Arma ne lit que JPEG et PAA sur un objet, et aucune image hors d'un PBO sans -filePatching
                                    // (journal de TTRD du 26/10 : texture [-1,-1], écran noir) : chemin gardé seulement s'il est lisible.
                                    private _use = if ((_lp select [((count _lp) - 4) max 0]) in [".jpg", "jpeg", ".paa"]) then { [_p] call comspec_atak_native_fnc_texUsable } else { "" };
                                    private _how = "image posée sur l'objet";
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
                                            _how = "écran noir : Arma refuse l'image (hors PBO) et " + _why;
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
            if ((_want select [0, 4]) isEqualTo "WEB:") then {
                _web = _want select [4];
                private _ui = _o getVariable ["COMSPEC_ATAK_ScreenUi", ""];
                if (_ui isEqualTo "") then {
                    private _n = (uiNamespace getVariable ["COMSPEC_ATAK_ScreenSeq", 0]) + 1;
                    uiNamespace setVariable ["COMSPEC_ATAK_ScreenSeq", _n];
                    _ui = format ["comspec_atak_scr_%1", _n];
                    _o setVariable ["COMSPEC_ATAK_ScreenUi", _ui];
                };
                _want = format ["#(rgb,1280,720,1)ui(""COMSPEC_RscScreenSlideTexture"",""%1"")", _ui];
                // Un écran en attente de son display à la fois (comme les écrans TOC) : les autres attendent leur tour,
                // sinon un display pourrait être rangé sous le nom d'un autre écran. Attente libérée au bout de 4 s.
                private _pend = uiNamespace getVariable ["COMSPEC_ATAK_ScreenPending", []];
                if ((count _pend) >= 3 && {(_pend select 0) isNotEqualTo _o} && {!isNull (_pend select 0)} && {diag_tickTime - (_pend select 2) < 4}
                    && {(_o getVariable ["COMSPEC_ATAK_ScreenCur", ""]) isNotEqualTo _want}) then { _want = _o getVariable ["COMSPEC_ATAK_ScreenCur", ""]; _web = ""; };
            };
            private _cur = _o getVariable ["COMSPEC_ATAK_ScreenCur", ""];
            if (_want isNotEqualTo _cur) then {
                // Texture UI posée : son display arrive dans « load ».
                if (_web isNotEqualTo "") then {
                    uiNamespace setVariable ["COMSPEC_ATAK_ScreenPending", [_o, _o getVariable ["COMSPEC_ATAK_ScreenUi", ""], diag_tickTime]];
                    _o setVariable ["COMSPEC_ATAK_ScreenWebShown", ""];
                };
                // Texture d'origine gardée avant le premier changement, remise à la fin de la diffusion.
                if (isNil { _o getVariable "COMSPEC_ATAK_ScreenOrig" }) then { _o setVariable ["COMSPEC_ATAK_ScreenOrig", (getObjectTextures _o) param [_sel, ""]]; };
                _o setObjectTexture [_sel, [_want, _o getVariable ["COMSPEC_ATAK_ScreenOrig", ""]] select (_want isEqualTo "")];
                _o setVariable ["COMSPEC_ATAK_ScreenCur", _want];
                _o setVariable ["COMSPEC_ATAK_ScreenSel", _sel];
            };
            if (_web isNotEqualTo "") then {
                private _disp = (uiNamespace getVariable ["COMSPEC_ATAK_ScreenDisps", createHashMap]) getOrDefault [_o getVariable ["COMSPEC_ATAK_ScreenUi", ""], displayNull];
                if (!isNull _disp) then {
                    if ((_o getVariable ["COMSPEC_ATAK_ScreenWebShown", ""]) isNotEqualTo _web) then {
                        _o setVariable ["COMSPEC_ATAK_ScreenWebShown", _web];
                        _o setVariable ["COMSPEC_ATAK_ScreenWebAt", diag_tickTime];
                        ["web", [_disp, _web]] call _fn;
                    };
                    // Le navigateur dessine après coup : texture recopiée souvent pendant 15 s, puis toutes les 10 s si l'écran est vu.
                    private _fresh = diag_tickTime - (_o getVariable ["COMSPEC_ATAK_ScreenWebAt", -1e9]) < 15;
                    private _seen = (player distance _o) < 60 && {(worldToScreen (ASLToAGL getPosASL _o)) isNotEqualTo []};
                    if (_fresh || {_seen && {diag_tickTime - (_o getVariable ["COMSPEC_ATAK_ScreenWebUpd", -1e9]) > 10}}) then {
                        _o setVariable ["COMSPEC_ATAK_ScreenWebUpd", diag_tickTime];
                        displayUpdate _disp;
                    };
                };
            };
        } forEach ((missionNamespace getVariable ["COMSPEC_ATAK_Screens", []]) select { !isNull _x && {(player distance _x) < 300} });
        // Écrans retirés (Zeus « Retirer l'écran ») : texture d'origine remise une fois sur ce poste.
        {
            if ((_x getVariable ["COMSPEC_ATAK_ScreenCur", ""]) isNotEqualTo "") then {
                _x setObjectTexture [_x getVariable ["COMSPEC_ATAK_ScreenSel", 0], _x getVariable ["COMSPEC_ATAK_ScreenOrig", ""]];
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
        true
    };
    case "web": {
        // Diapositive (JPEG en base64) dans une page data: centrée sur fond noir, sur toute la texture.
        _a params ["_d", ["_url", ""]];
        disableSerialization;
        if (isNull _d) exitWith { false };
        private _wb = _d getVariable ["COMSPEC_ATAK_ScreenWb", controlNull];
        if (isNull _wb) then {
            _wb = _d ctrlCreate ["COMSPEC_RscWebBrowser", -1];
            _wb ctrlSetPosition [0, 0, 1, 1];
            _wb ctrlCommit 0;
            _d setVariable ["COMSPEC_ATAK_ScreenWb", _wb];
        };
        ((uiNamespace getVariable ["COMSPEC_ATAK_ScreenWeb", createHashMap]) getOrDefault [_url, ["jpeg", ""]]) params ["_mime", "_b64"];
        if (isNull _wb || {_b64 isEqualTo ""}) exitWith { false };
        // Concaténation (pas format) : l'image dépasse de loin la longueur qu'accepte format.
        private _html = "<html><body style='margin:0;background:black;height:100vh;display:flex;align-items:center;justify-content:center;overflow:hidden'><img src='data:image/" + _mime + ";base64," + _b64 + "' style='width:100%;height:100%;object-fit:contain'></body></html>";
        private _enc = controlNull ctrlWebBrowserAction ["ToBase64", _html];
        _wb ctrlWebBrowserAction ["OpenDataAsURL", if (_enc isEqualType "" && {_enc isNotEqualTo ""}) then { "data:text/html;base64," + _enc } else { "data:text/html;charset=utf-8," + _html }];
        true
    };
    case "actions": {
        private _o = _a;
        private _cfg = _o getVariable ["COMSPEC_ATAK_Screen", []];
        if !(_cfg param [4, true]) exitWith { false };
        private _cond = { private _c = _target getVariable ["COMSPEC_ATAK_ScreenCast", []]; private _s = (_target getVariable ["COMSPEC_ATAK_Screen", []]) param [2, ""]; (count _c) >= 5 && {_s isEqualTo "" || {_s isEqualTo str side group _player}} };
        if (!isNil "ace_interact_menu_fnc_createAction") then {
            private _icon = "\z\comspec_atak_native\addons\main\data\app_briefing.paa";
            private _root = ["COMSPEC_ATAK_Screen", "Écran COMSPEC", _icon, {}, { (count (_target getVariable ["COMSPEC_ATAK_Screen", []])) >= 5 }, {
                private _out = [];
                private _c = _target getVariable ["COMSPEC_ATAK_ScreenCast", []];
                private _s = (_target getVariable ["COMSPEC_ATAK_Screen", []]) param [2, ""];
                if !(_s isEqualTo "" || {_s isEqualTo str side group _player}) exitWith { _out };
                if ((count _c) >= 5) then {
                    _out pushBack [["COMSPEC_ATAK_ScreenNext", "Diapositive suivante", "", { ["step", [_target, 1]] call comspec_atak_native_fnc_screenCast; }, { true }] call comspec_atak_native_fnc_aceAction, [], _target];
                    _out pushBack [["COMSPEC_ATAK_ScreenPrev", "Diapositive précédente", "", { ["step", [_target, -1]] call comspec_atak_native_fnc_screenCast; }, { true }] call comspec_atak_native_fnc_aceAction, [], _target];
                    _out pushBack [["COMSPEC_ATAK_ScreenStop", "Arrêter la diffusion", "", { ["stop", _target] call comspec_atak_native_fnc_screenCast; }, { true }] call comspec_atak_native_fnc_aceAction, [], _target];
                } else {
                    _out pushBack [["COMSPEC_ATAK_ScreenCast", "Diffuser mes diapositives", "", { ["cast", _target] call comspec_atak_native_fnc_screenCast; }, { [_player] call comspec_atak_native_fnc_hasDevice }] call comspec_atak_native_fnc_aceAction, [], _target];
                };
                _out
            }, [], [0, 0, 0], 4] call comspec_atak_native_fnc_aceAction;
            [_o, 0, [], _root] call ace_interact_menu_fnc_addActionToObject;
        } else {
            _o addAction [format ["<t color='#7fd0b0'>%1</t>", ("Écran : diapositive suivante") call comspec_atak_native_fnc_tr], { ["step", [_this select 0, 1]] call comspec_atak_native_fnc_screenCast; }, nil, 1.4, false, true, "", "private _player = _this; call (uiNamespace getVariable 'COMSPEC_ATAK_ScreenCond')", 4];
            _o addAction [format ["<t color='#7fd0b0'>%1</t>", ("Écran : diapositive précédente") call comspec_atak_native_fnc_tr], { ["step", [_this select 0, -1]] call comspec_atak_native_fnc_screenCast; }, nil, 1.39, false, true, "", "private _player = _this; call (uiNamespace getVariable 'COMSPEC_ATAK_ScreenCond')", 4];
            uiNamespace setVariable ["COMSPEC_ATAK_ScreenCond", _cond];
        };
        true
    };
    default { false };
};
