/*
    App Reco : note d'observation rapide, envoyée à Athena (/api/recon/notes) par la fonction d'Overwatch
    (reconPushNote), avec un repère d'équipe sur la carte.
    Même famille que l'app FRS / FRM (en-tête sombre, pastilles, cadre de saisie, compteur), mais pensée pour le terrain :
      - en-tête : RECO, heure, lieu le plus proche et carroyage visé ;
      - QUOI : sept tuiles à icône (véhicule, groupe armé, position, mine, civil, infrastructure, autre) ;
      - FIABILITÉ : vu directement / rapporté ;
      - OÙ : carroyage (saisie libre 6 à 10 chiffres), MA POSITION ou SOUS MON REGARD (point visé, 200 m), distance et azimut ;
      - OBSERVATION : 140 caractères avec compteur, puis ENVOYER ;
      - DERNIÈRES NOTES de la session, en cartes de la couleur de leur type.
    Paysage : QUOI et FIABILITÉ à gauche, OÙ et OBSERVATION à droite. Portrait : l'un sous l'autre (défilement).
    Saisie gardée par fn_recoDraftSave (champs recoGrid, recoText ; type et fiabilité dans le brouillon).
*/
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
if !([] call comspec_atak_native_fnc_bridge) exitWith {
    [[["title", "Reco"], ["text", "<t color='#8a9a93'>Les notes de reco partent vers Athena par COMSPEC Overwatch, qui n'est pas chargé sur ce serveur.</t>"]], [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
    true
};
private _font = _l get "font";
private _fs = _l get "fontSmall";
private _pad = (_l get "pad") * 2;
private _land = _l get "landscape";
private _ratio = (uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW]);
private _dir = "\z\comspec_atak_native\addons\main\data\";
private _form = uiNamespace getVariable ["COMSPEC_ATAK_Form", createHashMap];
uiNamespace setVariable ["COMSPEC_ATAK_Form", _form];
private _draft = uiNamespace getVariable ["COMSPEC_ATAK_RecoDraft", createHashMap];
uiNamespace setVariable ["COMSPEC_ATAK_RecoDraft", _draft];
private _hint = uiNamespace getVariable ["COMSPEC_ATAK_RecoHint", ["", false]];

// Teintes : fond nuit, ambre de la reco (distinct du rouge et du violet des FRS), texte.
private _cBg = [0.026, 0.031, 0.028, 1];
private _cHdr = [0.012, 0.015, 0.013, 1];
private _cTile = [0.065, 0.078, 0.071, 1];
private _cField = [0.014, 0.017, 0.015, 1];
private _cLine = [0.16, 0.20, 0.17, 1];
private _cAmber = [0.95, 0.66, 0.18, 1];
private _cText = [0.92, 0.95, 0.93, 1];
private _cDim = [0.55, 0.61, 0.58, 1];
private _cInk = [0.04, 0.035, 0.02, 1];

// Types de note (clés attendues par Athena) : libellé, couleur, icône.
private _types = [
    ["vehicle", "Véhicule", [0.86, 0.38, 0.24, 1], "reco_vehicle"],
    ["armed_group", "Groupe armé", [0.90, 0.26, 0.22, 1], "reco_armed"],
    ["static", "Position statique", [0.92, 0.56, 0.20, 1], "reco_static"],
    ["mine", "Obstacle / mine", [0.96, 0.78, 0.22, 1], "reco_mine"],
    ["civilian", "Civil", [0.36, 0.66, 0.88, 1], "reco_civil"],
    ["infrastructure", "Infrastructure", [0.58, 0.64, 0.74, 1], "reco_infra"],
    ["other", "Autre", [0.62, 0.67, 0.64, 1], "reco_other"]
];
private _tag = _draft getOrDefault ["tag", "armed_group"];
private _conf = _draft getOrDefault ["confidence", "vu_direct"];
private _grid = _draft getOrDefault ["grid", [getPosASL player, 8] call comspec_atak_native_fnc_gridRef];
private _text = _draft getOrDefault ["text", ""];
private _typ = (_types select { (_x select 0) isEqualTo _tag }) param [0, _types select 6];
private _tCol = _typ select 2;

private _mk = { params ["_c", "_p", ["_t", ""]]; [_c, _p, _t] call comspec_atak_native_fnc_pageCtrl };
private _box = { params ["_rect", "_rgba"]; private _c = ["COMSPEC_RscText", _rect] call _mk; _c ctrlSetBackgroundColor _rgba; _c };
private _lbl = {
    params ["_cls", "_rect", "_t", ["_fh", _fs], ["_col", _cText], ["_bold", false]];
    private _c = [_cls, _rect, _t] call _mk;
    _c ctrlSetFontHeight _fh;
    _c ctrlSetTextColor _col;
    if (_bold) then { _c ctrlSetFont "RobotoCondensedBold"; };
    _c
};
private _hit = {
    params ["_rect", "_code", ["_tip", ""]];
    private _b = ["COMSPEC_RscButtonInvisible", _rect] call _mk;
    _b ctrlAddEventHandler ["ButtonClick", _code];
    if (_tip isNotEqualTo "") then { _b ctrlSetTooltip ((_tip) call comspec_atak_native_fnc_tr); };
    _b
};
private _esc = { params ["_t"]; { _t = [_t, _x select 0, _x select 1] call CBA_fnc_replace; } forEach [["&", "&amp;"], ["<", "&lt;"], [">", "&gt;"]]; _t };
// Titre de section : libellé en capitales, valeur à droite, filet dessous ; renvoie le y du contenu.
private _sec = {
    params ["_x0", "_y0", "_w", "_t", ["_right", ""], ["_rightCol", _cDim]];
    [ "COMSPEC_RscText", [_x0, _y0, _w * 0.7, _fs * 1.3], _t, _fs * 0.8, _cDim, true] call _lbl;
    if (_right isNotEqualTo "") then { ["COMSPEC_RscTextRight", [_x0 + _w * 0.3, _y0, _w * 0.7, _fs * 1.3], _right, _fs * 0.8, _rightCol, true] call _lbl; };
    [[_x0, _y0 + _fs * 1.3, _w, pixelH], _cLine] call _box;
    _y0 + _fs * 1.3 + _pad * 0.6
};
// Rendu suivant : la saisie en cours est gardée d'abord.
private _redo = "[] call comspec_atak_native_fnc_recoDraftSave; %1 [{ ['RECO'] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;";

private _bg = [[0, 0, _bw, _bh], _cBg] call _box;

// ---------- En-tête ----------
private _hdrH = _font * 2.7;
[[0, 0, _bw, _hdrH], _cHdr] call _box;
[[0, 0, pixelW * 4, _hdrH], _cAmber] call _box;
[[0, _hdrH - pixelH * 2, _bw, pixelH * 2], [_tCol select 0, _tCol select 1, _tCol select 2, 0.7]] call _box;
["COMSPEC_RscText", [_pad, _hdrH * 0.08, _bw * 0.5, _font * 1.3], "RECO", _font * 1.2, _cAmber, true] call _lbl;
["COMSPEC_RscText", [_pad, _hdrH * 0.08 + _font * 1.25, _bw * 0.5, _fs * 1.2], format ["NOTE D'OBSERVATION · %1", [dayTime, "HH:MM"] call BIS_fnc_timeToString], _fs * 0.8, _cDim] call _lbl;
private _place = "";
private _loc = nearestLocations [getPos player, ["NameCityCapital", "NameCity", "NameVillage", "NameLocal"], 3000];
if ((count _loc) > 0) then { _place = toUpper text (_loc select 0); };
["COMSPEC_RscTextRight", [_bw * 0.45, _hdrH * 0.08, _bw * 0.55 - _pad, _font * 1.3], [_place, "—"] select (_place isEqualTo ""), _fs, _cText, true] call _lbl;
// Pastilles : type et fiabilité choisis.
private _chipX = _bw - _pad;
private _chipY = _hdrH * 0.08 + _font * 1.3;
{
    _x params ["_t", "_rgba", "_fg"];
    private _c = ["COMSPEC_RscTextCenter", [0, _chipY, 0.1, _fs * 1.15], toUpper _t, _fs * 0.72, _fg, true] call _lbl;
    private _w = (ctrlTextWidth _c) + _pad;
    _chipX = _chipX - _w;
    _c ctrlSetPosition [_chipX, _chipY, _w, _fs * 1.15];
    _c ctrlSetBackgroundColor _rgba;
    _c ctrlCommit 0;
    _chipX = _chipX - _pad / 3;
} forEach [[["Rapporté", "Vu directement"] select (_conf isEqualTo "vu_direct"), [0.13, 0.16, 0.14, 1], _cText], [_typ select 1, _tCol, _cInk]];

// ---------- Colonnes ----------
private _y0 = _hdrH + _pad;
private _wL = [_bw - 2 * _pad, (_bw - 3 * _pad) * 0.54] select _land;
private _xR = [_pad, _pad * 2 + _wL] select _land;
private _wR = [_bw - 2 * _pad, _bw - _xR - _pad] select _land;

// QUOI : tuiles à icône, 4 par rangée.
private _y = [_pad, _y0, _wL, "Quoi", _typ select 1, _tCol] call _sec;
private _gap = _pad * 0.5;
private _tw = (_wL - 3 * _gap) / 4;
private _th = ((_tw * _ratio * 0.8) max (_font * 2.6)) min (_font * 3.8);
{
    _x params ["_k", "_t", "_rgba", "_icon"];
    private _on = _k isEqualTo _tag;
    private _tx = _pad + (_forEachIndex mod 4) * (_tw + _gap);
    private _ty = _y + (floor (_forEachIndex / 4)) * (_th + _gap);
    [[_tx, _ty, _tw, _th], [_cTile, [(_rgba select 0) * 0.30, (_rgba select 1) * 0.30, (_rgba select 2) * 0.30, 1]] select _on] call _box;
    [[_tx, _ty, _tw, pixelH * ([1, 3] select _on)], [[1, 1, 1, 0.07], _rgba] select _on] call _box;
    private _ih = _th * 0.40;
    private _ic = ["COMSPEC_RscPicture", [_tx + (_tw - _ih / _ratio) / 2, _ty + _th * 0.12, _ih / _ratio, _ih], _dir + _icon + ".paa"] call _mk;
    _ic ctrlSetTextColor ([[0.70, 0.76, 0.73, 1], _rgba] select _on);
    private _c = ["COMSPEC_RscTextCenter", [_tx, _ty + _th * 0.60, _tw, _th * 0.3], _t, _fs * 0.8, [_cDim, _cText] select _on, _on] call _lbl;
    private _fh = _fs * 0.8;
    while { (ctrlTextWidth _c) > _tw * 0.94 && {_fh > _fs * 0.55} } do { _fh = _fh * 0.92; _c ctrlSetFontHeight _fh; };
    [[_tx, _ty, _tw, _th], compile format [_redo, format ["(uiNamespace getVariable ['COMSPEC_ATAK_RecoDraft', createHashMap]) set ['tag', '%1'];", _k]], _t] call _hit;
} forEach _types;
_y = _y + 2 * (_th + _gap) + _pad * 0.4;

// FIABILITÉ : deux choix côte à côte.
_y = [_pad, _y, _wL, "Fiabilité"] call _sec;
private _bh2 = _font * 1.7;
{
    _x params ["_k", "_t", "_sub"];
    private _on = _k isEqualTo _conf;
    private _bx = _pad + _forEachIndex * ((_wL + _gap) / 2);
    private _bww = (_wL - _gap) / 2;
    [[_bx, _y, _bww, _bh2], [_cTile, [0.30, 0.21, 0.06, 1]] select _on] call _box;
    [[_bx, _y + _bh2 - pixelH * 2, _bww, pixelH * 2], [[1, 1, 1, 0.05], _cAmber] select _on] call _box;
    private _c = ["COMSPEC_RscStructuredText", [_bx, _y + _bh2 * 0.1, _bww, _bh2 * 0.9]] call _mk;
    _c ctrlSetStructuredText parseText ((format ["<t align='center' font='RobotoCondensedBold' color='%1'>%2 %3</t><br/><t align='center' size='0.75' color='#8a9a93'>%4</t>",
        ["#c9d2cd", "#f2a92e"] select _on, ["○", "●"] select _on, _t, _sub]) call comspec_atak_native_fnc_tr);
    _c ctrlSetFontHeight _fs;
    [[_bx, _y, _bww, _bh2], compile format [_redo, format ["(uiNamespace getVariable ['COMSPEC_ATAK_RecoDraft', createHashMap]) set ['confidence', '%1'];", _k]], _t] call _hit;
} forEach [["vu_direct", "VU DIRECTEMENT", "par moi ou mon équipe"], ["rapporte", "RAPPORTÉ", "par un tiers, à confirmer"]];
private _yL = _y + _bh2 + _pad;

// ---------- OÙ ----------
_y = [_xR, [_yL, _y0] select _land, _wR, "Où"] call _sec;
private _rowH = _font * 1.6;
private _ew = _wR * 0.52;
[[_xR, _y, _ew, _rowH], _cField] call _box;
[[_xR, _y + _rowH - pixelH, _ew, pixelH], [_cAmber select 0, _cAmber select 1, _cAmber select 2, 0.6]] call _box;
private _gE = ["COMSPEC_RscEdit", [_xR, _y, _ew, _rowH], _grid] call _mk;
_gE ctrlSetBackgroundColor [0, 0, 0, 0];
_gE ctrlSetFont "EtelkaMonospacePro";
_gE ctrlSetFontHeight (_font * 0.95);
_gE ctrlSetTooltip (("Carroyage de 6, 8 ou 10 chiffres, ex. 1518 1730") call comspec_atak_native_fnc_tr);
_form set ["recoGrid", _gE];
// Distance et azimut depuis moi.
private _info = "";
private _gClean = (_grid splitString " ") joinString "";
private _gp = if (_gClean regexMatch "^[0-9]{6,10}$" && {((count _gClean) mod 2) isEqualTo 0}) then { ([_gClean] call BIS_fnc_gridToPos) param [0, []] } else { [] };
if ((count _gp) >= 2) then {
    private _dd = player distance2D _gp;
    _info = if (_dd < 15) then { "ICI" } else { format ["%1 · %2°", [format ["%1 m", round _dd], format ["%1 km", (_dd / 1000) toFixed 1]] select (_dd >= 1000), round (player getDir _gp)] };
};
["COMSPEC_RscTextRight", [_xR + _ew, _y, _wR - _ew, _rowH], [_info, "grille illisible"] select (_info isEqualTo ""), _fs * 0.85, [_cText, [0.95, 0.42, 0.36, 1]] select (_info isEqualTo "")] call _lbl;
_y = _y + _rowH + _pad * 0.6;
private _qb = (_wR - _gap) / 2;
{
    _x params ["_t", "_icon", "_set", "_tip"];
    private _bx = _xR + _forEachIndex * (_qb + _gap);
    [[_bx, _y, _qb, _rowH], _cTile] call _box;
    private _ih = _rowH * 0.6;
    private _ic = ["COMSPEC_RscPicture", [_bx + _pad * 0.5, _y + _rowH * 0.2, _ih / _ratio, _ih], _dir + _icon + ".paa"] call _mk;
    _ic ctrlSetTextColor _cAmber;
    private _c = ["COMSPEC_RscText", [_bx + _pad + _ih / _ratio, _y, _qb - _pad * 1.5 - _ih / _ratio, _rowH], _t, _fs * 0.85, _cText, true] call _lbl;
    private _fh = _fs * 0.85;
    while { (ctrlTextWidth _c) > (_qb - _pad * 1.5 - _ih / _ratio) && {_fh > _fs * 0.6} } do { _fh = _fh * 0.92; _c ctrlSetFontHeight _fh; };
    [[_bx, _y, _qb, _rowH], compile format [_redo, _set], _tip] call _hit;
} forEach [
    ["MA POSITION", "ui_gps", "(uiNamespace getVariable ['COMSPEC_ATAK_RecoDraft', createHashMap]) set ['grid', [getPosASL player, 8] call comspec_atak_native_fnc_gridRef];", "Ma position"],
    ["SOUS MON REGARD", "reco_eye", "(uiNamespace getVariable ['COMSPEC_ATAK_RecoDraft', createHashMap]) set ['grid', [([] call comspec_overwatch_connect_fnc_reconLookPos), 8] call comspec_atak_native_fnc_gridRef];", "Point visé, jusqu'à 200 m"]
];
_y = _y + _rowH + _pad;

// ---------- OBSERVATION ----------
private _n = count _text;
private _cnt = ["COMSPEC_RscTextRight", [_xR + _wR * 0.3, _y, _wR * 0.7, _fs * 1.3], format ["%1/140", _n], _fs * 0.8, [_cDim, [0.95, 0.42, 0.36, 1]] select (_n > 140), true] call _lbl;
_y = [_xR, _y, _wR, "Observation"] call _sec;
private _memoH = (_font * 4.2) max ([_bh - _y - _font * 2 - _pad * 3, _font * 4.2] select (!_land));
_memoH = _memoH min (_font * 7);
[[_xR, _y, _wR, _memoH], _cField] call _box;
[[_xR, _y, pixelW * 2, _memoH], [_tCol select 0, _tCol select 1, _tCol select 2, 0.8]] call _box;
private _ph = ["COMSPEC_RscStructuredText", [_xR + _pad * 0.6, _y + _pad * 0.3, _wR - _pad * 1.2, _fs * 2.6]] call _mk;
_ph ctrlSetStructuredText parseText (("<t size='0.8' color='#6f7a74'>Ce que vous voyez, combien, ce qu'ils font, vers où. Ex. : 2 pick-up armés, 6 PAX, direction nord, en mouvement.</t>") call comspec_atak_native_fnc_tr);
_ph ctrlShow (_n isEqualTo 0);
private _tE = ["COMSPEC_RscEditMulti", [_xR + _pad * 0.3, _y, _wR - _pad * 0.3, _memoH], _text] call _mk;
_tE ctrlSetBackgroundColor [0, 0, 0, 0];
_tE ctrlSetFontHeight (_font * 0.95);
_form set ["recoText", _tE];
uiNamespace setVariable ["COMSPEC_ATAK_RecoCount", [_cnt, _ph]];
_tE ctrlAddEventHandler ["KeyUp", {
    params ["_e"];
    (uiNamespace getVariable ["COMSPEC_ATAK_RecoCount", []]) params [["_c", controlNull], ["_p", controlNull]];
    private _k = count (ctrlText _e);
    _c ctrlSetText ((format ["%1/140", _k]) call comspec_atak_native_fnc_tr);
    _c ctrlSetTextColor ([[0.55, 0.61, 0.58, 1], [0.95, 0.42, 0.36, 1]] select (_k > 140));
    _p ctrlShow (_k isEqualTo 0);
}];
_tE ctrlAddEventHandler ["SetFocus", { ((uiNamespace getVariable ["COMSPEC_ATAK_RecoCount", []]) param [1, controlNull]) ctrlShow false; }];
_y = _y + _memoH + _pad * 0.7;

// ENVOYER
private _sh = _font * 1.9;
[[_xR, _y, _wR, _sh], _cAmber] call _box;
private _si = ["COMSPEC_RscPicture", [_xR + _pad, _y + _sh * 0.22, _sh * 0.56 / _ratio, _sh * 0.56], _dir + "ui_send.paa"] call _mk;
_si ctrlSetTextColor _cInk;
["COMSPEC_RscTextCenter", [_xR, _y, _wR, _sh], "ENVOYER LA NOTE", _font, _cInk, true] call _lbl;
[[_xR, _y, _wR, _sh], { [{ [] call comspec_atak_native_fnc_recoSubmit; }] call CBA_fnc_execNextFrame; }, "Repère d'équipe sur la carte et note envoyée à Athena"] call _hit;
_y = _y + _sh + _pad * 0.5;
if ((_hint select 0) isNotEqualTo "") then {
    private _h = ["COMSPEC_RscStructuredText", [_xR, _y, _wR, _fs * 2.4]] call _mk;
    _h ctrlSetStructuredText parseText ((format ["<t size='0.8' color='%1'>%2</t>", ["#7aa89a", "#e8b84a"] select (_hint select 1), [_hint select 0] call _esc]) call comspec_atak_native_fnc_tr);
    _y = _y + ((ctrlTextHeight _h) max (_fs * 1.2)) + _pad * 0.3;
};
private _yR = _y;

// ---------- DERNIÈRES NOTES ----------
private _log = missionNamespace getVariable ["COMSPEC_ReconNoteLog", []];
if !(_log isEqualType []) then { _log = []; };
_y = [_pad, (_yL max _yR) + _pad * 0.5, _bw - 2 * _pad, "Dernières notes", str (count _log)] call _sec;
if ((count _log) isEqualTo 0) then {
    ["COMSPEC_RscText", [_pad, _y, _bw - 2 * _pad, _fs * 1.4], "Aucune note pendant cette session.", _fs * 0.85, _cDim] call _lbl;
    _y = _y + _fs * 1.4 + _pad;
};
{
    _x params [["_lab", ""], ["_tx", ""], ["_g", ""]];
    // Couleur d'après le libellé renvoyé par Overwatch (clé ou libellé français).
    private _ty = (_types select { (_x select 0) isEqualTo _lab || {(toLower (_x select 1)) isEqualTo (toLower _lab)} }) param [0, _types select 6];
    private _col = _ty select 2;
    private _ch = _fs * 2.9;
    [[_pad, _y, _bw - 2 * _pad, _ch], _cTile] call _box;
    [[_pad, _y, pixelW * 4, _ch], _col] call _box;
    private _ic = ["COMSPEC_RscPicture", [_pad * 1.6, _y + _ch * 0.22, _ch * 0.56 / _ratio, _ch * 0.56], _dir + (_ty select 3) + ".paa"] call _mk;
    _ic ctrlSetTextColor _col;
    private _c = ["COMSPEC_RscStructuredText", [_pad * 2.2 + _ch * 0.56 / _ratio, _y + _ch * 0.08, _bw - _pad * 4 - _ch * 0.56 / _ratio, _ch * 0.9]] call _mk;
    _c ctrlSetStructuredText parseText ((format ["<t font='RobotoCondensedBold' color='%1'>%2</t>  <t font='EtelkaMonospacePro' size='0.8' color='#8a9a93'>%3</t><br/><t size='0.85'>%4</t>",
        (_col select [0, 3]) call BIS_fnc_colorRGBtoHTML, [toUpper _lab] call _esc, [_g] call _esc, [[_tx, "—"] select (_tx isEqualTo "")] call _esc]) call comspec_atak_native_fnc_tr);
    _c ctrlSetFontHeight _fs;
    _y = _y + _ch + _pad * 0.4;
} forEach _log;
_y = _y + _pad;
_bg ctrlSetPosition [0, 0, _bw, _bh max _y];
_bg ctrlCommit 0;
true
