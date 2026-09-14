/*
    Peint ou retire l’alerte plein écran sur le téléphone ATAK.
    Couvre le rectangle écran du terminal — dialog ouvert ET overlay mini (dsp).
*/
if (!hasInterface) exitWith {};

private _IDC_BG = 99888100;
private _IDC_TXT = 99888101;
private _IDC_BTN = 99888102;

private _fncDisplays = {
    private _out = [];
    {
        private _d = uiNamespace getVariable [_x, displayNull];
        if (!isNull _d) then { _out pushBackUnique _d; };
    } forEach ["cTab_Android_dlg", "cTab_Android_dsp"];
    _out
};

private _fncHideOn = {
    params ["_d"];
    if (isNull _d) exitWith {};
    {
        private _c = _d displayCtrl _x;
        if (!isNull _c) then { ctrlDelete _c; };
    } forEach [_IDC_BG, _IDC_TXT, _IDC_BTN];
};

private _fncIsPhoneRect = {
    params ["_p"];
    if (!(_p isEqualType []) || {(count _p) < 4}) exitWith { false };
    _p params ["", "", "_w", "_h"];
    if (_w < (0.04 * safezoneW) || {_h < (0.04 * safezoneH)}) exitWith { false };
    if (_w > (0.78 * safezoneW) && {_h > (0.82 * safezoneH)}) exitWith { false };
    true
};

private _fncScreenPos = {
    params ["_disp"];
    private _pos = [];
    private _dispName = "cTab_Android_dlg";
    if (_disp isEqualTo (uiNamespace getVariable ["cTab_Android_dsp", displayNull])) then {
        _dispName = "cTab_Android_dsp";
    };

    {
        _x params ["_idc"];
        private _c = _disp displayCtrl _idc;
        if (isNull _c) then { continue };
        if (!ctrlShown _c) then { continue };
        private _p = ctrlPosition _c;
        if ([_p] call _fncIsPhoneRect) exitWith { _pos = _p; };
    } forEach [[9401], [9410], [1201], [1202], [1200], [16], [18201]];

    if (_pos isEqualTo [] && {!isNil "cTab_fnc_getSettings"} && {!isNil "cTab_fnc_getFromPairs"}) then {
        private _mapName = [_dispName, "mapType"] call cTab_fnc_getSettings;
        private _mapTypes = [_dispName, "mapTypes"] call cTab_fnc_getSettings;
        private _mapIdc = [_mapTypes, _mapName] call cTab_fnc_getFromPairs;
        if (_mapIdc isEqualType 0) then {
            private _mapCtrl = _disp displayCtrl _mapIdc;
            if (!isNull _mapCtrl) then {
                private _p = ctrlPosition _mapCtrl;
                if ([_p] call _fncIsPhoneRect) then { _pos = _p; };
            };
        };
    };

    if (_pos isEqualTo []) then {
        {
            private _c = _disp displayCtrl _x;
            if (isNull _c) then { continue };
            private _p = ctrlPosition _c;
            if ((_p select 2) > 0.05 && {(_p select 3) > 0.05}) exitWith { _pos = _p; };
        } forEach [1201, 1202, 1200, 16, 18201, 9410, 4660];
    };

    _pos
};

private _state = missionNamespace getVariable ["COMSPEC_Athena_FsAlert", []];
private _alive = (_state isEqualType []) && {(count _state) >= 3} && {diag_tickTime <= (_state select 2)};
if (!_alive) then {
    missionNamespace setVariable ["COMSPEC_Athena_FsAlert", nil];
    { [_x] call _fncHideOn; } forEach (call _fncDisplays);
};

if (!_alive) exitWith {};

private _dev = uiNamespace getVariable ["COMSPEC_DeviceOverlay_Ctrl", controlNull];
if (!isNull _dev && {ctrlShown _dev}) exitWith {};

_state params ["_issuer", "_text"];
private _safeIssuer = [_issuer] call {
    params ["_s"];
    _s = str _s;
    _s = (_s splitString "<") joinString "‹";
    _s = (_s splitString ">") joinString "›";
    _s = (_s splitString "&") joinString " et ";
    _s
};
private _safeText = [_text] call {
    params ["_s"];
    _s = str _s;
    _s = (_s splitString "<") joinString "‹";
    _s = (_s splitString ">") joinString "›";
    _s = (_s splitString "&") joinString " et ";
    _s
};
if (_safeIssuer isEqualTo "") then { _safeIssuer = "Poste"; };
if (_safeText isEqualTo "") then { _safeText = "Message du poste de commandement"; };

private _displays = call _fncDisplays;
if (_displays isEqualTo []) exitWith {};

{
    private _disp = _x;
    private _pos = [_disp] call _fncScreenPos;
    if ((count _pos) < 4) then { continue };

    _pos params ["_px", "_py", "_pw", "_ph"];
    if (_pw < 0.04 || {_ph < 0.04}) then { continue };

    private _bg = _disp displayCtrl _IDC_BG;
    private _txt = _disp displayCtrl _IDC_TXT;
    private _btn = _disp displayCtrl _IDC_BTN;
    private _needCreate = isNull _bg || {ctrlParent _bg isNotEqualTo _disp} || {isNull _txt} || {isNull _btn};

    if (_needCreate) then {
        [_disp] call _fncHideOn;
        _bg = _disp ctrlCreate ["RscText", _IDC_BG];
        _txt = _disp ctrlCreate ["RscStructuredText", _IDC_TXT];
        _btn = _disp ctrlCreate ["RscButton", _IDC_BTN];
        private _fncDismiss = {
            missionNamespace setVariable ["COMSPEC_Athena_FsAlert", nil];
            if (!isNil "comspec_overwatch_atak_athena_fnc_athena_paintFullscreenAlert") then {
                [] call comspec_overwatch_atak_athena_fnc_athena_paintFullscreenAlert;
            };
        };
        _bg ctrlAddEventHandler ["MouseButtonDown", _fncDismiss];
        _txt ctrlAddEventHandler ["MouseButtonDown", _fncDismiss];
        _btn ctrlAddEventHandler ["ButtonClick", _fncDismiss];
        _btn ctrlSetText "FERMER";
    };

    _bg ctrlSetPosition _pos;
    _bg ctrlSetBackgroundColor [0.02, 0.08, 0.06, 0.96];
    _bg ctrlEnable true;
    _bg ctrlSetFade 0;
    _bg ctrlShow true;
    _bg ctrlCommit 0;

    private _txtH = _ph * 0.72;
    _txt ctrlSetPosition [_px + (_pw * 0.06), _py + (_ph * 0.08), _pw * 0.88, _txtH];
    private _titleSize = if (_ph < 0.22) then { "0.72" } else { "0.95" };
    private _bodySize = if (_ph < 0.22) then { "0.62" } else { "0.82" };
    _txt ctrlSetStructuredText parseText format [
        "<t align='center' size='%1' color='#7dffb0'>ALERTE POSTE</t><br/><t align='center' size='0.62' color='#8aa0b4'>%2</t><br/><br/><t align='center' size='%3' color='#e8f4f0'>%4</t>",
        _titleSize,
        _safeIssuer,
        _bodySize,
        _safeText
    ];
    _txt ctrlEnable true;
    _txt ctrlSetFade 0;
    _txt ctrlShow true;
    _txt ctrlCommit 0;

    private _btnH = (_ph * 0.14) min 0.045;
    private _btnY = _py + _ph - _btnH - (_ph * 0.05);
    _btn ctrlSetPosition [_px + (_pw * 0.18), _btnY, _pw * 0.64, _btnH max 0.022];
    _btn ctrlShow true;
    _btn ctrlCommit 0;
} forEach _displays;
