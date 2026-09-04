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
        ["if(window.COMSPEC_ATAK_reportMapViewport){window.COMSPEC_ATAK_reportMapViewport();}"] call COMSPEC_fnc_webExecJS;
        if (missionNamespace getVariable ["COMSPEC_ATAK_MapVisible", false]) then
        {
            [] call COMSPEC_fnc_webMapRaise;
        };
    },
    [],
    0.35
] call CBA_fnc_waitAndExecute;
