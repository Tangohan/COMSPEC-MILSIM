/*
    Sends pilot status (ROGER, INBOUND, ENGAGED, RTB) to the server via extension.
    params: [_status] string, one of ROGER, INBOUND, ENGAGED, RTB
*/
params ["_status"];
if (!hasInterface) exitWith {};

private _veh = vehicle player;
private _callsign = _veh getVariable ["COMSPEC_Callsign", ""];
if (_callsign isEqualTo "") then { _callsign = [] call comspec_overwatch_connect_fnc_getCallsign; };
if (_callsign isEqualTo "") then { _callsign = groupId (group player); };
if (_callsign == "") then { _callsign = groupId (group player); };
if (_callsign == "") then { _callsign = "PILOT"; };

private _s = toUpper (_status param [0, ""]);
if (_s in ["ROGER","INBOUND","ENGAGED","RTB"]) then {
    "COMSPECExtension" callExtension ["PilotResponse", [_callsign, _s]];
    [format ["[PILOT] %1 -> %2", _callsign, _s]] call comspec_overwatch_connect_fnc_appendLinkLog;
};
