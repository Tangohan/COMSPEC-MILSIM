/*
    Rafraîchit les panneaux dynamiques du terminal SEEK II :
    empreintes (dix doigts, NFIQ), iris (OD / OG), revue (bandeau watchlist,
    synthèse des modalités), signature, viseur du visage, barres d'état.

    Présentation seulement : les données restent celles de la fiche
    (COMSPEC_SsePerson_Samples / _Query / _Target). Le détail par doigt et par œil
    est dérivé de la qualité du relevé et de la graine du sujet — stable d'un
    rafraîchissement à l'autre, jamais retiré au hasard.
*/
if (!hasInterface) exitWith {};

private _disp = uiNamespace getVariable ["COMSPEC_SsePerson_Display", displayNull];
if (isNull _disp) then { _disp = findDisplay 9991; };
if (isNull _disp) exitWith {};

// Encre de l'écran (fond gris clair).
private _cInk = "#14181d";
private _cSub = "#33414c";
private _cMute = "#5a6670";
private _cOk = "#1a7f37";
private _cWarn = "#9a5b00";
private _cBad = "#b3261e";
private _cBlue = "#0a246a";

// --- État commun ---
private _samples = uiNamespace getVariable ["COMSPEC_SsePerson_Samples", []];
if (!(_samples isEqualType [])) then { _samples = []; };
private _byKind = createHashMap;
{
    if ((_x isEqualType []) && {(count _x) >= 3}) then { _byKind set [_x select 0, _x]; };
} forEach _samples;

private _bt = uiNamespace getVariable ["COMSPEC_SsePerson_Target", objNull];
private _seed = if (isNull _bt) then { 4242 } else { [_bt] call comspec_overwatch_connect_fnc_sseUnitSeed };
if (!(_seed isEqualType 0) || {_seed <= 0}) then { _seed = 4242; };

private _bioMsg = uiNamespace getVariable ["COMSPEC_SsePerson_BioMsg", ""];
if (!(_bioMsg isEqualType "")) then { _bioMsg = ""; };

private _queryPending = uiNamespace getVariable ["COMSPEC_SsePerson_QueryPending", false];
private _query = uiNamespace getVariable ["COMSPEC_SsePerson_Query", []];
if (!(_query isEqualType [])) then { _query = []; };

private _link = toLower (call {
    // État de liaison Athena : COMSPEC_LinkState est une chaîne ("linked", "degraded"…). Sans état
    // COMSPEC Link, on suit la liaison propre de l'ATAK natif (barre d'état verte = en ligne).
    private _s = missionNamespace getVariable ["COMSPEC_LinkState", ""];
    if (!(_s isEqualType "")) then { _s = ""; };
    _s = toLower _s;
    if (_s isEqualTo "connecting") then { _s = "degraded"; };
    if (_s isEqualTo "") then {
        private _n = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
        _s = if (_n isEqualType createHashMap) then { toLower (_n getOrDefault ["networkState", "offline"]) } else { "offline" };
        if (_s isEqualTo "connected") then { _s = "linked"; };
    };
    _s
});
private _online = _link isEqualTo "linked";

private _st = systemTime;
private _fnc_two = { params ["_n"]; if (_n < 10) then { format ["0%1", _n] } else { str _n } };
private _clock = format ["%1:%2", [_st select 3] call _fnc_two, [_st select 4] call _fnc_two];

// Couleur d'une qualité 0-100 (rouge / ambre / vert).
private _fnc_qCol = {
    params ["_q"];
    if (_q >= 80) exitWith { _cOk };
    if (_q >= 65) exitWith { _cWarn };
    _cBad
};

// =====================================================================
// EMPREINTES — dix doigts, ordre FBI (1 pouce D … 5 auriculaire D, 6 pouce G … 10 auriculaire G)
// =====================================================================
private _fingerNames = [
    "pouce droit", "index droit", "majeur droit", "annulaire droit", "auriculaire droit",
    "pouce gauche", "index gauche", "majeur gauche", "annulaire gauche", "auriculaire gauche"
];
private _fp = _byKind getOrDefault ["empreintes", []];
private _nfiqs = [];
// Relevé du moteur biométrique : vraies notes par doigt (-1 amputé, -2 main trop blessée).
private _fpDetail = _fp param [3, []];
if ((_fpDetail isEqualType []) && {(count _fpDetail) == 10}) then {
    _nfiqs = +_fpDetail;
} else {
if ((count _fp) >= 3) then {
    private _q = _fp select 1;
    // NFIQ : 1 = excellent … 5 = inexploitable. Base tirée de la qualité du relevé,
    // dispersion par doigt tirée de la graine du sujet (stable).
    private _base = switch (true) do {
        case (_q >= 92): { 1 };
        case (_q >= 84): { 2 };
        case (_q >= 76): { 3 };
        default { 4 };
    };
    for "_i" from 0 to 9 do {
        private _j = (floor (_seed / (3 ^ _i))) mod 3;
        _nfiqs pushBack (((_base + _j - 1) max 1) min 5);
    };
};
};
private _fpDone = (count _nfiqs) isEqualTo 10;
{
    private _ctrl = _disp displayCtrl _x;
    if (!isNull _ctrl) then {
        if (_fpDone && {(_nfiqs select _forEachIndex) < 1}) then {
            private _n = _nfiqs select _forEachIndex;
            _ctrl ctrlSetText (switch (_n) do { case -1: { "X" }; case -2: { "B" }; default { "-" }; });
            _ctrl ctrlSetTextColor [1, 1, 1, 1];
            _ctrl ctrlSetBackgroundColor (switch (_n) do { case -1: { [0.25, 0.26, 0.28, 0.95] }; case -2: { [0.55, 0.10, 0.10, 0.95] }; default { [0.70, 0.71, 0.72, 0.85] }; });
            _ctrl ctrlSetTooltip format ["%1 · %2", _fingerNames select _forEachIndex, switch (_n) do { case -1: { "amputé" }; case -2: { "main trop blessée : non relevable" }; default { "non relevé" }; }];
        } else {
        if (_fpDone) then {
            private _n = _nfiqs select _forEachIndex;
            _ctrl ctrlSetText str _n;
            _ctrl ctrlSetTextColor ([[0.05, 0.05, 0.05, 1], [1, 1, 1, 1]] select (_n >= 4));
            _ctrl ctrlSetBackgroundColor (switch (true) do {
                case (_n <= 2): { [0.16, 0.66, 0.27, 0.92] };
                case (_n == 3): { [0.95, 0.72, 0.10, 0.92] };
                default { [0.80, 0.16, 0.12, 0.92] };
            });
            _ctrl ctrlSetTooltip format ["%1 · NFIQ %2", _fingerNames select _forEachIndex, _n];
        } else {
            // Doigt attendu : index droit (début de la séquence slap 4-4-2).
            private _next = _forEachIndex isEqualTo 1;
            _ctrl ctrlSetText (["-", ">"] select _next);
            _ctrl ctrlSetTextColor ([[0.25, 0.26, 0.28, 1], [1, 1, 1, 1]] select _next);
            _ctrl ctrlSetBackgroundColor ([[0.70, 0.71, 0.72, 0.85], [0.08, 0.30, 0.78, 0.95]] select _next);
            _ctrl ctrlSetTooltip format ["%1 · non capturé", _fingerNames select _forEachIndex];
        };
        };
    };
} forEach [9610, 9611, 9612, 9613, 9614, 9615, 9616, 9617, 9618, 9619];

private _avgNfiq = 0;
private _fpUsable = _nfiqs select { _x >= 1 };
if (_fpDone && {_fpUsable isNotEqualTo []}) then {
    private _s = 0;
    { _s = _s + _x; } forEach _fpUsable;
    _avgNfiq = _s / (count _fpUsable);
};
private _fpFactors = _fp param [4, []];
if (!(_fpFactors isEqualType [])) then { _fpFactors = []; };

private _fpPrompt = if (_fpDone) then {
    format [
        "<t size='0.46' font='TahomaB' color='%1'>%4</t><t size='0.44' color='%2'>  ·  %5/10 doigts  ·  NFIQ moyen %3</t>",
        [_cOk, _cWarn] select (_avgNfiq >= 3.5), _cSub, _avgNfiq toFixed 1,
        ["CAPTURE TERMINÉE", "CAPTURE MÉDIOCRE : RECAPTURER"] select (_avgNfiq >= 3.5),
        count _fpUsable
    ]
} else {
    format [
        "<t size='0.46' font='TahomaB' color='%1'>Placez l'INDEX DROIT sur le capteur</t><t size='0.42' color='%2'>  ·  Place right index on sensor</t>",
        _cBlue, _cMute
    ]
};
if (_bioMsg isNotEqualTo "") then { _fpPrompt = _bioMsg; };
private _fpPromptCtrl = _disp displayCtrl 9621;
if (!isNull _fpPromptCtrl) then { _fpPromptCtrl ctrlSetStructuredText parseText _fpPrompt; };

private _fnc_step = {
    params ["_label", "_done"];
    format [
        "<t size='0.42' color='%1' font='TahomaB'>%2</t><t size='0.42' color='%3'>  %4</t>",
        [_cMute, _cOk] select _done,
        ["[  ]", "[OK]"] select _done,
        _cInk,
        _label
    ]
};
private _fpRows = [
    format ["<t size='0.44' font='TahomaB' color='%1'>SÉQUENCE</t>", _cSub],
    ["Slap 4-4-2 (D · G · pouces)", _fpDone] call _fnc_step,
    ["Roulés 10 doigts", _fpDone] call _fnc_step
];
_fpRows pushBack format [
    "<t size='0.38' color='%1'>NFIQ </t><t size='0.38' color='%2' font='TahomaB'>1-2</t><t size='0.38' color='%1'> bon · </t><t size='0.38' color='%3' font='TahomaB'>3</t><t size='0.38' color='%1'> moyen · </t><t size='0.38' color='%4' font='TahomaB'>4-5</t><t size='0.38' color='%1'> à refaire</t>",
    _cMute, _cOk, _cWarn, _cBad
];
if (_fpDone) then {
    _fpRows pushBack format ["<t size='0.38' color='%1'>Qualité %2%3 · réf. %4</t>", _cMute, _fp select 1, "%", _fp select 2];
    { _fpRows pushBack format ["<t size='0.38' color='%1'>· %2</t>", _cWarn, _x]; } forEach (_fpFactors select [0, 4]);
};
private _fpPanel = _disp displayCtrl 9622;
if (!isNull _fpPanel) then { _fpPanel ctrlSetStructuredText parseText (_fpRows joinString "<br/>"); };

// =====================================================================
// IRIS — œil droit (OD) et œil gauche (OG)
// =====================================================================
private _ir = _byKind getOrDefault ["iris", []];
private _irDone = (count _ir) >= 3;
private _eyeQ = [-1, -1];
private _irDetail = _ir param [3, []];
if (_irDone && {(_irDetail isEqualType [])} && {(count _irDetail) == 2}) then {
    _eyeQ = +_irDetail;
} else {
if (_irDone) then {
    private _q = _ir select 1;
    _eyeQ = [
        ((_q + ((_seed mod 7) - 3)) max 0) min 100,
        ((_q + ((floor (_seed / 7)) mod 7) - 3) max 0) min 100
    ];
};
};
{
    _x params ["_pic", "_txt", "_bg", "_bar"];
    private _q = _eyeQ select _forEachIndex;
    private _picCtrl = _disp displayCtrl _pic;
    if (!isNull _picCtrl) then { _picCtrl ctrlSetTextColor [1, 1, 1, [0.30, 1] select (_q >= 0)]; };
    private _txtCtrl = _disp displayCtrl _txt;
    if (!isNull _txtCtrl) then {
        _txtCtrl ctrlSetStructuredText parseText (if (_q < 0) then {
            format ["<t size='0.42' color='%1' align='center'>%2</t>", _cMute, ["Non capturé", "Pas d'iris (prothèse)"] select (_irDone && {_q == -1})]
        } else {
            format [
                "<t size='0.42' color='%1' align='center'>Qualité </t><t size='0.44' font='TahomaB' color='%2' align='center'>%3</t><t size='0.42' color='%2' align='center'>  ·  %4</t>",
                _cSub, [_q] call _fnc_qCol, _q, ["FAIBLE, RECAPTURER", "UTILISABLE"] select (_q >= 70)
            ]
        });
    };
    private _bgCtrl = _disp displayCtrl _bg;
    private _barCtrl = _disp displayCtrl _bar;
    if (!isNull _bgCtrl && {!isNull _barCtrl}) then {
        private _pp = ctrlPosition _bgCtrl;
        _pp set [2, ((_pp select 2) * ((_q max 0) / 100)) max 0.001];
        _barCtrl ctrlSetPosition _pp;
        _barCtrl ctrlCommit 0;
        _barCtrl ctrlSetBackgroundColor (switch (true) do {
            case (_q >= 80): { [0.12, 0.55, 0.22, 1] };
            case (_q >= 65): { [0.88, 0.62, 0.05, 1] };
            default { [0.75, 0.15, 0.11, 1] };
        });
    };
} forEach [[9632, 9627, 9636, 9638], [9633, 9628, 9637, 9639]];

private _irPrompt = if (_irDone) then {
    format [
        "<t size='0.46' font='TahomaB' color='%1'>CAPTURE IRIS TERMINÉE</t><t size='0.44' color='%2'>  ·  OD + OG</t>",
        _cOk, _cSub
    ]
} else {
    format [
        "<t size='0.46' font='TahomaB' color='%1'>Objectif à 10-15 cm des yeux, regard fixe</t><t size='0.42' color='%2'>  ·  Look into the lens</t>",
        _cBlue, _cMute
    ]
};
if (_bioMsg isNotEqualTo "") then { _irPrompt = _bioMsg; };
private _irPromptCtrl = _disp displayCtrl 9629;
if (!isNull _irPromptCtrl) then { _irPromptCtrl ctrlSetStructuredText parseText _irPrompt; };
private _irNote = _disp displayCtrl 9626;
if (!isNull _irNote) then {
    _irNote ctrlSetStructuredText parseText (if (_irDone) then {
        format ["<t size='0.42' color='%1'>Capture double · qualité %2%3 · réf. %4%5</t>", _cMute, _ir select 1, "%", _ir select 2,
            [format ["<br/><t color='%1'>%2</t>", _cWarn, ((_ir param [4, []]) select [0, 3]) joinString " · "], ""] select (((_ir param [4, []]) isEqualTo []))]
    } else {
        format ["<t size='0.42' color='%1'>Capture double (les deux yeux). Ôtez lunettes et couvre-chef.</t>", _cMute]
    });
};

// =====================================================================
// REVUE — bandeau watchlist (type BEWL), synthèse, complétude
// =====================================================================
private _source = if (_online) then { "Watchlist locale + ABIS (liaison)" } else { "Local watchlist (hors liaison)" };
private _banColor = [0.55, 0.56, 0.58, 1];
private _banTxt = "";
private _detail = "";
if (_queryPending) then {
    _banColor = [0.06, 0.20, 0.52, 1];
    _banTxt = "<t size='0.62' font='TahomaB' color='#ffffff'>QUERY…</t><br/><t size='0.44' color='#dde6ff'>Recherche dans la watchlist</t>";
    _detail = format ["<t size='0.42' color='%1'>Source : %2</t>", _cMute, _source];
} else {
    if ((count _query) >= 3) then {
        _query params ["_res", "_conf", "_ref", ["_xp", []]];
        // Résultat complet du moteur biométrique (tier réel, dossier, alertes).
        private _x4 = createHashMap;
        if (!isNil "comspec_sse_fnc_bioUnpack" && {_xp isNotEqualTo []}) then {
            _x4 = [_xp] call comspec_sse_fnc_bioUnpack;
            if (!(_x4 isEqualType createHashMap)) then { _x4 = createHashMap; };
        };
        uiNamespace setVariable ["COMSPEC_SsePerson_QueryDetail", _x4];
        switch (_res) do {
            case "insufficient": {
                _banColor = [0.42, 0.43, 0.45, 1];
                _banTxt = "<t size='0.62' font='TahomaB' color='#ffffff'>QUALITÉ INSUFFISANTE</t><br/><t size='0.44' color='#ffffff'>Relevés inexploitables : recapturez (NFIQ 1-4, iris 40+)</t>";
            };
            case "confirmed": {
                // Tier du dossier (moteur), sinon 1 ou 2 selon le sujet.
                private _tier = _x4 getOrDefault ["tier", 1 + (_seed mod 2)];
                if (_tier < 1) then { _tier = 0; };
                _banColor = [0.72, 0.07, 0.06, 1];
                if (_tier isEqualTo 0) then { _banColor = [0.06, 0.30, 0.55, 1]; };
                _banTxt = format [
                    "<t size='0.62' font='TahomaB' color='#ffffff'>%1</t><br/><t size='0.44' font='TahomaB' color='#ffffff'>%2</t>",
                    if (_tier > 0) then { format ["MATCH · TIER %1", _tier] } else { "MATCH · CONNU, NON SIGNALÉ" },
                    _x4 getOrDefault ["action", ["DETAIN : interpeller, retenir le sujet, aviser le PC", "QUESTION / DETAIN : questionner, retenir, aviser le PC"] select ((_tier - 1) max 0 min 1)]
                ];
            };
            case "possible": {
                _banColor = [0.93, 0.62, 0.04, 1];
                _banTxt = "<t size='0.62' font='TahomaB' color='#14181d'>POSSIBLE MATCH · TIER 3</t><br/><t size='0.44' font='TahomaB' color='#14181d'>QUESTION · DENY ACCESS : à confirmer par le PC</t>";
            };
            default {
                _banColor = [0.10, 0.52, 0.20, 1];
                _banTxt = "<t size='0.62' font='TahomaB' color='#ffffff'>NO MATCH</t><br/><t size='0.44' color='#ffffff'>Aucun signalement : enrôler (Enroll) et poursuivre selon consignes</t>";
                if ((_x4 getOrDefault ["latents", []]) isNotEqualTo []) then {
                    _banColor = [0.55, 0.30, 0.04, 1];
                    _banTxt = "<t size='0.62' font='TahomaB' color='#ffffff'>NO MATCH · LATENTES</t><br/><t size='0.44' color='#ffffff'>Inconnu des bases, mais ses empreintes sont sur des objets saisis</t>";
                };
            };
        };
        _detail = format [
            "<t size='0.42' color='%1'>Score </t><t size='0.42' font='TahomaB' color='%2'>%3</t><t size='0.42' color='%1'>  ·  dossier </t><t size='0.42' font='TahomaB' color='%2'>%4</t><t size='0.42' color='%1'>  ·  source : %5</t>",
            _cMute, _cInk,
            if (_res in ["none", "insufficient"]) then { "--" } else { _conf toFixed 1 },
            if (_ref isEqualTo "") then { "--" } else { _ref },
            _source
        ];
    } else {
        _banTxt = "<t size='0.62' font='TahomaB' color='#ffffff'>NON INTERROGÉ</t><br/><t size='0.44' color='#ffffff'>Capturez au moins une modalité puis appuyez sur QUERY</t>";
        _detail = format ["<t size='0.42' color='%1'>Source : %2</t>", _cMute, _source];
    };
};
private _ban = _disp displayCtrl 9640;
if (!isNull _ban) then { _ban ctrlSetBackgroundColor _banColor; };
private _banText = _disp displayCtrl 9641;
if (!isNull _banText) then { _banText ctrlSetStructuredText parseText _banTxt; };
private _det = _disp displayCtrl 9642;
if (!isNull _det) then { _det ctrlSetStructuredText parseText _detail; };

// Synthèse des modalités (9522) : une ligne par modalité, sujet en tête.
private _subj = if (isNull _bt) then {
    format ["<t size='0.44' color='%1'>Aucun sujet visé : placez-vous à côté de la personne.</t>", _cWarn]
} else {
    private _n = trim format ["%1 %2", _bt getVariable ["COMSPEC_SSE_FirstName", ""], _bt getVariable ["COMSPEC_SSE_LastName", ""]];
    if (_n isEqualTo "") then { _n = "personne visée"; };
    format [
        "<t size='0.44' color='%1'>Sujet : </t><t size='0.44' font='TahomaB' color='%2'>%3</t><t size='0.42' color='%1'>%4  ·  %5 m</t>",
        _cMute, _cInk, _n,
        ["", " (décédé)"] select (!alive _bt),
        (player distance _bt) toFixed 1
    ]
};
private _fnc_row = {
    params ["_label", "_done", "_txt"];
    format [
        "<t size='0.44' font='TahomaB' color='%1'>%2</t><t size='0.44' font='TahomaB' color='%3'>  %4</t><t size='0.42' color='%5'>  %6</t>",
        [_cMute, _cOk] select _done, ["[  ]", "[OK]"] select _done, _cInk, _label, _cMute, _txt
    ]
};
private _face = uiNamespace getVariable ["COMSPEC_SseFace", createHashMap];
private _fmeta = if (isNull _bt) then { [] } else { _bt getVariable ["comspec_sse_facePhotoMeta", []] };
if (!(_fmeta isEqualType [])) then { _fmeta = []; };
private _faceDone = (count _fmeta) >= 3;
private _dna = _byKind getOrDefault ["adn", []];
private _rows = [_subj];
_rows pushBack (["EMPREINTES", _fpDone, if (_fpDone) then { format ["%1/10 · NFIQ moy. %2", count _fpUsable, _avgNfiq toFixed 1] } else { "0/10" }] call _fnc_row);
_rows pushBack (["IRIS", _irDone, if (_irDone) then { format ["OD %1 · OG %2", _eyeQ select 0, _eyeQ select 1] } else { "0/2" }] call _fnc_row);
_rows pushBack (["VISAGE", _faceDone, if (_faceDone) then { format ["cliché %1 · qualité %2", _fmeta select 2, _fmeta select 1] } else { "aucun cliché" }] call _fnc_row);
_rows pushBack (["ADN", (count _dna) >= 3, if ((count _dna) >= 3) then { format ["écouvillon · %1", _dna select 2] } else { "optionnel (kit)" }] call _fnc_row);
// Dossier trouvé par le moteur : identité, motif, rencontres, alertes, concordance.
private _qd = uiNamespace getVariable ["COMSPEC_SsePerson_QueryDetail", createHashMap];
if ((count _query) >= 3 && {_qd isEqualType createHashMap} && {(count _qd) > 0}) then {
    if (_qd getOrDefault ["mismatch", false]) then {
        _rows pushBack format ["<t size='0.44' font='TahomaB' color='%1'>ALERTE : identité déclarée ≠ dossier (faux papiers ?)</t>", _cBad];
    };
    if ((_qd getOrDefault ["name", ""]) isNotEqualTo "") then {
        _rows pushBack format ["<t size='0.42' color='%1'>Dossier </t><t size='0.42' font='TahomaB' color='%2'>%3</t><t size='0.42' color='%1'>%4  ·  %5</t>",
            _cMute, _cInk, _qd get "name",
            [format [" (alias %1)", _qd getOrDefault ["alias", ""]], ""] select ((_qd getOrDefault ["alias", ""]) isEqualTo ""),
            _qd getOrDefault ["enrolledAt", _qd getOrDefault ["source", ""]]];
        if ((_qd getOrDefault ["reason", ""]) isNotEqualTo "" && {(_qd getOrDefault ["tier", 0]) > 0}) then {
            _rows pushBack format ["<t size='0.42' color='%1'>Motif : %2</t>", _cSub, _qd get "reason"];
        };
        private _enc = _qd getOrDefault ["encounters", []];
        if (_enc isNotEqualTo []) then {
            private _last = _enc select ((count _enc) - 1);
            _rows pushBack format ["<t size='0.42' color='%1'>Déjà rencontré %2 fois · dernier : %3 %4 %5</t>", _cSub, count _enc, _last param [0, ""], _last param [1, ""], _last param [2, ""]];
        };
    };
    if ((_qd getOrDefault ["fpCompared", 0]) > 0 || {(_qd getOrDefault ["irisCompared", 0]) > 0} || {(_qd getOrDefault ["faceScore", 0]) > 0}) then {
        _rows pushBack format ["<t size='0.42' color='%1'>Concordance : doigts %2/%3 · iris %4/%5 · visage %6</t>", _cMute,
            _qd getOrDefault ["fpMatched", 0], _qd getOrDefault ["fpCompared", 0], _qd getOrDefault ["irisMatched", 0], _qd getOrDefault ["irisCompared", 0],
            [str (_qd getOrDefault ["faceScore", 0]), "--"] select ((_qd getOrDefault ["faceScore", 0]) == 0)];
    };
    // Détail comme sur un vrai ABIS : minuties par doigt, distance de Hamming de l'iris, similarité faciale.
    private _fpD = _qd getOrDefault ["fpDetail", []];
    if (_fpD isNotEqualTo []) then {
        private _fn = ["PD", "ID", "MD", "AD", "OD", "PG", "IG", "MG", "AG", "OG"];
        _rows pushBack format ["<t size='0.40' color='%1'>Minuties (seuil 12) : %2</t>", _cMute,
            (_fpD apply { format ["%1 %2/%3", _fn select (_x select 0), _x select 1, _x select 2] }) joinString " · "];
    };
    private _hd = _qd getOrDefault ["irisHd", [-1, -1]];
    if ((_hd findIf { _x >= 0 }) >= 0) then {
        _rows pushBack format ["<t size='0.40' color='%1'>Iris, distance de Hamming (seuil 0,32) : OD %2 · OG %3</t>", _cMute,
            ["--", (_hd select 0) toFixed 3] select ((_hd select 0) >= 0), ["--", (_hd select 1) toFixed 3] select ((_hd select 1) >= 0)];
    };
    if ((_qd getOrDefault ["faceSim", -1]) >= 0) then {
        _rows pushBack format ["<t size='0.40' color='%1'>Visage, similarité : %2</t>", _cMute, (_qd get "faceSim") toFixed 2];
    };
    if ((_qd getOrDefault ["henry", ""]) isNotEqualTo "") then {
        _rows pushBack format ["<t size='0.40' color='%1'>Henry %2 · FPC %3</t>", _cMute, _qd get "henry", _qd getOrDefault ["fpc", ""]];
    };
    { _rows pushBack format ["<t size='0.42' color='%1'>Latente %2 : %3 (%4)</t>", _cWarn, _x select 0, _x select 1, _x select 2]; } forEach (_qd getOrDefault ["latents", []]);
    if ((_qd getOrDefault ["note", ""]) isNotEqualTo "") then { _rows pushBack format ["<t size='0.40' color='%1'>%2</t>", _cMute, _qd get "note"]; };
};
if (_bioMsg isNotEqualTo "") then { _rows pushBack _bioMsg; };
private _bioCtrl = _disp displayCtrl 9522;
if (!isNull _bioCtrl) then { _bioCtrl ctrlSetStructuredText parseText (_rows joinString "<br/>"); };

// Jauge de complétude (9566) : empreintes, iris, visage, ADN.
private _platen = _disp displayCtrl 9566;
if (!isNull _platen && {!isNull _bioCtrl}) then {
    private _full = (ctrlPosition _bioCtrl) select 2;
    private _pp = ctrlPosition _platen;
    private _n = (parseNumber _fpDone) + (parseNumber _irDone) + (parseNumber _faceDone) + (parseNumber ((count _dna) >= 3));
    private _frac = _n / 4;
    _pp set [2, _full * (_frac max 0.02)];
    _platen ctrlSetPosition _pp;
    _platen ctrlCommit 0;
    _platen ctrlSetBackgroundColor ([[0.55, 0.56, 0.58, 1], [0.12, 0.55, 0.22, 1]] select (_frac > 0));
};

// --- Signature (9520) ---
private _sig = uiNamespace getVariable ["COMSPEC_SsePerson_Signature", []];
private _signed = (_sig isEqualType []) && {(count _sig) >= 4};
private _sigTxt = if (_signed) then {
    format [
        "<t size='0.56' font='TahomaB' color='%4'>Signé  %1</t><br/><t size='0.48' color='%5'>%2  ·  %3</t>",
        _sig select 0,
        _sig select 1,
        _sig select 3,
        _cOk, _cSub
    ]
} else {
    format ["<t size='0.56' font='TahomaB' color='%1'>Non signé</t><br/><t size='0.48' color='%2'>La fiche partira sans procès-verbal (touche SIGN).</t>", _cWarn, _cSub]
};
(_disp displayCtrl 9520) ctrlSetStructuredText parseText _sigTxt;

// --- Viseur du visage (9577) : sujet, cadrage, qualité estimée, dernier cliché ---
private _ft = _face getOrDefault ["target", objNull];
if (isNull _ft) then { _ft = _bt; };
private _faceLines = [];
private _faceQ = -1;
if (isNull _ft) then {
    _faceLines pushBack format ["<t size='0.5' font='TahomaB' color='%1'>Aucun sujet.</t><br/><t size='0.42' color='%2'>Visez la personne (4 m) puis rouvrez le SEEK.</t>", _cInk, _cMute];
} else {
    private _who = trim format ["%1 %2", _ft getVariable ["COMSPEC_SSE_FirstName", ""], _ft getVariable ["COMSPEC_SSE_LastName", ""]];
    if (_who isEqualTo "") then { _who = "Sujet non identifié"; };
    private _info = _face getOrDefault ["info", createHashMap];
    private _angleTxt = switch (_face getOrDefault ["angle", "face"]) do {
        case "trois_quarts": { "Trois quarts" };
        case "profil": { "Profil" };
        default { "Face" };
    };
    private _dist = player distance _ft;
    _faceLines pushBack format ["<t size='0.5' font='TahomaB' color='%2'>%1</t>", _who, _cInk];
    _faceLines pushBack format [
        "<t size='0.42' color='%5'>%1  ·  zoom x%2  ·  %3 m%4</t>",
        _angleTxt, _face getOrDefault ["zoom", 1], _dist toFixed 1,
        ["", "  ·  au sol"] select ((_info getOrDefault ["couche", ""]) isEqualTo "sol"),
        _cSub
    ];
    if (_dist > 4) then {
        _faceLines pushBack format ["<t size='0.42' color='%1'>Trop loin : approchez-vous à 4 m au plus.</t>", _cWarn];
    } else {
        private _q = _info getOrDefault ["qualite", -1];
        if (_q >= 0) then {
            _faceQ = _q;
            private _qc = [_cBad, _cWarn, _cOk] select ((parseNumber (_q >= 55)) + (parseNumber (_q >= 80)));
            private _note = _info getOrDefault ["note", ""];
            _faceLines pushBack format [
                "<t size='0.42' color='%1'>Qualité estimée %2</t>%3",
                _qc, _q, ["", format [" <t size='0.4' color='%2'>· %1</t>", _note, _cMute]] select (_note isNotEqualTo "")
            ];
        };
    };
    private _meta = _ft getVariable ["comspec_sse_facePhotoMeta", []];
    if ((_meta isEqualType []) && {(count _meta) >= 3}) then {
        _faceLines pushBack format [
            "<t size='0.42' color='%3'>Cliché de %1 · qualité %2</t>",
            _meta select 2, _meta select 1, _cOk
        ];
    };
};
private _faceCtrl = _disp displayCtrl 9577;
if (!isNull _faceCtrl) then { _faceCtrl ctrlSetStructuredText parseText (_faceLines joinString "<br/>"); };

// --- Lecture du viseur (9525) : état de la capture et qualité ---
private _photo = uiNamespace getVariable ["COMSPEC_SsePerson_PhotoPending", false];
private _mode = switch (true) do {
    case (_face getOrDefault ["frozen", false]): { "<t color='#9fd0ff'>FIGÉ</t>" };
    case (!isNull (_face getOrDefault ["cam", objNull])): { "<t color='#7dff9a'>LIVE</t>" };
    default { "<t color='#b0b4b8'>VEILLE</t>" };
};
private _qTxt = if (_faceQ >= 0) then {
    format ["<t color='%1'>Q %2</t>", ["#ff8a7a", "#ffd166", "#7dff9a"] select ((parseNumber (_faceQ >= 55)) + (parseNumber (_faceQ >= 80))), _faceQ]
} else { "<t color='#b0b4b8'>Q --</t>" };
(_disp displayCtrl 9525) ctrlSetStructuredText parseText format [
    "<t size='0.46' font='TahomaB' align='center' color='#ffffff'>%1   %2%3</t>",
    _mode, _qTxt, ["", "   <t color='#ffd166'>ARMÉ</t>"] select _photo
];

// --- Barre de titre (9526) : batterie, heure ---
// Batterie simulée : décroît avec la durée de mission, jamais sous 12 %.
private _bat = (97 - floor (time / 420)) max 12;
(_disp displayCtrl 9526) ctrlSetStructuredText parseText format [
    "<t size='0.48' align='right' color='%1'>BAT %2%3</t><t size='0.48' align='right' color='#ffffff'>   %4</t>",
    ["#ffd166", "#ffffff"] select (_bat > 25), _bat, "%", _clock
];

// --- Barre d'état (9600) : liaison, synchro, dossier, compteur, file d'attente ---
if (_online) then { missionNamespace setVariable ["COMSPEC_SseLastSync", _clock]; };
private _lastSync = missionNamespace getVariable ["COMSPEC_SseLastSync", ""];
if (!(_lastSync isEqualType "") || {_lastSync isEqualTo ""}) then { _lastSync = "--:--"; };
private _linkTxt = switch (_link) do {
    case "linked": { format ["<t font='TahomaB' color='%1'>EN LIGNE</t><t color='%2'> · sync %3</t>", _cOk, _cSub, _lastSync] };
    case "degraded": { format ["<t font='TahomaB' color='%1'>DÉGRADÉE</t><t color='%2'> · Local DB · last sync %3</t>", _cWarn, _cSub, _lastSync] };
    default { format ["<t font='TahomaB' color='%1'>HORS LIAISON</t><t color='%2'> · Local DB · last sync %3</t>", _cBad, _cSub, _lastSync] };
};
private _case = ["get"] call comspec_overwatch_connect_fnc_sseActiveCase;
private _caseTxt = if (_case isEqualTo "") then {
    format ["<t color='%1'>Sans dossier</t>", _cMute]
} else {
    format ["<t font='TahomaB' color='%1'>%2</t>", _cInk, _case]
};
private _sent = missionNamespace getVariable ["COMSPEC_SsePerson_SentKinds", createHashMap];
private _nRec = if (_sent isEqualType createHashMap) then { count _sent } else { 0 };
private _recTxt = str (1000 + _nRec);
_recTxt = _recTxt select [1, 3];
private _outbox = ["get"] call comspec_overwatch_connect_fnc_outboxState;
private _pending = _outbox getOrDefault ["count", 0];
private _bits = [_linkTxt, _caseTxt, format ["<t color='%1'>REC %2</t>", _cSub, _recTxt], format ["<t color='%1'>Éch. %2</t>", _cSub, count _samples]];
if (_queryPending) then { _bits pushBack format ["<t font='TahomaB' color='%1'>QUERY…</t>", _cBlue]; };
if (_pending > 0) then { _bits pushBack format ["<t font='TahomaB' color='%1'>%2 en att.</t>", _cWarn, _pending]; };
_bits pushBack (["<t color='#5a6670'>Non signé</t>", format ["<t color='%1'>Signé</t>", _cOk]] select _signed);
(_disp displayCtrl 9600) ctrlSetStructuredText parseText format [
    "<t size='0.4' color='%1'>%2</t>",
    _cSub,
    _bits joinString "<t color='#8a9096'>  |  </t>"
];
