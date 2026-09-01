/*
    Charge une tenue de la communauté (avec cache de session).
*/
params [["_id", "", [""]]];

if (_id isEqualTo "") exitWith { [] };

private _cache = missionNamespace getVariable ["COMSPEC_ArsenalCloudLoadouts", nil];
if (isNil "_cache") then {
    _cache = createHashMap;
    missionNamespace setVariable ["COMSPEC_ArsenalCloudLoadouts", _cache, false];
};

if (_id in _cache) exitWith { _cache get _id };

private _raw = ["COMSPECExtension" callExtension ["GetWardrobe", [_id]]] call comspec_overwatch_connect_fnc_extResult;
if (!(_raw isEqualType "") || {_raw find "OK|" != 0}) exitWith { [] };

private _parts = (_raw select [3]) splitString toString [9];
if (count _parts < 3) exitWith { [] };

private _loadout = [_parts select 2] call comspec_overwatch_connect_fnc_arsenalNormalizeLoadout;
if (_loadout isEqualTo []) exitWith { [] };

_cache set [_id, _loadout];

private _names = missionNamespace getVariable ["COMSPEC_ArsenalCloudNames", nil];
if (isNil "_names") then {
    _names = createHashMap;
    missionNamespace setVariable ["COMSPEC_ArsenalCloudNames", _names, false];
};
_names set [_id, _parts select 1];

_loadout
