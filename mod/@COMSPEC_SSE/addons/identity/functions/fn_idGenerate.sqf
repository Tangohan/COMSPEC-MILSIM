/*
    Identité imprimée d'une personne, reproductible : même tirage que le site (SseIdentityGeneratorService::generate,
    bouton « Générer » du registre Athena) à partir d'une graine 0..16777215.
    [_unit, _key, _salt, _country] call comspec_sse_fnc_idGenerate -> HashMap
      _key     clé biométrique (graine = fn_bioHash de la clé) ; "" : clé de l'unité
      _salt    "" : sa propre identité (nom déclaré SSE s'il existe : COMSPEC_SSE_FirstName / LastName, fiche
               « identity ») ; autre valeur ("alt", "orig"…) : une autre identité (graine de « clé|sel »)
      _country pays des papiers ("" : pays de la mission)
    Tirages (comspec_sse_fnc_hash / pickFromSeed, sels du site) : fn, ln, pob, age (18 + h mod 48), mon, day, job.
    Sexe : celui du modèle (le site le tire avec « sex » quand on ne l'impose pas). Année : celle de la mission.
    Noms : réservoirs du pays fictif ; personne d'une région SSE (fiche « identity ») : réservoirs de cette région.
    Clés : last, first, sex (M | F), dob (AAAA-MM-JJ), pob, nat (code pays), profession, age, seed.
*/
params [["_unit", objNull, [objNull]], ["_key", "", [""]], ["_salt", "", [""]], ["_country", "", [""]]];
if (_key isEqualTo "" && {!isNull _unit}) then { _key = [_unit] call comspec_sse_fnc_bioPersonKey; };
if (_country isEqualTo "") then { _country = ["default"] call comspec_sse_fnc_idCountries; };
private _c = ([] call comspec_sse_fnc_idCountries) getOrDefault [_country, createHashMap];
private _seed = [[_key, _key + "|" + _salt] select (_salt isNotEqualTo "")] call comspec_sse_fnc_bioHash;
private _h = { [_seed, _this] call comspec_sse_fnc_hash };
private _pick = { params ["_list", "_s"]; if (_list isEqualTo []) exitWith { "" }; _list select ((_s call _h) mod (count _list)) };

private _sex = "M";
if (!isNull _unit && {((getArray (configOf _unit >> "identityTypes")) findIf { "woman" in toLower _x }) >= 0}) then { _sex = "F"; };

private _region = "";
private _sec = if (isNull _unit) then { nil } else { [_unit, "identity"] call comspec_sse_fnc_getSection };
if (!isNil "_sec" && {_sec isEqualType createHashMap}) then { _region = toUpper (_sec getOrDefault ["region", ""]); };
private _first = _c getOrDefault [["first", "firstF"] select (_sex isEqualTo "F"), ["Alex"]];
private _last = _c getOrDefault ["last", ["Doe"]];
private _places = _c getOrDefault ["cities", ["-"]];
if (_region isNotEqualTo "" && {_region isNotEqualTo "RANDOM"} && {!isNil "comspec_sse_fnc_getNarrativePools"}) then {
    private _p = [_region] call comspec_sse_fnc_getNarrativePools;
    if (_p isEqualType createHashMap) then {
        _first = _p getOrDefault ["firstNames", _first];
        _last = _p getOrDefault ["lastNames", _last];
    };
};
private _fn = [_first, "fn"] call _pick;
private _ln = [_last, "ln"] call _pick;
// Nom au féminin : forme donnée par le registre (polonais Kowalski -> Kowalska, tchèque -ová…), sinon terminaison
// (russe : Ivanov -> Ivanova), comme le site.
if (_sex isEqualTo "F" && {_region isEqualTo "" || {_region isEqualTo "RANDOM"}}) then {
    private _lf = _c getOrDefault ["lastF", []];
    private _li = _last find _ln;
    if (_li >= 0 && {_li < count _lf}) then { _ln = _lf select _li; } else { _ln = _ln + (_c getOrDefault ["lastFSuffix", ""]); };
};
if (_salt isEqualTo "" && {!isNull _unit}) then {
    // Nom déclaré par la mission (Eden, Zeus, générateur SSE) : c'est lui qui est imprimé.
    private _df = _unit getVariable ["COMSPEC_SSE_FirstName", ""];
    private _dl = _unit getVariable ["COMSPEC_SSE_LastName", ""];
    if (!isNil "_sec" && {_sec isEqualType createHashMap}) then {
        if (_df isEqualTo "") then { _df = _sec getOrDefault ["first_name", ""]; };
        if (_dl isEqualTo "") then { _dl = _sec getOrDefault ["last_name", ""]; };
    };
    if (_df isEqualType "" && {(trim _df) isNotEqualTo ""}) then { _fn = trim _df; };
    if (_dl isEqualType "" && {(trim _dl) isNotEqualTo ""}) then { _ln = trim _dl; };
};
private _age = 18 + (("age" call _h) mod 48);
private _m = 1 + (("mon" call _h) mod 12);
private _d = 1 + (("day" call _h) mod 28);
private _two = { if (_this < 10) then { "0" + str _this } else { str _this } };
private _profs = ["Agriculteur", "Commerçant", "Chauffeur", "Mécanicien", "Pêcheur", "Enseignant", "Étudiant", "Ouvrier du bâtiment",
    "Infirmier", "Fonctionnaire", "Électricien", "Sans emploi", "Boulanger", "Artisan", "Gardien"];

createHashMapFromArray [
    ["last", _ln], ["first", _fn], ["sex", _sex],
    ["dob", format ["%1-%2-%3", (date select 0) - _age, _m call _two, _d call _two]],
    ["pob", [_places, "pob"] call _pick], ["nat", _country], ["profession", [_profs, "job"] call _pick],
    ["age", _age], ["seed", _seed]
]
