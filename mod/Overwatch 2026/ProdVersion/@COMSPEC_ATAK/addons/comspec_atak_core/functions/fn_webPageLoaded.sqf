params [["_ctrl", controlNull]];

missionNamespace setVariable [
    "COMSPEC_ATAK_PageReady",
    true,
    false
];

[
    "INFO",
    "WEB",
    "Page phone.html chargee."
] call COMSPEC_fnc_log;

[] call COMSPEC_fnc_webPushState;
[] call COMSPEC_fnc_logPush;

true


[
    {
        [
            format [
                "if(window.COMSPEC_ATAK_liveMapShow){window.COMSPEC_ATAK_liveMapShow('%1',%2);}",
                [worldName] call COMSPEC_fnc_webJsEscape,
                worldSize
            ]
        ] call COMSPEC_fnc_webExecJS;
        if (missionNamespace getVariable ["COMSPEC_ATAK_MapVisible", false]) then
        {
            [] call COMSPEC_fnc_webMapShow;
        };
    },
    [],
    0.35
] call CBA_fnc_waitAndExecute;
