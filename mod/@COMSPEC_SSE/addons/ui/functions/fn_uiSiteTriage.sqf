private _disp = findDisplay 93300;
if (isNull _disp) exitWith { false };
private _center = [] call comspec_sse_fnc_uiGetRecord;
if (isNull _center) then { _center = player; };
if (!isNil "comspec_sse_fnc_triageSite") then {
    private _tri = [_center, 50] call comspec_sse_fnc_triageSite;
    hint format ["Triage site: %1 élément(s)", count _tri];
};
["site"] call comspec_sse_fnc_uiRefresh;
true
