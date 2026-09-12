/*
    Trace 3D des itinéraires : points GPS Athena + points locaux ECOTI.
*/
private _camPos = positionCameraToWorld [0, 0, 0];
private _lineCol = [0.95, 0.82, 0.15, 0.85];
private _doneCol = [0.55, 0.55, 0.55, 0.45];

private _gpsPts = missionNamespace getVariable ["COMSPEC_GpsNavDrawPoints", []];
if (_gpsPts isEqualType [] && {(count _gpsPts) >= 1}) then {
    private _prev = [];
    {
        if (!(_x isEqualType []) || {(count _x) < 3}) then { continue };
        private _pos = [_x select 0, _x select 1, _x select 2];
        if ((_pos select 2) < 0.5) then {
            _pos set [2, (getTerrainHeightASL [_pos select 0, _pos select 1]) + 1.2];
            _pos = ASLToAGL _pos;
        };
        private _reached = if ((count _x) >= 4) then { _x select 3 } else { false };
        private _col = if (_reached) then { _doneCol } else { _lineCol };
        if ((count _prev) >= 3) then {
            drawLine3D [_prev, _pos, _col];
        };
        private _lbl = if ((count _x) >= 5) then { _x select 4 } else { "Point" };
        private _dist = _camPos distance _pos;
        [
            _pos,
            "\a3\ui_f\data\map\markers\military\pickup_CA.paa",
            _col,
            _lbl,
            _dist,
            0.65
        ] call comspec_overwatch_connect_fnc_ecotiDrawBadge;
        _prev = _pos;
    } forEach _gpsPts;
};

private _local = missionNamespace getVariable ["COMSPEC_EcotiRoutePoints", []];
if (!(_local isEqualType [])) then { _local = []; };
if ((count _local) >= 1) then {
    private _prevL = [];
    private _idx = 0;
    {
        if (!(_x isEqualType []) || {(count _x) < 3}) then { continue };
        _idx = _idx + 1;
        private _pos = ASLToAGL (AGLToASL _x);
        if ((count _prevL) >= 3) then {
            drawLine3D [_prevL, _pos, [0.35, 0.95, 0.75, 0.9]];
        };
        [
            _pos,
            "\a3\ui_f\data\map\markers\military\triangle_CA.paa",
            [0.35, 0.95, 0.75, 0.95],
            format ["Itinéraire %1", _idx],
            _camPos distance _pos,
            0.6
        ] call comspec_overwatch_connect_fnc_ecotiDrawBadge;
        _prevL = _pos;
    } forEach _local;
};
