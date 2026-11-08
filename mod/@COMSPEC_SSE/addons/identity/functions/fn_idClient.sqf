/*
    Côté joueur du contrôle d'identité : envoi au serveur et réponses du serveur (remoteExec, allowedTargets = 1).
    ["send", [op, args]]                        demande au serveur (fn_idServer ; appel direct en solo / hôte)
    Réponses : request [contrôleur] · token [porteur, docId, libellé, fin] · answer [cible, réponse, texte]
      docs [cible, [[poignée, classe, libellé]…]] · doc [document imprimé, N0, sujet, mien, clé] · blank [poignée]
      result [docId, niveau, résultat] · seized / returned [cible, classe…] · vehicle [véhicule, op, occupants]
      notify [texte, type] · zeus [texte, genre] · packFetch []
      say [nom, phrase, type, indice]   phrase courte d'une IA contrôlée (IA réalistes), traduite ici
    Lot 4 et 5 : question [demandeur, [[qid, question, réponses]…], délai] · questionEnd [qid, juste, évasif]
      coverSheet [netId, fiche] · coverList [infiltrés] · coverState [on | off | burnt | warn, motif]
      talk [ia, clé, réponse, indice] (réponse d'un PNJ à ma question) · hvtTip [netId, sources, position, texte, par]
      athenaWatch [charge]   envoi d'un BOLO / d'une HVT à Athena par la DLL de ce poste (serveur dédié compris)
    État local (jamais diffusé) : comspec_sse_idTokens (documents présentés), comspec_sse_idSearch (dernière fouille),
    comspec_sse_idSeized (pièces saisies, pour « Rendre »), comspec_sse_idPending (demandes reçues).
*/
params [["_op", "", [""]], ["_a", []]];
if (_op isEqualTo "send") exitWith {
    _a params [["_sop", ""], ["_sargs", []]];
    if (isServer) then { [_sop, _sargs] call comspec_sse_fnc_idServer; } else { [_sop, _sargs] remoteExecCall ["comspec_sse_fnc_idServer", 2]; };
    true
};
// BOLO / HVT vers Athena (POST /api/sse/identity/checks, genre bolo | hvt) : aussi sur un serveur dédié sans interface.
if (_op isEqualTo "athenaWatch") exitWith {
    _a params [["_payload", createHashMap]];
    [_payload] spawn {
        params ["_payload"];
        private _ok = false;
        if (["has"] call comspec_sse_fnc_idAthenaPack) then {
            private _json = [_payload] call comspec_sse_fnc_toJsonApprox;
            private _parts = [];
            for "_i" from 0 to ((count _json) - 1) step 4000 do { _parts pushBack (_json select [_i, 4000]); };
            private _raw = ["SubmitIdCheck", _parts] call comspec_sse_fnc_extensionCall;
            _ok = (_raw select [0, 3]) isEqualTo "OK|";
            if (_ok && {hasInterface}) then { [format ["Athena : %1 enregistré (%2).", toUpper (_payload getOrDefault ["kind", ""]), (_raw splitString "|") param [2, ""]], "info"] call comspec_sse_fnc_zeusNotify; };
        };
        if (_ok) exitWith {};
        [createHashMapFromArray [
            ["kind", "IDWATCH"], ["command", "SubmitIdCheck"], ["payload", _payload],
            ["idempotency_key", _payload get "idempotency_key"], ["record_id", _payload get "idempotency_key"], ["txStatus", "PENDING"]
        ]] call comspec_sse_fnc_queueOffline;
    };
    true
};
if (!hasInterface) exitWith {};
private _say = { params ["_t", ["_type", "INFO"]]; [_t, _type, "", "IDCHECK"] call comspec_sse_fnc_notify; };
private _tokens = missionNamespace getVariable ["comspec_sse_idTokens", createHashMap];
missionNamespace setVariable ["comspec_sse_idTokens", _tokens];

switch (_op) do {
    case "notify": { _a params [["_t", ""], ["_type", "INFO"]]; [_t, _type] call _say; };
    case "zeus": { _a params [["_t", ""], ["_k", "info"]]; [_t, _k] call comspec_sse_fnc_zeusNotify; };
    case "request": { _a params [["_c", objNull]]; ["request", [_c]] call comspec_sse_fnc_idPresentPrompt; };
    case "token": {
        _a params [["_holder", objNull], ["_id", ""], ["_label", ""], ["_end", 0]];
        // Fin de validité en temps serveur ramenée à l'horloge locale (time diffère d'un poste à l'autre).
        _tokens set [_id, [_holder, _label, time + ((missionNamespace getVariable ["comspec_sse_id_tokenTime", 120]) - 1)]];
        ["", "INFO", objNull, format [("Papiers de %1 : %2. Menu ACE › Contrôle d'identité › Contrôler l'identité." call comspec_sse_fnc_tr), [name _holder, "?"] select (isNull _holder), _label]] call comspec_sse_fnc_toast;
    };
    case "say": {
        _a params [["_n", ""], ["_t", ""], ["_type", "INFO"], ["_hint", ""], ["_u", objNull]];
        // Bulle au-dessus de sa tête, sinon toast : ce que dit une IA n'a rien à faire dans le centre de notifications.
        [_t, _type, _u, _hint] call comspec_sse_fnc_toast;
    };
    case "answer": {
        _a params [["_t", objNull], ["_ans", ""], ["_text", ""]];
        // Dernière réponse d'une personne contrôlée : reprise par les comptes rendus SALUTE / SPOTREP (fn_idReport).
        missionNamespace setVariable ["comspec_sse_idLastAnswer", [_t, _ans, _text, time]];
        // Réponse d'une IA : au-dessus de sa tête. Demande faite à un joueur : simple toast.
        [_text, ["INFO", "WARNING"] select (_ans in ["refuse", "flee", "none"]), [_t, objNull] select (isNull _t || {isPlayer _t})] call comspec_sse_fnc_toast;
    };
    case "docs": {
        _a params [["_t", objNull], ["_list", []]];
        missionNamespace setVariable ["comspec_sse_idSearch", [_t, _list, time]];
        {
            _x params ["_h", "", "_label"];
            if ((_h select [0, 2]) isEqualTo "ID") then { _tokens set [_h, [_t, _label, time + 110]]; };
        } forEach _list;
        if (_list isEqualTo []) exitWith { ["Fouille : aucun document trouvé.", "WARNING"] call comspec_sse_fnc_toast; };
        ["", "INFO", objNull, format [("Fouille : %1 document(s). Menu ACE › Contrôle d'identité : examiner ou saisir." call comspec_sse_fnc_tr), count _list]] call comspec_sse_fnc_toast;
    };
    case "blank": { ["Document vierge : aucun titulaire, rien d'imprimé.", "WARNING"] call _say; };
    case "doc": {
        _a params [["_pub", createHashMap], ["_res", createHashMap], ["_subj", objNull], ["_own", false], ["_key", ""]];
        if (!_own) then { ["start", [_pub, _subj, _key, _res]] call comspec_sse_fnc_idHistory; };
        [_pub, _own] call comspec_sse_fnc_idViewerOpen;
    };
    case "result": {
        _a params [["_id", ""], ["_lvl", 1], ["_res", createHashMap]];
        // Base en ligne : le joueur interroge Athena avec sa propre liaison (DLL), puis complète le résultat local.
        if (_lvl isEqualTo 3 && {["has"] call comspec_sse_fnc_idAthenaPack}) exitWith {
            [_id, _res] spawn {
                params ["_id", "_res"];
                private _raw = ["QueryIdDocument", _res getOrDefault ["query", []]] call comspec_sse_fnc_extensionCall;
                ["merge", [_res, _raw]] call comspec_sse_fnc_idAthenaPack;
                ["level", [_id, 3, _res]] call comspec_sse_fnc_idHistory;
            };
        };
        ["level", [_id, _lvl, _res]] call comspec_sse_fnc_idHistory;
    };
    case "seized": {
        _a params [["_t", objNull], ["_cls", ""]];
        private _s = missionNamespace getVariable ["comspec_sse_idSeized", createHashMap];
        _s set [_cls, netId _t];
        missionNamespace setVariable ["comspec_sse_idSeized", _s];
        [format ["Document saisi sur %1 : il est dans votre inventaire (chaîne de possession ouverte).", [name _t, "?"] select (isNull _t)], "SUCCESS"] call _say;
    };
    case "returned": {
        _a params [["_t", objNull], ["_cls", ""]];
        (missionNamespace getVariable ["comspec_sse_idSeized", createHashMap]) deleteAt _cls;
        [format ["Document rendu à %1.", [name _t, "?"] select (isNull _t)]] call _say;
    };
    case "vehicle": {
        _a params [["_v", objNull], ["_vop", "vehicle"], ["_out", []]];
        if (_out isEqualTo []) exitWith { ["Aucun occupant à contrôler.", "WARNING"] call comspec_sse_fnc_toast; };
        { _x params ["_net", "_n", "_ans", "_text"]; [_text, ["INFO", "WARNING"] select (_ans in ["refuse", "flee", "none"]), objectFromNetId _net] call comspec_sse_fnc_toast; } forEach _out;
    };
    case "question": {
        _a params [["_who", ""], ["_list", []], ["_delay", 20]];
        if (_list isEqualTo []) exitWith {};
        missionNamespace setVariable ["comspec_sse_idQuestion", [_who, _list, time + _delay, createHashMap]];
        [format ["%1 vous pose des questions (%2 s). ATAK › Ma couverture, ou menu ACE personnel › Mes papiers › Répondre.", _who, round _delay], "WARNING", "", "IDCOVER"] call comspec_sse_fnc_notify;
        if (!isNil "comspec_atak_native_fnc_vibrate") then { [] call comspec_atak_native_fnc_vibrate; };
        [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "IDCOVER") then { ["IDCOVER"] call comspec_atak_native_fnc_pageRender; }; }] call CBA_fnc_execNextFrame;
    };
    case "questionEnd": {
        _a params [["_qid", ""]];
        private _q = missionNamespace getVariable ["comspec_sse_idQuestion", []];
        if (_q isEqualTo []) exitWith {};
        if !(_qid in (_q select 3)) then { (_q select 3) set [_qid, -1]; };
        // Plus aucune question ouverte : la fenêtre se ferme.
        if (((_q select 1) findIf { !((_x select 0) in (_q select 3)) }) < 0) then { missionNamespace setVariable ["comspec_sse_idQuestion", []]; };
        [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "IDCOVER") then { ["IDCOVER"] call comspec_atak_native_fnc_pageRender; }; }] call CBA_fnc_execNextFrame;
    };
    case "coverSheet": {
        _a params [["_net", ""], ["_sheet", createHashMap]];
        (missionNamespace getVariable ["comspec_sse_idCoverSheets", createHashMap]) set [_net, _sheet];
        missionNamespace setVariable ["comspec_sse_idCoverSheets", missionNamespace getVariable ["comspec_sse_idCoverSheets", createHashMap]];
        [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "IDCOVER") then { ["IDCOVER"] call comspec_atak_native_fnc_pageRender; }; }] call CBA_fnc_execNextFrame;
    };
    case "coverList": {
        _a params [["_l", []]];
        missionNamespace setVariable ["comspec_sse_idCoverList", _l];
        [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "IDCOVER") then { ["IDCOVER"] call comspec_atak_native_fnc_pageRender; }; }] call CBA_fnc_execNextFrame;
    };
    case "coverState": {
        _a params [["_state", ""], ["_why", ""]];
        // Tir de l'infiltré : relevé sur son poste seulement, le temps de l'infiltration (aucun coût sinon).
        private _eh = player getVariable ["comspec_sse_idFiredEh", -1];
        if (_state isEqualTo "on" && {_eh < 0}) then {
            player setVariable ["comspec_sse_idFiredEh", player addEventHandler ["FiredMan", {
                if ((time - (missionNamespace getVariable ["comspec_sse_idFiredAt", -10])) < 1) exitWith {};
                missionNamespace setVariable ["comspec_sse_idFiredAt", time];
                ["send", ["coverFired", [player]]] call comspec_sse_fnc_idClient;
            }]];
        };
        if (_state in ["off", "burnt"] && {_eh >= 0}) then { player removeEventHandler ["FiredMan", _eh]; player setVariable ["comspec_sse_idFiredEh", -1]; };
        (missionNamespace getVariable ["comspec_sse_idCoverSheets", createHashMap]) deleteAt (netId player);
        private _msg = switch (_state) do {
            case "on": { "Vous êtes infiltré : votre couverture tient tant que rien ne vous trahit (ATAK › Ma couverture)." };
            case "off": { "Infiltration levée." };
            case "burnt": { format ["Votre couverture est brûlée (%1) : les ennemis proches vous ont repéré.", _why call comspec_sse_fnc_tr] };
            default { format ["Vous attirez l'attention (%1).", _why call comspec_sse_fnc_tr] };
        };
        // Alerte discrète : vibreur seul avec l'ATAK 3.0.2, sinon vibreur et notification.
        if (_state in ["burnt", "warn"] && {[3, 0, 2] call comspec_sse_fnc_idAtakAtLeast}) exitWith {
            ["MESSAGE", _msg call comspec_sse_fnc_tr, 0, 10, "IDCOVER", "vibrate"] call comspec_atak_native_fnc_notify;
        };
        if (_state in ["burnt", "warn"] && {!isNil "comspec_atak_native_fnc_vibrate"}) then { [] call comspec_atak_native_fnc_vibrate; };
        [_msg, ["INFO", "WARNING"] select (_state in ["burnt", "warn"]), "", "IDCOVER"] call _say;
    };
    case "talk": {
        _a params [["_t", objNull], ["_k", ""], ["_txt", ""], ["_hint", ""]];
        [_txt, "INFO", _t, _hint] call comspec_sse_fnc_toast;
        // Réponse gardée sur la fiche du contrôle en cours (même personne) : reprise par la FRM.
        private _cur = ["current"] call comspec_sse_fnc_idHistory;
        if ((count _cur) > 0 && {(_cur getOrDefault ["subj", objNull]) isEqualTo _t}) then {
            private _talk = _cur getOrDefault ["talk", []];
            _talk pushBack [_k, _txt, _hint];
            _cur set ["talk", _talk];
        };
    };
    case "hvtTip": {
        _a params [["_net", ""], ["_srcs", []], ["_pos", []], ["_text", ""], ["_from", ""]];
        if (isNil "comspec_atak_native_fnc_hvtIntel" || {isNil "comspec_atak_native_fnc_datingSpecials"}) exitWith {};
        private _u = objectFromNetId _net;
        if (isNull _u) exitWith {};
        // Chasse aux HVT de l'ATAK : un tuyau confirmé par modalité (contrôle, visage, PID) ; deux sources = FIX.
        private _key = ["key", _u] call comspec_atak_native_fnc_datingSpecials;
        {
            private _id = ["tip", [_key, _x, 1, 30, _from, _pos, _text]] call comspec_atak_native_fnc_hvtIntel;
            if (_id isEqualType "" && {_id isNotEqualTo ""}) then { ["review", [_id, "OK"]] call comspec_atak_native_fnc_hvtIntel; };
        } forEach _srcs;
        [format ["HVT : %1 (chasse HVT de l'ATAK : FIX).", _text], "SUCCESS", "", "DATING"] call comspec_sse_fnc_notify;
    };
    case "packFetch": {
        [] spawn {
            private _raw = ["fetch"] call comspec_sse_fnc_idAthenaPack;
            if (_raw isNotEqualTo "") then { ["send", ["pack", [player, _raw]]] call comspec_sse_fnc_idClient; };
        };
    };
};
