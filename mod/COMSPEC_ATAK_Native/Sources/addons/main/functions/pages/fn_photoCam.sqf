/*
    Appareil photo dans l'écran du téléphone (app Photo, fn_pagePhotos) : comme sur un vrai téléphone, l'app s'ouvre
    sur le viseur en direct. Une caméra posée devant les yeux du joueur (téléphone tenu devant soi) rend dans l'écran
    (render-to-texture, anneau de 4 cibles comspec_photocam0..3 : une cible coupée puis relancée donnait une image
    noire). Elle suit la vue du joueur ; glisser sur le viseur décale l'objectif (bras qui tourne, 70° au plus).
    Le plein écran (fn_photoMode) reste proposé par un bouton.
    La caméra ne tourne que tant que l'app Photo est affichée (vérifié à chaque image), sinon elle est détruite.
    Arma ne sait capturer que l'écran du joueur (screenshot) : au déclenchement, l'écran bascule ~0,3 s sur une caméra
    posée exactement comme celle du viseur (même pose, même zoom), téléphone et HUD masqués, puis la vue revient.
    Params :
      ["bind", proportions]  → texture r2t à poser dans un RscPicture (caméra créée au besoin), proportions = largeur / hauteur
      ["stop"]               coupe la caméra et sa boucle
      ["zoom", +1 | -1]      palier de zoom numérique (x1 à x8)
      ["flash"]              AUTO → FORCÉ → COUPÉ
      ["selfie"]             objectif avant / arrière (avant : arme rangée, bras droit tendu, fn_gestureSelfie)
      ["pose"]               tient ou relâche la pose du selfie (boucle de la caméra, toutes les 1,2 s)
      ["aim", [dx, dy]]      décale l'objectif (fractions de l'image glissées) ; ["aimReset"] le recentre
      ["shoot", légende]     prend la photo et l'envoie vers Athena (gardée sur le téléphone hors réseau)
      ["osd"]                rafraîchit l'incrustation du viseur (grille, cap, heure, zoom)
      ["expose"]             règle le gain du rendu selon la lumière (jour, pénombre, nuit)
*/
params [["_act", "bind"], ["_arg", 0]];
if (!hasInterface) exitWith { false };
private _st = uiNamespace getVariable ["COMSPEC_ATAK_PhotoSt", createHashMap];
uiNamespace setVariable ["COMSPEC_ATAK_PhotoSt", _st];
private _zoomSteps = [1, 1.5, 2, 3, 4, 6, 8];
private _zs = { params ["_v"]; [_v toFixed 1, str _v] select ((_v mod 1) isEqualTo 0) };

// Pose de l'objectif : [position ASL, direction, haut, champ].
private _pose = {
    private _selfie = _st getOrDefault ["camSelfie", false];
    private _zoom = _st getOrDefault ["camZoom", 1];
    private _oy = _st getOrDefault ["camYaw", 0];
    private _op = _st getOrDefault ["camPitch", 0];
    private _eye = eyePos player;
    private _pos = _eye;
    private _dir = [0, 1, 0];
    if (_selfie) then {
        // Bras tendu devant soi, téléphone tourné vers le visage ; glisser déplace le bras autour de la tête.
        private _a = (getDirVisual player) + _oy;
        private _p = 10 + _op;
        private _r = 0.6;
        _pos = _eye vectorAdd [_r * (sin _a) * (cos _p), _r * (cos _a) * (cos _p), _r * (sin _p)];
        _dir = vectorNormalized (_eye vectorDiff _pos);
    } else {
        // Vue du joueur, décalée du geste de visée dans le téléphone.
        private _v = getCameraViewDirection player;
        private _yaw = (((_v select 0) atan2 (_v select 1)) + _oy + 360) mod 360;
        private _pit = (((asin (((_v select 2) max -1) min 1)) + _op) max -80) min 80;
        _dir = [(sin _yaw) * (cos _pit), (cos _yaw) * (cos _pit), sin _pit];
        _pos = _eye vectorAdd (_dir vectorMultiply 0.3);
    };
    private _right = _dir vectorCrossProduct [0, 0, 1];
    private _up = if ((vectorMagnitude _right) < 0.01) then { [0, 1, 0] } else { vectorNormalized (_right vectorCrossProduct _dir) };
    [_pos, _dir, _up, ([0.75, 0.9] select _selfie) / _zoom]
};
private _place = {
    params ["_cam"];
    (call _pose) params ["_pos", "_dir", "_up", "_fov"];
    _cam setPosASL _pos;
    _cam setVectorDirAndUp [_dir, _up];
    _cam camSetFov _fov;
    _cam camCommit 0;
};
private _stop = {
    private _cam = missionNamespace getVariable ["COMSPEC_ATAK_PhotoCam", objNull];
    if (!isNull _cam) then { _cam cameraEffect ["Terminate", "Back", _cam getVariable ["r2t", ""]]; camDestroy _cam; };
    missionNamespace setVariable ["COMSPEC_ATAK_PhotoCam", objNull];
    ["pose", false] call comspec_atak_native_fnc_photoCam;
    private _pfh = missionNamespace getVariable ["COMSPEC_ATAK_PhotoCamPfh", -1];
    if (_pfh >= 0) then { [_pfh] call CBA_fnc_removePerFrameHandler; };
    missionNamespace setVariable ["COMSPEC_ATAK_PhotoCamPfh", -1];
};
// Exposition du viseur. Une caméra rendue dans une texture ne s'adapte pas à la lumière comme la vue du joueur :
// la nuit ou dans un intérieur sombre, l'image restait noire alors que la vue du joueur était éclaircie par le moteur.
// Correction de couleurs du rendu (setPiPEffect type 3) : gain fort la nuit, comme le mode nuit d'un téléphone.
// Renvoie le niveau choisi (0 jour, 1 pénombre, 2 nuit).
private _expose = {
    params ["_name"];
    private _lux = (getLightingAt player) param [1, 0];
    private _lvl = switch (true) do {
        case (sunOrMoon < 0.5 && {_lux < 15}): { 2 };
        case (sunOrMoon < 0.5 || {_lux < 40}): { 1 };
        default { 0 };
    };
    if (_lvl isNotEqualTo (missionNamespace getVariable ["COMSPEC_ATAK_PhotoCamLvl", -1]) || {_name isNotEqualTo (missionNamespace getVariable ["COMSPEC_ATAK_PhotoCamLvlFor", ""])}) then {
        missionNamespace setVariable ["COMSPEC_ATAK_PhotoCamLvl", _lvl];
        missionNamespace setVariable ["COMSPEC_ATAK_PhotoCamLvlFor", _name];
        _name setPiPEffect ([
            [0],
            [3, 1, 1.6, 1.05, 0.01, [0, 0, 0, 0], [1, 1, 1, 0.9], [0.299, 0.587, 0.114, 0]],
            [3, 1, 3.2, 1.1, 0.03, [0, 0, 0, 0], [1, 1, 1, 0.55], [0.299, 0.587, 0.114, 0]]
        ] select _lvl);
    };
    _lvl
};
private _redraw = { [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "PHOTOS") then { ["PHOTOS"] call comspec_atak_native_fnc_pageRender; }; }] call CBA_fnc_execNextFrame; };

switch (_act) do {
    case "bind": {
        private _aspect = [_arg, 1] select (!(_arg isEqualType 0) || {_arg <= 0});
        if !(isPiPEnabled) exitWith { "" };
        private _cam = missionNamespace getVariable ["COMSPEC_ATAK_PhotoCam", objNull];
        if (isNull _cam) then {
            private _n = ((missionNamespace getVariable ["COMSPEC_ATAK_PhotoCamN", 0]) + 1) mod 4;
            missionNamespace setVariable ["COMSPEC_ATAK_PhotoCamN", _n];
            private _name = format ["comspec_photocam%1", _n];
            _cam = "camera" camCreate (ASLToAGL (eyePos player));
            [_cam] call _place;
            _cam cameraEffect ["Internal", "Back", _name];
            _cam setVariable ["r2t", _name];
            missionNamespace setVariable ["COMSPEC_ATAK_PhotoCam", _cam];
            private _lvl = [_name] call _expose;
            ["INFO", "PHOTO", format ["Viseur : caméra %1, PiP %2, lumière %3 (soleil/lune %4), exposition %5", _name, isPiPEnabled, (getLightingAt player) param [1, 0], sunOrMoon, _lvl]] call comspec_atak_native_fnc_log;
        };
        if ((missionNamespace getVariable ["COMSPEC_ATAK_PhotoCamPfh", -1]) < 0) then {
            missionNamespace setVariable ["COMSPEC_ATAK_PhotoCamPfh", [{
                private _cam = missionNamespace getVariable ["COMSPEC_ATAK_PhotoCam", objNull];
                private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
                // Hors de l'app Photo, téléphone fermé, joueur mort ou plein écran ouvert : caméra coupée.
                if (isNull _cam || {(_s getOrDefault ["activePage", ""]) isNotEqualTo "PHOTOS"} || {isNull (uiNamespace getVariable ["COMSPEC_ATAK_Display", displayNull])}
                    || {!alive player} || {uiNamespace getVariable ["COMSPEC_ATAK_PhotoMode", false]}) exitWith {
                    ["INFO", "PHOTO", format ["Viseur coupé : caméra %1, page %2, écran %3, plein écran %4", !isNull _cam, _s getOrDefault ["activePage", ""], !isNull (uiNamespace getVariable ["COMSPEC_ATAK_Display", displayNull]), uiNamespace getVariable ["COMSPEC_ATAK_PhotoMode", false]]] call comspec_atak_native_fnc_log;
                    ["stop"] call comspec_atak_native_fnc_photoCam;
                };
                // Pendant le déclenchement, la caméra de prise de vue reste figée sur la pose du viseur.
                if (uiNamespace getVariable ["COMSPEC_ATAK_PhotoShooting", false]) exitWith {};
                ["place"] call comspec_atak_native_fnc_photoCam;
                if (diag_tickTime >= (missionNamespace getVariable ["COMSPEC_ATAK_PhotoCamPoseAt", 0])) then {
                    missionNamespace setVariable ["COMSPEC_ATAK_PhotoCamPoseAt", diag_tickTime + 1.2];
                    ["pose", true] call comspec_atak_native_fnc_photoCam;
                };
                if (diag_tickTime >= (missionNamespace getVariable ["COMSPEC_ATAK_PhotoCamOsdAt", 0])) then {
                    missionNamespace setVariable ["COMSPEC_ATAK_PhotoCamOsdAt", diag_tickTime + 0.5];
                    ["expose"] call comspec_atak_native_fnc_photoCam;
                    ["osd"] call comspec_atak_native_fnc_photoCam;
                };
            }, 0] call CBA_fnc_addPerFrameHandler];
        };
        // Largeur et hauteur en puissances de 2, dans le sens du viseur.
        format ["#(argb,%3,%4,1)r2t(%1,%2)", _cam getVariable ["r2t", "comspec_photocam0"], _aspect, [512, 1024] select (_aspect >= 1), [1024, 512] select (_aspect >= 1)]
    };
    case "stop": { call _stop; true };
    case "expose": {
        private _cam = missionNamespace getVariable ["COMSPEC_ATAK_PhotoCam", objNull];
        if (isNull _cam) exitWith { -1 };
        [_cam getVariable ["r2t", ""]] call _expose
    };
    // Boucle par image : la caméra suit la vue du joueur (même pose que la prise de vue).
    case "place": {
        private _cam = missionNamespace getVariable ["COMSPEC_ATAK_PhotoCam", objNull];
        if (!isNull _cam) then { [_cam] call _place; };
        true
    };
    case "zoom": {
        private _z = _st getOrDefault ["camZoom", 1];
        private _i = 0;
        { if (_z >= _x - 0.01) then { _i = _forEachIndex; }; } forEach _zoomSteps;
        _i = ((_i + ([-1, 1] select (_arg > 0))) max 0) min ((count _zoomSteps) - 1);
        _st set ["camZoom", _zoomSteps select _i];
        playSound "ClickSoft";
        call _redraw;
        true
    };
    case "flash": {
        private _modes = ["AUTO", "ON", "OFF"];
        _st set ["flash", _modes select ((((_modes find (_st getOrDefault ["flash", "AUTO"])) max 0) + 1) mod 3)];
        playSound "ClickSoft";
        call _redraw;
        true
    };
    case "selfie": {
        _st set ["camSelfie", !(_st getOrDefault ["camSelfie", false])];
        _st set ["camYaw", 0];
        _st set ["camPitch", 0];
        ["pose", true] call comspec_atak_native_fnc_photoCam;
        playSound "ClickSoft";
        call _redraw;
        true
    };
    // Selfie : arme rangée et bras droit tendu, téléphone face au visage (fn_gestureSelfie) ; objectif arrière,
    // app quittée ou caméra coupée : bras baissé et arme ressortie. Le plein écran (fn_photoMode) reprend la main
    // avec l'arme gardée de côté, qu'il ressortira à sa fermeture.
    case "pose": {
        private _want = _arg isEqualTo true && {_st getOrDefault ["camSelfie", false]} && {alive player} && {vehicle player isEqualTo player};
        private _posed = missionNamespace getVariable ["COMSPEC_ATAK_PhotoCamPosed", false];
        if (_want) then {
            if (!_posed) then {
                missionNamespace setVariable ["COMSPEC_ATAK_PhotoCamPosed", true];
                missionNamespace setVariable ["COMSPEC_ATAK_PhotoCamWeapon", currentWeapon player];
                if ((currentWeapon player) isNotEqualTo "") then { player action ["SwitchWeapon", player, player, 299]; };
            };
            [player, "selfie"] call comspec_atak_native_fnc_gestureSelfie;
        } else {
            if (_posed) then {
                missionNamespace setVariable ["COMSPEC_ATAK_PhotoCamPosed", false];
                private _w = missionNamespace getVariable ["COMSPEC_ATAK_PhotoCamWeapon", ""];
                if (uiNamespace getVariable ["COMSPEC_ATAK_PhotoMode", false]) then {
                    private _ctx = uiNamespace getVariable ["COMSPEC_ATAK_PhotoCtx", []];
                    if (count _ctx > 2 && {(_ctx select 2) isEqualTo ""}) then { _ctx set [2, _w]; };
                } else {
                    [player, "exit"] call comspec_atak_native_fnc_gestureSelfie;
                    if (_w isNotEqualTo "" && {alive player} && {vehicle player isEqualTo player}) then { player selectWeapon _w; };
                };
            };
        };
        true
    };
    case "aim": {
        _arg params [["_dx", 0], ["_dy", 0]];
        // Glisser d'une largeur d'image tourne d'un champ : le point saisi reste sous le doigt.
        private _fov = ([0.75, 0.9] select (_st getOrDefault ["camSelfie", false])) / (_st getOrDefault ["camZoom", 1]);
        private _k = 2 * atan _fov;
        _st set ["camYaw", (((_st getOrDefault ["camYaw", 0]) - _dx * _k) max -70) min 70];
        _st set ["camPitch", (((_st getOrDefault ["camPitch", 0]) + _dy * _k) max -50) min 50];
        true
    };
    case "aimReset": { _st set ["camYaw", 0]; _st set ["camPitch", 0]; playSound "ClickSoft"; true };
    case "osd": {
        private _osd = uiNamespace getVariable ["COMSPEC_ATAK_PhotoCamOsd", controlNull];
        if (isNull _osd) exitWith { false };
        private _selfie = _st getOrDefault ["camSelfie", false];
        (call _pose) params ["", "_dir"];
        _osd ctrlSetStructuredText parseText ((format ["<t size='0.8' font='EtelkaMonospacePro' color='#e8eee9' shadow='2'>%1</t><t size='0.8' align='right' font='EtelkaMonospacePro' color='#e8eee9' shadow='2'>%2°  %3</t><br/><t size='0.8' font='RobotoCondensedBold' color='%5' shadow='2'>%4</t><t size='0.8' align='right' font='RobotoCondensedBold' color='#5cc76b' shadow='2'>x%6</t>",
            [getPosASL player, 8] call comspec_atak_native_fnc_gridRef, round ((((_dir select 0) atan2 (_dir select 1)) + 360) mod 360), [dayTime, "HH:MM"] call BIS_fnc_timeToString,
            (["OBJECTIF ARRIÈRE", "SELFIE"] select _selfie) + (["", "  · BASSE LUMIÈRE", "  · MODE NUIT"] select ((missionNamespace getVariable ["COMSPEC_ATAK_PhotoCamLvl", 0]) max 0 min 2)), ["#c9d4cf", "#f2d36b"] select _selfie, [_st getOrDefault ["camZoom", 1]] call _zs]) call comspec_atak_native_fnc_tr);
        true
    };
    case "shoot": {
        private _caption = [_arg, ""] select !(_arg isEqualType "");
        if !([] call comspec_atak_native_fnc_bridge) exitWith { ["WARNING", "Photos : COMSPEC Overwatch doit être chargé pour l'envoi vers ATAK web", 4, 30] call comspec_atak_native_fnc_notify; false };
        if !(missionNamespace getVariable ["COMSPEC_AthenaReady", false]) exitWith { ["WARNING", "Photos : connectez-vous d'abord à Athena (app Athena)", 4, 30] call comspec_atak_native_fnc_notify; false };
        if (uiNamespace getVariable ["COMSPEC_ATAK_PhotoShooting", false] || {diag_tickTime - (uiNamespace getVariable ["COMSPEC_ATAK_PhotoLast", -10]) < 2}) exitWith {
            ["INFO", "Patientez : enregistrement de la photo précédente…", 2, 20] call comspec_atak_native_fnc_notify;
            false
        };
        uiNamespace setVariable ["COMSPEC_ATAK_PhotoLast", diag_tickTime];
        if (_caption isEqualTo "") then { _caption = format ["%1 · %2 · %3°", [player] call comspec_atak_native_fnc_unitCallsign, [player] call comspec_atak_native_fnc_gridRef, round getDir player]; };
        // Flash : forcé, coupé, ou automatique la nuit et dans un intérieur sombre (même règle que le plein écran).
        private _eye = eyePos player;
        private _dark = (sunOrMoon < 0.5) || {
            ((lineIntersectsSurfaces [_eye, _eye vectorAdd [0, 0, 15], player, objNull, true, 1, "GEOM", "NONE"]) isNotEqualTo [])
            && {((getLightingAt player) select 1) < 8}
        };
        private _fire = switch (_st getOrDefault ["flash", "AUTO"]) do { case "ON": { true }; case "OFF": { false }; default { _dark }; };
        [_caption, _fire, call _pose] spawn {
            params ["_caption", "_fire", "_pose"];
            disableSerialization;
            _pose params ["_pos", "_dir", "_up", "_fov"];
            uiNamespace setVariable ["COMSPEC_ATAK_PhotoShooting", true];
            private _hud = shownHUD;
            if (!isNil "ace_interact_menu_fnc_hideMenu") then { [] call ace_interact_menu_fnc_hideMenu; };
            // Écran du téléphone masqué le temps du cliché (il serait capturé par-dessus l'image).
            private _phone = uiNamespace getVariable ["COMSPEC_ATAK_Display", displayNull];
            private _hidden = [];
            if (!isNull _phone) then { _hidden = (allControls _phone) select { ctrlShown _x }; { _x ctrlShow false; } forEach _hidden; };
            private _shot = "camera" camCreate (ASLToAGL _pos);
            _shot cameraEffect ["Internal", "Back"];
            _shot setPosASL _pos;
            _shot setVectorDirAndUp [_dir, _up];
            _shot camSetFov _fov;
            _shot camCommit 0;
            showHUD false;
            private _light = objNull;
            if (_fire) then {
                _light = "#lightpoint" createVehicleLocal (ASLToAGL _pos);
                _light setPosASL _pos;
                _light setLightColor [1, 0.97, 0.92];
                _light setLightAmbient [0.12, 0.12, 0.11];
                _light setLightBrightness ([0.9, 2.2] select (sunOrMoon < 0.5));
                _light setLightAttenuation [0.5, 0, 0, 1.4, 8, 10];
                _light setLightDayLight (sunOrMoon >= 0.5);
            };
            uiSleep 0.3;
            // Débit simulé : sans réseau, la photo reste sur le téléphone (app Galerie pour l'envoyer plus tard).
            private _noNet = ((([] call comspec_atak_native_fnc_linkQuality) getOrDefault ["bars", 0]) isEqualTo 0);
            private _ok = if (_noNet) then {
                screenshot format ["comspec_atak_%1.png", floor (diag_tickTime * 10)];
                false
            } else { ["", _caption, "CTAB", "", false, false, true] call comspec_overwatch_connect_fnc_captureReconImage };
            // Le fichier est écrit en fin d'image : on garde la vue un instant.
            uiSleep 0.15;
            if (!isNull _light) then { deleteVehicle _light; };
            _shot cameraEffect ["Terminate", "Back"];
            camDestroy _shot;
            { if (!isNull _x) then { _x ctrlShow true; }; } forEach _hidden;
            showHUD _hud;
            uiNamespace setVariable ["COMSPEC_ATAK_PhotoShooting", false];
            // Éclair blanc sur le viseur, comme un déclencheur.
            private _fl = uiNamespace getVariable ["COMSPEC_ATAK_PhotoCamFlash", controlNull];
            if (!isNull _fl) then {
                _fl ctrlSetBackgroundColor [1, 1, 1, [0.6, 0.9] select _fire];
                _fl ctrlCommit 0;
                _fl ctrlSetBackgroundColor [1, 1, 1, 0];
                _fl ctrlCommit ([0.35, 0.6] select _fire);
            };
            playSound "ClickSoft";
            private _n = (missionNamespace getVariable ["COMSPEC_ATAK_PhotoCamShots", 0]) + 1;
            missionNamespace setVariable ["COMSPEC_ATAK_PhotoCamShots", _n];
            if (_noNet) then {
                ["WARNING", "Pas de réseau : photo gardée sur le téléphone, à envoyer depuis l'app Galerie", 5, 40] call comspec_atak_native_fnc_notify;
            } else {
                [["WARNING", "SUCCESS"] select _ok, ["Photo non envoyée : voir l'app Galerie, onglet ENVOIS", "Photo en cours d'envoi vers Athena"] select _ok, 3, 30] call comspec_atak_native_fnc_notify;
            };
            uiNamespace setVariable ["COMSPEC_ATAK_PhotoLibAt", nil];
            [{ if (((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["activePage", ""]) isEqualTo "PHOTOS") then { ["PHOTOS"] call comspec_atak_native_fnc_pageRender; }; }, [], 0.3] call CBA_fnc_waitAndExecute;
        };
        true
    };
    default { false };
};
