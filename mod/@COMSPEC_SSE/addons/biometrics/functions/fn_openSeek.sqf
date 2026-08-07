params [
    ["_target", objNull, [objNull]]
];

if !([player, "seek"] call comspec_sse_fnc_hasEquipment) exitWith {
    hint "Terminal SEEK II (ou tablette cTab / Android ATAK compatible) requis.";
    false
};

missionNamespace setVariable ["comspec_sse_seekTarget", _target];
[_target] call comspec_sse_fnc_ensureGenerated;
if (!isNil "comspec_sse_fnc_uiSetRecord") then {
    [_target] call comspec_sse_fnc_uiSetRecord;
};

if !(createDialog "COMSPEC_SSE_SeekDialog") exitWith {
    private _sum = [_target] call comspec_sse_fnc_getBiometricSummary;
    hint format ["SEEK II (fallback)\nStatus: %1", _sum getOrDefault ["status", "?"]];
    false
};

true
