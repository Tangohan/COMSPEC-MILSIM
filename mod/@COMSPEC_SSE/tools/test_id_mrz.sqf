/*
    Test MRZ (ICAO 9303) : console de débogage, en local ou sur le serveur.
      [] execVM "\z\comspec_sse\addons\identity\..." n'existe pas : copier ce fichier dans la console et l'exécuter.
    Spécimen ICAO (passeport UTO, ERIKSSON ANNA MARIA) : L898902C3 -> 6, 740812 -> 2, 120415 -> 9, ZE184226B -> 1,
    composite -> 0. Hors jeu, même algorithme : python tools/check_id_mrz.py.
*/
private _fails = [];
private _ck = { [_this] call comspec_sse_fnc_idMrzCheck };
{
    _x params ["_in", "_want"];
    if ((_in call _ck) isNotEqualTo _want) then { _fails pushBack format ["%1 -> %2 (attendu %3)", _in, _in call _ck, _want]; };
} forEach [["L898902C3", 6], ["740812", 2], ["120415", 9], ["ZE184226B<<<<<", 1], ["L898902C3674081221204159ZE184226B<<<<<1", 0]];

private _spec = createHashMapFromArray [
    ["type", "passport"], ["country", "UTO"], ["nat", "UTO"], ["number", "L898902C3"], ["last", "Eriksson"],
    ["first", "Anna Maria"], ["sex", "F"], ["dob", "1974-08-12"], ["expires", "2012-04-15"], ["personal", "ZE184226B"]
];
private _l = [_spec] call comspec_sse_fnc_idMrz;
if ((_l select 0) isNotEqualTo "P<UTOERIKSSON<<ANNA<MARIA<<<<<<<<<<<<<<<<<<<") then { _fails pushBack ("TD3 ligne 1 : " + (_l select 0)); };
if ((_l select 1) isNotEqualTo "L898902C36UTO7408122F1204159ZE184226B<<<<<10") then { _fails pushBack ("TD3 ligne 2 : " + (_l select 1)); };
private _p = ["parse", _l] call comspec_sse_fnc_idMrz;
if !(_p get "ok") then { _fails pushBack format ["TD3 relu en erreur : %1", _p get "errors"]; };

// Carte TD1 générée puis relue ; puis faux (chiffre de contrôle décalé) : l'erreur doit ressortir.
private _card = createHashMapFromArray [
    ["type", "id_card"], ["country", "ALT"], ["nat", "ALT"], ["number", "AL1234567"], ["last", "Kostas-Andréou"],
    ["first", "Éléni"], ["sex", "F"], ["dob", "1990-01-31"], ["expires", "2031-06-30"]
];
_l = [_card] call comspec_sse_fnc_idMrz;
_p = ["parse", _l] call comspec_sse_fnc_idMrz;
if !(_p get "ok") then { _fails pushBack format ["TD1 relu en erreur : %1 / %2", _p get "errors", _l]; };
if ((_p get "last") isNotEqualTo "KOSTAS ANDREOU" || {(_p get "first") isNotEqualTo "ELENI"}) then { _fails pushBack format ["TD1 nom relu : %1 / %2", _p get "last", _p get "first"]; };
_card set ["mrzFlaw", "check"];
_p = ["parse", [_card] call comspec_sse_fnc_idMrz] call comspec_sse_fnc_idMrz;
if !("number" in (_p get "errors")) then { _fails pushBack "Faux (chiffre décalé) non détecté"; };

// Pays réel stylisé (univers réaliste-simulation) : Allemagne, code OACI « D<< » pour l'émetteur et la nationalité.
private _deMrz = (([] call comspec_sse_fnc_idRealCountries) getOrDefault ["DEU", createHashMap]) getOrDefault ["mrz", ""];
if (_deMrz isNotEqualTo "D") then { _fails pushBack format ["Code MRZ allemand : %1", _deMrz]; };
private _de = createHashMapFromArray [
    ["type", "passport"], ["country", "DEU"], ["mrzIssuer", _deMrz], ["nat", _deMrz], ["number", "DEK482913"], ["last", "Müller"],
    ["first", "Jürgen"], ["sex", "M"], ["dob", "1985-03-02"], ["expires", "2034-03-01"]
];
_l = [_de] call comspec_sse_fnc_idMrz;
if !(((_l select 0) select [0, 19]) isEqualTo "P<D<<MULLER<<JURGEN" && {((_l select 1) select [10, 3]) isEqualTo "D<<"}) then { _fails pushBack format ["Passeport allemand : %1", _l]; };
_p = ["parse", _l] call comspec_sse_fnc_idMrz;
if !(_p get "ok") then { _fails pushBack format ["Passeport allemand relu en erreur : %1", _p get "errors"]; };

private _msg = if (_fails isEqualTo []) then { "MRZ : tous les tests passent." } else { "MRZ : ÉCHEC" + endl + (_fails joinString endl) };
diag_log text _msg;
hint _msg;
_fails isEqualTo []
