/*
    Demandes de contrôle d'identité (SERVEUR). Appel : ["send", [op, args]] call comspec_sse_fnc_idClient.
    Le demandeur est retrouvé par remoteExecutedOwner (jamais cru sur parole) ; contrôles : distance (3 m, NFC 1,5 m,
    plus une marge de latence), jeton de présentation (réglage, 120 s), anti-flood (0,5 s par opération), durée réelle
    de l'action (« begin » envoyé au début de la barre de progression).
    Poignée de document (_h) : docId (« ID0007 », document présenté ou trouvé) ou classe d'un exemplaire porté.
      begin    [moi, op, cible]          début d'une action à durée
      ask      [moi, cible]              « Papiers, s'il vous plaît » (IA : fn_idAiAnswer ; joueur : demande 60 s)
      present  [moi, classe, contrôleur | objNull, "present" | "give"]   je tends (ou remets) un de mes documents
      refuse   [moi]                     je refuse de présenter mes papiers
      docs     [moi, cible]              fouille des papiers (mort, menotté, rendu, inconscient)
      view     [moi, _h]                 lecture (N0 si ce n'est pas mon document)
      check    [moi, _h, niveau 1..3]    MRZ, puce, base
      seize    [moi, cible, _h]          saisie (dans mon inventaire, chaîne de possession)
      return   [moi, cible, classe]      je rends un document saisi
      vehicle  [moi, véhicule]           contrôle des occupants        dismount [moi, véhicule]  faire descendre
      give / report / truth / pack       Zeus (curateur) : donner des papiers, signaler, vraie identité, paquet Athena
    Points d'entrée du Lot 3 (checkpoints IA), appel local au serveur seulement :
      ["aiAsk", [ia, joueur]] call comspec_sse_fnc_idServer   l'IA demande ses papiers à un joueur
    Évènements CBA serveur : comspec_sse_idAsked [cible, contrôleur], comspec_sse_idPresented [porteur, docId,
    contrôleur], comspec_sse_idRefused [porteur, contrôleur], comspec_sse_idChecked [contrôleur, docId, niveau, résultat].
*/
params [["_op", "", [""]], ["_args", [], [[]]]];
if (!isServer) exitWith {};
if !(missionNamespace getVariable ["comspec_sse_id_enabled", true]) exitWith {};
private _reg = ["init"] call comspec_sse_fnc_idRegistry;
private _owner = if (isRemoteExecuted) then { remoteExecutedOwner } else { clientOwner };
_args params [["_me", objNull, [objNull]]];
if (isRemoteExecuted && {_op isEqualTo "aiAsk"}) exitWith {};
if (isNull _me) exitWith {};
// Le poste qui demande doit être celui de l'unité qu'il dit être (sinon n'importe qui contrôlerait au nom d'un autre).
if (isMultiplayer && {isRemoteExecuted} && {(owner _me) isNotEqualTo _owner}) exitWith {
    diag_log text format ["[COMSPEC SSE] Identité : « %1 » refusé au poste %2 (unité d'un autre poste).", _op, _owner];
};
private _floodKey = format ["%1|%2", _owner, _op];
if (_op isNotEqualTo "begin" && {(time - ((_reg get "flood") getOrDefault [_floodKey, -10])) < 0.5}) exitWith {};
(_reg get "flood") set [_floodKey, time];

private _reply = {
    params ["_rop", "_ra", ["_to", _owner]];
    if (_to isEqualTo clientOwner || {!isMultiplayer}) then { [_rop, _ra] call comspec_sse_fnc_idClient; } else { [_rop, _ra] remoteExecCall ["comspec_sse_fnc_idClient", _to]; };
};
private _tell = {
    params ["_unit", "_text", ["_type", "INFO"]];
    if (isNull _unit || {!isPlayer _unit}) exitWith {};
    // Joueur de ce poste (hôte, solo) : appel direct.
    ["notify", [_text, _type], [owner _unit, clientOwner] select (local _unit && {hasInterface})] call _reply;
};
// Durée minimale réellement passée (barre de progression), 60 % de la durée affichée pour la latence.
private _dur = createHashMapFromArray [["ask", 2], ["view", 2], ["docs", 4], ["docsAlive", 6], ["seize", 2], ["return", 1], ["vehicle", 2], ["dismount", 2], ["check1", 3], ["check2", 4], ["check3", 3], ["present", 1]];
private _took = {
    params ["_name", "_target"];
    private _k = format ["%1|%2|%3", _owner, _name, (if (_target isEqualType "") then { _target } else { netId _target })];
    private _t0 = (_reg get "begin") getOrDefault [_k, -1];
    (_reg get "begin") deleteAt _k;
    if (_t0 < 0) exitWith { false };
    (time - _t0) >= ((_dur getOrDefault [_name, 1]) * 0.6)
};
private _near = { params ["_a", "_b", "_m"]; !isNull _a && {!isNull _b} && {(_a distance _b) <= _m} };
private _tokenTime = missionNamespace getVariable ["comspec_sse_id_tokenTime", 120];
private _grant = {
    params ["_docId", "_holder", ["_to", _owner]];
    (_reg get "tokens") set [format ["%1|%2", _to, _docId], [time + _tokenTime, _holder]];
};
private _label = {
    params ["_d"];
    if ((count _d) isEqualTo 0) exitWith { "Document vierge" };
    format ["%1 %2 · %3 %4", ["Carte d'identité", "Passeport", "Permis"] select ((["id_card", "passport", "driving_license"] find (_d get "type")) max 0),
        _d get "country", toUpper (_d get "last"), _d get "first"]
};
// Document imprimé (ce que voit celui qui le tient) : jamais la vérité.
private _pub = {
    params ["_d"];
    private _c = ([] call comspec_sse_fnc_idCountries) getOrDefault [_d get "country", createHashMap];
    private _p = createHashMap;
    { _p set [_x, _d getOrDefault [_x, ""]]; } forEach ["id", "type", "country", "number", "last", "first", "sex", "dob", "pob", "nat", "profession", "issued", "expires", "authority", "address"];
    _p set ["countryName", _c getOrDefault ["name", _d get "country"]];
    _p set ["chipLogo", _d getOrDefault ["chipExpected", false]];
    _p set ["mrz", [_d] call comspec_sse_fnc_idMrz];
    // Défauts physiques : visibles par qui regarde bien (le constat N0, lui, dépend du tirage).
    private _f = _d getOrDefault ["flaws", []];
    _p set ["holo", !("holo" in _f)];
    _p set ["font", "font" in _f];
    _p set ["photo", "photo" in _f];
    _p
};
// Poignée -> [document, porteur (unité / conteneur / objNull), autorisé, sujet (personne contrôlée ou objNull)].
private _resolve = {
    params ["_h"];
    private _d = createHashMap;
    private _holder = objNull;
    private _subj = objNull;
    private _ok = false;
    private _isCls = (_h select [0, 11]) isEqualTo "COMSPEC_ID_";
    _d = if (_isCls) then { ["ofClass", [_h]] call comspec_sse_fnc_idRegistry } else { ["doc", [_h]] call comspec_sse_fnc_idRegistry };
    private _t = (_reg get "tokens") getOrDefault [format ["%1|%2", _owner, _d getOrDefault ["id", _h]], []];
    if (_t isNotEqualTo [] && {(_t select 0) > time}) then { _ok = true; _holder = _t select 1; if (_holder isKindOf "CAManBase" && {_holder isNotEqualTo _me}) then { _subj = _holder; }; };
    // Document dans mes mains (le mien, remis, saisi) ou à mes pieds : toujours lisible, porteur = moi.
    private _cls = [_d getOrDefault ["cls", ""], _h] select _isCls;
    if (_cls isNotEqualTo "") then {
        private _mine = ([_me, 2] call comspec_sse_fnc_idDocsOf) select { (_x select 0) isEqualTo _cls };
        if (_mine isNotEqualTo []) then { _ok = true; _holder = (_mine select 0) select 1; };
    };
    [_d, _holder, _ok, _subj]
};

switch (_op) do {
    case "begin": {
        _args params ["", ["_name", ""], ["_target", objNull, [objNull, ""]]];
        if !(_name in _dur) exitWith {};
        (_reg get "begin") set [format ["%1|%2|%3", _owner, _name, (if (_target isEqualType "") then { _target } else { netId _target })], time];
    };

    case "ask": {
        _args params ["", ["_t", objNull, [objNull]]];
        if (isNull _t || {_t isEqualTo _me} || {!alive _t} || {!([_me, _t, 4] call _near)}) exitWith {};
        if !(["ask", _t] call _took) exitWith {};
        ["comspec_sse_idAsked", [_t, _me]] call CBA_fnc_localEvent;
        if (isPlayer _t) exitWith {
            private _req = _reg get "requests";
            private _list = (_req getOrDefault [netId _t, []]) select { (_x select 2) > time && {(_x select 1) isNotEqualTo (netId _me)} };
            _list pushBack [_owner, netId _me, time + 60];
            _req set [netId _t, _list];
            ["request", [_me], owner _t] call _reply;
            ["answer", [_t, "wait", format ["Demande faite à %1 : il doit présenter ses papiers (Mes papiers).", name _t]]] call _reply;
        };
        private _ans = [_t, _me] call comspec_sse_fnc_idAiAnswer;
        private _docId = _ans getOrDefault ["docId", ""];
        if ((_ans get "answer") isEqualTo "present" && {_docId isNotEqualTo ""}) then {
            [_docId, _t] call _grant;
            private _d = ["doc", [_docId]] call comspec_sse_fnc_idRegistry;
            ["token", [_t, _docId, [_d] call _label, time + _tokenTime]] call _reply;
            ["comspec_sse_idPresented", [_t, _docId, _me]] call CBA_fnc_localEvent;
        };
        if ((_ans get "answer") in ["refuse", "flee"]) then { ["comspec_sse_idRefused", [_t, _me]] call CBA_fnc_localEvent; };
        ["answer", [_t, _ans get "answer", _ans get "text"]] call _reply;
    };

    case "aiAsk": {
        _args params ["", ["_t", objNull, [objNull]]];
        if (isNull _t || {!isPlayer _t}) exitWith {};
        private _req = _reg get "requests";
        private _list = (_req getOrDefault [netId _t, []]) select { (_x select 2) > time };
        _list pushBack [owner _me, netId _me, time + 60];
        _req set [netId _t, _list];
        ["request", [_me], owner _t] call _reply;
        ["comspec_sse_idAsked", [_t, _me]] call CBA_fnc_localEvent;
    };

    case "present": {
        _args params ["", ["_cls", ""], ["_to", objNull, [objNull]], ["_mode", "present"]];
        if !(["present", _me] call _took) exitWith {};
        private _mine = ([_me, 0] call comspec_sse_fnc_idDocsOf) select { (_x select 0) isEqualTo _cls };
        if (_mine isEqualTo []) exitWith { [_me, "Ce document n'est plus dans votre inventaire.", "WARNING"] call _tell; };
        // Destinataire : demande en attente la plus récente, sinon la personne visée.
        private _pend = ((_reg get "requests") getOrDefault [netId _me, []]) select { (_x select 2) > time };
        if (isNull _to && {_pend isNotEqualTo []}) then { _to = objectFromNetId ((_pend select -1) select 1); };
        if (isNull _to || {!([_me, _to, 4] call _near)}) exitWith { [_me, "Personne à qui présenter vos papiers à moins de 3 m.", "WARNING"] call _tell; };
        private _d = ["ofClass", [_cls]] call comspec_sse_fnc_idRegistry;
        (_reg get "requests") set [netId _me, _pend select { (_x select 1) isNotEqualTo (netId _to) }];
        if ((count _d) isEqualTo 0) exitWith {
            [_to, format ["%1 vous présente un document vierge (aucun titulaire).", name _me], "WARNING"] call _tell;
        };
        private _toOwner = owner _to;
        [_d get "id", _me, _toOwner] call _grant;
        if (_mode isEqualTo "give") then { [_cls, _me, _to] call comspec_sse_fnc_idTransfer; };
        if (isPlayer _to) then {
            ["token", [_me, _d get "id", [_d] call _label, time + _tokenTime], _toOwner] call _reply;
            [_to, format [["%1 vous présente : %2.", "%1 vous remet : %2."] select (_mode isEqualTo "give"), name _me, [_d] call _label]] call _tell;
        };
        ["comspec_sse_idPresented", [_me, _d get "id", _to]] call CBA_fnc_localEvent;
    };

    case "refuse": {
        private _pend = ((_reg get "requests") getOrDefault [netId _me, []]) select { (_x select 2) > time };
        (_reg get "requests") deleteAt (netId _me);
        {
            private _c = objectFromNetId (_x select 1);
            [_c, format ["%1 refuse de présenter ses papiers.", name _me], "WARNING"] call _tell;
            ["comspec_sse_idRefused", [_me, _c]] call CBA_fnc_localEvent;
        } forEach _pend;
    };

    case "docs": {
        _args params ["", ["_t", objNull, [objNull]]];
        if (isNull _t || {!([_t] call comspec_sse_fnc_idCanSearch)} || {!([_me, _t, 4] call _near)}) exitWith {};
        if !([["docsAlive", "docs"] select !(alive _t), _t] call _took) exitWith {};
        // Papiers encore virtuels : posés maintenant sur la personne (ou au sol si son inventaire est plein).
        private _fresh = [];
        {
            private _d = ["doc", [_x]] call comspec_sse_fnc_idRegistry;
            if ((_d getOrDefault ["cls", ""]) isEqualTo "") then { _fresh pushBack _x; };
            [_x, _t] call comspec_sse_fnc_idMaterialize;
        } forEach (["unitDocs", [_t]] call comspec_sse_fnc_idRegistry);
        // L'objet ajouté arrive par le réseau (machine de l'unité) : lecture une demi-seconde plus tard.
        [{
            params ["_me", "_t", "_owner", "_reply", "_grant", "_label", "_tell", "_fresh"];
            private _reg = comspec_sse_idReg;
            private _tokenTime = missionNamespace getVariable ["comspec_sse_id_tokenTime", 120];
            private _list = [];
            private _known = [];
            {
                _x params ["_cls", "_where"];
                private _d = ["ofClass", [_cls]] call comspec_sse_fnc_idRegistry;
                if ((count _d) > 0) then {
                    _known pushBack (_d get "id");
                    (_reg get "tokens") set [format ["%1|%2", _owner, _d get "id"], [time + _tokenTime, _where]];
                    _list pushBack [_d get "id", _cls, [_d] call _label];
                } else {
                    _list pushBack [_cls, _cls, "Document vierge"];
                };
            } forEach ([_t, 3] call comspec_sse_fnc_idDocsOf);
            // Posés à l'instant mais pas encore arrivés par le réseau, ou stock d'exemplaires épuisé (restés virtuels) :
            // trouvés quand même (ils sont « sur » lui). Un document déjà parti ailleurs (saisi, remis) n'est pas compté.
            {
                if (!(_x in _known) && {(_x in _fresh) || {((["doc", [_x]] call comspec_sse_fnc_idRegistry) getOrDefault ["cls", ""]) isEqualTo ""}}) then {
                    private _d = ["doc", [_x]] call comspec_sse_fnc_idRegistry;
                    (_reg get "tokens") set [format ["%1|%2", _owner, _x], [time + _tokenTime, _t]];
                    _list pushBack [_x, "", [_d] call _label];
                };
            } forEach (["unitDocs", [_t]] call comspec_sse_fnc_idRegistry);
            ["docs", [_t, _list]] call _reply;
            if (isPlayer _t) then { [_t, format ["%1 fouille vos papiers.", name _me], "WARNING"] call _tell; };
        }, [_me, _t, _owner, _reply, _grant, _label, _tell, _fresh], 0.6] call CBA_fnc_waitAndExecute;
    };

    case "view": {
        _args params ["", ["_h", ""]];
        ([_h] call _resolve) params ["_d", "_holder", "_ok", "_subj"];
        if (!_ok) exitWith { [_me, "Ce document ne vous a pas été présenté (ou plus).", "WARNING"] call _tell; };
        if ((count _d) isEqualTo 0) exitWith { ["blank", [_h]] call _reply; };
        // Mon propre document : simple lecture. Sinon examen visuel (N0) au bout de 2 s.
        private _own = (_holder isEqualTo _me) && {(_d get "holderKey") isEqualTo ([_me] call comspec_sse_fnc_bioPersonKey)};
        private _res = createHashMap;
        if (!_own) then {
            if !(["view", _h] call _took) exitWith { _ok = false; };
            _res = [_d, _me, 0] call comspec_sse_fnc_idEvaluate;
            ["comspec_sse_idChecked", [_me, _d get "id", 0, _res]] call CBA_fnc_localEvent;
        };
        if (!_ok) exitWith {};
        ["doc", [[_d] call _pub, _res, _subj, _own, [_subj] call comspec_sse_fnc_bioPersonKey]] call _reply;
    };

    case "check": {
        _args params ["", ["_h", ""], ["_lvl", 1, [0]]];
        _lvl = (round _lvl max 1) min 3;
        ([_h] call _resolve) params ["_d", "_holder", "_ok"];
        if (!_ok || {(count _d) isEqualTo 0}) exitWith { [_me, "Document introuvable ou plus présenté.", "WARNING"] call _tell; };
        if !([format ["check%1", _lvl], _h] call _took) exitWith {};
        // Lecteur NFC : 1,5 m du document (sauf s'il est dans mes mains). MRZ et base : 3 m.
        if (!(_holder isEqualTo _me) && {!isNull _holder} && {!([_me, _holder, [4, 2] select (_lvl isEqualTo 2)] call _near)}) exitWith {
            [_me, ["Approchez-vous du document.", "Lecteur NFC : approchez-vous à moins de 1,5 m du document."] select (_lvl isEqualTo 2), "WARNING"] call _tell;
        };
        private _res = [_d, _me, _lvl] call comspec_sse_fnc_idEvaluate;
        if (_lvl isEqualTo 3) then {
            // Requête Athena faite par le joueur (DLL du poste) : champs imprimés seulement.
            _res set ["query", [_d get "country", _d get "type", _d get "number", _d get "last", _d get "first", _d get "dob"]];
            _res set ["athenaId", _d getOrDefault ["athenaId", ""]];
        };
        ["comspec_sse_idChecked", [_me, _d get "id", _lvl, _res]] call CBA_fnc_localEvent;
        ["result", [_d get "id", _lvl, _res]] call _reply;
    };

    case "seize": {
        _args params ["", ["_t", objNull, [objNull]], ["_h", ""]];
        if (isNull _t || {!([_me, _t, 4] call _near)}) exitWith {};
        if !(["seize", _t] call _took) exitWith {};
        private _d = ["doc", [_h]] call comspec_sse_fnc_idRegistry;
        if ((_h select [0, 11]) isEqualTo "COMSPEC_ID_") then { _d = ["ofClass", [_h]] call comspec_sse_fnc_idRegistry; };
        private _tok = (_reg get "tokens") getOrDefault [format ["%1|%2", _owner, _d getOrDefault ["id", "-"]], []];
        private _allowed = ([_t] call comspec_sse_fnc_idCanSearch) || {_tok isNotEqualTo [] && {(_tok select 0) > time}};
        if (!_allowed) exitWith { [_me, "Il faut qu'il vous ait présenté ce document, ou qu'il soit entravé.", "WARNING"] call _tell; };
        private _cls = if ((_h select [0, 11]) isEqualTo "COMSPEC_ID_") then { _h } else { [_d get "id", _t] call comspec_sse_fnc_idMaterialize };
        if (_cls isEqualTo "") exitWith { [_me, "Saisie impossible : plus d'exemplaire disponible.", "WARNING"] call _tell; };
        [{
            params ["_cls", "_t", "_me", "_d", "_tell", "_reply", "_owner"];
            private _from = (([_t, 3] call comspec_sse_fnc_idDocsOf) select { (_x select 0) isEqualTo _cls }) param [0, []];
            if (_from isEqualTo []) exitWith { [_me, "Le document n'est plus là.", "WARNING"] call _tell; };
            [_cls, _from select 1, _me] call comspec_sse_fnc_idTransfer;
            if (!isNil "comspec_sse_fnc_custody") then {
                private _remark = format ["Saisie du document %1 n° %2", _d getOrDefault ["type", "?"], _d getOrDefault ["number", "?"]];
                if (((["piece", [_t]] call comspec_sse_fnc_custody)) isEqualTo "") then { ["open", [_t, _me, _remark]] call comspec_sse_fnc_custody; } else { ["event", [_t, "SAISIE", _me, _remark]] call comspec_sse_fnc_custody; };
            };
            ["seized", [_t, _cls, _d getOrDefault ["id", ""]]] call _reply;
            if (isPlayer _t) then { [_t, format ["%1 a saisi un de vos documents.", name _me], "WARNING"] call _tell; };
        }, [_cls, _t, _me, _d, _tell, _reply, _owner], 0.6] call CBA_fnc_waitAndExecute;
    };

    case "return": {
        _args params ["", ["_t", objNull, [objNull]], ["_cls", ""]];
        if (isNull _t || {!alive _t} || {!([_me, _t, 4] call _near)}) exitWith {};
        if !(["return", _t] call _took) exitWith {};
        if !(_cls in (([_me, 0] call comspec_sse_fnc_idDocsOf) apply { _x select 0 })) exitWith {};
        [_cls, _me, _t] call comspec_sse_fnc_idTransfer;
        ["returned", [_t, _cls]] call _reply;
        if (isPlayer _t) then { [_t, format ["%1 vous rend un document.", name _me]] call _tell; };
    };

    case "vehicle";
    case "dismount": {
        _args params ["", ["_v", objNull, [objNull]]];
        if (isNull _v || {!([_me, _v, 4 + ((boundingBoxReal _v) select 2) * 0.5] call _near)} || {(speed _v) > 2}) exitWith {
            [_me, "Véhicule trop loin ou en mouvement.", "WARNING"] call _tell;
        };
        if !([_op, _v] call _took) exitWith {};
        private _out = [];
        {
            if (alive _x && {_x isNotEqualTo _me}) then {
                if (_op isEqualTo "dismount") then {
                    if (isPlayer _x) then {
                        [_x, format ["%1 vous demande de descendre du véhicule.", name _me], "WARNING"] call _tell;
                    } else {
                        ["comspec_sse_idMoveOut", [_x], _x] call CBA_fnc_targetEvent;
                    };
                    _out pushBack [netId _x, name _x, "dismount", ["Il descend du véhicule.", "Demande envoyée."] select (isPlayer _x)];
                } else {
                    if (isPlayer _x) then {
                        private _req = _reg get "requests";
                        private _list = (_req getOrDefault [netId _x, []]) select { (_x select 2) > time };
                        _list pushBack [_owner, netId _me, time + 60];
                        _req set [netId _x, _list];
                        ["request", [_me], owner _x] call _reply;
                        _out pushBack [netId _x, name _x, "wait", "Demande faite : il doit présenter ses papiers."];
                    } else {
                        // Occupants contrôlés depuis la portière : la distance au véhicule suffit.
                        private _ans = [_x, _me] call comspec_sse_fnc_idAiAnswer;
                        private _docId = _ans getOrDefault ["docId", ""];
                        if ((_ans get "answer") isEqualTo "present" && {_docId isNotEqualTo ""}) then {
                            [_docId, _x] call _grant;
                            ["token", [_x, _docId, [["doc", [_docId]] call comspec_sse_fnc_idRegistry] call _label, time + _tokenTime]] call _reply;
                        };
                        _out pushBack [netId _x, name _x, _ans get "answer", _ans get "text"];
                    };
                };
            };
        } forEach (crew _v);
        ["vehicle", [_v, _op, _out]] call _reply;
    };

    case "give";
    case "report";
    case "truth": {
        if !([_me] call comspec_sse_fnc_isAuthority) exitWith {
            diag_log text format ["[COMSPEC SSE] Identité : « %1 » réservé au Zeus, refusé au poste %2.", _op, _owner];
        };
        _args params ["", ["_u", objNull, [objNull]], ["_x1", createHashMap]];
        if (isNull _u) exitWith {};
        switch (_op) do {
            case "give": {
                private _id = [_u, _x1] call comspec_sse_fnc_idModuleGive;
                ["zeus", [[format ["Papiers donnés à %1 : %2.", name _u, [["doc", [_id]] call comspec_sse_fnc_idRegistry] call _label], "Échec : papiers non délivrés."] select (_id isEqualTo ""), ["info", "warn"] select (_id isEqualTo "")]] call _reply;
            };
            case "report": {
                private _mode = [_x1, ""] select !(_x1 isEqualType "");
                private _n = 0;
                {
                    private _d = ["doc", [_x]] call comspec_sse_fnc_idRegistry;
                    _d set ["report", _mode];
                    private _k = format ["%1|%2|%3", _d get "country", _d get "type", _d get "number"];
                    private _b = (_reg get "issued") getOrDefault [_k, []];
                    if (_b isNotEqualTo []) then { _b set [3, _mode]; };
                    _n = _n + 1;
                } forEach (["unitDocs", [_u]] call comspec_sse_fnc_idRegistry);
                ["zeus", [format ["%1 document(s) de %2 : %3.", _n, name _u, ["signalement levé", format ["signalé « %1 »", _mode]] select (_mode isNotEqualTo "")], "info"]] call _reply;
            };
            default {
                private _lines = [format ["Vrais papiers de %1 :", name _u]];
                {
                    private _d = ["doc", [_x]] call comspec_sse_fnc_idRegistry;
                    _lines pushBack format ["%1 n° %2 · imprimé %3 %4 · vrai %5 %6 · %7%8 · qualité %9", _d get "type", _d get "number",
                        toUpper (_d get "last"), _d get "first", toUpper (_d get "trueLast"), _d get "trueFirst", _d get "kind",
                        ["", format [" (%1)", _d get "forgeryKind"]] select ((_d get "forgeryKind") isNotEqualTo ""), _d get "quality"];
                    _lines pushBack format ["   défauts : %1 · puce : %2 · signalement : %3 · %4", [(_d get "flaws") joinString ", ", "aucun"] select ((_d get "flaws") isEqualTo []),
                        _d get "chip", [_d get "report", "aucun"] select ((_d get "report") isEqualTo ""), ["virtuel", "objet " + (_d get "cls")] select ((_d get "cls") isNotEqualTo "")];
                } forEach (["unitDocs", [_u]] call comspec_sse_fnc_idRegistry);
                if ((count _lines) isEqualTo 1) then { _lines pushBack "aucun document."; };
                ["zeus", [_lines joinString endl, "info"]] call _reply;
            };
        };
    };

    case "pack": {
        if !([_me] call comspec_sse_fnc_isAuthority) exitWith {};
        _args params ["", ["_raw", ""]];
        ["apply", _raw] call comspec_sse_fnc_idAthenaPack;
    };
};
