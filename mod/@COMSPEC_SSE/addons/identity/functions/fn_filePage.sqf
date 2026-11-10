/*
    App « Fichiers » du téléphone ATAK (page FICHIERS, déclarée dans COMSPEC_ATAK_Apps du SSE) : consultation des
    fichiers, jamais une vérification (le SEEK II vérifie la personne, l'app Contrôle ID le document en main).
      REQUÊTE   QW personne (nom, prénom, naissance) · QA document (numéro, pays) · QB biométrie (identifiant SEEK II,
                REC-…) · QV véhicule (plaque) ; « Interroger le fichier » : animation du terminal, puis réponse
      RÉPONSE   HIT / NO HIT, n° de requête, heure, source (fichier local de la mission, Athena si la liaison est là),
                mentions (ARMÉ ET DANGEREUX, mandat, recherché, disparu, volé…), fiches résumées, contrôles subis
      JOURNAL   consultations de la partie (téléphone, SEEK II, contrôle d'identité) ; un clic rouvre la réponse
    Lien profond (comspec_sse_fnc_openApp « FICHIERS ») : type, critères, source, run (recherche lancée tout de suite).
    Actions : comspec_sse_fnc_fileAction.
*/
params [["_page", "FICHIERS"], ["_rect", []]];
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
if ((count _rect) isEqualTo 4) then { _bw = _rect select 2; _bh = _rect select 3; };
private _st = uiNamespace getVariable ["comspec_sse_fileApp", createHashMap];
uiNamespace setVariable ["comspec_sse_fileApp", _st];
private _tr = { _this call comspec_sse_fnc_tr };
private _esc = { params ["_t"]; if !(_t isEqualType "") then { _t = str _t; }; { _t = [_t, _x select 0, _x select 1] call CBA_fnc_replace; } forEach [["&", "&amp;"], ["<", "&lt;"], [">", "&gt;"]]; _t };
private _act = { params ["_a", ["_arg", ""]]; compile format ["['%1', %2] call comspec_sse_fnc_fileAction;", _a, str _arg] };
private _icon = "\z\comspec_atak_native\addons\main\data\app_intel.paa";
// Lien profond : critères posés, recherche lancée à l'image suivante (la page est déjà à l'écran).
private _dl = ["take", "FICHIERS"] call comspec_sse_fnc_openApp;
if ((count _dl) > 0) then {
    _st set ["type", toUpper (_dl getOrDefault ["type", "QA"])];
    private _f = createHashMap;
    { _f set [_x, _dl getOrDefault [_x, ""]]; } forEach ["last", "first", "dob", "number", "country", "docType", "bioKey", "rec", "plate"];
    _st set ["form", _f];
    _st set ["source", _dl getOrDefault ["source", "phone"]];
    _st set ["tab", "query"];
    if (_dl getOrDefault ["run", false]) then { [{ ["run", "deep"] call comspec_sse_fnc_fileAction; }] call CBA_fnc_execNextFrame; };
};
private _type = _st getOrDefault ["type", "QA"];
private _form = _st getOrDefault ["form", createHashMap];
private _tab = _st getOrDefault ["tab", "query"];
private _hist = _st getOrDefault ["history", []];
private _online = (missionNamespace getVariable ["comspec_sse_idDll", false]) isEqualTo true;
private _labels = createHashMapFromArray [
    ["armed_dangerous", ["ARMÉ ET DANGEREUX", "red"]], ["hvt", ["HVT : cible prioritaire", "red"]], ["warrant", ["Mandat", "red"]],
    ["wanted", ["Recherché", "red"]], ["missing", ["Personne disparue", "amber"]], ["stolen", ["Document volé", "red"]],
    ["revoked", ["Document révoqué", "red"]], ["bolo", ["BOLO", "amber"]], ["lost", ["Document perdu", "amber"]],
    ["flagged", ["Signalé", "amber"]], ["holder_mismatch", ["Titulaire différent", "amber"]], ["enemy_force", ["Force ennemie", "amber"]],
    ["caution", ["Prudence", "amber"]], ["expired", ["Document expiré", "amber"]], ["homonym", ["Homonyme possible", "amber"]],
    ["unknown", ["Inconnu au fichier", "grey"]], ["no_vehicle_file", ["Aucun fichier véhicule", "grey"]]
];
private _rgba = createHashMapFromArray [["red", [1, 0.3, 0.3, 1]], ["amber", [0.95, 0.67, 0.2, 1]], ["grey", [0.55, 0.6, 0.65, 1]], ["green", [0.2, 0.83, 0.6, 1]]];
private _typeName = createHashMapFromArray [["QW", "Personne"], ["QA", "Document"], ["QB", "Biométrie"], ["QV", "Véhicule"]];
private _docName = { (["Carte d'identité", "Passeport", "Permis de conduire", "Carte militaire", "Document"] select (((["id_card", "passport", "driving_license", "military_id"] find _this)) call { if (_this < 0) then { 4 } else { _this } })) call _tr };

private _rows = [];
_rows pushBack ["strip",
    format ["<t font='EtelkaMonospaceProBold'>%1</t>", ("FICHIERS · SIMULATION" call _tr) call _esc],
    format ["<t font='EtelkaMonospacePro'>%1 · %2</t>", (["FICHIER LOCAL", "LOCAL + ATHENA"] select _online) call _tr, [dayTime, "HH:MM"] call BIS_fnc_timeToString],
    [0.071, 0.09, 0.114, 1]];
_rows pushBack ["segment", "", [
    ["REQUÊTE" call _tr, ["tab", "query"] call _act, _tab isEqualTo "query"],
    [format ["%1 (%2)", "JOURNAL" call _tr, count _hist], ["tab", "journal"] call _act, _tab isEqualTo "journal"]
]];

if (_tab isEqualTo "journal") exitWith {
    if (_hist isEqualTo []) then { _rows pushBack ["empty", _icon, "Aucune consultation" call _tr, "Les consultations de la partie (téléphone, SEEK II, contrôle d'identité) s'affichent ici." call _tr]; };
    for "_i" from ((count _hist) - 1) to 0 step -1 do {
        private _r = _hist select _i;
        private _c = (_r getOrDefault ["codes", []]) param [0, ""];
        private _col = [_rgba get "green", _rgba get ((_labels getOrDefault [_c, ["", "grey"]]) select 1)] select (_r getOrDefault ["hit", false]);
        _rows pushBack ["notif", _icon, _col,
            format ["%1 · %2 · %3", _r get "type", ["NO HIT", "HIT"] select (_r getOrDefault ["hit", false]), _r get "qrn"],
            _r getOrDefault ["at", ""],
            format ["%1 · %2%3", (_typeName getOrDefault [_r get "type", ""]) call _tr, ((values (_r getOrDefault ["query", createHashMap])) joinString " "),
                ["", " · " + (((_labels getOrDefault [_c, [_c]]) select 0) call _tr)] select (_c isNotEqualTo "")],
            ["show", _i] call _act, {}];
    };
    [_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
};

// --- Requête --------------------------------------------------------------------------------------------------------
_rows pushBack ["segment", "", [
    ["QW Personne" call _tr, ["type", "QW"] call _act, _type isEqualTo "QW", "Nom, prénom et date de naissance"],
    ["QA Document" call _tr, ["type", "QA"] call _act, _type isEqualTo "QA", "Numéro du document (pays facultatif)"],
    ["QB Biométrie" call _tr, ["type", "QB"] call _act, _type isEqualTo "QB", "Identifiant SEEK II (REC-…) ou clé biométrique"],
    ["QV Véhicule" call _tr, ["type", "QV"] call _act, _type isEqualTo "QV", "Plaque d'immatriculation"]
]];
switch (_type) do {
    case "QW": {
        _rows pushBack ["edit", "fq_last", "Nom" call _tr, _form getOrDefault ["last", ""]];
        _rows pushBack ["edit", "fq_first", "Prénom" call _tr, _form getOrDefault ["first", ""]];
        _rows pushBack ["edit", "fq_dob", "Né(e) le (AAAA-MM-JJ)" call _tr, _form getOrDefault ["dob", ""]];
    };
    case "QB": { _rows pushBack ["edit", "fq_bio", "Identifiant SEEK II" call _tr, [_form getOrDefault ["bioKey", ""], _form getOrDefault ["rec", ""]] select ((_form getOrDefault ["rec", ""]) isNotEqualTo "")]; };
    case "QV": { _rows pushBack ["edit", "fq_plate", "Plaque" call _tr, _form getOrDefault ["plate", ""]]; };
    default {
        _rows pushBack ["edit", "fq_number", "Numéro du document" call _tr, _form getOrDefault ["number", ""]];
        _rows pushBack ["edit", "fq_country", "Pays (code, facultatif)" call _tr, _form getOrDefault ["country", ""]];
    };
};
private _scan = ["text", ["files"]] call comspec_sse_fnc_scanAnim;
private _pending = _st getOrDefault ["pending", ""];
call {
    if (_scan isNotEqualTo "") exitWith { _rows pushBack ["text", _scan, [0.05, 0.08, 0.11, 1], "comspec_sse_fileScanCtrl"]; };
    if (_pending isNotEqualTo "") exitWith { _rows pushBack ["text", format ["<t color='#8fd3ff'>%1</t>", ("Réponse du fichier en attente…" call _tr) call _esc], [0.05, 0.08, 0.11, 1]]; };
    _rows pushBack ["big", [["Interroger le fichier" call _tr, ["run"] call _act, "green", true, "Consultation : aucune vérification sur la personne."]]];
};

// --- Réponse ----------------------------------------------------------------------------------------------------------
private _vi = _st getOrDefault ["view", -1];
private _res = _hist param [_vi, createHashMap];
if ((count _res) > 0 && {_pending isEqualTo ""} && {_scan isEqualTo ""}) then {
    private _hit = _res getOrDefault ["hit", false];
    private _codes = _res getOrDefault ["codes", []];
    private _grave = _codes select { ((_labels getOrDefault [_x, ["", "grey"]]) select 1) isEqualTo "red" };
    private _ath = _res getOrDefault ["athena", createHashMap];
    _rows pushBack ["banner", format ["<t color='%1'>%2</t>", ["#c9d3dd", ["#f2ab33", "#ff5a5a"] select (_grave isNotEqualTo [])] select _hit,
        [[(["NO HIT : AUCUNE FICHE" call _tr, "NO HIT"] select (_codes isEqualTo [])), format ["HIT · %1", count (_res getOrDefault ["records", []])]] select _hit] call _esc],
        [[0.09, 0.11, 0.14, 1], [[0.18, 0.12, 0.03, 1], [0.3, 0.02, 0.02, 1]] select (_grave isNotEqualTo [])] select _hit];
    _rows pushBack ["strip",
        format ["<t font='EtelkaMonospacePro' size='0.85'>%1 · %2 %3</t>", _res get "qrn", _res get "type", (_typeName getOrDefault [_res get "type", ""]) call _tr],
        format ["<t font='EtelkaMonospacePro' size='0.85'>%1 · %2</t>", _res getOrDefault ["at", ""],
            [("FICHIER LOCAL" call _tr), format ["ATHENA %1", _ath getOrDefault ["qrn", ""]]] select (_res getOrDefault ["online", false])],
        [0.06, 0.07, 0.09, 1]];
    if (_codes isNotEqualTo [] && {_hit}) then {
        _rows pushBack ["chips", _codes apply { (_labels getOrDefault [_x, [_x, "grey"]]) params ["_lt", "_lc"]; [_lt call _tr, _rgba get _lc, {}] }];
    };
    if ("armed_dangerous" in _codes) then {
        _rows pushBack ["text", format ["<t color='#ff5a5a' font='COMSPEC_BarlowBold'>%1</t>", ("Prudence : personne signalée armée et dangereuse. Ne l'abordez pas seul." call _tr) call _esc], [0.22, 0.05, 0.05, 1]];
    };
    {
        private _r = _x;
        private _kind = _r getOrDefault ["kind", "document"];
        private _rc = (_r getOrDefault ["codes", []]) param [0, ""];
        private _col = _rgba get ([(_labels getOrDefault [_rc, ["", "grey"]]) select 1, "green"] select (_rc isEqualTo ""));
        private _name = trim format ["%1 %2", toUpper (_r getOrDefault ["last", ""]), _r getOrDefault ["first", ""]];
        if (_name isEqualTo "") then { _name = "Titulaire non renseigné" call _tr; };
        private _sub = [];
        if (_kind isEqualTo "document") then { _sub pushBack ((_r getOrDefault ["docType", ""]) call _docName); };
        if (_kind isEqualTo "bio") then { _sub pushBack ("Fiche biométrique" call _tr); };
        if (_kind isEqualTo "person") then { _sub pushBack ("Avis" call _tr); };
        { if ((_r getOrDefault [_x, ""]) isNotEqualTo "") then { _sub pushBack (_r get _x); }; } forEach ["country", "number"];
        if ((_r getOrDefault ["dob", ""]) isNotEqualTo "") then { _sub pushBack format ["%1 %2", "né(e) le" call _tr, _r get "dob"]; };
        if ((_r getOrDefault ["expires", ""]) isNotEqualTo "") then { _sub pushBack format ["%1 %2", "expire le" call _tr, _r get "expires"]; };
        private _m = (_r getOrDefault ["codes", []]) apply { ((_labels getOrDefault [_x, [_x]]) select 0) call _tr };
        _rows pushBack ["notif", _icon, _col, _name, _r getOrDefault ["ref", ""], (_sub joinString " · ") + (["", " · " + (_m joinString ", ")] select (_m isNotEqualTo [])), {}, {}];
        { _rows pushBack ["text", format ["<t size='0.85' color='#c9d3dd'>%1</t>", [_x call _tr] call _esc]]; } forEach ((_r getOrDefault ["lines", []]) select [0, 4]);
        private _ck = _r getOrDefault ["checks", []];
        if (_ck isNotEqualTo []) then {
            _rows pushBack ["text", format ["<t size='0.8' color='#8b98a7'>%1</t>", [format ["%1 (%2) : %3", "Contrôles subis" call _tr, count _ck,
                ((_ck select [((count _ck) - 3) max 0, 3]) apply { _x params [["_c", ""], ["_g", ""], ["_b", ""], ["_n", 0], ["_v", ""]]; format ["%1 %2 %3 N%4 %5", _c, _g, _b, _n, toUpper _v] }) joinString " · "]] call _esc]];
        };
    } forEach (_res getOrDefault ["records", []]);
    if ((_ath getOrDefault ["checks", 0]) > 0) then {
        _rows pushBack ["info", "Contrôles subis (Athena)" call _tr, format ["%1%2", _ath get "checks", ["", format [" · %1", _ath getOrDefault ["lastcheck", ""]]] select ((_ath getOrDefault ["lastcheck", ""]) isNotEqualTo "")]];
    };
    if (_hit && {(_codes arrayIntersect ["wanted", "hvt", "warrant"]) isNotEqualTo []}) then {
        _rows pushBack ["buttons", [["Recherchés" call _tr, ["wanted"] call _act, false, true, "App Recherchés : avis de recherche de la communauté."]]];
    };
    _rows pushBack ["text", format ["<t size='0.75' color='#5b6673'>%1</t>", ("Simulation : données fictives du registre de la communauté et de la mission." call _tr) call _esc]];
};
[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
