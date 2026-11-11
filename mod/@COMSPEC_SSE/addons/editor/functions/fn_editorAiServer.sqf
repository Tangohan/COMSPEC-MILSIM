/*
    Fenêtre « IA de la zone » (SERVEUR) : tout ce qui lit ou change l'état d'une IA passe ici, demandeur vérifié
    (fn_isAuthority : Zeus ou administrateur, poste de l'unité qu'il dit être). L'état lui-même reste dans l'addon
    identity (fn_idAiConfig, registre) : cette fonction n'appelle que ses fonctions publiques.
    [_op, [moi, …]] remoteExecCall ["comspec_sse_fnc_editorAiServer", 2] ; réponses : ["aiReply", [genre, …]] au poste demandeur
      scan       [moi, position, rayon, unités]   -> rows [lignes, profils Athena, état des profils, gardés]
      status     [moi]                            -> status [nombre de profils, date du paquet, liaison du serveur]
      docs       [moi, unité]                     -> docs [netId, [[id, type, numéro, nom, prénom, nature, pays]…]]
      docAdd     [moi, unité, type] · docMark [moi, unité, docId, nature]   -> docs
      docView    [moi, unité, docId]              -> docView [docId, document imprimé] (aperçu « Ce que verra le joueur » :
                                                     les champs que reçoit un joueur qui le tient, jamais la vérité)
      apply      [moi, unités, réglages, garder]  -> applied [n, n gardées] ; réglage « country » (pays des papiers, « auto »
                                                     ou un code) posé sur l'unité (COMSPEC_ID_Country) avant que ses papiers
                                                     soient refaits, et gardé avec elle (+ athenaPost [[charge, id local]…] si le
                                                     serveur n'a pas la DLL : le poste du Zeus envoie, puis athenaDone)
      athenaDone [moi, id local, id Athena]       la copie sur Athena est faite (serveur ou poste du Zeus)
      unkeep     [moi, id] · restoreOne [moi, id] -> note [clé, paramètres, genre]
      restoreLocal                                serveur seulement (XEH_postInitServer) : remise en place des gardés locaux
    Gardés pour les prochaines parties : enregistrés sur ce serveur (profileNamespace « comspec_sse_editorAiKept », par
    carte et opération, identifiants « L<n> ») ET sur Athena (SubmitIdCheck ai_keep, par la DLL du serveur, sinon celle
    du poste du Zeus). Athena renvoie les siens dans le paquet (« ai_placements », identifiants numériques) ; une copie
    Athena d'un gardé local porte « local_id » dans sa configuration : affichée une fois, remise une fois (aiKeptDone).
    Retrouver une IA : nom de variable Eden, sinon même classe à moins de 20 m de la position gardée.
*/
params [["_op", "", [""]], ["_a", [], [[]]]];
if (!isServer) exitWith { false };
if (!(missionNamespace getVariable ["comspec_sse_id_enabled", true])) exitWith { false };
private _reg = ["init"] call comspec_sse_fnc_idRegistry;
private _str = { if (_this isEqualType "") then { _this } else { str _this } };
private _toMap = {
    if (_this isEqualType createHashMap) exitWith { +_this };
    if (_this isEqualType [] && {_this isNotEqualTo []} && {(_this select 0) isEqualType []}) exitWith { createHashMapFromArray _this };
    createHashMap
};
private _stamp = {
    systemTime params ["", "_m", "_dd", "_h", "_mi"];
    format ["%1%2/%3%4 %5%6:%7%8", ["", "0"] select (_dd < 10), _dd, ["", "0"] select (_m < 10), _m, ["", "0"] select (_h < 10), _h, ["", "0"] select (_mi < 10), _mi]
};

// --- Gardés locaux (profil du serveur) : liste de paires, jamais de HashMap enregistrée.
private _storeKey = format ["%1|%2", worldName, missionNamespace getVariable ["comspec_sse_id_opKey", ""]];
private _loadAll = {
    private _raw = profileNamespace getVariable ["comspec_sse_editorAiKept", []];
    if !(_raw isEqualType []) then { _raw = []; };
    createHashMapFromArray (_raw select { _x isEqualType [] && {(count _x) isEqualTo 2} && {(_x select 0) isEqualType ""} })
};
private _store = { ((call _loadAll) getOrDefault [_storeKey, []]) apply { _x call _toMap } };
private _saveStore = {
    params ["_list"];
    private _all = call _loadAll;
    _all set [_storeKey, _list apply { toArray _x }];
    profileNamespace setVariable ["comspec_sse_editorAiKept", toArray _all];
    saveProfileNamespace;
};
private _athenaList = { (_reg getOrDefault ["aiPlacements", []]) apply { _x call _toMap } };
// Tous les gardés de cette carte : [id, libellé, source (athena | server | both), date, entrée].
private _keptAll = {
    private _local = call _store;
    private _out = [];
    private _twins = [];
    {
        private _ath = _x;
        private _aid = (_ath getOrDefault ["id", ""]) call _str;
        private _lid = (((_ath getOrDefault ["config", []]) call _toMap) getOrDefault ["local_id", ""]) call _str;
        private _li = if (_lid isEqualTo "") then { -1 } else { _local findIf { ((_x getOrDefault ["id", ""]) call _str) isEqualTo _lid } };
        if (_li >= 0) then { _twins pushBack _lid; };
        _out pushBack [_aid, (_ath getOrDefault ["label", _aid]) call _str, ["athena", "both"] select (_li >= 0), "", _ath];
    } forEach (call _athenaList);
    {
        if !(((_x getOrDefault ["id", ""]) call _str) in _twins) then {
            _out pushBack [(_x getOrDefault ["id", ""]) call _str, (_x getOrDefault ["label", ""]) call _str, "server", (_x getOrDefault ["at", ""]) call _str, _x];
        };
    } forEach _local;
    _out
};
private _markDone = {
    params ["_ids"];
    private _done = _reg getOrDefault ["aiKeptDone", createHashMap];
    _reg set ["aiKeptDone", _done];
    { private _id = _x call _str; if (_id isNotEqualTo "") then { _done set [_id, true]; }; } forEach _ids;
};
private _unmarkDone = {
    params ["_ids"];
    private _done = _reg getOrDefault ["aiKeptDone", createHashMap];
    { _done deleteAt (_x call _str); } forEach _ids;
};
// Unité d'un enregistrement : nom de variable, sinon même classe à moins de 20 m.
private _find = {
    params ["_k", ["_used", []]];
    private _u = objNull;
    private _var = _k getOrDefault ["var_name", ""];
    if (_var isEqualType "" && {_var isNotEqualTo ""}) then { _u = missionNamespace getVariable [_var, objNull]; };
    if !(_u isEqualType objNull) then { _u = objNull; };
    if (isNull _u || {!alive _u}) then {
        private _pos = [parseNumber ((_k getOrDefault ["x", 0]) call _str), parseNumber ((_k getOrDefault ["y", 0]) call _str), 0];
        private _cls = (_k getOrDefault ["class_name", ""]) call _str;
        if (_cls isEqualTo "") then { _cls = "CAManBase"; };
        _u = ((nearestObjects [_pos, [_cls], 20]) select { alive _x && {!isPlayer _x} && {!(_x in _used)} }) param [0, objNull];
    };
    _u
};
// Pays des papiers (fenêtre « IA de la zone », onglet Identité) : variable lue par fn_idRegistry « ensure ».
private _setCountry = {
    params ["_units", "_c"];
    if !(_c isEqualType "") exitWith {};
    if (_c isEqualTo "") exitWith {};
    if (_c isNotEqualTo "auto" && {!(_c in ([] call comspec_sse_fnc_idCountries))}) exitWith {};
    { _x setVariable ["COMSPEC_ID_Country", _c]; } forEach _units;
};
private _restoreEntry = {
    params ["_k", ["_used", []]];
    private _u = [_k, _used] call _find;
    if (isNull _u) exitWith { objNull };
    private _cfg = (_k getOrDefault ["config", []]) call _toMap;
    [[_u], (_cfg getOrDefault ["country", ""]) call _str] call _setCountry;
    _cfg set ["profile", (_k getOrDefault ["profile_id", ""]) call _str];
    if ((_cfg get "profile") in ["0", ""]) then { _cfg set ["profile", ""]; };
    ["apply", [[_u], _cfg]] call comspec_sse_fnc_idAiConfig;
    _u
};

// --- Démarrage : gardés locaux remis en place (ceux d'Athena : fn_idAiConfig « restore », après le paquet).
if (_op isEqualTo "restoreLocal") exitWith {
    if (isRemoteExecuted) exitWith { false };
    private _used = [];
    private _missing = [];
    private _done = _reg getOrDefault ["aiKeptDone", createHashMap];
    _reg set ["aiKeptDone", _done];
    {
        private _k = _x;
        private _id = (_k getOrDefault ["id", ""]) call _str;
        private _aid = (_k getOrDefault ["athena_id", ""]) call _str;
        if (_id isEqualTo "" || {_done getOrDefault [_id, false]} || {_aid isNotEqualTo "" && {_done getOrDefault [_aid, false]}}) then { continue };
        private _u = [_k, _used] call _restoreEntry;
        [[_id, _aid]] call _markDone;
        if (isNull _u) then { _missing pushBack (_k getOrDefault ["label", _id]); } else { _used pushBack _u; };
    } forEach (call _store);
    if (_missing isNotEqualTo []) then { diag_log text format ["[COMSPEC SSE] Éditeur : IA gardées sur ce serveur non retrouvées : %1.", _missing joinString ", "]; };
    true
};

// --- Demandes du Zeus.
_a params [["_me", objNull, [objNull]]];
if (isNull _me) exitWith { false };
private _owner = if (isRemoteExecuted) then { remoteExecutedOwner } else { clientOwner };
if (isMultiplayer && {isRemoteExecuted} && {(owner _me) isNotEqualTo _owner}) exitWith { false };
private _reply = {
    params ["_kind", ["_payload", []]];
    private _msg = ["aiReply", [_kind] + _payload];
    if (_owner isEqualTo clientOwner || {!isMultiplayer}) then { _msg call comspec_sse_fnc_editorAi; } else { _msg remoteExecCall ["comspec_sse_fnc_editorAi", _owner]; };
};
if !([_me] call comspec_sse_fnc_isAuthority) exitWith {
    diag_log text format ["[COMSPEC SSE] Éditeur : « %1 » (IA de la zone) refusé au poste %2.", _op, _owner];
    ["note", ["Refused", [], "WARNING"]] call _reply;
    false
};
private _note = { params ["_key", ["_params", []], ["_kind", "INFO"]]; ["note", [_key, _params, _kind]] call _reply; };

// Copie sur Athena (SubmitIdCheck) : DLL du serveur dans un contexte planifié ; sans DLL ici, le poste du Zeus s'en charge.
private _postHere = {
    params ["_payload"];
    _payload set ["map", missionNamespace getVariable ["COMSPEC_MapId", 1]];
    _payload set ["op", missionNamespace getVariable ["comspec_sse_id_opKey", ""]];
    private _json = [_payload] call comspec_sse_fnc_toJsonApprox;
    private _parts = [];
    for "_i" from 0 to ((count _json) - 1) step 4000 do { _parts pushBack (_json select [_i, 4000]); };
    private _raw = ["SubmitIdCheck", _parts] call comspec_sse_fnc_extensionCall;
    if (_raw isEqualType []) then { _raw = _raw param [0, ""]; };
    if !(_raw isEqualType "") then { _raw = str _raw; };
    _raw
};
private _route = {
    // _jobs : [[charge, id local]…]. Copie faite : athenaDone (id local, id Athena), comme depuis le poste du Zeus ; puis
    // un mot au Zeus (note), qui relit la liste des gardés.
    params ["_jobs"];
    if (_jobs isEqualTo []) exitWith {};
    if !(["has"] call comspec_sse_fnc_idAthenaPack) exitWith { ["athenaPost", [_jobs]] call _reply; };
    [_jobs, _me, _owner, _postHere] spawn {
        params ["_jobs", "_me", "_owner", "_postHere"];
        private _ok = 0;
        {
            _x params ["_payload", "_lid"];
            private _raw = [_payload] call _postHere;
            if ((_raw select [0, 3]) isEqualTo "OK|") then {
                _ok = _ok + 1;
                if (_lid isNotEqualTo "") then { ["athenaDone", [_me, _lid, (_raw splitString "|") param [1, ""]]] call comspec_sse_fnc_editorAiServer; };
            };
        } forEach _jobs;
        private _all = _ok isEqualTo (count _jobs);
        private _msg = ["aiReply", ["note", ["KeptAthenaKo", "KeptAthenaOk"] select _all, [], ["WARNING", "SUCCESS"] select _all]];
        if (_owner isEqualTo clientOwner || {!isMultiplayer}) then { _msg call comspec_sse_fnc_editorAi; } else { _msg remoteExecCall ["comspec_sse_fnc_editorAi", _owner]; };
    };
};
private _row = {
    params ["_u", ["_kept", []]];
    private _w = [_u] call comspec_sse_fnc_aiWho;
    private _keptId = "";
    { if (_keptId isEqualTo "" && {([_x select 4] call _find) isEqualTo _u}) then { _keptId = _x select 0; }; } forEach _kept;
    [netId _u, _w getOrDefault ["given", ""], _w getOrDefault ["true", ""], _w getOrDefault ["truth", "pending"], _w getOrDefault ["side", "civ"], _w getOrDefault ["role", "civilian"],
        _w getOrDefault ["profileRef", ""], _w getOrDefault ["profileId", ""], _w getOrDefault ["docs", 0], _u getVariable ["COMSPEC_ID_Behaviour", "auto"], groupId (group _u), name _u,
        _u getVariable ["COMSPEC_ID_Carry", "auto"], _u getVariable ["COMSPEC_ID_Kind", "civil"], _keptId, _u getVariable ["COMSPEC_ID_Country", "auto"]]
};
private _docsOf = {
    params ["_u"];
    (["docs", [_u]] call comspec_sse_fnc_idAiConfig) apply {
        private _d = ["doc", [_x select 0]] call comspec_sse_fnc_idRegistry;
        _x + [(_d getOrDefault ["country", ""]) call _str]
    }
};
private _packAt = {
    // Date du paquet (generated_at d'Athena : « AAAA-MM-JJTHH:MM… » ou « AAAA-MM-JJ HH:MM… ») -> « JJ/MM HH:MM ».
    private _s = (_reg getOrDefault ["packAt", ""]) call _str;
    if ((count _s) < 16) exitWith { _s };
    format ["%1/%2 %3", _s select [8, 2], _s select [5, 2], _s select [11, 5]]
};
private _status = { [count (["list"] call comspec_sse_fnc_idAiConfig), call _packAt, ["has"] call comspec_sse_fnc_idAthenaPack] };
private _units = { params ["_l"]; (_l select { _x isEqualType objNull && {!isNull _x} && {alive _x} && {!isPlayer _x} && {_x isKindOf "CAManBase"} }) select [0, 60] };

switch (_op) do {
    case "scan": {
        _a params ["", ["_pos", [], [[]]], ["_radius", 50, [0]], ["_list", [], [[]]]];
        if (_list isEqualTo [] && {_pos isNotEqualTo []}) then { _list = _pos nearEntities ["CAManBase", (_radius max 5) min 1000]; };
        private _kept = call _keptAll;
        private _rows = ([_list] call _units) apply { [_x, _kept] call _row };
        ["rows", [_rows, ["list"] call comspec_sse_fnc_idAiConfig, call _status, _kept apply { _x select [0, 4] }]] call _reply;
    };
    case "status": { ["status", [call _status]] call _reply; };
    case "docs": {
        _a params ["", ["_u", objNull, [objNull]]];
        if (isNull _u) exitWith {};
        ["docs", [netId _u, [_u] call _docsOf]] call _reply;
    };
    case "docAdd": {
        _a params ["", ["_u", objNull, [objNull]], ["_type", "id_card", [""]]];
        if (isNull _u) exitWith {};
        ["docAdd", [_u, _type]] call comspec_sse_fnc_idAiConfig;
        ["docs", [netId _u, [_u] call _docsOf]] call _reply;
    };
    case "docMark": {
        _a params ["", ["_u", objNull, [objNull]], ["_id", "", [""]], ["_kind", "civil", [""]]];
        if (isNull _u || {_id isEqualTo ""} || {!(_kind in ["civil", "forged"])}) exitWith {};
        ["docMark", [_u, _id, _kind]] call comspec_sse_fnc_idAiConfig;
        ["docs", [netId _u, [_u] call _docsOf]] call _reply;
    };
    case "docView": {
        // Aperçu du Zeus : le papier tel qu'imprimé (mêmes champs que _pub de fn_idServer), sans la vérité.
        _a params ["", ["_u", objNull, [objNull]], ["_id", "", [""]]];
        if (isNull _u || {_id isEqualTo ""}) exitWith {};
        private _dd = ["doc", [_id]] call comspec_sse_fnc_idRegistry;
        if ((count _dd) isEqualTo 0) exitWith {};
        private _c = ([] call comspec_sse_fnc_idCountries) getOrDefault [_dd get "country", createHashMap];
        private _p = createHashMap;
        { _p set [_x, _dd getOrDefault [_x, ""]]; } forEach ["id", "type", "country", "number", "last", "first", "sex", "dob", "pob", "nat", "profession", "issued", "expires", "authority", "address", "rank", "force"];
        { _p set [_x select 0, _dd getOrDefault [_x select 0, ""]]; } forEach ((["get", _dd getOrDefault ["type", ""]] call comspec_sse_fnc_idDocTypes) getOrDefault ["inputs", []]);
        _p set ["countryName", _c getOrDefault ["name", _dd get "country"]];
        _p set ["countryNameEn", _c getOrDefault ["nameEn", ""]];
        _p set ["zone", [_c getOrDefault ["zone", ""], ""] select !(_c getOrDefault ["realSim", false])];
        _p set ["mrz", [_dd] call comspec_sse_fnc_idMrz];
        private _f = _dd getOrDefault ["flaws", []];
        _p set ["holo", !("holo" in _f)];
        _p set ["font", "font" in _f];
        _p set ["photo", "photo" in _f];
        _p set ["photoSwap", (_dd getOrDefault ["forgeryKind", ""]) isEqualTo "photo_swap"];
        ["docView", [_id, _p]] call _reply;
    };
    case "apply": {
        _a params ["", ["_list", [], [[]]], ["_spec", createHashMap, [createHashMap]], ["_keep", false, [false]]];
        private _targets = [_list] call _units;
        if (_targets isEqualTo []) exitWith { ["NeedCheck", [], "WARNING"] call _note; };
        [_targets, (_spec getOrDefault ["country", ""]) call _str] call _setCountry;
        private _n = ["apply", [_targets, _spec]] call comspec_sse_fnc_idAiConfig;
        private _nk = 0;
        if (_keep) then {
            private _local = call _store;
            private _seq = profileNamespace getVariable ["comspec_sse_editorAiKeptSeq", 0];
            if !(_seq isEqualType 0) then { _seq = 0; };
            private _jobs = [];
            {
                private _u = _x;
                // Même IA déjà gardée sur ce serveur : l'enregistrement est remplacé.
                _local = _local select { !(([_x] call _find) isEqualTo _u) };
                _seq = _seq + 1;
                private _id = format ["L%1", _seq];
                private _cfg = [];
                { _cfg pushBack [_x, (_spec get _x) call _str]; } forEach (keys _spec);
                _cfg pushBack ["local_id", _id];
                // Identité unique (nom donné, naissance, pays) gardée avec elle : remise par fn_idAiConfig « apply ».
                if (_u getVariable ["COMSPEC_SSE_NameAuthored", false]) then {
                    private _idn = ["get", [_u]] call comspec_sse_fnc_idIdentityOf;
                    _cfg pushBack ["identity", str ([_idn getOrDefault ["first", ""], _idn getOrDefault ["last", ""], _u getVariable ["COMSPEC_SSE_Sex", ""],
                        _u getVariable ["COMSPEC_SSE_BirthDate", ""], _u getVariable ["COMSPEC_SSE_BirthPlace", ""], _u getVariable ["COMSPEC_SSE_Country", ""]])];
                };
                // Réponses écrites par le maître du jeu et ce que l'IA peut évoquer (ia_rp) : gardées avec elle, remises par
                // fn_idAiConfig « apply » (ici et depuis le paquet Athena, champ libre de la configuration).
                if (!isNil "comspec_sse_fnc_iarpProfile") then {
                    private _ia = ["keepOf", _u] call comspec_sse_fnc_iarpProfile;
                    if (_ia isNotEqualTo "") then { _cfg pushBack ["iarp_answers", _ia]; };
                };
                private _var = vehicleVarName _u;
                private _who = [_u] call comspec_sse_fnc_aiWho;
                private _shown = _who getOrDefault ["given", ""];
                if (_shown isEqualTo "") then { _shown = _who getOrDefault ["true", name _u]; };
                private _label = format ["%1%2 · %3", _shown, ["", format [" (%1)", _var]] select (_var isNotEqualTo ""), mapGridPosition _u];
                private _e = createHashMapFromArray [
                    ["id", _id], ["kind", "ai"], ["profile_id", (_spec getOrDefault ["profile", ""]) call _str], ["var_name", _var], ["class_name", typeOf _u],
                    ["x", (getPosATL _u) select 0], ["y", (getPosATL _u) select 1], ["label", _label], ["config", _cfg], ["athena_id", ""], ["at", call _stamp], ["by", name _me]
                ];
                _local pushBack _e;
                _nk = _nk + 1;
                _jobs pushBack [createHashMapFromArray [["kind", "ai_keep"], ["place", createHashMapFromArray [
                    ["kind", "ai"], ["profile_id", _e get "profile_id"], ["var_name", _var], ["class_name", typeOf _u], ["x", _e get "x"], ["y", _e get "y"], ["label", _label], ["config", createHashMapFromArray _cfg]
                ]]], _id];
            } forEach _targets;
            profileNamespace setVariable ["comspec_sse_editorAiKeptSeq", _seq];
            [_local] call _saveStore;
            // Déjà remises en place pour cette partie : pas de doublon au prochain paquet.
            [_local apply { _x get "id" }] call _markDone;
            [_jobs] call _route;
        };
        ["applied", [_n, _nk]] call _reply;
    };
    case "athenaDone": {
        _a params ["", ["_lid", "", [""]], ["_aid", "", [""]]];
        if (_lid isEqualTo "" || {_aid isEqualTo ""}) exitWith {};
        private _local = call _store;
        private _i = _local findIf { ((_x getOrDefault ["id", ""]) call _str) isEqualTo _lid };
        if (_i < 0) exitWith {};
        (_local select _i) set ["athena_id", _aid];
        [_local] call _saveStore;
        [[_aid]] call _markDone;
    };
    case "unkeep": {
        _a params ["", ["_id", "", [""]]];
        if (_id isEqualTo "") exitWith {};
        private _jobs = [];
        private _local = call _store;
        private _i = _local findIf { ((_x getOrDefault ["id", ""]) call _str) isEqualTo _id };
        private _aid = if (_i >= 0) then { ((_local select _i) getOrDefault ["athena_id", ""]) call _str } else { _id };
        if (_i >= 0) then { _local deleteAt _i; [_local] call _saveStore; };
        // Copie Athena (entrée d'Athena, ou jumelle d'un gardé local) : retirée du paquet chargé et supprimée sur le site.
        private _ath = call _athenaList;
        private _ai = _ath findIf { ((_x getOrDefault ["id", ""]) call _str) isEqualTo _aid || {((((_x getOrDefault ["config", []]) call _toMap) getOrDefault ["local_id", ""]) call _str) isEqualTo _id} };
        if (_ai >= 0) then {
            _aid = ((_ath select _ai) getOrDefault ["id", ""]) call _str;
            private _lid = ((((_ath select _ai) getOrDefault ["config", []]) call _toMap) getOrDefault ["local_id", ""]) call _str;
            if (_lid isNotEqualTo "") then {
                private _li = _local findIf { ((_x getOrDefault ["id", ""]) call _str) isEqualTo _lid };
                if (_li >= 0) then { _local deleteAt _li; [_local] call _saveStore; };
            };
            _reg set ["aiPlacements", (_reg getOrDefault ["aiPlacements", []]) select { (((_x call _toMap) getOrDefault ["id", ""]) call _str) isNotEqualTo _aid }];
        };
        if (_aid isNotEqualTo "" && {(_aid select [0, 1]) isNotEqualTo "L"}) then { _jobs pushBack [createHashMapFromArray [["kind", "ai_unkeep"], ["id", parseNumber _aid]], ""]; };
        [_jobs] call _route;
        ["Forgot", [], "SUCCESS"] call _note;
        ["kept", [(call _keptAll) apply { _x select [0, 4] }]] call _reply;
    };
    case "restoreOne": {
        _a params ["", ["_id", "", [""]]];
        private _all = call _keptAll;
        private _i = _all findIf { (_x select 0) isEqualTo _id };
        if (_i < 0) exitWith { ["RestoreKo", [_id], "WARNING"] call _note; };
        private _k = (_all select _i) select 4;
        [[_id, (_k getOrDefault ["athena_id", ""]) call _str]] call _unmarkDone;
        private _u = [_k] call _restoreEntry;
        [[_id, (_k getOrDefault ["athena_id", ""]) call _str]] call _markDone;
        if (isNull _u) exitWith { ["RestoreKo", [(_all select _i) select 1], "WARNING"] call _note; };
        ["Restored", [(_all select _i) select 1], "SUCCESS"] call _note;
        ["applied", [1, 0]] call _reply;
    };
    default { false };
};
true
