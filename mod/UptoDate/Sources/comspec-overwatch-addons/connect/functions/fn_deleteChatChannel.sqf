/*
    Supprime un canal radio personnalisé via Athena.
    Params: [_channelKey]
*/
params [["_channelKey", "", [""]]];
if (!hasInterface) exitWith { false };
_channelKey = toLower (trim _channelKey);
if (_channelKey isEqualTo "") exitWith { false };
if (_channelKey in ["groupe", "commandement", "general", "jtac", "air", "squad", "global", "hq", "c2", "command", "group"]) exitWith {
    ["WARN", "Radio", "Canal système — suppression refusée", _channelKey] call comspec_overwatch_connect_fnc_log;
    ["COMSPEC_Warning", ["Les canaux système ne peuvent pas être supprimés."]] call comspec_overwatch_connect_fnc_showNotification;
    false
};
if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith { false };

private _mapId = str (missionNamespace getVariable ["comspec_overwatch_map_id", 1]);
if (_mapId isEqualTo "" || {_mapId isEqualTo "0"}) then { _mapId = "1"; };

private _raw = ["COMSPECExtension" callExtension ["DeleteChatChannel", [_channelKey, _mapId]]] call comspec_overwatch_connect_fnc_extResult;
if (!(_raw isEqualType "") || {_raw isEqualTo ""}) exitWith { false };
if ((_raw select [0, 3]) != "OK|") exitWith {
    private _err = toLower _raw;
    private _msg = "Impossible de supprimer le canal.";
    if ((_err find "system_channel") >= 0) then {
        _msg = "Les canaux système ne peuvent pas être supprimés.";
    };
    if ((_err find "not_found") >= 0) then {
        _msg = "Ce canal n’existe plus.";
    };
    if ((_err find "unauthorized") >= 0 || {(_err find "no_auth") >= 0}) then {
        _msg = "Liaison Athena requise pour supprimer un canal.";
    };
    ["WARN", "Radio", _msg, _raw] call comspec_overwatch_connect_fnc_log;
    ["COMSPEC_Warning", [_msg]] call comspec_overwatch_connect_fnc_showNotification;
    false
};

private _channels = +(missionNamespace getVariable ["COMSPEC_Comms_Channels", []]);
if (!(_channels isEqualType [])) then { _channels = []; };
_channels = _channels select {
    private _k = toLower (trim (_x param [0, ""]));
    !(_k isEqualTo _channelKey)
};
missionNamespace setVariable ["COMSPEC_Comms_Channels", _channels, false];

private _store = +(missionNamespace getVariable ["COMSPEC_Comms_Messages", []]);
if (_store isEqualType []) then {
    _store = _store select {
        private _ck = toLower (trim (_x param [4, "general"]));
        !(_ck isEqualTo _channelKey)
    };
    missionNamespace setVariable ["COMSPEC_Comms_Messages", _store, false];
};

private _active = toLower (trim (missionNamespace getVariable ["COMSPEC_Comms_Channel", "general"]));
if (_active isEqualTo _channelKey) then {
    missionNamespace setVariable ["COMSPEC_Comms_Channel", "general", false];
};

["INFO", "Radio", format ["Canal supprimé — %1", _channelKey]] call comspec_overwatch_connect_fnc_log;
["COMSPEC_Info", ["Canal radio supprimé."]] call comspec_overwatch_connect_fnc_showNotification;
["Canal radio supprimé.", "system", "info"] call comspec_overwatch_connect_fnc_announce;

if (!isNil "comspec_overwatch_connect_fnc_pollChatChannels") then {
    [] call comspec_overwatch_connect_fnc_pollChatChannels;
};
true
