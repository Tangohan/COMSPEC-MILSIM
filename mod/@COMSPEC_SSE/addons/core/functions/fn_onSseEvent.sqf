/*
    Listener noyau des événements COMSPEC SSE.
    Appelé depuis XEH_postInit — enregistre les handlers CBA une seule fois.
*/
if (!isNil "comspec_sse_eventBusReady") exitWith {};
comspec_sse_eventBusReady = true;

private _handler = {
    params ["_payload"];
    if (isNil "_payload") exitWith {};
    if !(_payload isEqualType createHashMap) exitWith {};

    private _type = _payload getOrDefault ["event_type", "?"];
    private _src = _payload getOrDefault ["source_system", "?"];
    private _sum = _payload getOrDefault ["summary", ""];
    [
        format ["SSE EVT %1 [%2] %3", _type, _src, _sum]
    ] call comspec_sse_fnc_log;
};

{
    [_x, _handler] call CBA_fnc_addEventHandler;
} forEach [
    "COMSPEC_SSE_ENTITY_IDENTIFIED",
    "COMSPEC_SSE_EVIDENCE_COLLECTED",
    "COMSPEC_SSE_BIOMETRIC_CAPTURED",
    "COMSPEC_SSE_PHOTO_TAKEN",
    "COMSPEC_SSE_TASK_RECEIVED"
];

["COMSPEC SSE event bus listeners ready"] call comspec_sse_fnc_log;
