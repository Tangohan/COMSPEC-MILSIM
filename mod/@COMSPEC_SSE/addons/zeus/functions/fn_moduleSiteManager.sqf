params ["_logic", "_units", "_activated"];
if (!_activated) exitWith { true };
private _pos = getPosATL _logic;
private _list = [_pos, 60] call comspec_sse_fnc_listSiteEntities;
private _pct = [_pos, 60] call comspec_sse_fnc_siteCompleteness;
private _tri = [_pos, 60] call comspec_sse_fnc_triageSite;
private _lines = [format ["SITE MANAGER — complétude %1%% — %2 éléments", _pct, count _list]];
{
    _lines pushBack format [
        "%1 | %2 | %3 | V%4",
        _x getOrDefault ["triage", "?"],
        _x getOrDefault ["type", "?"],
        _x getOrDefault ["level", "?"],
        _x getOrDefault ["INTEL_VALUE", 0]
    ];
} forEach (_tri select [0, (count _tri) min 12]);
hint (_lines joinString endl);
if (isNull _logic) exitWith { true };
deleteVehicle _logic;
true
