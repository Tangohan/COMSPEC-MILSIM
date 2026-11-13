/*
    comspec_fb_fnc_glare : éblouissement passager (0,4 s) pour qui VOIT le flash sans être dans la zone d'effet :
    spectateur, Zeus, joueur mort ou joueur au-delà du rayon maximal. Rien si un mur cache le flash, si on regarde
    à plus de 70° ou au-delà de 400 m. Bien plus marqué la nuit (pupille ouverte) et en JVN.
    [_posASL, _multiplicateur, _engin] call comspec_fb_fnc_glare
*/
params [["_pos", [0, 0, 0], [[]], 3], ["_mul", 1, [0]], ["_device", objNull, [objNull]]];
if !(missionNamespace getVariable ["comspec_fb_flash", true]) exitWith {};
private _cam = AGLToASL (positionCameraToWorld [0, 0, 0]);
private _d = _cam vectorDistance _pos;
if (_d > 400) exitWith {};
private _look = (AGLToASL (positionCameraToWorld [0, 0, 1])) vectorDiff _cam;
private _ang = acos (((vectorNormalized _look) vectorDotProduct (_cam vectorFromTo _pos)) max -1 min 1);
if (_ang > 70) exitWith {};
private _me = call CBA_fnc_currentUnit;
if ((lineIntersectsSurfaces [_pos vectorAdd [0, 0, 0.3], _cam, _device, vehicle _me, true, 1, "VIEW", "GEOM"]) isNotEqualTo []) exitWith {};
private _s = _mul * 0.6
    * linearConversion [0, 70, _ang, 1, 0.2, true]
    * linearConversion [0, 400, _d, 1, 0.05, true]
    * (0.25 + 0.75 * (1 - sunOrMoon));
if (!isNull _me && {currentVisionMode _me == 1}) then { _s = _s * 2 };
if (_s < 0.04) exitWith {};

private _prio = 1610;
private _h = -1;
while { _h = ppEffectCreate ["ColorCorrections", _prio]; _h < 0 } do { _prio = _prio + 1 };
_h ppEffectForceInNVG true;
_h ppEffectEnable true;
_h ppEffectAdjust [1, 1, 0, [1, 1, 1, _s min 0.8], [1, 1, 1, 1], [0.299, 0.587, 0.114, 0]];
_h ppEffectCommit 0;
[{
    _this ppEffectAdjust [1, 1, 0, [1, 1, 1, 0], [1, 1, 1, 1], [0.299, 0.587, 0.114, 0]];
    _this ppEffectCommit 0.4;
    [{ ppEffectDestroy _this }, _this, 0.45] call CBA_fnc_waitAndExecute;
}, _h, 0.05] call CBA_fnc_waitAndExecute;
