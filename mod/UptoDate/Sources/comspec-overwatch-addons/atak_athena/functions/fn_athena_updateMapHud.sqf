/*
    Tick HUD carte ATAK Enhanced :
    - encart identité superposé (Indicatif, Nom, Groupe, Fonction, Position),
      comme les outils carte, en bas à gauche au-dessus de Map Tools
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

// BCE construit le fond du tiroir avant ses boutons. Sur certaines reprises de
// mission / mises a jour du PBO, le display existe donc avec un grand panneau
// noir mais aucun menu. Rehydrater une fois par instance de display, et non a
// chaque tick du HUD (ATAK_getAPPs recree les controles du tiroir).
// Différé hors du 1er frame d’ouverture : évite un hitch / voile laiteux.
private _hydratedDisplay = uiNamespace getVariable ["COMSPEC_ATAK_MenuHydratedDisplay", displayNull];
if (_hydratedDisplay isNotEqualTo _disp) then {
    uiNamespace setVariable ["COMSPEC_ATAK_MenuHydratedDisplay", _disp];
    if (!isNil "BCE_fnc_ATAK_getAPPs") then {
        [{
            params ["_d"];
            private _cur = uiNamespace getVariable ["cTab_Android_dlg", displayNull];
            if (isNull _cur) then { _cur = uiNamespace getVariable ["cTab_Android_dsp", displayNull]; };
            if (isNull _cur || {_cur isNotEqualTo _d}) exitWith {};
            if (!isNil "BCE_fnc_ATAK_getAPPs") then {
                [true, true] call BCE_fnc_ATAK_getAPPs;
                diag_log "[COMSPEC][MAP] ATAK application menu hydrated (deferred)";
            };
        }, [_disp], 0.85] call CBA_fnc_waitAndExecute;
    };
};

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
private _nativeIdentity = [];
{
    private _c = _disp displayCtrl (17000 + _x);
    if (isNull _c) then { continue };
    _c ctrlSetBackgroundColor _bgPanel;
    _c ctrlSetTextColor _cyan;
    _nativeIdentity pushBack _c;
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

if (isNull _heading || {isNull _cursorBox} || {isNull _unitBox}) exitWith {
    // Repli : triplet IceMan Indicatif / Nom / Rôle toujours visible.
    { _x ctrlShow true; } forEach _nativeIdentity;
    [_disp] call comspec_overwatch_atak_athena_fnc_athena_fillIdentityOverlay;
};

// Ne plus masquer le triplet IceMan : c’est la superposition Indicatif / Nom / Rôle.
{ _x ctrlShow true; _x ctrlSetFade 0; _x ctrlCommit 0; } forEach _nativeIdentity;
[_disp] call comspec_overwatch_atak_athena_fnc_athena_fillIdentityOverlay;

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

// Encart identité : même style que Map Tools, au-dessus des outils carte.
private _idW = (_visW * 0.42) min 0.30;
if (_idW < 0.12) then { _idW = (_visW * 0.48) max 0.10; };
private _idH = (_visH * 0.28) max 0.118;
private _toolsReserve = (_visH * 0.14) max 0.055;
private _mtCtrl = _disp displayCtrl (17000 + 12012);
if (isNull _mtCtrl) then { _mtCtrl = _disp displayCtrl 12012; };
if (!isNull _mtCtrl && {ctrlShown _mtCtrl}) then {
    ([_mtCtrl] call _fncAbsPos) params ["", "_mty", "", "_mth"];
    if (_mth > 0.012 && {_mty > _visY}) then {
        _toolsReserve = (((_visY + _visH) - _mty) + 0.006) max 0.04;
    };
};
private _idX = _visX + _pad;
private _idY = _visY + _visH - _idH - _toolsReserve - _pad;
if (_idY < (_visY + _pad)) then { _idY = _visY + _pad; };
if ((_idX + _idW) > (_cursorX - 0.008)) then {
    _idW = ((_cursorX - _idX - 0.008) max 0.10);
};
_unitBox ctrlSetPosition [_idX, _idY, _idW, _idH];
_unitBox ctrlSetBackgroundColor _bgPanel;
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

private _fncCleanTxt = {
    params ["_v"];
    if (!(_v isEqualType "")) then { _v = str _v; };
    _v = trim _v;
    if (_v isEqualTo "" || {(toLower _v) in ["<null>", "any", "nil", "-", "none", "n/a"]}) then { "" } else { _v }
};

private _opName = [missionNamespace getVariable ["comspec_profile_name", ""]] call _fncCleanTxt;
if (_opName isEqualTo "") then {
    if (!isNil "comspec_overwatch_connect_fnc_collectOperatorIdentity") then {
        private _ident = [_player] call comspec_overwatch_connect_fnc_collectOperatorIdentity;
        if (_ident isEqualType createHashMap) then {
            private _fn = [_ident getOrDefault ["first_name_detected", ""]] call _fncCleanTxt;
            private _ln = [_ident getOrDefault ["last_name_detected", ""]] call _fncCleanTxt;
            _opName = trim (format ["%1 %2", _fn, _ln]);
        };
    };
};
if (_opName isEqualTo "") then { _opName = "—"; };

private _grpTxt = "";
if (!isNil "comspec_overwatch_connect_fnc_inGameGroupLabel") then {
    _grpTxt = [_player] call comspec_overwatch_connect_fnc_inGameGroupLabel;
};
_grpTxt = [_grpTxt] call _fncCleanTxt;
if (_grpTxt isEqualTo "") then { _grpTxt = "—"; };

private _fnTxt = [missionNamespace getVariable ["comspec_profile_function", ""]] call _fncCleanTxt;
if (_fnTxt isEqualTo "") then {
    _fnTxt = [missionNamespace getVariable ["comspec_profile_role", ""]] call _fncCleanTxt;
};
if (_fnTxt isEqualTo "" && {!isNil "comspec_overwatch_connect_fnc_getUnitRole"}) then {
    _fnTxt = [[_player] call comspec_overwatch_connect_fnc_getUnitRole] call _fncCleanTxt;
};
if (_fnTxt isEqualTo "" || {(toLower _fnTxt) in ["operator", "operateur"]}) then { _fnTxt = "—"; };

private _unitHtml = format [
    "<t font='EtelkaMonospacePro' size='0.58' color='#5EC7F2' align='left'>" +
    "INDICATIF  %1<br/>" +
    "NOM       %2<br/>" +
    "GROUPE    %3<br/>" +
    "FONCTION  %4<br/>" +
    "POSITION  %5</t>",
    _cs,
    _opName,
    _grpTxt,
    _fnTxt,
    [_playerPos] call _fncGrid
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
    // Chrome 88500+ : mapUIDestroy depuis mapUIUpdate (pas de rail ni menu clic droit).
    [_disp, _mapCtrl, [_visX, _visY, _visW, _visH]] call comspec_overwatch_atak_athena_fnc_mapUIUpdate;
};
