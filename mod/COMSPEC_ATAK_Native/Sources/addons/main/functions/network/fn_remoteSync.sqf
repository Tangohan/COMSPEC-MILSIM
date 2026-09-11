private _nativeAuth = ["GetAuthState",[]] call comspec_atak_native_fnc_extensionCall;
private _nativeReady = (toUpper _nativeAuth find "READY") >= 0;
if (!_nativeReady) exitWith {};

private _state = uiNamespace getVariable ["COMSPEC_ATAK_State",createHashMap];
_state set ["networkState","CONNECTED"];

private _until = missionNamespace getVariable ["COMSPEC_ATAK_NativeBackoffUntil",0];
if (diag_tickTime < _until) exitWith {};

private _changed = false;
_changed = ([] call comspec_atak_native_fnc_pollUnits) || _changed;
_changed = ([] call comspec_atak_native_fnc_pollMarkers) || _changed;
_changed = ([] call comspec_atak_native_fnc_pollOrders) || _changed;
_changed = ([] call comspec_atak_native_fnc_pollChat) || _changed;
if (_changed) then {
    private _data = uiNamespace getVariable ["COMSPEC_ATAK_Data",createHashMap];
    _data set ["lastNetworkUpdate",diag_tickTime];
};
