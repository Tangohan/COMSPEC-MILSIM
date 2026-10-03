params ["_display"];
disableSerialization;
if (!hasInterface || {isNull _display}) exitWith {};
private _state = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
_state set ["display", _display];
_state set ["history", []];
_state set ["pageSig", []];
_state set ["pageSigPage", ""];
_state set ["mapCentered", false];
uiNamespace setVariable ["COMSPEC_ATAK_Display", _display];
uiNamespace setVariable ["COMSPEC_ATAK_PageControls", []];
uiNamespace setVariable ["COMSPEC_ATAK_DockControls", []];
uiNamespace setVariable ["COMSPEC_ATAK_ToastControls", []];
uiNamespace setVariable ["COMSPEC_ATAK_BadgeSig", []];

[] call comspec_atak_native_fnc_schedulerStart;
[profileNamespace getVariable ["COMSPEC_ATAK_LastPage", "LAUNCHER"]] call comspec_atak_native_fnc_navigate;
[] call comspec_atak_native_fnc_statusUpdate;

["INFO", "UI", "display_created"] call comspec_atak_native_fnc_log;
["INFO", "MAP", "map_control_ready"] call comspec_atak_native_fnc_log;
