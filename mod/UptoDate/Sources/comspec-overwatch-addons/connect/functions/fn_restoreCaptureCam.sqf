/*
    Remet un flux téléphone / casque / tourelle après un cliché scène.
    Params: [cam, rtt, kind] tel que renvoyé par promoteCaptureCam.
*/
params [
    ["_cam", objNull],
    ["_rtt", ""],
    ["_kind", ""]
];

if (!hasInterface) exitWith {};
if (isNull _cam) exitWith {};
if (_rtt isEqualTo "") exitWith {};

if (_kind isEqualTo "phone") then {
    _cam cameraEffect ["Internal", "BACK", _rtt];
    switchCamera _cam;
    missionNamespace setVariable ["COMSPEC_OverlayCamPromoted", false, false];
} else {
    if (!(_kind in ["hcam_pip", "uav_pip"])) exitWith {};
    _cam cameraEffect ["Internal", "BACK", _rtt];
};
