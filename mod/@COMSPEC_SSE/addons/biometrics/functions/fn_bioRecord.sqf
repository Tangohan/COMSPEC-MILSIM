/*
    Dossier d'une personne dans la base biométrique (ABIS), tel que le serveur le connaît.

    Un dossier n'existe que si la personne a réellement été enrôlée quelque part :
      - choix du chef de mission (COMSPEC_SSE_BioDb : absent | enrole | bewl1..bewl4,
        ou ancien verdict imposé COMSPEC_SSE_MatchResult) ;
      - sinon population : part enrôlée et part en watchlist selon le camp (réglages) ;
      - avis de recherche Athena importés (nom ou alias réels) ;
      - rencontres de la mission (sujets transmis par un SEEK), latentes exclues.
    Le dossier ne porte que les modalités réellement enrôlées : une carte d'identité
    n'a que les deux index et une photo.

    Params: [_unit, _key]
    Returns: HashMap (vide si inconnu de toute base)
*/
params [["_unit", objNull, [objNull]], ["_key", "", [""]]];

if (_key isEqualTo "") then { _key = [_unit] call comspec_sse_fnc_bioPersonKey; };
if (_key isEqualTo "") exitWith { createHashMap };

private _profile = [_unit, _key] call comspec_sse_fnc_bioProfile;
private _r = { params ["_salt"]; [_key, "rec|" + _salt] call comspec_sse_fnc_bioRand };
private _str = {
    params ["_var"];
    if (isNull _unit) exitWith { "" };
    private _v = _unit getVariable [_var, ""];
    if (_v isEqualType "") then { toLower (trim _v) } else { "" }
};
private _norm = {
    params ["_s"];
    private _keep = toArray "abcdefghijklmnopqrstuvwxyz0123456789 ";
    private _out = (toArray (toLower _s)) select { _x in _keep };
    ((toString _out) splitString " ") joinString " "
};

private _sideId = _profile getOrDefault ["sideId", 3];
private _sideTag = ["east", "west", "indep", "civ"] select _sideId;
private _year = (date select 0);

// --- Décision d'enrôlement ------------------------------------------------------
private _db = ["COMSPEC_SSE_BioDb"] call _str;
private _legacy = ["COMSPEC_SSE_MatchResult"] call _str;
if (_db in ["", "auto"]) then {
    _db = switch (_legacy) do {
        case "confirmed": { ["bewl1", "bewl2"] select ((["legacyTier"] call _r) < 0.5) };
        case "possible": { "partiel" };
        case "none": { "absent" };
        default { "auto" };
    };
};

private _tier = 0;
private _enrolled = false;
private _kind = "";
switch (true) do {
    case (_db isEqualTo "absent"): {};
    case (_db isEqualTo "enrole"): { _enrolled = true; };
    case (_db isEqualTo "partiel"): { _enrolled = true; _tier = 3; _kind = "partiel"; };
    case ((_db select [0, 4]) isEqualTo "bewl"): {
        _enrolled = true;
        _tier = ((parseNumber (_db select [4])) max 1) min 4;
    };
    default {
        private _pEnr = missionNamespace getVariable [format ["comspec_sse_bio_enrolled_%1", _sideTag], 35];
        private _pWatch = missionNamespace getVariable [format ["comspec_sse_bio_watch_%1", _sideTag], 3];
        private _isHvt = !isNull _unit && {(toUpper (_unit getVariable ["comspec_sse_profile", ""])) isEqualTo "HVT"};
        if (_isHvt || {(["watch"] call _r) * 100 < _pWatch}) then {
            _enrolled = true;
            private _t = ["tier"] call _r;
            _tier = switch (true) do {
                case (_isHvt): { 1 };
                case (_t < 0.15): { 1 };
                case (_t < 0.40): { 2 };
                case (_t < 0.75): { 3 };
                default { 4 };
            };
        } else {
            _enrolled = (["enr"] call _r) * 100 < (_pEnr max _pWatch);
        };
    };
};

private _rec = createHashMap;

if (_enrolled) then {
    // Origine et modalités enrôlées.
    if (_kind isEqualTo "") then {
        _kind = if (_tier > 0) then { "detention" } else {
            if (_sideId == 3) then {
                ["carte", "recensement", "employe"] select (floor ((["src"] call _r) * 3))
            } else {
                ["detention", "controle", "recensement"] select (floor ((["src"] call _r) * 3))
            }
        };
    };
    private _all = [0, 1, 2, 3, 4, 5, 6, 7, 8, 9];
    private _q = { params ["_lo", "_hi", "_s"]; round (_lo + ((_hi - _lo) * ([_s] call _r))) };
    private _spec = switch (_kind) do {
        case "carte":       { ["Carte d'identité biométrique", [1, 6], [70, 86, "qf"] call _q, false, true, false] };
        case "recensement": { ["Recensement biométrique de district", _all, [62, 88, "qf"] call _q, (["ri"] call _r) < 0.5, true, false] };
        case "employe":     { ["Employé local enrôlé (accès emprise)", _all, [78, 93, "qf"] call _q, true, true, false] };
        case "controle":    { ["Contrôle de point de passage", _all, [60, 85, "qf"] call _q, (["ri"] call _r) < 0.35, true, false] };
        case "partiel":     { ["Dossier partiel (latente, photo ancienne)", [1, [0, 2, 3, 6, 7] select (floor ((["pf"] call _r) * 5))], [42, 56, "qf"] call _q, false, true, false] };
        default             { ["Détention antérieure", _all, [74, 93, "qf"] call _q, (["ri"] call _r) < 0.85, true, _tier in [1, 2] && {(["rd"] call _r) < 0.5}] };
    };
    _spec params ["_source", "_fps", "_fpQ", "_iris", "_face", "_dna"];
    private _ago = 1 + floor ((["ago"] call _r) * 8);
    _rec = createHashMapFromArray [
        ["source", _source],
        ["enrolledAt", format ["%1 (%2)", _year - _ago, _source]],
        ["fpFingers", _fps],
        ["fpQuality", _fpQ],
        ["iris", _iris],
        ["irisQuality", [65, 92, "qi"] call _q],
        ["face", _face],
        ["faceQuality", [[55, 85, "qv"] call _q, 48] select (_kind isEqualTo "partiel")],
        ["dna", _dna]
    ];
};

// --- Watchlist : conduite à tenir et motif ---------------------------------------
private _reason = "";
if (!isNull _unit) then {
    private _rv = _unit getVariable ["COMSPEC_SSE_BioReason", ""];
    if (_rv isEqualType "") then { _reason = trim _rv; };
};
if (_tier > 0 && {_reason isEqualTo ""}) then {
    private _pool = switch (_tier) do {
        case 1: { ["Mandat d'arrêt : financement d'un réseau IED", "Identifié sur une vidéo de revendication", "Chef de cellule, attentat contre un convoi", "Empreinte relevée sur un IED désamorcé"] };
        case 2: { ["Lié à une cache d'armes saisie", "Contact téléphonique d'un chef de cellule", "Évadé d'un centre de détention", "Guetteur identifié lors d'une embuscade"] };
        case 3: { ["Présent lors d'un incident à un point de contrôle", "Refus de contrôle répété", "Signalé pour trafic de carburant", "Fréquente une mosquée sous surveillance"] };
        default { ["Ancien employé d'emprise, badge révoqué", "Signalé par un informateur, non recoupé", "Proche d'une personne recherchée", "Vu à proximité d'un site sensible"] };
    };
    _reason = _pool select (floor ((["why"] call _r) * (count _pool)));
};

// --- Avis de recherche Athena -------------------------------------------------------
private _wanted = missionNamespace getVariable ["comspec_sse_bioWanted", []];
private _athena = [];
if ((missionNamespace getVariable ["comspec_sse_bio_athena", true]) && {_wanted isEqualType []}) then {
    private _names = [[_profile getOrDefault ["trueName", ""]] call _norm, [_profile getOrDefault ["alias", ""]] call _norm] select { _x isNotEqualTo "" };
    {
        _x params [["_wk", ""], ["_wid", ""], ["_wref", ""], ["_wname", ""], ["_walias", ""], ["_wlevel", ""], ["_wdet", ""]];
        if (([_wname] call _norm) in _names || {(([_walias] call _norm) isNotEqualTo "") && {([_walias] call _norm) in _names}}) exitWith { _athena = _x; };
    } forEach _wanted;
};
if (_athena isNotEqualTo []) then {
    _athena params [["_wk", ""], ["_wid", ""], ["_wref", ""], ["_wname", ""], ["_walias", ""], ["_wlevel", ""], ["_wdet", ""]];
    private _lv = toLower _wlevel;
    private _wTier = switch (true) do {
        case ((_lv find "prior") >= 0 || {(_lv find "crit") >= 0} || {(_lv find "high") >= 0} || {(_lv find "haut") >= 0}): { 1 };
        case ((_lv find "elev") >= 0 || {(_lv find "élev") >= 0} || {(_lv find "moy") >= 0} || {(_lv find "medium") >= 0}): { 2 };
        default { 3 };
    };
    if ((count _rec) == 0) then {
        // Un avis ne contient qu'un nom et une photo : pas d'empreinte à comparer.
        _rec = createHashMapFromArray [
            ["source", "Avis de recherche Athena"],
            ["enrolledAt", "Avis de recherche Athena"],
            ["fpFingers", []], ["fpQuality", 0], ["iris", false], ["irisQuality", 0],
            ["face", true], ["faceQuality", 55], ["dna", false]
        ];
    };
    _tier = if (_tier == 0) then { _wTier } else { _tier min _wTier };
    _rec set ["athenaRef", [_wref, format ["%1-%2", _wk, _wid]] select (_wref isEqualTo "")];
    if (_wdet isNotEqualTo "") then { _reason = _wdet; };
};

// --- Rencontres de la mission -------------------------------------------------------
private _runtime = missionNamespace getVariable ["comspec_sse_bioRuntime", createHashMap];
private _seen = if (_runtime isEqualType createHashMap) then { _runtime getOrDefault [_key, createHashMap] } else { createHashMap };
if ((count _seen) > 0) then {
    if ((count _rec) == 0) then {
        _rec = createHashMapFromArray [
            ["source", "Rencontre terrain (SEEK)"], ["enrolledAt", ""],
            ["fpFingers", []], ["fpQuality", 0], ["iris", false], ["irisQuality", 0],
            ["face", false], ["faceQuality", 0], ["dna", false]
        ];
    };
    private _fps = +(_rec get "fpFingers");
    { _fps pushBackUnique _x; } forEach (_seen getOrDefault ["fpFingers", []]);
    _rec set ["fpFingers", _fps];
    _rec set ["fpQuality", (_rec get "fpQuality") max (_seen getOrDefault ["fpQuality", 0])];
    if (_seen getOrDefault ["iris", false]) then {
        _rec set ["iris", true];
        _rec set ["irisQuality", (_rec get "irisQuality") max (_seen getOrDefault ["irisQuality", 0])];
    };
    if (_seen getOrDefault ["face", false]) then {
        _rec set ["face", true];
        _rec set ["faceQuality", (_rec get "faceQuality") max (_seen getOrDefault ["faceQuality", 0])];
    };
    if (_seen getOrDefault ["dna", false]) then { _rec set ["dna", true]; };
    _rec set ["encounters", _seen getOrDefault ["encounters", []]];
    if ((_rec get "enrolledAt") isEqualTo "") then {
        private _first = (_seen getOrDefault ["encounters", []]) param [0, []];
        _rec set ["enrolledAt", format ["Rencontre du %1", _first param [0, "?"]]];
    };
};

if ((count _rec) == 0) exitWith { createHashMap };

private _ref = "";
if (!isNull _unit) then {
    private _rv = _unit getVariable ["COMSPEC_SSE_RecordRef", ""];
    if (_rv isEqualType "") then { _ref = trim _rv; };
};
if (_ref isEqualTo "") then {
    private _n = ([_key + "|recid"] call comspec_sse_fnc_bioHash) mod 1000000;
    _ref = format ["BIO-%1", ((1000000 + _n) toFixed 0) select [1, 6]];
};

private _localTiers = missionNamespace getVariable ["comspec_sse_bio_localTiers", 2];
_rec set ["id", _ref];
_rec set ["key", _key];
_rec set ["name", _profile getOrDefault ["trueName", ""]];
_rec set ["alias", _profile getOrDefault ["alias", ""]];
_rec set ["nationality", _profile getOrDefault ["nationality", ""]];
_rec set ["tier", _tier];
_rec set ["reason", _reason];
_rec set ["encounters", _rec getOrDefault ["encounters", []]];
_rec set ["inLocal", (_tier > 0 && {_tier <= _localTiers}) || {(count (_rec get "encounters")) > 0}];
_rec
