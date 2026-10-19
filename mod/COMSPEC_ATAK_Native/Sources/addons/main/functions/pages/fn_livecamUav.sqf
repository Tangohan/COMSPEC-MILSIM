/*
    Live cam d'un drone du camp (idée d'un joueur, 19/10, comme le flux de nacelle de BCE) : on regarde la caméra de la
    nacelle d'un drone allié, piloté au terminal UAV, par un autre téléphone ou seul, sans le piloter ni l'appairer.
    La caméra suit la nacelle à chaque image (points mémoire uavCameraGunnerPos / uavCameraGunnerDir du drone, sinon
    ceux du pilote), rendue dans le même écran que le Live cam (r2t comspec_livecam) ; jour, vision nocturne, thermique.
    Le flux passe par la station de l'opérateur (fn_feedLink) : sans opérateur à portée, pas d'image.
    Params : [drone, mode 0 jour / 1 NV / 2 thermique]
*/
params [["_uav", objNull], ["_mode", 0]];
private _points = {
    params ["_u"];
    private _r = [];
    {
        _x params ["_p", "_d"];
        if ((_u selectionPosition [_p, "Memory"]) isNotEqualTo [0, 0, 0] && {(_u selectionPosition [_d, "Memory"]) isNotEqualTo [0, 0, 0]}) exitWith { _r = _x; };
    } forEach [["uavCameraGunnerPos", "uavCameraGunnerDir"], ["uavCameraDriverPos", "uavCameraDriverDir"]];
    _r
};
if (isNull _uav) exitWith { false };
["check", "LIVE CAM"] call comspec_atak_native_fnc_pipCheck;
private _cam = missionNamespace getVariable ["COMSPEC_ATAK_LivecamCam", objNull];
if (isNull _cam) then {
    _cam = "camera" camCreate (getPosATL _uav);
    _cam cameraEffect ["Internal", "Back", "comspec_livecam"];
    _cam camSetFov 0.75;
    missionNamespace setVariable ["COMSPEC_ATAK_LivecamCam", _cam];
};
"comspec_livecam" setPiPEffect [_mode];
// Boucle d'un porteur ou d'une caméra fixe encore en place : remplacée par celle du drone.
if ((missionNamespace getVariable ["COMSPEC_ATAK_LivecamKind", ""]) isNotEqualTo "uav" && {!isNil { missionNamespace getVariable "COMSPEC_ATAK_LivecamPfh" }}) then {
    [missionNamespace getVariable "COMSPEC_ATAK_LivecamPfh"] call CBA_fnc_removePerFrameHandler;
    missionNamespace setVariable ["COMSPEC_ATAK_LivecamPfh", nil];
};
missionNamespace setVariable ["COMSPEC_ATAK_LivecamKind", "uav"];
if ((attachedTo _cam) isNotEqualTo _uav) then {
    detach _cam;
    private _pts = [_uav] call _points;
    if ((count _pts) isEqualTo 0) then {
        // Drone sans point de caméra : sous le nez, regard vers l'avant et un peu vers le bas.
        _cam attachTo [_uav, [0, 0.3, -0.3]];
        _cam setVectorDirAndUp [[0, 0.94, -0.34], [0, 0.34, 0.94]];
    } else {
        _cam attachTo [_uav, [0, 0, 0], _pts select 0, true];
    };
    missionNamespace setVariable ["COMSPEC_ATAK_LivecamUavPts", _pts];
};
if (isNil { missionNamespace getVariable "COMSPEC_ATAK_LivecamPfh" }) then {
    missionNamespace setVariable ["COMSPEC_ATAK_LivecamPfh", [{
        params ["_args"];
        private _cam = missionNamespace getVariable ["COMSPEC_ATAK_LivecamCam", objNull];
        private _u = attachedTo _cam;
        if (isNull _cam || {isNull _u}) exitWith {};
        // Chaque image : la caméra suit la nacelle (direction relative au drone, la caméra lui est attachée).
        private _pts = missionNamespace getVariable ["COMSPEC_ATAK_LivecamUavPts", []];
        if ((count _pts) isEqualTo 2) then {
            private _dir = vectorNormalized ((_u selectionPosition [_pts select 1, "Memory"]) vectorDiff (_u selectionPosition [_pts select 0, "Memory"]));
            if ((vectorMagnitude _dir) > 0.5) then {
                private _side = _dir vectorCrossProduct [0, 0, 1];
                if ((vectorMagnitude _side) < 0.01) then { _side = [1, 0, 0]; };
                _cam setVectorDirAndUp [_dir, vectorNormalized (_side vectorCrossProduct _dir)];
            };
        };
        if (diag_tickTime < (_args select 0)) exitWith {};
        _args set [0, diag_tickTime + 1];
        // Chaque seconde : drone perdu, passé à l'ennemi ou flux coupé → arrêt ; sinon incrustation.
        private _l = ["eval", _u] call comspec_atak_native_fnc_feedLink;
        private _lost = !alive _u || {(side group _u) isNotEqualTo (side group player)};
        if (_lost || {(_l get "res") isEqualTo 0}) exitWith {
            [] call comspec_atak_native_fnc_livecamStop;
            ["WARNING", ["Live cam : flux du drone coupé", "Live cam : drone perdu"] select _lost, 3, 30] call comspec_atak_native_fnc_notify;
            private _st = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
            if ((_st getOrDefault ["activePage", ""]) isEqualTo "LIVECAM") then { [{ ["LIVECAM"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame; };
        };
        private _osd = uiNamespace getVariable ["COMSPEC_ATAK_LivecamOsd", controlNull];
        if (!isNull _osd) then {
            private _op = (UAVControl _u) param [0, objNull];
            _osd ctrlSetStructuredText parseText ((format ["<t font='COMSPEC_BarlowBold' color='#e5483a'>● LIVE</t>  <t font='COMSPEC_BarlowBold'>%1</t><br/><t size='0.8' color='#c9d4cf'>%2 · %3 m · alt %4 m · %5 · %6</t>",
                [_u, [], "NAME"] call comspec_atak_native_fnc_droneOsd, [getPosASL _u, 8] call comspec_atak_native_fnc_gridRef, round (player distance _u), round ((getPosATL _u) select 2),
                [format ["opérateur %1", [_op, true] call comspec_atak_native_fnc_unitCallsign], "sans opérateur"] select (isNull _op), [daytime, "HH:MM:SS"] call BIS_fnc_timeToString]) call comspec_atak_native_fnc_tr);
        };
    }, 0, [0]] call CBA_fnc_addPerFrameHandler];
};
true
