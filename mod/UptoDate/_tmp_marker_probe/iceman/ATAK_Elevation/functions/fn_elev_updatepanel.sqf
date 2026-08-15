#include "..\script_component.hpp"

private _display = uiNamespace getVariable ["cTab_Android_dlg", displayNull];
if (isNull _display) exitWith {};

private _pageGroup = uiNamespace getVariable ["Iceman_ATAK_Elevation_pageGroup", controlNull];
private _scanControls = if (isNull _pageGroup) then {
    allControls _display
} else {
    allControls _pageGroup
};

private _ctrls = createHashMap;
{
    private _idc = ctrlIDC _x;
    if (_idc >= 0) then {
        _ctrls set [str _idc, _x];
    };
} forEach _scanControls;

private _state = call Iceman_fnc_elev_getState;
private _mode = _state getOrDefault ["mode", "viewshed"];
private _planning = _state getOrDefault ["planning", false];
private _overlay = _state getOrDefault ["overlay", []];
private _status = _state getOrDefault ["status", "Ready"];
private _viewshedPoint = _state getOrDefault ["viewshedPoint", []];
private _heatmapCenter = _state getOrDefault ["heatmapCenter", []];

private _viewTab = _ctrls getOrDefault ["101", controlNull];
private _heatTab = _ctrls getOrDefault ["102", controlNull];
if (!isNull _viewTab) then {_viewTab ctrlSetBackgroundColor ([[0,0,0,0.45], [0.05,0.45,0.6,0.75]] select (_mode == "viewshed"))};
if (!isNull _heatTab) then {_heatTab ctrlSetBackgroundColor ([[0,0,0,0.45], [0.05,0.45,0.6,0.75]] select (_mode == "heatmap"))};

private _pointEdit = _ctrls getOrDefault ["121", controlNull];
if (!isNull _pointEdit) then {
    private _point = [_viewshedPoint, _heatmapCenter] select (_mode == "heatmap");
    if (!(_point isEqualTo [])) then {
        _pointEdit ctrlSetText ([_point] call Iceman_fnc_elev_posToGrid);
    };
};

{
    _x params ["_idc", "_key", "_default"];
    private _ctrl = _ctrls getOrDefault [str _idc, controlNull];
    if (!isNull _ctrl) then {
        _ctrl ctrlSetText str (_state getOrDefault [_key, _default]);
    };
} forEach ([[[123, "heightFt", 6], [125, "radiusM", 500]], [[123, "heatmapSizeM", 1000], [125, "sampleM", 80]]] select (_mode == "heatmap"));

private _pointLabel = _ctrls getOrDefault ["120", controlNull];
if (!isNull _pointLabel) then {
    _pointLabel ctrlSetStructuredText parseText (["Point", "Center"] select (_mode == "heatmap"));
};
private _param1Label = _ctrls getOrDefault ["122", controlNull];
if (!isNull _param1Label) then {
    _param1Label ctrlSetStructuredText parseText (["AGL ft", "Size m"] select (_mode == "heatmap"));
};
private _param2Label = _ctrls getOrDefault ["124", controlNull];
if (!isNull _param2Label) then {
    _param2Label ctrlSetStructuredText parseText (["Radius m", "Sample m"] select (_mode == "heatmap"));
};

private _statusCtrl = _ctrls getOrDefault ["30", controlNull];
if (!isNull _statusCtrl) then {
    private _statusText = if (_planning) then {"Planning..."} else {_status};
    _statusCtrl ctrlSetStructuredText parseText format ["<t align='center'>%1</t>", _statusText];
};

private _infoCtrl = _ctrls getOrDefault ["31", controlNull];
if (!isNull _infoCtrl) then {
    private _lines = [];
    _lines pushBack format ["Mode: %1", ["View Shed", "Heatmap"] select (_mode == "heatmap")];
    if (_mode == "viewshed") then {
        _lines pushBack format ["Point: %1", ["not set", [_viewshedPoint] call Iceman_fnc_elev_posToGrid] select !(_viewshedPoint isEqualTo [])];
        _lines pushBack format ["AGL: %1 ft", _state getOrDefault ["heightFt", 6]];
        _lines pushBack format ["Radius: %1 m", _state getOrDefault ["radiusM", 500]];
        if (!(_overlay isEqualTo [])) then {
            _lines pushBack format ["Segments: %1", count (_overlay param [2, []])];
            _lines pushBack format ["Visible: %1", _state getOrDefault ["visibleCount", 0]];
            _lines pushBack format ["Deadspace: %1", _state getOrDefault ["deadCount", 0]];
        };
    } else {
        _lines pushBack format ["Center: %1", ["not set", [_heatmapCenter] call Iceman_fnc_elev_posToGrid] select !(_heatmapCenter isEqualTo [])];
        _lines pushBack format ["Size: %1 m", _state getOrDefault ["heatmapSizeM", 1000]];
        _lines pushBack format ["Sample: %1 m", _state getOrDefault ["sampleM", 80]];
        if (!(_overlay isEqualTo [])) then {
            _lines pushBack format ["Cells: %1", count _overlay];
            _lines pushBack format ["Low: %1 m", round (_state getOrDefault ["minH", 0])];
            _lines pushBack format ["High: %1 m", round (_state getOrDefault ["maxH", 0])];
        };
    };
    _infoCtrl ctrlSetStructuredText parseText (_lines joinString "<br/>");
};
