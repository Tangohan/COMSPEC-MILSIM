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
// Rafraîchit l'incrustation (grille, distance, cap) et coupe le flux si le porteur meurt ou perd son téléphone.
if (isNil { missionNamespace getVariable "COMSPEC_ATAK_LivecamPfh" }) then {
    missionNamespace setVariable ["COMSPEC_ATAK_LivecamPfh", [{
        private _cam = missionNamespace getVariable ["COMSPEC_ATAK_LivecamCam", objNull];
        private _u = attachedTo _cam;
        if (isNull _cam || {isNull _u}) exitWith {};
        if (!alive _u || {!([_u] call comspec_atak_native_fnc_hasDevice)}) exitWith {
            [] call comspec_atak_native_fnc_livecamStop;
            ["WARNING", "Live cam : flux perdu", 3, 30] call comspec_atak_native_fnc_notify;
            [{ ["LIVECAM"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
        };
        private _osd = uiNamespace getVariable ["COMSPEC_ATAK_LivecamOsd", controlNull];
        if (!isNull _osd) then {
            _osd ctrlSetStructuredText parseText format ["<t font='RobotoCondensedBold' color='#e5483a'>● LIVE</t>  <t font='RobotoCondensedBold'>%1</t><br/><t size='0.8' color='#c9d4cf'>%2 · %3 m · cap %4° · %5</t>",
                [_u, true] call comspec_atak_native_fnc_unitCallsign, [getPosASL _u, 8] call comspec_atak_native_fnc_gridRef, round (player distance _u), round getDir _u, [daytime, "HH:MM:SS"] call BIS_fnc_timeToString];
        };
    }, 1] call CBA_fnc_addPerFrameHandler];
};
true
