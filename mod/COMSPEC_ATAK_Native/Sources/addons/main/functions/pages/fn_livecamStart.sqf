/* Démarre (ou retarget) la caméra poitrine du Live cam. Params : [porteur, mode 0 jour / 1 NV / 2 thermique] */
params ["_unit", ["_mode", 0]];
if (isNull _unit) exitWith { false };
private _cam = missionNamespace getVariable ["COMSPEC_ATAK_LivecamCam", objNull];
if (isNull _cam) then {
    _cam = "camera" camCreate (getPosATL _unit);
    _cam cameraEffect ["Internal", "Back", "comspec_livecam"];
    _cam camSetFov 0.75;
    missionNamespace setVariable ["COMSPEC_ATAK_LivecamCam", _cam];
};
if ((attachedTo _cam) isNotEqualTo _unit) then {
    detach _cam;
    // Buste : légèrement devant la poitrine, regard vers l'avant du porteur.
    _cam attachTo [_unit, [0, 0.22, 0.05], "spine3", true];
    _cam setVectorDirAndUp [[0, 1, 0], [0, 0, 1]];
};
"comspec_livecam" setPiPEffect [_mode];
// Boucle d'une caméra fixe (fn_camView) encore en place : remplacée par celle du porteur.
if ((missionNamespace getVariable ["COMSPEC_ATAK_LivecamKind", ""]) isEqualTo "fixed" && {!isNil { missionNamespace getVariable "COMSPEC_ATAK_LivecamPfh" }}) then {
    [missionNamespace getVariable "COMSPEC_ATAK_LivecamPfh"] call CBA_fnc_removePerFrameHandler;
    missionNamespace setVariable ["COMSPEC_ATAK_LivecamPfh", nil];
};
missionNamespace setVariable ["COMSPEC_ATAK_LivecamKind", "unit"];
// Rafraîchit l'incrustation (grille, distance, cap) et coupe le flux si le porteur meurt ou perd son téléphone.
if (isNil { missionNamespace getVariable "COMSPEC_ATAK_LivecamPfh" }) then {
    missionNamespace setVariable ["COMSPEC_ATAK_LivecamPfh", [{
        private _cam = missionNamespace getVariable ["COMSPEC_ATAK_LivecamCam", objNull];
        private _u = attachedTo _cam;
        if (isNull _cam || {isNull _u} || {_u isKindOf "COMSPEC_ATAK_CameraObj"}) exitWith {};
        if (!alive _u || {!([_u] call comspec_atak_native_fnc_hasDevice)}) exitWith {
            [] call comspec_atak_native_fnc_livecamStop;
            ["WARNING", "Live cam : flux perdu", 3, 30] call comspec_atak_native_fnc_notify;
            // Page Live cam, ou petite fenêtre de la carte (VOIR CAMÉRA) : seule la page à l'écran est redessinée.
            private _st = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
            private _pg = _st getOrDefault ["activePage", ""];
            if (_pg isEqualTo "MAP") then { _st set ["mapCam", ""]; };
            if (_pg in ["LIVECAM", "MAP"]) then { [{ [_this] call comspec_atak_native_fnc_pageRender; }, _pg] call CBA_fnc_execNextFrame; };
        };
        private _osd = uiNamespace getVariable ["COMSPEC_ATAK_LivecamOsd", controlNull];
        if (!isNull _osd) then {
            _osd ctrlSetStructuredText parseText ((format ["<t font='COMSPEC_BarlowBold' color='#e5483a'>● LIVE</t>  <t font='COMSPEC_BarlowBold'>%1</t><br/><t size='0.8' color='#c9d4cf'>%2 · %3 m · cap %4° · %5</t>",
                [_u, true] call comspec_atak_native_fnc_unitCallsign, [getPosASL _u, 8] call comspec_atak_native_fnc_gridRef, round (player distance _u), round getDir _u, [daytime, "HH:MM:SS"] call BIS_fnc_timeToString]) call comspec_atak_native_fnc_tr);
        };
    }, 1] call CBA_fnc_addPerFrameHandler];
};
true
