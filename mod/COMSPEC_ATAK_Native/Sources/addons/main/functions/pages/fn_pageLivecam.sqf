/*
    Live cam : caméra poitrine des autres porteurs d'ATAK du camp.
    Une caméra locale est fixée au buste du porteur choisi (os "spine3", regard vers l'avant)
    et rendue dans l'écran du téléphone (render-to-texture). Modes : jour, vision nocturne, thermique.
    La caméra est détruite dès qu'on quitte la page (fn_livecamStop, appelé par pageClear).
*/
disableSerialization;
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _pad = (_l get "pad") * 2;
private _font = _l get "font";
private _fs = _l get "fontSmall";
private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
private _gw = _bw - 0.012;
private _rowH = _font * 1.5;
private _land = _l get "landscape";

// Porteurs d'ATAK du camp (sauf moi), vivants.
private _feeds = allPlayers select { _x isNotEqualTo player && {alive _x} && {side group _x isEqualTo side group player} && {[_x] call comspec_atak_native_fnc_hasDevice} };
private _target = _s getOrDefault ["livecamTarget", ""];
private _unit = objectFromNetId _target;
if (isNull _unit || {!(_unit in _feeds)}) then { _unit = _feeds param [0, objNull]; _target = [netId _unit, ""] select (isNull _unit); _s set ["livecamTarget", _target]; };

// Liste (à gauche en horizontal, en haut en vertical) et image.
private _listRect = if (_land) then { [_pad, _pad, _gw * 0.3, _bh - 2 * _pad] } else { [_pad, _pad, _gw - 2 * _pad, (_bh * 0.28) min (_rowH * 5)] };
private _list = ["COMSPEC_RscListBox", _listRect] call comspec_atak_native_fnc_pageCtrl;
_list ctrlSetFontHeight _fs;
{
    private _i = _list lbAdd format ["%1 · %2 m", [_x, true] call comspec_atak_native_fnc_unitCallsign, round (player distance _x)];
    _list lbSetData [_i, netId _x];
    _list lbSetPicture [_i, "\z\comspec_atak_native\addons\main\data\app_photos.paa"];
    if (_x isEqualTo _unit) then { _list lbSetCurSel _i; };
} forEach _feeds;
if ((count _feeds) isEqualTo 0) then { _list lbAdd "Aucun autre ATAK en ligne"; };
_list ctrlAddEventHandler ["LBSelChanged", {
    params ["_c", "_i"];
    private _id = _c lbData _i;
    if (_id isEqualTo "") exitWith {};
    (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) set ["livecamTarget", _id];
    [{ ["LIVECAM"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
}];

private _vx = if (_land) then { _pad * 2 + _gw * 0.3 } else { _pad };
private _vy = if (_land) then { _pad } else { _pad * 2 + (_listRect select 3) };
private _vw = _gw - _vx - _pad;
private _btnH = _rowH;
private _vh = _bh - _vy - _pad * 2 - _btnH;
private _bg = ["COMSPEC_RscPanel", [_vx, _vy, _vw, _vh]] call comspec_atak_native_fnc_pageCtrl;
_bg ctrlSetBackgroundColor [0, 0, 0, 1];
if (isNull _unit) exitWith {
    private _t = ["COMSPEC_RscStructuredText", [_vx, _vy + _vh * 0.4, _vw, _rowH * 2]] call comspec_atak_native_fnc_pageCtrl;
    _t ctrlSetStructuredText parseText "<t align='center' color='#8a9a93'>Pas de flux : aucun porteur d'ATAK du camp en ligne.</t>";
    true
};
private _mode = _s getOrDefault ["livecamMode", 0];
[_unit, _mode] call comspec_atak_native_fnc_livecamStart;
private _pic = ["COMSPEC_RscPhone", [_vx, _vy, _vw, _vh], "#(argb,512,512,1)r2t(comspec_livecam,1.0)"] call comspec_atak_native_fnc_pageCtrl;
private _osd = ["COMSPEC_RscStructuredText", [_vx + _pad / 2, _vy + _pad / 2, _vw - _pad, _rowH * 1.6]] call comspec_atak_native_fnc_pageCtrl;
_osd ctrlSetStructuredText parseText format ["<t font='RobotoCondensedBold' color='#e5483a'>● LIVE</t>  <t font='RobotoCondensedBold'>%1</t><br/><t size='0.8' color='#c9d4cf'>%2 · %3 m · cap %4°</t>",
    [_unit, true] call comspec_atak_native_fnc_unitCallsign, [getPosASL _unit, 8] call comspec_atak_native_fnc_gridRef, round (player distance _unit), round getDir _unit];
uiNamespace setVariable ["COMSPEC_ATAK_LivecamOsd", _osd];

// Modes de vision
private _modes = [["JOUR", 0], ["VISION NOCTURNE", 1], ["THERMIQUE", 2]];
private _mw = (_vw - _pad) / 3;
{
    _x params ["_t", "_v"];
    private _b = ["COMSPEC_RscButton", [_vx + _forEachIndex * (_mw + _pad / 2), _vy + _vh + _pad, _mw, _btnH], _t] call comspec_atak_native_fnc_pageCtrl;
    _b ctrlSetFontHeight _fs;
    if (_v isEqualTo _mode) then { _b ctrlSetBackgroundColor [0.36, 0.78, 0.42, 0.9]; _b ctrlSetTextColor [0.03, 0.05, 0.04, 1]; };
    _b ctrlAddEventHandler ["ButtonClick", compile format ["(uiNamespace getVariable ['COMSPEC_ATAK_State', createHashMap]) set ['livecamMode', %1]; 'comspec_livecam' setPiPEffect [%1];
        [{ ['LIVECAM'] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;", _v]];
} forEach _modes;
true
