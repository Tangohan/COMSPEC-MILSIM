/*
    Boîte de réception du TOC sans téléphone (4.10.0). Avant, un message du poste était jeté si le destinataire n'avait
    pas de téléphone (fn_tocAction « recv »), un ordre n'arrivait que dans l'app Tâches, la réponse du poste à une
    demande et les retours des actions n'étaient qu'un bandeau du téléphone. Ici tout est gardé sur le poste du joueur,
    lisible dans une fenêtre (action ACE sur soi « Messages et ordres du TOC », ou touche à choisir dans
    Options > Commandes > COMSPEC ATAK), et rendu aux écrans et au PC du poste par fn_tocPanels. Jamais d'alerte du
    jeu : ni hint, ni texte à l'écran, ni bandeau ; le compteur de non-lus est dans le nom de l'action ACE.

    Entrée de la boîte : [id, genre "MSG" | "ANSWER" | "ORDER" | "FEEDBACK", heure, de, priorité, texte, lu, référence, instant]
    Ordres du camp tenus par le serveur (tableau « Ordres » des écrans, publié avec l'ORBAT par fn_tocTracks « publish ») :
      [id, heure, type, cible ("" : tout le camp), émetteur, consigne, priorité, [x, y] ou [],
       accusés [[élément ou nom, statut "SEEN" | "ACK" | "EXEC" | "DONE" | "FAILED", heure, saisi par ("" : lui-même)]...], instant]

    Modes (client) :
      ["initClient"]                 écoute des messages, réponses et ordres ; action ACE et touche
      ["push", [genre, de, priorité, texte, référence, id, heure]]   range une entrée (id déjà connu : ignorée)
      ["list", genres]               entrées, les plus récentes d'abord ([] : toutes)
      ["unread"]                     nombre d'entrées non lues (hors retours d'action)
      ["readAll"]                    tout marquer lu
      ["orders"]                     mes ordres : [[id, heure, type, émetteur, cible, consigne, priorité, mon statut, [x, y], instant]...]
      ["ack", [id, statut]]          j'accuse un ordre ("ACK" reçu | "EXEC" en cours | "DONE" terminé | "FAILED" impossible)
      ["requests"]                   mes demandes au TOC (lignes de la salle, fn_tocRoom) et leur réponse
      ["feedback", [type, texte, app]]   retour d'une action : ligne d'état du PC du poste, ligne du Zeus, journal de la boîte
      ["lastFeedback"]               [texte, type, âge en s] du dernier retour, [] s'il a plus de 8 s
      ["open"]                       ouvre la fenêtre
    Modes (serveur) :
      ["initServer"]                 tableau des ordres du camp (ordres émis, accusés reçus)
      ["board", camp]                ordres du camp, les 20 derniers
      ["ackFor", [camp, opérateur, UID, [id, élément, statut]]]   accusé saisi par l'opérateur du poste au nom d'un élément
                                     (compte rendu radio) ; appelé par fn_tocEntry « apply », émetteur déjà vérifié
    Exemple : ["ack", [_id, "ACK"]] call comspec_atak_native_fnc_tocInbox;
*/
params [["_mode", "list"], ["_a", []]];
private _fn = comspec_atak_native_fnc_tocInbox;
private _rank = { (["SEEN", "ACK", "EXEC", "DONE", "FAILED"] find (toUpper _this)) max 0 };
private _stLabel = createHashMapFromArray [["NEW", "à lire"], ["SEEN", "vu"], ["ACK", "reçu"], ["EXEC", "en cours"], ["DONE", "terminé"], ["FAILED", "impossible"]];
switch (_mode) do {
    case "initServer": {
        if (!isServer || {!isNil "COMSPEC_ATAK_TocOrders"}) exitWith {};
        COMSPEC_ATAK_TocOrders = createHashMap;
        ["comspec_atak_native_localOrder", {
            params [["_pairs", []], ["_side", ""]];
            if (!(_pairs isEqualType []) || {!(_side in ["WEST", "EAST", "GUER", "CIV"])}) exitWith {};
            private _o = createHashMapFromArray (_pairs select { _x isEqualType [] && {(count _x) >= 2} });
            private _id = _o getOrDefault ["id", ""];
            if (!(_id isEqualType "") || {_id isEqualTo ""}) exitWith {};
            private _l = COMSPEC_ATAK_TocOrders getOrDefault [_side, []];
            if ((_l findIf { (_x select 0) isEqualTo _id }) >= 0) exitWith {};
            private _p = _o getOrDefault ["pos", []];
            private _lbl = _o getOrDefault ["typeLabel", ""];
            if (_lbl isEqualTo "") then { _lbl = _o getOrDefault ["type", "ORDRE"]; };
            private _s = { private _v = _o getOrDefault [_this select 0, ""]; if !(_v isEqualType "") then { _v = str _v; }; _v select [0, _this select 1] };
            _l pushBack [_id select [0, 48], ["time", 8] call _s, _lbl select [0, 24], ["target", 32] call _s, ["issuer", 40] call _s, ["payload", 160] call _s, ["priority", 12] call _s,
                [[], [round (_p select 0), round (_p select 1)]] select (_p isEqualType [] && {(count _p) >= 2}), [], serverTime];
            if ((count _l) > 20) then { _l deleteRange [0, (count _l) - 20]; };
            COMSPEC_ATAK_TocOrders set [_side, _l];
            ["dirty", _side] call comspec_atak_native_fnc_tocTracks;
        }] call CBA_fnc_addEventHandler;
        ["comspec_atak_native_orderAck", {
            params [["_id", ""], ["_status", ""], ["_side", ""], "", ["_byName", ""], ["_members", []], ["_lead", false], ["_time", ""], ["_gname", ""]];
            private _l = COMSPEC_ATAK_TocOrders getOrDefault [_side, []];
            private _i = _l findIf { (_x select 0) isEqualTo _id };
            if (_i < 0 || {!(_status isEqualType "")} || {!(_byName isEqualType "")} || {!(_gname isEqualType "")}) exitWith {};
            private _rk = { (["SEEN", "ACK", "EXEC", "DONE", "FAILED"] find (toUpper _this)) max 0 };
            // Le chef répond pour son groupe : une ligne au nom du groupe ; sinon une ligne au nom du joueur.
            private _who = ([_byName, _gname] select (_lead isEqualTo true && {_gname isNotEqualTo ""})) select [0, 32];
            // Accusé saisi par l'opérateur du poste (fn_tocInbox « ackFor ») : « TOC · … » est gardé comme auteur.
            private _by = ["", _byName select [0, 40]] select ((_byName select [0, 3]) isEqualTo "TOC");
            private _acks = (_l select _i) select 8;
            private _j = _acks findIf { (_x select 0) isEqualTo _who };
            if (_j >= 0 && {(toUpper _status) isEqualTo "SEEN"}) exitWith {};
            if (_j >= 0 && {((toUpper _status) call _rk) < (((_acks select _j) select 1) call _rk)} && {_by isEqualTo ""}) exitWith {};
            private _row = [_who, toUpper (_status select [0, 8]), [_time, ""] select !(_time isEqualType ""), _by];
            if (_j >= 0) then { _acks set [_j, _row]; } else { if ((count _acks) < 24) then { _acks pushBack _row; }; };
            ["dirty", _side] call comspec_atak_native_fnc_tocTracks;
        }] call CBA_fnc_addEventHandler;
    };
    case "board": {
        if (!isServer || {isNil "COMSPEC_ATAK_TocOrders"}) exitWith { [] };
        +(COMSPEC_ATAK_TocOrders getOrDefault [_a, []])
    };
    case "ackFor": {
        if (!isServer) exitWith { false };
        if (isRemoteExecuted && {!(remoteExecutedOwner in [0, 2])} && {isNil "_tocTrusted"}) exitWith { false };
        _a params [["_side", ""], ["_who", ""], ["_uid", ""], ["_args", []]];
        _args params [["_id", ""], ["_el", ""], ["_status", "ACK"]];
        if (!(_id isEqualType "") || {!(_el isEqualType "")} || {!(_status isEqualType "")}) exitWith { false };
        _status = toUpper _status;
        _el = (trim _el) select [0, 32];
        if (_id isEqualTo "" || {_el isEqualTo ""} || {!(_status in ["ACK", "EXEC", "DONE", "FAILED"])}) exitWith { false };
        if (((COMSPEC_ATAK_TocOrders getOrDefault [_side, []]) findIf { (_x select 0) isEqualTo _id }) < 0) exitWith { false };
        // Joueurs de l'élément : leur ordre prend ce statut ; élément sans joueur (IA, groupe absent) : ligne à son nom.
        private _members = [];
        {
            if ((str side _x) isEqualTo _side && {(toLower (groupId _x)) isEqualTo (toLower _el)}) then {
                { if (isPlayer _x && {alive _x}) then { _members pushBack [getPlayerUID _x, name _x]; }; } forEach (units _x);
            };
        } forEach allGroups;
        if (_members isEqualTo []) then { _members = [[format ["grp:%1", _el], _el]]; };
        ["comspec_atak_native_sideEvent", ["comspec_atak_native_orderAck",
            [_id, _status, _side, _uid, format ["TOC · %1", _who select [0, 32]], _members, true, [dayTime, "HH:MM"] call BIS_fnc_timeToString, _el], _side]] call CBA_fnc_localEvent;
        true
    };
    case "initClient": {
        if (!hasInterface || {missionNamespace getVariable ["COMSPEC_ATAK_TocInboxInit", false]}) exitWith {};
        missionNamespace setVariable ["COMSPEC_ATAK_TocInboxInit", true];
        // Message du poste : gardé même sans téléphone (avant : jeté).
        ["comspec_atak_native_tocMsg", {
            params [["_cs", "TOC"], ["_body", ""], ["_time", ""], ["_prio", "ROUTINE"], ["_side", ""], ["_id", ""], ["_uid", ""], ["_to", ""]];
            if (isNull player || {_side isNotEqualTo (str side group player)} || {_uid isEqualTo (getPlayerUID player)}) exitWith {};
            if (_to isNotEqualTo "" && {_to isNotEqualTo (getPlayerUID player)}) exitWith {};
            ["push", ["MSG", format ["TOC · %1", _cs], _prio, _body, "", _id, _time]] call comspec_atak_native_fnc_tocInbox;
        }] call CBA_fnc_addEventHandler;
        // Réponse du poste à ma demande.
        ["comspec_atak_native_tocReqAnswer", {
            params [["_k", ""], ["_lbl", ""], ["_ans", ""], ["_who", ""]];
            ["push", ["ANSWER", _who, "", format ["%1 %2%3", _k, _lbl, ["", " : " + _ans] select (_ans isNotEqualTo "")]]] call comspec_atak_native_fnc_tocInbox;
        }] call CBA_fnc_addEventHandler;
        // Ordre pour moi, mon groupe ou tout le camp (même règle que fn_orderLocal « recv »).
        ["comspec_atak_native_localOrder", {
            params [["_pairs", []], ["_side", ""]];
            if (isNull player || {_side isNotEqualTo (str side group player)} || {!(_pairs isEqualType [])}) exitWith {};
            private _o = createHashMapFromArray (_pairs select { _x isEqualType [] && {(count _x) >= 2} });
            if ((_o getOrDefault ["issuerUid", ""]) isEqualTo (getPlayerUID player)) exitWith {};
            private _tg = toLower (_o getOrDefault ["target", ""]);
            private _forMe = (_o getOrDefault ["targetType", "all"]) isEqualTo "all" || {_tg isEqualTo ""}
                || {(_o getOrDefault ["targetRef", ""]) isEqualTo ([player] call comspec_atak_native_fnc_unitGroup)}
                || {_tg isEqualTo (toLower (groupId group player))} || {_tg isEqualTo (toLower (name player))};
            if (!_forMe) exitWith {};
            private _lbl = _o getOrDefault ["typeLabel", ""];
            if (_lbl isEqualTo "") then { _lbl = _o getOrDefault ["type", "ORDRE"]; };
            ["push", ["ORDER", _o getOrDefault ["issuer", "TOC"], _o getOrDefault ["priority", ""], format ["%1 : %2", _lbl, _o getOrDefault ["payload", ""]],
                _o getOrDefault ["id", ""], "O-" + (_o getOrDefault ["id", ""]), _o getOrDefault ["time", ""]]] call comspec_atak_native_fnc_tocInbox;
        }] call CBA_fnc_addEventHandler;
        ["COMSPEC ATAK", "TocInbox", ["Messages et ordres du TOC (sans téléphone)", "Ouvre la fenêtre des messages du poste, de vos ordres à accuser et des réponses à vos demandes. Aucune touche par défaut."],
            { ["open"] call comspec_atak_native_fnc_tocInbox; true }, "", [0, [false, false, false]]] call CBA_fnc_addKeybind;
        if (!isNil "ace_interact_menu_fnc_createAction") then {
            private _act = ["COMSPEC_ATAK_TocInbox", "Messages et ordres du TOC", "", { ["open"] call comspec_atak_native_fnc_tocInbox; },
                { missionNamespace getVariable ["comspec_atak_native_toc_enabled", true] }, {}, [], "", 4, [false, false, false, false, false],
                {
                    // Non-lus dans le nom de l'action : seul signal, aucune alerte à l'écran.
                    params ["", "", "", "_ad"];
                    private _n = ["unread"] call comspec_atak_native_fnc_tocInbox;
                    _ad set [1, format ["%1%2", ("Messages et ordres du TOC") call comspec_atak_native_fnc_tr, ["", format [" (%1)", _n]] select (_n > 0)]];
                }] call comspec_atak_native_fnc_aceAction;
            ["CAManBase", 1, ["ACE_SelfActions"], _act, true] call ace_interact_menu_fnc_addActionToClass;
        };
    };
    case "push": {
        _a params [["_kind", "MSG"], ["_from", ""], ["_prio", ""], ["_text", ""], ["_ref", ""], ["_id", ""], ["_time", ""]];
        if (!hasInterface || {!(_text isEqualType "")} || {_text isEqualTo ""}) exitWith { false };
        private _box = missionNamespace getVariable ["COMSPEC_ATAK_TocInbox", []];
        if (_id isEqualTo "") then {
            private _seq = (missionNamespace getVariable ["COMSPEC_ATAK_TocInboxSeq", 0]) + 1;
            missionNamespace setVariable ["COMSPEC_ATAK_TocInboxSeq", _seq];
            _id = format ["B%1", _seq];
        };
        if ((_box findIf { (_x select 0) isEqualTo _id }) >= 0) exitWith { false };
        if (_time isEqualTo "") then { _time = [dayTime, "HH:MM"] call BIS_fnc_timeToString; };
        if !(_from isEqualType "") then { _from = str _from; };
        _box pushBack [_id, _kind, _time, _from select [0, 48], _prio, _text select [0, 400], false, _ref, diag_tickTime];
        // Les retours d'action partent les premiers : 60 entrées au plus.
        if ((count _box) > 60) then {
            private _i = _box findIf { (_x select 1) isEqualTo "FEEDBACK" };
            _box deleteAt ([_i, 0] select (_i < 0));
        };
        missionNamespace setVariable ["COMSPEC_ATAK_TocInbox", _box];
        ["comspec_atak_native_tocInbox", [_id, _kind]] call CBA_fnc_localEvent;
        if (!isNull (uiNamespace getVariable ["COMSPEC_ATAK_TocInboxUi", displayNull])) then { ["uiFill"] call _fn; };
        true
    };
    case "list": {
        private _box = +(missionNamespace getVariable ["COMSPEC_ATAK_TocInbox", []]);
        if (_a isEqualType [] && {_a isNotEqualTo []}) then { _box = _box select { (_x select 1) in _a }; };
        reverse _box;
        _box
    };
    case "unread": { { !(_x select 6) && {(_x select 1) isNotEqualTo "FEEDBACK"} } count (missionNamespace getVariable ["COMSPEC_ATAK_TocInbox", []]) };
    case "readAll": {
        { _x set [6, true]; } forEach (missionNamespace getVariable ["COMSPEC_ATAK_TocInbox", []]);
        true
    };
    case "orders": {
        if (!hasInterface || {isNull player}) exitWith { [] };
        private _tasks = (uiNamespace getVariable ["COMSPEC_ATAK_Data", createHashMap]) getOrDefault ["localTasks", createHashMap];
        private _me = getPlayerUID player;
        private _out = [];
        {
            private _o = _y;
            private _mine = ((["list", [_x]] call comspec_atak_native_fnc_orderAck) getOrDefault [_me, []]) param [1, ""];
            private _st = _o getOrDefault ["status", "NEW"];
            if (_mine isNotEqualTo "" && {(_mine call _rank) >= (_st call _rank)}) then { _st = _mine; };
            private _lbl = _o getOrDefault ["typeLabel", ""];
            if (_lbl isEqualTo "") then { _lbl = _o getOrDefault ["type", "ORDRE"]; };
            _out pushBack [_o getOrDefault ["stamp", 0], [_x, _o getOrDefault ["time", ""], _lbl, _o getOrDefault ["issuer", ""], _o getOrDefault ["target", ""], _o getOrDefault ["payload", ""],
                _o getOrDefault ["priority", ""], _st, _o getOrDefault ["pos", []], _o getOrDefault ["stamp", 0]]];
        } forEach _tasks;
        _out sort false;
        _out apply { _x select 1 }
    };
    case "ack": {
        _a params [["_id", ""], ["_status", "ACK"]];
        if (!hasInterface || {isNull player} || {_id isEqualTo ""} || {!((toUpper _status) in ["ACK", "EXEC", "DONE", "FAILED"])}) exitWith { false };
        ["send", [_id, toUpper _status]] call comspec_atak_native_fnc_orderAck;
        private _tasks = (uiNamespace getVariable ["COMSPEC_ATAK_Data", createHashMap]) getOrDefault ["localTasks", createHashMap];
        private _o = _tasks getOrDefault [_id, createHashMap];
        if ((count _o) > 0) then { _o set ["status", toUpper _status]; };
        { if ((_x select 7) isEqualTo _id) then { _x set [6, true]; }; } forEach (missionNamespace getVariable ["COMSPEC_ATAK_TocInbox", []]);
        ["feedback", ["INFO", format ["Ordre : réponse « %1 » envoyée au poste", _stLabel getOrDefault [toUpper _status, _status]]]] call _fn;
        true
    };
    case "requests": {
        if (!hasInterface || {isNull player}) exitWith { [] };
        private _me = getPlayerUID player;
        private _rq = ((["state", str side group player] call comspec_atak_native_fnc_tocRoom) select 5) select { (_x param [4, ""]) isEqualTo _me };
        reverse _rq;
        _rq
    };
    case "lastFeedback": {
        private _l = missionNamespace getVariable ["COMSPEC_ATAK_TocFeedback", ["", "", -1e9]];
        if ((diag_tickTime - (_l select 2)) > 8) exitWith { [] };
        [_l select 0, _l select 1, diag_tickTime - (_l select 2)]
    };
    case "feedback": {
        _a params [["_type", "INFO"], ["_text", ""]];
        if (!hasInterface || {isNull player} || {!(_text isEqualType "")} || {_text isEqualTo ""}) exitWith { false };
        if !(_type isEqualType "") then { _type = "INFO"; };
        private _last = missionNamespace getVariable ["COMSPEC_ATAK_TocFeedback", ["", "", -1e9]];
        // Même texte deux fois de suite (fn_notify et l'appel direct) : une seule ligne.
        if ((_last select 0) isEqualTo _text && {(diag_tickTime - (_last select 2)) < 2}) exitWith { false };
        missionNamespace setVariable ["COMSPEC_ATAK_TocFeedback", [_text, toUpper _type, diag_tickTime]];
        private _pcOpen = ((player getVariable ["COMSPEC_ATAK_TocView", []]) param [0, ""]) isNotEqualTo "";
        private _zeus = !isNull (findDisplay 312);
        // Là où le joueur regarde : ligne d'état du PC du poste, ligne de retour du Zeus.
        if (_pcOpen) then { ["status", _text] call comspec_atak_native_fnc_tocLaptop; };
        if (_zeus) then { ["zeusLine", [_text, toUpper _type]] call _fn; };
        // Journal de la boîte : au PC, en Zeus, ou près d'un écran ou d'un poste du TOC (action ACE d'un écran).
        private _near = _pcOpen || {_zeus} || {
            private _p = player;
            ((missionNamespace getVariable ["COMSPEC_ATAK_TocScreens", []]) findIf { !isNull _x && {(_x distance _p) < 15} }) >= 0
        };
        if (_near) then { ["push", ["FEEDBACK", "", toUpper _type, _text]] call _fn; };
        ["comspec_atak_native_tocFeedback", [_text, toUpper _type]] call CBA_fnc_localEvent;
        true
    };
    case "zeusLine": {
        _a params [["_text", ""], ["_type", "INFO"]];
        disableSerialization;
        private _d = findDisplay 312;
        if (isNull _d || {_text isEqualTo ""}) exitWith { false };
        private _c = uiNamespace getVariable ["COMSPEC_ATAK_TocZeusLine", controlNull];
        if (isNull _c) then {
            _c = _d ctrlCreate ["RscStructuredText", -1];
            uiNamespace setVariable ["COMSPEC_ATAK_TocZeusLine", _c];
            _c ctrlSetPosition [safeZoneX + safeZoneW * 0.3, safeZoneY + safeZoneH * 0.93, safeZoneW * 0.4, safeZoneH * 0.032];
            _c ctrlSetBackgroundColor (["rgba", ["bg1", 0.92]] call comspec_atak_native_fnc_tocTheme);
            _c ctrlCommit 0;
        };
        private _tok = ["accent", "amber"] select (_type in ["WARNING", "ERROR"]);
        _c ctrlSetStructuredText parseText format ["<t align='center' size='0.9' color='%1'>TOC · %2</t>", ["hex", _tok] call comspec_atak_native_fnc_tocTheme, _text call comspec_atak_native_fnc_tr];
        _c ctrlShow true;
        private _stamp = diag_tickTime;
        uiNamespace setVariable ["COMSPEC_ATAK_TocZeusLineAt", _stamp];
        [{
            disableSerialization;
            if ((uiNamespace getVariable ["COMSPEC_ATAK_TocZeusLineAt", 0]) isNotEqualTo _this) exitWith {};
            private _c = uiNamespace getVariable ["COMSPEC_ATAK_TocZeusLine", controlNull];
            if (!isNull _c) then { ctrlDelete _c; };
        }, _stamp, 6] call CBA_fnc_waitAndExecute;
        true
    };
    case "open": {
        if (!hasInterface || {isNull player}) exitWith { false };
        disableSerialization;
        private _parent = [findDisplay 46, findDisplay 312] select (!isNull (findDisplay 312));
        if (isNull _parent) exitWith { false };
        private _old = uiNamespace getVariable ["COMSPEC_ATAK_TocInboxUi", displayNull];
        if (!isNull _old) then { _old closeDisplay 2; };
        private _d = _parent createDisplay "RscDisplayEmpty";
        if (isNull _d) exitWith { false };
        uiNamespace setVariable ["COMSPEC_ATAK_TocInboxUi", _d];
        private _x0 = safeZoneX + safeZoneW * 0.2;
        private _y0 = safeZoneY + safeZoneH * 0.16;
        private _w = safeZoneW * 0.6;
        private _h = safeZoneH * 0.66;
        private _row = safeZoneH * 0.04;
        private _pad = safeZoneW * 0.008;
        private _rgba = { ["rgba", _this] call comspec_atak_native_fnc_tocTheme };
        private _mk = {
            params ["_cls", "_pos", ["_bg", []], ["_idc", -1]];
            private _c = _d ctrlCreate [_cls, _idc];
            _c ctrlSetPosition _pos;
            if (_bg isNotEqualTo []) then { _c ctrlSetBackgroundColor _bg; };
            _c ctrlCommit 0;
            _c
        };
        ["RscText", [_x0, _y0, _w, _h], ["bg0", 0.97] call _rgba] call _mk;
        private _title = ["RscText", [_x0, _y0, _w, _row], ["bg2", 1] call _rgba] call _mk;
        _title ctrlSetText (("TOC · messages, ordres et demandes") call comspec_atak_native_fnc_tr);
        _title ctrlSetTextColor (["text", 1] call _rgba);
        private _lw = _w * 0.46;
        private _lb = ["RscListBox", [_x0 + _pad, _y0 + _row + _pad, _lw, _h - _row * 2 - _pad * 3], ["bg1", 1] call _rgba, 9101] call _mk;
        ["RscStructuredText", [_x0 + _lw + _pad * 2, _y0 + _row + _pad, _w - _lw - _pad * 3, _h - _row * 2 - _pad * 3], ["bg1", 1] call _rgba, 9102] call _mk;
        private _bw = (_w - _pad * 6) / 5;
        {
            _x params ["_txt", "_code"];
            private _b = ["RscButtonMenu", [_x0 + _pad + (_bw + _pad) * _forEachIndex, _y0 + _h - _row - _pad, _bw, _row]] call _mk;
            _b ctrlSetText (_txt call comspec_atak_native_fnc_tr);
            _b ctrlAddEventHandler ["ButtonClick", _code];
        } forEach [
            ["Reçu", { ["uiAck", "ACK"] call comspec_atak_native_fnc_tocInbox; }],
            ["En cours", { ["uiAck", "EXEC"] call comspec_atak_native_fnc_tocInbox; }],
            ["Terminé", { ["uiAck", "DONE"] call comspec_atak_native_fnc_tocInbox; }],
            ["Tout marquer lu", { ["readAll"] call comspec_atak_native_fnc_tocInbox; ["uiFill"] call comspec_atak_native_fnc_tocInbox; }],
            ["Fermer", { (uiNamespace getVariable ["COMSPEC_ATAK_TocInboxUi", displayNull]) closeDisplay 2; }]
        ];
        _lb ctrlAddEventHandler ["LBSelChanged", { ["uiShow", _this select 1] call comspec_atak_native_fnc_tocInbox; }];
        ["uiFill"] call _fn;
        true
    };
    case "uiFill": {
        disableSerialization;
        private _d = uiNamespace getVariable ["COMSPEC_ATAK_TocInboxUi", displayNull];
        if (isNull _d) exitWith { false };
        private _lb = _d displayCtrl 9101;
        private _keep = _lb lbData (lbCurSel _lb);
        lbClear _lb;
        private _kLbl = createHashMapFromArray [["MSG", "MESSAGE"], ["ANSWER", "RÉPONSE"], ["ORDER", "ORDRE"], ["FEEDBACK", "RETOUR"]];
        private _col = { ["rgba", [_this, 1]] call comspec_atak_native_fnc_tocTheme };
        // Mes ordres d'abord (à accuser), puis mes demandes, puis la boîte.
        {
            _x params ["_id", "_time", "_lbl", "", "", "_payload", "", "_st"];
            private _i = _lb lbAdd format ["%1 · %2 %3 · %4 · %5", _time, ("ORDRE") call comspec_atak_native_fnc_tr, _lbl call comspec_atak_native_fnc_tr, (_stLabel getOrDefault [_st, _st]) call comspec_atak_native_fnc_tr, _payload select [0, 50]];
            _lb lbSetData [_i, "O|" + _id];
            _lb lbSetColor [_i, (["amber", "text2"] select (_st in ["ACK", "EXEC", "DONE", "FAILED"])) call _col];
        } forEach (["orders"] call _fn);
        {
            private _i = _lb lbAdd format ["%1 · %2 %3 · %4", _x param [1, ""], ("DEMANDE") call comspec_atak_native_fnc_tr, (_x param [2, ""]) call comspec_atak_native_fnc_tr, (_x param [7, ""]) call comspec_atak_native_fnc_tr];
            _lb lbSetData [_i, "R|" + (_x param [0, ""])];
            _lb lbSetColor [_i, "blue" call _col];
        } forEach (["requests"] call _fn);
        {
            _x params ["_id", "_kind", "_time", "_from", "", "_text", "_read"];
            if (_kind isEqualTo "ORDER") then { continue };
            private _i = _lb lbAdd format ["%1 · %2%3 · %4", _time, (_kLbl getOrDefault [_kind, _kind]) call comspec_atak_native_fnc_tr, ["", " · " + _from] select (_from isNotEqualTo ""), (_text call comspec_atak_native_fnc_tr) select [0, 60]];
            _lb lbSetData [_i, "B|" + _id];
            _lb lbSetColor [_i, (["text", "muted"] select (_read || {_kind isEqualTo "FEEDBACK"})) call _col];
        } forEach (["list", []] call _fn);
        if ((lbSize _lb) isEqualTo 0) then {
            (_d displayCtrl 9102) ctrlSetStructuredText parseText (("Rien pour l'instant : les messages du poste, vos ordres et les réponses à vos demandes s'inscrivent ici") call comspec_atak_native_fnc_tr);
        } else {
            private _sel = 0;
            for "_i" from 0 to ((lbSize _lb) - 1) do { if ((_lb lbData _i) isEqualTo _keep) then { _sel = _i; }; };
            _lb lbSetCurSel _sel;
        };
        true
    };
    case "uiShow": {
        disableSerialization;
        private _d = uiNamespace getVariable ["COMSPEC_ATAK_TocInboxUi", displayNull];
        if (isNull _d || {!(_a isEqualType 0)} || {_a < 0}) exitWith { false };
        private _ref = ((_d displayCtrl 9101) lbData _a) splitString "|";
        _ref params [["_k", ""], ["_id", ""]];
        private _hex = { ["hex", _this] call comspec_atak_native_fnc_tocTheme };
        private _line = { format ["<t color='%1'>%2</t>  %3<br/>", "muted" call _hex, (_this select 0) call comspec_atak_native_fnc_tr, _this select 1] };
        private _txt = "";
        switch (_k) do {
            case "O": {
                private _o = ((["orders"] call _fn) select { (_x select 0) isEqualTo _id }) param [0, []];
                if (_o isEqualTo []) exitWith {};
                _o params ["", "_time", "_lbl", "_issuer", "_target", "_payload", "_prio", "_st", "_pos"];
                _txt = format ["<t size='1.2' color='%1'>%2</t><br/>", "amber" call _hex, _lbl call comspec_atak_native_fnc_tr]
                    + (["De", _issuer] call _line) + (["Pour", [_target, ("tout le camp") call comspec_atak_native_fnc_tr] select (_target isEqualTo "")] call _line)
                    + (["Heure", _time] call _line) + (["Priorité", _prio call comspec_atak_native_fnc_tr] call _line)
                    + (["Grille", ["", [[_pos param [0, 0], _pos param [1, 0], 0], 6] call comspec_atak_native_fnc_gridRef] select ((count _pos) >= 2)] call _line)
                    + (["Mon statut", (_stLabel getOrDefault [_st, _st]) call comspec_atak_native_fnc_tr] call _line)
                    + format ["<br/>%1", _payload];
            };
            case "R": {
                private _r = ((["requests"] call _fn) select { (_x param [0, ""]) isEqualTo _id }) param [0, []];
                if (_r isEqualTo []) exitWith {};
                _txt = format ["<t size='1.2' color='%1'>%2</t><br/>", "blue" call _hex, (_r param [2, ""]) call comspec_atak_native_fnc_tr]
                    + (["Heure", (_r param [1, ""]) + "Z"] call _line) + (["Grille", _r param [5, ""]] call _line)
                    + (["Statut", (_r param [7, ""]) call comspec_atak_native_fnc_tr] call _line) + (["Réponse du poste", _r param [8, ""]] call _line)
                    + format ["<br/>%1", _r param [6, ""]];
                { _txt = _txt + "<br/>" + ([_x param [0, ""], _x param [1, ""]] call _line); } forEach (_r param [10, []]);
            };
            default {
                private _box = missionNamespace getVariable ["COMSPEC_ATAK_TocInbox", []];
                private _i = _box findIf { (_x select 0) isEqualTo _id };
                if (_i < 0) exitWith {};
                (_box select _i) params ["", "", "_time", "_from", "_prio", "_text"];
                (_box select _i) set [6, true];
                _txt = (["De", _from] call _line) + (["Heure", _time] call _line) + (["Priorité", _prio call comspec_atak_native_fnc_tr] call _line) + format ["<br/>%1", _text call comspec_atak_native_fnc_tr];
            };
        };
        (_d displayCtrl 9102) ctrlSetStructuredText parseText _txt;
        true
    };
    case "uiAck": {
        disableSerialization;
        private _d = uiNamespace getVariable ["COMSPEC_ATAK_TocInboxUi", displayNull];
        if (isNull _d) exitWith { false };
        private _lb = _d displayCtrl 9101;
        private _ref = (_lb lbData (lbCurSel _lb)) splitString "|";
        if ((_ref param [0, ""]) isNotEqualTo "O") exitWith {
            (_d displayCtrl 9102) ctrlSetStructuredText parseText (("Choisissez d'abord un ordre dans la liste") call comspec_atak_native_fnc_tr);
            false
        };
        ["ack", [_ref param [1, ""], _a]] call _fn;
        ["uiFill"] call _fn;
        true
    };
    default { false };
};
