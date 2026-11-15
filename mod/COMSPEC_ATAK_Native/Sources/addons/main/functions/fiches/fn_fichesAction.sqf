/*
    App Fiches : les fiches du portail SSE (avis de recherche, fiches de vigilance, d'identification, de renseignement et de
    contrôle) lues sur le téléphone, avec leurs motifs, leurs mesures à prendre, leur zone et leurs liens. ICD-SSE-FICHES-001.
    Canal : SubmitIdCheck de COMSPEC Link (champ kind « sse_fiches », réponse OK|id|page), la même voie que le catalogue du SSE :
    aucune nouvelle DLL. Le site filtre selon l'habilitation du porteur (jeton de jeu) : une fiche au-dessus n'arrive jamais ici.
    Aucun toast, aucun hint, aucun systemChat : tous les retours passent par la ligne d'état de l'écran (« fichesStatus »).

    Données (uiNamespace COMSPEC_ATAK_Fiches, HashMap) : list (HashMaps, colonnes de "cols"), raw (lignes brutes), rev, at (diag_tickTime
    de la dernière lecture réussie, -1 : jamais), sync (systemTimeUTC), clock (HH:MM), meta [autorité fr, en, niveau, marquage, total,
    tronqué], types (code -> [fr, en]), src ("site" | "poste" | ""), err, busy. Hors ligne : la dernière version est gardée sur le poste
    (profileNamespace COMSPEC_ATAK_FichesCache) pour 24 h, SEULEMENT les fiches du niveau le plus bas (« interne ») : une fiche plus
    sensible ne quitte jamais la mémoire de la partie.

    Params : [action, argument]
      ["cols"]                 colonnes d'une fiche, dans l'ordre du site (ICD, SseFichesGameService::COLUMNS)
      ["ensure"]               relit le site si la liste est absente ou plus vieille que sa durée de validité (tâche de fond)
      ["load", forcer]         lecture (tâche de fond) ; forcer = true : ACTUALISER
      ["detail", réf]          relit une fiche en entier (textes non raccourcis)
      ["get", réf]             la fiche (HashMap) ou une HashMap vide        ["list"] toutes les fiches, déjà triées par le site
      ["age"]                  minutes depuis la dernière lecture réussie, -1 si jamais
      ["open", réf] / ["back"] ouvre une fiche (détail) / revient à la liste     ["openRef", réf] ouvre l'app sur cette fiche
      ["filter", [clé, valeur]] type | threat | zone ; ["clearFilters"]
      ["share", réf] / ["shareClose"]   vue de partage de la fiche ouverte
      ["shareBt", [réf, uid]]  envoie la référence par Bluetooth (app Bluetooth, appareil appairé à portée)
      ["shareSms", [réf, nom]] envoie la référence par SMS à un joueur
      ["receive", [de, réf, titre, genre, niveau, voie]] range une fiche reçue (Bluetooth ou SMS) dans « Reçues »
      ["inbox"]                fiches reçues  ·  ["status", texte] pose la ligne d'état  ·  ["title", réf] titre court d'une fiche
*/
params [["_act", "list"], ["_arg", ""]];
private _cols = [
    "ref", "kind", "type", "name", "alias", "threat", "armed", "level", "mark", "reason", "measures", "desc", "remarks", "note",
    "zone", "place", "seen", "issued", "until", "updated", "vehicles", "addresses", "people", "auth", "cut"
];
if (_act isEqualTo "cols") exitWith { _cols };

private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _store = uiNamespace getVariable ["COMSPEC_ATAK_Fiches", createHashMap];
if ((count _store) isEqualTo 0) then {
    _store = createHashMapFromArray [["list", []], ["raw", []], ["rev", ""], ["at", -1], ["sync", []], ["clock", ""], ["ttl", 300], ["meta", []],
        ["types", createHashMap], ["src", ""], ["err", ""], ["busy", false]];
    uiNamespace setVariable ["COMSPEC_ATAK_Fiches", _store];
};
private _render = {
    [{
        if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "FICHES") then {
            ["FICHES"] call comspec_atak_native_fnc_pageRender;
        };
    }] call comspec_atak_native_fnc_nextFrame;
};
private _say = {
    params ["_t"];
    (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) set ["fichesStatus", [_t, diag_tickTime]];
};
// Minutes écoulées depuis le premier instant jusqu'au second (calendrier grégorien : algorithme des jours civils).
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
// Ligne brute du site -> HashMap des colonnes.
private _rowMap = {
    params ["_r"];
    private _m = createHashMap;
    {
        private _v = _r param [_forEachIndex, ""];
        if (_v isEqualType "") then { _v = [_v] call _dec; };
        if (_v isEqualType []) then { _v = _v apply { [_x] call _dec }; };
        _m set [_x, _v];
    } forEach _cols;
    _m
};
// Texte envoyé au site : lettres, chiffres et quelques signes seulement (jamais de guillemet ni de barre inverse).
private _clean = {
    params ["_t", ["_max", 40]];
    if !(_t isEqualType "") exitWith { "" };
    ((_t regexReplace ["[^A-Za-z0-9 ._/:+-]", " "]) select [0, _max])
};
// Réponse « OK|id|page » de COMSPEC Link -> la page (tableau), [] si illisible.
private _parsePage = {
    params ["_raw"];
    if !(_raw isEqualType "") then { _raw = str _raw; };
    if ((_raw select [0, 3]) isNotEqualTo "OK|") exitWith { [] };
    private _rest = _raw select [3];
    private _cut = _rest find "|";
    if (_cut < 0) exitWith { [] };
    private _text = _rest select [_cut + 1];
    if ((_text select [0, 1]) isNotEqualTo "[") exitWith { [] };
    private _p = parseSimpleArray _text;
    if (!(_p isEqualType []) || {(count _p) < 9}) exitWith { [] };
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
// Message d'erreur lisible pour la ligne d'état.
private _errText = {
    params ["_raw"];
    switch (true) do {
        case (_raw isEqualTo ""): { "COMSPEC Link ne répond pas." };
        case ((_raw find "uplink_hold") >= 0): { "Liaison Athena coupée." };
        case ((_raw find "unauthorized") >= 0): { "Connexion Athena requise." };
        case ((_raw find "forbidden") >= 0): { "Votre profil n'a pas accès aux fiches." };
        case ((_raw find "no_tenant") >= 0): { "Compte Athena non relié à ce jeu." };
        case ((_raw find "rate_limited") >= 0): { "Athena est occupé : réessayez dans une minute." };
        case ((_raw find "unavailable") >= 0): { "Athena ne répond pas." };
        default { format ["Athena a refusé la lecture (%1).", _raw select [0, 40]] };
    }
};
// Liste des fiches (HashMaps) depuis les lignes brutes.
private _applyRows = {
    params ["_rows", "_meta", "_types", "_rev", "_ttl", "_src"];
    _store set ["raw", _rows];
    _store set ["list", _rows apply { [_x] call _rowMap }];
    private _m = +_meta;
    { if (_x isEqualType "") then { _m set [_forEachIndex, [_x] call _dec]; }; } forEach _m;
    _store set ["meta", _m];
    private _t = createHashMap;
    { if (_x isEqualType [] && {(count _x) >= 3}) then { _t set [_x select 0, [[_x select 1] call _dec, [_x select 2] call _dec]]; }; } forEach _types;
    _store set ["types", _t];
    _store set ["rev", _rev];
    _store set ["ttl", _ttl];
    _store set ["src", _src];
};

switch (_act) do {
    case "list": { _store get "list" };
    case "get": {
        private _i = (_store get "list") findIf { (_x get "ref") isEqualTo _arg };
        if (_i < 0) exitWith { createHashMap };
        (_store get "list") select _i
    };
    case "age": {
        if ((_store get "at") < 0 && {(count (_store get "sync")) isEqualTo 0}) exitWith { -1 };
        private _now = systemTimeUTC;
        (([_now] call _epochMin) - ([_store get "sync"] call _epochMin)) max 0
    };
    case "status": { [_arg] call _say; call _render; };
    case "inbox": { missionNamespace getVariable ["COMSPEC_ATAK_FichesInbox", []] };
    case "kindLabel": {
        createHashMapFromArray [["avis", "Avis de recherche"], ["identification", "Fiche d'identification"], ["vigilance", "Fiche de vigilance"],
            ["profile", "Fiche de renseignement"], ["control", "Fiche de contrôle"]] getOrDefault [_arg, "Fiche"]
    };
    // Titre court d'une fiche pour un partage : le nom seulement si la fiche est de diffusion large (avis, niveau le plus bas) ;
    // sinon le genre de fiche. Le destinataire lit le détail selon SES droits.
    case "title": {
        private _f = ["get", _arg] call comspec_atak_native_fnc_fichesAction;
        if ((count _f) isEqualTo 0) exitWith { "" };
        if ((_f get "kind") isEqualTo "avis" || {(_f get "level") isEqualTo "interne"}) exitWith { _f get "name" };
        ["kindLabel", _f get "kind"] call comspec_atak_native_fnc_fichesAction
    };

    case "ensure": {
        if (!hasInterface) exitWith { false };
        // Parties précédentes : les fiches sont celles de la communauté du serveur du moment, jamais celles d'une mission d'avant.
        if (isNil { missionNamespace getVariable "COMSPEC_ATAK_FichesMission" }) then {
            missionNamespace setVariable ["COMSPEC_ATAK_FichesMission", true];
            _store set ["list", []];
            _store set ["raw", []];
            _store set ["at", -1];
            _store set ["rev", ""];
            // Dernière version gardée sur le poste (niveau le plus bas seulement, 24 h) : montrée en attendant le site.
            private _saved = profileNamespace getVariable ["COMSPEC_ATAK_FichesCache", []];
            if (_saved isEqualType [] && {(count _saved) >= 5} && {(_saved select 0) isEqualType []}) then {
                _saved params ["_when", "_rev", "_rows", "_meta", "_types"];
                private _age = (([systemTimeUTC] call _epochMin) - ([_when] call _epochMin));
                if (_age >= 0 && {_age <= 1440} && {_rows isEqualType []} && {_meta isEqualType []} && {_types isEqualType []}) then {
                    [_rows, _meta, _types, "", 300, "poste"] call _applyRows;
                    _store set ["sync", _when];
                    _store set ["clock", format ["%1:%2", [_when select 3, "0" + str (_when select 3)] select ((_when select 3) < 10), [_when select 4, "0" + str (_when select 4)] select ((_when select 4) < 10)]];
                } else {
                    profileNamespace setVariable ["COMSPEC_ATAK_FichesCache", []];
                };
            };
        };
        private _fresh = (_store get "src") isEqualTo "site" && {(diag_tickTime - (_store get "at")) < (_store get "ttl")};
        if (_fresh || {_store get "busy"}) exitWith { false };
        if (diag_tickTime < (missionNamespace getVariable ["COMSPEC_ATAK_FichesRetryAt", -1])) exitWith { false };
        ["load", false] spawn comspec_atak_native_fnc_fichesAction;
        true
    };

    case "load": {
        if (!canSuspend) exitWith { ["load", _arg] spawn comspec_atak_native_fnc_fichesAction; false };
        private _force = _arg isEqualTo true;
        // Verrou daté : une lecture interrompue ne bloque pas les suivantes plus de 45 s.
        if ((_store get "busy") && {(diag_tickTime - (missionNamespace getVariable ["COMSPEC_ATAK_FichesBusyAt", -100])) < 45}) exitWith { false };
        if !([] call comspec_atak_native_fnc_bridge) exitWith {
            _store set ["err", "COMSPEC Overwatch n'est pas chargé : les fiches viennent d'Athena par lui."];
            call _render;
            false
        };
        if (([] call comspec_atak_native_fnc_athenaMode) isEqualTo "off") exitWith {
            _store set ["err", "ATAK local : pas de liaison Athena, pas de fiches du site."];
            call _render;
            false
        };
        _store set ["busy", true];
        missionNamespace setVariable ["COMSPEC_ATAK_FichesBusyAt", diag_tickTime];
        ["Actualisation des fiches…"] call _say;
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
        private _map = missionNamespace getVariable ["COMSPEC_MapId", 1];
        if !(_map isEqualType 0) then { _map = 1; };
        private _known = if (_force) then { "" } else { _store get "rev" };
        _known = toString ((toArray _known) select { (_x >= 48 && {_x <= 57}) || {_x >= 97 && {_x <= 102}} });
        private _rows = [];
        private _head = [];
        private _page = 0;
        private _guard = 0;
        private _ok = false;
        private _unchanged = false;
        private _lastRaw = "";
        if (_dll isNotEqualTo "no") then {
            while { _guard < 16 } do {
                _guard = _guard + 1;
                private _json = format ['{"kind":"sse_fiches","op":"list","map":%1,"rev":"%2","page":%3}', floor _map, _known, _page];
                _lastRaw = [_json] call _call;
                private _p = [_lastRaw] call _parsePage;
                if (_p isEqualTo []) exitWith {};
                if (_head isEqualTo []) then { _head = _p; };
                // Paquet changé entre deux pages : lecture abandonnée, reprise au prochain passage.
                if ((_p select 1) isNotEqualTo (_head select 1)) exitWith {};
                if ((_p param [9, false]) isEqualTo true) exitWith { _unchanged = true; _ok = true; };
                _rows append (_p param [6, []]);
                private _next = _p param [5, 0];
                if (!(_next isEqualType 0) || {_next <= _page}) exitWith { _ok = true; };
                _page = _next;
            };
        };
        private _clock = {
            systemTime params ["", "", "", "_h", "_mi"];
            format ["%1:%2", [str _h, "0" + str _h] select (_h < 10), [str _mi, "0" + str _mi] select (_mi < 10)]
        };
        if (_ok) then {
            if (_unchanged) then {
                // Rien de changé sur le site : mêmes fiches, lecture notée.
                _store set ["src", "site"];
            } else {
                [_rows, _head param [7, []], _head param [8, []], _head select 1, _head param [3, 300], "site"] call _applyRows;
                // Dernière version gardée sur le poste : niveau le plus bas seulement (les autres restent en mémoire de partie).
                private _floor = _rows select { (_x param [7, ""]) isEqualTo "interne" };
                private _floorMeta = +(_head param [7, []]);
                if ((count _floorMeta) >= 4) then {
                    _floorMeta set [2, "interne"];
                    _floorMeta set [3, [((_floor select 0) param [8, ""]), "UNCLASSIFIED//FOUO"] select (_floor isEqualTo [])];
                };
                profileNamespace setVariable ["COMSPEC_ATAK_FichesCache", [systemTimeUTC, _head select 1, _floor, _floorMeta, _head param [8, []]]];
                [] call comspec_atak_native_fnc_saveProfile;
            };
            _store set ["at", diag_tickTime];
            _store set ["sync", systemTimeUTC];
            _store set ["clock", call _clock];
            _store set ["err", ""];
            private _n = count (_store get "list");
            [format ["Synchronisé à %1 · %2 fiche(s)", _store get "clock", _n]] call _say;
            missionNamespace setVariable ["COMSPEC_ATAK_FichesRetryAt", -1];
        } else {
            private _why = if (_dll isEqualTo "no") then { "COMSPEC Link trop ancien : mettez-le à jour." } else { [_lastRaw] call _errText };
            _store set ["err", _why];
            private _have = (count (_store get "list")) > 0;
            [[_why, format ["%1 · dernière version de %2", _why, _store get "clock"]] select (_have && {(_store get "clock") isNotEqualTo ""})] call _say;
            missionNamespace setVariable ["COMSPEC_ATAK_FichesRetryAt", diag_tickTime + 60];
        };
        _store set ["busy", false];
        call _render;
        _ok
    };

    case "detail": {
        if (!canSuspend) exitWith { ["detail", _arg] spawn comspec_atak_native_fnc_fichesAction; false };
        private _ref = [_arg, 40] call _clean;
        if (_ref isEqualTo "" || {(missionNamespace getVariable ["COMSPEC_ATAK_FichesDll", ""]) isEqualTo "no"}) exitWith { false };
        private _p = [[format ['{"kind":"sse_fiches","op":"detail","ref":"%1"}', _ref]] call _call] call _parsePage;
        if (_p isEqualTo [] || {(_p param [6, []]) isEqualTo []}) exitWith { false };
        private _full = [(_p select 6) select 0] call _rowMap;
        private _i = (_store get "list") findIf { (_x get "ref") isEqualTo _arg };
        if (_i >= 0) then {
            _full set ["cut", false];
            (_store get "list") set [_i, _full];
            call _render;
        };
        true
    };

    case "open": {
        _s set ["fichesOpen", _arg];
        _s set ["fichesShare", false];
        call _render;
        private _f = ["get", _arg] call comspec_atak_native_fnc_fichesAction;
        // Texte raccourci dans la liste : le détail se relit en entier, en tâche de fond.
        if ((count _f) > 0 && {(_f get "cut") isEqualTo true}) then { ["detail", _arg] spawn comspec_atak_native_fnc_fichesAction; };
        true
    };
    case "back": {
        _s set ["fichesOpen", ""];
        _s set ["fichesShare", false];
        call _render;
    };
    case "openRef": {
        _s set ["fichesOpen", _arg];
        _s set ["fichesShare", false];
        if (((_s getOrDefault ["activePage", ""]) isEqualTo "FICHES")) then { call _render; } else { ["FICHES"] call comspec_atak_native_fnc_navigate; };
        ["ensure"] call comspec_atak_native_fnc_fichesAction;
        true
    };
    case "filter": {
        _arg params [["_k", ""], ["_v", ""]];
        if (_k in ["type", "threat", "zone"]) then { _s set ["fichesF_" + _k, _v]; };
        call _render;
    };
    case "clearFilters": {
        { _s set ["fichesF_" + _x, ""]; } forEach ["type", "threat", "zone"];
        call _render;
    };
    // Onglet de la liste (ALL | avis | sheets | inbox), taille du texte (cran de 0,15 entre 0,85 et 1,15) et mode compact.
    case "viewSet": { _s set ["fichesView", _arg]; call _render; };
    case "scale": {
        private _z = _s getOrDefault ["fichesScale", 1];
        if !(_z isEqualType 0) then { _z = 1; };
        _s set ["fichesScale", ((_z + 0.15 * ([_arg, 0] select !(_arg isEqualType 0))) max 0.85) min 1.15];
        call _render;
    };
    case "compact": { _s set ["fichesCompact", !(_s getOrDefault ["fichesCompact", false])]; call _render; };

    case "share": {
        _s set ["fichesShare", true];
        _s set ["fichesOpen", _arg];
        call _render;
    };
    case "shareClose": { _s set ["fichesShare", false]; call _render; };

    // Partage : la fiche ne voyage pas, seulement sa référence et un titre court. Le destinataire la résout dans sa propre liste, selon
    // SON habilitation. Le site vérifie que l'expéditeur lit la fiche, borne le débit et journalise (kind sse_fiche_share).
    case "shareBt";
    case "shareSms": {
        if (!canSuspend) exitWith { [_act, _arg] spawn comspec_atak_native_fnc_fichesAction; false };
        _arg params [["_ref", ""], ["_to", ""]];
        private _f = ["get", _ref] call comspec_atak_native_fnc_fichesAction;
        if ((count _f) isEqualTo 0) exitWith { ["Fiche introuvable : actualisez la liste."] call _say; call _render; false };
        // Anti-spam local : 6 partages par 2 minutes au plus et par téléphone (le site borne aussi, 12 par 10 minutes).
        private _stamps = (missionNamespace getVariable ["COMSPEC_ATAK_FichesShareTs", []]) select { diag_tickTime - _x < 120 };
        if ((count _stamps) >= 6) exitWith { ["Trop de partages : patientez deux minutes."] call _say; call _render; false };
        private _uid = _to;
        private _name = _to;
        if (_act isEqualTo "shareBt") then {
            private _bt = (["state"] call comspec_atak_native_fnc_btAction);
            _name = (_bt getOrDefault ["paired", createHashMap]) getOrDefault [_uid, "l'appareil"];
        };
        private _via = ["sms", "bt"] select (_act isEqualTo "shareBt");
        // Journal et contrôle côté site. Refus explicite (au-dessus de l'habilitation, débit) : rien ne part. Site injoignable : la
        // référence part quand même, le destinataire revérifie à l'ouverture.
        private _raw = [format ['{"kind":"sse_fiche_share","ref":"%1","to":"%2","via":"%3"}', [_ref, 40] call _clean, [_name, 60] call _clean, _via]] call _call;
        if ((_raw find "forbidden") >= 0 || {(_raw find "rate_limited") >= 0}) exitWith {
            [["Partage refusé : cette fiche est au-dessus de votre habilitation.", "Trop de partages : réessayez dans un moment."] select ((_raw find "rate_limited") >= 0)] call _say;
            call _render;
            false
        };
        private _title = ["title", _ref] call comspec_atak_native_fnc_fichesAction;
        private _sent = false;
        if (_act isEqualTo "shareBt") then {
            _sent = ["ficheSend", [_ref, _uid]] call comspec_atak_native_fnc_btAction;
        } else {
            // Joueur encore connecté ? (le SMS d'un absent resterait « non transmis » dans la conversation)
            if ((allPlayers findIf { (name _x) isEqualTo _name }) >= 0) then {
                _sent = [_name, format ["[FICHE] %1 · %2%3", _ref, ["kindLabel", _f get "kind"] call comspec_atak_native_fnc_fichesAction,
                    ["", " · " + _title] select (_title isNotEqualTo "")]] call comspec_atak_native_fnc_p2pSend;
            };
        };
        if (_sent isEqualTo true) then {
            _stamps pushBack diag_tickTime;
            missionNamespace setVariable ["COMSPEC_ATAK_FichesShareTs", _stamps];
            [format ["Fiche %1 envoyée à %2 (%3).", _ref, _name, ["SMS", "Bluetooth"] select (_act isEqualTo "shareBt")]] call _say;
        } else {
            [format ["Envoi à %1 impossible : %2.", _name, ["joueur injoignable", "appareil hors de portée ou Bluetooth éteint"] select (_act isEqualTo "shareBt")]] call _say;
        };
        call _render;
        _sent isEqualTo true
    };

    // Fiche reçue (Bluetooth, SMS) : rangée dans « Reçues » avec sa référence et son titre seulement ; l'ouverture passe par la liste
    // du destinataire (donc par son habilitation).
    case "receive": {
        _arg params [["_from", ""], ["_ref", ""], ["_title", ""], ["_kind", ""], ["_level", ""], ["_via", ""]];
        if (_ref isEqualTo "") exitWith { false };
        private _in = missionNamespace getVariable ["COMSPEC_ATAK_FichesInbox", []];
        _in = _in select { (_x select 1) isNotEqualTo _ref };
        _in = [[_from, _ref, _title select [0, 120], _kind, _level, _via, [dayTime, "HH:MM"] call BIS_fnc_timeToString]] + _in;
        missionNamespace setVariable ["COMSPEC_ATAK_FichesInbox", _in select [0, 20]];
        call _render;
        true
    };
    default { false };
};
