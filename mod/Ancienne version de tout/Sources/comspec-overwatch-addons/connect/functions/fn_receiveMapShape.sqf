/*
    Crée ou met à jour un marqueur local depuis une forme Athena.
    _payload: [shapeId, type, label, color, posX, posY, radius, points?]
      - type POINT/ICON → mil_dot
      - CIRCLE/ELLIPSE → ELLIPSE
      - LINE/POLYLINE → POLYLINE (points = [x,y,x,y,...] ou [[x,y],...])
*/
params [["_payload", [], [[]]]];
if (count _payload < 5) exitWith {};

private _shapeId = str (_payload select 0);
private _type = toUpper (str (_payload param [1, "POINT"]));
private _label = _payload param [2, ""];
private _color = _payload param [3, "ColorRed"];
private _posX = _payload param [4, 0];
private _posY = _payload param [5, 0];
private _radius = _payload param [6, 100];
private _points = if ((count _payload) >= 8) then { _payload select 7 } else { [] };
private _pos = [_posX, _posY, 0];

if (!(_color isEqualType "")) then { _color = str _color; };
if ((_color select [0, 1]) isEqualTo "#" || {(_color find "Color") < 0}) then {
    _color = "ColorRed";
};

private _existing = missionNamespace getVariable ["COMSPEC_MapShapeMarkers", createHashMap];
private _markerName = "COMSPEC_shape_" + _shapeId;
if (_markerName in _existing) then {
    deleteMarkerLocal (_existing get _markerName);
    _existing deleteAt _markerName;
};

private _flat = [];
if (_points isEqualType [] && {(count _points) > 0}) then {
    if ((_points select 0) isEqualType []) then {
        {
            if (_x isEqualType [] && {(count _x) >= 2}) then {
                _flat pushBack (_x select 0);
                _flat pushBack (_x select 1);
            };
        } forEach _points;
    } else {
        _flat = _points;
    };
};

private _isLine = (_type in ["LINE", "POLYLINE", "PATH"]) || {(count _flat) >= 4};

private _mrk = createMarkerLocal [_markerName, _pos];
if (_isLine && {(count _flat) >= 4}) then {
    _mrk setMarkerShapeLocal "POLYLINE";
    _mrk setMarkerPolylineLocal _flat;
    _mrk setMarkerPosLocal [_flat select 0, _flat select 1, 0];
} else {
    if (_type in ["CIRCLE", "ELLIPSE"]) then {
        _mrk setMarkerShapeLocal "ELLIPSE";
        _mrk setMarkerSizeLocal [_radius, _radius];
    } else {
        _mrk setMarkerShapeLocal "ICON";
        _mrk setMarkerTypeLocal "mil_dot";
    };
};
_mrk setMarkerColorLocal _color;
_mrk setMarkerTextLocal _label;
_existing set [_markerName, _markerName];
missionNamespace setVariable ["COMSPEC_MapShapeMarkers", _existing];
