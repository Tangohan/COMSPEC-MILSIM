/*
    Envoie vers Athena la photo sélectionnée dans l’inbox Athena
    ou la dernière photo locale Photo Library.
*/
if (!hasInterface) exitWith {};
if (isNil "comspec_overwatch_connect_fnc_captureReconImage") exitWith {
    [
        "Le module photo n’est pas disponible pour le moment.",
        "error",
        5
    ] call comspec_overwatch_atak_athena_fnc_athena_setPanelFeedback;
};

private _path = "";
private _caption = "";

private _listCtrl = controlNull;
private _group = uiNamespace getVariable ["COMSPEC_ATAK_Athena_group", controlNull];
if (!isNull _group) then { _listCtrl = _group controlsGroupCtrl 9710; };

if (!isNull _listCtrl) then {
    private _sel = lbCurSel _listCtrl;
    private _entries = _listCtrl getVariable ["COMSPEC_Athena_Entries", []];
    if (_sel >= 0 && {_sel < count _entries}) then {
        (_entries select _sel) params ["_kind", "", "", "", ["_meta", []]];
        if (_kind isEqualTo "photo" && {(_meta isEqualType []) && {(count _meta) >= 1}}) then {
            _path = _meta select 0;
            if ((count _meta) >= 2) then { _caption = format ["Photo ATAK — %1", _meta select 1]; };
        };
    };
};

// Repli : dernière photo locale Photo Library
if (_path isEqualTo "" && {!isNil "Iceman_fnc_photo_getRecords"}) then {
    private _records = call Iceman_fnc_photo_getRecords;
    if ((_records isEqualType []) && {(count _records) > 0}) then {
        private _rec = _records select ((count _records) - 1);
        if ((_rec isEqualType []) && {(count _rec) > 3}) then {
            _path = _rec select 2;
            private _fn = _rec select 3;
            private _g = if ((count _rec) > 8) then { _rec select 8 } else { mapGridPosition player };
            _caption = format ["Photo ATAK Enhanced — grille %1 (%2)", _g, _fn];
        };
    };
};

if (_path isEqualTo "") exitWith {
    [
        "Aucune photo à remonter — capturez d’abord depuis l’app Photos d’ATAK.",
        "warn",
        6
    ] call comspec_overwatch_atak_athena_fnc_athena_setPanelFeedback;
};

if (_caption isEqualTo "") then {
    _caption = format ["Photo ATAK Enhanced — grille %1", mapGridPosition player];
};

private _device = "CTAB";
private _feedId = "";
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
    _device = "DRONE";
    private _netId = netId _drone;
    if (_netId isEqualTo "") then { _netId = str _drone; };
    _feedId = format ["drone:%1", _netId];
} else {
    private _hasHcam = ("ItemcTabHCam" in (items player + assignedItems player))
        || {((headgear player) in (missionNamespace getVariable ["cTab_helmetClass_has_HCam", []]))};
    if (_hasHcam) then {
        _device = "HELMET";
        _feedId = format ["helmet:%1", getPlayerUID player];
    };
};

[_path, _caption, _device, _feedId] call comspec_overwatch_connect_fnc_captureReconImage;
[
    "Photo envoyée vers Athena.",
    "ok",
    5
] call comspec_overwatch_atak_athena_fnc_athena_setPanelFeedback;
[] call comspec_overwatch_atak_athena_fnc_athena_updatePanel;
