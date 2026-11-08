/*
    Bandeau « Supprimé · ANNULER » en haut de la zone de contenu, sur n'importe quelle app (menu du clic droit de l'app
    Carte : route, itinéraire, zone ou plan supprimés). Un seul à la fois ; disparaît après 8 s ou au clic.
    Params : [texte, code d'annulation] | [] (efface le bandeau)
*/
params [["_text", ""], ["_code", {}]];
disableSerialization;
{ ctrlDelete _x; } forEach (uiNamespace getVariable ["COMSPEC_ATAK_UndoSnack", []]);
uiNamespace setVariable ["COMSPEC_ATAK_UndoSnack", []];
if (_text isEqualTo "") exitWith { false };
private _d = [] call comspec_atak_native_fnc_display;
if (isNull _d) exitWith { false };
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["_bx", "_by", "_bw", "_bh"];
private _font = _l get "font";
private _fs = _l get "fontSmall";
private _pad = (_l get "pad") * 2;
private _h = _font * 1.9;
private _w = _bw * 0.9;
private _x0 = _bx + (_bw - _w) / 2;
private _y0 = _by + _pad;
private _list = [];
// Bandeau d'attention (fn_theme) : fond relief, liseré et texte à la couleur « attention ».
private _th = [] call comspec_atak_native_fnc_theme;
private _bg = _d ctrlCreate ["COMSPEC_RscText", -1];
_bg ctrlSetPosition [_x0, _y0, _w, _h]; _bg ctrlSetBackgroundColor ((((_th get "surfaceHi") select [0, 3]) apply { _x + 0.03 }) + [0.97]); _bg ctrlCommit 0;
_list pushBack _bg;
private _rim = _d ctrlCreate ["COMSPEC_RscText", -1];
_rim ctrlSetPosition [_x0, _y0, pixelW * 4, _h]; _rim ctrlSetBackgroundColor (_th get "warn"); _rim ctrlCommit 0;
_list pushBack _rim;
private _t = _d ctrlCreate ["COMSPEC_RscStructuredText", -1];
_t ctrlSetPosition [_x0 + _pad, _y0 + (_h - _font) / 2, _w * 0.68, _h]; _t ctrlCommit 0;
_t ctrlSetStructuredText parseText format ["<t color='%2' font='COMSPEC_BarlowBold'>%1</t>", ((_text) call comspec_atak_native_fnc_tr) regexReplace ["[<>&]", ""], _th get "hexWarn"]; // i18n:brut
_list pushBack _t;
private _b = _d ctrlCreate ["COMSPEC_RscButtonPrimary", -1];
_b ctrlSetPosition [_x0 + _w * 0.72, _y0 + _h * 0.15, _w * 0.26, _h * 0.7]; _b ctrlCommit 0;
_b ctrlSetText (("ANNULER") call comspec_atak_native_fnc_tr);
_b ctrlSetFontHeight ((_fs * 0.95) max COMSPEC_ATAK_FontMin);
_b setVariable ["comspec_undo", _code];
_b ctrlAddEventHandler ["ButtonClick", { params ["_c"]; [{ call (_this getVariable ["comspec_undo", {}]); [] call comspec_atak_native_fnc_undoSnack; }, _c] call comspec_atak_native_fnc_nextFrame; }];
_list pushBack _b;
uiNamespace setVariable ["COMSPEC_ATAK_UndoSnack", _list];
private _tok = diag_tickTime;
uiNamespace setVariable ["COMSPEC_ATAK_UndoSnackTok", _tok];
[{ if ((uiNamespace getVariable ["COMSPEC_ATAK_UndoSnackTok", -1]) isEqualTo _this) then { [] call comspec_atak_native_fnc_undoSnack; }; }, _tok, 8] call comspec_atak_native_fnc_waitAndExec;
true
