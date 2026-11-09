/*
    Liaison Athena du contrôle d'identité (DLL COMSPEC Link). Sans les fonctions dans la DLL, tout marche hors ligne :
    papiers générés, base locale (N3), file d'attente des transmissions.
    ["has"]                -> Boolean : la DLL connaît les fonctions d'identité (COMSPEC Link ≥ 2.0.118 :
                              GetAtakFeatures ; sinon sonde GetIdentityPackChunk « -1 », locale ; « ERR|unknown » = absente)
    ["fetch"]              -> texte du paquet ("" si échec) : GetIdentityPack [carte, opération, depuis] = OK|n|horodatage,
                              puis GetIdentityPackChunk [i] = OK|morceau i, recollés (contexte planifié : spawn)
    ["apply", texte]       SERVEUR : paquet -> registre (pays, documents délivrés, identités, avis de recherche) ; paquet
                              identique au dernier appliqué (empreinte reg « packFp », date de génération exclue) : ignoré.
                              Appliqué : évènement CBA global comspec_sse_idCoverDirty (fiches Ma couverture périmées)
    ["load"]               SERVEUR : charge le paquet au début de la mission (DLL du serveur, sinon celle d'un Zeus)
    ["merge", [N3, brut]]  JOUEUR : réponse QueryIdDocument = OK|[trouvé, verdict, réf, niveau, drapeaux, mis à jour]
                              ajoutée au résultat local du niveau N3
    Paquet (COMSPEC Link 2.0.118, CONTRACT-CHANGES) : JSON converti pour parseSimpleArray, objet = paires
    [["clé", valeur]…], null = "" ; racine version, generated_at, map, op, (since), countries, identities, documents,
    watch et kits (1re page seulement), revoked (ids révoqués depuis « since »). Ordre fixe des champs aussi accepté.
    Pays : code, name, mrz_code, number_format, fictional, puis name_en, authority, theme, languages, doc_types, colors,
    active (pays archivés compris : plus de papiers générés, ceux déjà émis restent lisibles).
    Kits : uid (Steam ID du compte Athena), user_id, scope, documents (ids des papiers du paquet) ; table remplacée à
    chaque paquet, puis papiers remis aux joueurs déjà connectés (fn_idKit).
    Avis (watch) : en plus du contrat, game_ref (BOLO / HVT nés en jeu), bio_key, known_docs, status : un avis déjà connu
    (même réf., même BOLO) n'est pas doublé ; un avis avec visage ou papiers connus redevient un BOLO (checkpoints).
    Lot 6 (atelier Athena, rétrocompatible : blocs absents = rien) :
      briefs     uid, user_id, scope, identity_id, title, cover_name, biography, legend, contacts, instructions, phone,
                 address : brief RP du joueur (légende fn_idLegend, app Ma couverture) ; identité de couverture =
                 infiltration ouverte (fn_idCover « auto »). Ajouts facultatifs : legend_free, employer, travel, family,
                 city, profession (rubriques du texte de légende), updated_at (date montrée au joueur)
      population identity_id, side (civ | west | east | guer), category (civilian | military | suspect | hvt), faction,
                 zone, language, documents : identités préparées remises aux IA à leur premier contrôle (fn_idRegistry)
      wanted     ref (OPW-n), identity_id, person_id, last_name, first_name, alias, level (surveillance | recherche |
                 hvt), sides (camps qui y ont accès, vide = tous), zone, documents (ids), has_photo : recherchés de
                 l'opération, avis locaux (N3 hors ligne, checkpoints) limités à ces camps ; papiers connus = BOLO
    Dans « settings » (comme ai_profiles) : loadouts { kits [{id, name, loadout (texte JSON du getUnitLoadout)}],
      players [{uid, kits [ids], default}] } : kits du configurateur d'équipement, remis au registre de l'addon
      loadout (fn_loadoutRegistry, caisses de kits) s'il est chargé.
*/
params [["_op", ""], ["_a", []]];
switch (_op) do {
    case "has": {
        private _have = missionNamespace getVariable "comspec_sse_idDll";
        if (!isNil "_have") exitWith { _have };
        private _call = {
            if (!isNil "comspec_overwatch_connect_fnc_uplinkOff" && {[] call comspec_overwatch_connect_fnc_uplinkOff}) exitWith { "ERR|uplink_off" };
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
        private _top = [_root, ["version", "generated_at", "map", "op", "countries", "identities", "documents", "watch", "kits"]] call _rec;
        private _reg = ["init"] call comspec_sse_fnc_idRegistry;
        // Paquet identique au dernier appliqué (hors date de génération et « since », qui changent à chaque lecture) :
        // rien à refaire. Avant, chaque rechargement (Demander la mise à jour, fenêtre IA du Zeus) reprenait toutes les
        // tables, remettait le kit et rouvrait les couvertures de tous les joueurs, même sans aucun changement sur Athena.
        private _fpKeys = (keys _top) - ["generated_at", "since"];
        _fpKeys sort true;
        private _fp = hashValue str (_fpKeys apply { [_x, _top get _x] });
        if (_fp isEqualTo (_reg getOrDefault ["packFp", ""])) exitWith {
            diag_log text "[COMSPEC SSE] Identité : paquet Athena inchangé depuis le dernier chargement, rien à refaire.";
        };
        _reg set ["packFp", _fp];
        // Date du paquet, montrée au joueur dont le kit n'a pas de brief daté (app Ma couverture).
        _reg set ["packAt", _top getOrDefault ["generated_at", ""]];
        // Réglage de la communauté (Bureau SSE d'Athena) : l'emporte sur le réglage CBA, diffusé aux postes des contrôleurs.
        private _settings = [_top getOrDefault ["settings", []], ["auto_transmit"]] call _rec;
        if ("auto_transmit" in _settings) then {
            private _at = _settings get "auto_transmit";
            missionNamespace setVariable ["comspec_sse_id_autoTransmitCommunity", _at isEqualTo true || {_at isEqualTo 1}, true];
        };
        // Profils d'IA et éléments gardés : dans « settings » (la DLL publiée le transmet sans connaître ces blocs).
        if ("ai_profiles" in _settings) then { ["ingest", [_settings get "ai_profiles", _settings getOrDefault ["ai_placements", []]]] call comspec_sse_fnc_idAiConfig; };
        // Kits du configurateur d'équipement (addon loadout, peut être absent) : registre serveur des caisses de kits.
        if ("loadouts" in _settings && {!isNil "comspec_sse_fnc_loadoutRegistry"}) then { ["ingest", [_settings get "loadouts"]] call comspec_sse_fnc_loadoutRegistry; };
        private _nC = 0; private _nD = 0; private _nW = 0;
        {
            private _c = [_x, ["code", "name", "mrz_code", "number_format", "fictional", "name_en", "authority", "theme", "languages", "doc_types", "colors", "active", "real_sim", "zone", "name_native", "validity"]] call _rec;
            private _code = _c getOrDefault ["code", ""];
            if (_code isNotEqualTo "") then {
                private _active = _c getOrDefault ["active", true];
                if (_active isEqualType "") then { _active = (toLower _active) in ["", "1", "true"]; };
                if (_active isEqualType 0) then { _active = _active > 0; };
                private _arr = { if (_this isEqualType []) then { _this } else { [] } };
                private _bool = { if (_this isEqualType "") exitWith { (toLower _this) in ["1", "true"] }; if (_this isEqualType 0) exitWith { _this > 0 }; _this isEqualTo true };
                // Pays réel stylisé (univers réaliste-simulation choisi sur Athena) : zone du gabarit générique, durées par type.
                private _val = _c getOrDefault ["validity", ""];
                if (_val isEqualType []) then { _val = createHashMapFromArray (_val select { _x isEqualType [] && {count _x == 2} }); };
                if (_val isEqualType createHashMap && {(count _val) isEqualTo 0}) then { _val = ""; };
                (_reg get "countries") set [_code, createHashMapFromArray [
                    ["code", _code], ["name", _c getOrDefault ["name", _code]], ["nameEn", _c getOrDefault ["name_en", ""]],
                    ["mrz", _c getOrDefault ["mrz_code", _code]], ["number_format", _c getOrDefault ["number_format", ""]],
                    ["fictional", _c getOrDefault ["fictional", true]], ["authority", _c getOrDefault ["authority", ""]],
                    ["theme", _c getOrDefault ["theme", ""]], ["languages", (_c getOrDefault ["languages", []]) call _arr],
                    ["docTypes", (_c getOrDefault ["doc_types", []]) call _arr], ["colors", (_c getOrDefault ["colors", []]) call _arr],
                    ["active", _active], ["realSim", (_c getOrDefault ["real_sim", false]) call _bool], ["zone", _c getOrDefault ["zone", ""]],
                    ["nameNative", _c getOrDefault ["name_native", ""]], ["validity", _val]
                ]];
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
        private _byId = _reg getOrDefault ["athenaById", createHashMap];
        _reg set ["athenaById", _byId];
        {
            private _d = [_x, ["id", "identity_id", "holder_person_id", "type", "country", "number", "issued_on", "expires_on", "authority", "chip", "origin", "integrity", "validity", "report", "forgery_kind", "forgery_quality"]] call _rec;
            private _idn = _ids getOrDefault [str (_d getOrDefault ["identity_id", ""]), createHashMap];
            _d set ["identity", _idn];
            private _k = format ["%1|%2|%3", _d getOrDefault ["country", ""], _d getOrDefault ["type", ""], _d getOrDefault ["number", ""]];
            (_reg get "athena") set [_k, _d];
            _byId set [str (_d getOrDefault ["id", ""]), _k];
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
        private _bolos = _reg getOrDefault ["bolos", createHashMap];
        _reg set ["bolos", _bolos];
        private _localRefs = (values _bolos) apply { _x getOrDefault ["gameRef", ""] };
        private _boloAdded = false;
        {
            private _w = [_x, ["ref", "last_name", "first_name", "alias", "level", "person_id", "game_ref", "bio_key", "known_docs", "status"]] call _rec;
            private _ref = _w getOrDefault ["ref", ""];
            private _gr = _w getOrDefault ["game_ref", ""];
            // Déjà connu (paquet rechargé, BOLO de cette partie remonté puis revenu) : pas de doublon.
            if (((_reg get "watch") findIf { (_x param [4, ""]) isEqualTo _ref }) >= 0 || {_gr isNotEqualTo "" && {_gr in _localRefs}}) then { continue; };
            (_reg get "watch") pushBack [_w getOrDefault ["last_name", ""], _w getOrDefault ["first_name", ""], _w getOrDefault ["alias", ""], _w getOrDefault ["level", "surveillance"], _ref];
            _nW = _nW + 1;
            // Avis avec visage ou papiers connus (BOLO / HVT d'une partie précédente) : redevient un BOLO.
            private _kd = _w getOrDefault ["known_docs", []];
            if !(_kd isEqualType []) then { _kd = []; };
            private _bk = _w getOrDefault ["bio_key", ""];
            if !(_bk isEqualType "") then { _bk = ""; };
            if (_kd isNotEqualTo [] || {_bk isNotEqualTo ""}) then {
                private _lv = _w getOrDefault ["level", "surveillance"];
                _bolos set [_ref, createHashMapFromArray [
                    ["ref", _ref], ["last", _w getOrDefault ["last_name", ""]], ["first", _w getOrDefault ["first_name", ""]], ["alias", _w getOrDefault ["alias", ""]],
                    ["level", ["surveillance", ["recherche", "hvt"] select (_lv isEqualTo "hvt")] select (_lv in ["prioritaire", "recherche", "hvt"])],
                    ["docs", _kd apply { toUpper ([str _x, _x] select (_x isEqualType "")) }],
                    ["notes", "Avis Athena"], ["kind", "BOLO"], ["key", _bk], ["clock", ""], ["gameRef", _gr]
                ]];
                _boloAdded = true;
            };
        } forEach (_top getOrDefault ["watch", []]);
        if (_boloAdded) then { ["boloList"] call comspec_sse_fnc_idHvt; };
        // Lot 6 : briefs RP des joueurs (Steam ID), population des IA, recherchés de l'opération.
        private _str = { if (_this isEqualType "") then { _this } else { str _this } };
        private _briefs = createHashMap;
        {
            private _b = [_x, ["uid", "user_id", "scope", "identity_id", "title", "cover_name", "biography", "legend", "contacts", "instructions", "phone", "address"]] call _rec;
            private _uid = (_b getOrDefault ["uid", ""]) call _str;
            if (_uid isNotEqualTo "") then { _briefs set [_uid, _b]; };
        } forEach (_top getOrDefault ["briefs", []]);
        if ("briefs" in _top) then { _reg set ["briefs", _briefs]; _reg set ["legends", createHashMap]; };
        private _pop = [];
        {
            private _p = [_x, ["identity_id", "side", "category", "faction", "zone", "language", "documents"]] call _rec;
            private _pd = _p getOrDefault ["documents", []];
            if (_pd isEqualType [] && {_pd isNotEqualTo []}) then { _p set ["documents", _pd apply { _x call _str }]; _pop pushBack _p; };
        } forEach (_top getOrDefault ["population", []]);
        if ("population" in _top) then { _reg set ["population", _pop]; };
        // Recherchés de l'opération : table remplacée à chaque paquet (avis « OPW- » retirés puis remis).
        if ("wanted" in _top) then {
            _reg set ["watch", (_reg get "watch") select { ((_x param [4, ""]) select [0, 4]) isNotEqualTo "OPW-" }];
            { if ((_x select [0, 4]) isEqualTo "OPW-") then { _bolos deleteAt _x; }; } forEach (keys _bolos);
        };
        private _byIdW = _reg getOrDefault ["athenaById", createHashMap];
        {
            private _w = [_x, ["ref", "identity_id", "person_id", "last_name", "first_name", "alias", "level", "sides", "zone", "documents", "has_photo"]] call _rec;
            private _i = _ids getOrDefault [(_w getOrDefault ["identity_id", ""]) call _str, createHashMap];
            private _l = _w getOrDefault ["last_name", ""];
            private _f = _w getOrDefault ["first_name", ""];
            if (_l isEqualTo "") then { _l = _i getOrDefault ["last_name", ""]; _f = _i getOrDefault ["first_name", ""]; };
            private _al = _w getOrDefault ["alias", ""];
            private _lv = _w getOrDefault ["level", "recherche"];
            if !(_lv in ["surveillance", "recherche", "hvt"]) then { _lv = "recherche"; };
            private _sides = _w getOrDefault ["sides", []];
            if !(_sides isEqualType []) then { _sides = []; };
            _sides = (_sides apply { toLower (_x call _str) }) select { _x in ["civ", "west", "east", "guer"] };
            private _ref = (_w getOrDefault ["ref", ""]) call _str;
            if (_ref isEqualTo "" || {_l isEqualTo "" && {_al isEqualTo ""}}) then { continue; };
            // Avis local limité aux camps autorisés (6e champ : camps, vide = tous).
            // Nom vide : avis sur l'alias seul, jamais recopié dans le champ nom (fn_idEvaluate le compare à part).
            (_reg get "watch") pushBack [_l, _f, _al, _lv, _ref, _sides];
            _nW = _nW + 1;
            // Papiers émis connus (ids du paquet ; absent = ignoré) : numéros à surveiller.
            private _wd = _w getOrDefault ["documents", []];
            if !(_wd isEqualType []) then { _wd = []; };
            private _nums = [];
            { private _k = _byIdW getOrDefault [_x call _str, ""]; if (_k isNotEqualTo "") then { _nums pushBackUnique toUpper ((_k splitString "|") param [2, ""]); }; } forEach _wd;
            if (_nums isNotEqualTo []) then {
                _bolos set [_ref, createHashMapFromArray [
                    ["ref", _ref], ["last", _l], ["first", _f], ["alias", _al], ["level", _lv], ["docs", _nums - [""]],
                    ["notes", "Recherché de l'opération (Athena)"], ["kind", "OPW"], ["key", ""], ["clock", ""], ["gameRef", ""], ["sides", _sides]
                ]];
                _boloAdded = true;
            };
        } forEach (_top getOrDefault ["wanted", []]);
        if (_boloAdded) then { ["boloList"] call comspec_sse_fnc_idHvt; };
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
        // Kits des joueurs (Steam ID -> papiers) : table remplacée, puis remis aux joueurs déjà là.
        private _kits = createHashMap;
        {
            private _kt = [_x, ["uid", "user_id", "scope", "documents"]] call _rec;
            private _uid = _kt getOrDefault ["uid", ""];
            if !(_uid isEqualType "") then { _uid = str _uid; };
            private _list = _kt getOrDefault ["documents", []];
            if (_uid isNotEqualTo "" && {_list isEqualType []} && {_list isNotEqualTo []}) then { _kits set [_uid, _list apply { [str _x, _x] select (_x isEqualType "") }]; };
        } forEach (_top getOrDefault ["kits", []]);
        if ("kits" in _top) then { _reg set ["kits", _kits]; };
        diag_log text format ["[COMSPEC SSE] Identité : paquet Athena chargé (%1 pays, %2 documents, %3 avis, %4 kits, %5 briefs, %6 identités de population).", _nC, _nD, _nW, count _kits, count _briefs, count _pop];
        // Fiches « Ma couverture » en cache sur les postes : périmées (liaison Athena, papiers, brief). Une page ouverte se
        // relit après la remise du kit ; les autres attendent leur prochaine ouverture (aucune relecture en boucle).
        [{ ["comspec_sse_idCoverDirty", []] call CBA_fnc_globalEvent; }, [], 6] call CBA_fnc_waitAndExecute;
        // Brief de couverture d'un joueur déjà là : son infiltration s'ouvre (après la remise du kit).
        if ((count _briefs) > 0) then {
            [{ { ["auto", [_x]] call comspec_sse_fnc_idCover; } forEach (allPlayers select { alive _x && {!(_x isKindOf "HeadlessClient_F")} }); }, [], 4] call CBA_fnc_waitAndExecute;
        };
        // Éléments gardés : remis en place une fois les unités de la mission apparues (une seule fois par élément).
        [{ ["restore"] call comspec_sse_fnc_idAiConfig; }, [], 5] call CBA_fnc_waitAndExecute;
        if ((count _kits) > 0) then {
            [{ { [_x] call comspec_sse_fnc_idKit; } forEach (allPlayers select { alive _x && {!(_x isKindOf "HeadlessClient_F")} }); }, [], 1] call CBA_fnc_waitAndExecute;
        };
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
