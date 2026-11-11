/*
    Numéro de document, reproductible.
    [_country, _type, _seed, _n, _ctx] call comspec_sse_fnc_idDocNumber -> String
      _seed   graine 0..16777215 (texte : ramené à une graine par fn_bioHash)
      _n      rang du tirage (le site essaie 0, 1, 2… tant que le numéro existe déjà)
      _ctx    HashMap facultatif (sex, dob, last, first, pob, region, area) : numéros à clé des pays réels
    Pays fictif : même algorithme que le site (SseIdentityGeneratorService::documentNumber) : « # » = chiffre
    (comspec_sse_fnc_hash [graine, "n" + type + rang + position] modulo 10), « @ » = lettre de ABCDEFGHJKLMNPRSTUVWXYZ
    (sel « l… »), le reste recopié en capitales. Formats : ALT « AL@###### », TNA « T######## », LVN « LV####### »,
    MLD « M@@###### ».
    Pays réel (registre) : motif ou schéma du type (numbers) ; « state » : format de la région (États-Unis) ; tirage par
    fn_idNumbers (clés justes, numéros fictifs).
*/
params [["_country", "ALT", [""]], ["_type", "id_card", [""]], ["_seed", 0, [0, ""]], ["_n", 0, [0]], ["_ctx", createHashMap, [createHashMap]]];
if (_seed isEqualType "") then { _seed = [_seed] call comspec_sse_fnc_bioHash; };
private _c = ([] call comspec_sse_fnc_idCountries) getOrDefault [_country, createHashMap];
private _fmt = _c getOrDefault ["number_format", ""];
if (_fmt isEqualType createHashMap) exitWith {
    private _spec = _fmt getOrDefault [_type, ""];
    if (_spec isEqualTo "state") then {
        private _r = ["region", [_country, _ctx getOrDefault ["region", ""]]] call comspec_sse_fnc_idCountries;
        if ((count _r) isEqualTo 0) then { _r = (values (_c getOrDefault ["regions", createHashMap])) param [0, createHashMap]; };
        _spec = _r getOrDefault [["dl", "id"] select (_type isEqualTo "id_card"), _r getOrDefault ["dl", "@########"]];
    };
    // Type sans format (extension, carte militaire d'un pays générique) : numéro générique du pays.
    if (_spec isEqualTo "") then { _spec = (_c getOrDefault ["iso2", "XX"]) + "@######"; };
    ["make", [_spec, _seed, format ["n%1%2", _type, _n], _ctx]] call comspec_sse_fnc_idNumbers
};
if (!(_fmt isEqualType "") || {_fmt isEqualTo ""}) then { _fmt = "@@#######"; };
private _salt = _type + str _n;
private _letters = toArray "ABCDEFGHJKLMNPRSTUVWXYZ";
private _out = [];
{
    switch (_x) do {
        case 35: { _out pushBack (48 + (([_seed, format ["n%1%2", _salt, _forEachIndex]] call comspec_sse_fnc_hash) mod 10)); };
        case 64: { _out pushBack (_letters select (([_seed, format ["l%1%2", _salt, _forEachIndex]] call comspec_sse_fnc_hash) mod (count _letters))); };
        default { _out append (toArray (toUpper (toString [_x]))); };
    };
} forEach (toArray _fmt);
toString _out
