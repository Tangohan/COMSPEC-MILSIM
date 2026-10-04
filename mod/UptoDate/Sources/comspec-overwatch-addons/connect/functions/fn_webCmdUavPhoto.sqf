/*
    Photo demandée par le poste web avec la caméra du drone du pilote (client du pilote).
    Params : [id de commande, drone]

    Arma ne sait capturer que l'écran du joueur (commande screenshot) : on bascule l'écran du pilote
    sur une caméra fixée sous le drone pendant environ 0,4 s, on capture, on rend la vue.
    La caméra reprend l'orientation de la nacelle du téléphone (piste visée, point observé) si elle est ouverte,
    sinon elle regarde devant et vers le bas ; zoom et mode de vision (jour, nuit, thermique) du téléphone.
    La photo part par le chemin recon habituel (fn_captureReconImage, appareil "UAV", flux drone:<netId>)
    et apparaît dans les Photos du poste.

    Limites (non contournables côté jeu) :
      - refusée si le pilote est dans un menu (dialogue, pause, carte, inventaire, Zeus) ou si une capture est en cours ;
      - le pilote voit sa vue basculer un court instant ; l'écran du téléphone ATAK est masqué le temps de la capture ;
      - position enregistrée avec la photo : celle du pilote (chemin recon) ; la grille du drone est dans la légende ;
      - capture refusée par le jeu si la qualité HDR est trop basse (même message que les photos du téléphone).
*/
params [["_id", ""], ["_d", objNull]];
private _fail = { params ["_m"]; [_id, "failed", _m] call comspec_overwatch_connect_fnc_webCmdAck; false };

private _busy = switch (true) do {
    case (missionNamespace getVariable ["COMSPEC_WebUavPhotoBusy", false]): { "une photo est déjà en cours" };
    case (missionNamespace getVariable ["COMSPEC_ReconCaptureBusy", false]): { "une capture est déjà en cours" };
    case (dialog): { "un menu est ouvert" };
    case (!isNull (findDisplay 49)): { "le jeu est en pause" };
    case (!isNull (findDisplay 602)): { "l'inventaire est ouvert" };
    case (visibleMap): { "la carte est ouverte" };
    case (!isNull curatorCamera): { "il est en Zeus" };
    case (cameraOn isNotEqualTo (vehicle player) && {cameraOn isNotEqualTo _d}): { "sa vue est sur un autre engin" };
    default { "" };
};
if (_busy isNotEqualTo "") exitWith { [format ["Photo impossible : %1 chez le pilote", _busy]] call _fail };

missionNamespace setVariable ["COMSPEC_WebUavPhotoBusy", true];
[_id, _d] spawn {
    params ["_id", "_d"];
    private _prevOn = cameraOn;
    private _prevView = cameraView;
    private _hud = shownHUD;
    if (!isNil "ace_interact_menu_fnc_hideMenu") then { [] call ace_interact_menu_fnc_hideMenu; };

    // Écran du téléphone masqué le temps du cliché (il serait capturé par-dessus l'image).
    private _phone = uiNamespace getVariable ["COMSPEC_ATAK_Display", displayNull];
    private _hidden = [];
    if (!isNull _phone) then {
        _hidden = (allControls _phone) select { ctrlShown _x };
        { _x ctrlShow false; } forEach _hidden;
    };

    // Caméra sous le drone, orientée comme la nacelle du téléphone si elle est ouverte.
    private _pc = missionNamespace getVariable ["COMSPEC_ATAK_DroneCam", objNull];
    private _cam = "camera" camCreate (getPosATL _d);
    _cam attachTo [_d, [0, 0.3, -0.3]];
    if (!isNull _pc) then {
        _cam setVectorDirAndUp [_d vectorWorldToModelVisual (vectorDir _pc), _d vectorWorldToModelVisual (vectorUp _pc)];
    } else {
        private _t = _d getVariable ["COMSPEC_DroneTask", []];
        private _p = if ((_t param [0, ""]) isEqualTo "OBSERVE" && {(_t param [1, []]) isEqualType []}) then { _t select 1 } else { [] };
        if ((count _p) >= 2) then {
            if ((count _p) < 3) then { _p = [_p select 0, _p select 1, getTerrainHeightASL _p]; };
            private _dir = vectorNormalized (_p vectorDiff (_d modelToWorldVisualWorld [0, 0.3, -0.3]));
            private _side = _dir vectorCrossProduct [0, 0, 1];
            if ((vectorMagnitude _side) < 0.01) then { _side = [1, 0, 0]; };
            _cam setVectorDirAndUp [_d vectorWorldToModelVisual _dir, _d vectorWorldToModelVisual (_side vectorCrossProduct _dir)];
        } else {
            _cam setVectorDirAndUp [[0, 0.8, -0.6], [0, 0.6, 0.8]];
        };
    };
    _cam cameraEffect ["Internal", "Back"];
    _cam camSetFov (0.7 / ((missionNamespace getVariable ["COMSPEC_ATAK_DroneZoom", 1]) max 1));
    _cam camCommit 0;
    private _vision = missionNamespace getVariable ["COMSPEC_ATAK_DroneVision", 0];
    if (_vision isEqualTo 1) then { camUseNVG true; };
    if (_vision isEqualTo 2) then { true setCamUseTI 0; };
    showHUD false;
    uiSleep 0.3;

    private _pos = getPosASL _d;
    private _model = _d getVariable ["COMSPEC_DroneName", ""];
    if (!(_model isEqualType "") || {_model isEqualTo ""}) then { _model = getText (configOf _d >> "displayName"); };
    private _grid = mapGridPosition _d;
    private _alt = round ((getPosATL _d) select 2);
    private _caption = format ["Drone %1 — grille %2, %3 m sol, cap %4°, photo demandée par le poste", _model, _grid, _alt, round getDir _d];
    private _ok = ["", _caption, "UAV", format ["drone:%1", netId _d], false, false, false] call comspec_overwatch_connect_fnc_captureReconImage;
    // La capture est écrite en fin d'image : on garde la vue deux images de plus.
    uiSleep 0.15;

    camUseNVG false;
    false setCamUseTI 0;
    _cam cameraEffect ["Terminate", "Back"];
    camDestroy _cam;
    if (!isNull _pc) then {
        _pc cameraEffect ["Internal", "Back", "comspec_dronecam"];
        "comspec_dronecam" setPiPEffect [_vision];
    };
    if (cameraOn isNotEqualTo _prevOn && {!isNull _prevOn}) then { _prevOn switchCamera _prevView; };
    { _x ctrlShow true; } forEach _hidden;
    showHUD _hud;
    missionNamespace setVariable ["COMSPEC_WebUavPhotoBusy", false];

    if (_ok isEqualTo true) then {
        if (!isNil "comspec_atak_native_fnc_notify") then { ["INFO", "Poste de commandement : photo prise avec votre drone", 4, 30] call comspec_atak_native_fnc_notify; };
        [_id, "done", format ["Photo prise en %1, envoi en cours (onglet Photos)", _grid],
            createHashMapFromArray [["grid", _grid], ["x", round (_pos select 0)], ["y", round (_pos select 1)], ["alt", _alt]]] call comspec_overwatch_connect_fnc_webCmdAck;
    } else {
        private _det = toLower str (missionNamespace getVariable ["COMSPEC_LastReconUploadDetail", ""]);
        private _why = switch (true) do {
            case ((_det find "screenshot_rejected") >= 0): { "capture refusée par le jeu (qualité HDR trop basse chez le pilote)" };
            case ((_det find "not_connected") >= 0 || {(_det find "unauthorized") >= 0}): { "liaison Athena du pilote coupée" };
            case ((_det find "queue_full") >= 0): { "file photo du pilote saturée" };
            default { "envoi refusé" };
        };
        [_id, "failed", format ["Photo non envoyée : %1", _why]] call comspec_overwatch_connect_fnc_webCmdAck;
    };
};
true
