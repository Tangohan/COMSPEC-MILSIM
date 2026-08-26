/*
    Bascule la caméra regardée en vue scène (pas de rendu vers texture).
    `screenshot` et l’extension BCE clichent alors CE point de vue.

    Params: [_restorePip] — true = le caller restaurera un PiP après le cliché.
    Retour: [cam, rtt, kind] à passer à restoreCaptureCam, ou [].
*/
params [["_restorePip", true]];

if (!hasInterface) exitWith { [] };
if (isNil "comspec_overwatch_connect_fnc_getActiveCaptureCam") exitWith { [] };

private _info = [] call comspec_overwatch_connect_fnc_getActiveCaptureCam;
_info params ["_cam", "_host", "_rtt", "_kind"];
if (isNull _cam) exitWith { [] };

// Vue scène = framebuffer principal. Un RTT (rttN / rendertarget9) n’est PAS
// ce que `screenshot` enregistre — d’où la photo du soldat au lieu de l’overlay.
_cam cameraEffect ["Internal", "BACK"];
cameraEffectEnableHUD true;
showCinemaBorder false;
missionNamespace setVariable ["COMSPEC_OverlayCamPromoted", true, false];

if (!_restorePip) exitWith { [] };
if (_rtt isEqualTo "") exitWith { [] };
if (!(_kind in ["hcam_pip", "uav_pip"])) exitWith { [] };

[_cam, _rtt, _kind]
