/*
    Check/validate a single 9-Line line. Params: [lineKey, checked]. lineKey e.g. "line1".
*/
params [["_lineKey", "line1"], ["_checked", true]];
private _id = missionNamespace getVariable ["COMSPEC_CurrentCASId", ""];
if (_id isEqualTo "") exitWith { ["COMSPEC_Warning", ["No CAS request selected"]] call comspec_overwatch_connect_fnc_showNotification; };
private _callsign = [] call comspec_overwatch_connect_fnc_getCallsign;
if (_callsign isEqualTo "") then { _callsign = "Pilot"; };
"COMSPECExtension" callExtension ["SendCASCheckLine", [_id, _lineKey, if (_checked) then {"true"} else {"false"}, _callsign]];
["COMSPEC_Info", [format ["Ligne %1 %2", _lineKey, if (_checked) then {"validée"} else {"décochée"}]]] call comspec_overwatch_connect_fnc_showNotification;
