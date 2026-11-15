/*
    App « Avis et vigilance » : envois au portail (ICD-SSE-ALERTES-001) : émettre (create), signaler (report), renouveler (renew),
    lever (lift), accuser lecture (ack). Chaque envoi est une fiche gardée dans « Mes envois » avec son parcours, lu par
    fn_reportWebState comme pour les apps BDA et Comptes rendus : « web » = "EN ATTENTE" puis "OK", "ÉCHEC" (« webWhy » : raison
    courte) ou "HORS LIGNE" (en file : repart seule au retour du réseau ou à la reprise de la liaison).
    Identifiant client « cid » : tiré UNE fois à la création de la fiche (poste du joueur, horloge, compteur) et renvoyé tel quel à
    chaque nouvel essai ; le site reconnaît le renvoi et rend la même réponse, sans doublon (ICD § 2).
    Aucun toast, aucun hint, aucun systemChat : le résultat se lit dans l'écran d'état de l'envoi et dans la ligne d'état.

    Fiche (HashMap) : id (= cid), cid, op, body (HashMap envoyée), ref (alerte visée, "" pour une émission), label, subj (sujet, pour
    reconnaître la réponse du bureau), at (heure), web, webWhy, queued, res (page de réponse), resRef, resState, answered.
    File : missionNamespace COMSPEC_ATAK_AlertesOut (30 au plus) ; ce qui n'est pas arrivé et les propositions en attente sont gardés
    sur le poste (profileNamespace COMSPEC_ATAK_AlertesOutbox, 24 h).

    Params : [action, argument]
      ["list"] / ["get", id]
      ["new", [op, corps (HashMap), réf visée, libellé, sujet]]   crée la fiche, tire son cid et l'envoie ; rend son id
      ["send", id]             envoie (ou met en file hors réseau)    ["retry", id]   RENVOYER, même cid
      ["flush"]                renvoie ce qui attend en file (2 par passage)
      ["del", id]              retire une fiche de « Mes envois »
      ["state", id]            [texte (HTML structuré), renvoi possible]
      ["restore"] / ["save"]   file du poste
      ["proposalCheck", alertes nouvelles]   réponse du bureau à une proposition (centre de notifications, vibreur seul)
*/
params [["_act", "list"], ["_arg", ""]];
private _out = missionNamespace getVariable ["COMSPEC_ATAK_AlertesOut", []];
missionNamespace setVariable ["COMSPEC_ATAK_AlertesOut", _out];
private _render = {
    [{
        if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "SSEALERT") then {
            ["SSEALERT"] call comspec_atak_native_fnc_pageRender;
        };
    }] call comspec_atak_native_fnc_nextFrame;
};
private _say = {
    params ["_t"];
    (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) set ["alertesStatus", [_t, diag_tickTime]];
};
private _find = {
    params ["_id"];
    _out param [_out findIf { (_x getOrDefault ["id", ""]) isEqualTo _id }, createHashMap]
};
private _pairs = { params ["_h"]; (keys _h) apply { [_x, _h get _x] } };
private _save = {
    // Gardé sur le poste : ce qui n'est pas arrivé, et les propositions dont le bureau n'a pas encore répondu.
    private _keep = _out select {
        (_x get "op") in ["create", "report", "renew", "lift"]
        && {(_x getOrDefault ["web", ""]) isNotEqualTo "OK" || {(_x getOrDefault ["resState", ""]) isEqualTo "proposal" && {(_x getOrDefault ["answered", ""]) isEqualTo ""}}}
    };
    private _flat = _keep apply {
        private _r = +_x;
        _r set ["body", [_x get "body"] call _pairs];
        // Un envoi interrompu repartira : il n'est plus « en cours » à la prochaine partie.
        if ((_r getOrDefault ["web", ""]) isEqualTo "EN ATTENTE") then { _r set ["web", "HORS LIGNE"]; _r set ["queued", true]; };
        [_r] call _pairs
    };
    profileNamespace setVariable ["COMSPEC_ATAK_AlertesOutbox", [systemTimeUTC, _flat select [((count _flat) - 20) max 0]]];
};

switch (_act) do {
    case "list": { _out };
    case "get": { [_arg] call _find };
    case "save": { call _save; true };
    case "restore": {
        private _saved = profileNamespace getVariable ["COMSPEC_ATAK_AlertesOutbox", []];
        if (!(_saved isEqualType []) || {(count _saved) < 2} || {!((_saved select 1) isEqualType [])}) exitWith { false };
        // 24 h au plus : jour civil d'hier ou d'aujourd'hui (UTC).
        private _when = _saved select 0;
        private _now = systemTimeUTC;
        private _days = { params ["_t"]; (_t param [0, 0]) * 372 + (_t param [1, 0]) * 31 + (_t param [2, 0]) };
        if (!(_when isEqualType []) || {(([_now] call _days) - ([_when] call _days)) > 1}) exitWith {
            profileNamespace setVariable ["COMSPEC_ATAK_AlertesOutbox", []];
            false
        };
        {
            if (_x isEqualType [] && {(_x findIf { !(_x isEqualType []) || {(count _x) isNotEqualTo 2} }) < 0}) then {
                private _r = createHashMapFromArray _x;
                private _b = _r getOrDefault ["body", []];
                if (_b isEqualType [] && {(_r getOrDefault ["id", ""]) isNotEqualTo ""} && {((_r get "id") isEqualType "")} && {(count ([_r get "id"] call _find)) isEqualTo 0}) then {
                    _r set ["body", createHashMapFromArray _b];
                    _out pushBack _r;
                };
            };
        } forEach (_saved select 1);
        true
    };

    case "new": {
        _arg params [["_op", ""], ["_body", createHashMap], ["_ref", ""], ["_label", ""], ["_subj", ""]];
        if !(_op in ["create", "report", "renew", "lift", "ack"]) exitWith { "" };
        // Identifiant client : 8 à 64 caractères [A-Za-z0-9_-], unique par communauté. Tiré ici, jamais retiré ensuite.
        private _seq = (missionNamespace getVariable ["COMSPEC_ATAK_AlertesSeq", 0]) + 1;
        missionNamespace setVariable ["COMSPEC_ATAK_AlertesSeq", _seq];
        private _uid = toString ((toArray (getPlayerUID player)) select { (_x >= 48 && {_x <= 57}) || {_x >= 65 && {_x <= 90}} || {_x >= 97 && {_x <= 122}} });
        systemTimeUTC params ["_y", "_mo", "_d", "_h", "_mi", "_se"];
        private _p2 = { params ["_n"]; [str _n, "0" + str _n] select (_n < 10) };
        private _cid = format ["AV-%1-%2%3%4%5%6%7-%8", _uid select [0, 20], _y, [_mo] call _p2, [_d] call _p2, [_h] call _p2, [_mi] call _p2, [_se] call _p2, _seq];
        private _send = +_body;
        if (_op isNotEqualTo "ack") then { _send set ["cid", _cid]; };
        private _rec = createHashMapFromArray [
            ["id", _cid], ["cid", _cid], ["op", _op], ["body", _send], ["ref", _ref], ["label", _label], ["subj", _subj],
            ["at", [dayTime, "HH:MM"] call BIS_fnc_timeToString], ["web", ""], ["webWhy", ""], ["queued", false], ["res", []], ["resRef", ""], ["resState", ""], ["answered", ""]
        ];
        _out pushBack _rec;
        while { (count _out) > 30 } do { _out deleteAt 0; };
        (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) set ["alertesLast", _cid];
        ["send", _cid] call comspec_atak_native_fnc_alertesSend;
        _cid
    };

    case "retry": {
        private _rec = [_arg] call _find;
        if ((count _rec) isEqualTo 0) exitWith { false };
        if ((_rec getOrDefault ["web", ""]) isEqualTo "EN ATTENTE") exitWith { ["Envoi déjà en cours"] call _say; call _render; false };
        if ((_rec getOrDefault ["web", ""]) isEqualTo "OK") exitWith { false };
        ["send", _arg] call comspec_atak_native_fnc_alertesSend
    };
    case "flush": {
        private _wait = _out select { (_x getOrDefault ["queued", false]) && {(_x getOrDefault ["web", ""]) isEqualTo "HORS LIGNE"} };
        { ["send", _x get "id"] call comspec_atak_native_fnc_alertesSend; } forEach (_wait select [0, 2]);
        (count _wait) > 0
    };
    case "del": {
        private _i = _out findIf { (_x getOrDefault ["id", ""]) isEqualTo _arg };
        if (_i >= 0 && {((_out select _i) getOrDefault ["web", ""]) isNotEqualTo "EN ATTENTE"}) then { _out deleteAt _i; };
        private _st = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
        if ((_st getOrDefault ["alertesLast", ""]) isEqualTo _arg) then { _st set ["alertesLast", ""]; };
        call _save;
        call _render;
        true
    };

    case "send": {
        private _rec = [_arg] call _find;
        if ((count _rec) isEqualTo 0 || {(_rec getOrDefault ["web", ""]) isEqualTo "EN ATTENTE"}) exitWith { false };
        _rec set ["webWhy", ""];
        // Mode de liaison (satellite ou relais) et pause des comptes non liés : hors réseau, l'envoi attend en file.
        private _why = ["gate"] call comspec_atak_native_fnc_alertesAction;
        if (_why isNotEqualTo "") exitWith {
            _rec set ["web", "HORS LIGNE"];
            _rec set ["queued", _why in ["offline", "paused"]];
            _rec set ["webWhy", ["gateText", _why] call comspec_atak_native_fnc_alertesAction];
            call _save;
            call _render;
            false
        };
        if (isNil "comspec_overwatch_connect_fnc_hashMapToJson") exitWith {
            _rec set ["web", "ÉCHEC"];
            _rec set ["webWhy", "COMSPEC Overwatch trop ancien : mettez-le à jour."];
            call _render;
            false
        };
        _rec set ["web", "EN ATTENTE"];
        _rec set ["queued", false];
        call _render;
        [_rec] spawn {
            params ["_rec"];
            private _op = _rec get "op";
            // Corps : le genre et l'opération du canal, la langue, puis les champs de la fiche (dont « cid », inchangé à chaque essai).
            private _data = +(_rec get "body");
            _data set ["kind", "sse_alertes"];
            _data set ["op", _op];
            _data set ["lang", ["lang"] call comspec_atak_native_fnc_alertesAction];
            private _raw = ["call", [_data] call comspec_overwatch_connect_fnc_hashMapToJson] call comspec_atak_native_fnc_alertesAction;
            private _p = ["page", [_raw, _op]] call comspec_atak_native_fnc_alertesAction;
            private _st = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
            private _store = uiNamespace getVariable ["COMSPEC_ATAK_Alertes", createHashMap];
            private _say = { params ["_t"]; _st set ["alertesStatus", [_t, diag_tickTime]]; };
            private _txt = { params ["_v"]; if (_v isEqualType "") then { _v } else { "" } };
            if (_p isNotEqualTo []) then {
                _rec set ["web", "OK"];
                _rec set ["webWhy", ""];
                _rec set ["res", _p];
                private _ref = _rec getOrDefault ["ref", ""];
                switch (_op) do {
                    // [1, "create", état, référence, adresse de signalement, jeton, message, replay]
                    case "create": {
                        _rec set ["resState", [_p param [2, ""]] call _txt];
                        _rec set ["resRef", [_p param [3, ""]] call _txt];
                        if ((_rec get "resState") isEqualTo "proposal") then {
                            [format ["Proposition %1 transmise au bureau : en attente de validation.", _rec get "resRef"]] call _say;
                        } else {
                            [format ["Alerte émise : %1.", _rec get "resRef"]] call _say;
                            ["load", true] spawn comspec_atak_native_fnc_alertesAction;
                        };
                        // Brouillon envoyé : l'assistant repart d'une page blanche.
                        ["sent", _rec get "id"] call comspec_atak_native_fnc_alertesDraft;
                    };
                    // [1, "report", numéro, référence de l'alerte, "new", replay]
                    case "report": {
                        _rec set ["resRef", [_p param [2, ""]] call _txt];
                        private _more = (_store getOrDefault ["more", createHashMap]) getOrDefault [_ref, createHashMap];
                        if ((count _more) > 0 && {!((_p param [5, false]) isEqualTo true)}) then { _more set ["reports", (_more getOrDefault ["reports", 0]) + 1]; };
                        [format ["Signalement %1 reçu par le bureau.", _rec get "resRef"]] call _say;
                    };
                    // [1, "renew", nouvelle référence, valable jusqu'au, adresse de signalement, jeton, replay]
                    case "renew": {
                        _rec set ["resRef", [_p param [2, ""]] call _txt];
                        [format ["Alerte renouvelée : %1, valable jusqu'au %2.", _rec get "resRef", [_p param [3, ""]] call _txt]] call _say;
                        ["load", true] spawn comspec_atak_native_fnc_alertesAction;
                    };
                    // [1, "lift", référence, levée le, replay]
                    case "lift": {
                        _rec set ["resRef", [_p param [2, ""]] call _txt];
                        [format ["Alerte %1 levée.", _rec get "resRef"]] call _say;
                        ["load", true] spawn comspec_atak_native_fnc_alertesAction;
                        ["detail", _ref] spawn comspec_atak_native_fnc_alertesAction;
                    };
                    // [1, "ack", référence, accusé le]
                    case "ack": {
                        _rec set ["resRef", [_p param [2, ""]] call _txt];
                        private _f = ["get", _ref] call comspec_atak_native_fnc_alertesAction;
                        if ((count _f) > 0) then { _f set ["acked", true]; };
                        ["Lecture accusée."] call _say;
                    };
                };
            } else {
                // Coupure pendant l'envoi : en file. Serveur qui ne connaît pas l'opération : dit tel quel, sans erreur. Refus : raison courte.
                private _low = toLower _raw;
                private _cut = _raw isEqualTo "" || {(_low find "uplink_hold") >= 0} || {(_low find "not_connected") >= 0} || {(_low find "timeout") >= 0} || {(_low find "unscheduled") >= 0};
                private _known = ["unauthorized", "forbidden", "no_tenant", "rate_limited", "unavailable", "maintenance", "not_found", "http_409", "closed", "invalid"];
                private _old = (_store getOrDefault ["avail", ""]) isEqualTo "no" || {!_cut && {(_known findIf { (_low find _x) >= 0 }) < 0}};
                _rec set ["web", ["ÉCHEC", "HORS LIGNE"] select _cut];
                _rec set ["queued", _cut];
                _rec set ["webWhy", switch (true) do {
                    case (_cut): { "Athena n'a pas répondu" };
                    case (_old): { "Indisponible sur ce serveur" };
                    default { ["errText", _raw] call comspec_atak_native_fnc_alertesAction };
                }];
                [format ["Non reçu : %1 (RENVOYER dans Mes envois).", _rec get "webWhy"]] call _say;
            };
            ["save"] call comspec_atak_native_fnc_alertesSend;
            if ((_st getOrDefault ["activePage", ""]) isEqualTo "SSEALERT") then { ["SSEALERT"] call comspec_atak_native_fnc_pageRender; };
        };
        true
    };

    // Parcours d'un envoi pour l'écran : [texte, renvoi possible]. Les états d'attente et d'échec sont ceux de fn_reportWebState.
    case "state": {
        private _rec = [_arg] call _find;
        if ((count _rec) isEqualTo 0) exitWith { ["", false] };
        private _web = _rec getOrDefault ["web", ""];
        if (_web isNotEqualTo "OK") exitWith {
            private _st = [_web, _rec getOrDefault ["webWhy", ""]] call comspec_atak_native_fnc_reportWebState;
            if (_web isEqualTo "HORS LIGNE" && {_rec getOrDefault ["queued", false]}) then {
                _st set [0, (_st select 0) + format ["<br/><t size='0.85' color='%1'>%2</t>", COMSPEC_ATAK_Th get "hexTextMuted", "En file : repart seul au retour du réseau."]];
            };
            _st
        };
        private _ok = COMSPEC_ATAK_Th get "hexOk";
        private _warn = COMSPEC_ATAK_Th get "hexWarn";
        private _ref = _rec getOrDefault ["resRef", ""];
        private _line = switch (_rec get "op") do {
            case "create": {
                if ((_rec getOrDefault ["resState", ""]) isEqualTo "proposal") then {
                    if ((_rec getOrDefault ["answered", ""]) isNotEqualTo "") then {
                        format ["<t color='%1'>%2</t>", _ok, format ["Proposition %1 acceptée par le bureau : %2", _ref, _rec get "answered"]]
                    } else {
                        format ["<t color='%1'>%2</t>", _warn, format ["Proposé au bureau · %1 · en attente de validation", _ref]]
                    }
                } else {
                    format ["<t color='%1'>%2</t>", _ok, format ["Émis · %1", _ref]]
                }
            };
            case "report": { format ["<t color='%1'>%2</t>", _ok, format ["Signalement %1 reçu par le bureau", _ref]] };
            case "renew": { format ["<t color='%1'>%2</t>", _ok, format ["Renouvelée · %1", _ref]] };
            case "lift": { format ["<t color='%1'>%2</t>", _ok, format ["Levée · %1", _ref]] };
            default { format ["<t color='%1'>%2</t>", _ok, "Lecture accusée"] };
        };
        [_line, false]
    };

    // Le site ne rend pas l'état d'une proposition : elle « n'apparaît dans la liste qu'une fois acceptée, sous sa référence
    // définitive » (ICD § 3.4). L'alerte nouvelle du même genre et du même sujet est donc la réponse du bureau.
    case "proposalCheck": {
        if !(_arg isEqualType []) exitWith { false };
        private _hit = false;
        {
            private _rec = _x;
            if ((_rec get "op") isEqualTo "create" && {(_rec getOrDefault ["resState", ""]) isEqualTo "proposal"} && {(_rec getOrDefault ["answered", ""]) isEqualTo ""}) then {
                private _subj = toLower (_rec getOrDefault ["subj", ""]);
                private _doc = (_rec get "body") getOrDefault ["doc", ""];
                private _i = _arg findIf { _subj isNotEqualTo "" && {(toLower (_x getOrDefault ["name", ""])) isEqualTo _subj} && {(_x getOrDefault ["doc", ""]) isEqualTo _doc} };
                if (_i >= 0) then {
                    _rec set ["answered", (_arg select _i) get "ref"];
                    _hit = true;
                    ["SUCCESS", format ["Proposition %1 acceptée par le bureau : %2", _rec getOrDefault ["resRef", ""], _rec get "answered"], 0, 30, "SSEALERT", "vibrate"] call comspec_atak_native_fnc_notify;
                };
            };
        } forEach _out;
        if (_hit) then { call _save; };
        _hit
    };
    default { false };
};
