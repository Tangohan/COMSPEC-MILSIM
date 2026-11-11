/*
    Numéros des papiers de pays réels (registre CfgComspecCountries), reproductibles depuis une graine.
    Portage hors jeu ligne à ligne : tools/countries/sse_numbers.py (essais : tools/countries/test_countries.py).
    ["make", [motif, graine, sel, contexte]] -> String
        motif     « # » chiffre, « @ » lettre (ABCDEFGHJKLMNPRSTUVWXYZ), « * » A-Z0-9, « & » jeu allemand
                  (CFGHJKLMNPRTVWXYZ0123456789), « {C} » clé OACI 7-3-1 des caractères alphanumériques qui précèdent,
                  « {R} » région, « {A} » indicatif, « {Y} » année de naissance (2 chiffres) ; autre caractère recopié.
                  « scheme:<nom> » : numéro à clé (fr_nir, be_rrn, be_idcard, us_ssn, nl_bsn, ch_ahv, at_svnr, it_cf,
                  it_cie, es_dni, es_nie, pl_pesel, pl_idcard, gb_dln).
        contexte  HashMap : sex (M | F), dob (AAAA-MM-JJ), last, first, pob, region, area
    ["check", [schéma, valeur]] -> Boolean (clé juste ; « us_ssn » : zone 9xx et groupe 01-49, jamais attribués)
    ["vin", [graine, sel, wmi]] -> numéro de châssis de 17 caractères, clé nord-américaine juste (position 9)
    Tout est FICTIF : NIR au rang de naissance 000 (jamais attribué), SSN en 9xx (jamais attribué), etc. SQF calcule
    en simple précision : les grands nombres sont réduits chiffre par chiffre.
*/
params [["_op", "make", [""]], ["_a", []]];
private _letters = "ABCDEFGHJKLMNPRSTUVWXYZ";
private _alnum = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
private _de = "CFGHJKLMNPRTVWXYZ0123456789";
private _esL = "TRWAGMYFPDXBNJZSQVHLCKE";
private _pad0 = { params ["_n", "_w"]; private _s = str (round _n); while { count _s < _w } do { _s = "0" + _s; }; _s };
private _modStr = { params ["_s", "_m"]; private _r = 0; { if (_x >= 48 && {_x <= 57}) then { _r = (_r * 10 + (_x - 48)) mod _m; }; } forEach (toArray _s); _r };
private _digits = { ((toArray _this) select { _x >= 48 && {_x <= 57} }) apply { _x - 48 } };
private _plain = {
    // Capitales sans accents, lettres seulement (même table que la MRZ).
    private _l = (([createHashMapFromArray [["type", "passport"], ["last", _this], ["first", ""], ["mrzIssuer", "XXX"], ["country", ""]]] call comspec_sse_fnc_idMrz) select 0) select [5];
    toString ((toArray _l) select { _x >= 65 && {_x <= 90} })
};
private _ean13 = { private _s = 0; { _s = _s + _x * ([1, 3] select (_forEachIndex mod 2)); } forEach (_this call _digits); (10 - (_s mod 10)) mod 10 };
private _atW = [3, 7, 9, 0, 5, 8, 4, 2, 1, 6];
private _peselW = [1, 3, 7, 9, 1, 3, 7, 9, 1, 3];
private _plW = [7, 3, 1, 9, 7, 3, 1, 7, 3];
private _plv = { if (_this >= 65) then { _this - 55 } else { _this - 48 } };
private _cfOdd = [1, 0, 5, 7, 9, 13, 15, 17, 19, 21, 2, 4, 18, 20, 11, 3, 6, 8, 12, 14, 16, 10, 22, 25, 24, 23];
private _cfCheck = {
    private _t = 0;
    {
        private _d = _x >= 48 && {_x <= 57};
        private _v = if (_d) then { _x - 48 } else { _x - 65 };
        // Rang impair (1er, 3e…) : table impaire, un chiffre vaut la lettre de même rang (0 = A, 1 = B…).
        _t = _t + (if ((_forEachIndex mod 2) isEqualTo 0) then { _cfOdd select _v } else { _v });
    } forEach (toArray _this);
    toString [65 + (_t mod 26)]
};
private _vinTl = createHashMapFromArray [["A", 1], ["B", 2], ["C", 3], ["D", 4], ["E", 5], ["F", 6], ["G", 7], ["H", 8], ["J", 1], ["K", 2], ["L", 3],
    ["M", 4], ["N", 5], ["P", 7], ["R", 9], ["S", 2], ["T", 3], ["U", 4], ["V", 5], ["W", 6], ["X", 7], ["Y", 8], ["Z", 9]];
private _vinCheck = {
    private _w = [8, 7, 6, 5, 4, 3, 2, 10, 0, 9, 8, 7, 6, 5, 4, 3, 2];
    private _s = 0;
    { private _c = toString [_x]; _s = _s + ([_vinTl getOrDefault [_c, 0], _x - 48] select (_x >= 48 && {_x <= 57})) * (_w select _forEachIndex); } forEach (toArray _this);
    _s = _s mod 11;
    ["X", str _s] select (_s < 10)
};

if (_op isEqualTo "check") exitWith {
    _a params [["_sch", ""], ["_v", ""]];
    private _d = _v call _digits;
    private _ds = toString (_d apply { _x + 48 });
    switch (_sch) do {
        case "fr_nir": { (count _d) isEqualTo 15 && {(parseNumber (_ds select [13, 2])) isEqualTo (97 - ([_ds select [0, 13], 97] call _modStr))} };
        case "be_rrn": { (count _d) isEqualTo 11 && {private _k = parseNumber (_ds select [9, 2]); _k isEqualTo (97 - ([_ds select [0, 9], 97] call _modStr)) || {_k isEqualTo (97 - (["2" + (_ds select [0, 9]), 97] call _modStr))}} };
        case "be_idcard": { (count _d) isEqualTo 12 && {private _m = [_ds select [0, 10], 97] call _modStr; (parseNumber (_ds select [10, 2])) isEqualTo ([_m, 97] select (_m isEqualTo 0))} };
        case "us_ssn": { private _p = _v splitString "-"; (count _p) isEqualTo 3 && {((_p select 0) select [0, 1]) isEqualTo "9"} && {private _g = parseNumber (_p select 1); _g >= 1 && {_g <= 49}} };
        case "nl_bsn": { (count _d) isEqualTo 9 && { private _s = 0; for "_i" from 0 to 7 do { _s = _s + (_d select _i) * (9 - _i); }; ((_s - (_d select 8)) mod 11) isEqualTo 0 } };
        case "ch_ahv": { (count _d) isEqualTo 13 && {(_ds select [0, 3]) isEqualTo "756"} && {(_d select 12) isEqualTo ((_ds select [0, 12]) call _ean13)} };
        case "at_svnr": { (count _d) isEqualTo 10 && { private _s = 0; { _s = _s + _x * (_atW select _forEachIndex); } forEach _d; (_s mod 11) isEqualTo (_d select 3) } };
        case "it_cf": { (count _v) isEqualTo 16 && {((_v select [0, 15]) call _cfCheck) isEqualTo (_v select [15, 1])} };
        case "es_dni": { (count _v) isEqualTo 9 && {(_esL select [[_v select [0, 8], 23] call _modStr, 1]) isEqualTo (_v select [8, 1])} };
        case "es_nie": { (count _v) isEqualTo 9 && {private _p = ["X", "Y", "Z"] find (_v select [0, 1]); _p >= 0 && {(_esL select [[str _p + (_v select [1, 7]), 23] call _modStr, 1]) isEqualTo (_v select [8, 1])}} };
        case "pl_pesel": { (count _d) isEqualTo 11 && { private _s = 0; for "_i" from 0 to 9 do { _s = _s + (_d select _i) * (_peselW select _i); }; ((10 - (_s mod 10)) mod 10) isEqualTo (_d select 10) } };
        case "pl_idcard": { (count _v) isEqualTo 9 && { private _s = 0; { _s = _s + (_x call _plv) * (_plW select _forEachIndex); } forEach (toArray (toUpper _v)); (_s mod 10) isEqualTo 0 } };
        default { true };
    }
};

private _seed = 0;
private _salt = "";
private _cnt = 0;
private _rand = { _cnt = _cnt + 1; ([_seed, format ["%1|%2", _salt, _cnt]] call comspec_sse_fnc_hash) mod _this };
private _pick = { _this select [(count _this) call _rand, 1] };

if (_op isEqualTo "vin") exitWith {
    _a params [["_s", 0], ["_sl", "vin"], ["_wmi", "SSE"]];
    _seed = _s; _salt = _sl;
    private _set = "ABCDEFGHJKLMNPRSTUVWXYZ0123456789";
    private _v = (_wmi select [0, 3]);
    while { count _v < 17 } do { _v = _v + (_set call _pick); };
    _v = (_v select [0, 8]) + "0" + (_v select [9]);
    (_v select [0, 8]) + (_v call _vinCheck) + (_v select [9])
};

_a params [["_spec", ""], ["_s", 0], ["_sl", ""], ["_ctx", createHashMap]];
_seed = _s; _salt = _sl;
private _sex = _ctx getOrDefault ["sex", "M"];
private _dob = _ctx getOrDefault ["dob", "1990-01-01"];
if ((count _dob) < 10) then { _dob = "1990-01-01"; };
private _rnd = { params ["_n", "_w"]; [_n call _rand, _w] call _pad0 };
private _many = { params ["_n", "_set"]; private _o = ""; for "_i" from 1 to _n do { _o = _o + (_set call _pick); }; _o };

if ((_spec select [0, 7]) isEqualTo "scheme:") exitWith {
    switch (_spec select [7]) do {
        case "fr_nir": {
            private _dept = _ctx getOrDefault ["region", ""];
            if !((count _dept) isEqualTo 2 && {(count (_dept call _digits)) isEqualTo 2}) then { _dept = "99"; };
            private _body = (["1", "2"] select (_sex isEqualTo "F")) + (_dob select [2, 2]) + (_dob select [5, 2]) + _dept + ([1 + (999 call _rand), 3] call _pad0) + "000";
            format ["%1 %2 %3 %4 %5 %6 %7", _body select [0, 1], _body select [1, 2], _body select [3, 2], _body select [5, 2], _body select [7, 3], _body select [10, 3], [97 - ([_body, 97] call _modStr), 2] call _pad0]
        };
        case "be_rrn": {
            private _ctr = ((2 * (1 + (497 call _rand))) + ([1, 0] select (_sex isEqualTo "F"))) min 997;
            private _body = (_dob select [2, 2]) + (_dob select [5, 2]) + (_dob select [8, 2]) + ([_ctr, 3] call _pad0);
            private _base = [_body, "2" + _body] select ((_dob select [0, 2]) isEqualTo "20");
            format ["%1.%2.%3-%4.%5", _body select [0, 2], _body select [2, 2], _body select [4, 2], _body select [6, 3], [97 - ([_base, 97] call _modStr), 2] call _pad0]
        };
        case "be_idcard": {
            private _b = [10, "0123456789"] call _many;
            private _m = [_b, 97] call _modStr;
            format ["%1-%2-%3", _b select [0, 3], _b select [3], [[_m, 97] select (_m isEqualTo 0), 2] call _pad0]
        };
        case "us_ssn": { format ["9%1-%2-%3", [100, 2] call _rnd, [1 + (49 call _rand), 2] call _pad0, [1 + (9999 call _rand), 4] call _pad0] };
        case "nl_bsn": {
            private _out = "";
            for "_try" from 1 to 40 do {
                private _d = [1 + (9 call _rand)];
                for "_i" from 1 to 7 do { _d pushBack (10 call _rand); };
                private _s = 0;
                { _s = _s + _x * (9 - _forEachIndex); } forEach _d;
                if ((_s mod 11) < 10) exitWith { _out = toString ((_d + [_s mod 11]) apply { _x + 48 }); };
            };
            _out
        };
        case "ch_ahv": {
            private _b = "756" + ([9, "0123456789"] call _many);
            _b = _b + str (_b call _ean13);
            format ["%1.%2.%3.%4", _b select [0, 3], _b select [3, 4], _b select [7, 4], _b select [11, 2]]
        };
        case "at_svnr": {
            private _tail = (_dob select [8, 2]) + (_dob select [5, 2]) + (_dob select [2, 2]);
            private _out = "";
            for "_try" from 1 to 40 do {
                private _n = str (1 + (9 call _rand)) + ([100, 2] call _rnd);
                private _s = 0;
                { _s = _s + _x * (_atW select _forEachIndex); } forEach ((_n + "0" + _tail) call _digits);
                if ((_s mod 11) < 10) exitWith { _out = format ["%1%2 %3", _n, _s mod 11, _tail]; };
            };
            _out
        };
        case "it_cf": {
            private _part = {
                params ["_t", "_isName"];
                private _p = _t call _plain;
                private _cons = (toArray _p) select { !(_x in [65, 69, 73, 79, 85]) };
                private _vow = (toArray _p) select { _x in [65, 69, 73, 79, 85] };
                if (_isName && {(count _cons) >= 4}) then { _cons = [_cons select 0, _cons select 2, _cons select 3]; };
                ((toString (_cons + _vow)) + "XXX") select [0, 3]
            };
            private _bel = createHashMapFromArray [["ROMA", "H501"], ["MILANO", "F205"], ["NAPOLI", "F839"], ["TORINO", "L219"], ["PALERMO", "G273"], ["GENOVA", "D969"],
                ["BOLOGNA", "A944"], ["FIRENZE", "D612"], ["BARI", "A662"], ["CATANIA", "C351"], ["VENEZIA", "L736"], ["VERONA", "L781"]];
            private _town = _bel getOrDefault [(_ctx getOrDefault ["pob", ""]) call _plain, (values _bel) select ((count _bel) call _rand)];
            private _day = (parseNumber (_dob select [8, 2])) + ([0, 40] select (_sex isEqualTo "F"));
            private _s = ([_ctx getOrDefault ["last", "ROSSI"], false] call _part) + ([_ctx getOrDefault ["first", "MARIO"], true] call _part) + (_dob select [2, 2])
                + ("ABCDEHLMPRST" select [(parseNumber (_dob select [5, 2])) - 1, 1]) + ([_day, 2] call _pad0) + _town;
            _s + (_s call _cfCheck)
        };
        case "it_cie": { "C" + (_letters call _pick) + ([5, "0123456789"] call _many) + ([2, _letters] call _many) };
        case "es_dni": { private _n = str (1 + (9 call _rand)) + ([7, "0123456789"] call _many); _n + (_esL select [[_n, 23] call _modStr, 1]) };
        case "es_nie": {
            private _p = 3 call _rand;
            private _n = [7, "0123456789"] call _many;
            (["X", "Y", "Z"] select _p) + _n + (_esL select [[str _p + _n, 23] call _modStr, 1])
        };
        case "pl_pesel": {
            private _m = (parseNumber (_dob select [5, 2])) + ([0, 20] select ((_dob select [0, 2]) isEqualTo "20"));
            private _b = (_dob select [2, 2]) + ([_m, 2] call _pad0) + (_dob select [8, 2]) + ([1000, 3] call _rnd) + str (2 * (5 call _rand) + ([1, 0] select (_sex isEqualTo "F")));
            private _s = 0;
            { _s = _s + _x * (_peselW select _forEachIndex); } forEach (_b call _digits);
            _b + str ((10 - (_s mod 10)) mod 10)
        };
        case "pl_idcard": {
            private _l = [3, _letters] call _many;
            private _d = [5, "0123456789"] call _many;
            private _s = 0;
            { _s = _s + (_x call _plv) * (_plW select _forEachIndex); } forEach (toArray (_l + "0" + _d));
            _l + str (_s mod 10) + _d
        };
        case "gb_dln": {
            private _sur = (_ctx getOrDefault ["last", "SMITH"]) call _plain;
            if ((_sur select [0, 3]) isEqualTo "MAC") then { _sur = "MC" + (_sur select [3]); };
            private _ini = (((_ctx getOrDefault ["first", "J"]) call _plain) select [0, 1]) + "9";
            ((_sur + "99999") select [0, 5]) + (_dob select [2, 1]) + ([(parseNumber (_dob select [5, 2])) + ([0, 50] select (_sex isEqualTo "F")), 2] call _pad0)
                + (_dob select [8, 2]) + (_dob select [3, 1]) + _ini + "9" + ([2, _letters] call _many)
        };
        default { [9, _alnum] call _many };
    }
};

// Motif.
private _out = "";
private _chars = toArray _spec;
private _i = 0;
while { _i < count _chars } do {
    private _c = _chars select _i;
    if (_c isEqualTo 123) then {
        private _j = _i;
        while { _j < count _chars && {(_chars select _j) isNotEqualTo 125} } do { _j = _j + 1; };
        private _tok = toString (_chars select [_i + 1, _j - _i - 1]);
        _out = _out + (switch (_tok) do {
            case "C": { str ([toString ((toArray _out) select { (_x >= 48 && {_x <= 57}) || {_x >= 65 && {_x <= 90}} })] call comspec_sse_fnc_idMrzCheck) };
            case "R": { _ctx getOrDefault ["region", ""] };
            case "A": { _ctx getOrDefault ["area", "555"] };
            case "Y": { _dob select [2, 2] };
            default { "" };
        });
        _i = _j + 1;
    } else {
        _out = _out + (switch (_c) do {
            case 35: { str (10 call _rand) };
            case 64: { _letters call _pick };
            case 42: { _alnum call _pick };
            case 38: { _de call _pick };
            default { toString [_c] };
        });
        _i = _i + 1;
    };
};
_out
