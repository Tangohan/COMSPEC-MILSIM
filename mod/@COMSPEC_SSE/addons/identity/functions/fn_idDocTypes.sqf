/*
    Registre des types de documents : CfgComspecDocuments (papiers intégrés + extensions, CfgComspecDocuments.hpp).
    [] call comspec_sse_fnc_idDocTypes                    -> HashMap clé -> type (tous, extensions coupées comprises)
    ["get", clé]                                          -> HashMap du type (vide si inconnu)
    ["list", [pays, avecCoupés]]                          -> clés servies : extensions actives, pays servi ; dans l'ordre
                                                             des catégories (identity, travel, permit, professional, pass, other)
    ["label", clé | [clé, pays]]  -> nom affiché (langue du téléphone ; avec le pays : nom du papier de ce pays)   ["item", clé] -> classe de l'objet vierge
    ["ofItem", classe]  -> clé du type d'un objet (vierge ou exemplaire <item>_NNN ; "" sinon)
    ["stock", clé]      -> nombre d'exemplaires déclarés (<item>_001…)
    ["texture", [clé, côté, codePays, zone]]  -> chemin PAA (repli : gabarit « bare » des pays réels, ou zone eu/am/ot, puis alt)
    ["side", [clé, côté]]  -> HashMap de mise en page : texture, photo [x,y,w,h] ou [], holo [x,y,w] ou [], fields [...]
    ["extensions"]      -> [[classe, nom, auteur, version, description, [clés…], aperçu, active, intégrée, coupée par défaut]…]
    ["enabled", classe d'extension]  -> Boolean (liste des extensions coupées : comspec_sse_docExtOff, serveur, diffusée)
    ["defaultOff"]      -> classes (minuscules) des extensions déclarées defaultOff = 1 (coupées tant qu'on ne les active pas)
    ["refresh"]         -> vide le cache (après activation / désactivation d'une extension)
    Type (HashMap) : key, name, nameEn, category, item, icon, ratio, sides, countries, mrz, mrzSide, mrzY, mrzStep,
    extension, inputs [[clé, libellé, défaut]…], recto / verso (HashMap de mise en page ; champ : key, x, y, w, h,
    transform, font, color, text, input, label, default).
*/
params [["_op", "", [""]], ["_a", [], [[], ""]]];
private _cats = ["identity", "travel", "permit", "professional", "pass", "other"];
private _all = uiNamespace getVariable "comspec_sse_idDocTypesCache";
if (_op isEqualTo "refresh") exitWith { uiNamespace setVariable ["comspec_sse_idDocTypesCache", nil]; true };
if (isNil "_all") then {
    _all = createHashMap;
    private _num = { params ["_cfg", "_def"]; if (isNumber _cfg) then { getNumber _cfg } else { _def } };
    private _txt = { params ["_cfg", "_def"]; if (isText _cfg) then { getText _cfg } else { _def } };
    private _readSide = {
        params ["_cfg"];
        if (!isClass _cfg) exitWith { createHashMap };
        private _s = createHashMapFromArray [["texture", [_cfg >> "texture", ""] call _txt], ["photo", []], ["holo", []], ["fields", []]];
        if (isClass (_cfg >> "photo")) then {
            _s set ["photo", [[_cfg >> "photo" >> "x", 0.04] call _num, [_cfg >> "photo" >> "y", 0.2] call _num, [_cfg >> "photo" >> "w", 0.26] call _num, [_cfg >> "photo" >> "h", 0.56] call _num]];
        };
        if (isClass (_cfg >> "holo")) then {
            _s set ["holo", [[_cfg >> "holo" >> "x", 0.22] call _num, [_cfg >> "holo" >> "y", 0.6] call _num, [_cfg >> "holo" >> "w", 0.13] call _num]];
        };
        private _fields = [];
        {
            _fields pushBack createHashMapFromArray [
                ["name", configName _x], ["key", [_x >> "key", ""] call _txt], ["x", [_x >> "x", 0] call _num], ["y", [_x >> "y", 0] call _num],
                ["w", [_x >> "w", 0.3] call _num], ["h", [_x >> "h", 0.062] call _num], ["transform", toLower ([_x >> "transform", ""] call _txt)],
                ["font", toLower ([_x >> "font", ""] call _txt)], ["color", [_x >> "color", "#15181d"] call _txt], ["text", [_x >> "text", ""] call _txt],
                ["input", ([_x >> "input", 0] call _num) isEqualTo 1], ["label", [_x >> "label", ""] call _txt], ["default", [_x >> "default", ""] call _txt]
            ];
        } forEach ("true" configClasses (_cfg >> "fields"));
        _s set ["fields", _fields];
        _s
    };
    {
        private _cfg = _x;
        private _key = toLower (configName _cfg);
        private _t = createHashMapFromArray [
            ["key", _key], ["name", [_cfg >> "displayName", _key] call _txt], ["nameEn", [_cfg >> "displayNameEn", ""] call _txt],
            ["category", toLower ([_cfg >> "category", "other"] call _txt)], ["item", [_cfg >> "item", ""] call _txt],
            ["icon", [_cfg >> "icon", ""] call _txt], ["ratio", [_cfg >> "ratio", 1.585] call _num], ["sides", [_cfg >> "sides", 2] call _num],
            ["countries", (getArray (_cfg >> "countries")) apply { toUpper _x }], ["mrz", toLower ([_cfg >> "mrz", ""] call _txt)],
            ["mrzSide", toLower ([_cfg >> "mrzSide", "verso"] call _txt)], ["mrzY", [_cfg >> "mrzY", 0.6] call _num], ["mrzStep", [_cfg >> "mrzStep", 0.11] call _num],
            ["extension", [_cfg >> "extension", ""] call _txt], ["chip", ([_cfg >> "chip", 0] call _num) isEqualTo 1], ["recto", [_cfg >> "recto"] call _readSide], ["verso", [_cfg >> "verso"] call _readSide]
        ];
        if ((_t get "nameEn") isEqualTo "") then { _t set ["nameEn", _t get "name"]; };
        if !((_t get "category") in _cats) then { _t set ["category", "other"]; };
        if ((_t get "sides") < 2 || {(count (_t get "verso")) isEqualTo 0}) then { _t set ["sides", 1]; };
        private _inputs = [];
        { { if (_x get "input") then { _inputs pushBack [_x get "key", [_x get "label", _x get "key"] select ((_x get "label") isEqualTo ""), _x get "default"]; }; } forEach (_x getOrDefault ["fields", []]); } forEach [_t get "recto", _t get "verso"];
        _t set ["inputs", _inputs];
        _all set [_key, _t];
    } forEach ("true" configClasses (configFile >> "CfgComspecDocuments"));
    uiNamespace setVariable ["comspec_sse_idDocTypesCache", _all];
};
if (_op isEqualTo "") exitWith { _all };

private _enabledExt = {
    params ["_ext"];
    if (_ext isEqualTo "") exitWith { true };
    // Liste coupée : variable diffusée par le serveur (fenêtre Extensions), sinon réglage CBA (serveur) au lancement.
    private _off = missionNamespace getVariable "comspec_sse_docExtOff";
    if (isNil "_off") then {
        // Avant la diffusion du serveur : réglage CBA, plus les extensions coupées par défaut (defaultOff = 1).
        _off = (((missionNamespace getVariable ["comspec_sse_docExt_off", ""]) splitString ",; ") apply { toLower (trim _x) })
            + (("getNumber (_x >> 'defaultOff') isEqualTo 1" configClasses (configFile >> "CfgComspecDocumentExtensions")) apply { toLower (configName _x) });
    };
    !((toLower _ext) in _off)
};

switch (_op) do {
    case "get": { _all getOrDefault [toLower _a, createHashMap] };
    case "label": {
        // ["label", clé] ou ["label", [clé, pays]] : nom du papier de ce pays (« Personalausweis » -> « Carte d'identité
        // allemande » n'est pas inventé : registre docNames [langue du pays, français, anglais]).
        private _key = _a;
        private _cc = "";
        if (_a isEqualType []) then { _key = _a param [0, ""]; _cc = toUpper (_a param [1, ""]); };
        private _t = _all getOrDefault [toLower _key, createHashMap];
        if ((count _t) isEqualTo 0) exitWith { _key };
        private _en = (missionNamespace getVariable ["COMSPEC_ATAK_Lang", "fr"]) isNotEqualTo "fr";
        private _dn = [];
        if (_cc isNotEqualTo "" && {!isNil "comspec_sse_fnc_idCountries"}) then {
            _dn = ((([] call comspec_sse_fnc_idCountries) getOrDefault [_cc, createHashMap]) getOrDefault ["docNames", createHashMap]) getOrDefault [toLower _key, []];
        };
        if ((count _dn) isEqualTo 3) exitWith { _dn select ([1, 2] select _en) };
        [_t get "name", _t get "nameEn"] select _en
    };
    case "item": { (_all getOrDefault [toLower _a, createHashMap]) getOrDefault ["item", ""] };
    case "ofItem": {
        private _cls = toLower _a;
        if (_cls isEqualTo "") exitWith { "" };
        private _base = _cls;
        // Exemplaire <item>_NNN : classe de l'objet vierge.
        if ((count _cls) > 4 && {(_cls select [(count _cls) - 4, 1]) isEqualTo "_"} && {(parseNumber (_cls select [(count _cls) - 3])) > 0}) then { _base = _cls select [0, (count _cls) - 4]; };
        private _k = (keys _all) findIf { (toLower ((_all get _x) get "item")) in [_cls, _base] };
        if (_k < 0) exitWith { "" };
        (keys _all) select _k
    };
    case "stock": {
        private _item = (_all getOrDefault [toLower _a, createHashMap]) getOrDefault ["item", ""];
        if (_item isEqualTo "") exitWith { 0 };
        private _n = 0;
        while { isClass (configFile >> "CfgWeapons" >> format ["%1_%2", _item, [_n + 1, 3] call CBA_fnc_formatNumber]) } do { _n = _n + 1; };
        _n
    };
    case "enabled": { [_a] call _enabledExt };
    case "defaultOff": { ("getNumber (_x >> 'defaultOff') isEqualTo 1" configClasses (configFile >> "CfgComspecDocumentExtensions")) apply { toLower (configName _x) } };
    case "list": {
        _a params [["_country", "", [""]], ["_withOff", false, [false]]];
        private _docTypes = [];
        if (_country isNotEqualTo "" && {!isNil "comspec_sse_fnc_idCountries"}) then {
            _docTypes = (([] call comspec_sse_fnc_idCountries) getOrDefault [toUpper _country, createHashMap]) getOrDefault ["docTypes", []];
        };
        if (_docTypes isEqualTo []) then { _docTypes = keys _all; };
        private _keys = keys _all;
        _keys = _keys select {
            private _t = _all get _x;
            (_withOff || {[_t get "extension"] call _enabledExt})
            && {_country isEqualTo "" || {(_t get "countries") isEqualTo []} || {(toUpper _country) in (_t get "countries")}}
            // Papier intégré : seulement ceux que le pays délivre (registre docTypes ; Royaume-Uni : pas de carte nationale).
            && {_country isEqualTo "" || {(_t get "extension") isNotEqualTo ""} || {_x in _docTypes}}
        };
        // Intégrés avant les extensions, puis par catégorie, puis par nom.
        _keys = [_keys, [], { private _t = _all get _x; format ["%1|%2|%3", ["0", "1"] select ((_t get "extension") isNotEqualTo ""), [_cats find (_t get "category"), 1] call CBA_fnc_formatNumber, toLower (_t get "name")] }, "ASCEND"] call BIS_fnc_sortBy;
        _keys
    };
    case "texture": {
        _a params [["_key", "", [""]], ["_side", "recto", [""]], ["_country", "alt", [""]], ["_zone", "", [""]]];
        private _t = _all getOrDefault [toLower _key, createHashMap];
        private _tex = (_t getOrDefault [toLower _side, createHashMap]) getOrDefault ["texture", ""];
        if (_tex isEqualTo "" && {(toLower _side) isEqualTo "verso"}) then { _tex = (_t getOrDefault ["recto", createHashMap]) getOrDefault ["texture", ""]; };
        if ((_tex find "%1") < 0) exitWith { _tex };
        private _codes = [toLower _country];
        // « bare » : pays réel du registre, gabarit sans texte (titre, étiquettes, bandeau posés en jeu) ; sinon zone de style.
        if (_zone isEqualTo "bare") then { _codes pushBack "bare"; } else {
            if (_zone isNotEqualTo "") then { _codes pushBack (["ot", "eu", "am"] select ((["other", "europe", "americas"] find (toLower _zone)) max 0)); };
        };
        _codes pushBackUnique "alt";
        private _found = "";
        {
            private _p = format [_tex, _x];
            // Un fichier absent fait planter l'image : on vérifie dans la liste des fichiers du jeu (fileExists lit les PBO).
            if (fileExists _p) exitWith { _found = _p; };
        } forEach _codes;
        _found
    };
    case "side": {
        _a params [["_key", "", [""]], ["_side", "recto", [""]]];
        private _t = _all getOrDefault [toLower _key, createHashMap];
        private _s = _t getOrDefault [toLower _side, createHashMap];
        if ((count _s) isEqualTo 0) then { _s = _t getOrDefault ["recto", createHashMap]; };
        _s
    };
    case "extensions": {
        private _out = [];
        {
            private _cls = configName _x;
            private _docs = ((keys _all) select { ((_all get _x) get "extension") isEqualTo _cls }) + ((getArray (_x >> "documents")) apply { toLower _x });
            _docs = _docs arrayIntersect _docs;
            _docs = _docs select { _x in _all };
            _out pushBack [_cls, getText (_x >> "name"), getText (_x >> "author"), getText (_x >> "version"), getText (_x >> "description"), _docs,
                getText (_x >> "preview"), [_cls] call _enabledExt, (getNumber (_x >> "builtin")) isEqualTo 1, (getNumber (_x >> "defaultOff")) isEqualTo 1];
        } forEach ("true" configClasses (configFile >> "CfgComspecDocumentExtensions"));
        _out
    };
    default { _all };
};
