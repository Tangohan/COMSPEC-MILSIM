/*
    Transmission du record UI courant (Athena / queue).
*/
private _rec = [] call comspec_sse_fnc_uiGetRecord;
if (isNull _rec) exitWith {
    hint "Aucun record à transmettre.";
    false
};

if (!isNil "comspec_sse_fnc_submitPersonRecord") then {
    [_rec] call comspec_sse_fnc_submitPersonRecord;
};
if (!isNil "comspec_sse_fnc_submitDigitalAcquisition") then {
    [_rec] call comspec_sse_fnc_submitDigitalAcquisition;
};
if (!isNil "comspec_sse_fnc_submitBiometricsSim") then {
    [_rec] call comspec_sse_fnc_submitBiometricsSim;
};

hint "Transmission SSE demandée (record courant).";

if (!isNil "comspec_sse_fnc_emitEvent") then {
    ["SSE_RecordCollected", [_rec, "ui_transmit"]] call comspec_sse_fnc_emitEvent;
};

true
