/*
    Génère un profil PHONE cohérent (lié au cluster).
    [_seed, _profile, _complexity, _cluster] call comspec_sse_fnc_generatePhone
*/
params [
    ["_seed", 0, [0]],
    ["_profile", "INSURGENT", [""]],
    ["_complexity", "STANDARD", [""]],
    ["_cluster", createHashMap, [createHashMap]]
];

_profile = [_profile] call comspec_sse_fnc_resolveProfile;
private _counts = [_complexity] call comspec_sse_fnc_resolveComplexity;
_counts params ["_nContacts", "_nSms", "_nDocs", "_hasBio", "_nIntel", "_nLocs"];

private _region = _cluster getOrDefault ["region", "IRAQ"];
private _pools = [_region] call comspec_sse_fnc_getNarrativePools;

if ((_cluster getOrDefault ["primaryName", ""]) isEqualTo "") then {
    private _person = [_seed, _profile, _complexity, _cluster] call comspec_sse_fnc_generatePerson;
    _cluster = _person getOrDefault ["cluster", _cluster];
};

private _theme = _cluster getOrDefault ["theme", "fuel_delivery"];
private _pack = [_theme, _seed, _cluster, _pools] call comspec_sse_fnc_getThemePack;

private _owner = _cluster getOrDefault ["primaryName", "UNKNOWN"];
private _phone = _cluster getOrDefault ["primaryPhone", format ["+964 750 %1", [_seed, "ph"] call comspec_sse_fnc_hash]];
private _contacts = _cluster getOrDefault ["networkContacts", ["ABU YASSIN", "FARID", "MUSTAFA"]];
private _sms = _cluster getOrDefault ["sharedSms", []];
if (count _sms == 0) then {
    private _pool = _pack getOrDefault ["sms", ["Confirmer demain."]];
    {
        _sms pushBack (createHashMapFromArray [
            ["from", _contacts select (_forEachIndex mod (count _contacts max 1))],
            ["text", _x],
            ["noise", false]
        ]);
    } forEach (_pool select [0, _nSms min (count _pool)]);
};

private _imei = format ["35%1%2", [_seed, "imei1"] call comspec_sse_fnc_hash, [_seed, "imei2"] call comspec_sse_fnc_hash];
private _sim = format ["89964%1", [_seed, "sim"] call comspec_sse_fnc_hash];
private _model = [_seed, "model", _pools get "phoneModels"] call comspec_sse_fnc_pickFromSeed;

private _locs = [];
{
    _x params ["_label", "_g"];
    _locs pushBack (createHashMapFromArray [["label", _label], ["grid", _g]]);
} forEach ((_pack getOrDefault ["locations", []]) select [0, _nLocs min 5]);

private _photos = [];
private _captions = ["Véhicule utilitaire", "Entrée bâtiment", "Groupe d'hommes", "Document photographié", "Point de vue rue", "Plaque véhicule", format ["Code %1 scribbled", _pack getOrDefault ["codeword", ""]]];
for "_i" from 0 to (((round (_nContacts / 2)) max 2) - 1) do {
    _photos pushBack (createHashMapFromArray [
        ["id", format ["PIC-%1-%2", _seed, _i]],
        ["caption", _captions select (_i mod (count _captions))]
    ]);
};

private _calls = [];
for "_i" from 0 to (((_nContacts / 2) max 3) - 1) do {
    private _c = _contacts select (_i mod (count _contacts));
    _calls pushBack (createHashMapFromArray [
        ["with", _c],
        ["duration", 20 + (([_seed, format ["call%1", _i]] call comspec_sse_fnc_hash) mod 400)],
        ["dir", if (_i mod 2 == 0) then {"IN"} else {"OUT"}],
        ["missed", (_i mod 5) == 0]
    ]);
};

private _notes = [
    _pack getOrDefault ["summary", ""],
    format ["Codeword: %1", _pack getOrDefault ["codeword", ""]],
    format ["Vehicle: %1 / %2", _pack getOrDefault ["vehicle", ""], _pack getOrDefault ["plate", ""]]
];

private _deleted = [];
if ((([_seed, "del"] call comspec_sse_fnc_hash) mod 100) < 40) then {
    _deleted pushBack (createHashMapFromArray [
        ["type", "sms"],
        ["text", "Message supprimé — référence dépôt"],
        ["recoverable", (([_seed, "rec"] call comspec_sse_fnc_hash) mod 100) > 40]
    ]);
};

createHashMapFromArray [
    ["uid", format ["SSE-DIG-%1", [_seed, "dig"] call comspec_sse_fnc_hash]],
    ["deviceType", "SMARTPHONE"],
    ["model", _model],
    ["owner", _owner],
    ["phoneNumber", _phone],
    ["imei", _imei],
    ["sim", _sim],
    ["contacts", _contacts],
    ["sms", _sms],
    ["calls", _calls],
    ["photos", _photos],
    ["locations", _locs],
    ["applications", _pools getOrDefault ["applications", []]],
    ["notes", _notes],
    ["deletedData", _deleted],
    ["theme", _theme],
    ["codeword", _pack getOrDefault ["codeword", ""]],
    ["cluster", _cluster]
]
