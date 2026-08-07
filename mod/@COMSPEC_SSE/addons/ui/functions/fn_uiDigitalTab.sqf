params [
    ["_tab", "", [""]]
];
if (_tab != "") then {
    missionNamespace setVariable ["comspec_sse_uiDigitalTab", toLower _tab];
};
[] call comspec_sse_fnc_uiFillDigital;
true
