/*
    Clavier du code, par-dessus l'écran du téléphone. Sert à mon téléphone (fn_lockScreen) et à celui d'un autre
    (fn_pagePhones). Mis à jour sur place : taper un chiffre ne redessine rien.
    Params : [action, argument]
      "show" hashmap : title, sub, msg, ok (code appelé avec [chiffres]), buttons ([[libellé, code]...])
      "draw"  redessine (après une reconstruction de l'écran)     "key" chiffre    "del"    "ok"
      "msg" texte      "close"      "up" : clavier affiché ?
*/
params [["_act", "draw"], ["_arg", ""]];
disableSerialization;
private _pad = uiNamespace getVariable ["COMSPEC_ATAK_Pad", createHashMap];
private _kill = {
    { ctrlDelete _x; } forEach (uiNamespace getVariable ["COMSPEC_ATAK_PadCtrls", []]);
    uiNamespace setVariable ["COMSPEC_ATAK_PadCtrls", []];
};
private _dots = {
    private _c = uiNamespace getVariable ["COMSPEC_ATAK_PadDots", controlNull];
    if (isNull _c) exitWith {};
    private _t = "";
    for "_i" from 1 to (count (_pad getOrDefault ["digits", ""])) do { _t = _t + "● "; };
    _c ctrlSetText _t; // i18n:brut
};
switch (_act) do {
    case "up": { uiNamespace getVariable ["COMSPEC_ATAK_PadUp", false] };
    case "close": {
        uiNamespace setVariable ["COMSPEC_ATAK_PadUp", false];
        call _kill;
        true
    };
    case "msg": {
        _pad set ["msg", _arg];
        _pad set ["digits", ""];
        call _dots;
        private _c = uiNamespace getVariable ["COMSPEC_ATAK_PadMsg", controlNull];
        if (!isNull _c) then { _c ctrlSetText ((_arg) call comspec_atak_native_fnc_tr); };
        true
    };
    case "key": {
        private _t = _pad getOrDefault ["digits", ""];
        if ((count _t) < 8) then { _pad set ["digits", _t + _arg]; };
        call _dots;
    };
    case "del": {
        private _t = _pad getOrDefault ["digits", ""];
        _pad set ["digits", _t select [0, ((count _t) - 1) max 0]];
        call _dots;
    };
    case "ok": {
        private _t = _pad getOrDefault ["digits", ""];
        if (_t isEqualTo "") exitWith {};
        [_t] call (_pad getOrDefault ["ok", {}]);
    };
    case "show": {
        if !(_arg isEqualType createHashMap) exitWith { false };
        _arg set ["digits", ""];
        uiNamespace setVariable ["COMSPEC_ATAK_Pad", _arg];
        uiNamespace setVariable ["COMSPEC_ATAK_PadUp", true];
        ["draw"] call comspec_atak_native_fnc_phonePad
    };
    case "draw": {
        call _kill;
        private _d = [] call comspec_atak_native_fnc_display;
        if (isNull _d || {!(uiNamespace getVariable ["COMSPEC_ATAK_PadUp", false])}) exitWith { false };
        private _l = [] call comspec_atak_native_fnc_layoutGet;
        (_l get "device") params ["_dx", "_dy", "_dw", "_dh"];
        private _font = _l get "font";
        private _fs = _l get "fontSmall";
        private _grp = _d ctrlCreate ["COMSPEC_RscControlsGroup", -1];
        _grp ctrlSetPosition [_dx, _dy, _dw, _dh];
        _grp ctrlCommit 0;
        private _made = [_grp];
        private _mk = {
            params ["_class", "_pos", ["_text", ""]];
            private _c = _d ctrlCreate [_class, -1, _grp];
            _c ctrlSetPosition _pos;
            if (_text isNotEqualTo "") then { _c ctrlSetText ((_text) call comspec_atak_native_fnc_tr); };
            _c ctrlCommit 0;
            _made pushBack _c;
            _c
        };
        private _bg = ["COMSPEC_RscText", [0, 0, _dw, _dh]] call _mk;
        _bg ctrlSetBackgroundColor [0.02, 0.03, 0.025, 0.97];
        private _btns = _pad getOrDefault ["buttons", []];
        private _rowH = _font * 1.5;
        private _y = _font * 0.8;
        private _title = ["COMSPEC_RscTextCenter", [0, _y, _dw, _font * 1.3], _pad getOrDefault ["title", ""]] call _mk;
        _title ctrlSetFont (COMSPEC_ATAK_FontSet select 1);
        _y = _y + _font * 1.3;
        private _sub = ["COMSPEC_RscTextCenter", [0, _y, _dw, _font * 1.1], _pad getOrDefault ["sub", ""]] call _mk;
        _sub ctrlSetFontHeight ((_fs * 0.85) max COMSPEC_ATAK_FontMin);
        _sub ctrlSetTextColor [0.72, 0.78, 0.75, 1];
        _y = _y + _font * 1.2;
        uiNamespace setVariable ["COMSPEC_ATAK_PadDots", ["COMSPEC_RscTextCenter", [0, _y, _dw, _font * 1.3]] call _mk];
        _y = _y + _font * 1.3;
        private _msg = ["COMSPEC_RscTextCenter", [0, _y, _dw, _font * 1.1], _pad getOrDefault ["msg", ""]] call _mk;
        _msg ctrlSetFontHeight ((_fs * 0.85) max COMSPEC_ATAK_FontMin);
        _msg ctrlSetTextColor [1, 0.72, 0.30, 1];
        uiNamespace setVariable ["COMSPEC_ATAK_PadMsg", _msg];
        _y = _y + _font * 1.3;
        // Tout tient dans l'écran, en portrait comme en paysage : les touches prennent la hauteur qui reste.
        private _gap = _font * 0.2;
        private _cw = ((_dw * 0.8) - _gap * 2) / 3;
        private _cx0 = _dw * 0.1;
        private _bottom = _dh - (_rowH + _gap) * (count _btns) - _font * 0.6;
        private _ch = (((_bottom - _y) - _gap * 3) / 4) max (_font * 1.1);
        private _addBtn = {
            params ["_pos", "_label", "_code", "_args"];
            private _face = ["COMSPEC_RscTextCenter", _pos, _label] call _mk;
            _face ctrlSetBackgroundColor [1, 1, 1, 0.10];
            private _b = ["COMSPEC_RscButtonOverlay", _pos] call _mk;
            _b setVariable ["code", _code];
            _b setVariable ["args", _args];
            _b ctrlAddEventHandler ["ButtonClick", {
                params ["_c"];
                [_c getVariable ["code", {}], _c getVariable ["args", []]] call comspec_atak_native_fnc_nextFrame;
            }];
        };
        {
            private _i = _forEachIndex;
            private _pos = [_cx0 + (_i mod 3) * (_cw + _gap), _y + (floor (_i / 3)) * (_ch + _gap), _cw, _ch];
            switch (_x) do {
                case "<": { [_pos, "EFFACER", { ["del"] call comspec_atak_native_fnc_phonePad; }, []] call _addBtn; };
                case "OK": { [_pos, "OK", { ["ok"] call comspec_atak_native_fnc_phonePad; }, []] call _addBtn; };
                default { [_pos, _x, { params ["_digit"]; ["key", _digit] call comspec_atak_native_fnc_phonePad; }, [_x]] call _addBtn; };
            };
        } forEach ["1", "2", "3", "4", "5", "6", "7", "8", "9", "<", "0", "OK"];
        private _by = _bottom + _font * 0.2;
        {
            _x params ["_label", "_code"];
            [[_dw * 0.1, _by + _forEachIndex * (_rowH + _gap), _dw * 0.8, _rowH], _label, _code, []] call _addBtn;
        } forEach _btns;
        uiNamespace setVariable ["COMSPEC_ATAK_PadCtrls", _made];
        ["front"] call comspec_atak_native_fnc_fx;
        true
    };
};
