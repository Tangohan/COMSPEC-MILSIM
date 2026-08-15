#include "..\script_component.hpp"

private _modeCtrl = controlNull;
private _infoCtrl = controlNull;
private _state = call Iceman_fnc_jump_getState;
private _tab = _state getOrDefault ["tab", "plan"];
private _group = uiNamespace getVariable ["Iceman_ATAK_Jump_group", controlNull];
private _controls = [];

if (!isNull _group) then {
    _controls = allControls _group;
} else {
    private _display = uiNamespace getVariable ["cTab_Android_dlg", displayNull];
    if (isNull _display) exitWith {};
    _controls = allControls _display;
};

{
    private _section = _x getVariable ["IcemanJumpSection", ""];
    if (_section != "") then {
        private _show = _section == "common" || {_section == _tab};
        _x ctrlShow _show;
        _x ctrlEnable _show;
    };
    if (ctrlIDC _x == 9430) then {_modeCtrl = _x};
    if (ctrlIDC _x == 9431) then {_infoCtrl = _x};
} forEach _controls;

if (isNull _modeCtrl && {isNull _infoCtrl}) exitWith {};

private _jumpPoint = _state getOrDefault ["jumpPoint", []];
private _dropZone = _state getOrDefault ["dropZone", []];
private _waypoints = _state getOrDefault ["waypoints", []];
private _distance = _state getOrDefault ["distance", 0];
private _canopyTime = _state getOrDefault ["canopyTime", 0];
private _mode = _state getOrDefault ["mode", "HAHO"];
private _selectMode = _state getOrDefault ["selectMode", ""];
private _planned = _state getOrDefault ["planned", false];
private _requiredExitAGL = _state getOrDefault ["requiredExitAGL", 0];
private _requiredPullAGL = _state getOrDefault ["requiredPullAGL", 0];
private _avgGroundSpeedKph = _state getOrDefault ["avgGroundSpeedKph", 30];
private _warnings = _state getOrDefault ["warnings", []];
private _ticks = _state getOrDefault ["ticks", []];

if (!isNull _modeCtrl) then {
    private _modeText = if (_selectMode == "") then {
        format ["%1 %2 ready", _mode, toUpper _tab]
    } else {
        private _target = switch (_selectMode) do {
            case "jumpPoint": {"jump point"};
            case "dropZone": {"drop zone"};
            case "waypoint": {"via point"};
            default {_selectMode};
        };
        format ["Tap map for %1", _target]
    };
    _modeCtrl ctrlSetStructuredText parseText format ["<t align='center'>%1</t>", _modeText];
};

if (!isNull _infoCtrl) then {
    private _lines = [];

    if (_tab == "waypoints") then {
        _lines pushBack format ["Waypoints: %1", count _waypoints];
        if (_waypoints isEqualTo []) then {
            _lines pushBack "No via points set.";
        } else {
            {
                _lines pushBack format ["%1. %2", _forEachIndex + 1, mapGridPosition _x];
            } forEach _waypoints;
        };
        _lines pushBack "";
        _lines pushBack "Path: JP -> via points -> DZ";
        _lines pushBack "Each via point adds canopy time and raises the required jump altitude.";
    } else {
        private _jpText = "not set";
        if !(_jumpPoint isEqualTo []) then {
            _jpText = mapGridPosition _jumpPoint;
        };

        private _dzText = "not set";
        if !(_dropZone isEqualTo []) then {
            _dzText = mapGridPosition _dropZone;
        };

        _lines pushBack format ["Mode: %1", _mode];
        _lines pushBack format ["JP: %1", _jpText];
        _lines pushBack format ["DZ: %1", _dzText];
        _lines pushBack format ["Via: %1", count _waypoints];
        _lines pushBack "Canopy base: 30 kph";

        if (!_planned) then {
            _lines pushBack "Plan: not generated";
            if (_mode == "HAHO") then {
                _lines pushBack "Pull: 3.5 sec after exit";
            } else {
                _lines pushBack "Pull: 300m AGL";
            };
        } else {
            private _eta = [_canopyTime] call Iceman_fnc_jump_formatTime;
            _lines pushBack format ["Distance: %1 km", (_distance / 1000) toFixed 2];
            _lines pushBack format ["Canopy ETA: %1", _eta];
            _lines pushBack format ["Avg canopy GS: %1 kph", _avgGroundSpeedKph toFixed 1];
            _lines pushBack format ["Map marks: %1", count _ticks];

            if (_mode == "HAHO") then {
                _lines pushBack format ["Required JP AGL: %1ft / %2m", round (_requiredExitAGL / 0.3048), round _requiredExitAGL];
                _lines pushBack "Pull delay: 3.5 sec";
            } else {
                private _ok = ["SHORT", "OK"] select (_requiredPullAGL <= 300);
                _lines pushBack format ["300m HALO pull: %1", _ok];
                _lines pushBack format ["Required pull AGL: %1m / %2ft", round _requiredPullAGL, round (_requiredPullAGL / 0.3048)];
            };

            {
                _lines pushBack format ["WARN: %1", _x];
            } forEach _warnings;
        };
    };

    _infoCtrl ctrlSetStructuredText parseText (_lines joinString "<br/>");
};
