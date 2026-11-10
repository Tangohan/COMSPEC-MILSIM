/*
    Fichiers de la mission (SERVEUR) : consultation type fichier (personne, document, biométrie, véhicule), fiches du
    SEEK II et relevés de documents. Données SSE locales au serveur de mission, sans Athena : documents délivrés (base des
    pays, paquet Athena déjà chargé), avis de recherche et BOLO, dossiers biométriques (ABIS de la mission), fiches SEEK II,
    contrôles subis en jeu. Jamais la vérité de jeu : une couverture est en règle, un faux est inconnu des fichiers.
    Appel du poste : remoteExecCall ["comspec_sse_fnc_fileServer", 2] (comspec_sse_fnc_fileClient « send »).
      query     [moi, type, critères, source, étiquette]   type QW | QA | QB | QV ; réponse « result » [étiquette, résultat]
      seekStore [moi, fiche SEEK II]                      fiche enregistrée au fichier (QB et QW la retrouvent)
      record    [moi, relevé]                             relevé d'un document (fn_controlRecord) : contrôle subi au fichier
    Évènement serveur comspec_sse_idChecked (fn_idServer) : chaque niveau de contrôle d'un document est noté au fichier.
    Résultat : HashMap qrn, type, query, source, at (HH:MM), date, hit, codes (mentions, du plus grave au moins grave),
      records [HashMap kind person | document | bio, ref, last, first, dob, number, country, docType, expires, codes, lines,
      bioKey, rec, checks [[heure, grille, contrôleur, niveau, verdict]…]], online (faux ici ; le poste complète par Athena).
*/
params [["_op", "", [""]], ["_args", [], [[]]]];
if (!isServer) exitWith {};
private _fr = missionNamespace getVariable "comspec_sse_fileReg";
if (isNil "_fr") then {
    _fr = createHashMapFromArray [["seq", 0], ["seek", createHashMap], ["checks", createHashMap], ["keyChecks", createHashMap], ["log", []]];
    missionNamespace setVariable ["comspec_sse_fileReg", _fr];
};
if (_op isEqualTo "init") exitWith {
    // Contrôles subis : chaque niveau lu par un contrôleur (téléphone, SEEK II) est inscrit au fichier du document.
    ["comspec_sse_idChecked", {
        params ["_me", "_id", "_lvl", "_res"];
        ["note", [_me, _id, _lvl, _res getOrDefault ["verdict", "ras"]]] call comspec_sse_fnc_fileServer;
    }] call CBA_fnc_addEventHandler;
    true
};
if (_op isEqualTo "note") exitWith {
    _args params [["_me", objNull], ["_id", ""], ["_lvl", 0], ["_verdict", "ras"], ["_key", ""]];
    private _line = [[dayTime, "HH:MM"] call BIS_fnc_timeToString, [mapGridPosition _me, ""] select (isNull _me), [name _me, "?"] select (isNull _me), _lvl, _verdict];
    if (_id isNotEqualTo "") then {
        private _l = (_fr get "checks") getOrDefault [_id, []];
        _l pushBack _line;
        if ((count _l) > 20) then { _l deleteAt 0; };
        (_fr get "checks") set [_id, _l];
    };
    if (_key isNotEqualTo "") then {
        private _l = (_fr get "keyChecks") getOrDefault [_key, []];
        _l pushBack _line;
        if ((count _l) > 20) then { _l deleteAt 0; };
        (_fr get "keyChecks") set [_key, _l];
    };
    true
};
private _owner = if (isRemoteExecuted) then { remoteExecutedOwner } else { clientOwner };
_args params [["_me", objNull, [objNull]]];
if (isNull _me) exitWith {};
if (isMultiplayer && {isRemoteExecuted} && {(owner _me) isNotEqualTo _owner}) exitWith {
    diag_log text format ["[COMSPEC SSE] Fichiers : « %1 » refusé au poste %2 (unité d'un autre poste).", _op, _owner];
};
private _reply = {
    params ["_rop", "_ra"];
    if (_owner isEqualTo clientOwner || {!isMultiplayer}) then { [_rop, _ra] call comspec_sse_fnc_fileClient; } else { [_rop, _ra] remoteExecCall ["comspec_sse_fnc_fileClient", _owner]; };
};
private _reg = ["init"] call comspec_sse_fnc_idRegistry;
private _U = { toUpper trim _this };
// Numéro de document comparé sans espaces ni tirets.
private _num = { (toUpper _this) regexReplace ["[^A-Z0-9]", ""] };
// Date de naissance : AAAA-MM-JJ ou JJ/MM/AAAA, comparée en chiffres AAAAMMJJ.
private _dob = {
    private _d = (_this regexReplace ["[^0-9]", ""]);
    if ((count _d) isEqualTo 8 && {(_this find "/") > 0 || {(_this find ".") > 0}}) then { _d = (_d select [4, 4]) + (_d select [2, 2]) + (_d select [0, 2]); };
    _d
};
// Codes des constats du contrôle N3 (fn_idEvaluate) vers les mentions du fichier.
private _codeMap = createHashMapFromArray [
    ["stolen", "stolen"], ["lost", "lost"], ["revoked", "revoked"], ["expired", "expired"], ["holder_mismatch", "holder_mismatch"],
    ["bolo_doc", "bolo"], ["watch_signale", "flagged"], ["watch_recherche", "wanted"], ["watch_hvt", "hvt"], ["unknown_number", "unknown"],
    ["mil_enemy_force", "enemy_force"], ["watch_homonym", "homonym"]
];
private _order = ["armed_dangerous", "hvt", "warrant", "wanted", "missing", "stolen", "revoked", "bolo", "lost", "flagged", "holder_mismatch", "enemy_force", "caution", "expired", "homonym", "unknown"];
private _sortCodes = { private _c = _this arrayIntersect _this; (_order select { _x in _c }) + (_c select { !(_x in _order) }) };
private _docRec = {
    params ["_id"];
    private _d = (_reg get "docs") getOrDefault [_id, createHashMap];
    if ((count _d) isEqualTo 0) exitWith { createHashMap };
    private _res = [_d, _me, 3, objNull] call comspec_sse_fnc_idEvaluate;
    private _codes = ((_res getOrDefault ["codes", []]) apply { _codeMap getOrDefault [_x, ""] }) select { _x isNotEqualTo "" };
    createHashMapFromArray [
        ["kind", "document"], ["ref", _id], ["last", _d getOrDefault ["last", ""]], ["first", _d getOrDefault ["first", ""]],
        ["dob", _d getOrDefault ["dob", ""]], ["number", _d getOrDefault ["number", ""]], ["country", _d getOrDefault ["country", ""]],
        ["docType", _d getOrDefault ["type", ""]], ["expires", _d getOrDefault ["expires", ""]], ["codes", _codes call _sortCodes],
        ["lines", _res getOrDefault ["lines", []]], ["checks", +((_fr get "checks") getOrDefault [_id, []])]
    ]
};

switch (_op) do {
    case "query": {
        _args params ["", ["_type", "QA", [""]], ["_q", createHashMap, [createHashMap]], ["_src", "phone", [""]], ["_tag", "", [""]]];
        _type = toUpper _type;
        private _seq = (_fr get "seq") + 1;
        _fr set ["seq", _seq];
        private _qrn = format ["FQ-%1-%2", (missionNamespace getVariable ["COMSPEC_MapId", 1]), (str (100000 + _seq)) select [1, 5]];
        private _recs = [];
        private _seenDocs = [];
        private _addDoc = { params ["_id"]; if (_id in _seenDocs) exitWith {}; _seenDocs pushBack _id; private _r = [_id] call _docRec; if ((count _r) > 0) then { _recs pushBack _r; }; };
        private _seekRec = {
            params ["_s"];
            createHashMapFromArray [
                ["kind", "bio"], ["ref", _s getOrDefault ["rec", ""]], ["rec", _s getOrDefault ["rec", ""]], ["bioKey", _s getOrDefault ["bioKey", ""]],
                ["last", _s getOrDefault ["last", ""]], ["first", _s getOrDefault ["first", ""]], ["dob", _s getOrDefault ["dob", ""]],
                ["number", _s getOrDefault ["docNumber", ""]], ["country", _s getOrDefault ["docCountry", ""]], ["docType", _s getOrDefault ["docType", ""]],
                ["codes", [[], ["flagged"]] select ((_s getOrDefault ["verdict", ""]) isEqualTo "signale")],
                ["lines", [format ["SEEK II %1 · %2 · %3 · %4", _s getOrDefault ["rec", ""], toUpper (_s getOrDefault ["verdict", ""]), _s getOrDefault ["by", ""], _s getOrDefault ["clock", ""]]]],
                ["checks", +((_fr get "keyChecks") getOrDefault [_s getOrDefault ["bioKey", "-"], []])]
            ]
        };
        switch (_type) do {
            case "QA": {
                private _n = (_q getOrDefault ["number", ""]) call _num;
                private _c = (_q getOrDefault ["country", ""]) call _U;
                private _t = _q getOrDefault ["docType", ""];
                if (_n isEqualTo "") exitWith {};
                {
                    private _p = _x splitString "|";
                    if (((_p param [2, ""]) call _num) isEqualTo _n && {_c isEqualTo "" || {(_p param [0, ""]) isEqualTo _c}} && {_t isEqualTo "" || {(_p param [1, ""]) isEqualTo _t}}) then {
                        [(_y param [0, ""])] call _addDoc;
                    };
                } forEach (_reg getOrDefault ["issued", createHashMap]);
                // Document du paquet Athena sans exemplaire en jeu : fiche résumée (numéro, état).
                {
                    private _p = _x splitString "|";
                    if (((_p param [2, ""]) call _num) isEqualTo _n && {_c isEqualTo "" || {(_p param [0, ""]) isEqualTo _c}} && {(_recs findIf { ((_x get "number") call _num) isEqualTo _n }) < 0}) then {
                        private _rep = _y getOrDefault ["report", ""];
                        _recs pushBack createHashMapFromArray [["kind", "document"], ["ref", _y getOrDefault ["ref", ""]], ["number", _p param [2, ""]], ["country", _p param [0, ""]], ["docType", _p param [1, ""]],
                            ["last", _y getOrDefault ["last", ""]], ["first", _y getOrDefault ["first", ""]], ["dob", _y getOrDefault ["dob", ""]],
                            ["codes", (switch (_rep) do { case "vole": { ["stolen"] }; case "perdu": { ["lost"] }; case "revoque": { ["revoked"] }; default { [] }; })], ["lines", []], ["checks", []]];
                    };
                } forEach (_reg getOrDefault ["athena", createHashMap]);
            };
            case "QW": {
                private _l = (_q getOrDefault ["last", ""]) call _U;
                private _f = (_q getOrDefault ["first", ""]) call _U;
                private _b = (_q getOrDefault ["dob", ""]) call _dob;
                if (_l isEqualTo "") exitWith {};
                {
                    _y params [["_bid", ""], ["_bl", ""], ["_bf", ""]];
                    if ((_bl call _U) isEqualTo _l && {_f isEqualTo "" || {(_bf call _U) isEqualTo _f}}) then {
                        private _d = (_reg get "docs") getOrDefault [_bid, createHashMap];
                        if (_b isEqualTo "" || {((_d getOrDefault ["dob", ""]) call _dob) isEqualTo _b}) then { [_bid] call _addDoc; };
                    };
                } forEach (_reg getOrDefault ["issued", createHashMap]);
                // Avis de recherche et BOLO sur le nom.
                {
                    _x params [["_wl", ""], ["_wf", ""], ["_wa", ""], ["_lvl", "surveillance"], ["_ref", ""]];
                    if (((_wl call _U) isEqualTo _l && {_f isEqualTo "" || {(_wf call _U) in [_f, ""]}}) || {(_wa call _U) in [trim format ["%1 %2", _f, _l], _l]}) then {
                        private _code = switch (_lvl) do { case "hvt": { "hvt" }; case "prioritaire"; case "recherche": { "wanted" }; default { "flagged" }; };
                        _recs pushBack createHashMapFromArray [["kind", "person"], ["ref", _ref], ["last", _wl], ["first", _wf], ["dob", ""], ["codes", [_code]],
                            ["lines", [format ["Avis %1 (réf. %2)%3", ["de surveillance", "de recherche", "HVT"] select ((["flagged", "wanted", "hvt"] find _code) max 0), _ref, ["", format [" · alias %1", _wa]] select (_wa isNotEqualTo "")]]], ["checks", []]];
                    };
                } forEach (_reg getOrDefault ["watch", []]);
                {
                    if ((((_y getOrDefault ["last", ""]) call _U) isEqualTo _l) && {_f isEqualTo "" || {((_y getOrDefault ["first", ""]) call _U) isEqualTo _f}}) then { _recs pushBack ([_y] call _seekRec); };
                } forEach (_fr get "seek");
            };
            case "QB": {
                private _k = trim (_q getOrDefault ["bioKey", ""]);
                private _rn = (_q getOrDefault ["rec", ""]) call _U;
                // Identifiant tapé dans un seul champ : REC-… (fiche SEEK II) ou clé biométrique.
                if (_rn isEqualTo "" && {(_k select [0, 4]) isEqualTo "REC-"}) then { _rn = _k call _U; _k = ""; };
                {
                    if ((_rn isNotEqualTo "" && {(_x call _U) isEqualTo _rn}) || {_k isNotEqualTo "" && {(_y getOrDefault ["bioKey", ""]) isEqualTo _k}}) then {
                        _recs pushBack ([_y] call _seekRec);
                        if (_k isEqualTo "") then { _k = _y getOrDefault ["bioKey", ""]; };
                    };
                } forEach (_fr get "seek");
                // Dossier de la base biométrique de la mission (ABIS) : enrôlé quelque part, ou sur une liste.
                if (_k isNotEqualTo "" && {!isNil "comspec_sse_fnc_bioRecord"}) then {
                    private _u = ["owner", _k] call comspec_sse_fnc_bioRegistry;
                    private _br = [_u, _k] call comspec_sse_fnc_bioRecord;
                    if ((count _br) > 0) then {
                        private _tier = _br getOrDefault ["tier", 0];
                        private _nm = (_br getOrDefault ["name", ""]) splitString " ";
                        _recs pushBack createHashMapFromArray [["kind", "bio"], ["ref", _br getOrDefault ["id", ""]], ["bioKey", _k],
                            ["first", _nm param [0, ""]], ["last", (_nm select [1, 9]) joinString " "], ["dob", ""],
                            ["codes", [[], ["hvt"], ["wanted"], ["flagged"], ["flagged"]] select ((_tier max 0) min 4)],
                            ["lines", [format ["Dossier biométrique %1%2", _br getOrDefault ["id", ""], ["", format [" · liste %1 : %2", _tier, _br getOrDefault ["reason", ""]]] select (_tier > 0)]]],
                            ["checks", +((_fr get "keyChecks") getOrDefault [_k, []])]];
                    };
                };
            };
            case "QV": {
                // Aucun fichier des véhicules dans les données SSE : réponse NO HIT claire.
            };
        };
        private _codes = [];
        { _codes append (_x getOrDefault ["codes", []]); } forEach _recs;
        _codes = _codes call _sortCodes;
        private _hit = _recs isNotEqualTo [];
        if (!_hit) then { _codes = [["unknown"], ["no_vehicle_file"]] select (_type isEqualTo "QV"); };
        private _res = createHashMapFromArray [
            ["qrn", _qrn], ["type", _type], ["query", _q], ["source", _src], ["at", [dayTime, "HH:MM"] call BIS_fnc_timeToString],
            ["date", date], ["hit", _hit], ["codes", _codes], ["records", _recs select [0, 12]], ["online", false], ["by", name _me]
        ];
        private _log = _fr get "log";
        _log pushBack [_qrn, _type, _src, name _me, _res get "at", _hit, _codes];
        if ((count _log) > 200) then { _log deleteAt 0; };
        diag_log text format ["[COMSPEC SSE] Fichiers : %1 %2 par %3 (%4) : %5 %6", _qrn, _type, name _me, _src, ["NO HIT", "HIT"] select _hit, _codes];
        ["result", [_tag, _res]] call _reply;
    };
    case "seekStore": {
        _args params ["", ["_s", createHashMap, [createHashMap]]];
        private _rn = _s getOrDefault ["rec", ""];
        if (_rn isEqualTo "") then { _fr set ["seq", (_fr get "seq") + 1]; _rn = format ["REC-%1", (str (100000 + (_fr get "seq"))) select [1, 5]]; _s set ["rec", _rn]; };
        // La cible (objet) n'est pas gardée : seulement sa clé biométrique.
        private _u = _s getOrDefault ["unit", objNull];
        if (_u isEqualType objNull && {!isNull _u} && {(_s getOrDefault ["bioKey", ""]) isEqualTo ""}) then { _s set ["bioKey", [_u] call comspec_sse_fnc_bioPersonKey]; };
        _s deleteAt "unit";
        if ((_s getOrDefault ["by", ""]) isEqualTo "") then { _s set ["by", name _me]; };
        (_fr get "seek") set [_rn, _s];
        ["note", [_me, "", 4, _s getOrDefault ["verdict", ""], _s getOrDefault ["bioKey", ""]]] call comspec_sse_fnc_fileServer;
        ["seekStored", [_rn, _s getOrDefault ["clock", ""]]] call _reply;
    };
    case "record": {
        _args params ["", ["_r", createHashMap, [createHashMap]]];
        // Relevé d'un document : contrôle subi inscrit au fichier du numéro (base locale), sans rien inventer.
        private _n = (_r getOrDefault ["number", ""]) call _num;
        private _id = "";
        { private _p = _x splitString "|"; if (((_p param [2, ""]) call _num) isEqualTo _n) exitWith { _id = _y param [0, ""]; }; } forEach (_reg getOrDefault ["issued", createHashMap]);
        ["note", [_me, _id, 0, _r getOrDefault ["visual", "releve"]]] call comspec_sse_fnc_fileServer;
        ["recorded", [_r getOrDefault ["key", ""], _id]] call _reply;
    };
};
true
