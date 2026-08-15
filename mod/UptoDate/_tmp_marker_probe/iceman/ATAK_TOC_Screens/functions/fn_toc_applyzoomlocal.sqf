params [["_target", objNull], ["_surfaceIndex", 0], ["_zoom", 1]];

if (isNull _target) exitWith {};

_zoom = (_zoom max 1) min 10;

private _streams = _target getVariable ["Iceman_TOC_streamsLocal", []];
private _changed = false;

{
    _x params [
        ["_slot", -1],
        ["_cam", objNull],
        ["_helper", objNull],
        ["_panel", objNull],
        ["_surfaceTexture", []],
        ["_renderTarget", ""]
    ];

    if (_slot == _surfaceIndex && {!isNull _cam}) then {
        private _baseFov = _x param [9, 0.5];
        private _fov = (_baseFov / _zoom) max 0.01 min 1.2;

        _cam camSetFov _fov;
        _cam camCommit 0;
        _x set [10, _zoom];
        _changed = true;
    };
} forEach _streams;

if (_changed) then {
    _target setVariable ["Iceman_TOC_streamsLocal", _streams];
};
