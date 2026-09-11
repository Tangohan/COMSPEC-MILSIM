private _nativeAuth = ["GetAuthState",[]] call comspec_atak_native_fnc_extensionCall;
private _nativeReady = (toUpper _nativeAuth find "READY") >= 0;
private _legacyReady = missionNamespace getVariable ["COMSPEC_AthenaReady",false];
if (!_nativeReady && {!_legacyReady}) exitWith {};
if (_nativeReady) then {
    private _state = uiNamespace getVariable ["COMSPEC_ATAK_State",createHashMap];
    _state set ["networkState","CONNECTED"];
};
private _until=missionNamespace getVariable ["COMSPEC_ApiBackoffUntil",0]; if (diag_tickTime<_until) exitWith {};
if (!isNil "comspec_overwatch_connect_fnc_pollAthenaMarkers") then {[] call comspec_overwatch_connect_fnc_pollAthenaMarkers;};
if (!isNil "comspec_overwatch_connect_fnc_pollOrders") then {[] call comspec_overwatch_connect_fnc_pollOrders;};
if (!isNil "comspec_overwatch_connect_fnc_pollChatMessages") then {[] call comspec_overwatch_connect_fnc_pollChatMessages;};
private _data=uiNamespace getVariable ["COMSPEC_ATAK_Data",createHashMap]; _data set ["lastNetworkUpdate",diag_tickTime]; [] call comspec_atak_native_fnc_importLegacyData;
