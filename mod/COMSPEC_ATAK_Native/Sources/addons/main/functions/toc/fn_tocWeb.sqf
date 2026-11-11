/*
    Poste TOC web (Athena, /atak/toc) : la salle TOC du camp et les plans envoyés du site, dans les deux sens.
    Aucune nouvelle commande COMSPEC Link : on réutilise ce que la DLL sait déjà faire.
      - Jeu -> site : instantané de la salle (chronos, main courante, EXCHECK, demandes, FLASH, postes, pertes,
        satellites, écrans) par « BriefingPlan.Push » (POST /api/atak/briefing-plan, canal "toc_room"), au plus toutes
        les 3 s quand la salle change, et toutes les 60 s sinon (le site sait ainsi que le jeu est en liaison).
        Envoyé par un seul joueur du camp : celui qui tient le poste, sinon le plus petit UID du camp ; si personne n'a
        confirmé l'envoi depuis 90 s, un autre joueur relié du camp prend le relais. Les UID des joueurs ne partent pas.
      - Site -> jeu : commandes web « toc » (fil WC1 du poll des charges, fn_webCmdDispatch) : chaque client du camp
        visé les remet au serveur ("apply"), qui ne les applique qu'une fois (id de commande) par fn_tocRoom "do" ou
        fn_planServer "webPatch", puis les cite dans l'instantané suivant (liste "wc") : le site sait qu'elles sont prises.
    Règle d'autorité : le serveur du jeu fait foi ; deux changements sur une même ligne sont appliqués dans l'ordre
    d'arrivée au serveur, le dernier gagne. Les lignes nées sur le web (id W…) sont reliées à leur id du jeu
    (COMSPEC_ATAK_TocWebIds) pour les commandes suivantes du web.
    Rien ne part vers Athena avant l'acceptation de la licence ni en mode ATAK local. Aucune fenêtre du jeu.
    Modes :
      ["initServer"]                          serveur (fn_tocRoom "init") : événement comspec_atak_native_tocWeb
      ["cmd", [id, commande, réf, args]]      client (fn_webCmdDispatch, genre "toc") -> true si la commande est pour ce camp
      ["apply", [camp, id, commande, réf, [[clé, valeur]...], UID]]   serveur
      ["tick"]                                client, toutes les 3 s (XEH_postInitClient) : envoi de la salle au site
      ["payload", camp]                       partout : texte JSON envoyé (tests, journal)
*/
params [["_mode", "tick"], ["_a", []]];
private _fn = comspec_atak_native_fnc_tocWeb;
private _sides = ["WEST", "EAST", "GUER", "CIV"];
private _linkOk = {
    // Athena choisie, licence acceptée, COMSPEC Overwatch (connect) chargé.
    !isNil "comspec_overwatch_connect_fnc_extDirect"
    && {([] call comspec_overwatch_connect_fnc_athenaMode) isEqualTo "on"}
    && {(([] call comspec_overwatch_connect_fnc_licenceStatus) param [1, false])}
};
switch (_mode) do {
    case "initServer": {
        if (!isServer) exitWith {};
        if (missionNamespace getVariable ["COMSPEC_ATAK_TocWebInit", false]) exitWith {};
        missionNamespace setVariable ["COMSPEC_ATAK_TocWebInit", true];
        ["comspec_atak_native_tocWeb", { ["apply", _this] call comspec_atak_native_fnc_tocWeb; }] call CBA_fnc_addEventHandler;
    };
    case "cmd": {
        _a params [["_id", ""], ["_cmd", ""], ["_ref", ""], ["_args", createHashMap]];
        if (!hasInterface || {isNull player} || {!(_args isEqualType createHashMap)}) exitWith { false };
        private _side = str side group player;
        if ((_args getOrDefault ["s", ""]) isNotEqualTo _side) exitWith { false };
        if !(call _linkOk) exitWith { false };
        ["comspec_atak_native_tocWeb", [_side, _id, _cmd, _ref, (keys _args) apply { [_x, _args get _x] }, getPlayerUID player]] call CBA_fnc_serverEvent;
        // Compte rendu au site : la commande est remise au serveur du jeu (qui ne l'applique qu'une fois).
        [_id, "done", "Remise au serveur du poste"] call comspec_overwatch_connect_fnc_webCmdAck;
        true
    };
    case "apply": {
        if (!isServer) exitWith { false };
        _a params [["_side", ""], ["_wc", ""], ["_cmd", ""], ["_ref", ""], ["_pairs", []], ["_uid", ""]];
        if (!(_side in _sides) || {!(_wc isEqualType "")} || {_wc isEqualTo ""} || {!(_pairs isEqualType [])}) exitWith { false };
        // Une commande n'est appliquée qu'une fois, même remise par plusieurs joueurs du camp.
        private _seen = missionNamespace getVariable ["COMSPEC_ATAK_TocWebSeen", createHashMap];
        if (_wc in _seen) exitWith { false };
        _seen set [_wc, true];
        if ((count _seen) > 600) then { _seen = createHashMapFromArray [[_wc, true]]; };
        missionNamespace setVariable ["COMSPEC_ATAK_TocWebSeen", _seen];
        private _m = createHashMapFromArray (_pairs select { _x isEqualType [] && {(count _x) isEqualTo 2} });
        private _get = { params ["_k", ["_d", ""]]; private _v = _m getOrDefault [_k, _d]; if !(_v isEqualType "") then { _v = str _v; }; [_v, ""] select (_v isEqualTo "-") };
        private _who = format ["%1 (web)", (["by", "Poste web"] call _get) select [0, 40]];
        private _ids = missionNamespace getVariable ["COMSPEC_ATAK_TocWebIds", createHashMap];
        private _rid = { if ((_this select [0, 1]) isEqualTo "W") then { _ids getOrDefault [_this, _this] } else { _this } };
        private _do = { params ["_act", "_args"]; ["do", [_side, _act, _args, _who, ""]] call comspec_atak_native_fnc_tocRoom };
        private _last = { params ["_i"]; private _l = (["state", _side] call comspec_atak_native_fnc_tocRoom) select _i; if ((count _l) > 0) then { (_l select -1) select 0 } else { "" } };
        switch (_cmd) do {
            case "timeradd": {
                ["timerAdd", [["n"] call _get, ["k", "UP"] call _get, parseNumber (["v", "0"] call _get)]] call _do;
                _ids set [["w"] call _get, [1] call _last];
            };
            case "timerstart": { ["timerStart", _ref call _rid] call _do; };
            case "timerstop": { ["timerStop", _ref call _rid] call _do; };
            case "timerreset": { ["timerReset", _ref call _rid] call _do; };
            case "timerdel": { ["timerDel", _ref call _rid] call _do; };
            case "log": { ["log", ["NOTE", ["t"] call _get]] call _do; };
            case "flash": { ["flash", ["t"] call _get] call _do; };
            case "flashclear": { ["flashClear", []] call _do; };
            case "exadd": {
                ["exAdd", [["ev"] call _get, ["cw"] call _get, ["el"] call _get, ["at"] call _get]] call _do;
                _ids set [["w"] call _get, [4] call _last];
            };
            case "exdone": { ["exDone", _ref call _rid] call _do; };
            case "exundo": { ["exUndo", _ref call _rid] call _do; };
            case "exdel": { ["exDel", _ref call _rid] call _do; };
            case "reqset": {
                private _st = ["st", "PRISE"] call _get;
                if !(_st in ["RECUE", "PRISE", "REFUSEE", "TERMINEE"]) then { _st = "PRISE"; };
                ["reqSet", [_ref call _rid, _st, ["an"] call _get]] call _do;
            };
            case "kill": {
                private _k = ["k", "wia"] call _get;
                if (_k in ["wia", "kia", "ekia", "ewia"]) then { ["kill", [["el"] call _get, _k, parseNumber (["v", "0"] call _get)]] call _do; };
            };
            case "plnclr": {
                // Éléments déjà posés par ce plan web (id W<plan>_n) : retirés avant le nouvel envoi.
                private _plan = (missionNamespace getVariable [format ["COMSPEC_ATAK_Plan_%1", _side], [0, []]]) param [1, []];
                private _del = (_plan apply { _x select 0 }) select { (_x find _ref) isEqualTo 0 };
                if ((count _del) > 0) then { [objNull, "webPatch", [_side, _del, []]] call comspec_atak_native_fnc_planServer; };
            };
            case "plnadd": {
                // Points "x:y,x:y" répartis sur p0, p1… (fil limité à 480 caractères).
                private _pts = [];
                for "_i" from 0 to 9 do {
                    private _chunk = [format ["p%1", _i]] call _get;
                    if (_chunk isEqualTo "") exitWith {};
                    { private _xy = _x splitString ":"; if ((count _xy) isEqualTo 2) then { _pts pushBack [parseNumber (_xy select 0), parseNumber (_xy select 1)]; }; } forEach (_chunk splitString ",");
                };
                private _f = ["f", "w2"] call _get;
                private _w = parseNumber ((_f splitString "w") param [1, "2"]);
                private _item = [_ref select [0, 24], ["k"] call _get, _pts, ["t"] call _get, ["c", "WHITE"] call _get,
                    (_f find "d") >= 0, (_f find "a") >= 0, (_w max 1) min 3, 0, _who, ""];
                [objNull, "webPatch", [_side, [], [_item]]] call comspec_atak_native_fnc_planServer;
            };
            default {};
        };
        missionNamespace setVariable ["COMSPEC_ATAK_TocWebIds", _ids];
        // Commandes appliquées (64 dernières) : citées dans l'instantané suivant, le site les marque reprises.
        private _var = format ["COMSPEC_ATAK_TocWebDone_%1", _side];
        private _done = +(missionNamespace getVariable [_var, []]);
        _done pushBack (parseNumber _wc);
        if ((count _done) > 64) then { _done deleteRange [0, (count _done) - 64]; };
        missionNamespace setVariable [_var, _done, true];
        // Plan seul : la salle n'a pas bougé, mais le site doit apprendre que la commande est prise.
        missionNamespace setVariable [format ["COMSPEC_ATAK_TocWebDirty_%1", _side], true, true];
        true
    };
    case "payload": {
        private _side = _a;
        private _js = comspec_atak_native_fnc_json;
        (["state", _side] call comspec_atak_native_fnc_tocRoom) params [["_rev", 0], ["_tm", []], ["_sats", []], ["_log", []], ["_ex", []], ["_rq", []], ["_fl", []], ["_posts", []], ["_kills", []]];
        private _timers = _tm apply {
            _x params ["_id", "_n", "_k", "_v", "_run", "_acc", "_t0"];
            [_id, _n, _k, _v, _run, round (_acc + ([0, (serverTime - _t0) max 0] select _run))]
        };
        // UID des joueurs retirés (demandeur, opérateur du satellite, poste).
        private _reqs = _rq apply { private _r = +_x; _r set [4, ""]; _r };
        private _sat = _sats apply { _x params [["_x0", -1], ["_y0", -1], ["_hw", 500], ["_sen", "AUTO"], ["_fol", ""], "", ["_op", ""]]; [_x0, _y0, _hw, _sen, _fol, "", _op] };
        private _pst = _posts apply { _x params [["_p", ""], ["_o", ""], "", ["_t", ""]]; [_p, _o, "", _t] };
        private _flash = if ((count _fl) >= 3) then { [_fl select 0, _fl select 1, _fl select 2] } else { [] };
        private _scr = ((missionNamespace getVariable ["COMSPEC_ATAK_TocScreens", []]) select { !isNull _x && {((_x getVariable ["COMSPEC_ATAK_TocScr", []]) param [1, ""]) in ["", _side]} }) apply {
            private _c = _x getVariable ["COMSPEC_ATAK_TocScr", []];
            [_c param [0, ""], _c param [3, ""], _x getVariable ["COMSPEC_ATAK_TocHall", ""]]
        };
        private _room = createHashMapFromArray [
            ["timers", _timers], ["log", _log select [((count _log) - 150) max 0]], ["excheck", _ex], ["requests", _reqs], ["flash", _flash],
            ["posts", _pst], ["kills", _kills], ["sats", _sat], ["screens", _scr select [0, 40]],
            ["local", [dayTime, "HH:MM:SS"] call BIS_fnc_timeToString], ["date", format ["%1-%2-%3", date select 0, date select 1, date select 2]]
        ];
        private _body = createHashMapFromArray [
            ["channel", "toc_room"], ["side", _side], ["rev", _rev], ["world", worldName],
            ["mission_key", missionNamespace getVariable ["COMSPEC_ATAK_MissionKey", format ["%1@%2", missionName, worldName]]],
            ["mapId", missionNamespace getVariable ["comspec_overwatch_map_id", 1]],
            ["wc", missionNamespace getVariable [format ["COMSPEC_ATAK_TocWebDone_%1", _side], []]],
            ["room", _room]
        ];
        [_body] call _js
    };
    case "tick": {
        if (!hasInterface || {isNull player} || {!alive player}) exitWith { false };
        if !(call _linkOk) exitWith { false };
        private _side = str side group player;
        if !(_side in _sides) exitWith { false };
        private _st = ["state", _side] call comspec_atak_native_fnc_tocRoom;
        private _rev = _st select 0;
        private _dirtyVar = format ["COMSPEC_ATAK_TocWebDirty_%1", _side];
        private _sentVar = format ["COMSPEC_ATAK_TocWebSent_%1", _side];
        (missionNamespace getVariable [_sentVar, [-1, -1e9]]) params ["_sentRev", "_sentAt"];
        private _dirty = missionNamespace getVariable [_dirtyVar, false];
        // Qui envoie : celui qui tient le poste, sinon le plus petit UID du camp ; relais par un autre après 90 s sans envoi.
        private _post = (_st select 7) param [0, []];
        private _holder = _post param [2, ""];
        private _camp = (allPlayers select { (str side group _x) isEqualTo _side }) apply { getPlayerUID _x };
        _camp sort true;
        private _me = getPlayerUID player;
        private _chosen = [_camp param [0, ""], _holder] select (_holder in _camp);
        private _late = (serverTime - _sentAt) > (90 + random 30);
        if (_me isNotEqualTo _chosen && {!_late}) exitWith { false };
        private _changed = _rev isNotEqualTo _sentRev || _dirty;
        if (!_changed && {(serverTime - _sentAt) < 60}) exitWith { false };
        if ((serverTime - (missionNamespace getVariable ["COMSPEC_ATAK_TocWebTry", -1e9])) < 3) exitWith { false };
        missionNamespace setVariable ["COMSPEC_ATAK_TocWebTry", serverTime];
        [_side, _rev, _sentVar, _dirtyVar] spawn {
            params ["_side", "_rev", "_sentVar", "_dirtyVar"];
            private _json = ["payload", _side] call comspec_atak_native_fnc_tocWeb;
            if ((count _json) > 190000) exitWith { ["WARN", "TOC", format ["Salle TOC trop lourde pour Athena (%1 octets)", count _json]] call comspec_atak_native_fnc_log; };
            private _r = (["COMSPECExtension", "BriefingPlan.Push", [_json]] call comspec_overwatch_connect_fnc_extDirect);
            if (_r isEqualType []) then { _r = _r param [0, ""]; };
            if ((_r select [0, 3]) isEqualTo "OK|") then {
                missionNamespace setVariable [_sentVar, [_rev, serverTime], true];
                missionNamespace setVariable [_dirtyVar, false, true];
            } else {
                ["INFO", "TOC", format ["Salle TOC vers Athena : %1", _r]] call comspec_atak_native_fnc_log;
            };
        };
        true
    };
    default { false };
};
