/*
    Check/validate a single 9-Line line. Params: [lineKey, checked]. lineKey e.g. "line1".
*/
params [["_lineKey", "line1"], ["_checked", true]];
private _id = missionNamespace getVariable ["COMSPEC_CurrentCASId", ""];
if (_id isEqualTo "") exitWith { ["COMSPEC CAS: No CAS selected"] call BIS_fnc_showNotification; };
private _callsign = missionNamespace getVariable ["COMSPEC_Callsign", name player];
if (_callsign isEqualTo "") then { _callsign = "Pilot"; };
"COMSPECExtension" callExtension ["SendCASCheckLine", [_id, _lineKey, if (_checked) then {"true"} else {"false"}, _callsign]];
["COMSPEC CAS: Line " + _lineKey + " " + (if (_checked) then {"checked"} else {"unchecked"})] call BIS_fnc_showNotification;
