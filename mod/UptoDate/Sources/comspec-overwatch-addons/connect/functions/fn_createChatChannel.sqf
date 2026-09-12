/*
    Crée un canal radio custom via Athena.
    Params: [_label]
*/
params [["_label", "", [""]]];
if (!hasInterface) exitWith { false };
_label = trim _label;
if (_label isEqualTo "") exitWith { false };
if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith { false };

private _cs = "";
if (!isNil "comspec_overwatch_connect_fnc_getCallsign") then {
    _cs = [] call comspec_overwatch_connect_fnc_getCallsign;
};
if (_cs isEqualTo "") then { _cs = name player; };

private _raw = ["COMSPECExtension" callExtension ["CreateChatChannel", [_label, _cs]]] call comspec_overwatch_connect_fnc_extResult;
if (!(_raw isEqualType "") || {_raw isEqualTo ""}) exitWith { false };
if ((_raw select [0, 3]) != "OK|") exitWith {
    ["WARN", "Radio", "Impossible de créer le canal", _raw] call comspec_overwatch_connect_fnc_log;
    ["COMSPEC_Warning", ["Impossible de créer le canal."]] call comspec_overwatch_connect_fnc_showNotification;
    false
};

private _body = _raw select [3];
private _key = "";
private _rx = _body regexFind ["\"channel_key\"\\s*:\\s*\"([^\"]+)\""];
if ((count _rx) > 0) then {
    private _m = _rx select 0;
    if ((count _m) > 1) then {
        private _cap = (_m select 1) select 0;
        if (_cap isEqualType "" && {_cap isNotEqualTo ""}) then { _key = _cap; };
    };
};
_key = toLower (trim _key);
if (_key isEqualTo "") then { _key = "general"; };

private _channels = +(missionNamespace getVariable ["COMSPEC_Comms_Channels", []]);
if (!(_channels isEqualType [])) then { _channels = []; };
private _exists = false;
{
    if ((toLower (trim (_x param [0, ""]))) isEqualTo _key) exitWith { _exists = true; };
} forEach _channels;
if (!_exists) then {
    _channels pushBack [_key, _label, "custom"];
    missionNamespace setVariable ["COMSPEC_Comms_Channels", _channels, false];
};

missionNamespace setVariable ["COMSPEC_Comms_Channel", _key, false];
["INFO", "Radio", format ["Canal créé — %1 (%2)", _label, _key]] call comspec_overwatch_connect_fnc_log;
["COMSPEC_Info", [format ["Canal « %1 » créé.", _label]]] call comspec_overwatch_connect_fnc_showNotification;
["Canal radio créé.", "system", "info"] call comspec_overwatch_connect_fnc_announce;

if (!isNil "comspec_overwatch_connect_fnc_pollChatChannels") then {
    [] call comspec_overwatch_connect_fnc_pollChatChannels;
};
true
