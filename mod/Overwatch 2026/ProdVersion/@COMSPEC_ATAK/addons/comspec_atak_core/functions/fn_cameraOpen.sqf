if (!hasInterface) exitWith { false };

if (missionNamespace getVariable ["COMSPEC_ATAK_CameraOpen", false]) exitWith
{
    if (isNull (findDisplay 88510)) then { createDialog "COMSPEC_ATAK_CameraHud"; };
    true
};

private _old = missionNamespace getVariable ["COMSPEC_ATAK_CameraObject", objNull];
if (!isNull _old) then
{
    _old cameraEffect ["Terminate", "Back"];
    camDestroy _old;
};

[] call COMSPEC_fnc_closeTablet;

private _cam = "camera" camCreate (ASLToAGL eyePos player);
_cam cameraEffect ["Internal", "Back"];
_cam camCommit 0;
showCinemaBorder false;
cameraEffectEnableHUD true;
switchCamera _cam;

missionNamespace setVariable ["COMSPEC_ATAK_CameraObject", _cam, false];
missionNamespace setVariable ["COMSPEC_ATAK_CameraOpen", true, false];
missionNamespace setVariable ["COMSPEC_ATAK_CameraClosing", false, false];
missionNamespace setVariable ["COMSPEC_ATAK_CameraShotBusy", false, false];

private _eh = addMissionEventHandler ["EachFrame", {
    private _cam = missionNamespace getVariable ["COMSPEC_ATAK_CameraObject", objNull];
    if (isNull _cam || {isNull player}) exitWith {};
    _cam setPosASL (eyePos player);
    _cam setVectorDirAndUp [getCameraViewDirection player, [0, 0, 1]];
}];
missionNamespace setVariable ["COMSPEC_ATAK_CameraFrameEh", _eh, false];

createDialog "COMSPEC_ATAK_CameraHud";
hint "Appareil photo — Déclencher, ou Échap pour fermer.";

["INFO", "ATHENA", "Viseur ouvert."] call COMSPEC_fnc_log;
true
