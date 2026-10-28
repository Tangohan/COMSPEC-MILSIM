params [
    ["_target", objNull, [objNull]]
];

if !([player, "seek"] call comspec_sse_fnc_hasEquipment) exitWith {
    ["Terminal SEEK II (ou tablette cTab / Android ATAK compatible) requis.", "WARNING"] call comspec_sse_fnc_notify;
    false
};

missionNamespace setVariable ["comspec_sse_seekTarget", _target];
[_target] call comspec_sse_fnc_ensureGenerated;
if (!isNil "comspec_sse_fnc_uiSetRecord") then {
    [_target] call comspec_sse_fnc_uiSetRecord;
};

if (!isNil "comspec_overwatch_connect_fnc_sseOpenTerminal") exitWith {
    [_target, 3, "seek"] call comspec_sse_fnc_uiOpenSeekHost
};

if !(createDialog "COMSPEC_SSE_SeekDialog") exitWith {
    private _sum = [_target] call comspec_sse_fnc_getBiometricSummary;
    [format ["SEEK II (fallback)\nStatus: %1", _sum getOrDefault ["status", "?"]], "INFO"] call comspec_sse_fnc_notify;
    false
};

true
