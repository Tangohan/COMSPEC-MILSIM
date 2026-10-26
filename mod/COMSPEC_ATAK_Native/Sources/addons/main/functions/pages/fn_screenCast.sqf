/*
    Écrans COMSPEC (idée d'un joueur, 25/10, WulffOperator) : les diapositives de l'app Briefing diffusées sur des écrans
    du jeu (TV, ordinateur, projecteur, tableau). Un écran est un objet désigné par le module Eden / Zeus « ATAK · Écran
    COMSPEC » (fn_moduleScreen) ou par script ; le téléphone le détecte à portée et le pilote, ACE (ou la molette) aussi.
    Chaque joueur affiche lui-même l'image sur l'objet (setObjectTexture local) : diapositive Athena téléchargée par son
    COMSPEC Link, ou la sienne du Google Slides synchronisé. Sans COMSPEC Link, l'écran reste noir pour lui.

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
    Demande d'un client (« add », « remove ») : remoteExecCall ["comspec_atak_native_fnc_screenCast", 2] (CfgRemoteExec),
    jugée sur le demandeur retrouvé par remoteExecutedOwner (fn_reqUnit) : Zeus, hôte, admin connecté ou UID autorisé
    (fn_adminAllowed). Plus d'événement CBA comspec_atak_native_screen.
    Exemple de mission : if (isServer) then { ["add", [this, "Salle de briefing", 30]] call comspec_atak_native_fnc_screenCast; };
*/
params [["_mode", ""], ["_a", objNull]];
// Lot mpauth3 (SEC-005) : l'événement CBA comspec_atak_native_screen ne disait pas qui l'avait levé. Tout client
// désignait comme écran n'importe quel objet (nom, portée, camp, texture de son choix, image posée sur l'objet chez
// chaque joueur) ou retirait un écran. Les demandes arrivent par remoteExecCall vers 2, jugées sur le demandeur, avec
// la règle du module Zeus qui les envoie. Module Eden, init d'objet (serveur), hôte d'une partie locale et partie
// solo : appel non distant, rien ne change.
if (isRemoteExecutedJIP) exitWith {
    diag_log format ["[COMSPEC ATAK NATIVE][WARN][SCREEN] Appel « %1 » refusé (rejoué depuis la file JIP)", _mode];
    false
};
private _remote = isMultiplayer && {isRemoteExecuted} && {remoteExecutedOwner isNotEqualTo 2};
private _req = objNull;
if (_remote) then { _req = [remoteExecutedOwner] call comspec_atak_native_fnc_reqUnit; };
if (_remote && {!(_mode in ["add", "remove"])}) exitWith {
    diag_log format ["[COMSPEC ATAK NATIVE][WARN][SCREEN] Action « %1 » refusée au poste %2 (locale au téléphone)", _mode, remoteExecutedOwner];
    false
};
if (_remote && {isNull _req}) exitWith {
    diag_log format ["[COMSPEC ATAK NATIVE][WARN][SCREEN] Demande « %1 » refusée au poste %2 (demandeur introuvable)", _mode, remoteExecutedOwner];
    false
};
if (_remote && {!([_req] call comspec_atak_native_fnc_adminAllowed)}) exitWith {
    diag_log format ["[COMSPEC ATAK NATIVE][WARN][SCREEN] Demande « %1 » refusée à %2 (ni Zeus ni admin)", _mode, name _req];
    false
};
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
    case "add": {
        if !(_a isEqualType []) exitWith { false };
        _a params [["_o", objNull, [objNull]], ["_name", "", [""]], ["_range", 25, [0]], ["_side", "", [""]], ["_sel", 0, [0]], ["_ace", true, [true]]];
        if (isNull _o) exitWith { false };
        if (!isServer) exitWith { ["add", _a] remoteExecCall ["comspec_atak_native_fnc_screenCast", 2]; true };
        if ((count _name) > 64) then { _name = _name select [0, 64]; };
        if !(_side in ["", "WEST", "EAST", "GUER", "CIV"]) then { _side = ""; };
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
        if (!(_a isEqualType objNull) || {isNull _a}) exitWith { false };
        if (!isServer) exitWith { ["remove", _a] remoteExecCall ["comspec_atak_native_fnc_screenCast", 2]; true };
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
                    if ((_c select 2) isEqualTo "GOOGLE") then {
                        private _p = missionNamespace getVariable ["COMSPEC_GoogleBriefingPath", ""];
                        if (_p isNotEqualTo "") then { _want = _p; };
                    } else {
                        private _url = ((_c select 3) param [_c select 4, []]) param [2, ""];
                        if (_url isNotEqualTo "") then {
                            // Cache : chemin de l'image, ou heure d'un échec (nouvel essai après 60 s).
                            private _p = _cache getOrDefault [_url, -1e9];
                            if (_p isEqualType "" && {_p isNotEqualTo ""}) then {
                                _want = _p;
                            } else {
                                if !(_p isEqualType 0) then { _p = -1e9; };
                                // Téléchargement hors de l'image en cours, un à la fois (fn_extAwait : sans figer le jeu).
                                // Verrou horodaté : libre après 30 s même si le script de téléchargement s'est arrêté.
                                if (diag_tickTime - _p > 60 && {diag_tickTime - (missionNamespace getVariable ["COMSPEC_ATAK_ScreenDl", -1e9]) > 30} && {!isNil "comspec_overwatch_connect_fnc_extResult"}) then {
                                    missionNamespace setVariable ["COMSPEC_ATAK_ScreenDl", diag_tickTime];
                                    [_url, ((_c select 3) select (_c select 4)) select 0, _cache] spawn {
                                        params ["_url", "_id", "_cache"];
                                        private _raw = [(["COMSPECExtension", "DownloadBriefingSlideImage", [_url, format ["%1_%2", _id, hashValue _url]]] call comspec_atak_native_fnc_extAwait)] call comspec_overwatch_connect_fnc_extResult;
                                        if (isNil "_raw") then { _raw = ""; };
                                        if (_raw isEqualType []) then { _raw = _raw param [0, ""]; };
                                        if !(_raw isEqualType "") then { _raw = str _raw; };
                                        private _dl = _raw splitString "|";
                                        private _p = if ((_dl param [0, ""]) isEqualTo "OK") then { ((_dl param [1, ""]) splitString (toString [92])) joinString "/" } else { "" };
                                        private _lp = toLower _p;
                                        // Arma ne lit que JPEG et PAA sur un objet, comme dans le téléphone.
                                        if !((_lp select [((count _lp) - 4) max 0]) in [".jpg", "jpeg", ".paa"]) then { _p = ""; };
                                        ["INFO", "BRIEFING", format ["Écran : diapositive %1, adresse %2, fichier %3", _id, _url, [_p, "illisible"] select (_p isEqualTo "")]] call comspec_atak_native_fnc_log;
                                        _cache set [_url, [_p, diag_tickTime] select (_p isEqualTo "")];
                                        missionNamespace setVariable ["COMSPEC_ATAK_ScreenDl", -1e9];
                                    };
                                };
                            };
                        };
                    };
                };
            };
            private _cur = _o getVariable ["COMSPEC_ATAK_ScreenCur", ""];
            if (_want isNotEqualTo _cur) then {
                // Texture d'origine gardée avant le premier changement, remise à la fin de la diffusion.
                if (isNil { _o getVariable "COMSPEC_ATAK_ScreenOrig" }) then { _o setVariable ["COMSPEC_ATAK_ScreenOrig", (getObjectTextures _o) param [_sel, ""]]; };
                _o setObjectTexture [_sel, [_want, _o getVariable ["COMSPEC_ATAK_ScreenOrig", ""]] select (_want isEqualTo "")];
                _o setVariable ["COMSPEC_ATAK_ScreenCur", _want];
                _o setVariable ["COMSPEC_ATAK_ScreenSel", _sel];
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
            _o addAction [format ["<t color='#7fd0b0'>%1</t>", ("Écran : diapositive suivante") call comspec_atak_native_fnc_tr], { ["step", [_this select 0, 1]] call comspec_atak_native_fnc_screenCast; }, nil, 1.4, false, true, "", "private _player = _this; call (missionNamespace getVariable 'COMSPEC_ATAK_ScreenCond')", 4];
            _o addAction [format ["<t color='#7fd0b0'>%1</t>", ("Écran : diapositive précédente") call comspec_atak_native_fnc_tr], { ["step", [_this select 0, -1]] call comspec_atak_native_fnc_screenCast; }, nil, 1.39, false, true, "", "private _player = _this; call (missionNamespace getVariable 'COMSPEC_ATAK_ScreenCond')", 4];
            missionNamespace setVariable ["COMSPEC_ATAK_ScreenCond", _cond];
        };
        true
    };
    default { false };
};
