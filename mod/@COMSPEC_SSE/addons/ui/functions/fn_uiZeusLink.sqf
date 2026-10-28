private _rec = [] call comspec_sse_fnc_uiGetRecord;
if (isNull _rec) exitWith { ["Sélectionnez un record (curseur / terminal).", "warn"] call comspec_sse_fnc_zeusNotify; false };
if (!isNil "comspec_sse_fnc_pivotSearch") then {
    private _m = [_rec] call comspec_sse_fnc_pivotSearch;
    [format ["Liens pivot créés/affichés: %1", count _m], "info"] call comspec_sse_fnc_zeusNotify;
};
["zeus"] call comspec_sse_fnc_uiRefresh;
true
