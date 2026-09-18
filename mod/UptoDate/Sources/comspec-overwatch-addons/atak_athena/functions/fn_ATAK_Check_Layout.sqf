/*
  Calage IceMan : ouvrir / fermer le menu d’applications.
  Même animation qu’IceMan (largeur, fondu, hauteur non touchée).
  Seule limite : la carte ne dépasse pas le cadre du téléphone.
*/

if (!hasInterface) exitWith {};

private _lineNum = -1;
if (!isNil "_line" && {_line isEqualType 0}) then { _lineNum = _line; };
if (_lineNum < 0 && {!isNil "_curLine"} && {_curLine isEqualType 0}) then { _lineNum = _curLine; };
if (_lineNum < 0 && {!isNil "_subInfos"} && {_subInfos isEqualType []} && {(count _subInfos) > 1}) then {
    private _cand = _subInfos param [1, -1];
    if (_cand isEqualType 0) then { _lineNum = _cand; };
};

private _ifaceInit = if (!isNil "_interfaceInit") then { _interfaceInit } else { false };
private _open = false;
if (!isNil "_show") then {
    _open = if (_show isEqualType true) then { _show } else { _show isNotEqualTo 0 };
};

private _dispName = "cTab_Android_dlg";
if (!isNil "_displayName" && {_displayName isEqualType ""} && {_displayName isNotEqualTo ""}) then {
    _dispName = _displayName;
};

private _disp = displayNull;
if (!isNil "_display" && {_display isEqualType displayNull}) then { _disp = _display; };
if (isNull _disp) then { _disp = uiNamespace getVariable ["cTab_Android_dlg", displayNull]; };
if (isNull _disp) exitWith {};

private _bgGroup = controlNull;
if (!isNil "_backgroundGroup" && {_backgroundGroup isEqualType controlNull}) then {
    _bgGroup = _backgroundGroup;
};
if (isNull _bgGroup) then { _bgGroup = _disp displayCtrl 4660; };
if (isNull _bgGroup) exitWith {};

private _bg = controlNull;
if (!isNil "_background" && {_background isEqualType controlNull}) then { _bg = _background; };
if (isNull _bg) then { _bg = _bgGroup controlsGroupCtrl 9; };
if (isNull _bg) then { _bg = _bgGroup; };

private _appGroup = controlNull;
if (!isNil "_group" && {_group isEqualType controlNull}) then { _appGroup = _group; };

private _onSwitch = _bgGroup getVariable ["Anim_SwitchTool", false];
private _onToggle = _bgGroup getVariable ["Anim_ToggleMenu", _ifaceInit];
private _fadeIgnore = _bgGroup getVariable ["Anim_fadeIgnore", _ifaceInit];
if (_onSwitch) then { _bgGroup setVariable ["Anim_SwitchTool", false]; };
if (_onToggle) then { _bgGroup setVariable ["Anim_ToggleMenu", false]; };
if (_fadeIgnore) then { _bgGroup setVariable ["Anim_fadeIgnore", false]; };

(ctrlPosition _bg) params ["", "", "_bgW", "_bgH"];
if (!(_bgW isEqualType 0) || {_bgW != _bgW} || {_bgW < 0.04}) then {
    _bgW = uiNamespace getVariable ["COMSPEC_ATAK_DrawerW", 0.12];
};
if (_bgW > 0.04) then {
    uiNamespace setVariable ["COMSPEC_ATAK_DrawerW", _bgW];
};
if (!(_bgH isEqualType 0) || {_bgH != _bgH} || {_bgH < 0.04}) then { _bgH = 0.2; };

private _targetMapName = [_dispName, "mapType"] call cTab_fnc_getSettings;
private _mapTypes = [_dispName, "mapTypes"] call cTab_fnc_getSettings;
private _targetMapIDC = [_mapTypes, _targetMapName] call cTab_fnc_getFromPairs;
if (!(_targetMapIDC isEqualType 0)) exitWith {};
private _targetMapCtrl = _disp displayCtrl _targetMapIDC;
if (isNull _targetMapCtrl) exitWith {};

(ctrlPosition _targetMapCtrl) params ["_MapX", "_MapY", "_MapW", "_MapH"];
if (!(_MapW isEqualType 0) || {_MapW != _MapW} || {_MapW < 0.04}) exitWith {};
if (!(_MapH isEqualType 0) || {_MapH != _MapH} || {_MapH < 0.04}) then { _MapH = 0.2; };

private _span = _MapW;
(ctrlPosition _bgGroup) params ["_dx", "", "_dw"];
if ((_dw isEqualType 0) && {_dw > 0.04}) then {
    _span = (((_dx + _dw) - _MapX) max _MapW);
};
private _phone = uiNamespace getVariable ["COMSPEC_ATAK_PhoneFrame", []];
_phone params [["_phoneDisp", displayNull], ["_phoneW", 0]];
if (_phoneDisp isNotEqualTo _disp || {_phoneW < 0.12}) then {
    uiNamespace setVariable ["COMSPEC_ATAK_PhoneFrame", [_disp, _span]];
    _phoneW = _span;
};

// IceMan : ouvert = 3/2 du tiroir, fermé = 5/2 du tiroir.
private _result = (_bgW / 2) * ([5, 3] select _open);
if (_result != _result) then { _result = _phoneW; };
_result = (_result max 0.08) min _phoneW;

[
    _targetMapCtrl,
    [[], [_MapX, _MapY, _result]],
    ["ATAK_Toggle_Spring", _ifaceInit, 1200, [3]]
] call BCE_fnc_Anim_CustomOffset;
_targetMapCtrl ctrlMapSetPosition [];

private _bat = _disp displayCtrl 2;
private _batX = if (isNull _bat) then { _MapX } else { (ctrlPosition _bat) select 0 };

{
    private _ctrl = _disp displayCtrl (17000 + _x);
    if (!isNull _ctrl) then {
        private _cw = ((ctrlPosition _ctrl) param [2, 0]) max 0.01;
        [
            _ctrl,
            [[], [
                (_MapX + _result - _cw + (_MapX - _batX)),
                (ctrlPosition _ctrl) select 1
            ]],
            ["ATAK_Toggle_Spring", _ifaceInit, 1200, [2, 3]]
        ] call BCE_fnc_Anim_CustomOffset;
    };
} forEach [2620, 2621, 2622];

if (!isNil "comspec_overwatch_atak_athena_fnc_athena_fillIdentityOverlay") then {
    [_disp, missionNamespace getVariable ["cTab_player", player]] call comspec_overwatch_atak_athena_fnc_athena_fillIdentityOverlay;
};

private _tool = _disp displayCtrl (17000 + 1300);
private _POSY = _MapY;
private _POSW = 0;
if (!isNull _tool) then {
    (ctrlPosition _tool) params ["", "_ty", "_tw"];
    _POSY = _ty;
    _POSW = _tw max 0;
    [
        _tool,
        [[], [_MapX + _result - _POSW, _POSY]],
        ["ATAK_Toggle_Spring", _ifaceInit, 1200, [2]]
    ] call BCE_fnc_Anim_CustomOffset;
};

{
    _x params ["_c", ["_ignoreFade", true], ["_skip", false]];
    if (isNull _c || {_skip}) then { continue };

    // IceMan : largeur 0/tiroir, hauteur inchangée (nil), fondu 1 = masqué / 0 = visible.
    private _fade = [0, nil] select _ignoreFade;
    [
        _c,
        [[], [
            _MapX + _result,
            _POSY,
            [0, _bgW] select _open,
            nil,
            [1, _fade] select (_open || {_ignoreFade})
        ]],
        ["ATAK_Toggle_Spring", _ifaceInit, 1200, [3]]
    ] call BCE_fnc_Anim_CustomOffset;
} forEach [
    [_bgGroup, true, false],
    [
        _appGroup,
        _fadeIgnore,
        !_onToggle && {!(_lineNum < 0)} && {!_onSwitch}
    ]
];

private _toolBnt = _disp displayCtrl 46600;
if (!isNull _toolBnt) then {
    private _th = ((ctrlPosition _toolBnt) param [3, 0]) max 0.01;
    [
        _toolBnt,
        [[], [
            _MapX + _result,
            _POSY + _bgH - _th,
            [0, _bgW] select _open
        ]],
        ["ATAK_Toggle_Spring", _ifaceInit, 1200, [3]]
    ] call BCE_fnc_Anim_CustomOffset;
};
