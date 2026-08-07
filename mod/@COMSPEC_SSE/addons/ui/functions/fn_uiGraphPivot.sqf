private _rec = [] call comspec_sse_fnc_uiGetRecord;
if (isNull _rec) exitWith { hint "Aucun record pour pivot."; false };
if (isNil "comspec_sse_fnc_pivotSearch") exitWith { false };
private _m = [_rec] call comspec_sse_fnc_pivotSearch;
hint format ["Pivot: %1 entité(s) liée(s)", count _m];
["graph"] call comspec_sse_fnc_uiRefresh;
true
