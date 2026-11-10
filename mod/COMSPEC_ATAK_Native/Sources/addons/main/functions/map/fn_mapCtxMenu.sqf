/*
    Menu contextuel de la carte (clic droit sans glisser) : VOIR LA CAMÉRA (équipier pointé), PING, MARQUEUR, Y ALLER,
    POINT DE PASSAGE (fiche de pose de l'étape au point, fn_wpAction "open"), ENVOYER À LA MONTRE (montre Garmin portée :
    boussole de la montre, fn_watch "send"), CENTRER ICI, COPIER LA GRILLE, CHAMP DE VISION ICI (fn_reliefAction depuis le point), EFFACER LE CHAMP DE VISION
    et EFFACER LA LIGNE DE VUE (quand il y en a un sur la carte), ZONE INTERDITE AU DRONE (poser, ou retirer celle
    sous le clic), RETIRER TOUTES LES ZONES (camp), MODIFIER LE MARQUEUR (marqueur joueur pointé, éditeur), SUPPRIMER (marqueur joueur ou alerte pointés), EFFACER LES ALERTES (repères
    d'alerte reçus). MARQUEUR ouvre la palette au point (fn_markerPlace) ou l'éditeur complet (réglage d'addon). Drone appairé et en vie : ORDRE AU DRONE
    ouvre un sous-menu ALLER, RECHERCHER, STATIONNAIRE, ATTERRIR au point cliqué (fn_droneAction "mapOrder"), et RETOUR.
    Chef ou poste TOC avec des groupes IA dans le camp : ORDRE AUX IA ouvre le sous-menu des ordres au groupe (celui
    pointé, sinon le dernier choisi ou le plus proche du clic ; la première ligne en change) : aller, patrouiller, attaquer,
    supprimer, se replier, tenir, me suivre, embarquer, débarquer, annuler, et POSTURE (prudent, combat, furtif, vitesse,
    formation) (fn_aiOrder). Équipier pointé : aussi CHAMP DE VISION de l'équipier
    et LOCALISER PAR RELAIS (triangulation de 6 à 10 s, fn_relayNet). EFFACER LES LOCALISATIONS quand il y en a une
    (triangulation en cours ou faite, géolocalisation GE, résultat de RECHERCHER).
    Étape de l'itinéraire pointée : MODIFIER L'ÉTAPE, NAVIGUER VERS L'ÉTAPE et SUPPRIMER L'ÉTAPE (fn_wpAt, fn_wpAction).
    Relais pointé (calque Relais) : la fiche du relais remplace l'en-tête (état, fréquence, perte, latence, débit, portée,
    structure, alimentation, places, liaison, distance) avec RELAIS PRÉFÉRÉ, COUVERTURE AUTOUR, Y ALLER, CENTRER ;
    au clic gauche la fiche seule (["open", [carte, écran, true]]), au clic droit la fiche puis les lignes habituelles.
    ["open", [contrôle carte, position écran, fiche seule]]  ouvre le menu au curseur (en-tête : grille, altitude, distance et azimut)
    ["relayAt", [contrôle carte, position écran]] -> relais du calque Relais sous le curseur, ou objNull
    ["close"]                                    ferme (clic ailleurs, Échap, choix d'une ligne)
    ["act", clé]                                 exécute une ligne (DRONE / BACK : ouvre / referme le sous-menu drone)
    ["render"]                                   dessine le menu (fn_pageMap, par-dessus la carte, et l'ouverture)
    Le menu a ses propres contrôles (uiNamespace COMSPEC_ATAK_MapCtxCtrls) : l'ouvrir ou le fermer ne redessine pas
    la page (plus de clignotement de toute la carte au clic droit).
    État : COMSPEC_ATAK_State mapCtx = HashMap [pos (monde), marker (nom ou ""), unit (netId de l'équipier pointé ou ""), at (heure),
    sub ("" ou "DRONE" : sous-menu affiché)].
*/
params [["_mode", ""], ["_arg", []]];
disableSerialization;
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _wipe = {
    { ctrlDelete _x; } forEach (uiNamespace getVariable ["COMSPEC_ATAK_MapCtxCtrls", []]);
    uiNamespace setVariable ["COMSPEC_ATAK_MapCtxCtrls", []];
};
private _unbindEsc = {
    private _d = [] call comspec_atak_native_fnc_display;
    private _h = uiNamespace getVariable ["COMSPEC_ATAK_MapCtxKey", -1];
    if (_h >= 0 && {!isNull _d}) then { _d displayRemoveEventHandler ["KeyDown", _h]; };
    uiNamespace setVariable ["COMSPEC_ATAK_MapCtxKey", -1];
};
// Relais sous le curseur : traité avant le switch, la fonction renvoyant true pour tous les autres modes (un booléen
// à la place de l'objet faisait échouer isNull, et avec lui le clic droit et le clic gauche sur la carte).
if (_mode isEqualTo "relayAt") exitWith {
    _arg params ["_map", "_screen"];
    if (isNull _map || {!(profileNamespace getVariable ["COMSPEC_ATAK_RelaysLayer", true])}) exitWith { objNull };
    private _near = 0.022 * (uiNamespace getVariable ["COMSPEC_ATAK_MapTs", 1]);
    private _hit = objNull;
    {
        private _sp = _map ctrlMapWorldToScreen (getPosASLVisual (_x select 0));
        private _dd = [(_sp select 0) - (_screen select 0), (_sp select 1) - (_screen select 1)] distance2D [0, 0];
        if (_dd < _near) then { _near = _dd; _hit = _x select 0; };
    } forEach (["list"] call comspec_atak_native_fnc_relayNet);
    _hit
};
switch (_mode) do {
    case "open": {
        _arg params ["_map", "_screen", ["_card", false]];
        if (isNull _map) exitWith {};
        private _w = _map ctrlMapScreenToWorld _screen;
        private _rl = ["relayAt", [_map, _screen]] call comspec_atak_native_fnc_mapCtxMenu;
        if (_card && {isNull _rl}) exitWith {};
        if (!isNull _rl) then { _w = getPosASL _rl; };
        private _m = [_map, _screen] call comspec_atak_native_fnc_markerAt;
        // Mêmes règles que la suppression (fn_markerDelete) : marqueurs posés par les joueurs et repères d'alerte reçus.
        if (([_m] call comspec_atak_native_fnc_markerDeletable) isEqualTo 0) then { _m = ""; };
        // Équipier pointé (joueur du camp avec un téléphone, à moins d'environ un symbole du clic) : ligne VOIR CAMÉRA.
        private _near = 0.03;
        private _who = objNull;
        {
            private _sp = _map ctrlMapWorldToScreen (getPosASLVisual _x);
            private _dd2 = [(_sp select 0) - (_screen select 0), (_sp select 1) - (_screen select 1)] distance2D [0, 0];
            if (_dd2 < _near) then { _near = _dd2; _who = _x; };
        } forEach (allPlayers select { _x isNotEqualTo player && {alive _x} && {side group _x isEqualTo side group player} && {[_x] call comspec_atak_native_fnc_hasDevice} });
        // Groupe IA du camp pointé (son chef à moins d'un symbole du clic) : choisi pour ORDRE AUX IA.
        private _aiG = grpNull;
        private _aiCan = ["can", player] call comspec_atak_native_fnc_aiOrder;
        private _gs = ["groups", [str side group player, _w]] call comspec_atak_native_fnc_aiOrder;
        if (_aiCan && {_gs isNotEqualTo []}) then {
            private _nearAi = 0.03;
            {
                private _sp = _map ctrlMapWorldToScreen (getPosASLVisual leader _x);
                private _dd3 = [(_sp select 0) - (_screen select 0), (_sp select 1) - (_screen select 1)] distance2D [0, 0];
                if (_dd3 < _nearAi) then { _nearAi = _dd3; _aiG = _x; };
            } forEach (_gs select [0, 12]);
            // Sinon le dernier groupe choisi s'il est encore là, sinon le plus proche du clic.
            if (isNull _aiG) then { _aiG = uiNamespace getVariable ["COMSPEC_ATAK_AiPick", grpNull]; };
            if (isNull _aiG || {!(_aiG in _gs)}) then { _aiG = _gs param [0, grpNull]; };
        };
        // Ligne ORDRE AUX IA indisponible mais montrée (chef sans groupe IA, groupes IA mais pas le droit, serveur sans le
        // mod à jour) : un clic dit pourquoi au lieu de ne rien proposer. Ni droit ni groupe IA : pas de ligne.
        private _aiWhy = if (_aiCan || {_gs isNotEqualTo []}) then { ["why", _aiG] call comspec_atak_native_fnc_aiOrder } else { "" };
        // Étape de l'itinéraire pointée : MODIFIER, NAVIGUER VERS, SUPPRIMER L'ÉTAPE.
        private _wpi = [_map, _screen] call comspec_atak_native_fnc_wpAt;
        if (_card) then { _m = ""; _who = objNull; _wpi = -1; };
        // Éléments supprimables sous le clic (2.9.38) : route de l'app Routes, itinéraire (app Itinéraire), guidage GPS,
        // zone et plan de l'app Zones ; [genre, id, nom, à moi].
        private _dels = [];
        if (!_card) then {
            private _rid = ["at", [_map, _screen]] call comspec_atak_native_fnc_routeAction;
            if (_rid isNotEqualTo "") then { _dels pushBack ["ROUTE", _rid, (["get", _rid] call comspec_atak_native_fnc_routeAction) getOrDefault ["name", ""], ["isMine", _rid] call comspec_atak_native_fnc_routeAction]; };
            if (_wpi >= 0) then { _dels pushBack ["WP", "", ("l'itinéraire (app Itinéraire)") call comspec_atak_native_fnc_tr, true]; };
            private _g = missionNamespace getVariable ["COMSPEC_ATAK_Route", createHashMap];
            if ((count _g) > 0 && {((_g getOrDefault ["pts", []]) findIf { ((_map ctrlMapWorldToScreen [_x select 0, _x select 1]) distance2D _screen) < (pixelW * 12) }) >= 0}) then {
                _dels pushBack ["GPS", "", ("le guidage GPS") call comspec_atak_native_fnc_tr, true];
            };
            private _zh = ["at", [_w]] call comspec_atak_native_fnc_zoneAction;
            if ((count _zh) > 0) then {
                _zh params ["_zb", "_zz"];
                private _zm = ["isMine", _zb get "id"] call comspec_atak_native_fnc_zoneAction;
                if (_zm) then { _dels pushBack ["ZONE", [_zb get "id", _zz select 0], _zz select 1, true]; };
                _dels pushBack ["PLAN", _zb get "id", _zb get "name", _zm];
            };
            // Axes, traits et points de contrôle sous le clic ; plan sans zones (seul moyen de le viser) ; étape d'une de mes routes.
            {
                _x params ["_ek", "_eb", "_eid", "_en"];
                private _em = ["isMine", _eb] call comspec_atak_native_fnc_zoneAction;
                if (_ek isEqualTo "PLAN") then {
                    if ((_dels findIf { (_x select 0) isEqualTo "PLAN" && {(_x select 1) isEqualTo _eb} }) < 0) then { _dels pushBack ["PLAN", _eb, _en, _em]; };
                } else {
                    if (_em) then { _dels pushBack [_ek, [_eb, _eid], _en, true]; };
                };
            } forEach ((["elemAt", [_map, _screen]] call comspec_atak_native_fnc_zoneAction) select [0, 6]);
            private _sh = ["stepAt", [_map, _screen]] call comspec_atak_native_fnc_routeAction;
            if ((count _sh) > 0) then { _dels pushBack ["STEP", [_sh select 0, _sh select 1], _sh select 2, true]; };
        };
        _s set ["mapCtx", createHashMapFromArray [["pos", [_w select 0, _w select 1, 0]], ["marker", _m], ["unit", [netId _who, ""] select (isNull _who)], ["at", diag_tickTime],
            ["relay", _rl], ["card", _card], ["wp", _wpi], ["aiG", _aiG], ["aiWhy", _aiWhy], ["dels", _dels], ["delCf", -1]]];
        [] call _unbindEsc;
        private _d = [] call comspec_atak_native_fnc_display;
        if (!isNull _d) then {
            uiNamespace setVariable ["COMSPEC_ATAK_MapCtxKey", _d displayAddEventHandler ["KeyDown", {
                params ["", "_key"];
                if (_key isNotEqualTo 0x01) exitWith { _this call comspec_atak_native_fnc_keyBlock };
                ["close"] call comspec_atak_native_fnc_mapCtxMenu;
                true
            }]];
        };
        playSound "ClickSoft";
        // À l'image suivante : la carte vient de prendre le focus (fn_mapUnfocus le lui retire), le menu passe devant.
        [{ ["render"] call comspec_atak_native_fnc_mapCtxMenu; }] call comspec_atak_native_fnc_nextFrame;
    };
    case "close": {
        [] call _unbindEsc;
        // Contrôles masqués tout de suite, supprimés à l'image suivante (jamais pendant le clic d'un de leurs boutons).
        private _old = uiNamespace getVariable ["COMSPEC_ATAK_MapCtxCtrls", []];
        { if (!isNull _x) then { _x ctrlShow false; _x ctrlEnable false; }; } forEach _old;
        [{ { ctrlDelete _x; } forEach _this; }, _old] call comspec_atak_native_fnc_nextFrame;
        uiNamespace setVariable ["COMSPEC_ATAK_MapCtxCtrls", []];
        _s set ["mapCtx", createHashMap];
    };
    case "act": {
        private _c = _s getOrDefault ["mapCtx", createHashMap];
        if ((count _c) isEqualTo 0) exitWith {};
        // Sous-menu drone : le menu reste ouvert et se redessine (à l'image suivante, hors du clic du bouton qu'on efface).
        // Ordre aux IA impossible ici : le motif plutôt qu'un sous-menu qui ne mènerait à rien.
        if (_arg isEqualTo "AI" && {(_c getOrDefault ["aiWhy", ""]) isNotEqualTo ""}) exitWith {
            ["close"] call comspec_atak_native_fnc_mapCtxMenu;
            ["WARNING", _c get "aiWhy", 5, 30] call comspec_atak_native_fnc_notify;
        };
        // Supprimer : sous-menu (plusieurs éléments sous le clic), puis deux clics (CONFIRMER), bandeau ANNULER ensuite.
        if (_arg isEqualTo "DELS") exitWith {
            _c set ["sub", "DEL"]; _c set ["at", diag_tickTime]; playSound "ClickSoft";
            [{ ["render"] call comspec_atak_native_fnc_mapCtxMenu; }] call comspec_atak_native_fnc_nextFrame;
        };
        if ((_arg select [0, 4]) isEqualTo "DEL_") exitWith {
            private _i = parseNumber (_arg select [4]);
            private _e = (_c getOrDefault ["dels", []]) param [_i, []];
            if ((count _e) isEqualTo 0) exitWith {};
            if ((_c getOrDefault ["delCf", -1]) isNotEqualTo _i) exitWith {
                _c set ["delCf", _i]; _c set ["at", diag_tickTime]; playSound "ClickSoft";
                [{ ["render"] call comspec_atak_native_fnc_mapCtxMenu; }] call comspec_atak_native_fnc_nextFrame;
            };
            ["close"] call comspec_atak_native_fnc_mapCtxMenu;
            playSound "ClickSoft";
            _e params ["_k", "_v", "_n", "_mineE"];
            private _T = { _this call comspec_atak_native_fnc_tr };
            switch (_k) do {
                case "ROUTE": {
                    if (_mineE) then {
                        ["trash", _v] call comspec_atak_native_fnc_routeAction;
                        [format [("Route %1 supprimée") call _T, _n], compile format ["['untrash', %1] call comspec_atak_native_fnc_routeAction;", str _v]] call comspec_atak_native_fnc_undoSnack;
                    } else {
                        ["set", [_v, "vis", false]] call comspec_atak_native_fnc_routeAction;
                        [format [("Route %1 masquée chez moi") call _T, _n], compile format ["['set', [%1, 'vis', true]] call comspec_atak_native_fnc_routeAction;", str _v]] call comspec_atak_native_fnc_undoSnack;
                    };
                };
                case "WP";
                case "GPS": {
                    ["itinDel", _k] call comspec_atak_native_fnc_routeAction;
                    [[("Itinéraire effacé") call _T, ("Guidage GPS arrêté et effacé") call _T] select (_k isEqualTo "GPS"), compile format ["['itinUndo', %1] call comspec_atak_native_fnc_routeAction;", str _k]] call comspec_atak_native_fnc_undoSnack;
                };
                case "ZONE": {
                    _v params ["_bid", "_zid"];
                    private _r = ["zoneCut", [_bid, _zid]] call comspec_atak_native_fnc_zoneAction;
                    if ((count _r) > 0) then { [format [("Zone %1 supprimée") call _T, _n], compile format ["['zoneRestore', %1] call comspec_atak_native_fnc_zoneAction;", str ([_bid] + _r)]] call comspec_atak_native_fnc_undoSnack; };
                };
                case "AXIS";
                case "LINE";
                case "CP": {
                    _v params ["_bid", "_eid"];
                    private _r = ["elemCut", [_bid, _k, _eid]] call comspec_atak_native_fnc_zoneAction;
                    if ((count _r) > 0) then { [format [("%1 supprimé") call _T, _n], compile format ["['elemRestore', %1] call comspec_atak_native_fnc_zoneAction;", str ([_bid] + _r)]] call comspec_atak_native_fnc_undoSnack; };
                };
                case "STEP": {
                    _v params ["_rid", "_si"];
                    private _r = ["stepCut", [_rid, _si]] call comspec_atak_native_fnc_routeAction;
                    if ((count _r) > 0) then { [format [("Étape %1 supprimée") call _T, _n], compile format ["['stepRestore', %1] call comspec_atak_native_fnc_routeAction;", str ([_rid] + _r)]] call comspec_atak_native_fnc_undoSnack; };
                };
                case "PLAN": {
                    if (_mineE) then {
                        ["trash", _v] call comspec_atak_native_fnc_zoneAction;
                        [format [("Plan %1 supprimé") call _T, _n], compile format ["['untrash', %1] call comspec_atak_native_fnc_zoneAction;", str _v]] call comspec_atak_native_fnc_undoSnack;
                    } else {
                        ["set", [_v, "vis", false]] call comspec_atak_native_fnc_zoneAction;
                        [format [("Plan %1 masqué chez moi") call _T, _n], compile format ["['set', [%1, 'vis', true]] call comspec_atak_native_fnc_zoneAction;", str _v]] call comspec_atak_native_fnc_undoSnack;
                    };
                };
            };
        };
        // Sous-menu Zones (ZN), ses états (ZNST), le rayon du découpage (ZN_RAD, menu gardé ouvert) et le thème des
        // noms des axes (ZNAX ; ZNAX_SUB : sous-thème MIL-STD suivant, menu gardé ouvert).
        if (_arg in ["ZN", "ZNST", "ZN_RAD", "ZNAX", "ZNAX_SUB"]) exitWith {
            if (_arg isEqualTo "ZN_RAD") then {
                private _rs = [150, 300, 600, 1000];
                private _r = profileNamespace getVariable ["COMSPEC_ATAK_ZnCtxRad", 300];
                profileNamespace setVariable ["COMSPEC_ATAK_ZnCtxRad", _rs select (((_rs find _r) + 1) mod (count _rs))];
            };
            if (_arg isEqualTo "ZNAX_SUB") then {
                private _subs = ((["themeRows"] call comspec_atak_native_fnc_zoneAction) apply { _x select 0 }) - ["MIL"];
                private _cur = profileNamespace getVariable ["COMSPEC_ATAK_ZnNameSub", "CITY"];
                profileNamespace setVariable ["COMSPEC_ATAK_ZnNameSub", _subs select (((_subs find _cur) + 1) mod (count _subs))];
            };
            _c set ["sub", switch (_arg) do { case "ZNST": { "ZNST" }; case "ZNAX"; case "ZNAX_SUB": { "ZNAX" }; default { "ZN" }; }];
            _c set ["at", diag_tickTime];
            playSound "ClickSoft";
            [{ ["render"] call comspec_atak_native_fnc_mapCtxMenu; }] call comspec_atak_native_fnc_nextFrame;
        };
        if (_arg in ["DRONE", "BACK", "AI", "AIPOST", "AITHEN", "AINEXT"]) exitWith {
            // Groupe IA suivant (du plus proche au plus loin du point cliqué).
            if (_arg isEqualTo "AINEXT") then {
                private _gs = ["groups", [str side group player, _c get "pos"]] call comspec_atak_native_fnc_aiOrder;
                _c set ["aiG", _gs param [((_gs find (_c getOrDefault ["aiG", grpNull])) + 1) mod ((count _gs) max 1), grpNull]];
            };
            _c set ["sub", switch (_arg) do {
                case "DRONE": { "DRONE" };
                case "AI";
                case "AINEXT": { "AI" };
                case "AIPOST": { "AIPOST" };
                case "AITHEN": { "AITHEN" };
                default { ["", "AI"] select ((_c getOrDefault ["sub", ""]) in ["AIPOST", "AITHEN"]) };
            }];
            _c set ["at", diag_tickTime];
            playSound "ClickSoft";
            [{ ["render"] call comspec_atak_native_fnc_mapCtxMenu; }] call comspec_atak_native_fnc_nextFrame;
        };
        private _pos = _c get "pos";
        private _m = _c getOrDefault ["marker", ""];
        ["close"] call comspec_atak_native_fnc_mapCtxMenu;
        playSound "ClickSoft";
        // Ordres aux IA : clé « AI_ » + ordre de fn_aiOrder, au groupe choisi et au point cliqué.
        if ((_arg select [0, 3]) isEqualTo "AI_") exitWith {
            private _g = _c getOrDefault ["aiG", grpNull];
            uiNamespace setVariable ["COMSPEC_ATAK_AiPick", _g];
            ["send", [_g, _arg select [3], [_pos select 0, _pos select 1]]] call comspec_atak_native_fnc_aiOrder;
        };
        // Nommer les axes autour avec un thème (préférence du joueur gardée, aussi dans le nouveau plan).
        if ((_arg select [0, 3]) isEqualTo "ZX_") exitWith {
            ["nameTheme", ["", _arg select [3]]] call comspec_atak_native_fnc_zoneAction;
            ["ctxGen", ["AXES", _pos, ""]] call comspec_atak_native_fnc_zoneAction;
        };
        // Sous-menu Zones : découpage autour du point (nouveau plan, calcul en arrière-plan), zone sous le clic.
        if ((_arg select [0, 3]) isEqualTo "ZG_") exitWith { ["ctxGen", [_arg select [3], _pos, (_c getOrDefault ["zone", []]) param [0, ""]]] call comspec_atak_native_fnc_zoneAction; };
        if ((_arg select [0, 3]) in ["ZS_", "ZR_", "ZN_"]) exitWith {
            (_c getOrDefault ["zone", []]) params [["_zbid", ""], ["_zzid", ""]];
            if (_zbid isEqualTo "") exitWith {};
            private _v = _arg select [3];
            switch (_arg select [0, 3]) do {
                case "ZS_": { ["sit", [_zbid, _zzid, _v, []]] call comspec_atak_native_fnc_zoneAction; };
                case "ZR_": { ["zoneSet", [_zbid, _zzid, "rm", _v]] call comspec_atak_native_fnc_zoneAction; };
                default {
                    switch (_v) do {
                        case "ENY": { ["#open", [_zbid, _zzid, [_pos select 0, _pos select 1]]] call comspec_atak_native_fnc_pageZones; };
                        case "VIS": {
                            private _zb = ["get", _zbid] call comspec_atak_native_fnc_zoneAction;
                            if ((count _zb) > 0) then { ["set", [_zbid, "vis", !(_zb get "vis")]] call comspec_atak_native_fnc_zoneAction; };
                        };
                        case "CITE": {
                            private _zb = ["get", _zbid] call comspec_atak_native_fnc_zoneAction;
                            private _zz = ["zone", [_zbid, _zzid]] call comspec_atak_native_fnc_zoneAction;
                            if ((count _zz) isEqualTo 0) exitWith {};
                            private _t = ["cite", [_zb, _zz]] call comspec_atak_native_fnc_zoneAction;
                            ["clip", _t] call comspec_atak_native_fnc_planAction;
                            uiNamespace setVariable ["COMSPEC_ATAK_ChatDraft", _t];
                            [format [("Copié : %1") call comspec_atak_native_fnc_tr, _t], 3] call comspec_atak_native_fnc_toast;
                            ["CHAT"] call comspec_atak_native_fnc_navigate;
                        };
                    };
                };
            };
        };
        switch (_arg) do {
            case "PING": { ["send", _pos] call comspec_atak_native_fnc_mapPing; };
            case "FIRESQ": { ["open", _pos] call comspec_atak_native_fnc_firesQuick; };
            // App Zones : fiche de la zone cliquée (état en un clic, signaler un ennemi au point cliqué).
            case "ZONE": {
                (_c getOrDefault ["zone", []]) params [["_zbid", ""], ["_zzid", ""]];
                if (_zbid isNotEqualTo "") then { ["#open", [_zbid, _zzid, [_pos select 0, _pos select 1]]] call comspec_atak_native_fnc_pageZones; };
            };
            case "MARKER": { ["open", _pos] call comspec_atak_native_fnc_markerPlace; };
            // Points de passage : fiche de pose au point (nom, type, partage, VALIDER), ou l'étape pointée.
            case "WPADD": { ["open", _pos] call comspec_atak_native_fnc_wpAction; };
            case "WPEDIT": { ["edit", _c getOrDefault ["wp", -1]] call comspec_atak_native_fnc_wpAction; };
            case "WPGO": { ["goto", _c getOrDefault ["wp", -1]] call comspec_atak_native_fnc_wpAction; };
            case "WPDEL": { ["del", _c getOrDefault ["wp", -1]] call comspec_atak_native_fnc_wpAction; };
            case "ROUTE": { [_pos, "Point de la carte"] spawn comspec_atak_native_fnc_routeCompute; };
            case "WATCH": {
                private _lbl = if (_m isNotEqualTo "" && {(markerText _m) isNotEqualTo ""}) then { markerText _m } else { mapGridPosition _pos };
                ["send", [_pos, _lbl select [0, 14]]] call comspec_atak_native_fnc_watch;
            };
            case "CENTER": { _s set ["mapFollow", false]; [_pos] call comspec_atak_native_fnc_mapCenter; };
            case "COPY": {
                private _g = [_pos, 10] call comspec_atak_native_fnc_gridRef;
                ["clip", _g] call comspec_atak_native_fnc_planAction;
                [format ["Grille %1 copiée", _g], 2] call comspec_atak_native_fnc_toast;
            };
            case "COPYMK": {
                // Marqueur en texte (nom, grille 10 chiffres, altitude) : presse-papiers et brouillon du chat, prêt à coller.
                if (_m isEqualTo "") exitWith {};
                private _mp = getMarkerPos _m;
                private _name = [markerText _m, [markerText _m] call comspec_atak_native_fnc_markerAutoName] select ((markerText _m) isEqualTo "");
                private _txt = format ["%1 : %2 (%3 m)", [_name, "Marqueur"] select (_name isEqualTo ""), [_mp, 10] call comspec_atak_native_fnc_gridRef, round (getTerrainHeightASL _mp)];
                ["clip", _txt] call comspec_atak_native_fnc_planAction;
                uiNamespace setVariable ["COMSPEC_ATAK_ChatDraft", _txt];
                [format ["Copié : %1", _txt], 3] call comspec_atak_native_fnc_toast;
            };
            case "DELETE": { if (_m isNotEqualTo "") then { [_m] call comspec_atak_native_fnc_markerDelete; }; };
            // Marqueur de joueur pointé : éditeur (titre, description, type, couleur, taille, orientation, opacité).
            case "MKEDIT": { if (_m isNotEqualTo "" && {(markerShape _m) isNotEqualTo ""}) then { [_m] call comspec_atak_native_fnc_markerEditOpen; }; };
            case "ALERTSDEL": {
                private _n = 0;
                { if ((_x find "COMSPEC_ALERT_") isEqualTo 0 || {(_x find "COMSPEC_PANIC_") isEqualTo 0}) then { deleteMarkerLocal _x; _n = _n + 1; }; } forEach allMapMarkers;
                _s set ["selectedMarker", ""];
                ["INFO", format ["%1 alerte(s) effacée(s) de la carte", _n], 2, 20] call comspec_atak_native_fnc_notify;
                [{ ["MAP"] call comspec_atak_native_fnc_pageRender; }] call comspec_atak_native_fnc_nextFrame;
            };
            case "NOFLY": { ["add", [_pos, 200]] call comspec_atak_native_fnc_noFly; };
            case "NOFLYDEL": {
                private _z = ["inside", _pos] call comspec_atak_native_fnc_noFly;
                if ((count _z) > 0) then { ["remove", _z select 0] call comspec_atak_native_fnc_noFly; };
            };
            case "NOFLYALL": { ["clear"] call comspec_atak_native_fnc_noFly; };
            case "VIEWDEL": {
                ["clear"] call comspec_atak_native_fnc_reliefAction;
                ["INFO", "Champ de vision effacé", 2, 20] call comspec_atak_native_fnc_notify;
            };
            case "LOSDEL": {
                ["losClear"] call comspec_atak_native_fnc_reliefAction;
                ["INFO", "Ligne de vue effacée", 2, 20] call comspec_atak_native_fnc_notify;
            };
            case "V3D": {
                // W.I.P : ne pas ouvrir la page (fn_navigate bloque aussi VIEW3D).
                ["Vue 3D en chantier (W.I.P), bientôt disponible", 2] call comspec_atak_native_fnc_toast;
            };
            case "VIEW": { ["compute", [_pos select 0, _pos select 1]] call comspec_atak_native_fnc_reliefAction; };
            case "VIEWU": { ["compute", _c getOrDefault ["unit", ""]] call comspec_atak_native_fnc_reliefAction; };
            case "RPIN": { ["pin", _c getOrDefault ["relay", objNull]] call comspec_atak_native_fnc_relayNet; };
            case "RCOV": {
                profileNamespace setVariable ["COMSPEC_ATAK_MeshOnMap", true];
                ["coverage", [_pos select 0, _pos select 1]] call comspec_atak_native_fnc_relayNet;
            };
            case "LOCATE": {
                profileNamespace setVariable ["COMSPEC_ATAK_MeshOnMap", true];
                ["locateStart", _c getOrDefault ["unit", ""]] call comspec_atak_native_fnc_relayNet;
            };
            case "LOCDEL": {
                // Triangulations par relais (et celle en cours), géolocalisations GE et résultat de la recherche.
                ["trilatClear"] call comspec_atak_native_fnc_relayNet;
                missionNamespace setVariable ["COMSPEC_ATAK_GeoTracks", []];
                ["clear"] call comspec_atak_native_fnc_mapSearch;
                ["INFO", "Localisations effacées de la carte", 2, 20] call comspec_atak_native_fnc_notify;
            };
            // Ordres au drone : envoyés à l'image suivante, hors du clic d'un bouton que "close" vient d'effacer ; le
            // journal du drone note chaque étape et un bandeau dit pourquoi un ordre est refusé (fn_droneAction "mapOrder").
            case "DGOTO";
            case "DHUNT";
            case "DHOVER";
            case "DLAND": {
                [{ ["mapOrder", _this] call comspec_atak_native_fnc_droneAction; }, [_arg select [1], +_pos]] call comspec_atak_native_fnc_nextFrame;
            };
            case "CAM": {
                private _u = objectFromNetId (_c getOrDefault ["unit", ""]);
                if (isNull _u || {!alive _u}) exitWith {};
                _s set ["mapCam", netId _u];
                ["MAP"] call comspec_atak_native_fnc_pageRender;
            };
        };
    };
    case "render": {
        call _wipe;
        private _c = _s getOrDefault ["mapCtx", createHashMap];
        if ((count _c) isEqualTo 0) exitWith {};
        // Menu oublié (page quittée puis rouverte) : on ne le ressort pas.
        if ((diag_tickTime - (_c getOrDefault ["at", 0])) > 60 || {(_s getOrDefault ["activePage", ""]) isNotEqualTo "MAP"}) exitWith { _s set ["mapCtx", createHashMap]; [] call _unbindEsc; };
        private _d = [] call comspec_atak_native_fnc_display;
        if (isNull _d) exitWith {};
        private _map = [_d] call comspec_atak_native_fnc_mapCtrl;
        if (isNull _map) exitWith {};
        private _l = [] call comspec_atak_native_fnc_layoutGet;
        (_l get "body") params ["_bx", "_by", "_bw", "_bh"];
        private _font = _l get "font";
        private _fs = _l get "fontSmall";
        private _ratio = (uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW]);
        private _pos = _c get "pos";
        private _m = _c getOrDefault ["marker", ""];
        if (_m isNotEqualTo "" && {(markerShape _m) isEqualTo ""}) then { _m = ""; _c set ["marker", ""]; };
        ([] call comspec_atak_native_fnc_accent) params ["_acc"];
        private _dir = "\z\comspec_atak_native\addons\main\data\";
        private _rows = [
            ["PING", "Ping", "signal au groupe", "\A3\ui_f\data\map\markers\military\circle_CA.paa", [1, 0.85, 0.15, 1]],
            ["MARKER", "Mettre un marqueur", ["palette, canal", "éditeur complet"] select ((missionNamespace getVariable ["comspec_atak_native_marker_menu", "palette"]) isEqualTo "editor"), _dir + "map_marker.paa", _acc],
            ["WPADD", "Point de passage", format ["étape %1 · %2", (count ((missionNamespace getVariable ["COMSPEC_ATAK_Waypoints", createHashMap]) getOrDefault ["pts", []])) + 1,
                toLower (["shareName", profileNamespace getVariable ["COMSPEC_ATAK_WpShare", 1]] call comspec_atak_native_fnc_wpAction)], _dir + "app_waypoints.paa", [0.95, 0.75, 0.18, 1]],
            ["ROUTE", "Y aller", "itinéraire GPS", _dir + "map_route.paa", [0.40, 0.65, 1, 1]],
            ["CENTER", "Centrer ici", "", _dir + "map_center.paa", (COMSPEC_ATAK_Th get "mapText")],
            ["COPY", "Copier la grille", "10 chiffres", _dir + "map_grid.paa", (COMSPEC_ATAK_Th get "mapText")],
            ["V3D", "Vue 3D ici", "W.I.P — bientôt disponible", _dir + "app_relief.paa", [0.55, 0.6, 0.57, 1]],
            ["VIEW", "Champ de vision ici", format ["rayon %1 m", _s getOrDefault ["rlRadius", 1000]], _dir + "app_relief.paa", (COMSPEC_ATAK_Th get "mapOk")]
        ];
        // FIRES court au point cliqué (fn_firesQuick) : cible posée, reste la munition et l'envoi.
        if (["can"] call comspec_atak_native_fnc_firesQuick) then { _rows insert [1, [["FIRESQ", "FIRES ici", "munition, envoi aux pilotes", _dir + "app_fires.paa", COMSPEC_ATAK_Th get "mapAlert"]]]; };
        // Montre Garmin portée : le point (ou le marqueur pointé) part sur la boussole de la montre (fn_watch "send").
        if (((assignedItems player) findIf { _x isKindOf ["COMSPEC_ATAK_GarminWatch", configFile >> "CfgWeapons"] }) >= 0
            || {((items player) findIf { _x isKindOf ["COMSPEC_ATAK_GarminWatchItem", configFile >> "CfgWeapons"] }) >= 0}) then {
            _rows insert [4, [["WATCH", "Envoyer à la montre", "boussole de la montre", _dir + "app_garmin.paa", (COMSPEC_ATAK_Th get "mapOk")]]];
        };
        // Zone de l'app Zones sous le clic : sa fiche en tête (signaler un ennemi au point, état en un clic).
        private _zh = ["at", [_pos]] call comspec_atak_native_fnc_zoneAction;
        if ((count _zh) > 0) then {
            _zh params ["_zb", "_zz"];
            _c set ["zone", [_zb get "id", _zz select 0]];
            private _zsr = ["state", _zz select 6] call comspec_atak_native_fnc_zoneAction;
            _rows insert [0, [["ZONE", format [("Zone %1 · signaler") call comspec_atak_native_fnc_tr, _zz select 1], format ["%1 · %2", _zb get "name", ((_zsr select 1) call comspec_atak_native_fnc_tr)], _dir + "app_zones.paa", _zsr select 4]]];
        };
        // Étape pointée : ses lignes en tête (la liste a pu changer depuis l'ouverture : index revérifié).
        private _wpPts = (missionNamespace getVariable ["COMSPEC_ATAK_Waypoints", createHashMap]) getOrDefault ["pts", []];
        private _wpi = _c getOrDefault ["wp", -1];
        if (_wpi >= 0 && {_wpi < count _wpPts}) then {
            private _wpn = (_wpPts select _wpi) select 1;
            private _wpc = (["type", (_wpPts select _wpi) param [2, "WP"]] call comspec_atak_native_fnc_wpAction) select 3;
            _rows insert [0, [
                ["WPEDIT", "Modifier l'étape", format ["%1. %2", _wpi + 1, _wpn], _dir + "app_waypoints.paa", _wpc],
                ["WPGO", "Naviguer vers l'étape", _wpn, _dir + "map_route.paa", (COMSPEC_ATAK_Th get "mapOk")]
            ]];
            _rows pushBack ["WPDEL", "Supprimer l'étape", _wpn, _dir + "ui_close.paa", [0.95, 0.30, 0.25, 1]];
        };
        // Relais pointé : sa fiche (entrée de fn_relayNet "list", relue à chaque rendu) et ses actions en tête.
        private _rl = _c getOrDefault ["relay", objNull];
        private _re = if (isNull _rl) then { [] } else { ["entry", _rl] call comspec_atak_native_fnc_relayNet };
        private _card = (_c getOrDefault ["card", false]) && {_re isNotEqualTo []};
        if (_re isNotEqualTo []) then {
            private _pinned = _rl isEqualTo (uiNamespace getVariable ["COMSPEC_ATAK_RelayPin", objNull]);
            private _relRows = [
                ["RPIN", ["Relais préféré", "Choix automatique"] select _pinned, ["garder ce relais", "meilleur relais reçu"] select _pinned, _dir + "app_waverelay.paa", _acc],
                ["RCOV", "Couverture autour", format ["rayon %1 km", round ((_s getOrDefault ["rcRadius", 5000]) / 1000)], _dir + "app_network.paa", [0.28, 0.70, 1, 1]],
                ["ROUTE", "Y aller", "itinéraire GPS", _dir + "map_route.paa", [0.40, 0.65, 1, 1]],
                ["CENTER", "Centrer ici", "", _dir + "map_center.paa", (COMSPEC_ATAK_Th get "mapText")]
            ];
            if (_card) then { _rows = _relRows; } else { _rows = _relRows + (_rows select { !((_x select 0) in ["ROUTE", "CENTER"]) }); };
        };
        private _cu = objectFromNetId (_c getOrDefault ["unit", ""]);
        if (!isNull _cu && {alive _cu}) then {
            private _cs = [_cu, true] call comspec_atak_native_fnc_unitCallsign;
            _rows insert [0, [
                ["CAM", "Voir la caméra", _cs, _dir + "app_photos.paa", (COMSPEC_ATAK_Th get "mapOk")],
                ["VIEWU", "Son champ de vision", _cs, _dir + "app_relief.paa", (COMSPEC_ATAK_Th get "mapOk")],
                ["LOCATE", "Localiser par relais", "triangulation", _dir + "app_waverelay.paa", [1, 0.65, 0.2, 1]]
            ]];
        };
        // Champ de vision et ligne de vue sur la carte (un seul de chaque, propre au joueur) : les effacer d'ici.
        private _vs = missionNamespace getVariable ["COMSPEC_ATAK_Viewshed", []];
        if ((count _vs) > 0) then {
            _rows pushBack ["VIEWDEL", "Effacer le champ de vision", _vs param [13, ""], _dir + "ui_close.paa", [0.95, 0.30, 0.25, 1]];
        };
        // Localisations affichées (triangulation par relais, en cours ou faite, géolocalisation, recherche) : les effacer.
        private _nLoc = (count (missionNamespace getVariable ["COMSPEC_ATAK_Trilat", []])) + (count (missionNamespace getVariable ["COMSPEC_ATAK_GeoTracks", []]))
            + ([0, 1] select ((count (missionNamespace getVariable ["COMSPEC_ATAK_TrilatPending", []])) > 0))
            + ([0, 1] select ((count ((uiNamespace getVariable ["COMSPEC_ATAK_MapSearch", createHashMap]) getOrDefault ["hit", createHashMap])) > 0));
        if (_nLoc > 0) then {
            _rows pushBack ["LOCDEL", "Effacer les localisations", format ["%1 sur la carte", _nLoc], _dir + "ui_close.paa", [0.95, 0.30, 0.25, 1]];
        };
        if ((count (missionNamespace getVariable ["COMSPEC_ATAK_ReliefLos", []])) > 0) then {
            _rows pushBack ["LOSDEL", "Effacer la ligne de vue", "", _dir + "ui_close.paa", [0.95, 0.30, 0.25, 1]];
        };
        private _nf = count (["list"] call comspec_atak_native_fnc_noFly);
        private _inNf = (count (["inside", _pos] call comspec_atak_native_fnc_noFly)) > 0;
        if (_inNf) then {
            _rows pushBack ["NOFLYDEL", "Retirer la zone interdite", "drone, tout le camp", _dir + "ui_close.paa", [0.95, 0.30, 0.25, 1]];
        } else {
            _rows pushBack ["NOFLY", "Zone interdite au drone", "rayon 200 m", _dir + "app_drone.paa", [0.95, 0.30, 0.25, 1]];
        };
        if (_nf > 1 || {_nf > 0 && {!_inNf}}) then {
            _rows pushBack ["NOFLYALL", "Retirer toutes les zones", format ["%1 zones, tout le camp", _nf], _dir + "ui_close.paa", [0.95, 0.30, 0.25, 1]];
        };
        if (_m isNotEqualTo "" && {([_m] call comspec_atak_native_fnc_markerDeletable) isEqualTo 1}) then {
            _rows pushBack ["MKEDIT", "Modifier le marqueur", "texte, type, couleur, taille", _dir + "map_marker.paa", _acc];
        };
        if (_m isNotEqualTo "") then {
            _rows pushBack ["COPYMK", "Copier le marqueur", "texte : nom, grille, altitude", _dir + "map_grid.paa", (COMSPEC_ATAK_Th get "mapText")];
            _rows pushBack ["DELETE", ["Supprimer le marqueur", "Effacer cette alerte"] select (([_m] call comspec_atak_native_fnc_markerDeletable) isEqualTo 2), [([markerText _m] call comspec_atak_native_fnc_markerAutoName), ""] select ((markerText _m) isEqualTo ""), _dir + "ui_close.paa", [0.95, 0.30, 0.25, 1]];
        };
        // Repères d'alerte reçus (CONTACT, WIA, SALUTE, PANIQUE…, locaux à ce téléphone) : tous d'un coup.
        private _na = { (_x find "COMSPEC_ALERT_") isEqualTo 0 || {(_x find "COMSPEC_PANIC_") isEqualTo 0} } count allMapMarkers;
        if (_na > 0) then { _rows pushBack ["ALERTSDEL", "Effacer les alertes", format ["%1 sur la carte", _na], _dir + "ui_bell.paa", [0.95, 0.30, 0.25, 1]]; };
        // Drone appairé (fn_droneAction) : une ligne ouvre le sous-menu des ordres au point cliqué.
        private _dr = missionNamespace getVariable ["COMSPEC_ATAK_Drone", objNull];
        private _drOk = !isNull _dr && {alive _dr};
        private _drCol = [0.30, 0.85, 0.95, 1];
        if (_drOk) then {
            private _dn = _dr getVariable ["COMSPEC_DroneName", ""];
            if (_dn isEqualTo "") then { _dn = getText (configOf _dr >> "displayName"); };
            _rows insert [0, [["DRONE", "Ordre au drone", _dn, _dir + "app_drone.paa", _drCol]]];
        };
        if (_drOk && {(_c getOrDefault ["sub", ""]) isEqualTo "DRONE"}) then {
            _rows = [
                ["DGOTO", "Aller", "puis stationnaire", _dir + "map_route.paa", _drCol],
                ["DHUNT", "Rechercher", format ["bandes, rayon %1 m", _s getOrDefault ["droneHuntRad", 250]], "\A3\ui_f\data\igui\cfg\simpletasks\types\search_ca.paa", _drCol],
                ["DHOVER", "Stationnaire", "caméra à la verticale", _dir + "app_drone.paa", _drCol],
                ["DLAND", "Atterrir", "se pose sur le point", "\A3\ui_f\data\igui\cfg\simpletasks\types\land_ca.paa", _drCol],
                ["BACK", "Retour", "", _dir + "ui_close.paa", (COMSPEC_ATAK_Th get "mapText")]
            ];
        };
        // Ordres aux IA (fn_aiOrder) : une ligne, puis les sous-menus ordres, « Ensuite… » (file d'ordres) et posture (avec la règle de tir).
        private _aiG = _c getOrDefault ["aiG", grpNull];
        private _aiCol = [1, 0.8, 0.25, 1];
        private _aiSub = _c getOrDefault ["sub", ""];
        private _aiWhy = _c getOrDefault ["aiWhy", ""];
        if (_aiSub isEqualTo "" && {!_card} && {_aiWhy isNotEqualTo "" || {!isNull _aiG && {({alive _x} count (units _aiG)) > 0}}}) then {
            _rows insert [0, [["AI", "Ordre aux IA", [groupId _aiG, "indisponible"] select (_aiWhy isNotEqualTo ""), _dir + "app_toc.paa", [_aiCol, [0.55, 0.6, 0.57, 1]] select (_aiWhy isNotEqualTo "")]]];
        };
        if (!isNull _aiG && {_aiWhy isEqualTo ""} && {_aiSub in ["AI", "AIPOST", "AITHEN"]}) then {
            private _e = (missionNamespace getVariable ["COMSPEC_ATAK_AiOrds", createHashMap]) getOrDefault [["netId", _aiG] call comspec_atak_native_fnc_aiOrder, createHashMap];
            private _cur = ["aucun ordre", _e getOrDefault ["lbl", ""]] select ((_e getOrDefault ["lbl", ""]) isNotEqualTo "");
            private _gs = ["groups", [str side group player, _pos]] call comspec_atak_native_fnc_aiOrder;
            private _head = [["AINEXT", ["label", _aiG] call comspec_atak_native_fnc_aiOrder, format ["groupe %1/%2", (_gs find _aiG) + 1, count _gs], _dir + "app_toc.paa", _aiCol]];
            private _ti = "\A3\ui_f\data\igui\cfg\simpletasks\types\";
            _rows = if (_aiSub isEqualTo "AITHEN") then { _head + [
                ["AI_THEN_MOVE", "Aller ici", "après l'ordre en cours", _dir + "map_route.paa", _aiCol],
                ["AI_THEN_PATROL", "Patrouiller ici", "après l'ordre en cours", _ti + "walk_ca.paa", _aiCol],
                ["AI_THEN_HOLD", "Tenir ici", "après l'ordre en cours", _ti + "defend_ca.paa", _aiCol],
                ["AI_THEN_WITHDRAW", "Se replier ici", "après l'ordre en cours", _ti + "run_ca.paa", _aiCol],
                ["BACK", "Retour", "ordres", _dir + "ui_close.paa", (COMSPEC_ATAK_Th get "mapText")]
            ] } else { if (_aiSub isEqualTo "AI") then { _head + [
                ["AI_MOVE", "Aller ici", _cur, _dir + "map_route.paa", _aiCol],
                ["AI_PATROL", "Patrouiller ici", "4 points, 120 m", _ti + "walk_ca.paa", _aiCol],
                ["AI_ATTACK", "Attaquer la zone", "fouille et détruit", _ti + "attack_ca.paa", [0.95, 0.35, 0.3, 1]],
                ["AI_SUPPRESS", "Supprimer la zone", "tir 30 s", _ti + "target_ca.paa", [0.95, 0.35, 0.3, 1]],
                ["AI_WITHDRAW", "Se replier ici", "vitesse rapide", _ti + "run_ca.paa", _aiCol],
                ["AI_HOLD", "Tenir ici", "", _ti + "defend_ca.paa", _aiCol],
                ["AI_FOLLOW", "Me suivre", "rejoint et suit", _ti + "meet_ca.paa", _aiCol],
                ["AI_BOARD", "Embarquer", "véhicule le plus proche", _ti + "getin_ca.paa", _aiCol],
                ["AI_DISMOUNT", "Débarquer", "", _ti + "getout_ca.paa", _aiCol],
                ["AI_GARRISON", "Occuper le bâtiment", "le plus proche, 60 m", _ti + "defend_ca.paa", _aiCol],
                ["AITHEN", "Ensuite…", "après l'ordre en cours", _ti + "walk_ca.paa", _aiCol],
                ["AIPOST", "Posture", "vitesse, formation, tir", _ti + "scout_ca.paa", _aiCol],
                ["AI_CANCEL", "Annuler l'ordre", "", _dir + "ui_close.paa", [0.95, 0.30, 0.25, 1]],
                ["BACK", "Retour", "", _dir + "ui_close.paa", (COMSPEC_ATAK_Th get "mapText")]
            ] } else { _head + ([
                ["AWARE", "Prudent"], ["COMBAT", "Combat"], ["STEALTH", "Furtif"],
                ["LIMITED", "Vitesse lente"], ["NORMAL", "Vitesse normale"], ["FULL", "Vitesse rapide"],
                ["COLUMN", "Colonne"], ["WEDGE", "En coin"], ["LINE", "En ligne"], ["FILE", "En file"],
                ["ROE_FREE", "Tir libre"], ["ROE_RETURN", "Riposte seulement"], ["ROE_HOLD", "Halte au feu"]
            ] apply {
                private _on = (_x select 0) in [_e getOrDefault ["beh", ""], _e getOrDefault ["spd", ""], _e getOrDefault ["frm", ""], _e getOrDefault ["roe", ""]];
                ["AI_" + (_x select 0), _x select 1, ["", "actuel"] select _on, _ti + "scout_ca.paa", _aiCol]
            }) + [
                ["BACK", "Retour", "ordres", _dir + "ui_close.paa", (COMSPEC_ATAK_Th get "mapText")]
            ] } };
        };
        // Supprimer ce qui est sous le clic : une ligne (un élément) ou un sous-menu (plusieurs) ; CONFIRMER au second clic.
        private _dels = _c getOrDefault ["dels", []];
        private _delCf = _c getOrDefault ["delCf", -1];
        private _delRow = {
            params ["_i"];
            (_dels select _i) params ["_k", "", "_n", "_mineE"];
            private _hide = !_mineE && {_k in ["ROUTE", "PLAN"]};
            private _what = switch (_k) do {
                case "ROUTE": { [format [("Supprimer la route %1") call comspec_atak_native_fnc_tr, _n], format [("Masquer chez moi la route %1") call comspec_atak_native_fnc_tr, _n]] select _hide };
                case "PLAN": { [format [("Supprimer le plan %1") call comspec_atak_native_fnc_tr, _n], format [("Masquer chez moi le plan %1") call comspec_atak_native_fnc_tr, _n]] select _hide };
                case "ZONE": { format [("Supprimer la zone %1") call comspec_atak_native_fnc_tr, _n] };
                case "AXIS": { format [("Supprimer l'axe %1") call comspec_atak_native_fnc_tr, _n] };
                case "LINE": { format [("Supprimer le trait %1") call comspec_atak_native_fnc_tr, _n] };
                case "CP": { format [("Supprimer le point de contrôle %1") call comspec_atak_native_fnc_tr, _n] };
                case "STEP": { format [("Supprimer l'étape %1") call comspec_atak_native_fnc_tr, _n] };
                default { format [("Effacer %1") call comspec_atak_native_fnc_tr, _n] };
            };
            ["DEL_" + str _i, [_what, ("CONFIRMER") call comspec_atak_native_fnc_tr] select (_delCf isEqualTo _i), ["deux clics, puis ANNULER", "second clic : c'est fait"] select (_delCf isEqualTo _i), _dir + "ui_trash.paa", [0.95, 0.30, 0.25, 1]]
        };
        if ((_c getOrDefault ["sub", ""]) isEqualTo "DEL") then {
            private _dr = [];
            for "_i" from 0 to ((count _dels) - 1) do { _dr pushBack ([_i] call _delRow); };
            _rows = _dr + [["BACK", "Retour", "", _dir + "ui_close.paa", (COMSPEC_ATAK_Th get "mapText")]];
        } else {
            private _top = (_c getOrDefault ["sub", ""]) isEqualTo "";
            if (_top && {!_card} && {(count _dels) isEqualTo 1}) then { _rows pushBack ([0] call _delRow); };
            if (_top && {!_card} && {(count _dels) > 1}) then { _rows pushBack ["DELS", "Supprimer…", format [("%1 éléments ici") call comspec_atak_native_fnc_tr, count _dels], _dir + "ui_trash.paa", [0.95, 0.30, 0.25, 1]]; };
        };
        // Zones : une ligne au menu principal, puis le sous-menu (tout part d'un clic : rien d'automatique).
        private _znSub = _c getOrDefault ["sub", ""];
        private _znCol = (COMSPEC_ATAK_Th get "mapOk");
        if (_znSub isEqualTo "" && {!_card}) then {
            _rows pushBack ["ZN", "Zones", "découper, plan, état", _dir + "app_zones.paa", _znCol];
        };
        if (_znSub isEqualTo "ZNAX") then {
            // Thèmes des noms des axes : MSR / ASR + nom du thème ; MIL-STD : nom de son sous-thème (ligne suivante).
            private _cur = profileNamespace getVariable ["COMSPEC_ATAK_ZnNameTheme", "MIL"];
            private _sub = profileNamespace getVariable ["COMSPEC_ATAK_ZnNameSub", "CITY"];
            _rows = ((["themeRows"] call comspec_atak_native_fnc_zoneAction) apply {
                ["ZX_" + (_x select 0), _x select 1, [((_x select 2) splitString ",") select [0, 2] joinString ",", "actuel"] select ((_x select 0) isEqualTo _cur), _dir + "map_route.paa", [_znCol, [1, 0.85, 0.2, 1]] select ((_x select 0) isEqualTo "MIL")]
            }) + [
                ["ZNAX_SUB", "Sous-thème MIL-STD (changer)", ["themeLabel", _sub] call comspec_atak_native_fnc_zoneAction, _dir + "map_route.paa", [1, 0.85, 0.2, 1]],
                ["ZN", "Retour", "zones", _dir + "ui_close.paa", (COMSPEC_ATAK_Th get "mapText")]
            ];
        };
        if (_znSub in ["ZN", "ZNST"]) then {
            private _zr = profileNamespace getVariable ["COMSPEC_ATAK_ZnCtxRad", 300];
            (_c getOrDefault ["zone", []]) params [["_zbid", ""], ["_zzid", ""]];
            private _zb = ["get", _zbid] call comspec_atak_native_fnc_zoneAction;
            private _zz = if (_zbid isEqualTo "") then { [] } else { ["zone", [_zbid, _zzid]] call comspec_atak_native_fnc_zoneAction };
            _rows = if (_znSub isEqualTo "ZNST") then {
                ((["states"] call comspec_atak_native_fnc_zoneAction) apply {
                    ["ZS_" + (_x select 0), _x select 1, ["", "actuel"] select ((_x select 0) isEqualTo (_zz param [6, ""])), _dir + "app_zones.paa", _x select 4]
                }) + [["ZN", "Retour", "zones", _dir + "ui_close.paa", (COMSPEC_ATAK_Th get "mapText")]]
            } else {
                private _r = [
                    ["ZN_RAD", "Rayon du découpage", format ["%1 m · changer", _zr], _dir + "app_zones.paa", _znCol],
                    ["ZG_G100", "Découper ici : grille 100 m", "nouveau plan", _dir + "app_zones.paa", _znCol],
                    ["ZG_G200", "Découper ici : grille 200 m", "nouveau plan", _dir + "app_zones.paa", _znCol],
                    ["ZG_G500", "Découper ici : grille 500 m", "nouveau plan", _dir + "app_zones.paa", _znCol],
                    ["ZG_BLOCK", "Découper ici : îlots", "par les routes", _dir + "app_zones.paa", _znCol],
                    ["ZG_RADIAL", "Découper ici : secteurs", "N, NE… et anneaux", _dir + "app_zones.paa", _znCol],
                    ["ZG_NEW", "Nouveau plan à partir d'ici", "ouvre l'app Zones", _dir + "app_zones.paa", _znCol],
                    ["ZG_MAGC", "Contour aimanté à partir d'ici", "à la route, app Zones", _dir + "app_zones.paa", [1, 0.85, 0.2, 1]],
                    ["ZG_MAGO", "Trait aimanté à partir d'ici", "pour découper, app Zones", _dir + "app_zones.paa", [1, 0.85, 0.2, 1]],
                    ["ZNAX", "Nommer les axes autour", "thème des noms ›", _dir + "map_route.paa", _znCol]
                ];
                if ((count _zz) > 0) then {
                    private _mineZ = ["isMine", _zbid] call comspec_atak_native_fnc_zoneAction;
                    _r = [
                        ["ZONE", format [("Fiche de la zone %1") call comspec_atak_native_fnc_tr, _zz select 1], _zb get "name", _dir + "app_zones.paa", _znCol],
                        ["ZNST", "Changer l'état", (((["state", _zz select 6] call comspec_atak_native_fnc_zoneAction) select 1) call comspec_atak_native_fnc_tr), _dir + "app_zones.paa", _znCol],
                        ["ZN_ENY", "Signaler un ennemi ici", "fiche de la zone", _dir + "app_zones.paa", [0.95, 0.35, 0.3, 1]],
                        ["ZN_CITE", "Citer la zone (@zone)", "dans un message", _dir + "app_zones.paa", _znCol],
                        ["ZN_VIS", ["Masquer ce plan", "Afficher ce plan"] select !(_zb getOrDefault ["vis", true]), _zb get "name", _dir + "app_zones.paa", _znCol]
                    ] + ([[], [
                        ["ZR_FILL", "Rendu : surligné", "cette zone", _dir + "app_zones.paa", _znCol],
                        ["ZR_LINE", "Rendu : contour", "cette zone", _dir + "app_zones.paa", _znCol],
                        ["ZR_POINT", "Rendu : point centré", "cette zone", _dir + "app_zones.paa", _znCol]
                    ]] select _mineZ) + _r;
                };
                _r + [["BACK", "Retour", "", _dir + "ui_close.paa", (COMSPEC_ATAK_Th get "mapText")]]
            };
        };
        private _rowH = _font * 1.55;
        private _headH = _font * 2.05;
        private _w = ((_font * 15) / _ratio) min (_bw * 0.9); // place pour le raccourci à droite des libellés longs
        // Fiche du relais : lignes « libellé · valeur » sous le nom.
        private _stats = [];
        if (_re isNotEqualTo []) then {
            _re params ["_o", "_ant", "_n", "_k", "_r", "_mb", "_ok", "_st", "_u", "_sl", "_bt", "_root", "_up", "_fq", "_auto", "_loss", "_hp", "_hl", "_hops", "_co", "_lat"];
            private _hc = createHashMapFromArray [["nominal", "#5cd96b"], ["dégradé", (COMSPEC_ATAK_Th get "hexMapWarn")], ["critique", "#f2685c"], ["hors service", "#9aa39e"]];
            private _km = { params ["_m"]; [format ["%1 m", round _m], format ["%1 km", (_m / 1000) toFixed 1]] select (_m >= 1000) };
            private _dme = player distance2D _ant;
            private _mine = _o isEqualTo (uiNamespace getVariable ["COMSPEC_ATAK_RelayLast", objNull]);
            private _T = { _this call comspec_atak_native_fnc_tr };
            private _tri = { params ["_v", "_a", "_b"]; ["#5cd96b", (COMSPEC_ATAK_Th get "hexMapWarn"), "#f2685c"] select ((parseNumber (_v > _a)) + (parseNumber (_v > _b))) };
            _stats = [
                ["État", format ["<t color='%1'>%2</t>%3", _hc getOrDefault [_hl, "#e6efe9"], _hl call _T, ["", format [" (%1)", _st call _T]] select (!_ok && {_st isNotEqualTo ""})]],
                ["Fréquence", format ["%1 MHz · %2%3", _fq, ["fixe", "auto"] select _auto call _T, ["", format [" · <t color='" + (COMSPEC_ATAK_Th get "hexMapWarn") + "'>%1</t>", format ["%1 sur le canal" call _T, _co]]] select (_co > 0)]],
                ["Perte · latence", ["<t color='#9aa39e'>" + ("aucun trafic" call _T) + "</t>", format ["<t color='%1'>%2 %%</t> · %3 ms", [_loss, 12, 40] call _tri, _loss, _lat]] select _ok],
                ["Débit · portée", format ["%1 Mbit/s · %2", _mb toFixed 1, [_r] call _km]],
                ["Structure · alim.", format ["<t color='%1'>%2 %%</t> · %3", [100 - _hp, 10, 50] call _tri, _hp,
                    ["secteur" call _T, format ["<t color='%1'>%2</t>", ["#e6efe9", (COMSPEC_ATAK_Th get "hexMapWarn")] select (_bt < 25), format ["batterie %1 %%" call _T, round _bt]]] select (_bt >= 0)]],
                ["Téléphones", [format ["%1 · %2", _u, "illimité" call _T], format ["<t color='%1'>%2</t>", ["#e6efe9", (COMSPEC_ATAK_Th get "hexMapWarn")] select (_u > _sl), format ["%1 / %2 places" call _T, _u, _sl]]] select (_sl > 0)],
                ["Liaison", switch (true) do {
                    case (_k isNotEqualTo "relay"): { ["passerelle de véhicule", "poste de commandement"] select (_k isEqualTo "cp") call _T };
                    case (_hops < 0): { "<t color='#f2685c'>" + ("sans liaison" call _T) + "</t>" };
                    case (_hops isEqualTo 0): { "raccordé (satellite ou fibre)" call _T };
                    default { format ["répéteur · %1 bond(s) via %2" call _T, _hops, ((_up getVariable ["COMSPEC_AtakRelayName", "relais"]) regexReplace ["[<>&]", ""]) select [0, 18]] };
                }],
                ["Distance", format ["%1 · %2°%3", [_dme] call _km, round (player getDir _ant), ["", " · <t color='#5cd96b'>" + ("utilisé" call _T) + "</t>"] select _mine]]
            ];
            _headH = _font * (1.35 + 0.98 * (count _stats)) + pixelH * 6;
            _w = ((_font * 19) / _ratio) min (_bw * 0.95);
        };
        // Menu long (ordres aux IA, posture) : lignes resserrées pour tenir dans la carte, jamais sous la hauteur du texte.
        _rowH = _rowH min ((((_bh - pixelH * 16 - _headH) / ((count _rows) max 1))) max (_font * 1.05));
        private _h = _headH + _rowH * (count _rows) + pixelH * 8;
        private _sp = _map ctrlMapWorldToScreen _pos;
        // Ouvert à droite et sous le curseur, recalé dans la carte s'il déborde (à gauche / au-dessus du point).
        private _x0 = (_sp select 0) + pixelW * 6;
        if (_x0 + _w > _bx + _bw - pixelW * 4) then { _x0 = (_sp select 0) - _w - pixelW * 6; };
        private _y0 = (_sp select 1) + pixelH * 6;
        if (_y0 + _h > _by + _bh - pixelH * 4) then { _y0 = (_sp select 1) - _h - pixelH * 6; };
        _x0 = (_x0 max (_bx + pixelW * 4)) min (_bx + _bw - _w - pixelW * 4);
        _y0 = (_y0 max (_by + pixelH * 4)) min (_by + _bh - _h - pixelH * 4);
        private _list = [];
        private _mk = {
            params ["_class", "_p", ["_text", ""]];
            private _ctl = _d ctrlCreate [_class, -1];
            _ctl ctrlSetPosition _p;
            if (_text isNotEqualTo "") then { _ctl ctrlSetText ((_text) call comspec_atak_native_fnc_tr); };
            _ctl ctrlCommit 0;
            _list pushBack _ctl;
            _ctl
        };
        // Repère du point cliqué (anneau à l'accent), ombre douce, carte sombre bordée, liseré d'accent en haut.
        private _ring = ["COMSPEC_RscIcon", [(_sp select 0) - _font * 0.45 / _ratio, (_sp select 1) - _font * 0.45, _font * 0.9 / _ratio, _font * 0.9], "\A3\ui_f\data\map\markers\military\circle_CA.paa"] call _mk;
        _ring ctrlSetTextColor _acc;
        { private _o = ["COMSPEC_RscText", [_x0 + pixelW * _x, _y0 + pixelH * _x, _w, _h]] call _mk; _o ctrlSetBackgroundColor [0, 0, 0, 0.22]; } forEach [2, 4];
        private _border = ["COMSPEC_RscText", [_x0 - pixelW, _y0 - pixelH, _w + pixelW * 2, _h + pixelH * 2]] call _mk;
        _border ctrlSetBackgroundColor [0.30, 0.36, 0.32, 0.9];
        private _bg = ["COMSPEC_RscText", [_x0, _y0, _w, _h]] call _mk;
        _bg ctrlSetBackgroundColor [0.035, 0.042, 0.038, 0.98];
        private _edge = ["COMSPEC_RscText", [_x0, _y0, _w, pixelH * 3]] call _mk;
        _edge ctrlSetBackgroundColor _acc;
        // En-tête : grille (police à chasse fixe), altitude, distance et azimut depuis moi.
        private _me = getPosATL player;
        private _dist = _me distance2D _pos;
        private _az = round (_me getDir _pos);
        private _hd = ["COMSPEC_RscStructuredText", [_x0 + pixelW * 8, _y0 + pixelH * 5, _w - pixelW * 16, _headH]] call _mk;
        if (_stats isNotEqualTo []) then {
            // Fiche du relais : nom et grille, puis une ligne par donnée (libellé à gauche, valeur à droite).
            private _lines = _stats apply { format ["<t size='0.8' color='" + (COMSPEC_ATAK_Th get "hexMapDim") + "'>%1</t><t size='0.8' align='right' color='#e6efe9'>%2</t>", (_x select 0) call comspec_atak_native_fnc_tr, _x select 1] };
            _hd ctrlSetStructuredText parseText (((format ["<t font='COMSPEC_BarlowBold' size='1' color='#e6efe9'>%1</t><t size='0.78' align='right' color='" + (COMSPEC_ATAK_Th get "hexMapDim") + "'>%2</t><br/>%3",
                ((_re select 2) regexReplace ["[<>&]", ""]) select [0, 26], [_pos, 8] call comspec_atak_native_fnc_gridRef, _lines joinString "<br/>"])) call comspec_atak_native_fnc_tr);
        } else {
            _hd ctrlSetStructuredText parseText ((format ["<t font='EtelkaMonospaceProBold' size='0.95' color='#e6efe9'>%1</t><br/><t size='0.78' color='" + (COMSPEC_ATAK_Th get "hexMapDim") + "'>%2 m · %3 · %4°</t>",
                [_pos, 8] call comspec_atak_native_fnc_gridRef, round (getTerrainHeightASL _pos),
                [format ["%1 m", round _dist], format ["%1 km", (_dist / 1000) toFixed 1]] select (_dist >= 1000), _az]) call comspec_atak_native_fnc_tr);
        };
        _hd ctrlSetFontHeight (_fs * 0.95);
        if (_m isNotEqualTo "") then { _hd ctrlSetTooltip ((format ["Marqueur pointé : %1", [([markerText _m] call comspec_atak_native_fnc_markerAutoName), _m] select ((markerText _m) isEqualTo "")]) call comspec_atak_native_fnc_tr); };
        private _ry = _y0 + pixelH * 6 + _headH;
        private _ih = _rowH * 0.56;
        private _iw = _ih / _ratio;
        {
            _x params ["_key", "_label", "_sub", "_icon", "_col"];
            private _div = ["COMSPEC_RscText", [_x0 + pixelW * 8, _ry, _w - pixelW * 16, pixelH]] call _mk;
            _div ctrlSetBackgroundColor [1, 1, 1, 0.07];
            // Pastille teintée sous l'icône.
            private _chip = ["COMSPEC_RscText", [_x0 + pixelW * 8, _ry + (_rowH - _ih * 1.45) / 2, _iw * 1.45, _ih * 1.45]] call _mk;
            _chip ctrlSetBackgroundColor [_col select 0, _col select 1, _col select 2, 0.16];
            private _ic = ["COMSPEC_RscIcon", [_x0 + pixelW * 8 + _iw * 0.225, _ry + (_rowH - _ih) / 2, _iw, _ih], _icon] call _mk;
            _ic ctrlSetTextColor _col;
            private _t = ["COMSPEC_RscStructuredText", [_x0 + pixelW * 16 + _iw * 1.45, _ry + (_rowH - _font * 0.95) / 2, _w - _iw * 1.45 - pixelW * 24, _rowH]] call _mk;
            _t ctrlSetStructuredText parseText ((format ["<t font='COMSPEC_BarlowBold' size='0.95' color='%1'>%2</t><t size='0.75' color='#7d8c85' align='right'>%3</t>",
                ["#e6efe9", "#f2685c"] select (_key in ["DELETE", "WPDEL", "ALERTSDEL", "VIEWDEL", "LOSDEL", "NOFLYALL"]), _label, (_sub regexReplace ["[<>&]", ""]) select [0, 22]]) call comspec_atak_native_fnc_tr);
            _t ctrlSetFontHeight _font;
            private _b = ["COMSPEC_RscButtonOverlay", [_x0, _ry, _w, _rowH]] call _mk;
            // Ligne exécutée à l'image suivante, hors du clic (2.9.40) : « close » supprimait ce bouton pendant son propre
            // gestionnaire et le moteur ne laissait plus passer aucun clic (carte figée après NOMMER LES AXES, tracé
            // impossible après ZONE DEPUIS LE CLIC, carte qui ne répond plus) ; même règle que fn_pageClear.
            _b ctrlAddEventHandler ["ButtonClick", compile format ["[{ ['act', '%1'] call comspec_atak_native_fnc_mapCtxMenu; }] call comspec_atak_native_fnc_nextFrame;", _key]];
            _ry = _ry + _rowH;
        } forEach _rows;
        // Suivis aussi par la page : un rendu complet (changement d'app) les efface avec le reste.
        private _pc = uiNamespace getVariable ["COMSPEC_ATAK_PageControls", []];
        _pc append _list;
        uiNamespace setVariable ["COMSPEC_ATAK_PageControls", _pc];
        uiNamespace setVariable ["COMSPEC_ATAK_MapCtxCtrls", _list];
    };
};
true
