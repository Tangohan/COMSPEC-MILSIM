/*
    Peint ou retire l’alerte plein écran sur le téléphone ATAK vraiment ouvert.
    Jamais sur le mini-overlay 3D, jamais de cadre IceMan créé à la volée
    (pack 14-09 stable : toast seulement, pas de calque).
    Jamais de largeur nulle : même famille que le plantage AutoArray.
*/
if (!hasInterface) exitWith {};

private _IDC_BG = 99888100;
private _IDC_TXT = 99888101;
private _IDC_BTN = 99888102;

private _fncOpenPhone = {
    private _d = displayNull;
    if (!isNil "comspec_overwatch_atak_athena_fnc_athena_phoneDisplay") then {
        _d = [] call comspec_overwatch_atak_athena_fnc_athena_phoneDisplay;
    };
    if (isNull _d) exitWith { displayNull };
    private _dsp = uiNamespace getVariable ["cTab_Android_dsp", displayNull];
    if (!isNull _dsp && {_d isEqualTo _dsp}) exitWith { displayNull };
    _d
};

private _fncKnownDisplays = {
    private _out = [];
    private _open = call _fncOpenPhone;
    if (!isNull _open) then { _out pushBack _open; };
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
    uiNamespace setVariable ["COMSPEC_Athena_FsAlertMenuLayer", false];
};

private _fncClampRect = {
    params ["_p"];
    if (!(_p isEqualType []) || {(count _p) < 4}) exitWith { [] };
    _p params ["_rx", "_ry", "_rw", "_rh"];
    if (!(_rx isEqualType 0) || {_rx != _rx}) then { _rx = 0; };
    if (!(_ry isEqualType 0) || {_ry != _ry}) then { _ry = 0; };
    if (!(_rw isEqualType 0) || {_rw != _rw} || {_rw < 0.04}) then { _rw = 0.04; };
    if (!(_rh isEqualType 0) || {_rh != _rh} || {_rh < 0.04}) then { _rh = 0.04; };
    [_rx, _ry, _rw, _rh]
};

private _fncUnion = {
    params ["_a", "_b"];
    if (_a isEqualTo []) exitWith { _b };
    if (_b isEqualTo []) exitWith { _a };
    _a params ["_ax", "_ay", "_aw", "_ah"];
    _b params ["_bx", "_by", "_bw", "_bh"];
    private _x1 = _ax min _bx;
    private _y1 = _ay min _by;
    private _x2 = (_ax + _aw) max (_bx + _bw);
    private _y2 = (_ay + _ah) max (_by + _bh);
    private _uw = (_x2 - _x1) max 0.04;
    private _uh = (_y2 - _y1) max 0.04;
    [_x1, _y1, _uw, _uh]
};

private _fncScreenPos = {
    params ["_disp"];
    private _pos = [];

    private _stored = uiNamespace getVariable ["COMSPEC_ATAK_FullMapRect", []];
    _stored params [["_storedDisp", displayNull], ["_rect", []]];
    if (_storedDisp isEqualTo _disp) then {
        _pos = [_rect] call _fncClampRect;
    };

    private _union = [];
    private _dispName = "cTab_Android_dlg";
    if (_disp isEqualTo (uiNamespace getVariable ["cTab_Android_dsp", displayNull])) then {
        _dispName = "cTab_Android_dsp";
    };
    if (!isNil "cTab_fnc_getSettings" && {!isNil "cTab_fnc_getFromPairs"}) then {
        private _mapName = [_dispName, "mapType"] call cTab_fnc_getSettings;
        private _mapTypes = [_dispName, "mapTypes"] call cTab_fnc_getSettings;
        private _mapIdc = [_mapTypes, _mapName] call cTab_fnc_getFromPairs;
        if (_mapIdc isEqualType 0) then {
            private _mapCtrl = _disp displayCtrl _mapIdc;
            if (!isNull _mapCtrl) then {
                _union = [ctrlPosition _mapCtrl] call _fncClampRect;
            };
        };
    };
    {
        private _c = _disp displayCtrl _x;
        if (isNull _c) then { continue };
        if (!ctrlShown _c) then { continue };
        private _p = [ctrlPosition _c] call _fncClampRect;
        if (_p isEqualTo []) then { continue };
        _union = [_union, _p] call _fncUnion;
    } forEach [4660, 46600, 9401, 9410];

    if (!(_union isEqualTo [])) then {
        if (_pos isEqualTo []) then {
            _pos = _union;
        } else {
            private _uw = _union param [2, 0];
            private _pw = _pos param [2, 0];
            if (_uw > (_pw + 0.01)) then { _pos = _union; };
        };
    };

    if (_pos isEqualTo []) then {
        {
            private _c = _disp displayCtrl _x;
            if (isNull _c) then { continue };
            private _p = [ctrlPosition _c] call _fncClampRect;
            if (_p isNotEqualTo []) exitWith { _pos = _p; };
        } forEach [1201, 1202, 1200, 16, 18201];
    };

    [_pos] call _fncClampRect
};

private _state = missionNamespace getVariable ["COMSPEC_Athena_FsAlert", []];
private _alive = (_state isEqualType []) && {(count _state) >= 3} && {diag_tickTime <= (_state select 2)};
if (!_alive) then {
    missionNamespace setVariable ["COMSPEC_Athena_FsAlert", nil];
    { [_x] call _fncHideOn; } forEach (call _fncKnownDisplays);
};

if (!_alive) exitWith {};

private _dev = uiNamespace getVariable ["COMSPEC_DeviceOverlay_Ctrl", controlNull];
if (!isNull _dev && {ctrlShown _dev}) exitWith {};

_state params ["_issuer", "_text"];
private _fncSafe = {
    params ["_s"];
    _s = str _s;
    _s = (_s splitString "<") joinString "‹";
    _s = (_s splitString ">") joinString "›";
    _s = (_s splitString "&") joinString " et ";
    _s = (_s splitString "%") joinString "pct";
    _s
};
private _safeIssuer = [_issuer] call _fncSafe;
private _safeText = [_text] call _fncSafe;
if (_safeIssuer isEqualTo "") then { _safeIssuer = "Poste"; };
if (_safeText isEqualTo "") then { _safeText = "Message du poste de commandement"; };

private _open = call _fncOpenPhone;
if (isNull _open) exitWith {
    { [_x] call _fncHideOn; } forEach (call _fncKnownDisplays);
};
{
    private _d = uiNamespace getVariable [_x, displayNull];
    if (!isNull _d && {_d isNotEqualTo _open}) then { [_d] call _fncHideOn; };
} forEach ["cTab_Android_dsp"];
private _displays = [_open];

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
    if (!isNull _txt) then {
        private _haveTxt = toLower (ctrlClassName _txt);
        if ((_haveTxt find "structured") < 0 && {(_haveTxt find "html") < 0}) then { _needCreate = true; };
    };
    private _menu = _disp displayCtrl 4660;
    private _menuShown = !isNull _menu && {ctrlShown _menu};
    if (_menuShown && {!(uiNamespace getVariable ["COMSPEC_Athena_FsAlertMenuLayer", false])}) then {
        _needCreate = true;
    };
    if (!_menuShown) then { uiNamespace setVariable ["COMSPEC_Athena_FsAlertMenuLayer", false]; };

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
        if (_menuShown) then { uiNamespace setVariable ["COMSPEC_Athena_FsAlertMenuLayer", true]; };
    };

    _bg ctrlSetPosition _pos;
    _bg ctrlSetBackgroundColor [0.02, 0.08, 0.06, 0.96];
    _bg ctrlEnable true;
    _bg ctrlSetFade 0;
    _bg ctrlShow true;
    _bg ctrlCommit 0;
    _bg ctrlSetZOrder 900;

    private _txtH = (_ph * 0.72) max 0.05;
    private _txtW = (_pw * 0.88) max 0.04;
    private _txtX = _px + ((_pw - _txtW) / 2);
    private _txtY = _py + ((_ph * 0.08) max 0.01);
    _txt ctrlSetPosition [_txtX, _txtY, _txtW, _txtH];
    _txt ctrlSetBackgroundColor [0, 0, 0, 0];
    _txt ctrlCommit 0;
    private _alertHtml = format [
        "<t align='center' size='1.15' color='#E8F5F0'>ALERTE POSTE</t><br/><br/>" +
        "<t align='center' size='0.92' color='#9ee0c0'>%1</t><br/><br/>" +
        "<t align='center' size='0.98' color='#F0F6FA'>%2</t>",
        _safeIssuer,
        _safeText
    ];
    [_txt, _alertHtml] call comspec_overwatch_connect_fnc_setPlainText;
    _txt ctrlEnable true;
    _txt ctrlSetFade 0;
    _txt ctrlShow true;
    _txt ctrlCommit 0;
    _txt ctrlSetZOrder 901;

    private _btnH = ((_ph * 0.14) min 0.05) max 0.028;
    private _btnW = (_pw * 0.64) max 0.08;
    private _btnX = _px + ((_pw - _btnW) / 2);
    private _btnY = _py + _ph - _btnH - ((_ph * 0.05) max 0.008);
    _btn ctrlSetPosition [_btnX, _btnY, _btnW, _btnH];
    _btn ctrlShow true;
    _btn ctrlEnable true;
    _btn ctrlCommit 0;
    _btn ctrlSetZOrder 902;
} forEach _displays;
