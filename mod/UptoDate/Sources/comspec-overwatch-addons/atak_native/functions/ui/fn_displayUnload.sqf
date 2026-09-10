params ["_display",["_exitCode",0]]; [] call comspec_atak_native_fnc_schedulerStop;
private _state=uiNamespace getVariable ["COMSPEC_ATAK_State",createHashMap]; _state set ["display",displayNull];
profileNamespace setVariable ["COMSPEC_ATAK_LastPage",_state getOrDefault ["activePage","MAP"]]; saveProfileNamespace;
uiNamespace setVariable ["COMSPEC_ATAK_Display",displayNull]; ["INFO","UI",format ["Display closed (%1); handlers cleaned",_exitCode]] call comspec_atak_native_fnc_log;
