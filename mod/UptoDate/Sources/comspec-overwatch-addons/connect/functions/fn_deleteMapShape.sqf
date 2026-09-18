/*
    Delete local marker for a map shape by id.
*/
params [["_shapeId", ""]];
if (_shapeId isEqualTo "") exitWith {};
private _markerName = "COMSPEC_shape_" + _shapeId;
private _existing = missionNamespace getVariable ["COMSPEC_MapShapeMarkers", createHashMap];
if (!(_existing isEqualType createHashMap)) exitWith {};
if (_markerName in _existing) then {
    private _muted = (missionNamespace getVariable ["COMSPEC_MarkerEhMuted", 0]) + 1;
    missionNamespace setVariable ["COMSPEC_MarkerEhMuted", _muted, false];
    deleteMarkerLocal (_existing get _markerName);
    _existing deleteAt _markerName;
    missionNamespace setVariable ["COMSPEC_MapShapeMarkers", _existing];
    private _unmute = (missionNamespace getVariable ["COMSPEC_MarkerEhMuted", 1]) - 1;
    if (_unmute < 0) then { _unmute = 0; };
    missionNamespace setVariable ["COMSPEC_MarkerEhMuted", _unmute, false];
};
