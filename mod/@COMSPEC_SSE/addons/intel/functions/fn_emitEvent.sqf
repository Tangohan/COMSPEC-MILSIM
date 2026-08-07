/*
    Émet un événement CBA SSE + journalise.
    [_eventName, _args] call comspec_sse_fnc_emitEvent
    Events: SSE_RecordCreated, SSE_RecordCollected, SSE_RecordExploited,
            SSE_IntelDiscovered, SSE_NetworkLinked, SSE_TriageDone, SSE_HookFired
*/
params [
    ["_eventName", "", [""]],
    ["_args", [], [[]]]
];

if (_eventName == "") exitWith { false };

private _full = if ((_eventName find "SSE_") == 0) then { _eventName } else { format ["SSE_%1", _eventName] };
private _cbaName = format ["comspec_sse_%1", toLower ((_full splitString "_") joinString "_")];

// Noms stables documentés
private _map = createHashMapFromArray [
    ["SSE_RecordCreated", "comspec_sse_recordCreated"],
    ["SSE_RecordCollected", "comspec_sse_recordCollected"],
    ["SSE_RecordExploited", "comspec_sse_recordExploited"],
    ["SSE_IntelDiscovered", "comspec_sse_intelDiscovered"],
    ["SSE_NetworkLinked", "comspec_sse_networkLinked"],
    ["SSE_TriageDone", "comspec_sse_triageDone"],
    ["SSE_HookFired", "comspec_sse_hookFired"],
    ["SSE_FusionUpdated", "comspec_sse_fusionUpdated"]
];

private _evt = _map getOrDefault [_full, format ["comspec_sse_%1", _eventName]];
[_evt, _args] call CBA_fnc_localEvent;
if (isServer) then {
    [_evt, _args] call CBA_fnc_globalEvent;
};

[format ["event %1", _evt]] call comspec_sse_fnc_log;
true
