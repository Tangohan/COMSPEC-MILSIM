private _raw = ["GetUnits",[]] call comspec_atak_native_fnc_extensionCall;
if ((_raw find "OK|") isNotEqualTo 0) exitWith { false };

private _units = createHashMap;
private _now = diag_tickTime;
private _tab = toString [9];
{
    private _cols = _x splitString _tab;
    if ((count _cols) < 4 || {(_cols select 0) isNotEqualTo "U"}) then { continue };
    private _callsign = trim (_cols select 1);
    private _pos = [parseNumber (_cols select 2),parseNumber (_cols select 3),0];
    if (_callsign isEqualTo "" || {_pos isEqualTo [0,0,0]}) then { continue };
    private _id = "athena:" + toLower _callsign;
    _units set [_id,createHashMapFromArray [
        ["id",_id],["callsign",_callsign],["position",_pos],["heading",0],
        ["affiliation","friend"],["type","infantry"],["freshness","LIVE"],["updated",_now]
    ]];
} forEach ((_raw select [3]) splitString (toString [10]));

["remoteUnits",_units] call comspec_atak_native_fnc_storeSet
