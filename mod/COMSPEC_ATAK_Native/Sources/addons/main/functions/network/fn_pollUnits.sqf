/* Unités suivies par Athena (BFT web). Avec Overwatch connect, on reprend sa liste d'effectifs (même session). */
private _units = createHashMap;
private _now = diag_tickTime;
private _add = {
    params ["_callsign", "_wx", "_wy", ["_role", ""], ["_age", 0], ["_status", ""]];
    _callsign = trim _callsign;
    if (_callsign isEqualTo "" || {_wx isEqualTo 0 && {_wy isEqualTo 0}}) exitWith {};
    private _id = "athena:" + toLower _callsign;
    _units set [_id, createHashMapFromArray [
        ["id", _id], ["callsign", _callsign], ["position", [_wx, _wy, 0]], ["heading", 0],
        ["affiliation", "friend"], ["type", "infantry"], ["freshness", "LIVE"], ["updated", _now - _age],
        ["role", _role], ["status", _status]
    ]];
};
if ([] call comspec_atak_native_fnc_bridge) then {
    if !([] call comspec_overwatch_connect_fnc_isReady) exitWith {};
    {
        _x params [["_cs", ""], "", "", ["_self", false], ["_wx", 0], ["_wy", 0], ["_role", ""], ["_age", 0], ["_status", ""]];
        if (!_self) then { [_cs, _wx, _wy, _role, _age, _status] call _add; };
    } forEach ([] call comspec_overwatch_connect_fnc_getUnitsList);
} else {
    private _raw = ["GetUnits", []] call comspec_atak_native_fnc_extensionCall;
    if ((_raw find "OK|") isNotEqualTo 0) exitWith {};
    private _tab = toString [9];
    {
        private _cols = _x splitString _tab;
        if ((count _cols) < 4 || {(_cols select 0) isNotEqualTo "U"}) then { continue };
        [_cols select 1, parseNumber (_cols param [5, _cols select 2]), parseNumber (_cols param [6, _cols select 3]), _cols param [4, ""], parseNumber (_cols param [7, "0"]), _cols param [8, ""]] call _add;
    } forEach ((_raw select [3]) splitString (toString [10]));
};
["remoteUnits", _units] call comspec_atak_native_fnc_storeSet
