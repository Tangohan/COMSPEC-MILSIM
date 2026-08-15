params [["_display", displayNull]];

if (isNull _display) then {
    _display = uiNamespace getVariable ["Iceman_TOC_viewDeviceDisplay", displayNull];
};
if (isNull _display) exitWith {};

[_display] call Iceman_fnc_toc_viewDeviceClear;

private _controls = [];
private _addControl = {
    params ["_class", "_x", "_y", "_w", "_h", ["_text", ""], ["_background", []]];

    private _ctrl = _display ctrlCreate [_class, -1];
    _ctrl ctrlSetPosition [_x, _y, _w, _h];
    if (_text != "") then {
        _ctrl ctrlSetText _text;
    };
    if !(_background isEqualTo []) then {
        _ctrl ctrlSetBackgroundColor _background;
    };
    _ctrl ctrlCommit 0;
    _controls pushBack _ctrl;
    _ctrl
};

private _drawOverlay = {
    params ["_x", "_y", "_w", "_h"];

    private _line = {
        params ["_lx", "_ly", "_lw", "_lh", ["_alpha", 0.42]];
        ["Iceman_TOC_Text", _lx, _ly, _lw, _lh, "", [0.25,1,0.45,_alpha]] call _addControl;
    };

    for "_i" from 1 to 2 do {
        [_x + (_w * (_i / 3)), _y, safeZoneW * 0.001, _h, 0.26] call _line;
        [_x, _y + (_h * (_i / 3)), _w, safeZoneH * 0.001, 0.26] call _line;
    };

    [_x + (_w * 0.5), _y + (_h * 0.38), safeZoneW * 0.0012, _h * 0.24, 0.65] call _line;
    [_x + (_w * 0.38), _y + (_h * 0.5), _w * 0.24, safeZoneH * 0.0012, 0.65] call _line;
    [_x + (_w * 0.47), _y + (_h * 0.47), _w * 0.06, safeZoneH * 0.0012, 0.75] call _line;
    [_x + (_w * 0.47), _y + (_h * 0.53), _w * 0.06, safeZoneH * 0.0012, 0.75] call _line;
    [_x + (_w * 0.47), _y + (_h * 0.47), safeZoneW * 0.0012, _h * 0.06, 0.75] call _line;
    [_x + (_w * 0.53), _y + (_h * 0.47), safeZoneW * 0.0012, _h * 0.06, 0.75] call _line;
};

private _openStreamFromControl = {
    params ["_ctrl"];
    private _display = ctrlParent _ctrl;
    private _stream = _ctrl getVariable ["Iceman_TOC_stream", []];
    if !(_stream isEqualTo []) then {
        _display setVariable ["Iceman_TOC_mode", "viewer"];
        _display setVariable ["Iceman_TOC_currentStream", _stream];
        [_display] call Iceman_fnc_toc_viewDeviceRefresh;
    };
};

private _mode = _display getVariable ["Iceman_TOC_mode", "home"];
private _overlay = _display getVariable ["Iceman_TOC_overlay", false];

if (_mode in ["viewer", "briefing"]) exitWith {
    private _stream = _display getVariable ["Iceman_TOC_currentStream", []];

    if (_stream isEqualTo []) exitWith {
        _display setVariable ["Iceman_TOC_mode", "home"];
        [_display] call Iceman_fnc_toc_viewDeviceRefresh;
    };

    _stream params ["_label", "_target", "_slot", "_cam", "_renderTarget", "_texture", "_feed", "_settings", ["_zoom", 1], ["_vision", "normal"]];
    _zoom = [_target, _slot] call Iceman_fnc_toc_getZoomValue;
    _vision = [_target, _slot, _vision] call Iceman_fnc_toc_getVisionValue;
    private _lookPos = [_cam] call Iceman_fnc_toc_cameraLookPos;
    private _lookGrid = [_lookPos, 8] call Iceman_fnc_toc_posToGrid;

    if (_mode == "briefing") exitWith {
        private _picX = safeZoneX + safeZoneW * 0.025;
        private _picY = safeZoneY + safeZoneH * 0.055;
        private _picW = safeZoneW * 0.95;
        private _picH = safeZoneH * 0.89;

        ["Iceman_TOC_Picture", _picX, _picY, _picW, _picH, _texture, [0,0,0,1]] call _addControl;
        if (_overlay) then {
            [_picX, _picY, _picW, _picH] call _drawOverlay;
        };

        private _briefLabel = ["Iceman_TOC_Text", safeZoneX + safeZoneW * 0.035, safeZoneY + safeZoneH * 0.017, safeZoneW * 0.60, safeZoneH * 0.028, format ["BRIEFING VIEW  |  %1  |  LOOK GRID %2", _label, _lookGrid], [0,0,0,0.45]] call _addControl;
        _briefLabel ctrlSetFont "RobotoCondensedBold";
        _briefLabel ctrlSetTextColor [0.86,0.94,0.96,1];

        private _escLabel = ["Iceman_TOC_Text", safeZoneX + safeZoneW * 0.84, safeZoneY + safeZoneH * 0.017, safeZoneW * 0.13, safeZoneH * 0.028, "ESC", [0,0,0,0.45]] call _addControl;
        _escLabel ctrlSetFont "RobotoCondensedBold";
        _escLabel ctrlSetTextColor [0.86,0.94,0.96,1];

        _display setVariable ["Iceman_TOC_dynamicControls", _controls];
    };

    private _title = ["Iceman_TOC_Text", safeZoneX + safeZoneW * 0.05, safeZoneY + safeZoneH * 0.055, safeZoneW * 0.50, safeZoneH * 0.035, format ["%1  |  Surface %2  |  LOOK GRID %3", _label, _slot, _lookGrid], [0,0,0,0]] call _addControl;
    _title ctrlSetFont "RobotoCondensedBold";
    _title ctrlSetTextColor [1,1,1,1];

    private _presenterState = missionNamespace getVariable ["Iceman_TOC_presenterState", [false, objNull, -1, "", "", 0]];
    private _isPresented = (_presenterState param [0, false]) && {(_presenterState param [1, objNull]) == _target} && {(_presenterState param [2, -1]) == _slot};
    if (_isPresented) then {
        private _presenter = _presenterState param [3, ""];
        private _presentBanner = ["Iceman_TOC_Text", safeZoneX + safeZoneW * 0.55, safeZoneY + safeZoneH * 0.055, safeZoneW * 0.13, safeZoneH * 0.035, format ["Presented by %1", _presenter], [0.08,0.16,0.10,0.9]] call _addControl;
        _presentBanner ctrlSetTextColor [0.7,1,0.74,1];
    };

    private _viewLabel = ["Iceman_TOC_Text", safeZoneX + safeZoneW * 0.69, safeZoneY + safeZoneH * 0.055, safeZoneW * 0.055, safeZoneH * 0.035, "View", [0,0,0,0]] call _addControl;
    _viewLabel ctrlSetTextColor [0.86,0.94,0.96,1];

    private _viewCombo = ["RscCombo", safeZoneX + safeZoneW * 0.745, safeZoneY + safeZoneH * 0.055, safeZoneW * 0.20, safeZoneH * 0.035, "", []] call _addControl;
    private _visionOptions = [
        ["Normal", "normal"],
        ["Night Vision", "nv"],
        ["Thermal WHOT", "thermal_whot"],
        ["Thermal BHOT", "thermal_bhot"],
        ["A3TI WHOT", "a3ti_whot"],
        ["A3TI BHOT", "a3ti_bhot"],
        ["A3TI Current", "a3ti_current"]
    ];
    {
        private _idx = _viewCombo lbAdd (_x # 0);
        _viewCombo lbSetData [_idx, _x # 1];
        if ((_x # 1) == _vision) then {
            _viewCombo lbSetCurSel _idx;
        };
    } forEach _visionOptions;
    _viewCombo setVariable ["Iceman_TOC_target", _target];
    _viewCombo setVariable ["Iceman_TOC_slot", _slot];
    _viewCombo ctrlAddEventHandler ["LBSelChanged", {
        params ["_ctrl", "_idx"];
        private _target = _ctrl getVariable ["Iceman_TOC_target", objNull];
        private _slot = _ctrl getVariable ["Iceman_TOC_slot", 0];
        if (!isNull _target && {_idx >= 0}) then {
            private _vision = _ctrl lbData _idx;
            [_target, _slot, _vision] call Iceman_fnc_toc_setVisionGlobal;
            [ctrlParent _ctrl] call Iceman_fnc_toc_viewDeviceRefresh;
        };
    }];

    private _cameraLabel = ["Iceman_TOC_Text", safeZoneX + safeZoneW * 0.05, safeZoneY + safeZoneH * 0.092, safeZoneW * 0.055, safeZoneH * 0.028, "Camera", [0,0,0,0]] call _addControl;
    _cameraLabel ctrlSetTextColor [0.86,0.94,0.96,1];

    private _cameraCombo = ["RscCombo", safeZoneX + safeZoneW * 0.105, safeZoneY + safeZoneH * 0.092, safeZoneW * 0.42, safeZoneH * 0.032, "", []] call _addControl;
    private _availableFeeds = call Iceman_fnc_toc_getFeeds;
    private _currentFeedId = "";
    if ((count _feed) >= 4) then {
        _currentFeedId = format ["%1:%2:%3", _feed # 1, _feed # 2, _feed # 3];
    };

    {
        private _idx = _cameraCombo lbAdd (_x # 0);
        _cameraCombo lbSetData [_idx, str _x];

        private _feedId = format ["%1:%2:%3", _x # 1, _x # 2, _x # 3];
        if (_feedId == _currentFeedId) then {
            _cameraCombo lbSetCurSel _idx;
        };
    } forEach _availableFeeds;

    if ((lbCurSel _cameraCombo) < 0 && {(lbSize _cameraCombo) > 0}) then {
        _cameraCombo lbSetCurSel 0;
    };

    _cameraCombo setVariable ["Iceman_TOC_stream", _stream];
    _cameraCombo ctrlAddEventHandler ["LBSelChanged", {
        params ["_ctrl", "_idx"];
        if (_idx < 0) exitWith {};

        private _stream = _ctrl getVariable ["Iceman_TOC_stream", []];
        if (_stream isEqualTo []) exitWith {};

        _stream params ["_label", "_target", "_slot", "_cam", "_renderTarget", "_texture", "_feed", "_settings"];
        if (isNull _target) exitWith {};

        private _newFeed = call compile (_ctrl lbData _idx);
        _settings = [_settings] call Iceman_fnc_toc_normalizeSettings;
        _settings set [9, _slot];

        [_target, _newFeed, _settings] call Iceman_fnc_toc_syncStreamGlobal;

        [ctrlParent _ctrl, _target, _slot] spawn {
            params ["_display", "_target", "_slot"];
            uiSleep 0.25;
            if (isNull _display) exitWith {};
            private _newStream = [_target, _slot] call Iceman_fnc_toc_findViewStream;
            if !(_newStream isEqualTo []) then {
                _display setVariable ["Iceman_TOC_currentStream", _newStream];
            };
            [_display] call Iceman_fnc_toc_viewDeviceRefresh;
        };
    }];

    private _picX = safeZoneX + safeZoneW * 0.05;
    private _picY = safeZoneY + safeZoneH * 0.13;
    private _picW = safeZoneW * 0.90;
    private _picH = safeZoneH * 0.725;

    ["Iceman_TOC_Picture", _picX, _picY, _picW, _picH, _texture, [0,0,0,1]] call _addControl;
    if (_overlay) then {
        [_picX, _picY, _picW, _picH] call _drawOverlay;
    };

    private _gridBanner = ["Iceman_TOC_Text", _picX + safeZoneW * 0.008, _picY + safeZoneH * 0.008, safeZoneW * 0.22, safeZoneH * 0.032, format ["LOOK GRID %1", _lookGrid], [0,0,0,0.55]] call _addControl;
    _gridBanner ctrlSetFont "RobotoCondensedBold";
    _gridBanner ctrlSetTextColor [0.7,1,0.74,1];

    private _back = ["Iceman_TOC_Button", safeZoneX + safeZoneW * 0.05, safeZoneY + safeZoneH * 0.875, safeZoneW * 0.10, safeZoneH * 0.032, "Back", []] call _addControl;
    _back ctrlAddEventHandler ["ButtonClick", {
        params ["_ctrl"];
        private _display = ctrlParent _ctrl;
        _display setVariable ["Iceman_TOC_mode", "home"];
        [_display] call Iceman_fnc_toc_viewDeviceRefresh;
    }];

    private _presentText = ["Present", "End Present"] select _isPresented;
    private _present = ["Iceman_TOC_Button", safeZoneX + safeZoneW * 0.16, safeZoneY + safeZoneH * 0.875, safeZoneW * 0.12, safeZoneH * 0.032, _presentText, []] call _addControl;
    _present setVariable ["Iceman_TOC_stream", _stream];
    _present setVariable ["Iceman_TOC_isPresented", _isPresented];
    _present ctrlAddEventHandler ["ButtonClick", {
        params ["_ctrl"];
        private _stream = _ctrl getVariable ["Iceman_TOC_stream", []];
        private _isPresented = _ctrl getVariable ["Iceman_TOC_isPresented", false];
        if !(_stream isEqualTo []) then {
            _stream params ["_label", "_target", "_slot"];
            [!_isPresented, _target, _slot, _label] call Iceman_fnc_toc_setPresenterGlobal;
        };
    }];

    private _snap = ["Iceman_TOC_Button", safeZoneX + safeZoneW * 0.29, safeZoneY + safeZoneH * 0.875, safeZoneW * 0.12, safeZoneH * 0.032, "Snapshot", []] call _addControl;
    _snap setVariable ["Iceman_TOC_stream", _stream];
    _snap ctrlAddEventHandler ["ButtonClick", {
        params ["_ctrl"];
        private _stream = _ctrl getVariable ["Iceman_TOC_stream", []];
        if !(_stream isEqualTo []) then {
            _stream params ["_label", "_target", "_slot", "_cam", "_renderTarget", "_texture", "_feed", "_settings", ["_zoom", 1], ["_vision", "normal"]];
            [_target, _slot, _label, _vision, _zoom] call Iceman_fnc_toc_snapshotGlobal;
        };
    }];

    private _gridText = ["Grid Off", "Grid On"] select _overlay;
    private _grid = ["Iceman_TOC_Button", safeZoneX + safeZoneW * 0.42, safeZoneY + safeZoneH * 0.875, safeZoneW * 0.12, safeZoneH * 0.032, _gridText, []] call _addControl;
    _grid ctrlAddEventHandler ["ButtonClick", {
        params ["_ctrl"];
        private _display = ctrlParent _ctrl;
        private _overlay = !(_display getVariable ["Iceman_TOC_overlay", false]);
        _display setVariable ["Iceman_TOC_overlay", _overlay];
        [_display] call Iceman_fnc_toc_viewDeviceRefresh;
    }];

    private _briefing = ["Iceman_TOC_Button", safeZoneX + safeZoneW * 0.55, safeZoneY + safeZoneH * 0.875, safeZoneW * 0.12, safeZoneH * 0.032, "Briefing", []] call _addControl;
    _briefing ctrlAddEventHandler ["ButtonClick", {
        params ["_ctrl"];
        private _display = ctrlParent _ctrl;
        _display setVariable ["Iceman_TOC_mode", "briefing"];
        [_display] call Iceman_fnc_toc_viewDeviceRefresh;
    }];

    private _close = ["Iceman_TOC_Button", safeZoneX + safeZoneW * 0.84, safeZoneY + safeZoneH * 0.875, safeZoneW * 0.11, safeZoneH * 0.032, "Close", []] call _addControl;
    _close ctrlAddEventHandler ["ButtonClick", {closeDialog 0;}];

    private _zoomOut = ["Iceman_TOC_Button", safeZoneX + safeZoneW * 0.24, safeZoneY + safeZoneH * 0.923, safeZoneW * 0.10, safeZoneH * 0.032, "Zoom -", []] call _addControl;
    _zoomOut ctrlAddEventHandler ["ButtonClick", {
        params ["_ctrl"];
        private _display = ctrlParent _ctrl;
        private _stream = _display getVariable ["Iceman_TOC_currentStream", []];
        if !(_stream isEqualTo []) then {
            _stream params ["_label", "_target", "_slot"];
            private _zoom = [_target, _slot] call Iceman_fnc_toc_getZoomValue;
            [_target, _slot, _zoom / 1.25] call Iceman_fnc_toc_setZoomGlobal;
            [_display] call Iceman_fnc_toc_viewDeviceRefresh;
        };
    }];

    private _zoomReset = ["Iceman_TOC_Button", safeZoneX + safeZoneW * 0.35, safeZoneY + safeZoneH * 0.923, safeZoneW * 0.10, safeZoneH * 0.032, "Reset", []] call _addControl;
    _zoomReset ctrlAddEventHandler ["ButtonClick", {
        params ["_ctrl"];
        private _display = ctrlParent _ctrl;
        private _stream = _display getVariable ["Iceman_TOC_currentStream", []];
        if !(_stream isEqualTo []) then {
            _stream params ["_label", "_target", "_slot"];
            [_target, _slot, 1] call Iceman_fnc_toc_setZoomGlobal;
            [_display] call Iceman_fnc_toc_viewDeviceRefresh;
        };
    }];

    private _zoomIn = ["Iceman_TOC_Button", safeZoneX + safeZoneW * 0.46, safeZoneY + safeZoneH * 0.923, safeZoneW * 0.10, safeZoneH * 0.032, "Zoom +", []] call _addControl;
    _zoomIn ctrlAddEventHandler ["ButtonClick", {
        params ["_ctrl"];
        private _display = ctrlParent _ctrl;
        private _stream = _display getVariable ["Iceman_TOC_currentStream", []];
        if !(_stream isEqualTo []) then {
            _stream params ["_label", "_target", "_slot"];
            private _zoom = [_target, _slot] call Iceman_fnc_toc_getZoomValue;
            [_target, _slot, _zoom * 1.25] call Iceman_fnc_toc_setZoomGlobal;
            [_display] call Iceman_fnc_toc_viewDeviceRefresh;
        };
    }];

    private _zoomText = ["Iceman_TOC_Text", safeZoneX + safeZoneW * 0.58, safeZoneY + safeZoneH * 0.923, safeZoneW * 0.16, safeZoneH * 0.032, format ["Zoom %1x", (round (_zoom * 10)) / 10], [0.04,0.07,0.08,0.95]] call _addControl;
    _zoomText ctrlSetFont "RobotoCondensedBold";
    _zoomText ctrlSetTextColor [0.86,0.94,0.96,1];

    _display setVariable ["Iceman_TOC_dynamicControls", _controls];
};

if (_mode == "snapshots") exitWith {
    private _title = ["Iceman_TOC_Text", safeZoneX + safeZoneW * 0.055, safeZoneY + safeZoneH * 0.12, safeZoneW * 0.65, safeZoneH * 0.04, "Snapshots", [0,0,0,0]] call _addControl;
    _title ctrlSetFont "RobotoCondensedBold";
    _title ctrlSetTextColor [1,1,1,1];

    private _snapshotsRaw = missionNamespace getVariable ["Iceman_TOC_snapshots", []];
    private _snapshots = [];
    for "_i" from ((count _snapshotsRaw) - 1) to 0 step -1 do {
        _snapshots pushBack (_snapshotsRaw # _i);
    };

    if (_snapshots isEqualTo []) then {
        private _empty = ["Iceman_TOC_Text", safeZoneX + safeZoneW * 0.22, safeZoneY + safeZoneH * 0.42, safeZoneW * 0.56, safeZoneH * 0.07, "No snapshots yet.", [0.06,0.09,0.10,0.92]] call _addControl;
        _empty ctrlSetFont "RobotoCondensedBold";
        _empty ctrlSetTextColor [0.86,0.94,0.96,1];
    } else {
        {
            if (_forEachIndex < 10) then {
                _x params ["_id", "_label", "_target", "_slot", "_author", "_time", "_vision", "_zoom"];
                private _rowY = safeZoneY + safeZoneH * (0.18 + (_forEachIndex * 0.062));
                ["Iceman_TOC_Text", safeZoneX + safeZoneW * 0.06, _rowY, safeZoneW * 0.72, safeZoneH * 0.048, "", [0.01,0.018,0.022,0.96]] call _addControl;

                private _text = ["Iceman_TOC_Text", safeZoneX + safeZoneW * 0.075, _rowY + safeZoneH * 0.006, safeZoneW * 0.58, safeZoneH * 0.032, format ["%1  |  %2  |  T+%3s  |  %4  |  %5x", _label, _author, round _time, toUpper _vision, (round (_zoom * 10)) / 10], [0,0,0,0]] call _addControl;
                _text ctrlSetTextColor [0.86,0.94,0.96,1];

                private _open = ["Iceman_TOC_Button", safeZoneX + safeZoneW * 0.67, _rowY + safeZoneH * 0.006, safeZoneW * 0.09, safeZoneH * 0.032, "Open", []] call _addControl;
                _open setVariable ["Iceman_TOC_target", _target];
                _open setVariable ["Iceman_TOC_slot", _slot];
                _open ctrlAddEventHandler ["ButtonClick", {
                    params ["_ctrl"];
                    private _display = ctrlParent _ctrl;
                    private _target = _ctrl getVariable ["Iceman_TOC_target", objNull];
                    private _slot = _ctrl getVariable ["Iceman_TOC_slot", 0];
                    private _stream = [_target, _slot] call Iceman_fnc_toc_findViewStream;
                    if !(_stream isEqualTo []) then {
                        _display setVariable ["Iceman_TOC_mode", "viewer"];
                        _display setVariable ["Iceman_TOC_currentStream", _stream];
                    };
                    [_display] call Iceman_fnc_toc_viewDeviceRefresh;
                }];
            };
        } forEach _snapshots;
    };

    private _back = ["Iceman_TOC_Button", safeZoneX + safeZoneW * 0.05, safeZoneY + safeZoneH * 0.915, safeZoneW * 0.11, safeZoneH * 0.035, "Back", []] call _addControl;
    _back ctrlAddEventHandler ["ButtonClick", {
        params ["_ctrl"];
        private _display = ctrlParent _ctrl;
        _display setVariable ["Iceman_TOC_mode", "home"];
        [_display] call Iceman_fnc_toc_viewDeviceRefresh;
    }];

    private _close = ["Iceman_TOC_Button", safeZoneX + safeZoneW * 0.84, safeZoneY + safeZoneH * 0.915, safeZoneW * 0.11, safeZoneH * 0.035, "Close", []] call _addControl;
    _close ctrlAddEventHandler ["ButtonClick", {closeDialog 0;}];

    _display setVariable ["Iceman_TOC_dynamicControls", _controls];
};

private _streams = call Iceman_fnc_toc_getActiveViewStreams;
private _signature = str (_streams apply {[_x # 1, _x # 2, _x # 4, _x # 8, _x # 9]});
_display setVariable ["Iceman_TOC_lastSignature", _signature];

private _presenterState = missionNamespace getVariable ["Iceman_TOC_presenterState", [false, objNull, -1, "", "", 0]];
if (_presenterState param [0, false]) then {
    private _presenter = _presenterState param [3, ""];
    private _presentLabel = _presenterState param [4, ""];
    private _banner = ["Iceman_TOC_Text", safeZoneX + safeZoneW * 0.055, safeZoneY + safeZoneH * 0.105, safeZoneW * 0.70, safeZoneH * 0.03, format ["Presenter: %1  |  %2", _presenter, _presentLabel], [0.08,0.16,0.10,0.9]] call _addControl;
    _banner ctrlSetTextColor [0.7,1,0.74,1];
};

private _snapshotsButton = ["Iceman_TOC_Button", safeZoneX + safeZoneW * 0.625, safeZoneY + safeZoneH * 0.915, safeZoneW * 0.10, safeZoneH * 0.035, "Snapshots", []] call _addControl;
_snapshotsButton ctrlAddEventHandler ["ButtonClick", {
    params ["_ctrl"];
    private _display = ctrlParent _ctrl;
    _display setVariable ["Iceman_TOC_mode", "snapshots"];
    [_display] call Iceman_fnc_toc_viewDeviceRefresh;
}];

private _refresh = ["Iceman_TOC_Button", safeZoneX + safeZoneW * 0.74, safeZoneY + safeZoneH * 0.915, safeZoneW * 0.095, safeZoneH * 0.035, "Refresh", []] call _addControl;
_refresh ctrlAddEventHandler ["ButtonClick", {
    params ["_ctrl"];
    [ctrlParent _ctrl] call Iceman_fnc_toc_viewDeviceRefresh;
}];

private _closeHome = ["Iceman_TOC_Button", safeZoneX + safeZoneW * 0.85, safeZoneY + safeZoneH * 0.915, safeZoneW * 0.095, safeZoneH * 0.035, "Close", []] call _addControl;
_closeHome ctrlAddEventHandler ["ButtonClick", {closeDialog 0;}];

private _count = count _streams;
if (_count == 0) exitWith {
    private _empty = ["Iceman_TOC_Text", safeZoneX + safeZoneW * 0.22, safeZoneY + safeZoneH * 0.42, safeZoneW * 0.56, safeZoneH * 0.07, "No active TOC screens.", [0.06,0.09,0.10,0.92]] call _addControl;
    _empty ctrlSetFont "RobotoCondensedBold";
    _empty ctrlSetTextColor [0.86,0.94,0.96,1];
    _display setVariable ["Iceman_TOC_dynamicControls", _controls];
};

private _cols = switch (true) do {
    case (_count <= 1): {1};
    case (_count <= 4): {2};
    case (_count <= 9): {3};
    default {4};
};
private _rows = ceil (_count / _cols);

private _gridX = safeZoneX + safeZoneW * 0.055;
private _gridY = safeZoneY + safeZoneH * 0.125;
private _gridW = safeZoneW * 0.89;
private _gridH = safeZoneH * 0.755;
private _gapX = safeZoneW * 0.012;
private _gapY = safeZoneH * 0.018;
private _tileW = (_gridW - ((_cols - 1) * _gapX)) / _cols;
private _tileH = (_gridH - ((_rows - 1) * _gapY)) / _rows;

{
    _x params ["_label", "_target", "_slot", "_cam", "_renderTarget", "_texture"];

    private _col = _forEachIndex mod _cols;
    private _row = floor (_forEachIndex / _cols);
    private _xPos = _gridX + (_col * (_tileW + _gapX));
    private _yPos = _gridY + (_row * (_tileH + _gapY));

    ["Iceman_TOC_Text", _xPos, _yPos, _tileW, _tileH, "", [0.01,0.018,0.022,0.96]] call _addControl;
    ["Iceman_TOC_Picture", _xPos + _tileW * 0.02, _yPos + _tileH * 0.08, _tileW * 0.96, _tileH * 0.76, _texture, [0,0,0,1]] call _addControl;
    private _lookGrid = [[_cam] call Iceman_fnc_toc_cameraLookPos, 8] call Iceman_fnc_toc_posToGrid;

    private _title = ["Iceman_TOC_Text", _xPos + _tileW * 0.02, _yPos + _tileH * 0.01, _tileW * 0.96, _tileH * 0.055, _label, [0.04,0.07,0.08,0.95]] call _addControl;
    _title ctrlSetFont "RobotoCondensedBold";
    _title ctrlSetTextColor [1,1,1,1];

    private _gridLabel = ["Iceman_TOC_Text", _xPos + _tileW * 0.02, _yPos + _tileH * 0.80, _tileW * 0.42, _tileH * 0.055, format ["GRID %1", _lookGrid], [0,0,0,0.55]] call _addControl;
    _gridLabel ctrlSetFont "RobotoCondensedBold";
    _gridLabel ctrlSetTextColor [0.7,1,0.74,1];

    private _view = ["Iceman_TOC_Button", _xPos + _tileW * 0.32, _yPos + _tileH * 0.87, _tileW * 0.36, _tileH * 0.095, "View Camera", []] call _addControl;
    _view setVariable ["Iceman_TOC_stream", _x];
    _view ctrlAddEventHandler ["ButtonClick", _openStreamFromControl];
} forEach _streams;

_display setVariable ["Iceman_TOC_dynamicControls", _controls];
