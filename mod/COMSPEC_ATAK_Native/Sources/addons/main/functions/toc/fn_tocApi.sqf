/*
    Fonctions publiques du TOC pour les créateurs de mission (feuille de route TOC, Z4). Une seule entrée, stable et
    versionnée : [verbe, arguments] call comspec_atak_native_fnc_tocApi. Mode d'emploi et exemples : docs/TOC-API.md.
    Contrat : un verbe publié ne change ni de nom ni d'ordre d'arguments ; un argument nouveau s'ajoute à la fin,
    facultatif ; la version (« version ») monte de 1.0 à 1.1 pour un ajout, à 2.0 pour une rupture.
    Tout est interne au serveur du jeu : ni Athena, ni téléphone, aucune requête réseau hors du jeu.
    Les verbes « server » s'appellent sur le serveur (initServer.sqf, déclencheur « serveur seulement ») : la mission y
    est de confiance (niveau administrateur, fn_tocAccess). Ailleurs ils ne font rien et rendent la valeur d'échec.
    Un appel distant venu d'un joueur est refusé : les joueurs passent par les demandes contrôlées du TOC.
    Arguments faux : rien n'est fait, la valeur d'échec est rendue, la raison est dans ["lastError"] et dans le journal
    COMSPEC (jamais d'erreur de script, jamais de fenêtre du jeu).
    Modes hors verbes : ["version"] | ["verbs"] | ["events"] | ["lastError"] | ["check", [verbe, arguments]] (pur : ""
    si les arguments sont justes, sinon la raison ; rejoué par tests/Unit/AtakTocApiContractTest.php)
*/
params [["_verb", "version"], ["_a", []]];
private _version = "1.0";
// ---- TABLE _sig (JSON) : [verbe, où ("server" | "any"), [[argument, type, obligatoire]...]]. Types : S texte, N nombre,
// B booléen, O objet, A tableau, C code, X tout.
private _sig = [
["roomCreate", "server", [["name", "S", true], ["side", "S", true], ["pc", "O", false], ["id", "S", false]]],
["roomBuild", "server", [["posATL", "A", true], ["dir", "N", true], ["size", "S", false], ["name", "S", false], ["side", "S", false]]],
["roomRename", "server", [["room", "S", true], ["name", "S", true]]],
["roomRemove", "server", [["room", "S", true]]],
["rooms", "any", [["side", "S", false]]],
["screenAdd", "server", [["object", "O", true], ["name", "S", false], ["side", "S", false], ["view", "S", false], ["room", "S", false]]],
["screenSpawn", "server", [["posATL", "A", true], ["dir", "N", true], ["class", "S", false], ["name", "S", false], ["side", "S", false], ["view", "S", false], ["room", "S", false]]],
["screenView", "server", [["screen", "O", true], ["view", "S", true]]],
["screenSource", "server", [["screen", "O", true], ["kind", "S", true], ["value", "X", false]]],
["screenText", "server", [["screen", "O", true], ["text", "X", true]]],
["screenPower", "server", [["screen", "O", true], ["on", "B", true]]],
["screenLink", "server", [["screen", "O", true], ["room", "S", true]]],
["screens", "any", [["roomOrSide", "S", false]]],
["lock", "server", [["room", "S", true], ["on", "B", true], ["reason", "S", false]]],
["locked", "any", [["room", "S", true]]],
["state", "any", [["side", "S", true]]],
["logAdd", "server", [["side", "S", true], ["text", "S", true], ["kind", "S", false]]],
["exAdd", "server", [["side", "S", true], ["event", "S", true], ["codeword", "S", false], ["element", "S", false], ["planned", "S", false]]],
["flash", "server", [["side", "S", true], ["text", "S", true]]],
["timerAdd", "server", [["side", "S", true], ["name", "S", true], ["kind", "S", false], ["value", "N", false]]],
["grant", "server", [["player", "X", true], ["role", "S", true]]],
["roleOf", "any", [["unit", "O", true], ["room", "S", false]]],
["can", "any", [["unit", "O", true], ["right", "S", true], ["room", "S", false]]],
["stationSet", "server", [["pc", "O", true], ["kind", "S", true], ["name", "S", false], ["fixed", "B", false]]],
["stations", "any", [["side", "S", false]]],
["journalAdd", "server", [["side", "S", true], ["text", "S", true], ["room", "S", false]]],
["journal", "server", [["side", "S", false], ["count", "N", false]]],
["presetSave", "server", [["room", "S", true], ["name", "S", true], ["library", "S", false]]],
["presetApply", "server", [["name", "S", true], ["room", "S", false], ["how", "S", false], ["posATL", "A", false], ["dir", "N", false], ["library", "S", false]]],
["presetImport", "server", [["text", "S", true], ["library", "S", false]]],
["presetExport", "server", [["name", "S", true], ["library", "S", false]]],
["presets", "server", [["library", "S", false]]],
["diag", "server", []],
["on", "any", [["event", "S", true], ["code", "C", true]]],
["off", "any", [["event", "S", true], ["id", "N", true]]]
];
// ---- FIN
// ---- TABLE _events (JSON) : [événement CBA, où il est levé ("server" | "all" | "client"), arguments].
private _events = [
["comspec_atak_toc_roomChanged", "server", "[salle, action]"],
["comspec_atak_toc_screenChanged", "server", "[écran, action, arguments]"],
["comspec_atak_toc_locked", "all", "[salle, verrouillée, auteur, motif]"],
["comspec_atak_toc_stationChanged", "all", "[netId du PC, salle, camp, rôle du poste, tenant, action]"],
["comspec_atak_toc_handover", "all", "[camp, salle, rôle du poste, sortant, entrant, état]"],
["comspec_atak_toc_journalAdded", "server", "[ligne du journal]"],
["comspec_atak_toc_denied", "server", "[unité, salle, droit, action]"],
["comspec_atak_toc_presetApplied", "server", "[salle, préréglage, écrans posés, écrans remplacés]"],
["comspec_atak_toc_saved", "server", "[format, numéro d'écriture]"],
["comspec_atak_toc_feedback", "client", "[réussi, texte, valeurs]"],
["comspec_atak_toc_journal", "client", "[camp, lignes]"],
["comspec_atak_toc_handoverList", "client", "[camp, relèves]"],
["comspec_atak_toc_presets", "client", "[préréglages du profil, préréglages de la mission]"],
["comspec_atak_toc_presetText", "client", "[nom, texte d'échange]"],
["comspec_atak_toc_diag", "client", "[texte du diagnostic]"]
];
// ---- FIN
private _sides = ["WEST", "EAST", "GUER", "CIV"];
private _check = {
    params [["_v", ""], ["_args", []]];
    private _row = (_sig select { (_x select 0) isEqualTo _v }) param [0, []];
    if (_row isEqualTo []) exitWith { format ["verbe inconnu : %1", _v] };
    if !(_args isEqualType []) exitWith { "les arguments doivent être un tableau" };
    private _err = "";
    {
        _x params ["_name", "_ty", "_req"];
        if (_err isNotEqualTo "") then { continue };
        private _val = _args param [_forEachIndex, nil];
        if (isNil "_val") then {
            if (_req) then { _err = format ["argument manquant : %1", _name]; };
            continue
        };
        private _ok = switch (_ty) do {
            case "S": { _val isEqualType "" };
            case "N": { _val isEqualType 0 };
            case "B": { _val isEqualType true };
            case "O": { _val isEqualType objNull };
            case "A": { _val isEqualType [] };
            case "C": { _val isEqualType {} };
            default { true };
        };
        if (!_ok) then { _err = format ["argument %1 : type %2 attendu", _name, _ty]; };
    } forEach (_row select 2);
    _err
};
if (_verb isEqualTo "version") exitWith { _version };
if (_verb isEqualTo "verbs") exitWith { _sig apply { [_x select 0, _x select 1, (_x select 2) apply { _x select 0 }] } };
if (_verb isEqualTo "events") exitWith { +_events };
if (_verb isEqualTo "lastError") exitWith { missionNamespace getVariable ["COMSPEC_ATAK_TocApiError", ""] };
if (_verb isEqualTo "check") exitWith { _a call _check };
if !(_a isEqualType []) then { _a = [_a]; };
// Valeur d'échec selon ce que le verbe rend.
private _fail = switch (true) do {
    case (_verb in ["roomCreate", "roomBuild", "roleOf", "presetImport", "presetExport"]): { "" };
    case (_verb isEqualTo "screenSpawn"): { objNull };
    case (_verb in ["rooms", "screens", "stations", "journal", "presets", "diag", "locked", "presetApply"]): { [] };
    case (_verb isEqualTo "state"): { createHashMap };
    case (_verb isEqualTo "on"): { -1 };
    default { false };
};
private _refuse = {
    missionNamespace setVariable ["COMSPEC_ATAK_TocApiError", _this];
    ["WARNING", "TOC", format ["API du TOC, « %1 » : %2", _verb, _this]] call comspec_atak_native_fnc_log;
};
missionNamespace setVariable ["COMSPEC_ATAK_TocApiError", ""];
if (isRemoteExecuted && {isMultiplayer} && {!(remoteExecutedOwner in [0, 2])}) exitWith { "appel distant refusé" call _refuse; _fail };
private _err = [_verb, _a] call _check;
if (_err isNotEqualTo "") exitWith { _err call _refuse; _fail };
private _where = ((_sig select { (_x select 0) isEqualTo _verb }) select 0) select 1;
if (_where isEqualTo "server" && {!isServer}) exitWith { "à appeler sur le serveur" call _refuse; _fail };
private _sideOk = { params ["_s"]; (toUpper _s) in _sides };
private _tocTrusted = true;
private _note = { params ["_sd", "_hall", "_det"]; ["add", [_sd, _hall, "MISSION", "api", format ["%1 %2", _verb, _det], true]] call comspec_atak_native_fnc_tocJournal; };
private _screenOk = { params ["_o"]; !isNull _o && {(count (_o getVariable ["COMSPEC_ATAK_TocScr", []])) >= 8} };
private _views = ["SPLIT", "MAP", "FEED", "NET", "ORBAT", "TOC", "CAM", "SAT", "CLOCK", "SQUAD", "LOG", "EXCHECK", "KILLS", "REQ", "STRIP", "PLATE", "MOSAIC", "TEXT", "COLA", "COLB"];
switch (_verb) do {
    case "roomCreate": {
        _a params ["_name", "_side", ["_pc", objNull], ["_id", ""]];
        if !([_side] call _sideOk) exitWith { "camp attendu : WEST, EAST, GUER ou CIV" call _refuse; _fail };
        if (_id isEqualTo "") then { _id = format ["M%1%2", floor (serverTime * 10), floor random 10000]; };
        ["do", ["create", [_name, toUpper _side, _pc, _id]]] call comspec_atak_native_fnc_tocHall;
        [toUpper _side, _id, _name] call _note;
        ["comspec_atak_toc_roomChanged", [_id, "create"]] call CBA_fnc_localEvent;
        _id
    };
    case "roomBuild": {
        _a params ["_pos", "_dir", ["_size", "M"], ["_name", "SALLE TOC"], ["_side", "WEST"]];
        if !(_pos isEqualTypeArray [0, 0, 0] && {[_side] call _sideOk} && {(toUpper _size) in ["S", "M", "L"]}) exitWith { "position [x, y, z], taille S, M ou L et camp attendus" call _refuse; _fail };
        private _id = ["spawnRoom", [_pos, _dir, toUpper _size, _name, toUpper _side]] call comspec_atak_native_fnc_tocHall;
        if !(_id isEqualType "") then { _id = ""; };
        [toUpper _side, _id, _name] call _note;
        ["comspec_atak_toc_roomChanged", [_id, "spawn"]] call CBA_fnc_localEvent;
        _id
    };
    case "roomRename": {
        _a params ["_id", "_name"];
        if ((["get", _id] call comspec_atak_native_fnc_tocHall) isEqualTo []) exitWith { "salle inconnue" call _refuse; _fail };
        ["do", ["rename", [_id, _name]]] call comspec_atak_native_fnc_tocHall;
        ["comspec_atak_toc_roomChanged", [_id, "rename"]] call CBA_fnc_localEvent;
        true
    };
    case "roomRemove": {
        _a params ["_id"];
        private _h = ["get", _id] call comspec_atak_native_fnc_tocHall;
        if (_h isEqualTo []) exitWith { "salle inconnue" call _refuse; _fail };
        ["do", ["roomRemove", _id]] call comspec_atak_native_fnc_tocHall;
        [_h select 2, _id, _h select 1] call _note;
        ["comspec_atak_toc_roomChanged", [_id, "roomRemove"]] call CBA_fnc_localEvent;
        true
    };
    case "rooms": {
        _a params [["_side", ""]];
        ((["dump"] call comspec_atak_native_fnc_tocHall) select { _side isEqualTo "" || {(_x select 2) in ["", toUpper _side]} }) apply {
            [_x select 0, _x select 1, _x select 2, count (["screens", _x select 0] call comspec_atak_native_fnc_tocHall), (["locked", _x select 0] call comspec_atak_native_fnc_tocAccess) select 0]
        }
    };
    case "screenAdd": {
        _a params ["_o", ["_name", ""], ["_side", ""], ["_view", "SPLIT"], ["_hall", ""]];
        if (isNull _o) exitWith { "objet absent" call _refuse; _fail };
        if !((toUpper _view) in _views) exitWith { "affichage inconnu" call _refuse; _fail };
        ["add", [_o, _name, toUpper _side, 0, toUpper _view, true, false]] call comspec_atak_native_fnc_tocScreen;
        if (_hall isNotEqualTo "") then { ["do", ["link", [_hall, _o]]] call comspec_atak_native_fnc_tocHall; };
        ["comspec_atak_toc_screenChanged", [_o, "add", [_view]]] call CBA_fnc_localEvent;
        [_o] call _screenOk
    };
    case "screenSpawn": {
        _a params ["_pos", "_dir", ["_cls", ""], ["_name", ""], ["_side", ""], ["_view", "SPLIT"], ["_hall", ""]];
        if !(_pos isEqualTypeArray [0, 0, 0] && {(toUpper _view) in _views}) exitWith { "position [x, y, z] et affichage connus attendus" call _refuse; _fail };
        private _o = ["spawn", [_pos, _dir, _cls, _name, toUpper _side, toUpper _view, true]] call comspec_atak_native_fnc_tocScreen;
        if (isNil "_o" || {!(_o isEqualType objNull)} || {isNull _o}) exitWith { "aucun écran posé (classe absente ?)" call _refuse; _fail };
        if (_hall isNotEqualTo "") then { ["do", ["link", [_hall, _o]]] call comspec_atak_native_fnc_tocHall; };
        ["comspec_atak_toc_screenChanged", [_o, "spawn", [_view]]] call CBA_fnc_localEvent;
        _o
    };
    case "screenView": {
        _a params ["_o", "_view"];
        if !([_o] call _screenOk) exitWith { "cet objet n'est pas un écran TOC" call _refuse; _fail };
        if !((toUpper _view) in _views) exitWith { "affichage inconnu" call _refuse; _fail };
        ["view", [_o, "mode", toUpper _view]] call comspec_atak_native_fnc_tocScreen;
        ["comspec_atak_toc_screenChanged", [_o, "view", ["mode", toUpper _view]]] call CBA_fnc_localEvent;
        // Un affichage refusé par le format de la dalle (carte sur un bandeau) laisse l'ancien : dit par le retour.
        ((_o getVariable ["COMSPEC_ATAK_TocScr", []]) param [3, ""]) isEqualTo (toUpper _view)
    };
    case "screenSource": {
        _a params ["_o", "_kind", ["_val", 0]];
        if !([_o] call _screenOk) exitWith { "cet objet n'est pas un écran TOC" call _refuse; _fail };
        _kind = toLower _kind;
        if !(_kind in ["cam", "sat", "squad", "mosaic", "zoom", "center", "auto"]) exitWith { "source inconnue : cam, sat, squad, mosaic, zoom, center ou auto" call _refuse; _fail };
        // Caméra : l'objet source (drone, caméra fixe, joueur) ou son netId ; "" retire la caméra.
        if (_kind isEqualTo "cam" && {_val isEqualType objNull}) then { _val = if (isNull _val) then { "" } else { netId _val }; };
        ["view", [_o, _kind, _val]] call comspec_atak_native_fnc_tocScreen;
        ["comspec_atak_toc_screenChanged", [_o, "view", [_kind, _val]]] call CBA_fnc_localEvent;
        true
    };
    case "screenText": {
        _a params ["_o", "_txt"];
        if !([_o] call _screenOk) exitWith { "cet objet n'est pas un écran TOC" call _refuse; _fail };
        if !(_txt isEqualType "" || {_txt isEqualType []}) exitWith { "texte ou réglage de l'affichage TEXTE attendu" call _refuse; _fail };
        if (_txt isEqualType "") then { _txt = ["parse", [_txt]] call comspec_atak_native_fnc_tocText; };
        ["view", [_o, "text", _txt]] call comspec_atak_native_fnc_tocScreen;
        ["comspec_atak_toc_screenChanged", [_o, "view", ["text"]]] call CBA_fnc_localEvent;
        true
    };
    case "screenPower": {
        _a params ["_o", "_on"];
        if !([_o] call _screenOk) exitWith { "cet objet n'est pas un écran TOC" call _refuse; _fail };
        ["do", ["power", [_o, _on]]] call comspec_atak_native_fnc_tocHall;
        ["comspec_atak_toc_screenChanged", [_o, "power", [_on]]] call CBA_fnc_localEvent;
        true
    };
    case "screenLink": {
        _a params ["_o", "_hall"];
        if !([_o] call _screenOk) exitWith { "cet objet n'est pas un écran TOC" call _refuse; _fail };
        if (_hall isEqualTo "") exitWith { ["do", ["unlink", _o]] call comspec_atak_native_fnc_tocHall; true };
        if ((["get", _hall] call comspec_atak_native_fnc_tocHall) isEqualTo []) exitWith { "salle inconnue" call _refuse; _fail };
        ["do", ["link", [_hall, _o]]] call comspec_atak_native_fnc_tocHall;
        true
    };
    case "screens": {
        _a params [["_k", ""]];
        ((missionNamespace getVariable ["COMSPEC_ATAK_TocScreens", []]) select { !isNull _x && {isNull (_x getVariable ["COMSPEC_ATAK_TocHost", objNull])} }) apply {
            private _c = _x getVariable ["COMSPEC_ATAK_TocScr", []];
            [_x, _c param [0, ""], _c param [1, ""], _c param [3, ""], _x getVariable ["COMSPEC_ATAK_TocHall", ""], _x getVariable ["COMSPEC_ATAK_TocOff", false]]
        } select { _k isEqualTo "" || {(_x select 4) isEqualTo _k} || {(_x select 2) isEqualTo (toUpper _k)} }
    };
    case "lock": {
        _a params ["_hall", "_on", ["_why", ""]];
        if ((["get", _hall] call comspec_atak_native_fnc_tocHall) isEqualTo []) exitWith { "salle inconnue" call _refuse; _fail };
        ["lock", [_hall, _on, _why, objNull]] call comspec_atak_native_fnc_tocAccess;
        ((["locked", _hall] call comspec_atak_native_fnc_tocAccess) select 0) isEqualTo _on
    };
    case "locked": { ["locked", _a select 0] call comspec_atak_native_fnc_tocAccess };
    case "state": {
        _a params ["_side"];
        _side = toUpper _side;
        if !([_side] call _sideOk) exitWith { "camp attendu : WEST, EAST, GUER ou CIV" call _refuse; _fail };
        private _st = ["state", _side] call comspec_atak_native_fnc_tocRoom;
        createHashMapFromArray [
            ["api", _version], ["side", _side], ["revision", _st param [0, 0]],
            ["rooms", ["rooms", [_side]] call comspec_atak_native_fnc_tocApi],
            ["screens", ["screens", [_side]] call comspec_atak_native_fnc_tocApi],
            ["stations", ["list", _side] call comspec_atak_native_fnc_tocStation],
            ["timers", +(_st param [1, []])], ["log", +(_st param [3, []])], ["excheck", +(_st param [4, []])],
            ["requests", +(_st param [5, []])], ["flash", +(_st param [6, []])], ["casualties", +(_st param [8, []])],
            ["accessMode", ["mode"] call comspec_atak_native_fnc_tocAccess]
        ]
    };
    case "logAdd": {
        _a params ["_side", "_txt", ["_kind", "INFO"]];
        if !([_side] call _sideOk) exitWith { "camp attendu : WEST, EAST, GUER ou CIV" call _refuse; _fail };
        ["do", [toUpper _side, "log", [_kind, _txt], "Mission", ""]] call comspec_atak_native_fnc_tocRoom;
        true
    };
    case "exAdd": {
        _a params ["_side", "_ev", ["_cw", ""], ["_el", ""], ["_at", ""]];
        if !([_side] call _sideOk) exitWith { "camp attendu : WEST, EAST, GUER ou CIV" call _refuse; _fail };
        ["do", [toUpper _side, "exAdd", [_ev, _cw, _el, _at], "Mission", ""]] call comspec_atak_native_fnc_tocRoom;
        true
    };
    case "flash": {
        _a params ["_side", "_txt"];
        if !([_side] call _sideOk) exitWith { "camp attendu : WEST, EAST, GUER ou CIV" call _refuse; _fail };
        if (_txt isEqualTo "") then { ["do", [toUpper _side, "flashClear", [], "Mission", ""]] call comspec_atak_native_fnc_tocRoom; }
            else { ["do", [toUpper _side, "flash", _txt, "Mission", ""]] call comspec_atak_native_fnc_tocRoom; };
        true
    };
    case "timerAdd": {
        _a params ["_side", "_name", ["_kind", "UP"], ["_val", 0]];
        if !([_side] call _sideOk && {(toUpper _kind) in ["UP", "DOWN", "AT"]}) exitWith { "camp et genre UP, DOWN ou AT attendus" call _refuse; _fail };
        ["do", [toUpper _side, "timerAdd", [_name, toUpper _kind, _val], "Mission", ""]] call comspec_atak_native_fnc_tocRoom;
        true
    };
    case "grant": {
        _a params ["_who", "_role"];
        if !(_who isEqualType objNull || {_who isEqualType ""}) exitWith { "joueur (unité) ou UID attendu" call _refuse; _fail };
        if !(_role in ["", "none", "chief", "admin"]) exitWith { "rôle attendu : chief, admin, none ou vide" call _refuse; _fail };
        ["grant", [_who, _role, objNull]] call comspec_atak_native_fnc_tocAccess
    };
    case "roleOf": { _a params ["_u", ["_hall", ""]]; ["roleOf", [_u, _hall]] call comspec_atak_native_fnc_tocAccess };
    case "can": { _a params ["_u", "_perm", ["_hall", ""]]; ["can", [_u, _perm, _hall]] call comspec_atak_native_fnc_tocAccess };
    case "stationSet": {
        _a params ["_pc", "_kind", ["_name", ""], ["_fixed", true]];
        if (isNull _pc) exitWith { "PC absent" call _refuse; _fail };
        ["set", [_pc, _kind, _name, _fixed, objNull]] call comspec_atak_native_fnc_tocStation
    };
    case "stations": { _a params [["_side", ""]]; ["list", toUpper _side] call comspec_atak_native_fnc_tocStation };
    case "journalAdd": {
        _a params ["_side", "_txt", ["_hall", ""]];
        ["add", [toUpper _side, _hall, "MISSION", "api", _txt, true]] call comspec_atak_native_fnc_tocJournal
    };
    case "journal": { _a params [["_side", ""], ["_n", 200]]; ["read", [toUpper _side, _n]] call comspec_atak_native_fnc_tocJournal };
    case "presetSave": {
        _a params ["_hall", "_name", ["_lib", "mission"]];
        private _p = ["capture", [_hall, _name]] call comspec_atak_native_fnc_tocPresets;
        if (_p isEqualTo []) exitWith { "salle sans écran" call _refuse; _fail };
        ["", _hall, _name] call _note;
        ["store", [_p, _lib]] call comspec_atak_native_fnc_tocPresets
    };
    case "presetApply": {
        _a params ["_name", ["_hall", ""], ["_how", "layout"], ["_pos", [0, 0, 0]], ["_dir", 0], ["_lib", "mission"]];
        private _p = ["read", [_name, _lib]] call comspec_atak_native_fnc_tocPresets;
        if (_p isEqualTo []) then { _p = ["read", [_name, "profile"]] call comspec_atak_native_fnc_tocPresets; };
        if (_p isEqualTo []) exitWith { "préréglage inconnu" call _refuse; _fail };
        private _r = ["place", [_p, _hall, _how, _pos, _dir]] call comspec_atak_native_fnc_tocPresets;
        ["", _r select 0, _name] call _note;
        ["comspec_atak_toc_presetApplied", [_r select 0, _name, _r select 1, _r select 2]] call CBA_fnc_localEvent;
        _r
    };
    case "presetImport": {
        _a params ["_txt", ["_lib", "mission"]];
        private _p = ["import", _txt] call comspec_atak_native_fnc_tocPresets;
        if (_p isEqualTo []) exitWith { "texte de préréglage illisible" call _refuse; _fail };
        ["store", [_p, _lib]] call comspec_atak_native_fnc_tocPresets;
        _p select 1
    };
    case "presetExport": {
        _a params ["_name", ["_lib", "mission"]];
        ["export", ["read", [_name, _lib]] call comspec_atak_native_fnc_tocPresets] call comspec_atak_native_fnc_tocPresets
    };
    case "presets": { _a params [["_lib", "mission"]]; ["list", _lib] call comspec_atak_native_fnc_tocPresets };
    case "diag": { ["collect"] call comspec_atak_native_fnc_tocDiag };
    case "on": {
        _a params ["_ev", "_code"];
        if ((_events findIf { (_x select 0) isEqualTo _ev }) < 0) exitWith { "événement inconnu" call _refuse; _fail };
        [_ev, _code] call CBA_fnc_addEventHandler
    };
    case "off": {
        _a params ["_ev", "_id"];
        if ((_events findIf { (_x select 0) isEqualTo _ev }) < 0) exitWith { "événement inconnu" call _refuse; _fail };
        [_ev, _id] call CBA_fnc_removeEventHandler;
        true
    };
    default { _fail };
};
