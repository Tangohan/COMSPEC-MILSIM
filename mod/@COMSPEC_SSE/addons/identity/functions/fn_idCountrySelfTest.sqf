/*
    Essais du registre des pays et des numéros (pur, toute machine, Eden compris : console de débogage).
    [] call comspec_sse_fnc_idCountrySelfTest -> [échecs [textes…], infos [textes…]]
      registre    chaque pays : code, noms FR et EN, code MRZ, villes, réservoirs de noms ; docTypes connus de
                  CfgComspecDocuments et pièce courante (primary) parmi eux ; alpha-2 unique ; countries[] des types de
                  documents tous au registre ; cartes (maps) jamais prises par deux pays
      résolution  « FR » -> FRA, « Altis » -> ALT, « fra » -> FRA ; anciennes valeurs ALT, TNA, LVN, MLD gardées
      OACI        spécimens publiés de la norme 9303 (passeport TD3, carte TD1 « UTO ») relus sans erreur
      clés        exemples publics (DNI 12345678Z, NIE X1234567L, PESEL 44051401359, BSN 111222333, AVS 756.9217.0769.85,
                  SVNR 1237 010180, codice fiscale RSSMRA85T10A562S) ; 20 tirages par pays et par numéro à clé
      MRZ pays    Allemagne « IDD<< » ; carte belge (numéro de 12 chiffres) en débordement OACI relue avec le même numéro
    Même jeu d'essais hors jeu : tools/countries/test_countries.py (Python), tests/Unit/SseCountryRegistryTest.php.
*/
private _ko = [];
private _info = [];
private _all = [] call comspec_sse_fnc_idCountries;
private _types = keys ([] call comspec_sse_fnc_idDocTypes);
private _iso2 = createHashMap;
private _maps = createHashMap;
{
    private _code = _x;
    private _c = _y;
    { if ((_c getOrDefault [_x, ""]) isEqualTo "") then { _ko pushBack format ["%1 : clé %2 vide", _code, _x]; }; } forEach ["name", "nameEn", "mrz"];
    if ((_c getOrDefault ["cities", []]) isEqualTo [] || {(_c getOrDefault ["first", []]) isEqualTo []} || {(_c getOrDefault ["last", []]) isEqualTo []}) then { _ko pushBack format ["%1 : villes ou noms manquants", _code]; };
    private _dt = _c getOrDefault ["docTypes", []];
    { if !(_x in _types) then { _ko pushBack format ["%1 : type %2 absent de CfgComspecDocuments", _code, _x]; }; } forEach _dt;
    if (_dt isNotEqualTo [] && {!((_c getOrDefault ["primary", ""]) in _dt)}) then { _ko pushBack format ["%1 : pièce courante hors docTypes", _code]; };
    if !(_c getOrDefault ["fictional", true]) then {
        private _i2 = _c getOrDefault ["iso2", ""];
        if ((count _i2) isNotEqualTo 2 || {(count _code) isNotEqualTo 3}) then { _ko pushBack format ["%1 : codes ISO invalides", _code]; };
        if (_i2 in _iso2) then { _ko pushBack format ["%1 : alpha-2 %2 déjà pris par %3", _code, _i2, _iso2 get _i2]; };
        _iso2 set [_i2, _code];
        { if !(_x in (_c getOrDefault ["number_format", createHashMap])) then { _ko pushBack format ["%1 : pas de numéro pour %2", _code, _x]; }; } forEach _dt;
    };
    { if (_x in _maps) then { _ko pushBack format ["carte %1 prise par %2 et %3", _x, _maps get _x, _code]; }; _maps set [_x, _code]; } forEach (_c getOrDefault ["maps", []]);
} forEach _all;
{
    private _t = _y;
    { if !(_x in _all) then { _ko pushBack format ["type %1 : pays %2 absent du registre", _t get "key", _x]; }; } forEach (_t getOrDefault ["countries", []]);
} forEach ([] call comspec_sse_fnc_idDocTypes);
_info pushBack format ["%1 pays (%2 réels)", count _all, { !(_y getOrDefault ["fictional", true]) } count _all];

// Résolution des valeurs gardées par les missions.
{
    _x params ["_in", "_want"];
    private _got = ["resolve", _in] call comspec_sse_fnc_idCountries;
    if (_got isNotEqualTo _want) then { _ko pushBack format ["résolution « %1 » : %2 au lieu de %3", _in, _got, _want]; };
} forEach [["FR", "FRA"], ["fra", "FRA"], ["Altis", "ALT"], ["ALT", "ALT"], ["TNA", "TNA"], ["LVN", "LVN"], ["MLD", "MLD"], ["DE", "DEU"]];

// Spécimens OACI 9303.
{
    private _p = ["parse", _x] call comspec_sse_fnc_idMrz;
    if !(_p getOrDefault ["ok", false]) then { _ko pushBack format ["spécimen OACI refusé : %1 (%2)", _x select 0, _p get "errors"]; };
} forEach [
    ["P<UTOERIKSSON<<ANNA<MARIA<<<<<<<<<<<<<<<<<<<", "L898902C36UTO7408122F1204159ZE184226B<<<<<10"],
    ["I<UTOD231458907<<<<<<<<<<<<<<<", "7408122F1204159UTO<<<<<<<<<<<6", "ERIKSSON<<ANNA<MARIA<<<<<<<<<<"]
];

// Clés : exemples publics, puis tirages.
{
    _x params ["_sch", "_v"];
    if !(["check", [_sch, _v]] call comspec_sse_fnc_idNumbers) then { _ko pushBack format ["clé %1 refusée : %2", _sch, _v]; };
} forEach [["es_dni", "12345678Z"], ["es_nie", "X1234567L"], ["pl_pesel", "44051401359"], ["nl_bsn", "111222333"], ["ch_ahv", "756.9217.0769.85"],
    ["at_svnr", "1237 010180"], ["it_cf", "RSSMRA85T10A562S"], ["pl_idcard", "ABA300000"]];
if (["check", ["es_dni", "12345678A"]] call comspec_sse_fnc_idNumbers) then { _ko pushBack "clé es_dni : un DNI faux est accepté"; };
private _n = 0;
{
    private _code = _x;
    {
        private _spec = _y;
        if ((_spec select [0, 7]) isEqualTo "scheme:") then {
            for "_i" from 1 to 20 do {
                private _ctx = createHashMapFromArray [["sex", ["M", "F"] select (_i mod 2)], ["dob", format ["19%1-0%2-1%3", 60 + _i, 1 + (_i mod 9), _i mod 9]],
                    ["last", "Dupont"], ["first", "Marie"], ["pob", "Roma"], ["region", "75"]];
                private _v = ["make", [_spec, 1000 + _i * 7919, _code, _ctx]] call comspec_sse_fnc_idNumbers;
                _n = _n + 1;
                if !(["check", [_spec select [7], _v]] call comspec_sse_fnc_idNumbers) exitWith { _ko pushBack format ["%1 %2 : %3 refusé par sa clé", _code, _spec, _v]; };
            };
        };
    } forEach ((_all get _code) getOrDefault ["number_format", createHashMap]);
} forEach ((keys _all) select { ((_all get _x) getOrDefault ["number_format", ""]) isEqualType createHashMap });
_info pushBack format ["%1 numéros à clé tirés", _n];

// MRZ des pays : Allemagne « IDD<< », numéro long belge.
private _de = [createHashMapFromArray [["type", "id_card"], ["country", "DEU"], ["mrzIssuer", "D"], ["nat", "D"], ["mrzCode", "ID"], ["number", "L01X00T47"],
    ["last", "Müller"], ["first", "Jürgen"], ["sex", "M"], ["dob", "1964-08-12"], ["expires", "2030-10-31"]]] call comspec_sse_fnc_idMrz;
if (((_de select 0) select [0, 5]) isNotEqualTo "IDD<<") then { _ko pushBack format ["MRZ allemande : %1", _de select 0]; };
if !((["parse", _de] call comspec_sse_fnc_idMrz) getOrDefault ["ok", false]) then { _ko pushBack "MRZ allemande refusée à la relecture"; };
private _be = [createHashMapFromArray [["type", "id_card"], ["country", "BEL"], ["mrzIssuer", "BEL"], ["nat", "BEL"], ["mrzCode", "ID"], ["number", "592-1234567-89"],
    ["last", "Peeters"], ["first", "Lotte"], ["sex", "F"], ["dob", "1990-02-03"], ["expires", "2030-02-03"]]] call comspec_sse_fnc_idMrz;
private _bp = ["parse", _be] call comspec_sse_fnc_idMrz;
if !(_bp getOrDefault ["ok", false]) then { _ko pushBack format ["MRZ belge (numéro long) refusée : %1", _bp get "errors"]; };
if ((_bp getOrDefault ["number", ""]) isNotEqualTo "592123456789") then { _ko pushBack format ["MRZ belge : numéro relu %1", _bp getOrDefault ["number", ""]]; };
[_ko, _info]
