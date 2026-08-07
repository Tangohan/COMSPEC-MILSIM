/*
    Action depuis le terminal SEEK.
    [_kind] call comspec_sse_fnc_seekCapture
*/
params [
    ["_kind", "fingerprint", [""]]
];

private _target = missionNamespace getVariable ["comspec_sse_seekTarget", objNull];
if (isNull _target) exitWith { hint "Aucune cible SEEK."; false };

switch (toLower _kind) do {
    case "fingerprint": { [_target, player] call comspec_sse_fnc_captureFingerprint; };
    case "iris": { [_target, player] call comspec_sse_fnc_captureIris; };
    case "face": { [_target, player] call comspec_sse_fnc_captureFace; };
    case "dna": { [_target, player] call comspec_sse_fnc_captureDNA; };
    case "all": { [_target, player] call comspec_sse_fnc_captureAll; };
};

// Refresh après délai progress bar
[{
    if (!isNull (findDisplay 93100)) then {
        [] call comspec_sse_fnc_seekOnLoad;
    };
}, [], 15] call CBA_fnc_waitAndExecute;

true
