/*
    Registre des kits Athena (SERVEUR, rien n'est diffusé : chaque joueur reçoit seulement la liste de ses kits).
      ["ingest", [_bloc]]   bloc settings.loadouts du paquet d'identité (fn_idAthenaPack), paires [["clé", valeur]…] :
                            kits [{id, name, loadout (texte JSON du getUnitLoadout)}…], players [{uid, kits [ids], default}…].
                            Tables remplacées ; 200 kits, 500 joueurs, 64 Ko par tenue au plus ; entrée illisible
                            ignorée (RPT). Puis chaque joueur connecté reçoit sa liste (fn_loadoutTake « mine »).
      ["kitsFor", [_uid]]   -> [[id, nom]…] kits proposés à ce joueur
      ["get", [_id]]        -> [nom, tenue] ou []
      ["default", [_uid]]   -> id du kit attribué au joueur, ou 0
      ["loaded"]            -> true si un paquet a apporté le bloc loadouts
    Les ids sont gardés tels que le site les envoie (nombre ou texte) et comparés par leur texte.
*/
params [["_op", ""], ["_a", []]];
if (!isServer) exitWith { [] };
private _reg = missionNamespace getVariable "comspec_sse_loReg";
if (isNil "_reg") then {
    _reg = createHashMapFromArray [["kits", createHashMap], ["players", createHashMap], ["loaded", false]];
    missionNamespace setVariable ["comspec_sse_loReg", _reg];
};
private _key = { if (_this isEqualType "") then { _this } else { if (_this isEqualType 0) then { (round _this) toFixed 0 } else { str _this } } };
switch (_op) do {
    case "ingest": {
        _a params [["_bloc", []]];
        // Objet du paquet : paires -> HashMap ; ordre fixe accepté (champs donnés).
        private _rec = {
            params ["_r", "_fields"];
            if (_r isEqualType createHashMap) exitWith { _r };
            if !(_r isEqualType []) exitWith { createHashMap };
            if (_r isNotEqualTo [] && {(_r select 0) isEqualType []} && {count (_r select 0) == 2} && {((_r select 0) select 0) isEqualType ""}) exitWith {
                createHashMapFromArray (_r select { _x isEqualType [] && {count _x == 2} && {(_x select 0) isEqualType ""} })
            };
            private _m = createHashMap;
            { _m set [_x, _r param [_forEachIndex, ""]]; } forEach _fields;
            _m
        };
        private _top = [_bloc, ["kits", "players"]] call _rec;
        private _kits = createHashMap;
        private _players = createHashMap;
        private _bad = 0;
        private _list = _top getOrDefault ["kits", []];
        if !(_list isEqualType []) then { _list = []; };
        if ((count _list) > 200) then { diag_log text format ["[COMSPEC SSE] Équipement : %1 kits dans le paquet, 200 gardés.", count _list]; _list = _list select [0, 200]; };
        {
            private _k = [_x, ["id", "name", "loadout"]] call _rec;
            private _id = _k getOrDefault ["id", ""];
            private _name = _k getOrDefault ["name", ""];
            if !(_name isEqualType "") then { _name = str _name; };
            private _raw = _k getOrDefault ["loadout", ""];
            private _lo = [];
            if (_raw isEqualType "" && {(count _raw) > 65536}) then {
                diag_log text format ["[COMSPEC SSE] Équipement : kit %1 ignoré (tenue de plus de 64 Ko).", _id];
            } else {
                _lo = [_raw] call comspec_sse_fnc_loadoutParse;
            };
            private _ks = _id call _key;
            if (_ks isEqualTo "" || {_lo isEqualTo []}) then {
                _bad = _bad + 1;
                diag_log text format ["[COMSPEC SSE] Équipement : kit illisible ignoré (id %1, %2).", _id, _name select [0, 40]];
            } else {
                _kits set [_ks, [[_name, format ["Kit %1", _ks]] select (_name isEqualTo ""), _lo, _id]];
            };
        } forEach _list;
        _list = _top getOrDefault ["players", []];
        if !(_list isEqualType []) then { _list = []; };
        if ((count _list) > 500) then { diag_log text format ["[COMSPEC SSE] Équipement : %1 joueurs dans le paquet, 500 gardés.", count _list]; _list = _list select [0, 500]; };
        {
            private _p = [_x, ["uid", "kits", "default"]] call _rec;
            private _uid = _p getOrDefault ["uid", ""];
            // Steam ID en nombre : précision perdue par le JSON, entrée inutilisable.
            if (_uid isEqualType 0) then { _uid = ""; };
            private _ids = _p getOrDefault ["kits", []];
            if !(_ids isEqualType []) then { _ids = []; };
            _ids = (_ids apply { _x call _key }) select { _x in _kits };
            private _def = (_p getOrDefault ["default", 0]) call _key;
            if (_def in ["", "0"] || {!(_def in _kits)}) then { _def = ""; };
            if (_def isNotEqualTo "" && {!(_def in _ids)}) then { _ids pushBack _def; };
            if (!(_uid isEqualType "") || {_uid isEqualTo ""}) then {
                _bad = _bad + 1;
                diag_log text "[COMSPEC SSE] Équipement : joueur sans Steam ID lisible ignoré (uid attendu en texte).";
            } else {
                _players set [_uid, [_ids, _def]];
            };
        } forEach _list;
        _reg set ["kits", _kits];
        _reg set ["players", _players];
        _reg set ["loaded", true];
        diag_log text format ["[COMSPEC SSE] Équipement : kits Athena chargés (%1 kits, %2 joueurs, %3 entrées ignorées).", count _kits, count _players, _bad];
        ["pushAll"] call comspec_sse_fnc_loadoutTake;
        true
    };
    case "kitsFor": {
        _a params [["_uid", ""]];
        private _kits = _reg get "kits";
        (((_reg get "players") getOrDefault [_uid call _key, [[], ""]]) select 0) apply { private _k = _kits get _x; [_k select 2, _k select 0] }
    };
    case "get": {
        _a params [["_id", ""]];
        private _k = (_reg get "kits") getOrDefault [_id call _key, []];
        if (_k isEqualTo []) exitWith { [] };
        [_k select 0, +(_k select 1)]
    };
    case "default": {
        _a params [["_uid", ""]];
        private _d = ((_reg get "players") getOrDefault [_uid call _key, [[], ""]]) select 1;
        if (_d isEqualTo "") exitWith { 0 };
        ((_reg get "kits") get _d) select 2
    };
    case "loaded": { _reg get "loaded" };
    default { [] };
};
