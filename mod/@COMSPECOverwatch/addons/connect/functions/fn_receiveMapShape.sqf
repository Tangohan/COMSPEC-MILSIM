/*
    Create or update a local marker from a map shape.
    _payload: [shapeId, type, label, color, posX, posY, radius]. radius optional (for CIRCLE/ELLIPSE).
*/
params [["_payload", [], [[]]]];
if (count _payload < 5) exitWith {};
private _shapeId = str (_payload select 0);
private _type = _payload param [1, "POINT"];
private _label = _payload param [2, ""];
private _color = _payload param [3, "ColorRed"];
private _posX = _payload param [4, 0];
private _posY = _payload param [5, 0];
private _radius = _payload param [6, 100];
private _pos = [_posX, _posY, 0];

private _existing = missionNamespace getVariable ["COMSPEC_MapShapeMarkers", createHashMap];
private _markerName = "COMSPEC_shape_" + _shapeId;
if (_markerName in _existing) then {
    deleteMarkerLocal (_existing get _markerName);
    _existing deleteAt _markerName;
};

private _mrk = createMarkerLocal [_markerName, _pos];
if (_type == "CIRCLE" || _type == "ELLIPSE") then {
    _mrk setMarkerShapeLocal "ELLIPSE";
    _mrk setMarkerSizeLocal [_radius, _radius];
} else {
    _mrk setMarkerShapeLocal "ICON";
    _mrk setMarkerTypeLocal "mil_dot";
};
_mrk setMarkerColorLocal _color;
_mrk setMarkerTextLocal _label;
_existing set [_markerName, _markerName];
missionNamespace setVariable ["COMSPEC_MapShapeMarkers", _existing];
