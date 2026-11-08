/*
    Panneau digital (réutilise le dialogue résultat + métadonnées).
    [_fog, _target] call comspec_sse_fnc_openDigitalPanel
*/
params [
    ["_fog", createHashMap, [createHashMap]],
    ["_target", objNull, [objNull]]
];

// Écran numérique (fn_fillResultDialog), quel que soit le type du support.
_fog set ["screen", "digital"];
missionNamespace setVariable ["comspec_sse_lastDigitalResult", _fog];
missionNamespace setVariable ["comspec_sse_lastResult", _fog];

if (!isNil "comspec_sse_fnc_showResult") exitWith {
    [_fog, _target] call comspec_sse_fnc_showResult;
};

[(_fog getOrDefault ["title", "DIGITAL"]) + endl + ((_fog getOrDefault ["lines", []]) joinString endl), "INFO"] call comspec_sse_fnc_notify;
true
