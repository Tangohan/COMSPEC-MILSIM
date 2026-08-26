/*
    Remet un flux casque / tourelle en image dans l’ATAK après un cliché scène.
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
if (!(_kind in ["hcam_pip", "uav_pip"])) exitWith {};

_cam cameraEffect ["Internal", "BACK", _rtt];
