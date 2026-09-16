/*
  COMSPEC : calage BCE à ressort. Une largeur / hauteur nulle ou négative
  ferme Arma (AutoArray). On borne chaque cadre avant écriture moteur.
*/
params [["_ctrl", controlNull], "_animType", ["_position_Param", []], ["_ignore", []]];

if (isNull _ctrl) exitWith { false };
if (!(_position_Param isEqualType []) || {(count _position_Param) < 2}) exitWith { false };

private _fncDim = {
    params ["_v"];
    if (!(_v isEqualType 0) || {_v != _v} || {_v < 0.001}) exitWith { 0.001 };
    _v
};

private _fncPos4 = {
    params ["_p", "_fallback"];
    if (!(_p isEqualType [])) then { _p = []; };
    if (!(_fallback isEqualType []) || {(count _fallback) < 4}) then {
        _fallback = [0, 0, 0.1, 0.1];
    };
    private _x0 = _p param [0, _fallback param [0, 0]];
    private _y0 = _p param [1, _fallback param [1, 0]];
    private _w0 = _p param [2, _fallback param [2, 0.1]];
    private _h0 = _p param [3, _fallback param [3, 0.1]];
    if (!(_x0 isEqualType 0) || {_x0 != _x0}) then { _x0 = _fallback param [0, 0]; };
    if (!(_y0 isEqualType 0) || {_y0 != _y0}) then { _y0 = _fallback param [1, 0]; };
    [_x0, _y0, [_w0] call _fncDim, [_h0] call _fncDim]
};

private _cur = ctrlPosition _ctrl;
if (!(_cur isEqualType []) || {(count _cur) < 4}) then { _cur = [0, 0, 0.1, 0.1]; };

private _instant = _position_Param param [2, false];
private _startIn = _position_Param param [0, []];
private _endIn = _position_Param param [1, []];
private _fadePoint = if ((_endIn isEqualType []) && {(count _endIn) > 4}) then { _endIn param [4, -1] } else { -1 };
private _fadeOn = (_fadePoint isEqualType 0) && {_fadePoint > -1};

private _start = [_startIn, _cur] call _fncPos4;
private _end = [_endIn, _cur] call _fncPos4;
if (_fadeOn) then { _end set [4, _fadePoint]; };

if (_instant) exitWith {
    _ctrl ctrlSetPosition _end;
    if (_fadeOn) then { _ctrl ctrlSetFade _fadePoint; };
    _ctrl ctrlCommit 0;
    true
};

if (!(_startIn isEqualType []) || {(count _startIn) < 1}) then {
    _start = [_cur, _cur] call _fncPos4;
};

private _customStart = _ctrl getVariable ["Animation_StartWithOffset", []];
private _customEnd = _ctrl getVariable ["Animation_EndWithOffset", []];
if ((_customStart isEqualType []) && {(count _customStart) > 0}) then {
    {
        if (!(isNil "_x")) then { _start set [_forEachIndex, _x]; };
    } forEach _customStart;
    _start = [_start, _cur] call _fncPos4;
};
if ((_customEnd isEqualType []) && {(count _customEnd) > 0}) then {
    {
        if (!(isNil "_x")) then { _end set [_forEachIndex, _x]; };
    } forEach _customEnd;
    _end = [_end, _cur] call _fncPos4;
    if (_fadeOn) then { _end set [4, _fadePoint]; };
};

if (_fadeOn && {(count _start) < 5}) then {
    _start set [4, 1 - _fadePoint];
};

_position_Param set [0, _start];
_position_Param set [1, _end];

if (isNil "BCE_fnc_Anim_Init") exitWith { false };
private _params = _animType call BCE_fnc_Anim_Init;
if (isNil "_params") exitWith { false };

private _type = toLowerANSI (_params getOrDefault ["type", ""]);
private _animParams = _params getOrDefault ["params", createHashMap];
if (_type isEqualTo "") exitWith { false };

private _queue = (_ctrl getVariable ["Animation_Queue", []]) select { !isNull _x };
if ((count _queue) > 0) then {
    terminate (_queue select 0);
    _queue deleteAt 0;
};

if (_type isNotEqualTo "spring") exitWith { false };

private _actions = [
    [{ _ctrl ctrlSetPositionX _this }, 0],
    [{ _ctrl ctrlSetPositionY _this }, 1],
    [{
        private _w = _this;
        if (!(_w isEqualType 0) || {_w != _w} || {_w < 0.001}) then { _w = 0.001 };
        _ctrl ctrlSetPositionW _w;
    }, 2],
    [{
        private _h = _this;
        if (!(_h isEqualType 0) || {_h != _h} || {_h < 0.001}) then { _h = 0.001 };
        _ctrl ctrlSetPositionH _h;
    }, 3]
] select { !((_x select 1) in _ignore) };

if (_fadeOn) then {
    _actions pushBack [{ _ctrl ctrlSetFade _this }, 4];
};

private _bgIdc = _position_Param param [3, 0];
private _mass = _animParams getOrDefault ["mass", 1];
private _freqResp = _animParams getOrDefault ["frequencyResponse", 1];
private _damping = _animParams getOrDefault ["damping", 0.99];
private _duration = _animParams getOrDefault ["duration", 0.65];
private _frameRate = _animParams getOrDefault ["frameRate", 60];
private _initialPosition = _animParams getOrDefault ["initialPosition", -1];
private _initialVelocity = _animParams getOrDefault ["initialVelocity", 0];
if (!(_frameRate isEqualType 0) || {_frameRate <= 0}) then { _frameRate = 60 }; 
if (!(_duration isEqualType 0) || {_duration <= 0}) then { _duration = 0.65 };

private _handler = [_ctrl, _start, _end, _bgIdc, _actions, _mass, _freqResp, _damping, _duration, _frameRate, _initialPosition, _initialVelocity] spawn {
    params ["_ctrl", "_startPoint", "_endPoint", "_bgIdc", "_actions", "_mass", "_freqResp", "_dampingRatio", "_duration", "_frameRate", "_initialPosition", "_initialVelocity"];
    if (isNull _ctrl) exitWith {};

    private _arange = _duration * _frameRate;
    private _stiffness = (((2 * pi) / (_freqResp max 0.001)) ^ 2) * (_mass max 0.001);
    private _undamped = sqrt (_stiffness / (_mass max 0.001));
    private _damped = _undamped * sqrt (abs (1 - (_dampingRatio ^ 2)));
    private _a = _undamped * _dampingRatio;
    private _b = _damped max 0.0001;
    private _c = (_initialVelocity + _a * _initialPosition) / _b;
    private _d = _initialPosition;

    _ctrl setVariable ["Animation_EndWithOffset_F", _endPoint];

    private _bgPos = [];
    private _bgCtrl = controlNull;
    if ((_bgIdc isEqualType 0) && {_bgIdc > 0}) then {
        _bgCtrl = (ctrlParent _ctrl) displayCtrl _bgIdc;
        if (!isNull _bgCtrl) then { _bgPos = ctrlPosition _bgCtrl; };
    };

    for "_t" from 0 to (1.5 * _arange) step 1 do {
        sleep (1 / _frameRate);
        if (isNull _ctrl) exitWith {};

        private _result = exp (-_a * _t) * (_c * sin deg (_b * _t) + (_d * cos deg (_b * _t))) - _initialPosition;
        private _breakout = (_arange < _t) && {(_d + _result) < 0.00001};
        if (_breakout) then { _result = 1; };
        _result = (_result max 0) min 1;

        private _start = _startPoint;
        private _end = _endPoint;
        if (!isNull _bgCtrl) then {
            private _offset = (ctrlPosition _bgCtrl) vectorDiff _bgPos;
            _start = _startPoint vectorAdd _offset;
            _end = _endPoint vectorAdd _offset;
        };

        private _vecPos = [_start, _end, _result] call BIS_fnc_lerpVector;
        if (!(_vecPos isEqualType []) || {(count _vecPos) < 2}) then { continue };
        if ((count _vecPos) > 2) then {
            private _w = _vecPos select 2;
            if (!(_w isEqualType 0) || {_w != _w} || {_w < 0.001}) then { _vecPos set [2, 0.001]; };
        };
        if ((count _vecPos) > 3) then {
            private _h = _vecPos select 3;
            if (!(_h isEqualType 0) || {_h != _h} || {_h < 0.001}) then { _vecPos set [3, 0.001]; };
        };

        {
            (_vecPos select (_x select 1)) call (_x select 0);
        } count _actions;
        _ctrl ctrlCommit 0;

        if (_breakout) then { break };
    };

    if (!isNull _ctrl) then {
        {
            _ctrl call _x;
        } count (_ctrl getVariable ["Animation_CallBack_onEnd", []]);
        private _q = _ctrl getVariable ["Animation_Queue", []];
        if ((count _q) > 0) then { _q deleteAt 0; };
        _ctrl setVariable ["Animation_Queue", _q];
        _ctrl setVariable ["Animation_EndWithOffset_F", nil];
    };
};

_queue pushBack _handler;
_ctrl setVariable ["Animation_Queue", _queue];
true
