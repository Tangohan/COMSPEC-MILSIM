params [["_action", ""]];

private _display = uiNamespace getVariable ["cTab_Android_dlg", displayNull];
private _miniDisplay = uiNamespace getVariable ["cTab_Android_dsp", displayNull];
if (isNull _display && {isNull _miniDisplay}) exitWith {false};

private _settings = ["cTab_Android_dlg", "showMenu"] call cTab_fnc_getSettings;
private _page = _settings param [0, ""];
if (_page != "VideoFeeds") exitWith {false};

private _hasDialog = !isNull _display;

private _current = if (_hasDialog) then {call BCE_fnc_ATAK_getCurrentAPP} else {["", controlNull]};
private _group = _current param [1, controlNull];
private _viewGroup = if (isNull _group) then {controlNull} else {_group controlsGroupCtrl 20};

switch (_action) do {
    case "feedList": {
        if (!_hasDialog) exitWith {false};
        call BCE_fnc_ATAK_toggleSubListMenu;
        true
    };
    case "map": {
        if (_hasDialog) then {
            [controlNull, 8] call Iceman_fnc_ATAK_Camera_Controls;
        } else {
            private _state = !(uiNamespace getVariable ["BCE_ATAK_VideoFeed_MapATAK", false]);
            uiNamespace setVariable ["BCE_ATAK_VideoFeed_MapATAK", _state];
            uiNamespace setVariable ["BCE_ATAK_VideoFeed_FullATAK", false];
            [_state] call Iceman_fnc_ATAK_setMapFeedOverlay;
        };
        true
    };
    case "full": {
        if (!_hasDialog) exitWith {false};
        [controlNull, 5] call Iceman_fnc_ATAK_Camera_Controls;
        true
    };
    case "zoomIn": {
        if (_hasDialog) then {
            [controlNull, 6] call Iceman_fnc_ATAK_Camera_Controls;
        } else {
            [1] call Iceman_fnc_ATAK_applyCameraZoom;
        };
        true
    };
    case "zoomOut": {
        if (_hasDialog) then {
            [controlNull, 7] call Iceman_fnc_ATAK_Camera_Controls;
        } else {
            [-1] call Iceman_fnc_ATAK_applyCameraZoom;
        };
        true
    };
    case "vision": {
        if (!_hasDialog) exitWith {false};
        private _ctrl = if (isNull _viewGroup) then {controlNull} else {_viewGroup controlsGroupCtrl 13};
        if (isNull _ctrl) exitWith {false};
        [_ctrl, 1] call BCE_fnc_ATAK_Camera_Controls;
        true
    };
    case "sync": {
        if (!_hasDialog) exitWith {false};
        private _ctrl = if (isNull _viewGroup) then {controlNull} else {_viewGroup controlsGroupCtrl 14};
        if (isNull _ctrl) exitWith {false};
        [_ctrl, 2] call BCE_fnc_ATAK_Camera_Controls;
        true
    };
    case "track": {
        if (!_hasDialog) exitWith {false};
        private _ctrl = if (isNull _viewGroup) then {controlNull} else {_viewGroup controlsGroupCtrl 11};
        if (isNull _ctrl) exitWith {false};
        [_ctrl, 0] call BCE_fnc_ATAK_Camera_Controls;
        true
    };
    case "turret": {
        if (!_hasDialog) exitWith {false};
        private _buttonGroup = _display displayCtrl 46600;
        private _ctrl = if (isNull _buttonGroup) then {controlNull} else {_buttonGroup controlsGroupCtrl 11};
        if (isNull _ctrl) exitWith {false};
        [_ctrl, 17000] call BCE_fnc_NextTurretButton;
        true
    };
    default {
        false
    };
};
