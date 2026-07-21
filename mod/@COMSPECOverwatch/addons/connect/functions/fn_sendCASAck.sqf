/*
    Send Roger / ACK for current CAS.
*/
private _id = missionNamespace getVariable ["COMSPEC_CurrentCASId", ""];
if (_id isEqualTo "") exitWith { ["COMSPEC_Warning", ["Aucune demande CAS sélectionnée"]] call BIS_fnc_showNotification; };
"COMSPECExtension" callExtension ["SendCASAck", [_id]];
missionNamespace setVariable ["COMSPEC_CurrentCASStatus", "ACKNOWLEDGED"];
["COMSPEC_Info", ["Roger transmis"]] call BIS_fnc_showNotification;
private _disp = uiNamespace getVariable ["COMSPEC_CAS_Display", displayNull];
if (!isNull _disp) then {
    (_disp displayCtrl 8003) ctrlSetText "Status: ACKNOWLEDGED";
};
