/*
    Dessine sur une carte (Draw) : dernière position connue + zones à pied / véhicule.
    Params : [_map]
*/
params [["_map", controlNull]];
if (isNull _map) exitWith {};

private _rows = missionNamespace getVariable ["COMSPEC_ReachCache", []];
if (!(_rows isEqualType [])) exitWith {};

private _sel = missionNamespace getVariable ["COMSPEC_ReachSelectedCs", ""];
if (!(_sel isEqualType "")) then { _sel = ""; };
private _selKey = toLower _sel;

private _now = diag_tickTime;
private _maxAge = 900;
private _minR = 25;
private _footKph = 5;
private _vehKph = 40;

private _radius = {
    params ["_kph", "_age"];
    private _a = (_age max 0) min 900;
    private _r = (_kph / 3.6) * _a;
    private _cap = (_kph / 3.6) * 900;
    if (_r < 25) then { 25 } else { _r min _cap }
};

{
    if (!(_x isEqualType []) || {(count _x) < 6}) then { continue };
    _x params ["_cs", "_gx", "_gy", ["_isSelf", false], ["_wx", 0], ["_wy", 0], ["_role", ""], ["_ageSec", 0], ["_status", "linked"], ["_stamp", _now]];
    if (_isSelf) then { continue };
    if ((abs _wx) < 1 && {(abs _wy) < 1}) then { continue };
    private _age = _ageSec;
    if (_stamp > 0) then { _age = _ageSec + (_now - _stamp); };
    if (_age > _maxAge && {!((toLower _status) in ["linked"])}) then { continue };

    private _pos = [_wx, _wy];
    private _st = toLower _status;
    private _isOff = !(_st in ["linked"]);
    private _isSel = (toLower _cs) isEqualTo _selKey && {_selKey isNotEqualTo ""};

    if (_isOff || {_isSel}) then {
        private _col = if (_isOff) then { [0.72, 0.78, 0.84, 0.92] } else { [0.2, 0.85, 0.45, 1] };
        _map drawIcon [
            "\A3\ui_f\data\map\markers\military\circle_CA.paa",
            _col,
            _pos,
            18,
            18,
            0,
            _cs,
            1,
            0.028,
            "RobotoCondensedBold",
            "right"
        ];
    };

    if (!_isSel) then { continue };

    private _footR = [_footKph, _age] call _radius;
    private _vehR = [_vehKph, _age] call _radius;
    _map drawEllipse [_pos, _vehR, _vehR, 0, [0.96, 0.62, 0.07, 0.55]];
    _map drawEllipse [_pos, _footR, _footR, 0, [0.29, 0.87, 0.5, 0.85]];
} forEach _rows;
