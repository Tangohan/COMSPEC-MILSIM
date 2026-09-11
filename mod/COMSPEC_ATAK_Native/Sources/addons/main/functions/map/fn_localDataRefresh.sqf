if (!hasInterface || {isNull player}) exitWith {};

private _data = uiNamespace getVariable ["COMSPEC_ATAK_Data",createHashMap];
private _units = createHashMap;
{
    private _remote = _y;
    private _age = diag_tickTime - (_remote getOrDefault ["updated",diag_tickTime]);
    private _freshness = switch (true) do {
        case (_age < 5): {"LIVE"};
        case (_age < 20): {"STALE"};
        case (_age < 60): {"LOST"};
        default {"OFFLINE"};
    };
    _remote set ["freshness",_freshness];
    _units set [_x,_remote];
} forEach (_data getOrDefault ["remoteUnits",createHashMap]);

{
    private _obj = _x;
    if (isNull _obj) then { continue };
    private _id = netId _obj;
    if (_id isEqualTo "0:0") then { _id = str _obj; };
    private _affiliation = if (side group _obj isEqualTo side group player) then {
        "friend"
    } else {
        switch (side group _obj) do {
            case east: {"hostile"};
            case civilian: {"neutral"};
            default {"unknown"};
        }
    };
    private _type = if (_obj isKindOf "CAManBase") then {
        "infantry"
    } else {
        if (_obj isKindOf "Air") then {"air"} else {if (_obj isKindOf "Tank") then {"armor"} else {"vehicle"}}
    };
    _units set [_id,createHashMapFromArray [
        ["id",_id],["object",_obj],
        ["callsign",if (isPlayer _obj) then {name _obj} else {groupId group _obj}],
        ["position",getPosASL _obj],["heading",getDir _obj],
        ["affiliation",_affiliation],["type",_type],["freshness","LIVE"],["updated",diag_tickTime]
    ]];
} forEach (allUnits select {
    alive _x && {(side group _x isEqualTo side group player) || {profileNamespace getVariable ["COMSPEC_ATAK_ShowHostile",false]}}
});
["units",_units] call comspec_atak_native_fnc_storeSet;

private _markers = createHashMap;
{ _markers set [_x,_y]; } forEach (_data getOrDefault ["remoteMarkers",createHashMap]);
{
    private _name = _x;
    _markers set [_name,createHashMapFromArray [
        ["id",_name],["position",getMarkerPos _name],["text",markerText _name],
        ["type",markerType _name],["color",markerColor _name],["shape",markerShape _name],
        ["size",markerSize _name],["dir",markerDir _name],["alpha",markerAlpha _name]
    ]];
} forEach allMapMarkers;
["markers",_markers] call comspec_atak_native_fnc_storeSet;
