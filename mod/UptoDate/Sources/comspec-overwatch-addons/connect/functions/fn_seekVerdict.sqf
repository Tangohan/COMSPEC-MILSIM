/*
    Verdict du SEEK II après QUERY : CONCORDANT / DISCORDANT / INCONNU / SIGNALÉ.

    [_bio, _file, _doc, _target] call comspec_overwatch_connect_fnc_seekVerdict
      _bio   résultat du moteur biométrique (comspec_sse_fnc_bioSearch) : verdict match | possible |
             latent | insufficient | none, name, tier, mismatch, ref, score, action
      _file  résultat des fichiers (comspec_sse_fileResult) : hit, codes, records, online (vide si aucun)
      _doc   document présenté (last, first, dob, number, country, type), vide si aucun
    Returns: HashMap
      verdict  "concordant" | "discordant" | "inconnu" | "signale"
      title, sub (texte d'en-tête), color ([r,g,b,a] du bandeau)
      lines    lignes en texte structuré (déjà échappées, déjà traduites)
      plain    1 à 4 phrases en clair (FR) de ce qui concorde ou non
      name, dob, ref (identité retenue au fichier), codes, enroll (bool : enrôlement conseillé)
*/
params [
    ["_bio", createHashMap, [createHashMap]],
    ["_file", createHashMap, [createHashMap]],
    ["_doc", createHashMap, [createHashMap]],
    ["_target", objNull, [objNull]]
];

private _tr = { _this call comspec_overwatch_connect_fnc_tr };
// Texte venu des données : jamais de balise involontaire dans le texte structuré.
private _esc = {
    params [["_s", ""]];
    if (!(_s isEqualType "")) then { _s = str _s; };
    private _o = "";
    {
        _o = _o + (switch (_x) do { case 38: { "&amp;" }; case 60: { "&lt;" }; case 62: { "&gt;" }; default { toString [_x] }; });
    } forEach (toArray _s);
    _o
};
private _norm = {
    params [["_s", ""]];
    if (!(_s isEqualType "")) exitWith { "" };
    ((toLower _s) splitString " -'.,") joinString " "
};

private _cOk = "#1a7f37";
private _cWarn = "#9a5b00";
private _cBad = "#b3261e";
private _cMute = "#5a6670";
private _cInk = "#14181d";

private _bv = _bio getOrDefault ["verdict", "none"];
private _tier = _bio getOrDefault ["tier", 0];
if (!(_tier isEqualType 0)) then { _tier = 0; };
private _codes = _file getOrDefault ["codes", []];
if (!(_codes isEqualType [])) then { _codes = []; };
_codes = (_codes select { _x isEqualType "" }) apply { toLower _x };
private _records = _file getOrDefault ["records", []];
if (!(_records isEqualType [])) then { _records = []; };
_records = _records select { _x isEqualType createHashMap };

// Identité retenue au fichier : fiche personne ou biométrique d'abord, sinon le nom du dossier biométrique.
private _recP = createHashMap;
{ if ((_x getOrDefault ["kind", ""]) in ["person", "bio"]) exitWith { _recP = _x; }; } forEach _records;
private _recD = createHashMap;
{ if ((_x getOrDefault ["kind", ""]) isEqualTo "document") exitWith { _recD = _x; }; } forEach _records;
private _fLast = _recP getOrDefault ["last", ""];
private _fFirst = _recP getOrDefault ["first", ""];
private _fDob = _recP getOrDefault ["dob", ""];
private _fName = trim format ["%1 %2", _fFirst, _fLast];
if (_fName isEqualTo "") then { _fName = _bio getOrDefault ["name", ""]; };
private _ref = _recP getOrDefault ["ref", ""];
if (_ref isEqualTo "") then { _ref = _bio getOrDefault ["ref", ""]; };

private _bioKnown = _bv in ["match", "possible"];
private _fileKnown = (_file getOrDefault ["hit", false]) isEqualTo true && {(count _recP) > 0};
private _known = _bioKnown || _fileKnown;

// --- Document présenté face au fichier -------------------------------------------------
private _hasDoc = (count _doc) > 0 && {((_doc getOrDefault ["last", ""]) isNotEqualTo "") || {(_doc getOrDefault ["first", ""]) isNotEqualTo ""}};
private _dLast = _doc getOrDefault ["last", ""];
private _dFirst = _doc getOrDefault ["first", ""];
private _dDob = _doc getOrDefault ["dob", ""];
private _dNum = _doc getOrDefault ["number", ""];
private _nameOk = true;
private _dobOk = true;
private _numOk = true;
private _cmp = [];
if (_hasDoc && {_known}) then {
    if (_fLast isNotEqualTo "" || {_fFirst isNotEqualTo ""}) then {
        _nameOk = ([_dLast] call _norm) isEqualTo ([_fLast] call _norm)
            && {_dFirst isEqualTo "" || {_fFirst isEqualTo ""} || {([_dFirst] call _norm) isEqualTo ([_fFirst] call _norm)}};
    } else {
        // Seul le nom complet du dossier biométrique est connu : le nom de famille doit y figurer.
        private _full = [_fName] call _norm;
        _nameOk = _full isEqualTo "" || {(_full find ([_dLast] call _norm)) >= 0};
    };
    _dobOk = _dDob isEqualTo "" || {_fDob isEqualTo ""} || {_dDob isEqualTo _fDob};
    private _rNum = _recD getOrDefault ["number", ""];
    _numOk = _dNum isEqualTo "" || {_rNum isEqualTo ""} || {(toUpper _dNum) isEqualTo (toUpper _rNum)};
    private _row = {
        params ["_label", "_ok", "_seen"];
        format ["<t size='0.42' color='%1'>%2 </t><t size='0.42' font='TahomaB' color='%3'>%4</t>%5",
            _cMute, _label, [_cBad, _cOk] select _ok, ["DIFFÈRE" call _tr, "CONCORDE" call _tr] select _ok,
            if (_ok) then { "" } else { format ["<t size='0.40' color='%1'>  (%2 : %3)</t>", _cMute, "fichier" call _tr, [_seen] call _esc] }]
    };
    _cmp pushBack (["Nom" call _tr, _nameOk, _fName] call _row);
    if (_dDob isNotEqualTo "" && {_fDob isNotEqualTo ""}) then { _cmp pushBack (["Naissance" call _tr, _dobOk, _fDob] call _row); };
    if (_dNum isNotEqualTo "" && {(_recD getOrDefault ["number", ""]) isNotEqualTo ""}) then { _cmp pushBack (["N° document" call _tr, _numOk, _recD get "number"] call _row); };
};

private _graveSet = ["wanted", "warrant", "flagged", "hvt", "bolo", "armed_dangerous", "stolen"];
private _docBadSet = ["holder_mismatch", "revoked", "lost"];
private _grave = _codes select { _x in _graveSet };
private _docBad = _codes select { _x in _docBadSet };
private _mismatch = (_bio getOrDefault ["mismatch", false]) isEqualTo true || {!_nameOk} || {!_dobOk} || {!_numOk};

private _verdict = switch (true) do {
    case (_grave isNotEqualTo [] || {_bv isEqualTo "match" && {_tier > 0}}): { "signale" };
    case (!_known): { "inconnu" };
    case (_mismatch || {_docBad isNotEqualTo []}): { "discordant" };
    default { "concordant" };
};

private _labels = createHashMapFromArray [
    ["wanted", "recherché"], ["warrant", "mandat"], ["flagged", "signalé"], ["hvt", "cible prioritaire (HVT)"],
    ["bolo", "avis de recherche (BOLO)"], ["stolen", "document volé"], ["lost", "document perdu"],
    ["revoked", "document révoqué"], ["expired", "document expiré"], ["missing", "personne disparue"],
    ["armed_dangerous", "armé et dangereux"], ["caution", "prudence"], ["holder_mismatch", "porteur différent du titulaire"]
];
private _codeTxt = (_codes apply { (_labels getOrDefault [_x, _x]) call _tr }) joinString ", ";

private _title = "";
private _sub = "";
private _color = [0.55, 0.56, 0.58, 1];
switch (_verdict) do {
    case "signale": {
        _title = "SIGNALÉ" call _tr;
        _sub = _bio getOrDefault ["action", ""];
        if (_sub isEqualTo "") then { _sub = "Retenir le sujet et aviser le PC" call _tr; };
        _color = [0.72, 0.07, 0.06, 1];
    };
    case "discordant": {
        _title = "DISCORDANT" call _tr;
        _sub = "Identité présentée différente du fichier : vérifier, questionner" call _tr;
        _color = [0.85, 0.45, 0.04, 1];
    };
    case "concordant": {
        _title = "CONCORDANT" call _tr;
        _sub = if (_bv isEqualTo "possible") then { "Correspondance possible : à confirmer par le PC" call _tr } else { "Identité confirmée par le fichier" call _tr };
        _color = [0.10, 0.52, 0.20, 1];
    };
    default {
        _title = "INCONNU" call _tr;
        _sub = if (_bv isEqualTo "insufficient") then { "Relevés insuffisants : relancez le contrôle" call _tr } else { "Inconnu des fichiers : ENROLL (A2) puis SIGN" call _tr };
        _color = [0.20, 0.30, 0.45, 1];
    };
};

private _lines = [];
private _plain = [];
if (_known) then {
    _lines pushBack format ["<t size='0.44' color='%1'>%2 </t><t size='0.44' font='TahomaB' color='%3'>%4</t><t size='0.42' color='%1'>%5%6</t>",
        _cMute, "Fichier :" call _tr, _cInk, [[_fName, "nom non renseigné" call _tr] select (_fName isEqualTo "")] call _esc,
        if (_fDob isEqualTo "") then { "" } else { format ["  ·  %1 %2", "né(e) le" call _tr, [_fDob] call _esc] },
        if (_ref isEqualTo "") then { "" } else { format ["  ·  %1", [_ref] call _esc] }];
    _plain pushBack format ["Fichier : %1%2.", [_fName, "nom non renseigné"] select (_fName isEqualTo ""), ["", format [" (%1)", _ref]] select (_ref isNotEqualTo "")];
} else {
    _lines pushBack format ["<t size='0.44' color='%1'>%2</t>", _cMute, "Aucune fiche : sujet inconnu des fichiers consultés." call _tr];
    _plain pushBack "Biométrie inconnue des fichiers.";
};
if (_hasDoc) then {
    _lines pushBack format ["<t size='0.42' color='%1'>%2 %3</t>", _cMute, "Document présenté :" call _tr,
        [trim format ["%1 %2 · %3 %4 · n° %5", _dFirst, _dLast, _doc getOrDefault ["type", ""], _doc getOrDefault ["country", ""], _dNum]] call _esc];
    _lines append _cmp;
    if (_known) then {
        _plain pushBack (if (_nameOk && _dobOk && _numOk) then {
            format ["Document de %1 %2 : concorde avec le fichier.", _dFirst, _dLast]
        } else {
            format ["Document de %1 %2 : %3 diffère du fichier.", _dFirst, _dLast,
                ((["", "nom"] select !_nameOk) + (["", " naissance"] select !_dobOk) + (["", " numéro"] select !_numOk)) call { trim _this }]
        });
    };
} else {
    _lines pushBack format ["<t size='0.42' color='%1'>%2</t>", _cMute, "Aucun document présenté (contrôle d'identité)." call _tr];
};
if ((_bio getOrDefault ["mismatch", false]) isEqualTo true) then {
    _lines pushBack format ["<t size='0.42' font='TahomaB' color='%1'>%2</t>", _cBad, "Identité déclarée ≠ dossier biométrique (faux papiers ?)" call _tr];
    _plain pushBack "Identité déclarée différente du dossier biométrique.";
};
if (_codes isNotEqualTo []) then {
    _lines pushBack format ["<t size='0.42' font='TahomaB' color='%1'>%2 %3</t>", [_cWarn, _cBad] select (_grave isNotEqualTo []), "Mentions :" call _tr, [_codeTxt] call _esc];
    _plain pushBack format ["Mentions : %1.", (_codes apply { _labels getOrDefault [_x, _x] }) joinString ", "];
};
if ((count _file) == 0) then {
    _lines pushBack format ["<t size='0.40' color='%1'>%2</t>", _cMute, "Fichiers non consultés : biométrie seule." call _tr];
} else {
    if !((_file getOrDefault ["online", true]) isEqualTo true) then {
        _lines pushBack format ["<t size='0.40' color='%1'>%2</t>", _cMute, "Hors ligne : fichier local seulement." call _tr];
    };
};

createHashMapFromArray [
    ["verdict", _verdict], ["title", _title], ["sub", _sub], ["color", _color],
    ["lines", _lines], ["plain", _plain select [0, 4]], ["name", _fName], ["dob", _fDob], ["ref", _ref],
    ["codes", _codes], ["enroll", _verdict isEqualTo "inconnu"]
]
