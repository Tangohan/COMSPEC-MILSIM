/*
    Open CAS 9-Line dialog. If no CAS data in namespace, fetch via extension.
*/
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {};
private _callsign = missionNamespace getVariable ["COMSPEC_Callsign", name player];
if (_callsign isEqualTo "") then { _callsign = "Pilot"; };
private _raw = ["COMSPECExtension" callExtension ["GetCASForCallsign", [_callsign, "1"]]] call comspec_overwatch_connect_fnc_extResult;
if (_raw isEqualTo "" || {(_raw select [0, 3]) != "OK|"}) exitWith {
    createDialog "COMSPEC_CAS_Dialog";
    private _disp = uiNamespace getVariable ["COMSPEC_CAS_Display", displayNull];
    if (!isNull _disp) then {
        (_disp displayCtrl 8010) ctrlSetStructuredText parseText "<t color='#888'>No CAS assigned to you. Polling...</t>";
    };
};
private _json = _raw select [3, count _raw - 3];
missionNamespace setVariable ["COMSPEC_CAS_Raw", _json];
[] call comspec_overwatch_connect_fnc_receiveCASRequest;
createDialog "COMSPEC_CAS_Dialog";
[] call comspec_overwatch_connect_fnc_updateCASState;
