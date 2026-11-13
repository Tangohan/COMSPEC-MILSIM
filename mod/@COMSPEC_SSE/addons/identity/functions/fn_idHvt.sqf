/*
    HVT et BOLO : chaîne contrôle → identification → confirmation PID → capture ou neutralisation → exploitation SSE
    → Athena, branchée sur la chasse aux HVT de l'ATAK (comspec_atak_native_fnc_hvtIntel : FIND, FIX, JACKPOT) et sur
    l'app Recherchés (main.pbo non modifié : données et évènements existants).
    SERVEUR :
      ["designate", [unité, réglages, zeus]] -> [texte, genre]   Zeus « Désigner HVT ». Réglages (HashMap) : alias,
          last / first (vrai nom ; vide : celui de ses papiers), docs (« n°, n° » papiers connus), level (hvt |
          recherche), cover (vrai : lui donner une carte de couverture), coverLast / coverFirst (nom de la couverture).
          Clé biométrique et visage au fichier (watchlist 1 ou 2), avis local (N3), chasse ATAK (FIND), BOLO, Athena.
      ["bolo", [réglages, zeus, unité | objNull]] -> [texte, genre]   Zeus « BOLO » : last, first, alias, level
          (surveillance | recherche | hvt), docs, notes ; posé sur une personne : son visage (clé) est joint. Diffusé
          aux checkpoints (liste papier, visage) et aux contrôleurs (onglet BOLO de Contrôle ID, app Recherchés), et
          remonté à Athena (watch[] des paquets suivants).
      ["boloRemove", [réf]] -> [texte, genre]          ["boloList"] -> fiches publiques (aussi missionNamespace
          comspec_sse_idBolos, diffusée : réf, nom, prénom, alias, niveau, papiers, notes, genre BOLO | HVT, heure)
      ["docFlag", [document]] -> [réf, niveau] ([] sinon)   numéro sur un BOLO ou parmi les papiers connus d'une HVT
      ["faceFlag", [unité]] -> [réf, niveau] ([] sinon)    visage d'un BOLO / d'une HVT (clé biométrique)
      ["seen", [sujet, contrôleur, niveau, verdict]]   identification au contrôle (N3 nom, N4 visage) : PID possible
      ["pid", [contrôleur, sujet]] -> [texte, genre]   PID confirmé (étape FIX) : tuyaux confirmés pour son groupe
          (sources CTRL-ID, BIO-N4, PID), compte rendu AAR, Zeus, Athena
      ["jackpot", [unité, « CAPTURÉ » | « EKIA », par]]   menottes ACE ou mort : JACKPOT, chaîne de possession ouverte
          et données SSE générées (exploitation), AAR, Zeus, Athena
      ["of", [unité]] -> fiche HVT (HashMap vide sinon)
      ["athena", [genre, données]]   envoi à Athena (SubmitIdCheck, genre bolo | hvt) : DLL du serveur, sinon d'un Zeus
    CLIENT : ["mine"] -> BOLO visibles par ce joueur (camps autorisés des recherchés de l'opération ; Zeus : tous)
             ["wantedSync"]   BOLO et HVT injectés dans l'app Recherchés (liste déjà chargée seulement ; un
          « Actualiser » les remplace par ceux d'Athena, qui les connaît une fois remontés) ; entrées aussi rangées dans
          missionNamespace COMSPEC_ATAK_WantedLocal (même format que COMSPEC_ATAK_Wanted, pour une fusion par l'ATAK).
*/
params [["_op", "", [""]], ["_a", []]];
// Liste diffusée, vue par ce joueur : recherchés de l'opération réservés à d'autres camps retirés.
if (_op isEqualTo "mine") exitWith {
    private _me = ["east", "west", "guer", "civ"] select (([east, west, independent, civilian] find (side group player)) max 0);
    (missionNamespace getVariable ["comspec_sse_idBolos", []]) select { ((_x param [9, []]) isEqualTo []) || {_me in (_x param [9, []])} || {!isNull (getAssignedCuratorLogic player)} }
};
if (_op isEqualTo "wantedSync") exitWith {
    if (!hasInterface) exitWith {};
    private _local = (["mine"] call comspec_sse_fnc_idHvt) apply {
        _x params ["_ref", "_l", "_f", "_al", "_lvl", "_docs", "_notes", ["_kind", "BOLO"], ["_at", ""]];
        [["watchlist", "person"] select (_kind isEqualTo "HVT"), _ref, _ref, format ["%1 %2", toUpper _l, _f], _al,
            format ["%1 · %2", _kind, _lvl], trim (format ["%1 %2", _notes, ["", format ["Papiers connus : %1.", _docs joinString ", "]] select (_docs isNotEqualTo [])]), "", _at]
    };
    missionNamespace setVariable ["COMSPEC_ATAK_WantedLocal", _local];
    private _w = uiNamespace getVariable ["COMSPEC_ATAK_Wanted", []];
    if ((count _w) < 3 || {!((_w select 0) isEqualType [])}) exitWith {};
    private _list = (_w select 0) select { !(((_x param [1, ""]) select [0, 5]) in ["BOLO-", "HVT-0"]) || {(_x param [0, ""]) isEqualTo "interest"} };
    _list append _local;
    uiNamespace setVariable ["COMSPEC_ATAK_Wanted", [_list, _w select 1, _w select 2]];
};
if (!isServer) exitWith { nil };
private _reg = ["init"] call comspec_sse_fnc_idRegistry;
private _hvts = _reg getOrDefault ["hvts", createHashMap];
_reg set ["hvts", _hvts];
private _bolos = _reg getOrDefault ["bolos", createHashMap];
_reg set ["bolos", _bolos];
private _tell = {
    params ["_u", "_op2", "_args"];
    if (isNull _u || {!isPlayer _u}) exitWith {};
    if (local _u && {hasInterface}) then { [_op2, _args] call comspec_sse_fnc_idClient; } else { [_op2, _args] remoteExecCall ["comspec_sse_fnc_idClient", owner _u]; };
};
private _zeus = {
    params ["_t", ["_k", "info"]];
    { private _z = getAssignedCuratorUnit _x; if (!isNull _z && {isPlayer _z}) then { [_z, "zeus", [_t, _k]] call _tell; }; } forEach allCurators;
};
private _docsOf = {
    params ["_s"];
    if !(_s isEqualType "") exitWith { [] };
    ((_s splitString ",; ") apply { toUpper (trim _x) }) select { _x isNotEqualTo "" }
};
// Liste publique (diffusée) : ce qu'on imprime sur un avis, jamais la nature des papiers.
private _publish = {
    private _pub = [];
    { _pub pushBack [_x, _y get "last", _y get "first", _y get "alias", _y get "level", _y get "docs", _y get "notes", _y get "kind", _y get "clock", _y getOrDefault ["sides", []]]; } forEach _bolos;
    missionNamespace setVariable ["comspec_sse_idBolos", _pub, true];
    _pub
};
// Référence stable envoyée à Athena (clé d'envoi : lettres, chiffres, « _ . : - ») : mission, session, numéro.
if !("sess" in _reg) then { _reg set ["sess", str (1000 + floor random 8999)]; };
private _mission = {
    private _ok = toArray "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789_";
    (toString ((toArray missionName) select { _x in _ok })) select [0, 24]
};
private _addBolo = {
    params ["_spec", "_kind", ["_key", ""]];
    private _seq = (_reg getOrDefault ["boloSeq", 0]) + 1;
    _reg set ["boloSeq", _seq];
    private _ref = format [["BOLO-%1", "HVT-%1"] select (_kind isEqualTo "HVT"), [str _seq, format ["0%1", _seq]] select (_kind isEqualTo "HVT")];
    private _lvl = _spec getOrDefault ["level", "recherche"];
    if !(_lvl in ["surveillance", "recherche", "hvt"]) then { _lvl = "recherche"; };
    private _b = createHashMapFromArray [
        ["ref", _ref], ["last", trim (_spec getOrDefault ["last", ""])], ["first", trim (_spec getOrDefault ["first", ""])],
        ["alias", trim (_spec getOrDefault ["alias", ""])], ["level", _lvl], ["docs", [_spec getOrDefault ["docs", ""]] call _docsOf],
        ["notes", trim (_spec getOrDefault ["notes", ""])], ["kind", _kind], ["key", _key], ["clock", [dayTime, "HH:MM"] call BIS_fnc_timeToString],
        ["gameRef", format ["%1-%2-%3-%4", ["BOLO", "HVT"] select (_kind isEqualTo "HVT"), call _mission, _reg get "sess", _seq]]
    ];
    _bolos set [_ref, _b];
    // Avis local (contrôle N3, liste papier des checkpoints) : vrai nom et alias.
    private _w = _reg get "watch";
    if ((_b get "last") isNotEqualTo "") then { _w pushBack [_b get "last", _b get "first", _b get "alias", _lvl, _ref]; };
    _b
};
private _boloAthena = {
    params ["_b", ["_status", "active"]];
    ["athena", [["bolo", "hvt"] select ((_b get "kind") isEqualTo "HVT"), createHashMapFromArray [
        ["game_ref", _b get "gameRef"], ["last_name", _b get "last"], ["first_name", _b get "first"], ["alias", _b get "alias"],
        ["level", _b get "level"], ["notes", _b get "notes"], ["bio_key", _b get "key"], ["known_docs", _b get "docs"], ["status", _status]
    ]]] call comspec_sse_fnc_idHvt;
};
private _broadcast = {
    params ["_text"];
    call _publish;
    if (missionNamespace getVariable ["comspec_sse_id_boloNotify", true]) then { ["comspec_sse_idBoloNew", [_text]] call CBA_fnc_globalEvent; };
};

switch (_op) do {
    case "of": {
        _a params [["_u", objNull]];
        if (isNull _u) exitWith { createHashMap };
        _hvts getOrDefault [[_u] call comspec_sse_fnc_bioPersonKey, createHashMap]
    };
    case "designate": {
        _a params [["_u", objNull], ["_spec", createHashMap], ["_by", objNull]];
        if (isNull _u || {!alive _u} || {!(_u isKindOf "CAManBase")}) exitWith { ["Placez le module sur une personne vivante.", "warn"] };
        private _key = [_u] call comspec_sse_fnc_bioPersonKey;
        if (_key in _hvts) exitWith { [format ["%1 est déjà une HVT (%2).", name _u, (_hvts get _key) get "ref"], "warn"] };
        private _lvl = ["recherche", "hvt"] select ((_spec getOrDefault ["level", "hvt"]) isEqualTo "hvt");
        // Vrai nom : saisi, sinon celui de ses papiers (ou de son identité générée).
        private _docs = (["unitDocs", [_u]] call comspec_sse_fnc_idRegistry) apply { ["doc", [_x]] call comspec_sse_fnc_idRegistry };
        private _own = ["get", [_u, _key]] call comspec_sse_fnc_idIdentityOf;
        private _tl = trim (_spec getOrDefault ["last", ""]);
        private _tf = trim (_spec getOrDefault ["first", ""]);
        if (_tl isEqualTo "") then { _tl = ((_docs select { (_x get "kind") isEqualTo "civil" }) param [0, createHashMap]) getOrDefault ["trueLast", _own get "last"]; };
        if (_tf isEqualTo "") then { _tf = ((_docs select { (_x get "kind") isEqualTo "civil" }) param [0, createHashMap]) getOrDefault ["trueFirst", _own get "first"]; };
        private _alias = trim (_spec getOrDefault ["alias", ""]);
        // Fichier biométrique (visage, empreintes) : watchlist 1 (HVT) ou 2 (recherché) ; chasse ATAK (Tinder : FIND).
        _u setVariable ["COMSPEC_SSE_BioDb", ["bewl2", "bewl1"] select (_lvl isEqualTo "hvt"), true];
        if (_alias isNotEqualTo "") then { _u setVariable ["COMSPEC_SSE_Alias", _alias, true]; };
        _u setVariable ["COMSPEC_SSE_BioReason", ["Recherché, désigné par le commandement", "HVT désignée par le commandement"] select (_lvl isEqualTo "hvt"), true];
        _u setVariable ["COMSPEC_SSE_BioTrueName", format ["%1 %2", _tf, _tl]];
        _u setVariable ["comspec_sse_idProfile", "hvt"];
        if (!isPlayer _u) then { _u setVariable ["COMSPEC_ATAK_TinderKind", "HVT", true]; };
        // Couverture : une carte au nom de la couverture (passe tout sauf la biométrie).
        if (_spec getOrDefault ["cover", false]) then {
            // Nom de couverture : saisi, sinon une autre identité tirée pour lui (jamais son vrai nom).
            private _alt = [_u, _key, "alt"] call comspec_sse_fnc_idGenerate;
            private _c = createHashMapFromArray [["type", "id_card"], ["kind", "cover"], ["quality", 92], ["materialize", isPlayer _u],
                ["last", _alt get "last"], ["first", _alt get "first"], ["dob", _alt get "dob"], ["pob", _alt get "pob"]];
            if ((trim (_spec getOrDefault ["coverLast", ""])) isNotEqualTo "") then { _c set ["last", trim (_spec get "coverLast")]; };
            if ((trim (_spec getOrDefault ["coverFirst", ""])) isNotEqualTo "") then { _c set ["first", trim (_spec get "coverFirst")]; };
            (_reg get "decided") set [_key, true];
            [_u, _c] call comspec_sse_fnc_idIssue;
            ["reset", [_u]] call comspec_sse_fnc_idLegend;
        };
        // Papiers connus : saisis, plus ses papiers actuels s'ils portent son vrai nom.
        private _known = [_spec getOrDefault ["docs", ""]] call _docsOf;
        { if ((toUpper (_x get "last")) isEqualTo (toUpper _tl)) then { _known pushBackUnique toUpper (_x get "number"); }; } forEach _docs;
        private _b = [createHashMapFromArray [["last", _tl], ["first", _tf], ["alias", _alias], ["level", _lvl], ["docs", _known joinString ","],
            ["notes", trim (_spec getOrDefault ["notes", ""])]], "HVT", _key] call _addBolo;
        private _h = createHashMapFromArray [
            ["ref", _b get "ref"], ["key", _key], ["net", netId _u], ["alias", _alias], ["last", _tl], ["first", _tf], ["docs", _known],
            ["level", _lvl], ["stage", "FIND"], ["by", [name _by, "Zeus"] select (isNull _by)], ["pid", []], ["pidNet", []], ["at", time], ["gameRef", _b get "gameRef"]
        ];
        _hvts set [_key, _h];
        [format ["HVT %1 : %2 %3%4.", _b get "ref", toUpper _tl, _tf, ["", format [" « %1 »", _alias]] select (_alias isNotEqualTo "")]] call _broadcast;
        [_b, "find"] call _boloAthena;
        ["add", ["HVT", format ["%1 désigné HVT (%2) : %3 %4%5", name _u, _b get "ref", toUpper _tl, _tf, ["", format [" « %1 »", _alias]] select (_alias isNotEqualTo "")], format ["hvt|%1", _key]]] call comspec_sse_fnc_idAar;
        [format ["%1 désigné %2 (%3). Visage et clé biométrique au fichier, chasse ATAK ouverte (FIND).", name _u, ["recherché", "HVT"] select (_lvl isEqualTo "hvt"), _b get "ref"], "info"]
    };
    case "bolo": {
        _a params [["_spec", createHashMap], ["_by", objNull], ["_u", objNull]];
        private _key = if (!isNull _u && {_u isKindOf "CAManBase"}) then { [_u] call comspec_sse_fnc_bioPersonKey } else { "" };
        if ((trim (_spec getOrDefault ["last", ""])) isEqualTo "" && {(trim (_spec getOrDefault ["alias", ""])) isEqualTo ""} && {_key isEqualTo ""}) exitWith {
            ["BOLO : saisissez au moins un nom ou un alias (ou posez le module sur la personne).", "warn"]
        };
        private _b = [_spec, "BOLO", _key] call _addBolo;
        // Alias seul : avis sur l'alias (liste papier des checkpoints).
        if ((_b get "last") isEqualTo "" && {(_b get "alias") isNotEqualTo ""}) then { (_reg get "watch") pushBack ["", "", _b get "alias", _b get "level", _b get "ref"]; };
        [format ["BOLO %1 : %2%3.", _b get "ref", trim format ["%1 %2", toUpper (_b get "last"), _b get "first"], ["", format [" « %1 »", _b get "alias"]] select ((_b get "alias") isNotEqualTo "")]] call _broadcast;
        [_b] call _boloAthena;
        ["add", ["BOLO", format ["BOLO %1 diffusé : %2 %3 (%4)", _b get "ref", toUpper (_b get "last"), _b get "first", _b get "level"], format ["bolo|%1", _b get "ref"]]] call comspec_sse_fnc_idAar;
        [format ["BOLO %1 diffusé aux checkpoints et aux contrôleurs%2.", _b get "ref", ["", " (visage joint)"] select (_key isNotEqualTo "")], "info"]
    };
    case "boloRemove": {
        _a params [["_ref", ""]];
        private _b = _bolos getOrDefault [_ref, createHashMap];
        if ((count _b) isEqualTo 0) exitWith { ["BOLO inconnu.", "warn"] };
        _bolos deleteAt _ref;
        _reg set ["watch", (_reg get "watch") select { (_x param [4, ""]) isNotEqualTo _ref }];
        call _publish;
        [_b, "removed"] call _boloAthena;
        [format ["%1 levé.", _ref], "info"]
    };
    case "boloList": { call _publish };
    case "docFlag": {
        _a params [["_d", createHashMap]];
        private _n = toUpper (_d getOrDefault ["number", ""]);
        if (_n isEqualTo "") exitWith { [] };
        private _out = [];
        { if (_n in (_y get "docs")) exitWith { _out = [_x, _y get "level"]; }; } forEach _bolos;
        _out
    };
    case "faceFlag": {
        _a params [["_u", objNull]];
        if (isNull _u) exitWith { [] };
        private _key = [_u] call comspec_sse_fnc_bioPersonKey;
        private _out = [];
        { if ((_y get "key") isEqualTo _key && {_key isNotEqualTo ""}) exitWith { _out = [_x, _y get "level"]; }; } forEach _bolos;
        _out
    };
    case "seen": {
        _a params [["_subj", objNull], ["_ctrl", objNull], ["_lvl", 3], ["_v", ""]];
        if (isNull _subj || {!(_v in ["hvt", "recherche"])}) exitWith { false };
        private _seen = _reg getOrDefault ["pidSeen", createHashMap];
        _reg set ["pidSeen", _seen];
        private _k = format ["%1|%2", netId _ctrl, netId _subj];
        private _e = _seen getOrDefault [_k, [0, []]];
        (_e select 1) pushBackUnique _lvl;
        _seen set [_k, [time, _e select 1]];
        true
    };
    case "pid": {
        _a params [["_ctrl", objNull], ["_subj", objNull]];
        if (isNull _subj || {!alive _subj}) exitWith { ["PID : la personne n'est plus là.", "warn"] };
        private _e = (_reg getOrDefault ["pidSeen", createHashMap]) getOrDefault [format ["%1|%2", netId _ctrl, netId _subj], []];
        if (_e isEqualTo [] || {(time - (_e select 0)) > 900}) exitWith { ["PID : contrôlez d'abord la personne (base N3 ou visage N4).", "warn"] };
        if ((_ctrl distance _subj) > 30) exitWith { ["PID : la personne doit être sous vos yeux (30 m).", "warn"] };
        private _key = [_subj] call comspec_sse_fnc_bioPersonKey;
        private _h = _hvts getOrDefault [_key, createHashMap];
        if ((count _h) isEqualTo 0) then {
            // Personne fichée (watchlist) sans désignation : elle devient une HVT suivie (chasse ATAK comprise).
            _h = createHashMapFromArray [["ref", ""], ["key", _key], ["net", netId _subj], ["alias", _subj getVariable ["COMSPEC_SSE_Alias", ""]],
                ["last", ""], ["first", ""], ["docs", []], ["level", "hvt"], ["stage", "FIND"], ["by", "PID"], ["pid", []], ["pidNet", []], ["at", time], ["gameRef", ""]];
            _hvts set [_key, _h];
            if (!isPlayer _subj) then { _subj setVariable ["COMSPEC_ATAK_TinderKind", "HVT", true]; };
        };
        (_h get "pid") pushBackUnique (name _ctrl);
        (_h get "pidNet") pushBackUnique (netId _ctrl);
        private _first = (_h get "stage") isEqualTo "FIND";
        if (_first) then { _h set ["stage", "FIX"]; };
        // Tuyaux confirmés pour le groupe du contrôleur : une source par modalité (le dossier passe en FIX).
        private _srcs = ["PID"];
        if (3 in (_e select 1)) then { _srcs pushBack "CTRL-ID"; };
        if (4 in (_e select 1)) then { _srcs pushBack "BIO-N4"; };
        if ((count _srcs) < 2) then { _srcs pushBack "CTRL-ID"; };
        private _text = format ["PID confirmé par %1 au %2 : %3.", name _ctrl, mapGridPosition _subj, ["identifié au contrôle", "visage reconnu (N4)"] select (4 in (_e select 1))];
        { if (isPlayer _x) then { [_x, "hvtTip", [netId _subj, _srcs, getPosATL _subj, _text, name _ctrl]] call _tell; }; } forEach (units group _ctrl);
        if (!isPlayer _subj) then { ["bust", [_subj, _ctrl]] call comspec_sse_fnc_idAiFsm; };
        [format ["HVT %1 : PID confirmé par %2 au %3 (FIX).", [_h get "ref", name _subj] select ((_h get "ref") isEqualTo ""), name _ctrl, mapGridPosition _subj], "warn"] call _zeus;
        ["add", ["PID", format ["%1 : PID confirmé par %2 au %3", name _subj, name _ctrl, mapGridPosition _subj], format ["pid|%1", _key]]] call comspec_sse_fnc_idAar;
        if (_first && {(_h get "gameRef") isNotEqualTo ""}) then {
            private _b = _bolos getOrDefault [_h get "ref", createHashMap];
            if ((count _b) > 0) then { [_b, "fix"] call _boloAthena; };
        };
        [format ["PID confirmé : %1 (%2). Tuyaux transmis à votre groupe (chasse HVT : FIX).", name _subj, ["recherché", "HVT"] select ((_h get "level") isEqualTo "hvt")], "info"]
    };
    case "jackpot": {
        _a params [["_u", objNull], ["_how", "CAPTURÉ"], ["_by", objNull]];
        if (isNull _u) exitWith { false };
        private _h = _hvts getOrDefault [[_u] call comspec_sse_fnc_bioPersonKey, createHashMap];
        if ((count _h) isEqualTo 0 || {(_h get "stage") isEqualTo "JACKPOT"}) exitWith { false };
        _h set ["stage", "JACKPOT"];
        _h set ["how", _how];
        // Exploitation SSE : données de la personne générées (fouille, téléphone, documents) et chaîne de possession.
        if (!isNil "comspec_sse_fnc_ensureGenerated") then { [_u] call comspec_sse_fnc_ensureGenerated; };
        if (_how isEqualTo "CAPTURÉ" && {!isNil "comspec_sse_fnc_custody"} && {!isNull _by}) then {
            if ((["piece", [_u]] call comspec_sse_fnc_custody) isEqualTo "") then { ["open", [_u, _by, format ["Capture de la HVT %1", _h get "ref"]]] call comspec_sse_fnc_custody; };
        };
        private _label = [_h get "ref", name _u] select ((_h get "ref") isEqualTo "");
        [format ["JACKPOT : HVT %1 %2%3. Exploitation SSE ouverte.", _label, _how, ["", format [" par %1", name _by]] select (!isNull _by)], "warn"] call _zeus;
        ["add", ["JACKPOT", format ["HVT %1 %2 au %3%4", _label, _how, mapGridPosition _u, ["", format [" (%1)", name _by]] select (!isNull _by)], format ["jackpot|%1", _h get "key"]]] call comspec_sse_fnc_idAar;
        { [objectFromNetId _x, "notify", [format ["JACKPOT : %1 %2. Exploitez la personne (SSE), puis transmettez à Athena.", _label, _how], "SUCCESS"]] call _tell; } forEach (_h getOrDefault ["pidNet", []]);
        private _b = _bolos getOrDefault [_h get "ref", createHashMap];
        if ((count _b) > 0) then { [_b, ["captured", "ekia"] select (_how isEqualTo "EKIA")] call _boloAthena; };
        true
    };
    case "athena": {
        _a params [["_kind", "bolo"], ["_watch", createHashMap]];
        if ((_watch getOrDefault ["game_ref", ""]) isEqualTo "") exitWith { false };
        private _rev = (_reg getOrDefault ["athenaRev", 0]) + 1;
        _reg set ["athenaRev", _rev];
        private _payload = createHashMapFromArray [
            ["kind", _kind], ["idempotency_key", format ["%1-%2-%3", _watch get "game_ref", _watch getOrDefault ["status", "active"], _rev]],
            ["map", str (missionNamespace getVariable ["COMSPEC_MapId", 1])], ["op", missionNamespace getVariable ["comspec_sse_id_opKey", ""]],
            ["watch", _watch]
        ];
        if (["has"] call comspec_sse_fnc_idAthenaPack) exitWith { ["athenaWatch", [_payload]] call comspec_sse_fnc_idClient; true };
        // Serveur sans liaison : le Zeus le plus proche de la décision (le premier connecté) l'envoie avec sa DLL.
        private _z = (allPlayers select { !isNull (getAssignedCuratorLogic _x) }) param [0, objNull];
        if (!isNull _z) exitWith { [_z, "athenaWatch", [_payload]] call _tell; true };
        ["athenaWatch", [_payload]] call comspec_sse_fnc_idClient;
        true
    };
    default { nil };
}
