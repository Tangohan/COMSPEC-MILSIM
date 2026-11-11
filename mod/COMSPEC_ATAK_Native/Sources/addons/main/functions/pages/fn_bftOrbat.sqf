/*
    ORBAT du site pour l'onglet ORBAT du BFT (fn_pageBft) : lecture, dernier ORBAT reçu et présence des joueurs.
    Source : GetCommunityOrbat de la DLL (GET /api/atak/community-orbat), en tâche de fond par fn_extAwait, une lecture à la
    fois, au plus toutes les 5 min. Le dernier ORBAT reçu reste dans uiNamespace jusqu'à la fin du jeu
    (rien sur le disque) et s'affiche comme « pas à jour » si une lecture échoue. Champs inconnus de l'API ignorés.
    Lecture bornée (retour de jeu 4.4.0 : « Lecture de l'ORBAT du site… » restait affiché sans fin) : réponse attendue 15 s au
    plus, LOADING jamais plus de 20 s (garde-fou relu à chaque affichage, même si la tâche de fond a disparu), puis DOWN avec
    sa raison et un nouvel essai 15 s, 30 s, 1 min, 2 min puis 5 min après. Aucune requête sans liaison autorisée (WAIT).
    Chaque changement d'état : une ligne « [COMSPEC orbat] » au RPT (codes d'erreur seuls, jamais de jeton).
    Rapprochement : « tag » de l'API = empreinte du Steam ID (HashText de « orbat|sel|uid »), jamais par le nom. Seuls les
    joueurs de mon camp sont rapprochés : un membre passé en face reste « hors ligne », rien ne révèle sa présence.
    Params : [action, argument]
      "load" forcer       relit l'API si l'échéance est passée (forcer : au plus toutes les 10 s) ; ne bloque jamais
      "receive" réponse   (tâche de fond) traite la réponse de la DLL
      "store"             table : state (IDLE | LOADING | OK | EMPTY | WAIT | NOLINK | FORBID | DOWN | DLL | OFF), why (raison
                            courte de DOWN), units, people, tags, total, okAt, loadAt, fails, truncated
                            WAIT : liaison Athena pas autorisée (compte non relié, licence en attente), rien n'est demandé ;
                            NOLINK : le site répond « compte non relié » ; EMPTY : aucun ORBAT défini sur le site
                            units : [id, profondeur, nom, code, lignes, restreinte, échelon, fonction MIL-STD, QG, id du parent]
                                    à plat, parent avant enfants (les quatre derniers : symboles de fn_milSym)
                            lignes : ["p", clé, poste, "commander" | "deputy" | ""] personne, ["v", clé, poste, nombre] poste vacant
                            people : clé -> [libellé, nom, grade, indicatif, genre (0 personne, 1 anonymisée, 2 vacant), [[unité, poste]...]]
      "presence"          [instant, clé de personne -> joueur de mon camp, joueurs de mon camp absents de l'ORBAT] (relu toutes les 2 s)
      "notice"            [texte, ton (ok | info | warn | alert | muted), réessayer] du bandeau de l'onglet (info, warn, alert :
                            au-dessus du tableau, en entier ; muted : une ligne du tableau)
      "sig"               signature pour fn_statusUpdate
*/
params [["_act", ""], ["_arg", ""]];
private _store = uiNamespace getVariable "COMSPEC_ATAK_BftOrbat";
if (isNil "_store") then {
    _store = createHashMapFromArray [["state", "IDLE"], ["units", []], ["people", createHashMap], ["tags", createHashMap], ["total", 0], ["salt", ""], ["rev", ""], ["tenant", ""], ["okAt", -1e9], ["truncated", false], ["why", ""], ["loadAt", diag_tickTime], ["fails", 0]];
    uiNamespace setVariable ["COMSPEC_ATAK_BftOrbat", _store];
};
private _forget = {
    _store set ["units", []]; _store set ["people", createHashMap]; _store set ["tags", createHashMap];
    _store set ["total", 0]; _store set ["salt", ""]; _store set ["rev", ""]; _store set ["truncated", false];
};
private _minutes = { floor ((diag_tickTime - (_store get "okAt")) / 60) };
// Changement d'état : une ligne au RPT, seulement quand l'état ou sa raison change.
private _set = {
    params ["_st", ["_why", ""], ["_detail", ""]];
    private _old = _store getOrDefault ["state", "IDLE"];
    _store set ["state", _st];
    _store set ["why", _why];
    if (_old isNotEqualTo _st || {_why isNotEqualTo (_store getOrDefault ["whyLog", ""])}) then {
        _store set ["whyLog", _why];
        diag_log format ["[COMSPEC orbat] %1 -> %2%3%4", _old, _st, ["", " : " + _why] select (_why isNotEqualTo ""), ["", " (" + _detail + ")"] select (_detail isNotEqualTo "")];
    };
};
// Échec : nouvel essai 15 s, 30 s, 1 min, 2 min, puis toutes les 5 min.
private _retry = {
    private _f = (_store getOrDefault ["fails", 0]) + 1;
    _store set ["fails", _f];
    missionNamespace setVariable ["COMSPEC_ATAK_BftOrbatNext", diag_tickTime + ((15 * 2 ^ ((_f - 1) min 5)) min 300)];
};
// Garde-fou : une lecture jamais finie (réponse perdue, tâche de fond arrêtée, partie relancée) ne laisse pas le bandeau.
private _watch = {
    if ((_store getOrDefault ["state", ""]) in ["LOADING", "IDLE"] && {diag_tickTime - (_store getOrDefault ["loadAt", diag_tickTime]) > 20}) then {
        ["DOWN", "pas de réponse en 15 s", "garde-fou 20 s"] call _set;
        call _retry;
        missionNamespace setVariable ["COMSPEC_ATAK_BftOrbatBusyAt", -1e9];
    };
};
switch (_act) do {
    case "store": { _store };
    case "load": {
        if (!hasInterface) exitWith {};
        // Autre communauté : l'ORBAT de la précédente n'est jamais montré.
        private _tenant = missionNamespace getVariable ["comspec_tenant_name", ""];
        if !(_tenant isEqualType "") then { _tenant = ""; };
        if (_tenant isNotEqualTo "") then {
            if ((_store get "tenant") isNotEqualTo "" && {(_store get "tenant") isNotEqualTo _tenant}) then { call _forget; _store set ["okAt", -1e9]; };
            _store set ["tenant", _tenant];
        };
        call _watch;
        if !([] call comspec_atak_native_fnc_bridge) exitWith { ["OFF", "COMSPEC Overwatch absent"] call _set; };
        // Licence ou compte : aucune requête vers Athena avant l'autorisation ; relu dès qu'elle arrive.
        if !(missionNamespace getVariable ["COMSPEC_AthenaReady", false]) exitWith { ["WAIT", "liaison Athena pas encore autorisée, aucune requête"] call _set; };
        // Une lecture à la fois ; au-delà de 25 s elle est tenue pour perdue (garde-fou ci-dessus).
        if (diag_tickTime - (missionNamespace getVariable ["COMSPEC_ATAK_BftOrbatBusyAt", -1e9]) < 25) exitWith {};
        private _force = _arg isEqualTo true && {diag_tickTime - (missionNamespace getVariable ["COMSPEC_ATAK_BftOrbatAskedAt", -1e9]) > 10};
        if (_force) then { _store set ["fails", 0]; };
        // Liaison qui vient d'être autorisée : lecture tout de suite.
        if (!_force && {!((_store get "state") in ["WAIT", "OFF", "IDLE"])} && {diag_tickTime < (missionNamespace getVariable ["COMSPEC_ATAK_BftOrbatNext", -1e9])}) exitWith {};
        missionNamespace setVariable ["COMSPEC_ATAK_BftOrbatBusyAt", diag_tickTime];
        missionNamespace setVariable ["COMSPEC_ATAK_BftOrbatAskedAt", diag_tickTime];
        // Échéance par défaut si la réponse n'est jamais traitée ; « receive » la remplace.
        missionNamespace setVariable ["COMSPEC_ATAK_BftOrbatNext", diag_tickTime + 30];
        // ORBAT déjà affiché : relu en silence. Sinon bandeau de lecture, borné par le garde-fou.
        // Déjà en lecture (nouvel essai après « occupé ») : le délai court depuis la première demande.
        if !((_store get "state") in ["OK", "EMPTY", "LOADING"]) then {
            _store set ["loadAt", diag_tickTime];
            ["LOADING", "", ["première lecture", "nouvel essai"] select ((_store getOrDefault ["fails", 0]) > 0)] call _set;
        };
        [] spawn {
            private _r = ["COMSPECExtension", "GetCommunityOrbat", [], 15] call comspec_atak_native_fnc_extAwait;
            if (_r isEqualType []) then { _r = _r param [0, ""]; };
            if !(_r isEqualType "") then { _r = ""; };
            ["receive", _r] call comspec_atak_native_fnc_bftOrbat;
            missionNamespace setVariable ["COMSPEC_ATAK_BftOrbatBusyAt", -1e9];
        };
    };
    case "receive": {
        private _next = { missionNamespace setVariable ["COMSPEC_ATAK_BftOrbatNext", diag_tickTime + _this]; };
        private _took = format ["%1 s", (diag_tickTime - (missionNamespace getVariable ["COMSPEC_ATAK_BftOrbatAskedAt", diag_tickTime])) toFixed 1];
        if ((_arg select [0, 3]) isNotEqualTo "OK|") exitWith {
            private _code = if ((_arg select [0, 4]) isEqualTo "ERR|") then { ((_arg select [4]) splitString "|") param [0, ""] } else { "" };
            private _detail = format ["%1, %2", ["réponse vide", "ERR|" + (_code select [0, 40])] select (_code isNotEqualTo ""), _took];
            switch (true) do {
                // Lecture précédente encore dans COMSPEC Link : nouvel essai bientôt, le garde-fou borne l'attente.
                case (_code isEqualTo "busy"): { 5 call _next; diag_log format ["[COMSPEC orbat] COMSPEC Link occupé par la lecture précédente (%1)", _took]; };
                case (_code in ["not_linked", "unauthorized", "no_tenant"]): { call _forget; ["NOLINK", "compte Athena non relié", _detail] call _set; 60 call _next; };
                case (_code isEqualTo "forbidden"): { call _forget; ["FORBID", "rôle sans accès à l'ORBAT", _detail] call _set; 300 call _next; };
                case (_code isEqualTo "uplink_hold"): { ["OFF", "plus de téléphone ATAK ou liaison coupée", _detail] call _set; 60 call _next; };
                case (_code isEqualTo "" || {(_code select [0, 7]) isEqualTo "unknown"} || {_code isEqualTo "use_async"}): { ["DLL", "COMSPEC Link trop ancien", _detail] call _set; 300 call _next; };
                default {
                    private _why = switch (true) do {
                        case (_code in ["timeout", "unscheduled"]): { "pas de réponse en 15 s" };
                        case (_code in ["network", "not_connected", "invalid_url"]): { "réseau injoignable" };
                        case (_code in ["rate_limited", "busy_retry"]): { "trop de demandes" };
                        case (_code in ["invalid_response", "invalid_json"]): { "réponse illisible" };
                        case (_code isEqualTo "too_large"): { "ORBAT trop volumineux" };
                        case (_code isEqualTo "unavailable" || {(_code select [0, 6]) isEqualTo "http_5"}): { "site momentanément indisponible" };
                        default { "erreur " + (_code select [0, 24]) };
                    };
                    ["DOWN", _why, _detail] call _set;
                    call _retry;
                };
            };
        };
        (_arg splitString "|") params ["", ["_n", "0"], ["_rev", ""]];
        // Même révision : rien à relire.
        if (_rev isNotEqualTo "" && {_rev isEqualTo (_store get "rev")}) exitWith {
            _store set ["okAt", diag_tickTime]; _store set ["fails", 0];
            [["EMPTY", "OK"] select ((count (_store get "units")) > 0), "", "révision inchangée, " + _took] call _set;
            300 call _next;
        };
        private _txt = "";
        private _ok = true;
        private _nChunks = ((parseNumber _n) max 0) min 100;
        for "_i" from 0 to (_nChunks - 1) do {
            private _c = ["COMSPECExtension", "GetCommunityOrbatChunk", [str _i]] call comspec_overwatch_connect_fnc_extDirect;
            if (_c isEqualType []) then { _c = _c param [0, ""]; };
            if !(_c isEqualType "" && {(_c select [0, 3]) isEqualTo "OK|"}) exitWith { _ok = false; };
            _txt = _txt + (_c select [3]);
        };
        private _root = if (_ok && {_txt isNotEqualTo ""}) then { parseSimpleArray _txt } else { [] };
        if (isNil "_root" || {!(_root isEqualType [])} || {(count _root) isEqualTo 0}) exitWith {
            ["DOWN", ["lecture incomplète", "réponse illisible"] select _ok, format ["%1 morceau(x), %2 car., %3", _nChunks, count _txt, _took]] call _set;
            call _retry;
        };
        private _h = {
            params ["_a"];
            if !(_a isEqualType []) exitWith { createHashMap };
            createHashMapFromArray (_a select { _x isEqualType [] && {(count _x) isEqualTo 2} && {(_x select 0) isEqualType ""} })
        };
        private _str = { params ["_m", "_k"]; private _v = _m getOrDefault [_k, ""]; if (_v isEqualType "") then { (trim _v) select [0, 60] } else { "" } };
        private _num = { params ["_m", "_k"]; private _v = _m getOrDefault [_k, 0]; [0, _v] select (_v isEqualType 0) };
        private _arr = { params ["_m", "_k"]; private _v = _m getOrDefault [_k, []]; [[], _v] select (_v isEqualType []) };
        private _m = [_root] call _h;
        private _people = createHashMap;
        private _tags = createHashMap;
        private _depth = createHashMap;
        private _total = 0;
        private _anonN = 0;
        // Une personne : clé, et libellé « Grade Indicatif · Prénom N. » calculé une fois pour toutes.
        private _addP = {
            params ["_p", "_unit", "_post"];
            private _pm = [_p] call _h;
            private _uid = [_pm, "user"] call _num;
            private _anon = _uid < 1 || {(_pm getOrDefault ["anonymized", false]) isEqualTo true};
            private _key = if (_anon) then { _anonN = _anonN + 1; format ["p:a%1", _anonN] } else { format ["p:u%1", _uid] };
            private _e = _people getOrDefault [_key, []];
            if ((count _e) isEqualTo 0) then {
                private _name = [_pm, "name"] call _str;
                private _grade = [_pm, "grade"] call _str;
                private _cs = [_pm, "callsign"] call _str;
                private _main = [_cs, _name] select (_cs isEqualTo "");
                private _label = trim format ["%1 %2", _grade, _main];
                if (_cs isNotEqualTo "" && {_name isNotEqualTo ""} && {(toLower _name) isNotEqualTo (toLower _cs)}) then { _label = format ["%1 · %2", _label, _name]; };
                _e = [_label, _name, _grade, _cs, [0, 1] select _anon, []];
                _people set [_key, _e];
                if (!_anon) then {
                    _total = _total + 1;
                    private _tag = [_pm, "tag"] call _str;
                    if (_tag isNotEqualTo "") then { _tags set [_tag, _key]; };
                };
            };
            (_e select 5) pushBack [_unit, _post];
            _key
        };
        private _units = (([_m, "units"] call _arr) select [0, 300]) apply {
            private _u = [_x] call _h;
            private _id = [_u, "id"] call _num;
            private _d = ((_depth getOrDefault [[_u, "parent"] call _num, -1]) + 1) min 15;
            _depth set [_id, _d];
            private _name = [_u, "name"] call _str;
            private _rows = [];
            {
                private _pm = [_x] call _h;
                private _title = [_pm, "title"] call _str;
                private _idx = _forEachIndex;
                private _kind = [_pm, "key"] call _str;
                { _rows pushBack ["p", [_x, _name, _title] call _addP, _title, _kind]; } forEach ([_pm, "holders"] call _arr);
                private _vac = [_pm, "vacant"] call _num;
                if (_vac > 0) then {
                    private _key = format ["p:v%1_%2", _id, _idx];
                    _people set [_key, [_title, _title, "", [_pm, "callsign"] call _str, 2, [[_name, _title]]]];
                    _rows pushBack ["v", _key, _title, _vac];
                };
            } forEach ([_u, "posts"] call _arr);
            { _rows pushBack ["p", [_x, _name, ""] call _addP, ""]; } forEach ([_u, "members"] call _arr);
            [_id, _d, _name, [_u, "code"] call _str, _rows, (_u getOrDefault ["restricted", false]) isEqualTo true,
                [_u, "echelon"] call _str, [_u, "function"] call _str, (_u getOrDefault ["hq", false]) isEqualTo true, [_u, "parent"] call _num]
        };
        _store set ["units", _units];
        _store set ["people", _people];
        _store set ["tags", _tags];
        _store set ["total", _total];
        _store set ["salt", [_m, "tag_salt"] call _str];
        _store set ["rev", _rev];
        _store set ["truncated", (_m getOrDefault ["truncated", false]) isEqualTo true];
        _store set ["okAt", diag_tickTime];
        _store set ["fails", 0];
        // Aucune unité : pas d'ORBAT défini sur le site pour cette communauté (état normal, expliqué au joueur).
        [["EMPTY", "OK"] select ((count _units) > 0), "", format ["%1 unité(s), %2 membre(s), %3 morceau(x), %4", count _units, _total, _nChunks, _took]] call _set;
        300 call _next;
    };
    case "presence": {
        private _seen = uiNamespace getVariable ["COMSPEC_ATAK_BftOrbatSeen", [-1e9, createHashMap, [], ""]];
        if (diag_tickTime - (_seen select 0) < 2 && {(_seen select 3) isEqualTo (_store get "rev")}) exitWith { _seen };
        private _salt = _store get "salt";
        private _tags = _store get "tags";
        // Empreintes gardées par UID : la DLL n'est appelée qu'une fois par joueur.
        private _hash = uiNamespace getVariable ["COMSPEC_ATAK_BftOrbatHash", ["", createHashMap]];
        if ((_hash select 0) isNotEqualTo _salt) then { _hash = [_salt, createHashMap]; uiNamespace setVariable ["COMSPEC_ATAK_BftOrbatHash", _hash]; };
        private _known = _hash select 1;
        private _canHash = _salt isNotEqualTo "" && {!isNil "comspec_overwatch_connect_fnc_extDirect"};
        // Mort : plus de camp lisible ; il garde celui relevé de son vivant.
        private _side = [side group player, playerSide] select (!alive player);
        private _friends = uiNamespace getVariable ["COMSPEC_ATAK_BftOrbatFriends", createHashMap];
        uiNamespace setVariable ["COMSPEC_ATAK_BftOrbatFriends", _friends];
        private _byKey = createHashMap;
        private _strays = [];
        {
            private _uid = getPlayerUID _x;
            if (_uid isEqualTo "" || {_x isKindOf "HeadlessClient_F"}) then { continue };
            private _mine = if (alive _x) then { (side group _x) isEqualTo _side } else { _friends getOrDefault [_uid, false] };
            _friends set [_uid, _mine];
            if (!_mine) then { continue };
            private _tag = _known getOrDefault [_uid, ""];
            if (_tag isEqualTo "" && {_canHash}) then {
                private _r = ["COMSPECExtension", "HashText", [format ["orbat|%1|%2", _salt, _uid]]] call comspec_overwatch_connect_fnc_extDirect;
                if (_r isEqualType []) then { _r = _r param [0, ""]; };
                if (_r isEqualType "" && {(_r select [0, 3]) isEqualTo "OK|"}) then { _tag = _r select [3, 24]; _known set [_uid, _tag]; };
            };
            private _key = _tags getOrDefault [_tag, ""];
            if (_key isNotEqualTo "") then { _byKey set [_key, _x]; } else { if (_x isNotEqualTo player) then { _strays pushBack _x; }; };
        } forEach allPlayers;
        _seen = [diag_tickTime, _byKey, _strays, _store get "rev"];
        uiNamespace setVariable ["COMSPEC_ATAK_BftOrbatSeen", _seen];
        _seen
    };
    case "notice": {
        call _watch;
        private _copy = (count (_store get "units")) > 0;
        private _old = [format ["dernier ORBAT reçu il y a %1 min, pas à jour", call _minutes], "aucun ORBAT reçu"] select !_copy;
        switch (_store get "state") do {
            // État normal (ORBAT pas encore saisi sur le site) : dire ce que l'on voit à la place.
            case "EMPTY": { ["Aucun ORBAT défini sur Athena pour votre unité. Les unités s'affichent par groupe de jeu à la place.", "info", false] };
            case "WAIT": { ["Liaison Athena pas encore autorisée (compte non relié ou licence en attente). L'ORBAT du site sera lu dès qu'elle le sera ; en attendant, les unités s'affichent par groupe de jeu.", "warn", false] };
            // Erreur : une ligne, sa raison et le bouton Réessayer ; le dernier ORBAT reçu reste affiché dessous.
            case "DOWN": {
                private _why = _store getOrDefault ["why", ""];
                if (_why isEqualTo "") then { _why = "site injoignable"; };
                [[format ["ORBAT du site indisponible : %1", _why], format ["ORBAT du site indisponible : %1 · dernier reçu il y a %2 min", _why, call _minutes]] select _copy, "alert", true]
            };
            case "LOADING": { [["Lecture de l'ORBAT du site…", "Mise à jour de l'ORBAT du site…"] select _copy, "muted", false] };
            case "OK": {
                switch (true) do {
                    case (!_copy): { ["Aucun ORBAT défini sur Athena pour votre unité. Les unités s'affichent par groupe de jeu à la place.", "info", false] };
                    case ((_store get "truncated")): { [format ["ORBAT du site incomplet : trop d'unités ou de membres. Lu il y a %1 min", call _minutes], "warn", false] };
                    case ((call _minutes) >= 15): { [format ["ORBAT du site : %1", _old], "warn", true] };
                    default { [format ["ORBAT du site · lu il y a %1 min", call _minutes], "muted", false] };
                }
            };
            case "NOLINK": { [["Compte Athena non lié : reliez votre compte dans l'app Athena pour voir l'ORBAT du site.", format ["Compte Athena non connecté : %1.", _old]] select _copy, "warn", false] };
            case "FORBID": { ["Votre rôle sur le site ne donne pas accès à l'ORBAT.", "warn", false] };
            case "DLL": { ["COMSPEC Link trop ancien pour l'ORBAT du site : quittez Arma 3 complètement pour le mettre à jour.", "alert", false] };
            case "OFF": { [format ["Liaison Athena coupée : %1.", _old], "warn", false] };
            default { ["Lecture de l'ORBAT du site…", "muted", false] };
        }
    };
    case "sig": {
        call _watch;
        private _seen = ["presence"] call comspec_atak_native_fnc_bftOrbat;
        [_store get "rev", _store get "state", floor ((call _minutes) / 5), count (_seen select 1), count (_seen select 2)]
    };
    default { nil };
}
