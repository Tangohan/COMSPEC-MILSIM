#include "..\script_component.hpp"

params ["_control", "_menuGroup", "_settings"];

private _display = ctrlParent _menuGroup;
private _findCtrl = {
    params ["_idc"];
    private _result = controlNull;
    {
        if (ctrlIDC _x == _idc) exitWith {_result = _x};
    } forEach allControls _display;
    _result
};

private _state = call Iceman_fnc_elev_getState;
private _mode = _state getOrDefault ["mode", "viewshed"];
if (_state getOrDefault ["planning", false]) exitWith {
    ["ELEVATION", "Elevation overlay is already generating.", 3] call cTab_fnc_addNotification;
};

private _heightCtrl = [123] call _findCtrl;
private _radiusCtrl = [125] call _findCtrl;

if (_mode == "heatmap") then {
    if (!isNull _heightCtrl) then {
        private _value = parseNumber (ctrlText _heightCtrl);
        _state set ["heatmapSizeM", (250 max _value) min 5000];
    };
    if (!isNull _radiusCtrl) then {
        private _value = parseNumber (ctrlText _radiusCtrl);
        _state set ["sampleM", (25 max _value) min 250];
    };
} else {
    if (!isNull _heightCtrl) then {
        private _value = parseNumber (ctrlText _heightCtrl);
        _state set ["heightFt", (1 max _value) min 200];
    };
    if (!isNull _radiusCtrl) then {
        private _value = parseNumber (ctrlText _radiusCtrl);
        _state set ["radiusM", (100 max _value) min 3000];
    };
};

private _point = [_state getOrDefault ["viewshedPoint", []], _state getOrDefault ["heatmapCenter", []]] select (_mode == "heatmap");
if (_point isEqualTo []) exitWith {
    ["ELEVATION", "Pick a map point first.", 4] call cTab_fnc_addNotification;
};

private _planningId = diag_tickTime;
_state set ["planning", true];
_state set ["planningId", _planningId];
_state set ["overlay", []];
_state set ["overlayType", _mode];
_state set ["active", false];
_state set ["status", ["Generating View Shed", "Generating Heatmap"] select (_mode == "heatmap")];
call Iceman_fnc_elev_updatePanel;

[
    _mode,
    _point,
    _state getOrDefault ["heightFt", 6],
    _state getOrDefault ["radiusM", 500],
    _state getOrDefault ["heatmapSizeM", 1000],
    _state getOrDefault ["sampleM", 80],
    _planningId
] spawn {
    params ["_mode", "_point", "_heightFt", "_radiusM", "_heatmapSizeM", "_sampleM", "_planningId"];
    uiSleep 0.01;

    private _state = call Iceman_fnc_elev_getState;
    private _result = if (_mode == "heatmap") then {
        [_point, _heatmapSizeM, _sampleM] call Iceman_fnc_elev_computeHeatmap
    } else {
        [_point, _heightFt, _radiusM] call Iceman_fnc_elev_computeViewshed
    };

    _state = call Iceman_fnc_elev_getState;
    if ((_state getOrDefault ["planningId", -1]) != _planningId) exitWith {};

    _state set ["planning", false];
    _state set ["planningId", -1];
    _state set ["active", true];
    _state set ["overlayType", _mode];

    if (_mode == "heatmap") then {
        _result params ["_cells", "_minH", "_maxH"];
        _state set ["overlay", _cells];
        _state set ["minH", _minH];
        _state set ["maxH", _maxH];
        _state set ["status", "Heatmap ready"];
        ["ELEVATION", format ["Heatmap ready: %1 cells.", count _cells], 4] call cTab_fnc_addNotification;
    } else {
        _result params ["_center", "_radius", "_segments", "_visibleCount", "_deadCount"];
        _state set ["overlay", [_center, _radius, _segments]];
        _state set ["visibleCount", _visibleCount];
        _state set ["deadCount", _deadCount];
        _state set ["status", "View Shed ready"];
        ["ELEVATION", format ["View Shed ready: %1 visible, %2 deadspace.", _visibleCount, _deadCount], 4] call cTab_fnc_addNotification;
    };

    call Iceman_fnc_elev_updatePanel;
};
