/*
    onLoad dispatcher.
    [_screen] call comspec_sse_fnc_uiOnLoad
*/
params [
    ["_screen", "terminal", [""]]
];

missionNamespace setVariable ["comspec_sse_uiScreen", _screen];
missionNamespace setVariable ["comspec_sse_uiDigitalTab", "overview"];
missionNamespace setVariable ["comspec_sse_uiMissionFilter", "ALL"];

[_screen] call comspec_sse_fnc_uiRefresh;
true
