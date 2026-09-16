/*
  COMSPEC workaround for BCE upstream bug (BCE_cTab_ATAK / Compat).
  Compat updateInterface calls Check_Layout without defining `_line`.

  Keep upstream layout behaviour, but recover `_line` from `_subInfos` /
  `_curLine` so the app panel + map still animate. The previous gate that
  skipped the app-group anim when `_line` was missing left Desktop blue on
  the left half and Athena floating without the menu background.

  CTD (RPT AutoArray negative size / ACCESS_VIOLATION) : le ressort BCE
  interpolait une largeur nulle ou déjà réduite. On fige le cadre téléphone
  une fois, on refuse toute largeur <= 0, et on n’empile pas les ressorts.
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

// Pendant une alerte plein écran : ne pas animer carte / tiroir (largeur négative).
private _fsAlert = missionNamespace getVariable ["COMSPEC_Athena_FsAlert", []];
if ((_fsAlert isEqualType []) && {(count _fsAlert) >= 3} && {diag_tickTime <= (_fsAlert select 2)}) exitWith {
    if (!isNil "comspec_overwatch_atak_athena_fnc_athena_paintFullscreenAlert") then {
        [] call comspec_overwatch_atak_athena_fnc_athena_paintFullscreenAlert;
    };
};

private _onSwitch = _bgGroup getVariable ["Anim_SwitchTool", false];
private _onToggle = _bgGroup getVariable ["Anim_ToggleMenu", _ifaceInit];
private _fadeIgnore = _bgGroup getVariable ["Anim_fadeIgnore", _ifaceInit];
if (_onSwitch) then { _bgGroup setVariable ["Anim_SwitchTool", false]; };
if (_onToggle) then { _bgGroup setVariable ["Anim_ToggleMenu", false]; };
if (_fadeIgnore) then { _bgGroup setVariable ["Anim_fadeIgnore", false]; };

(ctrlPosition _bg) params ["", "", "_bgW", "_bgH"];
if (!(_bgW isEqualType 0) || {_bgW != _bgW} || {_bgW < 0.04}) then { _bgW = 0; };
if (!(_bgH isEqualType 0) || {_bgH != _bgH} || {_bgH < 0.04}) then { _bgH = 0; };
private _storedMenu = uiNamespace getVariable ["COMSPEC_ATAK_MenuSize", []];
_storedMenu params [["_smW", 0], ["_smH", 0]];
if (_bgW < 0.04) then { _bgW = _smW; };
if (_bgH < 0.04) then { _bgH = _smH; };
if (_bgW < 0.04) then { _bgW = 0.16; };
if (_bgH < 0.04) then { _bgH = 0.36; };
uiNamespace setVariable ["COMSPEC_ATAK_MenuSize", [_bgW, _bgH]];

private _targetMapName = [_dispName, "mapType"] call cTab_fnc_getSettings;
private _mapTypes = [_dispName, "mapTypes"] call cTab_fnc_getSettings;
private _targetMapIDC = [_mapTypes, _targetMapName] call cTab_fnc_getFromPairs;
if (!(_targetMapIDC isEqualType 0)) exitWith {};
private _targetMapCtrl = _disp displayCtrl _targetMapIDC;
if (isNull _targetMapCtrl) exitWith {};

(ctrlPosition _targetMapCtrl) params ["_MapX", "_MapY", "_MapW", "_MapH"];
if (!(_MapX isEqualType 0) || {_MapX != _MapX}) then { _MapX = 0; };
if (!(_MapY isEqualType 0) || {_MapY != _MapY}) then { _MapY = 0; };
if (!(_MapW isEqualType 0) || {_MapW != _MapW}) then { _MapW = 0; };
if (!(_MapH isEqualType 0) || {_MapH != _MapH}) then { _MapH = 0; };
if (_MapW < 0.04 || {_MapH < 0.04}) exitWith {};

// Cadre téléphone = largeur carte menu fermé. Jamais carte + tiroir (ils se recouvrent).
if (!_showMenu && {_MapW > 0.12}) then {
    uiNamespace setVariable ["COMSPEC_ATAK_FullMapRect", [_disp, [_MapX, _MapY, _MapW, _MapH]]];
};
private _stored = uiNamespace getVariable ["COMSPEC_ATAK_FullMapRect", []];
_stored params [["_storedDisp", displayNull], ["_rect", []]];
if (_showMenu && {_storedDisp isEqualTo _disp} && {(count _rect) >= 4}) then {
    private _sW = _rect param [2, 0];
    if ((_sW isEqualType 0) && {_sW > 0.12}) then {
        _MapX = _rect param [0, _MapX];
        _MapY = _rect param [1, _MapY];
        _MapW = _sW;
        private _sH = _rect param [3, 0];
        if ((_sH isEqualType 0) && {_sH > 0.04}) then { _MapH = _sH; };
    };
};

// Comme BCE : 3/5 carte ouverte, 5/5 fermée, à partir de la largeur du fond de menu.
private _result = (_bgW / 2) * ([5, 3] select _showMenu);
if (!(_result isEqualType 0) || {_result != _result} || {_result < 0.08}) then {
    _result = ([_MapW, (_MapW * 0.6) max 0.08] select _showMenu) max 0.08;
};

// Jamais de ressort : largeur interpolée vers 0 = plantage moteur.
private _useInstant = true;

[
    _targetMapCtrl,
    [[], [_MapX, _MapY, _result]],
    ["ATAK_Toggle_Spring", _useInstant, 1200, [3]]
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
            ["ATAK_Toggle_Spring", _useInstant, 1200, [2, 3]]
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
        ["ATAK_Toggle_Spring", _useInstant, 1200, [2]]
    ] call BCE_fnc_Anim_CustomOffset;
};

// Layout background + app group.
// Important: never assign `nil` to a private then read it (SQF throws
// "Undefined variable"). Never put a bare `nil` fade channel either —
// omit the fade entry when Anim_fadeIgnore is set.
{
    _x params ["_c", ["_ignoreFade", true], ["_skip", false]];
    if (isNull _c || {_skip}) then { continue };

    // Largeur toujours celle du menu (jamais 0). Hauteur non touchée (scroll + retour).
    private _endW = _bgW max 0.04;
    private _endPos = [_MapX + _result, _POSY, _endW];

    [
        _c,
        [[], _endPos],
        ["ATAK_Toggle_Spring", _useInstant, 1200, [3]]
    ] call BCE_fnc_Anim_CustomOffset;
} forEach [
    [_bgGroup, true, false],
    [
        _appGroup,
        _fadeIgnore,
        !_onToggle && {!(_lineNum < 0)} && {!_onSwitch}
    ]
];

// Pied photos / recherche / radio : collé sous le tiroir, même largeur, jamais nulle.
private _toolBnt = _disp displayCtrl 46600;
if (!isNull _toolBnt) then {
    private _dockH = (ctrlPosition _toolBnt) param [3, 0];
    if (!(_dockH isEqualType 0) || {_dockH != _dockH} || {_dockH < 0.02}) then { _dockH = 0.02; };
    private _dockY = _POSY + _bgH - _dockH;
    [
        _toolBnt,
        [[], [_MapX + _result, _dockY, _bgW max 0.04]],
        ["ATAK_Toggle_Spring", _useInstant, 1200, [3]]
    ] call BCE_fnc_Anim_CustomOffset;
};

[_disp] spawn {
    uiSleep 0.05;
    if (!isNil "comspec_overwatch_atak_athena_fnc_athena_updateMapHud") then {
        [] call comspec_overwatch_atak_athena_fnc_athena_updateMapHud;
    };
    if (!isNil "comspec_overwatch_atak_athena_fnc_athena_hideForeignPages") then {
        [""] call comspec_overwatch_atak_athena_fnc_athena_hideForeignPages;
    };
    if (!isNil "comspec_overwatch_atak_athena_fnc_athena_fixReportsLayout") then {
        [] call comspec_overwatch_atak_athena_fnc_athena_fixReportsLayout;
    };
};
