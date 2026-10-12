/*
    Recherche 1:N dans la base biométrique (serveur).

    Comparaison par modalité : chaque doigt relevé n'est comparé que s'il figure
    aussi au dossier, avec une probabilité de concordance qui dépend de sa note
    NFIQ et de la qualité d'enrôlement ; idem pour chaque iris et pour le visage.
    Les scores se fusionnent. Hors liaison, seule la base locale du terminal est
    consultée (tiers embarqués + rencontres de la mission).

    Ce qu'un vrai système fait aussi, et que le moteur reproduit :
      - un relevé médiocre peut rater la bonne personne (faux négatif) ;
      - un individu ressemblant peut ressortir en « possible » (imposteur) ;
      - une identité déclarée différente du dossier est signalée ;
      - une latente relevée plus tôt dans la mission est rattachée au sujet.

    Params: [_probe] HashMap
      unit, key, declaredName, scope ("local" | "abis"),
      nfiqs [10], eyes [OD, OG], face (qualité, -1 = pas de cliché), attempt
    Returns: HashMap (voir fin de fichier)
*/
params [["_probe", createHashMap, [createHashMap]]];

private _unit = _probe getOrDefault ["unit", objNull];
if (!(_unit isEqualType objNull)) then { _unit = objNull; };
private _key = _probe getOrDefault ["key", ""];
// Sur le serveur, la clé de l'unité est celle du registre (jamais celle calculée par un poste).
if (_key isEqualTo "" || {!isNull _unit}) then { _key = [_unit] call comspec_sse_fnc_bioPersonKey; };
private _scope = _probe getOrDefault ["scope", "local"];
private _attempt = _probe getOrDefault ["attempt", 1];
private _nfiqs = _probe getOrDefault ["nfiqs", []];
private _eyes = _probe getOrDefault ["eyes", []];
private _faceQ = _probe getOrDefault ["face", -1];

private _matchT = missionNamespace getVariable ["comspec_sse_bio_matchThreshold", 60];
private _possT = missionNamespace getVariable ["comspec_sse_bio_possibleThreshold", 25];
private _r = { params ["_salt"]; [_key, format ["srch|%1|%2", _attempt, _salt]] call comspec_sse_fnc_bioRand };

private _res = createHashMapFromArray [
    ["verdict", "none"], ["score", 0], ["ref", ""], ["tier", 0], ["action", ""],
    ["name", ""], ["alias", ""], ["nationality", ""], ["source", ""], ["reason", ""],
    ["enrolledAt", ""], ["encounters", []], ["mismatch", false],
    ["declaredName", _probe getOrDefault ["declaredName", ""]],
    ["fpMatched", 0], ["fpCompared", 0], ["irisMatched", 0], ["irisCompared", 0], ["faceScore", 0],
    ["candidates", []], ["latents", []], ["scope", _scope], ["note", ""], ["athenaRef", ""],
    ["fpDetail", []], ["irisHd", [-1, -1]], ["faceSim", -1], ["henry", ""], ["fpc", ""]
];
// Seuils affichés comme sur un vrai ABIS : minuties concordantes par doigt, distance de Hamming de l'iris.
private _minT = 12;
private _hdT = 0.32;

// --- Qualité du relevé : assez pour interroger ? ----------------------------------------
private _goodFingers = [];
{ if (_x >= 1 && {_x <= 4}) then { _goodFingers pushBack _forEachIndex; }; } forEach _nfiqs;
private _goodEyes = [];
{ if (_x >= 40) then { _goodEyes pushBack _forEachIndex; }; } forEach _eyes;
private _faceOk = _faceQ >= 35;
if (_goodFingers isEqualTo [] && {_goodEyes isEqualTo []} && {!_faceOk}) exitWith {
    _res set ["verdict", "insufficient"];
    _res set ["note", "Relevés inexploitables (NFIQ 5, iris < 40, visage < 35) : recapturez."];
    _res
};

// Qualité moyenne du relevé (0-100), pour les fausses alertes.
private _qs = [];
{ _qs pushBack ([0, 95, 85, 70, 50, 25] select (_nfiqs select _x)); } forEach _goodFingers;
{ _qs pushBack (_eyes select _x); } forEach _goodEyes;
if (_faceOk) then { _qs pushBack _faceQ; };
private _avgQ = 0;
{ _avgQ = _avgQ + _x; } forEach _qs;
_avgQ = _avgQ / ((count _qs) max 1);

// --- Concordance avec le vrai dossier ------------------------------------------------------
private _rec = [_unit, _key] call comspec_sse_fnc_bioRecord;
private _reachable = (count _rec) > 0 && {_scope isEqualTo "abis" || {_rec getOrDefault ["inLocal", false]}};
private _verdict = "none";
private _score = 0;

if (_reachable) then {
    private _prof = [_unit, _key] call comspec_sse_fnc_bioProfile;
    private _pf = _prof getOrDefault ["fingers", []];
    private _fpDetail = [];
    _res set ["henry", _prof getOrDefault ["henry", ""]];
    _res set ["fpc", _prof getOrDefault ["fpc", ""]];
    private _recFps = _rec getOrDefault ["fpFingers", []];
    private _recFpQ = _rec getOrDefault ["fpQuality", 0];
    private _recFactor = switch (true) do { case (_recFpQ >= 70): { 1 }; case (_recFpQ >= 50): { 0.85 }; default { 0.7 }; };
    private _fpScore = 0;
    private _fpMatched = 0;
    private _fpCompared = 0;
    {
        if (_x in _recFps) then {
            private _n = _nfiqs select _x;
            _fpCompared = _fpCompared + 1;
            private _p = ([0, 0.995, 0.985, 0.94, 0.72] select _n) * _recFactor;
            // Minuties du doigt au dossier, et part retrouvée selon la qualité du relevé.
            private _tot = (_pf param [_x, []]) param [4, 60];
            private _ok = (["fp" + str _x] call _r) < _p;
            private _found = if (_ok) then {
                (_minT + 2) max (round (_tot * ([0, 0.8, 0.65, 0.45, 0.28] select _n) * (0.85 + (["fm" + str _x] call _r) * 0.3)))
            } else {
                3 + floor ((["fm" + str _x] call _r) * (_minT - 4))
            };
            _fpDetail pushBack [_x, _found min _tot, _tot];
            if (_ok) then {
                _fpMatched = _fpMatched + 1;
                _fpScore = _fpScore + ([0, 30, 26, 20, 12] select _n);
            };
        };
    } forEach _goodFingers;
    _fpScore = _fpScore min 70;
    _res set ["fpDetail", _fpDetail];

    private _irisScore = 0;
    private _irisMatched = 0;
    private _irisCompared = 0;
    if (_rec getOrDefault ["iris", false]) then {
        {
            private _q = _eyes select _x;
            _irisCompared = _irisCompared + 1;
            private _p = switch (true) do { case (_q >= 70): { 0.995 }; case (_q >= 55): { 0.93 }; default { 0.7 }; };
            private _hds = _res get "irisHd";
            if ((["ir" + str _x] call _r) < _p) then {
                _irisMatched = _irisMatched + 1;
                _irisScore = _irisScore + 40;
                // Même œil : distance de Hamming 0,12 à 0,30 (plus faible si le relevé est bon).
                _hds set [_x, parseNumber ((0.12 + ((100 - _q) / 100) * 0.25 + (["hd" + str _x] call _r) * 0.05) toFixed 3)];
            } else {
                _hds set [_x, parseNumber ((0.33 + (["hd" + str _x] call _r) * 0.05) toFixed 3)];
            };
        } forEach _goodEyes;
    };
    _irisScore = _irisScore min 60;

    private _faceScore = 0;
    if (_faceOk && {_rec getOrDefault ["face", false]}) then {
        private _rq = _rec getOrDefault ["faceQuality", 60];
        private _p = (switch (true) do { case (_faceQ >= 80): { 0.92 }; case (_faceQ >= 60): { 0.8 }; default { 0.55 }; }) * ([0.8, 1] select (_rq >= 60));
        if ((["face"] call _r) < _p) then {
            _faceScore = round (15 + (_faceQ / 5));
            _res set ["faceSim", parseNumber ((0.72 + (_faceQ / 100) * 0.2 + (["fs"] call _r) * 0.05) toFixed 2)];
        } else {
            _res set ["faceSim", parseNumber ((0.45 + (["fs"] call _r) * 0.2) toFixed 2)];
        };
    };

    _score = ((_fpScore + _irisScore + _faceScore) + ((["dec"] call _r) * 0.9)) min 99.6;
    _verdict = switch (true) do {
        case (_score >= _matchT && {_fpMatched >= 2 || {_irisMatched >= 1} || {_fpMatched >= 1 && {_faceScore > 0}}}): { "match" };
        case (_score >= _possT): { "possible" };
        default { "none" };
    };
    _res set ["fpMatched", _fpMatched];
    _res set ["fpCompared", _fpCompared];
    _res set ["irisMatched", _irisMatched];
    _res set ["irisCompared", _irisCompared];
    _res set ["faceScore", _faceScore];
};

// --- Imposteur : un individu ressemblant ressort -----------------------------------------
private _impRate = (missionNamespace getVariable ["comspec_sse_bio_impostorRate", 4]) / 100;
private _faceOnly = _goodFingers isEqualTo [] && {_goodEyes isEqualTo []};
private _pImp = _impRate * ((1 + 2 * (1 - (_avgQ / 100))) ^ 2) * ([1, 3] select _faceOnly);
private _candidates = [];
if (_impRate > 0 && {(["imp"] call _r) < _pImp}) then {
    private _pool = (allUnits + allDeadMen) select { _x isKindOf "CAManBase" && {!isPlayer _x} && {_x != _unit} };
    private _other = createHashMap;
    if (_pool isNotEqualTo []) then {
        private _start = floor ((["impPick"] call _r) * (count _pool));
        for "_i" from 0 to (((count _pool) min 12) - 1) do {
            private _u = _pool select ((_start + _i) mod (count _pool));
            private _orec = [_u] call comspec_sse_fnc_bioRecord;
            if ((count _orec) > 0 && {_scope isEqualTo "abis" || {_orec getOrDefault ["inLocal", false]}}) exitWith { _other = _orec; };
        };
    };
    if ((count _other) > 0) then {
        private _iScore = (_possT + ((["impScore"] call _r) * ((_matchT - _possT) * 0.7))) toFixed 1;
        _candidates pushBack [_other get "id", _other get "name", parseNumber _iScore, _other get "source", _other getOrDefault ["tier", 0]];
    };
};

// --- Résultat ---------------------------------------------------------------------------------
private _shown = createHashMap;
if (_verdict isNotEqualTo "none") then {
    _shown = _rec;
    _candidates = [[_rec get "id", _rec get "name", parseNumber (_score toFixed 1), _rec get "source", _rec getOrDefault ["tier", 0]]] + _candidates;
} else {
    if (_candidates isNotEqualTo []) then {
        // Le mauvais individu ressort en tête : à confirmer par un second relevé.
        _verdict = "possible";
        _score = (_candidates select 0) select 2;
        _shown = createHashMapFromArray [
            ["id", (_candidates select 0) select 0], ["name", (_candidates select 0) select 1],
            ["source", (_candidates select 0) select 3], ["tier", (_candidates select 0) select 4],
            ["alias", ""], ["nationality", ""], ["reason", ""], ["enrolledAt", ""], ["encounters", []]
        ];
        // Ce que l'opérateur voit d'un imposteur : peu de minuties, iris au-delà du seuil.
        _res set ["fpDetail", _goodFingers apply { [_x, 6 + floor ((["im" + str _x] call _r) * 5), 45 + floor ((["it" + str _x] call _r) * 40)] }];
        _res set ["irisHd", [0, 1] apply { if (_x in _goodEyes) then { parseNumber ((0.42 + (["ih" + str _x] call _r) * 0.06) toFixed 3) } else { -1 } }];
        _res set ["faceSim", [-1, parseNumber ((0.6 + (["if"] call _r) * 0.12) toFixed 2)] select _faceOk];
        _res set ["henry", ""];
        _res set ["fpc", ""];
        _res set ["impostor", true];
        _res set ["note", "Candidat à confirmer : concordance faible (minuties sous le seuil, iris non concordant). Gabarits différents du sujet : relevez une autre modalité avant toute mesure."];
    };
};

// Ancien indice de confiance imposé par la préparation de mission : on l'affiche.
if (!isNull _unit) then {
    private _forced = _unit getVariable ["COMSPEC_SSE_Confidence", -1];
    if (_forced isEqualType 0 && {_forced >= 0} && {_verdict isNotEqualTo "none"}) then { _score = _forced; };
};

_res set ["verdict", _verdict];
_res set ["score", parseNumber (_score toFixed 1)];
_res set ["candidates", _candidates];

if ((count _shown) > 0) then {
    private _tier = _shown getOrDefault ["tier", 0];
    _res set ["ref", _shown getOrDefault ["id", ""]];
    _res set ["tier", _tier];
    _res set ["name", _shown getOrDefault ["name", ""]];
    _res set ["alias", _shown getOrDefault ["alias", ""]];
    _res set ["nationality", _shown getOrDefault ["nationality", ""]];
    _res set ["source", _shown getOrDefault ["source", ""]];
    _res set ["reason", _shown getOrDefault ["reason", ""]];
    _res set ["enrolledAt", _shown getOrDefault ["enrolledAt", ""]];
    _res set ["encounters", _shown getOrDefault ["encounters", []]];
    _res set ["athenaRef", _shown getOrDefault ["athenaRef", ""]];
    _res set ["action", switch (_tier) do {
        case 1: { "DETAIN : interpeller, retenir le sujet, aviser le PC" };
        case 2: { "QUESTION / DETAIN : questionner, retenir, aviser le PC" };
        case 3: { "QUESTION · DENY ACCESS : questionner, refuser l'accès" };
        case 4: { "POUR INFO : consigner le contact, poursuivre" };
        default { "Connu des bases, non signalé : poursuivre selon consignes" };
    }];
    // Identité déclarée différente du dossier (faux papiers ?).
    private _norm = { params ["_s"]; ((toLower _s) splitString " -'") joinString "" };
    private _decl = [_res get "declaredName"] call _norm;
    if (_verdict isEqualTo "match" && {_decl isNotEqualTo ""} && {_decl isNotEqualTo ([_res get "name"] call _norm)} && {_decl isNotEqualTo ([_res get "alias"] call _norm)}) then {
        _res set ["mismatch", true];
    };
};

// --- Latentes de la mission rattachées au sujet ---------------------------------------------------
private _latents = missionNamespace getVariable ["comspec_sse_bioLatents", []];
private _lHits = [];
if (_latents isEqualType []) then {
    {
        if ((_x getOrDefault ["ownerKey", ""]) isEqualTo _key) then {
            private _lf = _x getOrDefault ["fingers", []];
            private _common = _goodFingers select { (_x in _lf) && {(_nfiqs select _x) <= 3} };
            if (_common isNotEqualTo []) then {
                _lHits pushBack [_x getOrDefault ["id", "LAT"], _x getOrDefault ["label", ""], _x getOrDefault ["grid", ""], _x getOrDefault ["when", ""]];
            };
        };
    } forEach _latents;
};
_res set ["latents", _lHits];
if (_verdict isEqualTo "none" && {_lHits isNotEqualTo []}) then {
    _res set ["verdict", "latent"];
    _res set ["note", "Inconnu des bases, mais ses empreintes figurent sur des objets saisis."];
};
if (((_res getOrDefault ["note", ""]) isEqualTo "") && {(_res get "verdict") isEqualTo "none"}) then {
    _res set ["note", if (_scope isEqualTo "local") then { "Aucune correspondance dans la base locale. En liaison, l'ABIS complet sera interrogé." } else { "Aucune correspondance : enrôlez le sujet (transmission)." }];
};

_res
