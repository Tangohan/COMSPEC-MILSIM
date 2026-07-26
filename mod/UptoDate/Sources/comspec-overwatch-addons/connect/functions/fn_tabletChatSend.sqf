/*
    Envoi messagerie depuis la tablette HTML (sans dialog 9999).
    Params: [_text]
*/
params [["_msg", "", [""]]];

if (!hasInterface) exitWith {};
_msg = trim _msg;
if (_msg isEqualTo "") exitWith {};

private _channel = missionNamespace getVariable ["COMSPEC_Comms_Channel", "SQUAD"];
private _priority = missionNamespace getVariable ["COMSPEC_Comms_Priority", "ROUTINE"];
private _kind = "FREE";

if ((_msg select [0, 1]) == "#") then {
    private _tokens = _msg splitString " ";
    if (count _tokens > 0) then {
        private _head = toUpper (_tokens select 0);
        switch (_head) do {
            case "#CONTACT": { _priority = "CONTACT"; _kind = "CONTACT"; };
            case "#SITREP": { _priority = "IMPORTANT"; _kind = "SITREP"; };
            case "#URGENT": { _priority = "URGENT"; _kind = "FREE"; };
        };
        _tokens deleteAt 0;
        _msg = _tokens joinString " ";
    };
};

if (_msg isEqualTo "") exitWith {};

private _formatted = [[] call comspec_overwatch_connect_fnc_getCallsign, _channel, _priority, _msg, _kind] call comspec_overwatch_connect_fnc_formatCommsMessage;

private _radioLog = missionNamespace getVariable ["COMSPEC_RadioReplay", []];
_radioLog pushBack [serverTime, name player, _channel, _priority, _kind, _msg];
if (count _radioLog > 200) then {
    _radioLog deleteRange [0, (count _radioLog) - 200];
};
missionNamespace setVariable ["COMSPEC_RadioReplay", _radioLog, true];

[_formatted] call comspec_overwatch_connect_fnc_appendLinkLog;
[player, "CHAT", _formatted, "", "INFANTRY", 0.7] call comspec_overwatch_connect_fnc_sendIntel;
["OnCommsMessage", createHashMapFromArray [["channel", _channel], ["priority", _priority], ["kind", _kind], ["text", _msg]]] call comspec_overwatch_connect_fnc_publishEvent;
["Message transmis.", "system", "info"] call comspec_overwatch_connect_fnc_announce;
