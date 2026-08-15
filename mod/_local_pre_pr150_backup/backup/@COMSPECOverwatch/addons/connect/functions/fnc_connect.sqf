if (!hasInterface) exitWith {};

private _url = missionNamespace getVariable ["comspec_overwatch_api_url", ""];
if (_url == "") exitWith {};

"COMSPECExtension" callExtension ["Connect", [_url]];
