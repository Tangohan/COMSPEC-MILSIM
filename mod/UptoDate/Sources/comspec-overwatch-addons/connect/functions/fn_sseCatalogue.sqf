/*
    Catalogue SSE du site de la communauté (ICD-SSE-CATALOGUE-001, docs/technical/ICD-SSE-CATALOGUE.md) : les listes
    proposées par le SEEK II, les modules Zeus et les attributs Eden viennent d'Athena au lieu d'être figées dans le mod.
    Canal : SubmitIdCheck de COMSPEC Link (champ kind « sse_catalogue », réponse OK|id|page), aucune nouvelle DLL.

      ["rows", section]                         -> lignes de la section, [] si le site ne l'a pas donnée
      ["choices", [section, codes, libellés]]   -> [codes, libellés, source] ; liste locale passée en repli
      ["ensure"]                                -> relit le site si la liste est absente ou périmée (tâche de fond)
      ["refresh"]                               -> relecture demandée par le joueur (tâche de fond)
      ["fetch", forcer]                         -> lecture immédiate (contexte planifié, ou Eden) -> true si à jour
      ["reset"]                                 -> début de mission : oublie les listes vivantes de la mission précédente
      ["state"]                                 -> [source, heure de synchro, révision]
      ["status"]                                -> texte de la ligne d'état des dialogues

    source : "site" (lu pendant cette partie), "poste" (référentiels gardés sur ce poste à la dernière synchro : le site
    ne répond pas), "local" (listes du mod). Les listes vivantes (dossiers, sites, avis, lore, campagnes) ne sont jamais
    gardées d'une partie à l'autre : elles appartiennent à la communauté du serveur du moment.
    Sections et colonnes : contexts/statuses/pieces/supports/site_types/documents/mentions/threats/notice_types
    [code, fr, en(, groupe)] · cases [id, réf, nom] · sites [id, réf, nom, type, dossier] · campaigns [clé, nom] ·
    wanted [id, réf, nom, niveau, type] · lore [uid, réf, nom].
    Mise à jour : événement local CBA « comspec_sse_catalogueUpdated » [source].
*/
params [["_op", "rows", [""]], ["_arg", []]];

private _refSections = ["contexts", "statuses", "pieces", "supports", "site_types", "documents", "mentions", "threats", "notice_types"];
private _data = uiNamespace getVariable ["COMSPEC_SseCatalogue", createHashMap];
// [révision, heure affichée, tick de la lecture, durée de validité, sections tronquées, session de lore]
private _meta = uiNamespace getVariable ["COMSPEC_SseCatalogueMeta", ["", "", -1, 600, [], []]];

if (_op isEqualTo "reset") exitWith {
    { if !(_x in _refSections) then { _data deleteAt _x; }; } forEach (keys _data);
    _meta set [0, ""];
    _meta set [2, -1];
    uiNamespace setVariable ["COMSPEC_SseCatalogue", _data];
    uiNamespace setVariable ["COMSPEC_SseCatalogueMeta", _meta];
    uiNamespace setVariable ["COMSPEC_SseCatalogueRetryAt", -1];
    true
};

private _fromSite = (_meta select 2) >= 0;
private _saved = profileNamespace getVariable ["COMSPEC_SseCatalogueRef", []];
if (!(_saved isEqualType []) || {(count _saved) < 2}) then { _saved = ["", []]; };

if (_op isEqualTo "rows") exitWith {
    private _section = if (_arg isEqualType "") then { _arg } else { "" };
    if (_section in _data) exitWith { _data get _section };
    if !(_section in _refSections) exitWith { [] };
    private _i = (_saved select 1) findIf { (_x param [0, ""]) isEqualTo _section };
    if (_i < 0) exitWith { [] };
    ((_saved select 1) select _i) param [1, []]
};

if (_op isEqualTo "state") exitWith {
    if (_fromSite) exitWith { ["site", _meta select 1, _meta select 0] };
    if ((_saved select 1) isNotEqualTo []) exitWith { ["poste", _saved select 0, ""] };
    ["local", "", ""]
};

if (_op isEqualTo "status") exitWith {
    (["state"] call comspec_overwatch_connect_fnc_sseCatalogue) params ["_source", "_when"];
    switch (_source) do {
        case "site": { format [("Listes du site · synchro %1") call comspec_overwatch_connect_fnc_tr, _when] };
        case "poste": { format [("Site injoignable · listes gardées sur ce poste (%1)") call comspec_overwatch_connect_fnc_tr, _when] };
        default { ("Site injoignable · listes locales du mod") call comspec_overwatch_connect_fnc_tr };
    }
};

if (_op isEqualTo "choices") exitWith {
    _arg params [["_section", "", [""]], ["_codes", [], [[]]], ["_labels", [], [[]]]];
    private _rows = ["rows", _section] call comspec_overwatch_connect_fnc_sseCatalogue;
    if (_rows isEqualTo []) exitWith { [_codes, _labels, "local"] };
    // Téléphone dans une autre langue que le français : libellés anglais du site.
    private _english = !isNil "comspec_atak_native_fnc_tr" && {("Oui" call comspec_atak_native_fnc_tr) isNotEqualTo "Oui"};
    private _outCodes = [];
    private _outLabels = [];
    {
        if (_x isEqualType [] && {(count _x) >= 2}) then {
            private _code = "";
            private _label = "";
            private _txt = { params ["_v"]; if (_v isEqualType "") then { _v } else { str _v } };
            switch (true) do {
                case (_section in _refSections): {
                    _code = [_x select 0] call _txt;
                    _label = [_x param [[1, 2] select _english, ""]] call _txt;
                    if (_label isEqualTo "") then { _label = [_x select 1] call _txt; };
                };
                case (_section isEqualTo "campaigns"): {
                    _code = [_x select 0] call _txt;
                    _label = [_x select 1] call _txt;
                };
                case (_section isEqualTo "lore"): {
                    private _ref = [_x param [1, ""]] call _txt;
                    private _name = [_x param [2, ""]] call _txt;
                    _code = [_x select 0] call _txt;
                    if (_code isEqualTo "") then { _code = _ref; };
                    _label = if (_name isEqualTo "") then { _ref } else { format ["%1 · %2", _ref, _name] };
                };
                default {
                    // cases, sites, wanted : la référence est le code envoyé au site.
                    private _name = [_x param [2, ""]] call _txt;
                    _code = [_x param [1, ""]] call _txt;
                    _label = if (_name isEqualTo "") then { _code } else { format ["%1 · %2", _code, _name] };
                };
            };
            if (_code isNotEqualTo "" && {!(_code in _outCodes)}) then {
                _outCodes pushBack _code;
                _outLabels pushBack _label;
            };
        };
    } forEach _rows;
    if (_outCodes isEqualTo []) exitWith { [_codes, _labels, "local"] };
    [_outCodes, _outLabels, (["state"] call comspec_overwatch_connect_fnc_sseCatalogue) select 0]
};

if (_op in ["ensure", "refresh"]) exitWith {
    if (!hasInterface) exitWith { false };
    private _force = _op isEqualTo "refresh";
    private _fresh = _fromSite && {(diag_tickTime - (_meta select 2)) < (_meta select 3)};
    if (!_force && {_fresh}) exitWith { false };
    if ((diag_tickTime - (uiNamespace getVariable ["COMSPEC_SseCatalogueBusy", -1000])) < 45) exitWith { false };
    if (!_force && {diag_tickTime < (uiNamespace getVariable ["COMSPEC_SseCatalogueRetryAt", -1])}) exitWith { false };
    // Eden : pas de rappel de la DLL, appel direct (une seule fois par minute en cas d'échec).
    if (is3DEN) exitWith { ["fetch", _force] call comspec_overwatch_connect_fnc_sseCatalogue };
    ["fetch", _force] spawn comspec_overwatch_connect_fnc_sseCatalogue;
    true
};

if (_op isNotEqualTo "fetch") exitWith { false };
if (!canSuspend && {!is3DEN}) exitWith { ["fetch", _arg] spawn comspec_overwatch_connect_fnc_sseCatalogue; false };
if ((diag_tickTime - (uiNamespace getVariable ["COMSPEC_SseCatalogueBusy", -1000])) < 45) exitWith { false };
// Verrou daté : une lecture interrompue ne bloque pas les suivantes plus de 45 s.
uiNamespace setVariable ["COMSPEC_SseCatalogueBusy", diag_tickTime];

private _map = missionNamespace getVariable ["COMSPEC_MapId", 1];
if !(_map isEqualType 0) then { _map = 1; };
// Code d'opération de la mission (session de lore) : lettres, chiffres et _ . : - seulement.
private _opKey = missionNamespace getVariable ["comspec_sse_id_opKey", ""];
if !(_opKey isEqualType "") then { _opKey = ""; };
_opKey = toString ((toArray _opKey) select { (_x >= 48 && {_x <= 58}) || {_x >= 65 && {_x <= 90}} || {_x >= 97 && {_x <= 122}} || {_x in [45, 46, 95]} });
_opKey = _opKey select [0, 64];
private _known = if (_fromSite) then { _meta select 0 } else { "" };

private _call = {
    params ["_page"];
    private _json = format ['{"kind":"sse_catalogue","map":%1,"op":"%2","rev":"%3","page":%4}', floor _map, _opKey, _known, _page];
    private _raw = if (canSuspend) then {
        ["COMSPECExtension", "SubmitIdCheck", [_json]] call comspec_overwatch_connect_fnc_extAwait
    } else {
        "COMSPECExtension" callExtension ["SubmitIdCheck", [_json]]
    };
    _raw = [_raw] call comspec_overwatch_connect_fnc_extResult;
    if (!(_raw isEqualType "") || {(_raw select [0, 3]) isNotEqualTo "OK|"}) exitWith { [] };
    // OK|id|page : la page commence après le deuxième « | ».
    private _rest = _raw select [3];
    private _cut = _rest find "|";
    if (_cut < 0) exitWith { [] };
    private _text = _rest select [_cut + 1];
    if ((_text select [0, 1]) isNotEqualTo "[") exitWith { [] };
    private _parsed = parseSimpleArray _text;
    if (!(_parsed isEqualType []) || {(count _parsed) < 9}) exitWith { [] };
    _parsed
};

private _ok = false;
private _fresh = createHashMap;
private _head = [];
private _page = 0;
private _guard = 0;
private _unchanged = false;
while { _guard < 12 } do {
    _guard = _guard + 1;
    private _p = [_page] call _call;
    if (_p isEqualTo []) exitWith {};
    if (_head isEqualTo []) then { _head = _p; };
    // Paquet changé entre deux pages : lecture abandonnée, reprise au prochain passage.
    if ((_p select 1) isNotEqualTo (_head select 1)) exitWith {};
    if ((_p param [9, false]) isEqualTo true) exitWith { _unchanged = true; _ok = true; };
    {
        _x params [["_name", "", [""]], ["_rows", [], [[]]]];
        if (_name isNotEqualTo "") then {
            private _list = _fresh getOrDefault [_name, []];
            _list append _rows;
            _fresh set [_name, _list];
        };
    } forEach (_p param [6, []]);
    private _next = _p param [5, 0];
    if (!(_next isEqualType 0) || {_next <= _page}) exitWith { _ok = true; };
    _page = _next;
};

systemTime params ["_y", "_mo", "_d", "_h", "_mi"];
private _two = { params ["_n"]; if (_n < 10) then { "0" + str _n } else { str _n } };
private _clock = format ["%1:%2", [_h] call _two, [_mi] call _two];

if (_ok) then {
    if (!_unchanged) then {
        uiNamespace setVariable ["COMSPEC_SseCatalogue", _fresh];
        // Référentiels gardés sur le poste : repli de la prochaine partie si le site ne répond pas.
        private _keep = [];
        { if (_x in _fresh) then { _keep pushBack [_x, _fresh get _x]; }; } forEach _refSections;
        profileNamespace setVariable ["COMSPEC_SseCatalogueRef", [format ["%1/%2 %3", [_d] call _two, [_mo] call _two, _clock], _keep]];
        if (!isNil "comspec_overwatch_connect_fnc_saveProfile") then { [] call comspec_overwatch_connect_fnc_saveProfile; };
    };
    private _ttl = _head param [3, 600];
    if (!(_ttl isEqualType 0) || {_ttl < 60}) then { _ttl = 600; };
    uiNamespace setVariable ["COMSPEC_SseCatalogueMeta", [_head select 1, _clock, diag_tickTime, _ttl, _head param [7, []], _head param [8, []]]];
} else {
    uiNamespace setVariable ["COMSPEC_SseCatalogueRetryAt", diag_tickTime + 60];
};
uiNamespace setVariable ["COMSPEC_SseCatalogueBusy", -1000];
if (!is3DEN && {!isNil "CBA_fnc_localEvent"}) then {
    ["comspec_sse_catalogueUpdated", [(["state"] call comspec_overwatch_connect_fnc_sseCatalogue) select 0]] call CBA_fnc_localEvent;
};
_ok
