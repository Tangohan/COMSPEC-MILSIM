/*
    Analyse d'un document pour le Zeus (fenêtre « Papiers de l'IA », fn_idZeusPapers). Fonction PURE : ni registre, ni
    réseau, ni date du jeu ; tout ce qu'elle lit lui est passé. Calculée sur le serveur (seul à connaître la vérité),
    rendue sur le poste du Zeus (textes en clés de stringtable, traduits là-bas).
    [_doc, _today, _extra] call comspec_sse_fnc_idZeusAnalysis -> HashMap
      _doc    document du registre (fn_idIssue : imprimé + vérité : trueLast, trueFirst, kind, quality, forgeryKind, flaws,
              chip, chipExpected, chipNote, report, expires…)
      _today  [année, mois, jour] (date du jeu, lue par l'appelant)
      _extra  HashMap facultative : base ([] numéro inconnu du pays · [docId, nom, prénom, signalement] fiche du pays ·
              absente : non vérifié), hasMrz (le type porte une bande MRZ ; défaut vrai)
    Résultat (toutes les phrases : [clé, [arguments]] ; un argument « STR_… » est lui-même traduit au rendu) :
      kind, kindKey · forgery, forgeryKey · verdict [clé, args] · state ok | warn | bad
      names [nom imprimé, prénom imprimé, vrai nom, vrai prénom, différent]
      expired, expires, issued · quality, qualityLines [[clé, args]…] · flaws [[code, titre, sens]…]
      checks [[clé, args, état]…] : ce que trouvera chaque niveau de contrôle (N0 à N4)
    Seuils des défauts : les mêmes que fn_idIssue (vérifiés par tests/Unit/IdZeusPapersAssetTest.php).
*/
params [["_doc", createHashMap, [createHashMap]], ["_today", [2035, 1, 1], [[]]], ["_extra", createHashMap, [createHashMap]]];
private _p = "STR_COMSPEC_SSE_ZP_";
private _out = createHashMap;
private _str = { if (_this isEqualType "") then { _this } else { str _this } };

// Défauts posés par fn_idIssue, procédé par procédé : [défaut, qualité qu'il faut atteindre pour l'éviter].
private _rules = createHashMapFromArray [
    ["counterfeit", [["holo", 70], ["font", 60], ["mrz", 45]]],
    ["altered", [["font", 75], ["mrzdata", 60]]],
    ["photo_swap", [["photo", 85]]],
    ["stolen_blank", [["font", 50]]]
];
// Défauts connus : titre et sens (clés). Un code inconnu (extension, version future) garde un libellé générique.
private _known = ["holo", "font", "mrz", "mrzdata", "photo"];
private _flawRow = {
    params ["_code"];
    if (_code in _known) exitWith { [_code, [_p + "F_" + _code + "_T", []], [_p + "F_" + _code + "_D", []]] };
    [_code, [_p + "F_other_T", [_code]], [_p + "F_other_D", []]]
};

if ((count _doc) isEqualTo 0) exitWith {
    _out set ["kind", ""];
    _out set ["state", "warn"];
    _out set ["verdict", [_p + "V_blank", []]];
    _out set ["names", ["", "", "", "", false]];
    _out set ["flaws", []];
    _out set ["checks", []];
    _out set ["qualityLines", []];
    _out
};

private _kind = (_doc getOrDefault ["kind", "civil"]) call _str;
if !(_kind in ["civil", "generated", "alias", "cover", "forged"]) then { _kind = "civil"; };
private _fk = (_doc getOrDefault ["forgeryKind", ""]) call _str;
private _q = _doc getOrDefault ["quality", 60];
if !(_q isEqualType 0) then { _q = 60; };
_q = round ((_q max 0) min 100);
private _flaws = _doc getOrDefault ["flaws", []];
if !(_flaws isEqualType []) then { _flaws = []; };

// --- Noms : imprimé contre vrai ------------------------------------------------------------------------------------
private _pl = (_doc getOrDefault ["last", ""]) call _str;
private _pf = (_doc getOrDefault ["first", ""]) call _str;
private _tl = (_doc getOrDefault ["trueLast", _pl]) call _str;
private _tf = (_doc getOrDefault ["trueFirst", _pf]) call _str;
if (_tl isEqualTo "") then { _tl = _pl; };
if (_tf isEqualTo "") then { _tf = _pf; };
private _differs = (toUpper _pl) isNotEqualTo (toUpper _tl) || {(toUpper _pf) isNotEqualTo (toUpper _tf)};
_out set ["names", [_pl, _pf, _tl, _tf, _differs]];

// --- Dates : même calcul que fn_idEvaluate (jours approchés, AAAA-MM-JJ) --------------------------------------------
private _days = { private _d = (_this splitString "-") apply { parseNumber _x }; ((_d param [0, 9999]) * 372) + ((_d param [1, 12]) * 31) + (_d param [2, 31]) };
_today params [["_yy", 2035], ["_mm", 1], ["_dd", 1]];
private _expires = (_doc getOrDefault ["expires", ""]) call _str;
private _expired = _expires isNotEqualTo "" && {(_expires call _days) < (_yy * 372 + _mm * 31 + _dd)};
_out set ["expired", _expired];
_out set ["expires", _expires];
_out set ["issued", (_doc getOrDefault ["issued", ""]) call _str];

// --- Nature et procédé --------------------------------------------------------------------------------------------
_out set ["kind", _kind];
_out set ["kindKey", _p + "Kind_" + _kind];
_out set ["forgery", _fk];
_out set ["forgeryKey", ["", _p + "Fk_" + _fk] select (_fk in keys _rules)];
_out set ["quality", _q];

// --- Défauts (chacun avec son sens) ------------------------------------------------------------------------------
_out set ["flaws", _flaws apply { [_x call _str] call _flawRow }];

// --- Qualité : ce que le score donne, en clair ---------------------------------------------------------------------
private _ql = [];
switch (true) do {
    case (_kind isEqualTo "cover"): { _ql pushBack [_p + "Q_cover", [_q]]; };
    case (_kind isNotEqualTo "forged"): { _ql pushBack [_p + "Q_na", [_q]]; };
    default {
        _ql pushBack [_p + "Q_head", [_q, [_p + "Fk_" + _fk, _p + "Fk_none"] select (_fk isEqualTo "")]];
        {
            _x params ["_code", "_need"];
            private _t = _p + "F_" + _code + "_T";
            if (_code in _flaws) then { _ql pushBack [_p + "Q_missing", [_t, _need]]; } else { _ql pushBack [_p + "Q_avoided", [_t, _need]]; };
        } forEach (_rules getOrDefault [_fk, []]);
        // Visibilité à l'œil : fn_idEvaluate multiplie la chance de voir un défaut par 1,25 - qualité / 100.
        _ql pushBack [_p + "Q_eye", [((1.25 - _q / 100) max 0) toFixed 2]];
        if ((_rules getOrDefault [_fk, []]) isEqualTo []) then { _ql pushBack [_p + "Q_free", []]; };
    };
};
_out set ["qualityLines", _ql];

// --- Ce que trouvera chaque niveau de contrôle ----------------------------------------------------------------------
private _checks = [];
// N0 : défauts visibles à l'œil (selon la lumière et le contrôleur), date.
private _eye = _flaws select { _x in ["holo", "font", "photo"] };
if (_eye isEqualTo []) then { _checks pushBack [_p + "N0_none", [], "ok"]; } else {
    _checks pushBack [_p + "N0_flaws", [(_eye apply { _p + "F_" + _x + "_T" })], "warn"];
};
if (_expired) then { _checks pushBack [_p + "N0_expired", [_expires], "warn"]; };
// N1 : bande MRZ.
private _hasMrz = _extra getOrDefault ["hasMrz", true];
switch (true) do {
    case (_hasMrz isNotEqualTo true): { _checks pushBack [_p + "N1_na", [], "ok"]; };
    case ("mrz" in _flaws): { _checks pushBack [_p + "N1_bad", [], "bad"]; };
    case ("mrzdata" in _flaws): { _checks pushBack [_p + "N1_name", [], "bad"]; };
    default { _checks pushBack [_p + "N1_ok", [], "ok"]; };
};
// N2 : puce.
if ((_doc getOrDefault ["chipExpected", false]) isNotEqualTo true) then { _checks pushBack [_p + "N2_na", [], "ok"]; } else {
    switch ((_doc getOrDefault ["chip", "ok"]) call _str) do {
        case "absent": { _checks pushBack [_p + "N2_absent", [], "warn"]; };
        case "defect": { _checks pushBack [_p + "N2_defect", [], "warn"]; };
        case "mismatch": { _checks pushBack [[_p + "N2_mismatch", _p + "N2_photo"] select ((_doc getOrDefault ["chipNote", ""]) isEqualTo "photo"), [], "bad"]; };
        default { _checks pushBack [_p + "N2_ok", [], "ok"]; };
    };
};
// N3 : base du pays (fiche passée par l'appelant), signalement.
private _base = _extra getOrDefault ["base", -1];
private _rep = (_doc getOrDefault ["report", ""]) call _str;
if (_base isEqualType []) then {
    if (_base isEqualTo []) then { _checks pushBack [_p + "N3_unknown", [], "bad"]; } else {
        _base params [["_bid", ""], ["_bl", ""], ["_bf", ""], ["_brep", ""]];
        if ((_brep call _str) isNotEqualTo "") then { _rep = _brep call _str; };
        if ((_bl call _str) isNotEqualTo "" && {(toUpper (_bl call _str)) isNotEqualTo (toUpper _pl) || {(toUpper (_bf call _str)) isNotEqualTo (toUpper _pf)}}) then {
            _checks pushBack [_p + "N3_other", [format ["%1 %2", _bf, toUpper (_bl call _str)]], "bad"];
        } else {
            _checks pushBack [_p + "N3_ok", [], "ok"];
        };
    };
};
switch (_rep) do {
    case "vole": { _checks pushBack [_p + "N3_vole", [], "bad"]; };
    case "perdu": { _checks pushBack [_p + "N3_perdu", [], "warn"]; };
    case "revoque": { _checks pushBack [_p + "N3_revoque", [], "bad"]; };
};
_out set ["report", _rep];
// N4 : visage (biométrie) : seule la vraie personne correspond à son fichier.
if (_differs) then {
    _checks pushBack [_p + "N4_bad", [format ["%1 %2", _pf, toUpper _pl], format ["%1 %2", _tf, toUpper _tl]], "bad"];
} else {
    _checks pushBack [_p + "N4_ok", [], "ok"];
};
_out set ["checks", _checks];

// --- Verdict en une phrase ---------------------------------------------------------------------------------------
private _verdict = switch (_kind) do {
    case "forged": { [_p + "V_forged", [[_p + "Fk_" + _fk, _p + "Fk_none"] select (_fk isEqualTo ""), _q, count _flaws]] };
    case "cover": { [_p + "V_cover", []] };
    default { [[_p + "V_true", _p + "V_otherName"] select _differs, []] };
};
_out set ["verdict", _verdict];
private _bad = (_checks findIf { (_x select 2) isEqualTo "bad" }) >= 0;
private _warn = (_checks findIf { (_x select 2) isEqualTo "warn" }) >= 0;
_out set ["state", ["ok", ["warn", "bad"] select _bad] select (_bad || {_warn} || {_kind in ["forged", "cover", "alias"]})];
_out
