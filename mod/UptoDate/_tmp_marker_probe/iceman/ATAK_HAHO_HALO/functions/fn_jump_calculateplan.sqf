#include "..\script_component.hpp"

params ["_points", ["_mode", "HAHO"]];

private _baseCanopyMS = 30 / 3.6;
private _canopyDescentMS = 3.5;
private _safetyFactor = 1.2;
private _landingReserveM = 75;
private _hahoPullDelayS = 3.5;
private _freefallMS = 50;
private _haloPullAGL = 300;
private _ftToM = 0.3048;

private _segments = [];
private _distance = 0;
private _canopyTime = 0;
private _weightedSpeed = 0;

for "_i" from 1 to ((count _points) - 1) do {
    private _a = _points # (_i - 1);
    private _b = _points # _i;
    private _dist = _a distance2D _b;
    if (_dist < 1) then {continue};

    private _bearing = _a getDir _b;
    private _groundSpeed = _baseCanopyMS;
    private _time = _dist / _groundSpeed;

    _segments pushBack [_a, _b, _dist, _bearing, _groundSpeed, _time];
    _distance = _distance + _dist;
    _canopyTime = _canopyTime + _time;
    _weightedSpeed = _weightedSpeed + (_groundSpeed * _dist);
};

private _avgGroundSpeedKph = if (_distance > 0) then {
    ((_weightedSpeed / _distance) * 3.6)
} else {
    30
};

private _requiredPullAGL = (_canopyTime * _canopyDescentMS * _safetyFactor) + _landingReserveM;
private _hahoFreefallLossM = _hahoPullDelayS * _freefallMS;
private _requiredExitAGL = if (_mode == "HAHO") then {
    _requiredPullAGL + _hahoFreefallLossM
} else {
    _haloPullAGL
};

private _warnings = [];
if (_mode == "HALO" && {_requiredPullAGL > _haloPullAGL}) then {
    _warnings pushBack format ["300m pull is short by %1m. Pull around %2m AGL or move the jump point closer.", ceil (_requiredPullAGL - _haloPullAGL), ceil _requiredPullAGL];
};

private _pointForTimeFromDZ = {
    params ["_timeAvailable"];

    private _remaining = _timeAvailable;
    private _found = [];

    for "_i" from ((count _segments) - 1) to 0 step -1 do {
        private _seg = _segments # _i;
        _seg params ["_a", "_b", "_dist", "_bearing", "_speed", "_segTime"];
        if (_remaining <= _segTime) exitWith {
            private _backDist = (_remaining * _speed) min _dist;
            private _ratio = if (_dist > 0) then {_backDist / _dist} else {0};
            _found = [
                (_b # 0) + (((_a # 0) - (_b # 0)) * _ratio),
                (_b # 1) + (((_a # 1) - (_b # 1)) * _ratio),
                0
            ];
        };
        _remaining = _remaining - _segTime;
    };

    _found
};

private _ticks = [];
if !(_segments isEqualTo []) then {
    if (_mode == "HAHO") then {
        private _maxTickFt = ceil ((_requiredExitAGL / _ftToM) / 1000) * 1000;
        for "_altFt" from 1000 to _maxTickFt step 1000 do {
            private _altM = _altFt * _ftToM;
            private _availableTime = ((_altM - _hahoFreefallLossM - _landingReserveM) max 0) / (_canopyDescentMS * _safetyFactor);
            if (_availableTime > 0 && {_availableTime <= _canopyTime}) then {
                private _pos = [_availableTime] call _pointForTimeFromDZ;
                if !(_pos isEqualTo []) then {
                    _ticks pushBack [_pos, format ["EXIT %1K", round (_altFt / 1000)], [0.2, 0.9, 1, 0.95]];
                };
            };
        };
    } else {
        private _fixedPullTime = ((_haloPullAGL - _landingReserveM) max 0) / (_canopyDescentMS * _safetyFactor);
        if (_fixedPullTime > 0 && {_fixedPullTime <= _canopyTime}) then {
            private _pullPos = [_fixedPullTime] call _pointForTimeFromDZ;
            if !(_pullPos isEqualTo []) then {
                _ticks pushBack [_pullPos, "300M PULL", [1, 0.45, 0.05, 0.95]];
            };
        };

        private _maxPullFt = ceil ((_requiredPullAGL / _ftToM) / 1000) * 1000;
        for "_altFt" from 1000 to _maxPullFt step 1000 do {
            private _altM = _altFt * _ftToM;
            private _availableTime = ((_altM - _landingReserveM) max 0) / (_canopyDescentMS * _safetyFactor);
            if (_availableTime > 0 && {_availableTime <= _canopyTime}) then {
                private _pos = [_availableTime] call _pointForTimeFromDZ;
                if !(_pos isEqualTo []) then {
                    _ticks pushBack [_pos, format ["PULL %1K", round (_altFt / 1000)], [1, 0.8, 0.1, 0.9]];
                };
            };
        };
    };
};

[
    _segments,
    _ticks,
    _distance,
    _canopyTime,
    _requiredExitAGL,
    _requiredPullAGL,
    _avgGroundSpeedKph,
    _warnings
]
