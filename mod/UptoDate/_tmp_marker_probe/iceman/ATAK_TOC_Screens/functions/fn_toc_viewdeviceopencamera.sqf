params [["_display", displayNull], ["_stream", []]];

if (isNull _display || {_stream isEqualTo []}) exitWith {};

_display setVariable ["Iceman_TOC_mode", "viewer"];
_display setVariable ["Iceman_TOC_currentStream", _stream];
[_display] call Iceman_fnc_toc_viewDeviceRefresh;
