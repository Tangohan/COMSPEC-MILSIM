#include "..\script_component.hpp"

params ["_map"];

private _state = call Iceman_fnc_elev_getState;
private _overlayType = _state getOrDefault ["overlayType", ""];
private _overlay = _state getOrDefault ["overlay", []];
private _viewshedPoint = _state getOrDefault ["viewshedPoint", []];
private _heatmapCenter = _state getOrDefault ["heatmapCenter", []];
private _planning = _state getOrDefault ["planning", false];

if (_overlayType == "viewshed") then {
    _overlay params [["_center", []], ["_radius", 0], ["_visibleSegments", []]];
    private _deadspaceColor = missionNamespace getVariable ["Iceman_ATAK_Elevation_deadspaceColor", [1, 0.05, 0.02, 0.35]];
    private _visibleColor = missionNamespace getVariable ["Iceman_ATAK_Elevation_visibleColor", [0.05, 1, 0.12, 0.42]];
    if (!(_center isEqualTo []) && {_radius > 0}) then {
        _map drawEllipse [_center, _radius, _radius, 0, _deadspaceColor, "#(argb,8,8,3)color(1,1,1,1)"];
    };
    {
        try {
            _map drawPolygon [_x, _visibleColor];
        } catch {};
    } forEach _visibleSegments;

    if (!(_viewshedPoint isEqualTo [])) then {
        _map drawIcon ["\A3\ui_f\data\map\markers\military\dot_CA.paa", [0.2, 0.65, 1, 1], _viewshedPoint, 28, 28, 0, "VIEWSHED", 1, 0.04, "RobotoCondensed", "right"];
    };
};

if (_overlayType == "heatmap") then {
    {
        _x params ["_pos", "_color", "_cell"];
        _map drawRectangle [_pos, _cell * 0.55, _cell * 0.55, 0, _color, "#(argb,8,8,3)color(1,1,1,1)"];
    } forEach _overlay;

    if (!(_heatmapCenter isEqualTo [])) then {
        _map drawIcon ["\A3\ui_f\data\map\markers\military\dot_CA.paa", [0.2, 0.65, 1, 1], _heatmapCenter, 24, 24, 0, "HEATMAP", 1, 0.04, "RobotoCondensed", "right"];
    };
};

if (_planning) then {
    private _pos = if (_overlayType == "heatmap") then {_heatmapCenter} else {_viewshedPoint};
    if (!(_pos isEqualTo [])) then {
        _map drawIcon ["\A3\ui_f\data\map\markers\military\dot_CA.paa", [0.2, 0.65, 1, 1], _pos, 26, 26, 0, "PROCESSING", 1, 0.04, "RobotoCondensed", "right"];
    };
};
