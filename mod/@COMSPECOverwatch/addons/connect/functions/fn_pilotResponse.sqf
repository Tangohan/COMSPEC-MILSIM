/*
    Sends pilot status (ROGER, INBOUND, ENGAGED, RTB) to the server via extension.
    params: [_status] string, one of ROGER, INBOUND, ENGAGED, RTB
*/
params ["_status"];
if (!hasInterface) exitWith {};

private _veh = vehicle player;
private _callsign = _veh getVariable ["COMSPEC_Callsign", groupId (group player)];
if (_callsign == "") then { _callsign = groupId (group player); };
if (_callsign == "") then { _callsign = "PILOT"; };

private _s = toUpper (_status param [0, ""]);
if (_s in ["ROGER","INBOUND","ENGAGED","RTB"]) then {
    "COMSPECExtension" callExtension ["PilotResponse", [_callsign, _s]];
    private _log = missionNamespace getVariable ["COMSPEC_Log", ""];
    _log = _log + "[PILOT] " + _callsign + " -> " + _s + "\n";
    missionNamespace setVariable ["COMSPEC_Log", _log, true];
};
