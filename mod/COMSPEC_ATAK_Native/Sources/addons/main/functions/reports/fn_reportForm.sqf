/*
    App Comptes rendus : la fiche d'un type de C.R., à sa propre présentation (catalogue fn_reportTypes).
    Params : [type du catalogue, état de l'app (uiNamespace COMSPEC_ATAK_RepUi)] ; renvoie les lignes de fn_formRender.
      - bandeau de tête (code du C.R., titre militaire, DTG, indicatif), bandeau d'alerte pour les C.R. d'urgence ;
      - frise des lignes de la fiche : remplie (vert), à faire (orange), manquante après un envoi refusé (rouge) ;
      - propositions tirées du jeu (ennemi visé ou connu, mon groupe, cible désignée, ma position) ;
      - les lignes elles-mêmes, chacune avec son repère (lettre SALUTE, numéro de 9 lignes, paragraphe, classe) :
        gros boutons colorés, niveaux VERT / ORANGE / ROUGE / NOIR, rose des vents, listes, champs ;
      - transmission (priorité, diffusion en jeu, remontée JNET, photo jointe), aperçu du message tel qu'il part.
*/
params ["_type", "_ui"];
_type params ["_code", "_label", "_family", "_web", "_help", "_fields", "_m"];
private _vals = _ui getOrDefault ["vals", createHashMap];
private _auto = _ui getOrDefault ["auto", createHashMap];
private _errors = _ui getOrDefault ["errors", []];
private _act = { params ["_a", ["_k", ""], ["_v", ""]]; compile format ["[%1, %2, %3] call comspec_atak_native_fnc_reportAction;", str _a, str _k, str _v] };
private _esc = { params ["_t"]; if !(_t isEqualType "") then { _t = str _t; }; { _t = [_t, _x select 0, _x select 1] call CBA_fnc_replace; } forEach [["&", "&amp;"], ["<", "&lt;"], [">", "&gt;"]]; _t };
private _rgb = createHashMapFromArray [
    ["red", [0.85, 0.22, 0.17, 1]], ["amber", [0.92, 0.6, 0.18, 1]], ["green", [0.30, 0.68, 0.36, 1]],
    ["VERT", [0.30, 0.68, 0.36, 1]], ["ORANGE", [0.92, 0.6, 0.18, 1]], ["ROUGE", [0.85, 0.22, 0.17, 1]], ["NOIR", [0.02, 0.02, 0.02, 1]],
    ["ROUTINE", [0.30, 0.68, 0.36, 1]], ["PRIORITAIRE", [0.91, 0.77, 0.28, 1]], ["IMMÉDIAT", [0.94, 0.54, 0.24, 1]], ["FLASH", [0.85, 0.22, 0.17, 1]]
];
private _color = _m getOrDefault ["color", "#5cc76b"];
private _flash = _m getOrDefault ["flash", false];
private _cur = {
    params ["_k", "_kind", ["_farg", ""]];
    private _d = switch (_kind) do {
        case "seg";
        case "combo";
        case "level": { _farg select 0 };
        case "big": { (_farg select 0) select 0 };
        case "edit";
        case "num": { _farg };
        default { "" };
    };
    _vals getOrDefault [_k, _d]
};
private _rows = [];
private _dtg = ([["dtg"]] call comspec_atak_native_fnc_reportAuto) getOrDefault ["dtg", ""];

// ── Tête de fiche ─────────────────────────────────────────────────────────────────────────────────────────────
_rows pushBack ["strip", format ["<t font='COMSPEC_BarlowBold' color='%1'>%2</t>  <t font='COMSPEC_BarlowBold' color='#e3ebe7'>%3</t>", _color, _m getOrDefault ["tag", _code], _m getOrDefault ["title", toUpper _label]],
    format ["<t size='0.85' color='#8a9a93'>%1 · %2</t>", _dtg, [[player, true] call comspec_atak_native_fnc_unitCallsign] call _esc]];
private _banner = _m getOrDefault ["banner", ""];
if (_banner isNotEqualTo "") then {
    _rows pushBack ["banner", format ["<t font='COMSPEC_BarlowBold' size='1.15' color='%1'>%2</t>", ["#ffffff", "#140c02"] select (_color isNotEqualTo "#e5483a"), _banner],
        [[0.92, 0.6, 0.18, 1], [0.78, 0.17, 0.13, 1]] select (_color isEqualTo "#e5483a")];
};
_rows pushBack ["text", format ["<t size='0.85' color='#c9d4cf'>%1</t>", _help]];

// Frise des lignes repérées : remplie, à faire, manquante.
private _steps = [];
private _nextSet = false;
{
    _x params ["_k", "_kind", "_flabel", ["_farg", ""], ["_fhelp", ""], ["_tag", ""], ["_req", false]];
    if (_tag isNotEqualTo "" && {_kind isNotEqualTo "sec"}) then {
        private _v = [_k, _kind, _farg] call _cur;
        private _filled = switch (_kind) do {
            case "big": { (_k in _vals) || {!_req} };
            case "edit";
            case "num";
            case "dtg";
            case "grid";
            case "memo";
            case "dir": { _v isNotEqualTo "" };
            default { true };
        };
        private _st = switch (true) do {
            case (_k in _errors): { "hot" };
            case _filled: { "done" };
            case (_req && {!_nextSet}): { _nextSet = true; "now" };
            default { "todo" };
        };
        _steps pushBack [_tag, _st];
    };
} forEach _fields;
if ((count _steps) > 1) then { _rows pushBack ["steps", _steps]; };

// Propositions tirées du jeu et commandes de la fiche.
private _toolBtns = [];
{
    switch (_x) do {
        case "enemy": { _toolBtns pushBack ["ENNEMI", ["auto", "enemy"] call _act, "", "Remplit la fiche avec l'ennemi que vous visez, sinon le plus proche connu de votre groupe (position estimée)"]; };
        case "group": { _toolBtns pushBack ["MON GROUPE", ["auto", "group"] call _act, "", "Effectifs, munitions, carburant et véhicules de votre groupe"]; };
        case "target": { _toolBtns pushBack ["CIBLE", ["auto", "target"] call _act, "", "Grille de votre désignateur laser, sinon de ce que vous visez"]; };
        case "here": { _toolBtns pushBack ["ICI", ["auto", "here"] call _act, "", "Votre position et l'heure"]; };
    };
} forEach (_m getOrDefault ["tools", []]);
private _bar = [];
if ((count _toolBtns) > 0) then { _bar pushBack ["Propositions", _toolBtns]; };
(uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) set ["backSub", ["REPORTS", ["back"] call _act]];
_bar pushBack ["Fiche", [["‹ RETOUR", ["back"] call _act, "", "Retour à la liste ; la fiche reste en brouillon"], ["EFFACER", ["clear"] call _act, "", "Repart d'une fiche vide"]]];
_rows pushBack ["bar", _bar];

// ── Lignes de la fiche ──────────────────────────────────────────────────────────────────────────────────────
{
    _x params ["_k", "_kind", "_flabel", ["_farg", ""], ["_fhelp", ""], ["_tag", ""], ["_req", false], ["_aid", ""]];
    private _v = [_k, _kind, _farg] call _cur;
    private _warn = _k in _errors;
    private _isAuto = (_auto getOrDefault [_k, "§"]) isEqualTo _v && {_v isNotEqualTo ""};
    // Astérisque des lignes requises dans sa propre balise : le libellé reste traduisible tel quel.
    private _lbl = _flabel + (["", "<t color='#e8c547'> *</t>"] select _req);
    switch (_kind) do {
        case "sec": { _rows pushBack ["section", _flabel, _farg]; };
        case "edit";
        case "num";
        case "dtg": { _rows pushBack ["line", _tag, _lbl, _v, ["edit", "autoedit"] select _isAuto, "rp_" + _k, _fhelp, "", _warn]; };
        case "grid": {
            _rows pushBack ["line", _tag, _lbl, _v, ["edit", "autoedit"] select _isAuto, "rp_" + _k, ["grille 6 à 10 chiffres", _fhelp] select (_fhelp isNotEqualTo ""), "", _warn];
            private _gb = [["MA POSITION", ["here", _k] call _act, "", "Votre grille actuelle"], ["SUR LA CARTE", ["pick", _k] call _act, "", "Touchez la carte pour placer le point"]];
            if (_aid in ["enemyGrid", "targetGrid"]) then { _gb pushBack [["ENNEMI", "CIBLE"] select (_aid isEqualTo "targetGrid"), ["autoKey", _k] call _act, "", "Grille estimée de l'ennemi visé ou le plus proche connu"]; };
            _rows pushBack ["bar", [["", _gb]]];
        };
        case "memo": {
            _rows pushBack ["memo", "rp_" + _k, [_flabel, ["", "Requis"] select _req] select (_flabel isEqualTo ""), _v, _farg];
            if (_warn) then { _rows pushBack ["text", "<t size='0.8' color='#ff6a5c'>Ce paragraphe est requis.</t>"]; };
        };
        case "seg";
        case "dir": {
            _rows pushBack ["lineseg", _tag, _lbl, _farg apply { [_x, ["set", _k, _x] call _act, _x isEqualTo _v] }, _fhelp, _warn];
        };
        case "level": {
            _rows pushBack ["lineseg", _tag, _lbl, _farg apply { [_x, ["set", _k, _x] call _act, _x isEqualTo _v, _rgb getOrDefault [_x, []]] }, _fhelp, _warn];
        };
        case "combo": { _rows pushBack ["linecombo", _tag, _lbl, "rp_" + _k, _farg apply { [_x, _x] }, _v, _fhelp, _warn]; };
        case "big": {
            _rows pushBack ["text", format ["<t font='COMSPEC_BarlowBold' color='%1'>%2</t>%3<t color='%4'>%5</t>%6", _color, _tag, ["", "  "] select (_tag isNotEqualTo ""),
                ["#e3ebe7", "#ff6a5c"] select _warn, _lbl, ["", format ["  <t size='0.75' color='#8a9a93'>%1</t>", _fhelp]] select (_fhelp isNotEqualTo "")]];
            // Bouton choisi à sa couleur (vert s'il n'en a pas), les autres sombres ; rien de choisi tant qu'on n'a pas touché.
            private _picked = _k in _vals;
            private _opts = _farg apply {
                _x params ["_o", ["_c", ""]];
                if (_c isEqualTo "") then { _c = "green"; };
                [_o, ["set", _k, _o] call _act, ["", _c] select (_picked && {_o isEqualTo _v})]
            };
            if ((count _opts) > 4) then {
                _rows pushBack ["big", _opts select [0, 3]];
                _rows pushBack ["big", _opts select [3]];
            } else {
                _rows pushBack ["big", _opts];
            };
        };
    };
} forEach _fields;

// Passerelles vers les apps qui prennent la suite.
switch (_code) do {
    case "CONTACT": {
        private _need = _vals getOrDefault ["need", "AUCUN"];
        if (_need isEqualTo "APPUI FEU") then { _rows pushBack ["buttons", [["DEMANDE DE TIR DANS L'APP CAS ›", ["go", "FIRES"] call _act, true]]]; };
        if (_need isEqualTo "MEDEVAC") then { _rows pushBack ["buttons", [["MEDEVAC 9 LIGNES DANS L'APP MÉDICAL ›", ["go", "MEDICAL"] call _act, true]]]; };
    };
    case "EAGLE_DOWN": {
        if ((_vals getOrDefault ["medevac", "URGENTE"]) isNotEqualTo "PAS BESOIN") then {
            _rows pushBack ["buttons", [["PRÉPARER LE MEDEVAC 9 LIGNES ›", ["go", "MEDICAL"] call _act, false, true, "Ouvre l'onglet MEDEVAC de l'app Médical ; envoyez d'abord ce compte rendu"]]];
        };
    };
};

// ── Transmission ────────────────────────────────────────────────────────────────────────────────────────────
_rows pushBack ["section", "Transmission", "Athena le reçoit aussi quand le téléphone est connecté"];
private _prio = _vals getOrDefault ["prio", _m getOrDefault ["prio", "ROUTINE"]];
_rows pushBack ["lineseg", "PRI", "Priorité", ["ROUTINE", "PRIORITAIRE", "IMMÉDIAT", "FLASH"] apply { [_x, ["set", "prio", _x] call _act, _x isEqualTo _prio, _rgb get _x] }, ""];
private _dest = _vals getOrDefault ["dest", "CAMP"];
_rows pushBack ["lineseg", "DIF", "Diffusion en jeu", ["CAMP", "MON GROUPE", "AUCUNE"] apply { [_x, ["set", "dest", _x] call _act, _x isEqualTo _dest] }, ""];
if (_m getOrDefault ["jnet", false]) then {
    private _up = _vals getOrDefault ["jnet", "ÉCHELON SUP."];
    _rows pushBack ["lineseg", "JNET", "Remonter à", ["ÉCHELON SUP.", "+2 ÉCHELONS", "SOMMET"] apply { [_x, ["set", "jnet", _x] call _act, _x isEqualTo _up] },
        "sur Athena : unités au-dessus de la vôtre"];
};
if ((_m getOrDefault ["photo", false]) && {[] call comspec_atak_native_fnc_bridge}) then {
    private _ph = _vals getOrDefault ["photo", []];
    _rows pushBack ["line", "PHO", "Photo jointe", [(_ph param [1, ""]), "aucune"] select ((count _ph) < 2), "auto", "", "elle part aussi vers la photothèque d'Athena"];
    _rows pushBack ["bar", [["", [[["JOINDRE UNE PHOTO", "FERMER LA LISTE"] select (_ui getOrDefault ["photoPick", false]), ["photo"] call _act, ["", "on"] select (_ui getOrDefault ["photoPick", false])],
        ["RETIRER", ["photoClear"] call _act, "", "", (count _ph) >= 2]]]]];
    if (_ui getOrDefault ["photoPick", false]) then {
        private _lib = uiNamespace getVariable ["COMSPEC_ATAK_PhotoLib", []];
        if ((count _lib) isEqualTo 0) then {
            _rows pushBack ["text", "<t size='0.85' color='#8a9a93'>Aucune photo sur le téléphone. Prenez-en une avec l'app Photo.</t>"];
        };
        // Les plus récentes d'abord (dernières de la liste du disque).
        private _n = count _lib;
        for "_i" from (_n - 1) to ((_n - 6) max 0) step -1 do {
            (_lib select _i) params ["_path", "_name"];
            _rows pushBack ["person", _path, format ["<t font='COMSPEC_BarlowBold'>%1</t>", [_name] call _esc], [["CHOISIR", ["photoSet", str _i] call _act, true]]];
        };
    };
};

// Aperçu du message tel qu'il part (valeurs saisies au dernier affichage).
private _prev = [format ["%1 // %2 // %3 // %4", _m getOrDefault ["tag", _code], _prio, _dtg, [player, true] call comspec_atak_native_fnc_unitCallsign]];
{
    _x params ["_k", "_kind", "_flabel", ["_farg", ""], ["_fhelp", ""], ["_tag", ""]];
    if (_kind isNotEqualTo "sec") then {
        private _v = [_k, _kind, _farg] call _cur;
        if (_kind isEqualTo "big" && {!(_k in _vals)}) then { _v = ""; };
        if (_v isNotEqualTo "") then {
            private _t = [_tag, toUpper ((_flabel splitString " ") param [0, _k])] select (_tag isEqualTo "");
            _prev pushBack format ["%1: %2", _t, ((_v splitString toString [13, 10]) joinString " / ") select [0, 90]];
        };
    };
} forEach _fields;
_rows pushBack ["text", format ["<t size='0.72' color='#8a9a93'>APERÇU DU MESSAGE</t><br/><t font='EtelkaMonospacePro' size='0.78' color='#b9c7c0'>%1</t>", (_prev apply { [_x] call _esc }) joinString "<br/>"], [0.03, 0.04, 0.035, 1]];

_rows pushBack ["buttons", [["ENREGISTRER LE BROUILLON", ["draft"] call _act]]];
_rows pushBack ["big", [[format ["TRANSMETTRE · %1", _prio], ["send"] call _act, ["green", "red"] select (_flash || {_prio in ["IMMÉDIAT", "FLASH"]})]]];
_rows
