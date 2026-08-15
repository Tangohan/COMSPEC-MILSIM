params [["_enabled", false], ["_target", objNull], ["_surfaceIndex", 0], ["_presenter", ""], ["_label", ""], ["_time", 0]];

missionNamespace setVariable ["Iceman_TOC_presenterState", [_enabled, _target, _surfaceIndex, _presenter, _label, _time]];

private _display = uiNamespace getVariable ["Iceman_TOC_viewDeviceDisplay", displayNull];
private _hasDevice = "Iceman_TOC_ViewDevice" in ((items player) + (assignedItems player));

if (!_enabled) exitWith {
    if (!isNull _display) then {
        [_display] call Iceman_fnc_toc_viewDeviceRefresh;
    };
};

if (isNull _target || {!_hasDevice}) exitWith {};

[_target, _surfaceIndex, _display] spawn {
    params ["_target", "_surfaceIndex", "_display"];

    if (isNull _display) then {
        createDialog "Iceman_TOC_ViewDeviceDialog";
        uiSleep 0.1;
        _display = uiNamespace getVariable ["Iceman_TOC_viewDeviceDisplay", displayNull];
    };

    if (isNull _display) exitWith {};

    private _stream = [_target, _surfaceIndex] call Iceman_fnc_toc_findViewStream;
    if !(_stream isEqualTo []) then {
        _display setVariable ["Iceman_TOC_mode", "viewer"];
        _display setVariable ["Iceman_TOC_currentStream", _stream];
    };

    [_display] call Iceman_fnc_toc_viewDeviceRefresh;
};
