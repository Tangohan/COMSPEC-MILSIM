/*
    Send CAS status update. Params: [status] e.g. ["TARGET_ACQUIRED"], ["INBOUND"], ["ENGAGED"], ["ABORTED"].
*/
params [["_status", "TARGET_ACQUIRED"]];
private _id = missionNamespace getVariable ["COMSPEC_CurrentCASId", ""];
if (_id isEqualTo "") exitWith { ["COMSPEC_Warning", ["Aucune demande CAS sélectionnée"]] call comspec_overwatch_connect_fnc_showNotification; };
"COMSPECExtension" callExtension ["SendCASState", [_id, _status]];
missionNamespace setVariable ["COMSPEC_CurrentCASStatus", _status];
["COMSPEC_Info", [format ["Statut CAS : %1", _status]]] call comspec_overwatch_connect_fnc_showNotification;
private _disp = uiNamespace getVariable ["COMSPEC_CAS_Display", displayNull];
if (!isNull _disp) then {
    (_disp displayCtrl 8003) ctrlSetText ("Status: " + _status);
};
