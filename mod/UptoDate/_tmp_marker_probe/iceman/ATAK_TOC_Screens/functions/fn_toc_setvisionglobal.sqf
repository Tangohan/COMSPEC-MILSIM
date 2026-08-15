params [["_target", objNull], ["_surfaceIndex", 0], ["_vision", "normal"]];

if (isNull _target) exitWith {};

private _valid = ["normal", "nv", "thermal", "thermal_whot", "thermal_bhot", "a3ti_whot", "a3ti_bhot", "a3ti_current"];
_vision = toLower _vision;
if !(_vision in _valid) then {
    _vision = "normal";
};

private _visionPairs = _target getVariable ["Iceman_TOC_visionModes", []];
_visionPairs = _visionPairs select {(_x param [0, -1]) != _surfaceIndex};
_visionPairs pushBack [_surfaceIndex, _vision];
_target setVariable ["Iceman_TOC_visionModes", _visionPairs, true];

private _streams = _target getVariable ["Iceman_TOC_streamsGlobal", []];
{
    if ((_x param [0, -1]) == _surfaceIndex) then {
        private _settings = [_x param [2, []]] call Iceman_fnc_toc_normalizeSettings;
        _settings set [10, _vision];
        _x set [2, _settings];
    };
} forEach _streams;
_target setVariable ["Iceman_TOC_streamsGlobal", _streams, true];

private _settings = [_target getVariable ["Iceman_TOC_settings", []]] call Iceman_fnc_toc_normalizeSettings;
if ((_settings param [9, 0]) == _surfaceIndex) then {
    _settings set [10, _vision];
    _target setVariable ["Iceman_TOC_settings", _settings, true];
};

[_target, _surfaceIndex, _vision] call Iceman_fnc_toc_applyVisionLocal;
["Iceman_TOC_vision", [_target, _surfaceIndex, _vision]] call CBA_fnc_globalEvent;
