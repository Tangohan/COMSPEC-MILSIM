params [["_display", displayNull]];

if (isNull _display) exitWith {};

uiNamespace setVariable ["Iceman_TOC_viewDeviceDisplay", _display];
_display setVariable ["Iceman_TOC_dynamicControls", []];
_display setVariable ["Iceman_TOC_mode", "home"];
_display setVariable ["Iceman_TOC_lastSignature", ""];
_display setVariable ["Iceman_TOC_refreshLoop", true];

private _presenterState = missionNamespace getVariable ["Iceman_TOC_presenterState", [false, objNull, -1, "", "", 0]];
if (_presenterState param [0, false]) then {
    private _stream = [_presenterState param [1, objNull], _presenterState param [2, -1]] call Iceman_fnc_toc_findViewStream;
    if !(_stream isEqualTo []) then {
        _display setVariable ["Iceman_TOC_mode", "viewer"];
        _display setVariable ["Iceman_TOC_currentStream", _stream];
    };
};

[_display] call Iceman_fnc_toc_viewDeviceRefresh;

[_display] spawn {
    params ["_display"];

    while {!isNull _display && {_display getVariable ["Iceman_TOC_refreshLoop", false]}} do {
        uiSleep 2;

        if (!isNull _display && {(_display getVariable ["Iceman_TOC_mode", "home"]) == "home"}) then {
            private _streams = call Iceman_fnc_toc_getActiveViewStreams;
            private _signature = str (_streams apply {[_x # 1, _x # 2, _x # 4, _x # 8, _x # 9]});
            if (_signature != (_display getVariable ["Iceman_TOC_lastSignature", ""])) then {
                [_display] call Iceman_fnc_toc_viewDeviceRefresh;
            };
        };
    };
};
