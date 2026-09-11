private _data = uiNamespace getVariable ["COMSPEC_ATAK_Data",createHashMap];
private _messages = +(_data getOrDefault ["messages",[]]);
private _seen = uiNamespace getVariable ["COMSPEC_ATAK_ChatSeen",createHashMap];
private _after = uiNamespace getVariable ["COMSPEC_ATAK_ChatAfter","0"];
private _mapId = str (missionNamespace getVariable ["comspec_atak_native_map_id",1]);
private _raw = ["GetChatMessages",[_mapId,"40",_after]] call comspec_atak_native_fnc_extensionCall;
if ((_raw find "OK|") isNotEqualTo 0) exitWith { false };

private _tab = toString [9];
private _changed = false;
{
    private _cols = _x splitString _tab;
    if ((count _cols) < 3) then { continue };
    private _id = _cols select 0;
    if (_id isEqualTo "" || {_seen getOrDefault [_id,false]}) then { continue };
    _seen set [_id,true];
    if ((parseNumber _id) > (parseNumber _after)) then { _after = _id; };
    _messages pushBack createHashMapFromArray [
        ["id",_id],["author",_cols select 1],["body",_cols select 2],
        ["time",_cols param [3,"--:--"]],["status","RECEIVED"]
    ];
    _changed = true;
} forEach ((_raw select [3]) splitString (toString [10]));

while {(count _messages) > 200} do { _messages deleteAt 0; };
uiNamespace setVariable ["COMSPEC_ATAK_ChatSeen",_seen];
uiNamespace setVariable ["COMSPEC_ATAK_ChatAfter",_after];
if (_changed) then {["messages",_messages] call comspec_atak_native_fnc_storeSet} else {false}
