/*
    Interroge Athena (GetChatChannels) et met à jour COMSPEC_Comms_Channels.
    Format lignes : channel_key\tlabel\tkind
*/
if (!hasInterface) exitWith { false };
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith { false };
if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith { false };

private _mapId = str (missionNamespace getVariable ["comspec_overwatch_map_id", 1]);
if (_mapId isEqualTo "" || {_mapId isEqualTo "0"}) then { _mapId = "1"; };

private _raw = ["COMSPECExtension" callExtension ["GetChatChannels", [_mapId]]] call comspec_overwatch_connect_fnc_extResult;
if (!(_raw isEqualType "") || {_raw isEqualTo ""}) exitWith { false };
if ((_raw select [0, 3]) != "OK|") exitWith { false };

private _body = _raw select [3];
private _lines = _body splitString (toString [10]);
private _tab = toString [9];
private _channels = [];

{
    private _line = trim _x;
    if (_line isEqualTo "") then { continue };
    private _cols = _line splitString _tab;
    if ((count _cols) < 1) then { continue };
    private _key = toLower (trim (_cols select 0));
    if (_key isEqualTo "") then { continue };
    private _label = if ((count _cols) > 1) then { trim (_cols select 1) } else { _key };
    private _kind = if ((count _cols) > 2) then { toLower (trim (_cols select 2)) } else { "custom" };
    if (_label isEqualTo "") then { _label = _key; };
    _channels pushBack [_key, _label, _kind];
} forEach _lines;

if (_channels isEqualTo []) exitWith { false };

missionNamespace setVariable ["COMSPEC_Comms_Channels", _channels, false];
true
