params [["_target", objNull], ["_surfaceIndex", 0], ["_fallback", "normal"]];

private _valid = ["normal", "nv", "thermal", "thermal_whot", "thermal_bhot", "a3ti_whot", "a3ti_bhot", "a3ti_current"];
_fallback = toLower _fallback;
if !(_fallback in _valid) then {
    _fallback = "normal";
};

if (isNull _target) exitWith {_fallback};

private _visionPairs = _target getVariable ["Iceman_TOC_visionModes", []];
private _idx = _visionPairs findIf {(_x param [0, -1]) == _surfaceIndex};
if (_idx < 0) exitWith {_fallback};

private _vision = toLower ((_visionPairs # _idx) param [1, _fallback]);
if !(_vision in _valid) then {
    _vision = _fallback;
};

_vision
