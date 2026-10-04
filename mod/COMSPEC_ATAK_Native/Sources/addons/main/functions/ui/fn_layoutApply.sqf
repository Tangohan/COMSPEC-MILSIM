/*
    Place la coque, la barre d'état (batterie, météo, heure, GPS, réseau), la barre d'app,
    le dock ou le rail, la carte et la zone de contenu.
*/
disableSerialization;
private _d = [] call comspec_atak_native_fnc_display;
if (isNull _d) exitWith { false };
private _l = [] call comspec_atak_native_fnc_layoutGet;
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _page = _s getOrDefault ["activePage", "LAUNCHER"];
private _interactive = _l get "interactive";
private _ratio = pixelH / pixelW;

private _set = {
    params ["_idc", "_pos"];
    private _c = _d displayCtrl _idc;
    _c ctrlSetPosition _pos;
    _c ctrlCommit 0;
    _c
};

private _phone = [88509, _l get "phone"] call _set;
_phone ctrlSetText (_l get "phoneTexture");
(_l get "device") params ["_dx", "_dy", "_dw", "_dh"];
(_l get "status") params ["", "_sy", "", "_sh"];
(_l get "appbar") params ["", "_ay", "", "_ah"];
private _pad = _l get "pad";
private _font = _l get "font";
private _fontS = _l get "fontSmall";

[88510, _l get "device"] call _set;
[88511, _l get "status"] call _set;

// Barre d'état : batterie | météo ........ heure ........ GPS | réseau
private _ih = _sh * 0.8;
private _iw = _ih * 2 / _ratio;
private _iy = _sy + (_sh - _ih) / 2;
[88512, [_dx + _pad, _iy, _iw, _ih]] call _set;
[88513, [_dx + _pad + _iw, _sy, _iw * 1.3, _sh]] call _set;
private _wx = _dx + _pad * 2 + _iw * 2.3;
private _ww = _iw * 3.6;
[88514, [_wx, _sy + _sh * 0.08, _ww, _sh * 0.84]] call _set;
[88523, [_wx + _pad * 0.5, _iy, _ih / _ratio, _ih]] call _set;
[88524, [_wx + _pad + _ih / _ratio, _sy, _ww - _ih / _ratio - _pad, _sh]] call _set;
[88525, [_dx + _dw * 0.42, _sy, _dw * 0.16, _sh]] call _set;
[88548, [_dx + _dw * 0.58, _iy, _ih / _ratio, _ih]] call _set;
[88549, [_dx + _dw * 0.58 + _pad + _ih / _ratio, _iy, _ih / _ratio, _ih]] call _set;
[88527, [_dx + _dw - _pad - _iw, _iy, _iw, _ih]] call _set;
[88526, [_dx + _dw - _pad * 2 - _iw - _ih / _ratio, _iy, _ih / _ratio, _ih]] call _set;
{ (_d displayCtrl _x) ctrlSetFontHeight _fontS; } forEach [88513, 88524];
// Mémoire vive (« RAM nn % », mise à jour par fn_statusUpdate) : entre le logo de liaison et le GPS, si la place suffit.
private _ram = uiNamespace getVariable ["COMSPEC_ATAK_RamCtrl", controlNull];
if (isNull _ram || {(ctrlParent _ram) isNotEqualTo _d}) then {
    _ram = _d ctrlCreate ["COMSPEC_RscTextRight", -1];
    _ram ctrlSetTextColor [0.62, 0.70, 0.66, 1];
    uiNamespace setVariable ["COMSPEC_ATAK_RamCtrl", _ram];
};
private _rx0 = _dx + _dw * 0.58 + _pad * 2 + 2 * _ih / _ratio;
private _rx1 = _dx + _dw - _pad * 3 - _iw - ([0, _ih / _ratio] select _interactive);
_ram ctrlSetPosition [_rx0, _sy, (_rx1 - _rx0) max 0, _sh];
_ram ctrlSetFontHeight (_fontS * 0.9);
_ram ctrlCommit 0;
if ((ctrlText _ram) isEqualTo "") then { _ram ctrlSetText "RAM 00%"; };
_ram ctrlShow ((ctrlTextWidth _ram) <= (_rx1 - _rx0));
(_d displayCtrl 88525) ctrlSetFontHeight _font;

// Barre d'app : retour | titre ........ rotation | mini/plein | apps
[88515, _l get "appbar"] call _set;
private _bh = _ah * 0.62;
private _bw = _bh / _ratio;
private _by = _ay + (_ah - _bh) / 2;
private _step = _bw * 1.7;
[88517, [_dx + _pad, _by, _bw, _bh]] call _set;
[88518, [_dx + _pad * 2 + _bw * 1.2, _ay, _dw - _step * 4, _ah]] call _set;
[88519, [_dx + _dw - _pad - _bw, _by, _bw, _bh]] call _set;
[88521, [_dx + _dw - _pad - _bw - _step, _by, _bw, _bh]] call _set;
[88522, [_dx + _dw - _pad - _bw - _step * 2, _by, _bw, _bh]] call _set;
[88528, [_dx + _dw * 0.45, _ay, _dw * 0.55 - _pad, _ah]] call _set;
(_d displayCtrl 88518) ctrlSetFontHeight _font;
(_d displayCtrl 88528) ctrlSetFontHeight _fontS;
(_d displayCtrl 88528) ctrlSetText (["Ctrl+Maj+U : prendre en main", ""] select _interactive);
{ (_d displayCtrl _x) ctrlShow _interactive; } forEach [88517, 88519, 88521, 88522, 88526];
(_d displayCtrl 88522) ctrlShow (_interactive && {_l get "mini"});
(_d displayCtrl 88521) ctrlSetText format ["\z\comspec_atak_native\addons\main\data\ui_%1.paa", ["collapse", "expand"] select (_l get "mini")];
(_d displayCtrl 88517) ctrlEnable ((count (_s getOrDefault ["history", []])) > 1);

// Touches physiques de la coque (fractions mesurées sur tools/src/android_s7_ca.png, paysage ;
// en portrait la texture est tournée d'un quart de tour horaire : (x, y) devient (1 - y, x)).
(_l get "phone") params ["_px", "_py", "_pw", "_ph"];
{
    _x params ["_idc", "_fx", "_fy", "_fw", "_fh"];
    if !(_l get "landscape") then { private _t = _fx; _fx = 1 - _fy; _fy = _t; _t = _fw; _fw = _fh; _fh = _t; };
    private _c = [_idc, [_px + _pw * (_fx - _fw / 2), _py + _ph * (_fy - _fh / 2), _pw * _fw, _ph * _fh]] call _set;
    _c ctrlShow _interactive;
} forEach [[88544, 0.782, 0.405, 0.045, 0.07], [88545, 0.783, 0.500, 0.045, 0.075], [88546, 0.782, 0.594, 0.045, 0.07], [88547, 0.396, 0.298, 0.06, 0.03]];

[88516, _l get "dockRect"] call _set;
[88520, _l get "rail"] call _set;

(_l get "body") params ["_bx", "_byy", "_bww", "_bhh"];
private _isMap = _page isEqualTo "MAP";
private _inspW = [0, _l get "inspW"] select _isMap;
[88530, [_bx, _byy, _bww - _inspW, _bhh]] call _set;
[88531, [_bx, _byy, _bww, _bhh]] call _set;
[88540, [_bx + _bww - _inspW, _byy, _inspW, _bhh]] call _set;
[88541, [_bx + _bww - _inspW + _pad, _byy + _pad, (_inspW - 2 * _pad) max 0, (_bhh - 2 * _pad) max 0]] call _set;
// Fond d'écran de l'accueil (réglage profil : athena, ops ou aucun).
private _wall = profileNamespace getVariable ["COMSPEC_ATAK_Wallpaper", "topo"];
private _walls = ["topo", "night", "desert", "olive", "dark", "soar", "athena", "ops"];
private _blur = ["", "_blur"] select (profileNamespace getVariable ["COMSPEC_ATAK_WallBlur", false]);
private _wc = [88542, [_bx, _byy, _bww, _bhh]] call _set;
_wc ctrlSetText (["", format ["\z\comspec_atak_native\addons\main\data\wall_%1%3_%2.paa", _wall, ["port", "land"] select (_l get "landscape"), _blur]] select (_wall in _walls));
_wc ctrlShow (_page isEqualTo "LAUNCHER" && {_wall in _walls});
(_d displayCtrl 88530) ctrlShow _isMap;
(_d displayCtrl 88531) ctrlShow !_isMap;
(_d displayCtrl 88540) ctrlShow (_isMap && {_inspW > 0});
(_d displayCtrl 88541) ctrlShow (_isMap && {_inspW > 0});

[] call comspec_atak_native_fnc_dockRender;
true
