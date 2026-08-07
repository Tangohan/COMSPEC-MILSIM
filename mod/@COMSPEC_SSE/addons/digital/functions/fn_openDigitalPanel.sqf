/*
    Panneau digital (réutilise le dialogue résultat + métadonnées).
    [_fog] call comspec_sse_fnc_openDigitalPanel
*/
params [
    ["_fog", createHashMap, [createHashMap]]
];

missionNamespace setVariable ["comspec_sse_lastDigitalResult", _fog];
missionNamespace setVariable ["comspec_sse_lastResult", _fog];

if (!isNil "comspec_sse_fnc_showResult") exitWith {
    [_fog] call comspec_sse_fnc_showResult;
};

hint ((_fog getOrDefault ["title", "DIGITAL"]) + endl + ((_fog getOrDefault ["lines", []]) joinString endl));
true
