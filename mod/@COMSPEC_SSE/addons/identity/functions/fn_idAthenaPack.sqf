/*
    Liaison Athena du contrôle d'identité (DLL COMSPEC Link). Sans les fonctions dans la DLL, tout marche hors ligne :
    papiers générés, base locale (N3), file d'attente des transmissions.
    ["has"]                -> Boolean : la DLL connaît les fonctions d'identité (COMSPEC Link ≥ 2.0.118 :
                              GetAtakFeatures ; sinon sonde GetIdentityPackChunk « -1 », locale ; « ERR|unknown » = absente)
    ["fetch"]              -> texte du paquet ("" si échec) : GetIdentityPack [carte, opération, depuis] = OK|n|horodatage,
                              puis GetIdentityPackChunk [i] = OK|morceau i, recollés (contexte planifié : spawn)
    ["apply", texte]       SERVEUR : paquet -> registre (pays, documents délivrés, identités, avis de recherche)
    ["load"]               SERVEUR : charge le paquet au début de la mission (DLL du serveur, sinon celle d'un Zeus)
    ["merge", [N3, brut]]  JOUEUR : réponse QueryIdDocument = OK|[trouvé, verdict, réf, niveau, drapeaux, mis à jour]
                              ajoutée au résultat local du niveau N3
    Paquet (COMSPEC Link 2.0.118, CONTRACT-CHANGES) : JSON converti pour parseSimpleArray, objet = paires
    [["clé", valeur]…], null = "" ; racine version, generated_at, map, op, (since), countries, identities, documents,
    watch (1re page seulement), revoked (ids révoqués depuis « since »). Ordre fixe des champs aussi accepté.
*/
params [["_op", ""], ["_a", []]];
switch (_op) do {
    case "has": {
        private _have = missionNamespace getVariable "comspec_sse_idDll";
        if (!isNil "_have") exitWith { _have };
        private _call = {
            private _r = "COMSPECExtension" callExtension _this;
            if (_r isEqualType []) then { _r = _r param [0, ""]; };
            if !(_r isEqualType "") then { _r = str _r; };
            _r
        };
        // COMSPEC Link 2.0.118 et plus : fonctions annoncées par GetAtakFeatures (OK|version|…,GetIdentityPack,…).
        private _caps = ["GetAtakFeatures", []] call _call;
        _have = (_caps find "GetIdentityPack") >= 0 && {(_caps find "QueryIdDocument") >= 0} && {(_caps find "SubmitIdCheck") >= 0};
        if (!_have && {_caps isNotEqualTo ""}) then {
            private _probe = ["GetIdentityPackChunk", ["-1"]] call _call;
            _have = _probe isNotEqualTo "" && {(toLower _probe) find "unknown" < 0};
        };
        if (!_have && {_caps isNotEqualTo ""}) then { diag_log text "[COMSPEC SSE] Identité : COMSPEC Link antérieur à 2.0.118, contrôles d'identité hors ligne (base locale, file d'attente)."; };
        missionNamespace setVariable ["comspec_sse_idDll", _have];
        _have
    };
    case "fetch": {
        if !(["has"] call comspec_sse_fnc_idAthenaPack) exitWith { "" };
        private _since = missionNamespace getVariable ["comspec_sse_idPackTs", ""];
        private _head = ["GetIdentityPack", [str (missionNamespace getVariable ["COMSPEC_MapId", 1]), missionNamespace getVariable ["comspec_sse_id_opKey", ""], _since]] call comspec_sse_fnc_extensionCall;
        if ((_head select [0, 3]) isNotEqualTo "OK|") exitWith { diag_log text format ["[COMSPEC SSE] Identité : paquet Athena indisponible (%1).", _head select [0, 80]]; "" };
        private _p = _head splitString "|";
        private _n = parseNumber (_p param [1, "0"]);
        missionNamespace setVariable ["comspec_sse_idPackTs", _p param [2, ""]];
        private _buf = "";
        private _ok = true;
        for "_i" from 0 to (_n - 1) do {
            private _c = ["GetIdentityPackChunk", [str _i]] call comspec_sse_fnc_extensionCall;
            if ((_c select [0, 3]) isNotEqualTo "OK|") exitWith { _ok = false; };
            _buf = _buf + (_c select [3]);
        };
        [_buf, ""] select !_ok
    };
    case "apply": {
        if (!isServer) exitWith {};
        private _raw = _a;
        if !(_raw isEqualType "") exitWith {};
        private _root = parseSimpleArray _raw;
        if !(_root isEqualType []) exitWith { diag_log text "[COMSPEC SSE] Identité : paquet Athena illisible."; };
        // Fiche en paires ou dans l'ordre des champs -> HashMap.
        private _rec = {
            params ["_r", "_fields"];
            if !(_r isEqualType []) exitWith { createHashMap };
            if (_r isNotEqualTo [] && {(_r select 0) isEqualType []} && {count (_r select 0) == 2} && {((_r select 0) select 0) isEqualType ""}) exitWith { createHashMapFromArray _r };
            private _m = createHashMap;
            { _m set [_x, _r param [_forEachIndex, ""]]; } forEach _fields;
            _m
        };
        private _top = [_root, ["version", "generated_at", "op", "countries", "identities", "documents", "watch"]] call _rec;
        private _reg = ["init"] call comspec_sse_fnc_idRegistry;
        private _nC = 0; private _nD = 0; private _nW = 0;
        {
            private _c = [_x, ["code", "name", "mrz_code", "number_format", "fictional"]] call _rec;
            private _code = _c getOrDefault ["code", ""];
            if (_code isNotEqualTo "") then {
                (_reg get "countries") set [_code, createHashMapFromArray [["code", _code], ["name", _c getOrDefault ["name", _code]], ["mrz", _c getOrDefault ["mrz_code", _code]], ["number_format", _c getOrDefault ["number_format", ""]], ["fictional", _c getOrDefault ["fictional", true]]]];
                _nC = _nC + 1;
            };
        } forEach (_top getOrDefault ["countries", []]);
        private _ids = createHashMap;
        {
            private _i = [_x, ["id", "person_id", "kind", "last_name", "first_name", "sex", "birth_date", "birth_place", "nationality", "arma_key", "profession"]] call _rec;
            _ids set [str (_i getOrDefault ["id", ""]), _i];
        } forEach (_top getOrDefault ["identities", []]);
        private _byKey = _reg getOrDefault ["athenaByKey", createHashMap];
        _reg set ["athenaByKey", _byKey];
        {
            private _d = [_x, ["id", "identity_id", "holder_person_id", "type", "country", "number", "issued_on", "expires_on", "authority", "chip", "origin", "integrity", "validity", "report", "forgery_kind", "forgery_quality"]] call _rec;
            private _idn = _ids getOrDefault [str (_d getOrDefault ["identity_id", ""]), createHashMap];
            _d set ["identity", _idn];
            private _k = format ["%1|%2|%3", _d getOrDefault ["country", ""], _d getOrDefault ["type", ""], _d getOrDefault ["number", ""]];
            (_reg get "athena") set [_k, _d];
            // Base locale (N3 hors ligne) : le document est connu, à ce nom, avec son signalement. Un faux
            // (origin forged) est inconnu des fichiers, comme sur le site ; une couverture est en règle.
            private _rep = ["stolen", "lost"] find (_d getOrDefault ["report", ""]);
            _rep = ["", "vole", "perdu"] select (_rep + 1);
            if ((_d getOrDefault ["validity", ""]) in ["revoked", "suspended"]) then { _rep = "revoque"; };
            if ((_d getOrDefault ["origin", ""]) isNotEqualTo "forged") then {
                (_reg get "issued") set [_k, ["", _idn getOrDefault ["last_name", ""], _idn getOrDefault ["first_name", ""], _rep]];
            };
            // Personne de la mission reliée par sa clé biométrique : ses papiers seront ceux d'Athena.
            private _ak = _idn getOrDefault ["arma_key", ""];
            if (_ak isEqualType "" && {_ak isNotEqualTo ""}) then { private _l = _byKey getOrDefault [_ak, []]; _l pushBack _k; _byKey set [_ak, _l]; };
            _nD = _nD + 1;
        } forEach (_top getOrDefault ["documents", []]);
        {
            private _w = [_x, ["ref", "last_name", "first_name", "alias", "level", "person_id"]] call _rec;
            (_reg get "watch") pushBack [_w getOrDefault ["last_name", ""], _w getOrDefault ["first_name", ""], _w getOrDefault ["alias", ""], _w getOrDefault ["level", "surveillance"], _w getOrDefault ["ref", ""]];
            _nW = _nW + 1;
        } forEach (_top getOrDefault ["watch", []]);
        // Papiers révoqués depuis le dernier paquet (paquet partiel, « since ») : signalés révoqués dans la base locale.
        private _revoked = (_top getOrDefault ["revoked", []]) apply { str _x };
        if (_revoked isNotEqualTo []) then {
            {
                if ((str (_y getOrDefault ["id", ""])) in _revoked) then {
                    _y set ["validity", "revoked"];
                    private _b = (_reg get "issued") getOrDefault [_x, []];
                    if (_b isNotEqualTo []) then { _b set [3, "revoque"]; };
                };
            } forEach (_reg get "athena");
        };
        diag_log text format ["[COMSPEC SSE] Identité : paquet Athena chargé (%1 pays, %2 documents, %3 avis).", _nC, _nD, _nW];
    };
    case "load": {
        if (!isServer || {!(missionNamespace getVariable ["comspec_sse_id_athenaPack", true])}) exitWith {};
        [] spawn {
            if (["has"] call comspec_sse_fnc_idAthenaPack) exitWith {
                private _raw = ["fetch"] call comspec_sse_fnc_idAthenaPack;
                if (_raw isNotEqualTo "") then { ["apply", _raw] call comspec_sse_fnc_idAthenaPack; };
            };
            // Serveur dédié sans liaison : le premier Zeus dont la DLL connaît le paquet le télécharge pour lui.
            private _zeus = (allPlayers select { !isNull (getAssignedCuratorLogic _x) && {(owner _x) isNotEqualTo clientOwner} }) param [0, objNull];
            if (!isNull _zeus) exitWith { ["packFetch", []] remoteExecCall ["comspec_sse_fnc_idClient", owner _zeus]; };
            // Pas encore de Zeus connecté : nouvel essai dans une minute (5 fois au plus).
            private _n = (missionNamespace getVariable ["comspec_sse_idPackTries", 0]) + 1;
            missionNamespace setVariable ["comspec_sse_idPackTries", _n];
            if (_n < 5) then { [{ ["load"] call comspec_sse_fnc_idAthenaPack; }, [], 60] call CBA_fnc_waitAndExecute; };
        };
    };
    case "merge": {
        _a params [["_res", createHashMap], ["_raw", ""]];
        if !(_raw isEqualType "") then { _raw = str _raw; };
        private _lines = _res get "lines";
        private _codes = _res get "codes";
        if ((_raw select [0, 3]) isNotEqualTo "OK|") exitWith { _lines pushBack "Athena injoignable : base locale seule."; _codes pushBack "athena_offline"; };
        private _arr = parseSimpleArray (_raw select [3]);
        if !(_arr isEqualType []) exitWith { _lines pushBack "Athena : réponse illisible, base locale seule."; };
        _arr params [["_found", false], ["_v", "inconnu"], ["_ref", ""], ["_lvl", ""], ["_flags", []], ["_upd", ""], ["_holder", []]];
        // Titulaire inscrit (seulement si le nom ne correspond pas et que le titulaire est sur une liste).
        if (_holder isEqualType [] && {(count _holder) >= 2}) then {
            _lines pushBack format ["Athena : ce document appartient à %1 %2%3.", toUpper (_holder select 0), _holder select 1,
                ["", format [" (%1)", _holder param [2, ""]]] select ((_holder param [2, ""]) isNotEqualTo "")];
        };
        if (_found isEqualType "") then { _found = (toLower _found) in ["1", "true", "found", "oui"]; };
        if (_found isEqualType 0) then { _found = _found > 0; };
        private _rank = ["na", "ok", "doubt", "fail", "alert"];
        private _worse = { params ["_s", "_vv"]; if ((_rank find _s) > (_rank find (_res get "state"))) then { _res set ["state", _s]; _res set ["verdict", _vv]; }; };
        if (_flags isEqualType []) then { { _codes pushBack ("athena_" + str _x); } forEach _flags; };
        // Papier inconnu d'Athena mais nom déclaré sur une liste : l'avis compte quand même (verdict signale / recherche / hvt).
        if (!_found && {_v in ["", "ras"]}) then { _v = "inconnu"; };
        if (_v isEqualTo "inconnu") exitWith {
            // Document généré en mission : inconnu d'Athena, c'est normal. Document du paquet : anomalie.
            if ((_res getOrDefault ["athenaId", ""]) isNotEqualTo "") then { _lines pushBack "Athena ne connaît plus ce document."; ["doubt", "doute"] call _worse; };
        };
        if (_v isEqualTo "ras") exitWith {
            if ("unknown_number" in _codes) then {
                _codes deleteAt (_codes find "unknown_number");
                private _li = _lines find "Numéro inconnu de la base du pays : jamais délivré.";
                if (_li >= 0) then { _lines deleteAt _li; };
                if (_codes findIf { !((_x select [0, 7]) isEqualTo "athena_") } < 0) then { _res set ["state", "ok"]; _res set ["verdict", "ras"]; };
            };
            _lines pushBack format ["Athena : document délivré, valide%1.", ["", format [" (réf. %1)", _ref]] select (_ref isNotEqualTo "")];
        };
        _lines pushBack format ["Athena : %1%2.", toUpper _v, ["", format [" (réf. %1)", _ref]] select (_ref isNotEqualTo "")];
        _codes pushBack ("athena_" + _v);
        switch (_v) do {
            case "signale": { ["doubt", "signale"] call _worse; };
            case "expire": { ["doubt", "expire"] call _worse; };
            case "recherche"; case "hvt"; case "vole"; case "revoque": { ["alert", _v] call _worse; };
            default { ["doubt", "doute"] call _worse; };
        };
    };
};
