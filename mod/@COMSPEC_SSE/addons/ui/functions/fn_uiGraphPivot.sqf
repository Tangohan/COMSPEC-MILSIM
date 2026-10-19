private _rec = [] call comspec_sse_fnc_uiGetRecord;
if (isNull _rec) exitWith { ["Aucun record pour pivot.", "WARNING"] call comspec_sse_fnc_notify; false };
if (isNil "comspec_sse_fnc_pivotSearch") exitWith { false };
private _m = [_rec] call comspec_sse_fnc_pivotSearch;
[format ["Pivot: %1 entité(s) liée(s)", count _m], "INFO"] call comspec_sse_fnc_notify;
["graph"] call comspec_sse_fnc_uiRefresh;
true
