/* Lanceur : grille d'icônes par section, 3 colonnes en mini, 6 en plein écran. */
disableSerialization;
private _d = ([] call comspec_atak_native_fnc_display);
if (isNull _d) exitWith { false };
private _g = _d displayCtrl 88531;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", ""];
private _cols = _l get "cols";
private _pad = (_l get "pad") * 2;
private _font = _l get "fontSmall";
private _tw = (_bw - 0.012 - _pad * (_cols + 1)) / _cols;
private _th = _tw * pixelH / pixelW * 0.92;
private _secH = _font * 1.7;
private _apps = ([] call comspec_atak_native_fnc_appList) select { [_x] call comspec_atak_native_fnc_appVisible };
private _sections = [];
{ _sections pushBackUnique (_x get "section"); } forEach _apps;

private _y = _pad;
{
    private _sec = _x;
    private _lbl = ["COMSPEC_RscLabel", [_pad, _y, _bw - 2 * _pad, _secH], toUpper _sec] call comspec_atak_native_fnc_pageCtrl;
    _lbl ctrlSetFontHeight _font;
    _y = _y + _secH;
    private _items = _apps select { (_x get "section") isEqualTo _sec };
    {
        private _col = _forEachIndex mod _cols;
        private _row = floor (_forEachIndex / _cols);
        private _tile = [_d, _g, [_pad + _col * (_tw + _pad), _y + _row * (_th + _pad), _tw, _th], _x get "icon", _x get "name", [_x get "page"] call comspec_atak_native_fnc_appBadge, _x get "page", _font] call comspec_atak_native_fnc_tileCreate;
        private _list = uiNamespace getVariable ["COMSPEC_ATAK_PageControls", []];
        _list append _tile;
        uiNamespace setVariable ["COMSPEC_ATAK_PageControls", _list];
    } forEach _items;
    _y = _y + (ceil ((count _items) / _cols)) * (_th + _pad) + _pad;
} forEach _sections;
true
