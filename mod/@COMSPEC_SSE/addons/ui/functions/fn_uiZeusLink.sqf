private _rec = [] call comspec_sse_fnc_uiGetRecord;
if (isNull _rec) exitWith { hint "Sélectionnez un record (curseur / terminal)."; false };
if (!isNil "comspec_sse_fnc_pivotSearch") then {
    private _m = [_rec] call comspec_sse_fnc_pivotSearch;
    hint format ["Liens pivot créés/affichés: %1", count _m];
};
["zeus"] call comspec_sse_fnc_uiRefresh;
true
