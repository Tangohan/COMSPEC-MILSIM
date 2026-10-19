/*
    Live cam d'un drone du camp (idée d'un joueur, 19/10, comme le flux de nacelle de BCE) : on regarde la caméra de la
    nacelle d'un drone allié, piloté au terminal UAV, par un autre téléphone ou seul, sans le piloter ni l'appairer.
    Calée sur la vue du terminal UAV (retour de joueur, 19/10 : « pods view not sync with players UAV Terminal View ») :
      - siège : vue du tireur (nacelle) ou du pilote, celle que l'opérateur regarde (COMSPEC_UavView, sinon UAVControl) ;
      - direction : tourelle de la nacelle (weaponDirection, synchronisée par le jeu sur tous les postes), caméra pilote
        (getPilotCameraDirection), sinon points mémoire uavCamera*Dir ; recalculée à chaque image ;
      - zoom et vision (jour / NV / thermique) : ceux du terminal, publiés par le poste de l'opérateur
        (XEH_postInitClient, COMSPEC_UavView [siège, champ, vision]) ; les boutons de vision restent utilisables.
    Avant, la caméra était attachée au point mémoire avec la rotation de l'os en plus de notre direction (angle compté
    deux fois), toujours sur la nacelle même quand l'opérateur pilotait, et sans zoom.
    Rendue dans le même écran que le Live cam (r2t comspec_livecam). Le flux passe par la station de l'opérateur
    (fn_feedLink) : sans opérateur à portée, pas d'image.
    Params : [drone, mode 0 jour / 1 NV / 2 thermique]
*/
params [["_uav", objNull], ["_mode", 0]];
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
// Nouveau drone : le siège et le zoom seront recalés par la boucle.
if ((missionNamespace getVariable ["COMSPEC_ATAK_LivecamUav", objNull]) isNotEqualTo _uav) then {
    missionNamespace setVariable ["COMSPEC_ATAK_LivecamUav", _uav];
    missionNamespace setVariable ["COMSPEC_ATAK_LivecamUavSeat", ""];
    missionNamespace setVariable ["COMSPEC_ATAK_LivecamUavSync", [-1, -1]];
};
if (isNil { missionNamespace getVariable "COMSPEC_ATAK_LivecamPfh" }) then {
    missionNamespace setVariable ["COMSPEC_ATAK_LivecamPfh", [{
        params ["_args"];
        private _cam = missionNamespace getVariable ["COMSPEC_ATAK_LivecamCam", objNull];
        private _u = missionNamespace getVariable ["COMSPEC_ATAK_LivecamUav", objNull];
        if (isNull _cam || {isNull _u}) exitWith {};
        // Vue regardée au terminal : siège publié par l'opérateur, sinon celui qu'il tient, sinon la nacelle s'il y en a une.
        private _view = _u getVariable ["COMSPEC_UavView", []];
        private _seat = _view param [0, ""];
        if !(_seat in ["DRIVER", "GUNNER"]) then {
            _seat = (UAVControl _u) param [1, ""];
            if !(_seat in ["DRIVER", "GUNNER"]) then { _seat = ["DRIVER", "GUNNER"] select ((count (_u weaponsTurret [0])) > 0 || {!isNull gunner _u}); };
        };
        private _pts = [["uavCameraDriverPos", "uavCameraDriverDir"], ["uavCameraGunnerPos", "uavCameraGunnerDir"]] select (_seat isEqualTo "GUNNER");
        private _p0 = _u selectionPosition [_pts select 0, "Memory"];
        // Siège changé : caméra replacée sur le point de vue de ce siège (sans suivre la rotation de l'os : la direction
        // est posée par nous, sinon l'angle de la nacelle comptait deux fois).
        if ((missionNamespace getVariable ["COMSPEC_ATAK_LivecamUavSeat", ""]) isNotEqualTo _seat || {(attachedTo _cam) isNotEqualTo _u}) then {
            missionNamespace setVariable ["COMSPEC_ATAK_LivecamUavSeat", _seat];
            detach _cam;
            _cam attachTo [_u, [[0, 0.3, -0.3], _p0] select (_p0 isNotEqualTo [0, 0, 0])];
        };
        // Direction, dans le repère du drone.
        private _dir = [];
        if (_seat isEqualTo "GUNNER") then {
            private _w = (_u weaponsTurret [0]) param [0, ""];
            if (_w isNotEqualTo "") then { _dir = _u vectorWorldToModelVisual (_u weaponDirection _w); };
        } else {
            if (isClass (configOf _u >> "PilotCamera")) then { _dir = getPilotCameraDirection _u; };
        };
        if ((count _dir) < 3 || {(vectorMagnitude _dir) < 0.5}) then {
            private _p1 = _u selectionPosition [_pts select 1, "Memory"];
            _dir = if (_p0 isNotEqualTo [0, 0, 0] && {_p1 isNotEqualTo [0, 0, 0]}) then { _p1 vectorDiff _p0 } else { [0, 0.94, -0.34] };
        };
        _dir = vectorNormalized _dir;
        private _side = _dir vectorCrossProduct [0, 0, 1];
        if ((vectorMagnitude _side) < 0.01) then { _side = [1, 0, 0]; };
        _cam setVectorDirAndUp [_dir, vectorNormalized (_side vectorCrossProduct _dir)];
        // Zoom et vision du terminal, appliqués quand ils changent (zoom : la caméra est détachée le temps du réglage,
        // un camCommit sur une caméra attachée ne prend pas toujours).
        _view params ["", ["_fov", 0.75], ["_vm", -1]];
        private _sync = missionNamespace getVariable ["COMSPEC_ATAK_LivecamUavSync", [-1, -1]];
        if (_fov isEqualType 0 && {abs (_fov - (_sync select 0)) > 0.005}) then {
            _sync set [0, _fov];
            private _att = attachedTo _cam;
            private _off = _att worldToModelVisual (ASLToAGL (getPosASLVisual _cam));
            detach _cam;
            _cam camSetFov ((_fov max 0.01) min 1.5);
            _cam camCommit 0;
            _cam attachTo [_att, _off];
            _cam setVectorDirAndUp [_dir, vectorNormalized (_side vectorCrossProduct _dir)];
        };
        if (_vm isEqualType 0 && {_vm in [0, 1, 2]} && {_vm isNotEqualTo (_sync select 1)}) then {
            _sync set [1, _vm];
            "comspec_livecam" setPiPEffect [_vm];
            (uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) set ["livecamMode", _vm];
        };
        missionNamespace setVariable ["COMSPEC_ATAK_LivecamUavSync", _sync];
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
