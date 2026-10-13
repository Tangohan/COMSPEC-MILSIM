/*
    App Accessoires : un établi, pas une liste. Le téléphone vu de dos (même rendu que « Voir l'arrière », fn_backView :
    phone_back*.paa selon le modèle et la coque, batterie externe et antenne en calque) au centre, entouré de cinq
    emplacements reliés à leur point de fixation par un trait fin : antenne (port SMA), batterie de rechange,
    module DL-7, filtre d'écran JVN, batterie externe (plaque). Toucher un emplacement ouvre un tiroir : objets compatibles
    de l'inventaire (FIXER), RETIRER si monté, actions propres (changer la batterie, ouvrir Extraction…).
    Données et actions : fn_accessoriesAction. Params (fn_pageRender, module) : [page, [x, y, largeur, hauteur]]
*/
params [["_page", "ACCESSORIES"], ["_rect", []]];
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
if ((count _rect) isEqualTo 4) then { _bw = _rect select 2; _bh = _rect select 3; };
private _fs = _l get "fontSmall";
private _pad = (_l get "pad") * 2;
private _k = _fs / (0.024 * safeZoneH);
private _st = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _open = _st getOrDefault ["accSlot", ""];
([] call comspec_atak_native_fnc_accent) params ["_acc", "_accHex"];
private _slots = ["slots"] call comspec_atak_native_fnc_accessoriesAction;
private _esc = { params ["_t"]; { _t = [_t, _x select 0, _x select 1] call CBA_fnc_replace; } forEach [["&", "&amp;"], ["<", "&lt;"], [">", "&gt;"]]; _t };
private _mk = { params ["_cls", "_pos", ["_txt", ""]]; [_cls, _pos, _txt] call comspec_atak_native_fnc_pageCtrl };
private _txt = {
    params ["_pos", "_s"];
    private _c = ["COMSPEC_RscStructuredText", _pos] call _mk;
    _c ctrlSetStructuredText parseText ((_s) call comspec_atak_native_fnc_tr);
    _c ctrlEnable false;
    _c
};
private _lw = pixelW * 2;
private _lh = pixelH * 2;
private _line = {
    params ["_x1", "_y1", "_x2", "_y2", "_col"];
    private _c = ["COMSPEC_RscText", [_x1 min _x2, _y1 min _y2, (abs (_x2 - _x1)) max _lw, (abs (_y2 - _y1)) max _lh]] call _mk;
    _c ctrlSetBackgroundColor _col;
    _c ctrlEnable false;
};

// Fond « tapis de découpe » : quadrillage discret.
private _bg = ["COMSPEC_RscText", [0, 0, _bw, _bh]] call _mk;
_bg ctrlSetBackgroundColor [0.028, 0.034, 0.031, 1];
private _gs = _bw / 12;
for "_i" from 1 to 11 do { [_gs * _i, 0, _gs * _i, _bh, [1, 1, 1, 0.035]] call _line; };
private _gy = _gs * (uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW]);
for "_i" from 1 to (floor (_bh / (_gy max 0.001))) do { [0, _gy * _i, _bw, _gy * _i, [1, 1, 1, 0.035]] call _line; };

// En-tête et aide.
private _model = profileNamespace getVariable ["COMSPEC_ATAK_PhoneModel", "s7"];
private _tint = profileNamespace getVariable ["COMSPEC_ATAK_ShellTint", "coyote"];
private _s7 = !(_model in ["rugged", "iphone"]);
private _nMounted = { _x get "mounted" } count _slots;
private _headH = _fs * 1.9;
[[_pad, _pad * 0.5, _bw - 2 * _pad, _headH], format ["<t size='%1' font='COMSPEC_BarlowBold' color='#e8eadf'>ÉTABLI</t><t size='%2' color='#8a9a93'>  ·  %3</t><t align='right' size='%2' color='%4'>%5 / %6 fixés</t>",
    _k * 1.15, _k * 0.85,
    [createHashMapFromArray [["rugged", "Tactique EUD"], ["iphone", "Premium"]] getOrDefault [_model, ""], "Coque " + (createHashMapFromArray [["coyote", "coyote"], ["od", "vert OD"], ["black", "noire"]] getOrDefault [_tint, "coyote"])] select _s7,
    _accHex, _nMounted, count _slots]] call _txt;
private _hintH = _fs * 1.5;
[[_pad, _bh - _hintH, _bw - 2 * _pad, _hintH], format ["<t align='center' size='%1' color='#6d7a74'>Touchez un emplacement pour fixer ou retirer un accessoire</t>", _k * 0.75]] call _txt;

// Disposition : portrait = 3 emplacements au-dessus et 2 au-dessous ; paysage = 3 à gauche et 2 à droite.
private _portrait = _bh > _bw * 1.1;
private _top = _pad * 0.5 + _headH + _pad * 0.5;
private _bottom = _bh - _hintH - _pad * 0.5;
private _ratio = (uiNamespace getVariable ["COMSPEC_ATAK_Ratio", pixelH / pixelW]);
private _boxes = [];   // [x, y, w, h] par emplacement, dans l'ordre de _order
private _attach = [];  // point de départ du trait sur l'emplacement
private _order = [];
private _ix = 0; private _iy = 0; private _iw = 0; private _ih = 0;
if (_portrait) then {
    _order = ["spare", "antenna", "dl7", "filter", "powerbank"];
    private _sw = (_bw - 4 * _pad) / 3;
    private _sh = _fs * 5.4;
    private _yTop = _top;
    private _yBot = _bottom - _sh;
    for "_i" from 0 to 4 do {
        private _low = _i > 2;
        // Rangée du bas : deux emplacements centrés.
        private _bx = if (_low) then { (_bw - 2 * _sw - _pad) / 2 + (_i - 3) * (_sw + _pad) } else { _pad + _i * (_sw + _pad) };
        private _by = [_yTop, _yBot] select _low;
        _boxes pushBack [_bx, _by, _sw, _sh];
        _attach pushBack [_bx + _sw / 2, [_by + _sh, _by] select _low];
    };
    // Coque : texture carrée, la coque n'en occupe que la bande du milieu (environ 640-1410 px de haut).
    private _zone = (_yBot - _pad) - (_yTop + _sh + _pad);
    _iw = _bw * 1.05;
    _ih = _iw * _ratio;
    if (_ih * 0.42 > _zone) then { _ih = _zone / 0.42; _iw = _ih / _ratio; };
    _ix = (_bw - _iw) / 2;
    _iy = (_yTop + _sh + _yBot) / 2 - _ih * 1024 / 2048;
} else {
    _order = ["spare", "dl7", "filter", "antenna", "powerbank"];
    private _sw = _bw * 0.27;
    private _sh = ((_bottom - _top - 2 * _pad) / 3) min (_fs * 5.4);
    private _span = 3 * _sh + 2 * _pad;
    private _y0 = _top + ((_bottom - _top) - _span) / 2;
    for "_i" from 0 to 4 do {
        private _right = _i > 2;
        // Colonne de droite : deux emplacements centrés en hauteur.
        private _by = if (_right) then { _y0 + (_sh + _pad) / 2 + (_i - 3) * (_sh + _pad) } else { _y0 + _i * (_sh + _pad) };
        private _bx = [_pad, _bw - _pad - _sw] select _right;
        _boxes pushBack [_bx, _by, _sw, _sh];
        _attach pushBack [[_bx + _sw, _bx] select _right, _by + _sh / 2];
    };
    private _zoneW = _bw - 2 * (_sw + 2 * _pad);
    _iw = _zoneW / 0.78;
    _ih = _iw * _ratio;
    if (_ih * 0.42 > (_bottom - _top)) then { _ih = (_bottom - _top) / 0.42; _iw = _ih / _ratio; };
    _ix = (_bw - _iw) / 2;
    _iy = (_top + _bottom) / 2 - _ih * 1024 / 2048;
};
private _tex = { params ["_p"]; [_ix + _iw * (_p select 0) / 2048, _iy + _ih * (_p select 1) / 2048] };

// Téléphone vu de dos, et ses accessoires en calque (comme fn_backView).
private _back = ["COMSPEC_RscPicture", [_ix, _iy, _iw, _ih]] call _mk;
_back ctrlSetText (((["\z\comspec_atak_native\addons\main\data\phone_back_" + _model + ".paa",
    format ["\z\comspec_atak_native\addons\main\data\phone_back%1.paa", ["", "_" + _tint] select (_tint in ["od", "black"])]] select _s7)) call comspec_atak_native_fnc_tr);
_back ctrlSetTextColor [1, 1, 1, 1];
_back ctrlEnable false;
if (_s7 && {missionNamespace getVariable ["COMSPEC_ATAK_PowerBankOn", false]}) then {
    private _pb = ["COMSPEC_RscPicture", [_ix, _iy, _iw, _ih], "\z\comspec_atak_native\addons\main\data\back_powerbank.paa"] call _mk;
    _pb ctrlSetTextColor [1, 1, 1, 1];
    _pb ctrlEnable false;
};
private _ant = ["get"] call comspec_atak_native_fnc_antenna;
if (_s7 && {_ant isNotEqualTo []}) then {
    private _a = ["COMSPEC_RscPicture", [_ix + _iw * 1426 / 2048, _iy + _ih * -832 / 2048, _iw * 384 / 2048, _ih * 1536 / 2048],
        format ["\z\comspec_atak_native\addons\main\data\antenna_%1_back.paa", _ant select 0]] call _mk;
    _a ctrlSetTextColor [1, 1, 1, 1];
    _a ctrlEnable false;
};

// Traits de rappel (en équerre) et points de fixation.
{
    private _id = _x;
    private _s = _slots param [_slots findIf { (_x get "id") isEqualTo _id }, createHashMap];
    if (count _s isEqualTo 0) then { continue };
    (_attach select _forEachIndex) params ["_sx", "_sy"];
    ([_s get "anchor"] call _tex) params ["_ax", "_ay"];
    private _sel = _open isEqualTo _id;
    private _col = if (_s get "mounted") then { [_acc select 0, _acc select 1, _acc select 2, [0.7, 1] select _sel] } else { [0.55, 0.6, 0.57, [0.35, 0.8] select _sel] };
    if (_portrait) then {
        [_sx, _sy, _sx, _ay, _col] call _line;
        [_sx, _ay, _ax, _ay, _col] call _line;
    } else {
        [_sx, _sy, _ax, _sy, _col] call _line;
        [_ax, _sy, _ax, _ay, _col] call _line;
    };
    private _dw = _fs * 0.55;
    private _dh = _dw * _ratio;
    private _dot = ["COMSPEC_RscPicture", [_ax - _dw / 2, _ay - _dh / 2, _dw, _dh], "\z\comspec_atak_native\addons\main\data\ui_disc.paa"] call _mk;
    _dot ctrlSetTextColor _col;
    _dot ctrlEnable false;
} forEach _order;

// Emplacements.
{
    private _id = _x;
    private _s = _slots param [_slots findIf { (_x get "id") isEqualTo _id }, createHashMap];
    if (count _s isEqualTo 0) then { continue };
    (_boxes select _forEachIndex) params ["_bx", "_by", "_bw2", "_bh2"];
    private _on = _s get "mounted";
    private _sel = _open isEqualTo _id;
    private _card = ["COMSPEC_RscText", [_bx, _by, _bw2, _bh2]] call _mk;
    _card ctrlSetBackgroundColor ([[0.075, 0.09, 0.083, 0.94], [0.10, 0.13, 0.115, 0.97]] select _sel);
    // Cadre fin (accent si sélectionné) et bande d'état à gauche.
    private _fc = [[0.25, 0.29, 0.27, 1], _acc] select _sel;
    [_bx, _by, _bx + _bw2, _by, _fc] call _line;
    [_bx, _by + _bh2 - _lh, _bx + _bw2, _by + _bh2 - _lh, _fc] call _line;
    [_bx + _bw2 - _lw, _by, _bx + _bw2 - _lw, _by + _bh2, _fc] call _line;
    private _strip = ["COMSPEC_RscText", [_bx, _by, _lw * 2, _bh2]] call _mk;
    _strip ctrlSetBackgroundColor ([[0.4, 0.45, 0.42, 0.6], _acc] select _on);
    // Icône de l'objet monté (éteinte si vide).
    private _icH = (_bh2 * 0.46) min (_fs * 2.4);
    private _icW = _icH / _ratio;
    private _ic = ["COMSPEC_RscPicture", [_bx + _pad * 0.8, _by + _pad * 0.5 + _fs * 1.05, _icW, _icH], _s get "icon"] call _mk;
    _ic ctrlSetTextColor ([[1, 1, 1, 0.22], [1, 1, 1, 1]] select _on);
    _ic ctrlEnable false;
    [[_bx + _pad * 0.6, _by + _pad * 0.25, _bw2 - _pad, _fs * 1.1], format ["<t size='%1' font='EtelkaMonospaceProBold' color='%2'>%3</t><t size='%4' color='#6d7a74'>  %5</t>",
        _k * 0.62, ["#8a9a93", _accHex] select _on, toUpper (_s get "title"), _k * 0.6, _s get "sub"]] call _txt;
    private _tx = _bx + _pad * 1.2 + _icW;
    [[_tx, _by + _pad * 0.4 + _fs * 1.05, (_bx + _bw2) - _tx - _pad * 0.3, _bh2 - _fs * 1.2], format ["<t size='%1' font='COMSPEC_BarlowBold' color='%2'>%3</t><br/><t size='%4' color='%5'>%6</t>",
        _k * 0.82, ["#6d7a74", "#e8eadf"] select _on, [(_s get "name")] call _esc, _k * 0.7, _s get "stateHex", [(_s get "state")] call _esc]] call _txt;
    // Pastille : nombre d'objets compatibles en poche.
    private _nc = count (_s get "cand");
    if (_nc > 0) then {
        private _bdH = _fs * 0.95;
        private _bd = ["COMSPEC_RscBadge", [_bx + _bw2 - _bdH / _ratio * 1.6 - _pad * 0.3, _by + _pad * 0.3, _bdH / _ratio * 1.6, _bdH], format ["+%1", _nc]] call _mk;
        _bd ctrlSetBackgroundColor [0.55, 0.38, 0.08, 1];
        _bd ctrlSetFontHeight ((_fs * 0.7) max COMSPEC_ATAK_FontMin);
        _bd ctrlEnable false;
    };
    private _btn = ["COMSPEC_RscButtonOverlay", [_bx, _by, _bw2, _bh2]] call _mk;
    _btn ctrlSetTooltip ((format ["%1 : %2", _s get "title", ["vide", _s get "name"] select _on]) call comspec_atak_native_fnc_tr);
    _btn ctrlAddEventHandler ["ButtonClick", compile format [
        "private _s = uiNamespace getVariable ['COMSPEC_ATAK_State', createHashMap]; _s set ['accSlot', [%1, ''] select ((_s getOrDefault ['accSlot', '']) isEqualTo %1)]; playSound 'ClickSoft'; ['refresh'] call comspec_atak_native_fnc_accessoriesAction;",
        str _id]];
} forEach _order;

// Tiroir de l'emplacement choisi.
private _s = _slots param [_slots findIf { (_x get "id") isEqualTo _open }, createHashMap];
if (count _s > 0) then {
    private _do = { params ["_verb", ["_arg", ""]]; compile format ["['do', [%1, %2, %3]] call comspec_atak_native_fnc_accessoriesAction;", str (_s get "id"), str _verb, str _arg] };
    private _rowH = _fs * 2.3;
    private _cand = _s get "cand";
    private _verbs = _s get "verbs";
    private _hasRemove = (_s get "remove") isNotEqualTo "";
    private _n = 1 + (count _cand) + (parseNumber ((count _verbs) > 0)) + (parseNumber ((count _cand) isEqualTo 0 && {!(_s get "mounted")}));
    private _sh = _fs * 2.2 + _n * (_rowH + _pad * 0.4) + _pad;
    private _sy = _bh - _sh;
    // Voile : un clic à côté ferme le tiroir.
    private _dim = ["COMSPEC_RscText", [0, 0, _bw, _sy]] call _mk;
    _dim ctrlSetBackgroundColor [0, 0, 0, 0.55];
    private _veil = ["COMSPEC_RscButtonInvisible", [0, 0, _bw, _sy]] call _mk;
    _veil ctrlAddEventHandler ["ButtonClick", { (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) set ["accSlot", ""]; ['refresh'] call comspec_atak_native_fnc_accessoriesAction; }];
    private _shBg = ["COMSPEC_RscText", [0, _sy, _bw, _sh]] call _mk;
    _shBg ctrlSetBackgroundColor [0.055, 0.067, 0.061, 0.99];
    [0, _sy, _bw, _sy, _acc] call _line;
    private _y = _sy + _pad * 0.5;
    [[_pad, _y, _bw - 2 * _pad, _fs * 1.6], format ["<t size='%1' font='COMSPEC_BarlowBold' color='#e8eadf'>%2</t><t size='%3' color='#8a9a93'>  ·  %4</t>",
        _k, toUpper (_s get "title"), _k * 0.75, _s get "sub"]] call _txt;
    private _x2 = ["COMSPEC_RscButton", [_bw - _pad - _fs * 4.2, _y, _fs * 4.2, _fs * 1.4], "FERMER"] call _mk;
    _x2 ctrlSetFontHeight ((_fs * 0.75) max COMSPEC_ATAK_FontMin);
    _x2 ctrlAddEventHandler ["ButtonClick", { (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) set ["accSlot", ""]; ['refresh'] call comspec_atak_native_fnc_accessoriesAction; }];
    _y = _y + _fs * 1.9;
    private _btnW = _fs * 5.6;
    private _row = {
        params ["_icon", "_title", "_sub", "_alpha"];
        private _icH = _rowH * 0.8;
        private _ic = ["COMSPEC_RscPicture", [_pad, _y + _rowH * 0.1, _icH / _ratio, _icH], _icon] call _mk;
        _ic ctrlSetTextColor [1, 1, 1, _alpha];
        _ic ctrlEnable false;
        [[_pad * 1.5 + _icH / _ratio, _y + _rowH * 0.05, _bw - _btnW * 2 - _pad * 3 - _icH / _ratio, _rowH], format ["<t size='%1' font='COMSPEC_BarlowBold'>%2</t><br/><t size='%3' color='#8a9a93'>%4</t>",
            _k * 0.85, [_title] call _esc, _k * 0.7, [_sub] call _esc]] call _txt;
    };
    // Monté actuellement.
    if (_s get "mounted") then {
        [_s get "icon", _s get "name", "monté · " + (_s get "state"), 1] call _row;
    } else {
        [_s get "icon", "Emplacement vide", _s get "state", 0.25] call _row;
    };
    private _bx = _bw - _pad - _btnW;
    if (_hasRemove) then {
        private _b = ["COMSPEC_RscButton", [_bx, _y + _rowH * 0.15, _btnW, _rowH * 0.7], _s get "remove"] call _mk;
        _b ctrlSetFontHeight ((_fs * 0.8) max COMSPEC_ATAK_FontMin);
        _b ctrlSetBackgroundColor [0.45, 0.14, 0.12, 1];
        _b ctrlAddEventHandler ["ButtonClick", ["remove"] call _do];
    };
    _y = _y + _rowH + _pad * 0.4;
    // Objets compatibles de l'inventaire.
    {
        _x params ["_name", "_icon", "_count", "_arg"];
        [_icon, _name, format ["dans l'inventaire · x%1", _count], 1] call _row;
        private _b = ["COMSPEC_RscButtonPrimary", [_bw - _pad - _btnW, _y + _rowH * 0.15, _btnW, _rowH * 0.7], ["FIXER", "REMPLACER"] select (_s get "mounted")] call _mk;
        _b ctrlSetFontHeight ((_fs * 0.8) max COMSPEC_ATAK_FontMin);
        _b ctrlAddEventHandler ["ButtonClick", ["fix", _arg] call _do];
        _y = _y + _rowH + _pad * 0.4;
    } forEach _cand;
    if ((count _cand) isEqualTo 0 && {!(_s get "mounted")}) then {
        [[_pad, _y, _bw - 2 * _pad, _rowH], format ["<t size='%1' color='#8a9a93'>Aucun objet compatible dans votre inventaire.</t>", _k * 0.8]] call _txt;
        _y = _y + _rowH + _pad * 0.4;
    };
    // Actions propres à l'emplacement (balise, batterie, DL-7).
    if ((count _verbs) > 0) then {
        private _vw = ((_bw - 2 * _pad) - ((count _verbs) - 1) * _pad) / (count _verbs);
        {
            _x params ["_label", "_verb"];
            private _b = [["COMSPEC_RscButton", "COMSPEC_RscButtonPrimary"] select (_forEachIndex isEqualTo 0), [_pad + _forEachIndex * (_vw + _pad), _y + _rowH * 0.1, _vw, _rowH * 0.8], _label] call _mk;
            _b ctrlSetFontHeight ((_fs * 0.8) max COMSPEC_ATAK_FontMin);
            _b ctrlAddEventHandler ["ButtonClick", [_verb] call _do];
        } forEach _verbs;
    };
};
true
