params [["_display", displayNull], ["_key", -1]];

if (isNull _display || {_key != 1}) exitWith {false};

private _mode = _display getVariable ["Iceman_TOC_mode", "home"];

if (_mode == "briefing") exitWith {
    _display setVariable ["Iceman_TOC_mode", "viewer"];
    [_display] call Iceman_fnc_toc_viewDeviceRefresh;
    true
};

if (_mode in ["viewer", "snapshots"]) exitWith {
    _display setVariable ["Iceman_TOC_mode", "home"];
    [_display] call Iceman_fnc_toc_viewDeviceRefresh;
    true
};

false
