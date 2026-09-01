/*
  COMSPEC workaround for BCE upstream bug (BCE_cTab_ATAK / Compat).
  Compat updateInterface calls Check_Layout without defining `_line`.

  Keep upstream layout behaviour, but recover `_line` from `_subInfos` /
  `_curLine` so the app panel + map still animate. The previous gate that
  skipped the app-group anim when `_line` was missing left Desktop blue on
  the left half and Athena floating without the menu background.
*/

// --- Capture caller scope (do NOT `private` these names — they come from Compat) ---
private _lineNum = -1;
if (!isNil "_line" && {_line isEqualType 0}) then { _lineNum = _line; };
if (_lineNum < 0 && {!isNil "_curLine"} && {_curLine isEqualType 0}) then { _lineNum = _curLine; };
if (_lineNum < 0 && {!isNil "_subInfos"} && {_subInfos isEqualType []} && {(count _subInfos) > 1}) then {
    private _cand = _subInfos param [1, -1];
    if (_cand isEqualType 0) then { _lineNum = _cand; };
};
// Keep -1 when Compat opens an app with ["", -1] — upstream then forces a layout anim.

private _ifaceInit = if (!isNil "_interfaceInit") then { _interfaceInit } else { false };
private _showMenu = if (!isNil "_show") then { _show } else { true };

private _dispName = "cTab_Android_dlg";
if (!isNil "_displayName" && {_displayName isEqualType ""} && {_displayName isNotEqualTo ""}) then {
    _dispName = _displayName;
};

private _disp = displayNull;
if (!isNil "_display" && {_display isEqualType displayNull}) then { _disp = _display; };
if (isNull _disp) then { _disp = uiNamespace getVariable ["cTab_Android_dlg", displayNull]; };

private _bgGroup = controlNull;
if (!isNil "_backgroundGroup" && {_backgroundGroup isEqualType controlNull}) then {
    _bgGroup = _backgroundGroup;
};
// Menu group IDC is 4660 — never 17000+1200 (map chrome).
if (isNull _bgGroup && {!isNull _disp}) then { _bgGroup = _disp displayCtrl 4660; };

private _bg = controlNull;
if (!isNil "_background" && {_background isEqualType controlNull}) then { _bg = _background; };
if (isNull _bg && {!isNull _bgGroup}) then { _bg = _bgGroup controlsGroupCtrl 9; };
if (isNull _bg) then { _bg = _bgGroup; };

private _appGroup = controlNull;
if (!isNil "_group" && {_group isEqualType controlNull}) then { _appGroup = _group; };

if (isNull _disp || {isNull _bgGroup}) exitWith {};

private _onSwitch = _bgGroup getVariable ["Anim_SwitchTool", false];
private _onToggle = _bgGroup getVariable ["Anim_ToggleMenu", _ifaceInit];
private _fadeIgnore = _bgGroup getVariable ["Anim_fadeIgnore", _ifaceInit];
if (_onSwitch) then { _bgGroup setVariable ["Anim_SwitchTool", false]; };
if (_onToggle) then { _bgGroup setVariable ["Anim_ToggleMenu", false]; };
if (_fadeIgnore) then { _bgGroup setVariable ["Anim_fadeIgnore", false]; };

(ctrlPosition _bg) params ["", "", "_bgW", "_bgH"];

private _targetMapName = [_dispName, "mapType"] call cTab_fnc_getSettings;
private _mapTypes = [_dispName, "mapTypes"] call cTab_fnc_getSettings;
private _targetMapIDC = [_mapTypes, _targetMapName] call cTab_fnc_getFromPairs;
if (!(_targetMapIDC isEqualType 0)) exitWith {};
private _targetMapCtrl = _disp displayCtrl _targetMapIDC;
if (isNull _targetMapCtrl) exitWith {};

(ctrlPosition _targetMapCtrl) params ["_MapX", "_MapY", "_MapW", "_MapH"];

// Menu width collapsed by a prior anim → rebuild from map geometry.
if (_bgW < 0.02 && {_MapW > 0.05}) then {
    // Full-bleed map → panel = 2/5. Already-split map (~3/5) → panel = map * 2/3.
    _bgW = if (_MapW < (safeZoneW * 0.55)) then { _MapW * 2/3 } else { _MapW * 2/5 };
};
if (_bgH < 0.02) then { _bgH = _MapH; };

private _result = _bgW / 2 * ([5, 3] select _showMenu);
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
        [
            _ctrl,
            [[], [
                (_MapX + _result - (ctrlPosition _ctrl select 2) + (_MapX - _batX)),
                (ctrlPosition _ctrl) select 1
            ]],
            ["ATAK_Toggle_Spring", _ifaceInit, 1200, [2, 3]]
        ] call BCE_fnc_Anim_CustomOffset;
    };
} forEach [2620, 2621, 2622];

private _callSign = _disp displayCtrl (17000 + 2620);
if (!isNull _callSign && {!isNil "cTab_player"} && {!isNull cTab_player}) then {
    _callSign ctrlSetText ([groupId group cTab_player, [cTab_player] call CBA_fnc_getGroupIndex] joinString ":");
};

private _tool = _disp displayCtrl (17000 + 1300);
private _POSY = _MapY;
private _POSW = 0;
if (!isNull _tool) then {
    (ctrlPosition _tool) params ["", "_ty", "_tw"];
    _POSY = _ty;
    _POSW = _tw;
    [
        _tool,
        [[], [_MapX + _result - _POSW, _POSY]],
        ["ATAK_Toggle_Spring", _ifaceInit, 1200, [2]]
    ] call BCE_fnc_Anim_CustomOffset;
};

// Layout background + app group.
// Important: never assign `nil` to a private then read it (SQF throws
// "Undefined variable"). Never put a bare `nil` fade channel either —
// omit the fade entry when Anim_fadeIgnore is set.
{
    _x params ["_c", ["_ignoreFade", true], ["_skip", false]];
    if (isNull _c || {_skip}) then { continue };

    private _endW = [0, _bgW] select _showMenu;
    private _endPos = [_MapX + _result, _POSY, _endW, _bgH];
    if (!_ignoreFade) then {
        // 5e canal = opacité cible (1 menu ouvert, 0 menu fermé)
        _endPos pushBack ([1, 0] select _showMenu);
    };

    [
        _c,
        [[], _endPos],
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
    [
        _toolBnt,
        [[], [
            _MapX + _result,
            _POSY + _bgH - ((ctrlPosition _toolBnt) select 3),
            [0, _bgW] select _showMenu
        ]],
        ["ATAK_Toggle_Spring", _ifaceInit, 1200, [3]]
    ] call BCE_fnc_Anim_CustomOffset;
};

[_disp] spawn {
    uiSleep 0.05;
    if (!isNil "comspec_overwatch_atak_athena_fnc_athena_updateMapHud") then {
        [] call comspec_overwatch_atak_athena_fnc_athena_updateMapHud;
    };
    if (!isNil "comspec_overwatch_atak_athena_fnc_athena_fixReportsLayout") then {
        [] call comspec_overwatch_atak_athena_fnc_athena_fixReportsLayout;
    };
};
