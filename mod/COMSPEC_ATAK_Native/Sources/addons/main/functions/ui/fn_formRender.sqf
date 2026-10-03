/*
    Formulaire défilant (pages Athena, Réseau, Réglages, éditeur de marqueur).
    Params : [lignes, rectangle, dans la zone de contenu (true) ou absolu sur le display (false)]
    Lignes :
      ["title", texte]                       ["text", texte structuré]
      ["edit", clé, libellé, valeur]          ["memo", clé, libellé, valeur, nb lignes]
      ["combo", clé, libellé, [[texte, donnée, image, couleur]...], donnée choisie]
      ["toggle", libellé, actif, code]        ["buttons", [[libellé, code, principal]...]]
    Les champs sont lisibles ensuite par [clé] call comspec_atak_native_fnc_formValue.
*/
params ["_rows", "_rect", ["_inContent", true]];
disableSerialization;
private _d = [] call comspec_atak_native_fnc_display;
private _l = [] call comspec_atak_native_fnc_layoutGet;
private _font = _l get "font";
private _fs = _l get "fontSmall";
private _pad = (_l get "pad") * 2;
_rect params ["_rx", "_ry", "_rw", "_rh"];
private _bg = ["COMSPEC_RscPanel", _rect, "", _inContent] call comspec_atak_native_fnc_pageCtrl;
_bg ctrlSetBackgroundColor [0.035, 0.045, 0.04, 0.96];
private _grp = ["COMSPEC_RscControlsGroup", _rect, "", _inContent] call comspec_atak_native_fnc_pageCtrl;
private _w = _rw - 0.014 - 2 * _pad;
private _rowH = _font * 1.55;
private _y = _pad;
private _fields = uiNamespace getVariable ["COMSPEC_ATAK_Form", createHashMap];
private _mk = {
    params ["_class", "_pos", ["_text", ""]];
    private _c = _d ctrlCreate [_class, -1, _grp];
    _c ctrlSetPosition _pos;
    if (_text isNotEqualTo "") then { _c ctrlSetText _text; };
    _c ctrlCommit 0;
    _c
};
{
    private _row = _x;
    switch (_row select 0) do {
        case "title": {
            private _t = ["COMSPEC_RscText", [_pad, _y, _w, _rowH], toUpper (_row select 1)] call _mk;
            _t ctrlSetFontHeight _fs;
            _t ctrlSetTextColor [0.36, 0.78, 0.42, 1];
            _y = _y + _rowH;
        };
        case "text": {
            private _t = ["COMSPEC_RscStructuredText", [_pad, _y, _w, _rowH]] call _mk;
            _t ctrlSetStructuredText parseText (_row select 1);
            private _h = (ctrlTextHeight _t) max (_fs * 1.2);
            _t ctrlSetPosition [_pad, _y, _w, _h];
            _t ctrlCommit 0;
            _y = _y + _h + _pad / 2;
        };
        case "edit";
        case "memo": {
            _row params ["_kind", "_key", "_label", ["_value", ""], ["_lines", 3]];
            private _lb = ["COMSPEC_RscLabel", [_pad, _y, _w, _fs * 1.2], _label] call _mk;
            _lb ctrlSetFontHeight (_fs * 0.9);
            _y = _y + _fs * 1.2;
            private _h = [_rowH, _font * 1.15 * _lines + _font * 0.3] select (_kind isEqualTo "memo");
            private _e = [["COMSPEC_RscEdit", "COMSPEC_RscEditMulti"] select (_kind isEqualTo "memo"), [_pad, _y, _w, _h], _value] call _mk;
            _e ctrlSetFontHeight _font;
            _fields set [_key, _e];
            _y = _y + _h + _pad / 2;
        };
        case "combo": {
            _row params ["", "_key", "_label", "_items", ["_selData", ""]];
            private _lb = ["COMSPEC_RscLabel", [_pad, _y, _w, _fs * 1.2], _label] call _mk;
            _lb ctrlSetFontHeight (_fs * 0.9);
            _y = _y + _fs * 1.2;
            private _c = ["COMSPEC_RscCombo", [_pad, _y, _w, _rowH]] call _mk;
            _c ctrlSetFontHeight _font;
            private _sel = 0;
            {
                _x params ["_text", "_data", ["_pic", ""], ["_color", []]];
                private _i = _c lbAdd _text;
                _c lbSetData [_i, _data];
                if (_pic isNotEqualTo "") then { _c lbSetPicture [_i, _pic]; };
                if ((count _color) isEqualTo 4) then { _c lbSetPictureColor [_i, _color]; _c lbSetPictureColorSelected [_i, _color]; };
                if (_data isEqualTo _selData) then { _sel = _i; };
            } forEach _items;
            _c lbSetCurSel _sel;
            _fields set [_key, _c];
            _y = _y + _rowH + _pad / 2;
        };
        case "toggle": {
            _row params ["", "_label", "_on", "_code"];
            private _b = ["COMSPEC_RscButton", [_pad, _y, _w, _rowH], format ["%1   %2", ["○", "●"] select _on, _label]] call _mk;
            _b ctrlSetFontHeight _fs;
            if (_on) then { _b ctrlSetTextColor [0.36, 0.78, 0.42, 1]; };
            _b ctrlAddEventHandler ["ButtonClick", _code];
            _y = _y + _rowH + _pad / 3;
        };
        case "buttons": {
            private _btns = _row select 1;
            private _n = count _btns;
            private _bw = (_w - (_n - 1) * _pad / 2) / (_n max 1);
            {
                _x params ["_label", "_code", ["_primary", false]];
                private _b = [["COMSPEC_RscButton", "COMSPEC_RscButtonPrimary"] select _primary, [_pad + _forEachIndex * (_bw + _pad / 2), _y, _bw, _rowH], _label] call _mk;
                _b ctrlSetFontHeight _fs;
                _b ctrlAddEventHandler ["ButtonClick", _code];
            } forEach _btns;
            _y = _y + _rowH + _pad / 2;
        };
    };
} forEach _rows;
uiNamespace setVariable ["COMSPEC_ATAK_Form", _fields];
_grp
