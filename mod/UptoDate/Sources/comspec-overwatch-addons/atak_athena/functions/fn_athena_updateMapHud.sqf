/*
    Tick HUD carte ATAK Enhanced :
    - cartouche curseur (GRID, DST, ELEV, RNG, BRG, dEL)
    - cartouche unite suivie (GROUP, CALLSIGN, GRID, ALT, SPD, horodatage)
    - cap en degres vrais, zoom +/−
    - restyle charbon / cyan du tiroir IceMan (fond, cases indicatif, outils)
    Les cartouches restent DANS le rectangle carte (jamais sur le tiroir droit).
*/
if (!hasInterface) exitWith {};

private _disp = uiNamespace getVariable ["cTab_Android_dlg", displayNull];
if (isNull _disp) then {
    _disp = uiNamespace getVariable ["cTab_Android_dsp", displayNull];
};

private _idcHeading = 99887810;
private _idcCursor = 99887811;
private _idcUnit = 99887812;
private _idcZoomIn = 99887820;
private _idcZoomOut = 99887821;

private _fncHide = {
    params ["_d"];
    if (isNull _d) exitWith {};
    {
        private _c = _d displayCtrl _x;
        if (!isNull _c) then { _c ctrlShow false; };
    } forEach [99887810, 99887811, 99887812, 99887820, 99887821];
};

if (isNull _disp) exitWith {};

private _overlay = uiNamespace getVariable ["COMSPEC_DeviceOverlay_Ctrl", controlNull];
private _overlayOn = !isNull _overlay && {ctrlShown _overlay} && {ctrlParent _overlay isEqualTo _disp};
if (_overlayOn) exitWith { [_disp] call _fncHide; };

private _mode = "";
if (!isNil "cTab_fnc_getSettings") then {
    _mode = ["cTab_Android_dlg", "mode"] call cTab_fnc_getSettings;
    if (!(_mode isEqualType "")) then { _mode = ""; };
};
if (_mode isNotEqualTo "BFT" && {_mode isNotEqualTo ""}) exitWith { [_disp] call _fncHide; };

private _mapCtrl = controlNull;
if (!isNil "cTab_fnc_getSettings" && {!isNil "cTab_fnc_getFromPairs"}) then {
    private _mapName = ["cTab_Android_dlg", "mapType"] call cTab_fnc_getSettings;
    private _mapTypes = ["cTab_Android_dlg", "mapTypes"] call cTab_fnc_getSettings;
    private _mapIdc = [_mapTypes, _mapName] call cTab_fnc_getFromPairs;
    if (_mapIdc isEqualType 0) then { _mapCtrl = _disp displayCtrl _mapIdc; };
};
if (isNull _mapCtrl) then {
    {
        private _c = _disp displayCtrl _x;
        if (!isNull _c && {ctrlShown _c}) exitWith { _mapCtrl = _c; };
    } forEach [1201, 1202, 16];
};
if (isNull _mapCtrl || {!ctrlShown _mapCtrl}) exitWith { [_disp] call _fncHide; };

(ctrlPosition _mapCtrl) params ["_mx", "_my", "_mw", "_mh"];
if (_mw < 0.08 || {_mh < 0.08}) exitWith { [_disp] call _fncHide; };

private _bgPanel = [0.071, 0.071, 0.071, 0.92];
private _bgTile = [0.12, 0.12, 0.12, 0.94];
private _cyan = [0.37, 0.78, 0.95, 1];
private _yellow = [1, 0.78, 0.12, 1];
private _white = [0.95, 0.96, 0.97, 1];

// --- Restyle IceMan / BCE chrome (runtime, no PBO patch) ---
private _bgGroup = _disp displayCtrl 4660;
if (!isNull _bgGroup) then {
    private _menuBg = _bgGroup controlsGroupCtrl 9;
    if (!isNull _menuBg) then { _menuBg ctrlSetBackgroundColor [0.055, 0.055, 0.055, 0.96]; };
};
{
    private _c = _disp displayCtrl _x;
    if (isNull _c) then { continue };
    _c ctrlSetBackgroundColor _bgTile;
    _c ctrlSetTextColor _white;
} forEach [46600, 1300, 17000 + 1200, 17000 + 1300];

{
    private _c = _disp displayCtrl (17000 + _x);
    if (isNull _c) then { continue };
    _c ctrlSetBackgroundColor _bgPanel;
    _c ctrlSetTextColor _cyan;
    // Vanilla self-info sits on the map; our unit cartouche replaces it.
    _c ctrlShow false;
} forEach [2620, 2621, 2622];

{
    private _c = _disp displayCtrl _x;
    if (isNull _c) then { continue };
    _c ctrlShow false;
} forEach [2617, 2618, 2619, 2620];

{
    private _c = _disp displayCtrl (17000 + _x);
    if (isNull _c) then { continue };
    _c ctrlSetBackgroundColor [0.18, 0.14, 0.04, 0.88];
    _c ctrlSetTextColor _yellow;
} forEach [2615, 2616];

// IceMan Drone Ops + BCE live-feed frames: charcoal / cyan, no fake GCS.
{
    private _c = _disp displayCtrl _x;
    if (isNull _c) then { continue };
    _c ctrlSetBackgroundColor _bgPanel;
    _c ctrlSetTextColor _cyan;
} forEach [8800, 8801, 8810, 8812, 8820, 8830, 8840, 8851, 8852, 8853, 8860, 8861];
{
    private _c = _disp displayCtrl (17000 + _x);
    if (isNull _c) then { continue };
    _c ctrlSetBackgroundColor [0.04, 0.04, 0.04, 0.85];
    _c ctrlSetTextColor _cyan;
} forEach [4630, 4631, 46310, 4632];

private _fncEnsure = {
    params ["_d", "_idc", "_class"];
    private _c = _d displayCtrl _idc;
    if (isNull _c || {ctrlParent _c isNotEqualTo _d}) then {
        if (!isNull _c) then { ctrlDelete _c; };
        _c = _d ctrlCreate [_class, _idc];
    };
    _c
};

private _heading = [_disp, _idcHeading, "RscStructuredText"] call _fncEnsure;
private _cursorBox = [_disp, _idcCursor, "RscStructuredText"] call _fncEnsure;
private _unitBox = [_disp, _idcUnit, "RscStructuredText"] call _fncEnsure;
private _zoomIn = [_disp, _idcZoomIn, "RscButton"] call _fncEnsure;
private _zoomOut = [_disp, _idcZoomOut, "RscButton"] call _fncEnsure;

if (isNull _heading || {isNull _cursorBox} || {isNull _unitBox}) exitWith {};

private _pad = _mw * 0.012;
private _headW = (_mw * 0.16) max 0.07;
private _headH = (_mh * 0.055) max 0.028;
_heading ctrlSetPosition [_mx + _pad, _my + _pad, _headW, _headH];
_heading ctrlSetBackgroundColor [0.16, 0.12, 0.02, 0.92];
_heading ctrlEnable false;

private _zW = (_mw * 0.046) max 0.028;
private _zH = (_mh * 0.055) max 0.028;
private _zX = _mx + _pad;
private _zY = _my + _pad + _headH + (_mh * 0.012);
_zoomIn ctrlSetPosition [_zX, _zY, _zW, _zH];
_zoomOut ctrlSetPosition [_zX, _zY + _zH + (_mh * 0.006), _zW, _zH];
{
    _x params ["_btn", "_label"];
    _btn ctrlSetText _label;
    _btn ctrlSetFont "RobotoCondensedBold";
    _btn ctrlSetFontHeight (_zH * 0.72);
    _btn ctrlSetBackgroundColor _bgTile;
    _btn ctrlSetTextColor _white;
    _btn ctrlEnable true;
    _btn ctrlShow true;
    _btn ctrlCommit 0;
} forEach [[_zoomIn, "+"], [_zoomOut, "-"]];

if (isNil {_zoomIn getVariable "COMSPEC_ATAK_ZoomWired"}) then {
    _zoomIn setVariable ["COMSPEC_ATAK_ZoomWired", true];
    _zoomIn ctrlAddEventHandler ["ButtonClick", {
        [0.72] call comspec_overwatch_atak_athena_fnc_athena_mapHudZoom;
    }];
};
if (isNil {_zoomOut getVariable "COMSPEC_ATAK_ZoomWired"}) then {
    _zoomOut setVariable ["COMSPEC_ATAK_ZoomWired", true];
    _zoomOut ctrlAddEventHandler ["ButtonClick", {
        [1.38] call comspec_overwatch_atak_athena_fnc_athena_mapHudZoom;
    }];
};

private _boxW = (_mw * 0.34) min 0.28;
if (_boxW < 0.12) then { _boxW = (_mw * 0.42) max 0.10; };
private _boxH = (_mh * 0.195) max 0.072;
private _boxY = _my + _mh - _boxH - _pad;
_cursorBox ctrlSetPosition [_mx + _pad, _boxY, _boxW, _boxH];
_cursorBox ctrlSetBackgroundColor _bgPanel;
_cursorBox ctrlEnable false;

private _unitX = _mx + _pad + _boxW + (_mw * 0.012);
private _unitMax = _mx + _mw - _pad;
if ((_unitX + _boxW) > _unitMax) then {
    _unitX = _unitMax - _boxW;
};
if (_unitX < (_mx + _pad + 0.04)) then {
    // Carte trop etroite : empiler sous le curseur n'est pas possible ; coller a droite du curseur.
    _unitX = _mx + _mw - _boxW - _pad;
};
_unitBox ctrlSetPosition [_unitX, _boxY, _boxW, _boxH];
_unitBox ctrlSetBackgroundColor _bgPanel;
_unitBox ctrlEnable false;

private _fncGrid = {
    params ["_pos"];
    private _g = mapGridPosition _pos;
    if (!(_g isEqualType "")) then { _g = str _g; };
    private _len = count _g;
    if (_len >= 10) exitWith {
        format ["%1 %2", _g select [0, 5], _g select [5, 5]]
    };
    if (_len >= 8) exitWith {
        format ["%1 %2", _g select [0, 4], _g select [4, 4]]
    };
    _g
};

private _fncKm = {
    params ["_m"];
    if (!(_m isEqualType 0)) exitWith { "--" };
    if (_m >= 1000) exitWith {
        format ["%1 KM", ((round (_m / 100)) / 10)]
    };
    format ["%1 M", round _m]
};

private _player = if (!isNil "cTab_player" && {!isNull cTab_player}) then { cTab_player } else { player };
private _veh = vehicle _player;
private _playerPos = getPosASLVisual _veh;
private _playerHdg = round (direction _veh);

private _cursorPos = [];
if (!isNil "cTabMapCursorPos" && {cTabMapCursorPos isEqualType []} && {(count cTabMapCursorPos) >= 2}) then {
    _cursorPos = +cTabMapCursorPos;
};
if (_cursorPos isEqualTo []) then {
    _cursorPos = _mapCtrl ctrlMapScreenToWorld getMousePosition;
};
if (!(_cursorPos isEqualType []) || {(count _cursorPos) < 2}) then {
    _cursorPos = +_playerPos;
};
if ((count _cursorPos) < 3) then { _cursorPos pushBack 0; };

private _elev = round (getTerrainHeightASL _cursorPos);
private _playerEl = round (_playerPos select 2);
private _dEl = _elev - _playerEl;
private _dst = _playerPos distance2D _cursorPos;
private _brg = round (_playerPos getDir _cursorPos);
if (_brg < 0) then { _brg = _brg + 360; };
private _rng = _playerPos distance _cursorPos;

private _cursorHtml = format [
    "<t font='EtelkaMonospacePro' size='0.68' color='#5EC7F2' align='left'>" +
    "%1<br/>" +
    "DST  %2<br/>" +
    "ELEV %3 M    BRG %4%5T<br/>" +
    "RNG  %6    dEL %7%8 M</t>",
    [_cursorPos] call _fncGrid,
    [_dst] call _fncKm,
    _elev,
    [_brg, 3] call (missionNamespace getVariable ["CBA_fnc_formatNumber", {str (_this select 0)}]),
    toString [176],
    [_rng] call _fncKm,
    ["", "+"] select (_dEl > 0),
    _dEl
];

private _unit = _player;
if (!isNil "cTab_fnc_findUserMarker" && {_cursorPos isNotEqualTo []}) then {
    private _hit = [_mapCtrl, _cursorPos] call cTab_fnc_findUserMarker;
    if (_hit isEqualType objNull && {!isNull _hit}) then { _unit = vehicle _hit; };
};
if (_unit isEqualTo _player) then {
    private _scan = ((_mw / 0.4) * 12) max 8;
    {
        if (_x isEqualTo _player) then { continue };
        if ((_x distance2D _cursorPos) < _scan) exitWith { _unit = vehicle _x; };
    } forEach (units group _player);
};

private _uPos = getPosASLVisual _unit;
private _grpName = groupId (group _unit);
if (!(_grpName isEqualType "") || {_grpName isEqualTo ""}) then { _grpName = "---"; };
private _cs = name _unit;
if (_unit isKindOf "AllVehicles" && {!(_unit isKindOf "CAManBase")}) then {
    private _crew = crew _unit;
    if (_crew isNotEqualTo []) then { _cs = name (_crew select 0); };
};
if (!(_cs isEqualType "") || {_cs isEqualTo ""}) then { _cs = "---"; };
private _alt = round (_uPos select 2);
private _spd = round (abs (speed _unit));
private _now = date;
private _zulu = format [
    "%1%2%3Z",
    [_now select 2, 2] call (missionNamespace getVariable ["CBA_fnc_formatNumber", {str (_this select 0)}]),
    [_now select 3, 2] call (missionNamespace getVariable ["CBA_fnc_formatNumber", {str (_this select 0)}]),
    [_now select 4, 2] call (missionNamespace getVariable ["CBA_fnc_formatNumber", {str (_this select 0)}])
];

private _unitHtml = format [
    "<t font='EtelkaMonospacePro' size='0.66' color='#5EC7F2' align='left'>" +
    "GROUP     %1<br/>" +
    "CALLSIGN  %2<br/>" +
    "%3<br/>" +
    "ALT %4 M     SPD %5 KM/H<br/>" +
    "%6</t>",
    _grpName,
    _cs,
    [_uPos] call _fncGrid,
    _alt,
    _spd,
    _zulu
];

private _hdgHtml = format [
    "<t font='EtelkaMonospacePro' size='0.78' color='#FFD01F' align='center' valign='middle'>%1%2T</t>",
    [_playerHdg, 3] call (missionNamespace getVariable ["CBA_fnc_formatNumber", {str (_this select 0)}]),
    toString [176]
];

_heading ctrlSetStructuredText parseText _hdgHtml;
_cursorBox ctrlSetStructuredText parseText _cursorHtml;
_unitBox ctrlSetStructuredText parseText _unitHtml;

{
    _x ctrlShow true;
    _x ctrlCommit 0;
} forEach [_heading, _cursorBox, _unitBox, _zoomIn, _zoomOut];
