/* Raccourcis d'apps : dock en bas en mode mini, rail vertical à gauche en plein écran. */
disableSerialization;
private _d = ([] call comspec_atak_native_fnc_display);
if (isNull _d) exitWith { false };
{ ctrlDelete _x; } forEach (uiNamespace getVariable ["COMSPEC_ATAK_DockControls", []]);

private _l = [] call comspec_atak_native_fnc_layoutGet;
private _active = (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", "LAUNCHER"];
// Dock choisi par le joueur (Réglages > Personnalisation), sinon celui des apps déclarées « dock ».
private _mine = profileNamespace getVariable ["COMSPEC_ATAK_DockApps", []];
private _all = [] call comspec_atak_native_fnc_appList;
private _apps = if ((count _mine) > 0) then { (_mine apply { private _id = _x; (_all select { (_x get "id") isEqualTo _id }) param [0, createHashMap] }) select { (count _x) > 0 && {[_x] call comspec_atak_native_fnc_appVisible} } } else { _all select { (_x get "dock") && {[_x] call comspec_atak_native_fnc_appVisible} } };
private _n = (count _apps) max 1;
private _made = [];

{
    private _app = _x;
    private _pos = if (_l get "dock") then {
        (_l get "dockRect") params ["_x0", "_y0", "_w", "_h"];
        private _cw = _w / _n;
        [_x0 + _forEachIndex * _cw + _cw * 0.08, _y0 + _h * 0.1, _cw * 0.84, _h * 0.8]
    } else {
        (_l get "rail") params ["_x0", "_y0", "_w", "_h"];
        private _ch = (_w * pixelH / pixelW * 0.8) min (_h / _n * 0.9);
        [_x0 + _w * 0.1, _y0 + _w * 0.1 + _forEachIndex * (_ch + _w * 0.1), _w * 0.8, _ch]
    };
    private _tile = [_d, controlNull, _pos, _app get "icon", "", [_app get "page"] call comspec_atak_native_fnc_appBadge, _app get "page", _l get "fontSmall"] call comspec_atak_native_fnc_tileCreate;
    if ((_app get "page") isEqualTo _active) then { (_tile select 0) ctrlSetBackgroundColor ((((([] call comspec_atak_native_fnc_accent) select 0) select [0, 3]) apply { _x * 0.45 }) + [1]); };
    (_tile select (count _tile - 1)) ctrlSetTooltip (_app get "name");
    _made append _tile;
} forEach _apps;

uiNamespace setVariable ["COMSPEC_ATAK_DockControls", _made];
true
