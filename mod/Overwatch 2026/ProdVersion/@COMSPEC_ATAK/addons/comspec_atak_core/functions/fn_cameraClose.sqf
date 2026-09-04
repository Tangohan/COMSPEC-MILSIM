if (missionNamespace getVariable ["COMSPEC_ATAK_CameraClosing", false]) exitWith { true };
missionNamespace setVariable ["COMSPEC_ATAK_CameraClosing", true, false];

private _eh = missionNamespace getVariable ["COMSPEC_ATAK_CameraFrameEh", -1];
if (_eh isEqualType 0 && {_eh >= 0}) then
{
    removeMissionEventHandler ["EachFrame", _eh];
};
missionNamespace setVariable ["COMSPEC_ATAK_CameraFrameEh", -1, false];

private _cam = missionNamespace getVariable ["COMSPEC_ATAK_CameraObject", objNull];
if (!isNull _cam) then
{
    _cam cameraEffect ["Terminate", "Back"];
    camDestroy _cam;
};
missionNamespace setVariable ["COMSPEC_ATAK_CameraObject", objNull, false];
missionNamespace setVariable ["COMSPEC_ATAK_CameraOpen", false, false];

if (!isNull (findDisplay 88510)) then
{
    closeDialog 0;
};

switchCamera player;
cameraEffectEnableHUD false;
hintSilent "";

[] spawn
{
    uiSleep 0.08;
    [] call COMSPEC_fnc_openTablet;
    ["if(window.COMSPEC_ATAK_forcePhoneApp){window.COMSPEC_ATAK_forcePhoneApp('camera');}"] call COMSPEC_fnc_webExecJS;
    private _pending = missionNamespace getVariable ["COMSPEC_ATAK_PendingToast", []];
    if ((_pending isEqualType []) && {(count _pending) >= 1}) then
    {
        [_pending select 0, _pending param [1, "INFO"]] call COMSPEC_fnc_notify;
        missionNamespace setVariable ["COMSPEC_ATAK_PendingToast", [], false];
    };
    missionNamespace setVariable ["COMSPEC_ATAK_CameraClosing", false, false];
};

true
