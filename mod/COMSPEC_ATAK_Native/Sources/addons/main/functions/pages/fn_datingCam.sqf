/*
    Photos de profil de Tinder (fn_pageDating) : une caméra posée devant la personne affichée rend son visage dans
    l'écran du téléphone (render-to-texture), comme une vraie photo de profil. Marche pour un joueur comme pour une IA
    (profils HVT / SSE) : on voit son vrai visage, sa tenue, son décor.
    Trois photos : 0 portrait serré, 1 buste, 2 en pied de trois quarts. Anneau de 4 cibles comme l'app Photo.
    La caméra ne tourne que tant que l'app Tinder est affichée (vérifié à chaque image), sinon elle est détruite.
    Params :
      ["bind", [unité, photo, proportions]] → texture r2t à poser dans un RscPicture ("" sans PiP)
      ["stop"]
*/
params [["_act", "bind"], ["_arg", []]];
if (!hasInterface) exitWith { "" };
// Pose pour la photo _shot de _u : [position ASL, direction, haut, champ].
private _pose = {
    params ["_u", "_shot"];
    private _head = (eyePos _u) vectorAdd [0, 0, 0.03];
    private _yaw = getDirVisual _u;
    if !(isNull objectParent _u) then { _yaw = getDirVisual (objectParent _u); };
    // [distance, décalage latéral en degrés, hauteur de visée, hauteur de la caméra, champ]
    (switch (_shot) do {
        case 1: { [1.5, 12, -0.25, -0.1, 0.42] };
        case 2: { [3.4, 32, -0.8, -0.45, 0.62] };
        default { [0.75, 6, -0.02, 0, 0.3] };
    }) params ["_dist", "_side", "_aimDz", "_camDz", "_fov"];
    private _a = _yaw + _side;
    private _target = _head vectorAdd [0, 0, _aimDz];
    private _pos = _head vectorAdd [_dist * sin _a, _dist * cos _a, _camDz];
    private _dir = vectorNormalized (_target vectorDiff _pos);
    private _right = _dir vectorCrossProduct [0, 0, 1];
    private _up = if ((vectorMagnitude _right) < 0.01) then { [0, 1, 0] } else { vectorNormalized (_right vectorCrossProduct _dir) };
    [_pos, _dir, _up, _fov]
};
private _stop = {
    private _cam = missionNamespace getVariable ["COMSPEC_ATAK_DateCam", objNull];
    if (!isNull _cam) then { _cam cameraEffect ["Terminate", "Back", _cam getVariable ["r2t", ""]]; camDestroy _cam; };
    missionNamespace setVariable ["COMSPEC_ATAK_DateCam", objNull];
    private _pfh = missionNamespace getVariable ["COMSPEC_ATAK_DateCamPfh", -1];
    if (_pfh >= 0) then { [_pfh] call CBA_fnc_removePerFrameHandler; };
    missionNamespace setVariable ["COMSPEC_ATAK_DateCamPfh", -1];
};
switch (_act) do {
    case "bind": {
        _arg params [["_u", objNull], ["_shot", 0], ["_aspect", 1]];
        // PiP coupée : journal RPT et notification (fn_pipCheck), photo du profil à la place du direct.
        if (isNull _u || {(["check", "TINDER"] call comspec_atak_native_fnc_pipCheck) isNotEqualTo ""}) exitWith { call _stop; "" };
        private _cam = missionNamespace getVariable ["COMSPEC_ATAK_DateCam", objNull];
        if (isNull _cam) then {
            private _n = ((missionNamespace getVariable ["COMSPEC_ATAK_DateCamN", 0]) + 1) mod 4;
            missionNamespace setVariable ["COMSPEC_ATAK_DateCamN", _n];
            private _name = format ["comspec_datecam%1", _n];
            _cam = "camera" camCreate (ASLToAGL (eyePos _u));
            _cam cameraEffect ["Internal", "Back", _name];
            _name setPiPEffect [0];
            _cam setVariable ["r2t", _name];
            missionNamespace setVariable ["COMSPEC_ATAK_DateCam", _cam];
        };
        _cam setVariable ["unit", _u];
        _cam setVariable ["shot", _shot];
        ([_u, _shot] call _pose) params ["_pos", "_dir", "_up", "_fov"];
        _cam setPosASL _pos; _cam setVectorDirAndUp [_dir, _up]; _cam camSetFov _fov; _cam camCommit 0;
        if ((missionNamespace getVariable ["COMSPEC_ATAK_DateCamPfh", -1]) < 0) then {
            missionNamespace setVariable ["COMSPEC_ATAK_DateCamPose", _pose];
            missionNamespace setVariable ["COMSPEC_ATAK_DateCamPfh", [{
                private _cam = missionNamespace getVariable ["COMSPEC_ATAK_DateCam", objNull];
                private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
                private _u = if (isNull _cam) then { objNull } else { _cam getVariable ["unit", objNull] };
                // Hors de Tinder, téléphone fermé ou personne disparue : caméra coupée.
                if (isNull _cam || {isNull _u} || {(_s getOrDefault ["activePage", ""]) isNotEqualTo "DATING"}
                    || {isNull (uiNamespace getVariable ["COMSPEC_ATAK_Display", displayNull])} || {!alive player}) exitWith { ["stop"] call comspec_atak_native_fnc_datingCam; };
                ([_u, _cam getVariable ["shot", 0]] call (missionNamespace getVariable "COMSPEC_ATAK_DateCamPose")) params ["_pos", "_dir", "_up", "_fov"];
                _cam setPosASL _pos; _cam setVectorDirAndUp [_dir, _up]; _cam camSetFov _fov; _cam camCommit 0;
            }, 0] call CBA_fnc_addPerFrameHandler];
        };
        format ["#(argb,512,512,1)r2t(%1,%2)", _cam getVariable ["r2t", "comspec_datecam0"], _aspect]
    };
    case "stop": { call _stop; true };
    default { "" };
};
