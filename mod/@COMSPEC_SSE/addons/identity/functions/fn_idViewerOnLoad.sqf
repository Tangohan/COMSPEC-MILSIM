/*
    Visionneuse de document (dialogs/idViewer.hpp) : gabarit PAA étiré au format réel, champs en texte structuré
    superposés aux coordonnées du type (CfgComspecDocuments, lu par fn_idDocTypes : papiers intégrés et extensions),
    MRZ en police à chasse fixe. Mêmes coordonnées que tools/gen_id_assets.py (étiquettes peintes sur le gabarit).
    ["load", display] · ["side"] recto / verso · ["zoom"] · ["scan"] passage aux fichiers (app Fichiers de l'ATAK) · ["render"] · ["buttons"]
    ["status", texte, couleur] : ligne d'état sous le document (retours de la fenêtre : jamais une alerte Arma).
    Bouton « Relever » (fn_idPhoto) : champs du document notés au relevé de contrôle ; ouvert depuis l'app pour un relevé,
    il part tout seul une demi-seconde après l'affichage. Bouton explicite quand le relevé est impossible.
    Défauts physiques d'un faux rendus tels quels (hologramme absent, police différente, photo rapportée) : un
    contrôleur attentif les voit ; le constat écrit (N0) dépend de sa compétence et de la lumière.
    Photo : portrait en direct d'un studio dédié (fn_idFace), portrait de repli en dessous si le PiP est coupé, puis un
    voile « photo imprimée » (photo_paper_ca). La lumière du studio ne noircit pas la photo : le constat « Trop sombre »
    reste (fn_idEvaluate), c'est un conseil.
*/
params [["_op", "render"], ["_disp", displayNull], ["_x2", ""]];
disableSerialization;
if (_op isEqualTo "load") then { uiNamespace setVariable ["comspec_sse_idViewerDisplay", _disp]; _op = "render"; };
private _d = uiNamespace getVariable ["comspec_sse_idViewerDisplay", displayNull];
private _st = uiNamespace getVariable ["comspec_sse_idView", createHashMap];
if (isNull _d || {(count _st) isEqualTo 0}) exitWith {};
private _doc = _st get "doc";
private _type = toLower (_doc getOrDefault ["type", "id_card"]);
// Fenêtre « Papiers de l'IA » du Zeus (fn_idZeusPapers) : même rendu, dans la zone qu'elle réserve (box), unité de hauteur
// de sa grille (vh), sans zoom, scanner, relevé ni bouton fermer (elle a les siens).
private _zeus = _st getOrDefault ["zeus", false];
private _vh = _st getOrDefault ["vh", safezoneH];
private _esc = { private _t = _this; if !(_t isEqualType "") then { _t = str _t; }; { _t = [_t, _x select 0, _x select 1] call CBA_fnc_replace; } forEach [["&", "&amp;"], ["<", "&lt;"], [">", "&gt;"]]; _t };

// --- Boutons : affichés seulement, côte à côte et de même largeur ; Relever explicite quand c'est impossible --------
private _buttons = {
    (_st getOrDefault ["geo", [0.2, 0.2, 0.6, 0.4]]) params ["_cx", "_cy", "_cw", "_ch"];
    private _by = _cy + _ch + _vh * 0.075;
    private _own = _st get "own";
    private _btns = [[93710, (_st getOrDefault ["sides", 2]) > 1], [93711, !_zeus], [93712, !_own && {!_zeus}], [93714, !_own && {!_zeus}], [93713, !_zeus]];
    { (_d displayCtrl (_x select 0)) ctrlShow (_x select 1); } forEach _btns;
    _btns = _btns select { _x select 1 };
    private _nb = count _btns;
    private _bw = (_cw - 0.01 * (_nb - 1)) / _nb;
    {
        private _b = _d displayCtrl (_x select 0);
        _b ctrlSetPosition [_cx + _forEachIndex * (_bw + 0.01), _by, _bw, _vh * 0.045];
        _b ctrlCommit 0;
    } forEach _btns;
    // Relever : texte et activation selon l'état (fn_idPhoto « can »).
    private _b = _d displayCtrl 93714;
    (["can"] call comspec_sse_fnc_idPhoto) params ["_ok", "_why", "_short"];
    _b ctrlSetText ((["RELEVER", _short] select (_short isNotEqualTo "")) call comspec_sse_fnc_tr);
    _b ctrlSetTooltip (([_why, "Champs du document notés au relevé de contrôle (téléphone › Contrôles)"] select (_why isEqualTo "")) call comspec_sse_fnc_tr);
    _b ctrlEnable _ok;
    _b ctrlSetTextColor ([[0.7, 0.72, 0.75, 1], [1, 1, 1, 1]] select _ok);
};
if (_op isEqualTo "buttons") exitWith { call _buttons };

if (_op isEqualTo "status") exitWith {
    // _disp = texte (français, traduit ici), _x2 = couleur.
    private _txt = if (_disp isEqualType "") then { _disp } else { "" };
    private _col = [_x2, "#cfe8cf"] select (_x2 isEqualTo "");
    _st set ["status", _txt];
    _st set ["statusColor", _col];
    private _c = _d displayCtrl 93706;
    _c ctrlSetStructuredText parseText ([format ["<t color='%1' size='0.9'>%2</t>", _col, (_txt call comspec_sse_fnc_tr) call _esc], ""] select (_txt isEqualTo ""));
    _c ctrlShow (_txt isNotEqualTo "");
    _c ctrlCommit 0;
    // État du bouton Relever recalculé (déjà relevé, pas de téléphone).
    call _buttons;
};

switch (_op) do {
    case "side": { if ((_st getOrDefault ["sides", 2]) > 1) then { _st set ["side", ["recto", "verso"] select ((_st get "side") isEqualTo "recto")]; }; };
    case "zoom": { _st set ["zoom", !(_st get "zoom")]; };
    case "scan": {
        closeDialog 2;
        if (isNil "comspec_atak_native_fnc_hasDevice" || {!([player] call comspec_atak_native_fnc_hasDevice)}) exitWith {
            ["Pas de téléphone ATAK sur vous : impossible de scanner le document.", "WARNING", "", "IDCHECK"] call comspec_sse_fnc_notify;
        };
        // Vérifier les papiers : passage aux fichiers sur ce document (numéro, pays, nom déjà saisis, recherche lancée) ;
        // la touche retour ramène au contrôle (app Contrôle ID) ou à l'écran d'avant.
        [{
            ["FICHIERS", createHashMapFromArray [["type", "QA"], ["number", _this getOrDefault ["number", ""]], ["country", _this getOrDefault ["country", ""]],
                ["docType", _this getOrDefault ["type", ""]], ["last", _this getOrDefault ["last", ""]], ["first", _this getOrDefault ["first", ""]],
                ["dob", _this getOrDefault ["dob", ""]], ["source", "idcheck"], ["run", true]]] call comspec_sse_fnc_openApp;
        }, _doc] call CBA_fnc_execNextFrame;
    };
};
if (_op in ["scan"]) exitWith {};

// --- Type et gabarit ---------------------------------------------------------------------------------------------
private _t = ["get", _type] call comspec_sse_fnc_idDocTypes;
if ((count _t) isEqualTo 0) then { _t = ["get", "id_card"] call comspec_sse_fnc_idDocTypes; _type = "id_card"; };
_st set ["sides", _t getOrDefault ["sides", 2]];
private _side = _st get "side";
if (_side isEqualTo "verso" && {(_st get "sides") < 2}) then { _side = "recto"; _st set ["side", "recto"]; };
private _lc = toLower (_doc getOrDefault ["country", "ALT"]);
// Pays réel stylisé : gabarit générique de sa zone de style (eu bordeaux, am bleu, ot vert/noir), nom posé en jeu.
private _zone = _doc getOrDefault ["zone", ""];
private _zoned = _zone in ["europe", "americas", "other"];
private _tpl = ["texture", [_type, _side, _lc, _zone]] call comspec_sse_fnc_idDocTypes;
private _dir = "\z\comspec_sse\addons\identity\data\";
private _layout = ["side", [_type, _side]] call comspec_sse_fnc_idDocTypes;

// Format réel (largeur / hauteur). Unités de l'interface : 1 x = 4/3 d'un y.
private _ratio = _t getOrDefault ["ratio", 1.585];
private _ch = safezoneH * ([0.46, 0.7] select (_st get "zoom"));
private _cw = _ch * _ratio * 0.75;
if (_cw > safezoneW * 0.94) then { _cw = safezoneW * 0.94; _ch = _cw / (_ratio * 0.75); };
private _cx = 0.5 - _cw / 2;
private _cy = safezoneY + (safezoneH - _ch) / 2 - safezoneH * 0.03;
private _box = _st getOrDefault ["box", []];
if (_box isNotEqualTo []) then {
    _box params ["_bx", "_by", "_bw", "_bh"];
    _ch = _bh;
    _cw = _ch * _ratio * 0.75;
    if (_cw > _bw) then { _cw = _bw; _ch = _cw / (_ratio * 0.75); };
    _cx = _bx + (_bw - _cw) / 2;
    _cy = _by + (_bh - _ch) / 2;
};
_st set ["geo", [_cx, _cy, _cw, _ch]];
private _card = _d displayCtrl 93720;
_card ctrlSetText _tpl;
_card ctrlSetPosition [_cx, _cy, _cw, _ch];
_card ctrlCommit 0;

// Contrôles superposés de l'affichage précédent : supprimés.
{ ctrlDelete _x; } forEach (uiNamespace getVariable ["comspec_sse_idViewerCtrls", []]);
private _ctrls = [];
private _cfgSize = configFile >> "RscStructuredText" >> "size";
private _base = if (isNumber _cfgSize) then { getNumber _cfgSize } else { call compile (getText _cfgSize) };
if (isNil "_base" || {!(_base isEqualType 0)} || {_base <= 0}) then { _base = 0.04; };
private _dmy = { private _p = _this splitString "-"; if ((count _p) < 3) exitWith { _this }; format ["%1.%2.%3", _p select 2, _p select 1, _p select 0] };
// Texte posé en fraction du document : [x, y, largeur, hauteur de ligne, texte, police, couleur].
private _put = {
    params ["_fx", "_fy", "_fw", "_fh", "_txt", ["_font", "RobotoCondensed"], ["_color", "#15181d"]];
    if (_txt isEqualTo "") exitWith {};
    private _c = _d ctrlCreate ["RscStructuredText", -1];
    _c ctrlSetPosition [_cx + _fx * _cw, _cy + _fy * _ch, _fw * _cw, _fh * _ch * 1.5];
    _c ctrlSetBackgroundColor [0, 0, 0, 0];
    _c ctrlSetStructuredText parseText format ["<t size='%1' font='%2' color='%3' shadow='0'>%4</t>", (_fh * _ch) / _base, _font, _color, _txt call _esc];
    _c ctrlCommit 0;
    _ctrls pushBack _c;
};
private _pic = {
    params ["_fx", "_fy", "_fw", "_fh", "_path", ["_angle", 0]];
    if (_path isEqualTo "") exitWith {};
    private _c = _d ctrlCreate ["RscPicture", -1];
    _c ctrlSetText _path;
    _c ctrlSetPosition [_cx + _fx * _cw, _cy + _fy * _ch, _fw * _cw, _fh * _ch];
    _c ctrlCommit 0;
    if (_angle isNotEqualTo 0) then { _c ctrlSetAngle [_angle, 0.5, 0.5]; };
    _ctrls pushBack _c;
};
// Police du nom : celle d'un faux « police différente » change (défaut rendu tel quel).
private _nameFont = ["RobotoCondensedBold", "TahomaB"] select (_doc getOrDefault ["font", false]);
private _value = {
    params ["_f"];
    private _k = toLower (_f get "key");
    private _v = _f get "text";
    if (_v isEqualTo "") then {
        _v = switch (_k) do {
            case "country": { _doc getOrDefault ["country", ""] };
            case "countryname": { _doc getOrDefault ["countryName", ""] };
            default { _doc getOrDefault [_f get "key", _doc getOrDefault [_k, ""]] };
        };
        if (_v isEqualTo "" && {(_f get "default") isNotEqualTo ""}) then { _v = _f get "default"; };
    };
    if !(_v isEqualType "") then { _v = str _v; };
    switch (_f get "transform") do {
        case "upper": { toUpper _v };
        case "dmy": { _v call _dmy };
        default { _v };
    }
};
// Photo : cadre du type, rapport réel du cadre transmis au studio (r2t), repli silhouette, voile « photo imprimée ».
private _photoBox = _layout getOrDefault ["photo", []];
if (_photoBox isNotEqualTo []) then {
    _photoBox params ["_fx", "_fy", "_fw", "_fh"];
    _doc set ["photoAspect", (_fw * _ratio) / _fh];
    private _face = ["start", _doc] call comspec_sse_fnc_idFace;
    // Photo rapportée ou substituée : décalée et de travers sur le fond imprimé.
    private _swap = (_doc getOrDefault ["photo", false]) || {_doc getOrDefault ["photoSwap", false]};
    private _spot = if (_swap) then { [_fx + 0.008, _fy + 0.012, _fw * 0.97, _fh * 0.97, 2.5] } else { [_fx, _fy, _fw, _fh, 0] };
    _spot params ["_px", "_py", "_pw", "_ph", "_angle"];
    [_px, _py, _pw, _ph, _dir + "silhouette_co.paa", _angle] call _pic;
    if (_face isNotEqualTo "") then { [_px, _py, _pw, _ph, _face, _angle] call _pic; };
    if (fileExists (_dir + "photo_paper_ca.paa")) then { [_px, _py, _pw, _ph, _dir + "photo_paper_ca.paa", _angle] call _pic; };
};
// Hologramme discret (absent sur certains faux : défaut relevé à l'examen).
private _holo = _layout getOrDefault ["holo", []];
if (_holo isNotEqualTo [] && {_doc getOrDefault ["holo", true]}) then {
    _holo params ["_fx", "_fy", "_fw"];
    [_fx, _fy, _fw, _fw * _ratio, _dir + "hologram_ca.paa"] call _pic;
};
// Champs.
{
    private _f = _x;
    private _font = switch (_f get "font") do {
        case "name": { _nameFont };
        case "mono": { "EtelkaMonospaceProBold" };
        default { "RobotoCondensed" };
    };
    [_f get "x", _f get "y", _f get "w", _f get "h", [_f] call _value, _font, _f get "color"] call _put;
} forEach (_layout getOrDefault ["fields", []]);
// MRZ (police à chasse fixe) sur le côté qui la porte.
if ((_t getOrDefault ["mrz", ""]) isNotEqualTo "" && {(_t getOrDefault ["mrzSide", "verso"]) isEqualTo _side}) then {
    private _lines = _doc getOrDefault ["mrz", []];
    if !(_lines isEqualType []) then { _lines = [_lines]; };
    if (_lines isNotEqualTo []) then {
        private _n = count (_lines select 0);
        // Un caractère ≈ 0,6 de sa hauteur ; ligne ajustée à 92 % de la largeur.
        private _fh = (0.92 * _cw / ((_n max 1) * 0.6 * 0.75)) / _ch;
        { [0.04, (_t get "mrzY") + _forEachIndex * (_t get "mrzStep"), 0.94, _fh, _x, "EtelkaMonospaceProBold", "#101010"] call _put; } forEach _lines;
    };
};
// Gabarit de zone : nom du pays posé dans le bandeau (le gabarit ne peint que « SIMULATION — DOCUMENT DE JEU »).
if (_zoned && {_side isEqualTo "recto"} && {_photoBox isNotEqualTo []}) then {
    private _hb = [0.155, 0.105] select ((_t get "ratio") < 1.5);
    private _cn = _doc getOrDefault ["countryNameEn", ""];
    if (_cn isEqualTo "") then { _cn = _doc getOrDefault ["countryName", ""]; };
    [0.135, _hb * 0.1, 0.7, _hb * 0.36, toUpper _cn, "RobotoCondensedBold", "#ffffff"] call _put;
};
uiNamespace setVariable ["comspec_sse_idViewerCtrls", _ctrls];

// --- Bandeau, constat N0, ligne d'état, boutons -------------------------------------------------------------------
private _sub = ["", "Votre document"] select (_st get "own");
// Mention discrète seulement pour un pays réel stylisé (_zoned) ; un pays du jeu n'en porte aucune.
if (_zoned) then { _sub = [_sub, "Document de jeu"] select (_sub isEqualTo ""); };
private _extName = "";
if ((_t getOrDefault ["extension", ""]) isNotEqualTo "") then {
    private _e = (["extensions"] call comspec_sse_fnc_idDocTypes) select { (_x select 0) isEqualTo (_t get "extension") };
    if (_e isNotEqualTo []) then { _extName = (_e select 0) select 1; };
};
private _title = format ["<t align='center' size='1.1' font='PuristaSemibold'>%1</t><br/><t align='center' size='0.85' color='#b8c2cc'>%2%3%4</t>",
    (["label", _type] call comspec_sse_fnc_idDocTypes) call _esc,
    ([_doc getOrDefault ["countryName", ""], _doc getOrDefault ["countryNameEn", ""]] select ((missionNamespace getVariable ["COMSPEC_ATAK_Lang", "fr"]) isNotEqualTo "fr" && {(_doc getOrDefault ["countryNameEn", ""]) isNotEqualTo ""})) call _esc,
    ["", " · " + (_sub call comspec_sse_fnc_tr)] select (_sub isNotEqualTo ""),
    ["", " · " + (_extName call _esc)] select (_extName isNotEqualTo "")];
(_d displayCtrl 93704) ctrlSetStructuredText parseText _title;
(_d displayCtrl 93704) ctrlSetPosition [_cx, _cy - _vh * 0.075, _cw, _vh * 0.07];
(_d displayCtrl 93704) ctrlCommit 0;
private _n0 = _st getOrDefault ["n0", createHashMap];
private _rem = if ((count _n0) > 0) then {
    format ["<t color='%1'>%2</t>", ["#cfe8cf", "#f2c94c"] select ((_n0 getOrDefault ["state", "ok"]) isNotEqualTo "ok"), (((_n0 getOrDefault ["lines", []]) apply { _x call comspec_sse_fnc_tr }) joinString " · ") call _esc]
} else { "" };
(_d displayCtrl 93705) ctrlSetStructuredText parseText _rem;
(_d displayCtrl 93705) ctrlSetPosition [_cx, _cy + _ch + _vh * 0.012, _cw, _vh * 0.03];
(_d displayCtrl 93705) ctrlShow (_rem isNotEqualTo "");
(_d displayCtrl 93705) ctrlCommit 0;
// Ligne d'état (relevé, erreurs) : sous le constat, gardée d'un rendu à l'autre.
private _status = _d displayCtrl 93706;
_status ctrlSetPosition [_cx, _cy + _ch + _vh * 0.044, _cw, _vh * 0.028];
_status ctrlCommit 0;
["status", _st getOrDefault ["status", ""], _st getOrDefault ["statusColor", ""]] call comspec_sse_fnc_idViewerOnLoad;
// Bouton « ? » du manuel (chapitre Documents), en haut à droite de la fenêtre.
if (!isNil "comspec_sse_fnc_guideButton") then {
    private _hb = _d getVariable ["comspec_sse_guideBtn", controlNull];
    if (isNull _hb) then { _hb = [_d, "IdCheck", _cx + _cw - _vh * 0.035, _cy - _vh * 0.075, _vh * 0.035] call comspec_sse_fnc_guideButton; _d setVariable ["comspec_sse_guideBtn", _hb]; } else {
        _hb ctrlSetPosition [_cx + _cw - _vh * 0.035, _cy - _vh * 0.075, _vh * 0.035, _vh * 0.035];
        _hb ctrlCommit 0;
    };
};
if ((_st getOrDefault ["auto", false]) && {!(_st getOrDefault ["shot", false])}) then {
    _st set ["shot", true];
    [{ ["shoot"] call comspec_sse_fnc_idPhoto; }, [], 0.5] call CBA_fnc_waitAndExecute;
};
