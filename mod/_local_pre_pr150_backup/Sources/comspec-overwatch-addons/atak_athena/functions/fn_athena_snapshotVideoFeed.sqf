/*
    Aperçu périodique de la propre caméra casque / drone connecté.
    Utilise screenshot + UploadLatestScreenshot (pas de RTMP).
    Activé uniquement si le module video_feeds est actif et le réglage CBA l’autorise.
*/
if (!hasInterface) exitWith {};
if (!(missionNamespace getVariable ["COMSPEC_AthenaReady", false])) exitWith {};
if (!(["video_feeds"] call comspec_overwatch_connect_fnc_isModModuleEnabled)) exitWith {};
if (!(missionNamespace getVariable ["comspec_overwatch_athena_feed_snapshot", false])) exitWith {};

// Éviter pendant les interfaces lourdes
if (!isNull (findDisplay 49)) exitWith {}; // pause
if (dialog) exitWith {};

private _device = "";
private _feedId = "";
private _caption = "";

private _hasHcam = ("ItemcTabHCam" in (items player + assignedItems player))
    || {((headgear player) in (missionNamespace getVariable ["cTab_helmetClass_has_HCam", []]))};
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

// Anti-spam
private _last = missionNamespace getVariable ["COMSPEC_Athena_LastFeedSnapAt", 0];
private _interval = missionNamespace getVariable ["comspec_overwatch_athena_feed_interval", 35];
if ((diag_tickTime - _last) < _interval) exitWith {};
missionNamespace setVariable ["COMSPEC_Athena_LastFeedSnapAt", diag_tickTime, false];

["", _caption, _device, _feedId] call comspec_overwatch_connect_fnc_captureReconImage;
