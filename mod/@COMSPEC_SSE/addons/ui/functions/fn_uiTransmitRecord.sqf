/*
    Transmission du record UI courant (Athena / file d’attente).
*/
private _rec = [] call comspec_sse_fnc_uiGetRecord;
if (isNull _rec) exitWith {
    hint "Aucun sujet à transmettre.";
    false
};

if (!isNil "comspec_sse_fnc_transmitEntity") then {
    [_rec, true, true] call comspec_sse_fnc_transmitEntity;
} else {
    if (!isNil "comspec_sse_fnc_submitPersonRecord") then {
        [_rec] call comspec_sse_fnc_submitPersonRecord;
    };
};

if (!isNil "comspec_sse_fnc_emitEvent") then {
    ["SSE_RecordCollected", [_rec, "ui_transmit"]] call comspec_sse_fnc_emitEvent;
};

true
