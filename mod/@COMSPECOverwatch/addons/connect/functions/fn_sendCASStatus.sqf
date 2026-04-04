/*
    Send CAS status update. Params: [status] e.g. ["TARGET_ACQUIRED"], ["INBOUND"], ["ENGAGED"], ["ABORTED"].
*/
params [["_status", "TARGET_ACQUIRED"]];
private _id = missionNamespace getVariable ["COMSPEC_CurrentCASId", ""];
if (_id isEqualTo "") exitWith { ["COMSPEC CAS: No CAS selected"] call BIS_fnc_showNotification; };
"COMSPECExtension" callExtension ["SendCASState", [_id, _status]];
missionNamespace setVariable ["COMSPEC_CurrentCASStatus", _status];
["COMSPEC CAS: " + _status] call BIS_fnc_showNotification;
private _disp = uiNamespace getVariable ["COMSPEC_CAS_Display", displayNull];
if (!isNull _disp) then {
    (_disp displayCtrl 8003) ctrlSetText ("Status: " + _status);
};
