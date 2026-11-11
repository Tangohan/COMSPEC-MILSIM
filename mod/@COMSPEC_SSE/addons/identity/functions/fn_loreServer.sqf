/*
    Lore de session ⇄ Athena (SERVEUR SEULEMENT), contrat ICD-SSE-LORE-001 : commande SubmitIdCheck de COMSPEC Link
    (aucune nouvelle DLL) → POST /api/sse/identity/checks, kind « lore_push » (remontée) et « lore_pull » (redescente).
    Une session = la communauté de la clé + un code d'opération (op) : une mission ne lit jamais une autre session.
    Rien ne part avant l'autorisation de la licence (motif de fn_fileSubmit ; serveur dédié : licence acceptée sur le
    poste d'un joueur connecté, signalée par « licence ») ni quand la liaison est coupée (fn_extensionCall).

    Espaces de noms (tout est posé ici) :
      unité, PUBLIC     comspec_sse_aiUid      identifiant stable (fn_loreUid)
                        comspec_sse_lore       données CONNUES des fichiers seulement, paires [[clé, valeur]…] :
                                               last first sex dob birth_place nationality profession aliases height_cm
                                               weight_kg build hair eyes marks addresses vehicles phones cautions flags
                                               affiliation country in_files
                        comspec_sse_loreAt     heure de la redescente (« AAAA-MM-JJ HH:MM UTC »)
      unité, SERVEUR    comspec_sse_loreAnswers  HashMap answers / evoke / banned (réponses de l'IA, jamais diffusées),
                                               fusionnées dans le profil RP (comspec_sse_iarpProfile : questions perso,
                                               phrases, cadre evoke / banned) ; comspec_sse_loreAnsKeys / lorePhrases :
                                               ce que la redescente y a mis (remplacé à la suivante)
                        comspec_sse_loreHidden caché MJ (paires), seulement si Athena l'a servi
      missionNamespace  comspec_sse_loreReg    SERVEUR : uid -> HashMap ref, known, answers, hidden, at
                        comspec_sse_loreUids   SERVEUR : uid -> unité (fn_loreUid)
                        comspec_sse_loreStatus PUBLIC : [op, envoi le, résultat, reçu le, résultat, intervalle auto (s),
                                               [titre, phase, verrou]] (aucune donnée de personne)
      profileNamespace  comspec_sse_loreLast   SERVEUR : [[op, [envoi le, résultat, reçu le, résultat]]…] (gardé)

    [_op, _args] call comspec_sse_fnc_loreServer     (remoteExecCall [..., 2] depuis un poste : Zeus ou administrateur)
      "init"                       XEH_postInit du serveur
      "attach"  [unité]            InitPost : identifiant posé, lore déjà reçu rattaché (IA créée après la synchro)
      "licence" [joueur]           licence acceptée sur ce poste (utile au serveur dédié)
      "push"    [demandeur, réglages]   réglages HashMap : op, scope (all | group | selection | sse | synced), units
      "pull"    [demandeur, réglages]   op
      "both"    [demandeur, réglages]   envoi puis redescente
      "auto"    [demandeur, réglages]   op, interval (s ; 0 : arrêt ; minimum 60)
      "status"  [demandeur]        état renvoyé au demandeur
      "eden"    [réglages]         module Eden (serveur, pas d'appel distant) : push / pull au démarrage, auto
    Réponses au demandeur : ["result", [texte, genre, état]] remoteExecCall ["comspec_sse_fnc_loreClient", poste].
*/
params [["_op", "", [""]], ["_a", [], [[]]], ["_ownerIn", -1, [0]]];
if (!isServer) exitWith { false };
private _remote = isRemoteExecuted && {remoteExecutedOwner isNotEqualTo 2};
private _ro = if (isRemoteExecuted) then { remoteExecutedOwner } else { clientOwner };
// Relance planifiée (spawn) d'une demande déjà vérifiée : poste du demandeur gardé. Jamais accepté d'un appel distant.
if (!_remote && {_ownerIn >= 0}) then { _ro = _ownerIn; };
private _nl = toString [10];
private _reg = missionNamespace getVariable "comspec_sse_loreReg";
if (isNil "_reg") then { _reg = createHashMap; missionNamespace setVariable ["comspec_sse_loreReg", _reg]; };

// ---------------------------------------------------------------- outils
private _str = { if (_this isEqualType "") then { _this } else { str _this } };
private _stamp = {
    systemTimeUTC params ["_y", "_m", "_d", "_h", "_mi"];
    private _p2 = { if (_this < 10) then { "0" + str _this } else { str _this } };
    format ["%1-%2-%3 %4:%5 UTC", _y, _m call _p2, _d call _p2, _h call _p2, _mi call _p2]
};
private _opOf = {
    params [["_spec", createHashMap]];
    private _o = trim ((_spec getOrDefault ["op", ""]) call _str);
    if (_o isEqualTo "") then { _o = trim ((missionNamespace getVariable ["comspec_sse_id_opKey", ""]) call _str); };
    // Contrat : 2 à 64 caractères A-Z a-z 0-9 _ . : -
    if ((count _o) < 2 || {(count _o) > 64} || {!(_o regexMatch "[A-Za-z0-9_.:\-]+")}) exitWith { "" };
    _o
};
private _licOk = {
    if (isNil "comspec_overwatch_connect_fnc_licenceStatus") exitWith { true };
    if (([] call comspec_overwatch_connect_fnc_licenceStatus) param [1, false]) exitWith { true };
    isDedicated && {missionNamespace getVariable ["comspec_sse_loreLicence", false]}
};
private _last = {
    // [op] -> [envoi le, résultat, reçu le, résultat]
    params ["_o"];
    private _all = profileNamespace getVariable ["comspec_sse_loreLast", []];
    if !(_all isEqualType []) then { _all = []; };
    private _i = _all findIf { _x isEqualType [] && {(_x param [0, ""]) isEqualTo _o} };
    if (_i < 0) exitWith { ["", "", "", ""] };
    private _v = (_all select _i) param [1, []];
    if !(_v isEqualType [] && {(count _v) isEqualTo 4}) exitWith { ["", "", "", ""] };
    +_v
};
private _publish = {
    params ["_o", ["_meta", []]];
    private _l = [_o] call _last;
    private _old = missionNamespace getVariable ["comspec_sse_loreStatus", []];
    if (_meta isEqualTo [] && {(_old param [0, ""]) isEqualTo _o}) then { _meta = _old param [6, []]; };
    missionNamespace setVariable ["comspec_sse_loreStatus", [_o] + _l + [missionNamespace getVariable ["comspec_sse_loreAutoS", 0], _meta], true];
};
private _remember = {
    // [op, "push" | "pull", résultat] : date et résultat gardés sur le serveur (profil), d'une partie à l'autre.
    params ["_o", "_what", "_txt"];
    private _all = profileNamespace getVariable ["comspec_sse_loreLast", []];
    if !(_all isEqualType []) then { _all = []; };
    _all = _all select { _x isEqualType [] && {(_x param [0, ""]) isNotEqualTo _o} };
    private _l = [_o] call _last;
    if (_what isEqualTo "push") then { _l set [0, call _stamp]; _l set [1, _txt]; } else { _l set [2, call _stamp]; _l set [3, _txt]; };
    _all pushBack [_o, _l];
    profileNamespace setVariable ["comspec_sse_loreLast", _all select [((count _all) - 20) max 0, 20]];
    saveProfileNamespace;
};
private _reply = {
    params ["_owner", "_txt", ["_kind", "info"]];
    diag_log text ("[COMSPEC SSE] Lore Athena : " + _txt);
    if (_owner <= 0) exitWith {};
    private _msg = ["result", [_txt, _kind, missionNamespace getVariable ["comspec_sse_loreStatus", []]]];
    if (_owner isEqualTo clientOwner) then { _msg call comspec_sse_fnc_loreClient; } else { _msg remoteExecCall ["comspec_sse_fnc_loreClient", _owner]; };
};
private _errText = {
    params ["_raw"];
    if (_raw isEqualTo "") exitWith { "liaison Athena coupée, ou COMSPEC Link absent du serveur" };
    private _c = (_raw splitString "|") param [1, _raw];
    switch (_c) do {
        case "unauthorized": { "serveur non relié à la communauté sur Athena (clé d'accès)" };
        case "forbidden": { "droit « Préparer le lore des sessions » requis sur Athena" };
        case "no_tenant": { "communauté inconnue d'Athena" };
        case "rate_limited": { "trop de demandes, réessayez dans une minute" };
        case "unavailable": { "Athena indisponible" };
        case "timeout": { "Athena ne répond pas (délai dépassé)" };
        default { _c };
    }
};
private _call = {
    // JSON -> [ok, id, ref | réponse brute]. Morceaux recollés par la DLL (Extension_Identity.cs, SubmitIdCheck ; 64
    // arguments au plus, « Async » en prend 2). Découpe en caractères (forceUnicode) : un nom accentué n'est jamais coupé
    // au milieu d'un caractère UTF-8 ; 3 000 caractères par morceau, soit 9 000 octets au pire.
    params ["_json"];
    forceUnicode 1;
    private _parts = [];
    for "_i" from 0 to ((count _json) - 1) step 3000 do { _parts pushBack (_json select [_i, 3000]); };
    if ((count _parts) > 60) exitWith { [false, 0, "ERR|too_large"] };
    private _raw = ["SubmitIdCheck", _parts] call comspec_sse_fnc_extensionCall;
    if (_raw isEqualType []) then { _raw = _raw param [0, ""]; };
    if !(_raw isEqualType "") then { _raw = str _raw; };
    if ((_raw select [0, 3]) isNotEqualTo "OK|") exitWith { [false, 0, _raw] };
    private _rest = _raw select [3];
    private _i = _rest find "|";
    if (_i < 0) exitWith { [true, parseNumber _rest, ""] };
    [true, parseNumber (_rest select [0, _i]), _rest select [_i + 1]]
};
// IA visées. synced : objets synchronisés au module Eden (et leur groupe) ; group : groupe de l'unité sous le module Zeus.
private _targets = {
    params ["_spec"];
    private _scope = (_spec getOrDefault ["scope", "all"]) call _str;
    private _given = (_spec getOrDefault ["units", []]) select { _x isEqualType objNull && {!isNull _x} };
    private _list = switch (_scope) do {
        case "selection": { _given };
        case "group";
        case "synced": { private _o = []; { _o append (units group _x); } forEach (_given select { _x isKindOf "CAManBase" }); _o };
        case "sse": {
            allUnits select { (_x getVariable ["comspec_sse_siteManaged", false]) || {(_x getVariable ["comspec_sse_siteId", ""]) isNotEqualTo ""}
                || {_x getVariable ["comspec_sse_enabled", false]} || {!isNil { _x getVariable "comspec_sse_data" }} }
        };
        default { allUnits };
    };
    (_list arrayIntersect _list) select { alive _x && {_x isKindOf "CAManBase"} && {!isPlayer _x} && {(getPlayerUID _x) isEqualTo ""} && {!(_x in playableUnits)} }
};

// ---------------------------------------------------------------- redescente : validation stricte
private _keysKnown = ["last", "first", "sex", "dob", "birth_place", "nationality", "profession", "aliases", "height_cm", "weight_kg", "build",
    "hair", "eyes", "marks", "addresses", "vehicles", "phones", "cautions", "flags", "affiliation", "country", "in_files"];
private _dec = {
    // Texte du site : "" (guillemet, déjà rendu par parseSimpleArray), \n retour à la ligne, \p barre verticale.
    params ["_s", ["_max", 500]];
    _s = [[_s, "\n", _nl] call CBA_fnc_replace, "\p", "|"] call CBA_fnc_replace;
    toString (((toArray _s) select { _x >= 32 || {_x isEqualTo 10} }) select [0, _max])
};
private _val = {
    // Valeur sûre : texte, nombre fini, booléen, tableau (20 éléments) de scalaires ou de petits tableaux (8 scalaires).
    params ["_v", ["_depth", 0]];
    if (_v isEqualType "") exitWith { [_v] call _dec };
    if (_v isEqualType true) exitWith { _v };
    if (_v isEqualType 0) exitWith { if (finite _v) then { _v } else { nil } };
    if (_v isEqualType [] && {_depth < 2}) exitWith {
        private _o = [];
        { private _w = [_x, _depth + 1] call _val; if (!isNil "_w" && {!(_w isEqualType [] && {_depth > 0})}) then { _o pushBack _w; }; } forEach (_v select [0, [20, 8] select (_depth > 0)]);
        _o
    };
    nil
};
private _pairs = {
    // [[clé, valeur]…] -> HashMap ; _allowed vide : toute clé courte (caché MJ).
    params ["_in", ["_allowed", []], ["_maxN", 40]];
    private _h = createHashMap;
    if !(_in isEqualType []) exitWith { _h };
    {
        if (_x isEqualType [] && {(count _x) isEqualTo 2} && {(_x select 0) isEqualType ""}) then {
            private _k = _x select 0;
            if ((count _k) > 0 && {(count _k) <= 40} && {_allowed isEqualTo [] || {_k in _allowed}}) then {
                private _v = [_x select 1] call _val;
                if (!isNil "_v") then { _h set [_k, _v]; };
            };
        };
    } forEach (_in select [0, _maxN]);
    _h
};
private _toPairs = { params ["_h"]; (keys _h) apply { [_x, _h get _x] } };

// ---------------------------------------------------------------- application à une unité
private _apply = {
    params ["_u", "_e"];
    if (isNull _u) exitWith {};
    _u setVariable ["comspec_sse_lore", [_e get "known"] call _toPairs, true];
    _u setVariable ["comspec_sse_loreAt", _e get "at", true];
    _u setVariable ["comspec_sse_loreAnswers", +(_e get "answers")];
    if ((count (_e get "hidden")) > 0) then { _u setVariable ["comspec_sse_loreHidden", [_e get "hidden"] call _toPairs]; } else { _u setVariable ["comspec_sse_loreHidden", nil]; };
    // Profil RP : réponses d'Athena ajoutées (questions perso « question : réponse », phrases), cadre evoke / banned.
    if (isNil "comspec_sse_fnc_iarpProfile") exitWith {};
    private _ans = _e get "answers";
    if ((count _ans) isEqualTo 0 && {(_u getVariable ["comspec_sse_loreAnsKeys", []]) isEqualTo []} && {(_u getVariable ["comspec_sse_lorePhrases", []]) isEqualTo []}) exitWith {};
    private _p = ["normalize", _u getVariable ["comspec_sse_iarpProfile", createHashMap]] call comspec_sse_fnc_iarpProfile;
    private _oldK = _u getVariable ["comspec_sse_loreAnsKeys", []];
    private _oldP = _u getVariable ["comspec_sse_lorePhrases", []];
    private _entries = (_p get "answers") select { !((_x param [0, ""]) in _oldK) };
    private _phrases = (_p get "phrases") - _oldP;
    private _newK = [];
    private _newP = [];
    private _list = _ans getOrDefault ["answers", []];
    if (_list isEqualType "") then { _list = _list splitString _nl; };
    if !(_list isEqualType []) then { _list = []; };
    {
        private _q = "";
        private _r = "";
        if (_x isEqualType [] && {(count _x) >= 2}) then { _q = (_x select 0) call _str; _r = (_x select 1) call _str; };
        if (_x isEqualType "") then {
            private _i = _x find ":";
            if (_i > 0) then { _q = trim (_x select [0, _i]); _r = trim (_x select [_i + 1]); } else { _r = trim _x; };
        };
        _q = trim (_q select [0, 60]);
        _r = trim (_r select [0, 300]);
        call {
            if (_r isEqualTo "") exitWith {};
            if (_q isEqualTo "") exitWith { if ((count _phrases) < 30) then { _phrases pushBackUnique _r; _newP pushBackUnique _r; }; };
            if (({ ((_x param [0, ""]) select [0, 1]) isEqualTo "c" } count _entries) >= 20) exitWith {};
            private _k = ["newKey", _entries] call comspec_sse_fnc_iarpAnswers;
            _entries = ["set", [_entries, [_k, _q, [_q], "random", [["*", [_r]]], 0, ""]]] call comspec_sse_fnc_iarpAnswers;
            if ((_entries findIf { (_x param [0, ""]) isEqualTo _k }) >= 0) then { _newK pushBack _k; };
        };
    } forEach (_list select [0, 20]);
    _p set ["answers", _entries];
    _p set ["phrases", _phrases];
    private _ev = (_ans getOrDefault ["evoke", ""]) call _str;
    if (_ev in ["auto", "none", "known", "free"]) then { _p set ["evoke", _ev]; };
    private _ban = _ans getOrDefault ["banned", []];
    if (_ban isEqualType "") then { _ban = (_ban splitString ",; ") apply { trim _x }; };
    if (_ban isEqualType [] && {_ban isNotEqualTo []}) then { _p set ["banned", (_ban select { _x isEqualType "" }) arrayIntersect ["armed", "explosives", "military", "wanted", "drugs", "politics"]]; };
    ["apply", [_u, _p]] call comspec_sse_fnc_iarpProfile;
    _u setVariable ["comspec_sse_loreAnsKeys", _newK];
    _u setVariable ["comspec_sse_lorePhrases", _newP];
};
private _clearUnit = {
    params ["_u"];
    { _u setVariable [_x, nil, true]; } forEach ["comspec_sse_lore", "comspec_sse_loreAt"];
    { _u setVariable [_x, nil]; } forEach ["comspec_sse_loreAnswers", "comspec_sse_loreHidden"];
};

// ---------------------------------------------------------------- opérations sans demandeur
if (_op isEqualTo "init") exitWith {
    if (_remote) exitWith { false };
    private _o = [createHashMap] call _opOf;
    if (_o isNotEqualTo "") then { [_o] call _publish; };
    true
};
if (_op isEqualTo "attach") exitWith {
    if (_remote) exitWith { false };
    _a params [["_u", objNull, [objNull]]];
    private _uid = ["assign", _u] call comspec_sse_fnc_loreUid;
    if (_uid isEqualTo "") exitWith { false };
    private _e = _reg getOrDefault [_uid, createHashMap];
    if ((count _e) > 0) then { [_u, _e] call _apply; };
    true
};
if (_op isEqualTo "licence") exitWith {
    _a params [["_who", objNull, [objNull]]];
    if (isNull _who || {_remote && {(owner _who) isNotEqualTo _ro}}) exitWith { false };
    missionNamespace setVariable ["comspec_sse_loreLicence", true];
    true
};

// ---------------------------------------------------------------- demandes (Zeus, administrateur, module Eden)
private _owner = 0;
private _spec = createHashMap;
if (_op isEqualTo "eden") then {
    if (_remote) exitWith { _op = ""; };
    _spec = _a param [0, createHashMap];
} else {
    _a params [["_me", objNull, [objNull]], ["_s", createHashMap, [createHashMap]]];
    if (isNull _me || {_remote && {(owner _me) isNotEqualTo _ro}} || {!([_me] call comspec_sse_fnc_isAuthority)}) exitWith {
        diag_log text format ["[COMSPEC SSE] Lore Athena : « %1 » refusé au poste %2 (ni Zeus ni administrateur).", _op, _ro];
        _op = "";
    };
    // Solo : poste 2 (remoteExecCall vers 2 s'exécute ici).
    _owner = [_ro, 2] select (!isMultiplayer && {_ro <= 0});
    _spec = _s;
};
if (_op isEqualTo "") exitWith { false };
if !(_spec isEqualType createHashMap) then { _spec = createHashMap; };
private _session = [_spec] call _opOf;
if (_op isEqualTo "status") exitWith {
    if (_session isNotEqualTo "") then { [_session] call _publish; };
    [_owner, ["code d'opération à régler (module ou COMSPEC SSE › Identité › Opération Athena).", format ["session %1.", _session]] select (_session isNotEqualTo ""), "info"] call _reply;
    true
};
if (_session isEqualTo "") exitWith {
    [_owner, "code d'opération absent ou invalide (2 à 64 caractères : lettres, chiffres, _ . : -). Exemple : OP-SOMBRE-LAC-2.", "error"] call _reply;
    false
};
if (!canSuspend) exitWith { [_op, _a, _owner] spawn comspec_sse_fnc_loreServer; true };

// Serveur dédié au démarrage (module Eden) : on attend qu'un joueur connecté ait accepté la licence (10 min au plus).
if (_op isEqualTo "eden" && {isDedicated}) then {
    private _t1 = diag_tickTime;
    waitUntil { sleep 2; (call _licOk) || {diag_tickTime - _t1 > 600} };
};

// Une opération à la fois (envoi et redescente lisent et écrivent le même registre).
private _t0 = diag_tickTime;
waitUntil { sleep 0.2; !(missionNamespace getVariable ["comspec_sse_loreBusy", false]) || {diag_tickTime - _t0 > 120} };
missionNamespace setVariable ["comspec_sse_loreBusy", true];

private _push = {
    private _units = [_spec] call _targets;
    if (_units isEqualTo []) exitWith { [_owner, "aucune IA dans la portée choisie : rien à envoyer.", "warn"] call _reply; [_session, "push", "aucune IA"] call _remember; false };
    private _cut = (count _units) > 500;
    _units = _units select [0, 500];
    // Fiches en JSON, puis lots : 25 personnes au plus (contrat) et 150 000 caractères au plus (50 morceaux de 3 000,
    // marge sous les 62 arguments laissés par « Async ») ; une fiche bornée fait 6 000 caractères au pire.
    private _rows = [];
    { private _p = [_x] call comspec_sse_fnc_lorePerson; if ((count _p) > 0) then { _rows pushBack ([_p] call comspec_sse_fnc_toJsonApprox); }; } forEach _units;
    private _batches = [];
    private _cur = [];
    private _len = 0;
    {
        private _l = count _x;
        if (_l > 140000) then { continue };
        if (_cur isNotEqualTo [] && {(count _cur) >= 25 || {_len + _l > 150000}}) then { _batches pushBack _cur; _cur = []; _len = 0; };
        _cur pushBack _x;
        _len = _len + _l + 1;
    } forEach _rows;
    if (_cur isNotEqualTo []) then { _batches pushBack _cur; };
    private _stampKey = (systemTimeUTC apply { str _x }) joinString "";
    private _sum = [0, 0, 0];
    private _locked = false;
    private _err = "";
    private _sent = 0;
    {
        private _head = createHashMapFromArray [
            ["kind", "lore_push"], ["idempotency_key", format ["lore-%1-%2-%3", _stampKey, floor random 10000, _forEachIndex + 1] select [0, 80]],
            ["op", _session], ["batch", [_forEachIndex + 1, count _batches]],
            ["mission", createHashMapFromArray [["title", (briefingName select [0, 120])], ["world", worldName], ["country", ["default"] call comspec_sse_fnc_idCountries]]]
        ];
        private _hj = [_head] call comspec_sse_fnc_toJsonApprox;
        private _json = (_hj select [0, (count _hj) - 1]) + ",""persons"":[" + (_x joinString ",") + "]}";
        private _r = [_json] call _call;
        if !(_r select 0) exitWith { _err = [_r select 2] call _errText; };
        // ref : ok;créées;complétées;ignorées;verrou
        private _f = (_r select 2) splitString ";";
        if ((_f param [0, ""]) isNotEqualTo "ok") exitWith { _err = format ["réponse inattendue (%1)", (_r select 2) select [0, 60]]; };
        { _sum set [_forEachIndex, (_sum select _forEachIndex) + ((parseNumber (_f param [_forEachIndex + 1, "0"])) max 0)]; } forEach [0, 0, 0];
        if ((_f param [4, "0"]) isEqualTo "1") then { _locked = true; };
        _sent = _sent + (count _x);
        sleep 0.3;
    } forEach _batches;
    private _txt = format ["envoi %1 : %2 IA envoyées (%3 lot(s)) · créées %4 · complétées %5 · ignorées %6%7%8%9.", _session, _sent, count _batches,
        _sum select 0, _sum select 1, _sum select 2,
        ["", " · session VERROUILLÉE sur Athena : rien n'a été modifié"] select _locked,
        ["", " · limite de 500 IA atteinte"] select _cut,
        ["", format [" · ERREUR : %1", _err]] select (_err isNotEqualTo "")];
    [_session, "push", _txt] call _remember;
    [_session] call _publish;
    [_owner, _txt, ["info", "warn"] select (_err isNotEqualTo "" || {_locked})] call _reply;
    _err isEqualTo ""
};

private _pull = {
    private _page = 0;
    private _got = createHashMap;
    private _meta = [];
    private _err = "";
    private _done = false;
    private _guard = 0;
    while { !_done && {_guard < 60} } do {
        _guard = _guard + 1;
        private _r = [format ['{"kind":"lore_pull","op":%1,"page":%2}', [_session] call comspec_sse_fnc_toJsonApprox, _page]] call _call;
        if !(_r select 0) exitWith { _err = [_r select 2] call _errText; };
        private _ref = trim (_r select 2);
        if ((count _ref) > 8000 || {(_ref select [0, 1]) isNotEqualTo "["} || {(_ref select [(count _ref) - 1, 1]) isNotEqualTo "]"}) exitWith { _err = "réponse illisible"; };
        private _arr = parseSimpleArray _ref;
        if (isNil "_arr" || {!(_arr isEqualType [])} || {(count _arr) isNotEqualTo 3}) exitWith { _err = "réponse illisible"; };
        _arr params [["_m", []], ["_rows", []], ["_next", 0]];
        if (!(_m isEqualType []) || {!(_rows isEqualType [])} || {!(_next isEqualType 0)}) exitWith { _err = "réponse au mauvais format"; };
        if (_page isEqualTo 0) then {
            _m params [["_sid", ""], ["_phase", 0], ["_lock", false], ["_srvAt", ""], ["_modAt", ""], ["_refresh", 0], ["_title", ""]];
            // Valeurs du site vérifiées avant tout calcul (un tableau SQF évalue toutes ses cases).
            private _num = { if (_this isEqualType 0 && {finite _this}) then { round _this } else { 0 } };
            _meta = [
                [(_title call _str), 120] call _dec,
                _phase call _num,
                (_lock isEqualTo true) || {_lock isEqualTo 1},
                ((_refresh call _num) max 0) min 86400,
                [(_modAt call _str), 40] call _dec
            ];
        };
        {
            if ((count _got) >= 500) exitWith {};
            if (_x isEqualType [] && {(count _x) >= 5} && {(_x select 0) isEqualType ""}) then {
                private _uid = ["clean", _x select 0] call comspec_sse_fnc_loreUid;
                if ((count _uid) >= 2 && {_uid isEqualTo (_x select 0)}) then {
                    _got set [_uid, createHashMapFromArray [
                        ["ref", [((_x select 1) call _str), 40] call _dec],
                        ["known", [_x select 2, _keysKnown, 30] call _pairs],
                        ["answers", [_x select 3, ["answers", "evoke", "banned"], 3] call _pairs],
                        ["hidden", [_x select 4, [], 40] call _pairs],
                        ["at", call _stamp]
                    ]];
                };
            };
        } forEach (_rows select [0, 100]);
        if (_next <= _page || {(count _got) >= 500}) then { _done = true; } else { _page = round _next; sleep 0.5; };
    };
    if (_err isNotEqualTo "" && {(count _got) isEqualTo 0}) exitWith {
        private _txt = format ["récupération %1 : ERREUR : %2.", _session, _err];
        [_session, "pull", _txt] call _remember;
        [_session] call _publish;
        [_owner, _txt, "error"] call _reply;
        false
    };
    // Registre remplacé (lecture complète) ou complété (lecture interrompue), puis rattachement aux IA présentes.
    if (_done) then { { _reg deleteAt _x; } forEach ((keys _reg) - (keys _got)); };
    { _reg set [_x, _y]; } forEach _got;
    { ["assign", _x] call comspec_sse_fnc_loreUid; } forEach (allUnits select { !isPlayer _x && {_x isKindOf "CAManBase"} });
    private _uids = missionNamespace getVariable ["comspec_sse_loreUids", createHashMap];
    private _n = 0;
    {
        private _u = _uids getOrDefault [_x, objNull];
        if (!isNull _u) then { [_u, _y] call _apply; _n = _n + 1; };
    } forEach _reg;
    // Personnes retirées sur Athena : plus rien sur l'unité.
    if (_done) then { { private _u = _y; if (!isNull _u && {!(_x in _reg)} && {!isNil { _u getVariable "comspec_sse_lore" }}) then { [_u] call _clearUnit; }; } forEach _uids; };
    if (_meta isNotEqualTo []) then { missionNamespace setVariable ["comspec_sse_loreSiteRefresh", _meta select 3]; };
    private _txt = format ["récupération %1 : %2 personne(s) sur Athena, %3 rattachée(s) à une IA de la partie%4%5%6.", _session, count _got, _n,
        ["", format [" · « %1 », phase %2", _meta param [0, ""], _meta param [1, 0]]] select (_meta isNotEqualTo []),
        ["", " · session verrouillée"] select (_meta param [2, false]),
        ["", format [" · lecture interrompue : %1", _err]] select (_err isNotEqualTo "")];
    [_session, "pull", _txt] call _remember;
    [_session, [_meta param [0, ""], _meta param [1, 0], _meta param [2, false]]] call _publish;
    [_owner, _txt, ["info", "warn"] select (_err isNotEqualTo "")] call _reply;
    true
};

private _auto = {
    params ["_sec"];
    if !(_sec isEqualType 0) then { _sec = 0; };
    _sec = [0, (round _sec) max 60] select (_sec > 0);
    private _tok = (missionNamespace getVariable ["comspec_sse_loreAutoTok", 0]) + 1;
    missionNamespace setVariable ["comspec_sse_loreAutoTok", _tok];
    missionNamespace setVariable ["comspec_sse_loreAutoS", _sec];
    [_session] call _publish;
    if (_sec isEqualTo 0) exitWith { [_owner, "rafraîchissement automatique arrêté.", "info"] call _reply; };
    [_owner, format ["rafraîchissement automatique toutes les %1 s (session %2).", _sec, _session], "info"] call _reply;
    [_tok, _sec, _session] spawn {
        params ["_tok", "_sec", "_session"];
        while { (missionNamespace getVariable ["comspec_sse_loreAutoTok", 0]) isEqualTo _tok } do {
            // Intervalle proposé par Athena (rafraîchissement_s) : respecté s'il est plus long.
            sleep (_sec max (missionNamespace getVariable ["comspec_sse_loreSiteRefresh", 0]));
            if ((missionNamespace getVariable ["comspec_sse_loreAutoTok", 0]) isNotEqualTo _tok) exitWith {};
            ["eden", [createHashMapFromArray [["op", _session], ["pull", true], ["quiet", true]]]] call comspec_sse_fnc_loreServer;
        };
    };
};

private _ok = false;
call {
    if !(call _licOk) exitWith {
        [_owner, "licence COMSPEC non acceptée sur ce poste : aucun envoi vers Athena.", "error"] call _reply;
    };
    switch (_op) do {
        case "push": { _ok = call _push; };
        case "pull": { _ok = call _pull; };
        case "both": { _ok = call _push; _ok = (call _pull) && {_ok}; };
        case "auto": { [_spec getOrDefault ["interval", 0]] call _auto; _ok = true; };
        case "eden": {
            if (_spec getOrDefault ["push", false]) then { _ok = call _push; };
            if (_spec getOrDefault ["pull", false]) then { _ok = call _pull; };
        };
    };
};
missionNamespace setVariable ["comspec_sse_loreBusy", false];
// Module Eden : rafraîchissement automatique lancé après la première synchro (hors appels de la boucle elle-même).
if (_op isEqualTo "eden" && {!(_spec getOrDefault ["quiet", false])} && {call _licOk} && {((_spec getOrDefault ["interval", 0]) call { [0, _this] select (_this isEqualType 0) }) > 0}) then {
    [_spec get "interval"] call _auto;
};
_ok
