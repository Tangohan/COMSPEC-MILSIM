/*
    Diffuse l'état Google Briefing à tous les clients (CBA JIP).
    Params: [_command, _url, _index]
      command: "show" | "step" | "end"
*/
params [
    ["_command", "show", [""]],
    ["_url", "", [""]],
    ["_index", 0, [0]]
];

if (isNil "CBA_fnc_globalEventJIP") exitWith {
    // Fallback local uniquement
    [_command, _url, _index, 0] call comspec_overwatch_connect_fnc_handleGoogleBriefingState;
    false
};

private _revision = (missionNamespace getVariable ["COMSPEC_GoogleBriefingRevision", 0]) + 1;
missionNamespace setVariable ["COMSPEC_GoogleBriefingRevision", _revision];

[
    "COMSPEC_GoogleBriefingState",
    [toLowerANSI _command, _url, floor _index, _revision],
    "COMSPEC_GoogleBriefingJIP"
] call CBA_fnc_globalEventJIP;

true
