/*
    Formulaire vanilla ouvert par-dessus l'interface Zeus (sans ZEN), pour les modules COMSPEC ATAK.
    Params : [titre, champs, code, arguments, aide]
      Champs : ["EDIT", libellé, valeur]  ["MEMO", libellé, valeur, lignes]  ["COMBO", libellé, [[texte, donnée]...], donnée]
               ["CHECK", libellé, valeur]  ["SLIDER", libellé, [min, max, valeur]]
      Code : appelé avec [[valeurs dans l'ordre des champs], arguments] à la validation.
*/
params [["_title", ""], ["_fields", []], ["_code", {}], ["_args", []], ["_help", ""]];
disableSerialization;
private _parent = findDisplay 312;
if (isNull _parent) then { _parent = findDisplay 46; };
if (isNull _parent) exitWith { false };
private _d = _parent createDisplay "RscDisplayEmpty";
if (isNull _d) exitWith { false };
private _w = safeZoneW * 0.42;
private _x0 = safeZoneX + (safeZoneW - _w) / 2;
private _rowH = safeZoneH * 0.034;
private _gap = safeZoneH * 0.008;
private _labW = _w * 0.36;
private _inH = 0;
// Le 4e élément n'est un nombre de lignes que pour MEMO (COMBO y range sa donnée) : pas de select qui évaluerait les deux.
{ _inH = _inH + _gap + (if ((_x select 0) isEqualTo "MEMO") then { _rowH * ((_x param [3, 4]) max 2) } else { _rowH }); } forEach _fields;
private _helpH = [0, _rowH * 1.6] select (_help isNotEqualTo "");
private _bodyH = (_inH + _gap) min (safeZoneH * 0.7);
private _h = _rowH * 1.3 + _helpH + _bodyH + _rowH * 1.6;
private _y0 = safeZoneY + (safeZoneH - _h) / 2;
private _mk = {
    params ["_cls", "_pos", ["_grp", controlNull]];
    private _c = if (isNull _grp) then { _d ctrlCreate [_cls, -1] } else { _d ctrlCreate [_cls, -1, _grp] };
    _c ctrlSetPosition _pos;
    _c ctrlCommit 0;
    _c
};
private _bg = ["COMSPEC_RscPanel", [_x0, _y0, _w, _h]] call _mk;
_bg ctrlSetBackgroundColor [0.035, 0.045, 0.04, 0.97];
private _head = ["COMSPEC_RscText", [_x0, _y0, _w, _rowH * 1.3]] call _mk;
_head ctrlSetBackgroundColor [0.10, 0.20, 0.12, 1];
_head ctrlSetText ((("  " + _title)) call comspec_atak_native_fnc_tr);
_head ctrlSetFont "RobotoCondensedBold";
private _y = _y0 + _rowH * 1.3;
if (_help isNotEqualTo "") then {
    private _hc = ["COMSPEC_RscStructuredText", [_x0 + _gap, _y + _gap / 2, _w - 2 * _gap, _helpH]] call _mk;
    _hc ctrlSetStructuredText parseText ((format ["<t size='0.85' color='#8a9a93'>%1</t>", _help]) call comspec_atak_native_fnc_tr);
    _y = _y + _helpH;
};
private _grp = ["COMSPEC_RscControlsGroup", [_x0, _y, _w, _bodyH]] call _mk;
private _gy = _gap;
private _ctrls = [];
{
    _x params ["_kind", "_label", ["_val", ""], ["_extra", 4]];
    // Même piège que plus haut : COMBO range sa donnée (texte) en 4e élément, « max » ne doit voir que MEMO.
    private _lh = if (_kind isEqualTo "MEMO") then { _rowH * (_extra max 2) } else { _rowH };
    private _lb = ["COMSPEC_RscText", [_gap, _gy, _labW, _rowH], _grp] call _mk;
    _lb ctrlSetText ((_label) call comspec_atak_native_fnc_tr);
    private _ix = _labW + _gap;
    private _iw = _w - _ix - _gap * 3;
    private _c = controlNull;
    switch (_kind) do {
        case "EDIT": { _c = ["COMSPEC_RscEdit", [_ix, _gy, _iw, _rowH], _grp] call _mk; _c ctrlSetText ([str _val, _val] select (_val isEqualType "")); }; // i18n:brut
        case "MEMO": { _c = ["COMSPEC_RscEditMulti", [_ix, _gy, _iw, _lh], _grp] call _mk; _c ctrlSetText _val; }; // i18n:brut
        case "COMBO": {
            _c = ["COMSPEC_RscCombo", [_ix, _gy, _iw, _rowH], _grp] call _mk;
            private _sel = 0;
            { _x params ["_t", "_v"]; private _i = _c lbAdd ((_t) call comspec_atak_native_fnc_tr); _c lbSetData [_i, str _v]; if (_v isEqualTo _extra) then { _sel = _i; }; } forEach _val;
            _c lbSetCurSel _sel;
            _c setVariable ["values", _val apply { _x select 1 }];
        };
        case "CHECK": { _c = ["COMSPEC_RscCheckbox", [_ix, _gy, _rowH * pixelW / pixelH, _rowH], _grp] call _mk; _c cbSetChecked _val; };
        case "SLIDER": {
            _val params [["_min", 0], ["_max", 100], ["_cur", 0]];
            _c = ["COMSPEC_RscSlider", [_ix, _gy, _iw * 0.78, _rowH], _grp] call _mk;
            _c sliderSetRange [_min, _max];
            _c sliderSetSpeed [1, 10];
            _c sliderSetPosition _cur;
            private _t = ["COMSPEC_RscTextRight", [_ix + _iw * 0.8, _gy, _iw * 0.2, _rowH], _grp] call _mk;
            _t ctrlSetText ((str round _cur) call comspec_atak_native_fnc_tr);
            _c setVariable ["out", _t];
            _c ctrlAddEventHandler ["SliderPosChanged", { params ["_c", "_v"]; (_c getVariable "out") ctrlSetText ((str round _v) call comspec_atak_native_fnc_tr); }];
        };
    };
    _c setVariable ["kind", _kind];
    _ctrls pushBack _c;
    _gy = _gy + _lh + _gap;
} forEach _fields;
private _by = _y0 + _h - _rowH * 1.3;
private _ok = ["COMSPEC_RscButtonPrimary", [_x0 + _w - _gap - _w * 0.22, _by, _w * 0.22, _rowH]] call _mk;
_ok ctrlSetText (("VALIDER") call comspec_atak_native_fnc_tr);
private _no = ["COMSPEC_RscButton", [_x0 + _w - 2 * _gap - _w * 0.44, _by, _w * 0.22, _rowH]] call _mk;
_no ctrlSetText (("ANNULER") call comspec_atak_native_fnc_tr);
_d setVariable ["form", [_ctrls, _code, _args]];
_no ctrlAddEventHandler ["ButtonClick", { (ctrlParent (_this select 0)) closeDisplay 2; }];
_ok ctrlAddEventHandler ["ButtonClick", {
    private _disp = ctrlParent (_this select 0);
    (_disp getVariable ["form", [[], {}, []]]) params ["_ctrls", "_code", "_args"];
    private _vals = _ctrls apply {
        private _c = _x;
        switch (_c getVariable ["kind", ""]) do {
            case "COMBO": { (_c getVariable ["values", []]) param [lbCurSel _c, ""] };
            case "CHECK": { cbChecked _c };
            case "SLIDER": { round sliderPosition _c };
            default { ctrlText _c };
        }
    };
    _disp closeDisplay 1;
    [_vals, _args] call _code;
}];
true
