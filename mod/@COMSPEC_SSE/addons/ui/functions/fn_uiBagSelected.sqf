private _rec = [] call comspec_sse_fnc_uiGetRecord;
if (isNull _rec) exitWith { hint "Aucun record."; false };
if (!isNil "comspec_sse_fnc_bagEvidence") then {
    [_rec, player] call comspec_sse_fnc_bagEvidence;
};
["evidence"] call comspec_sse_fnc_uiRefresh;
true
