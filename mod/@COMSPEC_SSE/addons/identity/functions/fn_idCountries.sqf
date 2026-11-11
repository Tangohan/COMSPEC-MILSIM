/*
    Pays des papiers : registre CfgComspecCountries (CfgComspecCountries.hpp, généré depuis tools/countries/countries.json ;
    une extension peut ajouter ses pays dans son config.cpp), puis pays du paquet Athena par-dessus.
    Pays réels d'abord (France, Allemagne, États-Unis… : codes ISO 3166 alpha-3, stables, repris par le site), puis les
    pays fictifs d'Arma (ALT, TNA, LVN, MLD), gardés pour les missions existantes.
    [] call comspec_sse_fnc_idCountries               -> HashMap code -> HashMap pays
    ["default"] call …                                -> code du pays de la mission (réglage « Pays des papiers », sinon
                                                         selon la carte ; pays archivé sur Athena : premier pays actif)
    ["auto"] call …                                   -> code choisi par la carte (maps[] du registre), sinon le réglage
                                                         « Pays par défaut des papiers » (carte inconnue)
    ["resolve", valeur] call …                        -> code valide : "auto" -> pays de la mission ; alpha-2 (« FR »),
                                                         minuscules, ancien nom (« Altis ») -> code ; inconnu -> pays de la mission
    ["ordered", avecFictifs] call …                   -> codes dans l'ordre des listes (réels, puis fictifs)
    ["label", [code, "fr"|"en"]] call …               -> nom affiché (langue du jeu par défaut)
    ["region", [code, région]] call …                 -> HashMap de la région (name ; États-Unis : dl, id, plate, authority, area)
    ["usable", [code, type]] call …                   -> Boolean : le pays délivre encore ce type (actif, docTypes)
    Clés d'un pays : code, iso2, mrz, tier (full | generic | fictional), fictional, order, name (FR), nameEn, nameNative,
    nameDoc, shortFr, shortEn, zone, colors [principale, accent], flagKind, flagColors, languages, languageName(En), speech,
    dateFormat, height (cm | ftin), currency, phone, postal, address, streets, cities (noms), cityRows ([ville, code postal,
    région]), plate, docTypes, primary, printPersonal, natText, maps, first, firstF, last, lastF, lastFSuffix, idCheck,
    number_format (pays fictif : motif ; pays réel : HashMap type -> motif), authority, authorities, validity, mrzCodes,
    docNames, police, labels, regions (HashMap code -> HashMap), chip, active.
    Pays fictifs : mêmes valeurs que le site (SseIdCountryRepository) : une graine donne la même identité des deux côtés.
*/
params [["_mode", ""], ["_args", []]];
private _base = missionNamespace getVariable "comspec_sse_idCountryReg";
if (isNil "_base") then {
    _base = createHashMap;
    private _read = {
        params ["_cfg"];
        private _h = createHashMap;
        {
            private _n = configName _x;
            call {
                if (isClass _x) exitWith { _h set [_n, [_x] call _read]; };
                if (isArray _x) exitWith { _h set [_n, getArray _x]; };
                if (isNumber _x) exitWith { _h set [_n, getNumber _x]; };
                _h set [_n, getText _x];
            };
        } forEach configProperties [_cfg, "true", true];
        _h
    };
    {
        private _c = [_x] call _read;
        private _code = toUpper (_c getOrDefault ["code", configName _x]);
        private _fict = (_c getOrDefault ["tier", "generic"]) isEqualTo "fictional";
        _c set ["code", _code];
        _c set ["fictional", _fict];
        _c set ["realSim", false];
        _c set ["chip", true];
        _c set ["active", true];
        _c set ["theme", ""];
        _c set ["colors", [_c getOrDefault ["colorMain", "#22529E"], _c getOrDefault ["colorAccent", "#B67C46"]]];
        private _rows = (_c getOrDefault ["cities", []]) select { _x isEqualType [] && {_x isNotEqualTo []} };
        _c set ["cityRows", _rows];
        _c set ["cities", _rows apply { _x select 0 }];
        if (_fict) then {
            _c set ["number_format", _c getOrDefault ["numberFormat", "@@#######"]];
            _c set ["validity", _c getOrDefault ["validityYears", 10]];
        } else {
            _c set ["number_format", _c getOrDefault ["numbers", createHashMap]];
            // Régions : classes r_<code> -> HashMap code -> région.
            private _regs = createHashMap;
            { _regs set [_y getOrDefault ["code", _x], _y]; } forEach (_c getOrDefault ["regions", createHashMap]);
            _c set ["regions", _regs];
        };
        _base set [_code, _c];
    } forEach ("true" configClasses (configFile >> "CfgComspecCountries"));
    missionNamespace setVariable ["comspec_sse_idCountryReg", _base];
};

if (_mode isEqualTo "ordered") exitWith {
    private _withFict = [_args, true] select (_args isEqualTo []);
    private _codes = (keys _base) select { _withFict || {!((_base get _x) get "fictional")} };
    [_codes, [], { private _c = _base get _x; format ["%1|%2|%3", parseNumber (_c get "fictional"), [_c getOrDefault ["order", 999], 4] call CBA_fnc_formatNumber, _x] }, "ASCEND"] call BIS_fnc_sortBy
};
if (_mode isEqualTo "label") exitWith {
    _args params [["_code", ""], ["_lang", ["en", "fr"] select (language isEqualTo "French")]];
    private _c = _base getOrDefault [toUpper _code, createHashMap];
    if ((count _c) isEqualTo 0) exitWith { _code };
    if (_c get "fictional") exitWith { [_c getOrDefault ["shortEn", _code], _c getOrDefault ["shortFr", _code]] select (_lang isEqualTo "fr") };
    [_c getOrDefault ["nameEn", _code], _c getOrDefault ["name", _code]] select (_lang isEqualTo "fr")
};
if (_mode isEqualTo "region") exitWith {
    _args params [["_code", ""], ["_r", ""]];
    ((_base getOrDefault [toUpper _code, createHashMap]) getOrDefault ["regions", createHashMap]) getOrDefault [_r, createHashMap]
};
if (_mode isEqualTo "auto") exitWith {
    private _w = toLower worldName;
    private _hit = (keys _base) select { _w in (((_base get _x) getOrDefault ["maps", []]) apply { toLower _x }) };
    if (_hit isNotEqualTo []) exitWith { _hit select 0 };
    // Carte inconnue du registre : réglage « Pays par défaut des papiers » (Altis par défaut, comme avant).
    private _fb = toUpper (missionNamespace getVariable ["comspec_sse_id_countryFallback", "ALT"]);
    [_fb, "ALT"] select !(_fb in _base)
};

if (_mode isEqualTo "default") exitWith {
    private _set = toUpper (missionNamespace getVariable ["comspec_sse_id_country", "auto"]);
    private _all = [] call comspec_sse_fnc_idCountries;
    private _code = if (_set isNotEqualTo "AUTO" && {_set in _all}) then { _set } else { ["auto"] call comspec_sse_fnc_idCountries };
    // Pays archivé sur Athena : premier pays encore actif (registre dans l'ordre des listes, puis ceux du paquet).
    if ((_all getOrDefault [_code, createHashMap]) getOrDefault ["active", true]) exitWith { _code };
    private _codes = ["ordered"] call comspec_sse_fnc_idCountries;
    _codes = _codes + ((keys _all) - _codes);
    private _i = _codes findIf { (_all getOrDefault [_x, createHashMap]) getOrDefault ["active", true] };
    [_code, _codes select _i] select (_i >= 0)
};
if (_mode isEqualTo "resolve") exitWith {
    private _v = if (_args isEqualType "") then { trim _args } else { "" };
    private _all = [] call comspec_sse_fnc_idCountries;
    if (_v isEqualTo "" || {(toLower _v) isEqualTo "auto"}) exitWith { ["default"] call comspec_sse_fnc_idCountries };
    private _u = toUpper _v;
    if (_u in _all) exitWith { _u };
    // Alpha-2 (« FR ») ou ancien nom affiché (« Altis », « République d'Altis », « France ») : même pays.
    private _k = (keys _all) findIf {
        private _c = _all get _x;
        _u isEqualTo toUpper (_c getOrDefault ["iso2", "~"])
        || {_u in ([_c getOrDefault ["name", ""], _c getOrDefault ["nameEn", ""], _c getOrDefault ["shortFr", ""], _c getOrDefault ["shortEn", ""]] apply { toUpper _x })}
    };
    if (_k >= 0) exitWith { (keys _all) select _k };
    ["default"] call comspec_sse_fnc_idCountries
};
if (_mode isEqualTo "usable") exitWith {
    _args params [["_code", ""], ["_type", ""]];
    private _c = ([] call comspec_sse_fnc_idCountries) getOrDefault [_code, createHashMap];
    if ((count _c) isEqualTo 0 || {!(_c getOrDefault ["active", true])}) exitWith { false };
    private _t = _c getOrDefault ["docTypes", []];
    // Type d'une extension (press_card…) : délivré par tous les pays.
    _t isEqualTo [] || {_type isEqualTo ""} || {_type in _t}
        || {((["get", _type] call comspec_sse_fnc_idDocTypes) getOrDefault ["extension", ""]) isNotEqualTo ""}
};

// Pays du paquet Athena (serveur) par-dessus le registre : le registre garde ses noms, formats et durées ; le paquet
// décide de l'état actif et des types délivrés. Pays absent du registre : repris des pays stylisés du site, sinon minimal.
private _reg = missionNamespace getVariable ["comspec_sse_idReg", createHashMap];
private _extra = _reg getOrDefault ["countries", createHashMap];
if ((count _extra) isEqualTo 0) exitWith { _base };
private _real = [] call comspec_sse_fnc_idRealCountries;
private _all = +_base;
{
    private _code = _x;
    private _known = _code in _all;
    private _c = _all getOrDefault [_code, +(_real getOrDefault [_code, createHashMapFromArray [["code", _code], ["mrz", _code], ["number_format", "@@#######"], ["cities", ["-"]], ["chip", true], ["nameEn", _code], ["validity", 10], ["authority", ""]]])];
    {
        if !(_known && {_x in ["name", "nameEn", "nameNative", "number_format", "validity", "authority", "mrz", "colors", "zone", "realSim", "theme", "languages", "fictional", "tier"]}) then {
            if (_y isNotEqualTo "") then { _c set [_x, _y]; };
        };
    } forEach _y;
    _all set [_code, _c];
} forEach _extra;
_all
