/*
    Configuration des IA (SERVEUR) : profil Athena ou identité générée, papiers, comportement, camp, et éléments gardés.
    Vérité côté serveur seulement : variables d'unité sans diffusion, registre comspec_sse_idReg. L'IA peut changer de poste
    (joueur, HC, serveur) : rien ne dépend d'un client, et un joueur qui rejoint n'a rien à recevoir.
    [_op, _a] call comspec_sse_fnc_idAiConfig
      ingest [profils, gardés]     paquet Athena (fn_idAthenaPack) : tables reg « aiProfiles », « aiPlacements »
      list                         -> [[id, réf., libellé, nom, camp, catégorie]…] pour la fenêtre du Zeus
      scan [position, rayon]       -> lignes d'état des IA autour ; scan [[], 0, unités] : ces unités
      row [unité]                  -> [netId, nom, camp, profil, papiers, comportement, groupe]
      apply [unités, spec]         spec : profile, papers, kind, behaviour, side
      issue [unité, id de profil]  -> papiers délivrés (fn_idRegistry « ensure »)
      sync [unité]                 le profil d'identité SSE a changé (fn_applyProfile) : les papiers suivent, un faux reste faux
      docs [unité] · docAdd [unité, type] · docMark [unité, docId, nature]
      restore                      éléments gardés remis en place (après le paquet)
    Retrouver une IA d'une partie à l'autre (un objet n'a pas d'identifiant stable) : son nom de variable Eden d'abord,
    fiable ; sinon la même classe à moins de 20 m de la position gardée, sans garantie si la mission a changé. Introuvable :
    rien n'est créé, le journal et le Zeus le disent.
*/
params [["_op", "", [""]], ["_a", []]];
if (!isServer) exitWith { nil };
private _reg = ["init"] call comspec_sse_fnc_idRegistry;
private _sideKey = { ["east", "west", "guer", "civ"] select (([east, west, independent, civilian] find _this) max 0) };
private _str = { if (_this isEqualType "") then { _this } else { str _this } };
private _clearDocs = {
    params ["_u"];
    private _key = [_u] call comspec_sse_fnc_bioPersonKey;
    {
        private _d = (_reg get "docs") getOrDefault [_x, createHashMap];
        private _cls = _d getOrDefault ["cls", ""];
        if (_cls isNotEqualTo "") then {
            ["comspec_sse_idLocalRemove", [_u, _cls], _u] call CBA_fnc_targetEvent;
            ["release", [_cls]] call comspec_sse_fnc_idRegistry;
        };
        (_reg get "docs") deleteAt _x;
    } forEach ((_reg get "units") getOrDefault [_key, []]);
    (_reg get "units") deleteAt _key;
    (_reg get "decided") set [_key, false];
    ["reset", [_u]] call comspec_sse_fnc_idLegend;
};
private _shownName = {
    params ["_u"];
    private _d = ["doc", [((_reg get "units") getOrDefault [[_u] call comspec_sse_fnc_bioPersonKey, []]) param [0, ""]]] call comspec_sse_fnc_idRegistry;
    // Nom imprimé (public : c'est ce que lit un contrôleur), repris par la bulle et les fenêtres.
    if ((count _d) > 0) then { _u setVariable ["comspec_sse_idName", format ["%1 %2", _d get "first", _d get "last"], true]; };
};

switch (_op) do {
    case "ingest": {
        _a params [["_profiles", []], ["_kept", []]];
        private _map = { if (_this isEqualType [] && {_this isNotEqualTo []} && {(_this select 0) isEqualType []}) then { createHashMapFromArray _this } else { createHashMap } };
        private _table = createHashMap;
        {
            private _p = _x call _map;
            private _id = (_p getOrDefault ["id", ""]) call _str;
            if (_id isNotEqualTo "") then {
                _p set ["documents", ((_p getOrDefault ["documents", []]) apply { _x call _str })];
                _table set [_id, _p];
            };
        } forEach _profiles;
        _reg set ["aiProfiles", _table];
        _reg set ["aiPlacements", _kept apply { _x call _map }];
    };
    case "list": {
        (values (_reg getOrDefault ["aiProfiles", createHashMap])) apply { [(_x get "id") call _str, _x getOrDefault ["ref", ""], _x getOrDefault ["label", ""], _x getOrDefault ["name", ""], _x getOrDefault ["side", "civ"], _x getOrDefault ["category", "civilian"]] }
    };
    case "row": {
        _a params [["_u", objNull]];
        private _key = [_u] call comspec_sse_fnc_bioPersonKey;
        private _pid = _u getVariable ["COMSPEC_ID_AiProfile", ""];
        private _prof = (_reg getOrDefault ["aiProfiles", createHashMap]) getOrDefault [_pid, createHashMap];
        private _docs = (_reg get "units") getOrDefault [_key, []];
        private _state = if !((_reg get "decided") getOrDefault [_key, false]) then { "à la demande" } else {
            if (_docs isEqualTo []) then { "aucun" } else { str (count _docs) }
        };
        [netId _u, _u getVariable ["comspec_sse_idName", name _u], (side group _u) call _sideKey, _prof getOrDefault ["ref", ["généré", "?"] select (_pid isNotEqualTo "")], _state, _u getVariable ["COMSPEC_ID_Behaviour", "auto"], groupId (group _u)]
    };
    case "scan": {
        _a params [["_pos", []], ["_radius", 50], ["_list", []]];
        if (_list isEqualTo [] && {_pos isNotEqualTo []}) then { _list = _pos nearEntities ["CAManBase", (_radius max 5) min 1000]; };
        ((_list select { alive _x && {!isPlayer _x} && {_x isKindOf "CAManBase"} }) select [0, 60]) apply { ["row", [_x]] call comspec_sse_fnc_idAiConfig }
    };
    case "issue": {
        _a params [["_u", objNull], ["_pid", ""]];
        private _p = (_reg getOrDefault ["aiProfiles", createHashMap]) getOrDefault [_pid, createHashMap];
        if ((count _p) isEqualTo 0) exitWith {
            diag_log text format ["[COMSPEC SSE] Identité : profil Athena %1 absent du paquet, papiers générés pour %2.", _pid, name _u];
            0
        };
        if ((_p getOrDefault ["papers", "identity"]) isEqualTo "none") exitWith { -1 };
        if ((_u getVariable ["COMSPEC_ID_Behaviour", "auto"]) isEqualTo "auto") then { _u setVariable ["COMSPEC_ID_Behaviour", _p getOrDefault ["behaviour", "auto"]]; };
        private _byId = _reg getOrDefault ["athenaById", createHashMap];
        private _n = 0;
        // Plusieurs IA sur un même profil : chacune reçoit ses exemplaires, avec les mêmes données.
        if ((_p getOrDefault ["papers", "identity"]) isEqualTo "identity") then {
            {
                private _k = _byId getOrDefault [_x, ""];
                if (_k isNotEqualTo "") then {
                    private _spec = ["athenaSpec", _k] call comspec_sse_fnc_idModuleGive;
                    if ((count _spec) > 0) then {
                        _spec set ["materialize", false];
                        if (([_u, _spec] call comspec_sse_fnc_idIssue) isNotEqualTo "") then { _n = _n + 1; };
                    };
                };
            } forEach (_p getOrDefault ["documents", []]);
        };
        // Identité sans papier émis, ou papiers « selon son camp » : papiers générés à son nom.
        if (_n isEqualTo 0) then {
            private _names = (_p getOrDefault ["name", ""]) splitString " ";
            private _spec = createHashMapFromArray [["type", ["id_card", "military_id"] select ((_p getOrDefault ["category", ""]) isEqualTo "military")], ["kind", "generated"]];
            if (_names isNotEqualTo []) then { _spec set ["first", _names select 0]; _spec set ["last", (_names select [1, 9]) joinString " "]; };
            if (([_u, _spec] call comspec_sse_fnc_idIssue) isNotEqualTo "") then { _n = 1; };
        };
        [_u] call _shownName;
        _n
    };
    case "apply": {
        _a params [["_units", []], ["_spec", createHashMap]];
        _units = _units select { !isNull _x && {alive _x} && {!isPlayer _x} && {_x isKindOf "CAManBase"} };
        private _sideName = _spec getOrDefault ["side", "auto"];
        private _sideNew = ([west, east, independent, civilian] param [["west", "east", "guer", "civ"] find _sideName, sideUnknown]);
        {
            private _u = _x;
            [_u] call _clearDocs;
            private _pid = _spec getOrDefault ["profile", ""];
            _u setVariable ["COMSPEC_ID_AiProfile", _pid];
            _u setVariable ["COMSPEC_ID_Behaviour", _spec getOrDefault ["behaviour", "auto"]];
            _u setVariable ["COMSPEC_ID_Carry", _spec getOrDefault ["papers", "auto"]];
            _u setVariable ["COMSPEC_ID_Kind", _spec getOrDefault ["kind", "civil"]];
            _u setVariable ["comspec_sse_idProfile", nil];
            ["ensure", [_u]] call comspec_sse_fnc_idRegistry;
            [_u] call _shownName;
        } forEach _units;
        // Camp : un groupe neuf pour les unités qui en changent, ensemble.
        if (_sideNew isNotEqualTo sideUnknown) then {
            private _move = _units select { (side group _x) isNotEqualTo _sideNew };
            if (_move isNotEqualTo []) then {
                private _g = createGroup [_sideNew, true];
                if (!isNull _g) then { _move joinSilent _g; };
            };
        };
        count _units
    };
    case "sync": {
        // Le profil d'identité SSE reste la seule source : nom, date et lieu de naissance suivent sur les papiers ;
        // un papier de couverture ou un faux garde son nom imprimé, seule sa vraie identité change (il reste détectable).
        _a params [["_u", objNull]];
        if (isNull _u) exitWith { 0 };
        private _key = [_u] call comspec_sse_fnc_bioPersonKey;
        private _sec = [_u, "identity"] call comspec_sse_fnc_getSection;
        if (isNil "_sec" || {!(_sec isEqualType createHashMap)}) then { _sec = createHashMap; };
        private _n = 0;
        {
            private _d = (_reg get "docs") getOrDefault [_x, createHashMap];
            if ((count _d) > 0) then {
                private _own = [_u, _key, "", _d get "country"] call comspec_sse_fnc_idGenerate;
                _d set ["trueLast", _own get "last"];
                _d set ["trueFirst", _own get "first"];
                if ((_d get "kind") in ["civil", "generated"]) then {
                    _d set ["last", _own get "last"];
                    _d set ["first", _own get "first"];
                    { _x params ["_field", "_k"]; private _v = _sec getOrDefault [_k, ""]; if (_v isEqualType "" && {_v isNotEqualTo ""}) then { _d set [_field, _v]; }; } forEach [["dob", "birth_date"], ["pob", "birth_place"]];
                    private _bk = format ["%1|%2|%3", _d get "country", _d get "type", _d get "number"];
                    private _b = (_reg get "issued") getOrDefault [_bk, []];
                    if (_b isNotEqualTo []) then { _b set [1, _d get "last"]; _b set [2, _d get "first"]; };
                };
                _n = _n + 1;
            };
        } forEach ((_reg get "units") getOrDefault [_key, []]);
        if (_n > 0) then { ["reset", [_u]] call comspec_sse_fnc_idLegend; [_u] call _shownName; };
        _n
    };
    case "docs": {
        _a params [["_u", objNull]];
        (((_reg get "units") getOrDefault [[_u] call comspec_sse_fnc_bioPersonKey, []]) apply { (_reg get "docs") getOrDefault [_x, createHashMap] }) select { (count _x) > 0 } apply {
            [_x get "id", _x get "type", _x get "number", _x get "last", _x get "first", _x get "kind"]
        }
    };
    case "docAdd": {
        _a params [["_u", objNull], ["_type", "id_card"]];
        ["ensure", [_u]] call comspec_sse_fnc_idRegistry;
        private _first = (["docs", [_u]] call comspec_sse_fnc_idAiConfig) param [0, []];
        // Mêmes nom et prénom que les papiers déjà portés.
        private _spec = createHashMapFromArray [["type", _type], ["kind", _first param [5, "civil"]]];
        if (_first isNotEqualTo []) then { _spec set ["last", _first select 3]; _spec set ["first", _first select 4]; };
        [_u, _spec] call comspec_sse_fnc_idIssue
    };
    case "docMark": {
        // Vrai ou faux : le papier est refait avec le même nom ; un faux reçoit ses défauts selon sa qualité (fn_idIssue).
        _a params [["_u", objNull], ["_id", ""], ["_kind", "civil"]];
        private _d = (_reg get "docs") getOrDefault [_id, createHashMap];
        if ((count _d) isEqualTo 0) exitWith { "" };
        private _spec = createHashMapFromArray [["type", _d get "type"], ["country", _d get "country"], ["kind", _kind], ["last", _d get "last"], ["first", _d get "first"], ["sex", _d get "sex"], ["dob", _d get "dob"], ["pob", _d get "pob"], ["quality", 50]];
        private _cls = _d getOrDefault ["cls", ""];
        if (_cls isNotEqualTo "") then {
            ["comspec_sse_idLocalRemove", [_u, _cls], _u] call CBA_fnc_targetEvent;
            ["release", [_cls]] call comspec_sse_fnc_idRegistry;
        };
        (_reg get "docs") deleteAt _id;
        private _key = [_u] call comspec_sse_fnc_bioPersonKey;
        (_reg get "units") set [_key, ((_reg get "units") getOrDefault [_key, []]) - [_id]];
        [_u, _spec] call comspec_sse_fnc_idIssue
    };
    case "restore": {
        private _done = _reg getOrDefault ["aiKeptDone", createHashMap];
        _reg set ["aiKeptDone", _done];
        private _missing = [];
        private _used = [];
        {
            private _k = _x;
            private _id = (_k getOrDefault ["id", ""]) call _str;
            if (_id isEqualTo "" || {_id in _done}) then { continue };
            _done set [_id, true];
            private _pos = [_k getOrDefault ["x", 0], _k getOrDefault ["y", 0], 0];
            private _cfg = _k getOrDefault ["config", []];
            if (_cfg isEqualType [] && {_cfg isNotEqualTo []} && {(_cfg select 0) isEqualType []}) then { _cfg = createHashMapFromArray _cfg; };
            if !(_cfg isEqualType createHashMap) then { _cfg = createHashMap; };
            if ((_k getOrDefault ["kind", "ai"]) isEqualTo "item") then {
                private _it = _cfg getOrDefault ["item", []];
                if (_it isEqualType [] && {_it isNotEqualTo []}) then { ["replay", [createHashMapFromArray _it, _pos]] call comspec_sse_fnc_editorServer; };
                continue
            };
            private _u = objNull;
            private _var = _k getOrDefault ["var_name", ""];
            if (_var isEqualType "" && {_var isNotEqualTo ""}) then { _u = missionNamespace getVariable [_var, objNull]; };
            if !(_u isEqualType objNull) then { _u = objNull; };
            if (isNull _u) then {
                private _near = (nearestObjects [_pos, [_k getOrDefault ["class_name", "CAManBase"]], 20]) select { alive _x && {!isPlayer _x} && {!(_x in _used)} };
                _u = _near param [0, objNull];
            };
            if (isNull _u) then { _missing pushBack (_k getOrDefault ["label", _id]); continue };
            _used pushBack _u;
            _cfg set ["profile", (_k getOrDefault ["profile_id", ""]) call _str];
            if ((_cfg get "profile") in ["0", ""]) then { _cfg set ["profile", ""]; };
            ["apply", [[_u], _cfg]] call comspec_sse_fnc_idAiConfig;
        } forEach (_reg getOrDefault ["aiPlacements", []]);
        if (_missing isNotEqualTo []) then {
            diag_log text format ["[COMSPEC SSE] Identité : éléments gardés non retrouvés : %1.", _missing joinString ", "];
            { private _z = getAssignedCuratorUnit _x; if (!isNull _z && {isPlayer _z}) then { ["zeus", [format ["Éléments gardés non retrouvés : %1.", _missing joinString ", "], "warn"]] remoteExecCall ["comspec_sse_fnc_idClient", owner _z]; }; } forEach allCurators;
        };
        count _used
    };
    default { nil };
}
