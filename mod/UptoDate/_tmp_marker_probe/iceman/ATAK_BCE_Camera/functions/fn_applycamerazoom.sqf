params [["_direction", 0]];

private _cam = objNull;
private _uavIndex = -1;
private _isHelmet = false;

private _entryHasRenderTarget = {
    params [["_entry", []]];
    if !(_entry isEqualType []) exitWith {false};
    (_entry findIf {_x isEqualType "" && {_x isEqualTo "rendertarget9"}}) >= 0
};

private _entryCamera = {
    params [["_entry", []]];
    private _camObj = objNull;
    if !(_entry isEqualType []) exitWith {_camObj};

    private _i = 1;
    while {_i < count _entry && {isNull _camObj}} do {
        private _camCandidate = _entry # _i;
        if (_camCandidate isEqualType objNull && {!isNull _camCandidate}) then {
            _camObj = _camCandidate;
        };
        _i = _i + 1;
    };
    _camObj
};

if (!isNil "cTabUAVcams" && {cTabUAVcams isEqualType []}) then {
    _uavIndex = cTabUAVcams findIf {[_x] call _entryHasRenderTarget};
    if (_uavIndex >= 0) then {
        _cam = [cTabUAVcams # _uavIndex] call _entryCamera;
    };

    if (isNull _cam) then {
        _uavIndex = cTabUAVcams findIf {
            !isNull ([_x] call _entryCamera)
        };
        if (_uavIndex >= 0) then {
            _cam = [cTabUAVcams # _uavIndex] call _entryCamera;
        };
    };
};

if (isNull _cam && {!isNil "cTabHcams"} && {cTabHcams isEqualType []} && {(count cTabHcams) > 0}) then {
    private _helmetCam = cTabHcams param [0, objNull];
    if (_helmetCam isEqualType objNull && {!isNull _helmetCam}) then {
        _cam = _helmetCam;
        _isHelmet = true;
    };
};

if (isNull _cam) exitWith {0};

private _key = ["Iceman_ATAK_UAVCameraFov", "Iceman_ATAK_HelmetCameraFov"] select _isHelmet;
private _defaultFov = [0.1, 0.7] select _isHelmet;
private _fov = uiNamespace getVariable [_key, (_cam getVariable [_key, _defaultFov])];

if (_direction > 0) then {
    _fov = _fov / 1.25;
};
if (_direction < 0) then {
    _fov = _fov * 1.25;
};

_fov = (_fov max 0.01) min 1.2;

uiNamespace setVariable [_key, _fov];
_cam setVariable [_key, _fov];
_cam camSetFov _fov;
_cam camCommit 0;
_cam camPrepareFov _fov;
_cam camCommitPrepared 0;

if (!_isHelmet && {_uavIndex >= 0}) then {
    private _entry = cTabUAVcams # _uavIndex;
    _entry set [5, _fov];
    cTabUAVcams set [_uavIndex, _entry];
    localNamespace setVariable ["TGP_View_Camera_FOV", _fov];
    uiNamespace setVariable ["cTab_Sync_CameraView", false];
};

round ((_defaultFov / _fov) * 10) / 10
