/*
    Dessine les pulses Super ping (anneaux qui s’agrandissent).
    Params : [_map]
*/
params [["_map", controlNull]];
if (isNull _map) exitWith {};

private _pulses = missionNamespace getVariable ["COMSPEC_SuperPingPulses", []];
if (!(_pulses isEqualType []) || {(count _pulses) == 0}) exitWith {};

private _now = diag_tickTime;
private _life = 7;
private _keep = [];
{
    if (!(_x isEqualType []) || {(count _x) < 4}) then { continue };
    _x params ["_pid", "_wx", "_wy", "_start"];
    private _age = _now - _start;
    if (_age < 0 || {_age > _life}) then { continue };
    _keep pushBack _x;
    private _t = (_age / _life) min 1;
    private _ease = 1 - ((1 - _t) ^ 1.35);
    private _pos = [_wx, _wy];
    private _a = (1 - _t) max 0.08;
    private _r1 = 30 + _ease * 420;
    private _r2 = 18 + _ease * 260;
    private _r3 = 10 + _ease * 120;
    _map drawEllipse [_pos, _r1, _r1, 0, [0.22, 0.83, 0.95, _a * 0.55]];
    _map drawEllipse [_pos, _r2, _r2, 0, [0.98, 0.8, 0.13, _a * 0.75]];
    _map drawEllipse [_pos, _r3, _r3, 0, [0.88, 0.97, 1, _a]];
    _map drawIcon [
        "\A3\ui_f\data\map\markers\military\circle_CA.paa",
        [0.13, 0.85, 0.95, (0.45 + _a * 0.55) min 1],
        _pos,
        18,
        18,
        0,
        "SUPER PING",
        1,
        0.03,
        "RobotoCondensedBold",
        "right"
    ];
} forEach _pulses;

if ((count _keep) != (count _pulses)) then {
    missionNamespace setVariable ["COMSPEC_SuperPingPulses", _keep, false];
};
