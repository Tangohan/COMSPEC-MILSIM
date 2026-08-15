params [["_target", objNull], ["_surfaceIndex", -1]];

if (isNull _target) exitWith {};

private _stopAll = _surfaceIndex < 0;
if (isNil "Iceman_TOC_vehicleCams" || {typeName Iceman_TOC_vehicleCams != "ARRAY"}) then {
    Iceman_TOC_vehicleCams = [];
};

private _cleanupStream = {
    params [["_slot", -1], ["_cam", objNull], ["_helper", objNull], ["_panel", objNull], ["_surfaceTexture", []], ["_renderTarget", ""]];

    if (isNil "Iceman_TOC_vehicleCams" || {typeName Iceman_TOC_vehicleCams != "ARRAY"}) then {
        Iceman_TOC_vehicleCams = [];
    };

    Iceman_TOC_vehicleCams = Iceman_TOC_vehicleCams select {
        private _entryTarget = _x # 0;
        private _entrySlot = if ((count _x) >= 6) then {_x # 1} else {-1};
        (_entryTarget != _target) || {_entrySlot != _slot}
    };

    if (!isNull _cam) then {
        if (_renderTarget != "") then {
            _cam cameraEffect ["TERMINATE", "BACK", _renderTarget];
        } else {
            _cam cameraEffect ["TERMINATE", "BACK"];
        };
        camDestroy _cam;
    };

    if (!isNull _helper) then {
        deleteVehicle _helper;
    };

    if (!isNull _panel) then {
        deleteVehicle _panel;
    };

    if !(_surfaceTexture isEqualTo []) then {
        _surfaceTexture params ["_surfaceTextureIndex", "_oldTexture", ["_oldMaterial", ""]];
        _target setObjectTexture [_surfaceTextureIndex, _oldTexture];
        _target setObjectMaterial [_surfaceTextureIndex, _oldMaterial];
    };
};

private _streams = _target getVariable ["Iceman_TOC_streamsLocal", []];
private _remaining = [];

if !(_streams isEqualTo []) then {
    {
        _x params [["_slot", -1], ["_cam", objNull], ["_helper", objNull], ["_panel", objNull], ["_surfaceTexture", []], ["_renderTarget", ""]];
        if (_stopAll || {_slot == _surfaceIndex}) then {
            [_slot, _cam, _helper, _panel, _surfaceTexture, _renderTarget] call _cleanupStream;
        } else {
            _remaining pushBack _x;
        };
    } forEach _streams;
} else {
    if (isNil "Iceman_TOC_vehicleCams" || {typeName Iceman_TOC_vehicleCams != "ARRAY"}) then {
        Iceman_TOC_vehicleCams = [];
    };
    Iceman_TOC_vehicleCams = Iceman_TOC_vehicleCams select {(_x # 0) != _target};

    private _cam = _target getVariable ["Iceman_TOC_cameraLocal", objNull];
    private _helper = _target getVariable ["Iceman_TOC_helperLocal", objNull];
    private _panel = _target getVariable ["Iceman_TOC_panelLocal", objNull];
    private _surfaceTexture = _target getVariable ["Iceman_TOC_surfaceTextureLocal", []];
    private _renderTarget = _target getVariable ["Iceman_TOC_renderTargetLocal", ""];
    [-1, _cam, _helper, _panel, _surfaceTexture, _renderTarget] call _cleanupStream;
};

if (_remaining isEqualTo []) then {
    _target setVariable ["Iceman_TOC_streamsLocal", nil];
    _target setVariable ["Iceman_TOC_cameraLocal", nil];
    _target setVariable ["Iceman_TOC_helperLocal", nil];
    _target setVariable ["Iceman_TOC_panelLocal", nil];
    _target setVariable ["Iceman_TOC_renderTargetLocal", nil];
    _target setVariable ["Iceman_TOC_surfaceTextureLocal", nil];
    [_target] call Iceman_fnc_toc_unregisterScreenLocal;
} else {
    private _last = _remaining # ((count _remaining) - 1);
    _last params ["_lastSlot", "_lastCam", "_lastHelper", "_lastPanel", "_lastSurfaceTexture", "_lastRenderTarget"];

    _target setVariable ["Iceman_TOC_streamsLocal", _remaining];
    _target setVariable ["Iceman_TOC_cameraLocal", _lastCam];
    _target setVariable ["Iceman_TOC_helperLocal", _lastHelper];
    _target setVariable ["Iceman_TOC_panelLocal", _lastPanel];
    _target setVariable ["Iceman_TOC_renderTargetLocal", _lastRenderTarget];
    _target setVariable ["Iceman_TOC_surfaceTextureLocal", _lastSurfaceTexture];
    [_target] call Iceman_fnc_toc_registerScreenLocal;
};
