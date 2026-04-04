/*
    Poll GetMapShapes from extension, parse minimal JSON and create/update/delete local markers.
*/
private _raw = "COMSPECExtension" callExtension ["GetMapShapes", ["1", ""]];
if (_raw isEqualTo "" || {(_raw select [0, 3]) != "OK|"}) exitWith {};
private _json = _raw select [3, count _raw - 3];
private _seenIds = [];
private _idx = 0;
while {_idx >= 0} do {
    private _idPos = _json find "\"id\"", _idx;
    if (_idPos < 0) exitWith {};
    _idx = _idPos + 4;
    private _rest = _json select [_idx, 80];
    private _num = _rest call BIS_fnc_parseNumber;
    if (isNil "_num" || {_num <= 0}) then { _idx = _idPos + 1; continue; };
    private _idStr = str (round _num);
    _seenIds pushBack _idStr;
    private _type = "POINT";
    private _typePos = _json find "\"type\"", _idPos;
    if (_typePos >= 0 && _typePos < _idPos + 200) then {
        private _q = _json find "\"", _typePos + 7;
        if (_q >= 0) then {
            private _q2 = _json find "\"", _q + 1;
            if (_q2 > _q) then { _type = _json select [_q + 1, _q2 - _q - 1]; };
        };
    };
    private _label = "";
    private _labelPos = _json find "\"label\"", _idPos;
    if (_labelPos >= 0 && _labelPos < _idPos + 300) then {
        private _q = _json find "\"", _labelPos + 8;
        if (_q >= 0) then {
            private _q2 = _json find "\"", _q + 1;
            if (_q2 > _q) then { _label = _json select [_q + 1, _q2 - _q - 1]; };
        };
    };
    private _color = "ColorRed";
    private _colorPos = _json find "\"color\"", _idPos;
    if (_colorPos >= 0 && _colorPos < _idPos + 400) then {
        private _q = _json find "\"", _colorPos + 7;
        if (_q >= 0) then {
            private _q2 = _json find "\"", _q + 1;
            if (_q2 > _q) then { _color = _json select [_q + 1, _q2 - _q - 1]; };
        };
    };
    private _x = 0; private _y = 0; private _radius = 100;
    private _centerPos = _json find "\"center\"", _idPos;
    if (_centerPos >= 0 && _centerPos < _idPos + 500) then {
        private _bracket = _json find "[", _centerPos;
        if (_bracket >= 0) then {
            private _rest2 = _json select [_bracket + 1, 60];
            _x = (_rest2 call BIS_fnc_parseNumber);
            if (isNil "_x") then { _x = 0 };
            private _comma = _rest2 find ",";
            if (_comma >= 0) then {
                _y = ((_rest2 select [_comma + 1, 30]) call BIS_fnc_parseNumber);
                if (isNil "_y") then { _y = 0 };
            };
        };
    };
    private _radPos = _json find "\"radius\"", _idPos;
    if (_radPos >= 0 && _radPos < _idPos + 600) then {
        private _rest3 = _json select [_radPos + 8, 20];
        _radius = (_rest3 call BIS_fnc_parseNumber);
        if (isNil "_radius" || {_radius <= 0}) then { _radius = 100 };
    };
    [_num, _type, _label, _color, _x, _y, _radius] call comspec_overwatch_connect_fnc_receiveMapShape;
    _idx = _idPos + 1;
};

private _existing = missionNamespace getVariable ["COMSPEC_MapShapeMarkers", createHashMap];
private _allMarkerNames = keys _existing;
{
    private _markerName = _x;
    private _idPart = _markerName select [14, count _markerName - 14];
    if (!(_idPart in _seenIds)) then {
        _idPart call comspec_overwatch_connect_fnc_deleteMapShape;
    };
} forEach _allMarkerNames;
