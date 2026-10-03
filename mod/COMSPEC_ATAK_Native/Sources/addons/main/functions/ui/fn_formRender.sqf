/*
    Formulaire défilant (pages Athena, Réseau, Réglages, éditeur de marqueur).
    Params : [lignes, rectangle, dans la zone de contenu (true) ou absolu sur le display (false)]
    Lignes :
      ["title", texte]                       ["text", texte structuré]
      ["edit", clé, libellé, valeur]          ["memo", clé, libellé, valeur, nb lignes]
      ["combo", clé, libellé, [[texte, donnée, image, couleur]...], donnée choisie]
      ["toggle", libellé, actif, code]        ["buttons", [[libellé, code, principal]...]]
      ["password", clé, libellé]              ["hero", image, texte structuré]
      ["gap"]
      ["section", titre, sous-titre]        ["switch", libellé, actif, code, aide, imposé]
      ["segment", libellé, [[texte, code, actif]...], aide]   ["info", libellé, valeur]
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
        case "section": {
            // En-tête de section : barre verte, titre et sous-titre.
            _row params ["", "_title", ["_sub", ""]];
            _y = _y + _pad / 2;
            private _bar = ["COMSPEC_RscText", [_pad, _y + _fs * 0.15, _pad * 0.35, _fs * 1.1]] call _mk;
            _bar ctrlSetBackgroundColor [0.36, 0.78, 0.42, 1];
            private _t = ["COMSPEC_RscStructuredText", [_pad * 1.6, _y, _w - _pad, _rowH]] call _mk;
            _t ctrlSetStructuredText parseText format ["<t font='RobotoCondensedBold' color='#5cc76b'>%1</t>%2", toUpper _title, ["", format ["<br/><t size='0.8' color='#8a9a93'>%1</t>", _sub]] select (_sub isNotEqualTo "")];
            private _h = (ctrlTextHeight _t) max (_fs * 1.3);
            _t ctrlSetPosition [_pad * 1.6, _y, _w - _pad, _h];
            _t ctrlCommit 0;
            _y = _y + _h + _pad / 2;
        };
        case "switch": {
            // Ligne réglage : libellé + aide à gauche, interrupteur à droite ; « IMPOSÉ » si la communauté fixe la valeur.
            _row params ["", "_label", "_on", "_code", ["_help", ""], ["_locked", false]];
            private _pillW = _font * 3.6 * pixelH / pixelW;
            private _card = ["COMSPEC_RscText", [_pad, _y, _w, _rowH]] call _mk;
            _card ctrlSetBackgroundColor [0.06, 0.075, 0.068, 1];
            private _t = ["COMSPEC_RscStructuredText", [_pad * 1.5, _y + _pad / 4, _w - _pillW - _pad * 2.5, _rowH]] call _mk;
            _t ctrlSetStructuredText parseText format ["<t size='0.95'>%1</t>%2", _label, ["", format ["<br/><t size='0.75' color='#8a9a93'>%1</t>", _help]] select (_help isNotEqualTo "")];
            private _h = ((ctrlTextHeight _t) + _pad / 2) max _rowH;
            _t ctrlSetPosition [_pad * 1.5, _y + _pad / 4, _w - _pillW - _pad * 2.5, _h - _pad / 2];
            _t ctrlCommit 0;
            _card ctrlSetPosition [_pad, _y, _w, _h];
            _card ctrlCommit 0;
            private _ph = _fs * 1.35;
            private _pill = ["COMSPEC_RscTextCenter", [_pad + _w - _pillW - _pad / 2, _y + (_h - _ph) / 2, _pillW, _ph], [["NON", "OUI"] select _on, format ["IMPOSÉ · %1", ["NON", "OUI"] select _on]] select _locked] call _mk;
            _pill ctrlSetFontHeight (_fs * 0.85);
            _pill ctrlSetBackgroundColor ([[[0.20, 0.23, 0.22, 1], [0.36, 0.78, 0.42, 1]] select _on, [0.55, 0.38, 0.08, 1]] select _locked);
            _pill ctrlSetTextColor ([[0.90, 0.94, 0.91, 1], [0.03, 0.05, 0.04, 1]] select (_on && {!_locked}));
            if !(_locked) then {
                private _b = ["COMSPEC_RscButtonOverlay", [_pad, _y, _w, _h]] call _mk;
                _b ctrlAddEventHandler ["ButtonClick", _code];
            } else {
                _card ctrlSetTooltip "Réglé par votre communauté sur Athena (Contrôle serveur)";
            };
            _y = _y + _h + _pad / 3;
        };
        case "segment": {
            // Choix exclusif en une ligne : libellé au-dessus, boutons accolés, celui choisi en vert.
            _row params ["", "_label", "_opts", ["_help", ""]];
            if (_label isNotEqualTo "") then {
                private _lb = ["COMSPEC_RscStructuredText", [_pad, _y, _w, _fs * 1.3]] call _mk;
                _lb ctrlSetStructuredText parseText format ["<t size='0.9'>%1</t>%2", _label, ["", format ["  <t size='0.75' color='#8a9a93'>%1</t>", _help]] select (_help isNotEqualTo "")];
                _y = _y + _fs * 1.35;
            };
            private _n = count _opts;
            private _sw = _w / (_n max 1);
            {
                _x params ["_text", "_code", ["_active", false]];
                private _b = ["COMSPEC_RscButton", [_pad + _forEachIndex * _sw, _y, _sw - _pad / 6, _rowH * 0.9], _text] call _mk;
                _b ctrlSetFontHeight (_fs * 0.9);
                if (_active) then { _b ctrlSetBackgroundColor [0.36, 0.78, 0.42, 0.95]; _b ctrlSetTextColor [0.03, 0.05, 0.04, 1]; };
                _b ctrlAddEventHandler ["ButtonClick", _code];
            } forEach _opts;
            _y = _y + _rowH * 0.9 + _pad / 2;
        };
        case "info": {
            _row params ["", "_label", "_value"];
            private _a = ["COMSPEC_RscStructuredText", [_pad, _y, _w * 0.5, _fs * 1.3]] call _mk;
            _a ctrlSetStructuredText parseText format ["<t size='0.85' color='#8a9a93'>%1</t>", _label];
            private _b = ["COMSPEC_RscStructuredText", [_pad + _w * 0.4, _y, _w * 0.6, _fs * 1.3]] call _mk;
            _b ctrlSetStructuredText parseText format ["<t size='0.85' align='right'>%1</t>", _value];
            _y = _y + _fs * 1.3;
        };
        case "gap": { _y = _y + _pad; };
        case "hero": {
            _row params ["", "_pic", "_text"];
            private _ih = _font * 3.2;
            private _iw = _ih * pixelH / pixelW; // carré à l'écran
            private _p = ["COMSPEC_RscPicture", [_pad, _y, _iw, _ih], _pic] call _mk;
            _p ctrlSetTextColor [1, 1, 1, 1];
            private _t = ["COMSPEC_RscStructuredText", [_pad * 2 + _iw, _y, _w - _iw - _pad, _ih]] call _mk;
            _t ctrlSetStructuredText parseText _text;
            private _h = (ctrlTextHeight _t) max _ih;
            _t ctrlSetPosition [_pad * 2 + _iw, _y, _w - _iw - _pad, _h];
            _t ctrlCommit 0;
            _y = _y + _h + _pad;
        };
        case "password": {
            // Mot de passe masqué : on garde la vraie valeur à part et on n'affiche que des points.
            _row params ["", "_key", "_label"];
            private _lb = ["COMSPEC_RscLabel", [_pad, _y, _w, _fs * 1.2], _label] call _mk;
            _lb ctrlSetFontHeight (_fs * 0.9);
            _y = _y + _fs * 1.2;
            private _secretVar = format ["COMSPEC_ATAK_Secret_%1", _key];
            private _cur = uiNamespace getVariable [_secretVar, ""];
            private _e = ["COMSPEC_RscEdit", [_pad, _y, _w, _rowH], (_cur splitString "") apply { "•" } joinString ""] call _mk;
            _e ctrlSetFontHeight _font;
            _e setVariable ["secretVar", _secretVar];
            private _redraw = {
                params ["_c"];
                [{ params ["_c"]; if (!isNull _c) then { _c ctrlSetText (((uiNamespace getVariable [_c getVariable "secretVar", ""]) splitString "") apply { "•" } joinString ""); }; }, [_c]] call CBA_fnc_execNextFrame;
            };
            _e setVariable ["redraw", _redraw];
            _e ctrlAddEventHandler ["Char", {
                params ["_c", "_char"];
                private _v = _c getVariable "secretVar";
                uiNamespace setVariable [_v, (uiNamespace getVariable [_v, ""]) + toString [_char]];
                [_c] call (_c getVariable "redraw");
            }];
            _e ctrlAddEventHandler ["KeyDown", {
                params ["_c", "_key"];
                private _v = _c getVariable "secretVar";
                private _cur = uiNamespace getVariable [_v, ""];
                if (_key isEqualTo 14) then { uiNamespace setVariable [_v, _cur select [0, ((count _cur) - 1) max 0]]; [_c] call (_c getVariable "redraw"); };
                if (_key isEqualTo 211) then { uiNamespace setVariable [_v, ""]; [_c] call (_c getVariable "redraw"); };
                false
            }];
            _fields set [_key, _e];
            _y = _y + _rowH + _pad / 2;
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
