/*
    Génère une identité PERSON cohérente (déterministe via seed).
    [_seed, _profile, _complexity, _cluster] call comspec_sse_fnc_generatePerson
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

private _firstNames = _pools get "firstNames";
private _lastNames = _pools get "lastNames";
private _aliases = _pools get "aliases";
private _rolesMap = _pools get "roles";
private _nats = _pools get "nationalities";
private _langs = _pools get "languages";
private _prefixes = _pools get "phonePrefixes";

// Nom
private _name = _cluster getOrDefault ["primaryName", ""];
if (_name isEqualTo "") then {
    private _fn = [_seed, "fn", _firstNames] call comspec_sse_fnc_pickFromSeed;
    private _ln = [_seed, "ln", _lastNames] call comspec_sse_fnc_pickFromSeed;
    _name = format ["%1 %2", _fn, _ln];
    _cluster set ["primaryName", _name];
};

private _alias = _cluster getOrDefault ["primaryAlias", ""];
if (_alias isEqualTo "") then {
    _alias = [_seed, "alias", _aliases] call comspec_sse_fnc_pickFromSeed;
    _cluster set ["primaryAlias", _alias];
};

private _roleList = _rolesMap getOrDefault [_profile, ["Individu"]];
private _role = [_seed, "role", _roleList] call comspec_sse_fnc_pickFromSeed;
private _nat = [_seed, "nat", _nats] call comspec_sse_fnc_pickFromSeed;
private _lang = [_seed, "lang", _langs] call comspec_sse_fnc_pickFromSeed;

private _prefix = [_seed, "pfx", _prefixes] call comspec_sse_fnc_pickFromSeed;
private _phone = _cluster getOrDefault ["primaryPhone", ""];
if (_phone isEqualTo "") then {
    _phone = format ["%1 %2 %3",
        _prefix,
        ([_seed, "p1"] call comspec_sse_fnc_hash) mod 1000,
        ([_seed, "p2"] call comspec_sse_fnc_hash) mod 10000
    ];
    _cluster set ["primaryPhone", _phone];
};

// Contacts réseau
private _contacts = _cluster getOrDefault ["networkContacts", []];
if (count _contacts == 0) then {
    private _pool = +_aliases;
    { if !(_x in _pool) then { _pool pushBack _x; }; } forEach ["FARID", "MUSTAFA", "OMAR SALEH", "THE DRIVER", "WAREHOUSE", "RELAY-2", "BROTHER 7"];
    for "_i" from 0 to ((_nContacts min 12) - 1) do {
        private _c = [_seed, format ["c%1", _i], _pool] call comspec_sse_fnc_pickFromSeed;
        if !(_c in _contacts) then { _contacts pushBack _c; };
    };
    if !(_alias in _contacts) then { _contacts pushBack _alias; };
    _cluster set ["networkContacts", _contacts];
};

// Thème + pack
private _theme = _cluster getOrDefault ["theme", ""];
if (_theme isEqualTo "") then {
    _theme = [_seed, "theme", _pools getOrDefault ["themes", ["fuel_delivery"]]] call comspec_sse_fnc_pickFromSeed;
    _cluster set ["theme", _theme];
};
private _pack = [_theme, _seed, _cluster, _pools] call comspec_sse_fnc_getThemePack;
_cluster set ["depotGrid", _pack getOrDefault ["grid", ""]];
_cluster set ["codeword", _pack getOrDefault ["codeword", ""]];
_cluster set ["sharedDocument", _pack getOrDefault ["documentTitle", "Document"]];
_cluster set ["deliveryNote", (_pack getOrDefault ["sms", [""]]) select 0];
_cluster set ["themeLabel", _pack getOrDefault ["label", _theme]];

// SMS depuis pack + bruit
private _smsPool = _pack getOrDefault ["sms", []];
private _sms = [];
for "_i" from 0 to (((count _smsPool) min _nSms) - 1) do {
    private _txt = _smsPool select _i;
    private _from = if (count _contacts > 0) then { _contacts select (_i mod (count _contacts)) } else { "UNKNOWN" };
    _sms pushBack (createHashMapFromArray [["from", _from], ["text", _txt], ["noise", false], ["theme", _theme]]);
};

private _noiseP = missionNamespace getVariable ["comspec_sse_noiseProbability", 0.25];
private _falseP = missionNamespace getVariable ["comspec_sse_falseLeadProbability", 0.05];
if ((([_seed, "noise"] call comspec_sse_fnc_hash) mod 100) / 100 < _noiseP) then {
    private _nTxt = [_seed, "nsms", _pools get "noiseSms"] call comspec_sse_fnc_pickFromSeed;
    _sms pushBack (createHashMapFromArray [["from", "MAMA"], ["text", _nTxt], ["noise", true]]);
};
if ((([_seed, "false"] call comspec_sse_fnc_hash) mod 100) / 100 < _falseP) then {
    private _fTxt = [_seed, "fsms", _pools get "falseLeads"] call comspec_sse_fnc_pickFromSeed;
    _sms pushBack (createHashMapFromArray [["from", "UNKNOWN"], ["text", _fTxt], ["noise", true], ["falseLead", true]]);
};
_cluster set ["sharedSms", _sms];

// Documents
private _docs = [_seed, _nDocs, _cluster, _pools] call comspec_sse_fnc_generateDocument;

// Locations
private _locations = [];
private _packLocs = _pack getOrDefault ["locations", []];
{
    _x params ["_label", "_g"];
    _locations pushBack (createHashMapFromArray [["label", _label], ["grid", _g], ["theme", _theme]]);
} forEach (_packLocs select [0, _nLocs min (count _packLocs)]);

// Intel
private _intel = [];
private _intelPack = _pack getOrDefault ["intel", []];
private _intelSlice = _intelPack select [0, _nIntel min 5];
{
    private _conf = 0.6 + (([_seed, _x] call comspec_sse_fnc_hash) mod 40) / 100;
    _intel pushBack (createHashMapFromArray [["text", _x], ["confidence", _conf]]);
} forEach _intelSlice;

// Biométrie
private _bio = createHashMap;
if (_hasBio) then {
    private _fpId = format ["FP-%1", [_seed, "fp"] call comspec_sse_fnc_hash];
    private _irId = format ["IR-%1", [_seed, "ir"] call comspec_sse_fnc_hash];
    private _dnaId = format ["DNA-%1", [_seed, "dna"] call comspec_sse_fnc_hash];
    private _heightCm = 165 + (([_seed, "h"] call comspec_sse_fnc_hash) mod 30);
    private _builds = ["slim", "medium", "heavy"];
    private _build = _builds select (([_seed, "bd"] call comspec_sse_fnc_hash) mod 3);
    _bio = createHashMapFromArray [
        ["fingerprintId", _fpId],
        ["irisId", _irId],
        ["dnaId", _dnaId],
        ["facePhoto", false],
        ["heightCm", _heightCm],
        ["build", _build]
    ];
};

private _bloodTypes = ["O POS", "O NEG", "A POS", "A NEG", "B POS", "B NEG", "AB POS", "AB NEG"];
private _blood = [_seed, "blood", _bloodTypes] call comspec_sse_fnc_pickFromSeed;
private _idH = [_seed, "idcode"] call comspec_sse_fnc_hash;
private _idCode = format [
    "%1-%2-%3",
    100 + (_idH mod 800),
    10 + ((_idH / 10) mod 80),
    1000 + ((_idH / 100) mod 8000)
];
private _dobApprox = format ["19%1", 70 + (([_seed, "dob"] call comspec_sse_fnc_hash) mod 30)];

private _identity = createHashMapFromArray [
    ["name", _name],
    ["alias", _alias],
    ["nationality", _nat],
    ["role", _role],
    ["profile", _profile],
    ["phone", _phone],
    ["language", _lang],
    ["region", _region],
    ["bloodType", _blood],
    ["idCode", _idCode],
    ["dobApprox", _dobApprox]
];

createHashMapFromArray [
    ["identity", _identity],
    ["biometrics", _bio],
    ["documents", _docs],
    ["locations", _locations],
    ["intel", _intel],
    ["contacts", _contacts],
    ["sms", _sms],
    ["phone", _phone],
    ["themePack", _pack],
    ["cluster", _cluster]
]
