private _display = uiNamespace getVariable ["COMSPEC_ATAK_Display", displayNull];
if (isNull _display) exitWith {false};

[
    format [
        "if(window.COMSPEC_ATAK_liveMapShow){window.COMSPEC_ATAK_liveMapShow('%1',%2);}",
        [worldName] call COMSPEC_fnc_webJsEscape,
        worldSize
    ]
] call COMSPEC_fnc_webExecJS;

true
