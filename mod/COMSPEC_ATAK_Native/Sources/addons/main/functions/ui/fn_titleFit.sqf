/*
    Titre de la barre d'app qui tient dans sa place (4.1.1), jamais sous 14 px et jamais coupé.
    Place : COMSPEC_ATAK_TitleBox (fn_layoutApply : du bouton retour au premier bouton de droite affiché). Dans l'ordre :
      1. titre complet, police réduite au besoin jusqu'au plancher (COMSPEC_ATAK_FontMin, 14 px au moins) ;
      2. téléphone mini en main : bouton de rotation masqué (le sens reste dans Réglages > Emplacement), place reprise ;
      3. abréviation de l'app (attribut « short » de COMSPEC_ATAK_Apps : « CR », « MSG », « DIST »…) ;
      4. icône de l'app à la place du texte.
    Avant : fn_textFit à 65 % au plus, plancher à 85 % de 14 px, dans une place trop étroite : 4 titres coupés (autotest 4.1.0).
    Params : [police voulue, titre complet traduit, abréviation traduite, icône] ; sans titre : ceux du dernier appel
    (fn_layoutApply seul, après un réglage ou un glisser).
*/
params ["_font", ["_full", nil], ["_short", ""], ["_icon", ""]];
disableSerialization;
private _d = [] call comspec_atak_native_fnc_display;
if (isNull _d) exitWith { "" };
if (isNil "_full") then {
    (uiNamespace getVariable ["COMSPEC_ATAK_TitleTexts", []]) params [["_f0", ""], ["_s0", ""], ["_i0", ""]];
    _full = _f0; _short = _s0; _icon = _i0;
} else {
    uiNamespace setVariable ["COMSPEC_ATAK_TitleTexts", [_full, _short, _icon]];
};
private _c = _d displayCtrl 88518;
private _ic = uiNamespace getVariable ["COMSPEC_ATAK_TitleIcon", controlNull];
if (isNull _ic || {(ctrlParent _ic) isNotEqualTo _d}) then {
    _ic = _d ctrlCreate ["COMSPEC_RscPicture", -1];
    _ic ctrlEnable false;
    uiNamespace setVariable ["COMSPEC_ATAK_TitleIcon", _ic];
};
_ic ctrlShow false;
private _box = uiNamespace getVariable ["COMSPEC_ATAK_TitleBox", []];
if ((count _box) < 5) exitWith { _c ctrlSetText _full; _full }; // i18n:brut (déjà traduit)
_box params ["_tx", "_ty", "_tw", "_th", "_tw2"];
// Texture du JPC : coordonnées propres (COMSPEC_ATAK_FontMin vaut 0), plancher à 70 % du corps.
private _floor = [COMSPEC_ATAK_FontMin max (pixelH * 14), _font * 0.7] select (COMSPEC_ATAK_FontMin <= 0);
private _margin = pixelW * 4;
// Hauteur de police à laquelle ce texte tient dans _w (ctrlTextWidth est proportionnel à la hauteur de police).
private _need = {
    params ["_t", "_w"];
    if (_t isEqualTo "") exitWith { -1 };
    _c ctrlSetText _t; // i18n:brut (déjà traduit)
    _c ctrlSetFontHeight _font;
    private _tw0 = ctrlTextWidth _c;
    if (_tw0 <= 0) exitWith { _font };
    (_font * ((_w - _margin) / _tw0)) min _font
};
private _rot = _d displayCtrl 88522;
private _setW = { params ["_w"]; _c ctrlSetPosition [_tx, _ty, _w, _th]; _c ctrlCommit 0; };
[_tw] call _setW;
private _done = false;
{
    _x params ["_t", "_w", "_hideRot"];
    private _h = if (_done || {_t isEqualTo ""} || {_w <= 0}) then { -1 } else { [_t, _w] call _need };
    if (!_done && {_h >= _floor}) then {
        _done = true;
        if (_hideRot) then { _rot ctrlShow false; };
        [_w] call _setW;
        _c ctrlSetText _t; // i18n:brut (déjà traduit)
        _c ctrlSetFontHeight _h;
    };
} forEach [[_full, _tw, false], [_full, [0, _tw2] select ((_tw2 > _tw) && {ctrlShown _rot}), true], [_short, _tw, false], [_short, [0, _tw2] select ((_tw2 > _tw) && {ctrlShown _rot}), true]];
if (_done) exitWith { ctrlText _c };
// Rien ne tient à 14 px : l'icône de l'app à la place du texte (sans icône : le titre au plancher, abrégé avec « … »).
if (_icon isNotEqualTo "") exitWith {
    _c ctrlSetText "";
    private _is = _th * 0.6;
    private _ratio = uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW];
    _ic ctrlSetText _icon; // i18n:brut (chemin)
    _ic ctrlSetTextColor (([] call comspec_atak_native_fnc_theme) get "onChrome");
    _ic ctrlSetPosition [_tx, _ty + (_th - _is) / 2, _is / _ratio, _is];
    _ic ctrlCommit 0;
    _ic ctrlShow (ctrlShown _c);
    ""
};
private _t = [_short, _full] select (_short isEqualTo "");
_c ctrlSetFontHeight _floor;
private _chars = toArray _t;
private _n = count _chars;
_c ctrlSetText _t; // i18n:brut (déjà traduit)
while { _n > 1 && {(ctrlTextWidth _c) > (_tw - _margin)} } do {
    _n = _n - 1;
    _c ctrlSetText ((toString (_chars select [0, _n])) + "…"); // i18n:brut (déjà traduit)
};
ctrlText _c
