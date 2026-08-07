params [
    ["_filter", "ALL", [""]]
];
missionNamespace setVariable ["comspec_sse_uiMissionFilter", toUpper _filter];
[] call comspec_sse_fnc_uiFillMission;
true
