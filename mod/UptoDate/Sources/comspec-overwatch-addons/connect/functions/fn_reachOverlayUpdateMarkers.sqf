/*
    Marqueurs locaux (carte vanilla / calque cTab) : dernière position + deux ellipses.
*/
private _sel = missionNamespace getVariable ["COMSPEC_ReachSelectedCs", ""];
private _row = missionNamespace getVariable ["COMSPEC_ReachSelectedRow", []];
private _rows = missionNamespace getVariable ["COMSPEC_ReachCache", []];
private _now = diag_tickTime;

private _seen = [];
if (_rows isEqualType []) then {
    {
        if (!(_x isEqualType []) || {(count _x) < 6}) then { continue };
        _x params ["_cs", "_gx", "_gy", ["_isSelf", false], ["_wx", 0], ["_wy", 0], ["_role", ""], ["_ageSec", 0], ["_status", "linked"], ["_stamp", _now]];
        if (_isSelf) then { continue };
        if ((abs _wx) < 1 && {(abs _wy) < 1}) then { continue };
        private _age = _ageSec;
        if (_stamp > 0) then { _age = _ageSec + (_now - _stamp); };
        private _st = toLower _status;
        if (_st in ["linked"]) then { continue };
        if (_age > 900) then { continue };
        private _safe = (_cs splitString " ") joinString "_";
        if ((count _safe) > 24) then { _safe = _safe select [0, 24]; };
        private _name = format ["COMSPEC_LK_%1", _safe];
        _seen pushBack _name;
        if (markerType _name isEqualTo "") then {
            createMarkerLocal [_name, [_wx, _wy]];
            _name setMarkerTypeLocal "mil_dot";
            _name setMarkerColorLocal "ColorWhite";
            _name setMarkerSizeLocal [0.7, 0.7];
            _name setMarkerAlphaLocal 0.75;
        } else {
            _name setMarkerPosLocal [_wx, _wy];
        };
        _name setMarkerTextLocal _cs;
    } forEach _rows;
};

{
    if ((_x find "COMSPEC_LK_") == 0 && {!(_x in _seen)}) then {
        deleteMarkerLocal _x;
    };
} forEach allMapMarkers;

private _mkFoot = "COMSPEC_REACH_FOOT";
private _mkVeh = "COMSPEC_REACH_VEH";
private _mkDot = "COMSPEC_REACH_DOT";

if (!(_sel isEqualType "") || {_sel isEqualTo ""} || {(count _row) < 6}) exitWith {
    { if !(markerType _x isEqualTo "") then { deleteMarkerLocal _x }; } forEach [_mkFoot, _mkVeh, _mkDot];
};

_row params ["_cs", "_gx", "_gy", ["_isSelf", false], ["_wx", 0], ["_wy", 0], ["_role", ""], ["_ageSec", 0], ["_status", "linked"], ["_stamp", _now]];
if ((abs _wx) < 1 && {(abs _wy) < 1}) exitWith {};

private _age = _ageSec;
if (_stamp > 0) then { _age = _ageSec + (_now - _stamp); };
_age = (_age max 0) min 900;
private _footR = ((5 / 3.6) * _age) max 25;
private _vehR = ((40 / 3.6) * _age) max 25;
_footR = _footR min ((5 / 3.6) * 900);
_vehR = _vehR min ((40 / 3.6) * 900);

if (markerType _mkVeh isEqualTo "") then {
    createMarkerLocal [_mkVeh, [_wx, _wy]];
    _mkVeh setMarkerShapeLocal "ELLIPSE";
    _mkVeh setMarkerColorLocal "ColorYellow";
    _mkVeh setMarkerBrushLocal "Border";
    _mkVeh setMarkerAlphaLocal 0.7;
} else {
    _mkVeh setMarkerPosLocal [_wx, _wy];
};
_mkVeh setMarkerSizeLocal [_vehR, _vehR];

if (markerType _mkFoot isEqualTo "") then {
    createMarkerLocal [_mkFoot, [_wx, _wy]];
    _mkFoot setMarkerShapeLocal "ELLIPSE";
    _mkFoot setMarkerColorLocal "ColorGreen";
    _mkFoot setMarkerBrushLocal "Border";
    _mkFoot setMarkerAlphaLocal 0.85;
} else {
    _mkFoot setMarkerPosLocal [_wx, _wy];
};
_mkFoot setMarkerSizeLocal [_footR, _footR];

if (markerType _mkDot isEqualTo "") then {
    createMarkerLocal [_mkDot, [_wx, _wy]];
    _mkDot setMarkerTypeLocal "mil_dot";
    _mkDot setMarkerColorLocal "ColorGreen";
    _mkDot setMarkerSizeLocal [0.9, 0.9];
} else {
    _mkDot setMarkerPosLocal [_wx, _wy];
};
_mkDot setMarkerTextLocal format ["%1 — dernière position", _cs];
