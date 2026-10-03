/* Coupe le Live cam : détruit la caméra et la boucle d'incrustation. */
private _cam = missionNamespace getVariable ["COMSPEC_ATAK_LivecamCam", objNull];
if (!isNull _cam) then { _cam cameraEffect ["Terminate", "Back", "comspec_livecam"]; camDestroy _cam; };
missionNamespace setVariable ["COMSPEC_ATAK_LivecamCam", objNull];
private _pfh = missionNamespace getVariable "COMSPEC_ATAK_LivecamPfh";
if (!isNil "_pfh") then { [_pfh] call CBA_fnc_removePerFrameHandler; missionNamespace setVariable ["COMSPEC_ATAK_LivecamPfh", nil]; };
uiNamespace setVariable ["COMSPEC_ATAK_LivecamOsd", controlNull];
true
