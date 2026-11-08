/*
    Bibliothèque de l'éditeur reliée à Athena (client). La bibliothèque du poste (profileNamespace) reste la référence
    de travail : on y écrit d'abord, Athena suit dès qu'il répond. Site injoignable, compte non relié ou sans habilitation :
    tout continue sur ce poste, les contenus restent marqués « dirty » et repartent à la prochaine ouverture.
    Canal : SubmitIdCheck de COMSPEC Link (champ kind, réponse OK|id|ref, aucune nouvelle DLL). Contrat :
    docs/technical/ICD-ATHENA-ATAK.md, ICD-SSE-EDITOR-001. Le jeton du compte relié sert d'identité : c'est le site qui
    vérifie l'habilitation et borne les données à la communauté.
    Eden : aucun rappel de la DLL, appels directs. Après un échec, plus d'essai pendant 60 s pour ne pas figer l'éditeur.
      ["newId"]        -> identifiant d'un nouveau contenu
      ["push", [item]]    envoie un contenu
      ["flush"]           envoie les contenus modifiés
      ["pull"]            envoie les contenus modifiés, puis relit la bibliothèque de la communauté
*/
params [["_op", "", [""]], ["_arg", []]];
if (!hasInterface) exitWith { false };
if (_op isEqualTo "newId") exitWith {
    systemTimeUTC params ["_y", "_m", "_d", "_h", "_mi", "_s"];
    format ["E%1%2%3-%4%5%6-%7", _y, _m, _d, _h, _mi, _s, 100000 + floor random 899999]
};
if (!canSuspend && {!is3DEN}) exitWith { _this spawn comspec_sse_fnc_editorLibrary; true };

private _lib = profileNamespace getVariable ["comspec_sse_editorLib", []];

private _send = {
    params ["_payload"];
    if (is3DEN && {diag_tickTime < (uiNamespace getVariable ["comspec_sse_editorRetryAt", -1])}) exitWith { "ERR|offline" };
    if (is3DEN && {!(uiNamespace getVariable ["comspec_sse_editorRestored", false])}) then {
        // Session Athena mémorisée : reprise une fois (Connexion Athena du menu COMSPEC SSE).
        uiNamespace setVariable ["comspec_sse_editorRestored", true];
        ["restore"] call comspec_sse_fnc_athenaEden;
    };
    if !(["has"] call comspec_sse_fnc_idAthenaPack) exitWith { "ERR|no_link" };
    private _json = [_payload] call comspec_sse_fnc_toJsonApprox;
    private _parts = [];
    for "_i" from 0 to ((count _json) - 1) step 4000 do { _parts pushBack (_json select [_i, 4000]); };
    private _raw = ["SubmitIdCheck", _parts] call comspec_sse_fnc_extensionCall;
    if (is3DEN && {(_raw select [0, 3]) isNotEqualTo "OK|"}) then { uiNamespace setVariable ["comspec_sse_editorRetryAt", diag_tickTime + 60]; };
    _raw
};
private _isOk = { (_this select [0, 3]) isEqualTo "OK|" };
private _code = { (_this splitString "|") param [1, ""] };

private _report = {
    params ["_raw"];
    private _ok = _raw call _isOk;
    private _err = _raw call _code;
    private _text = switch (true) do {
        case (_ok): { "Bibliothèque à jour avec Athena." };
        case (_err in ["forbidden", "unauthorized", "no_tenant"]): { "Compte Athena non relié ou sans habilitation Confidentiel : bibliothèque de ce poste." };
        case (_err isEqualTo "too_large"): { "Contenu trop long pour Athena : gardé sur ce poste." };
        default { "Athena injoignable : bibliothèque de ce poste." };
    };
    ["status", [_text call comspec_sse_fnc_tr, _ok]] call comspec_sse_fnc_editor;
};

private _wire = {
    params ["_it"];
    private _history = _it getOrDefault ["history", []];
    // Retours à la ligne d'un champ de saisie : un seul format, le seul que toJsonApprox échappe.
    private _body = [[_it get "body", toString [13], ""] call CBA_fnc_replace, toString [10], endl] call CBA_fnc_replace;
    createHashMapFromArray [
        ["id", _it get "id"], ["type", _it get "type"], ["title", _it get "title"], ["body", _body],
        ["marking", _it get "marking"], ["paper", _it get "paper"], ["nature", _it get "nature"], ["delivery", _it get "delivery"],
        ["state", _it get "state"], ["op", _it get "op"], ["link", _it get "link"], ["version", _it get "version"],
        ["history", _history select [(((count _history) - 15) max 0), 15]], ["found", _it getOrDefault ["found", []]]
    ]
};

// Modèles de départ : propres à chaque poste, jamais envoyés.
private _isSeed = { (_this get "id") in ["M0", "M1", "M2"] };

private _push = {
    params ["_it"];
    private _raw = [createHashMapFromArray [["kind", "editor_save"], ["item", [_it] call _wire]]] call _send;
    if !(_raw call _isOk) exitWith { _raw };
    if (((_raw splitString "|") param [2, ""]) isEqualTo "conflict") then {
        // Modifié entre-temps sur un autre poste : rien n'est perdu, ce travail devient une copie, l'original revient au pull.
        _it set ["id", ["newId"] call comspec_sse_fnc_editorLibrary];
        _it set ["title", format ["%1 (%2)", _it get "title", "copie de ce poste" call comspec_sse_fnc_tr]];
        _it set ["version", 1];
        _raw = [createHashMapFromArray [["kind", "editor_save"], ["item", [_it] call _wire]]] call _send;
    };
    if (_raw call _isOk) then { _it set ["dirty", false]; };
    _raw
};

// Envoie les contenus modifiés. Renvoie la première vraie panne ; un contenu trop long reste sur le poste, sans bloquer les autres.
private _flush = {
    private _result = "OK|";
    {
        if ((_result call _isOk) && {!(_x call _isSeed)} && {_x getOrDefault ["dirty", true]}) then {
            private _raw = [_x] call _push;
            if (!(_raw call _isOk) && {(_raw call _code) isNotEqualTo "too_large"}) then { _result = _raw; };
        };
    } forEach _lib;
    saveProfileNamespace;
    _result
};

private _decode = {
    params ["_s"];
    [[_s, "\n", toString [13, 10]] call CBA_fnc_replace, "\p", "|"] call CBA_fnc_replace
};

switch (_op) do {
    case "push": {
        _arg params [["_it", createHashMap, [createHashMap]]];
        if ((count _it) isEqualTo 0 || {_it call _isSeed}) exitWith {};
        private _raw = [_it] call _push;
        saveProfileNamespace;
        if !(_raw call _isOk) then { [_raw] call _report; };
    };
    case "flush": { call _flush; };
    case "pull": {
        // Une seule relecture à la fois.
        if (diag_tickTime - (uiNamespace getVariable ["comspec_sse_editorPullAt", -100]) < 5) exitWith {};
        uiNamespace setVariable ["comspec_sse_editorPullAt", diag_tickTime];
        private _raw = call _flush;
        if !(_raw call _isOk) exitWith { [_raw] call _report; };

        private _rows = [];
        private _offset = 0;
        private _pages = 0;
        while { _pages < 60 } do {
            _pages = _pages + 1;
            _raw = [createHashMapFromArray [["kind", "editor_list"], ["offset", _offset]]] call _send;
            if !(_raw call _isOk) exitWith {};
            private _p = _raw splitString "|";
            private _page = parseSimpleArray (_p param [2, "[]"]);
            if (_page isEqualType []) then { _rows append _page; };
            _offset = parseNumber (_p param [1, "0"]);
            if (_offset <= 0) exitWith {};
        };
        if !(_raw call _isOk) exitWith { [_raw] call _report; };

        {
            _x params ["_id", "_type", "_title", "_body", "_marking", "_paper", "_nature", "_delivery", "_state", "_opName", "_link", "_version", "_history", "_found"];
            private _site = createHashMapFromArray [
                ["id", _id], ["type", _type], ["title", [_title] call _decode], ["body", [_body] call _decode],
                ["marking", _marking], ["paper", _paper], ["nature", _nature], ["delivery", _delivery], ["state", _state],
                ["op", [_opName] call _decode], ["link", [_link] call _decode], ["version", _version], ["when", ""],
                ["history", _history apply { _x apply { [_x] call _decode } }], ["found", _found apply { [_x] call _decode }], ["dirty", false]
            ];
            private _i = _lib findIf { (_x get "id") isEqualTo _id };
            if (_i < 0) then {
                _lib pushBack _site;
            } else {
                private _local = _lib select _i;
                private _mine = _local getOrDefault ["found", []];
                // Découverte vue ici pendant que le site ne répondait pas : gardée, repart au prochain envoi.
                if (_found isEqualTo [] && {_mine isNotEqualTo []}) then { _site set ["found", _mine]; _site set ["dirty", true]; };
                if (!(_local getOrDefault ["dirty", true]) || {_version > (_local get "version")}) then { _local merge [_site, true]; };
            };
        } forEach (_rows select { _x isEqualType [] && {(count _x) >= 14} });
        profileNamespace setVariable ["comspec_sse_editorLib", _lib];
        saveProfileNamespace;
        [_raw] call _report;
        ["libRefresh"] call comspec_sse_fnc_editor;
    };
};
true
