/*
    App « Avis et vigilance » : avis de recherche, notices de couleur et fiches de vigilance du portail SSE, lus sur le téléphone
    (ICD-SSE-ALERTES-001). Données, cache, filtres, lecture, partage et navigation ; les envois (émettre, signaler, renouveler, lever,
    accuser lecture) sont dans fn_alertesSend, l'assistant d'émission dans fn_alertesDraft, les écrans dans fn_pageAlertes.
    Canal : SubmitIdCheck de COMSPEC Link (champ kind « sse_alertes », réponse OK|id|page), le même que l'app Fiches : aucune nouvelle DLL.
    Le site filtre selon l'habilitation du porteur (jeton de jeu) : une alerte au-dessus n'arrive jamais ici.
    Aucun toast, aucun hint, aucun systemChat : les retours passent par la ligne d'état de l'écran (« alertesStatus ») ; un nouvel avis de
    niveau élevé et la réponse du bureau à une proposition vont au centre de notifications du téléphone, en vibreur seul.

    Données (uiNamespace COMSPEC_ATAK_Alertes, HashMap) : list (HashMaps, colonnes de "cols"), raw, rev, at, sync, clock, ttl,
    meta [niveau de lecture, marquage, total, tronqué], src ("site" | "poste" | ""), err, busy, avail ("" | "yes" | "no" : le serveur
    connaît-il les alertes), cat (section -> lignes du catalogue), catRev, catAt, rights [émettre une vigilance, placer un avis, lever,
    signaler], more (réf -> détail), qr (adresse -> rangées), seeded. Hors ligne : la dernière version est gardée sur le poste
    (profileNamespace COMSPEC_ATAK_AlertesCache) pour 24 h, SEULEMENT les alertes du niveau le plus bas (« interne ») : une alerte plus
    sensible ne quitte jamais la mémoire de la partie.

    Params : [action, argument]
      ["cols"]                 colonnes d'une alerte, dans l'ordre du site (SseAlertGameService::COLUMNS)
      ["ensure"]               relit le site si la liste est absente ou plus vieille que sa durée de validité (tâche de fond)
      ["tick"]                 toutes les 60 s : file d'envoi vidée au retour du réseau, relecture si périmée
      ["load", forcer]         lecture du catalogue puis de la liste (tâche de fond) ; forcer = true : ACTUALISER
      ["detail", réf]          relit une alerte en entier (motifs, liens, signalement, QR code de l'adresse de signalement)
      ["get", réf] / ["list"]  l'alerte (HashMap, vide si inconnue) / toutes, déjà triées par le site
      ["more", réf]            détail gardé de l'alerte (HashMap, vide tant qu'il n'est pas lu)
      ["age"]                  minutes depuis la dernière lecture réussie, -1 si jamais
      ["ageOf", texte]         minutes depuis une date « AAAA-MM-JJ HH:MM » (UTC), -1 si illisible
      ["open", réf] / ["back"] / ["openRef", réf] / ["view", vue] / ["home"]
      ["filter", [clé, valeur]] type | zone | armed ; ["clearFilters"]
      ["isRead", alerte] / ["badge"]   lu sur ce poste ou accusé sur le site / nombre de non lus
      ["cat", section] / ["label", [section, clé]] / ["level", clé] / ["color", clé] / ["rights"] / ["lang"]
      ["gate"]                 "" si le téléphone peut parler à Athena, sinon la raison (bridge | local | offline | paused)
      ["gateText", raison]     la raison en clair
      ["qr", adresse]          rangées du QR code (calculées une fois par adresse)
      ["map", réf]             carte centrée sur le dernier lieu connu, quand il se retrouve sur la carte
      ["place", réf]           position [x, y] du dernier lieu connu, [] sinon
      ["ack", réf]             accuse lecture
      ["days", n] / ["renewSend"] / ["liftSend"]   durée du renouvellement, renouveler, lever (motif obligatoire)
      ["share", réf] / ["shareBt", [réf, uid]] / ["shareSms", [réf, nom]]   seule la référence part
      ["status", texte]        pose la ligne d'état  ·  ["title", réf] titre court d'une alerte
      ["call", json]           une commande du canal (texte brut de la DLL)  ·  ["page", [brut, op]] la page lue, [] si illisible
      ["errText", brut]        l'erreur en clair
*/
params [["_act", "list"], ["_arg", ""]];
private _cols = [
    "ref", "doc", "type", "color", "rank", "level", "order", "name", "alias", "threat", "armed", "conduct", "reason", "zone", "place",
    "issued", "until", "soon", "service", "svc", "channel", "delay", "url", "token", "state", "acked", "updated", "cls", "mark", "cut"
];
if (_act isEqualTo "cols") exitWith { _cols };

private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _store = uiNamespace getVariable ["COMSPEC_ATAK_Alertes", createHashMap];
if ((count _store) isEqualTo 0) then {
    _store = createHashMapFromArray [["list", []], ["raw", []], ["rev", ""], ["at", -1], ["sync", []], ["clock", ""], ["ttl", 300], ["meta", []],
        ["src", ""], ["err", ""], ["busy", false], ["avail", ""], ["cat", createHashMap], ["catRev", ""], ["catAt", -1],
        ["rights", [false, false, false, false]], ["more", createHashMap], ["qr", createHashMap], ["seeded", false]];
    uiNamespace setVariable ["COMSPEC_ATAK_Alertes", _store];
};
private _render = {
    [{
        if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "SSEALERT") then {
            ["SSEALERT"] call comspec_atak_native_fnc_pageRender;
        };
    }] call comspec_atak_native_fnc_nextFrame;
};
private _say = {
    params ["_t"];
    (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) set ["alertesStatus", [_t, diag_tickTime]];
};
// Minutes écoulées depuis l'an 1970 (calendrier grégorien : algorithme des jours civils).
private _epochMin = {
    params [["_t", []]];
    if ((count _t) < 5) exitWith { -1 };
    _t params ["_y", "_m", "_d", "_h", "_mi"];
    private _yy = [_y, _y - 1] select (_m <= 2);
    private _era = floor (_yy / 400);
    private _yoe = _yy - _era * 400;
    private _mp = (_m + 9) mod 12;
    private _doy = floor ((153 * _mp + 2) / 5) + _d - 1;
    private _doe = _yoe * 365 + floor (_yoe / 4) - floor (_yoe / 100) + _doy;
    ((_era * 146097 + _doe - 719468) * 1440) + _h * 60 + _mi
};
// Texte du site : « \p » est une barre verticale, « \n » un retour à la ligne.
private _dec = {
    params ["_t"];
    if !(_t isEqualType "") exitWith { "" };
    (_t regexReplace ["\\p", "|"]) regexReplace ["\\n", toString [10]]
};
// Ligne brute du site -> HashMap des colonnes (texte décodé, listes de textes décodées, nombres et booléens gardés).
private _rowMap = {
    params ["_r"];
    private _m = createHashMap;
    {
        private _v = _r param [_forEachIndex, ""];
        if (_v isEqualType "") then { _v = [_v] call _dec; };
        if (_v isEqualType []) then { _v = (_v select { _x isEqualType "" }) apply { [_x] call _dec }; };
        _m set [_x, _v];
    } forEach _cols;
    // Alias et conduite à tenir : toujours des listes ; rang : toujours un nombre de 1 à 4.
    { if ((_m get _x) isEqualType "") then { _m set [_x, [[_m get _x], []] select ((_m get _x) isEqualTo "")]; }; } forEach ["alias", "conduct"];
    private _rk = _m get "rank";
    if (_rk isEqualType "") then { _rk = parseNumber _rk; };
    if !(_rk isEqualType 0) then { _rk = 1; };
    _m set ["rank", ((floor _rk) max 1) min 4];
    _m
};
// Texte envoyé au site dans une lecture : lettres, chiffres et quelques signes seulement (jamais de guillemet ni de barre inverse).
private _clean = {
    params ["_t", ["_max", 40]];
    if !(_t isEqualType "") exitWith { "" };
    ((_t regexReplace ["[^A-Za-z0-9 ._/:+-]", " "]) select [0, _max])
};
// Réponse « OK|id|page » de COMSPEC Link -> la page (tableau) de l'opération attendue, [] si illisible.
private _parsePage = {
    params ["_raw", ["_op", ""]];
    if !(_raw isEqualType "") then { _raw = str _raw; };
    if ((_raw select [0, 3]) isNotEqualTo "OK|") exitWith { [] };
    private _rest = _raw select [3];
    private _cut = _rest find "|";
    if (_cut < 0) exitWith { [] };
    private _text = _rest select [_cut + 1];
    if ((_text select [0, 1]) isNotEqualTo "[") exitWith { [] };
    private _p = parseSimpleArray _text;
    if (!(_p isEqualType []) || {(count _p) < 3} || {(_p select 1) isNotEqualTo _op}) exitWith { [] };
    _p
};
// Une commande SubmitIdCheck (JSON d'un seul morceau) ; renvoie le texte brut de la DLL.
private _call = {
    params ["_json"];
    private _res = if (canSuspend) then {
        (["COMSPECExtension", "SubmitIdCheck", [_json], 30] call comspec_atak_native_fnc_extAwait) param [0, ""]
    } else {
        "COMSPECExtension" callExtension ["SubmitIdCheck", [_json]]
    };
    if (_res isEqualType []) then { _res = _res param [0, ""]; };
    if (!isNil "comspec_overwatch_connect_fnc_extResult") then { _res = [_res] call comspec_overwatch_connect_fnc_extResult; };
    if (isNil "_res") then { _res = ""; };
    if !(_res isEqualType "") then { _res = str _res; };
    _res
};
// Message d'erreur lisible. COMSPEC Link rend « unauthorized » pour tout refus 403 (compte non relié comme droit manquant), « http_409 »
// pour une alerte close, le code du site pour une saisie refusée (422).
private _errText = {
    params ["_raw"];
    switch (true) do {
        case (_raw isEqualTo ""): { "COMSPEC Link ne répond pas." };
        case ((_raw find "uplink_hold") >= 0): { "Liaison Athena coupée." };
        case ((_raw find "unauthorized") >= 0): { "Compte Athena non relié à ce jeu, ou droit manquant." };
        case ((_raw find "forbidden") >= 0): { "Votre profil n'a pas ce droit." };
        case ((_raw find "no_tenant") >= 0): { "Compte Athena non relié à ce jeu." };
        case ((_raw find "rate_limited") >= 0): { "Athena est occupé : réessayez dans une minute." };
        case ((_raw find "not_found") >= 0): { "Alerte introuvable, ou au-dessus de votre habilitation." };
        case ((_raw find "http_409") >= 0 || {(_raw find "closed") >= 0}): { "Alerte déjà levée ou expirée." };
        case ((_raw find "unavailable") >= 0 || {(_raw find "maintenance") >= 0}): { "Athena ne répond pas pour l'instant." };
        case ((_raw find "invalid") >= 0): { "Athena a refusé la saisie : vérifiez les champs." };
        case ((_raw find "unscheduled") >= 0): { "Envoi relancé en tâche de fond." };
        default { format ["Athena a refusé (%1).", _raw select [0, 40]] };
    }
};
private _en = { ("Oui" call comspec_atak_native_fnc_tr) isNotEqualTo "Oui" };
private _lang = { ["fr", "en"] select (call _en) };
// Le téléphone peut-il parler à Athena ? "" : oui ; sinon la raison.
private _gate = {
    if !([] call comspec_atak_native_fnc_bridge) exitWith { "bridge" };
    if (([] call comspec_atak_native_fnc_athenaMode) isEqualTo "off") exitWith { "local" };
    if !(["online"] call comspec_atak_native_fnc_offline) exitWith { "offline" };
    if (!isNil "comspec_overwatch_connect_fnc_linkGate" && {["active"] call comspec_overwatch_connect_fnc_linkGate}) exitWith { "paused" };
    ""
};
private _hexRgb = {
    params ["_h"];
    private _a = toArray (toLower _h);
    if ((count _a) isNotEqualTo 7) exitWith { [0.8, 0.85, 0.82, 1] };
    private _n = (_a select [1, 6]) apply { [_x - 48, _x - 87] select (_x >= 97) };
    if ((_n findIf { _x < 0 || {_x > 15} }) >= 0) exitWith { [0.8, 0.85, 0.82, 1] };
    [((_n select 0) * 16 + (_n select 1)) / 255, ((_n select 2) * 16 + (_n select 3)) / 255, ((_n select 4) * 16 + (_n select 5)) / 255, 1]
};
// Catalogue par défaut (niveaux, couleurs, délais, certitude : ICD § 3.1) tant que celui du site n'est pas lu.
private _catDefault = {
    createHashMapFromArray [
        ["level", [
            ["watch", "VIGILANCE", "BE ON THE LOOKOUT", 1, "#F2C200", "triangle"],
            ["reinforced", "VIGILANCE RENFORCÉE", "HEIGHTENED VIGILANCE", 2, "#F08C00", "triangle"],
            ["caution", "APPROCHE AVEC PRÉCAUTION", "APPROACH WITH CAUTION", 3, "#D9480F", "diamond"],
            ["danger", "DANGER — ARMÉ ET DANGEREUX", "DANGER — ARMED AND DANGEROUS", 4, "#B0121F", "octagon"]
        ]],
        ["color", [
            ["red", "Rouge", "Red", "#C8102E"], ["blue", "Bleue", "Blue", "#1F5FBF"], ["green", "Verte", "Green", "#1E8E4A"],
            ["yellow", "Jaune", "Yellow", "#E0B400"], ["orange", "Orange", "Orange", "#E8730C"], ["black", "Noire", "Black", "#3A3F45"]
        ]],
        ["delay", [["now", "Immédiat", "Immediately"], ["15m", "Sous 15 min", "Within 15 min"], ["1h", "Sous 1 h", "Within 1 h"],
            ["4h", "Sous 4 h", "Within 4 h"], ["24h", "Sous 24 h", "Within 24 h"]]],
        ["sure", [["low", "Incertain", "Unsure"], ["medium", "Probable", "Likely"], ["high", "Certain", "Certain"]]],
        ["threat", [["low", "Faible", "Low"], ["medium", "Moyenne", "Medium"], ["high", "Élevée", "High"], ["critical", "Critique", "Critical"]]],
        ["armed", [["yes", "Armé", "Armed"], ["no", "Non armé", "Unarmed"], ["unknown", "Inconnu", "Unknown"]]],
        ["type", []], ["reason", []], ["measure", []], ["service", []]
    ]
};
private _catGet = {
    params ["_section"];
    private _rows = (_store get "cat") getOrDefault [_section, []];
    if (_rows isEqualTo []) then { _rows = (call _catDefault) getOrDefault [_section, []]; };
    _rows
};
// Lignes du catalogue (« section », colonnes…) -> HashMap section -> lignes sans leur section.
private _catApply = {
    params ["_lines"];
    private _c = createHashMap;
    {
        if (_x isEqualType [] && {(count _x) >= 3} && {(_x select 0) isEqualType ""}) then {
            private _row = (_x select [1]) apply { if (_x isEqualType "") then { [_x] call _dec } else { _x } };
            (_c getOrDefaultCall [_x select 0, { [] }, true]) pushBack _row;
        };
    } forEach _lines;
    _store set ["cat", _c];
};
private _applyRows = {
    params ["_rows", "_meta", "_rev", "_ttl", "_src"];
    _store set ["raw", _rows];
    _store set ["list", _rows apply { [_x] call _rowMap }];
    private _m = +_meta;
    { if (_x isEqualType "") then { _m set [_forEachIndex, [_x] call _dec]; }; } forEach _m;
    _store set ["meta", _m];
    _store set ["rev", _rev];
    _store set ["ttl", (_ttl max 120) min 1800];
    _store set ["src", _src];
};
private _readKey = { params ["_f"]; format ["%1@%2", _f getOrDefault ["ref", ""], _f getOrDefault ["updated", ""]] };
private _clock = {
    systemTime params ["", "", "", "_h", "_mi"];
    format ["%1:%2", [str _h, "0" + str _h] select (_h < 10), [str _mi, "0" + str _mi] select (_mi < 10)]
};

switch (_act) do {
    case "list": { _store get "list" };
    case "get": {
        private _i = (_store get "list") findIf { (_x get "ref") isEqualTo _arg };
        if (_i >= 0) exitWith { (_store get "list") select _i };
        // Alerte levée ou expirée, ou ouverte par sa référence : celle du détail gardé.
        ((_store get "more") getOrDefault [_arg, createHashMap]) getOrDefault ["row", createHashMap]
    };
    case "more": { (_store get "more") getOrDefault [_arg, createHashMap] };
    case "age": {
        if ((_store get "at") < 0 && {(count (_store get "sync")) isEqualTo 0}) exitWith { -1 };
        (([systemTimeUTC] call _epochMin) - ([_store get "sync"] call _epochMin)) max 0
    };
    case "ageOf": {
        if !(_arg isEqualType "") exitWith { -1 };
        private _n = (_arg splitString "-: T") apply { parseNumber _x };
        if ((count _n) < 3) exitWith { -1 };
        _n resize [5, 0];
        (([systemTimeUTC] call _epochMin) - ([_n] call _epochMin)) max 0
    };
    case "status": { [_arg] call _say; call _render; };
    case "lang": { call _lang };
    case "gate": { call _gate };
    case "gateText": {
        switch (_arg) do {
            case "bridge": { "COMSPEC Overwatch n'est pas chargé : les alertes viennent d'Athena par lui." };
            case "local": { "ATAK local : pas de liaison Athena." };
            case "offline": {
                private _why = ["why"] call comspec_atak_native_fnc_linkMode;
                format ["Hors réseau%1 : lecture de la dernière version, envois gardés en file.", ["", format [" (%1)", _why]] select (_why isEqualType "" && {_why isNotEqualTo ""})]
            };
            case "paused": { "Liaison Athena en pause (compte non lié) : envois gardés en file." };
            default { "" };
        }
    };
    case "errText": { [_arg] call _errText };
    case "call": { [_arg] call _call };
    case "page": { _arg params [["_raw", ""], ["_op", ""]]; [_raw, _op] call _parsePage };
    case "rights": { _store get "rights" };
    case "cat": { [_arg] call _catGet };
    case "label": {
        _arg params [["_section", ""], ["_key", ""]];
        private _row = (([_section] call _catGet) select { (_x param [0, ""]) isEqualTo _key }) param [0, []];
        if (_row isEqualTo []) exitWith { _key };
        private _t = _row param [[1, 2] select (call _en), ""];
        [_t, _key] select (!(_t isEqualType "") || {_t isEqualTo ""})
    };
    // Niveau de vigilance : [titre, rang, couleur #RRGGBB, couleur RGBA, forme (triangle | diamond | octagon)].
    case "level": {
        private _row = ((["level"] call _catGet) select { (_x param [0, ""]) isEqualTo _arg }) param [0, []];
        if (_row isEqualTo []) exitWith { ["", 0, "#b4c1bb", [0.7, 0.76, 0.73, 1], ""] };
        private _hex = _row param [4, "#b4c1bb"];
        if !(_hex isEqualType "") then { _hex = "#b4c1bb"; };
        private _rk = _row param [3, 1];
        if !(_rk isEqualType 0) then { _rk = parseNumber (str _rk); };
        [_row param [[1, 2] select (call _en), ""], _rk, _hex, [_hex] call _hexRgb, _row param [5, ""]]
    };
    // Couleur de notice : [libellé, couleur #RRGGBB, couleur RGBA] ; ["", "", []] pour un avis sans couleur.
    case "color": {
        private _row = ((["color"] call _catGet) select { (_x param [0, ""]) isEqualTo _arg }) param [0, []];
        if (_row isEqualTo []) exitWith { ["", "", []] };
        private _hex = _row param [3, "#b4c1bb"];
        if !(_hex isEqualType "") then { _hex = "#b4c1bb"; };
        [_row param [[1, 2] select (call _en), ""], _hex, [_hex] call _hexRgb]
    };
    case "kindLabel": { ["Fiche de vigilance", "Avis de recherche"] select (_arg isEqualTo "avis") };
    // Titre court pour un partage : le sujet seulement si l'alerte est de diffusion large (niveau le plus bas), sinon son genre.
    case "title": {
        private _f = ["get", _arg] call comspec_atak_native_fnc_alertesAction;
        if ((count _f) isEqualTo 0) exitWith { "" };
        if ((_f get "cls") isEqualTo "interne") exitWith { _f get "name" };
        ["kindLabel", _f get "doc"] call comspec_atak_native_fnc_alertesAction
    };
    case "isRead": {
        if !(_arg isEqualType createHashMap) exitWith { true };
        if ((_arg getOrDefault ["acked", false]) isEqualTo true) exitWith { true };
        ([_arg] call _readKey) in (profileNamespace getVariable ["COMSPEC_ATAK_AlertesRead", []])
    };
    case "badge": {
        private _read = profileNamespace getVariable ["COMSPEC_ATAK_AlertesRead", []];
        { !((_x getOrDefault ["acked", false]) isEqualTo true) && {!(([_x] call _readKey) in _read)} } count (_store get "list")
    };

    case "ensure": {
        if (!hasInterface) exitWith { false };
        // Parties précédentes : les alertes sont celles de la communauté du serveur du moment, jamais celles d'une mission d'avant.
        if (isNil { missionNamespace getVariable "COMSPEC_ATAK_AlertesMission" }) then {
            missionNamespace setVariable ["COMSPEC_ATAK_AlertesMission", true];
            _store set ["list", []];
            _store set ["raw", []];
            _store set ["at", -1];
            _store set ["rev", ""];
            _store set ["more", createHashMap];
            // Catalogue gardé sur le poste (codes et libellés : rien de sensible) : l'assistant s'ouvre même hors réseau.
            private _savedCat = profileNamespace getVariable ["COMSPEC_ATAK_AlertesCat", []];
            if (_savedCat isEqualType [] && {(count _savedCat) >= 2} && {(_savedCat select 1) isEqualType []}) then {
                [_savedCat select 1] call _catApply;
                _store set ["catRev", ""];
            };
            // Dernière version gardée sur le poste (niveau le plus bas seulement, 24 h) : montrée en attendant le site.
            private _saved = profileNamespace getVariable ["COMSPEC_ATAK_AlertesCache", []];
            if (_saved isEqualType [] && {(count _saved) >= 4} && {(_saved select 0) isEqualType []}) then {
                _saved params ["_when", "_rev", "_rows", "_meta"];
                private _age = (([systemTimeUTC] call _epochMin) - ([_when] call _epochMin));
                if (_age >= 0 && {_age <= 1440} && {_rows isEqualType []} && {_meta isEqualType []}) then {
                    [_rows, _meta, "", 300, "poste"] call _applyRows;
                    _store set ["sync", _when];
                    _store set ["clock", format ["%1:%2", [_when select 3, "0" + str (_when select 3)] select ((_when select 3) < 10), [_when select 4, "0" + str (_when select 4)] select ((_when select 4) < 10)]];
                } else {
                    profileNamespace setVariable ["COMSPEC_ATAK_AlertesCache", []];
                };
            };
            // Envois restés en file à la dernière partie, puis battement d'une minute (file, relecture).
            ["restore"] call comspec_atak_native_fnc_alertesSend;
            [{ ["tick"] call comspec_atak_native_fnc_alertesAction; }, [], 60] call comspec_atak_native_fnc_waitAndExec;
        };
        private _fresh = (_store get "src") isEqualTo "site" && {(diag_tickTime - (_store get "at")) < (_store get "ttl")};
        if (_fresh || {_store get "busy"}) exitWith { false };
        if (diag_tickTime < (missionNamespace getVariable ["COMSPEC_ATAK_AlertesRetryAt", -1])) exitWith { false };
        if ((call _gate) isNotEqualTo "") exitWith { false };
        ["load", false] spawn comspec_atak_native_fnc_alertesAction;
        true
    };
    case "tick": {
        if (!hasInterface) exitWith { false };
        [{ ["tick"] call comspec_atak_native_fnc_alertesAction; }, [], 60] call comspec_atak_native_fnc_waitAndExec;
        if ((call _gate) isNotEqualTo "") exitWith { false };
        ["flush"] call comspec_atak_native_fnc_alertesSend;
        // Serveur sans alertes : une relecture toutes les dix minutes suffit (le site a pu être mis à jour).
        if ((_store get "avail") isEqualTo "no" && {(diag_tickTime - (missionNamespace getVariable ["COMSPEC_ATAK_AlertesNoAt", -1000])) < 600}) exitWith { false };
        ["ensure"] call comspec_atak_native_fnc_alertesAction
    };

    case "load": {
        if (!canSuspend) exitWith { ["load", _arg] spawn comspec_atak_native_fnc_alertesAction; false };
        private _force = _arg isEqualTo true;
        // Verrou daté : une lecture interrompue ne bloque pas les suivantes plus de 45 s.
        if ((_store get "busy") && {(diag_tickTime - (missionNamespace getVariable ["COMSPEC_ATAK_AlertesBusyAt", -100])) < 45}) exitWith { false };
        private _why = call _gate;
        if (_why isNotEqualTo "") exitWith {
            _store set ["err", ["gateText", _why] call comspec_atak_native_fnc_alertesAction];
            [_store get "err"] call _say;
            call _render;
            false
        };
        _store set ["busy", true];
        missionNamespace setVariable ["COMSPEC_ATAK_AlertesBusyAt", diag_tickTime];
        ["Actualisation des alertes…"] call _say;
        call _render;

        // COMSPEC Link trop ancien : SubmitIdCheck absent de GetAtakFeatures (commande locale, sans réseau).
        private _dll = missionNamespace getVariable ["COMSPEC_ATAK_FichesDll", ""];
        if (_dll isEqualTo "") then {
            private _f = (["COMSPECExtension", "GetAtakFeatures", []] call comspec_atak_native_fnc_extAwait) param [0, ""];
            if !(_f isEqualType "") then { _f = str _f; };
            if ((_f select [0, 3]) isEqualTo "OK|") then {
                _dll = ["no", "yes"] select ("SubmitIdCheck" in (((_f splitString "|") param [2, ""]) splitString ","));
                missionNamespace setVariable ["COMSPEC_ATAK_FichesDll", _dll];
            };
        };
        private _lg = call _lang;
        private _hexOnly = { params ["_t"]; toString ((toArray _t) select { (_x >= 48 && {_x <= 57}) || {_x >= 97 && {_x <= 122}} || {_x >= 65 && {_x <= 90}} }) };
        private _lastRaw = "";
        private _ok = false;
        private _unchanged = false;
        private _rows = [];
        private _head = [];

        if (_dll isNotEqualTo "no") then {
            // ---- Catalogue : relu si absent, sur ACTUALISER, ou toutes les 30 minutes (révision connue : réponse vide).
            if (_force || {(_store get "catAt") < 0} || {(diag_tickTime - (_store get "catAt")) > 1800}) then {
                private _lines = [];
                private _cHead = [];
                private _cPage = 0;
                private _cOk = false;
                private _cSame = false;
                private _cGuard = 0;
                private _cRev = [[_store get "catRev", ""] select _force] call _hexOnly;
                while { _cGuard < 12 } do {
                    _cGuard = _cGuard + 1;
                    private _raw = [format ['{"kind":"sse_alertes","op":"catalog","rev":"%1","page":%2,"lang":"%3"}', _cRev, _cPage, _lg]] call _call;
                    private _p = [_raw, "catalog"] call _parsePage;
                    if (_p isEqualTo []) exitWith { _lastRaw = _raw; };
                    if (_cHead isEqualTo []) then { _cHead = _p; };
                    if ((_p param [2, ""]) isNotEqualTo (_cHead param [2, ""])) exitWith {};
                    if ((_p param [7, false]) isEqualTo true) exitWith { _cSame = true; _cOk = true; };
                    _lines append (_p param [5, []]);
                    private _next = _p param [4, 0];
                    if (!(_next isEqualType 0) || {_next <= _cPage}) exitWith { _cOk = true; };
                    _cPage = _next;
                };
                if (_cOk) then {
                    private _r = _cHead param [6, []];
                    if (_r isEqualType [] && {(count _r) >= 4}) then { _store set ["rights", (_r select [0, 4]) apply { _x isEqualTo true }]; };
                    if (!_cSame) then {
                        [_lines] call _catApply;
                        profileNamespace setVariable ["COMSPEC_ATAK_AlertesCat", [_cHead param [2, ""], _lines]];
                    };
                    _store set ["catRev", _cHead param [2, ""]];
                    _store set ["catAt", diag_tickTime];
                };
            };

            // ---- Liste, page par page.
            private _known = [[_store get "rev", ""] select _force] call _hexOnly;
            private _page = 0;
            private _guard = 0;
            while { _guard < 16 } do {
                _guard = _guard + 1;
                _lastRaw = [format ['{"kind":"sse_alertes","op":"list","rev":"%1","page":%2,"lang":"%3","doc":""}', _known, _page, _lg]] call _call;
                private _p = [_lastRaw, "list"] call _parsePage;
                if (_p isEqualTo []) exitWith {};
                if (_head isEqualTo []) then { _head = _p; };
                // Paquet changé entre deux pages : lecture abandonnée, reprise au prochain passage.
                if ((_p param [2, ""]) isNotEqualTo (_head param [2, ""])) exitWith {};
                if ((_p param [9, false]) isEqualTo true) exitWith { _unchanged = true; _ok = true; };
                _rows append (_p param [7, []]);
                private _next = _p param [6, 0];
                if (!(_next isEqualType 0) || {_next <= _page}) exitWith { _ok = true; };
                _page = _next;
            };
        };

        if (_ok) then {
            _store set ["avail", "yes"];
            if (_unchanged) then {
                _store set ["src", "site"];
            } else {
                private _before = (_store get "list") apply { _x get "ref" };
                private _wasSite = (_store get "src") isEqualTo "site";
                [_rows, _head param [8, []], _head param [2, ""], _head param [4, 300], "site"] call _applyRows;
                // Dernière version gardée sur le poste : niveau le plus bas seulement (les autres restent en mémoire de partie).
                private _floor = _rows select { (_x param [27, ""]) isEqualTo "interne" };
                private _floorMeta = ["interne", [((_floor param [0, []]) param [28, "CUI"]), "CUI"] select (_floor isEqualTo []), count _floor, false];
                profileNamespace setVariable ["COMSPEC_ATAK_AlertesCache", [systemTimeUTC, _head param [2, ""], _floor, _floorMeta]];
                [] call comspec_atak_native_fnc_saveProfile;

                // Nouvel avis de niveau élevé (rang 3 ou 4) : centre de notifications du téléphone, vibreur seul. La première lecture
                // d'un poste ne prévient de rien (tout y serait « nouveau ») ; ensuite seules les références jamais vues comptent.
                private _seen = profileNamespace getVariable ["COMSPEC_ATAK_AlertesSeen", []];
                if !(_seen isEqualType []) then { _seen = []; };
                private _fresh = (_store get "list") select { !((_x get "ref") in _seen) && {!((_x get "ref") in _before)} };
                if ((_store get "seeded") || {_seen isNotEqualTo []} || {_wasSite}) then {
                    private _zoneF = _s getOrDefault ["alertesF_zone", ""];
                    private _hot = _fresh select { (_x get "rank") >= 3 && {_zoneF isEqualTo "" || {(_x get "zone") isEqualTo _zoneF}} };
                    {
                        private _lv = ["level", _x get "level"] call comspec_atak_native_fnc_alertesAction;
                        ["TACTICAL", format ["%1 · %2%3", _lv select 0, [_x get "name", "Sujet non identifié"] select ((_x get "name") isEqualTo ""),
                            ["", " · " + (_x get "zone")] select ((_x get "zone") isNotEqualTo "")], 0, 30, "SSEALERT", "vibrate"] call comspec_atak_native_fnc_notify;
                    } forEach (_hot select [0, 3]);
                    if ((count _hot) > 3) then {
                        ["TACTICAL", format ["%1 autres alertes de niveau élevé", (count _hot) - 3], 0, 30, "SSEALERT", "vibrate"] call comspec_atak_native_fnc_notify;
                    };
                    // Réponse du bureau à une proposition : l'alerte née de ma proposition vient d'entrer dans la liste.
                    ["proposalCheck", _fresh] call comspec_atak_native_fnc_alertesSend;
                };
                _store set ["seeded", true];
                _seen = (_seen + ((_store get "list") apply { _x get "ref" }));
                _seen = _seen arrayIntersect _seen;
                profileNamespace setVariable ["COMSPEC_ATAK_AlertesSeen", _seen select [((count _seen) - 300) max 0]];
            };
            _store set ["at", diag_tickTime];
            _store set ["sync", systemTimeUTC];
            _store set ["clock", call _clock];
            _store set ["err", ""];
            [format ["Synchronisé à %1 · %2 alerte(s)", _store get "clock", count (_store get "list")]] call _say;
            missionNamespace setVariable ["COMSPEC_ATAK_AlertesRetryAt", -1];
        } else {
            // Site sans le canal des alertes (plus ancien) : il prend la commande pour un contrôle d'identité et la refuse, ou rend une
            // page d'un autre genre. Ce n'est pas une panne : l'app dit « indisponible sur ce serveur », sans erreur.
            private _transient = ["uplink_hold", "unauthorized", "forbidden", "no_tenant", "rate_limited", "unavailable", "maintenance", "unscheduled", "timeout", "not_connected", "http_5", "payload_empty"];
            private _old = _dll isNotEqualTo "no" && {_lastRaw isNotEqualTo ""} && {(_transient findIf { (_lastRaw find _x) >= 0 }) < 0};
            if (_old) then {
                _store set ["avail", "no"];
                _store set ["err", ""];
                missionNamespace setVariable ["COMSPEC_ATAK_AlertesNoAt", diag_tickTime];
                ["Avis et vigilance : indisponible sur ce serveur."] call _say;
            } else {
                private _why = if (_dll isEqualTo "no") then { "COMSPEC Link trop ancien : mettez-le à jour." } else { [_lastRaw] call _errText };
                _store set ["err", _why];
                private _have = (count (_store get "list")) > 0;
                [[_why, format ["%1 · dernière version de %2", _why, _store get "clock"]] select (_have && {(_store get "clock") isNotEqualTo ""})] call _say;
            };
            missionNamespace setVariable ["COMSPEC_ATAK_AlertesRetryAt", diag_tickTime + 60];
        };
        _store set ["busy", false];
        call _render;
        _ok
    };

    case "detail": {
        if (!canSuspend) exitWith { ["detail", _arg] spawn comspec_atak_native_fnc_alertesAction; false };
        private _ref = [_arg, 40] call _clean;
        if (_ref isEqualTo "" || {(call _gate) isNotEqualTo ""} || {(missionNamespace getVariable ["COMSPEC_ATAK_FichesDll", ""]) isEqualTo "no"}) exitWith {
            // Hors réseau : le QR code de l'adresse déjà connue se calcule quand même.
            private _f = ["get", _arg] call comspec_atak_native_fnc_alertesAction;
            if ((count _f) > 0 && {(_f get "url") isNotEqualTo ""}) then { ["qr", _f get "url"] call comspec_atak_native_fnc_alertesAction; call _render; };
            false
        };
        private _p = [[format ['{"kind":"sse_alertes","op":"detail","ref":"%1","lang":"%2"}', _ref, call _lang]] call _call, "detail"] call _parsePage;
        if (_p isEqualTo [] || {!((_p param [2, []]) isEqualType [])} || {(_p param [2, []]) isEqualTo []}) exitWith { false };
        private _full = [_p select 2] call _rowMap;
        _full set ["cut", false];
        private _list = { params ["_a"]; if !(_a isEqualType []) exitWith { [] }; ((_a select { _x isEqualType "" }) apply { [_x] call _dec }) select { _x isNotEqualTo "" } };
        private _n = _p param [7, 0];
        if !(_n isEqualType 0) then { _n = 0; };
        (_store get "more") set [_arg, createHashMapFromArray [
            ["row", _full], ["reasons", [_p param [3, []]] call _list], ["vehicles", [_p param [4, []]] call _list], ["people", [_p param [5, []]] call _list],
            ["desc", [_p param [6, ""]] call _dec], ["reports", _n], ["liftedAt", [_p param [8, ""]] call _dec], ["liftReason", [_p param [9, ""]] call _dec],
            ["donts", [_p param [10, []]] call _list], ["at", diag_tickTime]
        ]];
        private _i = (_store get "list") findIf { (_x get "ref") isEqualTo _arg };
        if (_i >= 0) then { (_store get "list") set [_i, _full]; };
        if ((_full get "url") isNotEqualTo "") then { ["qr", _full get "url"] call comspec_atak_native_fnc_alertesAction; };
        call _render;
        true
    };
    case "qr": {
        if (!(_arg isEqualType "") || {_arg isEqualTo ""}) exitWith { [] };
        private _q = _store get "qr";
        if (_arg in _q) exitWith { _q get _arg };
        private _rows = [_arg] call comspec_atak_native_fnc_alertesQr;
        if ((count _q) > 40) then { _q = createHashMap; _store set ["qr", _q]; };
        _q set [_arg, _rows];
        _rows
    };

    case "open": {
        _s set ["alertesOpen", _arg];
        _s set ["alertesView", "detail"];
        // Lu sur ce poste (l'accusé de lecture, lui, part au site par ACCUSER LECTURE).
        private _f = ["get", _arg] call comspec_atak_native_fnc_alertesAction;
        if ((count _f) > 0) then {
            private _read = profileNamespace getVariable ["COMSPEC_ATAK_AlertesRead", []];
            if !(_read isEqualType []) then { _read = []; };
            _read pushBackUnique ([_f] call _readKey);
            profileNamespace setVariable ["COMSPEC_ATAK_AlertesRead", _read select [((count _read) - 300) max 0]];
        };
        call _render;
        ["detail", _arg] spawn comspec_atak_native_fnc_alertesAction;
        true
    };
    case "view": {
        _s set ["alertesView", _arg];
        call _render;
    };
    case "back": {
        private _v = _s getOrDefault ["alertesView", ""];
        if (_v in ["share", "report", "renew", "lift"] && {(_s getOrDefault ["alertesOpen", ""]) isNotEqualTo ""}) then {
            _s set ["alertesView", "detail"];
        } else {
            _s set ["alertesView", ""];
            _s set ["alertesOpen", ""];
        };
        call _render;
    };
    case "home": {
        _s set ["alertesView", ""];
        _s set ["alertesOpen", ""];
        call _render;
    };
    case "openRef": {
        _s set ["alertesOpen", _arg];
        _s set ["alertesView", "detail"];
        if ((_s getOrDefault ["activePage", ""]) isEqualTo "SSEALERT") then { call _render; } else { ["SSEALERT"] call comspec_atak_native_fnc_navigate; };
        ["ensure"] call comspec_atak_native_fnc_alertesAction;
        ["detail", _arg] spawn comspec_atak_native_fnc_alertesAction;
        true
    };
    case "filter": {
        _arg params [["_k", ""], ["_v", ""]];
        if (_k in ["type", "zone", "armed"]) then { _s set ["alertesF_" + _k, _v]; };
        call _render;
    };
    case "clearFilters": {
        { _s set ["alertesF_" + _x, ""]; } forEach ["type", "zone", "armed"];
        call _render;
    };

    // Dernier lieu connu : le contrat ne donne qu'un texte (lieu, zone). Une grille écrite dans ce texte, sinon un lieu-dit de la carte.
    case "place": {
        private _f = ["get", _arg] call comspec_atak_native_fnc_alertesAction;
        if ((count _f) isEqualTo 0) exitWith { [] };
        private _out = [];
        {
            if (_out isEqualTo [] && {_x isNotEqualTo ""}) then {
                private _g = (_x regexFind ["[0-9]{3,5}[ -]?[0-9]{3,5}"]) param [0, []];
                if (_g isNotEqualTo []) then {
                    private _digits = (((_g select 0) select 0) splitString " -") joinString "";
                    if (((count _digits) mod 2) isEqualTo 0) then {
                        private _p = ([_digits] call BIS_fnc_gridToPos) param [0, []];
                        if ((count _p) >= 2) then { _out = [_p select 0, _p select 1]; };
                    };
                };
            };
        } forEach [_f get "place", _f get "zone"];
        if (_out isNotEqualTo []) exitWith { _out };
        private _names = [toLower (_f get "place"), toLower (_f get "zone")] select { _x isNotEqualTo "" };
        if (_names isEqualTo []) exitWith { [] };
        {
            private _t = toLower (text _x);
            if (_out isEqualTo [] && {_t isNotEqualTo ""} && {(count _t) >= 3} && {(_names findIf { (_x find _t) >= 0 }) >= 0}) then {
                private _lp = locationPosition _x;
                _out = [_lp select 0, _lp select 1];
            };
        } forEach (nearestLocations [[worldSize / 2, worldSize / 2, 0], ["NameCityCapital", "NameCity", "NameVillage", "NameLocal", "NameMarine", "Airport"], worldSize]);
        _out
    };
    case "map": {
        private _p = ["place", _arg] call comspec_atak_native_fnc_alertesAction;
        if ((count _p) < 2) exitWith { ["Ce lieu ne se retrouve pas sur la carte : aucune grille dans l'alerte."] call _say; call _render; false };
        ["MAP"] call comspec_atak_native_fnc_navigate;
        [{ [_this, 0.05] call comspec_atak_native_fnc_mapCenter; }, [_p select 0, _p select 1]] call comspec_atak_native_fnc_nextFrame;
        true
    };

    case "ack": {
        private _f = ["get", _arg] call comspec_atak_native_fnc_alertesAction;
        if ((count _f) isEqualTo 0) exitWith { false };
        if ((_f get "acked") isEqualTo true) exitWith { ["Lecture déjà accusée."] call _say; call _render; true };
        ["new", ["ack", createHashMapFromArray [["ref", _arg]], _arg, format ["Accusé de lecture · %1", _arg]]] call comspec_atak_native_fnc_alertesSend;
        true
    };

    // Renouveler (droit d'émettre le document) et lever (motif obligatoire, droit de gestion) : formulaires de l'écran de l'alerte.
    case "days": {
        if (_arg in [7, 14, 30, 60, 90, 180]) then { _s set ["alertesDays", _arg]; };
        call _render;
    };
    case "renewSend": {
        private _ref = _s getOrDefault ["alertesOpen", ""];
        if (_ref isEqualTo "") exitWith { false };
        private _reason = trim (["av_reason", ""] call comspec_atak_native_fnc_formValue);
        ["new", ["renew", createHashMapFromArray [["ref", _ref], ["days", _s getOrDefault ["alertesDays", 30]], ["reason", toString ((toArray _reason) select [0, 500])]],
            _ref, format ["Renouvellement · %1", _ref]]] call comspec_atak_native_fnc_alertesSend;
        _s set ["alertesView", "sent"];
        call _render;
        true
    };
    case "liftSend": {
        private _ref = _s getOrDefault ["alertesOpen", ""];
        if (_ref isEqualTo "") exitWith { false };
        private _reason = trim (["av_reason", ""] call comspec_atak_native_fnc_formValue);
        if ((count _reason) < 3) exitWith { ["Motif de la levée obligatoire (3 caractères au moins)."] call _say; call _render; false };
        ["new", ["lift", createHashMapFromArray [["ref", _ref], ["reason", toString ((toArray _reason) select [0, 500])]], _ref, format ["Levée · %1", _ref]]] call comspec_atak_native_fnc_alertesSend;
        _s set ["alertesView", "sent"];
        call _render;
        true
    };

    case "share": {
        _s set ["alertesOpen", _arg];
        _s set ["alertesView", "share"];
        call _render;
    };
    // Partage : l'alerte ne voyage pas, seulement sa référence et un titre court, par les voies de l'app Fiches (même référence côté
    // site, ICD § 7). Le destinataire la lit dans sa propre liste, selon SON habilitation. Le site vérifie, borne le débit et journalise.
    case "shareBt";
    case "shareSms": {
        if (!canSuspend) exitWith { [_act, _arg] spawn comspec_atak_native_fnc_alertesAction; false };
        _arg params [["_ref", ""], ["_to", ""]];
        private _f = ["get", _ref] call comspec_atak_native_fnc_alertesAction;
        if ((count _f) isEqualTo 0) exitWith { ["Alerte introuvable : actualisez la liste."] call _say; call _render; false };
        // Anti-spam local : 6 partages par 2 minutes au plus et par téléphone (compteur commun avec l'app Fiches).
        private _stamps = (missionNamespace getVariable ["COMSPEC_ATAK_FichesShareTs", []]) select { diag_tickTime - _x < 120 };
        if ((count _stamps) >= 6) exitWith { ["Trop de partages : patientez deux minutes."] call _say; call _render; false };
        private _uid = _to;
        private _name = _to;
        if (_act isEqualTo "shareBt") then {
            private _bt = (["state"] call comspec_atak_native_fnc_btAction);
            _name = (_bt getOrDefault ["paired", createHashMap]) getOrDefault [_uid, "l'appareil"];
            // Le Bluetooth porte les références de l'app Fiches : la sienne doit connaître celle-ci.
            if ((count (["get", _ref] call comspec_atak_native_fnc_fichesAction)) isEqualTo 0) then { ["load", false] call comspec_atak_native_fnc_fichesAction; };
        };
        private _via = ["sms", "bt"] select (_act isEqualTo "shareBt");
        private _raw = "";
        if ((call _gate) isEqualTo "") then {
            _raw = [format ['{"kind":"sse_fiche_share","ref":"%1","to":"%2","via":"%3"}', [_ref, 40] call _clean, [_name, 60] call _clean, _via]] call _call;
        };
        if ((_raw find "forbidden") >= 0 || {(_raw find "rate_limited") >= 0}) exitWith {
            [["Partage refusé : cette alerte est au-dessus de votre habilitation.", "Trop de partages : réessayez dans un moment."] select ((_raw find "rate_limited") >= 0)] call _say;
            call _render;
            false
        };
        private _title = ["title", _ref] call comspec_atak_native_fnc_alertesAction;
        private _sent = false;
        if (_act isEqualTo "shareBt") then {
            if ((count (["get", _ref] call comspec_atak_native_fnc_fichesAction)) > 0) then {
                _sent = ["ficheSend", [_ref, _uid]] call comspec_atak_native_fnc_btAction;
            };
        } else {
            if ((allPlayers findIf { (name _x) isEqualTo _name }) >= 0) then {
                _sent = [_name, format ["[FICHE] %1 · %2%3", _ref, ["kindLabel", _f get "doc"] call comspec_atak_native_fnc_alertesAction,
                    ["", " · " + _title] select (_title isNotEqualTo "")]] call comspec_atak_native_fnc_p2pSend;
            };
        };
        if (_sent isEqualTo true) then {
            _stamps pushBack diag_tickTime;
            missionNamespace setVariable ["COMSPEC_ATAK_FichesShareTs", _stamps];
            [format ["Alerte %1 envoyée à %2 (%3).", _ref, _name, ["SMS", "Bluetooth"] select (_act isEqualTo "shareBt")]] call _say;
        } else {
            [format ["Envoi à %1 impossible : %2.", _name, ["joueur injoignable", "appareil hors de portée, Bluetooth éteint ou référence absente de l'app Fiches"] select (_act isEqualTo "shareBt")]] call _say;
        };
        call _render;
        _sent isEqualTo true
    };
    default { false };
};
