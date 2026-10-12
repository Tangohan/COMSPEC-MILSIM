/*
    Identité biologique d'une personne : ce que ses doigts, ses yeux, son visage et
    son ADN « sont » réellement, indépendamment de ce qu'on relève.

    Entièrement déterministe (graine de campagne + clé biométrique) : le serveur et
    chaque client recalculent le même profil sans rien transmettre. Les choix du
    chef de mission (doigts manquants, iris, faux papiers, parenté) sont lus sur
    l'unité.

    Doigts (ordre FBI) : 0 pouce D … 4 auriculaire D, 5 pouce G … 9 auriculaire G.
      [dessin, gabarit, état]   état : normal | use | cicatrice | ampute
    Yeux : 0 OD, 1 OG — [couleur, gabarit, état]   état : normal | cataracte | lesion | prothese
    ADN : 13 loci CODIS — [locus, allèle 1, allèle 2]

    Params: [_unit, _key, _skipRelative]
      _unit  personne (peut être nulle si seule la clé est connue : latente, relation)
      _key   clé biométrique ; "" = calculée depuis l'unité
    Returns: HashMap
*/
params [
    ["_unit", objNull, [objNull]],
    ["_key", "", [""]],
    ["_skipRelative", false, [false]]
];

if (_key isEqualTo "") then { _key = [_unit] call comspec_sse_fnc_bioPersonKey; };
if (_key isEqualTo "") exitWith { createHashMap };

private _r = { params ["_salt"]; [_key, _salt] call comspec_sse_fnc_bioRand };
private _str = {
    params ["_var"];
    if (isNull _unit) exitWith { "" };
    private _v = _unit getVariable [_var, ""];
    if (_v isEqualType "") then { trim _v } else { "" }
};

// --- Camp (config, valable aussi pour un corps) ---------------------------
private _sideId = 3;
if (!isNull _unit) then {
    _sideId = getNumber ((configOf _unit) >> "side");
    if !(_sideId in [0, 1, 2, 3]) then { _sideId = 3; };
};

// --- Identité déclarée (papiers) -------------------------------------------
private _first = ["COMSPEC_SSE_FirstName"] call _str;
private _last = ["COMSPEC_SSE_LastName"] call _str;
private _alias = ["COMSPEC_SSE_Alias"] call _str;
private _nat = ["COMSPEC_SSE_Nationality"] call _str;
private _region = "IRAQ";
if (!isNull _unit && {!isNil "comspec_sse_fnc_getSection"}) then {
    private _id = [_unit, "identity"] call comspec_sse_fnc_getSection;
    if (!isNil "_id" && {_id isEqualType createHashMap}) then {
        if (_first isEqualTo "") then { _first = trim (_id getOrDefault ["first_name", ""]); };
        if (_last isEqualTo "") then { _last = trim (_id getOrDefault ["last_name", ""]); };
        if (_alias isEqualTo "") then { _alias = trim (_id getOrDefault ["alias", ""]); };
        if (_nat isEqualTo "") then { _nat = trim (_id getOrDefault ["nationality", ""]); };
        _region = _id getOrDefault ["region", _region];
    };
};
private _declared = trim format ["%1 %2", _first, _last];
if (_declared isEqualTo "" && {!isNull _unit}) then {
    private _n = name _unit;
    if ((_n find "Error") < 0) then { _declared = _n; };
};

// --- Identité réelle ----------------------------------------------------------
// Faux papiers : imposés (vraie identité saisie) ou tirés selon le camp.
private _trueName = ["COMSPEC_SSE_BioTrueName"] call _str;
private _falsePapers = _trueName isNotEqualTo "";
if (!_falsePapers && {(["COMSPEC_SSE_BioFalsePapers"] call _str) isNotEqualTo "non"}) then {
    private _rate = if (_sideId == 3) then {
        missionNamespace getVariable ["comspec_sse_bio_falseId_civ", 2]
    } else {
        missionNamespace getVariable ["comspec_sse_bio_falseId_other", 12]
    };
    if ((["COMSPEC_SSE_BioFalsePapers"] call _str) isEqualTo "oui" || {(["falseid"] call _r) * 100 < _rate}) then {
        _falsePapers = true;
        private _fnPool = ["Abdul", "Ahmad", "Bilal", "Faisal", "Hamid", "Jabir", "Karim", "Mahmoud", "Nasser", "Omar", "Rashid", "Samir", "Tariq", "Yasin", "Zaid"];
        private _lnPool = ["Al-Amiri", "Al-Bayati", "Al-Dulaimi", "Al-Hashimi", "Al-Jaburi", "Al-Karbouli", "Al-Obeidi", "Al-Rawi", "Al-Saadi", "Al-Tikriti", "Haddad", "Mansour", "Qasim", "Saleh", "Yousef"];
        if (!isNil "comspec_sse_fnc_getNarrativePools") then {
            private _pools = [_region] call comspec_sse_fnc_getNarrativePools;
            if (_pools isEqualType createHashMap) then {
                _fnPool = _pools getOrDefault ["firstNames", _fnPool];
                _lnPool = _pools getOrDefault ["lastNames", _lnPool];
            };
        };
        private _fn = _fnPool select (floor ((["tfn"] call _r) * (count _fnPool)));
        private _ln = _lnPool select (floor ((["tln"] call _r) * (count _lnPool)));
        _trueName = format ["%1 %2", _fn, _ln];
        if (_trueName isEqualTo _declared) then { _falsePapers = false; };
    };
};
if (!_falsePapers) then { _trueName = _declared; };
if (_trueName isEqualTo "") then { _trueName = format ["Inconnu %1", _key]; };

private _age = 18 + floor ((((["age1"] call _r) + (["age2"] call _r)) / 2) * 50);

// --- Doigts -------------------------------------------------------------------
// Fréquences réelles : boucles ~65 %, verticilles ~30 %, arcs ~5 %. Une tendance
// par personne (mains « à verticilles », « à arcs ») et un miroir partiel D/G.
private _tend = ["fptend"] call _r;
private _pWhorl = [0.30, 0.55] select (_tend < 0.22);
private _pArch = [0.05, 0.22] select (_tend > 0.92);
private _worker = (["worker"] call _r) < ([0.12, 0.30] select (_age > 40));

private _missing = [];
private _mTxt = ["COMSPEC_SSE_BioMissingFingers"] call _str;
if (_mTxt isNotEqualTo "") then {
    {
        private _n = parseNumber _x;
        if (_n >= 1 && {_n <= 10}) then { _missing pushBackUnique (_n - 1); };
    } forEach (_mTxt splitString ",; ");
} else {
    if ((["amput"] call _r) < 0.012) then { _missing pushBack (floor ((["amputf"] call _r) * 10)); };
};

private _fingers = [];
for "_i" from 0 to 9 do {
    // Le doigt homologue de l'autre main garde souvent le même dessin.
    private _salt = if (_i >= 5 && {(["mir" + str _i] call _r) < 0.6}) then { format ["pat%1", _i - 5] } else { format ["pat%1", _i] };
    private _p = [_salt] call _r;
    private _isIndex = _i in [1, 6];
    private _pattern = switch (true) do {
        case (_p < _pArch * 0.6): { "Arc" };
        case (_p < _pArch): { "Arc en tente" };
        case (_p < _pArch + _pWhorl): { ["Verticille", "Double boucle"] select ((["wd" + str _i] call _r) < 0.15) };
        default { ["Boucle cubitale", "Boucle radiale"] select ((["rad" + str _i] call _r) < ([0.04, 0.2] select _isIndex)) };
    };
    private _state = "normal";
    if (_worker && {(["wear" + str _i] call _r) < 0.55}) then { _state = "use"; };
    if ((["scar" + str _i] call _r) < 0.03) then { _state = "cicatrice"; };
    if (_i in _missing) then { _state = "ampute"; };
    _fingers pushBack [_pattern, [_key, "fp" + str _i, 8] call comspec_sse_fnc_bioHex, _state];
};

// --- Iris ---------------------------------------------------------------------
private _cr = ["eyecol"] call _r;
private _colour = switch (true) do {
    case (_cr < 0.76): { "marron" };
    case (_cr < 0.87): { "noisette" };
    case (_cr < 0.94): { "vert" };
    default { "gris-bleu" };
};
private _irisAuth = toLower (["COMSPEC_SSE_BioIrisState"] call _str);
private _eyes = [];
for "_e" from 0 to 1 do {
    private _side = ["od", "og"] select _e;
    private _state = "normal";
    if (_irisAuth in ["", "auto"]) then {
        if (_age > 50 && {([format ["cat%1", _e]] call _r) < 0.18}) then { _state = "cataracte"; };
        if (([format ["prot%1", _e]] call _r) < 0.004) then { _state = "prothese"; };
    } else {
        if (_irisAuth isEqualTo "cataracte") then { _state = "cataracte"; };
        if (_irisAuth isEqualTo format ["prothese_%1", _side]) then { _state = "prothese"; };
        if (_irisAuth isEqualTo format ["lesion_%1", _side]) then { _state = "lesion"; };
    };
    private _col = _colour;
    if (_e == 1 && {(["hetero"] call _r) < 0.005}) then { _col = "vairon"; };
    _eyes pushBack [_col, [_key, "ir" + str _e, 16] call comspec_sse_fnc_bioHex, _state];
};

// --- Visage -------------------------------------------------------------------
private _faceClass = if (isNull _unit) then { "" } else { face _unit };
private _face = [[_key, "face", 12] call comspec_sse_fnc_bioHex, _faceClass];

// --- ADN (13 loci CODIS + amélogénine) ----------------------------------------
private _loci = [
    ["CSF1PO", 7, 15], ["FGA", 18, 28], ["TH01", 6, 10], ["TPOX", 6, 12],
    ["vWA", 13, 20], ["D3S1358", 12, 19], ["D5S818", 7, 14], ["D7S820", 7, 14],
    ["D8S1179", 8, 17], ["D13S317", 8, 15], ["D16S539", 8, 14], ["D18S51", 10, 22],
    ["D21S11", 27, 34]
];
private _allele = {
    params ["_locus", "_lo", "_hi", "_salt"];
    // Moyenne de deux tirages : les allèles centraux sont les plus fréquents.
    private _t = (([_salt + "a"] call _r) + ([_salt + "b"] call _r)) / 2;
    private _v = _lo + floor (_t * ((_hi - _lo) + 1));
    private _txt = str (_v min _hi);
    if (_locus in ["FGA", "D21S11"] && {([_salt + "m"] call _r) < 0.15}) then { _txt = _txt + ".2"; };
    if (_locus isEqualTo "TH01" && {_v == 9} && {([_salt + "m"] call _r) < 0.5}) then { _txt = "9.3"; };
    _txt
};

// Parenté : un parent / enfant partage exactement un allèle par locus, un frère
// en partage deux, un ou aucun (1/4, 1/2, 1/4).
private _relKey = "";
private _relation = "";
private _relName = "";
private _relProfile = createHashMap;
if (!_skipRelative) then {
    private _rel = ["COMSPEC_SSE_BioRelativeOf"] call _str;
    if (_rel isNotEqualTo "") then {
        private _relUnit = missionNamespace getVariable [_rel, objNull];
        if (!(_relUnit isEqualType objNull)) then { _relUnit = objNull; };
        _relKey = if (isNull _relUnit) then { toUpper _rel } else { [_relUnit] call comspec_sse_fnc_bioPersonKey };
        if (_relKey isNotEqualTo "" && {_relKey isNotEqualTo _key}) then {
            _relProfile = [_relUnit, _relKey, true] call comspec_sse_fnc_bioProfile;
            _relation = toLower (["COMSPEC_SSE_BioRelation"] call _str);
            if !(_relation in ["parent", "enfant", "frere"]) then { _relation = "frere"; };
            _relName = _relProfile getOrDefault ["trueName", _relKey];
        } else {
            _relKey = "";
        };
    };
};
private _relDna = _relProfile getOrDefault ["dna", []];

private _dna = [];
{
    _x params ["_locus", "_lo", "_hi"];
    private _a1 = [_locus, _lo, _hi, _locus + "1"] call _allele;
    private _a2 = [_locus, _lo, _hi, _locus + "2"] call _allele;
    if ((count _relDna) > _forEachIndex) then {
        private _ra = _relDna select _forEachIndex;
        private _pick = (["k" + _locus] call _r);
        switch (_relation) do {
            case "parent";
            case "enfant": { _a1 = _ra select (1 + floor (_pick * 2)); };
            default {
                if (_pick < 0.25) then { _a1 = _ra select 1; _a2 = _ra select 2; } else {
                    if (_pick < 0.75) then { _a1 = _ra select (1 + floor ((["k2" + _locus] call _r) * 2)); };
                };
            };
        };
    };
    _dna pushBack [_locus, _a1, _a2];
} forEach _loci;

createHashMapFromArray [
    ["key", _key],
    ["sideId", _sideId],
    ["declaredName", _declared],
    ["trueName", _trueName],
    ["alias", _alias],
    ["nationality", _nat],
    ["falsePapers", _falsePapers],
    ["age", _age],
    ["worker", _worker],
    ["fingers", _fingers],
    ["eyes", _eyes],
    ["face", _face],
    ["dna", _dna],
    ["sex", "XY"],
    ["relativeKey", _relKey],
    ["relation", _relation],
    ["relativeName", _relName]
]
