params [["_target", objNull], ["_feed", []], ["_settings", []]];

if (isNull _target || {_feed isEqualTo []}) exitWith {};

_settings = [_settings] call Iceman_fnc_toc_normalizeSettings;
private _surfaceIndex = _settings param [9, 0];
[_target, _surfaceIndex] call Iceman_fnc_toc_stopStream;

_feed params ["_label", "_type", "_data", "_seat"];
private _feedObject = [_type, _data] call Iceman_fnc_toc_findFeedObject;
if (isNull _feedObject || {!alive _feedObject}) exitWith {};

private _renderTarget = format ["iceman_toc_%1", Iceman_TOC_nextRenderTarget];
Iceman_TOC_nextRenderTarget = Iceman_TOC_nextRenderTarget + 1;
_target setVariable ["Iceman_TOC_renderTargetLocal", _renderTarget];

private _mode = _settings param [8, "surface"];
private _vision = _settings param [10, "normal"];
_vision = [_target, _surfaceIndex, _vision] call Iceman_fnc_toc_getVisionValue;
_settings set [10, _vision];
private _aspect = ((_settings # 3) / ((_settings # 4) max 0.05)) max 0.1 min 10;
private _textureWidth = [1024, 2048] select (_mode == "surface");
private _textureHeight = [1024, 2048] select (_mode == "surface");
private _texture = format ["#(argb,%1,%2,1)r2t(%3,%4)", _textureWidth, _textureHeight, _renderTarget, _aspect];

private _cam = objNull;
private _helper = objNull;
private _baseFov = 0.5;

switch (_type) do {
    case "helmet": {
        private _host = _feedObject;
        private _camOffset = [0.12,0,0.15];
        private _targetOffset = [0,8,1];

        if !(vehicle _host isKindOf "CAManBase") then {
            if (difficultyEnabled "3rdPersonView") then {
                _host = vehicle _host;
                _camOffset = [0,-8,4];
                _targetOffset = [0,8,2];
            } else {
                _host = objNull;
            };
        };

        if (!isNull _host) then {
            _helper = "Sign_Sphere10cm_F" createVehicleLocal getPosATL _host;
            hideObject _helper;
            _helper attachTo [_host, _targetOffset];

            _cam = "camera" camCreate getPosATL _host;
            _baseFov = 0.7;
            _cam camPrepareFov _baseFov;
            _cam camPrepareTarget _helper;
            _cam camCommitPrepared 0;

            if (vehicle _host == _host) then {
                _cam attachTo [_host, _camOffset, "Head"];
            } else {
                _cam attachTo [_host, _camOffset];
            };

            _cam cameraEffect ["INTERNAL", "BACK", _renderTarget];
        };
    };
    case "vehicle": {
        private _vehicle = _feedObject;
        private _cfg = configFile >> "CfgVehicles" >> typeOf _vehicle;
        private _seatName = ["Driver", "Gunner"] param [_seat, "Driver"];
        private _posMem = getText (_cfg >> ("uavCamera" + _seatName + "Pos"));
        private _dirMem = getText (_cfg >> ("uavCamera" + _seatName + "Dir"));

        _cam = "camera" camCreate [0,0,0];

        if (_posMem != "" && {_dirMem != ""}) then {
            _cam attachTo [_vehicle, [0,0,0], _posMem];
            if (isNil "Iceman_TOC_vehicleCams" || {typeName Iceman_TOC_vehicleCams != "ARRAY"}) then {
                Iceman_TOC_vehicleCams = [];
            };
            Iceman_TOC_vehicleCams pushBack [_target, _surfaceIndex, _vehicle, _cam, _posMem, _dirMem];
        } else {
            _helper = "Sign_Sphere10cm_F" createVehicleLocal getPosATL _vehicle;
            hideObject _helper;
            _helper attachTo [_vehicle, [0,80,0]];
            _cam attachTo [_vehicle, [0,4,1.5]];
            _cam camPrepareTarget _helper;
            _cam camPrepareFov 0.5;
            _cam camCommitPrepared 0;
        };

        _baseFov = [0.5, 0.1] select (_seat == 1);
        _cam camSetFov _baseFov;

        _cam cameraEffect ["INTERNAL", "BACK", _renderTarget];
        _cam camCommit 0;
    };
};

if (isNull _cam) exitWith {
    if (!isNull _helper) then {
        deleteVehicle _helper;
    };
};

private _zoom = [_target, _surfaceIndex] call Iceman_fnc_toc_getZoomValue;
_cam camSetFov ((_baseFov / _zoom) max 0.01 min 1.2);
_cam camCommit 0;

private _pipEffect = switch (true) do {
    case (_vision == "nv"): {1};
    case (_vision in ["thermal", "thermal_whot", "a3ti_whot"]): {2};
    case (_vision in ["thermal_bhot", "a3ti_bhot"]): {7};
    case (_vision == "a3ti_current"): {
        private _a3ti = missionNamespace getVariable ["A3TI_FLIR_VisionMode", -1];
        private _bceVision = player getVariable ["TGP_View_Optic_Mode", -1];
        switch (true) do {
            case (_bceVision == 5 || {_a3ti in [0, -2]}): {2};
            case (_bceVision == 4 || {_a3ti in [1, -3]}): {7};
            case (_bceVision == 3): {1};
            default {0};
        };
    };
    default {0};
};
_renderTarget setPiPEffect [_pipEffect];
[_renderTarget, _pipEffect] spawn {
    params ["_renderTarget", "_pipEffect"];
    uiSleep 0.1;
    _renderTarget setPiPEffect [_pipEffect];
};

private _panel = objNull;
private _surfaceTexture = [];

if (_mode == "surface") then {
    private _oldTexture = (getObjectTextures _target) param [_surfaceIndex, ""];
    private _oldMaterial = (getObjectMaterials _target) param [_surfaceIndex, ""];

    _surfaceTexture = [_surfaceIndex, _oldTexture, _oldMaterial];
    _target setVariable ["Iceman_TOC_surfaceTextureLocal", _surfaceTexture];
    _target setObjectTexture [_surfaceIndex, _texture];
    _target setObjectMaterial [_surfaceIndex, ""];
} else {
    _panel = "UserTexture1m_F" createVehicleLocal [0,0,0];
    _panel allowDamage false;
    _panel enableSimulation false;
    _panel hideObject false;
    [_target, _panel, _settings] call Iceman_fnc_toc_applyTransform;
    _panel setObjectTexture [0, _texture];
    _target setVariable ["Iceman_TOC_panelLocal", _panel];
};

private _streams = _target getVariable ["Iceman_TOC_streamsLocal", []];
_streams pushBack [_surfaceIndex, _cam, _helper, _panel, _surfaceTexture, _renderTarget, _feed, _settings, _texture, _baseFov, _zoom];
_target setVariable ["Iceman_TOC_streamsLocal", _streams];
[_target] call Iceman_fnc_toc_registerScreenLocal;

_target setVariable ["Iceman_TOC_cameraLocal", _cam];
_target setVariable ["Iceman_TOC_helperLocal", _helper];
_target setVariable ["Iceman_TOC_settings", _settings];
_target setVariable ["Iceman_TOC_feed", _feed];
