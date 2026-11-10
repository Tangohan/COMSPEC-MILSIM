/*
    Zone de lecture automatique (MRZ, ICAO 9303).
    [_doc] call comspec_sse_fnc_idMrz            -> lignes imprimées : TD1 (carte « I< », permis « D< », carte militaire « A< », 3 x 30),
                                                  TD3 (passeport, 2 x 44) ; même écriture que SseMrzService du site
    ["parse", _lines] call comspec_sse_fnc_idMrz -> HashMap : format (TD1 | TD3 | ""), ok, errors [codes], number, dob,
                                                  expires (AAMMJJ), sex, nat, issuer, last, first
    Codes d'erreur : format, number, dob, expiry, personal, composite.
    Document (HashMap) : type, country, number, last, first, sex, dob / expires (AAAA-MM-JJ), nat (code MRZ),
    mrzIssuer (code MRZ de l'émetteur ; à défaut celui du pays, fn_idCountries « mrz » : Allemagne DEU -> « D<< »).
    Faux : mrzFlaw "check" (chiffre de contrôle du numéro faux) ; mrzDob / mrzLast / mrzNumber (MRZ différente du
    recto, document modifié sans refaire la MRZ).
*/
params [["_a", createHashMap], ["_b", []]];
private _ck = { str ([_this] call comspec_sse_fnc_idMrzCheck) };
private _pad = {
    params ["_s", "_n"];
    _s = _s select [0, _n];
    while { count _s < _n } do { _s = _s + "<"; };
    _s
};

if (_a isEqualType "" && {_a isEqualTo "parse"}) exitWith {
    private _l = _b apply { toUpper _x };
    private _r = createHashMapFromArray [["format", ""], ["ok", false], ["errors", []]];
    private _err = _r get "errors";
    private _nm = {
        private _p = _this splitString "<";
        // « NOM<<PRENOM<SECOND » : premier bloc = nom ; le reste = prénoms.
        private _i = _this find "<<";
        if (_i < 0) exitWith { [(_p joinString " "), ""] };
        [((_this select [0, _i]) splitString "<") joinString " ", ((_this select [_i + 2]) splitString "<") joinString " "]
    };
    if ((count _l) isEqualTo 3 && {(_l findIf { (count _x) isNotEqualTo 30 }) < 0}) exitWith {
        _l params ["_l1", "_l2", "_l3"];
        _r set ["format", "TD1"];
        _r set ["issuer", _l1 select [2, 3]];
        _r set ["number", (_l1 select [5, 9]) regexReplace ["<", ""]];
        if (((_l1 select [5, 9]) call _ck) isNotEqualTo (_l1 select [14, 1])) then { _err pushBack "number"; };
        _r set ["dob", _l2 select [0, 6]];
        if (((_l2 select [0, 6]) call _ck) isNotEqualTo (_l2 select [6, 1])) then { _err pushBack "dob"; };
        _r set ["sex", _l2 select [7, 1]];
        _r set ["expires", _l2 select [8, 6]];
        if (((_l2 select [8, 6]) call _ck) isNotEqualTo (_l2 select [14, 1])) then { _err pushBack "expiry"; };
        _r set ["nat", _l2 select [15, 3]];
        private _comp = (_l1 select [5, 25]) + (_l2 select [0, 7]) + (_l2 select [8, 7]) + (_l2 select [18, 11]);
        if ((_comp call _ck) isNotEqualTo (_l2 select [29, 1])) then { _err pushBack "composite"; };
        (_l3 call _nm) params ["_last", "_first"];
        _r set ["last", _last];
        _r set ["first", _first];
        _r set ["ok", _err isEqualTo []];
        _r
    };
    if ((count _l) isEqualTo 2 && {(_l findIf { (count _x) isNotEqualTo 44 }) < 0}) exitWith {
        _l params ["_l1", "_l2"];
        _r set ["format", "TD3"];
        _r set ["issuer", _l1 select [2, 3]];
        ((_l1 select [5]) call _nm) params ["_last", "_first"];
        _r set ["last", _last];
        _r set ["first", _first];
        _r set ["number", (_l2 select [0, 9]) regexReplace ["<", ""]];
        if (((_l2 select [0, 9]) call _ck) isNotEqualTo (_l2 select [9, 1])) then { _err pushBack "number"; };
        _r set ["nat", _l2 select [10, 3]];
        _r set ["dob", _l2 select [13, 6]];
        if (((_l2 select [13, 6]) call _ck) isNotEqualTo (_l2 select [19, 1])) then { _err pushBack "dob"; };
        _r set ["sex", _l2 select [20, 1]];
        _r set ["expires", _l2 select [21, 6]];
        if (((_l2 select [21, 6]) call _ck) isNotEqualTo (_l2 select [27, 1])) then { _err pushBack "expiry"; };
        // Numéro personnel vide : chiffre « < » ou 0 admis.
        private _pn = _l2 select [28, 14];
        private _pc = _l2 select [42, 1];
        if !((_pn call _ck) isEqualTo _pc || {_pc isEqualTo "<" && {(_pn regexReplace ["<", ""]) isEqualTo ""}}) then { _err pushBack "personal"; };
        private _comp = (_l2 select [0, 10]) + (_l2 select [13, 7]) + (_l2 select [21, 22]);
        if ((_comp call _ck) isNotEqualTo (_l2 select [43, 1])) then { _err pushBack "composite"; };
        _r set ["ok", _err isEqualTo []];
        _r
    };
    _err pushBack "format";
    _r
};

// --- Écriture -------------------------------------------------------------------------------------------------
private _doc = _a;
private _type = _doc getOrDefault ["type", "id_card"];
if !(_type in ["id_card", "passport", "driving_license", "military_id"]) then {
    // Type d'extension (CfgComspecDocuments) : gabarit TD1 (code « I< ») ou TD3 selon sa déclaration, sinon pas de MRZ.
    private _m = (["get", _type] call comspec_sse_fnc_idDocTypes) getOrDefault ["mrz", ""];
    _type = ["", "id_card", "passport"] select ((["", "td1", "td3"] find _m) max 0);
};
if (_type isEqualTo "") exitWith { [] };
// Texte MRZ (même table que SseMrzService du site) : capitales sans accents, ß -> SS, Æ -> AE, Œ -> OE, tout autre
// caractère -> « < ». Par points de code (toArray) : les expressions régulières d'Arma travaillent sur les octets
// UTF-8 et cassaient les lettres accentuées.
private _tl = missionNamespace getVariable "comspec_sse_idMrzTl";
if (isNil "_tl") then {
    _tl = createHashMap;
    { _x params ["_to", "_codes"]; { _tl set [_x, _to]; } forEach _codes; } forEach [
        ["A", [192, 193, 194, 195, 196, 197, 224, 225, 226, 227, 228, 229, 260, 261]], ["AE", [198, 230]],
        ["C", [199, 231, 262, 263, 268, 269]], ["D", [270, 271, 272, 273]],
        ["E", [200, 201, 202, 203, 232, 233, 234, 235, 280, 281, 282, 283]], ["I", [204, 205, 206, 207, 236, 237, 238, 239]],
        ["L", [321, 322]], ["N", [209, 241, 323, 324, 327, 328]],
        ["O", [210, 211, 212, 213, 214, 216, 242, 243, 244, 245, 246, 248]], ["OE", [338, 339]], ["R", [344, 345]],
        ["S", [346, 347, 352, 353]], ["SS", [223, 7838]], ["T", [356, 357]],
        ["U", [217, 218, 219, 220, 249, 250, 251, 252, 366, 367]], ["Y", [221, 253, 255, 376]], ["Z", [377, 378, 379, 380, 381, 382]]
    ];
    missionNamespace setVariable ["comspec_sse_idMrzTl", _tl];
};
private _clean = {
    private _out = [];
    {
        private _c = _x;
        if (_c >= 97 && {_c <= 122}) then { _c = _c - 32; };
        if ((_c >= 65 && {_c <= 90}) || {_c >= 48 && {_c <= 57}} || {_c isEqualTo 60}) then {
            _out pushBack _c;
        } else {
            private _t = _tl getOrDefault [_c, ""];
            if (_t isEqualTo "") then { _out pushBack 60; } else { _out append (toArray _t); };
        };
    } forEach (toArray (trim _this));
    toString _out
};
// Champ nom ICAO : NOM<<PRÉNOMS (suites de « < » réduites à un, sans « < » aux bouts) ; prénom vide : NOM seul.
private _nameField = {
    params ["_l", "_f"];
    private _norm = { ((_this call _clean) splitString "<") joinString "<" };
    _l = _l call _norm;
    _f = _f call _norm;
    [_l, _l + "<<" + _f] select (_f isNotEqualTo "")
};
private _ymd = {
    // AAAA-MM-JJ -> AAMMJJ
    private _p = _this splitString "-";
    if ((count _p) < 3) exitWith { "<<<<<<" };
    ((_p select 0) select [2, 2]) + (_p select 1) + (_p select 2)
};
private _cc = _doc getOrDefault ["country", "ALT"];
private _iss = _doc getOrDefault ["mrzIssuer", ""];
if (_iss isEqualTo "") then { _iss = (([] call comspec_sse_fnc_idCountries) getOrDefault [_cc, createHashMap]) getOrDefault ["mrz", _cc]; };
private _issuer = [_iss call _clean, 3] call _pad;
// Nationalité : code MRZ ; même pays que l'émetteur (code du pays ou code MRZ) -> code MRZ de l'émetteur.
private _natRaw = _doc getOrDefault ["nat", _iss];
if (_natRaw isEqualTo _cc) then { _natRaw = _iss; };
private _nat = [_natRaw call _clean, 3] call _pad;
private _num = [(_doc getOrDefault ["mrzNumber", _doc getOrDefault ["number", ""]]) call _clean, 9] call _pad;
private _numCk = _num call _ck;
// Faux mal fait : chiffre de contrôle du numéro décalé.
if ((_doc getOrDefault ["mrzFlaw", ""]) isEqualTo "check") then { _numCk = str (((parseNumber _numCk) + 3) mod 10); };
private _dob = (_doc getOrDefault ["mrzDob", _doc getOrDefault ["dob", ""]]) call _ymd;
private _exp = (_doc getOrDefault ["expires", ""]) call _ymd;
private _sex = [_doc getOrDefault ["sex", "<"], "<"] select !((_doc getOrDefault ["sex", ""]) in ["M", "F"]);
private _name = [_doc getOrDefault ["mrzLast", _doc getOrDefault ["last", ""]], _doc getOrDefault ["first", ""]] call _nameField;

// Carte : TD1 « I< » ; permis (hors ICAO) : même gabarit, code « D< » (comme le site, pour le lecteur N1).
if (_type in ["id_card", "driving_license", "military_id"]) exitWith {
    private _l1 = [(["I<", "D<", "A<"] select ((["id_card", "driving_license", "military_id"] find _type) max 0)) + _issuer + _num + _numCk, 30] call _pad;
    private _l2 = _dob + (_dob call _ck) + _sex + _exp + (_exp call _ck) + _nat;
    _l2 = [_l2, 29] call _pad;
    private _comp = (_l1 select [5, 25]) + (_l2 select [0, 7]) + (_l2 select [8, 7]) + (_l2 select [18, 11]);
    _l2 = _l2 + (_comp call _ck);
    [_l1, _l2, [_name, 30] call _pad]
};
private _l1 = ["P<" + _issuer + _name, 44] call _pad;
private _pn = [(_doc getOrDefault ["personal", ""]) call _clean, 14] call _pad;
// Numéro personnel vide : chiffre de contrôle « < » (vaut 0 dans le chiffre global).
private _pnCk = [_pn call _ck, "<"] select ((_pn splitString "<") isEqualTo []);
private _l2 = _num + _numCk + _nat + _dob + (_dob call _ck) + _sex + _exp + (_exp call _ck) + _pn + _pnCk;
private _comp = (_l2 select [0, 10]) + (_l2 select [13, 7]) + (_l2 select [21, 22]);
[_l1, _l2 + (_comp call _ck)]
