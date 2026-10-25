/*
    Mode photo : viseur plein écran, on se déplace comme à pied (arme rangée, téléphone tenu devant soi).
    Sans zoom, vue du joueur gérée par le jeu (pas et souris) ; zoom (caméra scriptée) seulement à l'arrêt : se mettre
    à marcher (ZQSD / flèches) le ramène à x1, sans retour automatique. Avec la caméra scriptée active même à x1, le
    joueur restait planté (retour de TTRD, 18/10 : « on peut plus se déplacer »), et un zoom qui sautait à chaque
    pas était pénible.
    Clic gauche : photo envoyée sur ATAK web (plusieurs possibles). R : selfie (téléphone à bout de bras, tourné vers soi,
    cadrage à la souris dans la limite du bras). Molette ou + / - : zoom numérique x1 à x8, visée à la souris pendant
    le zoom (caméra scriptée). F : flash AUTO / OUI / NON (AUTO : nuit ou intérieur très sombre).
    B : type de flou automatique des visages (gaussien, mouvement, pixel, carré noir, aucun ; fn_photoFaces), affiché
    dans la barre des raccourcis. Objectif arrière : bras baissé par défaut (CBA « Photo : téléphone levé en main »).
    Espace : quitter et reprendre le téléphone. H (ou F1) : afficher / replier la barre des raccourcis, repliée
    d'elle-même quelques secondes après l'ouverture.
    Params : ["ENTER" | "SHOOT" | "SELFIE" | "ZOOM" | "FLASH" | "BLUR" | "HELP" | "MOUSE" | "FRAME" | "HUD" | "EXIT", légende, argument]
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
        { _st set _x; } forEach [["zoom", 1], ["selfie", false], ["yaw", 0], ["pitch", 10], ["tr0", -1], ["cam", objNull], ["fov", 0.75], ["nextCheck", 0], ["nextPose", 0], ["helpOpen", true], ["helpPin", false], ["helpUntil", diag_tickTime + 8], ["helpDrawn", -1]];
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
                // H ou F1 : barre des raccourcis affichée / repliée.
                if (_key in [0x23, 0x3B]) exitWith { ["HELP"] call comspec_atak_native_fnc_photoMode; true };
                if (_key isEqualTo 0x21) exitWith {
                    ["FLASH"] call comspec_atak_native_fnc_photoMode;
                    // Filet : si le jeu a quand même vu F (mode de tir), l'arme est rangée de nouveau.
                    [{ if ((uiNamespace getVariable ["COMSPEC_ATAK_PhotoMode", false]) && {(currentWeapon player) isNotEqualTo ""}) then { player action ["SwitchWeapon", player, player, 299]; }; }, [], 0.1] call CBA_fnc_waitAndExecute;
                    true
                };
                // B : type de flou des visages.
                if (_key isEqualTo 0x30) exitWith { ["BLUR"] call comspec_atak_native_fnc_photoMode; true };
                // T : marque la cible sous le réticule (cadre jaune « VISÉ »).
                if (_key isEqualTo 0x14) exitWith { ["mark", "PHONE"] call comspec_atak_native_fnc_lockAction; true };
                if (_key in [0x0D, 0x4E]) exitWith { ["ZOOM", "", 1] call comspec_atak_native_fnc_photoMode; true };
                if (_key in [0x0C, 0x4A]) exitWith { ["ZOOM", "", -1] call comspec_atak_native_fnc_photoMode; true };
                false }],
            _d46 displayAddEventHandler ["MouseButtonDown", { params ["", "_button"]; if (_button isEqualTo 0 && {isNull curatorCamera} && {!visibleMap} && {isNull (findDisplay 49)}) then { ["SHOOT"] call comspec_atak_native_fnc_photoMode; }; false }],
            _d46 displayAddEventHandler ["MouseZChanged", { params ["", "_z"]; if (_z isNotEqualTo 0) then { ["ZOOM", "", [-1, 1] select (_z > 0)] call comspec_atak_native_fnc_photoMode; }; false }],
            _d46 displayAddEventHandler ["MouseMoving", { params ["", "_dx", "_dy"]; ["MOUSE", "", [_dx, _dy]] call comspec_atak_native_fnc_photoMode; false }]
        ]];
        // Cadres des cibles marquées et de la cible visée, dessinés dans la scène.
        uiNamespace setVariable ["COMSPEC_ATAK_PhotoDraw", addMissionEventHandler ["Draw3D", {
            if !(uiNamespace getVariable ["COMSPEC_ATAK_PhotoMode", false]) exitWith {};
            // Pas de cadres dans la scène pendant la capture (ils sortaient en traits blancs sur la photo).
            if (uiNamespace getVariable ["COMSPEC_ATAK_PhotoShooting", false]) exitWith {};
            if !(missionNamespace getVariable ["comspec_atak_native_lock_enabled", true]) exitWith {};
            ["draw3d"] call comspec_atak_native_fnc_lockAction;
        }]];
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
            // Ligne de destination (réseau) tenue à jour.
            if (!_quit && {diag_tickTime >= (_st getOrDefault ["nextHud", 0])}) then { _st set ["nextHud", diag_tickTime + 2]; ["HUD"] call comspec_atak_native_fnc_photoMode; };
            // Barre des raccourcis repliée d'elle-même (sauf si le joueur l'a rouverte avec H).
            if (!_quit && {_st getOrDefault ["helpOpen", false]} && {!(_st getOrDefault ["helpPin", false])} && {diag_tickTime >= (_st getOrDefault ["helpUntil", 0])}) then {
                _st set ["helpOpen", false];
                ["HUD"] call comspec_atak_native_fnc_photoMode;
            };
        };
        if (_quit) exitWith { ["EXIT"] call comspec_atak_native_fnc_photoMode; };
        // Bras tendu, téléphone devant soi (geste relancé tant que le mode photo dure ; selfie : fn_gestureSelfie).
        if (diag_tickTime >= (_st getOrDefault ["nextPose", 0])) then {
            _st set ["nextPose", diag_tickTime + 1.2];
            // Animation réglable (CBA « ATAK · Photo ») : action/geste, animation CfgMoves ou fonction.
            private _self = _st getOrDefault ["selfie", false];
            private _anim = trim (missionNamespace getVariable [["comspec_atak_native_photo_anim", "comspec_atak_native_selfie_anim"] select _self, "comspec_atak_native_fnc_gestureSelfie"]);
            if (_self && {_anim isEqualTo ""}) then { _anim = trim (missionNamespace getVariable ["comspec_atak_native_photo_anim", "comspec_atak_native_fnc_gestureSelfie"]); };
            // Ancienne valeur par défaut (main tendue sans téléphone) : remplacée par le téléphone tenu en main.
            if ((toLower _anim) isEqualTo "gesturepoint") then { _anim = "comspec_atak_native_fnc_gestureSelfie"; };
            // Objectif arrière : bras baissé par défaut, sinon la main levée passe devant la vue (capture de TTRD, 18/10).
            // Réglage CBA personnel « Photo : téléphone levé en main » pour la remettre.
            if (!_self && {!(missionNamespace getVariable ["comspec_atak_native_photo_arm", false])}) then { _anim = ""; };
            // Passage selfie <-> photo : la fonction de l'animation précédente remet sa pose à zéro.
            private _prev = _st getOrDefault ["lastAnim", ""];
            if (_prev isNotEqualTo _anim && {(toLower _prev) find "_fnc_" >= 0}) then {
                private _fnPrev = missionNamespace getVariable [_prev, {}];
                if (_fnPrev isEqualType {}) then { [player, "exit"] call _fnPrev; };
            };
            if (_anim isNotEqualTo "") then {
                switch (true) do {
                    case ((toLower _anim) find "_fnc_" >= 0): {
                        private _fn = missionNamespace getVariable [_anim, {}];
                        if (_fn isEqualType {}) then { [player, ["photo", "selfie"] select _self] call _fn; };
                    };
                    case (isClass (configFile >> "CfgMovesMaleSdr" >> "States" >> _anim)): {
                        if ((toLower (animationState player)) isNotEqualTo (toLower _anim)) then { player playMoveNow _anim; };
                    };
                    default {
                        // Un geste retombe tout seul : on le relance ; une autre action n'est jouée qu'une fois par changement.
                        private _lo = toLower _anim;
                        if ((_lo find "gesture") isEqualTo 0) then {
                            if (((toLower (gestureState player)) find (_lo select [7])) < 0) then { player playActionNow _anim; };
                        } else {
                            if ((_st getOrDefault ["lastAnim", ""]) isNotEqualTo _anim) then { player playActionNow _anim; };
                        };
                    };
                };
            };
            _st set ["lastAnim", _anim];
        };
        private _selfie = _st getOrDefault ["selfie", false];
        private _zoom = _st getOrDefault ["zoom", 1];
        // Marche (objectif arrière) : une touche de déplacement ramène le zoom à x1 d'un coup (la vue revient au jeu,
        // qui seul fait marcher le joueur) ; le zoom ne revient pas de lui-même à l'arrêt.
        if (!_selfie && {_zoom > 1.01 || {abs ((_st getOrDefault ["fov", 0.75]) - 0.75) > 0.01}} && {
            ((["MoveForward", "MoveBack", "TurnLeft", "TurnRight", "MoveLeft", "MoveRight", "MoveFastForward", "MoveSlowForward", "EvasiveLeft", "EvasiveRight"] findIf { (inputAction _x) > 0 }) >= 0)
        }) then {
            _zoom = 1;
            _st set ["zoom", 1];
            _st set ["fov", 0.75];
            ["HUD"] call comspec_atak_native_fnc_photoMode;
        };
        private _eye = eyePos player;
        private _tPos = _eye;
        private _tDir = [0, 1, 0];
        private _rear = [];
        if (_selfie) then {
            // Corps figé pendant le selfie : la souris déplace le bras, pas le joueur.
            private _h = _st getOrDefault ["heading", getDir player];
            if ((abs ((((getDir player) - _h) + 540) mod 360 - 180)) > 0.5) then { player setDir _h; };
            private _a = _h + (_st getOrDefault ["yaw", 0]);
            private _p = _st getOrDefault ["pitch", 10];
            private _r = 0.6;
            _tPos = _eye vectorAdd [_r * (sin _a) * (cos _p), _r * (cos _a) * (cos _p), _r * (sin _p)];
            _tDir = vectorNormalized (_eye vectorDiff _tPos);
            // Téléphone en main (fn_gestureSelfie) : l'objectif est sur le téléphone (la main ne passe plus devant
            // l'image), la souris ne fait que tourner la vue autour de lui.
            private _lens = [player, "lens"] call comspec_atak_native_fnc_gestureSelfie;
            if (_lens isNotEqualTo []) then {
                _lens params ["_lp", "_ld"];
                // Souris : léger recadrage autour du visage (25° au plus), le visage reste dans l'image.
                private _ya = ((_ld select 0) atan2 (_ld select 1)) + (((_st getOrDefault ["yaw", 0]) max -25) min 25);
                private _pa = (((asin (((_ld select 2) max -1) min 1)) + ((((_st getOrDefault ["pitch", 10]) - 10) max -20) min 20)) max -80) min 80;
                _tPos = _lp;
                _tDir = [(sin _ya) * (cos _pa), (cos _ya) * (cos _pa), sin _pa];
            };
        } else {
            // Caméra scriptée (zoom) active : la vue du joueur ne suit plus la souris (getCameraViewDirection renvoie
            // la visée figée), d'où un viseur bloqué au zoom. La souris pilote alors un cap et une inclinaison propres
            // (MOUSE), le corps suit le cap ; sans caméra, on recopie la vue du joueur pour repartir de là.
            if (isNull (_st getOrDefault ["cam", objNull])) then {
                _tDir = getCameraViewDirection player;
                _st set ["aimYaw", (((_tDir select 0) atan2 (_tDir select 1)) + 360) mod 360];
                _st set ["aimPitch", asin (((_tDir select 2) max -1) min 1)];
            } else {
                private _ay = _st getOrDefault ["aimYaw", getDir player];
                private _ap = _st getOrDefault ["aimPitch", 0];
                if ((abs ((((getDir player) - _ay) + 540) mod 360 - 180)) > 0.5) then { player setDir _ay; };
                _tDir = [(sin _ay) * (cos _ap), (cos _ay) * (cos _ap), sin _ap];
            };
            _tPos = _eye vectorAdd (_tDir vectorMultiply 0.25);
            // Téléphone en main (fn_gestureSelfie, mode photo) : l'objectif arrière est au dos du téléphone.
            _rear = [player, "rear"] call comspec_atak_native_fnc_gestureSelfie;
            if (_rear isNotEqualTo []) then { _tPos = _rear select 0; };
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
        // Vue naturelle sans zoom ni selfie : pas de caméra scriptée, le joueur marche et tourne librement.
        private _natural = !_selfie && {_zoom < 1.01} && {_k >= 1} && {abs ((_st getOrDefault ["fov", 0.75]) - 0.75) < 0.01};
        // Téléphone en main masqué pour soi seul en vue naturelle (il cacherait la vue ; les autres le voient en main).
        private _ph = missionNamespace getVariable ["COMSPEC_ATAK_SelfiePhone", objNull];
        if (!isNull _ph && {(isObjectHidden _ph) isNotEqualTo _natural}) then { _ph hideObject _natural; };
        if (_natural) exitWith {
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
        if (!_active) exitWith { false };
        _arg params [["_dx", 0], ["_dy", 0]];
        // Zoom (caméra scriptée) : visée à la souris, plus lente quand le champ se resserre.
        if (!(_st getOrDefault ["selfie", false])) exitWith {
            if (isNull (_st getOrDefault ["cam", objNull])) exitWith { false };
            private _k = 2.2 * ((_st getOrDefault ["fov", 0.75]) / 0.75);
            _st set ["aimYaw", ((_st getOrDefault ["aimYaw", getDir player]) + _dx * _k + 360) mod 360];
            _st set ["aimPitch", (((_st getOrDefault ["aimPitch", 0]) - _dy * _k) max -80) min 80];
            true
        };
        // Cadrage du selfie : le bras tourne autour de la tête, dans la limite de ce qu'un bras atteint.
        // Axes inversables séparément (réglages CBA personnels « Selfie : inverser… »).
        if (missionNamespace getVariable ["comspec_atak_native_selfie_invert_x", false]) then { _dx = -_dx; };
        if (missionNamespace getVariable ["comspec_atak_native_selfie_invert_y", false]) then { _dy = -_dy; };
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
    case "BLUR": {
        if (!_active) exitWith { false };
        // Type de flou des visages pour la partie (le réglage CBA reste le point de départ).
        private _types = ["gauss", "motion", "pixel", "black", "off"];
        private _cur = uiNamespace getVariable ["COMSPEC_ATAK_BlurType", missionNamespace getVariable ["comspec_atak_native_blur_type", "gauss"]];
        uiNamespace setVariable ["COMSPEC_ATAK_BlurType", _types select ((((_types find _cur) max 0) + 1) mod 5)];
        playSound "ClickSoft";
        ["HUD"] call comspec_atak_native_fnc_photoMode;
        true
    };
    case "HELP": {
        if (!_active) exitWith { false };
        private _open = !(_st getOrDefault ["helpOpen", true]);
        _st set ["helpOpen", _open];
        _st set ["helpPin", _open];
        playSound "ClickSoft";
        ["HUD"] call comspec_atak_native_fnc_photoMode;
        true
    };
    case "HUD": {
        private _cam = uiNamespace getVariable ["COMSPEC_ATAK_CameraDisplay", displayNull];
        if (isNull _cam) exitWith { false };
        // Pendant la prise de vue, le viseur est masqué : rien n'est réaffiché avant la fin de la capture.
        if (uiNamespace getVariable ["COMSPEC_ATAK_PhotoShooting", false]) exitWith { false };
        private _selfie = _st getOrDefault ["selfie", false];
        private _z = _st getOrDefault ["zoom", 1];
        private _fl = _st getOrDefault ["flash", "AUTO"];
        private _zs = { params ["_v"]; [_v toFixed 1, str _v] select ((_v mod 1) isEqualTo 0) };
        private _bt = uiNamespace getVariable ["COMSPEC_ATAK_BlurType", missionNamespace getVariable ["comspec_atak_native_blur_type", "gauss"]];
        private _bl = (createHashMapFromArray [["gauss", "GAUSSIEN"], ["motion", "MOUVEMENT"], ["pixel", "PIXEL"], ["black", "CARRÉ NOIR"]]) getOrDefault [_bt, "COUPÉ"];
        (_cam displayCtrl 5) ctrlSetStructuredText parseText ((format ["<t align='center' font='COMSPEC_BarlowBold'><t color='#f2d36b'>%1</t>   <t color='#6d7a74'>|</t>   ZOOM <t color='#5cc76b'>x%2</t>   <t color='#6d7a74'>|</t>   FLASH <t color='%3'>%4</t>   <t color='#6d7a74'>|</t>   VISAGES <t color='%5'>%6</t></t>",
            ["OBJECTIF ARRIÈRE", "SELFIE"] select _selfie, [_z] call _zs, ["#e8eadf", "#f2ab33"] select (_fl isEqualTo "ON"),
            createHashMapFromArray [["AUTO", "AUTO"], ["ON", "FORCÉ"], ["OFF", "COUPÉ"]] get _fl,
            ["#5cc76b", "#8a9a93"] select (_bl isEqualTo "COUPÉ"), _bl]) call comspec_atak_native_fnc_tr);
        // Échelle de zoom à droite : palier en cours en vert.
        (_cam displayCtrl 6) ctrlSetStructuredText parseText ((((_zoomSteps apply {
            private _on = abs (_x - _z) < 0.01;
            format ["<t align='right' size='%1' color='%2' font='%3'>x%4</t>", [0.82, 1.12] select _on, ["#8a9a93", "#5cc76b"] select _on, [(COMSPEC_ATAK_FontSet select 0), (COMSPEC_ATAK_FontSet select 1)] select _on, [_x] call _zs]
        }) joinString "<br/>")) call comspec_atak_native_fnc_tr);
        // Exposition affichée : ISO et vitesse suivent la lumière au joueur (lampes et flash compris), ouverture f/1.7
        // du capteur du S7. ISO 1600 et plus en orange : photo bruitée.
        (getLightingAt player) params ["", ["_amb", 0], "", ["_dyn", 0]];
        private _lum = _amb + _dyn;
        // Palier d'exposition : 0 (plein jour) à 6 (nuit noire, aucun seuil atteint).
        private _ei = (([150, 60, 25, 10, 4, 1.5] findIf { _lum >= _x }) + 7) mod 7;
        private _iso = [100, 200, 400, 800, 1600, 3200, 6400] select _ei;
        (_cam displayCtrl 12) ctrlSetStructuredText parseText ((format ["<t align='right' font='COMSPEC_BarlowBold' size='1.1'><t color='#8a9a93' size='0.8'>ISO </t><t color='%1'>%2</t></t><br/><t align='right' color='#a9b8b0' size='0.85'>f/1.7   1/%3 s</t>",
            ["#eef3ef", "#f2ab33"] select (_iso >= 1600), _iso, [250, 200, 125, 60, 30, 15, 8] select _ei]) call comspec_atak_native_fnc_tr);
        // Barre des raccourcis : touches en pastilles claires, action à côté, groupes séparés d'un filet.
        // Action principale (photo) en vert, touches de sortie plus discrètes. Repliée : photo, aide et quitter seuls.
        private _fls = (createHashMapFromArray [["AUTO", "AUTO"], ["ON", "FORCÉ"], ["OFF", "COUPÉ"]]) get _fl;
        private _flash = format ["%1 %2", ("flash") call comspec_atak_native_fnc_tr, (_fls) call comspec_atak_native_fnc_tr];
        // B : type de flou des visages, nommé dans la barre (la touche n'y figurait pas en objectif arrière).
        private _blur = format ["%1 %2", ("flou") call comspec_atak_native_fnc_tr, (_bl) call comspec_atak_native_fnc_tr];
        private _open = _st getOrDefault ["helpOpen", true];
        private _keys = switch (true) do {
            case (!_open): { [["CLIC", "photo", 1], "|", ["H", "aide", 2], ["ESPACE", "quitter", 2]] };
            case (_selfie): { [["CLIC", "photo", 1], "|", ["MOLETTE", "zoom", 0], ["SOURIS", "cadrer", 0], "|", ["F", _flash, 0], ["B", _blur, 0], ["R", "objectif arrière", 0], "|", ["H", "masquer", 2], ["ESPACE", "quitter", 2]] };
            default { [["CLIC", "photo", 1], ["T", "marquer", 0], "|", ["MOLETTE", "zoom", 0], ["B", _blur, 0], "|", ["F", _flash, 0], ["R", "selfie", 0], "|", ["H", "masquer", 2], ["ESPACE", "quitter", 2]] };
        };
        private _kh = safeZoneH * 0.03;
        private _pad = safeZoneW * 0.005;
        private _gap = safeZoneW * 0.004;
        private _sp = safeZoneW * 0.012;
        private _gs = safeZoneW * 0.01;
        private _sq = _kh * pixelW / pixelH;
        // Première passe : textes posés et largeurs mesurées.
        private _items = [];
        private _n = 0;
        {
            if (_x isEqualType "") then { _items pushBack "|"; continue };
            if (_n > 7) then { continue };
            _x params ["_k", "_l", "_kind"];
            private _cap = _cam displayCtrl (20 + _n);
            private _lab = _cam displayCtrl (40 + _n);
            _cap ctrlSetText ((_k) call comspec_atak_native_fnc_tr);
            _lab ctrlSetText ((_l) call comspec_atak_native_fnc_tr);
            _cap ctrlSetBackgroundColor ([[0.92, 0.95, 0.93, 0.97], [0.36, 0.78, 0.42, 1], [0.3, 0.34, 0.32, 0.95]] select _kind);
            _cap ctrlSetTextColor ([[0.06, 0.08, 0.07, 1], [0.03, 0.08, 0.04, 1], [0.9, 0.93, 0.91, 1]] select _kind);
            (_cam displayCtrl (80 + _n)) ctrlSetBackgroundColor ([[0.5, 0.55, 0.52, 0.95], [0.18, 0.45, 0.22, 1], [0.12, 0.14, 0.13, 0.95]] select _kind);
            _lab ctrlSetTextColor ([[0.86, 0.91, 0.88, 1], [0.92, 0.97, 0.93, 1], [0.66, 0.72, 0.69, 1]] select _kind);
            _items pushBack [_n, ((ctrlTextWidth _cap) + 2 * _pad) max _sq, ctrlTextWidth _lab];
            _n = _n + 1;
        } forEach _keys;
        private _total = 0;
        private _prev = "";
        {
            if (_x isEqualType "") then { _total = _total + 2 * _gs; } else {
                if (_prev isEqualType []) then { _total = _total + _sp; };
                _total = _total + (_x select 1) + _gap + (_x select 2);
            };
            _prev = _x;
        } forEach _items;
        // Écran étroit : espacements resserrés pour tenir dans la largeur.
        private _k = 1;
        private _max = safeZoneW * 0.9;
        if (_total > _max) then {
            private _fixed = 0;
            { if (_x isEqualType []) then { _fixed = _fixed + (_x select 1) + (_x select 2); }; } forEach _items;
            _k = (((_max - _fixed) / ((_total - _fixed) max 0.001)) max 0.3) min 1;
            _total = _fixed + (_total - _fixed) * _k;
        };
        private _bx = safeZoneW * 0.012;
        private _by = safeZoneY + safeZoneH * 0.855;
        private _bh = _kh + safeZoneH * 0.02;
        private _x0 = safeZoneX + (safeZoneW - _total) / 2;
        private _cy = _by + (_bh - _kh) / 2 - safeZoneH * 0.002;
        private _fade = (_st getOrDefault ["helpDrawn", -1]) isNotEqualTo _open;
        _st set ["helpDrawn", _open];
        private _drawn = [_cam displayCtrl 7];
        (_cam displayCtrl 7) ctrlSetPosition [_x0 - _bx, _by, _total + 2 * _bx, _bh];
        private _cx = _x0;
        private _sep = 0;
        _prev = "";
        {
            if (_x isEqualType "") then {
                private _c = _cam displayCtrl (60 + _sep);
                if (_sep < 4) then {
                    _c ctrlSetPosition [_cx + _gs * _k, _by + _bh * 0.25, pixelW, _bh * 0.5];
                    _drawn pushBack _c;
                };
                _sep = _sep + 1;
                _cx = _cx + 2 * _gs * _k;
            } else {
                _x params ["_i", "_cw", "_lw"];
                if (_prev isEqualType []) then { _cx = _cx + _sp * _k; };
                (_cam displayCtrl (80 + _i)) ctrlSetPosition [_cx, _cy + safeZoneH * 0.003, _cw, _kh];
                (_cam displayCtrl (20 + _i)) ctrlSetPosition [_cx, _cy, _cw, _kh];
                (_cam displayCtrl (40 + _i)) ctrlSetPosition [_cx + _cw + _gap, _cy, _lw + pixelW * 4, _kh];
                _drawn append [_cam displayCtrl (80 + _i), _cam displayCtrl (20 + _i), _cam displayCtrl (40 + _i)];
                _cx = _cx + _cw + _gap + _lw;
            };
            _prev = _x;
        } forEach _items;
        for "_i" from 0 to 7 do { if (_i >= _n) then { { (_cam displayCtrl _x) ctrlShow false; } forEach [20 + _i, 40 + _i, 80 + _i]; }; };
        for "_i" from _sep to 3 do { (_cam displayCtrl (60 + _i)) ctrlShow false; };
        {
            _x ctrlShow true;
            if (_fade) then { _x ctrlSetFade 1; _x ctrlCommit 0; };
            _x ctrlSetFade 0;
            _x ctrlCommit ([0, 0.25] select _fade);
        } forEach _drawn;
        // Où part la photo : Athena (réseau présent) ou poste seul, et légende choisie dans l'app.
        private _net = (([] call comspec_atak_native_fnc_linkQuality) getOrDefault ["bars", 0]) > 0;
        private _cap = (uiNamespace getVariable ["COMSPEC_ATAK_PhotoCtx", []]) param [3, ""];
        if !(_cap isEqualType "") then { _cap = ""; };
        _cap = [[[_cap select [0, 40], "<", "&lt;"] call CBA_fnc_replace, ">", "&gt;"] call CBA_fnc_replace, "légende automatique (grille, cap)"] select (_cap isEqualTo "");
        (_cam displayCtrl 9) ctrlSetStructuredText parseText ((format ["<t align='center'>%1   <t color='#6d7a74'>|</t>   <t color='#a9b8b0'>%2</t></t>",
            ["<t color='#f2ab33'>HORS RÉSEAU : photo gardée sur le poste</t>", "<t color='#5cc76b'>ENVOI DIRECT VERS ATHENA</t>"] select _net, _cap]) call comspec_atak_native_fnc_tr);
        (_cam displayCtrl 11) ctrlSetBackgroundColor ([[0.95, 0.67, 0.2, 0.9], [0.36, 0.78, 0.42, 0.9]] select _net);
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
        // Les raccourcis changent (cadrer, objectif arrière) : barre rouverte quelques secondes.
        if !(_st getOrDefault ["helpPin", false]) then { _st set ["helpOpen", true]; _st set ["helpUntil", diag_tickTime + 5]; };
        playSound "ClickSoft";
        ["HUD"] call comspec_atak_native_fnc_photoMode;
        true
    };
    case "SHOOT": {
        if (!_active) exitWith { false };
        if (diag_tickTime - (uiNamespace getVariable ["COMSPEC_ATAK_PhotoLast", -10]) < 3) exitWith {
            private _cam = uiNamespace getVariable ["COMSPEC_ATAK_CameraDisplay", displayNull];
            if (!isNull _cam) then { (_cam displayCtrl 4) ctrlSetText (("Patientez : enregistrement de la photo précédente…") call comspec_atak_native_fnc_tr); };
            false
        };
        uiNamespace setVariable ["COMSPEC_ATAK_PhotoLast", diag_tickTime];
        // Flash : forcé, coupé, ou automatique la nuit et dans un intérieur sombre (toit au-dessus, peu de lumière).
        private _eye = eyePos player;
        // Intérieur : seulement vraiment sombre (sous un hangar en plein jour, l'ancien seuil 40 déclenchait le flash).
        private _dark = (sunOrMoon < 0.5) || {
            ((lineIntersectsSurfaces [_eye, _eye vectorAdd [0, 0, 15], player, objNull, true, 1, "GEOM", "NONE"]) isNotEqualTo [])
            && {((getLightingAt player) select 1) < 8}
        };
        private _fire = switch (_st getOrDefault ["flash", "AUTO"]) do { case "ON": { true }; case "OFF": { false }; default { _dark }; };
        (call _curView) params ["_viewPos", "_viewDir"];
        _viewPos params ["_px", "_py", "_pz"];
        // Fiche du cliché (relais Discord) : position et axe de l'objectif, objectif, zoom, flash, modèle, version.
        private _meta = ["", "", false, "", false, "phone", "", _viewPos, (((_viewDir select 0) atan2 (_viewDir select 1)) + 360) mod 360,
            [["lens", ["rear", "selfie"] select (_st getOrDefault ["selfie", false])], ["zoom", _st getOrDefault ["zoom", 1]],
             ["flash", ["off", "on"] select _fire], ["model", profileNamespace getVariable ["COMSPEC_ATAK_PhoneModel", "s7"]],
             ["atak", missionNamespace getVariable ["COMSPEC_ATAK_NativeVersion", ""]]],
            _viewDir];
        [_fire, [_px, _py, _pz], _meta, _st getOrDefault ["selfie", false]] spawn {
            params ["_fire", "_phone", "_meta", "_selfie"];
            disableSerialization;
            private _ctx = uiNamespace getVariable ["COMSPEC_ATAK_PhotoCtx", []];
            private _caption = _ctx param [3, ""];
            if (_caption isEqualTo "") then { _caption = format ["%1 · %2 · %3°", [player] call comspec_atak_native_fnc_unitCallsign, [player] call comspec_atak_native_fnc_gridRef, round getDir player]; };
            private _cam = uiNamespace getVariable ["COMSPEC_ATAK_CameraDisplay", displayNull];
            // Le viseur ne doit pas apparaître sur la photo (ni les cadres dessinés dans la scène).
            uiNamespace setVariable ["COMSPEC_ATAK_PhotoShooting", true];
            // Tout ce qui est affiché est masqué (sauf l'éclair blanc), puis réaffiché tel quel après la capture.
            private _vis = [];
            if (!isNull _cam) then { _vis = (allControls _cam) select { ctrlShown _x && {(ctrlIDC _x) isNotEqualTo 2} }; { _x ctrlShow false; } forEach _vis; };
            // Éclair réel au téléphone, très bref (visible sur la capture).
            private _light = objNull;
            if (_fire) then {
                _light = "#lightpoint" createVehicleLocal (ASLToAGL _phone);
                _light setPosASL _phone;
                // Éclair modéré : l'ancien (luminosité 6, lumière ambiante pleine, de jour aussi) éclairait toute la
                // scène à plat et l'adaptation HDR rendait la photo délavée.
                _light setLightColor [1, 0.97, 0.92];
                _light setLightAmbient [0.12, 0.12, 0.11];
                _light setLightBrightness ([0.9, 2.2] select (sunOrMoon < 0.5));
                _light setLightAttenuation [0.5, 0, 0, 1.4, 8, 10];
                _light setLightDayLight (sunOrMoon >= 0.5);
            };
            uiSleep 0.12;
            // Visages à flouer, repérés sur l'image qui part (Athena floute à la réception).
            private _faces = [_selfie] call comspec_atak_native_fnc_photoFaces;
            (_meta select 9) append _faces;
            // Débit simulé : sans réseau, la photo reste sur le poste (app Galerie pour la retransmettre).
            private _noNet = (([] call comspec_atak_native_fnc_linkQuality) get "bars") isEqualTo 0;
            // ATAK seul (sans COMSPEC Link) : la photo reste aussi sur le poste, rien n'est envoyé.
            private _noLink = isNil "comspec_overwatch_connect_fnc_captureReconImage";
            if (_noLink) then { _noNet = true; };
            private _ok = if (_noNet) then {
                private _shot = format ["comspec_atak_%1.png", floor (diag_tickTime * 10)];
                screenshot _shot;
                // Visages gardés avec le nom du fichier : l'envoi depuis la Galerie les floute aussi (40 photos au plus).
                if (_faces isNotEqualTo []) then {
                    private _kept = profileNamespace getVariable ["COMSPEC_ATAK_PhotoFaces", []];
                    if !(_kept isEqualType []) then { _kept = []; };
                    _kept pushBack [toLower _shot, _faces];
                    profileNamespace setVariable ["COMSPEC_ATAK_PhotoFaces", _kept select [((count _kept) - 40) max 0]];
                };
                [["WARNING", "INFO"] select _noLink, ["Pas de réseau : photo gardée sur le poste, à envoyer depuis l'app Galerie", "Photo prise : enregistrée dans le dossier Screenshots du profil Arma (envoi vers Athena avec COMSPEC Link)"] select _noLink, 5, 40] call comspec_atak_native_fnc_notify;
                false
            } else { ["", _caption, "CTAB", "", false, false, true, _meta] call comspec_overwatch_connect_fnc_captureReconImage };
            uiSleep 0.08;
            if (!isNull _light) then { deleteVehicle _light; };
            uiNamespace setVariable ["COMSPEC_ATAK_PhotoShooting", false];
            if (!isNull _cam) then {
                { _x ctrlShow true; } forEach _vis;
                private _flash = _cam displayCtrl 2;
                _flash ctrlSetBackgroundColor [1, 1, 1, [0.55, 0.9] select _fire];
                _flash ctrlCommit 0;
                _flash ctrlSetBackgroundColor [1, 1, 1, 0];
                _flash ctrlCommit ([0.35, 0.6] select _fire);
                private _n = (_ctx param [4, 0]) + ([0, 1] select _ok);
                _ctx set [4, _n];
                (_cam displayCtrl 4) ctrlSetText ((format ["%1%2", ["Photo non envoyée : voir Galerie > Envois", format ["%1 photo(s) en cours d'envoi vers Athena", _n]] select _ok, ["", " · flash"] select _fire]) call comspec_atak_native_fnc_tr);
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
        removeMissionEventHandler ["Draw3D", uiNamespace getVariable ["COMSPEC_ATAK_PhotoDraw", -1]];
        uiNamespace setVariable ["COMSPEC_ATAK_PhotoDraw", -1];
        [uiNamespace getVariable ["COMSPEC_ATAK_PhotoPFH", -1]] call CBA_fnc_removePerFrameHandler;
        private _ph = missionNamespace getVariable ["COMSPEC_ATAK_SelfiePhone", objNull];
        if (!isNull _ph) then { _ph hideObject false; };
        // Bras baissé : le geste n'est plus relancé et se termine de lui-même ; une animation CfgMoves est rendue,
        // une fonction reçoit [joueur, "exit"] pour remettre sa pose à zéro.
        private _last = _st getOrDefault ["lastAnim", ""];
        _st set ["lastAnim", ""];
        if (_last isNotEqualTo "" && {alive player} && {vehicle player isEqualTo player}) then {
            if ((toLower _last) find "_fnc_" >= 0) then {
                private _fn = missionNamespace getVariable [_last, {}];
                if (_fn isEqualType {}) then { [player, "exit"] call _fn; };
            } else {
                if ((toLower (animationState player)) isEqualTo (toLower _last)) then { player playMoveNow ""; player switchMove ""; };
            };
        };
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
