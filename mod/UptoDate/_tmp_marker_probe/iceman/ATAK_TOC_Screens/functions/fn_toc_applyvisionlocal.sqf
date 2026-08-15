params [["_target", objNull], ["_surfaceIndex", 0], ["_vision", "normal"]];

if (isNull _target) exitWith {};

private _valid = ["normal", "nv", "thermal", "thermal_whot", "thermal_bhot", "a3ti_whot", "a3ti_bhot", "a3ti_current"];
_vision = toLower _vision;
if !(_vision in _valid) then {
    _vision = "normal";
};

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

    if (_slot == _surfaceIndex && {_renderTarget != ""}) then {
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

        private _settings = [_x param [7, _target getVariable ["Iceman_TOC_settings", []]]] call Iceman_fnc_toc_normalizeSettings;
        _settings set [10, _vision];
        _x set [7, _settings];
        _changed = true;
    };
} forEach _streams;

if (_changed) then {
    _target setVariable ["Iceman_TOC_streamsLocal", _streams];
};
