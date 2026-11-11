/*
    Relais 3D détruit : gerbe d'étincelles et fumée au pied (armoire, coffret), lueur brève. Effets locaux, joués sur
    les clients à moins de 2 km (Extended_Killed_EventHandlers de towers/config.cpp), effacés après 40 s.
      [relais] call comspec_atak_native_fnc_relayFx
*/
params [["_o", objNull, [objNull]]];
if (!hasInterface || {isNull _o}) exitWith {};
private _p = getPosATL _o;
if (((positionCameraToWorld [0, 0, 0]) distance _p) > 2000) exitWith {};
private _at = [_p select 0, _p select 1, ((_p select 2) max 0) + 1.2];
private _made = [];
{
    private _src = "#particlesource" createVehicleLocal _at;
    _src setParticleClass _x;
    _src setPosATL _at;
    _made pushBack _src;
} forEach ["FireSparks", "MediumDestructionSmoke"];
private _light = "#lightpoint" createVehicleLocal _at;
_light setPosATL _at;
_light setLightColor [1, 0.6, 0.25];
_light setLightAmbient [1, 0.5, 0.2];
_light setLightBrightness 0.9;
_light setLightAttenuation [2, 0, 0, 1, 20, 40];
[{ deleteVehicle _this; }, _light, 4] call comspec_atak_native_fnc_waitAndExec;
[{ { deleteVehicle _x; } forEach _this; }, _made, 40] call comspec_atak_native_fnc_waitAndExec;
