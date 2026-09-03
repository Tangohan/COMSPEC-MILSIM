/*
    Tick HUD carte ATAK Enhanced :
    - bandeau identité noir juste sous l’heure (Indicatif, Rôle, Grille, Radio)
    - cartouche curseur (GRILLE, DIST, SOL, GIS, PORTÉE) en bas à droite
    - pas de boutons zoom +/− (ils se calaient sur le tiroir)
    Ne jamais restyler ni masquer le bouton natif des outils carte, ni le pied d’application IceMan.
    Les cartouches restent DANS le rectangle carte visible (jamais sous le tiroir droit).
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
private _idcAcct = 99887813;

private _fncHide = {
    params ["_d"];
    if (isNull _d) exitWith {};
    {
        private _c = _d displayCtrl _x;
        if (!isNull _c) then { _c ctrlShow false; };
    } forEach [99887810, 99887811, 99887812, 99887813, 99887820, 99887821];
    {
        _x params ["_a", "_b"];
        for "_i" from _a to _b do {
            private _c = _d displayCtrl _i;
            if (!isNull _c) then { _c ctrlShow false };
        };
    } forEach [
        [88540, 88540],
        [88550, 88559],
        [88600, 88640],
        [88650, 88650],
        [88700, 88700],
        [88800, 88815],
        [88900, 88924]
    ];
};

if (isNull _disp) exitWith {
    uiNamespace setVariable ["COMSPEC_MapUI_MouseWired", nil];
    missionNamespace setVariable ["COMSPEC_MapUI_ChromeCleared", false, false];
    if (missionNamespace getVariable ["COMSPEC_MAP_HudOpenLogged", false]) then {
        missionNamespace setVariable ["COMSPEC_MAP_HudOpenLogged", false, false];
        diag_log "[COMSPEC][MAP] Map display closed";
    };
};

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

if (!(missionNamespace getVariable ["COMSPEC_MAP_HudOpenLogged", false])) then {
    missionNamespace setVariable ["COMSPEC_MAP_HudOpenLogged", true, false];
    diag_log "[COMSPEC][MAP] Map display detected";
};

(ctrlPosition _mapCtrl) params ["_mx", "_my", "_mw", "_mh"];
if (_mw < 0.08 || {_mh < 0.08}) exitWith { [_disp] call _fncHide; };

// Carte visible : le tiroir d'apps (4660) recouvre le bord droit si on
// s'aligne sur ctrlPosition brute. Les cartouches restent à gauche du tiroir.
private _visX = _mx;
private _visY = _my;
private _visW = _mw;
private _visH = _mh;

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
    if (ctrlShown _bgGroup) then {
        (ctrlPosition _bgGroup) params ["_dx", "", "_dw"];
        if (_dw > 0.02 && {_dx > (_visX + 0.04)} && {_dx < (_visX + _visW)}) then {
            _visW = (_dx - _visX - 0.004) max 0.08;
        };
    };
};
{
    private _c = _disp displayCtrl (17000 + _x);
    if (isNull _c) then { continue };
    _c ctrlSetBackgroundColor _bgPanel;
    _c ctrlSetTextColor _cyan;
    // Identité native IceMan : masquée, remplacée par le cartouche COMSPEC.
    _c ctrlShow false;
} forEach [2620, 2621, 2622];

{
    private _c = _disp displayCtrl (17000 + _x);
    if (isNull _c) then { continue };
    // Boussole native : fond transparent, sinon un carré sombre la recouvre.
    _c ctrlSetBackgroundColor [0, 0, 0, 0];
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
        if (isNil {missionNamespace getVariable "COMSPEC_MAP_OverlayCreatedLogged"}) then {
            missionNamespace setVariable ["COMSPEC_MAP_OverlayCreatedLogged", true, false];
            diag_log "[COMSPEC][MAP] Creating operator overlay";
        };
    } else {
        if (isNil {missionNamespace getVariable "COMSPEC_MAP_OverlayExistsLogged"}) then {
            missionNamespace setVariable ["COMSPEC_MAP_OverlayExistsLogged", true, false];
            diag_log "[COMSPEC][MAP] Overlay already exists - skipped";
        };
    };
    _c
};

private _heading = [_disp, _idcHeading, "RscStructuredText"] call _fncEnsure;
private _cursorBox = [_disp, _idcCursor, "RscStructuredText"] call _fncEnsure;
private _unitBox = [_disp, _idcUnit, "RscStructuredText"] call _fncEnsure;
private _acctBanner = [_disp, _idcAcct, "RscStructuredText"] call _fncEnsure;
private _zoomIn = [_disp, _idcZoomIn, "RscButton"] call _fncEnsure;
private _zoomOut = [_disp, _idcZoomOut, "RscButton"] call _fncEnsure;

if (isNull _heading || {isNull _cursorBox} || {isNull _unitBox}) exitWith {};

private _pad = _visW * 0.012;
_heading ctrlShow false;
_heading ctrlEnable false;

{
    _x ctrlShow false;
    _x ctrlEnable false;
    _x ctrlCommit 0;
} forEach [_zoomIn, _zoomOut];

private _boxW = (_visW * 0.40) min 0.26;
if (_boxW < 0.10) then { _boxW = (_visW * 0.46) max 0.09; };
private _boxH = (_visH * 0.22) max 0.078;
private _cursorX = _visX + _visW - _pad - _boxW;
private _cursorY = _visY + _visH - _boxH - _pad;
_cursorBox ctrlSetPosition [_cursorX, _cursorY, _boxW, _boxH];
_cursorBox ctrlSetBackgroundColor _bgPanel;
_cursorBox ctrlEnable false;
_cursorBox ctrlSetFade 0;

private _fncAbsPos = {
    params ["_c"];
    if (isNull _c) exitWith { [0, 0, 0, 0] };
    (ctrlPosition _c) params ["_ax", "_ay", "_aw", "_ah"];
    private _p = ctrlParentControlsGroup _c;
    private _guard = 0;
    while {!isNull _p && {_guard < 8}} do {
        (ctrlPosition _p) params ["_px", "_py"];
        _ax = _ax + _px;
        _ay = _ay + _py;
        _p = ctrlParentControlsGroup _p;
        _guard = _guard + 1;
    };
    [_ax, _ay, _aw, _ah]
};
private _fncOsd = {
    params ["_d", "_idc"];
    private _c = _d displayCtrl (17000 + _idc);
    if (isNull _c) then { _c = _d displayCtrl _idc; };
    _c
};

// Bandeau noir juste sous l’heure (bas du bandeau OSD, centré sur l’horloge).
private _barH = (_visH * 0.042) max 0.022;
private _barW = (_visW * 0.72) min 0.50;
private _barY = _visY + 0.001;
private _barX = _visX + ((_visW - _barW) / 2);
private _timeCtrl = [_disp, 2613] call _fncOsd;
private _headerCtrl = _disp displayCtrl 1;
if (isNull _headerCtrl) then { _headerCtrl = _disp displayCtrl (17000 + 1); };
if (!isNull _timeCtrl) then {
    ([_timeCtrl] call _fncAbsPos) params ["_tx", "_ty", "_tw", "_th"];
    if (_tw > 0.02 && {_th > 0.006}) then {
        _barH = (_th * 0.95) max 0.020;
        _barY = _ty + _th + 0.002;
        _barW = ((_tw * 3.4) max (_visW * 0.58)) min (_visW * 0.90);
        _barX = _tx + (_tw / 2) - (_barW / 2);
    };
};
if (!isNull _headerCtrl) then {
    ([_headerCtrl] call _fncAbsPos) params ["_hx", "_hy", "_hw", "_hh"];
    // Bandeau OSD seulement (pas un groupe d’écran 17000+1 trop haut).
    if (_hw > 0.12 && {_hh > 0.010} && {_hh < (_visH * 0.16)} && {(_hy + _hh) <= (_visY + 0.05)}) then {
        _barY = _hy + _hh + 0.002;
        if (_barW > (_hw * 0.94)) then { _barW = _hw * 0.90; };
        if (_barX < _hx || {(_barX + _barW) > (_hx + _hw)}) then {
            _barX = _hx + ((_hw - _barW) / 2);
        };
    };
};
if (_barY < _visY) then { _barY = _visY + 0.001; };
if (_barX < _visX) then { _barX = _visX; };
if ((_barX + _barW) > (_visX + _visW)) then {
    _barW = ((_visX + _visW) - _barX) max 0.10;
};
_unitBox ctrlSetPosition [_barX, _barY, _barW, _barH];
_unitBox ctrlSetBackgroundColor [0, 0, 0, 0.94];
_unitBox ctrlSetFade 0;
_unitBox ctrlEnable false;

if (!isNull _acctBanner) then {
    _acctBanner ctrlShow false;
    _acctBanner ctrlCommit 0;
};

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
        format ["%1 km", ((round (_m / 100)) / 10)]
    };
    format ["%1 m", round _m]
};

private _player = if (!isNil "cTab_player" && {!isNull cTab_player}) then { cTab_player } else { player };
private _veh = vehicle _player;
private _playerPos = getPosASLVisual _veh;

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
    "<t font='EtelkaMonospacePro' size='0.64' color='#5EC7F2' align='left'>" +
    "GRILLE  %1<br/>" +
    "DIST    %2<br/>" +
    "SOL     %3 m    GIS %4%5<br/>" +
    "PORTÉE  %6    ΔALT %7%8 m</t>",
    [_cursorPos] call _fncGrid,
    [_dst] call _fncKm,
    _elev,
    [_brg, 3] call (missionNamespace getVariable ["CBA_fnc_formatNumber", {str (_this select 0)}]),
    toString [176],
    [_rng] call _fncKm,
    ["", "+"] select (_dEl > 0),
    _dEl
];

private _cs = [_player] call comspec_overwatch_atak_athena_fnc_athena_bftUnitLabel;
if (!(_cs isEqualType "")) then { _cs = str _cs; };
_cs = trim _cs;
if (_cs isEqualTo "") then { _cs = "—"; };

private _role = "";
if (!isNil "comspec_overwatch_connect_fnc_getUnitRole") then {
    _role = [_player] call comspec_overwatch_connect_fnc_getUnitRole;
};
if (!(_role isEqualType "")) then { _role = str _role; };
_role = trim _role;
if (_role isEqualTo "" || {(toLower _role) in ["operator", "operateur"]}) then { _role = "—"; };

private _radioTxt = "—";
if (!isNil "comspec_overwatch_connect_fnc_getRadioState") then {
    private _radioRaw = [_player] call comspec_overwatch_connect_fnc_getRadioState;
    if (_radioRaw isEqualType "") then {
        private _rp = _radioRaw splitString "|";
        private _freq = if ((count _rp) > 1) then { _rp select 1 } else { "N/A" };
        private _ch = if ((count _rp) > 2) then { _rp select 2 } else { "N/A" };
        if (!(_freq in ["", "N/A"])) then {
            _radioTxt = if ((_freq find "MHz") >= 0) then { _freq } else { format ["%1 MHz", _freq] };
        } else {
            if (!(_ch in ["", "N/A", "0"])) then {
                _radioTxt = format ["canal %1", _ch];
            };
        };
    };
};

private _unitHtml = format [
    "<t font='RobotoCondensedBold' size='0.52' color='#E8EEF2' align='left'>" +
    "INDICATIF  %1    RÔLE  %2    GRILLE  %3    RADIO  %4</t>",
    _cs,
    _role,
    [_playerPos] call _fncGrid,
    _radioTxt
];

_cursorBox ctrlSetStructuredText parseText _cursorHtml;
_unitBox ctrlSetStructuredText parseText _unitHtml;

{
    _x ctrlShow true;
    _x ctrlCommit 0;
} forEach [_cursorBox, _unitBox];
_heading ctrlShow false;
_heading ctrlCommit 0;
_zoomIn ctrlShow false;
_zoomOut ctrlShow false;
_zoomIn ctrlCommit 0;
_zoomOut ctrlCommit 0;

if (!isNil "comspec_overwatch_atak_athena_fnc_mapUIUpdate") then {
    [_disp, _mapCtrl, [_visX, _visY, _visW, _visH]] call comspec_overwatch_atak_athena_fnc_mapUIUpdate;
};
