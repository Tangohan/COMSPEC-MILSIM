params [["_display", displayNull]];

if (!isNull _display) then {
    _display setVariable ["Iceman_TOC_refreshLoop", false];
    [_display] call Iceman_fnc_toc_viewDeviceClear;
};

uiNamespace setVariable ["Iceman_TOC_viewDeviceDisplay", nil];
