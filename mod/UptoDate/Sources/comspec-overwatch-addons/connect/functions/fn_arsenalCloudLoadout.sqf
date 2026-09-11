/*
    Charge une tenue de la communauté (avec cache de session).
    Gère GetWardrobe chunké si le détail dépasse ~8 Ko.
*/
params [["_id", "", [""]]];

if (_id isEqualTo "") exitWith {
    missionNamespace setVariable ["COMSPEC_ArsenalCloudLoadoutError", "missing", false];
    []
};

missionNamespace setVariable ["COMSPEC_ArsenalCloudLoadoutError", "", false];

private _cache = missionNamespace getVariable ["COMSPEC_ArsenalCloudLoadouts", nil];
if (isNil "_cache") then {
    _cache = createHashMap;
    missionNamespace setVariable ["COMSPEC_ArsenalCloudLoadouts", _cache, false];
};

if (_id in _cache) exitWith { _cache get _id };

private _fnc_storeName = {
    params ["_wid", "_wname"];
    private _names = missionNamespace getVariable ["COMSPEC_ArsenalCloudNames", nil];
    if (isNil "_names") then {
        _names = createHashMap;
        missionNamespace setVariable ["COMSPEC_ArsenalCloudNames", _names, false];
    };
    if (_wname isNotEqualTo "") then { _names set [_wid, _wname]; };
};

private _raw = ["COMSPECExtension" callExtension ["GetWardrobe", [_id]]] call comspec_overwatch_connect_fnc_extResult;
if (!(_raw isEqualType "") || {_raw find "OK|" != 0}) exitWith {
    private _err = if (_raw isEqualType "") then { _raw } else { str _raw };
    private _code = if ((_err find "too_large") >= 0) then { "too_large" } else { "missing" };
    missionNamespace setVariable ["COMSPEC_ArsenalCloudLoadoutError", _code, false];
    []
};

private _body = _raw select [3];
private _assembled = "";
private _name = "";

if ((_body select [0, 7]) isEqualTo "CHUNKED") then {
    private _meta = _body splitString toString [9];
    // CHUNKED · id · name · chunks · chunkSize · totalLen
    if ((count _meta) < 4) exitWith {
        missionNamespace setVariable ["COMSPEC_ArsenalCloudLoadoutError", "too_large", false];
        []
    };
    private _wid = _meta select 1;
    _name = _meta select 2;
    private _chunks = parseNumber (_meta select 3);
    if (_chunks < 1) then { _chunks = 1; };
    [_wid, _name] call _fnc_storeName;
    private _buf = "";
    private _ok = true;
    for "_ci" from 0 to (_chunks - 1) do {
        private _cRaw = ["COMSPECExtension" callExtension ["GetWardrobeChunk", [_id, str _ci]]] call comspec_overwatch_connect_fnc_extResult;
        if (!(_cRaw isEqualType "") || {_cRaw find "OK|" != 0}) then {
            _ok = false;
            break;
        };
        private _cParts = (_cRaw select [3]) splitString toString [9];
        if ((count _cParts) < 3) then {
            _ok = false;
            break;
        };
        _buf = _buf + (_cParts select 2);
    };
    if (!_ok || {_buf isEqualTo ""}) exitWith {
        missionNamespace setVariable ["COMSPEC_ArsenalCloudLoadoutError", "too_large", false];
        []
    };
    _assembled = _buf;
} else {
    _assembled = _body;
};

private _parts = _assembled splitString toString [9];
if (count _parts < 3) exitWith {
    missionNamespace setVariable ["COMSPEC_ArsenalCloudLoadoutError", "missing", false];
    []
};

if (_name isEqualTo "") then { _name = _parts select 1; };
[_parts select 0, _name] call _fnc_storeName;

private _loadout = [_parts select 2] call comspec_overwatch_connect_fnc_arsenalNormalizeLoadout;
if (_loadout isEqualTo []) exitWith {
    missionNamespace setVariable ["COMSPEC_ArsenalCloudLoadoutError", "missing", false];
    []
};

_cache set [_id, _loadout];
_loadout
