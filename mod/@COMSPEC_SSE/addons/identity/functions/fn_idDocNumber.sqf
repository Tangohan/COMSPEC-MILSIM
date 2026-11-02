/*
    Numéro de document, reproductible : même algorithme que le site (SseIdentityGeneratorService::documentNumber).
    [_country, _type, _seed, _n] call comspec_sse_fnc_idDocNumber -> String
      _seed   graine 0..16777215 (texte : ramené à une graine par fn_bioHash)
      _n      rang du tirage (le site essaie 0, 1, 2… tant que le numéro existe déjà)
    Format du pays (number_format) : « # » = chiffre (comspec_sse_fnc_hash [graine, "n" + type + rang + position]
    modulo 10), « @ » = lettre de ABCDEFGHJKLMNPRSTUVWXYZ (sel « l… »), le reste recopié en capitales.
    Formats : ALT « AL@###### », TNA « T######## », LVN « LV####### », MLD « M@@###### ».
*/
params [["_country", "ALT", [""]], ["_type", "id_card", [""]], ["_seed", 0, [0, ""]], ["_n", 0, [0]]];
if (_seed isEqualType "") then { _seed = [_seed] call comspec_sse_fnc_bioHash; };
private _c = ([] call comspec_sse_fnc_idCountries) getOrDefault [_country, createHashMap];
private _fmt = _c getOrDefault ["number_format", ""];
if (_fmt isEqualType createHashMap) then { _fmt = _fmt getOrDefault [_type, ""]; };
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
