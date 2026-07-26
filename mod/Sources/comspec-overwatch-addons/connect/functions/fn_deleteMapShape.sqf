/*
    Delete local marker for a map shape by id.
*/
params [["_shapeId", ""]];
if (_shapeId isEqualTo "") exitWith {};
private _markerName = "COMSPEC_shape_" + _shapeId;
private _existing = missionNamespace getVariable ["COMSPEC_MapShapeMarkers", createHashMap];
if (_markerName in _existing) then {
    deleteMarkerLocal (_existing get _markerName);
    _existing deleteAt _markerName;
    missionNamespace setVariable ["COMSPEC_MapShapeMarkers", _existing];
};
