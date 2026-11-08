/*
    ORBAT du site pour l'onglet ORBAT du BFT (fn_pageBft) : lecture, dernier ORBAT reçu et présence des joueurs.
    Source : GetCommunityOrbat de la DLL (GET /api/atak/community-orbat), en tâche de fond par fn_extAwait, une lecture à la
    fois, au plus toutes les 5 min (1 min après une erreur). Le dernier ORBAT reçu reste dans uiNamespace jusqu'à la fin du jeu
    (rien sur le disque) et s'affiche comme « pas à jour » si une lecture échoue. Champs inconnus de l'API ignorés.
    Rapprochement : « tag » de l'API = empreinte du Steam ID (HashText de « orbat|sel|uid »), jamais par le nom. Seuls les
    joueurs de mon camp sont rapprochés : un membre passé en face reste « hors ligne », rien ne révèle sa présence.
    Params : [action, argument]
      "load" forcer       relit l'API si l'échéance est passée (forcer : au plus toutes les 10 s) ; ne bloque jamais
      "receive" réponse   (tâche de fond) traite la réponse de la DLL
      "store"             table : state (IDLE | OK | NOLINK | FORBID | DOWN | DLL | OFF), units, people, tags, total, okAt, truncated
                            units : [id, profondeur, nom, code, lignes, restreinte, échelon, fonction MIL-STD, QG, id du parent]
                                    à plat, parent avant enfants (les quatre derniers : symboles de fn_milSym)
                            lignes : ["p", clé, poste, "commander" | "deputy" | ""] personne, ["v", clé, poste, nombre] poste vacant
                            people : clé -> [libellé, nom, grade, indicatif, genre (0 personne, 1 anonymisée, 2 vacant), [[unité, poste]...]]
      "presence"          [instant, clé de personne -> joueur de mon camp, joueurs de mon camp absents de l'ORBAT] (relu toutes les 2 s)
      "notice"            [texte, ton (ok | warn | alert | muted), réessayer] du bandeau de l'onglet
      "sig"               signature pour fn_statusUpdate
*/
params [["_act", ""], ["_arg", ""]];
private _store = uiNamespace getVariable "COMSPEC_ATAK_BftOrbat";
if (isNil "_store") then {
    _store = createHashMapFromArray [["state", "IDLE"], ["units", []], ["people", createHashMap], ["tags", createHashMap], ["total", 0], ["salt", ""], ["rev", ""], ["tenant", ""], ["okAt", -1e9], ["truncated", false]];
    uiNamespace setVariable ["COMSPEC_ATAK_BftOrbat", _store];
};
private _forget = {
    _store set ["units", []]; _store set ["people", createHashMap]; _store set ["tags", createHashMap];
    _store set ["total", 0]; _store set ["salt", ""]; _store set ["rev", ""]; _store set ["truncated", false];
};
private _minutes = { floor ((diag_tickTime - (_store get "okAt")) / 60) };
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
        if !([] call comspec_atak_native_fnc_bridge) exitWith { _store set ["state", "OFF"]; };
        if !(missionNamespace getVariable ["COMSPEC_AthenaReady", false]) exitWith { _store set ["state", "NOLINK"]; };
        if (diag_tickTime - (missionNamespace getVariable ["COMSPEC_ATAK_BftOrbatBusyAt", -1e9]) < 60) exitWith {};
        private _force = _arg isEqualTo true && {diag_tickTime - (missionNamespace getVariable ["COMSPEC_ATAK_BftOrbatAskedAt", -1e9]) > 10};
        if (!_force && {diag_tickTime < (missionNamespace getVariable ["COMSPEC_ATAK_BftOrbatNext", -1e9])}) exitWith {};
        missionNamespace setVariable ["COMSPEC_ATAK_BftOrbatBusyAt", diag_tickTime];
        missionNamespace setVariable ["COMSPEC_ATAK_BftOrbatAskedAt", diag_tickTime];
        [] spawn {
            private _r = ["COMSPECExtension", "GetCommunityOrbat", []] call comspec_atak_native_fnc_extAwait;
            if (_r isEqualType []) then { _r = _r param [0, ""]; };
            if !(_r isEqualType "") then { _r = ""; };
            ["receive", _r] call comspec_atak_native_fnc_bftOrbat;
            missionNamespace setVariable ["COMSPEC_ATAK_BftOrbatBusyAt", -1e9];
        };
    };
    case "receive": {
        private _next = { missionNamespace setVariable ["COMSPEC_ATAK_BftOrbatNext", diag_tickTime + _this]; };
        if ((_arg select [0, 3]) isNotEqualTo "OK|") exitWith {
            private _code = if ((_arg select [0, 4]) isEqualTo "ERR|") then { _arg select [4] } else { "" };
            switch (true) do {
                case (_code isEqualTo "busy"): { 30 call _next; };
                case (_code in ["not_linked", "unauthorized", "no_tenant"]): { call _forget; _store set ["state", "NOLINK"]; 120 call _next; };
                case (_code isEqualTo "forbidden"): { call _forget; _store set ["state", "FORBID"]; 300 call _next; };
                case (_code isEqualTo "uplink_hold"): { _store set ["state", "OFF"]; 60 call _next; };
                case (_code isEqualTo "" || {(_code select [0, 7]) isEqualTo "unknown"}): { _store set ["state", "DLL"]; 300 call _next; };
                default { _store set ["state", "DOWN"]; 60 call _next; };
            };
        };
        (_arg splitString "|") params ["", ["_n", "0"], ["_rev", ""]];
        // Même révision : rien à relire.
        if (_rev isNotEqualTo "" && {_rev isEqualTo (_store get "rev")}) exitWith {
            _store set ["state", "OK"]; _store set ["okAt", diag_tickTime]; 300 call _next;
        };
        private _txt = "";
        private _ok = true;
        for "_i" from 0 to ((parseNumber _n) - 1) do {
            private _c = ["COMSPECExtension", "GetCommunityOrbatChunk", [str _i]] call comspec_overwatch_connect_fnc_extDirect;
            if (_c isEqualType []) then { _c = _c param [0, ""]; };
            if !(_c isEqualType "" && {(_c select [0, 3]) isEqualTo "OK|"}) exitWith { _ok = false; };
            _txt = _txt + (_c select [3]);
        };
        private _root = if (_ok && {_txt isNotEqualTo ""}) then { parseSimpleArray _txt } else { [] };
        if (isNil "_root" || {!(_root isEqualType [])} || {(count _root) isEqualTo 0}) exitWith { _store set ["state", "DOWN"]; 60 call _next; };
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
        _store set ["state", "OK"];
        _store set ["okAt", diag_tickTime];
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
        private _copy = (count (_store get "units")) > 0;
        private _old = [format ["dernier ORBAT reçu il y a %1 min, pas à jour", call _minutes], "aucun ORBAT reçu"] select !_copy;
        switch (_store get "state") do {
            case "OK": {
                switch (true) do {
                    case (!_copy): { ["Votre communauté n'a pas d'ORBAT sur le site.", "muted", false] };
                    case ((_store get "truncated")): { [format ["ORBAT du site incomplet : trop d'unités ou de membres. Lu il y a %1 min", call _minutes], "warn", false] };
                    case ((call _minutes) >= 15): { [format ["ORBAT du site : %1", _old], "warn", true] };
                    default { [format ["ORBAT du site · lu il y a %1 min", call _minutes], "muted", false] };
                }
            };
            case "NOLINK": { [["Compte Athena non lié : reliez votre compte dans l'app Athena pour voir l'ORBAT du site.", format ["Compte Athena non connecté : %1.", _old]] select _copy, "warn", false] };
            case "FORBID": { ["Votre rôle sur le site ne donne pas accès à l'ORBAT.", "warn", false] };
            case "DLL": { ["COMSPEC Link trop ancien pour l'ORBAT du site : quittez Arma 3 complètement pour le mettre à jour.", "alert", false] };
            case "DOWN": { [format ["Athena injoignable : %1. Touchez pour réessayer.", _old], "alert", true] };
            case "OFF": { [format ["Liaison Athena coupée : %1.", _old], "warn", false] };
            default { ["Lecture de l'ORBAT du site…", "muted", false] };
        }
    };
    case "sig": {
        private _seen = ["presence"] call comspec_atak_native_fnc_bftOrbat;
        [_store get "rev", _store get "state", floor ((call _minutes) / 5), count (_seen select 1), count (_seen select 2)]
    };
    default { nil };
}
