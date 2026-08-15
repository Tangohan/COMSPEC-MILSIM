params [["_target", objNull], ["_surfaceIndex", 0], ["_zoom", 1]];

if (isNull _target) exitWith {};

_zoom = (_zoom max 1) min 10;
_zoom = (round (_zoom * 100)) / 100;

private _zoomPairs = _target getVariable ["Iceman_TOC_zoomFactors", []];
_zoomPairs = _zoomPairs select {(_x param [0, -1]) != _surfaceIndex};
_zoomPairs pushBack [_surfaceIndex, _zoom];
_target setVariable ["Iceman_TOC_zoomFactors", _zoomPairs, true];

[_target, _surfaceIndex, _zoom] call Iceman_fnc_toc_applyZoomLocal;
["Iceman_TOC_zoom", [_target, _surfaceIndex, _zoom]] call CBA_fnc_globalEvent;
