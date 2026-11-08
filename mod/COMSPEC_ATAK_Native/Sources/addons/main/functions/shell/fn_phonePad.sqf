/*
    Clavier du code, par-dessus l'écran du téléphone. Sert à mon téléphone (fn_lockScreen), à celui d'un autre (fn_pagePhones)
    et au réglage du code (Réglages, fn_phoneOwn « pinSetup »). Fond d'écran flouté sous un voile, touches rondes, points de
    saisie ; deux colonnes en paysage. Mis à jour sur place : taper un chiffre ne redessine rien. Clavier physique : fn_keyBlock.
    Params : [action, argument]
      "show" hashmap : title, sub, msg, ok (code appelé avec [chiffres]), buttons ([[libellé, code]...]), setup (réglage du code)
      "draw"  redessine (après une reconstruction de l'écran)     "key" chiffre    "del"    "ok"
      "msg" texte      "count" [fin diag_tickTime, modèle avec %1] : décompte en secondes, tenu à jour par « tick »
      "tick" chaque seconde (fn_statusUpdate)      "close"      "up" : clavier affiché ?
*/
params [["_act", "draw"], ["_arg", ""]];
disableSerialization;
private _pad = uiNamespace getVariable ["COMSPEC_ATAK_Pad", createHashMap];
private _lockDir = "\z\comspec_atak_native\addons\items\data\lock\";
private _kill = {
    { ctrlDelete _x; } forEach (uiNamespace getVariable ["COMSPEC_ATAK_PadCtrls", []]);
    uiNamespace setVariable ["COMSPEC_ATAK_PadCtrls", []];
    uiNamespace setVariable ["COMSPEC_ATAK_PadDots", []];
};
// Points de saisie : au moins 4 places, un point plein par chiffre, centrés. Le nombre exact du code n'est pas révélé.
private _dots = {
    (uiNamespace getVariable ["COMSPEC_ATAK_PadDots", []]) params [["_list", []], ["_cx", 0], ["_y", 0], ["_size", 0], ["_step", 0]];
    if (_list isEqualTo [] || {isNull (_list select 0)}) exitWith {};
    private _n = count (_pad getOrDefault ["digits", ""]);
    private _slots = _n max 4;
    private _x0 = _cx - (_slots * _step) / 2;
    {
        private _on = _forEachIndex < _slots;
        _x ctrlShow _on;
        if (_on) then {
            _x ctrlSetText (_lockDir + (["ring", "dot"] select (_forEachIndex < _n)) + ".paa"); // i18n:brut
            _x ctrlSetPositionX (_x0 + _forEachIndex * _step + (_step - _size) / 2);
            _x ctrlCommit 0;
        };
    } forEach _list;
};
private _setMsg = {
    params ["_t"];
    private _c = uiNamespace getVariable ["COMSPEC_ATAK_PadMsg", controlNull];
    if (!isNull _c) then { _c ctrlSetText ((_t) call comspec_atak_native_fnc_tr); };
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
        _pad set ["count", []];
        call _dots;
        [_arg] call _setMsg;
        true
    };
    case "count": {
        _pad set ["count", _arg];
        _pad set ["digits", ""];
        call _dots;
        ["tick"] call comspec_atak_native_fnc_phonePad;
        true
    };
    case "tick": {
        (_pad getOrDefault ["count", []]) params [["_until", -1], ["_model", ""]];
        if (_until < 0) exitWith {};
        private _left = ceil (_until - diag_tickTime);
        if (_left <= 0) exitWith { _pad set ["count", []]; _pad set ["msg", ""]; [""] call _setMsg; };
        private _t = format [(_model) call comspec_atak_native_fnc_tr, _left];
        _pad set ["msg", _t];
        (uiNamespace getVariable ["COMSPEC_ATAK_PadMsg", controlNull]) ctrlSetText _t; // i18n:brut (déjà traduit)
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
        private _land = _l get "landscape";
        private _space = (_l get "pad") * 2;
        private _ratio = (uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW]);
        private _th = [] call comspec_atak_native_fnc_theme;
        private _day = (_th get "name") isEqualTo "DAY";
        private _nvg = ((_th get "name") isEqualTo "NVG") || {currentVisionMode player isEqualTo 1};
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
        private _pic = {
            params ["_path", "_pos", "_col"];
            private _c = ["COMSPEC_RscPhone", _pos, _path] call _mk;
            _c ctrlSetTextColor _col;
            _c ctrlEnable false;
            _c
        };
        // Fond : fond d'écran flouté (vert en vision nocturne), voile du thème.
        private _bg = ["COMSPEC_RscText", [0, 0, _dw, _dh]] call _mk;
        _bg ctrlSetBackgroundColor (_th get "bg0");
        private _wall = ["path", [profileNamespace getVariable ["COMSPEC_ATAK_Wallpaper", "topo"], true, _land]] call comspec_atak_native_fnc_wallPath;
        if (_wall isNotEqualTo "") then {
            [_wall, [0, 0, _dw, _dh], [[1, 1, 1, 1], [0.30, 0.62, 0.36, 1]] select _nvg] call _pic;
        };
        private _veil = ["COMSPEC_RscText", [0, 0, _dw, _dh]] call _mk;
        _veil ctrlSetBackgroundColor (((_th get "bg0") select [0, 3]) + [[0.62, 0.82] select (_day || _nvg)]);
        // Bloque tout ce qui est dessous : un clic entre deux touches n'atteint plus l'écran de verrouillage.
        ["COMSPEC_RscButtonInvisible", [0, 0, _dw, _dh]] call _mk;
        private _ink = _th get "text";
        private _dim = _th get "textDim";
        // Colonne du texte et zone des touches.
        private _btns = _pad getOrDefault ["buttons", []];
        private _rowH = _font * 1.5;
        private _gap = _font * 0.3;
        private _colW = [_dw * 0.84, _dw * 0.46] select _land;
        private _colX = [_dw * 0.08, _dw * 0.04] select _land;
        private _y = _dh * ([0.05, 0.1] select _land);
        private _is = _font * 1.5;
        [_lockDir + "lock_closed.paa", [_colX + (_colW - _is / _ratio) / 2, _y, _is / _ratio, _is], _th get "accentInk"] call _pic;
        _y = _y + _is + _gap;
        private _title = ["COMSPEC_RscTextCenter", [_colX, _y, _colW, _font * 1.4], _pad getOrDefault ["title", ""]] call _mk;
        _title ctrlSetFont (COMSPEC_ATAK_FontSet select 1);
        _title ctrlSetFontHeight (_font * 1.15);
        _title ctrlSetTextColor _ink;
        _y = _y + _font * 1.4;
        private _sub = ["COMSPEC_RscTextCenter", [_colX, _y, _colW, _font * 1.15], _pad getOrDefault ["sub", ""]] call _mk;
        _sub ctrlSetFontHeight ((_fs * 0.95) max COMSPEC_ATAK_FontMin);
        _sub ctrlSetTextColor _dim;
        _y = _y + _font * 1.15 + _gap;
        // Points de saisie : 8 contrôles créés une fois, placés par _dots.
        private _ds = _font * 0.55;
        private _dsW = _ds / _ratio;
        private _list = [];
        for "_i" from 0 to 7 do { _list pushBack ([_lockDir + "ring.paa", [0, _y + _gap / 2, _dsW, _ds], _ink] call _pic); };
        uiNamespace setVariable ["COMSPEC_ATAK_PadDots", [_list, _colX + _colW / 2, _y, _dsW, _dsW * 1.7]];
        _y = _y + _ds + _gap * 1.5;
        private _msg = ["COMSPEC_RscTextCenter", [_colX, _y, _colW, _font * 1.15], _pad getOrDefault ["msg", ""]] call _mk;
        _msg ctrlSetFontHeight ((_fs * 0.95) max COMSPEC_ATAK_FontMin);
        _msg ctrlSetTextColor (_th get "warn");
        uiNamespace setVariable ["COMSPEC_ATAK_PadMsg", _msg];
        _y = _y + _font * 1.3;
        private _addBtn = {
            params ["_pos", "_label", "_code", "_args"];
            private _b = ["COMSPEC_RscButtonInvisible", _pos, _label] call _mk;
            _b ctrlSetTextColor _ink;
            _b setVariable ["code", _code];
            _b setVariable ["args", _args];
            _b ctrlAddEventHandler ["ButtonClick", {
                params ["_c"];
                [_c getVariable ["code", {}], _c getVariable ["args", []]] call comspec_atak_native_fnc_nextFrame;
            }];
            _b
        };
        // Boutons de texte (CODE OUBLIÉ, RETOUR…) : pastilles, en bas de la colonne (sous le pavé en portrait).
        private _nb = count _btns;
        private _btnTop = _dh - (_rowH + _gap) * _nb - _font * 0.4;
        {
            _x params ["_label", "_code"];
            private _pos = [_colX, _btnTop + _forEachIndex * (_rowH + _gap), _colW, _rowH];
            [_lockDir + "pill.paa", _pos, _th get "surface2"] call _pic;
            private _b = [_pos, _label, _code, []] call _addBtn;
            _b ctrlSetFontHeight ((_fs * 0.95) max COMSPEC_ATAK_FontMin);
        } forEach _btns;
        // Pavé : à droite en paysage, sous le texte en portrait ; touches rondes à la taille qui tient.
        private _padTop = [_y, _dh * 0.05] select _land;
        private _padBot = [_btnTop - _gap, _dh * 0.95] select _land;
        private _areaX = [0, _dw * 0.52] select _land;
        private _areaW = [_dw, _dw * 0.44] select _land;
        private _kGap = _font * 0.3;
        private _kh = (((_padBot - _padTop) - _kGap * 3) / 4) min ((((_areaW * 0.92) - _kGap * 2 / _ratio) / 3) * _ratio);
        _kh = _kh max (_font * 1.1);
        private _kw = _kh / _ratio;
        private _gx = _kGap / _ratio;
        private _kx0 = _areaX + (_areaW - (_kw * 3 + _gx * 2)) / 2;
        private _ky0 = _padTop + ((_padBot - _padTop) - (_kh * 4 + _kGap * 3)) / 2;
        private _keyCol = ((_th get "surface2") select [0, 3]) + [0.92];
        {
            private _i = _forEachIndex;
            private _pos = [_kx0 + (_i mod 3) * (_kw + _gx), _ky0 + (floor (_i / 3)) * (_kh + _kGap), _kw, _kh];
            private _ok = _x isEqualTo "OK";
            [_lockDir + "key.paa", _pos, [_keyCol, _th get "accent"] select _ok] call _pic;
            private _face = _pos;
            switch (_x) do {
                case "<": {
                    _pos params ["_px", "_py", "_pw", "_ph"];
                    [_lockDir + "backspace.paa", [_px + _pw * 0.26, _py + _ph * 0.26, _pw * 0.48, _ph * 0.48], _ink] call _pic;
                    [_face, "", { ["del"] call comspec_atak_native_fnc_phonePad; }, []] call _addBtn;
                };
                case "OK": {
                    _pos params ["_px", "_py", "_pw", "_ph"];
                    ["\z\comspec_atak_native\addons\main\data\ui_check.paa", [_px + _pw * 0.26, _py + _ph * 0.26, _pw * 0.48, _ph * 0.48], _th get "onAccent"] call _pic;
                    [_face, "", { ["ok"] call comspec_atak_native_fnc_phonePad; }, []] call _addBtn;
                };
                default {
                    private _b = [_face, _x, { params ["_digit"]; ["key", _digit] call comspec_atak_native_fnc_phonePad; }, [_x]] call _addBtn;
                    _b ctrlSetFont (COMSPEC_ATAK_FontSet select 1);
                    _b ctrlSetFontHeight ((_kh * 0.46) max COMSPEC_ATAK_FontMin);
                };
            };
        } forEach ["1", "2", "3", "4", "5", "6", "7", "8", "9", "<", "0", "OK"];
        uiNamespace setVariable ["COMSPEC_ATAK_PadCtrls", _made];
        call _dots;
        ["front"] call comspec_atak_native_fnc_fx;
        true
    };
};
