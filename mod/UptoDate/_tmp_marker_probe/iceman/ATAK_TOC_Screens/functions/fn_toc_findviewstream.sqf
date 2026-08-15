params [["_target", objNull], ["_surfaceIndex", 0]];

private _streams = call Iceman_fnc_toc_getActiveViewStreams;
private _idx = _streams findIf {
    (_x param [1, objNull]) == _target && {(_x param [2, -1]) == _surfaceIndex}
};

if (_idx < 0) exitWith {[]};
_streams # _idx
