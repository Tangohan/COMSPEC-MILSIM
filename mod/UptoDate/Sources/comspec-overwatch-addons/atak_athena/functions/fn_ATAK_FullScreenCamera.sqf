/*
    Remplace BCE_fnc_ATAK_FullScreenCamera.

    Téléphone (Quick Picture) : rendu vers `rttN` + switchCamera — l’opérateur
    peut encore marcher. Casque : vue scène (Internal BACK), comme l’amont.

    Le cliché (TakePicture) bascule brièvement en vue scène pour enregistrer
    l’overlay, puis restaure rttN.
*/
params [["_controllable_unit", false], "_Init_Cam"];

if (isNil {_Init_Cam}) then {
    _Init_Cam = "camera" camCreate [0, 0, 0];
};

private _kind = "hcam";
private _rtt = "";
if (_controllable_unit) then {
    _kind = "phone";
    _rtt = "rttN";
    _Init_Cam cameraEffect ["Internal", "BACK", _rtt];
    switchCamera _Init_Cam;
    missionNamespace setVariable ["COMSPEC_OverlayCamPromoted", false, false];
} else {
    _Init_Cam cameraEffect ["Internal", "BACK"];
    missionNamespace setVariable ["COMSPEC_OverlayCamPromoted", true, false];
};

cutText ["", "BLACK IN", 0.5];

camUseNVG false;
false setCamUseTi 0;
false setCamUseTi 1;

cameraEffectEnableHUD true;
showCinemaBorder false;

localNamespace setVariable ["COMSPEC_OverlayCaptureCam", _Init_Cam];
localNamespace setVariable ["COMSPEC_OverlayCaptureKind", _kind];
localNamespace setVariable ["COMSPEC_OverlayCaptureRtt", _rtt];

_Init_Cam
