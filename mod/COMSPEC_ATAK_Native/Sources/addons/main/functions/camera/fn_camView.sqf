/*
    Vidéo live sur une caméra fixe (fn_camAction) : même caméra locale et même render-to-texture que le Live cam
    (comspec_livecam, fn_livecamStart / fn_livecamStop), fixée devant l'objectif de la caméra posée.
    Params : [caméra posée, mode 0 jour / 1 NV / 2 thermique, panoramique local en degrés (+ à droite)]
*/
params ["_obj", ["_mode", 0], ["_pan", 0]];
if (isNull _obj) exitWith { false };
private _cam = missionNamespace getVariable ["COMSPEC_ATAK_LivecamCam", objNull];
if (isNull _cam) then {
    _cam = "camera" camCreate (getPosATL _obj);
    _cam cameraEffect ["Internal", "Back", "comspec_livecam"];
    _cam camSetFov 0.75;
    missionNamespace setVariable ["COMSPEC_ATAK_LivecamCam", _cam];
};
// Boucle du Live cam d'un porteur encore en place : remplacée par celle de la caméra fixe.
private _pfh = missionNamespace getVariable "COMSPEC_ATAK_LivecamPfh";
if (!isNil "_pfh" && {(missionNamespace getVariable ["COMSPEC_ATAK_LivecamKind", ""]) isNotEqualTo "fixed"}) then {
    [_pfh] call CBA_fnc_removePerFrameHandler;
    missionNamespace setVariable ["COMSPEC_ATAK_LivecamPfh", nil];
};
detach _cam;
// Juste devant l'objectif, panoramique relatif à l'orientation de la caméra (suit un véhicule porteur).
_cam attachTo [_obj, [0, 0.12, 0.03]];
_cam setVectorDirAndUp [[sin _pan, cos _pan, 0], [0, 0, 1]];
"comspec_livecam" setPiPEffect [_mode];
missionNamespace setVariable ["COMSPEC_ATAK_LivecamKind", "fixed"];
if (isNil { missionNamespace getVariable "COMSPEC_ATAK_LivecamPfh" }) then {
    missionNamespace setVariable ["COMSPEC_ATAK_LivecamPfh", [{
        private _cam = missionNamespace getVariable ["COMSPEC_ATAK_LivecamCam", objNull];
        if (isNull _cam) exitWith {};
        private _o = attachedTo _cam;
        if (isNull _o || {!(_o isKindOf "COMSPEC_ATAK_CameraObj")}) exitWith {};
        private _st = ["status", _o] call comspec_atak_native_fnc_camAction;
        if ((_st select 0) isNotEqualTo "OK") exitWith {
            [] call comspec_atak_native_fnc_livecamStop;
            ["WARNING", format ["%1 : flux perdu (%2)", _o getVariable ["COMSPEC_ATAK_CamName", "Caméra"], toLower (_st select 1)], 3, 30] call comspec_atak_native_fnc_notify;
            [{ ["LIVECAM"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
        };
        private _osd = uiNamespace getVariable ["COMSPEC_ATAK_LivecamOsd", controlNull];
        if (!isNull _osd) then {
            private _d = vectorDir _cam;
            // Flux par les relais (fn_videoLink) : palier adaptatif, débit, relais et latence en troisième ligne.
            private _v = ["video", _o] call comspec_atak_native_fnc_camAction;
            (["adapt", ["LIVECAM", _v]] call comspec_atak_native_fnc_videoLink) params ["_vt", "_vtxt"];
            if (_vtxt isNotEqualTo "") then { ["INFO", _vtxt, 3, 30] call comspec_atak_native_fnc_notify; };
            _osd ctrlSetStructuredText parseText ((format ["<t font='RobotoCondensedBold' color='#e5483a'>● LIVE</t>  <t font='RobotoCondensedBold'>%1</t><br/><t size='0.8' color='#c9d4cf'>%2 · %3 m · cap %4° · %5%6</t>",
                _o getVariable ["COMSPEC_ATAK_CamName", "Caméra"], [getPosASL _o, 8] call comspec_atak_native_fnc_gridRef, round (player distance _o),
                round (((_d select 0) atan2 (_d select 1)) + 360) mod 360, [dayTime, "HH:MM:SS"] call BIS_fnc_timeToString,
                ["", "<br/>" + (["osd", [_v, _vt]] call comspec_atak_native_fnc_videoLink)] select (_v getOrDefault ["active", false])]) call comspec_atak_native_fnc_tr);
        };
    }, 1] call CBA_fnc_addPerFrameHandler];
};
true
