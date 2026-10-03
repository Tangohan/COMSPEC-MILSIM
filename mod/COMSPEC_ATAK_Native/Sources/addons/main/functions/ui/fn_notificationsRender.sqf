/* Toasts en haut de la zone de contenu, trois au plus. */
disableSerialization;
private _d = ([] call comspec_atak_native_fnc_display);
if (isNull _d) exitWith {};
{ ctrlDelete _x; } forEach (uiNamespace getVariable ["COMSPEC_ATAK_ToastControls", []]);
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _q = (_s getOrDefault ["notifications", []]) select { (_x getOrDefault ["expires", 0]) > diag_tickTime };
_s set ["notifications", _q];
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["_bx", "_by", "_bw", ""];
private _pad = _l get "pad";
private _h = (_l get "font") * 2.4;
private _w = [_bw * 0.4, _bw] select (_l get "mini");
private _made = [];
{
    if (_forEachIndex >= 3) then { continue };
    private _color = switch (_x getOrDefault ["type", "INFO"]) do {
        case "WARNING": { "#f2ab33" };
        case "ERROR";
        case "TACTICAL": { "#e5604f" };
        default { "#5cc76b" };
    };
    private _c = _d ctrlCreate ["COMSPEC_RscCard", -1];
    _c ctrlSetPosition [_bx + _bw - _w + _pad, _by + _pad + _forEachIndex * (_h + _pad), _w - 2 * _pad, _h];
    _c ctrlSetStructuredText parseText format ["<t size='0.8' color='%1'>%2</t><br/><t size='0.9'>%3</t>", _color, _x getOrDefault ["type", "INFO"], _x getOrDefault ["message", ""]];
    _c ctrlCommit 0;
    _made pushBack _c;
} forEach _q;
uiNamespace setVariable ["COMSPEC_ATAK_ToastControls", _made];
