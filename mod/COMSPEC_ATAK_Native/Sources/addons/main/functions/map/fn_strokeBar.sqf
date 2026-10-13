/*
    Barre des outils TRAIT et DESSIN LIBRE de la carte (fn_pageMap, sous la consigne) : couleur, épaisseur,
    flèche, ZONE (forme fermée, dessin libre), ANNULER (mon dernier trait) et EFFACER MES TRAITS.
    Réglages gardés dans le profil (lus par fn_markerStroke et fn_strokeDraw).
    Params : [[x, y, largeur disponible]]
*/
params [["_rect", [0, 0, 0.3]]];
disableSerialization;
_rect params ["_x0", "_y0", "_wMax"];
private _l = [] call comspec_atak_native_fnc_layoutGet;
private _fs = _l get "fontSmall";
private _pad = (_l get "pad") * 2;
private _ratio = (uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW]);
private _mk = { params ["_class", "_pos", ["_text", ""]]; [_class, _pos, _text, false] call comspec_atak_native_fnc_pageCtrl };
private _set = { params ["_var", "_val"]; compile format ["profileNamespace setVariable [%1, %2]; saveProfileNamespace; [{ ['MAP'] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;", str _var, _val] };
private _h = _fs * 1.55;
private _gap = _pad / 3;
private _draw = ((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["mapMode", ""]) isEqualTo "DRAW";
// Fond commun : la barre se lit sur toutes les cartes.
private _cur = profileNamespace getVariable ["COMSPEC_ATAK_DrawColor", "ColorRed"];
private _cols = [["ColorRed", [0.9, 0.1, 0.1, 1]], ["ColorOrange", [0.95, 0.5, 0.1, 1]], ["ColorYellow", [0.95, 0.85, 0.1, 1]], ["ColorGreen", [0.1, 0.7, 0.2, 1]],
    ["ColorBlue", [0.1, 0.3, 0.9, 1]], ["ColorPink", [0.9, 0.4, 0.7, 1]], ["ColorBlack", [0.05, 0.05, 0.05, 1]], ["ColorWhite", [1, 1, 1, 1]]];
private _cw = _h / _ratio;
private _widths = [["FIN", 1], ["MOY.", 2], ["ÉPAIS", 3]];
private _ww = _fs * 2.6 / _ratio * 0.62;
private _row1W = (count _cols) * (_cw + _gap) + (count _widths) * (_ww + _gap) + _gap;
private _bg = ["COMSPEC_RscMapPanel", [_x0 - _gap, _y0 - _gap, (_row1W min _wMax) + _gap, _h * 2 + _gap * 3]] call _mk;
_bg ctrlSetBackgroundColor [0.025, 0.030, 0.027, 0.9];
private _px = _x0;
{
    _x params ["_cls", "_rgb"];
    private _c = ["COMSPEC_RscButton", [_px, _y0, _cw, _h], ["", "●"] select (_cls isEqualTo _cur)] call _mk;
    _c ctrlSetBackgroundColor _rgb;
    _c ctrlSetTextColor ([[1, 1, 1, 1], [0, 0, 0, 1]] select (_cls in ["ColorWhite", "ColorYellow"]));
    _c ctrlAddEventHandler ["ButtonClick", ["COMSPEC_ATAK_DrawColor", str _cls] call _set];
    _px = _px + _cw + _gap;
} forEach _cols;
private _wCur = profileNamespace getVariable ["COMSPEC_ATAK_DrawWidth", 2];
{
    _x params ["_t", "_v"];
    private _b = ["COMSPEC_RscButton", [_x0 + (count _cols) * (_cw + _gap) + _gap + _forEachIndex * (_ww + _gap), _y0, _ww, _h], _t] call _mk;
    _b ctrlSetFontHeight ((_fs * 0.8) max COMSPEC_ATAK_FontMin);
    if (_v isEqualTo _wCur) then { _b ctrlSetBackgroundColor [0.36, 0.78, 0.42, 0.95]; _b ctrlSetTextColor [0.03, 0.05, 0.04, 1]; };
    _b ctrlSetTooltip (("Épaisseur du trait (vue par tous les joueurs)") call comspec_atak_native_fnc_tr);
    _b ctrlAddEventHandler ["ButtonClick", ["COMSPEC_ATAK_DrawWidth", str _v] call _set];
} forEach _widths;
// Seconde rangée : options et corrections.
private _y1 = _y0 + _h + _gap;
private _arrow = profileNamespace getVariable ["COMSPEC_ATAK_DrawArrow", false];
private _closed = profileNamespace getVariable ["COMSPEC_ATAK_DrawClosed", false];
private _btns = [
    ["FLÈCHE", ["COMSPEC_ATAK_DrawArrow", str !_arrow] call _set, _arrow, "Pointe de flèche au bout du trait, dans le sens du tracé"]
];
if (_draw) then { _btns pushBack ["ZONE", ["COMSPEC_ATAK_DrawClosed", str !_closed] call _set, _closed, "Forme fermée : le dessin revient à son point de départ"]; };
_btns append [
    ["ANNULER", { ["UNDO"] call comspec_atak_native_fnc_markerStroke; }, false, "Efface mon dernier trait"],
    ["EFF. MES TRAITS", { ["CLEARMINE"] call comspec_atak_native_fnc_markerStroke; }, false, "Efface tous mes traits et dessins"]
];
private _bw = (((_row1W min _wMax) - _gap * ((count _btns) - 1)) / (count _btns)) max (_fs * 2 / _ratio);
{
    _x params ["_t", "_code", "_on", "_tip"];
    private _b = ["COMSPEC_RscButton", [_x0 + _forEachIndex * (_bw + _gap), _y1, _bw, _h], _t] call _mk;
    _b ctrlSetFontHeight ((_fs * 0.8) max COMSPEC_ATAK_FontMin);
    if (_on) then { _b ctrlSetBackgroundColor [0.36, 0.78, 0.42, 0.95]; _b ctrlSetTextColor [0.03, 0.05, 0.04, 1]; };
    _b ctrlSetTooltip ((_tip) call comspec_atak_native_fnc_tr);
    _b ctrlAddEventHandler ["ButtonClick", _code];
} forEach _btns;
true
