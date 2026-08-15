params [["_target", objNull], ["_surfaceIndex", 0]];

if (isNull _target) exitWith {1};

private _zoomPairs = _target getVariable ["Iceman_TOC_zoomFactors", []];
private _idx = _zoomPairs findIf {(_x param [0, -1]) == _surfaceIndex};
if (_idx < 0) exitWith {1};

((_zoomPairs # _idx) param [1, 1]) max 1 min 10
