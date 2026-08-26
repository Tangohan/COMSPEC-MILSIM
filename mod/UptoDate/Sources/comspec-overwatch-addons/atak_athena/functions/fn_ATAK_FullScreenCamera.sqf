/*
    Remplace BCE_fnc_ATAK_FullScreenCamera.

    L’amont rend la caméra téléphone vers la texture `rttN` puis appelle
    `switchCamera` sur l’objet caméra. `screenshot` et l’extension de cliché
    enregistrent alors la vue soldat (dehors), pas l’overlay (cages, NOX).
    On rend la caméra en vue scène, comme le casque plein écran.
*/
params [["_controllable_unit", false], "_Init_Cam"];

if (isNil {_Init_Cam}) then {
    _Init_Cam = "camera" camCreate [0, 0, 0];
};

_Init_Cam cameraEffect ["Internal", "BACK"];
cutText ["", "BLACK IN", 0.5];

camUseNVG false;
false setCamUseTi 0;
false setCamUseTi 1;

cameraEffectEnableHUD true;
showCinemaBorder false;

private _kind = ["hcam", "phone"] select _controllable_unit;

localNamespace setVariable ["COMSPEC_OverlayCaptureCam", _Init_Cam];
localNamespace setVariable ["COMSPEC_OverlayCaptureKind", _kind];
missionNamespace setVariable ["COMSPEC_OverlayCamPromoted", true, false];

_Init_Cam
