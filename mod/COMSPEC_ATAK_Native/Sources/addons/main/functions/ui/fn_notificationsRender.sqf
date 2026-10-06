/*
    Toast compact : une seule ligne posée sur la barre de titre de l'app (entre le bouton retour et les boutons
    de droite), jamais sur le contenu. Un seul toast à la fois, le plus prioritaire (à égalité, le plus ancien) ;
    « +N » indique ceux qui attendent. Texte trop long : coupé avec « … », texte complet en info-bulle.
    Clic sur le toast : il disparaît. Appelée chaque seconde (fn_schedulerTick), après chaque page et à chaque
    notification : les contrôles ne sont recréés que si le toast affiché, la file ou la place changent (pas de clignotement).
*/
disableSerialization;
private _d = [] call comspec_atak_native_fnc_display;
if (isNull _d) exitWith {};
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _now = diag_tickTime;
// Expirés (affichés et finis) ou oubliés (en attente depuis plus de 30 s) : retirés de la file.
private _q = (_s getOrDefault ["notifications", []]) select {
    private _exp = _x getOrDefault ["expires", -1];
    if (_exp < 0) then { (_now - (_x getOrDefault ["created", _now])) < 30 } else { _exp > _now }
};
_s set ["notifications", _q];
// Écran éteint, en animation d'arrêt ou de démarrage : pas de toast.
private _hidden = ((uiNamespace getVariable ["COMSPEC_ATAK_PowerFx", []]) param [1, -1]) > _now
    || {((([] call comspec_atak_native_fnc_deviceHealth) get "state") in ["OFF", "BROKEN"])};
private _show = createHashMap;
if (!_hidden && {(count _q) > 0}) then {
    private _best = 0;
    { if ((_x getOrDefault ["priority", 10]) > ((_q select _best) getOrDefault ["priority", 10])) then { _best = _forEachIndex; }; } forEach _q;
    private _cur = _q findIf { (_x getOrDefault ["expires", -1]) >= 0 };
    _show = if (_cur >= 0 && {((_q select _cur) getOrDefault ["priority", 10]) >= ((_q select _best) getOrDefault ["priority", 10])}) then { _q select _cur } else { _q select _best };
    if ((_show getOrDefault ["expires", -1]) < 0) then { _show set ["expires", _now + (_show getOrDefault ["duration", 5])]; };
};
private _l = [] call comspec_atak_native_fnc_layoutGet;
private _sig = [_show getOrDefault ["id", -1], count _q, _l get "appbar", _l get "interactive"];
private _old = uiNamespace getVariable ["COMSPEC_ATAK_ToastControls", []];
if (_sig isEqualTo (uiNamespace getVariable ["COMSPEC_ATAK_ToastSig", []]) && {(count _old) isEqualTo 0 || {!isNull (_old select 0)}}) exitWith {};
uiNamespace setVariable ["COMSPEC_ATAK_ToastSig", _sig];
{ ctrlDelete _x; } forEach _old;
uiNamespace setVariable ["COMSPEC_ATAK_ToastControls", []];
if ((count _show) isEqualTo 0) exitWith {};

// Place : la barre d'app, sans couvrir le bouton retour ni les boutons de droite quand on a la souris.
private _ratio = pixelH / pixelW;
(_l get "appbar") params ["_ax", "_ay", "_aw", "_ah"];
private _pad = _l get "pad";
private _fs = _l get "fontSmall";
private _bw = _ah * 0.62 / _ratio;
private _x0 = _ax + _pad;
private _x1 = _ax + _aw - _pad;
if (_l get "interactive") then {
    _x0 = _ax + _pad * 2 + _bw * 1.2;
    _x1 = _ax + _aw - _pad - _bw - _bw * 1.7 * 2 - _pad;
};
private _w = (_x1 - _x0) max (_aw * 0.4);
private _h = _ah * 0.78;
private _y = _ay + (_ah - _h) / 2;

private _t = _show getOrDefault ["type", "INFO"];
private _color = switch (_t) do {
    case "WARNING": { [0.95, 0.67, 0.20, 1] };
    case "ERROR";
    case "TACTICAL": { [0.90, 0.38, 0.31, 1] };
    case "MESSAGE": { [0.44, 0.71, 0.91, 1] };
    case "SUCCESS": { [0.36, 0.78, 0.42, 1] };
    default { [0.66, 0.78, 0.73, 1] };
};
private _label = createHashMapFromArray [["INFO", "INFO"], ["SUCCESS", "OK"], ["WARNING", "ALERTE"], ["ERROR", "ERREUR"], ["MESSAGE", "MESSAGE"], ["TACTICAL", "TACTIQUE"]] getOrDefault [_t, _t];
private _msg = _show getOrDefault ["message", ""];
// Texte d'une seule ligne : balises et retours à la ligne retirés.
private _plain = (_msg regexReplace ["<[^>]*>", ""]) regexReplace ["[\r\n\t]+", " "];
// Entités HTML remplacées comme des chaînes entières (splitString coupait sur chaque lettre de « &lt; », « &apos; »… :
// « votre » devenait « v'<re »). &amp; en dernier pour ne pas recréer d'entité.
{ _plain = [_plain, _x select 0, _x select 1] call CBA_fnc_replace; } forEach [["&lt;", "<"], ["&gt;", ">"], ["&apos;", "'"], ["&quot;", """"], ["&amp;", "&"]];

private _made = [];
private _mk = {
    params ["_class", "_pos", ["_text", ""]];
    private _c = _d ctrlCreate [_class, -1];
    _c ctrlSetPosition _pos;
    if (_text isNotEqualTo "") then { _c ctrlSetText _text; };
    _c ctrlCommit 0;
    _made pushBack _c;
    _c
};
private _bg = ["COMSPEC_RscText", [_x0, _y, _w, _h]] call _mk;
_bg ctrlSetBackgroundColor [0.05, 0.065, 0.06, 0.97];
private _bar = ["COMSPEC_RscText", [_x0, _y, _pad * 0.6, _h]] call _mk;
_bar ctrlSetBackgroundColor _color;
private _tx = _x0 + _pad * 1.4;
private _lb = ["COMSPEC_RscText", [_tx, _y, _w * 0.3, _h], _label] call _mk;
_lb ctrlSetFont "RobotoCondensedBold";
_lb ctrlSetFontHeight _fs;
_lb ctrlSetTextColor _color;
private _lw = (ctrlTextWidth _lb) + _pad * 0.6;
_lb ctrlSetPosition [_tx, _y, _lw, _h];
_lb ctrlCommit 0;
// Compteur des toasts en attente, à droite.
private _more = (count _q) - 1;
private _cw = 0;
if (_more > 0) then {
    private _cnt = ["COMSPEC_RscTextRight", [_x0, _y, _w - _pad, _h], format ["+%1", _more]] call _mk;
    _cnt ctrlSetFontHeight _fs;
    _cnt ctrlSetTextColor [0.54, 0.60, 0.58, 1];
    _cw = (ctrlTextWidth _cnt) + _pad;
};
private _mx = _tx + _lw;
private _mw = (_x0 + _w - _pad - _cw) - _mx;
private _m = ["COMSPEC_RscText", [_mx, _y, _mw, _h], _plain] call _mk;
_m ctrlSetFontHeight _fs;
// Coupe au dernier mot qui tient, avec « … ».
if ((ctrlTextWidth _m) > _mw) then {
    private _codes = toArray _plain;
    private _n = floor ((count _codes) * _mw / (ctrlTextWidth _m)) max 1;
    while {_n > 1} do {
        _m ctrlSetText ((toString (_codes select [0, _n])) + "…");
        if ((ctrlTextWidth _m) <= _mw) exitWith {};
        _n = _n - 2;
    };
};
// Bouton invisible sur tout le toast : clic = masquer ; info-bulle = texte complet.
private _btn = ["COMSPEC_RscButtonInvisible", [_x0, _y, _w, _h]] call _mk;
_btn ctrlSetTooltip format ["%1 (clic : masquer)", _plain];
_btn setVariable ["toastId", _show getOrDefault ["id", -1]];
_btn ctrlAddEventHandler ["ButtonClick", {
    params ["_c"];
    private _id = _c getVariable ["toastId", -1];
    private _st = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
    _st set ["notifications", (_st getOrDefault ["notifications", []]) select { (_x getOrDefault ["id", -2]) isNotEqualTo _id }];
    [{ [] call comspec_atak_native_fnc_notificationsRender; }] call CBA_fnc_execNextFrame;
    true
}];
_btn ctrlShow (_l get "interactive");
// Entrée en fondu, sans déplacer le reste de l'écran.
if ((_show getOrDefault ["id", -1]) isNotEqualTo (uiNamespace getVariable ["COMSPEC_ATAK_ToastShown", -1])) then {
    { _x ctrlSetFade 1; _x ctrlCommit 0; _x ctrlSetFade 0; _x ctrlCommit 0.15; } forEach (_made - [_btn]);
};
uiNamespace setVariable ["COMSPEC_ATAK_ToastShown", _show getOrDefault ["id", -1]];
uiNamespace setVariable ["COMSPEC_ATAK_ToastControls", _made];
