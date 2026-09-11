private _mapId = str (missionNamespace getVariable ["comspec_atak_native_map_id",1]);
private _raw = ["GetOrders",[_mapId,"40",name player]] call comspec_atak_native_fnc_extensionCall;
if ((_raw find "OK|") isNotEqualTo 0) exitWith { false };

private _tasks = createHashMap;
private _tab = toString [9];
{
    private _cols = _x splitString _tab;
    if ((count _cols) < 6) then { continue };
    private _id = trim (_cols select 0);
    if (_id isEqualTo "") then { continue };
    _tasks set [_id,createHashMapFromArray [
        ["id",_id],["type",_cols select 1],["target",_cols select 2],
        ["priority",_cols select 3],["issuer",_cols select 4],["status",_cols select 5],
        ["payload",_cols param [6,""]],["targetType",_cols param [7,"all"]],
        ["targetRef",_cols param [8,""]],["aliases",_cols param [9,""]],
        ["typeLabel",_cols param [10,""]],["source","athena"]
    ]];
} forEach ((_raw select [3]) splitString (toString [10]));

["tasks",_tasks] call comspec_atak_native_fnc_storeSet
