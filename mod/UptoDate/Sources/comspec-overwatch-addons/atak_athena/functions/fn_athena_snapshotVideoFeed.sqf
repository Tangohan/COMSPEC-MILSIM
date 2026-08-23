/*
    Aperçu périodique de la propre caméra casque / drone connecté.
    Désactivé : la vue casque temps réel n’est pas au point (quota screenshot
    Arma + file_not_found). Les clichés ATAK passent par captureReconImage.
*/
if (true) exitWith {};
if (!hasInterface) exitWith {};
if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith {};
if (!(["video_feeds"] call comspec_overwatch_connect_fnc_isModModuleEnabled)) exitWith {};
if (!(missionNamespace getVariable ["comspec_overwatch_athena_feed_snapshot", false])) exitWith {};

// Éviter pendant les interfaces lourdes
if (!isNull (findDisplay 49)) exitWith {}; // pause
if (dialog) exitWith {};

// Photo ATAK / BCE en cours ou récente : ne pas recycler sa capture comme « aperçu casque ».
private _suppressUntil = missionNamespace getVariable ["COMSPEC_SuppressFeedSnapshotUntil", 0];
if (_suppressUntil isEqualType 0 && {diag_tickTime < _suppressUntil}) exitWith {};
if (!isNull (uiNamespace getVariable ["BCE_PhoneCAM_View", displayNull])) exitWith {};

private _device = "";
private _feedId = "";
private _caption = "";

private _helmetClasses = missionNamespace getVariable ["cTab_helmetClass_has_HCam", []];
if (!(_helmetClasses isEqualType [])) then { _helmetClasses = []; };
if (_helmetClasses isEqualTo [] && {!isNil "cTab_helmetClass_has_HCam"} && {cTab_helmetClass_has_HCam isEqualType []}) then {
    _helmetClasses = cTab_helmetClass_has_HCam;
};
private _hcamGear = (items player) + (assignedItems player);
private _hcamGoggles = goggles player;
if (_hcamGoggles isNotEqualTo "") then { _hcamGear pushBackUnique _hcamGoggles; };
private _hasHcam = ("ItemcTabHCam" in _hcamGear) || {((headgear player) in _helmetClasses)};
if (_hasHcam) then {
    private _uid = getPlayerUID player;
    _feedId = format ["helmet:%1", _uid];
    _device = "HELMET";
    private _cs = [] call comspec_overwatch_connect_fnc_getCallsign;
    if (_cs isEqualTo "") then { _cs = name player; };
    _caption = format ["Aperçu casque — %1 · grille %2", _cs, mapGridPosition player];
};

// Priorité drone si connecté (vue opérateur / terminal)
private _droneState = missionNamespace getVariable ["Iceman_ATAK_DroneOps_state", createHashMap];
private _drone = objNull;
if (_droneState isEqualType createHashMap) then {
    _drone = _droneState getOrDefault ["drone", objNull];
};
if (isNull _drone) then {
    private _uav = getConnectedUAV player;
    if (!isNull _uav && {alive _uav}) then { _drone = _uav; };
};
if (!isNull _drone && {alive _drone}) then {
    private _netId = netId _drone;
    if (_netId isEqualTo "") then { _netId = str _drone; };
    _feedId = format ["drone:%1", _netId];
    _device = "DRONE";
    private _disp = getText (configOf _drone >> "displayName");
    if (_disp isEqualTo "") then { _disp = typeOf _drone; };
    _caption = format ["Aperçu drone — %1 · grille %2", _disp, mapGridPosition _drone];
};

if (_device isEqualTo "" || {_feedId isEqualTo ""}) exitWith {};

// Après file_not_found répétés : ne pas relancer de captures fantômes.
private _failUntil = missionNamespace getVariable ["COMSPEC_FeedSnapFailUntil", 0];
if (_failUntil isEqualType 0 && {diag_tickTime < _failUntil}) exitWith {};

// Anti-spam
private _streamUntil = missionNamespace getVariable ["COMSPEC_HelmetStreamUntil", 0];
private _streamActive = (_streamUntil isEqualType 0) && {diag_tickTime < _streamUntil};
if (!_streamActive) then {
    missionNamespace setVariable ["COMSPEC_HelmetStreamActive", false, false];
};
private _interval = if (_streamActive) then {
    5
} else {
    missionNamespace getVariable ["comspec_overwatch_athena_feed_interval", 35]
};
private _last = missionNamespace getVariable ["COMSPEC_Athena_LastFeedSnapAt", 0];
if ((diag_tickTime - _last) < _interval) exitWith {};
missionNamespace setVariable ["COMSPEC_Athena_LastFeedSnapAt", diag_tickTime, false];

["", _caption, _device, _feedId, false, false] call comspec_overwatch_connect_fnc_captureReconImage;
