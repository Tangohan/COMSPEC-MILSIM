/*
    Visionneuse de document (dialogs/idViewer.hpp) : gabarit PAA étiré au format réel, champs en texte structuré
    superposés aux coordonnées du gabarit (tools/gen_id_assets.py, LAYOUT), MRZ en police à chasse fixe.
    ["load", display] · ["side"] recto / verso · ["zoom"] · ["scan"] app Contrôle ID de l'ATAK · ["render"]
    Bouton « Photographier » : cliché du document joint à la fiche FRM (fn_idPhoto) ; ouvert depuis l'app pour une photo,
    le cliché part tout seul une demi-seconde après l'affichage.
    Défauts physiques d'un faux rendus tels quels (hologramme absent, police différente, photo rapportée) : un
    contrôleur attentif les voit ; le constat écrit (N0) dépend de sa compétence et de la lumière.
    Photo : portrait en direct d'une scène dédiée (fn_idFace), portrait de repli en dessous si le PiP est coupé. La lumière
    de la scène ne noircit pas la photo : le constat « Trop sombre » reste (fn_idEvaluate), c'est un conseil.
*/
params [["_op", "render"], ["_disp", displayNull]];
disableSerialization;
if (_op isEqualTo "load") then { uiNamespace setVariable ["comspec_sse_idViewerDisplay", _disp]; _op = "render"; };
private _d = uiNamespace getVariable ["comspec_sse_idViewerDisplay", displayNull];
private _st = uiNamespace getVariable ["comspec_sse_idView", createHashMap];
if (isNull _d || {(count _st) isEqualTo 0}) exitWith {};
private _doc = _st get "doc";
private _type = _doc getOrDefault ["type", "id_card"];

switch (_op) do {
    case "side": { if (_type isNotEqualTo "passport") then { _st set ["side", ["recto", "verso"] select ((_st get "side") isEqualTo "recto")]; }; };
    case "zoom": { _st set ["zoom", !(_st get "zoom")]; };
    case "scan": {
        closeDialog 2;
        if (isNil "comspec_atak_native_fnc_hasDevice" || {!([player] call comspec_atak_native_fnc_hasDevice)}) exitWith {
            ["Pas de téléphone ATAK sur vous : impossible de scanner le document.", "WARNING", "", "IDCHECK"] call comspec_sse_fnc_notify;
        };
        [{
            if (isNull ([] call comspec_atak_native_fnc_display) || {!((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["interactive", false])}) then {
                [true] call comspec_atak_native_fnc_open;
            };
            [{ ["IDCHECK"] call comspec_atak_native_fnc_navigate; }] call CBA_fnc_execNextFrame;
        }] call CBA_fnc_execNextFrame;
    };
};
if (_op in ["scan"]) exitWith {};

private _side = _st get "side";
private _kind = switch (_type) do {
    case "passport": { "passport" };
    case "driving_license": { ["license_recto", "license_verso"] select (_side isEqualTo "verso") };
    case "military_id": { ["military_recto", "card_verso"] select (_side isEqualTo "verso") };
    default { ["card_recto", "card_verso"] select (_side isEqualTo "verso") };
};
private _lc = toLower (_doc getOrDefault ["country", "ALT"]);
// Pays réel stylisé : gabarit générique de sa zone de style (eu bordeaux, am bleu, ot vert/noir), nom posé en jeu.
private _zone = _doc getOrDefault ["zone", ""];
private _zoned = _zone in ["europe", "americas", "other"];
if (_zoned) then { _lc = ["ot", "eu", "am"] select ((["other", "europe", "americas"] find _zone) max 0); };
if !(_lc in ["alt", "tna", "lvn", "mld", "eu", "am", "ot"]) then { _lc = "alt"; };
private _dir = "\z\comspec_sse\addons\identity\data\";
private _tpl = _dir + (switch (_kind) do {
    case "passport": { format ["tpl_passport_%1_co.paa", _lc] };
    case "license_recto": { format ["tpl_license_%1_recto_co.paa", _lc] };
    case "military_recto": { format ["tpl_military_%1_recto_co.paa", _lc] };
    case "license_verso": { "tpl_license_verso_co.paa" };
    case "card_verso": { format ["tpl_card_%1_verso_co.paa", _lc] };
    default { format ["tpl_card_%1_recto_co.paa", _lc] };
});

// Format réel (largeur / hauteur) : ID-1 1,585 ; page TD3 1,42. Unités de l'interface : 1 x = 4/3 d'un y.
private _ratio = [1.585, 1.42] select (_type isEqualTo "passport");
private _ch = safezoneH * ([0.46, 0.7] select (_st get "zoom"));
private _cw = _ch * _ratio * 0.75;
if (_cw > safezoneW * 0.94) then { _cw = safezoneW * 0.94; _ch = _cw / (_ratio * 0.75); };
private _cx = 0.5 - _cw / 2;
private _cy = safezoneY + (safezoneH - _ch) / 2 - safezoneH * 0.03;
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
private _esc = { private _t = _this; if !(_t isEqualType "") then { _t = str _t; }; { _t = [_t, _x select 0, _x select 1] call CBA_fnc_replace; } forEach [["&", "&amp;"], ["<", "&lt;"], [">", "&gt;"]]; _t };
private _dmy = { private _p = _this splitString "-"; if ((count _p) < 3) exitWith { _this }; format ["%1.%2.%3", _p select 2, _p select 1, _p select 0] };
// Texte posé en fraction du document : [x, y, largeur, hauteur de ligne, texte, police].
private _put = {
    params ["_fx", "_fy", "_fw", "_fh", "_txt", ["_font", "RobotoCondensedBold"], ["_color", "#15181d"]];
    private _c = _d ctrlCreate ["RscStructuredText", -1];
    _c ctrlSetPosition [_cx + _fx * _cw, _cy + _fy * _ch, _fw * _cw, _fh * _ch * 1.5];
    _c ctrlSetBackgroundColor [0, 0, 0, 0];
    _c ctrlSetStructuredText parseText format ["<t size='%1' font='%2' color='%3' shadow='0'>%4</t>", (_fh * _ch) / _base, _font, _color, _txt call _esc];
    _c ctrlCommit 0;
    _ctrls pushBack _c;
};
private _pic = {
    params ["_fx", "_fy", "_fw", "_fh", "_path", ["_angle", 0]];
    private _c = _d ctrlCreate ["RscPicture", -1];
    _c ctrlSetText _path;
    _c ctrlSetPosition [_cx + _fx * _cw, _cy + _fy * _ch, _fw * _cw, _fh * _ch];
    _c ctrlCommit 0;
    if (_angle isNotEqualTo 0) then { _c ctrlSetAngle [_angle, 0.5, 0.5]; };
    _ctrls pushBack _c;
};
private _nameFont = ["RobotoCondensedBold", "TahomaB"] select (_doc getOrDefault ["font", false]);
private _g = { _doc getOrDefault [_this, ""] };
private _vh = 0.055;
private _face = ["start", _doc] call comspec_sse_fnc_idFace;
private _photo = {
    params ["_fx", "_fy", "_fw", "_fh"];
    // Photo rapportée ou substituée : décalée et de travers sur le fond imprimé.
    private _swap = (_doc getOrDefault ["photo", false]) || {_doc getOrDefault ["photoSwap", false]};
    private _spot = if (_swap) then { [_fx + 0.008, _fy + 0.012, _fw * 0.97, _fh * 0.97, 2.5] } else { [_fx, _fy, _fw, _fh, 0] };
    _spot params ["_px", "_py", "_pw", "_ph", "_angle"];
    [_px, _py, _pw, _ph, _dir + "silhouette_co.paa", _angle] call _pic;
    if (_face isNotEqualTo "") then { [_px, _py, _pw, _ph, _face, _angle] call _pic; };
};
private _holo = { params ["_fx", "_fy", "_fw"]; if (_doc getOrDefault ["holo", true]) then { [_fx, _fy, _fw, _fw * _ratio, _dir + "hologram_ca.paa"] call _pic; }; };
private _mrzLines = {
    params ["_y0", "_step"];
    private _lines = _doc getOrDefault ["mrz", []];
    if (_lines isEqualTo []) exitWith {};
    private _n = count (_lines select 0);
    // Police à chasse fixe : un caractère ≈ 0,6 de sa hauteur ; ligne ajustée à 92 % de la largeur.
    private _fh = (0.92 * _cw / (_n * 0.6 * 0.75)) / _ch;
    { [0.04, _y0 + _forEachIndex * _step, 0.94, _fh, _x, "EtelkaMonospaceProBold", "#101010"] call _put; } forEach _lines;
};

switch (_kind) do {
    case "card_recto": {
        [0.04, 0.21, 0.27, 0.62] call _photo;
        [0.22, 0.58, 0.15] call _holo;
        [0.35, 0.235, 0.6, _vh, toUpper ("last" call _g), _nameFont] call _put;
        [0.35, 0.355, 0.6, _vh, "first" call _g, _nameFont] call _put;
        [0.35, 0.475, 0.1, _vh, "sex" call _g] call _put;
        [0.47, 0.475, 0.22, _vh, "nat" call _g] call _put;
        [0.72, 0.475, 0.26, _vh, ("dob" call _g) call _dmy] call _put;
        [0.35, 0.595, 0.6, _vh, "pob" call _g] call _put;
        [0.35, 0.715, 0.34, _vh, "number" call _g, "RobotoCondensedBold", "#8c1414"] call _put;
        [0.72, 0.715, 0.26, _vh, ("expires" call _g) call _dmy] call _put;
    };
    case "military_recto": {
        [0.04, 0.21, 0.27, 0.62] call _photo;
        [0.22, 0.58, 0.15] call _holo;
        [0.35, 0.235, 0.6, _vh, toUpper ("last" call _g), _nameFont] call _put;
        [0.35, 0.355, 0.6, _vh, "first" call _g, _nameFont] call _put;
        [0.35, 0.475, 0.28, _vh, "rank" call _g] call _put;
        [0.65, 0.475, 0.32, _vh, ("dob" call _g) call _dmy] call _put;
        [0.35, 0.595, 0.6, _vh, "force" call _g] call _put;
        [0.35, 0.715, 0.34, _vh, "number" call _g, "RobotoCondensedBold", "#8c1414"] call _put;
        [0.72, 0.715, 0.26, _vh, ("expires" call _g) call _dmy] call _put;
    };
    case "card_verso": {
        [0.05, 0.095, 0.62, _vh, "address" call _g] call _put;
        [0.05, 0.255, 0.62, _vh, "authority" call _g] call _put;
        [0.05, 0.415, 0.45, _vh, ("issued" call _g) call _dmy] call _put;
        [0.55, 0.415, 0.42, _vh, "profession" call _g] call _put;
        [0.62, 0.105] call _mrzLines;
    };
    case "passport": {
        [0.04, 0.15, 0.28, 0.55] call _photo;
        [0.22, 0.5, 0.14] call _holo;
        [0.36, 0.16, 0.09, _vh, "P"] call _put;
        [0.46, 0.16, 0.14, _vh, "country" call _g] call _put;
        [0.62, 0.16, 0.36, _vh, "number" call _g, "RobotoCondensedBold", "#8c1414"] call _put;
        [0.36, 0.27, 0.62, _vh, toUpper ("last" call _g), _nameFont] call _put;
        [0.36, 0.38, 0.62, _vh, "first" call _g, _nameFont] call _put;
        [0.36, 0.49, 0.28, _vh, "nat" call _g] call _put;
        [0.66, 0.49, 0.32, _vh, ("dob" call _g) call _dmy] call _put;
        [0.36, 0.6, 0.1, _vh, "sex" call _g] call _put;
        [0.48, 0.6, 0.5, _vh, "pob" call _g] call _put;
        [0.36, 0.71, 0.16, _vh * 0.85, ("issued" call _g) call _dmy] call _put;
        [0.53, 0.71, 0.16, _vh * 0.85, ("expires" call _g) call _dmy] call _put;
        [0.70, 0.71, 0.29, _vh * 0.7, "authority" call _g] call _put;
        [0.80, 0.075] call _mrzLines;
    };
    case "license_recto": {
        [0.04, 0.21, 0.27, 0.62] call _photo;
        [0.35, 0.235, 0.6, _vh, toUpper ("last" call _g), _nameFont] call _put;
        [0.35, 0.345, 0.6, _vh, "first" call _g, _nameFont] call _put;
        [0.35, 0.455, 0.62, _vh, format ["%1  %2", ("dob" call _g) call _dmy, "pob" call _g]] call _put;
        [0.35, 0.565, 0.25, _vh, ("issued" call _g) call _dmy] call _put;
        [0.62, 0.565, 0.3, _vh, ("expires" call _g) call _dmy] call _put;
        [0.35, 0.675, 0.62, _vh * 0.9, "authority" call _g] call _put;
        [0.35, 0.785, 0.34, _vh, "number" call _g, "RobotoCondensedBold", "#8c1414"] call _put;
        [0.70, 0.785, 0.2, _vh, "B"] call _put;
    };
    case "license_verso": {
        // Ligne de la catégorie B (4e ligne du tableau).
        [0.22, 0.30, 0.18, _vh * 0.8, ("issued" call _g) call _dmy] call _put;
        [0.42, 0.30, 0.18, _vh * 0.8, ("expires" call _g) call _dmy] call _put;
        [0.64, 0.105] call _mrzLines;
    };
};
// Gabarit de zone : nom du pays posé dans le bandeau (le gabarit ne peint que « SIMULATION — DOCUMENT DE JEU »).
if (_zoned && {_kind isNotEqualTo "license_verso"} && {_kind isNotEqualTo "card_verso"}) then {
    private _hb = [0.17, 0.11] select (_kind isEqualTo "passport");
    private _cn = _doc getOrDefault ["countryNameEn", ""];
    if (_cn isEqualTo "") then { _cn = _doc getOrDefault ["countryName", ""]; };
    [0.13, _hb * 0.08, 0.8, _hb * 0.4, toUpper _cn, "RobotoCondensedBold", "#ffffff"] call _put;
};
uiNamespace setVariable ["comspec_sse_idViewerCtrls", _ctrls];

// Bandeau, constat N0, boutons.
private _sub = ["", "Votre document"] select (_st get "own");
// Mention discrète seulement pour un pays réel stylisé (_zoned) ; un pays du jeu n'en porte aucune.
if (_zoned) then { _sub = [_sub, "Document de jeu"] select (_sub isEqualTo ""); };
private _title = format ["<t align='center' size='1.1' font='PuristaSemibold'>%1</t><br/><t align='center' size='0.85' color='#b8c2cc'>%2%3</t>",
    (["Carte d'identité", "Passeport", "Permis de conduire", "Carte militaire"] select ((["id_card", "passport", "driving_license", "military_id"] find _type) max 0)) call comspec_sse_fnc_tr,
    ([_doc getOrDefault ["countryName", ""], _doc getOrDefault ["countryNameEn", ""]] select ((missionNamespace getVariable ["COMSPEC_ATAK_Lang", "fr"]) isNotEqualTo "fr" && {(_doc getOrDefault ["countryNameEn", ""]) isNotEqualTo ""})) call _esc, ["", " · " + (_sub call comspec_sse_fnc_tr)] select (_sub isNotEqualTo "")];
(_d displayCtrl 93704) ctrlSetStructuredText parseText _title;
(_d displayCtrl 93704) ctrlSetPosition [_cx, _cy - safezoneH * 0.075, _cw, safezoneH * 0.07];
(_d displayCtrl 93704) ctrlCommit 0;
private _n0 = _st getOrDefault ["n0", createHashMap];
private _rem = if ((count _n0) > 0) then {
    format ["<t color='%1'>%2</t>", ["#cfe8cf", "#f2c94c"] select ((_n0 getOrDefault ["state", "ok"]) isNotEqualTo "ok"), (((_n0 getOrDefault ["lines", []]) apply { _x call comspec_sse_fnc_tr }) joinString " · ") call _esc]
} else { "" };
(_d displayCtrl 93705) ctrlSetStructuredText parseText _rem;
(_d displayCtrl 93705) ctrlSetPosition [_cx, _cy + _ch + safezoneH * 0.012, _cw, safezoneH * 0.05];
(_d displayCtrl 93705) ctrlShow (_rem isNotEqualTo "");
(_d displayCtrl 93705) ctrlCommit 0;
private _by = _cy + _ch + safezoneH * 0.07;
// Boutons affichés seulement, côte à côte et de même largeur (aucun trou quand un bouton est masqué).
private _btns = [[93710, _type isNotEqualTo "passport"], [93711, true], [93712, !(_st get "own")], [93714, !(_st get "own")], [93713, true]];
{ (_d displayCtrl (_x select 0)) ctrlShow (_x select 1); } forEach _btns;
_btns = _btns select { _x select 1 };
private _nb = count _btns;
private _bw = (_cw - 0.01 * (_nb - 1)) / _nb;
{
    private _b = _d displayCtrl (_x select 0);
    _b ctrlSetPosition [_cx + _forEachIndex * (_bw + 0.01), _by, _bw, safezoneH * 0.045];
    _b ctrlCommit 0;
} forEach _btns;
if ((_st getOrDefault ["auto", false]) && {!(_st getOrDefault ["shot", false])}) then {
    _st set ["shot", true];
    [{ ["shoot"] call comspec_sse_fnc_idPhoto; }, [], 0.5] call CBA_fnc_waitAndExecute;
};
