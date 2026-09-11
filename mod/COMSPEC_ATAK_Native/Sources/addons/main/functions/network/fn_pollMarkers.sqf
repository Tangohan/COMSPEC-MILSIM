private _raw = ["GetMarkers",["world:" + worldName]] call comspec_atak_native_fnc_extensionCall;
if ((_raw find "OK|") isNotEqualTo 0) exitWith { false };

private _markers = createHashMap;
private _tab = toString [9];
{
    private _cols = _x splitString _tab;
    if ((count _cols) < 6 || {(_cols select 0) isNotEqualTo "M"}) then { continue };
    private _id = _cols select 1;
    private _pos = [parseNumber (_cols select 3),parseNumber (_cols select 4),0];
    if (_id isEqualTo "" || {_pos isEqualTo [0,0,0]}) then { continue };
    _markers set ["athena:" + _id,createHashMapFromArray [
        ["id",_id],["position",_pos],["type",_cols select 5],
        ["text",_cols param [6,""]],["color",_cols param [7,"ColorGreen"]],
        ["source",_cols param [8,"athena"]],["shape","ICON"],["size",[1,1]],["dir",0],["alpha",1]
    ]];
} forEach ((_raw select [3]) splitString (toString [10]));

["remoteMarkers",_markers] call comspec_atak_native_fnc_storeSet
