/*
    Émet un événement CBA SSE + journalise.
    [_eventName, _args] call comspec_sse_fnc_emitEvent
    Diffusé une fois sur chaque machine ; l'émetteur est ajouté en dernier argument (clientOwner).
    Events: SSE_RecordCreated, SSE_RecordCollected, SSE_RecordExploited,
            SSE_IntelDiscovered, SSE_NetworkLinked, SSE_TriageDone, SSE_HookFired
    (comspec_sse_triageDone est désormais levé localement par triageSite, sans passer par ici.)
*/
params [
    ["_eventName", "", [""]],
    ["_args", [], [[]]]
];

if (_eventName == "") exitWith { false };

if (isNil "comspec_sse_emitEventDepth") then { comspec_sse_emitEventDepth = 0; };
if (comspec_sse_emitEventDepth > 8) exitWith {
    ["emitEvent: profondeur max — drop " + _eventName, "WARNING"] call comspec_sse_fnc_log;
    false
};
comspec_sse_emitEventDepth = comspec_sse_emitEventDepth + 1;

private _full = if ((_eventName find "SSE_") == 0) then { _eventName } else { format ["SSE_%1", _eventName] };

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
// Une seule émission par machine (globalEvent inclut la machine locale).
// Dernier argument ajouté : clientOwner de l'émetteur, pour les retours réservés au joueur qui agit.
[_evt, _args + [clientOwner]] call CBA_fnc_globalEvent;

comspec_sse_emitEventDepth = (comspec_sse_emitEventDepth - 1) max 0;
[format ["event %1", _evt]] call comspec_sse_fnc_log;
true
