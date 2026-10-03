/*
    Place la coque (barre d'état, barre d'app, dock ou rail) et la zone de contenu.
    La carte n'occupe la zone que sur la page MAP ; l'inspecteur n'existe qu'en plein écran.
*/
disableSerialization;
private _d = findDisplay 88500;
if (isNull _d) exitWith { false };
private _l = [] call comspec_atak_native_fnc_layoutGet;
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _page = _s getOrDefault ["activePage", "LAUNCHER"];

private _set = {
    params ["_idc", "_pos"];
    private _c = _d displayCtrl _idc;
    _c ctrlSetPosition _pos;
    _c ctrlCommit 0;
    _c
};

(_l get "device") params ["_dx", "_dy", "_dw", "_dh"];
(_l get "status") params ["", "_sy", "", "_sh"];
(_l get "appbar") params ["", "_ay", "", "_ah"];
private _pad = _l get "pad";
private _font = _l get "font";
private _fontS = _l get "fontSmall";
private _sq = _ah * pixelW / pixelH;

[88510, _l get "device"] call _set;
[88511, _l get "status"] call _set;
[88512, [_dx + _pad, _sy, _dw * 0.6, _sh]] call _set;
[88513, [_dx + _dw * 0.4 - _pad, _sy, _dw * 0.6, _sh]] call _set;
[88515, _l get "appbar"] call _set;
[88517, [_dx, _ay, _sq * 1.2, _ah]] call _set;
[88518, [_dx + _sq * 1.35, _ay, _dw - _sq * 5.2, _ah]] call _set;
[88521, [_dx + _dw - _sq * 3.75, _ay, _sq * 1.6, _ah]] call _set;
[88519, [_dx + _dw - _sq * 2.05, _ay, _sq * 2.0, _ah]] call _set;
[88516, _l get "dock"] call _set;
[88520, _l get "rail"] call _set;
{ (_d displayCtrl _x) ctrlSetFontHeight _font; } forEach [88517, 88518, 88519];
{ (_d displayCtrl _x) ctrlSetFontHeight _fontS; } forEach [88512, 88513, 88521];
(_d displayCtrl 88521) ctrlSetText (["MIN", "MAX"] select (_l get "mini"));

(_l get "body") params ["_bx", "_by", "_bw", "_bh"];
private _isMap = _page isEqualTo "MAP";
private _inspW = [0, _l get "inspW"] select _isMap;
[88530, [_bx, _by, _bw - _inspW, _bh]] call _set;
[88531, [_bx, _by, _bw, _bh]] call _set;
[88540, [_bx + _bw - _inspW, _by, _inspW, _bh]] call _set;
[88541, [_bx + _bw - _inspW + _pad, _by + _pad, (_inspW - 2 * _pad) max 0, (_bh - 2 * _pad) max 0]] call _set;
(_d displayCtrl 88530) ctrlShow _isMap;
(_d displayCtrl 88531) ctrlShow !_isMap;
(_d displayCtrl 88540) ctrlShow (_isMap && {_inspW > 0});
(_d displayCtrl 88541) ctrlShow (_isMap && {_inspW > 0});
(_d displayCtrl 88517) ctrlEnable ((count (_s getOrDefault ["history", []])) > 1);

[] call comspec_atak_native_fnc_dockRender;
true
