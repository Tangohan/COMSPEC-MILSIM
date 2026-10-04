/*
    Mode photo : viseur plein écran, on se déplace comme à pied (arme rangée, téléphone tenu devant soi).
    Clic gauche : photo envoyée sur ATAK web (plusieurs possibles). R : selfie (téléphone à bout de bras, tourné vers soi,
    cadrage à la souris dans la limite du bras). Molette ou + / - : zoom numérique x1 à x8. F : flash AUTO / OUI / NON.
    Espace : quitter et reprendre le téléphone.
    Params : ["ENTER" | "SHOOT" | "SELFIE" | "ZOOM" | "FLASH" | "MOUSE" | "FRAME" | "HUD" | "EXIT", légende, argument]
*/
params [["_mode", "ENTER"], ["_caption", ""], ["_arg", 0]];
if (!hasInterface) exitWith { false };
private _active = uiNamespace getVariable ["COMSPEC_ATAK_PhotoMode", false];
// État de la prise de vue : zoom, selfie, cadrage, transition, caméra scriptée.
private _st = uiNamespace getVariable ["COMSPEC_ATAK_PhotoSt", createHashMap];
uiNamespace setVariable ["COMSPEC_ATAK_PhotoSt", _st];
private _zoomSteps = [1, 1.5, 2, 3, 4, 6, 8];
// Position et visée actuelles de l'objectif (point de départ d'une transition).
private _curView = {
    private _c = _st getOrDefault ["cam", objNull];
    if (!isNull _c) exitWith { [getPosASL _c, vectorDir _c] };
    private _d = getCameraViewDirection player;
    [(eyePos player) vectorAdd (_d vectorMultiply 0.25), _d]
};
switch (toUpper _mode) do {
    case "ENTER": {
        if (_active) exitWith { false };
        if !([] call comspec_atak_native_fnc_bridge) exitWith { ["WARNING", "Photos : COMSPEC Overwatch doit être chargé pour l'envoi vers ATAK web", 4, 30] call comspec_atak_native_fnc_notify; false };
        if !(missionNamespace getVariable ["COMSPEC_AthenaReady", false]) exitWith { ["WARNING", "Photos : connectez-vous d'abord à Athena (app Athena)", 4, 30] call comspec_atak_native_fnc_notify; false };
        private _s = uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap];
        uiNamespace setVariable ["COMSPEC_ATAK_PhotoMode", true];
        uiNamespace setVariable ["COMSPEC_ATAK_PhotoCtx", [_s getOrDefault ["interactive", false], uiNamespace getVariable ["COMSPEC_ATAK_HudWanted", false], currentWeapon player, _caption, 0]];
        uiNamespace setVariable ["COMSPEC_ATAK_HudWanted", false];
        { _st set _x; } forEach [["zoom", 1], ["selfie", false], ["yaw", 0], ["pitch", 10], ["tr0", -1], ["cam", objNull], ["fov", 0.75], ["nextCheck", 0], ["nextPose", 0]];
        if !((_st getOrDefault ["flash", ""]) in ["AUTO", "ON", "OFF"]) then { _st set ["flash", "AUTO"]; };
        [] call comspec_atak_native_fnc_close;
        // Arme rangée : le clic gauche ne tire pas.
        if ((currentWeapon player) isNotEqualTo "") then { player action ["SwitchWeapon", player, player, 299]; };
        ("COMSPEC_ATAK_Camera" call BIS_fnc_rscLayer) cutRsc ["COMSPEC_RscTitleCamera", "PLAIN", 0, false];
        private _d46 = findDisplay 46;
        uiNamespace setVariable ["COMSPEC_ATAK_PhotoEH", [
            _d46 displayAddEventHandler ["KeyDown", { params ["", "_key"];
                if (_key isEqualTo 0x39) exitWith { ["EXIT"] call comspec_atak_native_fnc_photoMode; true };
                if (_key isEqualTo 0x13) exitWith { ["SELFIE"] call comspec_atak_native_fnc_photoMode; true };
                if (_key isEqualTo 0x21) exitWith { ["FLASH"] call comspec_atak_native_fnc_photoMode; true };
                if (_key in [0x0D, 0x4E]) exitWith { ["ZOOM", "", 1] call comspec_atak_native_fnc_photoMode; true };
                if (_key in [0x0C, 0x4A]) exitWith { ["ZOOM", "", -1] call comspec_atak_native_fnc_photoMode; true };
                false }],
            _d46 displayAddEventHandler ["MouseButtonDown", { params ["", "_button"]; if (_button isEqualTo 0 && {isNull curatorCamera} && {!visibleMap} && {isNull (findDisplay 49)}) then { ["SHOOT"] call comspec_atak_native_fnc_photoMode; }; false }],
            _d46 displayAddEventHandler ["MouseZChanged", { params ["", "_z"]; if (_z isNotEqualTo 0) then { ["ZOOM", "", [-1, 1] select (_z > 0)] call comspec_atak_native_fnc_photoMode; }; false }],
            _d46 displayAddEventHandler ["MouseMoving", { params ["", "_dx", "_dy"]; ["MOUSE", "", [_dx, _dy]] call comspec_atak_native_fnc_photoMode; false }]
        ]];
        // Une boucle par image : objectif (zoom, selfie, transitions), geste du bras, sortie automatique.
        uiNamespace setVariable ["COMSPEC_ATAK_PhotoPFH", [{ ["FRAME"] call comspec_atak_native_fnc_photoMode; }, 0] call CBA_fnc_addPerFrameHandler];
        [{ ["HUD"] call comspec_atak_native_fnc_photoMode; }] call CBA_fnc_execNextFrame;
        true
    };
    case "FRAME": {
        if (!_active) exitWith {};
        // Sortie automatique si le joueur meurt, monte en véhicule ou perd le téléphone.
        private _quit = false;
        if (diag_tickTime >= (_st getOrDefault ["nextCheck", 0])) then {
            _st set ["nextCheck", diag_tickTime + 0.5];
            _quit = !alive player || {vehicle player isNotEqualTo player} || {!([] call comspec_atak_native_fnc_hasDevice)};
        };
        if (_quit) exitWith { ["EXIT"] call comspec_atak_native_fnc_photoMode; };
        // Bras tendu, téléphone devant soi (geste vanilla relancé tant que le mode photo dure).
        if (diag_tickTime >= (_st getOrDefault ["nextPose", 0])) then {
            _st set ["nextPose", diag_tickTime + 1.2];
            if (((toLower (gestureState player)) find "point") < 0) then { player playActionNow "gesturePoint"; };
        };
        private _selfie = _st getOrDefault ["selfie", false];
        private _zoom = _st getOrDefault ["zoom", 1];
        private _eye = eyePos player;
        private _tPos = _eye;
        private _tDir = [0, 1, 0];
        if (_selfie) then {
            // Corps figé pendant le selfie : la souris déplace le bras, pas le joueur.
            private _h = _st getOrDefault ["heading", getDir player];
            if ((abs ((((getDir player) - _h) + 540) mod 360 - 180)) > 0.5) then { player setDir _h; };
            private _a = _h + (_st getOrDefault ["yaw", 0]);
            private _p = _st getOrDefault ["pitch", 10];
            private _r = 0.6;
            _tPos = _eye vectorAdd [_r * (sin _a) * (cos _p), _r * (cos _a) * (cos _p), _r * (sin _p)];
            _tDir = vectorNormalized (_eye vectorDiff _tPos);
        } else {
            _tDir = getCameraViewDirection player;
            _tPos = _eye vectorAdd (_tDir vectorMultiply 0.25);
        };
        // Transition douce (passage selfie / objectif arrière).
        private _k = 1;
        private _t0 = _st getOrDefault ["tr0", -1];
        if (_t0 >= 0) then {
            _k = ((diag_tickTime - _t0) / 0.45) min 1;
            private _e = _k * _k * (3 - 2 * _k);
            _tPos = ((_st get "trPos") vectorMultiply (1 - _e)) vectorAdd (_tPos vectorMultiply _e);
            _tDir = vectorNormalized (((_st get "trDir") vectorMultiply (1 - _e)) vectorAdd (_tDir vectorMultiply _e));
            if (_k >= 1) then { _st set ["tr0", -1]; };
        };
        private _cam = _st getOrDefault ["cam", objNull];
        // Vue naturelle sans zoom ni selfie : pas de caméra scriptée.
        if (!_selfie && {_zoom < 1.01} && {_k >= 1} && {abs ((_st getOrDefault ["fov", 0.75]) - 0.75) < 0.01}) exitWith {
            if (!isNull _cam) then { _cam cameraEffect ["terminate", "back"]; camDestroy _cam; _st set ["cam", objNull]; };
        };
        if (isNull _cam) then {
            _cam = "camera" camCreate (ASLToAGL _tPos);
            _cam cameraEffect ["internal", "back"];
            _st set ["cam", _cam];
            _st set ["fov", 0.75];
        };
        private _fovT = ([0.75, 0.9] select _selfie) / _zoom;
        private _fov = _st getOrDefault ["fov", 0.75];
        _fov = _fov + (_fovT - _fov) * ((diag_deltaTime * 10) min 1);
        if ((abs (_fov - _fovT)) < 0.002) then { _fov = _fovT; };
        _st set ["fov", _fov];
        _cam camSetPos (ASLToAGL _tPos);
        _cam camSetFov _fov;
        _cam camCommit 0;
        _cam setPosASL _tPos;
        private _right = _tDir vectorCrossProduct [0, 0, 1];
        private _up = if ((vectorMagnitude _right) < 0.01) then { [0, 1, 0] } else { vectorNormalized (_right vectorCrossProduct _tDir) };
        _cam setVectorDirAndUp [_tDir, _up];
    };
    case "MOUSE": {
        // Cadrage du selfie : le bras tourne autour de la tête, dans la limite de ce qu'un bras atteint.
        if (!_active || {!(_st getOrDefault ["selfie", false])}) exitWith { false };
        _arg params [["_dx", 0], ["_dy", 0]];
        _st set ["yaw", (((_st getOrDefault ["yaw", 0]) - _dx * 2.5) max -75) min 75];
        _st set ["pitch", (((_st getOrDefault ["pitch", 10]) - _dy * 2.5) max -20) min 45];
        true
    };
    case "ZOOM": {
        if (!_active) exitWith { false };
        private _z = _st getOrDefault ["zoom", 1];
        private _i = 0;
        { if (_z >= _x - 0.01) then { _i = _forEachIndex; }; } forEach _zoomSteps;
        _i = ((_i + ([-1, 1] select (_arg > 0))) max 0) min ((count _zoomSteps) - 1);
        _st set ["zoom", _zoomSteps select _i];
        ["HUD"] call comspec_atak_native_fnc_photoMode;
        true
    };
    case "FLASH": {
        if (!_active) exitWith { false };
        private _modes = ["AUTO", "ON", "OFF"];
        _st set ["flash", _modes select ((((_modes find (_st getOrDefault ["flash", "AUTO"])) max 0) + 1) mod 3)];
        playSound "ClickSoft";
        ["HUD"] call comspec_atak_native_fnc_photoMode;
        true
    };
    case "HUD": {
        private _cam = uiNamespace getVariable ["COMSPEC_ATAK_CameraDisplay", displayNull];
        if (isNull _cam) exitWith { false };
        private _selfie = _st getOrDefault ["selfie", false];
        private _z = _st getOrDefault ["zoom", 1];
        private _fl = _st getOrDefault ["flash", "AUTO"];
        (_cam displayCtrl 5) ctrlSetText format ["%1     ZOOM x%2     FLASH %3",
            ["OBJECTIF ARRIÈRE", "SELFIE"] select _selfie, [_z toFixed 1, str _z] select ((_z mod 1) isEqualTo 0),
            createHashMapFromArray [["AUTO", "AUTO"], ["ON", "FORCÉ"], ["OFF", "COUPÉ"]] get _fl];
        (_cam displayCtrl 3) ctrlSetText ([
            "CLIC GAUCHE : photo     MOLETTE ou + / - : zoom     F : flash     R : selfie     ESPACE : quitter",
            "CLIC GAUCHE : photo     SOURIS : cadrer     MOLETTE : zoom     F : flash     R : objectif arrière     ESPACE : quitter"
        ] select _selfie);
        true
    };
    case "SELFIE": {
        if (!_active) exitWith { false };
        // Bascule animée : l'objectif glisse de la position actuelle vers la nouvelle (0,45 s).
        (call _curView) params ["_p", "_d"];
        _st set ["trPos", _p];
        _st set ["trDir", _d];
        _st set ["tr0", diag_tickTime];
        _st set ["selfie", !(_st getOrDefault ["selfie", false])];
        _st set ["yaw", 0];
        _st set ["pitch", 10];
        _st set ["heading", getDir player];
        playSound "ClickSoft";
        ["HUD"] call comspec_atak_native_fnc_photoMode;
        true
    };
    case "SHOOT": {
        if (!_active) exitWith { false };
        if (diag_tickTime - (uiNamespace getVariable ["COMSPEC_ATAK_PhotoLast", -10]) < 3) exitWith {
            private _cam = uiNamespace getVariable ["COMSPEC_ATAK_CameraDisplay", displayNull];
            if (!isNull _cam) then { (_cam displayCtrl 4) ctrlSetText "Patientez : enregistrement de la photo précédente…"; };
            false
        };
        uiNamespace setVariable ["COMSPEC_ATAK_PhotoLast", diag_tickTime];
        // Flash : forcé, coupé, ou automatique la nuit et dans un intérieur sombre (toit au-dessus, peu de lumière).
        private _eye = eyePos player;
        private _dark = (sunOrMoon < 0.5) || {
            ((lineIntersectsSurfaces [_eye, _eye vectorAdd [0, 0, 15], player, objNull, true, 1, "GEOM", "NONE"]) isNotEqualTo [])
            && {((getLightingAt player) select 1) < 40}
        };
        private _fire = switch (_st getOrDefault ["flash", "AUTO"]) do { case "ON": { true }; case "OFF": { false }; default { _dark }; };
        ((call _curView) select 0) params ["_px", "_py", "_pz"];
        [_fire, [_px, _py, _pz]] spawn {
            params ["_fire", "_phone"];
            disableSerialization;
            private _ctx = uiNamespace getVariable ["COMSPEC_ATAK_PhotoCtx", []];
            private _caption = _ctx param [3, ""];
            if (_caption isEqualTo "") then { _caption = format ["%1 · %2 · %3°", [player] call comspec_atak_native_fnc_unitCallsign, [player] call comspec_atak_native_fnc_gridRef, round getDir player]; };
            private _cam = uiNamespace getVariable ["COMSPEC_ATAK_CameraDisplay", displayNull];
            // Le viseur ne doit pas apparaître sur la photo.
            if (!isNull _cam) then { { (_cam displayCtrl _x) ctrlShow false; } forEach [1, 3, 4, 5]; };
            // Éclair réel au téléphone, très bref (visible sur la capture).
            private _light = objNull;
            if (_fire) then {
                _light = "#lightpoint" createVehicleLocal (ASLToAGL _phone);
                _light setPosASL _phone;
                _light setLightColor [1, 0.98, 0.95];
                _light setLightAmbient [1, 0.98, 0.95];
                _light setLightBrightness 6;
                _light setLightAttenuation [0.5, 0, 0, 0.6, 12, 15];
                _light setLightDayLight true;
            };
            uiSleep 0.12;
            // Débit simulé : sans réseau, la photo reste sur le poste (Photos > Bibliothèque pour la retransmettre).
            private _noNet = (([] call comspec_atak_native_fnc_linkQuality) get "bars") isEqualTo 0;
            private _ok = if (_noNet) then {
                screenshot format ["comspec_atak_%1.png", floor (diag_tickTime * 10)];
                ["WARNING", "Pas de réseau : photo gardée sur le poste, à retransmettre depuis Photos > Bibliothèque", 5, 40] call comspec_atak_native_fnc_notify;
                false
            } else { ["", _caption, "CTAB", "", false, false, true] call comspec_overwatch_connect_fnc_captureReconImage };
            uiSleep 0.08;
            if (!isNull _light) then { deleteVehicle _light; };
            if (!isNull _cam) then {
                { (_cam displayCtrl _x) ctrlShow true; } forEach [1, 3, 4, 5];
                private _flash = _cam displayCtrl 2;
                _flash ctrlSetBackgroundColor [1, 1, 1, [0.55, 0.9] select _fire];
                _flash ctrlCommit 0;
                _flash ctrlSetBackgroundColor [1, 1, 1, 0];
                _flash ctrlCommit ([0.35, 0.6] select _fire);
                private _n = (_ctx param [4, 0]) + ([0, 1] select _ok);
                _ctx set [4, _n];
                (_cam displayCtrl 4) ctrlSetText format ["%1%2", ["Photo non envoyée : voir l'app Photos", format ["%1 photo(s) en cours d'envoi vers Athena", _n]] select _ok, ["", " · flash"] select _fire];
            };
            playSound "ClickSoft";
        };
        true
    };
    case "EXIT": {
        if (!_active) exitWith { false };
        uiNamespace setVariable ["COMSPEC_ATAK_PhotoMode", false];
        private _cam = _st getOrDefault ["cam", objNull];
        if (!isNull _cam) then { _cam cameraEffect ["terminate", "back"]; camDestroy _cam; };
        _st set ["cam", objNull];
        _st set ["selfie", false];
        _st set ["zoom", 1];
        private _d46 = findDisplay 46;
        (uiNamespace getVariable ["COMSPEC_ATAK_PhotoEH", []]) params [["_k", -1], ["_m", -1], ["_z", -1], ["_mv", -1]];
        if (_k >= 0) then { _d46 displayRemoveEventHandler ["KeyDown", _k]; };
        if (_m >= 0) then { _d46 displayRemoveEventHandler ["MouseButtonDown", _m]; };
        if (_z >= 0) then { _d46 displayRemoveEventHandler ["MouseZChanged", _z]; };
        if (_mv >= 0) then { _d46 displayRemoveEventHandler ["MouseMoving", _mv]; };
        uiNamespace setVariable ["COMSPEC_ATAK_PhotoEH", []];
        [uiNamespace getVariable ["COMSPEC_ATAK_PhotoPFH", -1]] call CBA_fnc_removePerFrameHandler;
        // Bras baissé : le geste n'est plus relancé et se termine de lui-même.
        ("COMSPEC_ATAK_Camera" call BIS_fnc_rscLayer) cutText ["", "PLAIN"];
        (uiNamespace getVariable ["COMSPEC_ATAK_PhotoCtx", []]) params [["_interactive", true], ["_hud", false], ["_weapon", ""]];
        if (_weapon isNotEqualTo "" && {alive player} && {vehicle player isEqualTo player}) then { player selectWeapon _weapon; };
        if (alive player && {[] call comspec_atak_native_fnc_hasDevice}) then {
            uiNamespace setVariable ["COMSPEC_ATAK_HudWanted", _hud];
            [{
                params ["_interactive"];
                [_interactive] call comspec_atak_native_fnc_open;
                [{ ["PHOTOS"] call comspec_atak_native_fnc_navigate; }] call CBA_fnc_execNextFrame;
            }, [_interactive || {!_hud}]] call CBA_fnc_execNextFrame;
        };
        true
    };
    default { false };
}
