private _screens = missionNamespace getVariable ["Iceman_TOC_activeScreensLocal", []];
private _cleanScreens = [];
private _activeStreams = [];

{
    private _target = _x;
    if (!isNull _target) then {
        private _streams = _target getVariable ["Iceman_TOC_streamsLocal", []];

        if !(_streams isEqualTo []) then {
            _cleanScreens pushBackUnique _target;

            {
                private _entry = _x;
                _entry params [
                    ["_slot", -1],
                    ["_cam", objNull],
                    ["_helper", objNull],
                    ["_panel", objNull],
                    ["_surfaceTexture", []],
                    ["_renderTarget", ""]
                ];

                if (!isNull _cam && {_renderTarget != ""}) then {
                    private _feed = _entry param [6, _target getVariable ["Iceman_TOC_feed", []]];
                    private _settings = [_entry param [7, _target getVariable ["Iceman_TOC_settings", []]]] call Iceman_fnc_toc_normalizeSettings;
                    private _texture = _entry param [8, ""];
                    if (_texture == "") then {
                        private _aspect = ((_settings # 3) / ((_settings # 4) max 0.05)) max 0.1 min 10;
                        _texture = format ["#(argb,1024,1024,1)r2t(%1,%2)", _renderTarget, _aspect];
                    };

                    private _label = format ["TOC Screen %1", _slot];
                    if !(_feed isEqualTo []) then {
                        _label = _feed param [0, _label];
                    };

                    private _zoom = [_target, _slot] call Iceman_fnc_toc_getZoomValue;
                    private _vision = [_target, _slot, _settings param [10, "normal"]] call Iceman_fnc_toc_getVisionValue;
                    _activeStreams pushBack [_label, _target, _slot, _cam, _renderTarget, _texture, _feed, _settings, _zoom, _vision];
                };
            } forEach _streams;
        };
    };
} forEach _screens;

missionNamespace setVariable ["Iceman_TOC_activeScreensLocal", _cleanScreens];
_activeStreams
