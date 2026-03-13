#include "..\script_component.hpp"

if (!hasInterface) exitWith {};

private _uri = GVAR(uri);
if (_uri == "") exitWith {};

private _key = GVAR(key);
private _worldName = worldName;
private _worldSize = worldSize;
private _date = date;

"COMSPECExtension" callExtension ["Connect", [_uri, _key, getPlayerUID player, name player, _worldName, str _worldSize]];

LOG("COMSPEC Overwatch: Connect envoyé");
