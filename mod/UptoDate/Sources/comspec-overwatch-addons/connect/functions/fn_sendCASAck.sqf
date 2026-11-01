/*
    Send Roger / ACK for current CAS.
*/
private _id = missionNamespace getVariable ["COMSPEC_CurrentCASId", ""];
if (_id isEqualTo "") exitWith { ["COMSPEC_Warning", ["No CAS request selected"]] call comspec_overwatch_connect_fnc_showNotification; };
if (["SendCASAck"] call comspec_overwatch_connect_fnc_uplinkHold) exitWith {};
(["COMSPECExtension", "SendCASAck", [_id]] call comspec_overwatch_connect_fnc_extDirect);
missionNamespace setVariable ["COMSPEC_CurrentCASStatus", "ACKNOWLEDGED"];
["COMSPEC_Info", ["Roger transmis"]] call comspec_overwatch_connect_fnc_showNotification;
private _disp = uiNamespace getVariable ["COMSPEC_CAS_Display", displayNull];
if (!isNull _disp) then {
    (_disp displayCtrl 8003) ctrlSetText (("Status: ACKNOWLEDGED") call comspec_overwatch_connect_fnc_tr);
};
