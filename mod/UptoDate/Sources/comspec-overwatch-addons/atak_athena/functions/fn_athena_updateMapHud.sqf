/*
    HUD carte ATAK : comme le pack du 14/09, uniquement les cartouches IceMan
    (Indicatif / Nom / Rôle). Pas de cadre créé par-dessus, pas de destruction
    de chrome. Le menu d’applications reste celui d’IceMan.
*/
if (!hasInterface) exitWith {};

private _disp = displayNull;
if (!isNil "comspec_overwatch_atak_athena_fnc_athena_phoneDisplay") then {
    _disp = [] call comspec_overwatch_atak_athena_fnc_athena_phoneDisplay;
};

private _fncHideExtras = {
    params ["_d"];
    if (isNull _d) exitWith {};
    {
        private _c = _d displayCtrl _x;
        if (!isNull _c) then {
            _c ctrlShow false;
            _c ctrlEnable false;
        };
    } forEach [99887810, 99887811, 99887812, 99887813, 99887820, 99887821];
};

if (isNull _disp) exitWith {
    uiNamespace setVariable ["COMSPEC_MapUI_MouseWired", nil];
    uiNamespace setVariable ["COMSPEC_ATAK_FullMapRect", nil];
    if (isNil "cTabIfOpen") then {
        private _missSince = missionNamespace getVariable ["COMSPEC_ATAK_IfaceMissSince", -1];
        if (_missSince < 0) then {
            missionNamespace setVariable ["COMSPEC_ATAK_IfaceMissSince", diag_tickTime, false];
            _missSince = diag_tickTime;
        };
        if ((diag_tickTime - _missSince) > 1.5) then {
            missionNamespace setVariable ["COMSPEC_ATAK_DrawerWantOpen", false, false];
            missionNamespace setVariable ["COMSPEC_ATAK_IfaceTok", "", false];
            uiNamespace setVariable ["COMSPEC_ATAK_DrawerOpen", false];
            uiNamespace setVariable ["COMSPEC_ATAK_DrawerSession", nil];
            if (missionNamespace getVariable ["COMSPEC_MAP_HudOpenLogged", false]) then {
                missionNamespace setVariable ["COMSPEC_MAP_HudOpenLogged", false, false];
                diag_log "[COMSPEC][MAP] Map display closed";
                if (!isNil "comspec_overwatch_connect_fnc_log") then {
                    ["INFO", "MAP", "Téléphone refermé"] call comspec_overwatch_connect_fnc_log;
                };
            };
        };
    };
};

if (!(missionNamespace getVariable ["COMSPEC_MAP_OverlayRetired", false])) then {
    missionNamespace setVariable ["COMSPEC_MAP_OverlayRetired", true, false];
    {
        private _c = _disp displayCtrl _x;
        if (!isNull _c) then {
            _c ctrlShow false;
            _c ctrlEnable false;
        };
    } forEach [99887810, 99887811, 99887812, 99887813, 99887820, 99887821];
    diag_log "[COMSPEC][MAP] extra overlay retired";
};

[_disp] call _fncHideExtras;

private _overlay = uiNamespace getVariable ["COMSPEC_DeviceOverlay_Ctrl", controlNull];
private _overlayOn = !isNull _overlay && {ctrlShown _overlay} && {ctrlParent _overlay isEqualTo _disp};
if (_overlayOn) exitWith {
    if (!isNil "comspec_overwatch_atak_athena_fnc_athena_paintFullscreenAlert") then {
        [] call comspec_overwatch_atak_athena_fnc_athena_paintFullscreenAlert;
    };
};

private _mode = "";
if (!isNil "cTab_fnc_getSettings") then {
    _mode = ["cTab_Android_dlg", "mode"] call cTab_fnc_getSettings;
    if (!(_mode isEqualType "")) then { _mode = ""; };
};
if (_mode isNotEqualTo "BFT" && {_mode isNotEqualTo ""}) exitWith {
    if (!isNil "comspec_overwatch_atak_athena_fnc_athena_paintFullscreenAlert") then {
        [] call comspec_overwatch_atak_athena_fnc_athena_paintFullscreenAlert;
    };
};

private _mapCtrl = controlNull;
if (!isNil "cTab_fnc_getSettings" && {!isNil "cTab_fnc_getFromPairs"}) then {
    private _mapName = ["cTab_Android_dlg", "mapType"] call cTab_fnc_getSettings;
    private _mapTypes = ["cTab_Android_dlg", "mapTypes"] call cTab_fnc_getSettings;
    private _mapIdc = [_mapTypes, _mapName] call cTab_fnc_getFromPairs;
    if (_mapIdc isEqualType 0) then {
        _mapCtrl = _disp displayCtrl _mapIdc;
        if (isNull _mapCtrl) then { _mapCtrl = _disp displayCtrl (17000 + _mapIdc); };
    };
};
if (isNull _mapCtrl) then {
    {
        private _c = _disp displayCtrl _x;
        if (!isNull _c && {ctrlShown _c}) exitWith { _mapCtrl = _c; };
    } forEach [1201, 1202, 16, 18201, 18202, 17016];
};
if (isNull _mapCtrl || {!ctrlShown _mapCtrl}) exitWith {
    if (!isNil "comspec_overwatch_atak_athena_fnc_athena_paintFullscreenAlert") then {
        [] call comspec_overwatch_atak_athena_fnc_athena_paintFullscreenAlert;
    };
};

if (!(missionNamespace getVariable ["COMSPEC_MAP_HudOpenLogged", false])) then {
    missionNamespace setVariable ["COMSPEC_MAP_HudOpenLogged", true, false];
    missionNamespace setVariable ["COMSPEC_ATAK_IfaceMissSince", -1, false];
    diag_log "[COMSPEC][MAP] Map display detected";
    if (!isNil "comspec_overwatch_connect_fnc_log") then {
        ["INFO", "MAP", "Téléphone ouvert"] call comspec_overwatch_connect_fnc_log;
    };
};

(ctrlPosition _mapCtrl) params ["_mx", "_my", "_mw", "_mh"];
if (!(_mw isEqualType 0) || {_mw != _mw} || {_mw < 0.08}) exitWith {};
if (!(_mh isEqualType 0) || {_mh != _mh} || {_mh < 0.08}) exitWith {};

private _visX = _mx;
private _visY = _my;
private _visW = _mw;
private _visH = _mh;

private _bgGroup = _disp displayCtrl 4660;
if (!isNull _bgGroup && {ctrlShown _bgGroup}) then {
    (ctrlPosition _bgGroup) params ["_dx", "", "_dw"];
    if (_dw > 0.02 && {_dx > (_visX + 0.04)} && {_dx < (_visX + _visW)}) then {
        _visW = (_dx - _visX - 0.004) max 0.08;
    };
};

uiNamespace setVariable ["COMSPEC_ATAK_FullMapRect", [_visX, _visY, _visW, _visH]];

{
    private _c = _disp displayCtrl (17000 + _x);
    if (isNull _c) then { continue };
    _c ctrlSetBackgroundColor [0, 0, 0, 0];
    _c ctrlSetTextColor [1, 0.78, 0.12, 1];
} forEach [2615, 2616];

{
    private _c = _disp displayCtrl (17000 + _x);
    if (isNull _c) then { continue };
    _c ctrlShow true;
    _c ctrlSetFade 0;
    _c ctrlCommit 0;
} forEach [2620, 2621, 2622];
// IceMan 17000 + 2620 Indicatif / Nom / Rôle

if (!isNil "comspec_overwatch_atak_athena_fnc_athena_fillIdentityOverlay") then {
    [_disp] call comspec_overwatch_atak_athena_fnc_athena_fillIdentityOverlay;
};

if (!isNil "comspec_overwatch_atak_athena_fnc_athena_relabelBft") then {
    [] call comspec_overwatch_atak_athena_fnc_athena_relabelBft;
};

if (!isNil "comspec_overwatch_atak_athena_fnc_athena_paintFullscreenAlert") then {
    [] call comspec_overwatch_atak_athena_fnc_athena_paintFullscreenAlert;
};
